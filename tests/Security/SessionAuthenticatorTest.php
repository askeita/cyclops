<?php

namespace App\Tests\Security;

use App\Security\SessionAuthenticator;
use App\Security\UserProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;


/**
 * Class SessionAuthenticatorTest
 *
 * Tests for the SessionAuthenticator class.
 */
class SessionAuthenticatorTest extends WebTestCase
{
    private SessionAuthenticator $authenticator;


    /*
     * Sets up the test environment before each test method.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $userProvider = $this->createMock(UserProvider::class);
        $this->authenticator = new SessionAuthenticator($userProvider);
    }

    /**
     * Tests that the authenticator supports requests with a valid session.
     *
     * @return void
     */
    public function testSupportsWithValidSession(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $session->method('has')->with('user_email')->willReturn(true);
        $session->method('get')->with('user_email')->willReturn('test@example.com');

        $request = new Request();
        $request->setSession($session);

        $this->assertTrue($this->authenticator->supports($request));
    }

    /**
     * Tests that the authenticator does not support requests without a session.
     *
     * @return void
     */
    public function testDoesNotSupportWithoutSession(): void
    {
        $request = new Request();

        // the SessionAuthenticator expects a session to be present
        $this->expectException(SessionNotFoundException::class);
        $this->authenticator->supports($request);
    }

    /**
     * Tests that the authenticator does not support requests with an empty session.
     *
     * @return void
     */
    public function testDoesNotSupportWithEmptySession(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $session->method('has')->with('user_email')->willReturn(false);

        $request = new Request();
        $request->setSession($session);

        $this->assertFalse($this->authenticator->supports($request));
    }

    /**
     * Tests that the authenticator successfully authenticates with a valid session.
     *
     * @return void
     */
    public function testAuthenticateWithValidSession(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->with('user_email')->willReturn('test@example.com');

        $request = new Request();
        $request->setSession($session);

        $passport = $this->authenticator->authenticate($request);

        $this->assertInstanceOf(SelfValidatingPassport::class, $passport);
        $this->assertInstanceOf(UserBadge::class, $passport->getBadge(UserBadge::class));
    }

    /**
     * Tests that onAuthenticationFailure returns a JSON error response.
     *
     * @return void
     */
    public function testOnAuthenticationFailureReturnsJsonError(): void
    {
        $request = new Request();
        $exception = new AuthenticationException('Session invalid');

        $response = $this->authenticator->onAuthenticationFailure($request, $exception);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertInstanceOf(JsonResponse::class, $response);

        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('Authentication failed', $responseData['error']);
    }
}
