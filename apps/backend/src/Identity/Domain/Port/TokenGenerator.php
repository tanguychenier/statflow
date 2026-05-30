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

namespace App\Identity\Domain\Port;

/**
 * Source of cryptographically secure random material for opaque tokens
 * (password-reset links, API-key secrets). Kept behind a port so handlers stay
 * deterministic under test.
 */
interface TokenGenerator
{
    /**
     * Return a URL-safe random string with at least the requested entropy.
     */
    public function generate(int $byteLength = 32): string;
}
