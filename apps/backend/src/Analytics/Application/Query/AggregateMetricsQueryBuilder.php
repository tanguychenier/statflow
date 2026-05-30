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

namespace App\Analytics\Application\Query;

use App\Analytics\Domain\Query\FilterCompiler;
use App\Analytics\Domain\Query\Grain;
use App\Analytics\Domain\Query\ParameterBag;
use App\Analytics\Domain\ValueObject\DateRange;
use App\Analytics\Domain\ValueObject\FilterSet;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Builds the two ClickHouse statements that back an aggregate-metrics query: one
 * over `events` (visitors, pageviews, sessions, events, conversions) and one
 * over `sessions FINAL` (bounce rate, average duration).
 *
 * Splitting the work matches the schema: session-grained metrics cannot be
 * derived from a single INSERT block / the raw event stream (clickhouse.md §6).
 */
final class AggregateMetricsQueryBuilder
{
    /**
     * @param list<FilterSet> $filterGroups
     */
    public function eventStatement(Uuid $siteId, DateRange $range, array $filterGroups): SqlClause
    {
        $params = new ParameterBag();
        [$siteBind, $startBind, $endBind] = $this->commonBindings($params, $siteId, $range);

        $where = (new FilterCompiler($params))->compileGroups(Grain::Events, ...$filterGroups);

        $sql = sprintf(
            'SELECT '
            . 'uniq(visitor_id) AS visitors, '
            . "countIf(event_name = 'pageview') AS pageviews, "
            . 'uniq(session_id) AS sessions, '
            . 'count() AS events, '
            . "uniqIf(session_id, event_name = 'conversion') AS converting_sessions "
            . 'FROM statflow.events '
            . 'WHERE site_id = %s AND timestamp >= %s AND timestamp < %s AND (%s)',
            $siteBind,
            $startBind,
            $endBind,
            $where->sql,
        );

        return new SqlClause($sql, $params->all());
    }

    /**
     * @param list<FilterSet> $filterGroups
     */
    public function sessionStatement(Uuid $siteId, DateRange $range, array $filterGroups): SqlClause
    {
        $params = new ParameterBag();
        [$siteBind, $startBind, $endBind] = $this->commonBindings($params, $siteId, $range);

        $where = (new FilterCompiler($params))->compileGroups(Grain::Sessions, ...$filterGroups);

        $sql = sprintf(
            'SELECT '
            . 'count() AS total_sessions, '
            . 'countIf(bounce = 1) AS bounced_sessions, '
            . 'avg(duration_s) AS avg_duration '
            . 'FROM statflow.sessions FINAL '
            . 'WHERE site_id = %s AND started_at >= %s AND started_at < %s AND (%s)',
            $siteBind,
            $startBind,
            $endBind,
            $where->sql,
        );

        return new SqlClause($sql, $params->all());
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function commonBindings(ParameterBag $params, Uuid $siteId, DateRange $range): array
    {
        return [
            $params->bind('UUID', (string) $siteId, 'site'),
            $params->bind('DateTime64(3)', $range->fromDate() . ' 00:00:00', 'start'),
            $params->bind('DateTime64(3)', $range->exclusiveEnd()->format('Y-m-d') . ' 00:00:00', 'end'),
        ];
    }
}
