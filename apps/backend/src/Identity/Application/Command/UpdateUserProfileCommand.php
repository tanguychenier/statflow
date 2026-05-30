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

namespace App\Identity\Application\Command;

use App\Identity\Domain\ValueObject\AuditContext;

/**
 * Partial update of the current user's profile (PATCH /users/me). Null fields
 * are left unchanged; absence is expressed by passing null.
 */
final readonly class UpdateUserProfileCommand
{
    public function __construct(
        public string $userId,
        public ?string $name,
        public ?string $email,
        public AuditContext $auditContext,
    ) {
    }
}
