<?php

namespace App\Security;

use App\Controller\EmailController;
use App\Entity\ApiKey;
use App\Repository\ApiKeyRepository;
use DateTime;
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
    private ApiKeyRepository $apiKeyRepository;


    /**
     * UserProvider constructor.
     *
     * @param string $projectDir
     * @param ParameterBagInterface $parameterBag
     * @param EmailController $emailController
     * @param ApiKeyRepository $apiKeyRepository
     */
    public function __construct(
        string $projectDir,
        ParameterBagInterface $parameterBag,
        EmailController $emailController,
        ApiKeyRepository $apiKeyRepository
    ) {
        $this->projectDir = $projectDir;
        $this->parameterBag = $parameterBag;
        $this->emailController = $emailController;
        $this->apiKeyRepository = $apiKeyRepository;
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
        $encryptionKey = $this->parameterBag->get('app.encryption_key');
        $hashedEmail = hash('sha3-512', $identifier . $encryptionKey);

        /** @var ApiKey|null $apiKey */
        $apiKey = $this->apiKeyRepository->findOneBy([
            'email' => $hashedEmail,
            'emailVerified' => true,
            'isActive' => true,
        ]);

        if (!$apiKey) {
            throw new UserNotFoundException(sprintf('User with identifier "%s" not found.', $identifier));
        }

        $hasApiKey = !empty($apiKey->getKeyValue());
        $password = $apiKey->getPassword() ?? '';

        return new User($identifier, $password, ['ROLE_USER'], $hasApiKey);
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
        $encryptionKey = $this->parameterBag->get('app.encryption_key');
        $hashedEmail = hash('sha3-512', $identifier . $encryptionKey);

        // Email uniqueness check via repository
        $existing = $this->apiKeyRepository->findOneBy(['email' => $hashedEmail]);
        if ($existing) {
            return new JsonResponse(
                json_encode(['error' => 'Email is already registered']),
                Response::HTTP_CONFLICT,
                ['Content-Type' => 'application/json']
            );
        }

        // Create ApiKey entity in inactive/unverified state
        $entity = new ApiKey();
        $entity->setEmail($hashedEmail)
            ->setPassword($password)
            ->setIsActive(false)
            ->setEmailVerified(false)
            ->setCreatedAt(new DateTime());

        $verificationToken = bin2hex(random_bytes(16));
        $entity->setVerificationToken($verificationToken);

        $this->apiKeyRepository->save($entity, true);

        // Send verification email
        $this->emailController->sendVerificationEmail($identifier, $verificationToken);

        return new User($identifier, $password, ['ROLE_USER'], false);
    }
}
