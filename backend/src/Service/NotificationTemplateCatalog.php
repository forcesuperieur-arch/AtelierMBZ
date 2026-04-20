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
