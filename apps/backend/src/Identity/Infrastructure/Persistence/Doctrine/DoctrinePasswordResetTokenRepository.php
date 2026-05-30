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

use App\Identity\Domain\Model\PasswordResetToken;
use App\Identity\Domain\Port\PasswordResetTokenRepository;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Doctrine ORM adapter for {@see PasswordResetTokenRepository}. Invalidation
 * marks every outstanding token for a user as consumed (rather than deleting),
 * preserving an auditable history of issued resets.
 */
final readonly class DoctrinePasswordResetTokenRepository implements PasswordResetTokenRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function save(PasswordResetToken $token): void
    {
        $this->entityManager->persist($token);
        $this->entityManager->flush();
    }

    public function findByTokenHash(string $tokenHash): ?PasswordResetToken
    {
        /** @var PasswordResetToken|null $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from(PasswordResetToken::class, 't')
            ->where('t.tokenHash = :hash')
            ->setParameter('hash', $tokenHash)
            ->getQuery()
            ->getOneOrNullResult();

        return $result;
    }

    public function invalidateAllForUser(Uuid $userId): void
    {
        $this->entityManager->createQueryBuilder()
            ->update(PasswordResetToken::class, 't')
            ->set('t.consumedAt', ':now')
            ->where('t.userId = :userId')
            ->andWhere('t.consumedAt IS NULL')
            ->setParameter('now', new DateTimeImmutable())
            ->setParameter('userId', $userId->getValue())
            ->getQuery()
            ->execute();
    }
}
