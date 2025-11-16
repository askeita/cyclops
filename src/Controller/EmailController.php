<?php

namespace App\Controller;

use App\Entity\ApiKey;
use Doctrine\ORM\EntityManagerInterface;
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
    private EntityManagerInterface $em;

    /**
     * EmailController constructor.
     *
     * @param MailerInterface $mailer
     * @param EntityManagerInterface $em
     */
    public function __construct(MailerInterface $mailer, EntityManagerInterface $em)
    {
        $this->mailer = $mailer;
        $this->em = $em;
    }

    /**
     * Check if an email already exists in the database
     *
     * @param string $hashedEmail
     * @return boolean
     */
    #[Route('check', name: 'check', methods: ['GET'])]
    public function checkEmailAvailable(string $hashedEmail): bool
    {
        $repo = $this->em->getRepository(ApiKey::class);
        $exists = $repo->findOneBy(['email' => $hashedEmail]) !== null;
        return !$exists;
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

        $repo = $this->em->getRepository(ApiKey::class);
        /** @var ApiKey|null $apiKey */
        $apiKey = $repo->findOneBy(['verificationToken' => $token]);

        if (!$apiKey) {
            return new Response('Invalid or expired verification link', Response::HTTP_BAD_REQUEST);
        }

        $apiKey->setEmailVerified(true)
            ->setVerificationToken(null)
            ->setIsActive(true);
        $this->em->flush();

        return $this->redirectToRoute('app_login', ['emailVerified' => 'true']);
    }


}
