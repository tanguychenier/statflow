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

use App\Reporting\Domain\Exception\InvalidEmailException;
use App\Reporting\Domain\ValueObject\EmailAddress;
use App\Reporting\Domain\ValueObject\EmailRecipientList;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EmailAddress::class)]
#[CoversClass(EmailRecipientList::class)]
#[CoversClass(InvalidEmailException::class)]
final class EmailTest extends TestCase
{
    #[Test]
    public function itNormalisesDomainToLowercase(): void
    {
        $email = EmailAddress::fromString('User@Example.COM');

        self::assertSame('User@example.com', $email->value());
        self::assertSame('User@example.com', (string) $email);
    }

    #[Test]
    public function itRejectsMalformedAddress(): void
    {
        $this->expectException(InvalidEmailException::class);
        EmailAddress::fromString('not-an-email');
    }

    #[Test]
    public function itRejectsBlankAddress(): void
    {
        $this->expectException(InvalidEmailException::class);
        EmailAddress::fromString('   ');
    }

    #[Test]
    public function equalsComparesNormalisedValue(): void
    {
        self::assertTrue(
            EmailAddress::fromString('a@b.com')->equals(EmailAddress::fromString('a@B.com')),
        );
    }

    #[Test]
    public function recipientListDeduplicatesCaseInsensitively(): void
    {
        $list = EmailRecipientList::fromStrings(['a@b.com', 'a@B.com', 'c@d.com']);

        self::assertSame(2, $list->count());
        self::assertSame(['a@b.com', 'c@d.com'], $list->toStrings());
        self::assertCount(2, $list->all());
    }

    #[Test]
    public function recipientListRejectsEmpty(): void
    {
        $this->expectException(InvalidEmailException::class);
        EmailRecipientList::fromStrings([]);
    }

    #[Test]
    public function recipientListRejectsTooMany(): void
    {
        $values = [];
        for ($i = 0; $i <= EmailRecipientList::MAX_ITEMS; ++$i) {
            $values[] = sprintf('user%d@example.com', $i);
        }

        $this->expectException(InvalidEmailException::class);
        EmailRecipientList::fromStrings($values);
    }
}
