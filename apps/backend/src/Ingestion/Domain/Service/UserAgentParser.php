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

use App\Ingestion\Domain\Model\DeviceInfo;

/**
 * Lightweight, dependency-free User-Agent parser producing the device/browser/OS
 * fields stored in ClickHouse (clickhouse-schema.sql §1). It runs in the batch
 * writer, not on the hot path, and resolves the common desktop/mobile/tablet
 * families without an external database.
 *
 * The goal is the analytics taxonomy (a handful of LowCardinality buckets), not
 * forensic accuracy. Unknown agents resolve to a 'desktop' device with empty
 * browser/OS rather than throwing.
 */
final class UserAgentParser
{
    public function parse(string $userAgent): DeviceInfo
    {
        $ua = trim($userAgent);
        if ($ua === '') {
            return DeviceInfo::unknown();
        }

        $deviceType = $this->detectDeviceType($ua);
        [$browser, $browserVersion] = $this->detectBrowser($ua);
        [$os, $osVersion] = $this->detectOs($ua);

        return new DeviceInfo($deviceType, $browser, $browserVersion, $os, $osVersion);
    }

    private function detectDeviceType(string $ua): string
    {
        $lower = strtolower($ua);

        if (str_contains($lower, 'ipad') || (str_contains($lower, 'tablet') && !str_contains($lower, 'mobile'))) {
            return 'tablet';
        }

        if (str_contains($lower, 'android') && !str_contains($lower, 'mobile')) {
            return 'tablet';
        }

        if (str_contains($lower, 'mobi') || str_contains($lower, 'iphone') || str_contains($lower, 'ipod')) {
            return 'mobile';
        }

        return 'desktop';
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function detectBrowser(string $ua): array
    {
        // Order matters: Edge/Opera/Chrome all embed "Chrome"/"Safari" tokens.
        $candidates = [
            'Edge' => '/Edg(?:e|A|iOS)?\/([0-9.]+)/',
            'Opera' => '/(?:OPR|Opera)\/([0-9.]+)/',
            'Samsung Internet' => '/SamsungBrowser\/([0-9.]+)/',
            'Firefox' => '/Firefox\/([0-9.]+)/',
            'Chrome' => '/(?:Chrome|CriOS)\/([0-9.]+)/',
            'Safari' => '/Version\/([0-9.]+).*Safari/',
            'Internet Explorer' => '/(?:MSIE |rv:)([0-9.]+).*Trident/',
        ];

        foreach ($candidates as $name => $pattern) {
            if (preg_match($pattern, $ua, $matches) === 1) {
                return [$name, $this->majorVersion($matches[1])];
            }
        }

        return ['', ''];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function detectOs(string $ua): array
    {
        if (preg_match('/Windows NT ([0-9.]+)/', $ua, $m) === 1) {
            return ['Windows', $this->windowsName($m[1])];
        }

        if (preg_match('/(?:iPhone|iPad|iPod).*?OS ([0-9_]+)/', $ua, $m) === 1) {
            return ['iOS', $this->majorVersion(str_replace('_', '.', $m[1]))];
        }

        if (preg_match('/Mac OS X ([0-9_.]+)/', $ua, $m) === 1) {
            return ['macOS', $this->majorVersion(str_replace('_', '.', $m[1]))];
        }

        if (preg_match('/Android ([0-9.]+)/', $ua, $m) === 1) {
            return ['Android', $this->majorVersion($m[1])];
        }

        if (str_contains($ua, 'Linux')) {
            return ['Linux', ''];
        }

        if (str_contains($ua, 'CrOS')) {
            return ['Chrome OS', ''];
        }

        return ['', ''];
    }

    private function majorVersion(string $version): string
    {
        $parts = explode('.', $version);

        return $parts[0];
    }

    private function windowsName(string $ntVersion): string
    {
        return match ($ntVersion) {
            '10.0' => '10',
            '6.3' => '8.1',
            '6.2' => '8',
            '6.1' => '7',
            default => $ntVersion,
        };
    }
}
