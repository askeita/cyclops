<?php

namespace App\Entity;

use App\Repository\ApiKeyRepository;
use Doctrine\ORM\Mapping as ORM;


/**
 * ApiKey
 */
#[ORM\Entity(repositoryClass: ApiKeyRepository::class)]
#[ORM\Table(name: 'api_keys')]
class ApiKey
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'key_value', type: 'string', length: 255, unique: true, nullable: true)]
    private ?string $keyValue = null;

    #[ORM\Column(name: 'is_active', type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: 'last_used_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastUsedAt = null;

    #[ORM\Column(name: 'usage_count', type: 'integer', options: ['default' => 0])]
    private int $usageCount = 0;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $email = null;

    // Champs ajoutés pour correspondre aux accès existants
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $password = null;

    #[ORM\Column(name: 'email_verified', type: 'boolean', options: ['default' => false])]
    private bool $emailVerified = false;

    #[ORM\Column(name: 'verification_token', type: 'string', length: 255, nullable: true)]
    private ?string $verificationToken = null;

    #[ORM\Column(name: 'last_connection', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastConnection = null;


    /**
     * ApiKey constructor.
     */
    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    /**
     * Get the ID of the API key
     *
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Get the value of the API key
     *
     * @return string|null
     */
    public function getKeyValue(): ?string
    {
        return $this->keyValue;
    }

    /**
     * Set the value of the API key
     *
     * @param string $keyValue
     * @return $this
     */
    public function setKeyValue(string $keyValue): self
    {
        $this->keyValue = $keyValue;
        return $this;
    }

    /**
     * Check if the API key is active
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**
     * Set the active status of the API key
     *
     * @param bool $isActive check if the API key is active
     * @return $this
     */
    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    /**
     * Get the creation date of the API key
     *
     * @return \DateTimeInterface|null
     */
    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    /**
     * Set the creation date of the API key
     *
     * @param \DateTimeInterface $createdAt date of creation
     * @return $this
     */
    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * Get the last used date of the API key
     *
     * @return \DateTimeInterface|null
     */
    public function getLastUsedAt(): ?\DateTimeInterface
    {
        return $this->lastUsedAt;
    }

    /**
     * Set the last used date of the API key
     *
     * @param \DateTimeInterface|null $lastUsedAt date of last use
     * @return $this
     */
    public function setLastUsedAt(?\DateTimeInterface $lastUsedAt): self
    {
        $this->lastUsedAt = $lastUsedAt;
        return $this;
    }

    /**
     * Get the usage count of the API key
     *
     * @return int|null
     */
    public function getUsageCount(): int
    {
        return $this->usageCount;
    }

    /**
     * Set the usage count of the API key
     *
     * @param int $usageCount number of times the API key has been used
     * @return $this
     */
    public function setUsageCount(int $usageCount): self
    {
        $this->usageCount = $usageCount;
        return $this;
    }

    /**
     * Increment the usage count and update the last used date
     *
     * @return $this
     */
    public function incrementUsage(): self
    {
        $this->usageCount++;
        $this->lastUsedAt = new \DateTime();
        return $this;
    }

    /**
     * Get the email associated with the API key
     *
     * @return string|null
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * Set the email associated with the API key
     *
     * @param string|null $email
     * @return $this
     */
    public function setEmail(?string $email): self
    {
        $this->email = $email;
        return $this;
    }

    /**
     * Get the password associated with the API key
     *
     * @return string|null
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * Set the password associated with the API key
     *
     * @param string|null $password
     * @return $this
     */
    public function setPassword(?string $password): self
    {
        $this->password = $password;
        return $this;
    }

    /**
     * Check if the email is verified for the API key
     *
     * @return bool
     */
    public function isEmailVerified(): bool
    {
        return $this->emailVerified;
    }

    /**
     * Set the email verification status for the API key
     *
     * @param bool $emailVerified
     * @return $this
     */
    public function setEmailVerified(bool $emailVerified): self
    {
        $this->emailVerified = $emailVerified;
        return $this;
    }

    /**
     * Get the verification token for the API key
     *
     * @return string|null
     */
    public function getVerificationToken(): ?string
    {
        return $this->verificationToken;
    }

    /**
     * Set the verification token for the API key
     *
     * @param string|null $verificationToken
     * @return $this
     */
    public function setVerificationToken(?string $verificationToken): self
    {
        $this->verificationToken = $verificationToken;
        return $this;
    }

    /**
     * Get the last connection date of the API key
     *
     * @return \DateTimeInterface|null
     */
    public function getLastConnection(): ?\DateTimeInterface
    {
        return $this->lastConnection;
    }

    /**
     * Set the last connection date of the API key
     *
     * @param \DateTimeInterface|null $lastConnection
     * @return $this
     */
    public function setLastConnection(?\DateTimeInterface $lastConnection): self
    {
        $this->lastConnection = $lastConnection;
        return $this;
    }
}
