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

namespace App\Tests\Integration\Ingestion\Infrastructure\Persistence;

use App\Ingestion\Domain\ValueObject\SiteKey;
use App\Ingestion\Infrastructure\Persistence\Doctrine\DbalSiteRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Runs against a real PostgreSQL with the schema from
 * docs/data-model/postgres-schema.sql loaded (the container phase). Seeds a
 * site + settings row and reads it back through the DBAL adapter, including the
 * text-array parsing and the disabled/deleted filters.
 */
#[CoversClass(DbalSiteRepository::class)]
final class DbalSiteRepositoryTest extends KernelTestCase
{
    private Connection $connection;

    private DbalSiteRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        try {
            /** @var Connection $connection */
            $connection = self::getContainer()->get('doctrine.dbal.default_connection');
            $connection->executeQuery('SELECT 1');
        } catch (\Throwable $e) {
            self::markTestSkipped('PostgreSQL is not available: ' . $e->getMessage());
        }

        $this->connection = $connection;
        $this->repository = new DbalSiteRepository($this->connection);
        $this->seedTeam();
    }

    #[Test]
    public function itResolvesAnEnabledSiteWithItsSettings(): void
    {
        $key = $this->seedSite('stk_enabledkey0000001', enabled: true, allowedDomains: ['example.com', '*.app.example.com']);

        $site = $this->repository->findByKey(SiteKey::fromString($key));

        self::assertNotNull($site);
        self::assertSame($key, $site->trackerKey);
        self::assertTrue($site->trackingEnabled);
        self::assertSame(['example.com', '*.app.example.com'], $site->allowedDomains);
        self::assertTrue($site->botFilteringEnabled);
    }

    #[Test]
    public function itReturnsNullForADisabledSite(): void
    {
        $key = $this->seedSite('stk_disabledkey000001', enabled: false);

        self::assertNull($this->repository->findByKey(SiteKey::fromString($key)));
    }

    #[Test]
    public function itReturnsNullForAnUnknownKey(): void
    {
        self::assertNull($this->repository->findByKey(SiteKey::fromString('stk_neverseen00000001')));
    }

    #[Test]
    public function itCachesLookupsWithinARequest(): void
    {
        $key = $this->seedSite('stk_cachedkey00000001', enabled: true);

        $first = $this->repository->findByKey(SiteKey::fromString($key));
        // Delete the row; a cached lookup must still return the site.
        $this->connection->executeStatement('DELETE FROM sites WHERE tracker_key = :k', [
            'k' => $key,
        ]);
        $second = $this->repository->findByKey(SiteKey::fromString($key));

        self::assertNotNull($first);
        self::assertNotNull($second);
    }

    private function seedTeam(): void
    {
        $this->connection->executeStatement('DELETE FROM site_settings');
        $this->connection->executeStatement('DELETE FROM sites');
        $this->connection->executeStatement("DELETE FROM teams WHERE name = 'ingestion-test'");

        $this->connection->executeStatement('DELETE FROM users WHERE name = :name', [
            'name' => 'ingestion-test-owner',
        ]);

        $this->connection->executeStatement(
            <<<'SQL'
                INSERT INTO users (id, email, name, created_at, updated_at)
                VALUES (:id, :email, 'ingestion-test-owner', NOW(), NOW())
                ON CONFLICT DO NOTHING
                SQL,
            [
                'id' => '00000000-0000-4000-8000-000000000003',
                'email' => 'ingestion-owner@example.com',
            ],
        );

        $this->connection->executeStatement(
            <<<'SQL'
                INSERT INTO teams (id, name, slug, owner_id, created_at, updated_at)
                VALUES (:id, 'ingestion-test', 'ingestion-test', :owner, NOW(), NOW())
                ON CONFLICT DO NOTHING
                SQL,
            [
                'id' => '00000000-0000-4000-8000-000000000001',
                'owner' => '00000000-0000-4000-8000-000000000003',
            ],
        );
    }

    /**
     * @param list<string> $allowedDomains
     */
    private function seedSite(string $trackerKey, bool $enabled, array $allowedDomains = []): string
    {
        $siteId = sprintf('%08x-0000-4000-8000-000000000002', crc32($trackerKey));

        $this->connection->executeStatement(
            <<<'SQL'
                INSERT INTO sites (id, team_id, name, domain, tracker_key, tracking_enabled, created_at, updated_at)
                VALUES (:id, :team, :name, :domain, :key, :enabled, NOW(), NOW())
                SQL,
            [
                'id' => $siteId,
                'team' => '00000000-0000-4000-8000-000000000001',
                'name' => 'Test',
                'domain' => 'example.com',
                'key' => $trackerKey,
                'enabled' => $enabled ? 'true' : 'false',
            ],
        );

        $this->connection->executeStatement(
            <<<'SQL'
                INSERT INTO site_settings (site_id, allowed_domains, bot_filtering_enabled, updated_at)
                VALUES (:id, CAST(:domains AS JSONB), TRUE, NOW())
                SQL,
            [
                'id' => $siteId,
                'domains' => json_encode(array_values($allowedDomains), JSON_THROW_ON_ERROR),
            ],
        );

        return $trackerKey;
    }
}
