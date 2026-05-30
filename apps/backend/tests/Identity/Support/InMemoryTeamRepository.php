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

use App\Identity\Domain\Model\Team;
use App\Identity\Domain\Port\TeamRepository;
use App\Identity\Domain\ValueObject\TeamSlug;
use App\Shared\Domain\ValueObject\Uuid;

final class InMemoryTeamRepository implements TeamRepository
{
    /**
     * @var array<string, Team>
     */
    private array $teams = [];

    /**
     * @var array<string, int> team id => active site count
     */
    private array $siteCounts = [];

    private readonly InMemoryTeamMembershipRepository $memberships;

    public function __construct(?InMemoryTeamMembershipRepository $memberships = null)
    {
        $this->memberships = $memberships ?? new InMemoryTeamMembershipRepository();
    }

    public function save(Team $team): void
    {
        $this->teams[$team->id()->getValue()] = $team;
    }

    public function findById(Uuid $id): ?Team
    {
        $team = $this->teams[$id->getValue()] ?? null;

        return $team !== null && !$team->isDeleted() ? $team : null;
    }

    public function slugExists(TeamSlug $slug): bool
    {
        foreach ($this->teams as $team) {
            if (!$team->isDeleted() && $team->slug()->equals($slug)) {
                return true;
            }
        }

        return false;
    }

    public function findTeamsForUser(Uuid $userId): array
    {
        $result = [];
        foreach ($this->memberships->findByUser($userId) as $membership) {
            if ($membership->isPending()) {
                continue;
            }

            $team = $this->findById($membership->teamId());
            if ($team !== null) {
                $result[] = $team;
            }
        }

        return $result;
    }

    public function countActiveSites(Uuid $teamId): int
    {
        return $this->siteCounts[$teamId->getValue()] ?? 0;
    }

    public function countMembers(Uuid $teamId): int
    {
        return count($this->memberships->findByTeam($teamId));
    }

    public function setActiveSiteCount(Uuid $teamId, int $count): void
    {
        $this->siteCounts[$teamId->getValue()] = $count;
    }
}
