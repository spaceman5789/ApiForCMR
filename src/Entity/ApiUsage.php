<?php

namespace App\Entity;

use App\Repository\ApiUsageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ApiUsageRepository::class)]
class ApiUsage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $usedAt = null;

    #[ORM\Column(length: 255)]
    private ?string $endpoint = null;

    #[ORM\ManyToOne(inversedBy: 'apiUsages')]
    private ?apiKey $apikey = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $responseMessage = null;


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsedAt(): ?\DateTimeInterface
    {
        return $this->usedAt;
    }

    public function setUsedAt(\DateTimeInterface $usedAt): static
    {
        $this->usedAt = $usedAt;

        return $this;
    }

    public function getEndpoint(): ?string
    {
        return $this->endpoint;
    }

    public function setEndpoint(string $endpoint): static
    {
        $this->endpoint = $endpoint;

        return $this;
    }

    public function getApikey(): ?apiKey
    {
        return $this->apikey;
    }

    public function setApikey(?apiKey $apikey): static
    {
        $this->apikey = $apikey;

        return $this;
    }
    

    public function getResponseMessage(): ?string
    {
        return $this->responseMessage;
    }

    public function setResponseMessage(string $responseMessage): static
    {
        $this->responseMessage = $responseMessage;

        return $this;
    }

}
