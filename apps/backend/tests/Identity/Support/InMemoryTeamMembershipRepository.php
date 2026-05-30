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

namespace App\Tests\Identity\Support;

use App\Identity\Domain\Model\TeamMembership;
use App\Identity\Domain\Port\TeamMembershipRepository;
use App\Identity\Domain\ValueObject\TeamRole;
use App\Shared\Domain\ValueObject\Uuid;

final class InMemoryTeamMembershipRepository implements TeamMembershipRepository
{
    /**
     * @var array<string, TeamMembership>
     */
    private array $memberships = [];

    public function save(TeamMembership $membership): void
    {
        $this->memberships[$membership->id()->getValue()] = $membership;
    }

    public function remove(TeamMembership $membership): void
    {
        unset($this->memberships[$membership->id()->getValue()]);
    }

    public function findById(Uuid $id): ?TeamMembership
    {
        return $this->memberships[$id->getValue()] ?? null;
    }

    public function findByTeamAndUser(Uuid $teamId, Uuid $userId): ?TeamMembership
    {
        foreach ($this->memberships as $membership) {
            if ($membership->teamId()->equals($teamId) && $membership->userId()->equals($userId)) {
                return $membership;
            }
        }

        return null;
    }

    public function findByTeam(Uuid $teamId): array
    {
        return array_values(array_filter(
            $this->memberships,
            static fn (TeamMembership $m): bool => $m->teamId()->equals($teamId),
        ));
    }

    public function findByUser(Uuid $userId): array
    {
        return array_values(array_filter(
            $this->memberships,
            static fn (TeamMembership $m): bool => $m->userId()->equals($userId),
        ));
    }

    public function countOwners(Uuid $teamId): int
    {
        return count(array_filter(
            $this->memberships,
            static fn (TeamMembership $m): bool => $m->teamId()->equals($teamId) && $m->role() === TeamRole::Owner,
        ));
    }
}
