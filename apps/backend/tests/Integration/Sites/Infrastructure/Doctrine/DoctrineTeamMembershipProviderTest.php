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

namespace App\Tests\Integration\Sites\Infrastructure\Doctrine;

use App\Shared\Domain\ValueObject\Uuid;
use App\Sites\Domain\Model\TeamRole;
use App\Sites\Infrastructure\Doctrine\DoctrineTeamMembershipProvider;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration test for the raw-DBAL membership reader against the shared
 * PostgreSQL `team_members`/`teams`/`users` tables. Runs in the container phase;
 * inserts test rows inside a rolled-back transaction.
 */
#[CoversClass(DoctrineTeamMembershipProvider::class)]
final class DoctrineTeamMembershipProviderTest extends KernelTestCase
{
    private const NOW = '2026-01-01 00:00:00+00';

    private Connection $connection;

    private DoctrineTeamMembershipProvider $provider;

    private string $userId;

    private string $ownerId;

    private string $teamId;

    protected function setUp(): void
    {
        self::bootKernel();

        /** @var Connection $connection */
        $connection = self::getContainer()->get(Connection::class);
        $this->connection = $connection;
        $this->provider = new DoctrineTeamMembershipProvider($this->connection);

        $this->connection->beginTransaction();

        $this->userId = Uuid::generate()->getValue();
        $this->ownerId = Uuid::generate()->getValue();
        $this->teamId = Uuid::generate()->getValue();

        $this->insertUser($this->userId, 'member@example.com');
        $this->insertUser($this->ownerId, 'owner@example.com');
        $this->insertTeam($this->teamId, $this->ownerId);
    }

    protected function tearDown(): void
    {
        $this->connection->rollBack();
        parent::tearDown();
    }

    #[Test]
    public function itReturnsAcceptedMemberRole(): void
    {
        $this->insertMembership($this->teamId, $this->userId, 'admin', accepted: true);

        $role = $this->provider->roleOf(Uuid::fromString($this->userId), Uuid::fromString($this->teamId));

        self::assertSame(TeamRole::Admin, $role);
    }

    #[Test]
    public function pendingInvitationsCountAsNonMembers(): void
    {
        $this->insertMembership($this->teamId, $this->userId, 'editor', accepted: false);

        self::assertNull($this->provider->roleOf(Uuid::fromString($this->userId), Uuid::fromString($this->teamId)));
        self::assertSame([], $this->provider->accessibleTeamIds(Uuid::fromString($this->userId)));
    }

    #[Test]
    public function accessibleTeamIdsListsAcceptedTeamsOnly(): void
    {
        $secondTeam = Uuid::generate()->getValue();
        $this->insertTeam($secondTeam, $this->ownerId);

        $this->insertMembership($this->teamId, $this->userId, 'viewer', accepted: true);
        $this->insertMembership($secondTeam, $this->userId, 'viewer', accepted: false);

        $teams = $this->provider->accessibleTeamIds(Uuid::fromString($this->userId));

        self::assertCount(1, $teams);
        self::assertSame($this->teamId, $teams[0]->getValue());
    }

    private function insertUser(string $id, string $email): void
    {
        $this->connection->insert('users', [
            'id' => $id,
            'email' => $email,
            'name' => 'Test',
            'created_at' => self::NOW,
            'updated_at' => self::NOW,
        ]);
    }

    private function insertTeam(string $id, string $ownerId): void
    {
        $this->connection->insert('teams', [
            'id' => $id,
            'name' => 'Team ' . substr($id, 0, 8),
            'slug' => 't' . substr(str_replace('-', '', $id), 0, 20),
            'owner_id' => $ownerId,
            'created_at' => self::NOW,
            'updated_at' => self::NOW,
        ]);
    }

    private function insertMembership(string $teamId, string $userId, string $role, bool $accepted): void
    {
        $this->connection->insert('team_members', [
            'id' => Uuid::generate()->getValue(),
            'team_id' => $teamId,
            'user_id' => $userId,
            'role' => $role,
            'invited_at' => self::NOW,
            'accepted_at' => $accepted ? self::NOW : null,
            'created_at' => self::NOW,
            'updated_at' => self::NOW,
        ]);
    }
}
