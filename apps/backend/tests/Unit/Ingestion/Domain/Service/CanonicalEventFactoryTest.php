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

namespace App\Tests\Unit\Ingestion\Domain\Service;

use App\Ingestion\Domain\Exception\EventValidationFailed;
use App\Ingestion\Domain\Exception\FieldViolation;
use App\Ingestion\Domain\Model\EventName;
use App\Ingestion\Domain\Service\CanonicalEventFactory;
use App\Tests\Ingestion\Support\EventFixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CanonicalEventFactory::class)]
#[CoversClass(EventValidationFailed::class)]
#[CoversClass(FieldViolation::class)]
final class CanonicalEventFactoryTest extends TestCase
{
    private CanonicalEventFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new CanonicalEventFactory();
    }

    #[Test]
    public function itBuildsAValidCanonicalEvent(): void
    {
        $event = $this->factory->fromCanonical(EventFixtures::canonicalPageview([
            'referrer' => 'https://google.com/',
            'title' => 'Post 1',
            'viewport_width' => 1440,
            'language' => 'fr-FR',
        ]));

        self::assertSame(EventFixtures::EVENT_ID, $event->eventId);
        self::assertSame(EventName::Pageview, $event->eventName);
        self::assertSame(0, $event->seq);
        self::assertSame('example.com', $event->hostname);
        self::assertSame('2025-06-15T14:30:00.123Z', $event->timestamp->format('Y-m-d\TH:i:s.v\Z'));
        self::assertSame(1440, $event->viewportWidth);
        self::assertSame('fr-FR', $event->language);
        self::assertTrue($event->behavioral->isEmpty());
        self::assertSame([], $event->customProperties);
    }

    #[Test]
    public function itCollectsEveryMissingRequiredField(): void
    {
        try {
            $this->factory->fromCanonical([
                'event_name' => 'pageview',
            ]);
            self::fail('Expected EventValidationFailed.');
        } catch (EventValidationFailed $e) {
            $fields = array_map(static fn (FieldViolation $v): string => $v->field, $e->violations());
            self::assertContains('event_id', $fields);
            self::assertContains('site_key', $fields);
            self::assertContains('timestamp', $fields);
            self::assertContains('seq', $fields);
            self::assertContains('url', $fields);
            self::assertContains('pathname', $fields);
            self::assertContains('hostname', $fields);
        }
    }

    #[Test]
    public function itRejectsAnEventNameOutsideTheVocabulary(): void
    {
        $this->assertViolation(
            EventFixtures::canonicalPageview([
                'event_name' => 'scroll',
            ]),
            'event_name',
            'invalid_enum_value',
        );
    }

    #[Test]
    public function itRejectsTheServerDerivedConversionEvent(): void
    {
        $this->assertViolation(
            EventFixtures::canonicalPageview([
                'event_name' => 'conversion',
            ]),
            'event_name',
            'invalid_enum_value',
        );
    }

    #[Test]
    public function itRejectsAMalformedEventId(): void
    {
        $this->assertViolation(
            EventFixtures::canonicalPageview([
                'event_id' => 'not-a-uuid',
            ]),
            'event_id',
            'invalid_format',
        );
    }

    #[Test]
    public function itRejectsAnInvalidUrl(): void
    {
        $this->assertViolation(
            EventFixtures::canonicalPageview([
                'url' => 'not a url',
            ]),
            'url',
            'invalid_format',
        );
    }

    #[Test]
    public function itRejectsANegativeSeq(): void
    {
        $this->assertViolation(
            EventFixtures::canonicalPageview([
                'seq' => -1,
            ]),
            'seq',
            'out_of_range',
        );
    }

    #[Test]
    public function itRejectsANonIntegerSeq(): void
    {
        $this->assertViolation(
            EventFixtures::canonicalPageview([
                'seq' => '3',
            ]),
            'seq',
            'out_of_range',
        );
    }

    #[Test]
    public function itRejectsAnUnparseableTimestamp(): void
    {
        $this->assertViolation(
            EventFixtures::canonicalPageview([
                'timestamp' => '15 June 2025',
            ]),
            'timestamp',
            'invalid_format',
        );
    }

    #[Test]
    public function itRejectsANonStringTimestamp(): void
    {
        $this->assertViolation(
            EventFixtures::canonicalPageview([
                'timestamp' => 123,
            ]),
            'timestamp',
            'invalid_type',
        );
    }

    #[Test]
    public function itAcceptsAnAtomTimestampFromServerIntegrations(): void
    {
        $event = $this->factory->fromCanonical(
            EventFixtures::canonicalPageview([
                'timestamp' => '2025-06-15T14:30:00+00:00',
            ]),
        );

        self::assertSame('2025-06-15T14:30:00.000Z', $event->timestamp->format('Y-m-d\TH:i:s.v\Z'));
    }

    #[Test]
    #[DataProvider('outOfRangeDimensions')]
    public function itRejectsOutOfRangeDimensions(int $value): void
    {
        $this->assertViolation(
            EventFixtures::canonicalPageview([
                'viewport_width' => $value,
            ]),
            'viewport_width',
            'out_of_range',
        );
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function outOfRangeDimensions(): iterable
    {
        yield 'negative' => [-1];
        yield 'too large' => [20001];
    }

    #[Test]
    public function itRejectsANonIntegerDimension(): void
    {
        $this->assertViolation(
            EventFixtures::canonicalPageview([
                'screen_width' => 12.5,
            ]),
            'screen_width',
            'invalid_type',
        );
    }

    #[Test]
    public function itTruncatesOverlongUrlAsMaxLengthViolation(): void
    {
        $this->assertViolation(
            EventFixtures::canonicalPageview([
                'url' => 'https://e.com/' . str_repeat('a', 3000),
            ]),
            'url',
            'max_length_exceeded',
        );
    }

    #[Test]
    public function itValidatesBehavioralSignals(): void
    {
        $event = $this->factory->fromCanonical(EventFixtures::canonicalPageview([
            'event_name' => 'click',
            'behavioral' => [
                'click_x' => 742,
                'click_x_pct' => 51.5,
                'element_tag' => 'button',
                'scroll_depth_pct' => 42,
                'is_rage_click' => true,
            ],
        ]));

        self::assertSame(742, $event->behavioral->clickX);
        self::assertSame(51.5, $event->behavioral->clickXPct);
        self::assertSame('button', $event->behavioral->elementTag);
        self::assertSame(42, $event->behavioral->scrollDepthPct);
        self::assertTrue($event->behavioral->isRageClick);
    }

    #[Test]
    public function itRejectsAnOutOfRangeScrollDepth(): void
    {
        $this->assertViolation(
            EventFixtures::canonicalPageview([
                'event_name' => 'scroll_depth',
                'behavioral' => [
                    'scroll_depth_pct' => 101,
                ],
            ]),
            'behavioral.scroll_depth_pct',
            'out_of_range',
        );
    }

    #[Test]
    public function itRejectsAnOutOfRangeClickPercentage(): void
    {
        $this->assertViolation(
            EventFixtures::canonicalPageview([
                'event_name' => 'click',
                'behavioral' => [
                    'click_x_pct' => 150.0,
                ],
            ]),
            'behavioral.click_x_pct',
            'out_of_range',
        );
    }

    #[Test]
    public function itRejectsANonBooleanRageClick(): void
    {
        $this->assertViolation(
            EventFixtures::canonicalPageview([
                'event_name' => 'click',
                'behavioral' => [
                    'is_rage_click' => 'yes',
                ],
            ]),
            'behavioral.is_rage_click',
            'invalid_type',
        );
    }

    #[Test]
    public function itTruncatesOverlongElementText(): void
    {
        $event = $this->factory->fromCanonical(EventFixtures::canonicalPageview([
            'event_name' => 'click',
            'behavioral' => [
                'element_text' => str_repeat('x', 500),
            ],
        ]));

        self::assertNotNull($event->behavioral->elementText);
        self::assertSame(200, mb_strlen($event->behavioral->elementText));
    }

    #[Test]
    public function itRejectsBehavioralThatIsNotAnObject(): void
    {
        $this->assertViolation(
            EventFixtures::canonicalPageview([
                'behavioral' => 'oops',
            ]),
            'behavioral',
            'invalid_type',
        );
    }

    #[Test]
    public function itAcceptsValidCustomProperties(): void
    {
        $event = $this->factory->fromCanonical(EventFixtures::canonicalPageview([
            'custom_properties' => [
                'plan' => 'pro',
                'seats' => 5,
                'trial' => true,
            ],
        ]));

        self::assertSame([
            'plan' => 'pro',
            'seats' => 5,
            'trial' => true,
        ], $event->customProperties);
    }

    #[Test]
    public function itRejectsTooManyCustomProperties(): void
    {
        $props = [];
        for ($i = 0; $i < 21; ++$i) {
            $props['key_' . $i] = $i;
        }

        $this->assertViolation(
            EventFixtures::canonicalPageview([
                'custom_properties' => $props,
            ]),
            'custom_properties',
            'out_of_range',
        );
    }

    #[Test]
    public function itRejectsCustomPropertyKeysOutsideThePattern(): void
    {
        $this->assertViolation(
            EventFixtures::canonicalPageview([
                'custom_properties' => [
                    'Bad Key' => 'x',
                ],
            ]),
            'custom_properties.Bad Key',
            'invalid_format',
        );
    }

    #[Test]
    public function itRejectsNestedCustomPropertyValues(): void
    {
        $this->assertViolation(
            EventFixtures::canonicalPageview([
                'custom_properties' => [
                    'nested' => [
                        'a' => 1,
                    ],
                ],
            ]),
            'custom_properties.nested',
            'invalid_type',
        );
    }

    #[Test]
    public function itRejectsCustomPropertiesThatAreAList(): void
    {
        $this->assertViolation(
            EventFixtures::canonicalPageview([
                'custom_properties' => ['a', 'b'],
            ]),
            'custom_properties',
            'invalid_type',
        );
    }

    #[Test]
    public function itRejectsOverlongCustomPropertyStrings(): void
    {
        $this->assertViolation(
            EventFixtures::canonicalPageview([
                'custom_properties' => [
                    'note' => str_repeat('x', 1001),
                ],
            ]),
            'custom_properties.note',
            'max_length_exceeded',
        );
    }

    #[Test]
    public function itRejectsCustomPropertiesExceedingTheSerialisedByteBudget(): void
    {
        $props = [];
        for ($i = 0; $i < 10; ++$i) {
            $props['k' . $i] = str_repeat('v', 900);
        }

        $this->assertViolation(
            EventFixtures::canonicalPageview([
                'custom_properties' => $props,
            ]),
            'custom_properties',
            'out_of_range',
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function assertViolation(array $payload, string $field, string $code): void
    {
        try {
            $this->factory->fromCanonical($payload);
            self::fail(sprintf('Expected a "%s" violation on "%s".', $code, $field));
        } catch (EventValidationFailed $e) {
            foreach ($e->violations() as $violation) {
                if ($violation->field === $field && $violation->code === $code) {
                    self::assertSame($code, $violation->code);

                    return;
                }
            }

            self::fail(sprintf(
                'No "%s" violation on "%s". Got: %s',
                $code,
                $field,
                implode(', ', array_map(
                    static fn (FieldViolation $v): string => $v->field . ':' . $v->code,
                    $e->violations(),
                )),
            ));
        }
    }
}
