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

use App\Reporting\Domain\Exception\InvalidAlertException;
use App\Reporting\Domain\ValueObject\NotificationChannel;
use App\Reporting\Domain\ValueObject\NotificationChannelList;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(NotificationChannel::class)]
#[CoversClass(NotificationChannelList::class)]
final class NotificationChannelTest extends TestCase
{
    #[Test]
    public function itBuildsAnEmailChannel(): void
    {
        $channel = NotificationChannel::fromArray([
            'type' => 'email',
            'email' => 'a@b.com',
        ]);

        self::assertSame('email', $channel->type());
        self::assertSame('a@b.com', $channel->email()?->value());
        self::assertNull($channel->webhookUrl());
        self::assertSame([
            'type' => 'email',
            'email' => 'a@b.com',
        ], $channel->toArray());
    }

    #[Test]
    public function itBuildsWebhookAndSlackChannels(): void
    {
        $webhook = NotificationChannel::fromArray([
            'type' => 'webhook',
            'webhook_url' => 'https://hooks.test/x',
        ]);
        $slack = NotificationChannel::fromArray([
            'type' => 'slack',
            'webhook_url' => 'https://hooks.slack.com/x',
        ]);

        self::assertSame('https://hooks.test/x', $webhook->webhookUrl());
        self::assertSame('slack', $slack->type());
        self::assertSame([
            'type' => 'webhook',
            'webhook_url' => 'https://hooks.test/x',
        ], $webhook->toArray());
    }

    #[Test]
    public function itRejectsNonHttpWebhookUrl(): void
    {
        $this->expectException(InvalidAlertException::class);
        NotificationChannel::fromArray([
            'type' => 'webhook',
            'webhook_url' => 'ftp://evil/x',
        ]);
    }

    #[Test]
    public function itRejectsMissingChannelType(): void
    {
        $this->expectException(InvalidAlertException::class);
        NotificationChannel::fromArray([
            'email' => 'a@b.com',
        ]);
    }

    #[Test]
    public function itRejectsUnknownChannelType(): void
    {
        $this->expectException(InvalidAlertException::class);
        NotificationChannel::fromArray([
            'type' => 'carrier_pigeon',
        ]);
    }

    #[Test]
    public function itRejectsMissingEmailField(): void
    {
        $this->expectException(InvalidAlertException::class);
        NotificationChannel::fromArray([
            'type' => 'email',
        ]);
    }

    #[Test]
    public function listRejectsEmpty(): void
    {
        $this->expectException(InvalidAlertException::class);
        NotificationChannelList::fromArrayList([]);
    }

    #[Test]
    public function listRoundTrips(): void
    {
        $rows = [
            [
                'type' => 'email',
                'email' => 'a@b.com',
            ],
            [
                'type' => 'webhook',
                'webhook_url' => 'https://hooks.test/x',
            ],
        ];

        $list = NotificationChannelList::fromArrayList($rows);

        self::assertSame(2, $list->count());
        self::assertSame($rows, $list->toArrayList());
    }

    #[Test]
    public function listRejectsTooMany(): void
    {
        $rows = [];
        for ($i = 0; $i <= NotificationChannelList::MAX_ITEMS; ++$i) {
            $rows[] = [
                'type' => 'email',
                'email' => sprintf('u%d@b.com', $i),
            ];
        }

        $this->expectException(InvalidAlertException::class);
        NotificationChannelList::fromArrayList($rows);
    }
}
