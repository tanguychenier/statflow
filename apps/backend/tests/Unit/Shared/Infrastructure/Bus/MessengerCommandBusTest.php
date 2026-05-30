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

namespace App\Tests\Unit\Shared\Infrastructure\Bus;

use App\Shared\Domain\Bus\Command\Command;
use App\Shared\Domain\Exception\ConflictException;
use App\Shared\Infrastructure\Bus\MessengerCommandBus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(MessengerCommandBus::class)]
final class MessengerCommandBusTest extends TestCase
{
    #[Test]
    public function itDispatchesTheCommandOnTheUnderlyingBus(): void
    {
        $command = $this->command();
        $recorder = $this->recordingMessenger();

        (new MessengerCommandBus($recorder))->dispatch($command);

        self::assertSame([$command], $recorder->dispatched);
    }

    #[Test]
    public function itUnwrapsTheDomainExceptionThrownInsideAHandler(): void
    {
        $cause = ConflictException::of('domain', 'example.com');
        $messenger = $this->messengerThatThrows(new HandlerFailedException(new Envelope($this->command()), [$cause]));

        $this->expectExceptionObject($cause);

        (new MessengerCommandBus($messenger))->dispatch($this->command());
    }

    #[Test]
    public function itRethrowsTheWrapperWhenThereIsNoNestedCause(): void
    {
        // Symfony's HandlerFailedException always wraps at least one exception.
        // The "no nested cause" edge case (getPrevious() returning non-Throwable) can
        // only be constructed by bypassing HandlerFailedException's own __construct.
        // We build a subclass that calls RuntimeException::__construct with null previous
        // so that getPrevious() returns null, exercising the adapter's fallback path.
        $command = $this->command();
        $innerCause = new \RuntimeException('inner');
        new HandlerFailedException(new Envelope($command), [$innerCause]);

        // @phpstan-ignore-next-line (constructor.missingParentCall: intentional bypass to get getPrevious()===null)
        $wrapperWithNoCause = new class() extends HandlerFailedException {
            /**
             * @phpstan-ignore constructor.missingParentCall
             */
            public function __construct()
            {
                // Bypass HandlerFailedException::__construct (which requires non-empty $exceptions)
                // so that getPrevious() returns null, exercising the adapter's null-cause path.
                \RuntimeException::__construct('Wrapper with no retrievable cause', 0);
            }
        };

        $messenger = $this->messengerThatThrows($wrapperWithNoCause);

        $this->expectException(HandlerFailedException::class);

        (new MessengerCommandBus($messenger))->dispatch($this->command());
    }

    private function command(): Command
    {
        return new class() implements Command {};
    }

    private function recordingMessenger(): RecordingMessageBus
    {
        return new RecordingMessageBus();
    }

    private function messengerThatThrows(RuntimeException $exception): MessageBusInterface
    {
        return new class($exception) implements MessageBusInterface {
            public function __construct(
                private readonly RuntimeException $exception
            ) {
            }

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                throw $this->exception;
            }
        };
    }
}

/**
 * Test fixture: a message bus that records dispatched messages.
 */
final class RecordingMessageBus implements MessageBusInterface
{
    /**
     * @var list<object>
     */
    public array $dispatched = [];

    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $this->dispatched[] = $message;

        return new Envelope($message);
    }
}
