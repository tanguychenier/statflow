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

namespace App\Tests\Unit\Identity\Infrastructure\Security;

use App\Identity\Infrastructure\Security\RandomTokenGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RandomTokenGenerator::class)]
final class RandomTokenGeneratorTest extends TestCase
{
    #[Test]
    public function itProducesUrlSafeTokens(): void
    {
        $token = (new RandomTokenGenerator())->generate(32);

        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $token);
    }

    #[Test]
    public function consecutiveTokensDiffer(): void
    {
        $generator = new RandomTokenGenerator();

        self::assertNotSame($generator->generate(), $generator->generate());
    }

    #[Test]
    public function longerByteLengthsProduceLongerTokens(): void
    {
        $generator = new RandomTokenGenerator();

        self::assertGreaterThan(
            strlen($generator->generate(8)),
            strlen($generator->generate(64)),
        );
    }
}
