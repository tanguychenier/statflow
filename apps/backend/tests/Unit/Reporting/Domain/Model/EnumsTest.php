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
use App\Reporting\Domain\Exception\InvalidExportException;
use App\Reporting\Domain\Exception\InvalidReportTypeException;
use App\Reporting\Domain\Model\AlertCondition;
use App\Reporting\Domain\Model\AlertMetric;
use App\Reporting\Domain\Model\ComparisonPeriod;
use App\Reporting\Domain\Model\ExportFormat;
use App\Reporting\Domain\Model\ExportStatus;
use App\Reporting\Domain\Model\ReportType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReportType::class)]
#[CoversClass(AlertMetric::class)]
#[CoversClass(AlertCondition::class)]
#[CoversClass(ComparisonPeriod::class)]
#[CoversClass(ExportFormat::class)]
#[CoversClass(ExportStatus::class)]
final class EnumsTest extends TestCase
{
    #[Test]
    public function reportTypeParsesAndRejects(): void
    {
        self::assertSame(ReportType::Breakdown, ReportType::fromString('breakdown'));

        $this->expectException(InvalidReportTypeException::class);
        ReportType::fromString('nope');
    }

    #[Test]
    public function alertMetricRejectsUnknown(): void
    {
        self::assertSame(AlertMetric::Pageviews, AlertMetric::fromString('pageviews'));

        $this->expectException(InvalidAlertException::class);
        AlertMetric::fromString('weather');
    }

    #[Test]
    public function comparisonPeriodRejectsUnknown(): void
    {
        self::assertSame(ComparisonPeriod::PreviousWeek, ComparisonPeriod::fromString('previous_week'));

        $this->expectException(InvalidAlertException::class);
        ComparisonPeriod::fromString('previous_century');
    }

    #[Test]
    public function alertConditionBreachLogic(): void
    {
        self::assertTrue(AlertCondition::Above->isBreached(10.0, 5.0));
        self::assertFalse(AlertCondition::Above->isBreached(3.0, 5.0));
        self::assertTrue(AlertCondition::Below->isBreached(2.0, 5.0));
        self::assertTrue(AlertCondition::ChangePctAbove->requiresComparisonPeriod());
        self::assertFalse(AlertCondition::Above->requiresComparisonPeriod());

        $this->expectException(InvalidAlertException::class);
        AlertCondition::fromString('sideways');
    }

    #[Test]
    public function exportFormatExposesContentType(): void
    {
        self::assertSame('text/csv', ExportFormat::Csv->contentType());
        self::assertSame('application/x-ndjson', ExportFormat::Ndjson->contentType());
        self::assertSame('csv', ExportFormat::Csv->fileExtension());

        $this->expectException(InvalidExportException::class);
        ExportFormat::fromString('xlsx');
    }

    #[Test]
    public function exportStatusStateMachine(): void
    {
        self::assertTrue(ExportStatus::Pending->canTransitionTo(ExportStatus::Processing));
        self::assertTrue(ExportStatus::Processing->canTransitionTo(ExportStatus::Completed));
        self::assertTrue(ExportStatus::Processing->canTransitionTo(ExportStatus::Failed));
        self::assertFalse(ExportStatus::Completed->canTransitionTo(ExportStatus::Processing));
        self::assertFalse(ExportStatus::Pending->canTransitionTo(ExportStatus::Completed));
        self::assertTrue(ExportStatus::Completed->isTerminal());
        self::assertFalse(ExportStatus::Pending->isTerminal());
    }
}
