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

namespace App\Ingestion\Infrastructure\Redis;

use App\Ingestion\Domain\Exception\BufferUnavailable;
use App\Ingestion\Domain\Port\EventBufferPort;
use Predis\ClientInterface;
use Throwable;

/**
 * Redis Streams adapter for EventBufferPort. Each event is one XADD entry on the
 * `events:raw` stream; the batch-writer consumer group reads them via
 * XREADGROUP and acknowledges per batch (at-least-once delivery,
 * architecture.md §"Batch Writer Worker").
 *
 * A bounded `MAXLEN ~` cap protects memory if the consumer falls behind; the
 * cap is approximate ("~") so Redis can trim efficiently at part boundaries.
 */
final readonly class RedisStreamEventBuffer implements EventBufferPort
{
    private const STREAM_KEY = 'events:raw';

    private const MAX_STREAM_LENGTH = 1_000_000;

    public function __construct(
        private ClientInterface $redis,
        private BufferedEventCodec $codec,
    ) {
    }

    public function append(array $events): void
    {
        if ($events === []) {
            return;
        }

        try {
            foreach ($events as $event) {
                $this->redis->executeRaw([
                    'XADD',
                    self::STREAM_KEY,
                    'MAXLEN',
                    '~',
                    (string) self::MAX_STREAM_LENGTH,
                    '*',
                    'payload',
                    $this->codec->encode($event),
                ]);
            }
        } catch (Throwable $cause) {
            throw BufferUnavailable::fromCause($cause);
        }
    }
}
