/** Libellés lisibles des statuts RDV côté client. */
const STATUT_LABELS: Record<string, string> = {
  en_attente: 'En attente de confirmation',
  reserve: 'Réservé',
  confirme: 'Confirmé',
  reception: 'Moto réceptionnée',
  en_cours: 'Travaux en cours',
  travaux_supp_demandes: 'Travaux supplémentaires proposés',
  travaux_supp_valides: 'Travaux supplémentaires validés',
  travaux_supp_en_cours: 'Travaux en cours',
  pret_restitution: 'Prête à récupérer',
  termine: 'Travaux terminés',
  restitue: 'Restituée',
  livre: 'Restituée',
  facture: 'Facturé',
  annule: 'Annulé',
}

export function rdvStatutLabel(statut?: string | null): string {
  if (!statut) return '—'
  return STATUT_LABELS[statut] ?? statut.replace(/_/g, ' ')
}
