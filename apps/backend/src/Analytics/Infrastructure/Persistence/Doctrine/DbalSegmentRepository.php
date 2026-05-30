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

namespace App\Analytics\Infrastructure\Persistence\Doctrine;

use App\Analytics\Domain\Model\Segment;
use App\Analytics\Domain\Port\SegmentRepositoryPort;
use App\Analytics\Domain\ValueObject\Filter;
use App\Analytics\Domain\ValueObject\FilterSet;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

/**
 * Persists saved segments to `postgres.segments`. Uses DBAL with a JSONB
 * `filters` column rather than an ORM entity: segments are a thin read/write
 * projection for the analytics query layer and carry no rich behaviour that
 * would justify an aggregate-mapped entity.
 *
 * Soft-deleted rows (`deleted_at IS NOT NULL`) are excluded everywhere, matching
 * the partial index in postgres-schema.sql §9.
 */
final readonly class DbalSegmentRepository implements SegmentRepositoryPort
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function findBySite(Uuid $siteId): array
    {
        /** @var list<array<string, int|float|string|null>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id::text AS id, site_id::text AS site_id, name, filters, '
            . 'COALESCE(filter_combination, \'and\') AS filter_combination, '
            . 'created_by::text AS created_by, created_at '
            . 'FROM segments WHERE site_id = :site AND deleted_at IS NULL ORDER BY created_at DESC',
            [
                'site' => (string) $siteId,
            ],
        );

        return array_map($this->hydrate(...), $rows);
    }

    public function find(Uuid $siteId, Uuid $segmentId): ?Segment
    {
        /** @var array<string, int|float|string|null>|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT id::text AS id, site_id::text AS site_id, name, filters, '
            . 'COALESCE(filter_combination, \'and\') AS filter_combination, '
            . 'created_by::text AS created_by, created_at '
            . 'FROM segments WHERE site_id = :site AND id = :id AND deleted_at IS NULL',
            [
                'site' => (string) $siteId,
                'id' => (string) $segmentId,
            ],
        );

        return $row === false ? null : $this->hydrate($row);
    }

    public function save(Segment $segment): void
    {
        $this->connection->insert('segments', [
            'id' => (string) $segment->id,
            'site_id' => (string) $segment->siteId,
            'name' => $segment->name,
            'filters' => json_encode($this->encodeFilters($segment->filterSet), JSON_THROW_ON_ERROR),
            'filter_combination' => $segment->filterSet->combination->value,
            'created_by' => $segment->createdBy,
            'created_at' => $segment->createdAt->format('Y-m-d H:i:sP'),
            'updated_at' => $segment->createdAt->format('Y-m-d H:i:sP'),
        ]);
    }

    public function delete(Uuid $siteId, Uuid $segmentId): void
    {
        $this->connection->executeStatement(
            'UPDATE segments SET deleted_at = NOW() WHERE site_id = :site AND id = :id AND deleted_at IS NULL',
            [
                'site' => (string) $siteId,
                'id' => (string) $segmentId,
            ],
        );
    }

    /**
     * @param array<string, int|float|string|null> $row
     */
    private function hydrate(array $row): Segment
    {
        return Segment::reconstitute(
            Uuid::fromString((string) $row['id']),
            Uuid::fromString((string) $row['site_id']),
            (string) $row['name'],
            $this->decodeFilters($row['filters'], (string) $row['filter_combination']),
            $row['created_by'] === null ? null : (string) $row['created_by'],
            new DateTimeImmutable((string) $row['created_at']),
        );
    }

    /**
     * @return list<array{property: string, operator: string, value: mixed}>
     */
    private function encodeFilters(FilterSet $filterSet): array
    {
        return array_map(
            static fn (Filter $filter): array => [
                'property' => $filter->dimension->value,
                'operator' => $filter->operator->value,
                'value' => $filter->value,
            ],
            $filterSet->filters,
        );
    }

    private function decodeFilters(mixed $rawFilters, string $combination): FilterSet
    {
        $decoded = is_string($rawFilters)
            ? json_decode($rawFilters, true, 512, JSON_THROW_ON_ERROR)
            : $rawFilters;

        if (!is_array($decoded) || $decoded === []) {
            return FilterSet::empty();
        }

        /** @var list<array<string, mixed>> $filters */
        $filters = array_values(array_filter($decoded, is_array(...)));

        return FilterSet::fromArray($filters, $combination);
    }
}
