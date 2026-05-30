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

namespace App\Tests\Unit\Analytics\Infrastructure\Http\Sse;

use App\Analytics\Infrastructure\Http\Sse\SseWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SseWriter::class)]
final class SseWriterTest extends TestCase
{
    #[Test]
    public function itFramesAnEventWithNameAndJsonData(): void
    {
        $writer = new class() extends SseWriter {
            public string $buffer = '';

            protected function write(string $payload): void
            {
                $this->buffer .= $payload;
            }

            public function sleep(int $seconds): void
            {
                // no-op in tests
            }
        };

        $writer->emit('stats', [
            'current_visitors' => 5,
        ]);

        self::assertSame("event: stats\ndata: {\"current_visitors\":5}\n\n", $writer->buffer);
    }

    #[Test]
    public function flushAndSleepAreCallableWithoutOutput(): void
    {
        $writer = new class() extends SseWriter {
            protected function write(string $payload): void
            {
            }

            public function sleep(int $seconds): void
            {
            }
        };

        $writer->emit('event', [
            'x' => 1,
        ]);
        $writer->flush();
        $writer->sleep(0);

        $this->expectNotToPerformAssertions();
    }
}
