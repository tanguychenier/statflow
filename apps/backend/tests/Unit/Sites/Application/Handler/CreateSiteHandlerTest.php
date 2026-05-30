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

namespace App\Tests\Unit\Sites\Application\Handler;

use App\Sites\Application\Command\CreateSiteCommand;
use App\Sites\Application\Handler\CreateSiteHandler;
use App\Sites\Domain\Exception\DuplicateSiteDomainException;
use App\Sites\Domain\Exception\InvalidSiteDomainException;
use App\Sites\Domain\Exception\PermissionDeniedException;
use App\Sites\Domain\Model\TeamRole;
use App\Sites\Domain\Service\SiteAccessPolicy;
use App\Sites\Domain\ValueObject\TrackerKey;
use App\Tests\Unit\Sites\Fake\FrozenClock;
use App\Tests\Unit\Sites\Fake\InMemorySiteRepository;
use App\Tests\Unit\Sites\Fake\InMemoryTeamMembershipProvider;
use App\Tests\Unit\Sites\Fake\SequenceTrackerKeyGenerator;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreateSiteHandler::class)]
final class CreateSiteHandlerTest extends TestCase
{
    private const USER = '11111111-1111-4111-8111-111111111111';

    private const TEAM = '22222222-2222-4222-8222-222222222222';

    private InMemorySiteRepository $sites;

    private InMemoryTeamMembershipProvider $memberships;

    private SequenceTrackerKeyGenerator $keys;

    protected function setUp(): void
    {
        $this->sites = new InMemorySiteRepository();
        $this->memberships = new InMemoryTeamMembershipProvider();
        $this->keys = new SequenceTrackerKeyGenerator(str_repeat('a', 32), str_repeat('b', 32));
    }

    #[Test]
    public function itCreatesASiteForAnEditor(): void
    {
        $this->memberships->grant(self::USER, self::TEAM, TeamRole::Editor);

        $view = ($this->handler())(new CreateSiteCommand(
            actingUserId: self::USER,
            teamId: self::TEAM,
            name: 'My Site',
            domain: 'example.com',
            timezone: 'Europe/Paris',
        ));

        self::assertSame('My Site', $view->name);
        self::assertSame('example.com', $view->domain);
        self::assertSame(self::TEAM, $view->teamId);
        self::assertStringStartsWith('stk_', $view->trackerKey);
        self::assertNotNull($this->sites->findByTrackerKey(TrackerKey::fromString($view->trackerKey)));
    }

    #[Test]
    public function itRetriesKeyGenerationOnCollision(): void
    {
        $this->memberships->grant(self::USER, self::TEAM, TeamRole::Owner);
        $this->sites->reserveKey('stk_' . str_repeat('a', 32));

        $view = ($this->handler())(new CreateSiteCommand(self::USER, self::TEAM, 'Site', 'example.com'));

        self::assertSame('stk_' . str_repeat('b', 32), $view->trackerKey);
        self::assertSame(2, $this->keys->callCount());
    }

    #[Test]
    public function itRejectsNonMembers(): void
    {
        $this->expectException(PermissionDeniedException::class);

        ($this->handler())(new CreateSiteCommand(self::USER, self::TEAM, 'Site', 'example.com'));
    }

    #[Test]
    public function itRejectsDuplicateDomainInSameTeam(): void
    {
        $this->memberships->grant(self::USER, self::TEAM, TeamRole::Editor);
        ($this->handler())(new CreateSiteCommand(self::USER, self::TEAM, 'First', 'example.com'));

        $this->expectException(DuplicateSiteDomainException::class);

        ($this->handler())(new CreateSiteCommand(self::USER, self::TEAM, 'Second', 'example.com'));
    }

    #[Test]
    public function itRejectsInvalidDomain(): void
    {
        $this->memberships->grant(self::USER, self::TEAM, TeamRole::Editor);

        $this->expectException(InvalidSiteDomainException::class);

        ($this->handler())(new CreateSiteCommand(self::USER, self::TEAM, 'Site', 'https://example.com'));
    }

    private function handler(): CreateSiteHandler
    {
        return new CreateSiteHandler(
            $this->sites,
            $this->keys,
            new SiteAccessPolicy($this->memberships),
            new FrozenClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00')),
        );
    }
}
