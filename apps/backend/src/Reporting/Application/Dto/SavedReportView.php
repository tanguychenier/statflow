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

use App\Reporting\Domain\Model\SavedReport;

/**
 * Read model for a saved report, shaped to the OpenAPI `SavedReport` schema.
 * Built from the aggregate so controllers never touch domain entities directly.
 */
final readonly class SavedReportView
{
    /**
     * @param array<string, mixed> $query
     */
    private function __construct(
        public string $id,
        public string $siteId,
        public string $name,
        public ?string $description,
        public string $reportType,
        public array $query,
        public ?string $createdBy,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function fromReport(SavedReport $report): self
    {
        return new self(
            id: $report->id()->getValue(),
            siteId: $report->siteId()->getValue(),
            name: $report->name()->value(),
            description: $report->description()?->value(),
            reportType: $report->reportType()->value,
            query: $report->definition()->toArray(),
            createdBy: $report->createdBy()?->getValue(),
            createdAt: $report->createdAt()->format(DATE_ATOM),
            updatedAt: $report->updatedAt()->format(DATE_ATOM),
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
            'description' => $this->description,
            'report_type' => $this->reportType,
            'query' => $this->query === [] ? new \stdClass() : $this->query,
            'created_by' => $this->createdBy,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
