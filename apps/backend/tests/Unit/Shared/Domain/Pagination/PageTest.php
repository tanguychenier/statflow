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
use App\Shared\Domain\Pagination\Page;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Page::class)]
final class PageTest extends TestCase
{
    #[Test]
    public function aPageWithBothCursorsReportsBothDirections(): void
    {
        $page = Page::create(
            items: ['a', 'b'],
            limit: 20,
            nextCursor: Cursor::fromPosition([
                'id' => 2,
            ]),
            prevCursor: Cursor::fromPosition([
                'id' => 1,
            ]),
        );

        self::assertSame(['a', 'b'], $page->items);
        self::assertSame(20, $page->limit);
        self::assertTrue($page->hasNext());
        self::assertTrue($page->hasPrev());
    }

    #[Test]
    public function aPageWithoutCursorsReportsNoMorePages(): void
    {
        $page = Page::create(items: ['only'], limit: 10);

        self::assertFalse($page->hasNext());
        self::assertFalse($page->hasPrev());
        self::assertNull($page->nextCursor);
        self::assertNull($page->prevCursor);
    }

    #[Test]
    public function anEmptyPageHasNoItems(): void
    {
        $page = Page::empty(25);

        self::assertSame([], $page->items);
        self::assertSame(25, $page->limit);
        self::assertFalse($page->hasNext());
        self::assertFalse($page->hasPrev());
    }
}
