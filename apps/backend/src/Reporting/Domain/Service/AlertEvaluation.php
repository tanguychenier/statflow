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

namespace App\Reporting\Domain\Service;

use App\Reporting\Domain\Model\Alert;

/**
 * Pure decision logic for whether an alert fires given the metric values read
 * from Analytics. Kept separate from the {@see Alert} aggregate so the (slightly
 * involved) percentage-change maths stays unit-testable in isolation and the
 * aggregate is not coupled to comparison-period arithmetic.
 */
final class AlertEvaluation
{
    /**
     * Decide whether $alert is breached.
     *
     * Absolute conditions test $current directly. Percentage-change conditions
     * test the relative move from $baseline to $current; when the baseline is
     * zero (or missing) a change percentage is undefined, so the alert does not
     * fire. A missing current value never fires.
     */
    public function isBreached(Alert $alert, ?float $current, ?float $baseline): bool
    {
        if ($current === null) {
            return false;
        }

        if (!$alert->alertCondition()->requiresComparisonPeriod()) {
            return $alert->isBreachedBy($current);
        }

        if ($baseline === null || $baseline === 0.0) {
            return false;
        }

        $changePct = (($current - $baseline) / abs($baseline)) * 100.0;

        return $alert->isBreachedBy($changePct);
    }
}
