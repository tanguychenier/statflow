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

namespace App\Reporting\Domain\Port;

use App\Shared\Domain\ValueObject\Uuid;

/**
 * Keyset (cursor) listing filter shared by every site-scoped reporting resource.
 *
 * Results are always restricted to a single site; the cursor is an opaque token
 * whose shape is owned by the repository adapter. Matches the API's
 * `PaginatedResponse` contract (forward-only navigation).
 */
final readonly class ListCriteria
{
    public const DEFAULT_LIMIT = 25;

    public const MAX_LIMIT = 100;

    private function __construct(
        public Uuid $siteId,
        public ?string $cursor,
        public int $limit,
    ) {
    }

    public static function create(Uuid $siteId, ?string $cursor = null, int $limit = self::DEFAULT_LIMIT): self
    {
        return new self($siteId, $cursor, self::clampLimit($limit));
    }

    public static function clampLimit(int $limit): int
    {
        return max(1, min($limit, self::MAX_LIMIT));
    }
}
