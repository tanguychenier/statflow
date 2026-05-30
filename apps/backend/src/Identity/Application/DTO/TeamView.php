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

use App\Identity\Domain\Model\Team;
use App\Identity\Domain\ValueObject\TeamRole;

/**
 * Read model for the Team schema (openapi.yaml), including the caller's role and
 * the aggregate member/site counts.
 */
final readonly class TeamView
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public int $memberCount,
        public int $siteCount,
        public ?string $currentUserRole,
        public string $createdAt,
    ) {
    }

    public static function fromEntity(
        Team $team,
        int $memberCount,
        int $siteCount,
        ?TeamRole $currentUserRole,
    ): self {
        return new self(
            $team->id()->getValue(),
            $team->name(),
            $team->slug()->getValue(),
            $memberCount,
            $siteCount,
            $currentUserRole?->value,
            $team->createdAt()->format(DATE_ATOM),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'member_count' => $this->memberCount,
            'site_count' => $this->siteCount,
            'current_user_role' => $this->currentUserRole,
            'created_at' => $this->createdAt,
        ];
    }
}
