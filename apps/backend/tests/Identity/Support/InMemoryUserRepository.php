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

use App\Identity\Domain\Model\User;
use App\Identity\Domain\Port\UserRepository;
use App\Identity\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * In-memory {@see UserRepository} for application-layer tests. Mirrors the
 * adapter contract: finders exclude soft-deleted rows.
 */
final class InMemoryUserRepository implements UserRepository
{
    /**
     * @var array<string, User>
     */
    private array $users = [];

    public function save(User $user): void
    {
        $this->users[$user->id()->getValue()] = $user;
    }

    public function findById(Uuid $id): ?User
    {
        $user = $this->users[$id->getValue()] ?? null;

        return $user !== null && !$user->isDeleted() ? $user : null;
    }

    public function findByEmail(EmailAddress $email): ?User
    {
        foreach ($this->users as $user) {
            if (!$user->isDeleted() && $user->email()->equals($email)) {
                return $user;
            }
        }

        return null;
    }

    public function existsByEmail(EmailAddress $email): bool
    {
        return $this->findByEmail($email) !== null;
    }
}
