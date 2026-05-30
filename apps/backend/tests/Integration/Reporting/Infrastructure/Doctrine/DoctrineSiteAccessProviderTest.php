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

namespace App\Tests\Integration\Reporting\Infrastructure\Doctrine;

use App\Reporting\Domain\Model\TeamRole;
use App\Reporting\Infrastructure\Doctrine\DoctrineSiteAccessProvider;
use App\Shared\Domain\ValueObject\Uuid;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration test for {@see DoctrineSiteAccessProvider} against the shared
 * `sites` / `team_members` tables. Runs in the container phase; each test is
 * wrapped in a transaction rolled back in tearDown.
 */
#[CoversClass(DoctrineSiteAccessProvider::class)]
final class DoctrineSiteAccessProviderTest extends KernelTestCase
{
    private const NOW = '2026-01-01 00:00:00+00';

    private Connection $connection;

    private DoctrineSiteAccessProvider $provider;

    private string $userId;

    private string $teamId;

    private string $siteId;

    protected function setUp(): void
    {
        self::bootKernel();

        /** @var Connection $connection */
        $connection = self::getContainer()->get(Connection::class);
        $this->connection = $connection;
        $this->connection->beginTransaction();

        $this->provider = new DoctrineSiteAccessProvider($this->connection);

        $this->userId = Uuid::generate()->getValue();
        $this->teamId = Uuid::generate()->getValue();
        $this->siteId = Uuid::generate()->getValue();

        $this->seedUser($this->userId);
        $this->seedTeam($this->teamId, $this->userId);
        $this->seedSite($this->siteId, $this->teamId);
    }

    protected function tearDown(): void
    {
        $this->connection->rollBack();
        parent::tearDown();
    }

    #[Test]
    public function itResolvesAnAcceptedMembersRole(): void
    {
        $this->seedMembership($this->teamId, $this->userId, 'editor', '2026-01-01 00:00:00+00');

        $role = $this->provider->roleOnSite(Uuid::fromString($this->userId), Uuid::fromString($this->siteId));

        self::assertSame(TeamRole::Editor, $role);
    }

    #[Test]
    public function itReturnsNullForPendingMembership(): void
    {
        $this->seedMembership($this->teamId, $this->userId, 'editor', null);

        self::assertNull($this->provider->roleOnSite(Uuid::fromString($this->userId), Uuid::fromString($this->siteId)));
    }

    #[Test]
    public function itReturnsNullForNonMember(): void
    {
        self::assertNull($this->provider->roleOnSite(Uuid::generate(), Uuid::fromString($this->siteId)));
    }

    #[Test]
    public function itMasksDeletedSites(): void
    {
        $this->seedMembership($this->teamId, $this->userId, 'owner', '2026-01-01 00:00:00+00');
        $this->connection->update('sites', [
            'deleted_at' => '2026-02-01 00:00:00+00',
        ], [
            'id' => $this->siteId,
        ]);

        self::assertNull($this->provider->roleOnSite(Uuid::fromString($this->userId), Uuid::fromString($this->siteId)));
    }

    private function seedUser(string $id): void
    {
        $this->connection->insert('users', [
            'id' => $id,
            'email' => $id . '@example.com',
            'name' => 'Test',
            'created_at' => self::NOW,
            'updated_at' => self::NOW,
        ]);
    }

    private function seedTeam(string $id, string $ownerId): void
    {
        $this->connection->insert('teams', [
            'id' => $id,
            'name' => 'Team',
            'slug' => 't' . substr(str_replace('-', '', $id), 0, 20),
            'owner_id' => $ownerId,
            'created_at' => self::NOW,
            'updated_at' => self::NOW,
        ]);
    }

    private function seedSite(string $id, string $teamId): void
    {
        $this->connection->insert('sites', [
            'id' => $id,
            'team_id' => $teamId,
            'name' => 'Site',
            'domain' => 'access-' . substr(str_replace('-', '', $id), 0, 12) . '.example.com',
            'timezone' => 'UTC',
            'tracking_enabled' => true,
            'tracker_key' => 'stk_' . substr(str_replace('-', '', $id), 0, 28),
            'created_at' => self::NOW,
            'updated_at' => self::NOW,
        ]);
    }

    private function seedMembership(string $teamId, string $userId, string $role, ?string $acceptedAt): void
    {
        $this->connection->insert('team_members', [
            'id' => Uuid::generate()->getValue(),
            'team_id' => $teamId,
            'user_id' => $userId,
            'role' => $role,
            'invited_at' => self::NOW,
            'accepted_at' => $acceptedAt,
            'created_at' => self::NOW,
            'updated_at' => self::NOW,
        ]);
    }
}
