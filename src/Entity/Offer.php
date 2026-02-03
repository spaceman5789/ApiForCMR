<?php
namespace App\Entity;

use App\Repository\OfferRepository;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: OfferRepository::class)]
#[ApiResource(
    collectionOperations: ['get', 'post'], // Enable GET and POST for collections
    itemOperations: ['get'] // Enable GET for individual items
)]
class Offer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $offerId = null;

    #[ORM\Column(length: 255)]
    private ?string $country = null;

    #[ORM\OneToMany(mappedBy: 'offer', targetEntity: UserOfferPayout::class, cascade: ['persist', 'remove'])]
    private Collection $userOfferPayouts;

    public function __construct()
    {
        // Initialize as ArrayCollection to hold multiple UserOfferPayout instances
        $this->userOfferPayouts = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getOfferId(): ?string
    {
        return $this->offerId;
    }

    public function setOfferId(string $offerId): static
    {
        $this->offerId = $offerId;

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(string $country): static
    {
        $this->country = $country;

        return $this;
    }

    /**
     * @return Collection<int, UserOfferPayout>
     */
    public function getUserOfferPayouts(): Collection
    {
        return $this->userOfferPayouts;
    }

    public function addUserOfferPayout(UserOfferPayout $userOfferPayout): static
    {
        if (!$this->userOfferPayouts->contains($userOfferPayout)) {
            $this->userOfferPayouts->add($userOfferPayout);
            $userOfferPayout->setOffer($this);
        }

        return $this;
    }

    public function removeUserOfferPayout(UserOfferPayout $userOfferPayout): static
    {
        if ($this->userOfferPayouts->removeElement($userOfferPayout)) {
            // Set the owning side to null (unless already changed)
            if ($userOfferPayout->getOffer() === $this) {
                $userOfferPayout->setOffer(null);
            }
        }

        return $this;
    }
}
