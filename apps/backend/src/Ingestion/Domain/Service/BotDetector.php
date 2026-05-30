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

namespace App\Ingestion\Domain\Service;

use App\Ingestion\Domain\Model\RequestContext;

/**
 * Heuristic bot / spider / spam filter (roadmap 1.4).
 *
 * Filtering is intentionally conservative: it relies on stable, well-known
 * crawler User-Agent markers and obvious abuse signals (empty UA, headless
 * automation), not on fingerprinting. A site can disable it via
 * site_settings.bot_filtering_enabled — that toggle is applied by the caller.
 */
final class BotDetector
{
    /**
     * Substrings that reliably mark an automated agent. Lowercased before match.
     */
    private const BOT_MARKERS = [
        'bot', 'crawl', 'spider', 'slurp', 'mediapartners', 'adsbot',
        'bingpreview', 'facebookexternalhit', 'embedly', 'quora link preview',
        'pinterest', 'redditbot', 'whatsapp', 'telegrambot', 'discordbot',
        'headlesschrome', 'phantomjs', 'puppeteer', 'playwright', 'selenium',
        'python-requests', 'curl/', 'wget/', 'go-http-client', 'okhttp',
        'apache-httpclient', 'libwww-perl', 'scrapy', 'http_request',
        'google-read-aloud', 'googleother', 'gptbot', 'ccbot', 'claudebot',
        'lighthouse', 'pagespeed', 'pingdom', 'uptimerobot', 'statuscake',
    ];

    public function isBot(RequestContext $context): bool
    {
        $ua = trim($context->userAgent);

        // A real browser always sends a non-trivial User-Agent.
        if ($ua === '' || mb_strlen($ua) < 8) {
            return true;
        }

        $haystack = strtolower($ua);
        foreach (self::BOT_MARKERS as $marker) {
            if (str_contains($haystack, $marker)) {
                return true;
            }
        }

        return false;
    }
}
