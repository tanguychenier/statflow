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

namespace App\Tests\Unit\Reporting\Domain\Service;

use App\Reporting\Domain\Model\Alert;
use App\Reporting\Domain\Model\AlertCondition;
use App\Reporting\Domain\Model\AlertMetric;
use App\Reporting\Domain\Model\ComparisonPeriod;
use App\Reporting\Domain\Service\AlertEvaluation;
use App\Reporting\Domain\ValueObject\NotificationChannelList;
use App\Reporting\Domain\ValueObject\ReportName;
use App\Reporting\Domain\ValueObject\Threshold;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AlertEvaluation::class)]
final class AlertEvaluationTest extends TestCase
{
    private AlertEvaluation $evaluation;

    protected function setUp(): void
    {
        $this->evaluation = new AlertEvaluation();
    }

    #[Test]
    public function absoluteAboveBreaches(): void
    {
        $alert = $this->alert(AlertCondition::Above, 100.0, null);

        self::assertTrue($this->evaluation->isBreached($alert, 150.0, null));
        self::assertFalse($this->evaluation->isBreached($alert, 50.0, null));
    }

    #[Test]
    public function missingCurrentNeverBreaches(): void
    {
        $alert = $this->alert(AlertCondition::Above, 100.0, null);

        self::assertFalse($this->evaluation->isBreached($alert, null, null));
    }

    #[Test]
    public function percentageChangeBreaches(): void
    {
        $alert = $this->alert(AlertCondition::ChangePctAbove, 20.0, ComparisonPeriod::PreviousDay);

        // +50% move from 100 to 150 exceeds the +20% threshold.
        self::assertTrue($this->evaluation->isBreached($alert, 150.0, 100.0));
        // +10% move does not.
        self::assertFalse($this->evaluation->isBreached($alert, 110.0, 100.0));
    }

    #[Test]
    public function percentageChangeBelowBreaches(): void
    {
        $alert = $this->alert(AlertCondition::ChangePctBelow, -30.0, ComparisonPeriod::PreviousWeek);

        // -40% move breaches a "below -30%" rule.
        self::assertTrue($this->evaluation->isBreached($alert, 60.0, 100.0));
        self::assertFalse($this->evaluation->isBreached($alert, 80.0, 100.0));
    }

    #[Test]
    public function zeroOrMissingBaselineNeverBreachesPercentage(): void
    {
        $alert = $this->alert(AlertCondition::ChangePctAbove, 20.0, ComparisonPeriod::PreviousDay);

        self::assertFalse($this->evaluation->isBreached($alert, 150.0, 0.0));
        self::assertFalse($this->evaluation->isBreached($alert, 150.0, null));
    }

    private function alert(AlertCondition $condition, float $threshold, ?ComparisonPeriod $period): Alert
    {
        return Alert::create(
            id: Uuid::generate(),
            siteId: Uuid::generate(),
            name: ReportName::fromString('Alert'),
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
            now: new DateTimeImmutable('2026-05-29T08:00:00', new DateTimeZone('UTC')),
        );
    }
}
