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

use App\Reporting\Domain\Model\Alert;
use App\Reporting\Domain\Port\AlertRepository;
use App\Reporting\Domain\Port\AnalyticsQueryGateway;
use App\Reporting\Domain\Port\Clock;
use App\Reporting\Domain\Port\ReportMailer;
use App\Reporting\Domain\Service\AlertEvaluation;
use App\Reporting\Domain\ValueObject\NotificationChannel;
use App\Shared\Domain\ValueObject\Uuid;
use Throwable;

/**
 * Evaluates every enabled alert for a site, reading the current (and, for
 * percentage conditions, baseline) metric from Analytics and firing the alert's
 * notification channels when the rule is breached.
 *
 * A breach records the trigger time so the cadence can throttle; reading or
 * notification failures for one alert never abort the others. Email channels are
 * only sent when an SMTP transport is configured; webhook/slack delivery is left
 * to the channel sender adapter.
 */
final readonly class EvaluateAlertsHandler
{
    public function __construct(
        private AlertRepository $alerts,
        private AnalyticsQueryGateway $analytics,
        private AlertEvaluation $evaluation,
        private ReportMailer $mailer,
        private Clock $clock,
    ) {
    }

    public function evaluateSite(Uuid $siteId): void
    {
        foreach ($this->alerts->findEnabledForSite($siteId) as $alert) {
            $this->evaluateOne($alert);
        }
    }

    private function evaluateOne(Alert $alert): void
    {
        try {
            $reading = $this->analytics->evaluateMetric(
                $alert->siteId(),
                $alert->metric()->value,
                [
                    'filters' => $alert->filters(),
                ],
                $alert->comparisonPeriod()?->value,
            );
        } catch (Throwable) {
            return;
        }

        if (!$this->evaluation->isBreached($alert, $reading['current'], $reading['baseline'])) {
            return;
        }

        $this->fire($alert);

        $alert->markTriggered($this->clock->now());
        $this->alerts->save($alert);
    }

    private function fire(Alert $alert): void
    {
        if (!$this->mailer->isConfigured()) {
            return;
        }

        $subject = sprintf('Alert triggered: %s', $alert->name()->value());
        $body = sprintf(
            'Alert "%s" on metric %s breached its threshold of %s.',
            $alert->name()->value(),
            $alert->metric()->value,
            (string) $alert->thresholdValue(),
        );

        foreach ($alert->notificationChannels()->all() as $channel) {
            if ($channel->type() === NotificationChannel::TYPE_EMAIL && $channel->email() !== null) {
                $this->sendEmail($channel, $subject, $body);
            }
        }
    }

    private function sendEmail(NotificationChannel $channel, string $subject, string $body): void
    {
        $email = $channel->email();
        if ($email === null) {
            return;
        }

        try {
            $this->mailer->send($email, $subject, $body, $body);
        } catch (Throwable) {
            // A delivery failure must not prevent the trigger from being recorded.
        }
    }
}
