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

use App\Identity\Domain\Model\User;
use App\Identity\Domain\Port\UserRepository;
use App\Identity\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\Uuid;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Doctrine ORM adapter for {@see UserRepository}. Every finder excludes
 * soft-deleted rows (deleted_at IS NULL) so a freed email can be reused while the
 * historical row is preserved.
 */
final readonly class DoctrineUserRepository implements UserRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function save(User $user): void
    {
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    public function findById(Uuid $id): ?User
    {
        /** @var User|null $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->where('u.id = :id')
            ->andWhere('u.deletedAt IS NULL')
            ->setParameter('id', $id->getValue())
            ->getQuery()
            ->getOneOrNullResult();

        return $result;
    }

    public function findByEmail(EmailAddress $email): ?User
    {
        /** @var User|null $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->where('LOWER(u.email) = :email')
            ->andWhere('u.deletedAt IS NULL')
            ->setParameter('email', $email->getValue())
            ->getQuery()
            ->getOneOrNullResult();

        return $result;
    }

    public function existsByEmail(EmailAddress $email): bool
    {
        $count = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from(User::class, 'u')
            ->where('LOWER(u.email) = :email')
            ->andWhere('u.deletedAt IS NULL')
            ->setParameter('email', $email->getValue())
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
