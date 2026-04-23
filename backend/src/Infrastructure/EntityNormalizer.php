<?php

namespace App\Infrastructure;

use App\Entity\Client;
use App\Entity\User;
use App\Entity\Vehicule;

class EntityNormalizer
{
    public function normalizeClientLite(?Client $client): ?array
    {
        if (!$client instanceof Client) {
            return null;
        }

        return [
            'id' => $client->getId(),
            'atelierId' => $client->getAtelierId(),
            'nom' => $client->getNom(),
            'prenom' => $client->getPrenom(),
            'telephone' => $client->getTelephone(),
            'email' => $client->getEmail(),
            'adresse' => $client->getAdresse(),
        ];
    }

    public function normalizeUserLite(?User $user): ?array
    {
        if (!$user instanceof User) {
            return null;
        }

        return [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'nom' => $user->getNom(),
            'prenom' => $user->getPrenom(),
        ];
    }

    public function normalizeVehiculeLite(?Vehicule $vehicule): ?array
    {
        if (!$vehicule instanceof Vehicule) {
            return null;
        }

        return [
            'id' => $vehicule->getId(),
            'atelierId' => $vehicule->getAtelierId(),
            'plaque' => $vehicule->getPlaque(),
            'marque' => $vehicule->getMarque(),
            'modele' => $vehicule->getModele(),
            'annee' => $vehicule->getAnnee(),
            'cylindree' => $vehicule->getCylindree(),
            'typeMoto' => $vehicule->getTypeMoto(),
            'mileage' => $vehicule->getMileage(),
            'vin' => $vehicule->getVin(),
            'couleur' => $vehicule->getCouleur(),
            'datePremiereMiseEnCirculation' => $vehicule->getDatePremiereMiseEnCirculation()?->format('Y-m-d'),
            'categorieId' => $vehicule->getCategorie()?->getId(),
            'categorieNom' => $vehicule->getCategorie()?->getNom(),
        ];
    }
}
