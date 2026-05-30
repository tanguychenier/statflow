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

namespace App\Tests\Unit\Analytics\Domain\ValueObject;

use App\Analytics\Domain\Exception\InvalidFilter;
use App\Analytics\Domain\ValueObject\Dimension;
use App\Analytics\Domain\ValueObject\Filter;
use App\Analytics\Domain\ValueObject\FilterOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Filter::class)]
#[CoversClass(FilterOperator::class)]
#[CoversClass(InvalidFilter::class)]
final class FilterTest extends TestCase
{
    #[Test]
    public function itBuildsAScalarFilterFromAnArray(): void
    {
        $filter = Filter::fromArray([
            'property' => 'pathname',
            'operator' => 'eq',
            'value' => '/pricing',
        ]);

        self::assertSame(Dimension::Pathname, $filter->dimension);
        self::assertSame(FilterOperator::Eq, $filter->operator);
        self::assertSame('/pricing', $filter->scalarValue());
    }

    #[Test]
    public function itBuildsAListFilter(): void
    {
        $filter = Filter::fromArray([
            'property' => 'country',
            'operator' => 'in',
            'value' => ['FR', 'DE'],
        ]);

        self::assertSame(['FR', 'DE'], $filter->listValue());
    }

    #[Test]
    public function itCoercesNumericListValuesToStrings(): void
    {
        $filter = Filter::create(Dimension::Browser, FilterOperator::In, [1, 2]);

        self::assertSame(['1', '2'], $filter->listValue());
    }

    #[Test]
    public function itRejectsAMissingField(): void
    {
        $this->expectException(InvalidFilter::class);

        Filter::fromArray([
            'property' => 'pathname',
            'operator' => 'eq',
        ]);
    }

    #[Test]
    public function itRejectsAnUnknownOperator(): void
    {
        $this->expectException(InvalidFilter::class);

        Filter::fromArray([
            'property' => 'pathname',
            'operator' => 'matches',
            'value' => 'x',
        ]);
    }

    #[Test]
    public function itRejectsAListOperatorWithAScalarValue(): void
    {
        $this->expectException(InvalidFilter::class);

        Filter::create(Dimension::Country, FilterOperator::In, 'FR');
    }

    #[Test]
    public function itRejectsAScalarOperatorWithAListValue(): void
    {
        $filter = Filter::create(Dimension::Pathname, FilterOperator::Eq, '/x');

        $this->expectException(InvalidFilter::class);

        $filter->listValue();
    }

    #[Test]
    public function itRejectsAnEmptyListForInOperator(): void
    {
        $this->expectException(InvalidFilter::class);

        Filter::create(Dimension::Country, FilterOperator::In, []);
    }

    #[Test]
    public function itRejectsAnUnsupportedScalarType(): void
    {
        $this->expectException(InvalidFilter::class);

        Filter::create(Dimension::Pathname, FilterOperator::Eq, [
            'nested' => true,
        ]);
    }

    #[Test]
    public function itRejectsANonStringPropertyField(): void
    {
        $this->expectException(InvalidFilter::class);

        Filter::fromArray([
            'property' => 123,
            'operator' => 'eq',
            'value' => 'x',
        ]);
    }
}
