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

namespace App\Shared\Infrastructure\Bus;

use App\Shared\Domain\Bus\Query\Query;
use App\Shared\Domain\Bus\Query\QueryBus;
use App\Shared\Domain\Bus\Query\QueryResult;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Adapter binding the {@see QueryBus} port to the `query.bus` Messenger bus.
 *
 * {@see HandleTrait} dispatches the query and returns the single handler's result.
 * Domain exceptions thrown in the handler are unwrapped from Messenger's wrapper.
 */
final class MessengerQueryBus implements QueryBus
{
    use HandleTrait;

    public function __construct(MessageBusInterface $queryBus)
    {
        $this->messageBus = $queryBus;
    }

    public function ask(Query $query): QueryResult
    {
        try {
            /** @var QueryResult $result */
            $result = $this->handle($query);

            return $result;
        } catch (HandlerFailedException $exception) {
            $cause = $exception->getPrevious();
            throw ($cause instanceof \Throwable ? $cause : $exception);
        }
    }
}
