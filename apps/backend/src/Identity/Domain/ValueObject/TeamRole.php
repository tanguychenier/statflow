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

use App\Identity\Domain\Exception\InvalidTeamRoleException;

/**
 * The four team roles frozen by ADR-0009. Capabilities are encoded as a strict
 * privilege ordering: owner > admin > editor > viewer. Higher-privilege roles
 * include every capability of the roles below them.
 */
enum TeamRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Editor = 'editor';
    case Viewer = 'viewer';

    public static function fromString(string $value): self
    {
        return self::tryFrom($value)
            ?? throw InvalidTeamRoleException::forValue($value, self::values());
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $role): string => $role->value, self::cases());
    }

    public function isAtLeast(self $minimum): bool
    {
        return $this->rank() >= $minimum->rank();
    }

    /**
     * Owner and admin manage members, API keys, and team settings.
     */
    public function canManageMembers(): bool
    {
        return $this->isAtLeast(self::Admin);
    }

    public function canManageApiKeys(): bool
    {
        return $this->isAtLeast(self::Admin);
    }

    public function canManageTeam(): bool
    {
        return $this->isAtLeast(self::Admin);
    }

    /**
     * Editor and above may create or modify sites and analytics configuration.
     */
    public function canWriteResources(): bool
    {
        return $this->isAtLeast(self::Editor);
    }

    /**
     * Only the owner may delete the team or manage billing.
     */
    public function canDeleteTeam(): bool
    {
        return $this === self::Owner;
    }

    private function rank(): int
    {
        return match ($this) {
            self::Owner => 4,
            self::Admin => 3,
            self::Editor => 2,
            self::Viewer => 1,
        };
    }
}
