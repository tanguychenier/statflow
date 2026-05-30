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

namespace App\Tests\Integration\Sites\Infrastructure\Http;

use App\Shared\Domain\ValueObject\Uuid;
use App\Sites\Infrastructure\Http\Controller\SiteCollectionController;
use App\Sites\Infrastructure\Http\Controller\SiteItemController;
use App\Sites\Infrastructure\Http\Controller\SiteSettingsController;
use App\Sites\Infrastructure\Http\Controller\TrackerKeyController;
use App\Tests\Integration\Sites\Support\FixedActingUserResolver;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * End-to-end HTTP test of the Sites CRUD surface. Exercises the full pipeline:
 * controller, command/query bus, handlers, Doctrine repositories, and the
 * raw-DBAL membership provider. Runs in the container phase against real
 * PostgreSQL with the buses routed synchronously (sync:// in the test env).
 *
 * Wiring this test depends on (owned by the wiring agent, documented in the
 * report): ActingUserResolver aliased to {@see FixedActingUserResolver} in the
 * test environment, and all Sites handlers registered on the command/query bus.
 */
#[CoversClass(SiteCollectionController::class)]
#[CoversClass(SiteItemController::class)]
#[CoversClass(SiteSettingsController::class)]
#[CoversClass(TrackerKeyController::class)]
final class SiteCrudControllerTest extends WebTestCase
{
    private const NOW = '2026-01-01 00:00:00+00';

    private KernelBrowser $client;

    private Connection $connection;

    private string $ownerId;

    private string $editorId;

    private string $teamId;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        // Keep one kernel/container (and one DBAL connection) across the requests
        // a single test makes, so the seeding transaction wraps them all.
        $this->client->disableReboot();

        /** @var Connection $connection */
        $connection = self::getContainer()->get(Connection::class);
        $this->connection = $connection;
        $this->connection->beginTransaction();

        $this->ownerId = Uuid::generate()->getValue();
        $this->editorId = Uuid::generate()->getValue();
        $this->teamId = Uuid::generate()->getValue();

        $this->seedUser($this->ownerId, 'owner@example.com');
        $this->seedUser($this->editorId, 'editor@example.com');
        $this->seedTeam($this->teamId, $this->ownerId);
        $this->seedMembership($this->teamId, $this->ownerId, 'owner');
        $this->seedMembership($this->teamId, $this->editorId, 'editor');

        FixedActingUserResolver::$userId = $this->ownerId;
    }

    protected function tearDown(): void
    {
        $this->connection->rollBack();
        parent::tearDown();
    }

    #[Test]
    public function fullSiteLifecycle(): void
    {
        $siteId = $this->createSite();

        // Read it back.
        $this->client->request('GET', '/api/v1/sites/' . $siteId);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        // Patch the name (owner is allowed).
        $this->client->request('PATCH', '/api/v1/sites/' . $siteId, server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: (string) json_encode([
            'name' => 'Renamed',
        ]));
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame('Renamed', $this->decode()['name']);

        // Replace settings.
        $this->client->request('PUT', '/api/v1/sites/' . $siteId . '/settings', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: (string) json_encode([
            'allowed_domains' => ['*.example.com'],
            'data_retention_days' => 90,
        ]));
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame(90, $this->decode()['data_retention_days']);

        // Rotate the tracker key.
        $this->client->request('POST', '/api/v1/sites/' . $siteId . '/tracker-key/rotate');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertArrayHasKey('old_key_valid_until', $this->decode());

        // Delete it.
        $this->client->request('DELETE', '/api/v1/sites/' . $siteId);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // Gone afterwards.
        $this->client->request('GET', '/api/v1/sites/' . $siteId);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    #[Test]
    public function createReturns422OnInvalidDomain(): void
    {
        $this->client->request('POST', '/api/v1/sites', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: (string) json_encode([
            'team_id' => $this->teamId,
            'name' => 'Bad',
            'domain' => 'https://example.com',
        ]));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
    }

    #[Test]
    public function duplicateDomainReturns409(): void
    {
        $this->createSite('dup.example.com');

        $this->client->request('POST', '/api/v1/sites', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: (string) json_encode([
            'team_id' => $this->teamId,
            'name' => 'Second',
            'domain' => 'dup.example.com',
        ]));

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }

    #[Test]
    public function editorCannotRotateTrackerKey(): void
    {
        $siteId = $this->createSite();

        FixedActingUserResolver::$userId = $this->editorId;
        $this->client->request('POST', '/api/v1/sites/' . $siteId . '/tracker-key/rotate');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    #[Test]
    public function outsiderGets404OnSite(): void
    {
        $siteId = $this->createSite();

        FixedActingUserResolver::$userId = Uuid::generate()->getValue();
        $this->client->request('GET', '/api/v1/sites/' . $siteId);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    #[Test]
    public function listReturnsPaginatedEnvelope(): void
    {
        $this->createSite('a.example.com');
        $this->createSite('b.example.com');

        $this->client->request('GET', '/api/v1/sites?team_id=' . $this->teamId);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $body = $this->decode();
        self::assertArrayHasKey('data', $body);
        self::assertArrayHasKey('pagination', $body);
        self::assertIsArray($body['data']);
        self::assertGreaterThanOrEqual(2, count($body['data']));
    }

    private function createSite(string $domain = 'example.com'): string
    {
        $this->client->request('POST', '/api/v1/sites', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: (string) json_encode([
            'team_id' => $this->teamId,
            'name' => 'My Site',
            'domain' => $domain,
            'timezone' => 'Europe/Paris',
        ]));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $body = $this->decode();
        self::assertIsString($body['tracker_key']);
        self::assertStringStartsWith('stk_', $body['tracker_key']);

        $id = $body['id'];
        self::assertIsString($id);
        return $id;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(): array
    {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

        return $decoded;
    }

    private function seedUser(string $id, string $email): void
    {
        $this->connection->insert('users', [
            'id' => $id,
            'email' => $email,
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

    private function seedMembership(string $teamId, string $userId, string $role): void
    {
        $this->connection->insert('team_members', [
            'id' => Uuid::generate()->getValue(),
            'team_id' => $teamId,
            'user_id' => $userId,
            'role' => $role,
            'invited_at' => self::NOW,
            'accepted_at' => self::NOW,
            'created_at' => self::NOW,
            'updated_at' => self::NOW,
        ]);
    }
}
