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

namespace App\Tests\Integration\Analytics\Infrastructure\Doctrine;

use App\Analytics\Domain\Model\Segment;
use App\Analytics\Domain\ValueObject\Dimension;
use App\Analytics\Domain\ValueObject\Filter;
use App\Analytics\Domain\ValueObject\FilterCombination;
use App\Analytics\Domain\ValueObject\FilterOperator;
use App\Analytics\Domain\ValueObject\FilterSet;
use App\Analytics\Infrastructure\Persistence\Doctrine\DbalSegmentRepository;
use App\Shared\Domain\ValueObject\Uuid;
use App\Tests\Integration\Analytics\Support\PostgresSeeder;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Persistence integration test for {@see DbalSegmentRepository}. Runs against a
 * real PostgreSQL schema (container phase). Each case runs inside a transaction
 * rolled back in tearDown, so a freshly seeded parent site keeps the FK valid
 * without leaking state.
 */
#[CoversClass(DbalSegmentRepository::class)]
final class DbalSegmentRepositoryTest extends KernelTestCase
{
    private Connection $connection;

    private DbalSegmentRepository $repository;

    private Uuid $siteId;

    protected function setUp(): void
    {
        self::bootKernel();

        /** @var Connection $connection */
        $connection = self::getContainer()->get(Connection::class);
        $this->connection = $connection;
        $this->connection->beginTransaction();

        $this->repository = new DbalSegmentRepository($this->connection);
        $this->siteId = $this->seedSite();
    }

    protected function tearDown(): void
    {
        $this->connection->rollBack();
        parent::tearDown();
    }

    #[Test]
    public function itPersistsAndReloadsASegment(): void
    {
        $segment = $this->newSegment('Mobile FR');
        $this->repository->save($segment);

        $loaded = $this->repository->find($this->siteId, $segment->id);

        self::assertNotNull($loaded);
        self::assertSame('Mobile FR', $loaded->name);
        self::assertSame(FilterCombination::And, $loaded->filterSet->combination);
        self::assertCount(2, $loaded->filterSet->filters);
        self::assertSame(Dimension::DeviceType, $loaded->filterSet->filters[0]->dimension);
    }

    #[Test]
    public function itListsSegmentsNewestFirst(): void
    {
        $this->repository->save($this->newSegment('A', new DateTimeImmutable('2025-06-01T00:00:00Z')));
        $this->repository->save($this->newSegment('B', new DateTimeImmutable('2025-06-02T00:00:00Z')));

        $segments = $this->repository->findBySite($this->siteId);

        self::assertCount(2, $segments);
        self::assertSame('B', $segments[0]->name);
    }

    #[Test]
    public function itSoftDeletesASegment(): void
    {
        $segment = $this->newSegment('Doomed');
        $this->repository->save($segment);

        $this->repository->delete($this->siteId, $segment->id);

        self::assertNull($this->repository->find($this->siteId, $segment->id));
        self::assertSame([], $this->repository->findBySite($this->siteId));
    }

    #[Test]
    public function itIsolatesSegmentsBySite(): void
    {
        $other = $this->seedSite();
        $segment = $this->newSegment('Mine');
        $this->repository->save($segment);

        self::assertNull($this->repository->find($other, $segment->id));
    }

    private function newSegment(string $name, ?DateTimeImmutable $createdAt = null): Segment
    {
        return Segment::create(
            Uuid::generate(),
            $this->siteId,
            $name,
            FilterSet::of([
                Filter::create(Dimension::DeviceType, FilterOperator::Eq, 'mobile'),
                Filter::create(Dimension::Country, FilterOperator::In, ['FR', 'DE']),
            ], FilterCombination::And),
            null,
            $createdAt ?? new DateTimeImmutable('2025-06-01T00:00:00Z'),
        );
    }

    private function seedSite(): Uuid
    {
        return (new PostgresSeeder($this->connection))->seedSite();
    }
}
