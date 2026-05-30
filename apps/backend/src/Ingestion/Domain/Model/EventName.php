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

namespace App\Ingestion\Domain\Model;

/**
 * The closed event-name vocabulary (event-contract.md §4).
 *
 * This is the single source of truth in the Ingestion domain for which event
 * names are accepted. `conversion` is derived server-side by the analytics
 * layer and is therefore not in the set the tracker is allowed to submit.
 */
enum EventName: string
{
    case Pageview = 'pageview';
    case RouteChange = 'route_change';
    case Engagement = 'engagement';
    case Click = 'click';
    case RageClick = 'rage_click';
    case DeadClick = 'dead_click';
    case ScrollDepth = 'scroll_depth';
    case FormFocus = 'form_focus';
    case FormSubmit = 'form_submit';
    case FormAbandon = 'form_abandon';
    case ElementVisibility = 'element_visibility';
    case Custom = 'custom';
    case WebVitalLcp = 'web_vital_lcp';
    case WebVitalCls = 'web_vital_cls';
    case WebVitalInp = 'web_vital_inp';
    case JsError = 'js_error';
    case HeatmapBatch = 'heatmap_batch';

    /**
     * `conversion` is the one vocabulary member the tracker must never submit:
     * it is derived server-side from goal definitions (event-contract.md §8).
     */
    public const SERVER_DERIVED = 'conversion';

    public static function isAccepted(string $name): bool
    {
        return self::tryFrom($name) !== null;
    }

    /**
     * Behavioral signals are only meaningful on these names (event-contract.md §5).
     */
    public function carriesBehavioralSignals(): bool
    {
        return match ($this) {
            self::Click, self::RageClick, self::DeadClick, self::ScrollDepth, self::Engagement => true,
            default => false,
        };
    }
}
