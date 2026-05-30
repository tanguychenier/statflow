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

use App\Analytics\Domain\Model\Funnel;
use App\Analytics\Domain\Model\FunnelStep;
use App\Analytics\Domain\Model\FunnelTriggerType;
use App\Analytics\Domain\Port\FunnelRepositoryPort;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

/**
 * Reads persisted funnels and their ordered steps from
 * `postgres.funnels` / `funnel_steps`. The Analytics context only needs to read
 * funnel definitions to resolve labels and step triggers for the funnel query;
 * funnel CRUD belongs to another context, so this is a DBAL read projection.
 */
final readonly class DbalFunnelRepository implements FunnelRepositoryPort
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function find(Uuid $siteId, Uuid $funnelId): ?Funnel
    {
        /** @var array<string, int|float|string|null>|false $funnelRow */
        $funnelRow = $this->connection->fetchAssociative(
            'SELECT id::text AS id, site_id::text AS site_id, name, created_at, updated_at '
            . 'FROM funnels WHERE id = :id AND site_id = :site AND deleted_at IS NULL',
            [
                'id' => (string) $funnelId,
                'site' => (string) $siteId,
            ],
        );

        if ($funnelRow === false) {
            return null;
        }

        /** @var list<array<string, int|float|string|null>> $stepRows */
        $stepRows = $this->connection->fetchAllAssociative(
            'SELECT step_index, name, trigger_type, url_pattern, event_name '
            . 'FROM funnel_steps WHERE funnel_id = :id ORDER BY step_index ASC',
            [
                'id' => (string) $funnelId,
            ],
        );

        return Funnel::reconstitute(
            Uuid::fromString((string) $funnelRow['id']),
            Uuid::fromString((string) $funnelRow['site_id']),
            (string) $funnelRow['name'],
            array_map($this->hydrateStep(...), $stepRows),
            new DateTimeImmutable((string) $funnelRow['created_at']),
            new DateTimeImmutable((string) $funnelRow['updated_at']),
        );
    }

    /**
     * @param array<string, int|float|string|null> $row
     */
    private function hydrateStep(array $row): FunnelStep
    {
        $index = (int) $row['step_index'];
        $label = (string) $row['name'];
        $trigger = FunnelTriggerType::from((string) $row['trigger_type']);

        return $trigger === FunnelTriggerType::Pageview
            ? FunnelStep::pageview($index, $label, (string) ($row['url_pattern'] ?? ''))
            : FunnelStep::event($index, $label, (string) ($row['event_name'] ?? ''));
    }
}
