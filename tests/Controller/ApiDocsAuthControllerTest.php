<?php

namespace App\Tests\Controller;

use App\Entity\ApiKey;
use App\Kernel;
use App\Repository\ApiKeyRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Cookie;


/**
 * Class ApiDocsAuthControllerTest
 *
 * Tests for the API Docs Authentication Controller
 */
class ApiDocsAuthControllerTest extends WebTestCase
{
    /**
     * Set up the test environment
     *
     * @return void
     */
    protected function setUp(): void
    {
        // Ensure test kernel/client is created fresh for each test
        static::ensureKernelShutdown();
    }

    /**
     * Define test env vars to ensure DATABASE_URL is available before kernel boot
     *
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        $testDbDir = sys_get_temp_dir() . '/cyclops_test_repo';
        if (!is_dir($testDbDir)) {
            @mkdir($testDbDir, 0755, true);
        }
        $dbPath = $testDbDir . '/test.db';

        $env = [
            'APP_ENV' => 'test',
            'APP_SECRET' => 's$cretf0rt3st',
            'DATABASE_URL' => 'sqlite:///' . $dbPath,
            'MAILER_DSN' => 'null://null',
        ];

        foreach ($env as $k => $v) {
            $_ENV[$k] = $v;
            $_SERVER[$k] = $v;
            putenv($k . '=' . $v);
        }
    }

    /**
     * Test that requesting the cookie without the API key header returns 401
     *
     * @return void
     */
    public function testIssueCookieReturns401WhenHeaderMissing(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/docs/auth');

        $this->assertSame(401, $client->getResponse()->getStatusCode());

        $json = json_decode((string) $client->getResponse()->getContent(), true);
        if (null !== $json) {
            $this->assertArrayHasKey('message', $json);
            $this->assertSame('Authentication required', $json['message']);
        }

        // No cookie must be set
        $cookies = $client->getResponse()->headers->getCookies();
        $this->assertEmpty(array_filter($cookies, fn ($c) => $c->getName() === 'api_docs'));
    }

    /**
     * Test that requesting the cookie with an invalid API key returns 401
     *
     * @return void
     */
    public function testIssueCookieReturns401WithInvalidKey(): void
    {
        $client = static::createClient();

        // Mock repository to return null (invalid API key)
        $mockRepo = $this->createMock(ApiKeyRepository::class);
        $mockRepo->method('findOneBy')->willReturn(null);
        $client->getContainer()->set(ApiKeyRepository::class, $mockRepo);

        $client->request('POST', '/api/docs/auth', [], [], [
            'HTTP_X-API-KEY' => 'invalid_key',
            'HTTPS' => 'on',
        ]);

        $this->assertSame(401, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString('Invalid API key', (string) $client->getResponse()->getContent());

        // No cookie must be set
        $cookies = $client->getResponse()->headers->getCookies();
        $this->assertEmpty(array_filter($cookies, fn ($c) => $c->getName() === 'api_docs'));
    }

    /**
     * Test that requesting the cookie with a valid API key sets the signed cookie
     *
     * @return void
     */
    public function testIssueCookieSetsSignedCookieForValidKey(): void
    {
        $client = static::createClient();

        // Prepare a valid ApiKey entity
        $keyValue = 'valid_key_123';
        $apiKey = (new ApiKey())
            ->setKeyValue($keyValue)
            ->setIsActive(true)
            ->setEmail('tester@example.com');

        // Mock repository to return the valid entity
        $mockRepo = $this->createMock(ApiKeyRepository::class);
        $mockRepo->method('findOneBy')->willReturn($apiKey);
        $client->getContainer()->set(ApiKeyRepository::class, $mockRepo);

        // Perform secure request so cookie should be "secure"
        $client->request('POST', '/api/docs/auth', [], [], [
            'HTTP_X-API-KEY' => $keyValue,
            'HTTPS' => 'on',
        ]);

        $response = $client->getResponse();
        $this->assertSame(204, $response->getStatusCode(), (string) $response->getContent());

        // Extract the cookie from the response
        $cookies = array_values(array_filter(
            $response->headers->getCookies(),
            fn ($c) => $c->getName() === 'api_docs'
        ));

        $this->assertCount(1, $cookies, 'The api_docs cookie should be set');
        /** @var Cookie $cookie */
        $cookie = $cookies[0];

        // Check cookie attributes
        $this->assertSame('/api/docs', $cookie->getPath());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertTrue($cookie->isSecure());
        $this->assertSame(Cookie::SAMESITE_LAX, $cookie->getSameSite());
        $this->assertGreaterThan(time(), $cookie->getExpiresTime());
        $this->assertLessThanOrEqual(time() + 3700, $cookie->getExpiresTime());

        // Validate signature value
        /** @var string $secret */
        $secret = self::getContainer()->getParameter('kernel.secret');
        $expectedSignature = hash_hmac('sha256', $keyValue, $secret);
        $this->assertSame($keyValue . '.' . $expectedSignature, $cookie->getValue());
    }

    /**
     * Get the kernel class for the test environment.
     *
     * @return string
     */
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }
}
