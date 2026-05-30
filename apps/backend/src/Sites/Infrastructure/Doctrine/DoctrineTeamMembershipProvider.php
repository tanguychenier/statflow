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

namespace App\Sites\Infrastructure\Doctrine;

use App\Shared\Domain\ValueObject\Uuid;
use App\Sites\Domain\Model\TeamRole;
use App\Sites\Domain\Port\TeamMembershipProvider;
use Doctrine\DBAL\Connection;

/**
 * Reads team membership from the shared PostgreSQL `team_members` table via raw
 * DBAL, deliberately not through the Identity context's Doctrine entities: the
 * Sites context owns no model there and must stay decoupled (hexagonal/Deptrac).
 *
 * Only accepted memberships count (accepted_at IS NOT NULL); pending invitations
 * grant no access, per ADR-0009.
 */
final readonly class DoctrineTeamMembershipProvider implements TeamMembershipProvider
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function roleOf(Uuid $userId, Uuid $teamId): ?TeamRole
    {
        $role = $this->connection->fetchOne(
            <<<'SQL'
                SELECT role FROM team_members
                WHERE user_id = :userId AND team_id = :teamId AND accepted_at IS NOT NULL
                SQL,
            [
                'userId' => $userId->getValue(),
                'teamId' => $teamId->getValue(),
            ],
        );

        if (!is_string($role)) {
            return null;
        }

        return TeamRole::fromString($role);
    }

    public function accessibleTeamIds(Uuid $userId): array
    {
        $rows = $this->connection->fetchFirstColumn(
            <<<'SQL'
                SELECT team_id FROM team_members
                WHERE user_id = :userId AND accepted_at IS NOT NULL
                SQL,
            [
                'userId' => $userId->getValue(),
            ],
        );

        return array_map(
            static fn (mixed $id): Uuid => Uuid::fromString(is_scalar($id) ? (string) $id : ''),
            $rows,
        );
    }
}
