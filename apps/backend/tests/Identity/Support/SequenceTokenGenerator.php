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

namespace App\Tests\Identity\Support;

use App\Identity\Domain\Port\TokenGenerator;

/**
 * Deterministic {@see TokenGenerator}: returns predictable, incrementing tokens
 * so reset-link and API-key tests can assert exact values.
 */
final class SequenceTokenGenerator implements TokenGenerator
{
    private int $counter = 0;

    public function __construct(
        private readonly string $prefix = 'token'
    ) {
    }

    public function generate(int $byteLength = 32): string
    {
        return $this->prefix . '-' . str_pad((string) (++$this->counter), $byteLength, '0', STR_PAD_LEFT);
    }
}
