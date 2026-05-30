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

namespace App\Tests\Unit\Sites\Fake;

use App\Sites\Domain\Port\TrackerKeyGenerator;
use App\Sites\Domain\ValueObject\TrackerKey;

/**
 * Yields a deterministic sequence of tracker keys so collision/retry behaviour
 * can be exercised. Each call returns the next preset key, then falls back to a
 * generated unique suffix.
 */
final class SequenceTrackerKeyGenerator implements TrackerKeyGenerator
{
    private int $cursor = 0;

    /**
     * @var list<string>
     */
    private array $sequence;

    public function __construct(string ...$suffixes)
    {
        $this->sequence = array_values($suffixes);
    }

    public function generate(): TrackerKey
    {
        $suffix = $this->sequence[$this->cursor] ?? sprintf('%032d', $this->cursor);
        ++$this->cursor;

        return TrackerKey::fromString(TrackerKey::PREFIX . $suffix);
    }

    public function callCount(): int
    {
        return $this->cursor;
    }
}
