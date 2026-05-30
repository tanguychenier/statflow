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

namespace App\Tests\Integration\Identity\Infrastructure\Http;

use App\Identity\Infrastructure\Http\RefreshCookieFactory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * End-to-end HTTP test for the session auth flow (register, login, /users/me,
 * refresh, logout). Runs in the container phase against real PostgreSQL/Redis
 * with the ES256 signing key configured. Asserts the documented status codes,
 * the TokenResponse body, and the HttpOnly sf_rt refresh cookie.
 */
#[CoversNothing]
final class AuthFlowTest extends WebTestCase
{
    private const PASSWORD = 'correcthorsebattery';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        // Drive the flow over HTTPS so the Secure-flagged refresh cookie (sf_rt)
        // is stored and replayed by the BrowserKit cookie jar.
        $this->client = self::createClient([], [
            'HTTPS' => true,
        ]);
        // The flow chains register -> login -> me -> refresh -> logout across
        // several requests; keep one kernel so the refresh cookie and the Redis
        // refresh-token session survive between them.
        $this->client->disableReboot();
    }

    #[Test]
    public function registerLoginMeRefreshLogout(): void
    {
        $email = 'flow-' . bin2hex(random_bytes(4)) . '@example.com';

        $this->json('POST', '/api/v1/auth/register', [
            'email' => $email,
            'password' => self::PASSWORD,
            'name' => 'Flow',
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $registered = $this->decode();
        self::assertSame($email, $registered['email']);
        self::assertFalse($registered['email_verified']);

        $this->json('POST', '/api/v1/auth/login', [
            'email' => $email,
            'password' => self::PASSWORD,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $login = $this->decode();
        self::assertArrayHasKey('access_token', $login);
        self::assertSame('Bearer', $login['token_type']);
        self::assertSame(900, $login['expires_in']);
        // The sf_rt cookie is path-scoped to the auth endpoints, so it must be
        // looked up under that path rather than the cookie jar's default '/'.
        self::assertNotNull($this->client->getCookieJar()->get(RefreshCookieFactory::COOKIE_NAME, '/api/v1/auth'));

        self::assertIsString($login['access_token']);
        $token = $login['access_token'];
        $this->client->request('GET', '/api/v1/users/me', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame($email, $this->decode()['email']);

        $this->client->request('POST', '/api/v1/auth/refresh');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertArrayHasKey('access_token', $this->decode());

        $this->client->request('POST', '/api/v1/auth/logout', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    #[Test]
    public function loginWithWrongPasswordReturns401(): void
    {
        $email = 'wrong-' . bin2hex(random_bytes(4)) . '@example.com';
        $this->json('POST', '/api/v1/auth/register', [
            'email' => $email,
            'password' => self::PASSWORD,
            'name' => 'X',
        ]);

        $this->json('POST', '/api/v1/auth/login', [
            'email' => $email,
            'password' => 'totally-wrong-1',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertSame('https://statflow.io/errors/invalid-credentials', $this->decode()['type']);
    }

    #[Test]
    public function registeringADuplicateEmailReturns409(): void
    {
        $email = 'dup-' . bin2hex(random_bytes(4)) . '@example.com';
        $this->json('POST', '/api/v1/auth/register', [
            'email' => $email,
            'password' => self::PASSWORD,
            'name' => 'X',
        ]);

        $this->json('POST', '/api/v1/auth/register', [
            'email' => $email,
            'password' => self::PASSWORD,
            'name' => 'Y',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }

    #[Test]
    public function registeringWithAShortPasswordReturns422(): void
    {
        $this->json('POST', '/api/v1/auth/register', [
            'email' => 'short@example.com',
            'password' => 'short',
            'name' => 'X',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    #[Test]
    public function forgotPasswordAlwaysReturns202(): void
    {
        $this->json('POST', '/api/v1/auth/forgot-password', [
            'email' => 'nobody-here@example.com',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);
    }

    #[Test]
    public function meRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/v1/users/me');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    #[Test]
    public function jwksIsPubliclyReadable(): void
    {
        $this->client->request('GET', '/api/v1/auth/.well-known/jwks.json');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertArrayHasKey('keys', $this->decode());
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function json(string $method, string $uri, array $payload): void
    {
        $this->client->request(
            $method,
            $uri,
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(): array
    {
        $content = (string) $this->client->getResponse()->getContent();
        $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
