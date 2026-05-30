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

namespace App\Tests\Unit\Shared\Domain\Pagination;

use App\Shared\Domain\Pagination\Cursor;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Cursor::class)]
final class CursorTest extends TestCase
{
    #[Test]
    public function itRoundTripsAPosition(): void
    {
        $cursor = Cursor::fromPosition([
            'id' => 123,
            'created_at' => '2025-06-15T14:30:00Z',
        ]);

        $decoded = Cursor::decode($cursor->encode());

        self::assertSame([
            'id' => 123,
            'created_at' => '2025-06-15T14:30:00Z',
        ], $decoded->position());
    }

    #[Test]
    public function theEncodedFormIsUrlSafeAndUnpadded(): void
    {
        $encoded = Cursor::fromPosition([
            'id' => 999_999_999,
        ])->encode();

        self::assertDoesNotMatchRegularExpression('/[+\/=]/', $encoded);
    }

    #[Test]
    public function itRejectsNonBase64(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Cursor::decode('not base64 %%%');
    }

    #[Test]
    public function itRejectsBase64ThatIsNotAJsonObject(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Cursor::decode(rtrim(strtr(base64_encode('42'), '+/', '-_'), '='));
    }
}
