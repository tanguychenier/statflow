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

namespace App\Tests\Unit\Reporting\Domain\ValueObject;

use App\Reporting\Domain\Exception\InvalidReportDefinitionException;
use App\Reporting\Domain\ValueObject\QueryDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(QueryDefinition::class)]
#[CoversClass(InvalidReportDefinitionException::class)]
final class QueryDefinitionTest extends TestCase
{
    #[Test]
    public function itKeepsAnObjectPayload(): void
    {
        $values = [
            'metric' => 'pageviews',
            'date_range' => [
                'from' => '2026-01-01',
                'to' => '2026-01-31',
            ],
        ];

        self::assertSame($values, QueryDefinition::fromArray($values)->toArray());
    }

    #[Test]
    public function itAcceptsAnEmptyObject(): void
    {
        self::assertSame([], QueryDefinition::fromArray([])->toArray());
    }

    #[Test]
    public function itRejectsAList(): void
    {
        $this->expectException(InvalidReportDefinitionException::class);
        QueryDefinition::fromArray(['a', 'b']);
    }

    #[Test]
    public function itRejectsAnOversizedPayload(): void
    {
        $this->expectException(InvalidReportDefinitionException::class);
        QueryDefinition::fromArray([
            'blob' => str_repeat('x', QueryDefinition::MAX_SERIALISED_BYTES),
        ]);
    }
}
