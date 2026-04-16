<?php

namespace App\Entity\Trait;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

trait VOTrait
{
    #[ORM\Column(nullable: true)]
    #[Groups(['vehicule:read', 'vehicule:write'])]
    private ?int $mileage = null;

    #[ORM\Column(length: 17, nullable: true)]
    #[Groups(['vehicule:read', 'vehicule:write'])]
    private ?string $vin = null;

    #[ORM\Column(options: ['default' => false])]
    #[Groups(['vehicule:read', 'vehicule:write'])]
    private bool $isA2Compatible = false;

    #[ORM\Column(type: 'date', nullable: true)]
    #[Groups(['vehicule:read', 'vehicule:write'])]
    private ?\DateTimeInterface $datePremiereMiseEnCirculation = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['vehicule:read', 'vehicule:write'])]
    private ?string $couleur = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    #[Groups(['vehicule:read', 'vehicule:write'])]
    private ?string $registrationCost = null;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['vehicule:read', 'vehicule:write'])]
    private ?array $optionsAndAccessories = null;

    #[ORM\Column(type: 'date', nullable: true)]
    #[Groups(['vehicule:read', 'vehicule:write'])]
    private ?\DateTimeInterface $controleTechniqueDate = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['vehicule:read', 'vehicule:write'])]
    private ?string $controleTechniqueResultat = null;

    public function getMileage(): ?int { return $this->mileage; }
    public function setMileage(?int $mileage): static { $this->mileage = $mileage; return $this; }

    public function getVin(): ?string { return $this->vin; }
    public function setVin(?string $vin): static { $this->vin = $vin; return $this; }

    public function getIsA2Compatible(): bool { return $this->isA2Compatible; }
    public function setIsA2Compatible(bool $v): static { $this->isA2Compatible = $v; return $this; }

    public function getDatePremiereMiseEnCirculation(): ?\DateTimeInterface { return $this->datePremiereMiseEnCirculation; }
    public function setDatePremiereMiseEnCirculation(?\DateTimeInterface $v): static { $this->datePremiereMiseEnCirculation = $v; return $this; }

    public function getCouleur(): ?string { return $this->couleur; }
    public function setCouleur(?string $v): static { $this->couleur = $v; return $this; }

    public function getRegistrationCost(): ?string { return $this->registrationCost; }
    public function setRegistrationCost(?string $v): static { $this->registrationCost = $v; return $this; }

    public function getOptionsAndAccessories(): ?array { return $this->optionsAndAccessories; }
    public function setOptionsAndAccessories(?array $v): static { $this->optionsAndAccessories = $v; return $this; }

    public function getControleTechniqueDate(): ?\DateTimeInterface { return $this->controleTechniqueDate; }
    public function setControleTechniqueDate(?\DateTimeInterface $v): static { $this->controleTechniqueDate = $v; return $this; }

    public function getControleTechniqueResultat(): ?string { return $this->controleTechniqueResultat; }
    public function setControleTechniqueResultat(?string $v): static { $this->controleTechniqueResultat = $v; return $this; }
}
