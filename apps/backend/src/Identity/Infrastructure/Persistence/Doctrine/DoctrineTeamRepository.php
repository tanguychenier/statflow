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

namespace App\Identity\Infrastructure\Persistence\Doctrine;

use App\Identity\Domain\Model\Team;
use App\Identity\Domain\Model\TeamMembership;
use App\Identity\Domain\Port\TeamRepository;
use App\Identity\Domain\ValueObject\TeamSlug;
use App\Shared\Domain\ValueObject\Uuid;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Doctrine ORM adapter for {@see TeamRepository}. Active-site counting reaches the
 * `sites` table (owned by the Sites context) through raw DBAL rather than that
 * context's entities, so the two contexts stay decoupled (hexagonal/Deptrac).
 */
final readonly class DoctrineTeamRepository implements TeamRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Connection $connection,
    ) {
    }

    public function save(Team $team): void
    {
        $this->entityManager->persist($team);
        $this->entityManager->flush();
    }

    public function findById(Uuid $id): ?Team
    {
        /** @var Team|null $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from(Team::class, 't')
            ->where('t.id = :id')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('id', $id->getValue())
            ->getQuery()
            ->getOneOrNullResult();

        return $result;
    }

    public function slugExists(TeamSlug $slug): bool
    {
        $count = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from(Team::class, 't')
            ->where('t.slug = :slug')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('slug', $slug->getValue())
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    public function findTeamsForUser(Uuid $userId): array
    {
        /** @var list<Team> $teams */
        $teams = $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from(Team::class, 't')
            ->innerJoin(TeamMembership::class, 'm', 'WITH', 'm.teamId = t.id')
            ->where('m.userId = :userId')
            ->andWhere('m.acceptedAt IS NOT NULL')
            ->andWhere('t.deletedAt IS NULL')
            ->orderBy('t.createdAt', 'DESC')
            ->addOrderBy('t.id', 'DESC')
            ->setParameter('userId', $userId->getValue())
            ->getQuery()
            ->getResult();

        return $teams;
    }

    public function countActiveSites(Uuid $teamId): int
    {
        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM sites WHERE team_id = :teamId AND deleted_at IS NULL',
            [
                'teamId' => $teamId->getValue(),
            ],
        );

        return is_numeric($count) ? (int) $count : 0;
    }

    public function countMembers(Uuid $teamId): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(m.id)')
            ->from(TeamMembership::class, 'm')
            ->where('m.teamId = :teamId')
            ->setParameter('teamId', $teamId->getValue())
            ->getQuery()
            ->getSingleScalarResult();
    }
}
