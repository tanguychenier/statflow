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

use App\Reporting\Domain\Model\ScheduledReport;
use App\Reporting\Domain\Port\ListCriteria;
use App\Reporting\Domain\Port\ResultPage;
use App\Reporting\Domain\Port\ScheduledReportRepository;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * Doctrine ORM adapter for {@see ScheduledReportRepository}. Keyset paginated by
 * (created_at DESC, id DESC); the due-sweep finder orders by next_send_at ASC so
 * the most overdue schedules are delivered first.
 */
final readonly class DoctrineScheduledReportRepository implements ScheduledReportRepository
{
    use CursorCodec;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function save(ScheduledReport $report): void
    {
        $this->entityManager->persist($report);
        $this->entityManager->flush();
    }

    public function findById(Uuid $siteId, Uuid $id): ?ScheduledReport
    {
        /** @var ScheduledReport|null $result */
        $result = $this->activeQuery()
            ->andWhere('s.id = :id')
            ->andWhere('s.siteId = :siteId')
            ->setParameter('id', $id->getValue())
            ->setParameter('siteId', $siteId->getValue())
            ->getQuery()
            ->getOneOrNullResult();

        return $result;
    }

    public function listForSite(ListCriteria $criteria): ResultPage
    {
        $qb = $this->activeQuery()
            ->andWhere('s.siteId = :siteId')
            ->setParameter('siteId', $criteria->siteId->getValue())
            ->orderBy('s.createdAt', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->setMaxResults($criteria->limit + 1);

        $cursor = $this->decodeCursor($criteria->cursor);
        if ($cursor !== null) {
            [$createdAt, $id] = $cursor;
            $qb->andWhere('(s.createdAt < :cAt OR (s.createdAt = :cAt AND s.id < :cId))')
                ->setParameter('cAt', $createdAt)
                ->setParameter('cId', $id);
        }

        /** @var list<ScheduledReport> $rows */
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

    public function findDue(DateTimeImmutable $now, int $limit): array
    {
        /** @var list<ScheduledReport> $rows */
        $rows = $this->activeQuery()
            ->andWhere('s.isActive = true')
            ->andWhere('s.nextSendAt IS NOT NULL')
            ->andWhere('s.nextSendAt <= :now')
            ->setParameter('now', $now)
            ->orderBy('s.nextSendAt', 'ASC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();

        return $rows;
    }

    private function activeQuery(): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(ScheduledReport::class, 's')
            ->where('s.deletedAt IS NULL');
    }
}
