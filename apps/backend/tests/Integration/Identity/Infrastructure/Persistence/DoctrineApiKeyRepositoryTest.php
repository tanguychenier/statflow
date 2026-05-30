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

namespace App\Tests\Integration\Identity\Infrastructure\Persistence;

use App\Identity\Domain\Model\ApiKey;
use App\Identity\Domain\Port\ApiKeyRepository;
use App\Identity\Domain\ValueObject\ApiKeyScope;
use App\Shared\Domain\ValueObject\Uuid;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Verifies the Doctrine API-key adapter against real PostgreSQL, exercising the
 * native TEXT[]/UUID[] array column mapping (scopes, site_ids) and the hash
 * lookup used by Bearer authentication. Runs in the container phase against the
 * migrated schema; wrapped in a rolled-back transaction.
 */
#[CoversNothing]
final class DoctrineApiKeyRepositoryTest extends KernelTestCase
{
    private const NOW = '2026-01-01 00:00:00+00';

    private EntityManagerInterface $em;

    private ApiKeyRepository $apiKeys;

    private Uuid $teamId;

    private Uuid $userId;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->apiKeys = self::getContainer()->get(ApiKeyRepository::class);
        $this->em->getConnection()->beginTransaction();

        $this->userId = Uuid::generate();
        $this->teamId = Uuid::generate();
        $this->seedTeam();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    #[Test]
    public function itRoundTripsScopesAndSiteIdsThroughPostgresArrays(): void
    {
        $id = Uuid::generate();
        $siteId = Uuid::generate();

        $this->apiKeys->save(ApiKey::issue(
            $id,
            $this->teamId,
            $this->userId,
            'CI',
            hash('sha256', 'sfk_live_secret'),
            'sfk_live_abc',
            [ApiKeyScope::AnalyticsRead, ApiKeyScope::ReportsRead],
            [$siteId],
            null,
            new \DateTimeImmutable(),
        ));
        $this->em->clear();

        $found = $this->apiKeys->findById($id);

        self::assertNotNull($found);
        self::assertCount(2, $found->scopes());
        self::assertTrue($found->hasScope(ApiKeyScope::AnalyticsRead));
        self::assertTrue($found->allowsSite($siteId));
        self::assertFalse($found->allowsSite(Uuid::generate()));
    }

    #[Test]
    public function itFindsAKeyByItsHash(): void
    {
        $hash = hash('sha256', 'sfk_live_secret');
        $this->apiKeys->save(ApiKey::issue(
            Uuid::generate(),
            $this->teamId,
            $this->userId,
            'CI',
            $hash,
            'sfk_live_abc',
            [ApiKeyScope::AnalyticsRead],
            [],
            null,
            new \DateTimeImmutable(),
        ));
        $this->em->clear();

        self::assertNotNull($this->apiKeys->findByHash($hash));
        self::assertNull($this->apiKeys->findByHash(hash('sha256', 'wrong')));
    }

    private function seedTeam(): void
    {
        $connection = $this->em->getConnection();
        $short = substr((string) $this->teamId, 0, 12);

        $connection->insert('users', [
            'id' => (string) $this->userId,
            'email' => 'apikey-' . $short . '@example.com',
            'name' => 'Owner',
            'created_at' => self::NOW,
            'updated_at' => self::NOW,
        ]);

        $connection->insert('teams', [
            'id' => (string) $this->teamId,
            'name' => 'Team ' . $short,
            'slug' => 't' . substr(str_replace('-', '', (string) $this->teamId), 0, 20),
            'owner_id' => (string) $this->userId,
            'created_at' => self::NOW,
            'updated_at' => self::NOW,
        ]);
    }
}
