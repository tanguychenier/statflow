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

use App\Identity\Domain\Model\ApiKey;
use App\Identity\Domain\Port\ApiKeyRepository;
use App\Shared\Domain\ValueObject\Uuid;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Doctrine ORM adapter for {@see ApiKeyRepository}. Lookups by hash power
 * Bearer-token authentication; the unique index on key_hash guarantees at most
 * one match.
 */
final readonly class DoctrineApiKeyRepository implements ApiKeyRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function save(ApiKey $apiKey): void
    {
        $this->entityManager->persist($apiKey);
        $this->entityManager->flush();
    }

    public function findById(Uuid $id): ?ApiKey
    {
        return $this->entityManager->find(ApiKey::class, $id->getValue());
    }

    public function findByHash(string $keyHash): ?ApiKey
    {
        /** @var ApiKey|null $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('k')
            ->from(ApiKey::class, 'k')
            ->where('k.keyHash = :hash')
            ->setParameter('hash', $keyHash)
            ->getQuery()
            ->getOneOrNullResult();

        return $result;
    }

    public function findActiveByTeam(Uuid $teamId): array
    {
        /** @var list<ApiKey> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('k')
            ->from(ApiKey::class, 'k')
            ->where('k.teamId = :teamId')
            ->andWhere('k.revokedAt IS NULL')
            ->orderBy('k.createdAt', 'DESC')
            ->addOrderBy('k.id', 'DESC')
            ->setParameter('teamId', $teamId->getValue())
            ->getQuery()
            ->getResult();

        return $rows;
    }
}
