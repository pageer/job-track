<?php

namespace App\Entity;

use App\Repository\CompanyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CompanyRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_COMPANY_USER_NAME', fields: ['user', 'name'])]
class Company
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['company.read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'companies')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['company.read'])]
    private ?string $name = null;

    #[ORM\Column]
    #[Groups(['company.read'])]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * @var Collection<int, Job>
     */
    #[ORM\OneToMany(mappedBy: 'companyRef', targetEntity: Job::class)]
    private Collection $jobs;

    /**
     * @var Collection<int, Person>
     */
    #[ORM\OneToMany(mappedBy: 'company', targetEntity: Person::class)]
    private Collection $people;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->jobs = new ArrayCollection();
        $this->people = new ArrayCollection();
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, Job>
     */
    public function getJobs(): Collection
    {
        return $this->jobs;
    }

    /**
     * @return Collection<int, Person>
     */
    public function getPeople(): Collection
    {
        return $this->people;
    }

    #[Groups(['company.read'])]
    public function getJobCount(): int
    {
        return $this->jobs->count();
    }
}
