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

namespace App\Identity\Domain\Port;

use App\Identity\Domain\Model\Team;
use App\Identity\Domain\ValueObject\TeamSlug;
use App\Shared\Domain\ValueObject\Uuid;

interface TeamRepository
{
    public function save(Team $team): void;

    public function findById(Uuid $id): ?Team;

    public function slugExists(TeamSlug $slug): bool;

    /**
     * Active teams the user belongs to (accepted memberships only), newest first.
     *
     * @return list<Team>
     */
    public function findTeamsForUser(Uuid $userId): array;

    public function countActiveSites(Uuid $teamId): int;

    public function countMembers(Uuid $teamId): int;
}
