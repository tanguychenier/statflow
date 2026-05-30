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

namespace App\Tests\Unit\Reporting\Application\Dto;

use App\Reporting\Application\Dto\AlertView;
use App\Reporting\Application\Dto\ExportView;
use App\Reporting\Application\Dto\PaginatedView;
use App\Reporting\Application\Dto\SavedReportView;
use App\Reporting\Application\Dto\ScheduledReportView;
use App\Reporting\Domain\Model\Alert;
use App\Reporting\Domain\Model\AlertCondition;
use App\Reporting\Domain\Model\AlertMetric;
use App\Reporting\Domain\Model\Export;
use App\Reporting\Domain\Model\ExportFormat;
use App\Reporting\Domain\Model\ReportType;
use App\Reporting\Domain\Model\SavedReport;
use App\Reporting\Domain\Model\ScheduledReport;
use App\Reporting\Domain\ValueObject\CronExpression;
use App\Reporting\Domain\ValueObject\EmailRecipientList;
use App\Reporting\Domain\ValueObject\NotificationChannelList;
use App\Reporting\Domain\ValueObject\QueryDefinition;
use App\Reporting\Domain\ValueObject\ReportName;
use App\Reporting\Domain\ValueObject\ReportTimezone;
use App\Reporting\Domain\ValueObject\Threshold;
use App\Shared\Domain\ValueObject\Uuid;
use App\Tests\Unit\Reporting\Fake\InMemoryExportArtifactStorage;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SavedReportView::class)]
#[CoversClass(ScheduledReportView::class)]
#[CoversClass(AlertView::class)]
#[CoversClass(ExportView::class)]
#[CoversClass(PaginatedView::class)]
final class ViewsTest extends TestCase
{
    private const SITE = '22222222-2222-4222-8222-222222222222';

    #[Test]
    public function savedReportViewMatchesContract(): void
    {
        $report = SavedReport::create(
            Uuid::generate(),
            Uuid::fromString(self::SITE),
            ReportName::fromString('Top pages'),
            null,
            ReportType::Breakdown,
            QueryDefinition::fromArray([
                'property' => 'page',
            ]),
            null,
            $this->now(),
        );

        $array = SavedReportView::fromReport($report)->toArray();

        self::assertArrayHasKey('report_type', $array);
        self::assertSame('breakdown', $array['report_type']);
        self::assertSame([
            'property' => 'page',
        ], $array['query']);
        self::assertNull($array['description']);
    }

    #[Test]
    public function savedReportViewEmptyQuerySerialisesAsObject(): void
    {
        $report = SavedReport::create(
            Uuid::generate(),
            Uuid::fromString(self::SITE),
            ReportName::fromString('Empty'),
            null,
            ReportType::Aggregate,
            QueryDefinition::fromArray([]),
            null,
            $this->now(),
        );

        $json = json_encode(SavedReportView::fromReport($report)->toArray());

        self::assertIsString($json);
        self::assertStringContainsString('"query":{}', $json);
    }

    #[Test]
    public function scheduledReportViewMatchesContract(): void
    {
        $report = ScheduledReport::schedule(
            Uuid::generate(),
            Uuid::fromString(self::SITE),
            null,
            ReportName::fromString('Weekly'),
            EmailRecipientList::fromStrings(['a@b.com']),
            CronExpression::fromString('0 9 * * 1'),
            ReportTimezone::fromString('UTC'),
            null,
            $this->now(),
        );

        $array = ScheduledReportView::fromReport($report)->toArray();

        self::assertSame(['a@b.com'], $array['recipients']);
        self::assertSame('0 9 * * 1', $array['schedule_cron']);
        self::assertTrue($array['is_active']);
        self::assertNull($array['last_sent_at']);
        self::assertNotNull($array['next_send_at']);
    }

    #[Test]
    public function alertViewMatchesContract(): void
    {
        $alert = Alert::create(
            Uuid::generate(),
            Uuid::fromString(self::SITE),
            ReportName::fromString('Spike'),
            AlertMetric::Pageviews,
            AlertCondition::Above,
            Threshold::fromFloat(100.0),
            null,
            [[
                'property' => 'country',
                'operator' => 'is',
                'value' => 'FR',
            ]],
            NotificationChannelList::fromArrayList([[
                'type' => 'email',
                'email' => 'a@b.com',
            ]]),
            null,
            $this->now(),
        );

        $array = AlertView::fromAlert($alert)->toArray();

        self::assertSame('pageviews', $array['metric']);
        self::assertSame('above', $array['condition']);
        self::assertSame(100.0, $array['threshold']);
        self::assertNull($array['comparison_period']);
        /** @var list<mixed> $filters */
        $filters = $array['filters'];
        /** @var list<mixed> $channels */
        $channels = $array['notification_channels'];
        self::assertCount(1, $filters);
        self::assertCount(1, $channels);
    }

    #[Test]
    public function exportViewExposesDownloadUrlWhenCompleted(): void
    {
        $export = Export::request(
            Uuid::generate(),
            Uuid::fromString(self::SITE),
            ExportFormat::Csv,
            QueryDefinition::fromArray([]),
            null,
            null,
            $this->now(),
        );
        $export->markProcessing();
        $export->markCompleted(5, 200, 'k.csv', $this->now()->modify('+1 hour'), $this->now());

        $storage = new InMemoryExportArtifactStorage();
        $storage->stored['k.csv'] = 'data';

        $array = ExportView::fromExport($export, $storage)->toArray();

        self::assertSame('completed', $array['status']);
        self::assertSame(5, $array['row_count']);
        self::assertNotNull($array['download_url']);
        self::assertNotNull($array['expires_at']);
    }

    #[Test]
    public function exportViewHasNoDownloadUrlWhenPending(): void
    {
        $export = Export::request(
            Uuid::generate(),
            Uuid::fromString(self::SITE),
            ExportFormat::Ndjson,
            QueryDefinition::fromArray([]),
            null,
            null,
            $this->now(),
        );

        $array = ExportView::fromExport($export, new InMemoryExportArtifactStorage())->toArray();

        self::assertSame('pending', $array['status']);
        self::assertNull($array['download_url']);
    }

    #[Test]
    public function paginatedViewShapesPagination(): void
    {
        $view = new PaginatedView([[
            'id' => '1',
        ]], 'cursor-2', 25);
        $array = $view->toArray();

        /** @var list<mixed> $data */
        $data = $array['data'];
        self::assertCount(1, $data);
        /** @var array{next_cursor: string|null, has_next: bool, has_prev: bool, limit: int} $pagination */
        $pagination = $array['pagination'];
        self::assertSame('cursor-2', $pagination['next_cursor']);
        self::assertTrue($pagination['has_next']);
        self::assertFalse($pagination['has_prev']);
        self::assertSame(25, $pagination['limit']);
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-05-29T08:00:00', new DateTimeZone('UTC'));
    }
}
