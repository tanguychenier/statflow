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

namespace App\Sites\Domain\Port;

use App\Shared\Domain\ValueObject\Uuid;
use App\Sites\Domain\Model\Site;
use App\Sites\Domain\ValueObject\Hostname;
use App\Sites\Domain\ValueObject\TrackerKey;

/**
 * Driven port for site persistence. Implemented by a Doctrine adapter against
 * the PostgreSQL `sites`/`site_settings` tables. Only active (non soft-deleted)
 * rows are visible through the finders.
 */
interface SiteRepository
{
    public function save(Site $site): void;

    /**
     * Active site by id, or null when it does not exist / is soft-deleted.
     */
    public function findById(Uuid $id): ?Site;

    /**
     * @return list<Site>
     */
    public function findByTeam(Uuid $teamId): array;

    /**
     * Active site by its public tracker key, or null. Used by ingestion-side
     * resolution; honours the rotation grace period when implemented.
     */
    public function findByTrackerKey(TrackerKey $key): ?Site;

    /**
     * Whether another active site in $teamId already uses $domain.
     * $excludeSiteId is ignored from the check (used when renaming a site).
     */
    public function domainExistsInTeam(Uuid $teamId, Hostname $domain, ?Uuid $excludeSiteId = null): bool;

    /**
     * Whether any site already uses this tracker key (global uniqueness).
     */
    public function trackerKeyExists(TrackerKey $key): bool;

    public function list(SiteListCriteria $criteria): SiteList;
}
