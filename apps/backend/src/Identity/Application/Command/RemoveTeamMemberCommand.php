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

final readonly class RemoveTeamMemberCommand
{
    public function __construct(
        public string $actorId,
        public string $teamId,
        public string $userId,
        public AuditContext $auditContext,
    ) {
    }
}
