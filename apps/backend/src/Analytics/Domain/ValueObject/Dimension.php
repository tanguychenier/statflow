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

namespace App\Analytics\Domain\ValueObject;

use App\Analytics\Domain\Exception\UnknownDimension;

/**
 * A filterable / breakdownable event dimension (OpenAPI `breakdown.property`
 * and `Filter.property`).
 *
 * Each case maps to exactly one physical ClickHouse `events` column (or a small
 * derived expression). Restricting filters and breakdowns to this closed set is
 * what makes the query builder injection-safe: column identifiers are never
 * taken from user input, only operands are bound.
 */
enum Dimension: string
{
    case Pathname = 'pathname';
    case Hostname = 'hostname';
    case ReferrerDomain = 'referrer_domain';
    case ReferrerSource = 'referrer_source';
    case UtmSource = 'utm_source';
    case UtmMedium = 'utm_medium';
    case UtmCampaign = 'utm_campaign';
    case UtmTerm = 'utm_term';
    case UtmContent = 'utm_content';
    case Country = 'country';
    case Region = 'region';
    case City = 'city';
    case DeviceType = 'device_type';
    case Browser = 'browser';
    case BrowserVersion = 'browser_version';
    case Os = 'os';
    case OsVersion = 'os_version';
    case Language = 'language';
    case EntryPage = 'entry_page';
    case ExitPage = 'exit_page';
    case EventName = 'event_name';

    public static function fromString(string $value): self
    {
        return self::tryFrom($value) ?? throw UnknownDimension::forName($value);
    }

    /**
     * The physical `events` column this dimension reads from.
     *
     * `country` is exposed by its semantic name in the API but stored as
     * `country_code`; `referrer_domain` is derived from the `referrer` URL.
     * `entry_page` / `exit_page` are session-grained and only valid against the
     * `sessions` table — the query builder rejects them on event-grained queries.
     */
    public function eventColumn(): string
    {
        return match ($this) {
            self::Country => 'country_code',
            self::ReferrerDomain => 'domainWithoutWWW(referrer)',
            self::EntryPage, self::ExitPage => throw UnknownDimension::forName($this->value . ' (session-only)'),
            default => $this->value,
        };
    }

    /**
     * The physical `sessions` column this dimension reads from, where one exists.
     */
    public function sessionColumn(): string
    {
        return match ($this) {
            self::Country => 'country_code',
            self::ReferrerDomain => 'domainWithoutWWW(referrer)',
            self::EntryPage => 'entry_page',
            self::ExitPage => 'exit_page',
            default => $this->value,
        };
    }

    /**
     * Session-grained dimensions cannot be evaluated on the raw `events` stream.
     */
    public function isSessionScoped(): bool
    {
        return $this === self::EntryPage || $this === self::ExitPage;
    }
}
