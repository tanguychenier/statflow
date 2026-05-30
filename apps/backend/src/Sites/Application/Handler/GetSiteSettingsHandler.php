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
use App\Sites\Application\Dto\SiteSettingsView;
use App\Sites\Application\Query\GetSiteSettingsQuery;
use App\Sites\Domain\Exception\SiteNotFoundException;
use App\Sites\Domain\Port\SiteRepository;
use App\Sites\Domain\Service\SiteAccessPolicy;

/**
 * Returns a site's full settings to any member of its team.
 */
final readonly class GetSiteSettingsHandler
{
    public function __construct(
        private SiteRepository $sites,
        private SiteAccessPolicy $accessPolicy,
    ) {
    }

    public function __invoke(GetSiteSettingsQuery $query): SiteSettingsView
    {
        $userId = Uuid::fromString($query->actingUserId);
        $siteId = Uuid::fromString($query->siteId);

        $site = $this->sites->findById($siteId);
        if ($site === null) {
            throw SiteNotFoundException::withId($siteId);
        }

        $this->accessPolicy->assertCanView($userId, $site);

        return SiteSettingsView::fromSite($site);
    }
}
