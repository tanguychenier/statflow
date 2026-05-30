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

namespace App\Tests\Unit\Sites\Fake;

use App\Shared\Domain\ValueObject\Uuid;
use App\Sites\Domain\Model\Site;
use App\Sites\Domain\Port\SiteList;
use App\Sites\Domain\Port\SiteListCriteria;
use App\Sites\Domain\Port\SiteRepository;
use App\Sites\Domain\ValueObject\Hostname;
use App\Sites\Domain\ValueObject\TrackerKey;

/**
 * In-memory site repository for application-layer unit tests. Reproduces the
 * visibility rules of the Doctrine adapter (active rows only) and supports a
 * simple offset cursor so listing/pagination logic can be exercised.
 */
final class InMemorySiteRepository implements SiteRepository
{
    /**
     * @var array<string, Site>
     */
    private array $sites = [];

    /**
     * @var list<string> tracker keys that should report as already taken
     */
    private array $reservedKeys = [];

    public function reserveKey(string $key): void
    {
        $this->reservedKeys[] = $key;
    }

    public function save(Site $site): void
    {
        $this->sites[$site->id()->getValue()] = $site;
    }

    public function findById(Uuid $id): ?Site
    {
        $site = $this->sites[$id->getValue()] ?? null;

        return $site !== null && !$site->isDeleted() ? $site : null;
    }

    public function findByTeam(Uuid $teamId): array
    {
        return array_values(array_filter(
            $this->active(),
            static fn (Site $s): bool => $s->teamId()->equals($teamId),
        ));
    }

    public function findByTrackerKey(TrackerKey $key): ?Site
    {
        foreach ($this->active() as $site) {
            if ($site->trackerKey()->equals($key)) {
                return $site;
            }
        }

        return null;
    }

    public function domainExistsInTeam(Uuid $teamId, Hostname $domain, ?Uuid $excludeSiteId = null): bool
    {
        foreach ($this->active() as $site) {
            if ($excludeSiteId !== null && $site->id()->equals($excludeSiteId)) {
                continue;
            }

            if ($site->teamId()->equals($teamId) && $site->domain()->equals($domain)) {
                return true;
            }
        }

        return false;
    }

    public function trackerKeyExists(TrackerKey $key): bool
    {
        if (in_array($key->value(), $this->reservedKeys, true)) {
            return true;
        }

        foreach ($this->sites as $site) {
            if ($site->trackerKey()->equals($key)) {
                return true;
            }
        }

        return false;
    }

    public function list(SiteListCriteria $criteria): SiteList
    {
        $accessible = array_map(static fn (Uuid $id): string => $id->getValue(), $criteria->accessibleTeamIds);

        $matches = array_values(array_filter(
            $this->active(),
            function (Site $s) use ($accessible, $criteria): bool {
                if (!in_array($s->teamId()->getValue(), $accessible, true)) {
                    return false;
                }

                if ($criteria->teamFilter !== null && !$s->teamId()->equals($criteria->teamFilter)) {
                    return false;
                }

                if ($criteria->search !== null) {
                    $needle = strtolower($criteria->search);
                    $haystack = strtolower($s->name()->value() . ' ' . $s->domain()->value());
                    if (!str_contains($haystack, $needle)) {
                        return false;
                    }
                }

                return true;
            },
        ));

        usort($matches, static fn (Site $a, Site $b): int => $b->createdAt() <=> $a->createdAt());

        $offset = $criteria->cursor !== null ? (int) $criteria->cursor : 0;
        $page = array_slice($matches, $offset, $criteria->limit);
        $hasMore = count($matches) > $offset + $criteria->limit;

        return new SiteList($page, $hasMore ? (string) ($offset + $criteria->limit) : null);
    }

    /**
     * @return list<Site>
     */
    private function active(): array
    {
        return array_values(array_filter(
            $this->sites,
            static fn (Site $s): bool => !$s->isDeleted(),
        ));
    }
}
