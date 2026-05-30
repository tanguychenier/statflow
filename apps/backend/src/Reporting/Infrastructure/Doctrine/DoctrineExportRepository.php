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

use App\Reporting\Domain\Model\Export;
use App\Reporting\Domain\Port\ExportRepository;
use App\Shared\Domain\ValueObject\Uuid;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Doctrine ORM adapter for {@see ExportRepository}. Exports are not soft-deleted
 * (they expire by retention), so finders read the full row by id.
 */
final readonly class DoctrineExportRepository implements ExportRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function save(Export $export): void
    {
        $this->entityManager->persist($export);
        $this->entityManager->flush();
    }

    public function findById(Uuid $siteId, Uuid $id): ?Export
    {
        /** @var Export|null $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('e')
            ->from(Export::class, 'e')
            ->where('e.id = :id')
            ->andWhere('e.siteId = :siteId')
            ->setParameter('id', $id->getValue())
            ->setParameter('siteId', $siteId->getValue())
            ->getQuery()
            ->getOneOrNullResult();

        return $result;
    }

    public function findByIdUnscoped(Uuid $id): ?Export
    {
        return $this->entityManager->find(Export::class, $id->getValue());
    }
}
