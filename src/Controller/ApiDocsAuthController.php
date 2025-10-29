<?php

namespace App\Controller;

use App\Repository\ApiKeyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


/**
 * Class ApiDocsAuthController
 */
class ApiDocsAuthController extends AbstractController
{
    private ApiKeyRepository $apiKeyRepository;


    /**
     * ApiDocsAuthController constructor.
     *
     * @param ApiKeyRepository $apiKeyRepository
     * @param string $appSecret
     */
    public function __construct(
        ApiKeyRepository $apiKeyRepository,
        #[Autowire('%kernel.secret%')] private readonly string $appSecret
    ) {
        $this->apiKeyRepository = $apiKeyRepository;
    }

    /**
     * Issue a cookie for API docs authentication
     *
     * @param Request $request
     * @return Response
     */
    #[Route('/api/docs/auth', name: 'api_docs_auth', methods: ['POST'])]
    public function issueCookie(Request $request): Response
    {
        $apiKey = $request->headers->get('X-API-KEY');
        if (!$apiKey) {
            return new Response('Missing X-API-KEY', 400);
        }

        $apiKeyEntity = $this->apiKeyRepository->findOneBy(['keyValue' => $apiKey, 'isActive' => true]);
        if (!$apiKeyEntity) {
            return new Response('Invalid API key', 401);
        }

        $signature = hash_hmac('sha256', $apiKey, $this->appSecret);
        $value = $apiKey . '.' . $signature;

        $cookie = Cookie::create('api_docs')
            ->withValue($value)
            ->withPath('/api/docs')
            ->withHttpOnly(true)
            ->withSecure($request->isSecure())
            ->withSameSite(Cookie::SAMESITE_LAX)
            ->withExpires(strtotime('+1 hour'));

        $response = new Response('', 204);
        $response->headers->setCookie($cookie);

        return $response;
    }
}
