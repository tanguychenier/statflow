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

use App\Reporting\Domain\Model\ReportType;
use App\Reporting\Domain\Model\SavedReport;
use App\Reporting\Domain\ValueObject\QueryDefinition;
use App\Reporting\Domain\ValueObject\ReportDescription;
use App\Reporting\Domain\ValueObject\ReportName;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SavedReport::class)]
final class SavedReportTest extends TestCase
{
    private const SITE = '22222222-2222-4222-8222-222222222222';

    private const USER = '11111111-1111-4111-8111-111111111111';

    #[Test]
    public function itExposesItsConfiguration(): void
    {
        $report = $this->report();

        self::assertSame('Top pages', $report->name()->value());
        self::assertSame('Most visited', $report->description()?->value());
        self::assertSame(ReportType::Breakdown, $report->reportType());
        self::assertSame([
            'property' => 'page',
        ], $report->definition()->toArray());
        self::assertSame(self::USER, $report->createdBy()?->getValue());
        self::assertFalse($report->isDeleted());
    }

    #[Test]
    public function softDeleteIsIdempotent(): void
    {
        $report = $this->report();
        $now = new DateTimeImmutable('2026-05-29T09:00:00', new DateTimeZone('UTC'));

        $report->softDelete($now);
        $report->softDelete($now->modify('+1 hour'));

        self::assertTrue($report->isDeleted());
    }

    private function report(): SavedReport
    {
        return SavedReport::create(
            id: Uuid::generate(),
            siteId: Uuid::fromString(self::SITE),
            name: ReportName::fromString('Top pages'),
            description: ReportDescription::fromNullableString('Most visited'),
            reportType: ReportType::Breakdown,
            definition: QueryDefinition::fromArray([
                'property' => 'page',
            ]),
            createdBy: Uuid::fromString(self::USER),
            now: new DateTimeImmutable('2026-05-29T08:00:00', new DateTimeZone('UTC')),
        );
    }
}
