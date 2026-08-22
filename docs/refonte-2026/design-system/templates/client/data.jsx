/* Données de démonstration du portail client. Elles reprennent la forme
   renvoyée par `/api/client/*` : véhicules avec seuil de vidange, RDV avec
   statut codé, frise, demandes de travaux, état des lieux et OR. */
const CL_MOTOS = [
  { id: 1, marque: 'Yamaha', modele: 'MT-07', plaque: 'EF-771-GH', annee: 2021, cylindree: '689 cm³', kilometrage: 19842, notes: '', prochaineVidange: { km: 19000, due: true } },
  { id: 2, marque: 'Honda', modele: 'CB500F', plaque: 'RT-204-BC', annee: 2019, cylindree: '471 cm³', kilometrage: 31250, notes: 'Kit chaîne changé en 2025.', prochaineVidange: { km: 34000, due: false } },
];

const CL_RDVS = [
  { id: 2431, date: 'mardi 26 août 08:00', vehicule: 'Yamaha MT-07 · EF-771-GH', statut: 'en_cours', futur: true, intervention: 'Révision 20 000 km', prixEstime: 289 },
  { id: 2444, date: 'jeudi 11 septembre 09:00', vehicule: 'Honda CB500F · RT-204-BC', statut: 'confirme', futur: true, intervention: 'Plaquettes avant', prixEstime: 74.9, annulationDemandee: true },
  { id: 2318, date: 'jeudi 12 juin 08:30', vehicule: 'Honda CB500F · RT-204-BC', statut: 'paye', futur: false, intervention: 'Plaquettes avant + purge', prixEstime: 168 },
  { id: 2204, date: 'mardi 19 février 08:00', vehicule: 'Yamaha MT-07 · EF-771-GH', statut: 'restitue', futur: false, intervention: 'Révision 10 000 km', prixEstime: 189 },
];

const CL_RDV_DETAIL = {
  id: 2431,
  date: 'mardi 26 août 2026 à 08:00',
  statut: 'en_cours',
  intervention: 'Révision 20 000 km',
  moto: 'Yamaha MT-07',
  plaque: 'EF-771-GH',
  prixEstime: 289,
  prestations: [
    { designation: 'Révision 20 000 km — huile, filtres, bougies', prix: 231.05 },
    { designation: 'Contrôle et réglage de chaîne', prix: 20.7 },
    { designation: 'Contrôle des 22 points constructeur', prix: 37.25 },
  ],
  timeline: [
    { statut: 'confirme', date: '19 août 14:12' },
    { statut: 'receptionne', date: '26 août 08:06' },
    { statut: 'en_cours', date: '26 août 09:30' },
  ],
  demande: {
    id: 88,
    urgence: 'urgent',
    description: 'Le disque arrière est voilé. Nous conseillons de le remplacer pendant que la moto est sur le pont : le refaire plus tard demande une nouvelle immobilisation.',
    prestations: [
      { designation: 'Disque de frein arrière', prix: 118 },
      { designation: 'Main d’œuvre · 1 h 00', prix: 50 },
    ],
    prixEstime: 168,
  },
  etatDesLieux: { signedAt: '26 août 08:12', kilometrage: 19842, carburant: 'Réserve', observations: 'Rayure carénage droit, 8 cm, signalée par le client. Top-case laissé sur la moto, vide.' },
  ordres: [{ id: 1, numero: 'OR-2431', type: 'Ordre de réparation' }],
  photos: 3,
};

const CL_HISTORIQUE = [
  { id: 1, date: '12 juin 2026', vehicule: 'Honda CB500F · RT-204-BC', numero: 'OR-2318', travaux: 'Remplacement plaquettes avant Brembo 07BB19, purge du circuit de frein, contrôle des durites.' },
  { id: 2, date: '19 février 2026', vehicule: 'Yamaha MT-07 · EF-771-GH', numero: 'OR-2204', travaux: 'Révision 10 000 km : huile Motul 7100, filtre HF204, filtre à air, contrôle des 22 points.' },
  { id: 3, date: '4 octobre 2025', vehicule: 'Yamaha MT-07 · EF-771-GH', numero: 'OR-2087', travaux: 'Pneu arrière Michelin Road 6 monté équilibré, réglage de la tension de chaîne.' },
];

const CL_PRESTATIONS = [
  { id: 1, nom: 'Révision constructeur', description: 'Selon le carnet de votre modèle', prix: 'dès 189 €' },
  { id: 2, nom: 'Vidange simple', description: 'Huile et filtre à huile', prix: '96 €' },
  { id: 3, nom: 'Plaquettes avant', description: 'Pièce et pose comprises', prix: '74,90 €' },
  { id: 4, nom: 'Pneu arrière monté équilibré', description: 'À la dimension de votre moto', prix: 'dès 145 €' },
  { id: 5, nom: 'Diagnostic panne', description: 'Déduit si la réparation est faite ici', prix: '59 €' },
];

const CL_SLOTS = ['08:00', '08:30', '09:00', '10:30', '14:00', '15:30', '16:00'];

Object.assign(window, { CL_MOTOS, CL_RDVS, CL_RDV_DETAIL, CL_HISTORIQUE, CL_PRESTATIONS, CL_SLOTS });
