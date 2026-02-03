<?php

namespace App\Entity;

use App\Repository\UserOfferPayoutRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserOfferPayoutRepository::class)]
class UserOfferPayout
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    

    #[ORM\ManyToOne]
    private ?User $user = null;

    #[ORM\Column]
    private ?float $payout = null;

    #[ORM\ManyToOne(inversedBy: 'userOfferPayouts')]
    private ?Offer $offer = null;

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

    public function getPayout(): ?float
    {
        return $this->payout;
    }

    public function setPayout(float $payout): static
    {
        $this->payout = $payout;

        return $this;
    }

    public function getOffer(): ?Offer
    {
        return $this->offer;
    }

    public function setOffer(?Offer $offer): static
    {
        $this->offer = $offer;

        return $this;
    }
}
