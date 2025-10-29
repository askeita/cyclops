<?php

namespace App\Security;

use App\Entity\ApiKey;
use Symfony\Component\Security\Core\User\UserInterface;


/**
 * Class ApiKeyUser
 */
class ApiKeyUser implements UserInterface
{
    private ApiKey $apiKey;


    /**
     * ApiKeyUser constructor.
     *
     * @param ApiKey $apiKey
     */
    public function __construct(ApiKey $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * Get the user identifier (email associated with the API key)
     *
     * @return string
     */
    public function getUserIdentifier(): string
    {
        return $this->apiKey->getKeyValue();
    }

    /**
     * Get the roles granted to the user
     *
     * @return array
     */
    public function getRoles(): array
    {
        return ['ROLE_API_USER', 'ROLE_USER'];
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // No sensitive data to erase
    }
}
