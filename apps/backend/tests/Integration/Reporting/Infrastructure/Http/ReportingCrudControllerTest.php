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

namespace App\Tests\Integration\Reporting\Infrastructure\Http;

use App\Reporting\Infrastructure\Http\Controller\AlertController;
use App\Reporting\Infrastructure\Http\Controller\ExportController;
use App\Reporting\Infrastructure\Http\Controller\SavedReportController;
use App\Reporting\Infrastructure\Http\Controller\ScheduledReportController;
use App\Shared\Domain\ValueObject\Uuid;
use App\Tests\Integration\Reporting\Support\FixedActingUserResolver;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * End-to-end HTTP test of the Reporting CRUD surface. Exercises the full
 * pipeline: controllers, command/query bus, handlers, Doctrine repositories,
 * and the raw-DBAL site-access provider. Runs in the container phase against
 * real PostgreSQL with the buses routed synchronously (sync:// in the test env).
 *
 * Wiring this test depends on (owned by the wiring agent, documented in the
 * report): the Reporting ActingUserResolver aliased to
 * {@see FixedActingUserResolver} in the test environment, all Reporting handlers
 * registered on the buses, and the export job dispatched synchronously so the
 * artifact is generated within the request.
 */
#[CoversClass(SavedReportController::class)]
#[CoversClass(ScheduledReportController::class)]
#[CoversClass(AlertController::class)]
#[CoversClass(ExportController::class)]
final class ReportingCrudControllerTest extends WebTestCase
{
    private const NOW = '2026-01-01 00:00:00+00';

    private KernelBrowser $client;

    private Connection $connection;

    private string $editorId;

    private string $viewerId;

    private string $teamId;

    private string $siteId;

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

        $this->editorId = Uuid::generate()->getValue();
        $this->viewerId = Uuid::generate()->getValue();
        $this->teamId = Uuid::generate()->getValue();
        $this->siteId = Uuid::generate()->getValue();

        $this->seedUser($this->editorId);
        $this->seedUser($this->viewerId);
        $this->seedTeam($this->teamId, $this->editorId);
        $this->seedMembership($this->teamId, $this->editorId, 'editor');
        $this->seedMembership($this->teamId, $this->viewerId, 'viewer');
        $this->seedSite($this->siteId, $this->teamId);

        FixedActingUserResolver::$userId = $this->editorId;
    }

    protected function tearDown(): void
    {
        $this->connection->rollBack();
        parent::tearDown();
    }

    #[Test]
    public function savedReportLifecycle(): void
    {
        $id = $this->createSavedReport();

        $this->client->request('GET', $this->path('reports/' . $id));
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame('breakdown', $this->decode()['report_type']);

        $this->client->request('GET', $this->path('reports'));
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertArrayHasKey('pagination', $this->decode());

        $this->client->request('DELETE', $this->path('reports/' . $id));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->client->request('GET', $this->path('reports/' . $id));
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    #[Test]
    public function scheduledReportLifecycle(): void
    {
        $this->client->request('POST', $this->path('scheduled-reports'), server: $this->json(), content: (string) json_encode([
            'name' => 'Weekly digest',
            'recipients' => ['a@b.com', 'c@d.com'],
            'schedule_cron' => '0 9 * * 1',
            'timezone' => 'Europe/Paris',
        ]));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $id = $this->decode()['id'];
        self::assertIsString($id);

        $this->client->request('PATCH', $this->path('scheduled-reports/' . $id), server: $this->json(), content: (string) json_encode([
            'is_active' => false,
        ]));
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertFalse($this->decode()['is_active']);

        $this->client->request('DELETE', $this->path('scheduled-reports/' . $id));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    #[Test]
    public function scheduledReportRejectsSubDailyCron(): void
    {
        $this->client->request('POST', $this->path('scheduled-reports'), server: $this->json(), content: (string) json_encode([
            'name' => 'Too frequent',
            'recipients' => ['a@b.com'],
            'schedule_cron' => '*/5 * * * *',
            'timezone' => 'UTC',
        ]));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
    }

    #[Test]
    public function alertLifecycle(): void
    {
        $this->client->request('POST', $this->path('alerts'), server: $this->json(), content: (string) json_encode([
            'name' => 'Traffic spike',
            'metric' => 'pageviews',
            'condition' => 'above',
            'threshold' => 1000,
            'notification_channels' => [[
                'type' => 'email',
                'email' => 'ops@example.com',
            ]],
        ]));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $id = $this->decode()['id'];
        self::assertIsString($id);

        $this->client->request('PATCH', $this->path('alerts/' . $id), server: $this->json(), content: (string) json_encode([
            'threshold' => 2000,
            'is_active' => false,
        ]));
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        // JSON has a single number type, so a whole-valued threshold may decode as
        // int; compare the numeric value rather than its PHP type.
        $threshold = $this->decode()['threshold'];
        self::assertIsNumeric($threshold);
        self::assertEqualsWithDelta(2000.0, (float) $threshold, 0.0001);

        $this->client->request('DELETE', $this->path('alerts/' . $id));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    #[Test]
    public function alertRejectsPercentageWithoutComparisonPeriod(): void
    {
        $this->client->request('POST', $this->path('alerts'), server: $this->json(), content: (string) json_encode([
            'name' => 'Drop',
            'metric' => 'visitors',
            'condition' => 'change_pct_below',
            'threshold' => -30,
            'notification_channels' => [[
                'type' => 'email',
                'email' => 'ops@example.com',
            ]],
        ]));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    #[Test]
    public function exportRequestAndPoll(): void
    {
        $this->client->request('POST', $this->path('exports'), server: $this->json(), content: (string) json_encode([
            'format' => 'csv',
            'query' => [
                'report_type' => 'breakdown',
                'property' => 'page',
            ],
        ]));
        self::assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);
        $id = $this->decode()['id'];
        self::assertIsString($id);

        $this->client->request('GET', $this->path('exports/' . $id));
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertContains($this->decode()['status'], ['pending', 'processing', 'completed']);
    }

    #[Test]
    public function viewerCannotCreateButCanList(): void
    {
        FixedActingUserResolver::$userId = $this->viewerId;

        $this->client->request('POST', $this->path('reports'), server: $this->json(), content: (string) json_encode([
            'name' => 'Nope',
            'report_type' => 'aggregate',
            'query' => [],
        ]));
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $this->client->request('GET', $this->path('reports'));
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    #[Test]
    public function outsiderGets404OnList(): void
    {
        FixedActingUserResolver::$userId = Uuid::generate()->getValue();

        $this->client->request('GET', $this->path('reports/' . Uuid::generate()->getValue()));
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    private function createSavedReport(): string
    {
        $this->client->request('POST', $this->path('reports'), server: $this->json(), content: (string) json_encode([
            'name' => 'Top pages',
            'description' => 'Most visited pages',
            'report_type' => 'breakdown',
            'query' => [
                'property' => 'page',
            ],
        ]));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $id = $this->decode()['id'];
        self::assertIsString($id);
        return $id;
    }

    private function path(string $suffix): string
    {
        return sprintf('/api/v1/sites/%s/%s', $this->siteId, $suffix);
    }

    /**
     * @return array<string, string>
     */
    private function json(): array
    {
        return [
            'CONTENT_TYPE' => 'application/json',
        ];
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

    private function seedSite(string $id, string $teamId): void
    {
        $this->connection->insert('sites', [
            'id' => $id,
            'team_id' => $teamId,
            'name' => 'Site',
            'domain' => 'rep-' . substr(str_replace('-', '', $id), 0, 12) . '.example.com',
            'timezone' => 'UTC',
            'tracking_enabled' => true,
            'tracker_key' => 'stk_' . substr(str_replace('-', '', $id), 0, 28),
            'created_at' => self::NOW,
            'updated_at' => self::NOW,
        ]);
    }
}
