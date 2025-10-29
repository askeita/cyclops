<?php

namespace App\Tests\Service;

use App\Service\ApiKeyService;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;


/**
 * Class ApiKeyServiceTest
 *
 * @package App\Tests\Service
 */
class ApiKeyServiceTest extends WebTestCase
{
    private ApiKeyService $apiKeyService;


    /**
     * Set up the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist');
        $entityManager->method('flush');

        $this->apiKeyService = new ApiKeyService($entityManager);
    }

    /**
     * Test the createApiKey method.
     *
     * @return void
     */
    public function testCreateApiKey(): void
    {
        $apiKey = $this->apiKeyService->createApiKey();

        $this->assertNotNull($apiKey->getKeyValue());
        $this->assertNotEmpty($apiKey->getKeyValue());
        $this->assertInstanceOf(DateTimeInterface::class, $apiKey->getCreatedAt());
        $this->assertTrue($apiKey->isActive());

        // Check the format of the key value
        $keyValue = $apiKey->getKeyValue();
        $this->assertStringStartsWith('ak_', $keyValue);
        $this->assertEquals(67, strlen($keyValue)); // 3 (ak_) + 64 (hex)
        $this->assertMatchesRegularExpression('/^ak_[a-f0-9]{64}$/', $keyValue);
    }

    /**
     * Test that multiple created API keys are unique.
     *
     * @return void
     */
    public function testCreateMultipleApiKeysAreUnique(): void
    {
        $apiKey1 = $this->apiKeyService->createApiKey();
        $apiKey2 = $this->apiKeyService->createApiKey();

        $this->assertNotNull($apiKey1->getKeyValue());
        $this->assertNotNull($apiKey2->getKeyValue());
        $this->assertNotEquals($apiKey1->getKeyValue(), $apiKey2->getKeyValue());
    }

    /**
     * Test that the createdAt date is recent.
     *
     * @return void
     */
    public function testApiKeyCreatedAtIsRecent(): void
    {
        $beforeCreation = new DateTime();
        $apiKey = $this->apiKeyService->createApiKey();
        $afterCreation = new DateTime();

        $this->assertGreaterThanOrEqual($beforeCreation, $apiKey->getCreatedAt());
        $this->assertLessThanOrEqual($afterCreation, $apiKey->getCreatedAt());
    }

    /**
     * Test that the API key is active by default.
     *
     * @return void
     */
    public function testApiKeyIsActiveByDefault(): void
    {
        $apiKey = $this->apiKeyService->createApiKey();

        $this->assertTrue($apiKey->isActive());
    }
}
