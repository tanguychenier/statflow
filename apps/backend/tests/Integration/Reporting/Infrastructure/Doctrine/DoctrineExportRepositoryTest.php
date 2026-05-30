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

namespace App\Tests\Integration\Reporting\Infrastructure\Doctrine;

use App\Reporting\Domain\Model\Export;
use App\Reporting\Domain\Model\ExportFormat;
use App\Reporting\Domain\Model\ExportStatus;
use App\Reporting\Domain\ValueObject\QueryDefinition;
use App\Reporting\Infrastructure\Doctrine\DoctrineExportRepository;
use App\Shared\Domain\ValueObject\Uuid;
use App\Tests\Integration\Reporting\Support\ReportingFixtures;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Persistence integration test for {@see DoctrineExportRepository}.
 */
#[CoversClass(DoctrineExportRepository::class)]
final class DoctrineExportRepositoryTest extends KernelTestCase
{
    private const SITE = '33333333-3333-4333-8333-333333333333';

    private EntityManagerInterface $entityManager;

    private DoctrineExportRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        /** @phpstan-ignore-next-line */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        assert($em instanceof EntityManagerInterface);
        $this->entityManager = $em;
        $this->repository = new DoctrineExportRepository($em);

        $this->entityManager->getConnection()->beginTransaction();
        ReportingFixtures::seedSite($this->entityManager->getConnection(), self::SITE);
    }

    protected function tearDown(): void
    {
        $this->entityManager->getConnection()->rollBack();
        parent::tearDown();
    }

    #[Test]
    public function itPersistsLifecycleTransitions(): void
    {
        $now = new DateTimeImmutable('2026-05-29T08:00:00', new DateTimeZone('UTC'));
        $export = Export::request(
            Uuid::generate(),
            Uuid::fromString(self::SITE),
            ExportFormat::Csv,
            QueryDefinition::fromArray([
                'report_type' => 'breakdown',
            ]),
            null,
            null,
            $now,
        );
        $this->repository->save($export);

        $export->markProcessing();
        $export->markCompleted(5, 1024, 'key.csv', $now->modify('+1 hour'), $now);
        $this->repository->save($export);
        $this->entityManager->clear();

        $loaded = $this->repository->findById(Uuid::fromString(self::SITE), $export->id());

        self::assertNotNull($loaded);
        self::assertSame(ExportStatus::Completed, $loaded->status());
        self::assertSame(5, $loaded->rowCount());
        self::assertSame(1024, $loaded->fileSizeBytes());
        self::assertSame('key.csv', $loaded->artifactKey());
    }

    #[Test]
    public function findByIdIsSiteScopedButUnscopedFinderIsNot(): void
    {
        $export = Export::request(
            Uuid::generate(),
            Uuid::fromString(self::SITE),
            ExportFormat::Ndjson,
            QueryDefinition::fromArray([]),
            null,
            null,
            new DateTimeImmutable('2026-05-29T08:00:00', new DateTimeZone('UTC')),
        );
        $this->repository->save($export);
        $this->entityManager->clear();

        self::assertNull($this->repository->findById(Uuid::generate(), $export->id()));
        self::assertNotNull($this->repository->findByIdUnscoped($export->id()));
    }
}
