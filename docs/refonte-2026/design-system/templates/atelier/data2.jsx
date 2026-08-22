const NAV_GROUPS = [
  { label: 'Pilotage', items: [
    { id: 'stat', label: 'Stat', icon: 'ri-bar-chart-2-line' },
    { id: 'cockpit', label: 'Cockpit SRC', icon: 'ri-radar-line' },
    { id: 'live', label: 'Suivi Live', icon: 'ri-eye-line' },
  ] },
  { label: 'Atelier', items: [
    { id: 'rdv', label: 'Prise de RDV', icon: 'ri-calendar-line' },
    { id: 'planning', label: 'Planning', icon: 'ri-calendar-2-line' },
    { id: 'reception', label: 'Réception', icon: 'ri-inbox-line' },
    { id: 'bench', label: 'En atelier', icon: 'ri-hourglass-line', badge: 4 },
    { id: 'restitution', label: 'Restitution', icon: 'ri-key-2-line', badge: 2 },
  { id: 'etat', label: 'État des lieux', icon: 'ri-camera-line' },
    { id: 'etat', label: 'État des lieux', icon: 'ri-camera-line' },
    { id: 'bays', label: 'Ponts & Méca', icon: 'ri-tools-line' },
    { id: 'travaux', label: 'Travaux compl.', icon: 'ri-hammer-line', badge: 2, badgeTone: 'error' },
  ] },
  { label: 'Commerce', items: [
    { id: 'clients', label: 'Clients', icon: 'ri-group-line' },
    { id: 'motos', label: 'Fiches moto', icon: 'ri-motorbike-line' },
    { id: 'devis', label: 'Devis', icon: 'ri-draft-line' },
    { id: 'complementaires', label: 'Travaux compl.', icon: 'ri-hammer-line' },
    { id: 'messages', label: 'Messages', icon: 'ri-chat-3-line' },
  { id: 'rappels', label: 'Rappels', icon: 'ri-notification-3-line' },
    { id: 'factures', label: 'Factures', icon: 'ri-bank-card-line' },
  ] },
];

const QUEUE_ITEMS = [
  { kind: 'Devis · 6 jours', icon: 'ri-draft-line', level: 'critical', count: 3, title: 'DV-2418 · Ludovic Renard', detail: 'La moto est réceptionnée, le devis non signé', actions: ['Faire signer sur place'] },
  { kind: 'Travaux compl. · 4 h', icon: 'ri-hammer-line', level: 'watch', count: 2, title: 'Marc Delaunay', detail: 'MT-07 · à valider avant montage', actions: ['Appeler', 'Ouvrir'] },
  { kind: 'Restitution · 17:00', icon: 'ri-key-2-line', level: 'normal', count: 2, title: 'Julien Ravel', detail: 'Z900 · facture à éditer' },
  { kind: 'Stock · 4 pièces', icon: 'ri-archive-line', level: 'normal', count: 4, title: 'Plaquettes avant Brembo', detail: '2 en stock · seuil 4' },
];

const CHECKINS = [
  { time: '08:00', duration: '2 h', customer: 'Ludovic Renard', vehicle: 'Yamaha MT-09', plate: 'EX-421-QR', work: 'Révision 20 000 km · devis DV-2418 en attente', state: 'signed' },
  { time: '08:30', duration: '3 h', customer: 'Nadia Belkacem', vehicle: 'Yamaha Tracer 9', plate: 'GT-908-ZK', work: 'Révision + plaquettes avant', state: 'todo' },
  { time: '09:00', duration: '1 h 30', customer: 'Marc Delaunay', vehicle: 'Yamaha MT-07', plate: 'CD-114-VF', work: 'Pneus avant + arrière', state: 'todo', flag: 'Travaux compl. en attente' },
  { time: '09:30', duration: '5 h', customer: 'Élodie Fournier', vehicle: 'Honda Africa Twin', plate: 'AV-330-LP', work: 'Distribution · immobilisation prévue 2 jours', state: 'signed' },
  { time: '10:30', duration: '2 h', customer: 'Pierre Guérin', vehicle: 'Honda CB500F', plate: 'DR-118-NX', work: 'Diagnostic électrique · pas de pont affecté', state: 'todo', unassigned: true },
  { time: '11:30', duration: '1 h', customer: 'Sarah Amrani', vehicle: 'Kawasaki Z650', plate: 'EL-447-TY', work: 'Révision annuelle', state: 'todo' },
  { time: '14:00', duration: '3 h', customer: 'Julien Ravel', vehicle: 'Kawasaki Z900', plate: 'BR-742-TM', work: 'Pneus + vidange · restitution à 17:00', state: 'signed' },
];

const BAY_CONTROL = [
  { name: 'Pont 1', state: 'occupied', spec: 'Type ATELIER · 350 kg', mechanic: 'Karim M.', programme: [
    { time: '08:00', label: 'MT-09 · Renard', status: 'Terminé', state: 'done' },
    { time: '11:00', label: 'Z900 · Ravel', status: 'En cours', state: 'running' },
  ] },
  { name: 'Pont 2', state: 'occupied', spec: 'Type ATELIER · 350 kg', mechanic: 'Karim M.', programme: [
    { time: '09:00', label: 'Tracer 9 · Belkacem', status: 'En cours', state: 'running' },
    { time: '15:00', label: 'Vespa GTS · Marchand', status: 'Réservé', state: 'booked' },
  ] },
  { name: 'Pont 3', state: 'free', spec: 'Type MOTO · 250 kg', programme: [
    { time: '10:00', label: 'CB500F · Guérin', status: 'Non affecté', state: 'unassigned' },
  ], note: '2 autres RDV à placer' },
  { name: 'Pont 4', state: 'conflict', spec: 'Type ATELIER · 350 kg', mechanic: 'Sophie L.', programme: [
    { time: '08:00', label: 'MT-07 · Delaunay', status: 'Terminé', state: 'done' },
    { time: '11:00', label: 'R1250 GS · chevauchement', status: 'À arbitrer', tone: 'error' },
  ] },
  { name: 'Pont 5', state: 'occupied', spec: 'Type ATELIER · 400 kg', mechanic: 'Thomas B.', programme: [
    { time: '09:00', label: 'Africa Twin · Fournier', status: 'En cours', state: 'running' },
  ], note: 'Immobilisation prévue 2 jours' },
  { name: 'Pont 6', state: 'maintenance', spec: 'Type ATELIER · 350 kg', mechanic: 'Thomas B.',
    note: 'Le pont est exclu du taux d’occupation et du planning tant qu’il est désactivé.' },
];

const CLIENTS = [
  { name: 'Renard Ludovic', phone: '06 12 44 98 07', email: 'l.renard@mail.fr', vehicles: 2, last: '15 août 2026', ca: '3 420 €' },
  { name: 'Belkacem Nadia', phone: '07 88 21 63 40', email: 'n.belkacem@mail.fr', vehicles: 1, last: '15 août 2026', ca: '1 980 €' },
  { name: 'Delaunay Marc', phone: '06 74 09 55 12', email: 'marc.delaunay@mail.fr', vehicles: 3, last: '15 août 2026', ca: '5 105 €' },
  { name: 'Fournier Élodie', phone: '06 33 12 87 90', email: 'e.fournier@mail.fr', vehicles: 1, last: '15 août 2026', ca: '2 740 €' },
  { name: 'Ravel Julien', phone: '07 61 40 22 18', email: 'j.ravel@mail.fr', vehicles: 2, last: '14 août 2026', ca: '6 890 €' },
  { name: 'Guérin Pierre', phone: '06 09 74 31 55', email: 'p.guerin@mail.fr', vehicles: 1, last: '12 août 2026', ca: '840 €' },
  { name: 'Amrani Sarah', phone: '07 22 63 90 04', email: 's.amrani@mail.fr', vehicles: 1, last: '08 août 2026', ca: '1 120 €' },
  { name: 'Marchand Céline', phone: '06 50 18 77 29', email: 'c.marchand@mail.fr', vehicles: 2, last: '02 août 2026', ca: '2 260 €' },
  { name: 'Vasseur Antoine', phone: '06 41 90 12 66', email: 'a.vasseur@mail.fr', vehicles: 1, last: '28 juillet 2026', ca: '1 495 €' },
  { name: 'Lacroix Hugo', phone: '07 03 55 84 71', email: 'h.lacroix@mail.fr', vehicles: 2, last: '19 juillet 2026', ca: '3 010 €' },
];

/* The collapsed rail carries the same ids as the expanded nav, so switching
   states never moves the selection. */
const RAIL_ITEMS = [
  { id: 'stat', label: 'Stat', icon: 'ri-bar-chart-2-line' },
  { id: 'rdv', label: 'Prise de RDV', icon: 'ri-calendar-line' },
  { id: 'planning', label: 'Planning', icon: 'ri-calendar-2-line' },
  { id: 'reception', label: 'Réception', icon: 'ri-inbox-line' },
  { id: 'bench', label: 'En atelier', icon: 'ri-hourglass-line', badge: 4 },
  { id: 'restitution', label: 'Restitution', icon: 'ri-key-2-line', badge: 2 },
  { id: 'etat', label: 'État des lieux', icon: 'ri-camera-line' },
  { id: 'bays', label: 'Ponts & Méca', icon: 'ri-tools-line' },
  { id: 'clients', label: 'Clients', icon: 'ri-group-line' },
  { id: 'motos', label: 'Fiches moto', icon: 'ri-motorbike-line' },
  { id: 'devis', label: 'Devis', icon: 'ri-draft-line' },
  { id: 'complementaires', label: 'Travaux compl.', icon: 'ri-hammer-line' },
  { id: 'messages', label: 'Messages', icon: 'ri-chat-3-line' },
  { id: 'rappels', label: 'Rappels', icon: 'ri-notification-3-line' },
  { id: 'factures', label: 'Factures', icon: 'ri-bank-card-line' },
  { id: 'stock', label: 'Stock', icon: 'ri-archive-line' },
  { id: 'explorer', label: 'Explorer', icon: 'ri-search-line' },
  { id: 'admin', label: 'Administration', icon: 'ri-settings-3-line' },
];

Object.assign(window, { NAV_GROUPS, QUEUE_ITEMS, CHECKINS, BAY_CONTROL, CLIENTS, RAIL_ITEMS });
