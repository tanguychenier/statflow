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

namespace App\Tests\Unit\Reporting\Infrastructure;

use App\Reporting\Domain\ValueObject\EmailAddress;
use App\Reporting\Infrastructure\Mailer\NullReportMailer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(NullReportMailer::class)]
final class NullReportMailerTest extends TestCase
{
    #[Test]
    public function itIsNeverConfigured(): void
    {
        self::assertFalse((new NullReportMailer())->isConfigured());
    }

    #[Test]
    public function sendIsANoOp(): void
    {
        $mailer = new NullReportMailer();

        $mailer->send(EmailAddress::fromString('a@b.com'), 'subject', '<p>html</p>', 'text');

        $this->expectNotToPerformAssertions();
    }
}
