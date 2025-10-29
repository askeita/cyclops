<?php

namespace App\Controller;

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

        // Use test database in test environment
        $dbFile = $this->getParameter('kernel.environment') === 'test' ? 'test.db' : 'data.db';
        $pdo = new \PDO('sqlite:' . $this->getParameter('kernel.project_dir'). '/var/' . $dbFile);
        $stmt = $pdo->prepare('SELECT id, key_value, last_connection FROM api_keys
                                      WHERE email = :email AND email_verified = :email_verified');
        $stmt->execute(['email' => $hashedEmail, 'email_verified' => 1]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Update user last connection time
        $updatedLastConnection = (new \DateTime())->format('Y-m-d H:i:s');
        if ($user) {
            $stmt = $pdo->prepare("UPDATE api_keys SET last_connection = :last_connection WHERE id = :id");
            $stmt->execute(['last_connection' => $updatedLastConnection, 'id' => $user['id']]);
        }

        return $this->render('dashboard/index.html.twig', [
            'userApiKey' => $user['key_value'] ?? '',
            'lastConnection' => $user['last_connection'] ?? (new \DateTime())->format('Y-m-d H:i:s'),
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

        // Use test database in test environment
        $dbFile = $this->getParameter('kernel.environment') === 'test' ? 'test.db' : 'data.db';
        $pdo = new \PDO('sqlite:' . $this->getParameter('kernel.project_dir') . '/var/' . $dbFile);

        // Hash the email to match the database storage
        $encryptionKey = $this->getParameter('app.encryption_key');
        $hashedEmail = hash('sha3-512', $email . $encryptionKey);

        // Check if an API key already exists for this email
        $stmt = $pdo->prepare('SELECT key_value FROM api_keys WHERE email = :email');
        $stmt->execute(['email' => $hashedEmail]);
        $existingKey = $stmt->fetchColumn();

        if ($existingKey) {
            return new Response('API key already exists', Response::HTTP_CONFLICT);
        }

        // Generate a new API key
        $apiKey = bin2hex(random_bytes(16));

        // Store the new API key in the database
        $stmt = $pdo->prepare('UPDATE api_keys SET key_value = :key_value, is_active = :is_active
                WHERE email = :email');
        $result = $stmt->execute([
            'key_value' => $apiKey,
            'email' => $hashedEmail,
            'is_active' => 1,
        ]);

        if ($result && $stmt->rowCount() > 0) {
            return new Response($apiKey, Response::HTTP_OK);
        }

        return new Response('Failed to generate API key', Response::HTTP_INTERNAL_SERVER_ERROR);
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
