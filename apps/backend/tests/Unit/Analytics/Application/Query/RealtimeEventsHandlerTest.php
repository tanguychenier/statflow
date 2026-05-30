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
use App\Shared\Domain\ValueObject\Uuid;
use App\Tests\Unit\Analytics\Support\FakeClickHouseClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RealtimeEventsHandler::class)]
final class RealtimeEventsHandlerTest extends TestCase
{
    #[Test]
    public function itSelectsAndOrdersOnIngestedAtForAStableWatermark(): void
    {
        $client = new FakeClickHouseClient();
        $handler = new RealtimeEventsHandler($client);

        ($handler)(Uuid::generate());

        $sql = $client->lastSql();
        self::assertStringContainsString('ingested_at', $sql);
        self::assertStringContainsString('ORDER BY ingested_at DESC', $sql);
        self::assertStringNotContainsString('ORDER BY timestamp', $sql);
    }

    #[Test]
    public function itFiltersTheWatermarkOnIngestedAtNotClientTimestamp(): void
    {
        $client = new FakeClickHouseClient();
        $handler = new RealtimeEventsHandler($client);

        ($handler)(Uuid::generate(), '2026-05-16T20:30:00.123Z');

        $call = $client->selectCalls[0];
        self::assertStringContainsString('AND ingested_at > {since:DateTime64(3)}', $call['sql']);
        self::assertSame('2026-05-16 20:30:00.123', $call['bindings']['since']);
    }

    #[Test]
    public function itReturnsIngestedAtSoTheControllerCanWatermarkOnIt(): void
    {
        $client = new FakeClickHouseClient([[
            [
                'timestamp' => '2026-05-16 20:30:05.000',
                'ingested_at' => '2026-05-16 20:30:06.250',
                'event_name' => 'pageview',
                'pathname' => '/pricing',
                'referrer_source' => 'google',
                'country' => 'FR',
                'device_type' => 'desktop',
                'browser' => 'firefox',
            ],
        ]]);
        $handler = new RealtimeEventsHandler($client);

        $rows = ($handler)(Uuid::generate());

        self::assertCount(1, $rows);
        self::assertSame('2026-05-16T20:30:06.250Z', $rows[0]['ingested_at']);
        self::assertSame('2026-05-16T20:30:05.000Z', $rows[0]['timestamp']);
        self::assertSame('pageview', $rows[0]['event_name']);
    }
}
