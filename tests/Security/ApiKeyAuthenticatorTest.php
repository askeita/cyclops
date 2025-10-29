<?php

namespace App\Tests\Security;

use App\Entity\ApiKey;
use App\Repository\ApiKeyRepository;
use App\Security\ApiKeyAuthenticator;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;


/**
 * Class ApiKeyAuthenticatorTest
 *
 * Tests for the ApiKeyAuthenticator class.
 */
class ApiKeyAuthenticatorTest extends WebTestCase
{
    /** @var ApiKeyAuthenticator */
    private ApiKeyAuthenticator $authenticator;

    /** @var ApiKeyRepository&MockObject */
    private ApiKeyRepository $apiKeyRepository;


    /**
     * Set up the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->apiKeyRepository = $this->createMock(ApiKeyRepository::class);
        $this->authenticator = new ApiKeyAuthenticator($this->apiKeyRepository, 'test_secret');
    }

    /**
     * Test if the authenticator supports requests with API key in header.
     *
     * @return void
     */
    public function testSupportsWithApiKeyHeader(): void
    {
        $request = new Request();
        $request->headers->set('X-API-KEY', 'test-api-key');

        $this->assertTrue($this->authenticator->supports($request));
    }

    /**
     * Test if the authenticator does not support requests with API key in query parameters.
     *
     * @return void
     */
    public function testSupportsWithApiKeyQuery(): void
    {
        $request = new Request(['api_key' => 'test-api-key']);

        // The authenticator only supports header-based API keys
        $this->assertFalse($this->authenticator->supports($request));
    }

    /**
     * Test if the authenticator does not support requests without API key.
     *
     * @return void
     */
    public function testDoesNotSupportWithoutApiKey(): void
    {
        $request = new Request();

        $this->assertFalse($this->authenticator->supports($request));
    }

    /**
     * Test authentication with a valid API key in the header.
     *
     * @return void
     */
    public function testAuthenticateWithValidHeaderKey(): void
    {
        $request = new Request();
        $request->headers->set('X-API-KEY', 'valid-api-key');

        // Mock the ApiKey entity
        $apiKeyEntity = $this->createMock(ApiKey::class);

        // Mock repository to return active key
        $this->apiKeyRepository
            ->method('findOneBy')
            ->with(['keyValue' => 'valid-api-key', 'isActive' => true])
            ->willReturn($apiKeyEntity);

        $passport = $this->authenticator->authenticate($request);

        $this->assertInstanceOf(SelfValidatingPassport::class, $passport);
        $this->assertInstanceOf(UserBadge::class, $passport->getBadge(UserBadge::class));
    }

    /**
     * Test authentication with an invalid API key.
     *
     * @return void
     */
    public function testAuthenticateWithInvalidApiKey(): void
    {
        $request = new Request();
        $request->headers->set('X-API-KEY', 'invalid-api-key');

        // Mock repository to return null
        $this->apiKeyRepository
            ->method('findOneBy')
            ->with(['keyValue' => 'invalid-api-key', 'isActive' => true])
            ->willReturn(null);

        $passport = $this->authenticator->authenticate($request);
        $this->assertInstanceOf(SelfValidatingPassport::class, $passport);

        // Trigger user resolution to execute the loader and throw
        /** @var UserBadge $userBadge */
        $userBadge = $passport->getBadge(UserBadge::class);

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('Invalid API key.');
        $userBadge->getUser();
    }

    /**
     * Test authentication failure response.
     *
     * @return void
     */
    public function testOnAuthenticationFailureReturnsUnauthorized(): void
    {
        $request = new Request();
        $exception = new AuthenticationException('Invalid API key');

        $response = $this->authenticator->onAuthenticationFailure($request, $exception);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(401, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Authentication failed', $data['message']);
        $this->assertEquals('An authentication exception occurred.', $data['error']);
    }

    /**
     * Test authentication success response.
     *
     * @return void
     */
    public function testOnAuthenticationSuccessReturnsNull(): void
    {
        $request = new Request();
        $token = $this->createMock(TokenInterface::class);

        $result = $this->authenticator->onAuthenticationSuccess($request, $token, 'main');

        $this->assertNull($result);
    }
}
