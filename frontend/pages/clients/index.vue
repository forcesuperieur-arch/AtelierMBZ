<template>
  <div class="pk-clients">
    <!-- En-tête. Le constat du tour 2d : la liste doit commencer au PREMIER
         écran. Les quatre cartes hautes en grid-4 deviennent donc une bande
         d'une seule ligne, posée à côté du titre au lieu de le pousser. -->
    <header class="pk-clients-head">
      <div class="pk-clients-identity">
        <h1 class="pk-clients-title">Clients</h1>
        <span class="pk-clients-rule" aria-hidden="true" />
      </div>

      <div class="pk-strip" data-testid="clients-mesures">
        <div class="pk-strip-cell">
          <span class="pk-overline">Fiches clients</span>
          <span class="pk-strip-value" data-testid="clients-mesure-total">{{ nombre(stats.total) }}</span>
        </div>
        <div class="pk-strip-cell">
          <span class="pk-overline">Avec rendez-vous</span>
          <span class="pk-strip-value">
            {{ nombre(stats.avec_rdv) }}<span v-if="stats.total" class="pk-strip-suffix"> · {{ partAvecRdv }}</span>
          </span>
        </div>
        <div class="pk-strip-cell">
          <span class="pk-overline">Motos suivies</span>
          <span class="pk-strip-value">
            {{ nombre(stats.vehicules) }}<span v-if="stats.total" class="pk-strip-suffix"> · {{ motosParFiche }} par fiche</span>
          </span>
        </div>
        <div class="pk-strip-cell">
          <span class="pk-overline">Facturé à ce jour</span>
          <span class="pk-strip-value">
            {{ euros(stats.ca_total) }}<span class="pk-strip-suffix"> · TTC</span>
          </span>
        </div>
      </div>

      <AppButton
        variant="primary"
        icon="i-ri-user-add-line"
        label="Créer une fiche client"
        data-testid="clients-ouvrir-nouvelle-fiche"
        @click="ouvrirNouvelleFiche"
      />
    </header>

    <!-- La liste. La recherche vit dans l'EN-TÊTE du tableau et non au-dessus :
         une carte de recherche séparée coûtait une hauteur d'écran entière et
         repoussait la première ligne sous la ligne de flottaison. -->
    <section class="pk-list" data-testid="clients-liste">
      <div class="pk-list-bar">
        <label class="pk-search" :class="{ 'pk-search--rempli': rechercheActive }">
          <AppIcon name="i-ri-search-line" class="pk-search-icon" aria-hidden="true" />
          <span class="pk-sr-only">Chercher une fiche client par nom, téléphone ou e-mail</span>
          <input
            v-model="search"
            type="search"
            class="pk-search-input"
            placeholder="Nom, téléphone ou e-mail"
            data-testid="clients-recherche"
            @input="debouncedFetch"
          >
        </label>

        <!-- Le compte n'apparaît qu'une fois connu : annoncer « 0 fiche »
             pendant le chargement serait une réponse, et une fausse. -->
        <span v-if="!loading && !erreur" class="pk-list-scope" data-testid="clients-portee">
          <template v-if="rechercheActive">{{ nombre(totalItems) }} {{ totalItems > 1 ? 'fiches trouvées' : 'fiche trouvée' }}</template>
          <template v-else>{{ nombre(totalItems) }} {{ totalItems > 1 ? 'fiches' : 'fiche' }}</template>
        </span>
      </div>

      <!-- Règle 5 : la cause, puis ce que l'échec n'a pas emporté. -->
      <div v-if="erreur" class="pk-list-state">
        <AppErrorState
          title="Liste des clients indisponible"
          :description="erreur.texte"
          consequence="Les fiches déjà enregistrées ne sont pas perdues."
          action-label="Charger de nouveau la liste"
          :code="erreur.code"
          :heure="erreur.heure"
          data-testid="clients-erreur"
          @retry="fetchClients"
        />
      </div>

      <!-- La forme du tableau est déjà là : la page ne saute pas à l'arrivée. -->
      <div v-else-if="loading" class="pk-list-state">
        <AppLoadingState title="Chargement des fiches clients" :colonnes="6" :lignes="8" />
      </div>

      <!-- Filtré à blanc : la donnée EXISTE, c'est la recherche qui la cache.
           D'où ce gabarit plutôt que l'état vide, et le nombre de fiches que
           l'effacement ferait réapparaître. -->
      <div v-else-if="!clients.length && rechercheActive" class="pk-list-state">
        <AppFilterEmptyState
          title="Aucune fiche ne répond à cette recherche"
          :nombre-filtres="1"
          :suggestion="suggestionRecherche"
          data-testid="clients-recherche-sans-resultat"
          @retirer="effacerRecherche"
          @effacer="effacerRecherche"
        />
      </div>

      <div v-else-if="!clients.length" class="pk-list-state">
        <AppEmptyState
          icon="i-ri-group-line"
          title="Aucune fiche client"
          description="Une fiche naît au comptoir, à la prise de rendez-vous, ou se saisit ici. Tant qu’un client n’a pas de fiche, aucun rendez-vous ne peut lui être posé."
          action-label="Créer une fiche client"
          secondary-label="Poser un rendez-vous au comptoir"
          data-testid="clients-vide"
          @action="ouvrirNouvelleFiche"
          @secondary="ouvrirPriseDeRdv"
        />
      </div>

      <div v-else class="pk-table-scroll">
        <div class="pk-table" role="table" aria-label="Fiches clients">
          <div class="pk-row pk-row--head" role="row">
            <span class="pk-overline" role="columnheader">Client</span>
            <span class="pk-overline" role="columnheader">Téléphone</span>
            <span class="pk-overline" role="columnheader">E-mail</span>
            <span class="pk-overline" role="columnheader">Motos</span>
            <span class="pk-overline" role="columnheader">Fiche ouverte le</span>
            <span class="pk-overline" role="columnheader"><span class="pk-sr-only">Ouvrir la fiche</span></span>
          </div>

          <div
            v-for="c in clients"
            :key="c.id"
            class="pk-row"
            role="row"
            data-testid="clients-ligne"
          >
            <span class="pk-cell pk-cell--strong" role="cell">{{ c.nom }} {{ c.prenom }}</span>

            <span class="pk-cell pk-cell--dense" role="cell">{{ telephoneLisible(c.telephone) }}</span>

            <span class="pk-cell pk-cell--email" role="cell">
              <template v-if="c.email">{{ c.email }}</template>
              <span v-else class="pk-cell-absent">Sans e-mail</span>
            </span>

            <span class="pk-cell" role="cell">
              <template v-if="c.vehicules_count">
                {{ c.vehicules_count }} {{ c.vehicules_count > 1 ? 'motos' : 'moto' }}
                <span v-if="immats(c)" class="pk-cell-second">{{ immats(c) }}</span>
              </template>
              <span v-else class="pk-cell-absent">Aucune moto</span>
            </span>

            <!-- La liste sert `created_at`, la réponse du POST sert `createdAt` :
                 lire les deux évite un « — » sur la fiche qu'on vient de créer. -->
            <span class="pk-cell pk-cell--quiet" role="cell">{{ dateLisible(c.created_at ?? c.createdAt) }}</span>

            <span class="pk-cell pk-cell--action" role="cell">
              <NuxtLink
                :to="`/clients/${c.id}`"
                class="pk-row-link"
                :aria-label="`Ouvrir la fiche de ${c.nom} ${c.prenom}`"
                data-testid="clients-ouvrir-fiche"
              >
                Ouvrir la fiche
                <AppIcon name="i-ri-arrow-right-line" />
              </NuxtLink>
            </span>
          </div>
        </div>
      </div>

      <footer v-if="!loading && !erreur && clients.length" class="pk-list-foot">
        <span class="pk-list-tally" data-testid="clients-total">
          {{ nombre(totalItems) }} {{ totalItems > 1 ? 'fiches' : 'fiche' }} · {{ pageSize }} par page
        </span>

        <nav v-if="totalPages > 1" class="pk-pager" aria-label="Pages de la liste">
          <button
            class="pk-pager-step"
            :disabled="page <= 1"
            data-testid="clients-page-precedente"
            @click="page--; fetchClients()"
          >
            <AppIcon name="i-ri-arrow-left-line" />
            Page précédente
          </button>

          <button
            v-for="p in visiblePages"
            :key="p"
            class="pk-pager-num"
            :class="{ 'pk-pager-num--courante': p === page }"
            :aria-current="p === page ? 'page' : undefined"
            :aria-label="`Page ${p}`"
            @click="page = p; fetchClients()"
          >
            {{ p }}
          </button>

          <button
            class="pk-pager-step"
            :disabled="page >= totalPages"
            data-testid="clients-page-suivante"
            @click="page++; fetchClients()"
          >
            Page suivante
            <AppIcon name="i-ri-arrow-right-line" />
          </button>
        </nav>
      </footer>
    </section>

    <!-- Règle 4 : la saisie s'ouvre à droite, la liste reste lisible derrière.
         Une modale voilait l'écran entier pour cinq champs, et interdisait de
         vérifier au passage si le client n'existait pas déjà. -->
    <AppSidePanel
      :open="showNew"
      icon="i-ri-user-add-line"
      title="Nouvelle fiche client"
      subtitle="Une seconde fiche pour le même client coupe l’historique de la moto en deux."
      @close="showNew = false"
    >
      <form id="pk-nouvelle-fiche" @submit.prevent="createClient">
        <AppPanelSection label="Identité">
          <div class="pk-grid-2">
            <div class="pk-field">
              <label class="pk-field-label" for="pk-client-prenom">Prénom</label>
              <input id="pk-client-prenom" v-model="newClient.prenom" class="pk-field-input" required data-testid="clients-champ-prenom">
            </div>
            <div class="pk-field">
              <label class="pk-field-label" for="pk-client-nom">Nom</label>
              <input id="pk-client-nom" v-model="newClient.nom" class="pk-field-input" required data-testid="clients-champ-nom">
            </div>
          </div>
        </AppPanelSection>

        <AppPanelSection label="Coordonnées">
          <div class="pk-field">
            <label class="pk-field-label" for="pk-client-telephone">Téléphone</label>
            <input
              id="pk-client-telephone"
              v-model="newClient.telephone"
              class="pk-field-input"
              type="tel"
              inputmode="tel"
              autocomplete="tel"
              required
              data-testid="clients-champ-telephone"
            >
            <p class="pk-field-hint">Sans numéro, aucun message « moto prête » ne part au client.</p>
            <AppFieldError :message="erreurTelephone" />
          </div>

          <div class="pk-field">
            <label class="pk-field-label" for="pk-client-email">E-mail</label>
            <input
              id="pk-client-email"
              v-model="newClient.email"
              class="pk-field-input"
              type="email"
              autocomplete="email"
              data-testid="clients-champ-email"
            >
            <p class="pk-field-hint">Sans e-mail, ni devis ni facture ne peuvent lui être envoyés.</p>
            <AppFieldError :message="erreurEmail" />
          </div>

          <div class="pk-field">
            <label class="pk-field-label" for="pk-client-adresse">Adresse</label>
            <input id="pk-client-adresse" v-model="newClient.adresse" class="pk-field-input" autocomplete="street-address" data-testid="clients-champ-adresse">
          </div>
        </AppPanelSection>

        <AppPanelSection label="Consentement">
          <!-- Pas de `for` : l'étiquette enveloppe déjà la case, et les deux
               ensemble font basculer deux fois sur certains navigateurs. -->
          <label class="pk-consent">
            <input v-model="consentRGPD" type="checkbox" class="pk-consent-box" data-testid="clients-champ-consentement">
            <span class="pk-consent-text">
              Le client accepte que ses coordonnées soient conservées pour le suivi de son atelier, selon la
              <NuxtLink to="/public/politique-confidentialite" target="_blank" class="pk-consent-link">politique de confidentialité</NuxtLink>.
            </span>
          </label>
          <p v-if="!consentRGPD" class="pk-field-hint">Sans cet accord, la fiche ne peut pas être enregistrée.</p>
        </AppPanelSection>
      </form>

      <template #footer>
        <AppButton
          variant="primary"
          type="submit"
          form="pk-nouvelle-fiche"
          label="Créer la fiche client"
          block
          :loading="creating"
          :disabled="!consentRGPD"
          data-testid="clients-enregistrer-fiche"
        />
        <AppButton variant="ghost" label="Annuler" block @click="showNew = false" />
      </template>
    </AppSidePanel>
  </div>
</template>

<script setup lang="ts">
const api = useApi()
const toast = useToast()
const { validateClientFields } = useValidation()
const loading = ref(true)
const clients = ref<any[]>([])
const search = ref('')
const showNew = ref(false)
const creating = ref(false)
const page = ref(1)
const pageSize = 50
const totalItems = ref(0)
const totalPages = computed(() => Math.ceil(totalItems.value / pageSize) || 1)
const visiblePages = computed(() => {
  const pages: number[] = []
  const start = Math.max(1, page.value - 2)
  const end = Math.min(totalPages.value, page.value + 2)
  for (let i = start; i <= end; i++) pages.push(i)
  return pages
})

const newClient = reactive({ prenom: '', nom: '', telephone: '', email: '', adresse: '' })
const consentRGPD = ref(false)

const stats = reactive({ total: 0, avec_rdv: 0, vehicules: 0, ca_total: 0 })

/* ---- Écriture des nombres, à la française ----------------------------------
   Le design system l'impose : 1 284, 418 260 €, 1,3 — jamais le point décimal
   ni le « 418.3k » que rendait l'ancien abrégé, illisible sur un chiffre
   d'affaires qu'on recopie. */
const NOMBRE_FR = new Intl.NumberFormat('fr-FR')
const EUROS_FR = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 })
const PART_FR = new Intl.NumberFormat('fr-FR', { style: 'percent', maximumFractionDigits: 0 })
const DECIMAL_FR = new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })
const DATE_FR = new Intl.DateTimeFormat('fr-FR', { day: '2-digit', month: 'long', year: 'numeric' })

function nombre(val: number | null | undefined): string {
  return NOMBRE_FR.format(Number(val) || 0)
}

function euros(val: number | null | undefined): string {
  return EUROS_FR.format(Number(val) || 0)
}

const partAvecRdv = computed(() => (stats.total ? PART_FR.format(stats.avec_rdv / stats.total) : ''))
const motosParFiche = computed(() => (stats.total ? DECIMAL_FR.format(stats.vehicules / stats.total) : ''))

function dateLisible(val: string | null | undefined): string {
  if (!val) return '—'
  const d = new Date(val)
  return isNaN(d.getTime()) ? '—' : DATE_FR.format(d)
}

/** Le numéro se lit par paires, comme on le dicte au téléphone. */
function telephoneLisible(brut: string | null | undefined): string {
  const compact = String(brut ?? '').replace(/[\s.-]/g, '')
  if (/^0\d{9}$/.test(compact)) return compact.replace(/(\d{2})(?=\d)/g, '$1 ')
  return String(brut ?? '').trim() || '—'
}

/** Les immats servent à reconnaître la bonne fiche quand deux noms se ressemblent. */
function immats(client: any): string {
  const plaques = (client?.vehicules ?? [])
    .map((v: any) => v?.plaque)
    .filter(Boolean)
    .slice(0, 2)
  if (!plaques.length) return ''
  const reste = (client?.vehicules_count ?? plaques.length) - plaques.length
  return reste > 0 ? `${plaques.join(', ')} +${reste}` : plaques.join(', ')
}

/* ---- Recherche et états ---------------------------------------------------- */
const rechercheActive = computed(() => search.value.trim().length > 0)

/** Total de la dernière liste NON filtrée : c'est ce que l'effacement rendrait. */
const totalSansRecherche = ref(0)

const suggestionRecherche = computed(() => {
  if (!totalSansRecherche.value) return null
  return {
    filtre: search.value.trim(),
    nombre: totalSansRecherche.value,
    objet: totalSansRecherche.value > 1 ? 'fiches' : 'fiche',
  }
})

/** Ce que le serveur a répondu, mis en phrase : cause, effet, code à dicter. */
const erreur = ref<{ texte: string, code: string, heure: string } | null>(null)

/* ---- Saisie : l'erreur se dit AU CHAMP, pas dans un toast qui disparaît ----
   Le contrôle de format reste celui de `useValidation` — mêmes règles, même
   verdict qu'auparavant ; seul l'endroit où il s'affiche change. Il ne
   s'affiche qu'après une tentative d'envoi : corriger quelqu'un pendant qu'il
   tape son numéro n'aide personne. */
const envoiTente = ref(false)
const erreurTelephone = computed(() => (
  envoiTente.value ? (validateClientFields({ telephone: newClient.telephone })[0] ?? '') : ''
))
const erreurEmail = computed(() => (
  envoiTente.value ? (validateClientFields({ email: newClient.email })[0] ?? '') : ''
))

async function fetchClients() {
  loading.value = true
  erreur.value = null
  try {
    const params = new URLSearchParams()
    if (search.value.trim()) params.set('search', search.value.trim())
    params.set('page', String(page.value))
    params.set('limit', String(pageSize))
    const data = await api.get(`/clients?${params}`)
    const raw = data?.['hydra:member'] ?? data?.member ?? (Array.isArray(data) ? data : [])
    totalItems.value = data?.['hydra:totalItems'] ?? data?.totalItems ?? raw.length
    // Mémorisé hors recherche seulement : c'est le chiffre que l'état filtré
    // annonce, et il serait faux s'il venait d'une liste déjà filtrée.
    if (!search.value.trim()) totalSansRecherche.value = totalItems.value
    clients.value = raw.map((c: any) => ({
      ...c,
      vehicules_count: c.vehicules?.length ?? 0,
    }))
  } catch (e: any) {
    const statut = Number(e?.statusCode ?? e?.status ?? 0)
    erreur.value = {
      texte: messageErreur(e, "la liste des clients n'a pas pu être chargée"),
      code: statut ? String(statut) : '',
      heure: new Intl.DateTimeFormat('fr-FR', { hour: '2-digit', minute: '2-digit' }).format(new Date()),
    }
    clients.value = []
  } finally {
    loading.value = false
  }
}

let timeout: ReturnType<typeof setTimeout>
function debouncedFetch() {
  clearTimeout(timeout)
  page.value = 1
  timeout = setTimeout(fetchClients, 300)
}

function effacerRecherche() {
  search.value = ''
  debouncedFetch()
}

function ouvrirNouvelleFiche() {
  envoiTente.value = false
  showNew.value = true
}

/** L'autre chemin par lequel une fiche naît, quand la liste est encore vide. */
function ouvrirPriseDeRdv() {
  navigateTo('/rdv/new')
}

async function createClient() {
  creating.value = true
  envoiTente.value = true
  try {
    const formatErrors = validateClientFields({
      telephone: newClient.telephone,
      email: newClient.email,
    })
    if (formatErrors.length) {
      return
    }
    const c = await api.post('/clients', {
      ...newClient,
      consentDate: new Date().toISOString(),
      consentSource: 'backoffice_form',
    })
    clients.value.unshift(c)
    showNew.value = false
    toast.add({
      title: 'Fiche client créée',
      description: `${newClient.nom} ${newClient.prenom} — la fiche est en tête de liste.`,
      color: 'success',
    })
    Object.assign(newClient, { prenom: '', nom: '', telephone: '', email: '', adresse: '' })
    consentRGPD.value = false
    envoiTente.value = false
  } catch (e: any) {
    toast.add({
      title: 'Création impossible',
      description: messageErreur(e, "la fiche client n'a pas été créée"),
      color: 'error',
    })
  } finally {
    creating.value = false
  }
}

onMounted(() => {
  fetchClients()
  api.get('/clients/stats').then((s: any) => {
    if (s) Object.assign(stats, s)
  }).catch(() => {
    // stats not available — compute from loaded clients
    const c = clients.value
    stats.total = c.length
    stats.avec_rdv = c.filter((x: any) => x.rdv_count > 0).length
    stats.vehicules = c.reduce((a: number, x: any) => a + (x.vehicules_count ?? 0), 0)
  })
})
</script>

<style scoped>
/* Toute couleur, tout rayon, toute durée et toute cible viennent des tokens
   --pk-* : rien n'est écrit en dur ici, sinon la page cesserait de suivre le
   thème et les réglages de mouvement du poste. */

.pk-clients { display: flex; flex-direction: column; gap: 14px; min-width: 0; color: var(--pk-ink); }

/* ---- En-tête : le titre, la bande de mesures, l'action ---- */
.pk-clients-head { display: flex; align-items: flex-end; gap: 16px; flex-wrap: wrap; }
.pk-clients-identity { display: flex; flex-direction: column; gap: 4px; }
.pk-clients-title { margin: 0; font-size: 28px; font-weight: 500; letter-spacing: -0.015em; line-height: 1.1; }

/* La seule décoration qu'un en-tête de page reçoit. Le 44 × 4 est la mesure
   du filet de la maquette, pas une cible tactile : aucun token ne le porte. */
.pk-clients-rule { width: 44px; height: 4px; background: var(--pk-accent); }

.pk-strip {
  flex: 1;
  min-width: 320px;
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  background: var(--pk-surface);
  border: 1px solid var(--pk-border);
  border-radius: var(--pk-radius-card);
}

.pk-strip-cell {
  display: flex;
  flex-direction: column;
  gap: 3px;
  min-width: 0;
  padding: 10px 18px;
  border-right: 1px solid var(--pk-border-quiet);
}
.pk-strip-cell:last-child { border-right: none; }
.pk-strip-value { font-size: 22px; font-weight: 700; line-height: 1; font-variant-numeric: tabular-nums; }
.pk-strip-suffix { font-size: 12px; font-weight: 500; color: var(--pk-ink-muted); }

/* Surtitres : le seul endroit, avec les mots de statut, où les capitales sont
   autorisées. Le texte reste en casse phrase dans le balisage — un lecteur
   d'écran ne doit pas épeler. */
.pk-overline {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--pk-ink-muted);
}

/* ---- La liste ---- */
.pk-list {
  display: flex;
  flex-direction: column;
  min-width: 0;
  background: var(--pk-surface);
  border: 1px solid var(--pk-border);
  border-radius: var(--pk-radius-card);
  overflow: hidden;
}

.pk-list-bar {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
  padding: 12px 16px;
  border-bottom: 1px solid var(--pk-border);
}

.pk-search {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 320px;
  max-width: 100%;
  min-height: var(--pk-target-desk);
  padding: 0 12px;
  border: 1px solid var(--pk-border-control);
  border-radius: var(--pk-radius-card);
  background: var(--pk-surface-raised);
  color: var(--pk-ink-muted);
  transition: border-color var(--pk-duration-state) var(--pk-easing);
}
.pk-search:focus-within,
.pk-search--rempli { border-color: var(--pk-border-strong); }
.pk-search-icon { font-size: 16px; flex-shrink: 0; }
.pk-search-input { flex: 1; min-width: 0; border: none; background: none; color: var(--pk-ink); font: inherit; font-size: 13px; }
.pk-search-input::placeholder { color: var(--pk-ink-muted); }
/* Pas de `outline: none` ici, même « parce que le cadre porte déjà un repère » :
   le design system écrit « jamais supprimé », sans exception. Le cadre change
   de filet au focus, et l'anneau global de main.css se pose sur le champ —
   les deux se cumulent sans se gêner, l'anneau étant décalé vers l'intérieur. */

.pk-list-scope { margin-left: auto; font-size: 12px; color: var(--pk-ink-muted); font-variant-numeric: tabular-nums; }
.pk-list-state { padding: 16px; }

/* ---- Le tableau dense ----
   Les colonnes ne se replient pas sous 900 px : elles défilent. Masquer une
   colonne masquerait une donnée que le comptoir cherche justement. */
.pk-table-scroll { overflow-x: auto; }
.pk-table { min-width: 880px; }

.pk-row {
  display: grid;
  grid-template-columns: 1.5fr 1.1fr 1.6fr 1fr 1fr 132px;
  align-items: center;
  gap: 12px;
  padding: 9px 16px;
  min-height: var(--pk-target-desk);
  border-bottom: 1px solid var(--pk-border-quiet);
  font-size: 13px;
}
.pk-row--head { min-height: 0; background: var(--pk-surface-raised); border-bottom: 1px solid var(--pk-border); }

/* Zébrure : sur une liste de cinquante lignes c'est elle qui garde l'œil sur
   la bonne ligne d'un bout à l'autre de la largeur. */
.pk-row:not(.pk-row--head):nth-child(odd) { background: var(--pk-surface-raised); }
.pk-row:not(.pk-row--head):hover { background: var(--pk-accent-soft); }

.pk-cell { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pk-cell--strong { font-weight: 600; }
.pk-cell--quiet,
.pk-cell--email { color: var(--pk-ink-quiet); }
.pk-cell--dense { color: var(--pk-ink-quiet); font-family: var(--font-dense); font-size: 12px; font-variant-numeric: tabular-nums; }
.pk-cell-second { display: block; font-family: var(--font-dense); font-size: 11px; color: var(--pk-ink-muted); }
.pk-cell-absent { color: var(--pk-ink-muted); }
.pk-cell--action { display: flex; justify-content: flex-end; overflow: visible; }

.pk-row-link {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  min-height: var(--pk-target-desk);
  padding: 0 4px;
  color: var(--pk-link);
  font-size: 12px;
  font-weight: 600;
  text-decoration: none;
  white-space: nowrap;
}
.pk-row-link:hover { text-decoration: underline; }

/* ---- Pied de liste ---- */
.pk-list-foot {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
  padding: 11px 16px;
  border-top: 1px solid var(--pk-border);
}
.pk-list-tally { font-size: 12px; color: var(--pk-ink-quiet); font-variant-numeric: tabular-nums; }

.pk-pager {
  margin-left: auto;
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  /* 8 px entre « page précédente » et « page suivante » : deux cibles voisines
     aux effets opposés ne doivent jamais se toucher. */
  gap: var(--pk-target-gap);
}

.pk-pager-step,
.pk-pager-num {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 5px;
  min-height: var(--pk-target-desk);
  padding: 0 12px;
  border: 1px solid var(--pk-border-control);
  border-radius: var(--pk-radius-card);
  background: transparent;
  color: var(--pk-ink);
  font: inherit;
  font-size: 12px;
  cursor: pointer;
  transition: background var(--pk-duration-state) var(--pk-easing),
    border-color var(--pk-duration-state) var(--pk-easing);
}
.pk-pager-num { min-width: var(--pk-target-desk); font-variant-numeric: tabular-nums; }
.pk-pager-step:hover:not(:disabled),
.pk-pager-num:hover { background: var(--pk-neutral-surface); }
.pk-pager-step:disabled { color: var(--pk-ink-muted); border-color: var(--pk-border); cursor: not-allowed; }
.pk-pager-num--courante { background: var(--pk-accent); border-color: transparent; color: var(--pk-accent-ink); font-weight: 700; }

/* ---- Panneau de saisie ---- */
.pk-grid-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; }

/* L'écart entre deux champs vient du `gap` de la section de panneau : en
   ajouter un ici les additionnerait. */
.pk-field { display: flex; flex-direction: column; gap: 4px; }

/* L'étiquette est au-dessus du champ, jamais remplacée par un texte d'exemple :
   celui-ci disparaît dès la première frappe. */
.pk-field-label { font-size: 12px; font-weight: 600; color: var(--pk-ink-quiet); }

.pk-field-input {
  min-height: 38px;
  padding: 0 10px;
  border: 1px solid var(--pk-border-control);
  border-radius: var(--pk-radius-card);
  background: var(--pk-surface-raised);
  color: var(--pk-ink);
  font: inherit;
  font-size: 13px;
  transition: border-color var(--pk-duration-state) var(--pk-easing);
}
.pk-field-input:focus { border-color: var(--pk-border-strong); }
.pk-field-hint { margin: 0; font-size: 12px; line-height: 1.45; color: var(--pk-ink-muted); }

.pk-consent { display: flex; align-items: flex-start; gap: 10px; min-height: var(--pk-target-desk); cursor: pointer; }
.pk-consent-box { margin-top: 3px; width: 18px; height: 18px; flex-shrink: 0; accent-color: var(--pk-accent); }
.pk-consent-text { font-size: 12px; line-height: 1.45; color: var(--pk-ink-quiet); }
.pk-consent-link { color: var(--pk-link); font-weight: 600; }

/* L'anneau de focus n'est jamais supprimé : une seule déclaration pour tous les
   contrôles écrits ici, afin qu'aucun n'y échappe par oubli. */
.pk-row-link:focus-visible,
.pk-pager-step:focus-visible,
.pk-pager-num:focus-visible,
.pk-field-input:focus-visible,
.pk-consent-box:focus-visible {
  outline: var(--pk-focus-width) solid var(--pk-focus-ring);
  outline-offset: var(--pk-focus-offset);
}

.pk-sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip-path: inset(50%);
  white-space: nowrap;
}

@media (max-width: 900px) {
  .pk-strip { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .pk-strip-cell:nth-child(2) { border-right: none; }
  .pk-strip-cell:nth-child(1),
  .pk-strip-cell:nth-child(2) { border-bottom: 1px solid var(--pk-border-quiet); }
  .pk-search { width: 100%; }
  .pk-list-scope { margin-left: 0; }
}
</style>
