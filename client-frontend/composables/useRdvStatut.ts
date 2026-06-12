/** Libellés lisibles des statuts RDV côté client. */
const STATUT_LABELS: Record<string, string> = {
  cree: 'Demande enregistrée',
  en_attente: 'En attente de confirmation',
  reserve: 'Réservé',
  confirme: 'Confirmé',
  reception: 'Moto réceptionnée',
  en_cours: 'Travaux en cours',
  en_pause: 'Travaux en pause',
  en_attente_pieces: 'En attente de pièces',
  en_attente_reprise: 'En attente de reprise des travaux',
  en_gardiennage: 'En gardiennage à l\'atelier',
  travaux_supp_demandes: 'Travaux supplémentaires proposés',
  travaux_supp_valides: 'Travaux supplémentaires validés',
  travaux_supp_en_cours: 'Travaux en cours',
  pret_restitution: 'Prête à récupérer',
  termine: 'Travaux terminés — moto prête',
  restitue: 'Restituée',
  restitue_partiel: 'Restitution partielle',
  livre: 'Restituée',
  no_show: 'Rendez-vous manqué',
  facture: 'Facturé',
  paye: 'Payé',
  annule: 'Annulé',
}

export function rdvStatutLabel(statut?: string | null): string {
  if (!statut) return '—'
  return STATUT_LABELS[statut] ?? statut.replace(/_/g, ' ')
}
