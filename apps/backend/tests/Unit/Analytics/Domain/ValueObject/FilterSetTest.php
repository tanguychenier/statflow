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
use App\Analytics\Domain\ValueObject\FilterCombination;
use App\Analytics\Domain\ValueObject\FilterOperator;
use App\Analytics\Domain\ValueObject\FilterSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FilterSet::class)]
#[CoversClass(FilterCombination::class)]
final class FilterSetTest extends TestCase
{
    #[Test]
    public function anEmptySetReportsEmpty(): void
    {
        self::assertTrue(FilterSet::empty()->isEmpty());
    }

    #[Test]
    public function itBuildsFromAnArrayWithACombination(): void
    {
        $set = FilterSet::fromArray(
            [[
                'property' => 'device_type',
                'operator' => 'eq',
                'value' => 'mobile',
            ]],
            'or',
        );

        self::assertFalse($set->isEmpty());
        self::assertSame(FilterCombination::Or, $set->combination);
        self::assertCount(1, $set->filters);
    }

    #[Test]
    public function itRejectsAnUnknownCombination(): void
    {
        $this->expectException(InvalidFilter::class);

        FilterSet::fromArray([[
            'property' => 'device_type',
            'operator' => 'eq',
            'value' => 'mobile',
        ]], 'xor');
    }

    #[Test]
    public function andGroupsSkipsEmptySets(): void
    {
        $a = FilterSet::of([Filter::create(Dimension::Pathname, FilterOperator::Eq, '/x')]);
        $empty = FilterSet::empty();

        self::assertSame([$a], $a->andGroups($empty));
        self::assertSame([$a], $empty->andGroups($a));
        self::assertSame([], $empty->andGroups($empty));
    }

    #[Test]
    public function andGroupsKeepsBothNonEmptySets(): void
    {
        $a = FilterSet::of([Filter::create(Dimension::Pathname, FilterOperator::Eq, '/x')]);
        $b = FilterSet::of([Filter::create(Dimension::DeviceType, FilterOperator::Eq, 'mobile')]);

        self::assertSame([$a, $b], $a->andGroups($b));
    }
}
