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

use App\Shared\Domain\Bus\Query\Query;
use App\Shared\Domain\Bus\Query\QueryResult;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Infrastructure\Bus\MessengerQueryBus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(MessengerQueryBus::class)]
final class MessengerQueryBusTest extends TestCase
{
    #[Test]
    public function itReturnsTheHandlerResult(): void
    {
        $result = $this->queryResult();
        $messenger = $this->messengerReturning($result);

        self::assertSame($result, (new MessengerQueryBus($messenger))->ask($this->query()));
    }

    #[Test]
    public function itUnwrapsTheDomainExceptionThrownInsideAHandler(): void
    {
        $cause = NotFoundException::of('Site', 'abc');
        $messenger = $this->messengerThatThrows(new HandlerFailedException(new Envelope($this->query()), [$cause]));

        $this->expectExceptionObject($cause);

        (new MessengerQueryBus($messenger))->ask($this->query());
    }

    #[Test]
    public function itRethrowsTheWrapperWhenThereIsNoNestedCause(): void
    {
        // Symfony's HandlerFailedException always wraps at least one exception.
        // The "no nested cause" edge case (getPrevious() returning null) can only
        // be constructed by bypassing HandlerFailedException's own __construct.
        // We build a subclass that calls RuntimeException::__construct with null
        // so getPrevious() (final) returns null, exercising the adapter's fallback.
        $innerCause = new \RuntimeException('inner');
        new HandlerFailedException(new Envelope($this->query()), [$innerCause]);

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

        (new MessengerQueryBus($messenger))->ask($this->query());
    }

    private function query(): Query
    {
        return new class() implements Query {};
    }

    private function queryResult(): QueryResult
    {
        return new class() implements QueryResult {};
    }

    private function messengerReturning(QueryResult $result): MessageBusInterface
    {
        return new class($result) implements MessageBusInterface {
            public function __construct(
                private readonly QueryResult $result
            ) {
            }

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                return new Envelope($message, [new HandledStamp($this->result, 'handler')]);
            }
        };
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
