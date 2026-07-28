<template>
  <div v-if="loadError" class="sejour-alert sejour-alert--error">
    <span><AppIcon name="i-ri-error-warning-line" /> Alerte séjour atelier indisponible.</span>
    <button class="sejour-link" @click="load">Réessayer</button>
  </div>

  <div v-else-if="motos.length" class="sejour-alert">
    <div class="sejour-head">
      <span class="sejour-icon"><AppIcon name="i-ri-timer-line" /></span>
      <span class="sejour-title">
        {{ motos.length }} moto{{ motos.length > 1 ? 's' : '' }} à l'atelier depuis plus de {{ seuil }} h ouvrées
      </span>
      <button class="sejour-link" @click="expanded = !expanded">
        {{ expanded ? 'Masquer' : 'Voir le détail' }}
      </button>
      <NuxtLink to="/en-atelier" class="sejour-link sejour-link--onglet">Suivi complet →</NuxtLink>
    </div>

    <ul v-if="expanded" class="sejour-list">
      <li v-for="moto in motosAffichees" :key="moto.rdv_id" class="sejour-item">
        <div class="sejour-item-main">
          <strong>{{ moto.plaque || 'Sans plaque' }}</strong>
          <span class="sejour-sep">·</span>
          <span>{{ moto.vehicule || 'Moto' }}</span>
          <span class="sejour-sep">·</span>
          <span>{{ moto.client_nom || 'Client inconnu' }}</span>
        </div>
        <div class="sejour-item-meta">
          <span class="sejour-duree">{{ formatDuree(moto.heures_ouvrees) }}</span>
          <span class="sejour-statut">{{ statutLabel(moto.statut) }}</span>
          <span>{{ moto.mecanicien || 'Non affecté' }}</span>
          <button class="sejour-link" @click="ouvrirFiche(moto)">Ouvrir la fiche</button>
        </div>
      </li>
      <li v-if="motos.length > motosAffichees.length" class="sejour-reste">
        … et {{ motos.length - motosAffichees.length }} autre{{ motos.length - motosAffichees.length > 1 ? 's' : '' }}
        (les plus anciennes sont affichées en premier)
      </li>
    </ul>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'

/**
 * Bandeau d'alerte « moto immobilisée à l'atelier depuis plus de N heures ouvrées ».
 * Les jours de fermeture (week-end, fériés, fermetures exceptionnelles) ne sont pas
 * comptés — le calcul est fait côté serveur (SejourAtelierService).
 */
interface MotoEnDepassement {
  rdv_id: number
  statut: string
  recu_le: string
  date_rdv: string
  heure_rdv: string
  type_intervention: string | null
  pont_nom: string | null
  heures_ouvrees: number
  jours_ouvres: number
  client_nom: string | null
  client_telephone: string | null
  vehicule: string | null
  plaque: string | null
  mecanicien: string | null
}

const props = withDefaults(defineProps<{ seuil?: number }>(), { seuil: 72 })

const REFRESH_MS = 5 * 60 * 1000
// Le bandeau reste un rappel : au-delà, la liste noierait la page hôte.
const MAX_LIGNES_AFFICHEES = 10

const api = useApi()
const { open: openRdvDetail } = useRdvDetailModal()

const motos = ref<MotoEnDepassement[]>([])
const loadError = ref(false)
const expanded = ref(false)
let timer: ReturnType<typeof setInterval> | null = null

// L'API renvoie déjà les motos triées de la plus ancienne à la plus récente.
const motosAffichees = computed(() => motos.value.slice(0, MAX_LIGNES_AFFICHEES))

const STATUT_LABELS: Record<string, string> = {
  reception: 'Réceptionnée',
  en_cours: 'En cours',
  en_pause: 'En pause',
  en_attente_pieces: 'Attente pièces',
  en_attente_reprise: 'Attente reprise',
  en_gardiennage: 'Gardiennage',
}

function statutLabel(statut: string): string {
  return STATUT_LABELS[statut] || statut
}

function formatDuree(heures: number): string {
  const jours = Math.floor(heures / 24)
  const reste = Math.round(heures % 24)
  return jours > 0 ? `${jours} j ${reste} h ouvrées` : `${Math.round(heures)} h ouvrées`
}

function ouvrirFiche(moto: MotoEnDepassement) {
  openRdvDetail({
    id: moto.rdv_id,
    statut: moto.statut,
    date_rdv: moto.date_rdv,
    heure_debut: moto.heure_rdv,
    type_intervention: moto.type_intervention || undefined,
    pont_nom: moto.pont_nom || undefined,
    client_nom: moto.client_nom || undefined,
    client_telephone: moto.client_telephone || undefined,
    vehicule_info: moto.vehicule || undefined,
    vehicule_plaque: moto.plaque || undefined,
    mecanicien_nom: moto.mecanicien || undefined,
  })
}

async function load() {
  try {
    const data = await api.get(`/sejour-atelier/alertes?seuil=${props.seuil}`)
    motos.value = data?.motos ?? []
    loadError.value = false
  } catch {
    // Bandeau secondaire : on signale l'échec sans casser la page hôte.
    loadError.value = true
  }
}

onMounted(() => {
  load()
  timer = setInterval(load, REFRESH_MS)
})

onUnmounted(() => {
  if (timer) clearInterval(timer)
})
</script>

<style scoped>
.sejour-alert {
  margin-bottom: 16px;
  padding: 10px 14px;
  border-radius: 12px;
  background: var(--warning-soft);
  border: 1px solid var(--warning);
}

.sejour-alert--error {
  display: flex;
  align-items: center;
  gap: 10px;
  background: var(--overlay-soft);
  border-color: var(--border-1);
  color: var(--content-3);
  font-size: 12px;
}

.sejour-head {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}

.sejour-icon {
  font-size: 15px;
}

.sejour-title {
  font-size: 13px;
  font-weight: 700;
  color: var(--warning-content);
}

.sejour-link {
  margin-left: auto;
  padding: 0;
  background: transparent;
  border: none;
  color: var(--accent-content);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  text-decoration: underline;
}

/* Le lien vers l'onglet suit le bouton « Voir le détail » : seul le premier
   élément après le titre pousse à droite. */
.sejour-link--onglet {
  margin-left: 0;
}

.sejour-list {
  margin: 10px 0 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.sejour-item {
  padding: 8px 10px;
  border-radius: 8px;
  background: rgba(0, 0, 0, 0.18);
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.sejour-item-main {
  font-size: 12px;
  color: var(--content-1);
}

.sejour-item-meta {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
  font-size: 11px;
  color: var(--content-3);
}

.sejour-sep {
  margin: 0 6px;
  color: var(--content-3);
}

.sejour-duree {
  font-weight: 700;
  color: var(--warning-content);
}

.sejour-reste {
  padding: 4px 10px;
  font-size: 11px;
  color: var(--content-3);
}

.sejour-statut {
  padding: 2px 8px;
  border-radius: 999px;
  background: var(--overlay-hover);
  color: var(--content-2);
}
</style>
