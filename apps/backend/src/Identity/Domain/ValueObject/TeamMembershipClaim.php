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
 * One entry in the JWT `teams` claim: the team a user belongs to and their role
 * within it. Authorisation voters read these without a database round-trip
 * (api/README.md §2.1).
 */
final readonly class TeamMembershipClaim
{
    public function __construct(
        public Uuid $teamId,
        public TeamRole $role,
    ) {
    }

    /**
     * @return array{team_id: string, role: string}
     */
    public function toArray(): array
    {
        return [
            'team_id' => $this->teamId->getValue(),
            'role' => $this->role->value,
        ];
    }
}
