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

namespace App\Reporting\Application\Dto;

use App\Reporting\Domain\Model\ScheduledReport;

/**
 * Read model for a scheduled report, shaped to the OpenAPI `ScheduledReport`
 * schema. Timestamps are rendered as RFC 3339; absent ones serialise to null.
 */
final readonly class ScheduledReportView
{
    /**
     * @param list<string> $recipients
     */
    private function __construct(
        public string $id,
        public string $siteId,
        public ?string $savedReportId,
        public string $name,
        public array $recipients,
        public string $scheduleCron,
        public string $timezone,
        public bool $isActive,
        public ?string $lastSentAt,
        public ?string $nextSendAt,
        public string $createdAt,
    ) {
    }

    public static function fromReport(ScheduledReport $report): self
    {
        return new self(
            id: $report->id()->getValue(),
            siteId: $report->siteId()->getValue(),
            savedReportId: $report->savedReportId()?->getValue(),
            name: $report->name()->value(),
            recipients: $report->recipients()->toStrings(),
            scheduleCron: $report->cron()->value(),
            timezone: $report->reportTimezone()->value(),
            isActive: $report->isActive(),
            lastSentAt: $report->lastSentAt()?->format(DATE_ATOM),
            nextSendAt: $report->nextSendAt()?->format(DATE_ATOM),
            createdAt: $report->createdAt()->format(DATE_ATOM),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'site_id' => $this->siteId,
            'saved_report_id' => $this->savedReportId,
            'name' => $this->name,
            'recipients' => $this->recipients,
            'schedule_cron' => $this->scheduleCron,
            'timezone' => $this->timezone,
            'is_active' => $this->isActive,
            'last_sent_at' => $this->lastSentAt,
            'next_send_at' => $this->nextSendAt,
            'created_at' => $this->createdAt,
        ];
    }
}
