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

namespace App\Shared\Infrastructure\ClickHouse;

/**
 * Splits a ClickHouse HTTP DSN into the base endpoint and the query defaults the
 * HTTP interface needs.
 *
 * ClickHouse's HTTP interface only handles requests at `/`; a database carried in
 * the DSN path (e.g. `http://user:pass@host:8123/statflow`) must be moved to the
 * `database` query parameter, otherwise the server answers 404. Any userinfo and
 * port are preserved on the endpoint so the HTTP client keeps authenticating.
 */
final class ClickHouseDsn
{
    /**
     * @return array{0: string, 1: array<string, string>} endpoint, query defaults
     */
    public static function split(string $dsn): array
    {
        $parts = parse_url($dsn);
        if ($parts === false || !isset($parts['host'])) {
            return [$dsn, []];
        }

        $endpoint = ($parts['scheme'] ?? 'http') . '://';

        if (isset($parts['user'])) {
            $endpoint .= rawurlencode($parts['user']);
            if (isset($parts['pass'])) {
                $endpoint .= ':' . rawurlencode($parts['pass']);
            }
            $endpoint .= '@';
        }

        $endpoint .= $parts['host'];
        if (isset($parts['port'])) {
            $endpoint .= ':' . $parts['port'];
        }
        $endpoint .= '/';

        $query = [];
        $database = trim($parts['path'] ?? '', '/');
        if ($database !== '') {
            $query['database'] = $database;
        }

        return [$endpoint, $query];
    }
}
