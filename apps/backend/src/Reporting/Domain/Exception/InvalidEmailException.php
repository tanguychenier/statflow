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

namespace App\Reporting\Domain\Exception;

/**
 * Raised when a recipient email address is malformed, the recipient list is
 * empty, or it exceeds its item bound (error-catalog `validation-failed`,
 * HTTP 422).
 */
final class InvalidEmailException extends ReportingDomainException
{
    public static function empty(): self
    {
        return new self('Email address must not be empty.');
    }

    public static function malformed(string $value): self
    {
        return new self(sprintf('"%s" is not a valid email address.', $value));
    }

    public static function recipientsRequired(): self
    {
        return new self('At least one recipient is required.');
    }

    public static function tooManyRecipients(int $max): self
    {
        return new self(sprintf('A scheduled report may have at most %d recipients.', $max));
    }
}
