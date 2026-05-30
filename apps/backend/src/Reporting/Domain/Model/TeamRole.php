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

namespace App\Reporting\Domain\Model;

/**
 * Team roles as frozen by ADR-0009, mirrored in the Reporting context so its
 * authorization never reaches into another context's domain model.
 *
 * Reporting only reads a member's role to decide whether a mutation is allowed;
 * it never persists membership (that belongs to Identity). The role hierarchy is
 * total and ordered by privilege.
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
     * Any accepted member (Viewer and above) may read reports, alerts and export
     * status. Reading is the floor of the role hierarchy.
     */
    public function canViewReporting(): bool
    {
        return true;
    }

    /**
     * Editor and above may create, edit and delete reports, scheduled reports,
     * alerts, and may request data exports.
     */
    public function canManageReporting(): bool
    {
        return $this->isAtLeast(self::Editor);
    }
}
