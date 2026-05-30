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

use App\Reporting\Domain\Exception\InvalidAlertException;
use App\Reporting\Domain\ValueObject\NotificationChannelList;
use App\Reporting\Domain\ValueObject\ReportName;
use App\Reporting\Domain\ValueObject\Threshold;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

/**
 * A threshold rule that fires a notification when a site metric crosses a
 * boundary — the aggregate root for alerts (OpenAPI `Alert`).
 *
 * Enforces the metric/condition invariants (a percentage-change condition needs
 * a comparison period; an absolute one forbids it) and records the last-fired
 * time so the evaluator can throttle. Filters are stored opaque, mirroring the
 * segment JSONB format and replayed by the Analytics evaluator. Persistence
 * mapping is declared in the Infrastructure layer (ADR-0004).
 */
class Alert
{
    private readonly string $id;

    private readonly string $siteId;

    private string $name;

    private readonly ?string $createdBy;

    private readonly string $metric;

    private string $condition;

    private string $threshold;

    private readonly ?string $comparisonPeriod;

    /**
     * @var list<array<string, string>>
     */
    private array $notificationChannels;

    private bool $isActive;

    private ?DateTimeImmutable $lastTriggeredAt = null;

    private DateTimeImmutable $updatedAt;

    private ?DateTimeImmutable $deletedAt = null;

    /**
     * @param list<array<string, mixed>> $filters
     */
    private function __construct(
        Uuid $id,
        Uuid $siteId,
        ReportName $name,
        AlertMetric $metric,
        AlertCondition $condition,
        Threshold $threshold,
        ?ComparisonPeriod $comparisonPeriod,
        private readonly array $filters,
        NotificationChannelList $channels,
        ?Uuid $createdBy,
        private readonly DateTimeImmutable $createdAt,
    ) {
        self::assertComparisonPeriodConsistency($condition, $comparisonPeriod);

        $this->id = $id->getValue();
        $this->siteId = $siteId->getValue();
        $this->name = $name->value();
        $this->metric = $metric->value;
        $this->condition = $condition->value;
        $this->threshold = self::encodeThreshold($threshold);
        $this->comparisonPeriod = $comparisonPeriod?->value;
        $this->notificationChannels = $channels->toArrayList();
        $this->isActive = true;
        $this->createdBy = $createdBy?->getValue();
        $this->updatedAt = $this->createdAt;
    }

    /**
     * @param list<array<string, mixed>> $filters
     */
    public static function create(
        Uuid $id,
        Uuid $siteId,
        ReportName $name,
        AlertMetric $metric,
        AlertCondition $condition,
        Threshold $threshold,
        ?ComparisonPeriod $comparisonPeriod,
        array $filters,
        NotificationChannelList $channels,
        ?Uuid $createdBy,
        DateTimeImmutable $now,
    ): self {
        return new self(
            $id,
            $siteId,
            $name,
            $metric,
            $condition,
            $threshold,
            $comparisonPeriod,
            $filters,
            $channels,
            $createdBy,
            $now,
        );
    }

    public function rename(ReportName $name, DateTimeImmutable $now): void
    {
        $this->name = $name->value();
        $this->touch($now);
    }

    public function changeCondition(AlertCondition $condition, Threshold $threshold, DateTimeImmutable $now): void
    {
        $period = $this->comparisonPeriod !== null ? ComparisonPeriod::from($this->comparisonPeriod) : null;
        self::assertComparisonPeriodConsistency($condition, $period);

        $this->condition = $condition->value;
        $this->threshold = self::encodeThreshold($threshold);
        $this->touch($now);
    }

    public function changeNotificationChannels(NotificationChannelList $channels, DateTimeImmutable $now): void
    {
        $this->notificationChannels = $channels->toArrayList();
        $this->touch($now);
    }

    public function activate(DateTimeImmutable $now): void
    {
        if ($this->isActive) {
            return;
        }

        $this->isActive = true;
        $this->touch($now);
    }

    public function deactivate(DateTimeImmutable $now): void
    {
        if (!$this->isActive) {
            return;
        }

        $this->isActive = false;
        $this->touch($now);
    }

    public function markTriggered(DateTimeImmutable $triggeredAt): void
    {
        $this->lastTriggeredAt = $triggeredAt;
        $this->updatedAt = $triggeredAt;
    }

    /**
     * Whether $observed breaches the rule. For percentage-change conditions the
     * caller passes the already-computed percentage delta.
     */
    public function isBreachedBy(float $observed): bool
    {
        return $this->alertCondition()->isBreached($observed, $this->thresholdValue());
    }

    public function softDelete(DateTimeImmutable $now): void
    {
        if ($this->deletedAt !== null) {
            return;
        }

        $this->deletedAt = $now;
        $this->isActive = false;
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

    public function name(): ReportName
    {
        return ReportName::fromString($this->name);
    }

    public function metric(): AlertMetric
    {
        return AlertMetric::from($this->metric);
    }

    public function alertCondition(): AlertCondition
    {
        return AlertCondition::from($this->condition);
    }

    public function thresholdValue(): float
    {
        return (float) $this->threshold;
    }

    public function comparisonPeriod(): ?ComparisonPeriod
    {
        return $this->comparisonPeriod !== null ? ComparisonPeriod::from($this->comparisonPeriod) : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function filters(): array
    {
        return $this->filters;
    }

    public function notificationChannels(): NotificationChannelList
    {
        return NotificationChannelList::fromArrayList($this->notificationChannels);
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function createdBy(): ?Uuid
    {
        return $this->createdBy !== null ? Uuid::fromString($this->createdBy) : null;
    }

    public function lastTriggeredAt(): ?DateTimeImmutable
    {
        return $this->lastTriggeredAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private static function assertComparisonPeriodConsistency(
        AlertCondition $condition,
        ?ComparisonPeriod $period,
    ): void {
        if ($condition->requiresComparisonPeriod() && $period === null) {
            throw InvalidAlertException::comparisonPeriodRequired();
        }

        if (!$condition->requiresComparisonPeriod() && $period !== null) {
            throw InvalidAlertException::comparisonPeriodNotAllowed();
        }
    }

    private static function encodeThreshold(Threshold $threshold): string
    {
        return number_format($threshold->value(), Threshold::SCALE, '.', '');
    }

    private function touch(DateTimeImmutable $now): void
    {
        $this->updatedAt = $now;
    }
}
