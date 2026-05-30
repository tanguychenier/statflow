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

namespace App\Sites\Application\Handler;

use App\Shared\Domain\ValueObject\Uuid;
use App\Sites\Application\Command\CreateSiteCommand;
use App\Sites\Application\Dto\SiteView;
use App\Sites\Domain\Exception\DuplicateSiteDomainException;
use App\Sites\Domain\Model\Site;
use App\Sites\Domain\Port\Clock;
use App\Sites\Domain\Port\SiteRepository;
use App\Sites\Domain\Port\TrackerKeyGenerator;
use App\Sites\Domain\Service\SiteAccessPolicy;
use App\Sites\Domain\ValueObject\Hostname;
use App\Sites\Domain\ValueObject\SiteName;
use App\Sites\Domain\ValueObject\Timezone;
use App\Sites\Domain\ValueObject\TrackerKey;
use RuntimeException;

/**
 * Registers a new site: authorizes the caller, validates input, guarantees a
 * unique tracker key, and persists the aggregate (which seeds default settings).
 */
final readonly class CreateSiteHandler
{
    private const KEY_GENERATION_ATTEMPTS = 5;

    public function __construct(
        private SiteRepository $sites,
        private TrackerKeyGenerator $keyGenerator,
        private SiteAccessPolicy $accessPolicy,
        private Clock $clock,
    ) {
    }

    public function __invoke(CreateSiteCommand $command): SiteView
    {
        $userId = Uuid::fromString($command->actingUserId);
        $teamId = Uuid::fromString($command->teamId);

        $this->accessPolicy->assertCanCreateInTeam($userId, $teamId);

        $name = SiteName::fromString($command->name);
        $domain = Hostname::fromString($command->domain);
        $timezone = Timezone::fromString($command->timezone);

        if ($this->sites->domainExistsInTeam($teamId, $domain)) {
            throw DuplicateSiteDomainException::inTeam($domain);
        }

        $now = $this->clock->now();

        $site = Site::register(
            id: Uuid::generate(),
            teamId: $teamId,
            name: $name,
            domain: $domain,
            timezone: $timezone,
            trackerKey: $this->uniqueTrackerKey(),
            now: $now,
        );

        $this->sites->save($site);

        return SiteView::fromSite($site);
    }

    private function uniqueTrackerKey(): TrackerKey
    {
        for ($attempt = 0; $attempt < self::KEY_GENERATION_ATTEMPTS; ++$attempt) {
            $key = $this->keyGenerator->generate();

            if (!$this->sites->trackerKeyExists($key)) {
                return $key;
            }
        }

        throw new RuntimeException('Unable to generate a unique tracker key after multiple attempts.');
    }
}
