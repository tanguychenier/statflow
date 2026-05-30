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

namespace App\Identity\Infrastructure\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Shared controller plumbing for the Identity HTTP layer: dispatch a message and
 * unwrap its single handler result, decode JSON bodies, and read typed fields.
 * Domain exceptions thrown inside handlers are unwrapped so the global
 * ExceptionListener can render them as RFC 9457 Problem Details.
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
    private function stringField(array $body, string $field): string
    {
        $value = $body[$field] ?? null;

        return is_string($value) ? $value : '';
    }

    /**
     * @param array<string, mixed> $body
     */
    private function nullableStringField(array $body, string $field): ?string
    {
        $value = $body[$field] ?? null;

        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return list<string>
     */
    private function stringListField(array $body, string $field): array
    {
        $value = $body[$field] ?? null;

        if (!is_array($value)) {
            return [];
        }

        $list = [];
        foreach ($value as $item) {
            if (is_string($item)) {
                $list[] = $item;
            }
        }

        return $list;
    }
}
