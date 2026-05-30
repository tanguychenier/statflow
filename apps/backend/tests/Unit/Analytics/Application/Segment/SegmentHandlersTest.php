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

namespace App\Tests\Unit\Analytics\Application\Segment;

use App\Analytics\Application\Segment\CreateSegmentHandler;
use App\Analytics\Application\Segment\DeleteSegmentHandler;
use App\Analytics\Application\Segment\ListSegmentsHandler;
use App\Analytics\Application\Segment\SegmentPresenter;
use App\Analytics\Application\Segment\SegmentRequestFactory;
use App\Analytics\Domain\Exception\InvalidSegment;
use App\Analytics\Domain\Exception\SegmentNotFound;
use App\Analytics\Domain\Model\Segment;
use App\Analytics\Domain\ValueObject\Dimension;
use App\Analytics\Domain\ValueObject\Filter;
use App\Analytics\Domain\ValueObject\FilterOperator;
use App\Analytics\Domain\ValueObject\FilterSet;
use App\Shared\Domain\ValueObject\Uuid;
use App\Tests\Unit\Analytics\Support\FrozenClock;
use App\Tests\Unit\Analytics\Support\InMemorySegmentRepository;
use App\Tests\Unit\Analytics\Support\PassthroughQueryCache;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreateSegmentHandler::class)]
#[CoversClass(ListSegmentsHandler::class)]
#[CoversClass(DeleteSegmentHandler::class)]
#[CoversClass(SegmentPresenter::class)]
#[CoversClass(SegmentRequestFactory::class)]
#[CoversClass(SegmentNotFound::class)]
final class SegmentHandlersTest extends TestCase
{
    private Uuid $site;

    private InMemorySegmentRepository $repo;

    private PassthroughQueryCache $cache;

    protected function setUp(): void
    {
        $this->site = Uuid::generate();
        $this->repo = new InMemorySegmentRepository();
        $this->cache = new PassthroughQueryCache();
    }

    #[Test]
    public function itCreatesPersistsAndPresentsASegment(): void
    {
        $handler = new CreateSegmentHandler(
            $this->repo,
            $this->cache,
            new FrozenClock(new DateTimeImmutable('2025-06-01T10:00:00Z')),
        );

        $command = (new SegmentRequestFactory())->createCommand($this->site, [
            'name' => 'Mobile FR',
            'filters' => [
                [
                    'property' => 'device_type',
                    'operator' => 'eq',
                    'value' => 'mobile',
                ],
                [
                    'property' => 'country',
                    'operator' => 'eq',
                    'value' => 'FR',
                ],
            ],
            'filter_combination' => 'and',
        ], 'user-1');

        $payload = ($handler)($command);

        self::assertSame('Mobile FR', $payload['name']);
        self::assertSame((string) $this->site, $payload['site_id']);
        self::assertSame('and', $payload['filter_combination']);
        self::assertSame('user-1', $payload['created_by']);
        self::assertSame('2025-06-01T10:00:00Z', $payload['created_at']);
        /** @var list<mixed> $payloadFilters */
        $payloadFilters = $payload['filters'];
        self::assertCount(2, $payloadFilters);
        self::assertSame([(string) $this->site], $this->cache->invalidatedSites);
        self::assertCount(1, $this->repo->findBySite($this->site));
    }

    #[Test]
    public function itListsSegmentsForASite(): void
    {
        $this->repo->save($this->segment('A'));
        $this->repo->save($this->segment('B'));

        $payload = (new ListSegmentsHandler($this->repo))($this->site);

        self::assertCount(2, $payload);
        self::assertContains($payload[0]['name'], ['A', 'B']);
    }

    #[Test]
    public function itDeletesAnExistingSegmentAndInvalidatesCache(): void
    {
        $segment = $this->segment('A');
        $this->repo->save($segment);

        (new DeleteSegmentHandler($this->repo, $this->cache))($this->site, $segment->id);

        self::assertSame([], $this->repo->findBySite($this->site));
        self::assertSame([(string) $this->site], $this->cache->invalidatedSites);
    }

    #[Test]
    public function deletingAMissingSegmentThrowsNotFound(): void
    {
        $this->expectException(SegmentNotFound::class);

        (new DeleteSegmentHandler($this->repo, $this->cache))($this->site, Uuid::generate());
    }

    #[Test]
    public function requestFactoryRejectsAnEmptyName(): void
    {
        $this->expectException(InvalidSegment::class);

        (new SegmentRequestFactory())->createCommand($this->site, [
            'name' => '  ',
            'filters' => [[
                'property' => 'device_type',
                'operator' => 'eq',
                'value' => 'mobile',
            ]],
        ], null);
    }

    #[Test]
    public function requestFactoryRejectsMissingFilters(): void
    {
        $this->expectException(InvalidSegment::class);

        (new SegmentRequestFactory())->createCommand($this->site, [
            'name' => 'X',
            'filters' => [],
        ], null);
    }

    private function segment(string $name): Segment
    {
        return Segment::create(
            Uuid::generate(),
            $this->site,
            $name,
            FilterSet::of([Filter::create(Dimension::DeviceType, FilterOperator::Eq, 'mobile')]),
            null,
            new DateTimeImmutable('2025-06-01T00:00:00Z'),
        );
    }
}
