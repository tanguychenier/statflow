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
use App\Sites\Application\Command\DeleteSiteCommand;
use App\Sites\Domain\Exception\SiteNotFoundException;
use App\Sites\Domain\Port\Clock;
use App\Sites\Domain\Port\SiteDataDeletionScheduler;
use App\Sites\Domain\Port\SiteRepository;
use App\Sites\Domain\Service\SiteAccessPolicy;

/**
 * Soft-deletes a site (Owner only) and hands its id to the data-deletion
 * scheduler so the analytical store is purged asynchronously. Idempotent: a
 * second delete on an already-deleted site is a no-op that still 204s.
 */
final readonly class DeleteSiteHandler
{
    public function __construct(
        private SiteRepository $sites,
        private SiteAccessPolicy $accessPolicy,
        private SiteDataDeletionScheduler $deletionScheduler,
        private Clock $clock,
    ) {
    }

    public function __invoke(DeleteSiteCommand $command): void
    {
        $userId = Uuid::fromString($command->actingUserId);
        $siteId = Uuid::fromString($command->siteId);

        $site = $this->sites->findById($siteId);
        if ($site === null) {
            throw SiteNotFoundException::withId($siteId);
        }

        $this->accessPolicy->assertCanDelete($userId, $site);

        $site->softDelete($this->clock->now());

        $this->sites->save($site);

        $this->deletionScheduler->scheduleDeletion($site->id());
    }
}
