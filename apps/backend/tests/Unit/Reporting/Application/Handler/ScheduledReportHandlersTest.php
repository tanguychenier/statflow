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

use App\Reporting\Application\Command\CreateScheduledReportCommand;
use App\Reporting\Application\Command\DeleteScheduledReportCommand;
use App\Reporting\Application\Command\UpdateScheduledReportCommand;
use App\Reporting\Application\Handler\CreateScheduledReportHandler;
use App\Reporting\Application\Handler\DeleteScheduledReportHandler;
use App\Reporting\Application\Handler\ListScheduledReportsHandler;
use App\Reporting\Application\Handler\UpdateScheduledReportHandler;
use App\Reporting\Application\Query\ListScheduledReportsQuery;
use App\Reporting\Domain\Exception\InvalidScheduleException;
use App\Reporting\Domain\Exception\PermissionDeniedException;
use App\Reporting\Domain\Exception\ReportNotFoundException;
use App\Reporting\Domain\Model\ReportType;
use App\Reporting\Domain\Model\SavedReport;
use App\Reporting\Domain\Model\TeamRole;
use App\Reporting\Domain\Service\ReportingAccessPolicy;
use App\Reporting\Domain\ValueObject\QueryDefinition;
use App\Reporting\Domain\ValueObject\ReportName;
use App\Shared\Domain\ValueObject\Uuid;
use App\Tests\Unit\Reporting\Fake\FrozenClock;
use App\Tests\Unit\Reporting\Fake\InMemorySavedReportRepository;
use App\Tests\Unit\Reporting\Fake\InMemoryScheduledReportRepository;
use App\Tests\Unit\Reporting\Fake\InMemorySiteAccessProvider;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreateScheduledReportHandler::class)]
#[CoversClass(UpdateScheduledReportHandler::class)]
#[CoversClass(ListScheduledReportsHandler::class)]
#[CoversClass(DeleteScheduledReportHandler::class)]
final class ScheduledReportHandlersTest extends TestCase
{
    private const USER = '11111111-1111-4111-8111-111111111111';

    private const SITE = '22222222-2222-4222-8222-222222222222';

    private InMemoryScheduledReportRepository $repo;

    private InMemorySavedReportRepository $savedRepo;

    private InMemorySiteAccessProvider $access;

    private FrozenClock $clock;

    protected function setUp(): void
    {
        $this->repo = new InMemoryScheduledReportRepository();
        $this->savedRepo = new InMemorySavedReportRepository();
        $this->access = new InMemorySiteAccessProvider();
        $this->clock = new FrozenClock(new DateTimeImmutable('2026-05-29T08:00:00', new DateTimeZone('UTC')));
    }

    #[Test]
    public function editorCreatesSchedule(): void
    {
        $this->access->grant(self::USER, self::SITE, TeamRole::Editor);

        $created = ($this->create())($this->createCommand());

        self::assertSame('Weekly', $created->name);
        self::assertSame(['a@b.com'], $created->recipients);
        self::assertSame('0 9 * * *', $created->scheduleCron);
        self::assertNotNull($created->nextSendAt);
    }

    #[Test]
    public function viewerCannotCreate(): void
    {
        $this->access->grant(self::USER, self::SITE, TeamRole::Viewer);

        $this->expectException(PermissionDeniedException::class);
        ($this->create())($this->createCommand());
    }

    #[Test]
    public function createValidatesSavedReportReference(): void
    {
        $this->access->grant(self::USER, self::SITE, TeamRole::Editor);

        $this->expectException(ReportNotFoundException::class);
        ($this->create())($this->createCommand(savedReportId: Uuid::generate()->getValue()));
    }

    #[Test]
    public function createAcceptsValidSavedReportReference(): void
    {
        $this->access->grant(self::USER, self::SITE, TeamRole::Editor);
        $saved = $this->persistSavedReport();

        $created = ($this->create())($this->createCommand(savedReportId: $saved));

        self::assertSame($saved, $created->savedReportId);
    }

    #[Test]
    public function updateAppliesPartialChanges(): void
    {
        $this->access->grant(self::USER, self::SITE, TeamRole::Editor);
        $created = ($this->create())($this->createCommand());

        $updated = ($this->update())(new UpdateScheduledReportCommand(
            actingUserId: self::USER,
            siteId: self::SITE,
            scheduledReportId: $created->id,
            name: 'Renamed',
            isActive: false,
        ));

        self::assertSame('Renamed', $updated->name);
        self::assertFalse($updated->isActive);
        self::assertNull($updated->nextSendAt);
    }

    #[Test]
    public function updateRequiresCronAndTimezoneTogether(): void
    {
        $this->access->grant(self::USER, self::SITE, TeamRole::Editor);
        $created = ($this->create())($this->createCommand());

        $this->expectException(InvalidScheduleException::class);
        ($this->update())(new UpdateScheduledReportCommand(
            actingUserId: self::USER,
            siteId: self::SITE,
            scheduledReportId: $created->id,
            scheduleCron: '0 18 * * *',
        ));
    }

    #[Test]
    public function updateReschedulesWhenBothProvided(): void
    {
        $this->access->grant(self::USER, self::SITE, TeamRole::Editor);
        $created = ($this->create())($this->createCommand());

        $updated = ($this->update())(new UpdateScheduledReportCommand(
            actingUserId: self::USER,
            siteId: self::SITE,
            scheduledReportId: $created->id,
            scheduleCron: '0 18 * * *',
            timezone: 'UTC',
        ));

        self::assertSame('2026-05-29T18:00:00+00:00', $updated->nextSendAt);
    }

    #[Test]
    public function listReturnsSchedules(): void
    {
        $this->access->grant(self::USER, self::SITE, TeamRole::Editor);
        ($this->create())($this->createCommand());

        $page = ($this->list())(new ListScheduledReportsQuery(self::USER, self::SITE));

        self::assertCount(1, $page->data);
    }

    #[Test]
    public function deleteRemovesSchedule(): void
    {
        $this->access->grant(self::USER, self::SITE, TeamRole::Editor);
        $created = ($this->create())($this->createCommand());

        ($this->delete())(new DeleteScheduledReportCommand(self::USER, self::SITE, $created->id));

        self::assertNull($this->repo->findById(Uuid::fromString(self::SITE), Uuid::fromString($created->id)));
    }

    private function createCommand(?string $savedReportId = null): CreateScheduledReportCommand
    {
        return new CreateScheduledReportCommand(
            actingUserId: self::USER,
            siteId: self::SITE,
            name: 'Weekly',
            savedReportId: $savedReportId,
            recipients: ['a@b.com'],
            scheduleCron: '0 9 * * *',
            timezone: 'UTC',
        );
    }

    private function persistSavedReport(): string
    {
        $report = SavedReport::create(
            Uuid::generate(),
            Uuid::fromString(self::SITE),
            ReportName::fromString('Saved'),
            null,
            ReportType::Breakdown,
            QueryDefinition::fromArray([]),
            null,
            $this->clock->now(),
        );
        $this->savedRepo->save($report);

        return $report->id()->getValue();
    }

    private function policy(): ReportingAccessPolicy
    {
        return new ReportingAccessPolicy($this->access);
    }

    private function create(): CreateScheduledReportHandler
    {
        return new CreateScheduledReportHandler($this->repo, $this->savedRepo, $this->policy(), $this->clock);
    }

    private function update(): UpdateScheduledReportHandler
    {
        return new UpdateScheduledReportHandler($this->repo, $this->policy(), $this->clock);
    }

    private function list(): ListScheduledReportsHandler
    {
        return new ListScheduledReportsHandler($this->repo, $this->policy());
    }

    private function delete(): DeleteScheduledReportHandler
    {
        return new DeleteScheduledReportHandler($this->repo, $this->policy(), $this->clock);
    }
}
