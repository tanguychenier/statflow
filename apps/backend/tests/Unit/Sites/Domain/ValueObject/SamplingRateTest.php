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

namespace App\Tests\Unit\Sites\Domain\ValueObject;

use App\Sites\Domain\Exception\InvalidSamplingRateException;
use App\Sites\Domain\ValueObject\SamplingRate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SamplingRate::class)]
#[CoversClass(InvalidSamplingRateException::class)]
final class SamplingRateTest extends TestCase
{
    #[Test]
    public function itAcceptsBoundaryValues(): void
    {
        self::assertSame(0.0, SamplingRate::fromFloat(0.0)->value());
        self::assertSame(1.0, SamplingRate::fromFloat(1.0)->value());
        self::assertSame(1.0, SamplingRate::default()->value());
    }

    #[Test]
    public function itRoundsToThreeDecimals(): void
    {
        self::assertSame(0.123, SamplingRate::fromFloat(0.12349)->value());
        self::assertSame(0.25, SamplingRate::fromFloat(0.25)->value());
    }

    #[Test]
    public function itRejectsRateBelowZero(): void
    {
        $this->expectException(InvalidSamplingRateException::class);

        SamplingRate::fromFloat(-0.01);
    }

    #[Test]
    public function itRejectsRateAboveOne(): void
    {
        $this->expectException(InvalidSamplingRateException::class);

        SamplingRate::fromFloat(1.01);
    }
}
