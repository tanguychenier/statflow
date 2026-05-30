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

namespace App\Tests\Unit\Reporting\Domain\Model;

use App\Reporting\Domain\Exception\InvalidAlertException;
use App\Reporting\Domain\Model\Alert;
use App\Reporting\Domain\Model\AlertCondition;
use App\Reporting\Domain\Model\AlertMetric;
use App\Reporting\Domain\Model\ComparisonPeriod;
use App\Reporting\Domain\ValueObject\NotificationChannelList;
use App\Reporting\Domain\ValueObject\ReportName;
use App\Reporting\Domain\ValueObject\Threshold;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Alert::class)]
final class AlertTest extends TestCase
{
    private const SITE = '22222222-2222-4222-8222-222222222222';

    #[Test]
    public function itCreatesAnAbsoluteAlert(): void
    {
        $alert = $this->alert(AlertCondition::Above, 100.0, null);

        self::assertSame(AlertMetric::Pageviews, $alert->metric());
        self::assertSame(100.0, $alert->thresholdValue());
        self::assertNull($alert->comparisonPeriod());
        self::assertTrue($alert->isActive());
        self::assertTrue($alert->isBreachedBy(150.0));
        self::assertFalse($alert->isBreachedBy(50.0));
    }

    #[Test]
    public function itRequiresComparisonPeriodForPercentageCondition(): void
    {
        $this->expectException(InvalidAlertException::class);
        $this->alert(AlertCondition::ChangePctAbove, 10.0, null);
    }

    #[Test]
    public function itForbidsComparisonPeriodForAbsoluteCondition(): void
    {
        $this->expectException(InvalidAlertException::class);
        $this->alert(AlertCondition::Above, 10.0, ComparisonPeriod::PreviousDay);
    }

    #[Test]
    public function changeConditionRevalidatesInvariant(): void
    {
        $alert = $this->alert(AlertCondition::Above, 100.0, null);
        $now = $this->now();

        $this->expectException(InvalidAlertException::class);
        $alert->changeCondition(AlertCondition::ChangePctAbove, Threshold::fromFloat(5.0), $now);
    }

    #[Test]
    public function markTriggeredRecordsTime(): void
    {
        $alert = $this->alert(AlertCondition::Above, 100.0, null);
        $triggeredAt = new DateTimeImmutable('2026-05-29T10:00:00', new DateTimeZone('UTC'));

        $alert->markTriggered($triggeredAt);

        self::assertSame($triggeredAt->format(DATE_ATOM), $alert->lastTriggeredAt()?->format(DATE_ATOM));
    }

    #[Test]
    public function deactivateAndSoftDelete(): void
    {
        $alert = $this->alert(AlertCondition::Above, 100.0, null);
        $now = $this->now();

        $alert->deactivate($now);
        self::assertFalse($alert->isActive());

        $alert->softDelete($now);
        self::assertTrue($alert->isDeleted());
        self::assertFalse($alert->isActive());
    }

    #[Test]
    public function thresholdPersistsWithFourDecimalPrecision(): void
    {
        $alert = $this->alert(AlertCondition::Below, 12.3456, null);

        self::assertSame(12.3456, $alert->thresholdValue());
    }

    private function alert(AlertCondition $condition, float $threshold, ?ComparisonPeriod $period): Alert
    {
        return Alert::create(
            id: Uuid::generate(),
            siteId: Uuid::fromString(self::SITE),
            name: ReportName::fromString('Traffic spike'),
            metric: AlertMetric::Pageviews,
            condition: $condition,
            threshold: Threshold::fromFloat($threshold),
            comparisonPeriod: $period,
            filters: [],
            channels: NotificationChannelList::fromArrayList([[
                'type' => 'email',
                'email' => 'a@b.com',
            ]]),
            createdBy: null,
            now: $this->now(),
        );
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-05-29T08:00:00', new DateTimeZone('UTC'));
    }
}
