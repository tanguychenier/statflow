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

namespace App\Sites\Infrastructure\Http;

use App\Sites\Domain\Exception\InvalidSiteSettingsException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Shared controller plumbing for the Sites HTTP layer: dispatching a message and
 * unwrapping the single handler result, decoding JSON bodies, and reading typed
 * fields. Keeps every controller thin and consistent. Domain exceptions thrown
 * inside handlers are unwrapped so the global ExceptionListener can map them.
 */
trait BusDispatcher
{
    /**
     * Dispatch a message and return its single handler's result.
     */
    private function handle(MessageBusInterface $bus, object $message): mixed
    {
        try {
            $envelope = $bus->dispatch($message);
        } catch (HandlerFailedException $exception) {
            throw $exception->getPrevious() ?? $exception;
        }

        return $this->resultOf($envelope);
    }

    private function resultOf(Envelope $envelope): mixed
    {
        /** @var HandledStamp|null $stamp */
        $stamp = $envelope->last(HandledStamp::class);

        return $stamp?->getResult();
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeBody(Request $request): array
    {
        $content = $request->getContent();

        if ($content === '') {
            return [];
        }

        $decoded = json_decode($content, true);

        if (!is_array($decoded)) {
            throw new BadRequestHttpException('Request body must be a valid JSON object.');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function requireString(array $body, string $field): string
    {
        if (!array_key_exists($field, $body) || !is_string($body[$field]) || trim($body[$field]) === '') {
            throw InvalidSiteSettingsException::notString($field);
        }

        return $body[$field];
    }

    /**
     * @param array<string, mixed> $body
     */
    private function optionalString(array $body, string $field, string $default): string
    {
        if (!array_key_exists($field, $body) || $body[$field] === null) {
            return $default;
        }

        if (!is_string($body[$field])) {
            throw InvalidSiteSettingsException::notString($field);
        }

        return $body[$field];
    }

    /**
     * @param array<string, mixed> $body
     */
    private function nullableStringField(array $body, string $field): ?string
    {
        if (!array_key_exists($field, $body) || $body[$field] === null) {
            return null;
        }

        if (!is_string($body[$field])) {
            throw InvalidSiteSettingsException::notString($field);
        }

        $value = trim($body[$field]);

        return $value === '' ? null : $value;
    }

    private function nullableString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
