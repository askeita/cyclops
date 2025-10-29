<?php

namespace App\Tests\Security;

use App\Entity\ApiKey;
use App\Security\ApiKeyUser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;


/**
 * Class ApiKeyUserTest
 *
 * @package App\Tests\Security
 */
class ApiKeyUserTest extends WebTestCase
{
    /**
     * Test creating an ApiKeyUser instance
     *
     * @return void
     */
    public function testCreateApiKeyUser(): void
    {
        $apiKey = $this->createMock(ApiKey::class);
        $apiKey->method('getKeyValue')->willReturn('test_key');
        $apiKey->method('getEmail')->willReturn('test@example.com');

        $user = new ApiKeyUser($apiKey);

        $this->assertEquals('test_key', $user->getUserIdentifier());
        $this->assertEquals(['ROLE_API_USER', 'ROLE_USER'], $user->getRoles());
    }

    /**
     * Test getRoles method
     *
     * @return void
     */
    public function testGetRoles(): void
    {
        $apiKey = $this->createMock(ApiKey::class);
        $apiKey->method('getEmail')->willReturn('test@example.com');
        $user = new ApiKeyUser($apiKey);
        $roles = $user->getRoles();

        $this->assertIsArray($roles);
        $this->assertContains('ROLE_API_USER', $roles);
    }

    /**
     * Test eraseCredentials method
     *
     * @return void
     */
    public function testEraseCredentials(): void
    {
        $apiKey = $this->createMock(ApiKey::class);
        $apiKey->method('getEmail')->willReturn('test@example.com');
        $user = new ApiKeyUser($apiKey);

        // Should not throw any exceptions
        $user->eraseCredentials();
        $this->assertTrue(true);
    }

    /**
     * Test user identifier persistence
     *
     * @return void
     */
    public function testUserIdentifierPersistence(): void
    {
        $apiKey = $this->createMock(ApiKey::class);
        $apiKey->method('getKeyValue')->willReturn('persistent_key');
        $apiKey->method('getEmail')->willReturn('persistent@example.com');
        $user = new ApiKeyUser($apiKey);

        $this->assertEquals('persistent_key', $user->getUserIdentifier());

        // Identifier should remain the same
        $this->assertEquals('persistent_key', $user->getUserIdentifier());
    }
}
