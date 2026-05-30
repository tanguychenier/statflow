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

namespace App\Analytics\Application\Segment;

use App\Analytics\Domain\Model\Segment;
use App\Analytics\Domain\Port\QueryCachePort;
use App\Analytics\Domain\Port\SegmentRepositoryPort;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * Creates and persists a saved segment, then returns its presentation payload.
 *
 * Creating a segment invalidates the site's query cache: cached results were
 * computed without this segment, but reports referencing it must recompute.
 */
final readonly class CreateSegmentHandler
{
    public function __construct(
        private SegmentRepositoryPort $segments,
        private QueryCachePort $cache,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function __invoke(CreateSegmentCommand $command): array
    {
        $segment = Segment::create(
            Uuid::generate(),
            $command->siteId,
            $command->name,
            $command->filterSet,
            $command->createdBy,
            DateTimeImmutable::createFromInterface($this->clock->now()),
        );

        $this->segments->save($segment);
        $this->cache->invalidateSite($command->siteId);

        return SegmentPresenter::toArray($segment);
    }
}
