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

use App\Reporting\Application\Command\CreateSavedReportCommand;
use App\Reporting\Application\Command\DeleteSavedReportCommand;
use App\Reporting\Application\Handler\CreateSavedReportHandler;
use App\Reporting\Application\Handler\DeleteSavedReportHandler;
use App\Reporting\Application\Handler\GetSavedReportHandler;
use App\Reporting\Application\Handler\ListSavedReportsHandler;
use App\Reporting\Application\Query\GetSavedReportQuery;
use App\Reporting\Application\Query\ListSavedReportsQuery;
use App\Reporting\Domain\Exception\InvalidReportTypeException;
use App\Reporting\Domain\Exception\PermissionDeniedException;
use App\Reporting\Domain\Exception\ReportNotFoundException;
use App\Reporting\Domain\Exception\SiteNotFoundException;
use App\Reporting\Domain\Model\TeamRole;
use App\Reporting\Domain\Service\ReportingAccessPolicy;
use App\Shared\Domain\ValueObject\Uuid;
use App\Tests\Unit\Reporting\Fake\FrozenClock;
use App\Tests\Unit\Reporting\Fake\InMemorySavedReportRepository;
use App\Tests\Unit\Reporting\Fake\InMemorySiteAccessProvider;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreateSavedReportHandler::class)]
#[CoversClass(GetSavedReportHandler::class)]
#[CoversClass(ListSavedReportsHandler::class)]
#[CoversClass(DeleteSavedReportHandler::class)]
final class SavedReportHandlersTest extends TestCase
{
    private const USER = '11111111-1111-4111-8111-111111111111';

    private const SITE = '22222222-2222-4222-8222-222222222222';

    private InMemorySavedReportRepository $repo;

    private InMemorySiteAccessProvider $access;

    private FrozenClock $clock;

    protected function setUp(): void
    {
        $this->repo = new InMemorySavedReportRepository();
        $this->access = new InMemorySiteAccessProvider();
        $this->clock = new FrozenClock(new DateTimeImmutable('2026-05-29T08:00:00', new DateTimeZone('UTC')));
    }

    #[Test]
    public function editorCreatesAndReadsReport(): void
    {
        $this->access->grant(self::USER, self::SITE, TeamRole::Editor);

        $created = ($this->create())(new CreateSavedReportCommand(
            self::USER,
            self::SITE,
            'Top pages',
            'desc',
            'breakdown',
            [
                'property' => 'page',
            ],
        ));

        self::assertSame('Top pages', $created->name);
        self::assertSame('breakdown', $created->reportType);

        $fetched = ($this->get())(new GetSavedReportQuery(self::USER, self::SITE, $created->id));
        self::assertSame($created->id, $fetched->id);
    }

    #[Test]
    public function viewerCannotCreate(): void
    {
        $this->access->grant(self::USER, self::SITE, TeamRole::Viewer);

        $this->expectException(PermissionDeniedException::class);
        ($this->create())(new CreateSavedReportCommand(self::USER, self::SITE, 'n', null, 'breakdown', []));
    }

    #[Test]
    public function nonMemberCannotCreate(): void
    {
        $this->expectException(SiteNotFoundException::class);
        ($this->create())(new CreateSavedReportCommand(self::USER, self::SITE, 'n', null, 'breakdown', []));
    }

    #[Test]
    public function invalidReportTypeIsRejected(): void
    {
        $this->access->grant(self::USER, self::SITE, TeamRole::Editor);

        $this->expectException(InvalidReportTypeException::class);
        ($this->create())(new CreateSavedReportCommand(self::USER, self::SITE, 'n', null, 'nope', []));
    }

    #[Test]
    public function getRejectsUnknownReport(): void
    {
        $this->access->grant(self::USER, self::SITE, TeamRole::Viewer);

        $this->expectException(ReportNotFoundException::class);
        ($this->get())(new GetSavedReportQuery(self::USER, self::SITE, Uuid::generate()->getValue()));
    }

    #[Test]
    public function listIsScopedAndPaginated(): void
    {
        $this->access->grant(self::USER, self::SITE, TeamRole::Editor);

        for ($i = 0; $i < 3; ++$i) {
            ($this->create())(new CreateSavedReportCommand(self::USER, self::SITE, "r{$i}", null, 'breakdown', []));
            $this->clock->set($this->clock->now()->modify('+1 minute'));
        }

        $page = ($this->list())(new ListSavedReportsQuery(self::USER, self::SITE, null, 2));

        self::assertCount(2, $page->data);
        self::assertNotNull($page->nextCursor);
        /** @var array{has_next: bool} $pagination */
        $pagination = $page->toArray()['pagination'];
        self::assertTrue($pagination['has_next']);
    }

    #[Test]
    public function deleteRemovesReport(): void
    {
        $this->access->grant(self::USER, self::SITE, TeamRole::Editor);
        $created = ($this->create())(new CreateSavedReportCommand(self::USER, self::SITE, 'n', null, 'breakdown', []));

        ($this->delete())(new DeleteSavedReportCommand(self::USER, self::SITE, $created->id));

        self::assertNull($this->repo->findById(Uuid::fromString(self::SITE), Uuid::fromString($created->id)));
    }

    #[Test]
    public function deleteRejectsUnknownReport(): void
    {
        $this->access->grant(self::USER, self::SITE, TeamRole::Editor);

        $this->expectException(ReportNotFoundException::class);
        ($this->delete())(new DeleteSavedReportCommand(self::USER, self::SITE, Uuid::generate()->getValue()));
    }

    private function policy(): ReportingAccessPolicy
    {
        return new ReportingAccessPolicy($this->access);
    }

    private function create(): CreateSavedReportHandler
    {
        return new CreateSavedReportHandler($this->repo, $this->policy(), $this->clock);
    }

    private function get(): GetSavedReportHandler
    {
        return new GetSavedReportHandler($this->repo, $this->policy());
    }

    private function list(): ListSavedReportsHandler
    {
        return new ListSavedReportsHandler($this->repo, $this->policy());
    }

    private function delete(): DeleteSavedReportHandler
    {
        return new DeleteSavedReportHandler($this->repo, $this->policy(), $this->clock);
    }
}
