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

namespace App\Reporting\Domain\Service;

use App\Reporting\Domain\Exception\PermissionDeniedException;
use App\Reporting\Domain\Exception\SiteNotFoundException;
use App\Reporting\Domain\Model\TeamRole;
use App\Reporting\Domain\Port\SiteAccessProvider;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Central authorization for the Reporting context, expressed against team roles
 * (ADR-0009).
 *
 * A user with no role on the target site gets a not-found (the site is masked
 * entirely, never disclosing cross-team existence); an accepted member with an
 * insufficient role gets a permission-denied. Viewers may read; editors and
 * above may mutate and request exports.
 */
final readonly class ReportingAccessPolicy
{
    public function __construct(
        private SiteAccessProvider $siteAccess,
    ) {
    }

    /**
     * Assert the user may read reporting resources for the site, returning the
     * resolved role for any further checks the caller wants to make.
     */
    public function assertCanView(Uuid $userId, Uuid $siteId): TeamRole
    {
        return $this->requireRole($userId, $siteId);
    }

    /**
     * Assert the user may create/edit/delete reporting resources or request
     * exports for the site.
     */
    public function assertCanManage(Uuid $userId, Uuid $siteId): TeamRole
    {
        $role = $this->requireRole($userId, $siteId);

        if (!$role->canManageReporting()) {
            throw PermissionDeniedException::requires('reports:write');
        }

        return $role;
    }

    private function requireRole(Uuid $userId, Uuid $siteId): TeamRole
    {
        $role = $this->siteAccess->roleOnSite($userId, $siteId);

        if ($role === null) {
            throw SiteNotFoundException::withId($siteId);
        }

        return $role;
    }
}
