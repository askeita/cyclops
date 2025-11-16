<?php

namespace App\Controller;

use App\Entity\ApiKey;
use Doctrine\ORM\EntityManagerInterface;
use Random\RandomException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;


/**
 * DashboardController
 */
#[Route('/dashboard', name: 'dashboard_')]
#[IsGranted('ROLE_USER')]
class DashboardController extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * User dashboard route
     *
     * @param Request $request
     * @return Response
     */
    #[Route('/', name: 'home', methods: ['GET'])]
    public function dashboard(Request $request): Response
    {
        // Retrieves the user email from the session
        $email = $request->getSession()->get('user_email');
        if (!$email) {
            return $this->redirectToRoute('app_home');
        }

        // Hash the email to match the database storage
        $encryptionKey = $this->getParameter('app.encryption_key');
        $hashedEmail = hash('sha3-512', $email . $encryptionKey);

        $repo = $this->em->getRepository(ApiKey::class);
        /** @var ApiKey|null $user */
        $user = $repo->findOneBy(['email' => $hashedEmail, 'emailVerified' => true]);

        // Update user last connection time
        $updatedLastConnection = new \DateTime();
        if ($user) {
            $user->setLastConnection($updatedLastConnection);
            $this->em->flush();
        }

        return $this->render('dashboard/index.html.twig', [
            'userApiKey' => $user?->getKeyValue() ?? '',
            'lastConnection' => ($user?->getLastConnection() ?? $updatedLastConnection)->format('Y-m-d H:i:s'),
            'userEmail' => $email,
        ]);
    }

    /**
     * Generate a new API key for the logged-in user
     *
     * @param Request $request
     * @return Response
     * @throws RandomException
     */
    #[Route('/generate-api-key', name: 'generate_api_key', methods: ['POST'])]
    public function generateApiKey(Request $request): Response
    {
        $email = $request->getSession()->get('user_email');
        if (!$email) {
            return new Response('Unauthorized', Response::HTTP_UNAUTHORIZED);
        }

        // Hash the email to match the database storage
        $encryptionKey = $this->getParameter('app.encryption_key');
        $hashedEmail = hash('sha3-512', $email . $encryptionKey);

        $repo = $this->em->getRepository(ApiKey::class);
        /** @var ApiKey|null $user */
        $user = $repo->findOneBy(['email' => $hashedEmail]);

        if (!$user) {
            return new Response('User not found', Response::HTTP_NOT_FOUND);
        }

        if ($user->getKeyValue()) {
            return new Response('API key already exists', Response::HTTP_CONFLICT);
        }

        // Generate a new API key
        $apiKey = bin2hex(random_bytes(16));
        $user->setKeyValue($apiKey)->setIsActive(true);
        $this->em->flush();

        return new Response($apiKey, Response::HTTP_OK);
    }

    /**
     * User logout route
     *
     * @param Request $request
     * @return Response
     */
    #[Route('/logout', name: 'app_logout')]
    public function logout(Request $request): Response
    {
        // Clear user session
        $request->getSession()->invalidate();
        $this->addFlash('success', 'You have been successfully logged out');

        return $this->redirectToRoute('app_home');
    }
}
