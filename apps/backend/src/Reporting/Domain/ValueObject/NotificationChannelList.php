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

namespace App\Reporting\Domain\ValueObject;

use App\Reporting\Domain\Exception\InvalidAlertException;

/**
 * A non-empty list of {@see NotificationChannel}s for an alert. The OpenAPI
 * `AlertCreateRequest` requires at least one channel; an upper bound keeps a
 * single alert from fanning out to an unbounded set of destinations.
 */
final readonly class NotificationChannelList
{
    public const MIN_ITEMS = 1;

    public const MAX_ITEMS = 20;

    /**
     * @param list<NotificationChannel> $channels
     */
    private function __construct(
        private array $channels,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public static function fromArrayList(array $rows): self
    {
        $channels = array_map(
            NotificationChannel::fromArray(...),
            $rows,
        );

        if (count($channels) < self::MIN_ITEMS) {
            throw InvalidAlertException::channelsRequired();
        }

        if (count($channels) > self::MAX_ITEMS) {
            throw InvalidAlertException::tooManyChannels(self::MAX_ITEMS);
        }

        return new self($channels);
    }

    /**
     * @return list<NotificationChannel>
     */
    public function all(): array
    {
        return $this->channels;
    }

    /**
     * @return list<array<string, string>>
     */
    public function toArrayList(): array
    {
        return array_map(static fn (NotificationChannel $c): array => $c->toArray(), $this->channels);
    }

    public function count(): int
    {
        return count($this->channels);
    }
}
