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

use App\Analytics\Application\Query\RealtimeEventsHandler;
use App\Analytics\Application\Query\RealtimeHandler;
use App\Shared\Domain\ValueObject\Uuid;
use App\Tests\Unit\Analytics\Support\FakeClickHouseClient;
use App\Tests\Unit\Analytics\Support\FakeRealtimeCounter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RealtimeHandler::class)]
#[CoversClass(RealtimeEventsHandler::class)]
final class RealtimeHandlerTest extends TestCase
{
    private Uuid $site;

    protected function setUp(): void
    {
        $this->site = Uuid::generate();
    }

    #[Test]
    public function itPrefersTheRedisVisitorCount(): void
    {
        $client = new FakeClickHouseClient([
            [[
                'active_visitors' => 7,
                'active_sessions' => 5,
            ]],
            [[
                'pathname' => '/home',
                'visitors' => 3,
            ]],
            [[
                'referrer_domain' => 'google',
                'visitors' => 2,
            ]],
        ]);

        $result = (new RealtimeHandler($client, new FakeRealtimeCounter(42)))($this->site);

        self::assertSame(42, $result['current_visitors']);
        self::assertSame([[
            'pathname' => '/home',
            'visitors' => 3,
        ]], $result['top_pages']);
        self::assertSame([[
            'referrer_domain' => 'google',
            'visitors' => 2,
        ]], $result['top_sources']);
        self::assertArrayHasKey('updated_at', $result);
    }

    #[Test]
    public function itFallsBackToTheClickHouseCountWhenRedisIsCold(): void
    {
        $client = new FakeClickHouseClient([
            [[
                'active_visitors' => 7,
            ]],
            [],
            [],
        ]);

        $result = (new RealtimeHandler($client, new FakeRealtimeCounter(null)))($this->site);

        self::assertSame(7, $result['current_visitors']);
        self::assertStringContainsString('INTERVAL 5 MINUTE', $client->selectCalls[0]['sql']);
    }

    #[Test]
    public function realtimeEventsAreMappedAndIsoFormatted(): void
    {
        $client = new FakeClickHouseClient([[
            [
                'timestamp' => '2025-06-15 14:30:00.123',
                'event_name' => 'pageview',
                'pathname' => '/x',
                'referrer_source' => 'google',
                'country' => 'FR',
                'device_type' => 'desktop',
                'browser' => 'Firefox',
            ],
        ]]);

        $events = (new RealtimeEventsHandler($client))($this->site);

        self::assertSame('2025-06-15T14:30:00.123Z', $events[0]['timestamp']);
        self::assertSame('pageview', $events[0]['event_name']);
        self::assertSame('FR', $events[0]['country']);
    }

    #[Test]
    public function realtimeEventsApplyTheWatermark(): void
    {
        $client = new FakeClickHouseClient([[]]);

        (new RealtimeEventsHandler($client))($this->site, '2025-06-15T14:00:00.000Z');

        $sql = $client->selectCalls[0]['sql'];
        self::assertStringContainsString('ingested_at > {since:DateTime64(3)}', $sql);
        self::assertSame('2025-06-15 14:00:00.000', $client->selectCalls[0]['bindings']['since']);
    }
}
