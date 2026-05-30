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

namespace App\Tests\Unit\Sites\Domain\ValueObject;

use App\Sites\Domain\Exception\InvalidRetentionException;
use App\Sites\Domain\ValueObject\RetentionDays;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RetentionDays::class)]
#[CoversClass(InvalidRetentionException::class)]
final class RetentionDaysTest extends TestCase
{
    #[Test]
    #[DataProvider('validValues')]
    public function itAcceptsInRangeValues(int $value): void
    {
        self::assertSame($value, RetentionDays::fromInt($value)->value());
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function validValues(): iterable
    {
        yield 'minimum' => [RetentionDays::MIN];
        yield 'maximum' => [RetentionDays::MAX];
        yield 'default' => [RetentionDays::DEFAULT];
        yield 'mid' => [180];
    }

    #[Test]
    #[DataProvider('outOfRange')]
    public function itRejectsOutOfRangeValues(int $value): void
    {
        $this->expectException(InvalidRetentionException::class);

        RetentionDays::fromInt($value);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function outOfRange(): iterable
    {
        yield 'below min' => [RetentionDays::MIN - 1];
        yield 'above max' => [RetentionDays::MAX + 1];
        yield 'zero' => [0];
        yield 'negative' => [-5];
    }
}
