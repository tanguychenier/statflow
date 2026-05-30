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

namespace App\Identity\Application\DTO;

use App\Identity\Domain\Model\TeamMembership;
use App\Identity\Domain\Model\User;

/**
 * Read model for the TeamMember schema (openapi.yaml). Joins a membership row to
 * the user's email and display name.
 */
final readonly class TeamMemberView
{
    public function __construct(
        public string $id,
        public string $userId,
        public string $email,
        public string $name,
        public string $role,
        public string $status,
        public string $joinedAt,
    ) {
    }

    public static function fromEntities(TeamMembership $membership, User $user): self
    {
        $joinedAt = $membership->acceptedAt() ?? $membership->invitedAt();

        return new self(
            $membership->id()->getValue(),
            $membership->userId()->getValue(),
            $user->email()->getValue(),
            $user->name(),
            $membership->role()->value,
            $membership->status(),
            $joinedAt->format(DATE_ATOM),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'email' => $this->email,
            'name' => $this->name,
            'role' => $this->role,
            'status' => $this->status,
            'joined_at' => $this->joinedAt,
        ];
    }
}
