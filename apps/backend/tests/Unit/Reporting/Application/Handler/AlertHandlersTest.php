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

namespace App\Tests\Unit\Reporting\Application\Handler;

use App\Reporting\Application\Command\CreateAlertCommand;
use App\Reporting\Application\Command\DeleteAlertCommand;
use App\Reporting\Application\Command\UpdateAlertCommand;
use App\Reporting\Application\Handler\CreateAlertHandler;
use App\Reporting\Application\Handler\DeleteAlertHandler;
use App\Reporting\Application\Handler\ListAlertsHandler;
use App\Reporting\Application\Handler\UpdateAlertHandler;
use App\Reporting\Application\Query\ListAlertsQuery;
use App\Reporting\Domain\Exception\InvalidAlertException;
use App\Reporting\Domain\Exception\PermissionDeniedException;
use App\Reporting\Domain\Exception\ReportNotFoundException;
use App\Reporting\Domain\Model\TeamRole;
use App\Reporting\Domain\Service\ReportingAccessPolicy;
use App\Shared\Domain\ValueObject\Uuid;
use App\Tests\Unit\Reporting\Fake\FrozenClock;
use App\Tests\Unit\Reporting\Fake\InMemoryAlertRepository;
use App\Tests\Unit\Reporting\Fake\InMemorySiteAccessProvider;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreateAlertHandler::class)]
#[CoversClass(UpdateAlertHandler::class)]
#[CoversClass(ListAlertsHandler::class)]
#[CoversClass(DeleteAlertHandler::class)]
final class AlertHandlersTest extends TestCase
{
    private const USER = '11111111-1111-4111-8111-111111111111';

    private const SITE = '22222222-2222-4222-8222-222222222222';

    private InMemoryAlertRepository $repo;

    private InMemorySiteAccessProvider $access;

    private FrozenClock $clock;

    protected function setUp(): void
    {
        $this->repo = new InMemoryAlertRepository();
        $this->access = new InMemorySiteAccessProvider();
        $this->clock = new FrozenClock(new DateTimeImmutable('2026-05-29T08:00:00', new DateTimeZone('UTC')));
    }

    #[Test]
    public function editorCreatesAbsoluteAlert(): void
    {
        $this->access->grant(self::USER, self::SITE, TeamRole::Editor);

        $created = ($this->create())($this->absoluteCommand());

        self::assertSame('pageviews', $created->metric);
        self::assertSame('above', $created->condition);
        self::assertSame(100.0, $created->threshold);
        self::assertCount(1, $created->notificationChannels);
    }

    #[Test]
    public function createPercentageAlertNeedsComparisonPeriod(): void
    {
        $this->access->grant(self::USER, self::SITE, TeamRole::Editor);

        $this->expectException(InvalidAlertException::class);
        ($this->create())(new CreateAlertCommand(
            self::USER,
            self::SITE,
            'pct',
            'pageviews',
            'change_pct_above',
            20.0,
            null,
            [],
            [[
                'type' => 'email',
                'email' => 'a@b.com',
            ]],
        ));
    }

    #[Test]
    public function viewerCannotCreate(): void
    {
        $this->access->grant(self::USER, self::SITE, TeamRole::Viewer);

        $this->expectException(PermissionDeniedException::class);
        ($this->create())($this->absoluteCommand());
    }

    #[Test]
    public function updateChangesThresholdAndDeactivates(): void
    {
        $this->access->grant(self::USER, self::SITE, TeamRole::Editor);
        $created = ($this->create())($this->absoluteCommand());

        $updated = ($this->update())(new UpdateAlertCommand(
            actingUserId: self::USER,
            siteId: self::SITE,
            alertId: $created->id,
            threshold: 250.0,
            isActive: false,
        ));

        self::assertSame(250.0, $updated->threshold);
        self::assertFalse($updated->isActive);
    }

    #[Test]
    public function updateRejectsEmptyChannelList(): void
    {
        $this->access->grant(self::USER, self::SITE, TeamRole::Editor);
        $created = ($this->create())($this->absoluteCommand());

        $this->expectException(InvalidAlertException::class);
        ($this->update())(new UpdateAlertCommand(
            actingUserId: self::USER,
            siteId: self::SITE,
            alertId: $created->id,
            notificationChannels: [],
        ));
    }

    #[Test]
    public function updateRejectsUnknownAlert(): void
    {
        $this->access->grant(self::USER, self::SITE, TeamRole::Editor);

        $this->expectException(ReportNotFoundException::class);
        ($this->update())(new UpdateAlertCommand(self::USER, self::SITE, Uuid::generate()->getValue(), name: 'x'));
    }

    #[Test]
    public function listAndDelete(): void
    {
        $this->access->grant(self::USER, self::SITE, TeamRole::Editor);
        $created = ($this->create())($this->absoluteCommand());

        $page = ($this->list())(new ListAlertsQuery(self::USER, self::SITE));
        self::assertCount(1, $page->data);

        ($this->delete())(new DeleteAlertCommand(self::USER, self::SITE, $created->id));
        self::assertNull($this->repo->findById(Uuid::fromString(self::SITE), Uuid::fromString($created->id)));
    }

    private function absoluteCommand(): CreateAlertCommand
    {
        return new CreateAlertCommand(
            actingUserId: self::USER,
            siteId: self::SITE,
            name: 'Traffic',
            metric: 'pageviews',
            condition: 'above',
            threshold: 100.0,
            comparisonPeriod: null,
            filters: [],
            notificationChannels: [[
                'type' => 'email',
                'email' => 'a@b.com',
            ]],
        );
    }

    private function policy(): ReportingAccessPolicy
    {
        return new ReportingAccessPolicy($this->access);
    }

    private function create(): CreateAlertHandler
    {
        return new CreateAlertHandler($this->repo, $this->policy(), $this->clock);
    }

    private function update(): UpdateAlertHandler
    {
        return new UpdateAlertHandler($this->repo, $this->policy(), $this->clock);
    }

    private function list(): ListAlertsHandler
    {
        return new ListAlertsHandler($this->repo, $this->policy());
    }

    private function delete(): DeleteAlertHandler
    {
        return new DeleteAlertHandler($this->repo, $this->policy(), $this->clock);
    }
}
