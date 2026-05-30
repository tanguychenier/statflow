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
 * Where an alert delivers its notification. Mirrors the OpenAPI
 * `NotificationChannel` schema: an `email` channel carries a recipient address,
 * while `webhook` and `slack` channels carry a target URL.
 *
 * URLs are constrained to http(s) so a stored channel can never be coerced into
 * an SSRF vector against a non-HTTP scheme; reachability is the sender's concern.
 */
final readonly class NotificationChannel
{
    public const TYPE_EMAIL = 'email';

    public const TYPE_WEBHOOK = 'webhook';

    public const TYPE_SLACK = 'slack';

    private const URL_TYPES = [self::TYPE_WEBHOOK, self::TYPE_SLACK];

    private function __construct(
        private string $type,
        private ?EmailAddress $email,
        private ?string $webhookUrl,
    ) {
    }

    public static function forEmail(string $address): self
    {
        return new self(self::TYPE_EMAIL, EmailAddress::fromString($address), null);
    }

    public static function forWebhook(string $url): self
    {
        return new self(self::TYPE_WEBHOOK, null, self::validateUrl($url));
    }

    public static function forSlack(string $url): self
    {
        return new self(self::TYPE_SLACK, null, self::validateUrl($url));
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $type = $data['type'] ?? null;
        if (!is_string($type)) {
            throw InvalidAlertException::channelTypeRequired();
        }

        return match ($type) {
            self::TYPE_EMAIL => self::forEmail(self::requireString($data, 'email')),
            self::TYPE_WEBHOOK => self::forWebhook(self::requireString($data, 'webhook_url')),
            self::TYPE_SLACK => self::forSlack(self::requireString($data, 'webhook_url')),
            default => throw InvalidAlertException::unknownChannelType($type),
        };
    }

    public function type(): string
    {
        return $this->type;
    }

    public function email(): ?EmailAddress
    {
        return $this->email;
    }

    public function webhookUrl(): ?string
    {
        return $this->webhookUrl;
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        if ($this->type === self::TYPE_EMAIL) {
            return [
                'type' => $this->type,
                'email' => (string) $this->email,
            ];
        }

        return [
            'type' => $this->type,
            'webhook_url' => (string) $this->webhookUrl,
        ];
    }

    /**
     * @internal Exposes which channel types require a URL, for tests and callers.
     *
     * @return list<string>
     */
    public static function urlTypes(): array
    {
        return self::URL_TYPES;
    }

    private static function validateUrl(string $url): string
    {
        $trimmed = trim($url);

        if (filter_var($trimmed, FILTER_VALIDATE_URL) === false) {
            throw InvalidAlertException::malformedWebhookUrl($trimmed);
        }

        $scheme = strtolower((string) parse_url($trimmed, PHP_URL_SCHEME));
        if ($scheme !== 'http' && $scheme !== 'https') {
            throw InvalidAlertException::malformedWebhookUrl($trimmed);
        }

        return $trimmed;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function requireString(array $data, string $field): string
    {
        $value = $data[$field] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw InvalidAlertException::channelFieldRequired($field);
        }

        return $value;
    }
}
