<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;


/**
 * LoginEntryPoint
 */
class LoginEntryPoint implements AuthenticationEntryPointInterface
{
    private UrlGeneratorInterface $urlGenerator;

    /**
     * Constructor
     *
     * @param UrlGeneratorInterface $urlGenerator
     */
    public function __construct(UrlGeneratorInterface $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * Start the authentication process
     *
     * @param Request $request
     * @param AuthenticationException|null $authException
     * @return Response
     */
    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        // For AJAX or API requests, return a 401 Unauthorized response
        if ($request->isXmlHttpRequest() || $request->getContentTypeFormat() === 'json') {
            return new Response('Authentication required', Response::HTTP_UNAUTHORIZED);
        }

        // For regular requests, redirect to the login page
        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }
}
