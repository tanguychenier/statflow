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
use App\Sites\Application\Command\UpdateSiteCommand;
use App\Sites\Application\Dto\SiteView;
use App\Sites\Domain\Exception\DuplicateSiteDomainException;
use App\Sites\Domain\Exception\SiteNotFoundException;
use App\Sites\Domain\Port\Clock;
use App\Sites\Domain\Port\SiteRepository;
use App\Sites\Domain\Service\SiteAccessPolicy;
use App\Sites\Domain\ValueObject\Hostname;
use App\Sites\Domain\ValueObject\SiteName;
use App\Sites\Domain\ValueObject\Timezone;

/**
 * Applies a partial update to a site's metadata. Admin/Owner only. Only the
 * supplied fields change; a domain change re-checks per-team uniqueness.
 */
final readonly class UpdateSiteHandler
{
    public function __construct(
        private SiteRepository $sites,
        private SiteAccessPolicy $accessPolicy,
        private Clock $clock,
    ) {
    }

    public function __invoke(UpdateSiteCommand $command): SiteView
    {
        $userId = Uuid::fromString($command->actingUserId);
        $siteId = Uuid::fromString($command->siteId);

        $site = $this->sites->findById($siteId);
        if ($site === null) {
            throw SiteNotFoundException::withId($siteId);
        }

        $this->accessPolicy->assertCanAdminister($userId, $site);

        $now = $this->clock->now();

        if ($command->name !== null) {
            $site->rename(SiteName::fromString($command->name), $now);
        }

        if ($command->domain !== null) {
            $domain = Hostname::fromString($command->domain);

            if ($this->sites->domainExistsInTeam($site->teamId(), $domain, $site->id())) {
                throw DuplicateSiteDomainException::inTeam($domain);
            }

            $site->changeDomain($domain, $now);
        }

        if ($command->timezone !== null) {
            $site->changeTimezone(Timezone::fromString($command->timezone), $now);
        }

        $this->sites->save($site);

        return SiteView::fromSite($site);
    }
}
