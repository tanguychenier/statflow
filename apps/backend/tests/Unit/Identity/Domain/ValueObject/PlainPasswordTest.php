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

namespace App\Tests\Unit\Identity\Domain\ValueObject;

use App\Identity\Domain\Exception\WeakPasswordException;
use App\Identity\Domain\ValueObject\PlainPassword;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PlainPassword::class)]
final class PlainPasswordTest extends TestCase
{
    #[Test]
    public function itAcceptsAPasswordAtTheMinimumLength(): void
    {
        $password = PlainPassword::fromString('123456789012');

        self::assertSame('123456789012', $password->reveal());
    }

    #[Test]
    public function itRejectsAPasswordShorterThanTwelveCharacters(): void
    {
        $this->expectException(WeakPasswordException::class);

        PlainPassword::fromString('short');
    }

    #[Test]
    public function itRejectsAPasswordLongerThanTheMaximum(): void
    {
        $this->expectException(WeakPasswordException::class);

        PlainPassword::fromString(str_repeat('a', 129));
    }

    #[Test]
    public function verificationWrapperBypassesThePolicy(): void
    {
        $password = PlainPassword::forVerification('short');

        self::assertSame('short', $password->reveal());
    }

    #[Test]
    public function debugInfoMasksThePlaintext(): void
    {
        $password = PlainPassword::fromString('correcthorsebattery');

        self::assertSame([
            'value' => '********',
        ], $password->__debugInfo());
    }

    #[Test]
    public function theWeaknessFailureIsAValidationError(): void
    {
        try {
            PlainPassword::fromString('tiny');
            self::fail('Expected WeakPasswordException.');
        } catch (WeakPasswordException $e) {
            self::assertSame(422, $e->getStatusCode());
            self::assertSame('password', $e->getErrors()[0]->field);
            self::assertSame('min_length_not_met', $e->getErrors()[0]->code);
        }
    }
}
