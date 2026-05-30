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

namespace App\Tests\Unit\Reporting\Fake;

use App\Reporting\Domain\Model\Alert;
use App\Reporting\Domain\Port\AlertRepository;
use App\Reporting\Domain\Port\ListCriteria;
use App\Reporting\Domain\Port\ResultPage;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * In-memory {@see AlertRepository} for application-layer tests.
 */
final class InMemoryAlertRepository implements AlertRepository
{
    /**
     * @var array<string, Alert>
     */
    private array $alerts = [];

    public function save(Alert $alert): void
    {
        $this->alerts[$alert->id()->getValue()] = $alert;
    }

    public function findById(Uuid $siteId, Uuid $id): ?Alert
    {
        $alert = $this->alerts[$id->getValue()] ?? null;

        if ($alert === null || $alert->isDeleted() || !$alert->siteId()->equals($siteId)) {
            return null;
        }

        return $alert;
    }

    public function listForSite(ListCriteria $criteria): ResultPage
    {
        $matches = array_values(array_filter(
            $this->alerts,
            static fn (Alert $a): bool => !$a->isDeleted() && $a->siteId()->equals($criteria->siteId),
        ));

        usort($matches, static fn (Alert $a, Alert $b): int => $b->createdAt() <=> $a->createdAt());

        $offset = $criteria->cursor !== null ? (int) $criteria->cursor : 0;
        $page = array_slice($matches, $offset, $criteria->limit);
        $hasMore = count($matches) > $offset + $criteria->limit;

        return new ResultPage($page, $hasMore ? (string) ($offset + $criteria->limit) : null);
    }

    public function findEnabledForSite(Uuid $siteId): array
    {
        return array_values(array_filter(
            $this->alerts,
            static fn (Alert $a): bool => !$a->isDeleted() && $a->isActive() && $a->siteId()->equals($siteId),
        ));
    }
}
