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

use App\Identity\Domain\Model\User;
use App\Identity\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Persistence port for users. Adapters live in Infrastructure. All lookups
 * exclude soft-deleted rows unless the method name says otherwise.
 */
interface UserRepository
{
    public function save(User $user): void;

    public function findById(Uuid $id): ?User;

    public function findByEmail(EmailAddress $email): ?User;

    public function existsByEmail(EmailAddress $email): bool;
}
