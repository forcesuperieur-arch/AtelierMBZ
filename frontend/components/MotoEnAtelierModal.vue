<template>
  <AppModal :open="open" size="lg" @update:open="onOpenChange">
    <template #header>
      <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
        <span style="font-size:16px;font-weight:700;color:var(--content-1);">
          {{ moto?.plaque || 'Moto' }} — {{ moto?.vehicule || 'Moto' }}
        </span>
        <StatusBadge v-if="moto" :status="moto.statut" />
        <span v-if="moto" class="anciennete" :class="{ 'anciennete--alerte': moto.en_depassement }">
          {{ formatDuree(moto.heures_ouvrees) }} ouvrées
        </span>
      </div>
    </template>

    <div v-if="moto" class="corps">
      <!-- Contexte -->
      <div class="bloc">
        <div class="bloc-grid">
          <div><span class="etiquette">Arrivée :</span> {{ formatDateHeure(moto.recu_le) }}</div>
          <div><span class="etiquette">RDV :</span> {{ formatDate(moto.date_rdv) }} à {{ moto.heure_rdv }}</div>
          <div><span class="etiquette">Type :</span> {{ moto.type_intervention || '—' }}</div>
          <div><span class="etiquette">Pont :</span> {{ moto.pont_nom || '—' }}</div>
        </div>
      </div>

      <!-- Client + appel -->
      <div class="bloc">
        <div class="bloc-titre">{{ moto.client_nom || 'Client inconnu' }}</div>
        <div class="ligne-actions">
          <a v-if="moto.client_telephone" :href="`tel:${moto.client_telephone}`" class="btn btn-primary btn-sm">
            📞 Appeler {{ moto.client_telephone }}
          </a>
          <span v-else class="note">Aucun numéro de téléphone enregistré.</span>
          <button v-if="moto.client_telephone" class="btn btn-ghost btn-sm" @click="copierTelephone">
            {{ telephoneCopie ? '✓ Copié' : 'Copier le numéro' }}
          </button>
        </div>
        <div v-if="moto.derniere_relance" class="note">
          Dernière relance envoyée le {{ formatDateHeure(moto.derniere_relance) }}.
        </div>
      </div>

      <!-- Relance client -->
      <div class="bloc">
        <div class="bloc-titre">Relancer le client</div>
        <textarea
          v-model="messageRelance"
          class="form-input"
          rows="2"
          maxlength="500"
          placeholder="Message ajouté à la relance (ex. : nous attendons votre accord pour les freins)"
        ></textarea>
        <div class="ligne-actions">
          <button class="btn btn-primary btn-sm" :disabled="relanceEnCours" @click="relancer('email')">
            {{ relanceEnCours ? 'Envoi…' : '✉ Relancer par e-mail' }}
          </button>
          <button class="btn btn-ghost btn-sm" :disabled="relanceEnCours" @click="relancer('sms')">
            💬 Relancer par SMS
          </button>
        </div>
      </div>

      <!-- Affectation mécanicien -->
      <div class="bloc">
        <div class="bloc-titre">Mécanicien</div>
        <div class="ligne-actions">
          <select v-model="mecanicienId" class="form-input" style="max-width:260px;">
            <option :value="null">Non affecté</option>
            <option v-for="meca in mecaniciens" :key="meca.id" :value="meca.id">
              {{ meca.prenom }} {{ meca.nom }}
            </option>
          </select>
          <button
            class="btn btn-primary btn-sm"
            :disabled="affectationEnCours || mecanicienId === mecanicienIdInitial"
            @click="affecterMecanicien"
          >
            {{ affectationEnCours ? 'Enregistrement…' : 'Enregistrer' }}
          </button>
          <span class="note">Actuel : {{ moto.mecanicien || 'non affecté' }}</span>
        </div>
      </div>

      <!-- Changement de statut -->
      <div class="bloc">
        <div class="bloc-titre">Faire avancer le dossier</div>
        <div v-if="transitionsChargement" class="note">Chargement des actions disponibles…</div>
        <div v-else-if="!transitions.length" class="note">
          Aucune action de statut disponible depuis « {{ statutLabel(moto.statut) }} »
          (les étapes bloquantes se traitent depuis Réception ou l'espace mécanicien).
        </div>
        <div v-else class="ligne-actions">
          <button
            v-for="transition in transitions"
            :key="transition"
            class="btn btn-ghost btn-sm"
            :disabled="transitionEnCours"
            @click="appliquerTransition(transition)"
          >
            {{ transitionLabel(transition) }}
          </button>
        </div>
      </div>
    </div>

    <template #footer>
      <div style="display:flex;justify-content:flex-end;gap:10px;">
        <button class="btn btn-ghost" @click="fermer">Fermer</button>
      </div>
    </template>
  </AppModal>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'

/**
 * Fiche actionnable d'une moto en atelier : appeler le client, le relancer,
 * (ré)affecter un mécanicien, faire avancer le statut. Les transitions proposées
 * viennent du serveur (mêmes gardes métier que le planning) — aucun statut n'est
 * deviné côté front.
 */
interface MotoEnAtelier {
  rdv_id: number
  statut: string
  recu_le: string
  date_rdv: string
  heure_rdv: string
  type_intervention: string | null
  pont_nom: string | null
  heures_ouvrees: number
  en_depassement: boolean
  client_nom: string | null
  client_telephone: string | null
  vehicule: string | null
  plaque: string | null
  mecanicien: string | null
  derniere_relance?: string | null
}

const props = defineProps<{ open: boolean; moto: MotoEnAtelier | null }>()
const emit = defineEmits<{ 'update:open': [boolean]; changed: [] }>()

const api = useApi()
const toast = useToast()

/** API toast du projet (Nuxt UI) : add({ title, description, color }). */
function notifier(title: string, description: string, color: 'success' | 'error') {
  toast.add({ title, description, color })
}

const messageRelance = ref('')
const relanceEnCours = ref(false)
const telephoneCopie = ref(false)
const mecaniciens = ref<any[]>([])
const mecanicienId = ref<number | null>(null)
const mecanicienIdInitial = ref<number | null>(null)
const affectationEnCours = ref(false)
const transitions = ref<string[]>([])
const transitionsChargement = ref(false)
const transitionEnCours = ref(false)

const STATUT_LABELS: Record<string, string> = {
  reception: 'Réceptionnée',
  en_cours: 'En cours',
  en_pause: 'En pause',
  en_attente_pieces: 'Attente pièces',
  en_attente_reprise: 'Attente reprise',
  en_gardiennage: 'Gardiennage',
}

// Libellés métier des transitions du workflow RDV (backend/config/packages/workflow.yaml).
// Le workflow expose plusieurs alias pour une même étape : on les libelle tous, et
// les doublons sont masqués par TRANSITIONS_REDONDANTES ci-dessous.
const TRANSITION_LABELS: Record<string, string> = {
  start_travail: '▶ Démarrer les travaux',
  mettre_en_pause: '⏸ Mettre en pause',
  pause_travail: '⏸ Mettre en pause',
  reprendre: '▶ Reprendre',
  reprendre_travail: '▶ Reprendre',
  attendre_pieces: '📦 En attente de pièces',
  mettre_en_attente_pieces: '📦 En attente de pièces',
  reprendre_apres_pieces: '📦 Pièces reçues, reprendre',
  mettre_en_attente_reprise: '⏭ Reprise remise à plus tard',
  reprendre_demain: '▶ Reprendre le travail',
  mettre_en_gardiennage: '🅿 Mettre en gardiennage',
  passer_gardiennage: '🅿 Mettre en gardiennage',
  sortir_gardiennage: '🅿 Sortir du gardiennage',
  terminer: '✓ Terminer',
  restituer: '🏍 Restituer',
  restituer_partiel: '🏍 Restitution partielle',
  reporter: '📅 Reporter le rendez-vous',
  annuler: '✕ Annuler',
  no_show: '✕ Client absent',
  declarer_no_show: '✕ Client absent',
  facturer: '🧾 Facturer',
}

/** Alias en doublon : on garde une seule entrée par action métier. */
const TRANSITIONS_REDONDANTES = ['pause_travail', 'reprendre_travail', 'mettre_en_attente_pieces', 'passer_gardiennage', 'declarer_no_show']

function statutLabel(statut: string): string {
  return STATUT_LABELS[statut] || statut
}

function transitionLabel(transition: string): string {
  return TRANSITION_LABELS[transition] || transition
}

function formatDuree(heures: number): string {
  const jours = Math.floor(heures / 24)
  const reste = Math.round(heures % 24)
  return jours > 0 ? `${jours} j ${reste} h` : `${Math.round(heures)} h`
}

function formatDate(valeur: string): string {
  if (!valeur) return '—'
  const d = new Date(valeur)
  return Number.isNaN(d.getTime()) ? valeur : d.toLocaleDateString('fr-FR')
}

function formatDateHeure(valeur: string): string {
  if (!valeur) return '—'
  const d = new Date(valeur)
  return Number.isNaN(d.getTime())
    ? valeur
    : d.toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

const rdvId = computed(() => props.moto?.rdv_id ?? null)

async function chargerContexte() {
  const id = rdvId.value
  if (!id) return

  messageRelance.value = ''
  telephoneCopie.value = false

  // Liste des mécaniciens (pagination complète, ordre stable) + mécanicien courant.
  if (!mecaniciens.value.length) {
    try {
      mecaniciens.value = await api.getAll('/mecaniciens?order[id]=asc')
    } catch {
      notifier('Mécaniciens indisponibles', 'La liste des mécaniciens n\'a pas pu être chargée.', 'error')
    }
  }
  const courant = mecaniciens.value.find(
    m => `${m.prenom} ${m.nom}`.trim() === (props.moto?.mecanicien || '').trim(),
  )
  mecanicienId.value = courant?.id ?? null
  mecanicienIdInitial.value = mecanicienId.value

  transitionsChargement.value = true
  try {
    const data = await api.get(`/rendez-vous/${id}/transitions`)
    const proposees: string[] = data?.transitions ?? []
    // Un alias n'est retiré que si l'action équivalente est aussi proposée.
    const libelles = new Set<string>()
    transitions.value = proposees.filter((t) => {
      if (TRANSITIONS_REDONDANTES.includes(t) && proposees.some(
        autre => autre !== t && TRANSITION_LABELS[autre] === TRANSITION_LABELS[t],
      )) return false
      const libelle = TRANSITION_LABELS[t] || t
      if (libelles.has(libelle)) return false
      libelles.add(libelle)
      return true
    })
  } catch {
    transitions.value = []
    notifier('Actions indisponibles', 'Les actions de statut n\'ont pas pu être chargées.', 'error')
  } finally {
    transitionsChargement.value = false
  }
}

watch(
  () => [props.open, rdvId.value],
  ([ouvert]) => {
    if (ouvert) chargerContexte()
  },
  { immediate: true },
)

async function copierTelephone() {
  const tel = props.moto?.client_telephone
  if (!tel) return
  try {
    await navigator.clipboard.writeText(tel)
    telephoneCopie.value = true
    setTimeout(() => { telephoneCopie.value = false }, 2000)
  } catch {
    notifier('Copie impossible', 'Le presse-papiers est inaccessible dans ce navigateur.', 'error')
  }
}

const ERREURS_RELANCE: Record<string, string> = {
  AUCUN_CANAL_DISPONIBLE: 'Ce client n\'a ni e-mail ni téléphone enregistré.',
  CLIENT_INCONNU: 'Aucun client rattaché à ce rendez-vous.',
  MOTO_PAS_EN_ATELIER: 'Cette moto n\'est plus en atelier — actualise la liste.',
  ENVOI_ECHOUE: 'L\'envoi a échoué (vérifie la configuration e-mail/SMS).',
  MESSAGE_TROP_LONG: 'Message trop long (500 caractères maximum).',
}

async function relancer(canal: 'email' | 'sms') {
  const id = rdvId.value
  if (!id || relanceEnCours.value) return

  relanceEnCours.value = true
  try {
    const res = await api.post(`/sejour-atelier/${id}/relancer`, {
      canal,
      message: messageRelance.value.trim(),
    })
    notifier('Relance envoyée', `Par ${res?.canal === 'sms' ? 'SMS' : 'e-mail'} à ${res?.destinataire}.`, 'success')
    messageRelance.value = ''
    emit('changed')
  } catch (e: any) {
    const code = e?.data?.error
    notifier('Relance impossible', ERREURS_RELANCE[code] || e?.data?.detail || e?.message || 'Envoi refusé.', 'error')
  } finally {
    relanceEnCours.value = false
  }
}

async function affecterMecanicien() {
  const id = rdvId.value
  if (!id || affectationEnCours.value) return

  affectationEnCours.value = true
  try {
    await api.patch(`/rendez-vous/${id}`, {
      mecanicien: mecanicienId.value ? `/api/mecaniciens/${mecanicienId.value}` : null,
    })
    notifier('Mécanicien mis à jour', mecanicienId.value ? 'Le dossier est réaffecté.' : 'Le dossier n\'est plus affecté.', 'success')
    mecanicienIdInitial.value = mecanicienId.value
    emit('changed')
  } catch (e: any) {
    notifier('Affectation impossible', e?.data?.error || e?.data?.detail || e?.message || 'Enregistrement refusé.', 'error')
  } finally {
    affectationEnCours.value = false
  }
}

async function appliquerTransition(transition: string) {
  const id = rdvId.value
  if (!id || transitionEnCours.value) return

  transitionEnCours.value = true
  try {
    await api.post(`/rendez-vous/${id}/transition/${transition}`, {})
    notifier('Dossier mis à jour', `${transitionLabel(transition)} appliqué.`, 'success')
    emit('changed')
    emit('update:open', false)
  } catch (e: any) {
    // Le serveur porte les gardes métier : on affiche son refus tel quel.
    notifier('Action refusée', e?.data?.error || e?.data?.message || e?.message || 'Le serveur a refusé cette action.', 'error')
  } finally {
    transitionEnCours.value = false
  }
}

function onOpenChange(valeur: boolean) {
  emit('update:open', valeur)
}

function fermer() {
  emit('update:open', false)
}
</script>

<style scoped>
.corps {
  display: flex;
  flex-direction: column;
  gap: 12px;
  font-size: 13px;
  color: var(--content-2);
}

.bloc {
  padding: 12px;
  border-radius: 8px;
  background: var(--overlay-soft);
  border: 1px solid var(--border-2);
}

.bloc-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}

.bloc-titre {
  font-weight: 600;
  color: var(--content-1);
  margin-bottom: 8px;
}

.etiquette {
  color: var(--content-3);
}

.ligne-actions {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 8px;
}

.btn-sm {
  padding: 6px 12px;
  font-size: 12px;
}

.note {
  font-size: 11px;
  color: var(--content-3);
}

.anciennete {
  padding: 2px 8px;
  border-radius: 999px;
  background: var(--overlay-hover);
  font-size: 11px;
  font-weight: 700;
  color: var(--content-2);
}

.anciennete--alerte {
  background: var(--warning-soft);
  color: var(--warning-content);
}

textarea.form-input {
  width: 100%;
  resize: vertical;
}
</style>
