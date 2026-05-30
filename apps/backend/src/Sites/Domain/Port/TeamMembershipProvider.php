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
use App\Sites\Domain\Model\TeamRole;

/**
 * Driven port exposing a user's role inside a team.
 *
 * Team membership is owned by the Identity context; the Sites context must not
 * read its tables directly (hexagonal + Deptrac). The implementing adapter
 * lives in Sites\Infrastructure and queries the shared PostgreSQL schema, but
 * the contract is owned here so authorization logic stays in this context.
 */
interface TeamMembershipProvider
{
    /**
     * The accepted role of $userId within $teamId, or null when the user is not
     * an accepted member of that team (pending invitations count as non-members).
     */
    public function roleOf(Uuid $userId, Uuid $teamId): ?TeamRole;

    /**
     * Ids of every team the user is an accepted member of. Drives the listing
     * read side: sites outside these teams are never returned.
     *
     * @return list<Uuid>
     */
    public function accessibleTeamIds(Uuid $userId): array;
}
