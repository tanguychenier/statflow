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

use App\Reporting\Application\Command\UpdateAlertCommand;
use App\Reporting\Application\Dto\AlertView;
use App\Reporting\Domain\Exception\InvalidAlertException;
use App\Reporting\Domain\Exception\ReportNotFoundException;
use App\Reporting\Domain\Model\Alert;
use App\Reporting\Domain\Model\AlertCondition;
use App\Reporting\Domain\Port\AlertRepository;
use App\Reporting\Domain\Port\Clock;
use App\Reporting\Domain\Service\ReportingAccessPolicy;
use App\Reporting\Domain\ValueObject\NotificationChannelList;
use App\Reporting\Domain\ValueObject\ReportName;
use App\Reporting\Domain\ValueObject\Threshold;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

/**
 * Applies a partial update to an alert (editor and above). Condition and
 * threshold change together so the aggregate's comparison-period invariant is
 * re-validated atomically.
 */
final readonly class UpdateAlertHandler
{
    public function __construct(
        private AlertRepository $alerts,
        private ReportingAccessPolicy $accessPolicy,
        private Clock $clock,
    ) {
    }

    public function __invoke(UpdateAlertCommand $command): AlertView
    {
        $userId = Uuid::fromString($command->actingUserId);
        $siteId = Uuid::fromString($command->siteId);
        $alertId = Uuid::fromString($command->alertId);

        $this->accessPolicy->assertCanManage($userId, $siteId);

        $alert = $this->alerts->findById($siteId, $alertId);
        if ($alert === null) {
            throw ReportNotFoundException::alert($alertId);
        }

        $now = $this->clock->now();

        $this->applyName($alert, $command, $now);
        $this->applyCondition($alert, $command, $now);
        $this->applyChannels($alert, $command, $now);
        $this->applyActivation($alert, $command, $now);

        $this->alerts->save($alert);

        return AlertView::fromAlert($alert);
    }

    private function applyName(Alert $alert, UpdateAlertCommand $command, DateTimeImmutable $now): void
    {
        if ($command->name !== null) {
            $alert->rename(ReportName::fromString($command->name), $now);
        }
    }

    private function applyCondition(Alert $alert, UpdateAlertCommand $command, DateTimeImmutable $now): void
    {
        if ($command->condition === null && $command->threshold === null) {
            return;
        }

        $condition = $command->condition !== null
            ? AlertCondition::fromString($command->condition)
            : $alert->alertCondition();

        $threshold = $command->threshold !== null
            ? Threshold::fromFloat($command->threshold)
            : Threshold::fromFloat($alert->thresholdValue());

        $alert->changeCondition($condition, $threshold, $now);
    }

    private function applyChannels(Alert $alert, UpdateAlertCommand $command, DateTimeImmutable $now): void
    {
        if ($command->notificationChannels === null) {
            return;
        }

        if ($command->notificationChannels === []) {
            throw InvalidAlertException::channelsRequired();
        }

        $alert->changeNotificationChannels(
            NotificationChannelList::fromArrayList($command->notificationChannels),
            $now,
        );
    }

    private function applyActivation(Alert $alert, UpdateAlertCommand $command, DateTimeImmutable $now): void
    {
        if ($command->isActive === null) {
            return;
        }

        if ($command->isActive) {
            $alert->activate($now);
        } else {
            $alert->deactivate($now);
        }
    }
}
