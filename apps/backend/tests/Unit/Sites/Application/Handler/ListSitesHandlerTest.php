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

use App\Shared\Domain\ValueObject\Uuid;
use App\Sites\Application\Dto\PaginatedSites;
use App\Sites\Application\Dto\SiteView;
use App\Sites\Application\Handler\ListSitesHandler;
use App\Sites\Application\Query\ListSitesQuery;
use App\Sites\Domain\Model\Site;
use App\Sites\Domain\Model\TeamRole;
use App\Sites\Domain\ValueObject\Hostname;
use App\Sites\Domain\ValueObject\SiteName;
use App\Sites\Domain\ValueObject\Timezone;
use App\Sites\Domain\ValueObject\TrackerKey;
use App\Tests\Unit\Sites\Fake\InMemorySiteRepository;
use App\Tests\Unit\Sites\Fake\InMemoryTeamMembershipProvider;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ListSitesHandler::class)]
#[CoversClass(PaginatedSites::class)]
final class ListSitesHandlerTest extends TestCase
{
    private const USER = '11111111-1111-4111-8111-111111111111';

    private const TEAM_A = '22222222-2222-4222-8222-222222222222';

    private const TEAM_B = '33333333-3333-4333-8333-333333333333';

    private const TEAM_OTHER = '44444444-4444-4444-8444-444444444444';

    private InMemorySiteRepository $sites;

    private InMemoryTeamMembershipProvider $memberships;

    protected function setUp(): void
    {
        $this->sites = new InMemorySiteRepository();
        $this->memberships = new InMemoryTeamMembershipProvider();
        $this->memberships->grant(self::USER, self::TEAM_A, TeamRole::Owner);
        $this->memberships->grant(self::USER, self::TEAM_B, TeamRole::Viewer);

        $this->addSite(self::TEAM_A, 'Alpha', 'alpha.com', '2026-01-01', 'a');
        $this->addSite(self::TEAM_B, 'Beta', 'beta.com', '2026-01-02', 'b');
        $this->addSite(self::TEAM_OTHER, 'Gamma', 'gamma.com', '2026-01-03', 'c');
    }

    #[Test]
    public function itListsOnlyAccessibleTeams(): void
    {
        $result = ($this->handler())(new ListSitesQuery(self::USER));

        $domains = array_map(static fn (SiteView $s): string => $s->domain, $result->sites);
        self::assertContains('alpha.com', $domains);
        self::assertContains('beta.com', $domains);
        self::assertNotContains('gamma.com', $domains, 'sites from teams the user is not in are hidden');
    }

    #[Test]
    public function teamFilterNarrowsResults(): void
    {
        $result = ($this->handler())(new ListSitesQuery(self::USER, teamId: self::TEAM_A));

        self::assertCount(1, $result->sites);
        self::assertSame('alpha.com', $result->sites[0]->domain);
    }

    #[Test]
    public function teamFilterOutsideMembershipReturnsEmptyPage(): void
    {
        $result = ($this->handler())(new ListSitesQuery(self::USER, teamId: self::TEAM_OTHER));

        self::assertSame([], $result->sites);
        self::assertNull($result->nextCursor);
    }

    #[Test]
    public function searchMatchesNameOrDomain(): void
    {
        $result = ($this->handler())(new ListSitesQuery(self::USER, search: 'alpha'));

        self::assertCount(1, $result->sites);
        self::assertSame('alpha.com', $result->sites[0]->domain);
    }

    #[Test]
    public function userWithoutMembershipsGetsEmptyPage(): void
    {
        $result = ($this->handler())(new ListSitesQuery('55555555-5555-4555-8555-555555555555'));

        self::assertSame([], $result->sites);
        self::assertNull($result->nextCursor);
    }

    #[Test]
    public function paginationEmitsACursorWhenMorePagesExist(): void
    {
        $result = ($this->handler())(new ListSitesQuery(self::USER, limit: 1));

        self::assertCount(1, $result->sites);
        self::assertNotNull($result->nextCursor);
        /** @var array{has_next: bool} $pagination */
        $pagination = $result->toArray()['pagination'];
        self::assertTrue($pagination['has_next']);
    }

    private function addSite(string $teamId, string $name, string $domain, string $createdAt, string $keyChar): void
    {
        $this->sites->save(Site::register(
            id: Uuid::generate(),
            teamId: Uuid::fromString($teamId),
            name: SiteName::fromString($name),
            domain: Hostname::fromString($domain),
            timezone: Timezone::default(),
            trackerKey: TrackerKey::fromString('stk_' . str_repeat($keyChar, 32)),
            now: new DateTimeImmutable($createdAt),
        ));
    }

    private function handler(): ListSitesHandler
    {
        return new ListSitesHandler($this->sites, $this->memberships);
    }
}
