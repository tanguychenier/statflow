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

use App\Reporting\Domain\Model\ScheduledReport;
use App\Reporting\Domain\Port\Clock;
use App\Reporting\Domain\Port\ReportMailer;
use App\Reporting\Domain\Port\ScheduledReportRepository;
use App\Reporting\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\Uuid;
use Throwable;

/**
 * Delivers a single due scheduled report to its recipients and advances the
 * schedule to the next slot.
 *
 * Driven by id so it can run as an isolated, retriable unit of work: the sweep
 * enqueues one job per due schedule, and this use-case re-checks dueness under
 * the current clock to absorb duplicate deliveries. When no SMTP transport is
 * configured the run is skipped but the schedule is still advanced, so an
 * unconfigured install never accumulates an ever-growing backlog of due rows.
 */
final readonly class SendScheduledReportHandler
{
    public function __construct(
        private ScheduledReportRepository $schedules,
        private ReportMailer $mailer,
        private Clock $clock,
    ) {
    }

    public function send(Uuid $siteId, Uuid $scheduleId): void
    {
        $schedule = $this->schedules->findById($siteId, $scheduleId);
        if ($schedule === null) {
            return;
        }

        $now = $this->clock->now();
        if (!$schedule->isDue($now)) {
            return;
        }

        if ($this->mailer->isConfigured()) {
            $this->deliver($schedule);
        }

        $schedule->markSent($now);
        $this->schedules->save($schedule);
    }

    private function deliver(ScheduledReport $schedule): void
    {
        $subject = sprintf('Statflow report: %s', $schedule->name()->value());
        $body = sprintf('Your scheduled report "%s" is attached.', $schedule->name()->value());

        foreach ($schedule->recipients()->all() as $recipient) {
            $this->deliverOne($recipient, $subject, $body);
        }
    }

    private function deliverOne(EmailAddress $recipient, string $subject, string $body): void
    {
        try {
            $this->mailer->send($recipient, $subject, $body, $body);
        } catch (Throwable) {
            // A single recipient's transient failure must not abort the run nor
            // hold back the schedule; the next slot will retry the full list.
        }
    }
}
