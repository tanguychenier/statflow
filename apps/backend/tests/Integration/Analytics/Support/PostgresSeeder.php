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

namespace App\Tests\Integration\Analytics\Support;

use App\Shared\Domain\ValueObject\Uuid;
use Doctrine\DBAL\Connection;

/**
 * Seeds the minimal users -> teams -> sites chain the analytics tables depend on
 * via foreign keys. Used inside the per-test transaction that the integration
 * cases roll back, so the seed never leaks between tests.
 */
final readonly class PostgresSeeder
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * Create a user, a personal team, and a site; return the site id. When a site
     * id is supplied the parent chain is created around it, letting callers seed
     * the fixed-id site their domain objects already reference.
     */
    public function seedSite(?Uuid $siteId = null): Uuid
    {
        $userId = Uuid::generate();
        $teamId = Uuid::generate();
        $siteId ??= Uuid::generate();
        $short = substr((string) $siteId, 0, 8);
        $now = '2026-01-01 00:00:00+00';

        $this->connection->insert('users', [
            'id' => (string) $userId,
            'email' => sprintf('owner-%s@example.com', $short),
            'name' => 'Owner',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->connection->insert('teams', [
            'id' => (string) $teamId,
            'name' => 'Team ' . $short,
            'slug' => 'team-' . $short,
            'owner_id' => (string) $userId,
            'is_personal' => 'true',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->connection->insert('sites', [
            'id' => (string) $siteId,
            'team_id' => (string) $teamId,
            'name' => 'Site ' . $short,
            'domain' => sprintf('%s.example.com', $short),
            'tracker_key' => 'stk_' . substr((string) $siteId, 0, 16),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $siteId;
    }
}
