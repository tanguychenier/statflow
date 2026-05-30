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

namespace App\Tests\Unit\Analytics\Domain\ValueObject;

use App\Analytics\Domain\Exception\UnknownMetric;
use App\Analytics\Domain\ValueObject\Metric;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Metric::class)]
#[CoversClass(UnknownMetric::class)]
final class MetricTest extends TestCase
{
    #[Test]
    public function itParsesEveryKnownMetric(): void
    {
        self::assertSame(Metric::Visitors, Metric::fromString('visitors'));
        self::assertSame(Metric::ConversionRate, Metric::fromString('conversion_rate'));
    }

    #[Test]
    public function itRejectsAnUnknownMetric(): void
    {
        $this->expectException(UnknownMetric::class);

        Metric::fromString('clicks_per_minute');
    }

    #[Test]
    public function itExposesTheDefaultMetricSet(): void
    {
        $defaults = Metric::defaults();

        self::assertContains(Metric::Visitors, $defaults);
        self::assertContains(Metric::BounceRate, $defaults);
        self::assertNotContains(Metric::ConversionRate, $defaults);
    }

    #[Test]
    #[DataProvider('sessionScopedCases')]
    public function itFlagsSessionScopedMetrics(Metric $metric, bool $expected): void
    {
        self::assertSame($expected, $metric->isSessionScoped());
    }

    /**
     * @return iterable<string, array{Metric, bool}>
     */
    public static function sessionScopedCases(): iterable
    {
        yield 'bounce rate' => [Metric::BounceRate, true];
        yield 'avg duration' => [Metric::AvgDuration, true];
        yield 'visitors' => [Metric::Visitors, false];
        yield 'pageviews' => [Metric::Pageviews, false];
    }
}
