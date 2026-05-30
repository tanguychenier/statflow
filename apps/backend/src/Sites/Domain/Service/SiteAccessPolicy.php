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

namespace App\Sites\Domain\Service;

use App\Shared\Domain\ValueObject\Uuid;
use App\Sites\Domain\Exception\PermissionDeniedException;
use App\Sites\Domain\Exception\SiteNotFoundException;
use App\Sites\Domain\Model\Site;
use App\Sites\Domain\Model\TeamRole;
use App\Sites\Domain\Port\TeamMembershipProvider;

/**
 * Central authorization for the Sites context, expressed against team roles
 * (ADR-0009). Non-membership is reported as "not found" rather than "forbidden"
 * so the API never discloses the existence of a site in another team
 * (error-catalog `not-found`); insufficient role yields `permission-denied`.
 */
final readonly class SiteAccessPolicy
{
    public function __construct(
        private TeamMembershipProvider $memberships,
    ) {
    }

    /**
     * Any accepted member of the team may read the site. A non-member gets a
     * not-found, masking the resource entirely.
     */
    public function assertCanView(Uuid $userId, Site $site): void
    {
        $this->requireRole($userId, $site);
    }

    public function assertCanManage(Uuid $userId, Site $site): void
    {
        $role = $this->requireRole($userId, $site);

        if (!$role->canManageSites()) {
            throw PermissionDeniedException::requires('sites:manage');
        }
    }

    public function assertCanAdminister(Uuid $userId, Site $site): void
    {
        $role = $this->requireRole($userId, $site);

        if (!$role->canAdministerSites()) {
            throw PermissionDeniedException::requires('sites:administer');
        }
    }

    public function assertCanDelete(Uuid $userId, Site $site): void
    {
        $role = $this->requireRole($userId, $site);

        if (!$role->canDeleteSites()) {
            throw PermissionDeniedException::requires('sites:delete');
        }
    }

    /**
     * Assert the user may create sites in $teamId. Used before a Site aggregate
     * exists, so non-membership maps to permission-denied (the team id is
     * caller-supplied, not a hidden resource).
     */
    public function assertCanCreateInTeam(Uuid $userId, Uuid $teamId): void
    {
        $role = $this->memberships->roleOf($userId, $teamId);

        if ($role === null) {
            throw PermissionDeniedException::requires('team:membership');
        }

        if (!$role->canManageSites()) {
            throw PermissionDeniedException::requires('sites:manage');
        }
    }

    private function requireRole(Uuid $userId, Site $site): TeamRole
    {
        $role = $this->memberships->roleOf($userId, $site->teamId());

        if ($role === null) {
            throw SiteNotFoundException::withId($site->id());
        }

        return $role;
    }
}
