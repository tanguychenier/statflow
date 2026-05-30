<?php

declare(strict_types=1);

/*
 * This file is part of Statflow.
 *
 * (c) Tanguy Chénier <tanguychenier@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Reporting\Application\Handler;

use App\Reporting\Domain\Model\Export;
use App\Reporting\Domain\Model\ExportStatus;
use App\Reporting\Domain\Port\AnalyticsQueryGateway;
use App\Reporting\Domain\Port\Clock;
use App\Reporting\Domain\Port\ExportArtifactStorage;
use App\Reporting\Domain\Port\ExportRepository;
use App\Reporting\Domain\Port\ReportMailer;
use App\Reporting\Domain\Service\ExportRowSerializer;
use App\Reporting\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\Uuid;
use DateInterval;
use Throwable;

/**
 * Generates a pending export off the request thread: marks it processing, runs
 * the saved query against Analytics, serialises the rows, stores the artifact,
 * and marks the job completed (or failed, with a message). Optionally notifies
 * the requester by email when an SMTP transport is configured.
 *
 * This is a plain use-case driven by id; the Messenger consumer in the
 * Infrastructure layer unwraps its job message and delegates here, keeping the
 * Application layer free of transport concerns. It is idempotent: an export
 * already processing or in a terminal state is skipped, so a redelivered job
 * never regenerates or corrupts a finished export.
 */
final readonly class ProcessExportHandler
{
    /**
     * Download artifacts stay valid for one hour (OpenAPI Export.expires_at).
     */
    public const DOWNLOAD_TTL = 'PT1H';

    public function __construct(
        private ExportRepository $exports,
        private AnalyticsQueryGateway $analytics,
        private ExportArtifactStorage $storage,
        private ExportRowSerializer $serializer,
        private ReportMailer $mailer,
        private Clock $clock,
    ) {
    }

    public function process(Uuid $exportId): void
    {
        $export = $this->exports->findByIdUnscoped($exportId);

        if ($export === null
            || $export->status()->isTerminal()
            || $export->status() === ExportStatus::Processing
        ) {
            return;
        }

        $export->markProcessing();
        $this->exports->save($export);

        try {
            $this->generate($export);
        } catch (Throwable $exception) {
            $export->markFailed($exception->getMessage(), $this->clock->now());
            $this->exports->save($export);

            return;
        }

        $this->notify($export);
    }

    private function generate(Export $export): void
    {
        $rows = $this->analytics->fetchRows(
            $export->siteId(),
            $this->reportTypeOf($export),
            $export->query(),
        );

        $contents = $this->serializer->serialize($export->format(), $rows);

        $artifactKey = $this->storage->store(
            $export->id()->getValue(),
            $export->format()->fileExtension(),
            $contents,
        );

        $now = $this->clock->now();
        $expiresAt = $now->add(new DateInterval(self::DOWNLOAD_TTL));

        $export->markCompleted(
            rowCount: count($rows),
            fileSizeBytes: strlen($contents),
            artifactKey: $artifactKey,
            expiresAt: $expiresAt,
            completedAt: $now,
        );

        $this->exports->save($export);
    }

    private function notify(Export $export): void
    {
        $recipient = $export->notifyEmail();
        if ($recipient === null || !$this->mailer->isConfigured()) {
            return;
        }

        $this->sendReadyEmail($recipient, $export);
    }

    private function sendReadyEmail(EmailAddress $recipient, Export $export): void
    {
        $subject = sprintf('Your %s export is ready', strtoupper($export->format()->value));
        $body = sprintf(
            'Your export (%s) completed with %d rows.',
            $export->id()->getValue(),
            $export->rowCount() ?? 0,
        );

        try {
            $this->mailer->send($recipient, $subject, $body, $body);
        } catch (Throwable) {
            // A notification failure must not fail an otherwise-successful export.
        }
    }

    /**
     * The query family stored alongside the export, defaulting to a tabular
     * breakdown when the request did not pin one.
     */
    private function reportTypeOf(Export $export): string
    {
        $type = $export->query()['report_type'] ?? null;

        return is_string($type) && $type !== '' ? $type : 'breakdown';
    }
}
