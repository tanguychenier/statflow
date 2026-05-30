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

use App\Reporting\Domain\Exception\InvalidExportException;
use App\Reporting\Domain\Model\Export;
use App\Reporting\Domain\Model\ExportFormat;
use App\Reporting\Domain\Model\ExportStatus;
use App\Reporting\Domain\ValueObject\EmailAddress;
use App\Reporting\Domain\ValueObject\QueryDefinition;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Export::class)]
final class ExportTest extends TestCase
{
    private const SITE = '22222222-2222-4222-8222-222222222222';

    #[Test]
    public function itStartsPending(): void
    {
        $export = $this->export();

        self::assertSame(ExportStatus::Pending, $export->status());
        self::assertSame(ExportFormat::Csv, $export->format());
        self::assertSame('a@b.com', $export->notifyEmail()?->value());
        self::assertNull($export->completedAt());
    }

    #[Test]
    public function itProgressesToCompleted(): void
    {
        $export = $this->export();
        $now = $this->now();

        $export->markProcessing();
        self::assertSame(ExportStatus::Processing, $export->status());

        $export->markCompleted(42, 1024, 'key.csv', $now->modify('+1 hour'), $now);

        self::assertSame(ExportStatus::Completed, $export->status());
        self::assertSame(42, $export->rowCount());
        self::assertSame(1024, $export->fileSizeBytes());
        self::assertSame('key.csv', $export->artifactKey());
        self::assertSame($now->format(DATE_ATOM), $export->completedAt()?->format(DATE_ATOM));
    }

    #[Test]
    public function itProgressesToFailed(): void
    {
        $export = $this->export();
        $export->markProcessing();

        $export->markFailed('boom', $this->now());

        self::assertSame(ExportStatus::Failed, $export->status());
        self::assertSame('boom', $export->errorMessage());
    }

    #[Test]
    public function itCanFailDirectlyFromPending(): void
    {
        $export = $this->export();

        $export->markFailed('rejected', $this->now());

        self::assertSame(ExportStatus::Failed, $export->status());
    }

    #[Test]
    public function itRejectsCompletingAPendingExport(): void
    {
        $export = $this->export();

        $this->expectException(InvalidExportException::class);
        $export->markCompleted(1, 1, 'key.csv', $this->now(), $this->now());
    }

    #[Test]
    public function itRejectsReprocessingACompletedExport(): void
    {
        $export = $this->export();
        $export->markProcessing();
        $export->markCompleted(1, 1, 'key.csv', $this->now(), $this->now());

        $this->expectException(InvalidExportException::class);
        $export->markProcessing();
    }

    private function export(): Export
    {
        return Export::request(
            id: Uuid::generate(),
            siteId: Uuid::fromString(self::SITE),
            format: ExportFormat::Csv,
            query: QueryDefinition::fromArray([
                'report_type' => 'breakdown',
            ]),
            notifyEmail: EmailAddress::fromString('a@b.com'),
            createdBy: null,
            now: $this->now(),
        );
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-05-29T08:00:00', new DateTimeZone('UTC'));
    }
}
