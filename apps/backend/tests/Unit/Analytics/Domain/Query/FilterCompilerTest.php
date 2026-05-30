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

namespace App\Tests\Unit\Analytics\Domain\Query;

use App\Analytics\Domain\Exception\InvalidFilter;
use App\Analytics\Domain\Query\BoundExpression;
use App\Analytics\Domain\Query\FilterCompiler;
use App\Analytics\Domain\Query\Grain;
use App\Analytics\Domain\Query\ParameterBag;
use App\Analytics\Domain\ValueObject\Dimension;
use App\Analytics\Domain\ValueObject\Filter;
use App\Analytics\Domain\ValueObject\FilterCombination;
use App\Analytics\Domain\ValueObject\FilterOperator;
use App\Analytics\Domain\ValueObject\FilterSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FilterCompiler::class)]
#[CoversClass(BoundExpression::class)]
#[CoversClass(ParameterBag::class)]
final class FilterCompilerTest extends TestCase
{
    #[Test]
    public function anEmptyGroupCompilesToAlwaysTrue(): void
    {
        $expr = $this->compile([]);

        self::assertSame('1 = 1', $expr->sql);
        self::assertSame([], $expr->bindings);
    }

    #[Test]
    public function itCompilesAnAndCombinationWithBoundOperands(): void
    {
        $set = FilterSet::of([
            Filter::create(Dimension::DeviceType, FilterOperator::Eq, 'mobile'),
            Filter::create(Dimension::Country, FilterOperator::In, ['FR', 'DE']),
        ], FilterCombination::And);

        $expr = $this->compile([$set]);

        self::assertSame(
            '(device_type = {p0:String} AND country_code IN {p1:Array(String)})',
            $expr->sql,
        );
        self::assertSame([
            'p0' => 'mobile',
            'p1' => ['FR', 'DE'],
        ], $expr->bindings);
    }

    #[Test]
    public function itJoinsSeveralGroupsWithTopLevelAnd(): void
    {
        $a = FilterSet::of([Filter::create(Dimension::Pathname, FilterOperator::StartsWith, '/blog')]);
        $b = FilterSet::of([Filter::create(Dimension::Browser, FilterOperator::Neq, 'IE')], FilterCombination::Or);

        $expr = $this->compile([$a, $b]);

        self::assertSame(
            '(startsWith(pathname, {p0:String})) AND (browser != {p1:String})',
            $expr->sql,
        );
    }

    #[Test]
    #[DataProvider('operatorCases')]
    public function itRendersEachOperator(FilterOperator $operator, mixed $value, string $expectedFragment): void
    {
        $set = FilterSet::of([Filter::create(Dimension::Pathname, $operator, $value)]);

        self::assertSame('(' . $expectedFragment . ')', $this->compile([$set])->sql);
    }

    /**
     * @return iterable<string, array{FilterOperator, mixed, string}>
     */
    public static function operatorCases(): iterable
    {
        yield 'eq' => [FilterOperator::Eq, '/a', 'pathname = {p0:String}'];
        yield 'neq' => [FilterOperator::Neq, '/a', 'pathname != {p0:String}'];
        yield 'in' => [FilterOperator::In, ['/a'], 'pathname IN {p0:Array(String)}'];
        yield 'not_in' => [FilterOperator::NotIn, ['/a'], 'pathname NOT IN {p0:Array(String)}'];
        yield 'contains' => [FilterOperator::Contains, 'a', 'positionCaseInsensitive(pathname, {p0:String}) > 0'];
        yield 'not_contains' => [FilterOperator::NotContains, 'a', 'positionCaseInsensitive(pathname, {p0:String}) = 0'];
        yield 'starts_with' => [FilterOperator::StartsWith, '/a', 'startsWith(pathname, {p0:String})'];
        yield 'gt' => [FilterOperator::Gt, 5, 'pathname > {p0:Int64}'];
        yield 'gte' => [FilterOperator::Gte, 5, 'pathname >= {p0:Int64}'];
        yield 'lt' => [FilterOperator::Lt, 5, 'pathname < {p0:Int64}'];
        yield 'lte' => [FilterOperator::Lte, 5, 'pathname <= {p0:Int64}'];
    }

    #[Test]
    public function itBindsFloatAndBooleanScalars(): void
    {
        $set = FilterSet::of([
            Filter::create(Dimension::Pathname, FilterOperator::Gt, 1.5),
            Filter::create(Dimension::Pathname, FilterOperator::Eq, true),
        ]);

        $expr = $this->compile([$set]);

        self::assertSame('(pathname > {p0:Float64} AND pathname = {p1:UInt8})', $expr->sql);
        self::assertSame([
            'p0' => 1.5,
            'p1' => 1,
        ], $expr->bindings);
    }

    #[Test]
    public function itRejectsASessionScopedDimensionOnTheEventGrain(): void
    {
        $set = FilterSet::of([Filter::create(Dimension::EntryPage, FilterOperator::Eq, '/home')]);

        $this->expectException(InvalidFilter::class);

        $this->compile([$set]);
    }

    #[Test]
    public function itAllowsASessionScopedDimensionOnTheSessionGrain(): void
    {
        $set = FilterSet::of([Filter::create(Dimension::EntryPage, FilterOperator::Eq, '/home')]);

        $expr = (new FilterCompiler(new ParameterBag()))->compileGroups(Grain::Sessions, $set);

        self::assertSame('(entry_page = {p0:String})', $expr->sql);
    }

    /**
     * @param list<FilterSet> $groups
     */
    private function compile(array $groups): BoundExpression
    {
        return (new FilterCompiler(new ParameterBag()))->compileGroups(Grain::Events, ...$groups);
    }
}
