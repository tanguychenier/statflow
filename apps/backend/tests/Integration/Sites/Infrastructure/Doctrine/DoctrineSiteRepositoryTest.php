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
use App\Sites\Domain\Model\Site;
use App\Sites\Domain\Port\SiteListCriteria;
use App\Sites\Domain\ValueObject\AllowedDomainList;
use App\Sites\Domain\ValueObject\ExcludedIpList;
use App\Sites\Domain\ValueObject\Hostname;
use App\Sites\Domain\ValueObject\RetentionDays;
use App\Sites\Domain\ValueObject\SiteName;
use App\Sites\Domain\ValueObject\Timezone;
use App\Sites\Domain\ValueObject\TrackerConfig;
use App\Sites\Domain\ValueObject\TrackerKey;
use App\Sites\Infrastructure\Doctrine\DoctrineSiteRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Persistence integration test for {@see DoctrineSiteRepository}.
 *
 * Runs against a real PostgreSQL schema in the container phase. Each test runs
 * inside a transaction rolled back in tearDown so cases stay isolated.
 */
#[CoversClass(DoctrineSiteRepository::class)]
final class DoctrineSiteRepositoryTest extends KernelTestCase
{
    private const NOW = '2026-01-01 00:00:00+00';

    private EntityManagerInterface $entityManager;

    private DoctrineSiteRepository $repository;

    /**
     * @var array<string, true>
     */
    private array $seededTeams = [];

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @phpstan-ignore-next-line */
        $em = $container->get(EntityManagerInterface::class);
        assert($em instanceof EntityManagerInterface);
        $this->entityManager = $em;
        $this->repository = new DoctrineSiteRepository($this->entityManager);

        $this->entityManager->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->entityManager->getConnection()->rollBack();
        parent::tearDown();
    }

    #[Test]
    public function itPersistsAndReloadsASiteWithSettings(): void
    {
        $site = $this->newSite('persist.example.com', 'p');
        $site->replaceSettings(
            allowedDomains: AllowedDomainList::fromArray(['*.persist.example.com']),
            excludedIps: ExcludedIpList::fromArray(['10.1.2.3']),
            stripQueryParams: true,
            customDomainEnabled: false,
            retentionDays: RetentionDays::fromInt(120),
            trackerConfig: TrackerConfig::default(),
            now: new DateTimeImmutable('2026-01-02T00:00:00+00:00'),
        );

        $this->repository->save($site);
        $this->entityManager->clear();

        $reloaded = $this->repository->findById($site->id());

        self::assertNotNull($reloaded);
        self::assertSame('persist.example.com', $reloaded->domain()->value());
        self::assertSame(120, $reloaded->retentionDays()?->value());
        self::assertSame(['*.persist.example.com'], $reloaded->settings()->allowedDomains()->toArray());
        self::assertSame(['10.1.2.3'], $reloaded->settings()->excludedIps()->toArray());
        self::assertTrue($reloaded->settings()->stripQueryParams());
    }

    #[Test]
    public function softDeletedSitesAreNotReturnedByFinders(): void
    {
        $site = $this->newSite('deleted.example.com', 'd');
        $this->repository->save($site);

        $site->softDelete(new DateTimeImmutable('2026-02-01T00:00:00+00:00'));
        $this->repository->save($site);
        $this->entityManager->clear();

        self::assertNull($this->repository->findById($site->id()));
        self::assertNull($this->repository->findByTrackerKey($site->trackerKey()));
    }

    #[Test]
    public function domainUniquenessIsScopedPerTeamAndIgnoresSelf(): void
    {
        $teamId = Uuid::generate();
        $site = $this->newSiteForTeam($teamId, 'unique.example.com', 'u');
        $this->repository->save($site);
        $this->entityManager->clear();

        self::assertTrue($this->repository->domainExistsInTeam($teamId, Hostname::fromString('unique.example.com')));
        self::assertFalse($this->repository->domainExistsInTeam(Uuid::generate(), Hostname::fromString('unique.example.com')));
        self::assertFalse(
            $this->repository->domainExistsInTeam($teamId, Hostname::fromString('unique.example.com'), $site->id()),
            'a site must not conflict with itself',
        );
    }

    #[Test]
    public function rotationReplacesTheSingleTrackerKeyAndFreesTheOldOne(): void
    {
        // ADR-0009 §1: one key per site. After rotation only the new key occupies
        // the namespace; the old value is overwritten with no grace window.
        $site = $this->newSite('rotate.example.com', 'r');
        $oldKey = $site->trackerKey();
        $this->repository->save($site);

        $newKey = TrackerKey::fromString('stk_' . str_repeat('x', 32));
        $site->rotateTrackerKey($newKey, new DateTimeImmutable('2026-03-01T00:00:00+00:00'));
        $this->repository->save($site);
        $this->entityManager->clear();

        self::assertTrue($this->repository->trackerKeyExists($newKey));
        self::assertFalse($this->repository->trackerKeyExists($oldKey), 'the old key is freed immediately');

        $reloaded = $this->repository->findByTrackerKey($newKey);
        self::assertNotNull($reloaded);
        self::assertNull($this->repository->findByTrackerKey($oldKey));
    }

    #[Test]
    public function listPaginatesByCursor(): void
    {
        $teamId = Uuid::generate();
        for ($i = 0; $i < 3; ++$i) {
            $site = $this->newSiteForTeam($teamId, "list{$i}.example.com", chr(ord('a') + $i));
            $this->repository->save($site);
        }
        $this->entityManager->clear();

        $criteria = SiteListCriteria::create(accessibleTeamIds: [$teamId], limit: 2);
        $firstPage = $this->repository->list($criteria);

        self::assertCount(2, $firstPage->sites);
        self::assertNotNull($firstPage->nextCursor);

        $secondPage = $this->repository->list(SiteListCriteria::create(
            accessibleTeamIds: [$teamId],
            cursor: $firstPage->nextCursor,
            limit: 2,
        ));

        self::assertCount(1, $secondPage->sites);
        self::assertNull($secondPage->nextCursor);
    }

    #[Test]
    public function listSearchFiltersByNameOrDomain(): void
    {
        $teamId = Uuid::generate();
        $this->repository->save($this->newSiteForTeam($teamId, 'searchable.example.com', 's', 'Findable'));
        $this->repository->save($this->newSiteForTeam($teamId, 'other.example.com', 'o', 'Hidden'));
        $this->entityManager->clear();

        $page = $this->repository->list(SiteListCriteria::create(accessibleTeamIds: [$teamId], search: 'findable'));

        self::assertCount(1, $page->sites);
        self::assertSame('searchable.example.com', $page->sites[0]->domain()->value());
    }

    private function newSite(string $domain, string $keyChar): Site
    {
        return $this->newSiteForTeam(Uuid::generate(), $domain, $keyChar);
    }

    private function newSiteForTeam(Uuid $teamId, string $domain, string $keyChar, string $name = 'Site'): Site
    {
        $this->ensureTeam($teamId);

        return Site::register(
            id: Uuid::generate(),
            teamId: $teamId,
            name: SiteName::fromString($name),
            domain: Hostname::fromString($domain),
            timezone: Timezone::default(),
            trackerKey: TrackerKey::fromString('stk_' . str_repeat($keyChar, 32)),
            now: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
        );
    }

    private function ensureTeam(Uuid $teamId): void
    {
        $key = $teamId->getValue();
        if (isset($this->seededTeams[$key])) {
            return;
        }
        $this->seededTeams[$key] = true;

        $connection = $this->entityManager->getConnection();
        $short = substr(str_replace('-', '', $key), 0, 12);
        $userId = Uuid::generate()->getValue();

        $connection->insert('users', [
            'id' => $userId,
            'email' => 'owner-' . $short . '@example.com',
            'name' => 'Owner',
            'created_at' => self::NOW,
            'updated_at' => self::NOW,
        ]);

        $connection->insert('teams', [
            'id' => $key,
            'name' => 'Team ' . $short,
            'slug' => 't' . substr(str_replace('-', '', $key), 0, 20),
            'owner_id' => $userId,
            'created_at' => self::NOW,
            'updated_at' => self::NOW,
        ]);
    }
}
