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
use App\Sites\Application\Command\ReplaceSiteSettingsCommand;
use App\Sites\Application\Command\SiteSettingsInput;
use App\Sites\Application\Dto\SiteSettingsView;
use App\Sites\Domain\Exception\SiteNotFoundException;
use App\Sites\Domain\Port\Clock;
use App\Sites\Domain\Port\SiteRepository;
use App\Sites\Domain\Service\SiteAccessPolicy;

/**
 * Replaces a site's full settings (PUT semantics). Admin/Owner only. The raw
 * body is parsed into validated value objects, then applied atomically to the
 * aggregate so retention (on the site row) and behaviour (on site_settings)
 * stay consistent.
 */
final readonly class ReplaceSiteSettingsHandler
{
    public function __construct(
        private SiteRepository $sites,
        private SiteAccessPolicy $accessPolicy,
        private Clock $clock,
    ) {
    }

    public function __invoke(ReplaceSiteSettingsCommand $command): SiteSettingsView
    {
        $userId = Uuid::fromString($command->actingUserId);
        $siteId = Uuid::fromString($command->siteId);

        $site = $this->sites->findById($siteId);
        if ($site === null) {
            throw SiteNotFoundException::withId($siteId);
        }

        $this->accessPolicy->assertCanAdminister($userId, $site);

        $input = SiteSettingsInput::fromArray($command->settings);

        $site->replaceSettings(
            allowedDomains: $input->allowedDomains,
            excludedIps: $input->excludedIps,
            stripQueryParams: $input->stripQueryParams,
            customDomainEnabled: $input->customDomainEnabled,
            retentionDays: $input->retentionDays,
            trackerConfig: $input->trackerConfig,
            now: $this->clock->now(),
        );

        $this->sites->save($site);

        return SiteSettingsView::fromSite($site);
    }
}
