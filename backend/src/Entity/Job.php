<?php

namespace App\Entity;

use App\Enum\JobStatus;
use App\Repository\JobRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: JobRepository::class)]
class Job
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['job.read', 'job.ref', 'job.list'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'jobs')]
    #[ORM\JoinColumn(nullable: false)]
    private ?JobSearch $jobSearch = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['job.read', 'job.ref', 'job.list'])]
    private ?string $title = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['job.read', 'job.ref', 'job.list'])]
    private ?string $company = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['job.read'])]
    private ?string $descriptionHtml = null;

    #[ORM\Column(length: 2048, nullable: true)]
    #[Assert\Url]
    #[Groups(['job.read'])]
    private ?string $descriptionUrl = null;

    #[ORM\Column(type: Types::ENUM, enumType: JobStatus::class, length: 20)]
    #[Assert\NotNull]
    #[Groups(['job.read', 'job.ref', 'job.list'])]
    private ?JobStatus $status = JobStatus::Investigating;

    #[ORM\Column]
    #[Groups(['job.read', 'job.list'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\OneToOne(mappedBy: 'job', targetEntity: Application::class, cascade: ['persist', 'remove'])]
    #[Groups(['job.read'])]
    private ?Application $application = null;

    /**
     * @var Collection<int, JobNote>
     */
    #[ORM\OneToMany(mappedBy: 'job', targetEntity: JobNote::class, orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    #[Groups(['job.read'])]
    private Collection $notes;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->notes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getJobSearch(): ?JobSearch
    {
        return $this->jobSearch;
    }

    public function setJobSearch(?JobSearch $jobSearch): static
    {
        $this->jobSearch = $jobSearch;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getCompany(): ?string
    {
        return $this->company;
    }

    public function setCompany(string $company): static
    {
        $this->company = $company;

        return $this;
    }

    public function getDescriptionHtml(): ?string
    {
        return $this->descriptionHtml;
    }

    public function setDescriptionHtml(?string $descriptionHtml): static
    {
        $this->descriptionHtml = $descriptionHtml;

        return $this;
    }

    public function getDescriptionUrl(): ?string
    {
        return $this->descriptionUrl;
    }

    public function setDescriptionUrl(?string $descriptionUrl): static
    {
        $this->descriptionUrl = $descriptionUrl;

        return $this;
    }

    public function getStatus(): ?JobStatus
    {
        return $this->status;
    }

    public function setStatus(?JobStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getApplication(): ?Application
    {
        return $this->application;
    }

    public function setApplication(?Application $application): static
    {
        // set the owning side of the relation if necessary
        if ($application?->getJob() !== $this) {
            $application?->setJob($this);
        }
        $this->application = $application;

        return $this;
    }

    public function hasApplication(): bool
    {
        return null !== $this->application;
    }

    /**
     * @return Collection<int, JobNote>
     */
    public function getNotes(): Collection
    {
        return $this->notes;
    }

    public function addNote(JobNote $note): static
    {
        if (!$this->notes->contains($note)) {
            $this->notes->add($note);
            $note->setJob($this);
        }

        return $this;
    }

    public function removeNote(JobNote $note): static
    {
        if ($this->notes->removeElement($note)) {
            // set the owning side to null (unless already changed)
            if ($note->getJob() === $this) {
                $note->setJob(null);
            }
        }

        return $this;
    }

    #[Groups(['job.list', 'job.read'])]
    public function getJobSearchId(): ?int
    {
        return $this->jobSearch?->getId();
    }

    #[Groups(['job.list', 'job.read'])]
    public function getActionDate(): ?\DateTimeImmutable
    {
        return $this->application?->getActionDate();
    }
}
