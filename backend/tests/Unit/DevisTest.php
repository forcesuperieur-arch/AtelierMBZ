<?php

namespace App\Tests\Unit;

use App\Entity\Client;
use App\Entity\Devis;
use App\Entity\Vehicule;
use PHPUnit\Framework\TestCase;

class DevisTest extends TestCase
{
    public function testConstructInitializesDefaults(): void
    {
        $d = new Devis();
        $this->assertNotNull($d->getDateCreation());
        $this->assertNotNull($d->getCreatedAt());
        $this->assertNotNull($d->getDateValidite());
        $this->assertEmpty($d->getLignes());

        $expected = (new \DateTime('+30 days'))->format('Y-m-d');
        $this->assertSame($expected, $d->getDateValidite()->format('Y-m-d'));
    }

    public function testPrePersistGeneratesNumero(): void
    {
        $d = new Devis();
        $client = new Client();
        $client->setNom('Test');
        $client->setPrenom('Jean');
        $client->setEmail('test@example.com');
        $client->setTelephone('0600000000');
        $d->setClient($client);
        $d->prePersist();

        $this->assertSame("Test", $d->getSnapClientNom());
    }

    public function testPrePersistDoesNotOverwriteExistingNumero(): void
    {
        $d = new Devis();
        $d->setNumeroDevis('DEV-TEST-001');
        $d->prePersist();

        $this->assertSame('DEV-TEST-001', $d->getNumeroDevis());
    }

    public function testSnapshotClientData(): void
    {
        $d = new Devis();
        $client = new Client();
        $client->setNom('Dupont');
        $client->setPrenom('Jean');
        $client->setEmail('jean@example.test');
        $client->setTelephone('0600000000');
        $d->setClient($client);

        $vehicule = new Vehicule();
        $vehicule->setPlaque('AB-123-CD');
        $vehicule->setMarque('Yamaha');
        $vehicule->setModele('Tracer 9');
        $d->setVehicule($vehicule);

        $d->snapshotClientData();

        $this->assertSame('Dupont', $d->getSnapClientNom());
        $this->assertSame('Jean', $d->getSnapClientPrenom());
        $this->assertSame('jean@example.test', $d->getSnapClientEmail());
        $this->assertSame('0600000000', $d->getSnapClientTelephone());
        $this->assertSame('AB-123-CD', $d->getSnapVehiculePlaque());
        $this->assertSame('Yamaha', $d->getSnapVehiculeMarque());
        $this->assertSame('Tracer 9', $d->getSnapVehiculeModele());
    }

    public function testSnapshotClientDataDoesNotOverwrite(): void
    {
        $d = new Devis();
        $d->setSnapClientNom('Exist');

        $client = new Client();
        $client->setNom('New');
        $d->setClient($client);

        $d->snapshotClientData();
        $this->assertSame('Exist', $d->getSnapClientNom());
    }

    public function testHasSnapshot(): void
    {
        $d = new Devis();
        $this->assertFalse($d->hasSnapshot());

        $d->setSnapClientNom('Nom');
        $this->assertTrue($d->hasSnapshot());
    }

    public function testPreUpdateSetsUpdatedAt(): void
    {
        $d = new Devis();
        $old = $d->getUpdatedAt();
        sleep(1);
        $d->preUpdate();

        $this->assertGreaterThan($old, $d->getUpdatedAt());
    }
}
