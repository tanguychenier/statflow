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

use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\Pagination\Cursor;
use App\Shared\Domain\Pagination\PaginationRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PaginationRequest::class)]
final class PaginationRequestTest extends TestCase
{
    #[Test]
    public function itDefaultsToLimit20AndForwardDirection(): void
    {
        $request = PaginationRequest::fromPrimitives();

        self::assertSame(20, $request->limit);
        self::assertSame('next', $request->direction);
        self::assertTrue($request->isForward());
        self::assertNull($request->cursor);
    }

    #[Test]
    public function itDecodesAProvidedCursor(): void
    {
        $encoded = Cursor::fromPosition([
            'id' => 7,
        ])->encode();

        $request = PaginationRequest::fromPrimitives(cursor: $encoded, limit: 50, direction: 'prev');

        self::assertSame(50, $request->limit);
        self::assertFalse($request->isForward());
        self::assertNotNull($request->cursor);
        self::assertSame([
            'id' => 7,
        ], $request->cursor->position());
    }

    #[Test]
    public function itTreatsAnEmptyCursorAsAbsent(): void
    {
        self::assertNull(PaginationRequest::fromPrimitives(cursor: '')->cursor);
    }

    #[Test]
    public function itRejectsLimitBelowOne(): void
    {
        $exception = $this->captureValidation(static fn (): PaginationRequest => PaginationRequest::fromPrimitives(limit: 0));

        self::assertSame('limit', $exception->getErrors()[0]->field);
        self::assertSame('out_of_range', $exception->getErrors()[0]->code);
    }

    #[Test]
    public function itRejectsLimitAboveMax(): void
    {
        $this->expectException(ValidationException::class);

        PaginationRequest::fromPrimitives(limit: 101);
    }

    #[Test]
    public function itRejectsAnUnknownDirection(): void
    {
        $exception = $this->captureValidation(static fn (): PaginationRequest => PaginationRequest::fromPrimitives(direction: 'sideways'));

        self::assertSame('direction', $exception->getErrors()[0]->field);
        self::assertSame('invalid_enum_value', $exception->getErrors()[0]->code);
    }

    #[Test]
    public function itRejectsAMalformedCursor(): void
    {
        $exception = $this->captureValidation(static fn (): PaginationRequest => PaginationRequest::fromPrimitives(cursor: 'not base64 %%%'));

        self::assertSame('cursor', $exception->getErrors()[0]->field);
        self::assertSame('invalid_format', $exception->getErrors()[0]->code);
    }

    private function captureValidation(callable $callback): ValidationException
    {
        try {
            $callback();
        } catch (ValidationException $exception) {
            return $exception;
        }

        self::fail('Expected a ValidationException to be thrown.');
    }
}
