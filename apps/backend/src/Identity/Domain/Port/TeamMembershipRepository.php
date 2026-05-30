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

use App\Identity\Domain\Model\TeamMembership;
use App\Shared\Domain\ValueObject\Uuid;

interface TeamMembershipRepository
{
    public function save(TeamMembership $membership): void;

    public function remove(TeamMembership $membership): void;

    public function findById(Uuid $id): ?TeamMembership;

    public function findByTeamAndUser(Uuid $teamId, Uuid $userId): ?TeamMembership;

    /**
     * @return list<TeamMembership>
     */
    public function findByTeam(Uuid $teamId): array;

    /**
     * @return list<TeamMembership>
     */
    public function findByUser(Uuid $userId): array;

    public function countOwners(Uuid $teamId): int;
}
