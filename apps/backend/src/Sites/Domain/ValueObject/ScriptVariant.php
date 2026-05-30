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

namespace App\Sites\Domain\ValueObject;

use App\Sites\Domain\Exception\InvalidScriptVariantException;

/**
 * The JavaScript snippet build served for a site.
 *
 *  - default: standard snippet
 *  - compat:  wider browser-compatibility build
 *  - manual:  manual page() calls only (no auto pageview)
 *  - entropy: adds the short-lived browser-entropy CGNAT mitigation
 *
 * Matches the `site_settings.script_variant` CHECK constraint.
 */
enum ScriptVariant: string
{
    case Default = 'default';
    case Compat = 'compat';
    case Manual = 'manual';
    case Entropy = 'entropy';

    public static function fromString(string $value): self
    {
        return self::tryFrom($value) ?? throw InvalidScriptVariantException::unknown($value);
    }
}
