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

namespace App\Reporting\Infrastructure\Http;

use App\Shared\Domain\Exception\ValidationException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Shared controller plumbing for the Reporting HTTP layer: dispatching a message
 * and unwrapping the single handler result, decoding JSON bodies, and reading
 * typed fields. Domain exceptions thrown inside handlers are unwrapped so the
 * global ExceptionListener can map them to RFC 9457 responses.
 */
trait BusDispatcher
{
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
        $value = $body[$field] ?? null;

        if (!is_string($value) || trim($value) === '') {
            throw $this->validationError($field, 'A non-empty string is required.');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function optionalString(array $body, string $field): ?string
    {
        $value = $body[$field] ?? null;

        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw $this->validationError($field, 'Must be a string.');
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function requireFloat(array $body, string $field): float
    {
        $value = $body[$field] ?? null;

        if (!is_int($value) && !is_float($value)) {
            throw $this->validationError($field, 'A number is required.');
        }

        return (float) $value;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function optionalFloat(array $body, string $field): ?float
    {
        $value = $body[$field] ?? null;

        if ($value === null) {
            return null;
        }

        if (!is_int($value) && !is_float($value)) {
            throw $this->validationError($field, 'Must be a number.');
        }

        return (float) $value;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function optionalBool(array $body, string $field): ?bool
    {
        $value = $body[$field] ?? null;

        if ($value === null) {
            return null;
        }

        if (!is_bool($value)) {
            throw $this->validationError($field, 'Must be a boolean.');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return list<mixed>
     */
    private function requireList(array $body, string $field): array
    {
        $value = $body[$field] ?? null;

        if (!is_array($value) || !array_is_list($value)) {
            throw $this->validationError($field, 'An array is required.');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return list<mixed>|null
     */
    private function optionalList(array $body, string $field): ?array
    {
        $value = $body[$field] ?? null;

        if ($value === null) {
            return null;
        }

        if (!is_array($value) || !array_is_list($value)) {
            throw $this->validationError($field, 'Must be an array.');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    private function requireObject(array $body, string $field): array
    {
        $value = $body[$field] ?? null;

        if (!is_array($value)) {
            throw $this->validationError($field, 'An object is required.');
        }

        if ($value !== [] && array_is_list($value)) {
            throw $this->validationError($field, 'An object is required.');
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * @param list<mixed> $items
     *
     * @return list<string>
     */
    private function stringList(array $items, string $field): array
    {
        $strings = [];
        foreach ($items as $item) {
            if (!is_string($item)) {
                throw $this->validationError($field, 'Must be an array of strings.');
            }
            $strings[] = $item;
        }

        return $strings;
    }

    /**
     * @param list<mixed> $items
     *
     * @return list<array<string, mixed>>
     */
    private function objectList(array $items, string $field): array
    {
        $objects = [];
        foreach ($items as $item) {
            if (!is_array($item) || ($item !== [] && array_is_list($item))) {
                throw $this->validationError($field, 'Must be an array of objects.');
            }
            /** @var array<string, mixed> $item */
            $objects[] = $item;
        }

        return $objects;
    }

    private function validationError(string $field, string $detail): ValidationException
    {
        return ValidationException::forField($field, 'invalid', $detail);
    }
}
