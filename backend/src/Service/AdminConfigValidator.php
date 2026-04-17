<?php

namespace App\Service;

final class AdminConfigValidator
{
    public function validateConfigPayload(array $configData, array $horaires = []): array
    {
        $errors = [];

        $this->validatePercentage($configData, 'tva_mo_taux', 'Le taux de TVA main d’œuvre doit être compris entre 0 et 100.', $errors);
        $this->validatePercentage($configData, 'tva_pieces_taux', 'Le taux de TVA pièces doit être compris entre 0 et 100.', $errors);
        $this->validatePercentage($configData, 'accompte_pourcentage', 'Le pourcentage d’acompte doit être compris entre 0 et 100.', $errors);

        $this->validatePositiveNumber($configData, 'taux_horaire_mo_standard', 'Le taux horaire standard doit être supérieur ou égal à 0.', $errors);
        $this->validatePositiveNumber($configData, 'taux_horaire_mo_complexe', 'Le taux horaire complexe doit être supérieur ou égal à 0.', $errors);
        $this->validatePositiveNumber($configData, 'taux_horaire_mo_expert', 'Le taux horaire expert doit être supérieur ou égal à 0.', $errors);
        $this->validatePositiveNumber($configData, 'forfait_mo_minimum', 'Le forfait minimum doit être supérieur ou égal à 0.', $errors);
        $this->validatePositiveNumber($configData, 'validite_devis_jours', 'La validité du devis doit être un entier positif.', $errors, true);
        $this->validatePositiveNumber($configData, 'garantie_travaux_jours', 'La garantie travaux doit être un entier positif.', $errors, true);
        $this->validatePositiveNumber($configData, 'tarif_gardiennage_journalier', 'Le tarif de gardiennage doit être supérieur ou égal à 0.', $errors);

        foreach ($horaires as $horaire) {
            $this->validateHoraire($horaire, $errors);
        }

        return array_values(array_unique($errors));
    }

    private function validatePercentage(array $data, string $field, string $message, array &$errors): void
    {
        if (!array_key_exists($field, $data)) {
            return;
        }

        if (!is_numeric($data[$field]) || (float) $data[$field] < 0 || (float) $data[$field] > 100) {
            $errors[] = $message;
        }
    }

    private function validatePositiveNumber(array $data, string $field, string $message, array &$errors, bool $integerOnly = false): void
    {
        if (!array_key_exists($field, $data)) {
            return;
        }

        if (!is_numeric($data[$field])) {
            $errors[] = $message;
            return;
        }

        $value = $integerOnly ? (int) $data[$field] : (float) $data[$field];
        if ($value < 0) {
            $errors[] = $message;
        }
    }

    private function validateHoraire(array $horaire, array &$errors): void
    {
        $jour = (int) ($horaire['jour_semaine'] ?? $horaire['jourSemaine'] ?? -1);
        $isOpen = (bool) ($horaire['is_ouvert'] ?? $horaire['isOuvert'] ?? false);

        if (!$isOpen) {
            return;
        }

        $open = trim((string) ($horaire['heure_ouverture'] ?? $horaire['heureOuverture'] ?? ''));
        $close = trim((string) ($horaire['heure_fermeture'] ?? $horaire['heureFermeture'] ?? ''));
        $pauseStart = trim((string) ($horaire['pause_debut'] ?? $horaire['pauseDebut'] ?? ''));
        $pauseEnd = trim((string) ($horaire['pause_fin'] ?? $horaire['pauseFin'] ?? ''));

        if ($open === '' || $close === '') {
            $errors[] = sprintf('Le jour %d doit contenir une heure d’ouverture et de fermeture.', $jour);
            return;
        }

        if (!$this->isValidTime($open) || !$this->isValidTime($close)) {
            $errors[] = sprintf('Le jour %d contient un format horaire invalide.', $jour);
            return;
        }

        if ($open >= $close) {
            $errors[] = sprintf('Le jour %d a des horaires incohérents : ouverture doit être avant fermeture.', $jour);
        }

        if (($pauseStart === '') xor ($pauseEnd === '')) {
            $errors[] = sprintf('Le jour %d doit avoir une pause complète : début et fin.', $jour);
            return;
        }

        if ($pauseStart !== '' && $pauseEnd !== '') {
            if (!$this->isValidTime($pauseStart) || !$this->isValidTime($pauseEnd)) {
                $errors[] = sprintf('Le jour %d contient un format de pause invalide.', $jour);
                return;
            }

            if ($pauseStart >= $pauseEnd) {
                $errors[] = sprintf('Le jour %d a une pause incohérente : début doit être avant fin.', $jour);
            }

            if ($pauseStart <= $open || $pauseEnd >= $close) {
                $errors[] = sprintf('Le jour %d a une pause hors plage d’ouverture.', $jour);
            }
        }
    }

    private function isValidTime(string $value): bool
    {
        return (bool) preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value);
    }
}
