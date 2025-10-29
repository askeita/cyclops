<?php

namespace App\Security;

use App\Controller\EmailController;
use DateTime;
use PDO;
use Random\RandomException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;


/**
 * Class UserProvider
 */
class UserProvider implements UserProviderInterface
{
    private string $projectDir;
    private ParameterBagInterface $parameterBag;
    private EmailController $emailController;


    /**
     * UserProvider constructor.
     *
     * @param string $projectDir
     * @param ParameterBagInterface $parameterBag
     * @param EmailController $emailController
     */
    public function __construct(
        string $projectDir,
        ParameterBagInterface $parameterBag,
        EmailController $emailController
    ) {
        $this->projectDir = $projectDir;
        $this->parameterBag = $parameterBag;
        $this->emailController = $emailController;
    }

    /**
     * Refreshes the user by reloading it from the database
     *
     * @param UserInterface $user
     * @return UserInterface
     * @throws UnsupportedUserException if the user class is not supported
     */
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User && !$user instanceof ApiKeyUser) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', get_class($user)));
        }

        // For ApiKeyUser, return the same instance as it's stateless
        if ($user instanceof ApiKeyUser) {
            return $user;
        }

        // Reload regular User from the database to check if they still have an API key
        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    /**
     * Checks if the provider supports the given user class
     *
     * @param string $class
     * @return bool
     */
    public function supportsClass(string $class): bool
    {
        return User::class === $class ||
               ApiKeyUser::class === $class ||
               is_subclass_of($class, User::class) ||
               is_subclass_of($class, ApiKeyUser::class);
    }

    /**
     * Load the user by their identifier (email in this case)
     *
     * @param string $identifier
     * @return UserInterface
     */
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        // Hash email for uniqueness check
        $encryptionKey = $this->parameterBag->get('app.encryption_key');
        $hashedEmail = hash('sha3-512', $identifier . $encryptionKey);

        // Use test database in test environment
        $dbFile = $this->parameterBag->get('kernel.environment') === 'test' ? 'test.db' : 'data.db';
        $pdo = new PDO('sqlite:' . $this->projectDir . '/var/' . $dbFile);

        // Check if the user has a verified API key in the database
        $stmt = $pdo->prepare('SELECT password, key_value FROM api_keys
                 WHERE email = :email AND email_verified = :email_verified AND is_active = :is_active');
        $stmt->execute(['email' => $hashedEmail, 'email_verified' => 1, 'is_active' => 1]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new UserNotFoundException(sprintf('User with identifier "%s" not found.', $identifier));
        }
        $hasApiKey = !empty($user['key_value']);

        return new User($identifier, $user['password'], ['ROLE_USER'], $hasApiKey);
    }

    /**
     * Creates a new user in the database
     *
     * @param string $identifier
     * @param string $password
     * @return UserInterface|JsonResponse
     * @throws RandomException
     * @throws TransportExceptionInterface
     */
    public function createUser(string $identifier, string $password): UserInterface|JsonResponse
    {
        // Hash email for uniqueness check
        $encryptionKey = $this->parameterBag->get('app.encryption_key');
        $hashedEmail = hash('sha3-512', $identifier . $encryptionKey);

        // Use test database in test environment
        $dbFile = $this->parameterBag->get('kernel.environment') === 'test' ? 'test.db' : 'data.db';
        $pdo = new PDO('sqlite:' . $this->projectDir . '/var/' . $dbFile);

        // Email uniqueness check
        $emailIsAvailable = $this->emailController->checkEmailAvailable($pdo, $hashedEmail);
        if (!$emailIsAvailable) {
            return new JsonResponse(
                json_encode(['error' => 'Email is already registered']),
                Response::HTTP_CONFLICT,
                ['Content-Type' => 'application/json']
            );
        }

        // Generate verification token
        $verificationToken = bin2hex(random_bytes(16));

        $stmt = $pdo->prepare('INSERT INTO api_keys (email, password, email_verified, verification_token, is_active, created_at)
                 VALUES (:email, :password, :email_verified, :verification_token, :is_active, :created_at)');
        $stmt->execute([
            'email' => $hashedEmail,
            'password' => $password,
            'email_verified' => 0,
            'is_active' => 0,
            'created_at' => (new DateTime())->format('Y-m-d H:i:s'),
            'verification_token' => $verificationToken,
        ]);

        // Send verification email
        $this->emailController->sendVerificationEmail($identifier, $verificationToken);

        return new User($identifier, $password, ['ROLE_USER'], false);
    }
}
