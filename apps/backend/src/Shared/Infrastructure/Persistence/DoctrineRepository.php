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

namespace App\Shared\Infrastructure\Persistence;

use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Bus\Event\EventBus;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

/**
 * Base class for Doctrine-backed repository adapters in the PostgreSQL contexts.
 *
 * Beyond the ORM plumbing it provides the canonical persist-then-publish flow:
 * {@see saveAggregate()} flushes the entity inside the unit of work and only then
 * releases the aggregate's recorded domain events onto the `event.bus`, so events
 * are never published for a write that failed to commit.
 *
 * @template TEntity of object
 */
abstract class DoctrineRepository
{
    public function __construct(
        protected readonly EntityManagerInterface $entityManager,
        private readonly EventBus $eventBus,
    ) {
    }

    /**
     * @return class-string<TEntity>
     */
    abstract protected function entityClass(): string;

    /**
     * @return EntityRepository<TEntity>
     */
    protected function repository(): EntityRepository
    {
        return $this->entityManager->getRepository($this->entityClass());
    }

    protected function persist(object $entity): void
    {
        $this->entityManager->persist($entity);
    }

    protected function flush(): void
    {
        $this->entityManager->flush();
    }

    protected function remove(object $entity): void
    {
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }

    /**
     * Persist an aggregate root, flush, then publish its recorded domain events.
     */
    protected function saveAggregate(AggregateRoot $aggregate): void
    {
        $this->entityManager->persist($aggregate);
        $this->entityManager->flush();

        $events = $aggregate->pullDomainEvents();

        if ($events !== []) {
            $this->eventBus->publish(...$events);
        }
    }
}
