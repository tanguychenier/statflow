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

use App\Identity\Domain\Port\PasswordHasher;
use App\Identity\Domain\ValueObject\HashedPassword;
use App\Identity\Domain\ValueObject\PlainPassword;

/**
 * Deterministic test hasher: the "hash" is a recognisable prefix plus the
 * plaintext, so assertions can verify the password without bcrypt's cost. Never
 * use outside tests.
 */
final class PlaintextPasswordHasher implements PasswordHasher
{
    private const PREFIX = 'hashed:';

    public bool $rehashNeeded = false;

    public function hash(PlainPassword $plain): HashedPassword
    {
        return HashedPassword::fromHash(self::PREFIX . $plain->reveal());
    }

    public function verify(PlainPassword $plain, HashedPassword $hash): bool
    {
        return hash_equals($hash->getValue(), self::PREFIX . $plain->reveal());
    }

    public function needsRehash(HashedPassword $hash): bool
    {
        return $this->rehashNeeded;
    }
}
