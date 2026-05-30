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

namespace App\Analytics\Infrastructure\ClickHouse;

use App\Analytics\Domain\Port\ClickHouseClientPort;
use App\Shared\Infrastructure\ClickHouse\ClickHouseDsn;
use RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * ClickHouse HTTP interface adapter.
 *
 * Uses ClickHouse's HTTP endpoint (default port 8123). SELECTs return the JSON
 * format; INSERTs use JSONEachRow. Named bindings are sent as ClickHouse
 * server-side query parameters (`param_<name>`), so the `{name:Type}`
 * placeholders in the SQL are substituted by the server with full
 * type-checking and escaping — there is no client-side interpolation and thus
 * no SQL-injection surface.
 */
final readonly class HttpClickHouseClient implements ClickHouseClientPort
{
    private const FORMAT_SELECT = ' FORMAT JSON';

    /**
     * Base HTTP endpoint (scheme://[user:pass@]host:port/) with the database path
     * segment stripped — ClickHouse's HTTP interface only handles requests at `/`.
     */
    private string $endpoint;

    /**
     * @var array<string, string> Query defaults (e.g. the database) carried in the DSN path.
     */
    private array $endpointQuery;

    public function __construct(
        private HttpClientInterface $httpClient,
        string $dsn,
    ) {
        [$this->endpoint, $this->endpointQuery] = ClickHouseDsn::split($dsn);
    }

    public function select(string $query, array $bindings = []): array
    {
        $response = $this->httpClient->request('POST', $this->endpoint, [
            'query' => array_merge(
                $this->endpointQuery,
                [
                    'query' => $query . self::FORMAT_SELECT,
                ],
                $this->encodeBindings($bindings),
            ),
            'headers' => [
                'Content-Type' => 'text/plain',
            ],
        ]);

        $status = $response->getStatusCode();
        if ($status !== 200) {
            throw new RuntimeException(sprintf(
                'ClickHouse query failed (HTTP %d): %s',
                $status,
                $response->getContent(false),
            ));
        }

        /** @var array{data?: list<array<string, int|float|string|null>>} $decoded */
        $decoded = $response->toArray(false);

        return $decoded['data'] ?? [];
    }

    public function insert(string $table, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $ndjson = implode("\n", array_map(
            static fn (array $row): string => json_encode($row, JSON_THROW_ON_ERROR),
            $rows,
        ));

        $response = $this->httpClient->request('POST', $this->endpoint, [
            'query' => array_merge($this->endpointQuery, [
                'query' => sprintf('INSERT INTO %s FORMAT JSONEachRow', $table),
            ]),
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => $ndjson,
        ]);

        $status = $response->getStatusCode();
        if ($status !== 200) {
            throw new RuntimeException(sprintf(
                'ClickHouse insert failed (HTTP %d): %s',
                $status,
                $response->getContent(false),
            ));
        }
    }

    /**
     * Map named bindings to ClickHouse `param_<name>` query parameters. Arrays
     * are encoded in ClickHouse array literal syntax so `{name:Array(String)}`
     * placeholders resolve correctly.
     *
     * @param array<string, scalar|list<scalar>> $bindings
     *
     * @return array<string, string>
     */
    private function encodeBindings(array $bindings): array
    {
        $encoded = [];
        foreach ($bindings as $name => $value) {
            $encoded['param_' . $name] = $this->encodeValue($value);
        }

        return $encoded;
    }

    /**
     * @param scalar|list<scalar> $value
     */
    private function encodeValue(mixed $value): string
    {
        if (is_array($value)) {
            return '[' . implode(',', array_map($this->encodeArrayItem(...), $value)) . ']';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    /**
     * ClickHouse array parameters are quoted per-element with single quotes;
     * embedded quotes and backslashes are escaped.
     *
     * @param scalar $item
     */
    private function encodeArrayItem(mixed $item): string
    {
        if (is_int($item) || is_float($item)) {
            return (string) $item;
        }

        $escaped = str_replace(['\\', "'"], ['\\\\', "\\'"], (string) $item);

        return "'" . $escaped . "'";
    }
}
