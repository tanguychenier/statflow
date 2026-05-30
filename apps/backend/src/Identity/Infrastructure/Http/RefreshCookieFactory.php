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

namespace App\Identity\Infrastructure\Http;

use App\Identity\Domain\ValueObject\RefreshToken;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

/**
 * Builds and reads the rotating refresh-token cookie (`sf_rt`). The cookie is
 * HttpOnly, Secure, SameSite=Strict and path-scoped to the auth endpoints, as
 * required by api/README.md §2.1 — it is never exposed to JavaScript and only
 * travels to /api/v1/auth.
 */
final class RefreshCookieFactory
{
    public const COOKIE_NAME = 'sf_rt';

    private const COOKIE_PATH = '/api/v1/auth';

    public function create(RefreshToken $token): Cookie
    {
        return Cookie::create(self::COOKIE_NAME)
            ->withValue($token->value)
            ->withExpires(time() + $token->ttlSeconds)
            ->withPath(self::COOKIE_PATH)
            ->withSecure(true)
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_STRICT);
    }

    public function expire(): Cookie
    {
        return Cookie::create(self::COOKIE_NAME)
            ->withValue('')
            ->withExpires(1)
            ->withPath(self::COOKIE_PATH)
            ->withSecure(true)
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_STRICT);
    }

    public function read(Request $request): ?string
    {
        $value = $request->cookies->get(self::COOKIE_NAME);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
