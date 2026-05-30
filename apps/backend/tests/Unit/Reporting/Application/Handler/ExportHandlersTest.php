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

namespace App\Tests\Unit\Reporting\Application\Handler;

use App\Reporting\Application\Command\CreateExportCommand;
use App\Reporting\Application\Handler\CreateExportHandler;
use App\Reporting\Application\Handler\GetExportHandler;
use App\Reporting\Application\Handler\ProcessExportHandler;
use App\Reporting\Application\Query\GetExportQuery;
use App\Reporting\Domain\Exception\InvalidExportException;
use App\Reporting\Domain\Exception\PermissionDeniedException;
use App\Reporting\Domain\Exception\ReportNotFoundException;
use App\Reporting\Domain\Model\ExportStatus;
use App\Reporting\Domain\Model\TeamRole;
use App\Reporting\Domain\Service\ExportRowSerializer;
use App\Reporting\Domain\Service\ReportingAccessPolicy;
use App\Shared\Domain\ValueObject\Uuid;
use App\Tests\Unit\Reporting\Fake\FakeAnalyticsQueryGateway;
use App\Tests\Unit\Reporting\Fake\FrozenClock;
use App\Tests\Unit\Reporting\Fake\InMemoryExportArtifactStorage;
use App\Tests\Unit\Reporting\Fake\InMemoryExportRepository;
use App\Tests\Unit\Reporting\Fake\InMemorySiteAccessProvider;
use App\Tests\Unit\Reporting\Fake\RecordingExportJobDispatcher;
use App\Tests\Unit\Reporting\Fake\RecordingReportMailer;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreateExportHandler::class)]
#[CoversClass(GetExportHandler::class)]
#[CoversClass(ProcessExportHandler::class)]
final class ExportHandlersTest extends TestCase
{
    private const USER = '11111111-1111-4111-8111-111111111111';

    private const SITE = '22222222-2222-4222-8222-222222222222';

    private InMemoryExportRepository $repo;

    private InMemorySiteAccessProvider $access;

    private RecordingExportJobDispatcher $dispatcher;

    private InMemoryExportArtifactStorage $storage;

    private FakeAnalyticsQueryGateway $analytics;

    private RecordingReportMailer $mailer;

    private FrozenClock $clock;

    protected function setUp(): void
    {
        $this->repo = new InMemoryExportRepository();
        $this->access = new InMemorySiteAccessProvider();
        $this->dispatcher = new RecordingExportJobDispatcher();
        $this->storage = new InMemoryExportArtifactStorage();
        $this->analytics = new FakeAnalyticsQueryGateway();
        $this->mailer = new RecordingReportMailer();
        $this->clock = new FrozenClock(new DateTimeImmutable('2026-05-29T08:00:00', new DateTimeZone('UTC')));
    }

    #[Test]
    public function createEnqueuesPendingExport(): void
    {
        $this->access->grant(self::USER, self::SITE, TeamRole::Editor);

        $view = ($this->create())(new CreateExportCommand(
            self::USER,
            self::SITE,
            'csv',
            [
                'report_type' => 'breakdown',
                'property' => 'page',
            ],
            'me@b.com',
        ));

        self::assertSame('pending', $view->status);
        self::assertSame('csv', $view->format);
        self::assertCount(1, $this->dispatcher->dispatched);
        self::assertSame($view->id, $this->dispatcher->dispatched[0]);
    }

    #[Test]
    public function viewerCannotCreateExport(): void
    {
        $this->access->grant(self::USER, self::SITE, TeamRole::Viewer);

        $this->expectException(PermissionDeniedException::class);
        ($this->create())(new CreateExportCommand(self::USER, self::SITE, 'csv', [], null));
    }

    #[Test]
    public function invalidFormatRejected(): void
    {
        $this->access->grant(self::USER, self::SITE, TeamRole::Editor);

        $this->expectException(InvalidExportException::class);
        ($this->create())(new CreateExportCommand(self::USER, self::SITE, 'xlsx', [], null));
    }

    #[Test]
    public function processGeneratesArtifactAndNotifies(): void
    {
        $this->access->grant(self::USER, self::SITE, TeamRole::Editor);
        $this->analytics->withRows([[
            'page' => '/home',
            'views' => 10,
        ]]);

        $view = ($this->create())(new CreateExportCommand(self::USER, self::SITE, 'csv', [
            'report_type' => 'breakdown',
        ], 'me@b.com'));

        ($this->process())->process(Uuid::fromString($view->id));

        $export = $this->repo->findByIdUnscoped(Uuid::fromString($view->id));
        self::assertNotNull($export);
        self::assertSame(ExportStatus::Completed, $export->status());
        self::assertSame(1, $export->rowCount());
        self::assertGreaterThan(0, $export->fileSizeBytes());
        self::assertCount(1, $this->mailer->sent);
        self::assertSame('me@b.com', $this->mailer->sent[0]['to']);
        self::assertNotEmpty($this->storage->stored);
    }

    #[Test]
    public function processMarksFailedOnAnalyticsError(): void
    {
        $this->access->grant(self::USER, self::SITE, TeamRole::Editor);
        $this->analytics->failOnFetch();

        $view = ($this->create())(new CreateExportCommand(self::USER, self::SITE, 'csv', [], null));

        ($this->process())->process(Uuid::fromString($view->id));

        $export = $this->repo->findByIdUnscoped(Uuid::fromString($view->id));
        self::assertNotNull($export);
        self::assertSame(ExportStatus::Failed, $export->status());
        self::assertNotNull($export->errorMessage());
    }

    #[Test]
    public function processIsIdempotentForCompletedExport(): void
    {
        $this->access->grant(self::USER, self::SITE, TeamRole::Editor);
        $view = ($this->create())(new CreateExportCommand(self::USER, self::SITE, 'csv', [], null));

        ($this->process())->process(Uuid::fromString($view->id));
        $savesAfterFirst = $this->repo->saveCount;

        ($this->process())->process(Uuid::fromString($view->id));

        self::assertSame($savesAfterFirst, $this->repo->saveCount);
    }

    #[Test]
    public function processSkipsNotificationWhenMailerUnconfigured(): void
    {
        $this->access->grant(self::USER, self::SITE, TeamRole::Editor);
        $this->mailer->configured(false);
        $view = ($this->create())(new CreateExportCommand(self::USER, self::SITE, 'csv', [], 'me@b.com'));

        ($this->process())->process(Uuid::fromString($view->id));

        self::assertEmpty($this->mailer->sent);
        self::assertSame(ExportStatus::Completed, $this->repo->findByIdUnscoped(Uuid::fromString($view->id))?->status());
    }

    #[Test]
    public function getReturnsDownloadUrlWhenCompleted(): void
    {
        $this->access->grant(self::USER, self::SITE, TeamRole::Editor);
        $view = ($this->create())(new CreateExportCommand(self::USER, self::SITE, 'csv', [], null));
        ($this->process())->process(Uuid::fromString($view->id));

        $fetched = ($this->get())(new GetExportQuery(self::USER, self::SITE, $view->id));

        self::assertSame('completed', $fetched->status);
        self::assertNotNull($fetched->downloadUrl);
        self::assertNotNull($fetched->expiresAt);
    }

    #[Test]
    public function getRejectsUnknownExport(): void
    {
        $this->access->grant(self::USER, self::SITE, TeamRole::Viewer);

        $this->expectException(ReportNotFoundException::class);
        ($this->get())(new GetExportQuery(self::USER, self::SITE, Uuid::generate()->getValue()));
    }

    private function policy(): ReportingAccessPolicy
    {
        return new ReportingAccessPolicy($this->access);
    }

    private function create(): CreateExportHandler
    {
        return new CreateExportHandler($this->repo, $this->dispatcher, $this->policy(), $this->clock);
    }

    private function get(): GetExportHandler
    {
        return new GetExportHandler($this->repo, $this->storage, $this->policy());
    }

    private function process(): ProcessExportHandler
    {
        return new ProcessExportHandler(
            $this->repo,
            $this->analytics,
            $this->storage,
            new ExportRowSerializer(),
            $this->mailer,
            $this->clock,
        );
    }
}
