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

namespace App\Tests\Integration\Reporting\Support;

use App\Shared\Domain\ValueObject\Uuid;
use Doctrine\DBAL\Connection;

/**
 * Seeds the user -> team -> site chain the Reporting aggregates reference through
 * foreign keys. Used inside the per-test transaction the repository cases roll
 * back, so the seed never leaks between tests.
 */
final class ReportingFixtures
{
    private const NOW = '2026-01-01 00:00:00+00';

    public static function seedSite(Connection $connection, string $siteId, ?string $teamId = null): void
    {
        $userId = Uuid::generate()->getValue();
        $teamId ??= Uuid::generate()->getValue();
        $short = substr(str_replace('-', '', $siteId), 0, 12);

        $connection->insert('users', [
            'id' => $userId,
            'email' => 'owner-' . $short . '@example.com',
            'name' => 'Owner',
            'created_at' => self::NOW,
            'updated_at' => self::NOW,
        ]);

        $connection->insert('teams', [
            'id' => $teamId,
            'name' => 'Team ' . $short,
            'slug' => 't' . substr(str_replace('-', '', $teamId), 0, 20),
            'owner_id' => $userId,
            'created_at' => self::NOW,
            'updated_at' => self::NOW,
        ]);

        $connection->insert('sites', [
            'id' => $siteId,
            'team_id' => $teamId,
            'name' => 'Site ' . $short,
            'domain' => 'rep-' . $short . '.example.com',
            'timezone' => 'UTC',
            'tracking_enabled' => true,
            'tracker_key' => 'stk_' . substr(str_replace('-', '', $siteId), 0, 28),
            'created_at' => self::NOW,
            'updated_at' => self::NOW,
        ]);
    }
}
