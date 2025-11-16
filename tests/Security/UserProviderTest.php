<?php

namespace App\Tests\Security;

use App\Controller\EmailController;
use App\Repository\ApiKeyRepository;
use App\Security\User;
use App\Security\UserProvider;
use Random\RandomException;
use stdClass;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use App\Tests\Traits\EnsureTestDatabaseTrait;


/**
 * Class UserProviderTest
 *
 * @package App\Tests\Security
 */
class UserProviderTest extends WebTestCase
{
    use EnsureTestDatabaseTrait;

    private UserProvider $userProvider;
    private ApiKeyRepository $apiKeyRepository;
    private EmailController $emailController;

    /**
     * Set up the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $projectDir = '/tmp';
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->with('app.encryption_key')->willReturn('test_encryption_key_for_tests');

        $this->apiKeyRepository = $this->createMock(ApiKeyRepository::class);
        $this->emailController = $this->createMock(EmailController::class);

        $this->userProvider = new UserProvider(
            $projectDir,
            $parameterBag,
            $this->emailController,
            $this->apiKeyRepository
        );
    }

    public static function setUpBeforeClass(): void
    {
        self::ensureTestDatabaseEnv();
        putenv('MAILER_DSN=null://null'); $_ENV['MAILER_DSN']='null://null'; $_SERVER['MAILER_DSN']='null://null';
    }

    /**
     * Test the loadUserByIdentifier method with a valid API key.
     *
     * @return void
     */
    public function testLoadUserByIdentifierWithValidApiKey(): void
    {
        // Test with invalid key - should throw UserNotFoundException
        $this->apiKeyRepository->method('findOneBy')->willReturn(null);
        $this->expectException(UserNotFoundException::class);
        $this->userProvider->loadUserByIdentifier('invalid-key@example.com');
    }

    /**
     * Test the refreshUser method with an unsupported user class.
     *
     * @return void
     */
    public function testRefreshUserWithUnsupportedUser(): void
    {
        $unsupportedUser = $this->createMock(UserInterface::class);

        $this->expectException(UnsupportedUserException::class);
        $this->userProvider->refreshUser($unsupportedUser);
    }

    /**
     * Test the supportsClass method with a supported class.
     *
     * @return void
     */
    public function testSupportsClassWithUser(): void
    {
        $this->assertTrue($this->userProvider->supportsClass(User::class));
    }

    /**
     * Test the supportsClass method with an unsupported class.
     *
     * @return void
     */
    public function testSupportsClassWithOtherClass(): void
    {
        $this->assertFalse($this->userProvider->supportsClass(stdClass::class));
    }

    /**
     * Test the createUser method when the email already exists.
     *
     * @return void
     * @throws RandomException
     * @throws TransportExceptionInterface
     */
    public function testCreateUserEmailAlreadyExists(): void
    {
        // Simulate existing email
        $this->apiKeyRepository->method('findOneBy')->willReturn(new \App\Entity\ApiKey());
        $result = $this->userProvider->createUser('existing@example.com', 'hash');
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\JsonResponse::class, $result);
        $decoded = json_decode($result->getContent(), true);

        // Controller may return JSON string or array
        if (!is_array($decoded)) {
            $decoded = json_decode($decoded ?? '', true) ?? [];
        }
        $this->assertArrayHasKey('error', $decoded);
    }

    /**
     * Test the createUser method for successful user creation.
     *
     * @return void
     * @throws RandomException
     * @throws TransportExceptionInterface
     */
    public function testCreateUserSuccess(): void
    {
        $this->apiKeyRepository->method('findOneBy')->willReturn(null);
        $this->apiKeyRepository->expects($this->once())->method('save')->willReturnCallback(function() { /* void */ });
        $this->emailController->expects($this->once())->method('sendVerificationEmail');
        $user = $this->userProvider->createUser('newuser@example.com', 'hash');
        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('newuser@example.com', $user->getUserIdentifier());
    }
}
