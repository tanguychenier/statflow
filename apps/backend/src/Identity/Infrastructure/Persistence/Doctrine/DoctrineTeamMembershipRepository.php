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

use App\Identity\Domain\Model\TeamMembership;
use App\Identity\Domain\Port\TeamMembershipRepository;
use App\Identity\Domain\ValueObject\TeamRole;
use App\Shared\Domain\ValueObject\Uuid;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Doctrine ORM adapter for {@see TeamMembershipRepository}.
 */
final readonly class DoctrineTeamMembershipRepository implements TeamMembershipRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function save(TeamMembership $membership): void
    {
        $this->entityManager->persist($membership);
        $this->entityManager->flush();
    }

    public function remove(TeamMembership $membership): void
    {
        $this->entityManager->remove($membership);
        $this->entityManager->flush();
    }

    public function findById(Uuid $id): ?TeamMembership
    {
        return $this->entityManager->find(TeamMembership::class, $id->getValue());
    }

    public function findByTeamAndUser(Uuid $teamId, Uuid $userId): ?TeamMembership
    {
        /** @var TeamMembership|null $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(TeamMembership::class, 'm')
            ->where('m.teamId = :teamId')
            ->andWhere('m.userId = :userId')
            ->setParameter('teamId', $teamId->getValue())
            ->setParameter('userId', $userId->getValue())
            ->getQuery()
            ->getOneOrNullResult();

        return $result;
    }

    public function findByTeam(Uuid $teamId): array
    {
        /** @var list<TeamMembership> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(TeamMembership::class, 'm')
            ->where('m.teamId = :teamId')
            ->orderBy('m.invitedAt', 'ASC')
            ->addOrderBy('m.id', 'ASC')
            ->setParameter('teamId', $teamId->getValue())
            ->getQuery()
            ->getResult();

        return $rows;
    }

    public function findByUser(Uuid $userId): array
    {
        /** @var list<TeamMembership> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(TeamMembership::class, 'm')
            ->where('m.userId = :userId')
            ->setParameter('userId', $userId->getValue())
            ->getQuery()
            ->getResult();

        return $rows;
    }

    public function countOwners(Uuid $teamId): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(m.id)')
            ->from(TeamMembership::class, 'm')
            ->where('m.teamId = :teamId')
            ->andWhere('m.role = :owner')
            ->setParameter('teamId', $teamId->getValue())
            ->setParameter('owner', TeamRole::Owner->value)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
