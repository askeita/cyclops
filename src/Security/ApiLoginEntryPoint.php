<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


/**
 * Class ApiLoginEntryPoint
 *
 * Handles unauthenticated access to API endpoints and redirects to login page for API docs.
 */
class ApiLoginEntryPoint implements AuthenticationEntryPointInterface
{
    private UrlGeneratorInterface $urlGenerator;


    /**
     * ApiLoginEntryPoint constructor.
     *
     * @param UrlGeneratorInterface $urlGenerator
     */
    public function __construct(UrlGeneratorInterface $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * Starts the authentication process.
     *
     * @param Request $request
     * @param AuthenticationException|null $authException
     * @return Response
     */
    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        // Rediriger vers /login quand on tente d'accéder à la doc API via navigateur
        $route = (string) $request->attributes->get('_route', '');
        $path = $request->getPathInfo();
        $accept = (string) $request->headers->get('Accept', '');

        if ($route === 'api_doc' || str_starts_with($path, '/api/docs') || str_contains($accept, 'text/html')) {
            return new RedirectResponse($this->urlGenerator->generate('app_login'));
        }

        // Pour les endpoints API, retourner une réponse JSON 401
        return new JsonResponse([
            'message' => 'Authentication required',
            'error' => 'Unauthorized'
        ], Response::HTTP_UNAUTHORIZED);
    }
}
