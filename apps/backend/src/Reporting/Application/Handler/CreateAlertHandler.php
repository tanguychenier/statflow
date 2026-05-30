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

use App\Reporting\Application\Command\CreateAlertCommand;
use App\Reporting\Application\Dto\AlertView;
use App\Reporting\Domain\Model\Alert;
use App\Reporting\Domain\Model\AlertCondition;
use App\Reporting\Domain\Model\AlertMetric;
use App\Reporting\Domain\Model\ComparisonPeriod;
use App\Reporting\Domain\Port\AlertRepository;
use App\Reporting\Domain\Port\Clock;
use App\Reporting\Domain\Service\ReportingAccessPolicy;
use App\Reporting\Domain\ValueObject\NotificationChannelList;
use App\Reporting\Domain\ValueObject\ReportName;
use App\Reporting\Domain\ValueObject\Threshold;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Persists a new metric alert (editor and above). The aggregate enforces the
 * comparison-period invariant; filters are stored opaque for the evaluator.
 */
final readonly class CreateAlertHandler
{
    public function __construct(
        private AlertRepository $alerts,
        private ReportingAccessPolicy $accessPolicy,
        private Clock $clock,
    ) {
    }

    public function __invoke(CreateAlertCommand $command): AlertView
    {
        $userId = Uuid::fromString($command->actingUserId);
        $siteId = Uuid::fromString($command->siteId);

        $this->accessPolicy->assertCanManage($userId, $siteId);

        $comparisonPeriod = $command->comparisonPeriod !== null
            ? ComparisonPeriod::fromString($command->comparisonPeriod)
            : null;

        $alert = Alert::create(
            id: Uuid::generate(),
            siteId: $siteId,
            name: ReportName::fromString($command->name),
            metric: AlertMetric::fromString($command->metric),
            condition: AlertCondition::fromString($command->condition),
            threshold: Threshold::fromFloat($command->threshold),
            comparisonPeriod: $comparisonPeriod,
            filters: array_values($command->filters),
            channels: NotificationChannelList::fromArrayList($command->notificationChannels),
            createdBy: $userId,
            now: $this->clock->now(),
        );

        $this->alerts->save($alert);

        return AlertView::fromAlert($alert);
    }
}
