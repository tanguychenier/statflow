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
use App\Sites\Application\Command\ReplaceSiteSettingsCommand;
use App\Sites\Application\Command\SiteSettingsInput;
use App\Sites\Application\Handler\ReplaceSiteSettingsHandler;
use App\Sites\Domain\Exception\InvalidRetentionException;
use App\Sites\Domain\Exception\InvalidSiteSettingsException;
use App\Sites\Domain\Exception\PermissionDeniedException;
use App\Sites\Domain\Model\Site;
use App\Sites\Domain\Model\TeamRole;
use App\Sites\Domain\Service\SiteAccessPolicy;
use App\Sites\Domain\ValueObject\Hostname;
use App\Sites\Domain\ValueObject\SiteName;
use App\Sites\Domain\ValueObject\Timezone;
use App\Sites\Domain\ValueObject\TrackerKey;
use App\Tests\Unit\Sites\Fake\FrozenClock;
use App\Tests\Unit\Sites\Fake\InMemorySiteRepository;
use App\Tests\Unit\Sites\Fake\InMemoryTeamMembershipProvider;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReplaceSiteSettingsHandler::class)]
#[CoversClass(SiteSettingsInput::class)]
final class ReplaceSiteSettingsHandlerTest extends TestCase
{
    private const USER = '11111111-1111-4111-8111-111111111111';

    private const TEAM = '22222222-2222-4222-8222-222222222222';

    private InMemorySiteRepository $sites;

    private InMemoryTeamMembershipProvider $memberships;

    private Site $site;

    protected function setUp(): void
    {
        $this->sites = new InMemorySiteRepository();
        $this->memberships = new InMemoryTeamMembershipProvider();
        $this->site = Site::register(
            id: Uuid::generate(),
            teamId: Uuid::fromString(self::TEAM),
            name: SiteName::fromString('Site'),
            domain: Hostname::fromString('example.com'),
            timezone: Timezone::default(),
            trackerKey: TrackerKey::fromString('stk_' . str_repeat('a', 32)),
            now: new DateTimeImmutable('2026-01-01'),
        );
        $this->sites->save($this->site);
    }

    #[Test]
    public function adminReplacesSettingsAndReturnsView(): void
    {
        $this->memberships->grant(self::USER, self::TEAM, TeamRole::Admin);

        $view = ($this->handler())(new ReplaceSiteSettingsCommand(
            actingUserId: self::USER,
            siteId: $this->site->id()->getValue(),
            settings: [
                'allowed_domains' => ['*.example.com'],
                'excluded_ips' => ['10.0.0.1'],
                'data_retention_days' => 90,
                'strip_query_params' => true,
                'custom_domain_enabled' => true,
                'tracker_config' => [
                    'track_clicks' => false,
                    'hash_based_routing' => true,
                    'ignored_selectors' => ['.ad'],
                    'sampling_rate' => 0.5,
                    'script_variant' => 'compat',
                ],
            ],
        ));

        self::assertSame(['*.example.com'], $view->allowedDomains);
        self::assertSame(['10.0.0.1'], $view->excludedIps);
        self::assertSame(90, $view->dataRetentionDays);
        self::assertTrue($view->stripQueryParams);
        self::assertTrue($view->customDomainEnabled);
        self::assertFalse($view->trackClicks);
        self::assertTrue($view->hashBasedRouting);
        self::assertSame(['.ad'], $view->ignoredSelectors);
        self::assertSame(0.5, $view->samplingRate);
    }

    #[Test]
    public function putAppliesDefaultsForOmittedFields(): void
    {
        $this->memberships->grant(self::USER, self::TEAM, TeamRole::Admin);

        $view = ($this->handler())(new ReplaceSiteSettingsCommand(self::USER, $this->site->id()->getValue(), []));

        self::assertSame([], $view->allowedDomains);
        self::assertSame([], $view->excludedIps);
        self::assertSame(365, $view->dataRetentionDays);
        self::assertFalse($view->stripQueryParams);
        self::assertTrue($view->trackClicks);
        self::assertTrue($view->trackScroll);
        self::assertSame(1.0, $view->samplingRate);
    }

    #[Test]
    public function editorCannotReplaceSettings(): void
    {
        $this->memberships->grant(self::USER, self::TEAM, TeamRole::Editor);

        $this->expectException(PermissionDeniedException::class);

        ($this->handler())(new ReplaceSiteSettingsCommand(self::USER, $this->site->id()->getValue(), []));
    }

    #[Test]
    public function invalidRetentionIsRejected(): void
    {
        $this->memberships->grant(self::USER, self::TEAM, TeamRole::Admin);

        $this->expectException(InvalidRetentionException::class);

        ($this->handler())(new ReplaceSiteSettingsCommand(
            self::USER,
            $this->site->id()->getValue(),
            [
                'data_retention_days' => 10,
            ],
        ));
    }

    #[Test]
    public function nonBooleanFlagIsRejected(): void
    {
        $this->memberships->grant(self::USER, self::TEAM, TeamRole::Admin);

        $this->expectException(InvalidSiteSettingsException::class);

        ($this->handler())(new ReplaceSiteSettingsCommand(
            self::USER,
            $this->site->id()->getValue(),
            [
                'strip_query_params' => 'yes',
            ],
        ));
    }

    private function handler(): ReplaceSiteSettingsHandler
    {
        return new ReplaceSiteSettingsHandler(
            $this->sites,
            new SiteAccessPolicy($this->memberships),
            new FrozenClock(new DateTimeImmutable('2026-02-01T00:00:00+00:00')),
        );
    }
}
