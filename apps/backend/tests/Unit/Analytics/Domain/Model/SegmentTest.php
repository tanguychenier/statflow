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

namespace App\Tests\Unit\Analytics\Domain\Model;

use App\Analytics\Domain\Exception\InvalidSegment;
use App\Analytics\Domain\Model\Segment;
use App\Analytics\Domain\ValueObject\Dimension;
use App\Analytics\Domain\ValueObject\Filter;
use App\Analytics\Domain\ValueObject\FilterOperator;
use App\Analytics\Domain\ValueObject\FilterSet;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Segment::class)]
#[CoversClass(InvalidSegment::class)]
final class SegmentTest extends TestCase
{
    #[Test]
    public function itCreatesAValidSegment(): void
    {
        $segment = Segment::create(
            Uuid::generate(),
            Uuid::generate(),
            '  Mobile FR  ',
            $this->filters(),
            'user-1',
            new DateTimeImmutable('2025-06-01T00:00:00Z'),
        );

        self::assertSame('Mobile FR', $segment->name);
        self::assertSame('user-1', $segment->createdBy);
    }

    #[Test]
    public function itRejectsAnEmptyName(): void
    {
        $this->expectException(InvalidSegment::class);

        Segment::create(Uuid::generate(), Uuid::generate(), '   ', $this->filters(), null, new DateTimeImmutable());
    }

    #[Test]
    public function itRejectsAnOverlongName(): void
    {
        $this->expectException(InvalidSegment::class);

        Segment::create(
            Uuid::generate(),
            Uuid::generate(),
            str_repeat('x', Segment::MAX_NAME_LENGTH + 1),
            $this->filters(),
            null,
            new DateTimeImmutable(),
        );
    }

    #[Test]
    public function itRejectsAnEmptyFilterSet(): void
    {
        $this->expectException(InvalidSegment::class);

        Segment::create(Uuid::generate(), Uuid::generate(), 'X', FilterSet::empty(), null, new DateTimeImmutable());
    }

    #[Test]
    public function reconstituteSkipsInvariants(): void
    {
        $segment = Segment::reconstitute(
            Uuid::generate(),
            Uuid::generate(),
            '',
            FilterSet::empty(),
            null,
            new DateTimeImmutable(),
        );

        self::assertSame('', $segment->name);
    }

    private function filters(): FilterSet
    {
        return FilterSet::of([Filter::create(Dimension::DeviceType, FilterOperator::Eq, 'mobile')]);
    }
}
