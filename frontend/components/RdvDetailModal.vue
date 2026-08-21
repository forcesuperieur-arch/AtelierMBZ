<template>
  <!-- La règle 4 du design system : le poste de travail ne se quitte pas. Le
       détail d'un rendez-vous s'ouvre en PANNEAU à droite, pas en modale — le
       planning reste lisible derrière, et on ne perd pas sa place dans la
       grille pour lire un numéro de téléphone. -->
  <AppSidePanel
    :open="isOpen"
    icon="i-ri-calendar-2-line"
    :title="titre"
    :subtitle="sousTitre"
    @close="close"
  >
    <template v-if="rdv">
      <AppPanelSection label="Le rendez-vous" :aside="formatMinutes(rdv.duree_estimee)">
        <div class="pk-rdv-quand">{{ heurePlage }}</div>
        <div class="pk-rdv-ligne">{{ formatDisplayDate(rdv.date_rdv) }}</div>
        <div v-if="rdv.pont_nom || rdv.mecanicien_nom" class="pk-rdv-ligne">
          <span v-if="rdv.pont_nom">{{ rdv.pont_nom }}</span>
          <span v-if="rdv.pont_nom && rdv.mecanicien_nom"> · </span>
          <span v-if="rdv.mecanicien_nom">{{ rdv.mecanicien_nom }}</span>
        </div>
        <div v-if="rdv.type_intervention" class="pk-rdv-chip">{{ rdv.type_intervention }}</div>
      </AppPanelSection>

      <AppPanelSection v-if="clientName !== 'Client' || rdv.client_telephone" label="Client">
        <div class="pk-rdv-nom">{{ clientName }}</div>
        <div v-if="rdv.client_telephone" class="pk-rdv-ligne">
          <AppIcon name="i-ri-phone-line" /> {{ rdv.client_telephone }}
        </div>
        <div v-if="rdv.client_email" class="pk-rdv-ligne">{{ rdv.client_email }}</div>
      </AppPanelSection>

      <AppPanelSection v-if="rdv.vehicule_info || rdv.vehicule_plaque" label="Moto">
        <div class="pk-rdv-nom">{{ rdv.vehicule_info || 'Véhicule' }}</div>
        <div v-if="rdv.vehicule_plaque" class="pk-rdv-ligne">
          <AppIcon name="i-ri-motorbike-line" /> {{ rdv.vehicule_plaque }}
        </div>
      </AppPanelSection>

      <!-- Le motif annoncé porte un filet jaune : c'est ce qu'a dit le client,
           et c'est la première chose que le mécanicien doit lire. -->
      <AppPanelSection v-if="motif" label="Motif annoncé">
        <p class="pk-rdv-motif">{{ motif }}</p>
      </AppPanelSection>

      <AppPanelSection v-if="rdv.commandes?.length" label="Commandes" :aside="`${rdv.commandes.length}`">
        <div class="pk-rdv-commandes">
          <span v-for="cmd in rdv.commandes" :key="cmd" class="pk-rdv-commande">{{ cmd }}</span>
        </div>
      </AppPanelSection>
    </template>

    <template #footer>
      <button class="btn btn-ghost" @click="close">Fermer le panneau</button>
    </template>
  </AppSidePanel>
</template>

<script setup lang="ts">
/**
 * Détail d'un rendez-vous — maquette 36a, passée de la modale au panneau.
 *
 * Le composant garde son nom et son composable : il est ouvert depuis quatre
 * écrans (planning, Ponts & Méca, poste mécanicien, fiche client), et le
 * renommer aurait fait diverger ces appels sans rien apporter.
 */
const { isOpen, rdvData: rdv, close } = useRdvDetailModal()

const clientName = computed(() => {
  if (!rdv.value) return 'Client'
  return [rdv.value.client_prenom, rdv.value.client_nom].filter(Boolean).join(' ') || 'Client'
})

const titre = computed(() => (rdv.value ? `RDV #${rdv.value.id}` : 'Rendez-vous'))

/** Véhicule · immat · client, sur une ligne — le sous-titre du contrat SidePanel. */
const sousTitre = computed(() => {
  if (!rdv.value) return ''
  return [rdv.value.vehicule_info, rdv.value.vehicule_plaque, clientName.value !== 'Client' ? clientName.value : '']
    .filter(Boolean)
    .join(' · ')
})

const heurePlage = computed(() => {
  const r = rdv.value
  if (!r?.heure_debut) return '—'
  const debut = String(r.heure_debut).slice(0, 5)
  const duree = Number(r.duree_estimee ?? 0)
  if (!duree) return debut
  const [h, m] = debut.split(':').map(Number)
  const fin = new Date(2000, 0, 1, h, m + duree)
  return `${debut} → ${String(fin.getHours()).padStart(2, '0')}:${String(fin.getMinutes()).padStart(2, '0')}`
})

const motif = computed(() => rdv.value?.description_probleme || rdv.value?.commentaire || '')

function formatDisplayDate(d: string | undefined) {
  if (!d) return '—'
  try {
    return new Date(`${d}T00:00:00`).toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' })
  } catch {
    return d
  }
}
</script>

<style scoped>
.pk-rdv-quand {
  font-size: 22px;
  font-weight: 500;
  letter-spacing: -0.015em;
  line-height: 1.15;
}

.pk-rdv-ligne {
  font-size: 13px;
  color: var(--pk-ink-quiet);
}

.pk-rdv-nom {
  font-size: 15px;
  font-weight: 600;
  color: var(--pk-ink);
}

.pk-rdv-chip {
  align-self: flex-start;
  padding: 4px 10px;
  border: 1px solid var(--pk-border);
  font-size: 12px;
}

.pk-rdv-motif {
  margin: 0;
  padding: 12px 14px;
  background: var(--pk-surface-raised);
  border-left: 3px solid var(--pk-accent);
  font-size: 14px;
  line-height: 1.5;
  white-space: pre-wrap;
}

.pk-rdv-commandes { display: flex; flex-wrap: wrap; gap: 6px; }

.pk-rdv-commande {
  padding: 3px 9px;
  border: 1px solid var(--pk-border);
  border-radius: var(--pk-radius-pill);
  font-size: 12px;
  color: var(--pk-ink-quiet);
}
</style>
