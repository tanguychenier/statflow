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

namespace App\Reporting\Domain\Model;

use App\Reporting\Domain\ValueObject\CronExpression;
use App\Reporting\Domain\ValueObject\EmailRecipientList;
use App\Reporting\Domain\ValueObject\ReportName;
use App\Reporting\Domain\ValueObject\ReportTimezone;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

/**
 * A recurring email delivery of a (optionally saved) report — the aggregate root
 * for scheduled reports (OpenAPI `ScheduledReport`).
 *
 * Owns its cron schedule and recipient list, and tracks the send lifecycle:
 * `nextSendAt` is recomputed from the cron whenever the schedule changes or a
 * run completes, so the dispatcher only ever scans for due rows. Sending is
 * opt-in and degrades gracefully when no SMTP transport is configured.
 * Persistence mapping is declared in the Infrastructure layer (ADR-0004).
 */
class ScheduledReport
{
    private readonly string $id;

    private readonly string $siteId;

    private readonly ?string $savedReportId;

    private string $name;

    /**
     * @var list<string>
     */
    private array $recipients;

    private string $scheduleCron;

    private string $timezone;

    private bool $isActive;

    private readonly ?string $createdBy;

    private ?DateTimeImmutable $lastSentAt = null;

    private ?DateTimeImmutable $nextSendAt = null;

    private DateTimeImmutable $updatedAt;

    private ?DateTimeImmutable $deletedAt = null;

    private function __construct(
        Uuid $id,
        Uuid $siteId,
        ?Uuid $savedReportId,
        ReportName $name,
        EmailRecipientList $recipients,
        CronExpression $schedule,
        ReportTimezone $timezone,
        ?Uuid $createdBy,
        private readonly DateTimeImmutable $createdAt,
    ) {
        $this->id = $id->getValue();
        $this->siteId = $siteId->getValue();
        $this->savedReportId = $savedReportId?->getValue();
        $this->name = $name->value();
        $this->recipients = $recipients->toStrings();
        $this->scheduleCron = $schedule->value();
        $this->timezone = $timezone->value();
        $this->isActive = true;
        $this->createdBy = $createdBy?->getValue();
        $this->updatedAt = $this->createdAt;
        $this->nextSendAt = $schedule->nextRunAfter($this->createdAt, $timezone->toDateTimeZone());
    }

    public static function schedule(
        Uuid $id,
        Uuid $siteId,
        ?Uuid $savedReportId,
        ReportName $name,
        EmailRecipientList $recipients,
        CronExpression $schedule,
        ReportTimezone $timezone,
        ?Uuid $createdBy,
        DateTimeImmutable $now,
    ): self {
        return new self($id, $siteId, $savedReportId, $name, $recipients, $schedule, $timezone, $createdBy, $now);
    }

    public function rename(ReportName $name, DateTimeImmutable $now): void
    {
        $this->name = $name->value();
        $this->touch($now);
    }

    public function changeRecipients(EmailRecipientList $recipients, DateTimeImmutable $now): void
    {
        $this->recipients = $recipients->toStrings();
        $this->touch($now);
    }

    /**
     * Reconfigure the cadence. The next send time is recomputed from $now so an
     * edit never replays a missed slot or skips ahead.
     */
    public function reschedule(CronExpression $schedule, ReportTimezone $timezone, DateTimeImmutable $now): void
    {
        $this->scheduleCron = $schedule->value();
        $this->timezone = $timezone->value();
        $this->nextSendAt = $schedule->nextRunAfter($now, $timezone->toDateTimeZone());
        $this->touch($now);
    }

    public function activate(DateTimeImmutable $now): void
    {
        if ($this->isActive) {
            return;
        }

        $this->isActive = true;
        $this->nextSendAt = $this->cron()->nextRunAfter($now, $this->reportTimezone()->toDateTimeZone());
        $this->touch($now);
    }

    public function deactivate(DateTimeImmutable $now): void
    {
        if (!$this->isActive) {
            return;
        }

        $this->isActive = false;
        $this->nextSendAt = null;
        $this->touch($now);
    }

    /**
     * Record a completed send and advance the schedule to the following slot.
     */
    public function markSent(DateTimeImmutable $sentAt): void
    {
        $this->lastSentAt = $sentAt;
        $this->nextSendAt = $this->cron()->nextRunAfter($sentAt, $this->reportTimezone()->toDateTimeZone());
        $this->updatedAt = $sentAt;
    }

    public function isDue(DateTimeImmutable $now): bool
    {
        return $this->isActive
            && $this->deletedAt === null
            && $this->nextSendAt !== null
            && $this->nextSendAt <= $now;
    }

    public function softDelete(DateTimeImmutable $now): void
    {
        if ($this->deletedAt !== null) {
            return;
        }

        $this->deletedAt = $now;
        $this->isActive = false;
        $this->nextSendAt = null;
        $this->touch($now);
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function id(): Uuid
    {
        return Uuid::fromString($this->id);
    }

    public function siteId(): Uuid
    {
        return Uuid::fromString($this->siteId);
    }

    public function savedReportId(): ?Uuid
    {
        return $this->savedReportId !== null ? Uuid::fromString($this->savedReportId) : null;
    }

    public function name(): ReportName
    {
        return ReportName::fromString($this->name);
    }

    public function recipients(): EmailRecipientList
    {
        return EmailRecipientList::fromStrings($this->recipients);
    }

    public function cron(): CronExpression
    {
        return CronExpression::fromString($this->scheduleCron);
    }

    public function reportTimezone(): ReportTimezone
    {
        return ReportTimezone::fromString($this->timezone);
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function createdBy(): ?Uuid
    {
        return $this->createdBy !== null ? Uuid::fromString($this->createdBy) : null;
    }

    public function lastSentAt(): ?DateTimeImmutable
    {
        return $this->lastSentAt;
    }

    public function nextSendAt(): ?DateTimeImmutable
    {
        return $this->nextSendAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function touch(DateTimeImmutable $now): void
    {
        $this->updatedAt = $now;
    }
}
