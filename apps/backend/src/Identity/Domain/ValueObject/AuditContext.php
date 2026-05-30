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

namespace App\Identity\Domain\ValueObject;

use App\Shared\Domain\ValueObject\Uuid;

/**
 * The "who and from where" of an audited action. actor may be null for
 * system-initiated actions (audit_log.actor_id is nullable). actorEmail is
 * denormalised so the trail survives a later account deletion.
 */
final readonly class AuditContext
{
    public function __construct(
        public ?Uuid $actorId = null,
        public ?string $actorEmail = null,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
    ) {
    }

    public static function system(): self
    {
        return new self();
    }
}
