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

use App\Reporting\Domain\Model\ScheduledReport;
use App\Reporting\Domain\ValueObject\CronExpression;
use App\Reporting\Domain\ValueObject\EmailRecipientList;
use App\Reporting\Domain\ValueObject\ReportName;
use App\Reporting\Domain\ValueObject\ReportTimezone;
use App\Reporting\Infrastructure\Doctrine\DoctrineScheduledReportRepository;
use App\Shared\Domain\ValueObject\Uuid;
use App\Tests\Integration\Reporting\Support\ReportingFixtures;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Persistence integration test for {@see DoctrineScheduledReportRepository}.
 */
#[CoversClass(DoctrineScheduledReportRepository::class)]
final class DoctrineScheduledReportRepositoryTest extends KernelTestCase
{
    private const SITE = '33333333-3333-4333-8333-333333333333';

    private EntityManagerInterface $entityManager;

    private DoctrineScheduledReportRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        /** @phpstan-ignore-next-line */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        assert($em instanceof EntityManagerInterface);
        $this->entityManager = $em;
        $this->repository = new DoctrineScheduledReportRepository($em);

        $this->entityManager->getConnection()->beginTransaction();
        ReportingFixtures::seedSite($this->entityManager->getConnection(), self::SITE);
    }

    protected function tearDown(): void
    {
        $this->entityManager->getConnection()->rollBack();
        parent::tearDown();
    }

    #[Test]
    public function itPersistsAndReloadsASchedule(): void
    {
        $report = $this->newSchedule('Weekly', '0 9 * * *');
        $this->repository->save($report);
        $this->entityManager->clear();

        $loaded = $this->repository->findById(Uuid::fromString(self::SITE), $report->id());

        self::assertNotNull($loaded);
        self::assertSame('Weekly', $loaded->name()->value());
        self::assertSame(['a@b.com'], $loaded->recipients()->toStrings());
        self::assertSame('0 9 * * *', $loaded->cron()->value());
        self::assertNotNull($loaded->nextSendAt());
    }

    #[Test]
    public function findDueReturnsOverdueActiveSchedules(): void
    {
        $past = new DateTimeImmutable('2026-01-01T00:00:00', new DateTimeZone('UTC'));
        $report = $this->newSchedule('Overdue', '0 9 * * *', $past);
        $this->repository->save($report);
        $this->entityManager->clear();

        $due = $this->repository->findDue(new DateTimeImmutable('2026-06-01T00:00:00', new DateTimeZone('UTC')), 50);

        $ids = array_map(static fn (ScheduledReport $r): string => $r->id()->getValue(), $due);
        self::assertContains($report->id()->getValue(), $ids);
    }

    #[Test]
    public function findDueIgnoresInactiveSchedules(): void
    {
        $past = new DateTimeImmutable('2026-01-01T00:00:00', new DateTimeZone('UTC'));
        $report = $this->newSchedule('Disabled', '0 9 * * *', $past);
        $report->deactivate($past);
        $this->repository->save($report);
        $this->entityManager->clear();

        $due = $this->repository->findDue(new DateTimeImmutable('2026-06-01T00:00:00', new DateTimeZone('UTC')), 50);

        $ids = array_map(static fn (ScheduledReport $r): string => $r->id()->getValue(), $due);
        self::assertNotContains($report->id()->getValue(), $ids);
    }

    private function newSchedule(string $name, string $cron, ?DateTimeImmutable $at = null): ScheduledReport
    {
        return ScheduledReport::schedule(
            id: Uuid::generate(),
            siteId: Uuid::fromString(self::SITE),
            savedReportId: null,
            name: ReportName::fromString($name),
            recipients: EmailRecipientList::fromStrings(['a@b.com']),
            schedule: CronExpression::fromString($cron),
            timezone: ReportTimezone::fromString('UTC'),
            createdBy: null,
            now: $at ?? new DateTimeImmutable('2026-05-29T08:00:00', new DateTimeZone('UTC')),
        );
    }
}
