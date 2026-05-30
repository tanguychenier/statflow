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
 * Raised when an alert definition is inconsistent: unknown metric/condition,
 * out-of-range threshold, a percentage-change condition without a comparison
 * period, or a malformed notification channel (error-catalog
 * `validation-failed`, HTTP 422).
 */
final class InvalidAlertException extends ReportingDomainException
{
    public static function unknownMetric(string $value): self
    {
        return new self(sprintf('Unsupported alert metric "%s".', $value));
    }

    public static function unknownCondition(string $value): self
    {
        return new self(sprintf('Unsupported alert condition "%s".', $value));
    }

    public static function unknownComparisonPeriod(string $value): self
    {
        return new self(sprintf('Unsupported comparison period "%s".', $value));
    }

    public static function comparisonPeriodRequired(): self
    {
        return new self('A comparison period is required for percentage-change conditions.');
    }

    public static function comparisonPeriodNotAllowed(): self
    {
        return new self('A comparison period is only valid for percentage-change conditions.');
    }

    public static function nonFiniteThreshold(): self
    {
        return new self('Alert threshold must be a finite number.');
    }

    public static function thresholdOutOfRange(): self
    {
        return new self('Alert threshold is out of the supported range.');
    }

    public static function channelsRequired(): self
    {
        return new self('At least one notification channel is required.');
    }

    public static function tooManyChannels(int $max): self
    {
        return new self(sprintf('An alert may have at most %d notification channels.', $max));
    }

    public static function channelTypeRequired(): self
    {
        return new self('Notification channel type is required.');
    }

    public static function unknownChannelType(string $value): self
    {
        return new self(sprintf('Unsupported notification channel type "%s".', $value));
    }

    public static function channelFieldRequired(string $field): self
    {
        return new self(sprintf('Notification channel field "%s" is required.', $field));
    }

    public static function malformedWebhookUrl(string $value): self
    {
        return new self(sprintf('"%s" is not a valid http(s) URL.', $value));
    }
}
