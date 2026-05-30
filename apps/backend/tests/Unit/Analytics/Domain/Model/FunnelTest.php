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

namespace App\Tests\Unit\Analytics\Domain\Model;

use App\Analytics\Domain\Model\Funnel;
use App\Analytics\Domain\Model\FunnelStep;
use App\Analytics\Domain\Model\FunnelTriggerType;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Funnel::class)]
#[CoversClass(FunnelStep::class)]
#[CoversClass(FunnelTriggerType::class)]
final class FunnelTest extends TestCase
{
    #[Test]
    public function itReconstitutesStepsSortedByIndex(): void
    {
        $funnel = Funnel::reconstitute(
            Uuid::generate(),
            Uuid::generate(),
            'Signup',
            [
                FunnelStep::event(2, 'Done', 'signup_complete'),
                FunnelStep::pageview(0, 'Landing', '/'),
                FunnelStep::pageview(1, 'Pricing', '/pricing'),
            ],
            new DateTimeImmutable(),
            new DateTimeImmutable(),
        );

        self::assertSame(3, $funnel->stepCount());
        self::assertSame([0, 1, 2], array_map(static fn (FunnelStep $s): int => $s->stepIndex, $funnel->steps));
    }

    #[Test]
    public function pageviewStepDisplaysUrlPatternWhenNoEvent(): void
    {
        $step = FunnelStep::pageview(0, 'Landing', '/pricing');

        self::assertSame('/pricing', $step->displayEventName());
    }

    #[Test]
    public function eventStepDisplaysEventName(): void
    {
        $step = FunnelStep::event(1, 'Done', 'signup_complete');

        self::assertSame('signup_complete', $step->displayEventName());
    }
}
