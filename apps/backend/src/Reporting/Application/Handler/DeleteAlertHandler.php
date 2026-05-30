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

namespace App\Reporting\Application\Handler;

use App\Reporting\Application\Command\DeleteAlertCommand;
use App\Reporting\Domain\Exception\ReportNotFoundException;
use App\Reporting\Domain\Port\AlertRepository;
use App\Reporting\Domain\Port\Clock;
use App\Reporting\Domain\Service\ReportingAccessPolicy;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Soft-deletes an alert (editor and above), which also disables it so it is
 * never evaluated again.
 */
final readonly class DeleteAlertHandler
{
    public function __construct(
        private AlertRepository $alerts,
        private ReportingAccessPolicy $accessPolicy,
        private Clock $clock,
    ) {
    }

    public function __invoke(DeleteAlertCommand $command): void
    {
        $userId = Uuid::fromString($command->actingUserId);
        $siteId = Uuid::fromString($command->siteId);
        $alertId = Uuid::fromString($command->alertId);

        $this->accessPolicy->assertCanManage($userId, $siteId);

        $alert = $this->alerts->findById($siteId, $alertId);
        if ($alert === null) {
            throw ReportNotFoundException::alert($alertId);
        }

        $alert->softDelete($this->clock->now());

        $this->alerts->save($alert);
    }
}
