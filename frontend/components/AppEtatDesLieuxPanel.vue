<template>
  <AppSidePanel
    :open="open"
    icon="i-ri-camera-line"
    :title="`État des lieux · ${vehicule || 'moto'}`"
    :subtitle="sousTitre"
    @close="$emit('close')"
  >
    <AppLoadingState v-if="chargement" title="Chargement de l'état des lieux" :colonnes="3" :lignes="3" />

    <AppErrorState
      v-else-if="erreur"
      title="L'état des lieux n'a pas pu être chargé"
      :description="erreur"
      consequence="Les photos déjà prises ne sont pas perdues."
      action-label="Réessayer"
      @retry="charger"
    />

    <template v-else>
      <!-- ENTRÉE. La série qui fait preuve : c'est elle qu'on compare à la
           sortie le jour où un client conteste une rayure. -->
      <AppPanelSection label="Entrée" :aside="resumeEntree">
        <div class="edl-serie">
          <div v-for="(angle, i) in ANGLES" :key="`e-${angle}`" class="edl-vue">
            <div class="edl-cliche" :class="{ 'est-vide': !entree[i] }">
              <AppIcon :name="entree[i] ? 'i-ri-image-line' : 'i-ri-camera-line'" />
            </div>
            <span class="edl-angle" :class="{ 'est-manquant': !entree[i] }">{{ angle }}</span>
          </div>
        </div>
        <p v-if="signee" class="edl-signature">
          Signée par {{ etat?.signed_by || 'le client' }}<span v-if="dateSignature"> le {{ dateSignature }}</span>.
        </p>
      </AppPanelSection>

      <!-- SORTIE. Le même ordre, pour que la comparaison soit lisible sans
           effort au moment du litige. -->
      <AppPanelSection label="Sortie" :aside="`${sortie.length} / ${ANGLES.length}`">
        <div class="edl-serie">
          <div v-for="(angle, i) in ANGLES" :key="`s-${angle}`" class="edl-vue">
            <div class="edl-cliche" :class="{ 'est-vide': !sortie[i], 'est-a-prendre': !sortie[i] }">
              <AppIcon :name="sortie[i] ? 'i-ri-image-line' : 'i-ri-camera-line'" />
            </div>
            <span class="edl-angle" :class="{ 'est-manquant': !sortie[i] }">{{ angle }}</span>
          </div>
        </div>
      </AppPanelSection>

      <AppPanelSection v-if="etat?.observations" label="Réserves notées à l'entrée">
        <p class="edl-reserve">{{ etat.observations }}</p>
        <p class="edl-note">Reprises telles quelles sur l'OR et le bon de sortie.</p>
      </AppPanelSection>

      <AppPanelSection v-if="etat?.kilometrage || etat?.niveau_carburant" label="Relevés d'entrée">
        <div v-if="etat.kilometrage" class="edl-releve"><span>Compteur</span><strong>{{ formatKm(etat.kilometrage) }}</strong></div>
        <div v-if="etat.niveau_carburant" class="edl-releve"><span>Carburant</span><strong>{{ etat.niveau_carburant }}</strong></div>
      </AppPanelSection>

      <!-- La décision de 47b, écrite à l'écran : on ne bloque pas. -->
      <AppPanelSection>
        <div class="edl-avis">
          <AppIcon name="i-ri-information-line" class="edl-avis-glyphe" />
          <p class="edl-avis-texte">
            La restitution reste possible avec une série de sortie incomplète — elle sera
            inscrite sur le bon : « {{ sortie.length }} photos de sortie sur {{ ANGLES.length }} ».
            Bloquer la sortie d'une moto pour une photo manquante ferait perdre le client,
            pas gagner le litige.
          </p>
        </div>
      </AppPanelSection>
    </template>

    <template #footer>
      <button v-if="etat?.pdf_disponible" class="btn btn-primary" @click="imprimer">
        Imprimer l'état des lieux
      </button>
      <button class="btn btn-ghost" @click="$emit('close')">Fermer le panneau</button>
    </template>
  </AppSidePanel>
</template>

<script setup lang="ts">
/**
 * État des lieux photo — maquette 47b, en panneau ouvert depuis la Réception.
 *
 * « Six angles imposés, même ordre à l'entrée et à la sortie : c'est la
 * comparaison qui fait preuve. » Le modèle de données ne porte pas encore
 * l'angle sur chaque cliché — la colonne existe mais reste vide. L'ORDRE le
 * porte donc, et c'est exactement ce que dit la maquette : la n-ième photo
 * d'une série est le n-ième angle. Rien à changer au serveur pour ça.
 *
 * La décision de fond de 47b est écrite à l'écran plutôt que codée en dur dans
 * un blocage : une série de sortie incomplète n'empêche pas la restitution,
 * elle s'inscrit sur le bon. « Bloquer la sortie d'une moto pour une photo
 * manquante ferait perdre le client, pas gagner le litige. »
 */
const props = defineProps({
  open: { type: Boolean, default: false },
  rdvId: { type: [Number, String], default: null },
  vehicule: { type: String, default: '' },
  plaque: { type: String, default: '' },
  client: { type: String, default: '' },
})

defineEmits(['close'])

const api = useApi()
const { openPdf } = usePdfDownload()

/** Les six angles de 47b, dans l'ordre imposé. */
const ANGLES = ['Face avant', 'Flanc droit', 'Flanc gauche', 'Arrière', 'Compteur', 'Détail réserve']

const etat = ref<any>(null)
const photos = ref<any[]>([])
const chargement = ref(false)
const erreur = ref('')

const sousTitre = computed(() => [props.plaque, props.client].filter(Boolean).join(' · '))
const signee = computed(() => Boolean(etat.value?.signe))

const dateSignature = computed(() => {
  const brut = etat.value?.signed_at
  if (!brut) return ''
  try {
    return new Date(brut).toLocaleString('fr-FR', { day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit' })
  } catch { return '' }
})

const TYPES_ENTREE = ['checkin', 'reception']
const TYPES_SORTIE = ['restitution']

function serie(types: string[]) {
  return photos.value
    .filter((p: any) => types.includes(String(p.type || '')))
    .sort((a: any, b: any) => String(a.taken_at ?? a.created_at ?? '').localeCompare(String(b.taken_at ?? b.created_at ?? '')))
    .slice(0, ANGLES.length)
}

const entree = computed(() => serie(TYPES_ENTREE))
const sortie = computed(() => serie(TYPES_SORTIE))

const resumeEntree = computed(() => {
  const n = entree.value.length
  return signee.value ? `${n} / ${ANGLES.length} · signé` : `${n} / ${ANGLES.length}`
})

function formatKm(km: any) {
  const n = Number(km)
  return Number.isFinite(n) ? `${new Intl.NumberFormat('fr-FR').format(n)} km` : String(km)
}

async function charger() {
  if (!props.rdvId) return
  chargement.value = true
  erreur.value = ''
  try {
    const [e, p] = await Promise.all([
      api.get(`/rendez-vous/${props.rdvId}/etat-des-lieux`),
      api.get(`/photo_interventions?rendezVous=${props.rdvId}&itemsPerPage=60`).catch(() => null),
    ])
    etat.value = e
    const brut = p?.member ?? p?.['hydra:member'] ?? (Array.isArray(p) ? p : [])
    photos.value = brut
  } catch (err: any) {
    erreur.value = messageErreur(err, "l'état des lieux n'a pas pu être lu")
  } finally {
    chargement.value = false
  }
}

function imprimer() {
  if (etat.value?.id) openPdf(`/etat-des-lieux/${etat.value.id}/pdf`)
}

watch(() => [props.open, props.rdvId], ([ouvert]) => { if (ouvert) charger() }, { immediate: true })
</script>

<style scoped>
/* Trois colonnes plutôt que six : le panneau fait 456 px, et un cliché sous
   130 px de large ne montre plus la rayure qu'il est censé prouver. */
.edl-serie {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
}

.edl-vue { display: flex; flex-direction: column; gap: 5px; }

.edl-cliche {
  aspect-ratio: 4 / 3;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--pk-neutral-surface);
  border: 1px solid var(--pk-border);
  border-radius: var(--pk-radius-tile);
  font-size: 20px;
  color: var(--pk-ink-quiet);
}

/* Un emplacement vide est en pointillé et sans fond : le design system veut
   qu'« une chose vide » se distingue d'une chose présente mais sombre. */
.edl-cliche.est-vide {
  background: transparent;
  border: 2px dashed var(--pk-ink-muted);
  color: var(--pk-ink-muted);
}

.edl-angle { font-size: 12px; font-weight: 600; }
.edl-angle.est-manquant { font-weight: 400; color: var(--pk-ink-quiet); }

.edl-signature { margin: 0; font-size: 12px; color: var(--pk-success-ink); }
.edl-reserve { margin: 0; padding: 12px 14px; background: var(--pk-surface-raised); border-left: 3px solid var(--pk-warning-line); font-size: 14px; line-height: 1.5; white-space: pre-wrap; }
.edl-note { margin: 0; font-size: 12px; color: var(--pk-ink-muted); }

.edl-releve { display: flex; align-items: baseline; justify-content: space-between; font-size: 13px; color: var(--pk-ink-quiet); }
.edl-releve strong { font-size: 14px; font-weight: 600; color: var(--pk-ink); }

.edl-avis { display: flex; gap: 10px; padding: 12px 14px; background: var(--pk-surface-raised); border: 1px solid var(--pk-border); }
.edl-avis-glyphe { font-size: 18px; flex-shrink: 0; color: var(--pk-ink-quiet); }
.edl-avis-texte { margin: 0; font-size: 13px; line-height: 1.5; color: var(--pk-ink-quiet); }
</style>
