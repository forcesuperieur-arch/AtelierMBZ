<?php

namespace App\Service;

use App\Entity\NotificationTemplate;
use Doctrine\ORM\EntityManagerInterface;

class NotificationTemplateCatalog
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function getDefaults(): array
    {
        return [
            [
                'code' => 'rdv_confirmation',
                'channel' => 'email',
                'libelle' => 'Confirmation de rendez-vous',
                'sujet' => 'Confirmation de votre rendez-vous du {{date_rdv}}',
                'corps' => '<p>Bonjour {{client_prenom}},</p><p>Votre rendez-vous du <strong>{{date_rdv}}</strong> à <strong>{{heure_rdv}}</strong> est confirmé.</p><p>Type d\'intervention : {{type_intervention}}</p>',
                'variables' => ['client_nom', 'client_prenom', 'date_rdv', 'heure_rdv', 'type_intervention'],
            ],
            [
                'code' => 'rdv_confirmation',
                'channel' => 'sms',
                'libelle' => 'Confirmation de rendez-vous SMS',
                'sujet' => null,
                'corps' => 'Bonjour {{client_prenom}}, votre rendez-vous du {{date_rdv}} à {{heure_rdv}} est confirmé.',
                'variables' => ['client_prenom', 'date_rdv', 'heure_rdv'],
            ],
            [
                'code' => 'rappel_j1',
                'channel' => 'email',
                'libelle' => 'Rappel J-1',
                'sujet' => 'Rappel : rendez-vous demain à {{heure_rdv}}',
                'corps' => '<p>Bonjour {{client_prenom}},</p><p>Rappel : votre rendez-vous est prévu demain, le <strong>{{date_rdv}}</strong> à <strong>{{heure_rdv}}</strong>.</p>',
                'variables' => ['client_prenom', 'date_rdv', 'heure_rdv'],
            ],
            [
                'code' => 'rappel_j1',
                'channel' => 'sms',
                'libelle' => 'Rappel J-1 SMS',
                'sujet' => null,
                'corps' => 'Rappel Atelier : rendez-vous demain le {{date_rdv}} à {{heure_rdv}}.',
                'variables' => ['date_rdv', 'heure_rdv'],
            ],
            [
                'code' => 'travaux_termines',
                'channel' => 'email',
                'libelle' => 'Travaux terminés',
                'sujet' => 'Votre moto est prête',
                'corps' => '<p>Bonjour {{client_prenom}},</p><p>Les travaux sur votre véhicule sont terminés. Vous pouvez venir le récupérer.</p>',
                'variables' => ['client_prenom'],
            ],
            [
                'code' => 'travaux_termines',
                'channel' => 'sms',
                'libelle' => 'Travaux terminés SMS',
                'sujet' => null,
                'corps' => 'Atelier : votre véhicule est prêt. Vous pouvez passer le récupérer.',
                'variables' => [],
            ],
            [
                'code' => 'demande_complementaire',
                'channel' => 'email',
                'libelle' => 'Travaux complémentaires',
                'sujet' => 'Validation de travaux complémentaires',
                'corps' => '<p>Bonjour {{client_prenom}},</p><p>Des travaux complémentaires nécessitent votre accord sur le dossier {{reference}}.</p>',
                'variables' => ['client_prenom', 'reference'],
            ],
            [
                'code' => 'demande_complementaire',
                'channel' => 'sms',
                'libelle' => 'Travaux complémentaires SMS',
                'sujet' => null,
                'corps' => 'Atelier : merci de valider les travaux complémentaires du dossier {{reference}}. Lien : {{companion_url}}',
                'variables' => ['reference', 'companion_url'],
            ],
            [
                'code' => 'rdv_annulation',
                'channel' => 'email',
                'libelle' => 'Annulation de rendez-vous',
                'sujet' => 'Annulation de votre rendez-vous du {{date_rdv}}',
                'corps' => '<p>Bonjour {{client_prenom}},</p><p>Votre rendez-vous du <strong>{{date_rdv}}</strong> à <strong>{{heure_rdv}}</strong> a été annulé.</p><p>Contactez-nous pour en fixer un nouveau.</p>',
                'variables' => ['client_nom', 'client_prenom', 'date_rdv', 'heure_rdv'],
            ],
            [
                'code' => 'rdv_annulation',
                'channel' => 'sms',
                'libelle' => 'Annulation de rendez-vous SMS',
                'sujet' => null,
                'corps' => 'Atelier : votre rendez-vous du {{date_rdv}} à {{heure_rdv}} a été annulé. Contactez-nous.',
                'variables' => ['date_rdv', 'heure_rdv'],
            ],
            [
                'code' => 'rdv_refus',
                'channel' => 'email',
                'libelle' => 'Refus de rendez-vous',
                'sujet' => 'Refus de votre demande de rendez-vous',
                'corps' => '<p>Bonjour {{client_prenom}},</p><p>Votre demande de rendez-vous du <strong>{{date_rdv}}</strong> n\'a pu être acceptée.</p><p>Motif : {{motif_refus}}.</p>{{message_alternatif}}',
                'variables' => ['client_prenom', 'date_rdv', 'heure_rdv', 'motif_refus', 'message_alternatif'],
            ],
            [
                'code' => 'rdv_refus',
                'channel' => 'sms',
                'libelle' => 'Refus de rendez-vous SMS',
                'sujet' => null,
                'corps' => 'Atelier : demande de RDV du {{date_rdv}} refusée ({{motif_refus}}). {{message_alternatif}}',
                'variables' => ['date_rdv', 'motif_refus', 'message_alternatif'],
            ],
            [
                'code' => 'rdv_modifie',
                'channel' => 'email',
                'libelle' => 'Modification de rendez-vous',
                'sujet' => 'Votre rendez-vous a été modifié',
                'corps' => '<p>Bonjour {{client_prenom}},</p><p>Votre rendez-vous a été modifié. Nouveau créneau : <strong>{{date_rdv}}</strong> à <strong>{{heure_rdv}}</strong>.</p>',
                'variables' => ['client_nom', 'client_prenom', 'date_rdv', 'heure_rdv'],
            ],
            [
                'code' => 'rdv_modifie',
                'channel' => 'sms',
                'libelle' => 'Modification de rendez-vous SMS',
                'sujet' => null,
                'corps' => 'Atelier : votre rendez-vous a été modifié. Nouveau créneau : {{date_rdv}} à {{heure_rdv}}.',
                'variables' => ['date_rdv', 'heure_rdv'],
            ],
            [
                'code' => 'facture_emise',
                'channel' => 'email',
                'libelle' => 'Facture émise',
                'sujet' => 'Votre facture {{numero_facture}} est disponible',
                'corps' => '<p>Bonjour {{client_prenom}},</p><p>Votre facture n° <strong>{{numero_facture}}</strong> d\'un montant de <strong>{{montant_ttc}} €</strong> est disponible.</p>',
                'variables' => ['client_prenom', 'numero_facture', 'montant_ttc'],
            ],
            [
                'code' => 'facture_emise',
                'channel' => 'sms',
                'libelle' => 'Facture émise SMS',
                'sujet' => null,
                'corps' => 'Atelier : votre facture {{numero_facture}} ({{montant_ttc}} €) est disponible.',
                'variables' => ['numero_facture', 'montant_ttc'],
            ],
            [
                'code' => 'restitution_prete',
                'channel' => 'email',
                'libelle' => 'Moto prête pour restitution',
                'sujet' => 'Votre moto est prête — restitution disponible',
                'corps' => '<p>Bonjour {{client_prenom}},</p><p>Votre véhicule est prêt. Vous pouvez venir le récupérer à nos horaires d\'ouverture.</p>',
                'variables' => ['client_prenom'],
            ],
            [
                'code' => 'restitution_prete',
                'channel' => 'sms',
                'libelle' => 'Moto prête pour restitution SMS',
                'sujet' => null,
                'corps' => 'Atelier : votre moto est prête, vous pouvez venir la récupérer.',
                'variables' => [],
            ],
            // --- Gardiennage relances ---
            [
                'code' => 'gardiennage_debut',
                'channel' => 'email',
                'libelle' => 'Gardiennage — Entrée en gardiennage (immédiat)',
                'sujet' => 'Votre moto {{plaque}} est prête — frais de gardiennage à compter de ce jour',
                'corps' => '<p>Bonjour {{client_prenom}},</p><p>Votre véhicule ({{plaque}}, dossier {{reference_rdv}}) est prêt à être récupéré.</p><p>À compter d\'aujourd\'hui, des frais de gardiennage de <strong>{{tarif_journalier}} € par jour ouvré</strong> s\'appliqueront jusqu\'à la restitution.</p><p>Merci de venir le récupérer dans les meilleurs délais à nos horaires d\'ouverture, ou de nous contacter pour convenir d\'une date.</p>',
                'variables' => ['client_prenom', 'plaque', 'reference_rdv', 'tarif_journalier'],
            ],
            [
                'code' => 'relance_gardiennage_j15',
                'channel' => 'email',
                'libelle' => 'Gardiennage — Relance J+15',
                'sujet' => 'Votre moto {{plaque}} est toujours en attente de récupération',
                'corps' => '<p>Bonjour {{client_prenom}},</p><p>Votre véhicule ({{plaque}}) est toujours présent dans notre atelier depuis {{seuil_jours}} jours ouvrés. Merci de bien vouloir venir le récupérer.</p>',
                'variables' => ['client_prenom', 'plaque', 'seuil_jours'],
            ],
            [
                'code' => 'relance_gardiennage_j15',
                'channel' => 'sms',
                'libelle' => 'Gardiennage — Relance J+15 SMS',
                'sujet' => null,
                'corps' => 'Atelier : votre moto {{plaque}} est toujours chez nous. Merci de venir la récupérer (réf. {{reference_rdv}}).',
                'variables' => ['plaque', 'reference_rdv'],
            ],
            [
                'code' => 'relance_gardiennage_j30',
                'channel' => 'email',
                'libelle' => 'Gardiennage — Relance J+30',
                'sujet' => 'Rappel urgent : votre moto {{plaque}} toujours en gardiennage',
                'corps' => '<p>Bonjour {{client_prenom}},</p><p>Cela fait {{seuil_jours}} jours ouvrés que votre véhicule ({{plaque}}) est présent dans notre atelier. Des frais de gardiennage peuvent s\'appliquer. Contactez-nous sans délai.</p>',
                'variables' => ['client_prenom', 'plaque', 'seuil_jours'],
            ],
            [
                'code' => 'relance_gardiennage_j30',
                'channel' => 'sms',
                'libelle' => 'Gardiennage — Relance J+30 SMS',
                'sujet' => null,
                'corps' => 'URGENT — Atelier : votre moto {{plaque}} est en gardiennage depuis {{seuil_jours}}j. Contactez-nous rapidement.',
                'variables' => ['plaque', 'seuil_jours'],
            ],
            [
                'code' => 'relance_gardiennage_j45',
                'channel' => 'email',
                'libelle' => 'Gardiennage — Proposition officielle J+45',
                'sujet' => 'Proposition de gardiennage pour votre moto {{plaque}}',
                'corps' => '<p>Bonjour {{client_prenom}},</p><p>Après {{seuil_jours}} jours ouvrés, nous vous informons que la garde de votre véhicule ({{plaque}}) fait l\'objet d\'une facturation de gardiennage. Contactez-nous pour organiser la restitution.</p>',
                'variables' => ['client_prenom', 'plaque', 'seuil_jours'],
            ],
            [
                'code' => 'relance_gardiennage_j45',
                'channel' => 'sms',
                'libelle' => 'Gardiennage — Proposition officielle J+45 SMS',
                'sujet' => null,
                'corps' => 'Atelier : votre moto {{plaque}} fait l\'objet d\'une facturation gardiennage. Contactez-nous (réf. {{reference_rdv}}).',
                'variables' => ['plaque', 'reference_rdv'],
            ],
            [
                'code' => 'relance_gardiennage_j180',
                'channel' => 'email',
                'libelle' => 'Gardiennage — Procédure abandon J+180',
                'sujet' => 'Procédure d\'abandon engagée — véhicule {{plaque}}',
                'corps' => '<p>Bonjour {{client_prenom}},</p><p>Sans réponse de votre part depuis {{seuil_jours}} jours ouvrés, nous engageons la procédure légale d\'abandon de véhicule pour la moto ({{plaque}}). Cette lettre vaut mise en demeure.</p>',
                'variables' => ['client_prenom', 'plaque', 'seuil_jours'],
            ],
            [
                'code' => 'relance_gardiennage_j180',
                'channel' => 'sms',
                'libelle' => 'Gardiennage — Procédure abandon J+180 SMS',
                'sujet' => null,
                'corps' => 'Atelier : procédure d\'abandon engagée pour votre moto {{plaque}} après {{seuil_jours}}j. Contactez-nous d\'urgence.',
                'variables' => ['plaque', 'seuil_jours'],
            ],
            // --- Dépôt-vente mandat ---
            [
                'code' => 'mandat_depot_vente_j7',
                'channel' => 'email',
                'libelle' => 'Dépôt-vente — Mandat expire dans 7 jours',
                'sujet' => 'Mandat dépôt-vente {{vehicule}} — expiration le {{date_expiry}}',
                'corps' => '<p>Bonjour,</p><p>Le mandat dépôt-vente pour le véhicule <strong>{{vehicule}}</strong> expire le <strong>{{date_expiry}}</strong>.</p><p>Pensez à contacter le déposant pour renouveler ou clôturer le dossier (réf. dépôt #{{depot_id}}).</p>',
                'variables' => ['vehicule', 'date_expiry', 'depot_id'],
            ],
            // [SPRINT-4] I2 — rappel révision J-30 client
            [
                'code' => 'rappel_prochaine_revision',
                'channel' => 'email',
                'libelle' => 'Rappel révision — J-30',
                'sujet' => 'Rappel : révision de votre {{marque}} {{modele}} prévue le {{date_revision}}',
                'corps' => '<p>Bonjour {{client_prenom}},</p><p>Votre <strong>{{marque}} {{modele}}</strong> a une révision prévue le <strong>{{date_revision}}</strong>.</p><p>Appelez-nous ou prenez rendez-vous en ligne pour confirmer votre créneau.</p><p>À bientôt,<br>L\'équipe {{atelier_nom}}</p>',
                'variables' => ['client_prenom', 'marque', 'modele', 'date_revision', 'atelier_nom'],
            ],
            [
                'code' => 'rappel_prochaine_revision',
                'channel' => 'sms',
                'libelle' => 'Rappel révision — SMS J-30',
                'sujet' => '',
                'corps' => 'Bonjour {{client_prenom}}, votre {{marque}} {{modele}} est due en révision le {{date_revision}}. Prenez RDV : {{lien_rdv}}',
                'variables' => ['client_prenom', 'marque', 'modele', 'date_revision', 'lien_rdv'],
            ],
        ];
    }

    public function ensureDefaultsForAtelier(int $atelierId): int
    {
        if ($atelierId <= 0) {
            return 0;
        }

        $repository = $this->em->getRepository(NotificationTemplate::class);
        $created = 0;

        foreach ($this->getDefaults() as $definition) {
            $existing = $repository->findOneBy([
                'atelierId' => $atelierId,
                'code' => $definition['code'],
                'channel' => $definition['channel'],
            ]);

            if ($existing instanceof NotificationTemplate) {
                continue;
            }

            $template = new NotificationTemplate();
            $template->setAtelierId($atelierId);
            $template->setCode($definition['code']);
            $template->setChannel($definition['channel']);
            $template->setLibelle($definition['libelle']);
            $template->setSujet($definition['sujet']);
            $template->setCorps($definition['corps']);
            $template->setVariables($definition['variables']);
            $template->setIsActive(true);

            $this->em->persist($template);
            $created++;
        }

        if ($created > 0) {
            $this->em->flush();
        }

        return $created;
    }
}
