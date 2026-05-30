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

namespace App\Tests\Unit\Shared\Domain\Exception;

use App\Shared\Domain\Exception\FieldError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FieldError::class)]
final class FieldErrorTest extends TestCase
{
    #[Test]
    public function itExposesItsMembers(): void
    {
        $error = new FieldError('utm_source', 'max_length_exceeded', 'utm_source must not exceed 200 characters.');

        self::assertSame('utm_source', $error->field);
        self::assertSame('max_length_exceeded', $error->code);
        self::assertSame('utm_source must not exceed 200 characters.', $error->message);
    }

    #[Test]
    public function itSerialisesToTheContractShape(): void
    {
        $error = new FieldError('pathname', 'required', 'pathname is required.');

        self::assertSame(
            [
                'field' => 'pathname',
                'code' => 'required',
                'message' => 'pathname is required.',
            ],
            $error->toArray(),
        );
    }
}
