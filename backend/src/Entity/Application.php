<?php

namespace App\Entity;

use App\Enum\ResumeKind;
use App\Repository\ApplicationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: ApplicationRepository::class)]
class Application
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['application.read', 'application.summary'])]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'application', targetEntity: Job::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Job $job = null;

    #[ORM\Column(type: Types::ENUM, enumType: ResumeKind::class, length: 10, nullable: true)]
    #[Groups(['application.read'])]
    private ?ResumeKind $resumeKind = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['application.read'])]
    private ?string $resumeFileName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $resumeFilePath = null;

    #[ORM\Column(length: 120, nullable: true)]
    #[Groups(['application.read'])]
    private ?string $resumeMimeType = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['application.read'])]
    private ?int $resumeFileSize = null;

    #[ORM\Column(length: 2048, nullable: true)]
    #[Groups(['application.read'])]
    private ?string $resumeLinkUrl = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['application.read'])]
    private ?string $coverLetterHtml = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['application.read'])]
    private ?string $notes = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    #[Groups(['application.read'])]
    private ?\DateTimeImmutable $actionDate = null;

    #[ORM\Column]
    #[Groups(['application.read', 'application.summary'])]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * @var Collection<int, Interview>
     */
    #[ORM\OneToMany(mappedBy: 'application', targetEntity: Interview::class, orphanRemoval: true)]
    #[ORM\OrderBy(['date' => 'ASC'])]
    #[Groups(['application.read'])]
    private Collection $interviews;

    public function __construct()
    {
        $this->interviews = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getJob(): ?Job
    {
        return $this->job;
    }

    #[Groups(['application.read'])]
    public function getJobId(): ?int
    {
        return $this->job?->getId();
    }

    #[Groups(['application.read'])]
    public function getJobTitle(): ?string
    {
        return $this->job?->getTitle();
    }

    #[Groups(['application.read'])]
    public function getJobCompany(): ?string
    {
        return $this->job?->getCompany();
    }

    public function setJob(?Job $job): static
    {
        $this->job = $job;

        return $this;
    }

    public function getResumeKind(): ?ResumeKind
    {
        return $this->resumeKind;
    }

    public function setResumeKind(?ResumeKind $resumeKind): static
    {
        $this->resumeKind = $resumeKind;

        return $this;
    }

    public function getResumeFileName(): ?string
    {
        return $this->resumeFileName;
    }

    public function setResumeFileName(?string $resumeFileName): static
    {
        $this->resumeFileName = $resumeFileName;

        return $this;
    }

    public function getResumeFilePath(): ?string
    {
        return $this->resumeFilePath;
    }

    public function setResumeFilePath(?string $resumeFilePath): static
    {
        $this->resumeFilePath = $resumeFilePath;

        return $this;
    }

    public function getResumeMimeType(): ?string
    {
        return $this->resumeMimeType;
    }

    public function setResumeMimeType(?string $resumeMimeType): static
    {
        $this->resumeMimeType = $resumeMimeType;

        return $this;
    }

    public function getResumeFileSize(): ?int
    {
        return $this->resumeFileSize;
    }

    public function setResumeFileSize(?int $resumeFileSize): static
    {
        $this->resumeFileSize = $resumeFileSize;

        return $this;
    }

    public function getResumeLinkUrl(): ?string
    {
        return $this->resumeLinkUrl;
    }

    public function setResumeLinkUrl(?string $resumeLinkUrl): static
    {
        $this->resumeLinkUrl = $resumeLinkUrl;

        return $this;
    }

    public function getCoverLetterHtml(): ?string
    {
        return $this->coverLetterHtml;
    }

    public function setCoverLetterHtml(?string $coverLetterHtml): static
    {
        $this->coverLetterHtml = $coverLetterHtml;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function getActionDate(): ?\DateTimeImmutable
    {
        return $this->actionDate;
    }

    public function setActionDate(?\DateTimeImmutable $actionDate): static
    {
        $this->actionDate = $actionDate;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, Interview>
     */
    public function getInterviews(): Collection
    {
        return $this->interviews;
    }

    public function addInterview(Interview $interview): static
    {
        if (!$this->interviews->contains($interview)) {
            $this->interviews->add($interview);
            $interview->setApplication($this);
        }

        return $this;
    }

    public function removeInterview(Interview $interview): static
    {
        if ($this->interviews->removeElement($interview)) {
            // set the owning side to null (unless already changed)
            if ($interview->getApplication() === $this) {
                $interview->setApplication(null);
            }
        }

        return $this;
    }
}
