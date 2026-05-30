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

namespace App\Identity\Application\Service;

use App\Identity\Domain\Exception\PermissionDeniedException;
use App\Identity\Domain\Exception\TeamNotFoundException;
use App\Identity\Domain\Model\Team;
use App\Identity\Domain\Model\TeamMembership;
use App\Identity\Domain\Port\TeamMembershipRepository;
use App\Identity\Domain\Port\TeamRepository;
use App\Identity\Domain\ValueObject\TeamRole;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Resolves an actor's relationship to a team and enforces role requirements at
 * the application boundary. Returning a 404 (TeamNotFound) when the actor is not
 * a member avoids leaking team existence (error catalog: not-found semantics).
 */
final readonly class TeamAccessGuard
{
    public function __construct(
        private TeamRepository $teams,
        private TeamMembershipRepository $memberships,
    ) {
    }

    /**
     * Load the team or fail with 404. Used before any membership check so that
     * non-existent and inaccessible teams are indistinguishable.
     */
    public function requireTeam(Uuid $teamId): Team
    {
        $team = $this->teams->findById($teamId);

        if ($team === null || $team->isDeleted()) {
            throw TeamNotFoundException::withId($teamId);
        }

        return $team;
    }

    /**
     * The actor must be an accepted member; pending invitees are treated as
     * non-members. Hides existence with a 404 rather than a 403.
     */
    public function requireMembership(Uuid $actorId, Uuid $teamId): TeamMembership
    {
        $membership = $this->memberships->findByTeamAndUser($teamId, $actorId);

        if ($membership === null || $membership->isPending()) {
            throw TeamNotFoundException::withId($teamId);
        }

        return $membership;
    }

    public function requireRole(Uuid $actorId, Uuid $teamId, TeamRole $minimum, string $capability): TeamMembership
    {
        $membership = $this->requireMembership($actorId, $teamId);

        if (!$membership->role()->isAtLeast($minimum)) {
            throw PermissionDeniedException::requires($capability);
        }

        return $membership;
    }
}
