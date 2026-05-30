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

namespace App\Tests\Unit\Reporting\Domain\ValueObject;

use App\Reporting\Domain\Exception\InvalidAlertException;
use App\Reporting\Domain\ValueObject\Threshold;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Threshold::class)]
final class ThresholdTest extends TestCase
{
    #[Test]
    public function itRoundsToFourDecimals(): void
    {
        self::assertSame(1.2346, Threshold::fromFloat(1.23456)->value());
        self::assertSame(-5.0, Threshold::fromFloat(-5.0)->value());
    }

    #[Test]
    public function itRejectsNonFinite(): void
    {
        $this->expectException(InvalidAlertException::class);
        Threshold::fromFloat(INF);
    }

    #[Test]
    public function itRejectsOutOfRange(): void
    {
        $this->expectException(InvalidAlertException::class);
        Threshold::fromFloat(Threshold::MAX_ABS * 10);
    }
}
