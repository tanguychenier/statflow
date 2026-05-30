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

namespace App\Tests\Unit\Analytics\Application\Query;

use App\Analytics\Application\Query\SegmentResolver;
use App\Analytics\Domain\Exception\SegmentNotFound;
use App\Analytics\Domain\Model\Segment;
use App\Analytics\Domain\ValueObject\Dimension;
use App\Analytics\Domain\ValueObject\Filter;
use App\Analytics\Domain\ValueObject\FilterOperator;
use App\Analytics\Domain\ValueObject\FilterSet;
use App\Shared\Domain\ValueObject\Uuid;
use App\Tests\Unit\Analytics\Support\InMemorySegmentRepository;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SegmentResolver::class)]
final class SegmentResolverTest extends TestCase
{
    private Uuid $site;

    private InMemorySegmentRepository $repo;

    protected function setUp(): void
    {
        $this->site = Uuid::generate();
        $this->repo = new InMemorySegmentRepository();
    }

    #[Test]
    public function noSegmentAndNoInlineFiltersYieldsNoGroups(): void
    {
        $groups = $this->resolver()->resolveGroups($this->site, null, FilterSet::empty());

        self::assertSame([], $groups);
    }

    #[Test]
    public function inlineFiltersOnlyYieldOneGroup(): void
    {
        $inline = FilterSet::of([Filter::create(Dimension::DeviceType, FilterOperator::Eq, 'mobile')]);

        $groups = $this->resolver()->resolveGroups($this->site, null, $inline);

        self::assertSame([$inline], $groups);
    }

    #[Test]
    public function segmentAndInlineFiltersAreAndedTogether(): void
    {
        $segment = Segment::create(
            Uuid::generate(),
            $this->site,
            'Mobile',
            FilterSet::of([Filter::create(Dimension::DeviceType, FilterOperator::Eq, 'mobile')]),
            null,
            new DateTimeImmutable(),
        );
        $this->repo->save($segment);

        $inline = FilterSet::of([Filter::create(Dimension::Country, FilterOperator::Eq, 'FR')]);

        $groups = $this->resolver()->resolveGroups($this->site, $segment->id, $inline);

        self::assertCount(2, $groups);
        self::assertSame($segment->filterSet, $groups[0]);
        self::assertSame($inline, $groups[1]);
    }

    #[Test]
    public function anUnknownSegmentThrows(): void
    {
        $this->expectException(SegmentNotFound::class);

        $this->resolver()->resolveGroups($this->site, Uuid::generate(), FilterSet::empty());
    }

    private function resolver(): SegmentResolver
    {
        return new SegmentResolver($this->repo);
    }
}
