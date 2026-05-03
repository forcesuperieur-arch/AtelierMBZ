<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Fournisseur;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

class FournisseurTest extends TestCase
{
    private Fournisseur $fournisseur;

    protected function setUp(): void
    {
        $this->fournisseur = new Fournisseur();
        $this->fournisseur->setNom('Moto Pieces Plus');
    }

    public function testDefaultValues(): void
    {
        $this->assertSame(3, $this->fournisseur->getDelaiLivraisonJours());
        $this->assertSame(1, $this->fournisseur->getIsActive());
        $this->assertInstanceOf(\DateTimeInterface::class, $this->fournisseur->getCreatedAt());
    }

    public function testSettersAndGetters(): void
    {
        $this->fournisseur->setNom('Euro Moto');
        $this->fournisseur->setContact('Jean Dupont');
        $this->fournisseur->setTelephone('0123456789');
        $this->fournisseur->setEmail('contact@euromoto.fr');
        $this->fournisseur->setAdresse('12 Rue de la Moto');
        $this->fournisseur->setSiret('12345678901234');
        $this->fournisseur->setDelaiLivraisonJours(5);
        $this->fournisseur->setNotes('Fournisseur privilegie');
        $this->fournisseur->setIsActive(0);
        $this->fournisseur->setAtelierId(2);

        $this->assertSame('Euro Moto', $this->fournisseur->getNom());
        $this->assertSame('Jean Dupont', $this->fournisseur->getContact());
        $this->assertSame('0123456789', $this->fournisseur->getTelephone());
        $this->assertSame('contact@euromoto.fr', $this->fournisseur->getEmail());
        $this->assertSame('12 Rue de la Moto', $this->fournisseur->getAdresse());
        $this->assertSame('12345678901234', $this->fournisseur->getSiret());
        $this->assertSame(5, $this->fournisseur->getDelaiLivraisonJours());
        $this->assertSame('Fournisseur privilegie', $this->fournisseur->getNotes());
        $this->assertSame(0, $this->fournisseur->getIsActive());
        $this->assertSame(2, $this->fournisseur->getAtelierId());
    }

    public function testValidationNomNotBlank(): void
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        $this->fournisseur->setNom('');
        $violations = $validator->validate($this->fournisseur);

        $this->assertGreaterThan(0, $violations->count());
    }

    public function testValidationNomMaxLength(): void
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        $this->fournisseur->setNom(str_repeat('A', 201));
        $violations = $validator->validate($this->fournisseur);

        $this->assertGreaterThan(0, $violations->count());
    }

    public function testValidationNomWithinMaxLength(): void
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        $this->fournisseur->setNom(str_repeat('A', 200));
        $violations = $validator->validate($this->fournisseur);

        $this->assertSame(0, $violations->count());
    }
}
