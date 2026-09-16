<?php

namespace App\Entity;

use App\Repository\OneDriveTokenRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OneDriveTokenRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_ONEDRIVE_TOKEN_USER', fields: ['user'])]
class OneDriveToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'oneDriveToken', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $accessToken = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $refreshToken = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $accountEmail = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $accountDisplayName = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }

    public function setAccessToken(string $accessToken): static
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    public function setRefreshToken(string $refreshToken): static
    {
        $this->refreshToken = $refreshToken;

        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getAccountEmail(): ?string
    {
        return $this->accountEmail;
    }

    public function setAccountEmail(?string $accountEmail): static
    {
        $this->accountEmail = $accountEmail;

        return $this;
    }

    public function getAccountDisplayName(): ?string
    {
        return $this->accountDisplayName;
    }

    public function setAccountDisplayName(?string $accountDisplayName): static
    {
        $this->accountDisplayName = $accountDisplayName;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
