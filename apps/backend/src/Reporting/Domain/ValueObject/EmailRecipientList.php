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

use App\Reporting\Domain\Exception\InvalidEmailException;

/**
 * A non-empty, de-duplicated list of recipient addresses for a scheduled report.
 *
 * Bounded to the OpenAPI `recipients` constraint (1..50 items). Duplicates are
 * collapsed (case-insensitively, via {@see EmailAddress} normalisation) so the
 * same inbox is never mailed twice for one run.
 */
final readonly class EmailRecipientList
{
    public const MIN_ITEMS = 1;

    public const MAX_ITEMS = 50;

    /**
     * @param list<EmailAddress> $recipients
     */
    private function __construct(
        private array $recipients,
    ) {
    }

    /**
     * @param list<string> $values
     */
    public static function fromStrings(array $values): self
    {
        /** @var array<string, EmailAddress> $unique keyed by normalised value */
        $unique = [];

        foreach ($values as $value) {
            $email = EmailAddress::fromString($value);
            $unique[$email->value()] = $email;
        }

        $recipients = array_values($unique);

        if (count($recipients) < self::MIN_ITEMS) {
            throw InvalidEmailException::recipientsRequired();
        }

        if (count($recipients) > self::MAX_ITEMS) {
            throw InvalidEmailException::tooManyRecipients(self::MAX_ITEMS);
        }

        return new self($recipients);
    }

    /**
     * @return list<EmailAddress>
     */
    public function all(): array
    {
        return $this->recipients;
    }

    /**
     * @return list<string>
     */
    public function toStrings(): array
    {
        return array_map(static fn (EmailAddress $e): string => $e->value(), $this->recipients);
    }

    public function count(): int
    {
        return count($this->recipients);
    }
}
