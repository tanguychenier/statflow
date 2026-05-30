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

use App\Identity\Domain\Exception\InvalidEmailException;
use App\Identity\Domain\ValueObject\EmailAddress;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EmailAddress::class)]
final class EmailAddressTest extends TestCase
{
    #[Test]
    public function itNormalisesToLowercaseAndTrims(): void
    {
        $email = EmailAddress::fromString('  Alice@Example.COM  ');

        self::assertSame('alice@example.com', $email->getValue());
        self::assertSame('alice@example.com', (string) $email);
    }

    #[Test]
    public function itExposesTheDomain(): void
    {
        self::assertSame('example.com', EmailAddress::fromString('alice@example.com')->getDomain());
    }

    #[Test]
    public function itComparesByValue(): void
    {
        $a = EmailAddress::fromString('a@b.com');
        $b = EmailAddress::fromString('A@B.com');
        $c = EmailAddress::fromString('c@b.com');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }

    #[Test]
    public function itRejectsAnEmptyAddress(): void
    {
        $this->expectException(InvalidEmailException::class);

        EmailAddress::fromString('   ');
    }

    /**
     * @param non-empty-string $value
     */
    #[Test]
    #[DataProvider('malformedProvider')]
    public function itRejectsMalformedAddresses(string $value): void
    {
        $this->expectException(InvalidEmailException::class);

        EmailAddress::fromString($value);
    }

    /**
     * @return list<array{0: string}>
     */
    public static function malformedProvider(): array
    {
        return [
            ['not-an-email'],
            ['missing@domain'],
            ['@example.com'],
            ['spaces in@email.com'],
            ['two@@at.com'],
        ];
    }

    #[Test]
    public function itRejectsAddressesOverTheMaxLength(): void
    {
        $this->expectException(InvalidEmailException::class);

        $local = str_repeat('a', 250);
        EmailAddress::fromString($local . '@example.com');
    }

    #[Test]
    public function theValidationFailureCarriesAFieldError(): void
    {
        try {
            EmailAddress::fromString('bad');
            self::fail('Expected InvalidEmailException.');
        } catch (InvalidEmailException $e) {
            self::assertSame(422, $e->getStatusCode());
            self::assertSame('https://statflow.io/errors/validation-failed', $e->getType());
            self::assertNotEmpty($e->getErrors());
            self::assertSame('email', $e->getErrors()[0]->field);
        }
    }
}
