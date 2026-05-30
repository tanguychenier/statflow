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

use App\Reporting\Domain\Model\Alert;
use App\Reporting\Domain\Model\AlertCondition;
use App\Reporting\Domain\Model\AlertMetric;
use App\Reporting\Domain\Model\ComparisonPeriod;
use App\Reporting\Domain\ValueObject\NotificationChannelList;
use App\Reporting\Domain\ValueObject\ReportName;
use App\Reporting\Domain\ValueObject\Threshold;
use App\Reporting\Infrastructure\Doctrine\DoctrineAlertRepository;
use App\Shared\Domain\ValueObject\Uuid;
use App\Tests\Integration\Reporting\Support\ReportingFixtures;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Persistence integration test for {@see DoctrineAlertRepository}.
 */
#[CoversClass(DoctrineAlertRepository::class)]
final class DoctrineAlertRepositoryTest extends KernelTestCase
{
    private const SITE = '33333333-3333-4333-8333-333333333333';

    private EntityManagerInterface $entityManager;

    private DoctrineAlertRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        /** @phpstan-ignore-next-line */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        assert($em instanceof EntityManagerInterface);
        $this->entityManager = $em;
        $this->repository = new DoctrineAlertRepository($em);

        $this->entityManager->getConnection()->beginTransaction();
        ReportingFixtures::seedSite($this->entityManager->getConnection(), self::SITE);
    }

    protected function tearDown(): void
    {
        $this->entityManager->getConnection()->rollBack();
        parent::tearDown();
    }

    #[Test]
    public function itPersistsAndReloadsAnAlertWithPrecision(): void
    {
        $alert = $this->newAlert(AlertCondition::ChangePctAbove, 12.3456, ComparisonPeriod::PreviousWeek);
        $this->repository->save($alert);
        $this->entityManager->clear();

        $loaded = $this->repository->findById(Uuid::fromString(self::SITE), $alert->id());

        self::assertNotNull($loaded);
        self::assertSame(AlertMetric::Pageviews, $loaded->metric());
        self::assertSame(12.3456, $loaded->thresholdValue());
        self::assertSame(ComparisonPeriod::PreviousWeek, $loaded->comparisonPeriod());
        self::assertCount(1, $loaded->notificationChannels()->all());
    }

    #[Test]
    public function findEnabledForSiteExcludesDisabled(): void
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $enabled = $this->newAlert(AlertCondition::Above, 100.0, null);
        $disabled = $this->newAlert(AlertCondition::Above, 100.0, null);
        $disabled->deactivate($now);

        $this->repository->save($enabled);
        $this->repository->save($disabled);
        $this->entityManager->clear();

        $alerts = $this->repository->findEnabledForSite(Uuid::fromString(self::SITE));
        $ids = array_map(static fn (Alert $a): string => $a->id()->getValue(), $alerts);

        self::assertContains($enabled->id()->getValue(), $ids);
        self::assertNotContains($disabled->id()->getValue(), $ids);
    }

    private function newAlert(AlertCondition $condition, float $threshold, ?ComparisonPeriod $period): Alert
    {
        return Alert::create(
            id: Uuid::generate(),
            siteId: Uuid::fromString(self::SITE),
            name: ReportName::fromString('Alert'),
            metric: AlertMetric::Pageviews,
            condition: $condition,
            threshold: Threshold::fromFloat($threshold),
            comparisonPeriod: $period,
            filters: [[
                'property' => 'country',
                'operator' => 'is',
                'value' => 'FR',
            ]],
            channels: NotificationChannelList::fromArrayList([[
                'type' => 'email',
                'email' => 'a@b.com',
            ]]),
            createdBy: null,
            now: new DateTimeImmutable('2026-05-29T08:00:00', new DateTimeZone('UTC')),
        );
    }
}
