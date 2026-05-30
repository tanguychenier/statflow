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

namespace App\Sites\Application\Command;

/**
 * Full replacement of a site's settings (OpenAPI PUT SiteSettings).
 *
 * PUT semantics: any omitted field falls back to its documented default rather
 * than retaining the previous value. The handler is responsible for applying
 * those defaults, so the carrier holds already-resolved primitive values.
 *
 * @phpstan-type RawSettings array{
 *     allowed_domains?: list<string>,
 *     excluded_ips?: list<string>,
 *     data_retention_days?: int|null,
 *     strip_query_params?: bool,
 *     custom_domain_enabled?: bool,
 *     tracker_config?: array<string, mixed>
 * }
 */
final readonly class ReplaceSiteSettingsCommand
{
    /**
     * @param array<string, mixed> $settings the raw, validated PUT body
     */
    public function __construct(
        public string $actingUserId,
        public string $siteId,
        public array $settings,
    ) {
    }
}
