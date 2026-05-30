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
use App\Sites\Domain\Model\TeamRole;
use App\Sites\Domain\Port\TeamMembershipProvider;

/**
 * In-memory team membership for unit tests. Membership is keyed by
 * "userId|teamId"; absence means "not a member".
 */
final class InMemoryTeamMembershipProvider implements TeamMembershipProvider
{
    /**
     * @var array<string, TeamRole>
     */
    private array $roles = [];

    public function grant(string $userId, string $teamId, TeamRole $role): void
    {
        $this->roles[$userId . '|' . $teamId] = $role;
    }

    public function roleOf(Uuid $userId, Uuid $teamId): ?TeamRole
    {
        return $this->roles[$userId->getValue() . '|' . $teamId->getValue()] ?? null;
    }

    public function accessibleTeamIds(Uuid $userId): array
    {
        $teamIds = [];

        foreach (array_keys($this->roles) as $key) {
            [$user, $team] = explode('|', $key, 2);
            if ($user === $userId->getValue()) {
                $teamIds[] = Uuid::fromString($team);
            }
        }

        return $teamIds;
    }
}
