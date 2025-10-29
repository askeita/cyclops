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

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private ?string $keyValue = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastUsedAt = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $usageCount = 0;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $email = null;


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
    public function getUsageCount(): ?int
    {
        return $this->usageCount;
    }

    /**
     * Set the usage count of the API key
     *
     * @param int|null $usageCount number of times the API key has been used
     * @return $this
     */
    public function setUsageCount(?int $usageCount): self
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
}
