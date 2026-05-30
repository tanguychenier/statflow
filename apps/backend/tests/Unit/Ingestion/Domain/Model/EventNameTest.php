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

namespace App\Tests\Unit\Ingestion\Domain\Model;

use App\Ingestion\Domain\Model\EventName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EventName::class)]
final class EventNameTest extends TestCase
{
    #[Test]
    #[DataProvider('acceptedNames')]
    public function itAcceptsEveryVocabularyMember(string $name): void
    {
        self::assertTrue(EventName::isAccepted($name));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function acceptedNames(): iterable
    {
        foreach ([
            'pageview', 'route_change', 'engagement', 'click', 'rage_click',
            'dead_click', 'scroll_depth', 'form_focus', 'form_submit', 'form_abandon',
            'element_visibility', 'custom', 'web_vital_lcp', 'web_vital_cls',
            'web_vital_inp', 'js_error', 'heatmap_batch',
        ] as $name) {
            yield $name => [$name];
        }
    }

    #[Test]
    public function itRejectsTheServerDerivedConversionName(): void
    {
        self::assertFalse(EventName::isAccepted('conversion'));
        self::assertSame('conversion', EventName::SERVER_DERIVED);
    }

    #[Test]
    public function itRejectsTheLegacyScrollName(): void
    {
        self::assertFalse(EventName::isAccepted('scroll'));
    }

    #[Test]
    #[DataProvider('behavioralNames')]
    public function itKnowsWhichNamesCarryBehavioralSignals(string $name, bool $carries): void
    {
        self::assertSame($carries, EventName::from($name)->carriesBehavioralSignals());
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function behavioralNames(): iterable
    {
        yield 'click carries' => ['click', true];
        yield 'rage_click carries' => ['rage_click', true];
        yield 'dead_click carries' => ['dead_click', true];
        yield 'scroll_depth carries' => ['scroll_depth', true];
        yield 'engagement carries' => ['engagement', true];
        yield 'pageview does not' => ['pageview', false];
        yield 'custom does not' => ['custom', false];
    }
}
