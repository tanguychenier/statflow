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

/**
 * Read-side filter for listing sites.
 *
 * Results are always restricted to the teams the caller belongs to; the
 * optional team filter narrows further. Pagination is keyset (cursor) based to
 * match the API's `PaginatedResponse`. The cursor is an opaque token whose
 * shape is owned by the repository adapter.
 */
final readonly class SiteListCriteria
{
    public const DEFAULT_LIMIT = 25;

    public const MAX_LIMIT = 100;

    /**
     * @param list<Uuid>  $accessibleTeamIds teams the caller may read sites from
     * @param Uuid|null   $teamFilter        optional single-team narrowing filter
     * @param string|null $search            fuzzy match on site name/domain
     */
    private function __construct(
        public array $accessibleTeamIds,
        public ?Uuid $teamFilter,
        public ?string $search,
        public ?string $cursor,
        public int $limit,
    ) {
    }

    /**
     * @param list<Uuid> $accessibleTeamIds
     */
    public static function create(
        array $accessibleTeamIds,
        ?Uuid $teamFilter = null,
        ?string $search = null,
        ?string $cursor = null,
        int $limit = self::DEFAULT_LIMIT,
    ): self {
        $search = $search !== null ? trim($search) : null;
        if ($search === '') {
            $search = null;
        }

        $limit = max(1, min($limit, self::MAX_LIMIT));

        return new self($accessibleTeamIds, $teamFilter, $search, $cursor, $limit);
    }
}
