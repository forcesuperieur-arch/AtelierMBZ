const RAIL = [
  { id: 'stat', label: 'Stat', icon: 'ri-bar-chart-2-line' },
  { id: 'rdv', label: 'Prise de RDV', icon: 'ri-calendar-line' },
  { id: 'planning', label: 'Planning', icon: 'ri-calendar-2-line' },
  { id: 'reception', label: 'Réception', icon: 'ri-inbox-line' },
  { id: 'atelier', label: 'En atelier', icon: 'ri-hourglass-line', badge: 4 },
  { id: 'ponts', label: 'Ponts & Méca', icon: 'ri-tools-line' },
  { id: 'clients', label: 'Clients', icon: 'ri-group-line' },
  { id: 'motos', label: 'Fiches moto', icon: 'ri-motorbike-line' },
  { id: 'devis', label: 'Devis', icon: 'ri-draft-line' },
  { id: 'complementaires', label: 'Travaux compl.', icon: 'ri-hammer-line' },
  { id: 'messages', label: 'Messages', icon: 'ri-chat-3-line' },
  { id: 'rappels', label: 'Rappels', icon: 'ri-notification-3-line' },
  { id: 'factures', label: 'Factures', icon: 'ri-bank-card-line' },
  { id: 'stock', label: 'Stock', icon: 'ri-archive-line' },
];

const HOURS = ['08:00', '09:00', '10:00', '11:00', '12:00', '14:00', '15:00', '16:00', '17:00'];

const BAYS = [
  { name: 'Pont 1', assignee: 'Karim M.' },
  { name: 'Pont 2', assignee: 'Karim M.' },
  { name: 'Pont 3', assignee: 'Non affecté' },
  { name: 'Pont 4', assignee: 'Sophie L.' },
  { name: 'Pont 5', assignee: 'Thomas B.' },
  { name: 'Pont 6', assignee: 'Thomas B.' },
];

const APPOINTMENTS = [
  { id: 'renard-am', column: 2, row: 1, span: 2, state: 'received', statusLabel: '08:00 · réceptionnée', vehicle: 'MT-09 · Renard', detail: 'Révision 20 000' },
  { id: 'renard-pm', column: 2, row: 7, span: 2, state: 'ready', statusLabel: '15:30 · à restituer', vehicle: 'MT-09 · Renard', detail: 'Prête · essai validé', panel: 'restitution' },
  { id: 'belkacem', column: 3, row: 1, span: 3, state: 'confirmed', statusLabel: '08:30 · à réceptionner', vehicle: 'Tracer 9 · Belkacem', detail: 'Révision + plaquettes', panel: 'reception' },
  { id: 'amrani', column: 3, row: 6, span: 2, state: 'confirmed', statusLabel: '14:00 · confirmé', vehicle: 'Z650 · Amrani', detail: 'Révision annuelle' },
  { id: 'guerin', column: 4, row: 3, span: 2, state: 'unassigned', statusLabel: '10:30 · sans pont', vehicle: 'CB500F · Guérin', detail: 'Diagnostic' },
  { id: 'delaunay', column: 5, row: 1, span: 3, state: 'running', statusLabel: '08:00 · en cours', vehicle: 'MT-07 · Delaunay', detail: 'Travaux compl. en attente', detailTone: 'warning' },
  { id: 'vasseur', column: 5, row: 4, span: 2, state: 'conflict', icon: 'ri-error-warning-line', statusLabel: 'Conflit 11:00', vehicle: 'R1250 GS · Vasseur', detail: 'Chevauche 11:30' },
  { id: 'fournier', column: 6, row: 2, span: 4, state: 'running', statusLabel: '09:00 · en cours', vehicle: 'Africa Twin · Fournier', detail: 'Distribution · 2 j' },
  { id: 'ravel', column: 7, row: 6, span: 3, state: 'confirmed', statusLabel: '14:00 · confirmé', vehicle: 'Z900 · Ravel', detail: 'Pneus + vidange' },
];

const QUEUE = [
  { icon: 'ri-draft-line', level: 'critical', count: 3, title: 'Devis en attente de validation client', detail: 'Le plus ancien : 6 jours · seuil 3 jours' },
  { icon: 'ri-hammer-line', level: 'watch', count: 2, title: 'Travaux complémentaires à valider', detail: '2 clients à rappeler · moto immobilisée' },
  { icon: 'ri-key-2-line', level: 'normal', count: 2, title: 'Restitutions à préparer aujourd’hui', detail: 'Facture et signature à finaliser' },
];

const BAY_STATE = [
  { name: 'Pont 1', vehicle: 'Yamaha MT-09 · EX-421-QR', customer: 'Ludovic Renard', note: '2 RDV restants aujourd’hui' },
  { name: 'Pont 2', vehicle: 'Yamaha Tracer 9 · GT-908-ZK', customer: 'Nadia Belkacem', note: '1 RDV restant aujourd’hui' },
  { name: 'Pont 3', state: 'free', note: '3 RDV restants aujourd’hui' },
  { name: 'Pont 4', vehicle: 'Yamaha MT-07 · CD-114-VF', customer: 'Marc Delaunay', note: '0 RDV restant aujourd’hui' },
  { name: 'Pont 5', vehicle: 'Honda Africa Twin · AV-330-LP', customer: 'Élodie Fournier', note: '1 RDV restant aujourd’hui' },
  { name: 'Pont 6', vehicle: 'Kawasaki Z900 · BR-742-TM', customer: 'Julien Ravel', note: '2 RDV restants aujourd’hui' },
];

const MECHANICS = [
  { initials: 'KM', name: 'Karim M.', task: 'Pont 2 · révision Tracer 9', time: '1 h 10' },
  { initials: 'SL', name: 'Sophie L.', task: 'Pont 4 · pneus MT-07', time: '0 h 45' },
  { initials: 'TB', name: 'Thomas B.', task: 'Pont 5 · distribution Africa Twin', time: '2 h 05' },
];

const EXPLORER_ROWS = [
  { client: 'Nadia Belkacem', moto: 'Yamaha MT-125', last: 'mai 2024', spend: '168 €', due: 'oui', overdue: true },
  { client: 'Théo Lemaire', moto: 'Honda CB650R', last: 'juin 2024', spend: '312 €', due: 'oui', overdue: true },
  { client: 'Sarah Amrani', moto: 'Kawasaki Z650', last: 'juillet 2024', spend: '204 €', due: 'bientôt' },
  { client: 'Marc Delaunay', moto: 'Suzuki SV650', last: 'août 2024', spend: '189 €', due: 'oui', overdue: true },
  { client: 'Camille Perrot', moto: 'Honda CB500F', last: 'août 2024', spend: '276 €', due: 'bientôt' },
];

Object.assign(window, { RAIL, HOURS, BAYS, APPOINTMENTS, QUEUE, BAY_STATE, MECHANICS, EXPLORER_ROWS });
