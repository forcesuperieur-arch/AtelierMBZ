/* Données de la refonte du portail. Elles ajoutent ce que le portail actuel ne
   couvre pas : le devis à valider ligne par ligne, la conversation avec
   l'atelier, le carnet d'entretien et les documents téléchargeables. */
const PT_CLIENT = { prenom: 'Thomas', nom: 'Berthier', email: 'thomas.berthier@gmail.com', tel: '06 42 18 90 33', adresse: '14 rue des Postes\n59000 Lille' };

const PT_MOTO = {
  marque: 'Yamaha', modele: 'MT-07', plaque: 'EF-771-GH', annee: 2021, cylindree: '689 cm³',
  km: 19842, prochaineVidange: { km: 19000, due: true },
};

const PT_SUIVI = {
  or: 'OR-2431', intervention: 'Révision 20 000 km', depose: 'mardi 26 août, 08 h 06',
  statut: 'en_cours', restitution: 'jeudi 28 août, vers 16 h',
  etapes: [
    { cle: 'confirme', titre: 'Rendez-vous confirmé', date: '19 août, 14 h 12', fait: true },
    { cle: 'receptionne', titre: 'Moto réceptionnée', date: '26 août, 08 h 06', fait: true, detail: 'État des lieux signé avec Julie D.' },
    { cle: 'en_cours', titre: 'Intervention en cours', date: '26 août, 09 h 30', fait: true, courant: true, detail: 'Karim L. travaille sur votre moto.' },
    { cle: 'prete', titre: 'Prête à récupérer', date: 'prévu jeudi 28 août, vers 16 h', fait: false },
    { cle: 'restitue', titre: 'Restituée', date: '', fait: false },
  ],
  etatDesLieux: {
    signe: '26 août, 08 h 12', km: 19842, carburant: 'Réserve',
    observations: 'Rayure carénage droit, 8 cm, signalée à la dépose. Top-case laissé sur la moto, vide.',
    photos: ['Trois quarts avant droit', 'Carénage droit — rayure', 'Compteur à la dépose'],
  },
};

const PT_DEVIS = {
  numero: 'OR-2431', emis: 'aujourd’hui, 11 h 04', par: 'Karim L., mécanicien',
  urgence: 'À décider avant ce soir',
  motif: 'Le disque arrière est voilé. Nous conseillons de le remplacer pendant que la moto est sur le pont : le refaire plus tard demande une nouvelle immobilisation.',
  lignes: [
    { id: 1, designation: 'Disque de frein arrière', detail: 'Pièce d’origine Yamaha, référence 1WS-F582V-00', prix: 118, requis: true },
    { id: 2, designation: 'Main d’œuvre', detail: '1 h 00 au tarif atelier', prix: 50, requis: true },
    { id: 3, designation: 'Plaquettes arrière', detail: 'Recommandé avec un disque neuf, pas indispensable', prix: 42, requis: false },
  ],
};

const PT_MESSAGES = [
  { de: 'atelier', auteur: 'Julie D.', date: '26 août, 08 h 14', texte: 'Bonjour Thomas, votre moto est bien arrivée. L’état des lieux est signé, vous le retrouvez dans le suivi.' },
  { de: 'client', auteur: 'Vous', date: '26 août, 08 h 40', texte: 'Merci. Est-ce que la révision comprend le contrôle de la chaîne ?' },
  { de: 'atelier', auteur: 'Julie D.', date: '26 août, 09 h 02', texte: 'Oui, contrôle et réglage sont dans la révision. Rien à ajouter de votre côté.' },
  { de: 'atelier', auteur: 'Karim L.', date: 'aujourd’hui, 11 h 04', texte: 'J’ai trouvé un disque arrière voilé. Je vous ai envoyé un devis, il attend votre accord.', nonLu: true },
];

/* L'échéance du prochain entretien. Douze mois après la dernière intervention,
   posée par l'atelier à la clôture de l'OR. Le client peut couper : les rappels
   d'entretien s'arrêtent, les messages liés à une intervention en cours
   continuent — ce ne sont pas les mêmes messages. */
const PT_ECHEANCE = {
  quoi: 'Révision 20 000 km',
  date: '12 juin 2027',
  dans: 'dans 10 mois',
  pose: 'Posée par Karim B. à la clôture de l’OR-2318, le 12 juin 2026',
  base: 'Douze mois après votre dernier passage.',
  estimation: 'Entre 210 et 240 € selon l’état des filtres',
  canal: 'SMS',
  actif: true,
};

const PT_CARNET = [
  { date: '12 juin 2026', km: 18120, or: 'OR-2318', titre: 'Plaquettes avant', travaux: 'Plaquettes avant Brembo 07BB19, purge du circuit de frein, contrôle des durites.', montant: 168 },
  { date: '19 février 2026', km: 15430, or: 'OR-2204', titre: 'Révision 10 000 km', travaux: 'Huile Motul 7100, filtre HF204, filtre à air, contrôle des 22 points constructeur.', montant: 189 },
  { date: '4 octobre 2025', km: 11890, or: 'OR-2087', titre: 'Pneu arrière', travaux: 'Michelin Road 6 monté équilibré, réglage de la tension de chaîne.', montant: 145 },
];

const PT_DOCS = [
  { type: 'Facture', numero: 'FA-2026-0412', date: '12 juin 2026', montant: 168, poids: '84 Ko' },
  { type: 'Ordre de réparation', numero: 'OR-2318', date: '12 juin 2026', montant: null, poids: '112 Ko' },
  { type: 'État des lieux', numero: 'EDL-2318', date: '12 juin 2026', montant: null, poids: '1,4 Mo' },
  { type: 'Facture', numero: 'FA-2026-0118', date: '19 février 2026', montant: 189, poids: '82 Ko' },
  { type: 'Ordre de réparation', numero: 'OR-2204', date: '19 février 2026', montant: null, poids: '108 Ko' },
];

const PT_NAV = [
  { id: 'accueil', label: 'Accueil', icon: 'ri-home-5-line' },
  { id: 'suivi', label: 'Suivi', icon: 'ri-progress-4-line' },
  { id: 'messages', label: 'Messages', icon: 'ri-chat-3-line' },
  { id: 'moto', label: 'Ma moto', icon: 'ri-motorbike-line' },
  { id: 'documents', label: 'Documents', icon: 'ri-file-list-2-line' },
  { id: 'compte', label: 'Mon compte', icon: 'ri-user-line' },
];

/* Les pages légales. Le portail actuel en a quatre : CGV, clauses
   particulières, confidentialité, mentions. Elles sont versionnées côté API
   (`/api/clauses-legales`, champ `isActive`) — la version qui s'applique est
   celle en vigueur au moment du rendez-vous, pas la dernière publiée. */
const PT_LEGAL = {
  'legal-clauses': {
    titre: 'Clauses particulières',
    intro: 'Certaines prestations sont soumises à des clauses signées à la prise de rendez-vous. Voici celles qui s’appliquent à vos interventions.',
    version: 'Version 3 · en vigueur depuis le 1ᵉʳ mars 2026',
    regle: 'Un rendez-vous pris sous une version antérieure reste régi par cette version.',
    articles: [
      { titre: 'Immobilisation prolongée', cle: 'Au-delà de 30 jours, un forfait de gardiennage de 8 € par jour peut s’appliquer.', texte: 'Passé un délai de trente jours à compter de la mise à disposition du véhicule réparé, notifiée par SMS et par e-mail, l’atelier peut appliquer un forfait de gardiennage de 8 € par jour entamé. Ce forfait n’est pas dû si le retard est imputable à l’atelier ou à un fournisseur.' },
      { titre: 'Pièces fournies par le client', cle: 'L’atelier pose la pièce mais ne garantit ni la pièce ni le résultat de la pose.', texte: 'Lorsque le client fournit lui-même une pièce, l’atelier assure la main-d’œuvre sans garantie sur la pièce, sa compatibilité ni les conséquences de sa défaillance. La main-d’œuvre reste due si la pièce se révèle inadaptée en cours d’intervention.' },
      { titre: 'Travaux complémentaires', cle: 'Aucun travail non prévu n’est engagé sans votre accord, sauf sécurité immédiate.', texte: 'Tout travail non prévu au devis initial fait l’objet d’une demande d’accord, transmise par la messagerie de votre espace ou par téléphone. Sans réponse, le véhicule est restitué en l’état, les travaux prévus effectués. Font exception les interventions nécessaires à la sécurité immédiate du véhicule pour sa sortie de l’atelier.' },
      { titre: 'Pièces remplacées', cle: 'Vos pièces usées vous sont rendues sur demande, formulée avant l’intervention.', texte: 'Les pièces remplacées sont conservées quinze jours puis recyclées par une filière agréée. Elles sont remises au client sur demande formulée avant le début de l’intervention, à l’exception des pièces soumises à consigne ou à obligation de destruction.' },
      { titre: 'Essai sur route', cle: 'Un essai peut être nécessaire ; il est fait par un mécanicien assuré de l’atelier.', texte: 'Certaines interventions exigent un essai sur route, réalisé par un mécanicien de l’atelier couvert par l’assurance professionnelle. La distance parcourue est mentionnée sur l’ordre de réparation.' },
    ],
  },
  'legal-confidentialite': {
    titre: 'Politique de confidentialité',
    intro: 'Ce que l’atelier conserve, pourquoi, et combien de temps.',
    version: 'Version 2 · 12 janvier 2026',
    regle: 'Responsable de traitement : Motoblouz SAS. Délégué : dpo@motoblouz.com.',
    articles: [
      { titre: 'Ce que nous conservons', cle: 'Vos coordonnées, votre moto, l’historique des interventions et les factures.', texte: 'Nom, prénom, adresse postale, e-mail, téléphone ; immatriculation, modèle et kilométrage de votre véhicule ; les ordres de réparation, devis, factures et photos d’état des lieux liés à vos passages en atelier.' },
      { titre: 'Pourquoi', cle: 'Exécuter la prestation, vous joindre pendant l’intervention, tenir nos obligations comptables.', texte: 'Le traitement repose sur l’exécution du contrat de réparation pour vos coordonnées et votre véhicule, et sur nos obligations légales pour les factures et la traçabilité des pièces. Aucune donnée n’est utilisée à des fins publicitaires sans votre accord.' },
      { titre: 'Combien de temps', cle: 'Trois ans après votre dernier passage ; dix ans pour les pièces comptables.', texte: 'Vos coordonnées sont conservées trois ans à compter de votre dernier passage, puis anonymisées. Les factures et pièces comptables sont conservées dix ans, comme la loi l’exige. Les photos d’état des lieux sont supprimées un an après la restitution.' },
      { titre: 'Les photos d’état des lieux', cle: 'Elles servent à constater l’état du véhicule à la dépose et à la restitution.', texte: 'Les photos prises au comptoir constatent l’état du véhicule à l’entrée et à la sortie. Elles ne sont visibles que par vous et par le personnel de l’atelier concerné, ne sont jamais transmises à un tiers, et sont supprimées un an après la restitution.' },
      { titre: 'Vos droits', cle: 'Accès, rectification, effacement, portabilité — depuis votre espace ou par e-mail.', texte: 'Vous pouvez consulter et corriger vos informations depuis « Mon compte », demander l’effacement de votre compte au même endroit, et obtenir une copie de vos données par e-mail à dpo@motoblouz.com. Une réclamation peut être adressée à la CNIL.' },
    ],
  },
  'legal-cgv': {
    titre: 'Conditions générales',
    intro: 'Les conditions applicables aux prestations réalisées par l’atelier.',
    version: 'Version 4 · en vigueur depuis le 1ᵉʳ juin 2026',
    regle: 'Un rendez-vous pris sous une version antérieure reste régi par cette version.',
    articles: [
      { titre: 'Devis et accord', cle: 'Aucune intervention payante ne démarre sans un devis accepté.', texte: 'Un devis détaillé est établi avant toute intervention et transmis par la messagerie de votre espace. Il indique les prestations, les pièces, le taux horaire et le total TTC. L’accord peut être donné en ligne, au comptoir ou par téléphone ; il est tracé dans votre espace avec sa date et son heure.' },
      { titre: 'Délais', cle: 'La date de restitution est un engagement, sauf pièce indisponible.', texte: 'La date annoncée à la prise de rendez-vous est un engagement de l’atelier. En cas d’indisponibilité d’une pièce chez le fournisseur, vous êtes informé le jour même, avec une nouvelle date et la possibilité de récupérer le véhicule en l’état.' },
      { titre: 'Paiement', cle: 'À la restitution, par carte, espèces ou virement. Pas de paiement en ligne.', texte: 'Le règlement s’effectue à la restitution du véhicule, par carte bancaire, en espèces dans la limite légale, ou par virement reçu avant la remise des clés. Les interventions prises en charge par un tiers payeur (assurance, garantie, concession) font l’objet d’un accord préalable de ce tiers.' },
      { titre: 'Garantie', cle: 'Un an sur la main-d’œuvre, la garantie du fabricant sur les pièces.', texte: 'La main-d’œuvre est garantie un an. Les pièces neuves posées par l’atelier bénéficient de la garantie de leur fabricant. La garantie ne couvre pas l’usure normale, un défaut d’entretien postérieur, ni une utilisation en compétition.' },
      { titre: 'Restitution', cle: 'Vous signez l’état des lieux de sortie ; les réserves sont notées à ce moment-là.', texte: 'La restitution donne lieu à un état des lieux de sortie, photographié et signé. Toute réserve sur l’état du véhicule doit être formulée à cet instant. Passé la remise des clés, seule une réclamation portant sur l’intervention elle-même reste recevable.' },
    ],
  },
  'legal-mentions': {
    titre: 'Mentions légales',
    intro: 'L’éditeur de ce service et l’atelier qui réalise les prestations.',
    version: 'Mise à jour le 3 février 2026',
    regle: 'Hébergement : Scaleway SAS, 8 rue de la Ville l’Évêque, 75008 Paris.',
    articles: [
      { titre: 'Éditeur', cle: 'Motoblouz SAS, 2 rue des Champs, 59175 Templemars.', texte: 'Motoblouz SAS, société par actions simplifiée au capital de 1 000 000 €, immatriculée au RCS de Lille Métropole. Siège : 2 rue des Champs, 59175 Templemars. TVA intracommunautaire : FR00000000000.' },
      { titre: 'Atelier', cle: 'Paddock Lille — 12 rue de la Mécanique, 59000 Lille. 03 20 00 00 00.', texte: 'Les prestations sont réalisées par l’atelier Paddock Lille, 12 rue de la Mécanique, 59000 Lille. Téléphone : 03 20 00 00 00. Assurance professionnelle souscrite auprès d’AXA France, contrat n° 0000000, couvrant la responsabilité civile professionnelle et les véhicules confiés.' },
      { titre: 'Médiation', cle: 'En cas de litige non résolu, un médiateur de la consommation peut être saisi.', texte: 'Conformément au code de la consommation, tout litige n’ayant pas trouvé de solution amiable peut être soumis gratuitement au médiateur de la consommation dont les coordonnées sont affichées à l’accueil de l’atelier et communiquées sur demande.' },
    ],
  },
};

const ptEuro = (n) => n.toFixed(2).replace('.', ',') + ' €';
const ptKm = (n) => n.toLocaleString('fr-FR') + ' km';

Object.assign(window, { PT_CLIENT, PT_MOTO, PT_SUIVI, PT_DEVIS, PT_MESSAGES, PT_ECHEANCE, PT_CARNET, PT_DOCS, PT_NAV, PT_LEGAL, ptEuro, ptKm });
