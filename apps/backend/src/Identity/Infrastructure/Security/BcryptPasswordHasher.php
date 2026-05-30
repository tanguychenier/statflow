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

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\Port\PasswordHasher;
use App\Identity\Domain\ValueObject\HashedPassword;
use App\Identity\Domain\ValueObject\PlainPassword;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

/**
 * Adapts Symfony's password hasher to the domain {@see PasswordHasher} port.
 *
 * ADR-0009 §2 fixes the v1 algorithm to bcrypt, so the wiring agent injects a
 * bcrypt-configured {@see PasswordHasherInterface}. The port stays algorithm-
 * agnostic; only this adapter knows the concrete hasher.
 */
final readonly class BcryptPasswordHasher implements PasswordHasher
{
    public function __construct(
        private PasswordHasherInterface $hasher,
    ) {
    }

    public function hash(PlainPassword $plain): HashedPassword
    {
        return HashedPassword::fromHash($this->hasher->hash($plain->reveal()));
    }

    public function verify(PlainPassword $plain, HashedPassword $hash): bool
    {
        return $this->hasher->verify($hash->getValue(), $plain->reveal());
    }

    public function needsRehash(HashedPassword $hash): bool
    {
        return $this->hasher->needsRehash($hash->getValue());
    }
}
