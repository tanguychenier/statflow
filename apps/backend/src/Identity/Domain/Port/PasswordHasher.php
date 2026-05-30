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

use App\Identity\Domain\ValueObject\HashedPassword;
use App\Identity\Domain\ValueObject\PlainPassword;

/**
 * Hashes and verifies passwords. The concrete algorithm (bcrypt per ADR-0009)
 * is an infrastructure detail; the domain only depends on this contract.
 */
interface PasswordHasher
{
    public function hash(PlainPassword $plain): HashedPassword;

    public function verify(PlainPassword $plain, HashedPassword $hash): bool;

    /**
     * True when the stored hash uses outdated parameters and should be rehashed
     * on the next successful verification.
     */
    public function needsRehash(HashedPassword $hash): bool;
}
