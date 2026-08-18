<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user.read'])]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Groups(['user.read'])]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['user.read'])]
    private ?string $name = null;

    /**
     * @var list<string>
     */
    #[ORM\Column]
    #[Groups(['user.read'])]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column]
    #[Groups(['user.read'])]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * @var Collection<int, JobSearch>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: JobSearch::class, orphanRemoval: true)]
    private Collection $jobSearches;

    /**
     * @var Collection<int, Resume>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Resume::class, orphanRemoval: true)]
    private Collection $resumes;

    /**
     * @var Collection<int, CoverLetter>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: CoverLetter::class, orphanRemoval: true)]
    private Collection $coverLetters;

    public function __construct()
    {
        $this->jobSearches = new ArrayCollection();
        $this->resumes = new ArrayCollection();
        $this->coverLetters = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @see UserInterface
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function isAdmin(): bool
    {
        return in_array('ROLE_ADMIN', $this->getRoles(), true);
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, JobSearch>
     */
    public function getJobSearches(): Collection
    {
        return $this->jobSearches;
    }

    public function addJobSearch(JobSearch $jobSearch): static
    {
        if (!$this->jobSearches->contains($jobSearch)) {
            $this->jobSearches->add($jobSearch);
            $jobSearch->setUser($this);
        }

        return $this;
    }

    public function removeJobSearch(JobSearch $jobSearch): static
    {
        if ($this->jobSearches->removeElement($jobSearch)) {
            // set the owning side to null (unless already changed)
            if ($jobSearch->getUser() === $this) {
                $jobSearch->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Resume>
     */
    public function getResumes(): Collection
    {
        return $this->resumes;
    }

    public function addResume(Resume $resume): static
    {
        if (!$this->resumes->contains($resume)) {
            $this->resumes->add($resume);
            $resume->setUser($this);
        }

        return $this;
    }

    public function removeResume(Resume $resume): static
    {
        if ($this->resumes->removeElement($resume)) {
            // set the owning side to null (unless already changed)
            if ($resume->getUser() === $this) {
                $resume->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, CoverLetter>
     */
    public function getCoverLetters(): Collection
    {
        return $this->coverLetters;
    }

    public function addCoverLetter(CoverLetter $coverLetter): static
    {
        if (!$this->coverLetters->contains($coverLetter)) {
            $this->coverLetters->add($coverLetter);
            $coverLetter->setUser($this);
        }

        return $this;
    }

    public function removeCoverLetter(CoverLetter $coverLetter): static
    {
        if ($this->coverLetters->removeElement($coverLetter)) {
            // set the owning side to null (unless already changed)
            if ($coverLetter->getUser() === $this) {
                $coverLetter->setUser(null);
            }
        }

        return $this;
    }
}
