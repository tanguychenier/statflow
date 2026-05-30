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

namespace App\Identity\Domain\Service;

use App\Identity\Domain\Port\TeamRepository;
use App\Identity\Domain\ValueObject\TeamSlug;
use RuntimeException;

/**
 * Allocates a globally unique team slug from a free-form name, appending a
 * numeric suffix on collision. Slug uniqueness is enforced both here and by the
 * teams_slug_unique partial index, which remains the ultimate guard against the
 * race window between check and insert.
 */
final readonly class SlugAllocator
{
    private const MAX_ATTEMPTS = 1000;

    public function __construct(
        private TeamRepository $teams,
    ) {
    }

    public function allocate(string $name): TeamSlug
    {
        $base = TeamSlug::fromName($name);

        if (!$this->teams->slugExists($base)) {
            return $base;
        }

        for ($suffix = 2; $suffix <= self::MAX_ATTEMPTS; $suffix++) {
            $candidate = $base->withSuffix($suffix);

            if (!$this->teams->slugExists($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException(sprintf('Unable to allocate a unique slug for "%s".', $name));
    }
}
