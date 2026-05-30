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

namespace App\Reporting\Infrastructure\Doctrine;

use App\Reporting\Domain\Model\TeamRole;
use App\Reporting\Domain\Port\SiteAccessProvider;
use App\Shared\Domain\ValueObject\Uuid;
use Doctrine\DBAL\Connection;

/**
 * Resolves a user's role on a site by joining the shared PostgreSQL `sites` and
 * `team_members` tables via raw DBAL.
 *
 * Deliberately not through the Sites or Identity Doctrine entities: Reporting
 * owns no model there and must stay decoupled (hexagonal/Deptrac). Only active
 * sites (deleted_at IS NULL) and accepted memberships (accepted_at IS NOT NULL)
 * grant access, per ADR-0009.
 */
final readonly class DoctrineSiteAccessProvider implements SiteAccessProvider
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function roleOnSite(Uuid $userId, Uuid $siteId): ?TeamRole
    {
        $role = $this->connection->fetchOne(
            <<<'SQL'
                SELECT tm.role
                FROM sites s
                INNER JOIN team_members tm ON tm.team_id = s.team_id
                WHERE s.id = :siteId
                    AND s.deleted_at IS NULL
                    AND tm.user_id = :userId
                    AND tm.accepted_at IS NOT NULL
                SQL,
            [
                'siteId' => $siteId->getValue(),
                'userId' => $userId->getValue(),
            ],
        );

        if (!is_string($role)) {
            return null;
        }

        return TeamRole::fromString($role);
    }
}
