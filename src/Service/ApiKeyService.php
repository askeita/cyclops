<?php

namespace App\Service;

use App\Entity\ApiKey;
use Doctrine\ORM\EntityManagerInterface;
use Random\RandomException;

/**
 * Service for managing API keys
 */
class ApiKeyService
{
    private EntityManagerInterface $entityManager;

    /**
     * ApiKeyService constructor.
     *
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Create a new API key
     *
     * @param string|null $name Optional name/identifier for the API key
     * @return ApiKey
     */
    public function createApiKey(?string $name = null): ApiKey
    {
        $apiKey = new ApiKey();

        // Generate a unique API key with the format ak_[64 hex characters]
        try {
            $keyValue = 'ak_' . bin2hex(random_bytes(32));
        } catch (RandomException $e) {
            throw new \RuntimeException('Failed to generate a secure API key.', 0, $e);
        }
        $apiKey->setKeyValue($keyValue);

        // Set creation date
        $apiKey->setCreatedAt(new \DateTime());

        // Set as active by default
        $apiKey->setIsActive(true);

        $this->entityManager->persist($apiKey);
        $this->entityManager->flush();

        return $apiKey;
    }

    /**
     * Deactivate an API key
     *
     * @param string $keyValue
     * @return bool True if the key was found and deactivated, false otherwise
     */
    public function deactivateApiKey(string $keyValue): bool
    {
        $apiKey = $this->entityManager->getRepository(ApiKey::class)->findOneBy([
            'keyValue' => $keyValue
        ]);

        if (!$apiKey) {
            return false;
        }

        $apiKey->setIsActive(false);
        $this->entityManager->flush();

        return true;
    }

    /**
     * Get all API keys
     *
     * @return ApiKey[]
     */
    public function getAllApiKeys(): array
    {
        return $this->entityManager->getRepository(ApiKey::class)->findAll();
    }

    /**
     * Get active API keys
     *
     * @return ApiKey[]
     */
    public function getActiveApiKeys(): array
    {
        return $this->entityManager->getRepository(ApiKey::class)->findBy([
            'isActive' => true
        ]);
    }

    /**
     * Find API key by value
     *
     * @param string $keyValue
     * @return ApiKey|null
     */
    public function findApiKeyByValue(string $keyValue): ?ApiKey
    {
        return $this->entityManager->getRepository(ApiKey::class)->findOneBy([
            'keyValue' => $keyValue
        ]);
    }

    /**
     * Update last used timestamp for an API key
     *
     * @param ApiKey $apiKey
     * @return void
     */
    public function updateLastUsed(ApiKey $apiKey): void
    {
        $apiKey->setLastUsedAt(new \DateTime());
        $apiKey->setUsageCount($apiKey->getUsageCount() + 1);
        $this->entityManager->flush();
    }
}
