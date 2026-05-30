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

namespace App\Tests\Integration\Analytics\Infrastructure\ClickHouse;

use App\Analytics\Infrastructure\ClickHouse\HttpClickHouseClient;
use App\Shared\Infrastructure\ClickHouse\ClickHouseDsn;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpClient\HttpClient;

/**
 * Runs against a real ClickHouse (container phase). Verifies that named bindings
 * are passed as server-side query parameters (`param_*`) and substituted by the
 * server with correct typing, including array parameters and the string-escaping
 * that protects against injection.
 */
#[CoversClass(HttpClickHouseClient::class)]
final class HttpClickHouseClientTest extends TestCase
{
    private string $dsn;

    protected function setUp(): void
    {
        $envDsn = $_SERVER['CLICKHOUSE_DSN'] ?? getenv('CLICKHOUSE_DSN');
        $this->dsn = is_string($envDsn) && $envDsn !== '' ? $envDsn : 'http://127.0.0.1:8123';
        [$endpoint, $endpointQuery] = ClickHouseDsn::split($this->dsn);

        try {
            $ping = HttpClient::create()->request('GET', $endpoint, [
                'query' => array_merge($endpointQuery, [
                    'query' => 'SELECT 1',
                ]),
            ]);
            if ($ping->getStatusCode() !== 200) {
                self::markTestSkipped('ClickHouse is not available.');
            }
        } catch (\Throwable $e) {
            self::markTestSkipped('ClickHouse is not available: ' . $e->getMessage());
        }
    }

    #[Test]
    public function itBindsAScalarParameter(): void
    {
        $rows = $this->client()->select('SELECT {n:UInt32} AS value', [
            'n' => 7,
        ]);

        self::assertSame('7', (string) $rows[0]['value']);
    }

    #[Test]
    public function itBindsAStringParameterSafely(): void
    {
        $malicious = "'; DROP TABLE statflow.events; --";

        $rows = $this->client()->select('SELECT {s:String} AS value', [
            's' => $malicious,
        ]);

        self::assertSame($malicious, (string) $rows[0]['value']);
    }

    #[Test]
    public function itBindsAnArrayParameter(): void
    {
        $rows = $this->client()->select(
            'SELECT arrayJoin({xs:Array(String)}) AS value ORDER BY value',
            [
                'xs' => ['alpha', "o'brien", 'gamma'],
            ],
        );

        self::assertSame(['alpha', 'gamma', "o'brien"], array_map(static fn (array $r): string => (string) $r['value'], $rows));
    }

    #[Test]
    public function itBindsABooleanAsUInt8(): void
    {
        $rows = $this->client()->select('SELECT {flag:UInt8} AS value', [
            'flag' => true,
        ]);

        self::assertSame('1', (string) $rows[0]['value']);
    }

    #[Test]
    public function itThrowsOnAFailedQuery(): void
    {
        $this->expectException(RuntimeException::class);

        $this->client()->select('SELECT this_is_not_valid_sql FROM nowhere');
    }

    private function client(): HttpClickHouseClient
    {
        return new HttpClickHouseClient(HttpClient::create(), $this->dsn);
    }
}
