<template>
  <div class="pk-devis">
    <div class="page-header">
      <div class="page-title">Devis</div>
      <div class="pk-espace" />
      <AppButton
        variant="primary"
        icon="i-ri-file-add-line"
        label="Rédiger un devis"
        data-testid="devis-nouveau"
        @click="ouvrirNouveauDevis"
      />
    </div>

    <!-- Règle 3 : tout compteur mène quelque part. Chaque mesure est un bouton
         qui pose le filtre correspondant sur la liste juste dessous. -->
    <div v-if="!loading && !loadError" class="pk-mesures" data-testid="devis-mesures">
      <button
        v-for="m in mesures"
        :key="m.cle"
        type="button"
        class="pk-mesure"
        :class="`pk-mesure--${m.ton}`"
        :aria-pressed="filterStatus === m.filtre"
        :data-testid="`devis-mesure-${m.cle}`"
        @click="filterStatus = m.filtre"
      >
        <span class="pk-surtitre">{{ m.titre }}</span>
        <span class="pk-mesure-chiffre">{{ m.chiffre }}</span>
        <span class="pk-mesure-detail">{{ m.detail }}</span>
      </button>
    </div>

    <div class="pk-barre">
      <button
        v-for="f in statusFilters"
        :key="f.value"
        type="button"
        class="pk-pilule"
        :class="{ 'pk-pilule--active': filterStatus === f.value }"
        :aria-pressed="filterStatus === f.value"
        :data-testid="`devis-filtre-${f.value}`"
        @click="filterStatus = f.value"
      >
        {{ f.label }} <span class="pk-pilule-compte">· {{ compteFiltre(f.value) }}</span>
      </button>

      <div class="pk-espace" />

      <input
        v-model="searchText"
        class="pk-recherche"
        type="search"
        aria-label="Chercher un devis par numéro ou par nom de client"
        placeholder="Numéro de devis, nom du client…"
        data-testid="devis-recherche"
      >

      <!-- Le tri n'est pas un détail de présentation : c'est la règle de lecture
           de l'écran. Un devis sans réponse depuis trois jours passe devant un
           devis d'hier, et l'utilisateur doit savoir pourquoi. -->
      <span class="pk-tri">
        <AppIcon name="i-ri-sort-desc" />
        Du plus ancien au plus récent
      </span>
    </div>

    <AppLoadingState v-if="loading" title="Lecture des devis en cours" :colonnes="6" :lignes="6" />

    <AppErrorState
      v-else-if="loadError"
      title="Liste des devis indisponible"
      :description="loadError"
      consequence="Aucun devis n'a été modifié : c'est leur lecture qui a échoué."
      action-label="Réessayer"
      @retry="loadDevis()"
    />

    <AppEmptyState
      v-else-if="!devisList.length"
      icon="i-ri-file-list-3-line"
      title="Aucun devis enregistré"
      description="Un devis part d'un client et de sa moto, puis des lignes de main d'œuvre et de pièces. Le premier se rédige depuis cet écran."
      action-label="Rédiger un devis"
      @action="ouvrirNouveauDevis"
    />

    <AppFilterEmptyState
      v-else-if="!filteredDevis.length"
      title="Aucun devis ne correspond"
      :nombre-filtres="nombreFiltres"
      :suggestion="suggestion"
      @retirer="retirerLeFiltreSuggere"
      @effacer="effacerLesFiltres"
    />

    <div v-else class="pk-liste" data-testid="devis-liste">
      <div class="pk-liste-defilement">
        <div class="pk-tete">
          <span>Numéro</span>
          <span>Client et moto</span>
          <span>Objet</span>
          <span class="pk-droite">Montant</span>
          <span class="pk-droite">Ancienneté</span>
          <span>Statut</span>
          <span />
        </div>

        <NuxtLink
          v-for="(d, i) in filteredDevis"
          :key="d.id"
          :to="`/devis/${d.id}`"
          class="pk-ligne"
          :class="[
            i % 2 === 1 ? 'pk-ligne--zebre' : '',
            `pk-ligne--${niveauAttente(d)}`,
          ]"
          data-testid="devis-ligne"
        >
          <span class="pk-numero">{{ d.numero_devis }}</span>

          <span class="pk-cellule">
            <span class="pk-cellule-forte">{{ clientDe(d) }}</span>
            <span class="pk-cellule-douce">{{ motoDe(d) }}</span>
          </span>

          <span class="pk-objet" :class="{ 'pk-objet--vide': !objetDe(d) }">
            {{ objetDe(d) || 'Aucune ligne — rien à envoyer' }}
            <span v-if="autresLignes(d)" class="pk-cellule-douce">+ {{ autresLignes(d) }} autre{{ autresLignes(d) > 1 ? 's' : '' }} ligne{{ autresLignes(d) > 1 ? 's' : '' }}</span>
          </span>

          <span class="pk-droite pk-montant">{{ formatCurrency(Number(d.total_ttc)) }}</span>

          <span
            class="pk-droite pk-attente"
            :class="`pk-attente--${niveauAttente(d)}`"
            :title="`Créé le ${formatDate(d.date_creation || d.dateCreation)}`"
          >
            {{ libelleAnciennete(d) }}
          </span>

          <span>
            <span class="pk-statut" :class="`pk-statut--${motStatut(d).ton}`">{{ motStatut(d).mot }}</span>
          </span>

          <span class="pk-chevron"><AppIcon name="i-ri-arrow-right-s-line" /></span>
        </NuxtLink>
      </div>

      <div class="pk-liste-pied">
        {{ filteredDevis.length }} devis affiché{{ filteredDevis.length > 1 ? 's' : '' }} ·
        le plus ancien en tête, c'est celui qui attend une réponse depuis le plus longtemps.
      </div>
    </div>

    <AppModal v-model:open="showNew" size="xl">
      <template #header>
        <span class="pk-modale-titre">Nouveau devis</span>
      </template>

      <div class="pk-form">
        <div class="pk-champ">
          <label class="pk-label" for="devis-client">Client</label>
          <input
            id="devis-client"
            v-model="newDevis.clientSearch"
            class="pk-input"
            placeholder="Nom ou prénom, au moins deux lettres"
            autocomplete="off"
            @input="searchClients"
          >
          <div v-if="clientResults.length" class="pk-resultats">
            <button
              v-for="c in clientResults"
              :key="c.id"
              type="button"
              class="pk-resultat"
              @click="selectClient(c)"
            >
              <span class="pk-cellule-forte">{{ c.prenom }} {{ c.nom }}</span>
              <span class="pk-cellule-douce">{{ c.telephone }}</span>
            </button>
          </div>
          <p v-if="newDevis.selectedClient" class="pk-choisi">
            <AppIcon name="i-ri-check-line" />
            {{ newDevis.selectedClient.prenom }} {{ newDevis.selectedClient.nom }}
          </p>
          <AppFieldError :message="erreurClient" />
        </div>

        <div class="pk-champ">
          <label class="pk-label" for="devis-vehicule">Moto</label>
          <select
            id="devis-vehicule"
            v-model="newDevis.vehiculeId"
            class="pk-input"
            :disabled="!newDevis.selectedClient || loadingVehicules"
          >
            <option :value="null">{{ libelleChoixMoto }}</option>
            <option v-for="v in clientVehicules" :key="v.id" :value="v.id">
              {{ v.marque }} {{ v.modele }} — {{ v.plaque }}
            </option>
          </select>
          <p class="pk-aide">Sans moto rattachée, le devis reste chiffrable, mais il ne remontera pas dans le carnet de la machine.</p>
        </div>

        <div class="pk-champ">
          <label class="pk-label" for="devis-km">Kilométrage au compteur</label>
          <div class="pk-unite">
            <input id="devis-km" v-model.number="newDevis.kilometrage" type="number" class="pk-input" placeholder="28 412" min="0">
            <span class="pk-unite-mot">km</span>
          </div>
        </div>

        <div class="pk-champ">
          <span class="pk-label">Lignes du devis</span>

          <div class="pk-lignes-defilement">
            <div v-for="(ligne, i) in newDevis.lignes" :key="i" class="pk-ligne-saisie">
              <select v-model="ligne.type" class="pk-input" aria-label="Nature de la ligne">
                <option value="forfait_mo">Forfait</option>
                <option value="main_oeuvre_libre">Main d'œuvre</option>
                <option value="piece">Pièce</option>
              </select>
              <input v-model="ligne.designation" class="pk-input" placeholder="Ce qui est fait, ou la pièce posée" aria-label="Désignation de la ligne">
              <input v-model.number="ligne.quantite" type="number" class="pk-input pk-centre" placeholder="1" min="1" aria-label="Quantité">
              <input v-model.number="ligne.prix_unitaire_ht" type="number" class="pk-input pk-droite" placeholder="0,00" step="0.01" aria-label="Prix unitaire hors taxes">
              <select v-model.number="ligne.tva" class="pk-input" aria-label="Taux de TVA">
                <option :value="20">TVA 20&nbsp;%</option>
                <option :value="10">TVA 10&nbsp;%</option>
                <option :value="0">TVA 0&nbsp;%</option>
              </select>
              <button type="button" class="pk-retirer" :aria-label="`Retirer la ligne ${i + 1}`" @click="retirerLigne(i)">
                <AppIcon name="i-ri-close-line" />
              </button>
            </div>
          </div>

          <div class="pk-lignes-pied">
            <AppButton variant="ghost" size="sm" icon="i-ri-add-line" label="Ajouter une ligne" @click="ajouterLigne" />
            <div class="pk-espace" />
            <span class="pk-total">Total HT · {{ formatCurrency(totalHtSaisi) }}</span>
          </div>
          <AppFieldError :message="erreurLignes" />
        </div>

        <div class="pk-champ">
          <label class="pk-label" for="devis-remise">Remise sur le total</label>
          <div class="pk-remise">
            <input id="devis-remise" v-model.number="newDevis.remise" type="range" min="0" max="100" class="pk-curseur">
            <span class="pk-remise-valeur">{{ newDevis.remise }}&nbsp;%</span>
          </div>
        </div>

        <div class="pk-champ">
          <label class="pk-label" for="devis-notes">Note lue par le client</label>
          <textarea id="devis-notes" v-model="newDevis.notes_client" class="pk-input" rows="2" placeholder="Ce que le client doit savoir avant de répondre" />
        </div>
      </div>

      <template #footer>
        <AppButton variant="ghost" label="Annuler" @click="showNew = false" />
        <AppButton
          variant="primary"
          :label="submitting ? 'Enregistrement…' : 'Enregistrer le devis en brouillon'"
          :loading="submitting"
          data-testid="devis-enregistrer"
          @click="submitDevis"
        />
      </template>
    </AppModal>
  </div>
</template>

<script setup lang="ts">
const api = useApi()
const toast = useToast()
const loading = ref(true)
const loadError = ref('')
const devisList = ref<any[]>([])
const showNew = ref(false)
const submitting = ref(false)
const filterStatus = ref('all')
const clientResults = ref<any[]>([])
const searchText = ref('')

/** Un jour civil, en millisecondes. */
const JOUR_MS = 24 * 60 * 60 * 1000
/** Au-delà, un devis envoyé sans réponse entre dans « À relancer ». */
const SEUIL_RELANCE_JOURS = 2
/** Au-delà, l'attente n'est plus une réserve : c'est une affaire qui se perd. */
const SEUIL_URGENCE_JOURS = 4

const statusFilters = [
  { value: 'all', label: 'Tous' },
  { value: 'a_relancer', label: 'À relancer' },
  { value: 'brouillon', label: 'Brouillons' },
  { value: 'envoye', label: 'Envoyés' },
  { value: 'accepte', label: 'Acceptés' },
  { value: 'refuse', label: 'Refusés' },
  { value: 'expire', label: 'Périmés' },
]

/**
 * Le mot de statut, en vocabulaire d'atelier, avec le trio de couleurs qui va
 * avec. Les CAPITALES sont proscrites partout sauf ici et sur les surtitres.
 */
const MOTS_STATUT: Record<string, { mot: string; ton: string }> = {
  brouillon: { mot: 'Brouillon', ton: 'neutre' },
  envoye: { mot: 'Envoyé', ton: 'info' },
  accepte: { mot: 'Accepté', ton: 'succes' },
  refuse: { mot: 'Refusé', ton: 'erreur' },
  expire: { mot: 'Périmé', ton: 'reserve' },
  converti: { mot: 'Converti', ton: 'succes' },
}

const newDevis = reactive({
  clientSearch: '',
  selectedClient: null as any,
  vehiculeId: null as number | null,
  kilometrage: null as number | null,
  lignes: [{ type: 'forfait_mo', designation: '', quantite: 1, prix_unitaire_ht: 0, tva: 20 }] as any[],
  notes_client: '',
  remise: 0,
})

const clientVehicules = ref<any[]>([])
const loadingVehicules = ref(false)
const erreurClient = ref('')
const erreurLignes = ref('')

/**
 * L'instant de référence de l'ancienneté, figé au chargement. Recalculer à
 * chaque rendu ferait varier un chiffre affiché sans que rien ne se passe.
 */
const maintenant = ref(Date.now())

function debutDeJournee(ms: number): number {
  const d = new Date(ms)
  d.setHours(0, 0, 0, 0)
  return d.getTime()
}

/** Nombre de jours civils écoulés depuis la création du devis. */
function ancienneteEnJours(d: any): number | null {
  const brut = d?.date_creation || d?.dateCreation
  if (!brut) return null
  const t = new Date(brut).getTime()
  if (Number.isNaN(t)) return null
  return Math.max(0, Math.round((debutDeJournee(maintenant.value) - debutDeJournee(t)) / JOUR_MS))
}

function libelleAnciennete(d: any): string {
  const j = ancienneteEnJours(d)
  if (j === null) return '—'
  if (j === 0) return "aujourd'hui"
  return `${j} jour${j > 1 ? 's' : ''}`
}

/**
 * Le ton de la ligne ne dépend pas de l'âge seul : un devis accepté ou refusé
 * n'attend plus rien de personne. Seul l'envoyé sans réponse se colore.
 */
function niveauAttente(d: any): string {
  if (d?.statut !== 'envoye') return 'calme'
  const j = ancienneteEnJours(d)
  if (j === null) return 'calme'
  if (j > SEUIL_URGENCE_JOURS) return 'urgent'
  if (j > SEUIL_RELANCE_JOURS) return 'reserve'
  return 'calme'
}

function estARelancer(d: any): boolean {
  return d?.statut === 'envoye' && (ancienneteEnJours(d) ?? 0) > SEUIL_RELANCE_JOURS
}

function motStatut(d: any): { mot: string; ton: string } {
  return MOTS_STATUT[d?.statut] || { mot: d?.statut || 'Inconnu', ton: 'neutre' }
}

/**
 * Le nom composé au chargement d'abord ; à défaut l'instantané figé à la
 * création, qui survit à l'effacement RGPD de la fiche client.
 */
function clientDe(d: any): string {
  if (d?.client_nom?.trim()) return d.client_nom
  const snap = [d?.snap_client_prenom, d?.snap_client_nom].filter(Boolean).join(' ')
  return snap || 'Client non retrouvé'
}

/** La moto vient de l'instantané figé à la création : c'est celle du devis. */
function motoDe(d: any): string {
  const modele = [d?.snap_vehicule_marque, d?.snap_vehicule_modele].filter(Boolean).join(' ')
  const immat = d?.snap_vehicule_plaque
  if (modele && immat) return `${modele} · ${immat}`
  return modele || immat || 'Sans moto rattachée'
}

function lignesDe(d: any): any[] {
  return Array.isArray(d?.lignes) ? d.lignes.filter((l: any) => l && typeof l === 'object' && l.designation) : []
}

function objetDe(d: any): string {
  return lignesDe(d)[0]?.designation || ''
}

function autresLignes(d: any): number {
  return Math.max(0, lignesDe(d).length - 1)
}

function sommeTtc(liste: any[]): number {
  return liste.reduce((total, d) => total + Number(d?.total_ttc || 0), 0)
}

function parStatut(liste: any[]): any[] {
  if (filterStatus.value === 'all') return liste
  if (filterStatus.value === 'a_relancer') return liste.filter(estARelancer)
  return liste.filter(d => d.statut === filterStatus.value)
}

function parRecherche(liste: any[]): any[] {
  const q = searchText.value.toLowerCase().trim()
  if (!q) return liste
  return liste.filter(d => `${d.numero_devis || ''} ${d.client_nom || ''}`.toLowerCase().includes(q))
}

/**
 * Tri par ANCIENNETÉ, et non par date de création décroissante : un devis sans
 * réponse depuis trois jours passe devant un devis d'hier. Le tri se fait ici,
 * sur la liste déjà chargée — l'appel serveur, lui, ne bouge pas.
 */
function parAnciennete(liste: any[]): any[] {
  return [...liste].sort((a, b) => {
    const ta = new Date(a.date_creation || a.dateCreation || 0).getTime() || 0
    const tb = new Date(b.date_creation || b.dateCreation || 0).getTime() || 0
    return ta - tb
  })
}

const filteredDevis = computed(() => parAnciennete(parRecherche(parStatut(devisList.value))))

function compteFiltre(valeur: string): number {
  if (valeur === 'all') return devisList.value.length
  if (valeur === 'a_relancer') return devisList.value.filter(estARelancer).length
  return devisList.value.filter(d => d.statut === valeur).length
}

const mesures = computed(() => {
  const envoyes = devisList.value.filter(d => d.statut === 'envoye')
  const aRelancer = envoyes.filter(estARelancer)
  const acceptes = devisList.value.filter(d => d.statut === 'accepte')
  const brouillons = devisList.value.filter(d => d.statut === 'brouillon')
  const plusVieuxBrouillon = brouillons.reduce((max, d) => Math.max(max, ancienneteEnJours(d) ?? 0), 0)

  return [
    {
      cle: 'attente',
      filtre: 'envoye',
      ton: 'neutre',
      titre: 'En attente de réponse',
      chiffre: envoyes.length,
      detail: `${formatEurosRonds(sommeTtc(envoyes))} engagés`,
    },
    {
      cle: 'relance',
      filtre: 'a_relancer',
      ton: 'erreur',
      titre: 'À relancer',
      chiffre: aRelancer.length,
      detail: `sans réponse depuis plus de ${SEUIL_RELANCE_JOURS} jours`,
    },
    {
      cle: 'accepte',
      filtre: 'accepte',
      ton: 'succes',
      titre: 'Acceptés',
      chiffre: acceptes.length,
      detail: acceptes.length
        ? `${formatEurosRonds(sommeTtc(acceptes))} à poser au planning`
        : 'rien à poser au planning',
    },
    {
      cle: 'brouillon',
      filtre: 'brouillon',
      ton: 'neutre',
      titre: 'Brouillons',
      chiffre: brouillons.length,
      detail: brouillons.length
        ? `le plus ancien attend d'être envoyé depuis ${plusVieuxBrouillon} jour${plusVieuxBrouillon > 1 ? 's' : ''}`
        : 'rien en attente d\'envoi',
    },
  ]
})

const nombreFiltres = computed(() => (filterStatus.value !== 'all' ? 1 : 0) + (searchText.value.trim() ? 1 : 0))

/**
 * 29b : dire quel retrait rapporte le plus, chiffré. Sans ce chiffre,
 * l'utilisateur retire ses filtres au hasard jusqu'à ce que ça revienne.
 */
const suggestion = computed(() => {
  const candidats: Array<{ cle: string; filtre: string; nombre: number; objet: string }> = []

  if (searchText.value.trim()) {
    candidats.push({
      cle: 'recherche',
      filtre: `Recherche « ${searchText.value.trim()} »`,
      nombre: parStatut(devisList.value).length,
      objet: 'devis',
    })
  }
  if (filterStatus.value !== 'all') {
    const libelle = statusFilters.find(f => f.value === filterStatus.value)?.label || filterStatus.value
    candidats.push({
      cle: 'statut',
      filtre: libelle,
      nombre: parRecherche(devisList.value).length,
      objet: 'devis',
    })
  }

  const meilleur = candidats.filter(c => c.nombre > 0).sort((a, b) => b.nombre - a.nombre)[0]
  return meilleur || null
})

function retirerLeFiltreSuggere() {
  const s = suggestion.value
  if (!s) return
  if (s.cle === 'recherche') searchText.value = ''
  else filterStatus.value = 'all'
}

function effacerLesFiltres() {
  searchText.value = ''
  filterStatus.value = 'all'
}

const totalHtSaisi = computed(() =>
  newDevis.lignes.reduce((s, l) => s + (l.prix_unitaire_ht || 0) * (l.quantite || 1), 0),
)

const libelleChoixMoto = computed(() => {
  if (!newDevis.selectedClient) return 'Choisissez d\'abord le client'
  if (loadingVehicules.value) return 'Lecture du parc du client…'
  return clientVehicules.value.length ? 'Aucune moto rattachée' : 'Ce client n\'a aucune moto enregistrée'
})

function formatCurrency(v: number) {
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(v || 0)
}

/** Les mesures se lisent de loin : les centimes y sont du bruit. */
function formatEurosRonds(v: number) {
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(v || 0)
}

function formatDate(d: string) {
  if (!d) return ''
  try { return new Date(d).toLocaleDateString('fr-FR') } catch { return d }
}

let searchTimer: any = null
function searchClients() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(async () => {
    if (newDevis.clientSearch.length < 2) { clientResults.value = []; return }
    try {
      const data = await api.get(`/clients?search=${encodeURIComponent(newDevis.clientSearch)}`)
      clientResults.value = data?.['hydra:member'] ?? data?.member ?? (Array.isArray(data) ? data : [])
    } catch { clientResults.value = [] }
  }, 300)
}

async function selectClient(c: any) {
  newDevis.selectedClient = c
  newDevis.clientSearch = `${c.prenom} ${c.nom}`
  clientResults.value = []
  erreurClient.value = ''

  // Le véhicule d'un devis doit appartenir au client choisi (garde côté serveur aussi) :
  // on ne propose que ses motos, pas une recherche libre sur tout le parc.
  newDevis.vehiculeId = null
  clientVehicules.value = []
  loadingVehicules.value = true
  try {
    const data = await api.get(`/vehicules?client=${c.id}`)
    clientVehicules.value = data?.['hydra:member'] ?? data?.member ?? (Array.isArray(data) ? data : [])
  } catch {
    clientVehicules.value = []
  } finally {
    loadingVehicules.value = false
  }
}

function ligneVierge() {
  return { type: 'forfait_mo', designation: '', quantite: 1, prix_unitaire_ht: 0, tva: 20 }
}

function ajouterLigne() {
  newDevis.lignes = [...newDevis.lignes, ligneVierge()]
}

function retirerLigne(i: number) {
  newDevis.lignes = newDevis.lignes.filter((_, index) => index !== i)
}

function ouvrirNouveauDevis() {
  erreurClient.value = ''
  erreurLignes.value = ''
  showNew.value = true
}

function resetNewDevisForm() {
  newDevis.clientSearch = ''
  newDevis.selectedClient = null
  newDevis.vehiculeId = null
  newDevis.kilometrage = null
  newDevis.lignes = [ligneVierge()]
  newDevis.notes_client = ''
  newDevis.remise = 0
  clientVehicules.value = []
  erreurClient.value = ''
  erreurLignes.value = ''
}

async function submitDevis() {
  erreurClient.value = ''
  erreurLignes.value = ''

  if (!newDevis.selectedClient) {
    erreurClient.value = 'Sans client, le devis ne peut être ni numéroté ni envoyé.'
    return
  }
  const lignesValides = newDevis.lignes.filter(l => l.designation?.trim())
  if (lignesValides.length === 0) {
    erreurLignes.value = 'Une ligne sans désignation n\'est pas comptée : un devis vide ne peut pas partir chez le client.'
    return
  }
  submitting.value = true
  try {
    const payload: any = {
      client: `/api/clients/${newDevis.selectedClient.id}`,
      vehicule: newDevis.vehiculeId ? `/api/vehicules/${newDevis.vehiculeId}` : null,
      kilometrage: newDevis.kilometrage,
      notes_client: newDevis.notes_client,
      remise_pourcentage: newDevis.remise || 0,
      lignes: lignesValides.map(l => ({
        type: l.type,
        designation: l.designation,
        quantite: l.quantite,
        prix_unitaire_ht: l.prix_unitaire_ht,
        taux_tva: l.tva ?? 20,
      })),
    }
    await api.post('/devis', payload)
    toast.add({ title: 'Devis enregistré en brouillon', description: 'Il reste à l\'envoyer depuis sa fiche.', color: 'success' })
    showNew.value = false
    resetNewDevisForm()
    await loadDevis()
  } catch (e: any) {
    toast.add({
      title: 'Enregistrement impossible',
      description: messageErreur(e, "le devis n'a pas été créé"),
      color: 'error',
    })
  } finally {
    submitting.value = false
  }
}

async function loadDevis() {
  try {
    const raw = await api.getAll('/devis?order[dateCreation]=desc')
    devisList.value = raw.map((d: any) => {
      const c = d.client
      return {
        ...d,
        client_nom: c ? `${c.prenom} ${c.nom}` : d.client_nom ?? '',
      }
    })
    maintenant.value = Date.now()
    loadError.value = ''
  } catch (e: any) {
    // Ne jamais avaler l'échec en silence : sinon la page affiche « aucun devis »
    // alors que la liste n'a simplement pas pu être chargée (réseau, 500, session).
    loadError.value = messageErreur(e, "la liste des devis n'a pas pu être lue")
  }
}

onMounted(async () => {
  await loadDevis()
  loading.value = false
})
</script>

<style scoped>
.pk-devis { color: var(--pk-ink); }
.pk-espace { flex: 1; }

.pk-surtitre {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--pk-ink-muted);
}

/* ---- Bandeau de mesures ---- */
.pk-mesures {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  margin-bottom: 16px;
  border: 1px solid var(--pk-border);
  border-radius: var(--pk-radius-card);
  overflow: hidden;
  background: var(--pk-surface);
}

.pk-mesure {
  display: flex;
  flex-direction: column;
  gap: 3px;
  min-height: var(--pk-target-desk);
  padding: 14px 20px;
  border: none;
  border-right: 1px solid var(--pk-border-quiet);
  background: var(--pk-surface);
  color: var(--pk-ink);
  font: inherit;
  text-align: left;
  cursor: pointer;
  transition: background var(--pk-duration-state) var(--pk-easing);
}
.pk-mesure:last-child { border-right: none; }
.pk-mesure:hover { background: var(--pk-surface-raised); }
.pk-mesure[aria-pressed='true'] { box-shadow: inset 0 -3px 0 var(--pk-accent); }
.pk-mesure:focus-visible {
  outline: var(--pk-focus-width) solid var(--pk-focus-ring);
  outline-offset: calc(var(--pk-focus-offset) * -1);
}

.pk-mesure-chiffre { font-size: 26px; font-weight: 700; line-height: 1; }
.pk-mesure-detail { font-size: 12px; color: var(--pk-ink-muted); }

.pk-mesure--erreur { background: var(--pk-error-surface); }
.pk-mesure--erreur .pk-surtitre,
.pk-mesure--erreur .pk-mesure-chiffre,
.pk-mesure--erreur .pk-mesure-detail { color: var(--pk-error-ink); }
.pk-mesure--erreur:hover { background: var(--pk-error-surface); }

.pk-mesure--succes .pk-mesure-chiffre { color: var(--pk-success-ink); }

/* ---- Barre de filtres ---- */
.pk-barre {
  display: flex;
  align-items: center;
  gap: var(--pk-target-gap);
  flex-wrap: wrap;
  padding: 10px 0 14px;
}

.pk-pilule {
  flex-shrink: 0;
  min-height: var(--pk-target-desk);
  padding: 0 14px;
  border: 1px solid var(--pk-border-control);
  border-radius: var(--pk-radius-pill);
  background: transparent;
  color: var(--pk-ink);
  font: inherit;
  font-size: 13px;
  white-space: nowrap;
  cursor: pointer;
  transition: background var(--pk-duration-state) var(--pk-easing),
              border-color var(--pk-duration-state) var(--pk-easing);
}
.pk-pilule:hover { border-color: var(--pk-border-strong); }
.pk-pilule--active {
  background: var(--pk-border-strong);
  border-color: var(--pk-border-strong);
  color: var(--pk-surface-raised);
  font-weight: 600;
}
.pk-pilule:focus-visible {
  outline: var(--pk-focus-width) solid var(--pk-focus-ring);
  outline-offset: var(--pk-focus-offset);
}
.pk-pilule-compte { color: inherit; opacity: 0.7; }

.pk-recherche {
  min-height: var(--pk-target-desk);
  width: 280px;
  max-width: 100%;
  padding: 0 12px;
  border: 1px solid var(--pk-border-control);
  border-radius: var(--pk-radius-card);
  background: var(--pk-surface-raised);
  color: var(--pk-ink);
  font: inherit;
  font-size: 13px;
}
.pk-recherche:focus-visible {
  outline: var(--pk-focus-width) solid var(--pk-focus-ring);
  outline-offset: var(--pk-focus-offset);
}

.pk-tri {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: var(--pk-ink-quiet);
  white-space: nowrap;
}

/* ---- Liste ---- */
.pk-liste {
  display: flex;
  flex-direction: column;
  border: 1px solid var(--pk-border);
  border-radius: var(--pk-radius-card);
  background: var(--pk-surface);
  overflow: hidden;
}

.pk-liste-defilement { overflow-x: auto; }

.pk-tete,
.pk-ligne {
  display: grid;
  grid-template-columns: 112px minmax(190px, 1.3fr) minmax(170px, 1fr) 110px 108px 96px 28px;
  align-items: center;
  gap: 12px;
  min-width: 900px;
  padding: 0 16px;
}

.pk-tete {
  min-height: 34px;
  border-bottom: 1px solid var(--pk-border);
  background: var(--pk-surface-raised);
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--pk-ink-muted);
}

.pk-ligne {
  min-height: var(--pk-target-desk);
  padding-top: 8px;
  padding-bottom: 8px;
  border-bottom: 1px solid var(--pk-border-quiet);
  font-size: 13px;
  color: var(--pk-ink);
  text-decoration: none;
  transition: background var(--pk-duration-state) var(--pk-easing);
}
.pk-ligne--zebre { background: var(--pk-surface-raised); }
.pk-ligne:hover { background: var(--pk-accent-soft); }
.pk-ligne:focus-visible {
  outline: var(--pk-focus-width) solid var(--pk-focus-ring);
  outline-offset: calc(var(--pk-focus-offset) * -1);
}

/* Le filet de gauche en ombre interne plutôt qu'en bordure : une bordure
   décalerait les colonnes des seules lignes en retard. */
.pk-ligne--reserve { box-shadow: inset 3px 0 0 var(--pk-warning-line); }
.pk-ligne--urgent { box-shadow: inset 3px 0 0 var(--pk-error-line); }

.pk-numero { font-weight: 600; }
.pk-cellule { display: flex; flex-direction: column; min-width: 0; }
.pk-cellule-forte { font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pk-cellule-douce { font-size: 12px; color: var(--pk-ink-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.pk-objet {
  display: flex;
  flex-direction: column;
  min-width: 0;
  color: var(--pk-ink-quiet);
  overflow: hidden;
  text-overflow: ellipsis;
}
.pk-objet--vide { color: var(--pk-warning-ink-soft); }

.pk-droite { text-align: right; }
.pk-montant { font-weight: 600; }

.pk-attente { color: var(--pk-ink-quiet); }
.pk-attente--reserve { color: var(--pk-warning-ink-soft); font-weight: 700; }
.pk-attente--urgent { color: var(--pk-error-ink); font-weight: 700; }

.pk-statut {
  display: inline-flex;
  align-items: center;
  padding: 3px 9px;
  border-radius: var(--pk-radius-pill);
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.02em;
  white-space: nowrap;
}
.pk-statut--neutre { background: var(--pk-neutral-surface); color: var(--pk-ink-quiet); }
.pk-statut--info { background: var(--pk-info-surface); color: var(--pk-info-ink); }
.pk-statut--succes { background: var(--pk-success-surface); color: var(--pk-success-ink); }
.pk-statut--erreur { background: var(--pk-error-surface); color: var(--pk-error-ink); }
.pk-statut--reserve { background: var(--pk-warning-surface); color: var(--pk-warning-ink); }

.pk-chevron { color: var(--pk-ink-muted); text-align: right; }

.pk-liste-pied {
  padding: 10px 16px;
  border-top: 1px solid var(--pk-border);
  background: var(--pk-surface-raised);
  font-size: 12px;
  color: var(--pk-ink-quiet);
}

/* ---- Modale de rédaction ---- */
.pk-modale-titre { font-size: 17px; font-weight: 500; letter-spacing: -0.015em; color: var(--pk-ink); }

.pk-form { display: flex; flex-direction: column; gap: 16px; color: var(--pk-ink); }
.pk-champ { display: flex; flex-direction: column; gap: 6px; }

.pk-label {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--pk-ink-muted);
}

.pk-aide { margin: 0; font-size: 12px; line-height: 1.45; color: var(--pk-ink-quiet); }

.pk-input {
  width: 100%;
  min-height: var(--pk-target-desk);
  padding: 10px 12px;
  border: 1px solid var(--pk-border-control);
  border-radius: var(--pk-radius-card);
  background: var(--pk-surface-raised);
  color: var(--pk-ink);
  font: inherit;
  font-size: 14px;
}
.pk-input:focus-visible {
  outline: var(--pk-focus-width) solid var(--pk-focus-ring);
  outline-offset: var(--pk-focus-offset);
}
.pk-input:disabled { color: var(--pk-ink-muted); cursor: not-allowed; }
textarea.pk-input { min-height: 0; resize: vertical; }

.pk-resultats {
  margin-top: 4px;
  max-height: 180px;
  overflow-y: auto;
  border: 1px solid var(--pk-border);
  border-radius: var(--pk-radius-card);
  background: var(--pk-surface-raised);
}

.pk-resultat {
  display: flex;
  align-items: baseline;
  gap: 10px;
  width: 100%;
  min-height: var(--pk-target-desk);
  padding: 8px 12px;
  border: none;
  border-bottom: 1px solid var(--pk-border-quiet);
  background: transparent;
  color: var(--pk-ink);
  font: inherit;
  font-size: 13px;
  text-align: left;
  cursor: pointer;
}
.pk-resultat:last-child { border-bottom: none; }
.pk-resultat:hover { background: var(--pk-accent-soft); }
.pk-resultat:focus-visible {
  outline: var(--pk-focus-width) solid var(--pk-focus-ring);
  outline-offset: calc(var(--pk-focus-offset) * -1);
}

.pk-choisi {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 0;
  font-size: 13px;
  color: var(--pk-success-ink);
}

.pk-unite { display: flex; align-items: center; gap: 8px; }
.pk-unite-mot { font-size: 13px; color: var(--pk-ink-quiet); }

.pk-lignes-defilement { overflow-x: auto; }

.pk-ligne-saisie {
  display: grid;
  grid-template-columns: 130px minmax(240px, 2fr) 70px 110px 110px var(--pk-target-desk);
  gap: 6px;
  align-items: center;
  min-width: 760px;
  margin-bottom: 8px;
}
.pk-ligne-saisie .pk-input { font-size: 13px; }
.pk-centre { text-align: center; }

.pk-retirer {
  min-width: var(--pk-target-desk);
  min-height: var(--pk-target-desk);
  display: flex;
  align-items: center;
  justify-content: center;
  border: none;
  background: transparent;
  color: var(--pk-error-ink);
  font-size: 18px;
  cursor: pointer;
}
.pk-retirer:focus-visible {
  outline: var(--pk-focus-width) solid var(--pk-focus-ring);
  outline-offset: var(--pk-focus-offset);
}

.pk-lignes-pied { display: flex; align-items: center; gap: 12px; margin-top: 4px; }
.pk-total { font-size: 14px; font-weight: 700; }

.pk-remise { display: flex; align-items: center; gap: 12px; }
.pk-curseur { flex: 1; accent-color: var(--pk-accent); min-height: var(--pk-target-desk); }
/* Largeur en caractères : « 100 % » ne doit pas faire sauter le curseur. */
.pk-remise-valeur { min-width: 5ch; text-align: right; font-size: 14px; font-weight: 700; }

@media (max-width: 720px) {
  .pk-recherche { width: 100%; }
}
</style>
