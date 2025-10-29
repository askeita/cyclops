<?php

namespace App\Controller;

use PDO;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


/**
 * Class EmailController
 */
#[Route('/email-', name: 'email_')]
class EmailController extends AbstractController
{
    private MailerInterface $mailer;

    /**
     * EmailController constructor.
     *
     * @param MailerInterface $mailer
     */
    public function __construct(MailerInterface $mailer)
    {
        $this->mailer = $mailer;
    }

    /**
     * Check if an email already exists in the database
     *
     * @param PDO $pdo
     * @param string $hashedEmail
     * @return boolean
     */
    #[Route('check', name: 'check', methods: ['GET'])]
    public function checkEmailAvailable(PDO $pdo, string $hashedEmail): bool
    {
        $stmt = $pdo->prepare('SELECT COUNT(id) FROM api_keys WHERE email = :email');
        $stmt->execute(['email' => $hashedEmail]);
        if ($stmt->fetchColumn() > 0) {
            return false;
        }

        return true;
    }

    /**
     * Send verification email
     *
     * @param string $email email address
     * @param string $token verification token
     * @return void
     * @throws TransportExceptionInterface
     */
    #[Route('send-verification', name: 'send_verification')]
    public function sendVerificationEmail(string $email, string $token): void
    {
        $verificationUrl = $this->generateUrl('email_verify',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $emailMessage = (new Email())
            ->from('askeita.dev@gmail.com')
            ->to($email)
            ->subject('Please verify your email address')
            ->html($this->renderView('emails/verification.html.twig', [
                'verificationUrl' => $verificationUrl
            ]));
        $this->mailer->send($emailMessage);
    }

    /**
     * Email verification route
     *
     * @param Request $request
     * @return Response
     */
    #[Route('verify', name: 'verify', methods: ['GET'])]
    public function verifyEmail(Request $request): Response
    {
        $token = $request->query->get('token');
        if (!$token) {
            return new Response('Missing token', Response::HTTP_BAD_REQUEST);
        }

        // Use test database in test environment
        $dbFile = $this->getParameter('kernel.environment') === 'test' ? 'test.db' : 'data.db';
        $pdo = new PDO('sqlite:' . $this->getParameter('kernel.project_dir') . '/var/' . $dbFile);
        $stmt = $pdo->prepare('SELECT email FROM api_keys WHERE verification_token = :token');
        $stmt->execute(['token' => $token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return new Response('Invalid or expired verification link', Response::HTTP_BAD_REQUEST);
        }

        $stmt = $pdo->prepare('UPDATE api_keys SET email_verified = :email_verified,
                        verification_token = :verification_token, is_active = :is_active
                WHERE verification_token = :token');
        $stmt->execute([
            'email_verified' => 1,
            'verification_token' => NULL,
            'is_active' => 1,
            'token' => $token,
        ]);

        if ($stmt->rowCount() > 0) {
            return $this->redirectToRoute('app_login', ['emailVerified' => 'true']);
        }

        return new Response('Invalid or expired verification link', Response::HTTP_BAD_REQUEST);
    }

}
