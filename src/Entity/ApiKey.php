<?php

namespace App\Entity;

use App\Repository\ApiKeyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\User;

#[ORM\Entity(repositoryClass: ApiKeyRepository::class)]
class ApiKey
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?bool $isActive = null;

    #[ORM\Column(length: 255)]
    private ?string $token = null;

    #[ORM\Column(length: 255)]
    private ?string $clientId = null;

    #[ORM\OneToOne(targetEntity: User::class, inversedBy: "apiKey", cascade: ["persist"])]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }



    #[ORM\OneToMany(mappedBy: 'apikey', targetEntity: ApiUsage::class)]
    private Collection $apiUsages;

    public function __construct()
    {
        $this->apiUsages = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(string $token): static
    {
        $this->token = $token;

        return $this;
    }

    public function getClientId(): ?string
    {
        return $this->clientId;
    }

    public function setClientId(string $clientId): static
    {
        $this->clientId = $clientId;

        return $this;
    }

    /**
     * @return Collection<int, ApiUsage>
     */
    public function getApiUsages(): Collection
    {
        return $this->apiUsages;
    }

    public function addApiUsage(ApiUsage $apiUsage): static
    {
        if (!$this->apiUsages->contains($apiUsage)) {
            $this->apiUsages->add($apiUsage);
            $apiUsage->setApikey($this);
        }

        return $this;
    }

    public function removeApiUsage(ApiUsage $apiUsage): static
    {
        if ($this->apiUsages->removeElement($apiUsage)) {
            // set the owning side to null (unless already changed)
            if ($apiUsage->getApikey() === $this) {
                $apiUsage->setApikey(null);
            }
        }

        return $this;
    }
}
