<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;


/**
 * SessionAuthenticator
 */
class SessionAuthenticator extends AbstractAuthenticator
{
    private UserProvider $userProvider;


    /**
     * Constructor
     *
     * @param UserProvider $userProvider
     */
    public function __construct(UserProvider $userProvider)
    {
        $this->userProvider = $userProvider;
    }

    /**
     * Check if the request contains a session with 'user_email'
     *
     * @param Request $request
     * @return bool|null
     */
    public function supports(Request $request): ?bool
    {
        return $request->getSession()->has('user_email');
    }

    /**
     * Authenticate the user based on the email stored in the session
     *
     * @param Request $request
     * @return Passport
     * @throws AuthenticationException
     */
    public function authenticate(Request $request): Passport
    {
        $email = $request->getSession()->get('user_email');

        if (null === $email) {
            throw new AuthenticationException('No email found in session.');
        }

        return new SelfValidatingPassport(
            new UserBadge($email, function ($userIdentifier) use ($email) {
                return $this->userProvider->loadUserByIdentifier($email);
            })
        );
    }

    /**
     * Called when authentication is successful
     *
     * @param Request $request
     * @param TokenInterface $token
     * @param string $firewallName
     * @return Response|null
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    /**
     * Called when authentication fails
     *
     * @param Request $request
     * @param AuthenticationException $exception
     * @return Response|null
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(['error' => 'Authentication failed'], 401);
    }
}
