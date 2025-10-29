<?php

namespace App\Security;

use App\Repository\ApiKeyRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;


/**
 * Class ApiKeyAuthenticator
 */
class ApiKeyAuthenticator extends AbstractAuthenticator
{
    /**
     * ApiKeyAuthenticator constructor.
     *
     * @param ApiKeyRepository $apiKeyRepository
     * @param string $appSecret
     */
    public function __construct(
        private readonly ApiKeyRepository $apiKeyRepository,
        #[Autowire('%kernel.secret%')] private readonly string $appSecret
    ) {}

    /**
     * Determines if the authenticator supports the given request.
     *
     * @param Request $request
     * @return bool|null
     */
    public function supports(Request $request): ?bool
    {
        if ($request->headers->has('X-API-KEY')) {
            return true;
        }

        if (str_starts_with($request->getPathInfo(), '/api/docs') && $request->cookies->has('api_docs')) {
            return true;
        }

        return false;
    }

    /**
     * Authenticates the request and returns a Passport.
     *
     * @param Request $request
     * @return Passport
     * @throws CustomUserMessageAuthenticationException
     */
    public function authenticate(Request $request): Passport
    {
        $apiKey = $request->headers->get('X-API-KEY');

        if (!$apiKey && str_starts_with($request->getPathInfo(), '/api/docs')) {
            $cookie = $request->cookies->get('api_docs');
            if ($cookie && str_contains($cookie, '.')) {
                [$key, $sig] = explode('.', $cookie, 2);
                $expected = hash_hmac('sha256', $key, $this->appSecret);
                if (!hash_equals($expected, $sig)) {
                    throw new CustomUserMessageAuthenticationException('Invalid docs cookie.');
                }
                $apiKey = $key;
            }
        }

        if (null === $apiKey) {
            throw new CustomUserMessageAuthenticationException('No API key provided.');
        }

        return new SelfValidatingPassport(
            new UserBadge($apiKey, function (string $key) {
                $apiKeyEntity = $this->apiKeyRepository->findOneBy(['keyValue' => $key, 'isActive' => true]);
                if (!$apiKeyEntity) {
                    throw new CustomUserMessageAuthenticationException('Invalid API key.');
                }
                return new ApiKeyUser($apiKeyEntity);
            })
        );
    }

    /**
     * Handles successful authentication.
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
     * Handles authentication failure.
     *
     * @param Request $request
     * @param AuthenticationException $exception
     * @return Response|null
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'message' => 'Authentication failed',
            'error' => $exception->getMessageKey(),
        ], Response::HTTP_UNAUTHORIZED);
    }
}
