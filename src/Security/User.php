<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;


/**
 * User
 */
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    private string $userIdentifier;
    private ?string $password;
    private array $roles;
    private bool $hasApiKey;


    /**
     * User constructor.
     *
     * @param string $userIdentifier     user userIdentifier
     * @param bool $hasApiKey   check if user has an API key
     */
    public function __construct(string $userIdentifier, string $password, array $roles = [], bool $hasApiKey = false)
    {
        $this->userIdentifier = $userIdentifier;
        $this->password = $password;
        $this->roles = $roles;
        $this->hasApiKey = $hasApiKey;
    }

    /**
     * Erase sensitive data (not used in this case)
     *
     * @return void
     */
    #[\Deprecated]
    public function eraseCredentials(): void {}

    /**
     * Get the user identifier (userIdentifier in this case)
     *
     * @return string
     */
    public function getUserIdentifier(): string
    {
        return $this->userIdentifier;
    }

    /**
     * Get the password (not used in this case)
     *
     * @return string|null
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * Check if the user has an API key
     *
     * @return bool
     */
    public function hasApiKey(): bool
    {
        return $this->hasApiKey;
    }

    /**
     * Get the roles granted to the user
     *
     * @return array
     */
    public function getRoles(): array
    {
        $this->roles = ['ROLE_USER'];

        // Grant ROLE_API_USER if the user has an API key
        if ($this->hasApiKey) {
            $this->roles[] = 'ROLE_API_USER';
        }

        return $this->roles;
    }
}
