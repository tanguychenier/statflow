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

namespace App\Identity\Domain\ValueObject;

use App\Identity\Domain\Exception\InvalidApiKeyScopeException;

/**
 * The fixed resource:action scope vocabulary for programmatic API keys
 * (ADR-0009 §4). Matches the api_keys_scopes_valid CHECK constraint in
 * postgres-schema.sql and the ApiKeyScope enum in openapi.yaml.
 */
enum ApiKeyScope: string
{
    case AnalyticsRead = 'analytics:read';
    case SitesRead = 'sites:read';
    case SitesWrite = 'sites:write';
    case ReportsRead = 'reports:read';
    case ReportsWrite = 'reports:write';

    public static function fromString(string $value): self
    {
        return self::tryFrom($value)
            ?? throw InvalidApiKeyScopeException::forValue($value, self::values());
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $scope): string => $scope->value, self::cases());
    }
}
