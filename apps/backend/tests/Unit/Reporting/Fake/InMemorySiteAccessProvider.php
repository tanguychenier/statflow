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

use App\Reporting\Domain\Model\TeamRole;
use App\Reporting\Domain\Port\SiteAccessProvider;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * In-memory {@see SiteAccessProvider} for application-layer tests. A user has a
 * role on a site only when explicitly granted; otherwise access is denied,
 * reproducing the masking behaviour of the Doctrine adapter.
 */
final class InMemorySiteAccessProvider implements SiteAccessProvider
{
    /**
     * @var array<string, TeamRole> keyed by "userId|siteId"
     */
    private array $grants = [];

    public function grant(string $userId, string $siteId, TeamRole $role): void
    {
        $this->grants[$userId . '|' . $siteId] = $role;
    }

    public function roleOnSite(Uuid $userId, Uuid $siteId): ?TeamRole
    {
        return $this->grants[$userId->getValue() . '|' . $siteId->getValue()] ?? null;
    }
}
