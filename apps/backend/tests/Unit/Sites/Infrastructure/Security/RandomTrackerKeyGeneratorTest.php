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

namespace App\Tests\Unit\Sites\Infrastructure\Security;

use App\Sites\Infrastructure\Clock\SystemClock;
use App\Sites\Infrastructure\Security\RandomTrackerKeyGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RandomTrackerKeyGenerator::class)]
#[CoversClass(SystemClock::class)]
final class RandomTrackerKeyGeneratorTest extends TestCase
{
    #[Test]
    public function itGeneratesWellFormedUniqueKeys(): void
    {
        $generator = new RandomTrackerKeyGenerator();

        $first = $generator->generate();
        $second = $generator->generate();

        self::assertStringStartsWith('stk_', $first->value());
        self::assertMatchesRegularExpression('/^stk_[A-Za-z0-9_-]{32,64}$/', $first->value());
        self::assertNotSame($first->value(), $second->value());
    }

    #[Test]
    public function systemClockReturnsUtc(): void
    {
        $now = (new SystemClock())->now();

        self::assertSame('UTC', $now->getTimezone()->getName());
    }
}
