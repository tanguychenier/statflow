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

namespace App\Sites\Domain\Model;

/**
 * Team roles as frozen by ADR-0009.
 *
 * The Sites context only ever reads a member's role to decide whether a
 * mutation is allowed; it never persists team membership (that belongs to the
 * Identity context). The role hierarchy is total and ordered by privilege.
 */
enum TeamRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Editor = 'editor';
    case Viewer = 'viewer';

    public static function fromString(string $value): self
    {
        return self::from($value);
    }

    /**
     * Privilege rank — higher means more capabilities.
     * Used so callers can express "at least editor" without enumerating roles.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Owner => 3,
            self::Admin => 2,
            self::Editor => 1,
            self::Viewer => 0,
        };
    }

    public function isAtLeast(self $minimum): bool
    {
        return $this->rank() >= $minimum->rank();
    }

    /**
     * Editor and above may create/edit sites, goals, funnels, segments, reports.
     */
    public function canManageSites(): bool
    {
        return $this->isAtLeast(self::Editor);
    }

    /**
     * Admin and above may change site administrative configuration
     * (settings replacement, tracker-key rotation).
     */
    public function canAdministerSites(): bool
    {
        return $this->isAtLeast(self::Admin);
    }

    /**
     * Only the owner may permanently delete a site and its analytical data.
     */
    public function canDeleteSites(): bool
    {
        return $this === self::Owner;
    }
}
