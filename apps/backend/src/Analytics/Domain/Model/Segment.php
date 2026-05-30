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

use App\Analytics\Domain\Exception\InvalidSegment;
use App\Analytics\Domain\ValueObject\FilterSet;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

/**
 * A reusable, named visitor/session filter (`postgres.segments`).
 *
 * Pure domain aggregate: invariants on name and filter presence are enforced
 * here, independent of any persistence concern.
 */
final readonly class Segment
{
    public const MAX_NAME_LENGTH = 100;

    private function __construct(
        public Uuid $id,
        public Uuid $siteId,
        public string $name,
        public FilterSet $filterSet,
        public ?string $createdBy,
        public DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(
        Uuid $id,
        Uuid $siteId,
        string $name,
        FilterSet $filterSet,
        ?string $createdBy,
        DateTimeImmutable $createdAt,
    ): self {
        $trimmed = trim($name);
        if ($trimmed === '') {
            throw InvalidSegment::emptyName();
        }
        if (mb_strlen($trimmed) > self::MAX_NAME_LENGTH) {
            throw InvalidSegment::nameTooLong(self::MAX_NAME_LENGTH);
        }
        if ($filterSet->isEmpty()) {
            throw InvalidSegment::noFilters();
        }

        return new self($id, $siteId, $trimmed, $filterSet, $createdBy, $createdAt);
    }

    /**
     * Reconstitute from persistence without re-running creation invariants
     * (the row was valid when it was written).
     */
    public static function reconstitute(
        Uuid $id,
        Uuid $siteId,
        string $name,
        FilterSet $filterSet,
        ?string $createdBy,
        DateTimeImmutable $createdAt,
    ): self {
        return new self($id, $siteId, $name, $filterSet, $createdBy, $createdAt);
    }
}
