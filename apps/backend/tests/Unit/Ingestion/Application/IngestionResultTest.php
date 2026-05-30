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

namespace App\Tests\Unit\Ingestion\Application;

use App\Ingestion\Application\Dto\EventResult;
use App\Ingestion\Application\Dto\IngestionResult;
use App\Ingestion\Domain\Exception\FieldViolation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IngestionResult::class)]
#[CoversClass(EventResult::class)]
final class IngestionResultTest extends TestCase
{
    #[Test]
    public function allAcceptedWhenNoRejections(): void
    {
        $result = new IngestionResult([EventResult::accepted(0), EventResult::accepted(1)]);

        self::assertTrue($result->allAccepted());
        self::assertSame(2, $result->accepted());
        self::assertSame(0, $result->rejected());
        self::assertNull($result->firstRejection());
    }

    #[Test]
    public function itCountsAndExposesTheFirstRejection(): void
    {
        $violation = new FieldViolation('event_name', 'invalid_enum_value', 'bad');
        $result = new IngestionResult([
            EventResult::accepted(0),
            EventResult::rejected(1, 'validation-failed', [$violation]),
            EventResult::rejected(2, 'invalid-tracker-key'),
        ]);

        self::assertFalse($result->allAccepted());
        self::assertSame(1, $result->accepted());
        self::assertSame(2, $result->rejected());
        self::assertSame(1, $result->firstRejection()?->index);
    }

    #[Test]
    public function batchResultArrayMatchesTheOpenApiShape(): void
    {
        $violation = new FieldViolation('event_name', 'invalid_enum_value', 'bad');
        $result = new IngestionResult([
            EventResult::accepted(0),
            EventResult::rejected(1, 'validation-failed', [$violation]),
        ]);

        $array = $result->toArray();

        self::assertSame(1, $array['accepted']);
        self::assertSame(1, $array['rejected']);
        self::assertSame([
            'index' => 0,
            'status' => 'accepted',
        ], $array['results'][0]);
        self::assertSame('rejected', $array['results'][1]['status']);
        self::assertSame('validation-failed', $array['results'][1]['code']);
        /** @var list<array<string, mixed>> $fieldErrors */
        $fieldErrors = $array['results'][1]['errors'];
        self::assertSame('event_name', $fieldErrors[0]['field']);
    }

    #[Test]
    public function aRejectionWithoutFieldErrorsOmitsTheErrorsKey(): void
    {
        $result = new IngestionResult([EventResult::rejected(0, 'invalid-tracker-key')]);

        self::assertArrayNotHasKey('errors', $result->toArray()['results'][0]);
    }
}
