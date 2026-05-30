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

use App\Analytics\Application\Query\FunnelHandler;
use App\Analytics\Application\Query\FunnelQuery;
use App\Analytics\Domain\Exception\FunnelNotFound;
use App\Analytics\Domain\Model\Funnel;
use App\Analytics\Domain\Model\FunnelStep;
use App\Analytics\Domain\ValueObject\DateRange;
use App\Analytics\Domain\ValueObject\FilterSet;
use App\Shared\Domain\ValueObject\Uuid;
use App\Tests\Unit\Analytics\Support\FakeClickHouseClient;
use App\Tests\Unit\Analytics\Support\InMemoryFunnelRepository;
use App\Tests\Unit\Analytics\Support\PassthroughQueryCache;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FunnelHandler::class)]
#[CoversClass(FunnelQuery::class)]
#[CoversClass(FunnelNotFound::class)]
final class FunnelHandlerTest extends TestCase
{
    private Uuid $site;

    private Uuid $funnelId;

    private InMemoryFunnelRepository $funnels;

    protected function setUp(): void
    {
        $this->site = Uuid::generate();
        $this->funnelId = Uuid::generate();
        $this->funnels = new InMemoryFunnelRepository();
        $this->funnels->add(Funnel::reconstitute(
            $this->funnelId,
            $this->site,
            'Signup',
            [
                FunnelStep::pageview(0, 'Landing', '/'),
                FunnelStep::pageview(1, 'Pricing', '/pricing'),
                FunnelStep::event(2, 'Signup', 'signup'),
            ],
            new DateTimeImmutable(),
            new DateTimeImmutable(),
        ));
    }

    #[Test]
    public function itComputesStepConversionFromTheLevelHistogram(): void
    {
        $client = new FakeClickHouseClient([
            [[
                'level' => 1,
                'users' => 50,
            ], [
                'level' => 2,
                'users' => 30,
            ], [
                'level' => 3,
                'users' => 20,
            ]],
            [[
                'step_index' => 1,
                'avg_gap_s' => 120.0,
            ], [
                'step_index' => 2,
                'avg_gap_s' => 300.0,
            ]],
        ]);

        $result = ($this->handler($client))($this->query());

        self::assertSame(7, $result['conversion_window_days']);
        self::assertCount(3, $result['steps']);

        self::assertSame(100, $result['steps'][0]['entered']);
        self::assertSame(100, $result['steps'][0]['converted']);
        self::assertSame(0.0, $result['steps'][0]['avg_time_to_convert_seconds']);

        self::assertSame(100, $result['steps'][1]['entered']);
        self::assertSame(50, $result['steps'][1]['converted']);
        self::assertSame(50, $result['steps'][1]['dropped']);
        self::assertSame(50.0, $result['steps'][1]['conversion_rate_pct']);
        self::assertSame(120.0, $result['steps'][1]['avg_time_to_convert_seconds']);

        self::assertSame(50, $result['steps'][2]['entered']);
        self::assertSame(20, $result['steps'][2]['converted']);
        self::assertSame(40.0, $result['steps'][2]['conversion_rate_pct']);
    }

    #[Test]
    public function itPassesOneConditionPerStepAndTheWindowBinding(): void
    {
        $client = new FakeClickHouseClient([[], []]);

        ($this->handler($client))($this->query());

        $sql = $client->selectCalls[0]['sql'];
        self::assertStringContainsString('windowFunnel({window:UInt32})(timestamp, step_index = 0, step_index = 1, step_index = 2)', $sql);
        self::assertSame(7 * 86400, $client->selectCalls[0]['bindings']['window']);
    }

    #[Test]
    public function itRejectsAnUnknownFunnel(): void
    {
        $client = new FakeClickHouseClient([]);

        $query = new FunnelQuery(
            $this->site,
            Uuid::generate(),
            DateRange::fromStrings('2025-06-01', '2025-06-30'),
            7,
            FilterSet::empty(),
            null,
        );

        $this->expectException(FunnelNotFound::class);

        ($this->handler($client))($query);
    }

    private function query(): FunnelQuery
    {
        return new FunnelQuery(
            $this->site,
            $this->funnelId,
            DateRange::fromStrings('2025-06-01', '2025-06-30'),
            7,
            FilterSet::empty(),
            null,
        );
    }

    private function handler(FakeClickHouseClient $client): FunnelHandler
    {
        return new FunnelHandler($client, new PassthroughQueryCache(), $this->funnels);
    }
}
