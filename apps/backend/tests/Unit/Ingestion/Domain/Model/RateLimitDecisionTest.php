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

namespace App\Tests\Unit\Ingestion\Domain\Model;

use App\Ingestion\Domain\Model\RateLimitDecision;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RateLimitDecision::class)]
final class RateLimitDecisionTest extends TestCase
{
    #[Test]
    public function allowedHasNoRetryAfter(): void
    {
        $decision = RateLimitDecision::allowed();

        self::assertTrue($decision->allowed);
        self::assertSame(0, $decision->retryAfterSeconds);
    }

    #[Test]
    public function deniedCarriesAtLeastOneSecondRetryAfter(): void
    {
        self::assertSame(30, RateLimitDecision::denied(30)->retryAfterSeconds);
        self::assertFalse(RateLimitDecision::denied(30)->allowed);
        self::assertSame(1, RateLimitDecision::denied(0)->retryAfterSeconds);
        self::assertSame(1, RateLimitDecision::denied(-5)->retryAfterSeconds);
    }
}
