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

use App\Reporting\Domain\Model\SavedReport;
use App\Reporting\Domain\Port\ListCriteria;
use App\Reporting\Domain\Port\ResultPage;
use App\Reporting\Domain\Port\SavedReportRepository;
use App\Shared\Domain\ValueObject\Uuid;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * Doctrine ORM adapter for {@see SavedReportRepository}.
 *
 * Listing uses keyset (cursor) pagination ordered by (created_at DESC, id DESC),
 * stable under inserts and matching the API's `next_cursor` contract. Only
 * active rows (deleted_at IS NULL) are exposed by every finder.
 */
final readonly class DoctrineSavedReportRepository implements SavedReportRepository
{
    use CursorCodec;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function save(SavedReport $report): void
    {
        $this->entityManager->persist($report);
        $this->entityManager->flush();
    }

    public function findById(Uuid $siteId, Uuid $id): ?SavedReport
    {
        /** @var SavedReport|null $result */
        $result = $this->activeQuery()
            ->andWhere('r.id = :id')
            ->andWhere('r.siteId = :siteId')
            ->setParameter('id', $id->getValue())
            ->setParameter('siteId', $siteId->getValue())
            ->getQuery()
            ->getOneOrNullResult();

        return $result;
    }

    public function listForSite(ListCriteria $criteria): ResultPage
    {
        $qb = $this->activeQuery()
            ->andWhere('r.siteId = :siteId')
            ->setParameter('siteId', $criteria->siteId->getValue())
            ->orderBy('r.createdAt', 'DESC')
            ->addOrderBy('r.id', 'DESC')
            ->setMaxResults($criteria->limit + 1);

        $cursor = $this->decodeCursor($criteria->cursor);
        if ($cursor !== null) {
            [$createdAt, $id] = $cursor;
            $qb->andWhere('(r.createdAt < :cAt OR (r.createdAt = :cAt AND r.id < :cId))')
                ->setParameter('cAt', $createdAt)
                ->setParameter('cId', $id);
        }

        /** @var list<SavedReport> $rows */
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

    private function activeQuery(): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()
            ->select('r')
            ->from(SavedReport::class, 'r')
            ->where('r.deletedAt IS NULL');
    }
}
