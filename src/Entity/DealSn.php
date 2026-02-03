<?php

namespace App\Entity;

use App\Repository\DealTnRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DealSnRepository::class)]
class DealSn
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: "string", unique: true,nullable: true)]
    private ?string $order_id = null;

    #[ORM\Column(length: 255,nullable: true)]
    private ?string $lead_id = null;

    #[ORM\Column(length: 255,nullable: true)]
    private ?string $client_id = null;

    #[ORM\Column(length: 255,nullable: true)]
    private ?string $status = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE,nullable: true)]
    private ?\DateTimeInterface $date_created = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE,nullable: true)]
    private ?\DateTimeInterface $date_modified = null;

    #[ORM\Column(length: 255,nullable: true)]
    private ?string $contact_id = null;

    #[ORM\Column(length: 255,nullable: true)]
    private ?string $offer_id = null;

    #[ORM\Column (nullable: true)]
    private ?bool $is_sent = null;

    #[ORM\Column(nullable: true)]
    private ?array $jsonValue = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $bitrix_Id = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrderId(): ?string
    {
        return $this->order_id;
    }

    public function setOrderId(string $order_id): static
    {
        $this->order_id = $order_id;

        return $this;
    }

    public function getLeadId(): ?string
    {
        return $this->lead_id;
    }

    public function setLeadId(string $lead_id): static
    {
        $this->lead_id = $lead_id;

        return $this;
    }

    public function getClientId(): ?string
    {
        return $this->client_id;
    }

    public function setClientId(string $client_id): static
    {
        $this->client_id = $client_id;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getDateCreated(): ?\DateTimeInterface
    {
        return $this->date_created;
    }

    public function setDateCreated(\DateTimeInterface $date_created): static
    {
        $this->date_created = $date_created;

        return $this;
    }

    public function getDateModified(): ?\DateTimeInterface
    {
        return $this->date_modified;
    }

    public function setDateModified(\DateTimeInterface $date_modified): static
    {
        $this->date_modified = $date_modified;

        return $this;
    }

    public function getContactId(): ?string
    {
        return $this->contact_id;
    }

    public function setContactId(string $contact_id): static
    {
        $this->contact_id = $contact_id;

        return $this;
    }

    public function getOfferId(): ?string
    {
        return $this->offer_id;
    }

    public function setOfferId(string $offer_id): static
    {
        $this->offer_id = $offer_id;

        return $this;
    }

    public function isIsSent(): ?bool
    {
        return $this->is_sent;
    }

    public function setIsSent(bool $is_sent): static
    {
        $this->is_sent = $is_sent;

        return $this;
    }

    public function getJsonValue(): ?array
    {
        return $this->jsonValue;
    }

    public function setJsonValue(?array $jsonValue): static
    {
        $this->jsonValue = $jsonValue;

        return $this;
    }

    public function getBitrixId(): ?string
    {
        return $this->bitrix_Id;
    }

    public function setBitrixId(?string $bitrix_Id): static
    {
        $this->bitrix_Id = $bitrix_Id;

        return $this;
    }
}
