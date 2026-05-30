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
use App\Sites\Domain\Model\Site;
use App\Sites\Domain\Port\SiteList;
use App\Sites\Domain\Port\SiteListCriteria;
use App\Sites\Domain\Port\SiteRepository;
use App\Sites\Domain\ValueObject\Hostname;
use App\Sites\Domain\ValueObject\TrackerKey;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * Doctrine ORM adapter for {@see SiteRepository}.
 *
 * Listing uses keyset (cursor) pagination ordered by (created_at DESC, id DESC),
 * which is stable under inserts and matches the API's `next_cursor` contract.
 * Only active rows (deleted_at IS NULL) are exposed by every finder.
 */
final readonly class DoctrineSiteRepository implements SiteRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function save(Site $site): void
    {
        $this->entityManager->persist($site);
        $this->entityManager->flush();
    }

    public function findById(Uuid $id): ?Site
    {
        /** @var Site|null $result */
        $result = $this->activeQuery()
            ->andWhere('s.id = :id')
            ->setParameter('id', $id->getValue())
            ->getQuery()
            ->getOneOrNullResult();

        return $result;
    }

    public function findByTeam(Uuid $teamId): array
    {
        /** @var list<Site> $result */
        $result = $this->activeQuery()
            ->andWhere('s.teamId = :teamId')
            ->setParameter('teamId', $teamId->getValue())
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function findByTrackerKey(TrackerKey $key): ?Site
    {
        /** @var Site|null $result */
        $result = $this->activeQuery()
            ->andWhere('s.trackerKey = :key')
            ->setParameter('key', $key->value())
            ->getQuery()
            ->getOneOrNullResult();

        return $result;
    }

    public function domainExistsInTeam(Uuid $teamId, Hostname $domain, ?Uuid $excludeSiteId = null): bool
    {
        $qb = $this->activeQuery()
            ->select('COUNT(s.id)')
            ->andWhere('s.teamId = :teamId')
            ->andWhere('LOWER(s.domain) = :domain')
            ->setParameter('teamId', $teamId->getValue())
            ->setParameter('domain', $domain->value());

        if ($excludeSiteId !== null) {
            $qb->andWhere('s.id != :excludeId')
                ->setParameter('excludeId', $excludeSiteId->getValue());
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    public function trackerKeyExists(TrackerKey $key): bool
    {
        $count = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(s.id)')
            ->from(Site::class, 's')
            ->where('s.trackerKey = :key')
            ->setParameter('key', $key->value())
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    public function list(SiteListCriteria $criteria): SiteList
    {
        $qb = $this->activeQuery()
            ->andWhere('s.teamId IN (:teamIds)')
            ->setParameter('teamIds', array_map(static fn (Uuid $id): string => $id->getValue(), $criteria->accessibleTeamIds))
            ->orderBy('s.createdAt', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->setMaxResults($criteria->limit + 1);

        if ($criteria->teamFilter !== null) {
            $qb->andWhere('s.teamId = :teamFilter')
                ->setParameter('teamFilter', $criteria->teamFilter->getValue());
        }

        if ($criteria->search !== null) {
            $qb->andWhere('(LOWER(s.name) LIKE :search OR LOWER(s.domain) LIKE :search)')
                ->setParameter('search', '%' . strtolower($criteria->search) . '%');
        }

        $this->applyCursor($qb, $criteria->cursor);

        /** @var list<Site> $rows */
        $rows = $qb->getQuery()->getResult();

        $hasMore = count($rows) > $criteria->limit;
        if ($hasMore) {
            $rows = array_slice($rows, 0, $criteria->limit);
        }

        $nextCursor = $hasMore && $rows !== [] ? $this->encodeCursor($rows[count($rows) - 1]) : null;

        return new SiteList($rows, $nextCursor);
    }

    private function activeQuery(): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(Site::class, 's')
            ->where('s.deletedAt IS NULL');
    }

    private function applyCursor(QueryBuilder $qb, ?string $cursor): void
    {
        if ($cursor === null) {
            return;
        }

        $decoded = $this->decodeCursor($cursor);
        if ($decoded === null) {
            return;
        }

        [$createdAt, $id] = $decoded;

        $qb->andWhere('(s.createdAt < :cursorCreatedAt OR (s.createdAt = :cursorCreatedAt AND s.id < :cursorId))')
            ->setParameter('cursorCreatedAt', $createdAt)
            ->setParameter('cursorId', $id);
    }

    private function encodeCursor(Site $site): string
    {
        return base64_encode($site->createdAt()->format('Y-m-d H:i:s.u') . '|' . $site->id()->getValue());
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function decodeCursor(string $cursor): ?array
    {
        $decoded = base64_decode($cursor, true);
        if ($decoded === false || !str_contains($decoded, '|')) {
            return null;
        }

        [$createdAt, $id] = explode('|', $decoded, 2);

        if ($createdAt === '' || $id === '') {
            return null;
        }

        return [$createdAt, $id];
    }
}
