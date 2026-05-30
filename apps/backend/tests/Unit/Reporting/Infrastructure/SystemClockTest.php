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

use App\Reporting\Infrastructure\Clock\SystemClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SystemClock::class)]
final class SystemClockTest extends TestCase
{
    #[Test]
    public function itReturnsUtcTimeCloseToNow(): void
    {
        $clock = new SystemClock();

        $now = $clock->now();

        self::assertSame('UTC', $now->getTimezone()->getName());
        self::assertLessThanOrEqual(2, abs($now->getTimestamp() - time()));
    }
}
