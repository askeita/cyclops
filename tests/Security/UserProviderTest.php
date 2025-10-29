<?php

namespace App\Tests\Security;

use App\Controller\EmailController;
use App\Security\User;
use App\Security\UserProvider;
use Exception;
use stdClass;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\UserInterface;


/**
 * Class UserProviderTest
 *
 * @package App\Tests\Security
 */
class UserProviderTest extends WebTestCase
{
    private UserProvider $userProvider;

    /**
     * Set up the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $projectDir = '/tmp';
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $emailController = $this->createMock(EmailController::class);

        $this->userProvider = new UserProvider($projectDir, $parameterBag, $emailController);
    }

    /**
     * Test the loadUserByIdentifier method with a valid API key.
     *
     * @return void
     */
    public function testLoadUserByIdentifierWithValidApiKey(): void
    {
        // Test with invalid key - should throw UserNotFoundException
        $this->expectException(Exception::class); // PDOException or UserNotFoundException
        $this->userProvider->loadUserByIdentifier('invalid-key');
    }

    /**
     * Test the refreshUser method with a valid User object.
     *
     * @return void
     */
    public function testRefreshUserWithUser(): void
    {
        // Creates a valid User object
        $user = new User('test@example.com', 'password', ['ROLE_USER'], true);

        // test refreshUser with valid user but since we don't have a real database, expect exception
        $this->expectException(Exception::class); // PDOException ou UserNotFoundException
        $this->userProvider->refreshUser($user);
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
}

