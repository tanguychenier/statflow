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

namespace App\Tests\Unit\Analytics\Application\Query;

use App\Analytics\Application\Query\RetentionHandler;
use App\Analytics\Application\Query\RetentionQuery;
use App\Analytics\Domain\ValueObject\DateRange;
use App\Analytics\Domain\ValueObject\RetentionInterval;
use App\Shared\Domain\ValueObject\Uuid;
use App\Tests\Unit\Analytics\Support\FakeClickHouseClient;
use App\Tests\Unit\Analytics\Support\PassthroughQueryCache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RetentionHandler::class)]
#[CoversClass(RetentionQuery::class)]
final class RetentionHandlerTest extends TestCase
{
    private Uuid $site;

    protected function setUp(): void
    {
        $this->site = Uuid::generate();
    }

    #[Test]
    public function itAssemblesCohortRetentionCurves(): void
    {
        $client = new FakeClickHouseClient([[
            [
                'cohort_date' => '2025-06-02',
                'cohort_size' => 100,
                'period' => 0,
                'retained_count' => 100,
            ],
            [
                'cohort_date' => '2025-06-02',
                'cohort_size' => 100,
                'period' => 1,
                'retained_count' => 40,
            ],
            [
                'cohort_date' => '2025-06-02',
                'cohort_size' => 100,
                'period' => 2,
                'retained_count' => 25,
            ],
            [
                'cohort_date' => '2025-06-09',
                'cohort_size' => 80,
                'period' => 0,
                'retained_count' => 80,
            ],
            [
                'cohort_date' => '2025-06-09',
                'cohort_size' => 80,
                'period' => 1,
                'retained_count' => 20,
            ],
        ]]);

        $result = ($this->handler($client))($this->query(RetentionInterval::Week));

        self::assertSame('week', $result['interval']);
        self::assertCount(2, $result['cohorts']);

        /** @var array{cohort_date: string, cohort_size: int, retention: list<array{period: int, retained_count: int, retention_pct: float}>} $first */
        $first = $result['cohorts'][0];
        self::assertSame('2025-06-02', $first['cohort_date']);
        self::assertSame(100, $first['cohort_size']);
        self::assertSame(100.0, $first['retention'][0]['retention_pct']);
        self::assertSame(40.0, $first['retention'][1]['retention_pct']);
        self::assertSame(25.0, $first['retention'][2]['retention_pct']);

        /** @var array{cohort_date: string, cohort_size: int, retention: list<array{period: int, retained_count: int, retention_pct: float}>} $second */
        $second = $result['cohorts'][1];
        self::assertSame(25.0, $second['retention'][1]['retention_pct']);
    }

    #[Test]
    public function cohortSizeIsTheAcquisitionDenominatorNotTheReturnEventCount(): void
    {
        // A return-event filter narrows period 0 to fewer visitors than were
        // acquired; cohort_size must stay the full acquisition cohort so the
        // retention percentages are not overstated.
        $client = new FakeClickHouseClient([[
            [
                'cohort_date' => '2025-06-02',
                'cohort_size' => 200,
                'period' => 0,
                'retained_count' => 120,
            ],
            [
                'cohort_date' => '2025-06-02',
                'cohort_size' => 200,
                'period' => 1,
                'retained_count' => 60,
            ],
        ]]);

        $result = ($this->handler($client))($this->query(RetentionInterval::Week));

        /** @var array{cohort_date: string, cohort_size: int, retention: list<array{period: int, retained_count: int, retention_pct: float}>} $cohort */
        $cohort = $result['cohorts'][0];
        self::assertSame(200, $cohort['cohort_size']);
        self::assertSame(60.0, $cohort['retention'][0]['retention_pct']);
        self::assertSame(30.0, $cohort['retention'][1]['retention_pct']);
    }

    #[Test]
    public function generatedSqlDerivesCohortSizeFromFirstSeenWithoutDistinctInWindow(): void
    {
        $client = new FakeClickHouseClient([[]]);

        ($this->handler($client))($this->query(RetentionInterval::Week));
        $sql = $client->selectCalls[0]['sql'];

        self::assertStringNotContainsStringIgnoringCase('OVER ()', $sql);
        self::assertStringNotContainsStringIgnoringCase('count(DISTINCT', $sql);
        self::assertStringContainsString('cohort_sizes AS (', $sql);
        self::assertStringContainsString('uniq(visitor_id) AS cohort_size', $sql);
        self::assertStringContainsString('c.cohort_size AS cohort_size', $sql);
        self::assertStringContainsString('INNER JOIN cohort_sizes', $sql);
    }

    #[Test]
    public function itBindsTheReturnEventAndIntervalUnit(): void
    {
        $client = new FakeClickHouseClient([[]]);

        $query = new RetentionQuery(
            $this->site,
            DateRange::fromStrings('2025-01-01', '2025-06-30'),
            RetentionInterval::Month,
            'signup',
        );
        ($this->handler($client))($query);

        self::assertSame('signup', $client->selectCalls[0]['bindings']['return_event']);
        self::assertStringContainsString('toStartOfMonth', $client->selectCalls[0]['sql']);
        self::assertStringContainsString("dateDiff('month'", $client->selectCalls[0]['sql']);
    }

    #[Test]
    public function emptyResultYieldsNoCohorts(): void
    {
        $client = new FakeClickHouseClient([[]]);

        $result = ($this->handler($client))($this->query(RetentionInterval::Week));

        self::assertSame([], $result['cohorts']);
    }

    private function query(RetentionInterval $interval): RetentionQuery
    {
        return new RetentionQuery(
            $this->site,
            DateRange::fromStrings('2025-06-01', '2025-06-30'),
            $interval,
            'pageview',
        );
    }

    private function handler(FakeClickHouseClient $client): RetentionHandler
    {
        return new RetentionHandler($client, new PassthroughQueryCache());
    }
}
