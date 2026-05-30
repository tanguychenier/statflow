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

use App\Ingestion\Application\Service\EventBatchWriter;
use App\Ingestion\Domain\Model\BufferedEvent;
use Predis\ClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Reads buffered events from the `events:raw` Redis Stream via a consumer group
 * and hands each batch to the EventBatchWriter, acknowledging only after a
 * successful write (at-least-once delivery, architecture.md §"Batch Writer").
 *
 * One read = one ClickHouse INSERT. A failed write leaves the batch unacked so
 * a later pass (or another consumer) retries it; malformed entries that cannot
 * even be decoded are acked-and-logged so one poison message cannot wedge the
 * stream.
 *
 * Each cycle first reclaims entries left pending by a crashed or stalled
 * consumer (XAUTOCLAIM): without this, messages delivered to a worker that died
 * before acking would sit in the pending list forever, invisible to a fresh
 * `>` read. Reclaiming makes the at-least-once guarantee survive worker crashes.
 */
final readonly class RedisStreamConsumer
{
    private const STREAM_KEY = 'events:raw';

    private const GROUP = 'statflow_writers';

    /**
     * How long an entry must sit unacknowledged before another consumer may
     * reclaim it — long enough that a healthy in-flight batch is never stolen.
     */
    private const RECLAIM_MIN_IDLE_MS = 30000;

    private LoggerInterface $logger;

    public function __construct(
        private ClientInterface $redis,
        private BufferedEventCodec $codec,
        private EventBatchWriter $batchWriter,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function ensureGroup(): void
    {
        // MKSTREAM creates the stream if absent; BUSYGROUP means it already exists.
        // Start the group at 0 (stream beginning), not $ (latest): events buffered
        // before the worker first runs must still be delivered — an at-least-once
        // ingestion buffer cannot silently drop anything written before startup.
        try {
            $this->redis->executeRaw([
                'XGROUP', 'CREATE', self::STREAM_KEY, self::GROUP, '0', 'MKSTREAM',
            ]);
        } catch (\Throwable $e) {
            if (!str_contains($e->getMessage(), 'BUSYGROUP')) {
                throw $e;
            }
        }
    }

    /**
     * Read and process one batch. Returns the number of events written.
     *
     * Reclaims stale pending entries first; only when none remain does it read
     * fresh messages, so crash-orphaned events are always retried before new work.
     *
     * @param non-empty-string $consumerName
     */
    public function consumeOnce(string $consumerName, int $batchSize = 1000, int $blockMs = 2000): int
    {
        $reclaimed = $this->reclaimStale($consumerName, $batchSize);
        if ($reclaimed > 0) {
            return $reclaimed;
        }

        /** @var mixed $response */
        $response = $this->redis->executeRaw([
            'XREADGROUP', 'GROUP', self::GROUP, $consumerName,
            'COUNT', (string) $batchSize,
            'BLOCK', (string) $blockMs,
            'STREAMS', self::STREAM_KEY, '>',
        ]);

        return $this->process($this->extractReadEntries($response));
    }

    /**
     * Claims entries idle beyond the reclaim threshold from any (possibly dead)
     * consumer and processes them, so an event is never lost to a crash.
     *
     * @param non-empty-string $consumerName
     */
    private function reclaimStale(string $consumerName, int $batchSize): int
    {
        /** @var mixed $response */
        $response = $this->redis->executeRaw([
            'XAUTOCLAIM', self::STREAM_KEY, self::GROUP, $consumerName,
            (string) self::RECLAIM_MIN_IDLE_MS, '0', 'COUNT', (string) $batchSize,
        ]);

        return $this->process($this->extractClaimedEntries($response));
    }

    /**
     * Decode, write, and acknowledge one batch of [id, payload] pairs.
     *
     * @param list<array{0: string, 1: string}> $entries
     */
    private function process(array $entries): int
    {
        if ($entries === []) {
            return 0;
        }

        $ids = [];
        $events = [];
        foreach ($entries as [$id, $payload]) {
            $ids[] = $id;
            $decoded = $this->tryDecode($payload, $id);
            if ($decoded instanceof BufferedEvent) {
                $events[] = $decoded;
            }
        }

        if ($events !== []) {
            $this->batchWriter->write($events);
        }

        $this->acknowledge($ids);

        return count($events);
    }

    private function tryDecode(string $payload, string $id): ?BufferedEvent
    {
        try {
            return $this->codec->decode($payload);
        } catch (\Throwable $e) {
            $this->logger->warning('Discarding undecodable stream entry', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param list<string> $ids
     */
    private function acknowledge(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $this->redis->executeRaw(['XACK', self::STREAM_KEY, self::GROUP, ...$ids]);
    }

    /**
     * Flattens an XREADGROUP reply into [id, payload] pairs.
     * Reply shape: [ [streamName, [ [id, [field, value, ...]], ... ]] ].
     *
     * @return list<array{0: string, 1: string}>
     */
    private function extractReadEntries(mixed $response): array
    {
        if (!is_array($response) || $response === []) {
            return [];
        }

        $firstStream = $response[0] ?? null;
        $streamReply = is_array($firstStream) ? ($firstStream[1] ?? null) : null;

        return is_array($streamReply) ? $this->entriesToPairs($streamReply) : [];
    }

    /**
     * Flattens an XAUTOCLAIM reply into [id, payload] pairs.
     * Reply shape: [ cursor, [ [id, [field, value, ...]], ... ], [deletedId, ...] ].
     *
     * @return list<array{0: string, 1: string}>
     */
    private function extractClaimedEntries(mixed $response): array
    {
        $entries = is_array($response) ? ($response[1] ?? null) : null;

        return is_array($entries) ? $this->entriesToPairs($entries) : [];
    }

    /**
     * @param array<mixed> $streamReply list of [id, [field, value, ...]] entries
     *
     * @return list<array{0: string, 1: string}>
     */
    private function entriesToPairs(array $streamReply): array
    {
        $entries = [];
        foreach ($streamReply as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            if (!isset($entry[0], $entry[1])) {
                continue;
            }
            if (!is_array($entry[1])) {
                continue;
            }
            $id = is_scalar($entry[0]) ? (string) $entry[0] : '';
            /** @var list<mixed> $fields */
            $fields = array_values($entry[1]);
            $payload = $this->payloadField($fields);
            if ($payload !== null) {
                $entries[] = [$id, $payload];
            }
        }

        return $entries;
    }

    /**
     * @param list<mixed> $fields flat [field, value, field, value, ...]
     */
    private function payloadField(array $fields): ?string
    {
        $count = count($fields);
        for ($i = 0; $i + 1 < $count; $i += 2) {
            if (is_scalar($fields[$i]) && (string) $fields[$i] === 'payload') {
                return is_scalar($fields[$i + 1]) ? (string) $fields[$i + 1] : null;
            }
        }

        return null;
    }
}
