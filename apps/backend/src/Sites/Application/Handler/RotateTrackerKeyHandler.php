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
use App\Sites\Application\Command\RotateTrackerKeyCommand;
use App\Sites\Application\Dto\TrackerKeyRotationResult;
use App\Sites\Domain\Exception\SiteNotFoundException;
use App\Sites\Domain\Port\Clock;
use App\Sites\Domain\Port\SiteRepository;
use App\Sites\Domain\Port\TrackerKeyGenerator;
use App\Sites\Domain\Service\SiteAccessPolicy;
use App\Sites\Domain\ValueObject\TrackerKey;
use RuntimeException;

/**
 * Rotates a site's tracker key. Admin/Owner only. A site has exactly one key
 * (ADR-0009 §1), so rotation issues a new value and revokes the old one
 * immediately; the response reports that revocation instant.
 */
final readonly class RotateTrackerKeyHandler
{
    private const KEY_GENERATION_ATTEMPTS = 5;

    public function __construct(
        private SiteRepository $sites,
        private TrackerKeyGenerator $keyGenerator,
        private SiteAccessPolicy $accessPolicy,
        private Clock $clock,
    ) {
    }

    public function __invoke(RotateTrackerKeyCommand $command): TrackerKeyRotationResult
    {
        $userId = Uuid::fromString($command->actingUserId);
        $siteId = Uuid::fromString($command->siteId);

        $site = $this->sites->findById($siteId);
        if ($site === null) {
            throw SiteNotFoundException::withId($siteId);
        }

        $this->accessPolicy->assertCanAdminister($userId, $site);

        $now = $this->clock->now();
        $site->rotateTrackerKey($this->uniqueTrackerKey(), $now);

        $this->sites->save($site);

        return new TrackerKeyRotationResult(
            trackerKey: $site->trackerKey()->value(),
            oldKeyValidUntil: $now->format(DATE_ATOM),
        );
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
