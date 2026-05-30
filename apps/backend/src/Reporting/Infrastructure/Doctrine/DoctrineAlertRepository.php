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

use App\Reporting\Domain\Model\Alert;
use App\Reporting\Domain\Port\AlertRepository;
use App\Reporting\Domain\Port\ListCriteria;
use App\Reporting\Domain\Port\ResultPage;
use App\Shared\Domain\ValueObject\Uuid;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * Doctrine ORM adapter for {@see AlertRepository}. Keyset paginated by
 * (created_at DESC, id DESC); the evaluation finder returns every enabled,
 * active alert for a site.
 */
final readonly class DoctrineAlertRepository implements AlertRepository
{
    use CursorCodec;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function save(Alert $alert): void
    {
        $this->entityManager->persist($alert);
        $this->entityManager->flush();
    }

    public function findById(Uuid $siteId, Uuid $id): ?Alert
    {
        /** @var Alert|null $result */
        $result = $this->activeQuery()
            ->andWhere('a.id = :id')
            ->andWhere('a.siteId = :siteId')
            ->setParameter('id', $id->getValue())
            ->setParameter('siteId', $siteId->getValue())
            ->getQuery()
            ->getOneOrNullResult();

        return $result;
    }

    public function listForSite(ListCriteria $criteria): ResultPage
    {
        $qb = $this->activeQuery()
            ->andWhere('a.siteId = :siteId')
            ->setParameter('siteId', $criteria->siteId->getValue())
            ->orderBy('a.createdAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->setMaxResults($criteria->limit + 1);

        $cursor = $this->decodeCursor($criteria->cursor);
        if ($cursor !== null) {
            [$createdAt, $id] = $cursor;
            $qb->andWhere('(a.createdAt < :cAt OR (a.createdAt = :cAt AND a.id < :cId))')
                ->setParameter('cAt', $createdAt)
                ->setParameter('cId', $id);
        }

        /** @var list<Alert> $rows */
        $rows = $qb->getQuery()->getResult();

        $hasMore = count($rows) > $criteria->limit;
        if ($hasMore) {
            $rows = array_slice($rows, 0, $criteria->limit);
        }

        $next = null;
        if ($hasMore && $rows !== []) {
            $last = $rows[count($rows) - 1];
            $next = $this->encodeCursor($last->createdAt(), $last->id()->getValue());
        }

        return new ResultPage($rows, $next);
    }

    public function findEnabledForSite(Uuid $siteId): array
    {
        /** @var list<Alert> $rows */
        $rows = $this->activeQuery()
            ->andWhere('a.siteId = :siteId')
            ->andWhere('a.isActive = true')
            ->setParameter('siteId', $siteId->getValue())
            ->orderBy('a.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $rows;
    }

    private function activeQuery(): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()
            ->select('a')
            ->from(Alert::class, 'a')
            ->where('a.deletedAt IS NULL');
    }
}
