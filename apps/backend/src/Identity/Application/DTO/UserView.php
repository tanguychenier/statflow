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

use App\Identity\Domain\Model\User;

/**
 * Read model for the User schema (openapi.yaml). Shaped for JSON serialisation;
 * never exposes the password hash.
 */
final readonly class UserView
{
    public function __construct(
        public string $id,
        public string $email,
        public string $name,
        public ?string $avatarUrl,
        public bool $emailVerified,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function fromEntity(User $user): self
    {
        return new self(
            $user->id()->getValue(),
            $user->email()->getValue(),
            $user->name(),
            $user->avatarUrl(),
            $user->isEmailVerified(),
            $user->createdAt()->format(DATE_ATOM),
            $user->updatedAt()->format(DATE_ATOM),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'name' => $this->name,
            'avatar_url' => $this->avatarUrl,
            'email_verified' => $this->emailVerified,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
