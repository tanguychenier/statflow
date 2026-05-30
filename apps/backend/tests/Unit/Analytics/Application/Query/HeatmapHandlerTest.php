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

use App\Analytics\Application\Query\HeatmapHandler;
use App\Analytics\Application\Query\HeatmapQuery;
use App\Analytics\Domain\ValueObject\DateRange;
use App\Analytics\Domain\ValueObject\HeatmapType;
use App\Shared\Domain\ValueObject\Uuid;
use App\Tests\Unit\Analytics\Support\FakeClickHouseClient;
use App\Tests\Unit\Analytics\Support\PassthroughQueryCache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(HeatmapHandler::class)]
#[CoversClass(HeatmapQuery::class)]
final class HeatmapHandlerTest extends TestCase
{
    private Uuid $site;

    protected function setUp(): void
    {
        $this->site = Uuid::generate();
    }

    #[Test]
    public function itBuildsAClickHeatmapWithNormalisedWeights(): void
    {
        $client = new FakeClickHouseClient([[
            [
                'bucket_x' => 100,
                'bucket_y' => 50,
                'clicks' => 40,
            ],
            [
                'bucket_x' => 20,
                'bucket_y' => 10,
                'clicks' => 10,
            ],
        ]]);

        $query = new HeatmapQuery(
            $this->site,
            DateRange::fromStrings('2025-06-01', '2025-06-30'),
            '/pricing',
            HeatmapType::Click,
            1024,
            1920,
        );
        $result = ($this->handler($client))($query);

        self::assertSame('click', $result['heatmap_type']);
        self::assertSame(50, $result['total_events']);
        self::assertSame(50.0, $result['click_points'][0]['x_pct']);
        self::assertSame(25.0, $result['click_points'][0]['y_pct']);
        self::assertSame(1.0, $result['click_points'][0]['weight']);
        self::assertSame(0.25, $result['click_points'][1]['weight']);
        self::assertStringContainsString('FROM statflow.heatmap_stats', $client->selectCalls[0]['sql']);
        self::assertStringContainsString('pathname = {pathname:String}', $client->selectCalls[0]['sql']);
    }

    #[Test]
    public function itBuildsAScrollHeatmapWithCumulativeDepths(): void
    {
        $client = new FakeClickHouseClient([[
            [
                'depth_band' => 0,
                'visitors' => 100,
            ],
            [
                'depth_band' => 50,
                'visitors' => 60,
            ],
            [
                'depth_band' => 90,
                'visitors' => 20,
            ],
        ]]);

        $query = new HeatmapQuery(
            $this->site,
            DateRange::fromStrings('2025-06-01', '2025-06-30'),
            '/blog/*',
            HeatmapType::Scroll,
            null,
            null,
        );
        $result = ($this->handler($client))($query);

        self::assertSame('scroll', $result['heatmap_type']);
        self::assertSame([], $result['click_points']);
        self::assertArrayHasKey('scroll_depths', $result);
        /** @var list<array{depth_pct: int, sessions_pct: float}> $scrollDepths */
        // @phpstan-ignore-next-line
        $scrollDepths = $result['scroll_depths'];
        self::assertCount(11, $scrollDepths);
        self::assertSame(0, $scrollDepths[0]['depth_pct']);
        self::assertSame(100.0, $scrollDepths[0]['sessions_pct']);
        self::assertSame(100, $scrollDepths[10]['depth_pct']);
        self::assertStringContainsString('FROM statflow.scroll_depth_stats', $client->selectCalls[0]['sql']);
        self::assertStringContainsString('pathname LIKE {pathname:String}', $client->selectCalls[0]['sql']);
        self::assertSame('/blog/%', $client->selectCalls[0]['bindings']['pathname']);
    }

    #[Test]
    public function emptyClickHeatmapHasZeroWeights(): void
    {
        $client = new FakeClickHouseClient([[]]);

        $query = new HeatmapQuery(
            $this->site,
            DateRange::fromStrings('2025-06-01', '2025-06-30'),
            '/x',
            HeatmapType::Click,
            null,
            null,
        );
        $result = ($this->handler($client))($query);

        self::assertSame(0, $result['total_events']);
        self::assertSame([], $result['click_points']);
    }

    private function handler(FakeClickHouseClient $client): HeatmapHandler
    {
        return new HeatmapHandler($client, new PassthroughQueryCache());
    }
}
