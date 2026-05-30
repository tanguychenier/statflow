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

namespace App\Tests\Integration\Analytics\Infrastructure\Doctrine;

use App\Analytics\Domain\Model\FunnelStep;
use App\Analytics\Domain\Model\FunnelTriggerType;
use App\Analytics\Infrastructure\Persistence\Doctrine\DbalFunnelRepository;
use App\Shared\Domain\ValueObject\Uuid;
use App\Tests\Integration\Analytics\Support\PostgresSeeder;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Persistence integration test for {@see DbalFunnelRepository}. Runs against a
 * real PostgreSQL schema (container phase) inside a rolled-back transaction.
 */
#[CoversClass(DbalFunnelRepository::class)]
final class DbalFunnelRepositoryTest extends KernelTestCase
{
    private Connection $connection;

    private DbalFunnelRepository $repository;

    private Uuid $siteId;

    protected function setUp(): void
    {
        self::bootKernel();

        /** @var Connection $connection */
        $connection = self::getContainer()->get(Connection::class);
        $this->connection = $connection;
        $this->connection->beginTransaction();

        $this->repository = new DbalFunnelRepository($this->connection);
        $this->siteId = (new PostgresSeeder($this->connection))->seedSite();
    }

    protected function tearDown(): void
    {
        $this->connection->rollBack();
        parent::tearDown();
    }

    #[Test]
    public function itLoadsAFunnelWithOrderedSteps(): void
    {
        $funnelId = $this->seedFunnel();

        $funnel = $this->repository->find($this->siteId, $funnelId);

        self::assertNotNull($funnel);
        self::assertSame('Signup', $funnel->name);
        self::assertSame(3, $funnel->stepCount());
        self::assertSame([0, 1, 2], array_map(static fn (FunnelStep $s): int => $s->stepIndex, $funnel->steps));
        self::assertSame(FunnelTriggerType::Pageview, $funnel->steps[0]->triggerType);
        self::assertSame('/pricing', $funnel->steps[1]->urlPattern);
        self::assertSame('signup', $funnel->steps[2]->eventName);
    }

    #[Test]
    public function itReturnsNullForAnUnknownFunnel(): void
    {
        self::assertNull($this->repository->find($this->siteId, Uuid::generate()));
    }

    #[Test]
    public function itIsolatesFunnelsBySite(): void
    {
        $funnelId = $this->seedFunnel();
        $other = (new PostgresSeeder($this->connection))->seedSite();

        self::assertNull($this->repository->find($other, $funnelId));
    }

    private function seedFunnel(): Uuid
    {
        $funnelId = Uuid::generate();
        $this->connection->insert('funnels', [
            'id' => (string) $funnelId,
            'site_id' => (string) $this->siteId,
            'name' => 'Signup',
            'created_at' => '2026-01-01 00:00:00+00',
            'updated_at' => '2026-01-01 00:00:00+00',
        ]);

        $steps = [
            [0, 'Landing', 'pageview', '/', null],
            [1, 'Pricing', 'pageview', '/pricing', null],
            [2, 'Signup', 'event', null, 'signup'],
        ];
        foreach ($steps as [$index, $name, $trigger, $url, $event]) {
            $this->connection->insert('funnel_steps', [
                'id' => (string) Uuid::generate(),
                'funnel_id' => (string) $funnelId,
                'step_index' => $index,
                'name' => $name,
                'trigger_type' => $trigger,
                'url_pattern' => $url,
                'event_name' => $event,
            ]);
        }

        return $funnelId;
    }
}
