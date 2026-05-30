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

namespace App\Analytics\Domain\Model;

use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

/**
 * A persisted, named funnel (`postgres.funnels`) read by the funnel query
 * engine. The Analytics context only reads funnels (CRUD is a Sites/Reporting
 * concern); this aggregate exists so the funnel query can resolve step
 * definitions and labels.
 */
final readonly class Funnel
{
    public const MIN_STEPS = 2;

    public const MAX_STEPS = 16;

    /**
     * @param list<FunnelStep> $steps ordered by stepIndex ascending
     */
    private function __construct(
        public Uuid $id,
        public Uuid $siteId,
        public string $name,
        public array $steps,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }

    /**
     * @param list<FunnelStep> $steps
     */
    public static function reconstitute(
        Uuid $id,
        Uuid $siteId,
        string $name,
        array $steps,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ): self {
        usort($steps, static fn (FunnelStep $a, FunnelStep $b): int => $a->stepIndex <=> $b->stepIndex);

        return new self($id, $siteId, $name, $steps, $createdAt, $updatedAt);
    }

    public function stepCount(): int
    {
        return count($this->steps);
    }
}
