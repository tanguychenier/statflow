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

namespace App\Sites\Application\Handler;

use App\Shared\Domain\ValueObject\Uuid;
use App\Sites\Application\Dto\PaginatedSites;
use App\Sites\Application\Dto\SiteView;
use App\Sites\Application\Query\ListSitesQuery;
use App\Sites\Domain\Port\SiteListCriteria;
use App\Sites\Domain\Port\SiteRepository;
use App\Sites\Domain\Port\TeamMembershipProvider;

/**
 * Lists the sites visible to the authenticated user. Visibility is bounded by
 * the user's accepted team memberships; an explicit team filter outside that set
 * simply yields an empty page rather than an error (no existence disclosure).
 */
final readonly class ListSitesHandler
{
    public function __construct(
        private SiteRepository $sites,
        private TeamMembershipProvider $memberships,
    ) {
    }

    public function __invoke(ListSitesQuery $query): PaginatedSites
    {
        $userId = Uuid::fromString($query->actingUserId);
        $accessibleTeamIds = $this->memberships->accessibleTeamIds($userId);

        $teamFilter = $query->teamId !== null ? Uuid::fromString($query->teamId) : null;

        $effectiveLimit = max(1, min($query->limit, SiteListCriteria::MAX_LIMIT));

        if ($accessibleTeamIds === [] || !$this->filterIsAccessible($teamFilter, $accessibleTeamIds)) {
            return new PaginatedSites([], null, $effectiveLimit);
        }

        $criteria = SiteListCriteria::create(
            accessibleTeamIds: $accessibleTeamIds,
            teamFilter: $teamFilter,
            search: $query->search,
            cursor: $query->cursor,
            limit: $query->limit,
        );

        $page = $this->sites->list($criteria);

        return new PaginatedSites(
            sites: array_map(SiteView::fromSite(...), $page->sites),
            nextCursor: $page->nextCursor,
            limit: $criteria->limit,
        );
    }

    /**
     * @param list<Uuid> $accessibleTeamIds
     */
    private function filterIsAccessible(?Uuid $teamFilter, array $accessibleTeamIds): bool
    {
        if ($teamFilter === null) {
            return true;
        }

        foreach ($accessibleTeamIds as $teamId) {
            if ($teamId->equals($teamFilter)) {
                return true;
            }
        }

        return false;
    }
}
