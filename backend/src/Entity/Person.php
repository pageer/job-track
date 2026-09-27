<?php

namespace App\Entity;

use App\Repository\PersonRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PersonRepository::class)]
class Person
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['person.list', 'person.read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'people')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['person.list', 'person.read'])]
    private ?string $name = null;

    #[ORM\ManyToOne(inversedBy: 'people')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Company $company = null;

    #[ORM\Column(length: 254, nullable: true)]
    #[Groups(['person.list', 'person.read'])]
    private ?string $email = null;

    #[ORM\Column(length: 2048, nullable: true)]
    #[Groups(['person.list', 'person.read'])]
    private ?string $linkedInUrl = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['person.read'])]
    private ?string $notes = null;

    #[ORM\Column]
    #[Groups(['person.list', 'person.read'])]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * @var Collection<int, Contact>
     */
    #[ORM\OneToMany(mappedBy: 'person', targetEntity: Contact::class, orphanRemoval: true)]
    #[ORM\OrderBy(['date' => 'DESC', 'createdAt' => 'DESC'])]
    #[Groups(['person.read'])]
    private Collection $contacts;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->contacts = new ArrayCollection();
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

    public function getCompany(): ?Company
    {
        return $this->company;
    }

    public function setCompany(?Company $company): static
    {
        $this->company = $company;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getLinkedInUrl(): ?string
    {
        return $this->linkedInUrl;
    }

    public function setLinkedInUrl(?string $linkedInUrl): static
    {
        $this->linkedInUrl = $linkedInUrl;

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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, Contact>
     */
    public function getContacts(): Collection
    {
        return $this->contacts;
    }

    public function addContact(Contact $contact): static
    {
        if (!$this->contacts->contains($contact)) {
            $this->contacts->add($contact);
            $contact->setPerson($this);
        }

        return $this;
    }

    public function removeContact(Contact $contact): static
    {
        if ($this->contacts->removeElement($contact)) {
            // set the owning side to null (unless already changed)
            if ($contact->getPerson() === $this) {
                $contact->setPerson(null);
            }
        }

        return $this;
    }

    #[Groups(['person.list', 'person.read'])]
    public function getCompanyId(): ?int
    {
        return $this->company?->getId();
    }

    #[Groups(['person.list', 'person.read'])]
    public function getCompanyName(): ?string
    {
        return $this->company?->getName();
    }

    #[Groups(['person.list', 'person.read'])]
    public function getLastContact(): ?Contact
    {
        $first = $this->contacts->first();
        if (!$first instanceof Contact) {
            return null;
        }

        return $first;
    }

    #[Groups(['person.list', 'person.read'])]
    public function getNeedsFollowUp(): bool
    {
        foreach ($this->contacts as $contact) {
            if ($contact->getNeedsFollowUp()) {
                return true;
            }
        }

        return false;
    }
}
