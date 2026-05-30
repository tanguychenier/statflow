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

namespace App\Tests\Unit\Reporting\Application\Handler;

use App\Reporting\Application\Handler\EvaluateAlertsHandler;
use App\Reporting\Domain\Model\Alert;
use App\Reporting\Domain\Model\AlertCondition;
use App\Reporting\Domain\Model\AlertMetric;
use App\Reporting\Domain\Service\AlertEvaluation;
use App\Reporting\Domain\ValueObject\NotificationChannelList;
use App\Reporting\Domain\ValueObject\ReportName;
use App\Reporting\Domain\ValueObject\Threshold;
use App\Shared\Domain\ValueObject\Uuid;
use App\Tests\Unit\Reporting\Fake\FakeAnalyticsQueryGateway;
use App\Tests\Unit\Reporting\Fake\FrozenClock;
use App\Tests\Unit\Reporting\Fake\InMemoryAlertRepository;
use App\Tests\Unit\Reporting\Fake\RecordingReportMailer;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EvaluateAlertsHandler::class)]
final class EvaluateAlertsHandlerTest extends TestCase
{
    private const SITE = '22222222-2222-4222-8222-222222222222';

    private InMemoryAlertRepository $repo;

    private FakeAnalyticsQueryGateway $analytics;

    private RecordingReportMailer $mailer;

    private FrozenClock $clock;

    protected function setUp(): void
    {
        $this->repo = new InMemoryAlertRepository();
        $this->analytics = new FakeAnalyticsQueryGateway();
        $this->mailer = new RecordingReportMailer();
        $this->clock = new FrozenClock(new DateTimeImmutable('2026-05-29T09:00:00', new DateTimeZone('UTC')));
    }

    #[Test]
    public function itFiresAndRecordsTriggerWhenBreached(): void
    {
        $alert = $this->persist(120.0);
        $this->analytics->withReading(200.0);

        ($this->handler())->evaluateSite(Uuid::fromString(self::SITE));

        self::assertCount(1, $this->mailer->sent);
        self::assertNotNull($this->repo->findById(Uuid::fromString(self::SITE), $alert->id())?->lastTriggeredAt());
    }

    #[Test]
    public function itDoesNotFireWhenWithinThreshold(): void
    {
        $alert = $this->persist(500.0);
        $this->analytics->withReading(200.0);

        ($this->handler())->evaluateSite(Uuid::fromString(self::SITE));

        self::assertEmpty($this->mailer->sent);
        self::assertNull($this->repo->findById(Uuid::fromString(self::SITE), $alert->id())?->lastTriggeredAt());
    }

    #[Test]
    public function itSkipsAlertWhenAnalyticsFails(): void
    {
        $this->persist(100.0);
        $this->analytics->failOnEvaluate();

        ($this->handler())->evaluateSite(Uuid::fromString(self::SITE));

        self::assertEmpty($this->mailer->sent);
    }

    #[Test]
    public function itRecordsTriggerEvenWhenMailerUnconfigured(): void
    {
        $this->mailer->configured(false);
        $alert = $this->persist(100.0);
        $this->analytics->withReading(200.0);

        ($this->handler())->evaluateSite(Uuid::fromString(self::SITE));

        self::assertNotNull($this->repo->findById(Uuid::fromString(self::SITE), $alert->id())?->lastTriggeredAt());
    }

    private function persist(float $threshold): Alert
    {
        $alert = Alert::create(
            Uuid::generate(),
            Uuid::fromString(self::SITE),
            ReportName::fromString('Spike'),
            AlertMetric::Pageviews,
            AlertCondition::Above,
            Threshold::fromFloat($threshold),
            null,
            [],
            NotificationChannelList::fromArrayList([[
                'type' => 'email',
                'email' => 'a@b.com',
            ]]),
            null,
            new DateTimeImmutable('2026-05-29T08:00:00', new DateTimeZone('UTC')),
        );
        $this->repo->save($alert);

        return $alert;
    }

    private function handler(): EvaluateAlertsHandler
    {
        return new EvaluateAlertsHandler(
            $this->repo,
            $this->analytics,
            new AlertEvaluation(),
            $this->mailer,
            $this->clock,
        );
    }
}
