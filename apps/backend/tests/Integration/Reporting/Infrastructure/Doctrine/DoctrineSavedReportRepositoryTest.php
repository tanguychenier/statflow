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

use App\Reporting\Domain\Model\ReportType;
use App\Reporting\Domain\Model\SavedReport;
use App\Reporting\Domain\Port\ListCriteria;
use App\Reporting\Domain\ValueObject\QueryDefinition;
use App\Reporting\Domain\ValueObject\ReportDescription;
use App\Reporting\Domain\ValueObject\ReportName;
use App\Reporting\Infrastructure\Doctrine\DoctrineSavedReportRepository;
use App\Shared\Domain\ValueObject\Uuid;
use App\Tests\Integration\Reporting\Support\ReportingFixtures;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Persistence integration test for {@see DoctrineSavedReportRepository}.
 *
 * Runs against a real PostgreSQL schema in the container phase. Each test runs
 * inside a transaction rolled back in tearDown so cases stay isolated.
 */
#[CoversClass(DoctrineSavedReportRepository::class)]
final class DoctrineSavedReportRepositoryTest extends KernelTestCase
{
    private const SITE = '33333333-3333-4333-8333-333333333333';

    private EntityManagerInterface $entityManager;

    private DoctrineSavedReportRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        /** @phpstan-ignore-next-line */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        assert($em instanceof EntityManagerInterface);
        $this->entityManager = $em;
        $this->repository = new DoctrineSavedReportRepository($em);

        $this->entityManager->getConnection()->beginTransaction();
        ReportingFixtures::seedSite($this->entityManager->getConnection(), self::SITE);
    }

    protected function tearDown(): void
    {
        $this->entityManager->getConnection()->rollBack();
        parent::tearDown();
    }

    #[Test]
    public function itPersistsAndReloadsAReport(): void
    {
        $report = $this->newReport('Top pages', [
            'property' => 'page',
        ]);
        $this->repository->save($report);
        $this->entityManager->clear();

        $loaded = $this->repository->findById(Uuid::fromString(self::SITE), $report->id());

        self::assertNotNull($loaded);
        self::assertSame('Top pages', $loaded->name()->value());
        self::assertSame(ReportType::Breakdown, $loaded->reportType());
        self::assertSame([
            'property' => 'page',
        ], $loaded->definition()->toArray());
    }

    #[Test]
    public function itHidesSoftDeletedReports(): void
    {
        $report = $this->newReport('Gone', []);
        $report->softDelete(new DateTimeImmutable('now', new DateTimeZone('UTC')));
        $this->repository->save($report);
        $this->entityManager->clear();

        self::assertNull($this->repository->findById(Uuid::fromString(self::SITE), $report->id()));
    }

    #[Test]
    public function itScopesFindToTheOwningSite(): void
    {
        $report = $this->newReport('Scoped', []);
        $this->repository->save($report);
        $this->entityManager->clear();

        $otherSite = Uuid::generate();
        self::assertNull($this->repository->findById($otherSite, $report->id()));
    }

    #[Test]
    public function itPaginatesByKeyset(): void
    {
        $base = new DateTimeImmutable('2026-05-01T00:00:00', new DateTimeZone('UTC'));
        for ($i = 0; $i < 3; ++$i) {
            $this->repository->save($this->newReportAt("r{$i}", $base->modify("+{$i} minute")));
        }
        $this->entityManager->clear();

        $first = $this->repository->listForSite(ListCriteria::create(Uuid::fromString(self::SITE), null, 2));
        self::assertCount(2, $first->items);
        self::assertNotNull($first->nextCursor);

        $second = $this->repository->listForSite(ListCriteria::create(Uuid::fromString(self::SITE), $first->nextCursor, 2));
        self::assertCount(1, $second->items);
        self::assertNull($second->nextCursor);
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function newReport(string $name, array $definition): SavedReport
    {
        return $this->newReportAt($name, new DateTimeImmutable('2026-05-01T00:00:00', new DateTimeZone('UTC')), $definition);
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function newReportAt(string $name, DateTimeImmutable $at, array $definition = []): SavedReport
    {
        return SavedReport::create(
            id: Uuid::generate(),
            siteId: Uuid::fromString(self::SITE),
            name: ReportName::fromString($name),
            description: ReportDescription::fromNullableString('desc'),
            reportType: ReportType::Breakdown,
            definition: QueryDefinition::fromArray($definition),
            createdBy: null,
            now: $at,
        );
    }
}
