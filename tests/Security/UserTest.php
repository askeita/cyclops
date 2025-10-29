<?php

namespace App\Tests\Security;

use App\Security\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;


/**
 * Class UserTest
 *
 * @package App\Tests\Security
 */
class UserTest extends WebTestCase
{
    /**
     * Test creating a user without an API key.
     *
     * @return void
     */
    public function testCreateUserWithoutApiKey(): void
    {
        $email = 'test@example.com';
        $password = 'hashedPassword123';
        $roles = ['ROLE_USER'];

        $user = new User($email, $password, $roles, false);

        $this->assertEquals($email, $user->getUserIdentifier());
        $this->assertEquals($password, $user->getPassword());
        $this->assertFalse($user->hasApiKey());

        // Should only have ROLE_USER
        $expectedRoles = ['ROLE_USER'];
        $this->assertEquals($expectedRoles, $user->getRoles());
    }

    /**
     * Test creating a user with an API key.
     *
     * @return void
     */
    public function testCreateUserWithApiKey(): void
    {
        $email = 'test@example.com';
        $password = 'hashedPassword123';
        $roles = ['ROLE_USER'];

        $user = new User($email, $password, $roles, true);

        $this->assertEquals($email, $user->getUserIdentifier());
        $this->assertEquals($password, $user->getPassword());
        $this->assertTrue($user->hasApiKey());

        // Should have both ROLE_USER and ROLE_API_USER
        $expectedRoles = ['ROLE_USER', 'ROLE_API_USER'];
        $this->assertEquals($expectedRoles, $user->getRoles());
    }

    /**
     * Test default values when creating a user.
     *
     * @return void
     */
    public function testDefaultValues(): void
    {
        $user = new User('test@example.com', 'password123');

        $this->assertFalse($user->hasApiKey());
        $this->assertEquals(['ROLE_USER'], $user->getRoles());
    }

    /**
     * Test the eraseCredentials method.
     *
     * @return void
     */
    public function testEraseCredentials(): void
    {
        $user = new User('test@example.com', 'password123');

        // Should not throw any exceptions
        $user->eraseCredentials();
        $this->assertTrue(true);
    }

    /**
     * Test that roles always include ROLE_USER.
     *
     * @return void
     */
    public function testRolesAutomaticallyIncludeRoleUser(): void
    {
        $user = new User('test@example.com', 'password123', [], false);

        // Even with empty roles array, should still get ROLE_USER
        $roles = $user->getRoles();
        $this->assertContains('ROLE_USER', $roles);
    }

    /**
     * Test that a user with an API key gets the ROLE_API_USER role.
     *
     * @return void
     */
    public function testApiKeyUserGetsApiRole(): void
    {
        $user = new User('test@example.com', 'password123', [], true);

        $roles = $user->getRoles();
        $this->assertContains('ROLE_USER', $roles);
        $this->assertContains('ROLE_API_USER', $roles);
    }
}
