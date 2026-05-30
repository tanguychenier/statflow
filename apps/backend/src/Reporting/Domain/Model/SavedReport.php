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

use App\Reporting\Domain\ValueObject\QueryDefinition;
use App\Reporting\Domain\ValueObject\ReportDescription;
use App\Reporting\Domain\ValueObject\ReportName;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

/**
 * A named, replayable analytics query configuration owned by a site — the
 * aggregate root for saved reports (OpenAPI `SavedReport`).
 *
 * Persisted fields are primitives so the persistence adapter hydrates without
 * value-object factories; rich behaviour is exposed through value-object
 * accessors. The stored `definition` JSON is opaque to this context (validated
 * only as a bounded JSON object) and is replayed against the Analytics context.
 * Persistence mapping is declared in the Infrastructure layer (ADR-0004).
 */
class SavedReport
{
    private readonly string $id;

    private readonly string $siteId;

    private readonly string $name;

    private readonly ?string $description;

    private readonly string $reportType;

    /**
     * @var array<string, mixed>
     */
    private readonly array $definition;

    private readonly ?string $createdBy;

    private DateTimeImmutable $updatedAt;

    private ?DateTimeImmutable $deletedAt = null;

    private function __construct(
        Uuid $id,
        Uuid $siteId,
        ReportName $name,
        ?ReportDescription $description,
        ReportType $reportType,
        QueryDefinition $definition,
        ?Uuid $createdBy,
        private readonly DateTimeImmutable $createdAt,
    ) {
        $this->id = $id->getValue();
        $this->siteId = $siteId->getValue();
        $this->name = $name->value();
        $this->description = $description?->value();
        $this->reportType = $reportType->value;
        $this->definition = $definition->toArray();
        $this->createdBy = $createdBy?->getValue();
        $this->updatedAt = $this->createdAt;
    }

    public static function create(
        Uuid $id,
        Uuid $siteId,
        ReportName $name,
        ?ReportDescription $description,
        ReportType $reportType,
        QueryDefinition $definition,
        ?Uuid $createdBy,
        DateTimeImmutable $now,
    ): self {
        return new self($id, $siteId, $name, $description, $reportType, $definition, $createdBy, $now);
    }

    public function softDelete(DateTimeImmutable $now): void
    {
        if ($this->deletedAt !== null) {
            return;
        }

        $this->deletedAt = $now;
        $this->updatedAt = $now;
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

    public function description(): ?ReportDescription
    {
        return $this->description !== null ? ReportDescription::fromNullableString($this->description) : null;
    }

    public function reportType(): ReportType
    {
        return ReportType::from($this->reportType);
    }

    public function definition(): QueryDefinition
    {
        return QueryDefinition::fromArray($this->definition);
    }

    public function createdBy(): ?Uuid
    {
        return $this->createdBy !== null ? Uuid::fromString($this->createdBy) : null;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
