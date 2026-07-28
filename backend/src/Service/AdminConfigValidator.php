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
        // Seuil d'alerte séjour atelier : au moins 1 h (0 alerterait tout, dès l'arrivée)
        // et au plus un an d'heures ouvrées.
        $this->validateBorne($configData, 'min_photos_entree', 0, 20, 'Le nombre de photos d’entrée exigées doit être compris entre 0 et 20.', $errors);
        $this->validateBorne($configData, 'relance_travaux_delai_heures', 1, 168, 'Le délai de relance des travaux supplémentaires doit être compris entre 1 h et 168 h.', $errors);
        $this->validateBorne($configData, 'relance_heure_min', 0, 23, 'L’heure de début d’envoi doit être comprise entre 0 et 23.', $errors);
        $this->validateBorne($configData, 'relance_heure_max', 1, 24, 'L’heure de fin d’envoi doit être comprise entre 1 et 24.', $errors);
        $this->validateBorne($configData, 'lien_public_jours', 1, 3650, 'La validité des liens clients doit être comprise entre 1 et 3650 jours.', $errors);
        $this->validateBorne($configData, 'essai_points_min', 0, 50, 'Le nombre de points de contrôle exigés doit être compris entre 0 et 50.', $errors);
        $this->validateBorne($configData, 'rappel_alerte_heures', 1, 720, 'Le délai avant de re-signaler une moto doit être compris entre 1 h et 720 h.', $errors);
        $this->validateFenetreHoraire($configData, $errors);
        $this->validateRappelsRdv($configData, $errors);

        $this->validateBorne(
            $configData,
            'seuil_sejour_atelier_heures',
            1,
            24 * 365,
            'Le seuil d’alerte « moto en atelier » doit être un nombre d’heures entre 1 et 8760.',
            $errors,
        );

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

    /** La fenêtre d'envoi doit être cohérente, sinon plus aucune relance ne partirait. */
    private function validateFenetreHoraire(array $data, array &$errors): void
    {
        if (!isset($data['relance_heure_min'], $data['relance_heure_max'])) {
            return;
        }

        if ((int) $data['relance_heure_max'] <= (int) $data['relance_heure_min']) {
            $errors[] = 'L’heure de fin d’envoi doit être postérieure à l’heure de début.';
        }
    }

    /** Jours de rappel avant RDV : 1 ou 2 délais strictement positifs. */
    private function validateRappelsRdv(array $data, array &$errors): void
    {
        if (!array_key_exists('rappels_rdv_jours', $data)) {
            return;
        }

        $jours = $data['rappels_rdv_jours'];
        if (!is_array($jours) || $jours === []) {
            $errors[] = 'Il faut au moins un rappel avant rendez-vous.';
            return;
        }

        if (count($jours) > 2) {
            $errors[] = 'Deux rappels avant rendez-vous au maximum.';
        }

        foreach ($jours as $jour) {
            if (!is_numeric($jour) || (int) $jour < 1 || (int) $jour > 60) {
                $errors[] = 'Chaque rappel doit être réglé entre 1 et 60 jours avant le rendez-vous.';
                break;
            }
        }
    }

    private function validateBorne(array $data, string $field, int $min, int $max, string $message, array &$errors): void
    {
        if (!array_key_exists($field, $data)) {
            return;
        }

        if (!is_numeric($data[$field])) {
            $errors[] = $message;
            return;
        }

        $value = (int) $data[$field];
        if ($value < $min || $value > $max) {
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
