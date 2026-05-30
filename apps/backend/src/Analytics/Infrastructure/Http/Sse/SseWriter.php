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

namespace App\Analytics\Infrastructure\Http\Sse;

/**
 * Emits Server-Sent Events framing. Extracted from the stream controller so the
 * raw `echo`/`flush`/`sleep` side effects can be swapped for a buffer in tests.
 */
class SseWriter
{
    /**
     * @param array<string, mixed> $data
     */
    public function emit(string $event, array $data): void
    {
        $json = json_encode($data, JSON_THROW_ON_ERROR);
        $this->write(sprintf("event: %s\ndata: %s\n\n", $event, $json));
    }

    public function flush(): void
    {
        if (function_exists('ob_get_level') && ob_get_level() > 0) {
            @ob_flush();
        }
        flush();
    }

    public function sleep(int $seconds): void
    {
        sleep($seconds);
    }

    protected function write(string $payload): void
    {
        echo $payload;
    }
}
