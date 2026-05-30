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

namespace App\Reporting\Domain\Port;

use App\Reporting\Domain\Model\TeamRole;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Driven port resolving a user's effective role on a site.
 *
 * Sites and team membership are owned by other contexts; Reporting must not read
 * their domain models (hexagonal/Deptrac). The implementing adapter joins the
 * shared `sites` and `team_members` tables (or queries via the query bus) and
 * returns the role, leaving the authorization decision to {@see ReportingAccessPolicy}.
 */
interface SiteAccessProvider
{
    /**
     * The accepted role of $userId on the team that owns $siteId, or null when
     * the site does not exist, is deleted, or the user is not an accepted member
     * of its team. The two cases are deliberately indistinguishable to callers
     * so the API never discloses a site's existence across teams.
     */
    public function roleOnSite(Uuid $userId, Uuid $siteId): ?TeamRole;
}
