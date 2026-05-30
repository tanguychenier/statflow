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

use App\Reporting\Domain\Model\Alert;

/**
 * Read model for an alert, shaped to the OpenAPI `Alert` schema.
 */
final readonly class AlertView
{
    /**
     * @param list<array<string, mixed>>  $filters
     * @param list<array<string, string>> $notificationChannels
     */
    private function __construct(
        public string $id,
        public string $siteId,
        public string $name,
        public string $metric,
        public string $condition,
        public float $threshold,
        public ?string $comparisonPeriod,
        public array $filters,
        public array $notificationChannels,
        public bool $isActive,
        public ?string $lastTriggeredAt,
        public string $createdAt,
    ) {
    }

    public static function fromAlert(Alert $alert): self
    {
        return new self(
            id: $alert->id()->getValue(),
            siteId: $alert->siteId()->getValue(),
            name: $alert->name()->value(),
            metric: $alert->metric()->value,
            condition: $alert->alertCondition()->value,
            threshold: $alert->thresholdValue(),
            comparisonPeriod: $alert->comparisonPeriod()?->value,
            filters: $alert->filters(),
            notificationChannels: $alert->notificationChannels()->toArrayList(),
            isActive: $alert->isActive(),
            lastTriggeredAt: $alert->lastTriggeredAt()?->format(DATE_ATOM),
            createdAt: $alert->createdAt()->format(DATE_ATOM),
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
            'name' => $this->name,
            'metric' => $this->metric,
            'condition' => $this->condition,
            'threshold' => $this->threshold,
            'comparison_period' => $this->comparisonPeriod,
            'filters' => $this->filters,
            'notification_channels' => $this->notificationChannels,
            'is_active' => $this->isActive,
            'last_triggered_at' => $this->lastTriggeredAt,
            'created_at' => $this->createdAt,
        ];
    }
}
