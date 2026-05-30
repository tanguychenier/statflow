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

namespace App\Tests\Unit\Reporting\Infrastructure;

use App\Reporting\Application\Query\Analytics\EvaluateMetricQuery;
use App\Reporting\Application\Query\Analytics\FetchExportRowsQuery;
use App\Reporting\Application\Query\Analytics\MetricReading;
use App\Reporting\Application\Query\Analytics\TabularRows;
use App\Reporting\Infrastructure\Analytics\BusAnalyticsQueryGateway;
use App\Reporting\Infrastructure\Analytics\NullAnalyticsQueryGateway;
use App\Shared\Domain\Bus\Query\Query;
use App\Shared\Domain\Bus\Query\QueryBus;
use App\Shared\Domain\Bus\Query\QueryResult;
use App\Shared\Domain\ValueObject\Uuid;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BusAnalyticsQueryGateway::class)]
#[CoversClass(NullAnalyticsQueryGateway::class)]
#[CoversClass(FetchExportRowsQuery::class)]
#[CoversClass(EvaluateMetricQuery::class)]
#[CoversClass(TabularRows::class)]
#[CoversClass(MetricReading::class)]
final class AnalyticsQueryGatewayTest extends TestCase
{
    #[Test]
    public function busGatewayUnwrapsTabularRows(): void
    {
        $bus = $this->bus(new TabularRows([[
            'page' => '/home',
            'views' => 3,
        ]]));
        $gateway = new BusAnalyticsQueryGateway($bus);

        $rows = $gateway->fetchRows(Uuid::generate(), 'breakdown', [
            'property' => 'page',
        ]);

        self::assertSame([[
            'page' => '/home',
            'views' => 3,
        ]], $rows);
        self::assertInstanceOf(FetchExportRowsQuery::class, $bus->lastQuery); // @phpstan-ignore-line
    }

    #[Test]
    public function busGatewayUnwrapsMetricReading(): void
    {
        $bus = $this->bus(new MetricReading(120.0, 100.0));
        $gateway = new BusAnalyticsQueryGateway($bus);

        $reading = $gateway->evaluateMetric(Uuid::generate(), 'pageviews', [], 'previous_day');

        self::assertSame([
            'current' => 120.0,
            'baseline' => 100.0,
        ], $reading);
        self::assertInstanceOf(EvaluateMetricQuery::class, $bus->lastQuery); // @phpstan-ignore-line
    }

    #[Test]
    public function busGatewayFallsBackOnUnexpectedResult(): void
    {
        $bus = $this->bus(new class() implements QueryResult {});
        $gateway = new BusAnalyticsQueryGateway($bus);

        self::assertSame([], $gateway->fetchRows(Uuid::generate(), 'breakdown', []));
        self::assertSame([
            'current' => null,
            'baseline' => null,
        ], $gateway->evaluateMetric(Uuid::generate(), 'pageviews', [], null));
    }

    #[Test]
    public function nullGatewayReturnsEmptyResults(): void
    {
        $gateway = new NullAnalyticsQueryGateway();

        self::assertSame([], $gateway->fetchRows(Uuid::generate(), 'breakdown', []));
        self::assertSame([
            'current' => null,
            'baseline' => null,
        ], $gateway->evaluateMetric(Uuid::generate(), 'pageviews', [], null));
    }

    private function bus(QueryResult $result): QueryBus
    {
        return new class($result) implements QueryBus {
            public ?Query $lastQuery = null;

            public function __construct(
                private readonly QueryResult $result
            ) {
            }

            public function ask(Query $query): QueryResult
            {
                $this->lastQuery = $query;

                return $this->result;
            }
        };
    }
}
