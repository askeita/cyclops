<?php

namespace App\Controller;

use App\Security\User;
use App\Security\UserProvider;
use Exception;
use Random\RandomException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;


/**
 * Class HomeController
 */
class HomeController extends AbstractController
{
    private UserProvider $userProvider;
    private UserPasswordHasherInterface $passwordHasher;
    private CsrfTokenManagerInterface $csrfTokenManager;
    private ValidatorInterface $validator;


    /**
     * Constructor
     *
     * @param UserProvider $userProvider
     * @param UserPasswordHasherInterface $passwordHasher
     * @param CsrfTokenManagerInterface $csrfTokenManager
     * @param ValidatorInterface $validator
     */
    public function __construct(
        UserProvider $userProvider,
        UserPasswordHasherInterface $passwordHasher,
        CsrfTokenManagerInterface $csrfTokenManager,
        ValidatorInterface $validator
    ) {
        $this->userProvider = $userProvider;
        $this->passwordHasher = $passwordHasher;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->validator = $validator;
    }

    /**
     * Home page
     *
     * @return Response
     */
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('home/index.html.twig');
    }

    /**
     * Login form
     *
     *
     * @param AuthenticationUtils $authenticationUtils
     * @return Response
     */
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('dashboard_home');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('home/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    /**
     * Signup form
     *
     * @return Response
     */
    #[Route('/signup', name: 'app_signup')]
    public function signup(): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('dashboard_home');
        }

        return $this->render('home/signup.html.twig');
    }

    /**
     * Get CSRF token for form protection
     *
     * @return JsonResponse
     */
    #[Route('/csrf-token', name: 'csrf_token', methods: ['GET'])]
    public function getCsrfToken(): JsonResponse
    {
        $token = $this->csrfTokenManager->getToken('authenticate');

        return new JsonResponse([
            'token' => $token->getValue()
        ]);
    }

    /**
     * Login CSRF-protected
     *
     * @param Request $request
     * @return JsonResponse|RedirectResponse
     */
    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function apiLogin(Request $request): JsonResponse|RedirectResponse
    {
        // Validate CSRF token
        $token = $request->headers->get('X-CSRF-Token');
        if (!$this->isCsrfTokenValid('authenticate', $token)) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], 403);
        }
        $data = json_decode($request->getContent(), true);

        // Validate input
        $violations = $this->validator->validate($data, new Assert\Collection([
            'email' => [new Assert\NotBlank(), new Assert\Email(), new Assert\Length(max: 254)],
            'password' => [new Assert\NotBlank(), new Assert\Length(min: 8, max: 128)],
            '_token' => [new Assert\NotBlank()],
        ]));

        if (count($violations) > 0) {
            return new JsonResponse(['error' => 'Invalid input data'], 400);
        }

        try {
            $user = $this->userProvider->loadUserByIdentifier($data['email']);
            if ($this->passwordHasher->isPasswordValid($user, $data['password'])) {
                $request->getSession()->set('user_email', $data['email']);

                return new JsonResponse([
                    'success' => true,
                    'redirect' => $this->generateUrl('dashboard_home')
                ]);
            }
        } catch (Exception) {
            // User not found or other error
            return new JsonResponse(['error' => 'Authentication failed'], 401);
        }

        return new JsonResponse(['error' => 'Invalid credentials'], 401);
    }

    /**
     * Signup CSRF-protected
     *
     * @param Request $request
     * @return JsonResponse
     */
    #[Route('/api/signup', name: 'api_signup', methods: ['POST'])]
    public function apiSignup(Request $request): JsonResponse
    {
        // Validate CSRF token
        $token = $request->headers->get('X-CSRF-Token');
        if (!$this->isCsrfTokenValid('authenticate', $token)) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], 403);
        }
        $data = json_decode($request->getContent(), true);

        // Validate input
        $violations = $this->validator->validate($data, new Assert\Collection([
            'email' => [new Assert\NotBlank(), new Assert\Email(), new Assert\Length(max: 254)],
            'password' => [new Assert\NotBlank(), new Assert\Length(min: 8, max: 128)],
            'confirmPassword' => [new Assert\NotBlank(), new Assert\Length(min: 8, max: 128)],
            '_token' => [new Assert\NotBlank()],
        ]));

        if (count($violations) > 0) {
            return new JsonResponse(['error' => 'Invalid input data'], 400);
        }

        // Check password confirmation
        if ($data['password'] !== $data['confirmPassword']) {
            return new JsonResponse(['error' => 'Passwords do not match'], 400);
        }

        // Check if user already exists
        try {
            $this->userProvider->loadUserByIdentifier($data['email']);
            // If no exception was thrown, user exists
            return new JsonResponse(['error' => 'User already exists'], 409);
        } catch (UserNotFoundException) {
            // Expected path: user does not exist, proceed to create
        } catch (Exception) {
            return new JsonResponse(['error' => 'Failed to create account'], 500);
        }

        // Hash new user password
        $user = new User($data['email'], $data['password'], ['ROLE_USER']);
        $hashedPassword = $this->passwordHasher->hashPassword($user, $data['password']);

        // Persist new user
        try {
            $createdUser = $this->userProvider->createUser($data['email'], $hashedPassword);
        } catch (RandomException | TransportExceptionInterface) {
            return new JsonResponse(['error' => 'Failed to send confirmation email'], 500);
        }
        if (!$createdUser instanceof User) {
            return new JsonResponse(['error' => 'Failed to create account'], 500);
        }

        return new JsonResponse(['success' => true, 'message' => 'Account created successfully']);
    }
}
