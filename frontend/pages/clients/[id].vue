<template>
  <div class="fc">
    <!-- La fiche s'ouvre depuis la liste, souvent le téléphone à la main : le
         retour doit être DANS la page. « Précédent » du navigateur suppose
         qu'on sait d'où l'on vient, ce qui est faux quand on arrive d'une
         recherche ou d'un lien collé. -->
    <NuxtLink to="/clients" class="fc-retour" data-testid="fc-retour">
      <AppIcon name="i-ri-arrow-left-line" />
      <span>Tous les clients</span>
    </NuxtLink>

    <AppLoadingState
      v-if="loading"
      title="Fiche client en cours d’ouverture"
      :colonnes="5"
      :lignes="6"
    />

    <AppErrorState
      v-else-if="erreur"
      title="Fiche client illisible"
      :description="erreur"
      consequence="Aucune donnée de ce client n’a été touchée."
      action-label="Rouvrir la fiche"
      issue-label="Revenir à la liste des clients"
      data-testid="fc-erreur"
      @retry="charger"
      @issue="navigateTo('/clients')"
    />

    <template v-else-if="client">
      <!-- ================= Qui c'est, et ce qu'il pèse ================= -->
      <header class="fc-tete" data-testid="fc-entete">
        <div class="fc-initiales" aria-hidden="true">{{ initiales }}</div>

        <div class="fc-identite">
          <div class="fc-nom-ligne">
            <h1 class="fc-nom">{{ client.prenom }} {{ client.nom }}</h1>
            <!-- Le segment est un MOT DE STATUT : c'est le seul cas, avec les
                 surtitres, où le design system autorise les capitales. -->
            <span v-if="segmentAffiche" class="fc-segment">{{ segmentAffiche }}</span>
          </div>

          <div class="fc-contact">
            <a v-if="client.telephone" class="fc-contact-item fc-contact-lien" :href="lienTelephone">
              <AppIcon name="i-ri-phone-line" />{{ client.telephone }}
            </a>
            <span v-else class="fc-contact-item fc-contact-absent">
              <AppIcon name="i-ri-phone-line" />aucun numéro — les rappels de rendez-vous ne partent pas
            </span>

            <a v-if="client.email" class="fc-contact-item fc-contact-lien" :href="`mailto:${client.email}`">
              <AppIcon name="i-ri-mail-line" />{{ client.email }}
            </a>
            <span v-else class="fc-contact-item fc-contact-absent">
              <AppIcon name="i-ri-mail-line" />aucune adresse e-mail
            </span>

            <span class="fc-contact-item">
              <AppIcon name="i-ri-map-pin-line" />{{ client.adresse || 'adresse non renseignée' }}
            </span>
          </div>

          <div class="fc-actions">
            <button type="button" class="btn btn-primary" data-testid="fc-nouveau-rdv" @click="navigateTo('/rdv/new')">
              <AppIcon name="i-ri-calendar-line" /> Poser un rendez-vous
            </button>
            <button type="button" class="btn" data-testid="fc-modifier" @click="ouvrirEdition">
              <AppIcon name="i-ri-pencil-line" /> Corriger les coordonnées
            </button>
          </div>
        </div>

        <div class="fc-mesures">
          <div class="fc-mesure">
            <span class="fc-surtitre">Passages</span>
            <span class="fc-mesure-valeur">{{ clientRdvs.length }}</span>
            <span class="fc-mesure-note">{{ notePassages }}</span>
          </div>
          <div class="fc-mesure">
            <span class="fc-surtitre">Total dépensé</span>
            <span class="fc-mesure-valeur">{{ formatCurrency(caTotal) }}</span>
            <span class="fc-mesure-note">{{ noteDepense }}</span>
          </div>
          <div class="fc-mesure" :class="{ 'fc-mesure--attention': motosAAtelier.length > 0 }">
            <span class="fc-surtitre">Motos suivies</span>
            <span class="fc-mesure-valeur">{{ client.vehicules?.length || 0 }}</span>
            <span class="fc-mesure-note">{{ noteMotos }}</span>
          </div>
        </div>
      </header>

      <div class="fc-corps">
        <div class="fc-colonne">
          <!-- ============ Ce qu'on doit savoir avant de décrocher ============ -->
          <section
            v-for="rdv in rdvsEnAtelier"
            :key="`atelier-${rdv.id}`"
            class="fc-atelier"
            data-testid="fc-moto-atelier"
          >
            <AppIcon name="i-ri-tools-fill" class="fc-atelier-icone" />
            <div class="fc-atelier-texte">
              <p class="fc-atelier-titre">{{ rdv.vehicule_info || 'Une moto' }} est à l’atelier en ce moment</p>
              <p v-if="ligneAtelier(rdv)" class="fc-atelier-detail">{{ ligneAtelier(rdv) }}</p>
            </div>
            <StatusBadge :status="rdv.status" />
            <button type="button" class="btn btn-primary" @click="openRdvDetail(rdv)">
              <AppIcon name="i-ri-external-link-line" /> Ouvrir le rendez-vous · {{ formatDate(rdv.date_rdv) }}
            </button>
          </section>

          <!-- ============================ Ses motos ============================ -->
          <section class="fc-carte" data-testid="fc-motos">
            <header class="fc-carte-tete">
              <h2 class="fc-carte-titre">Ses motos</h2>
              <span class="fc-compteur">{{ client.vehicules?.length || 0 }}</span>
              <span class="fc-carte-note">Une ligne ouvre l’historique de cette moto seule</span>
            </header>

            <div v-if="client.vehicules?.length" class="fc-motos">
              <!-- Cliquer une moto FILTRE l'historique du dessous plutôt que de
                   déplier une seconde liste : deux historiques côte à côte, on
                   ne sait plus lequel fait foi. -->
              <button
                v-for="v in client.vehicules"
                :key="v.id"
                type="button"
                class="fc-moto"
                :class="{ 'fc-moto--retenue': motoFiltree === v.id }"
                :aria-pressed="motoFiltree === v.id"
                @click="basculerMoto(v.id)"
              >
                <AppIcon name="i-ri-motorbike-fill" class="fc-moto-icone" />

                <span class="fc-moto-identite">
                  <span class="fc-moto-nom">
                    {{ v.marque }} {{ v.modele }}<template v-if="v.annee"> · {{ v.annee }}</template>
                  </span>
                  <span class="fc-moto-immat">
                    {{ v.plaque }}<template v-if="v.kilometrage"> · {{ formatKm(v.kilometrage) }}</template>
                  </span>
                </span>

                <span class="fc-moto-col">
                  <span class="fc-moto-etiquette">Dernier passage</span>
                  <span class="fc-moto-valeur">{{ dernierPassage(v.id) }}</span>
                </span>

                <span class="fc-moto-col">
                  <span class="fc-moto-etiquette">Passages</span>
                  <span class="fc-moto-valeur">{{ vehicleRdvCount(v.id) }}</span>
                </span>

                <span
                  class="fc-jeton"
                  :class="estAAtelier(v.id) ? 'fc-jeton--atelier' : 'fc-jeton--dehors'"
                >{{ estAAtelier(v.id) ? 'EN ATELIER' : 'AU GARAGE' }}</span>

                <AppIcon name="i-ri-arrow-right-s-line" class="fc-moto-chevron" />
              </button>
            </div>

            <div v-else class="fc-carte-corps">
              <AppEmptyState
                icon="i-ri-motorbike-line"
                title="Aucune moto rattachée à ce client"
                description="Une moto entre au fichier à la prise de rendez-vous, avec son immat et son kilométrage. Elle apparaît ici dès le premier créneau posé."
                action-label="Poser un rendez-vous"
                @action="navigateTo('/rdv/new')"
              />
            </div>
          </section>

          <!-- ======================= L'historique fusionné ======================= -->
          <section class="fc-carte" data-testid="fc-historique">
            <header class="fc-carte-tete">
              <h2 class="fc-carte-titre">Historique</h2>
              <span class="fc-compteur">{{ historique.length }}</span>
              <button
                v-if="motoFiltree"
                type="button"
                class="fc-filtre"
                data-testid="fc-retirer-filtre"
                @click="motoFiltree = null"
              >
                <AppIcon name="i-ri-filter-fill" />
                {{ nomMotoFiltree }} seule · retirer le filtre
                <AppIcon name="i-ri-close-line" />
              </button>
              <span class="fc-carte-note">Du plus récent au plus ancien</span>
            </header>

            <div v-if="historiqueVisible.length" class="fc-lignes">
              <button
                v-for="rdv in historiqueVisible"
                :key="rdv.id"
                type="button"
                class="fc-ligne"
                data-testid="fc-ligne-historique"
                @click="openRdvDetail(rdv)"
              >
                <span class="fc-ligne-quand">
                  <span class="fc-ligne-date">{{ formatDate(rdv.date_rdv) }}</span>
                  <span v-if="rdv.heure_debut" class="fc-ligne-heure">{{ rdv.heure_debut }}</span>
                </span>

                <span class="fc-ligne-objet">
                  <span class="fc-ligne-titre">{{ rdv.type_intervention || 'Passage à l’atelier' }}</span>
                  <span v-if="sousLigne(rdv)" class="fc-ligne-sous">{{ sousLigne(rdv) }}</span>
                </span>

                <span class="fc-ligne-moto">{{ rdv.vehicule_info || 'moto non rattachée' }}</span>

                <StatusBadge :status="rdv.status" />

                <AppIcon name="i-ri-arrow-right-s-line" class="fc-ligne-chevron" />
              </button>

              <!-- Un repli, pas un onglet : la ligne dit ce qu'elle cache et
                   jusqu'où ça remonte, donc on sait si ça vaut le clic. -->
              <button
                v-if="restantHistorique > 0"
                type="button"
                class="fc-plus"
                data-testid="fc-voir-plus"
                @click="toutAfficher = true"
              >
                <AppIcon name="i-ri-arrow-down-s-line" />
                <span class="fc-plus-libelle">Voir les {{ restantHistorique }} passages plus anciens</span>
                <span class="fc-plus-note">le plus ancien remonte au {{ formatDate(dateLaPlusAncienne) }}</span>
              </button>
            </div>

            <div v-else class="fc-carte-corps">
              <AppFilterEmptyState
                v-if="motoFiltree"
                :title="`Aucun passage sur ${nomMotoFiltree}`"
                :nombre-filtres="1"
                :suggestion="suggestionFiltre"
                @retirer="motoFiltree = null"
                @effacer="motoFiltree = null"
              />
              <AppEmptyState
                v-else
                icon="i-ri-calendar-line"
                title="Ce client n’est encore jamais passé"
                description="L’historique se remplit tout seul : chaque rendez-vous posé au planning vient s’y inscrire, avec sa moto et son état."
                action-label="Poser un rendez-vous"
                @action="navigateTo('/rdv/new')"
              />
            </div>
          </section>
        </div>

        <!-- ===================== Ce qui ne sort pas de l'atelier ===================== -->
        <aside class="fc-aside">
          <section class="fc-carte" data-testid="fc-notes">
            <header class="fc-carte-tete">
              <AppIcon name="i-ri-lock-line" class="fc-carte-icone" />
              <h2 class="fc-carte-titre">Notes d’atelier</h2>
              <button type="button" class="btn-link fc-carte-lien" @click="ouvrirEdition">
                {{ client.notes ? 'Modifier' : 'Écrire' }}
              </button>
            </header>

            <p v-if="client.notes" class="fc-note">{{ client.notes }}</p>
            <p v-else class="fc-note fc-note--vide">
              Rien de noté. Ce qu’on écrit ici reste entre nous : ni le devis, ni la facture, ni le suivi en ligne ne le reprennent.
            </p>

            <AppPanelSection label="Relation">
              <div class="fc-fait">
                <span class="fc-fait-cle">Client depuis</span>
                <span class="fc-fait-donnee">{{ formatDate(client.created_at || client.createdAt) }}</span>
              </div>
              <div class="fc-fait">
                <span class="fc-fait-cle">Dernier passage</span>
                <span class="fc-fait-donnee">{{ dernierPassageGlobal }}</span>
              </div>
              <div class="fc-fait">
                <span class="fc-fait-cle">Motos suivies</span>
                <span class="fc-fait-donnee">{{ client.vehicules?.length || 0 }}</span>
              </div>
            </AppPanelSection>

            <AppPanelSection v-if="!client.isAnonymized" label="Données personnelles" data-testid="fc-rgpd">
              <p class="fc-aide">
                L’export remet au client tout ce que l’atelier détient sur lui, en un fichier. L’effacement, lui, ne se rejoue pas.
              </p>
              <div class="fc-pile">
                <button type="button" class="btn" :disabled="exporting" data-testid="fc-export" @click="exportClient">
                  <AppIcon name="i-ri-download-2-line" />
                  {{ exporting ? 'Export en cours…' : 'Exporter les données du client' }}
                </button>
                <button type="button" class="btn fc-btn-danger" data-testid="fc-anonymiser" @click="demanderAnonymisation">
                  <AppIcon name="i-ri-eraser-line" /> Effacer les données personnelles
                </button>
              </div>
            </AppPanelSection>

            <AppPanelSection v-else label="Données personnelles">
              <div class="fc-efface" role="status">
                <AppIcon name="i-ri-eraser-line" class="fc-efface-icone" />
                <p class="fc-efface-texte">
                  Les données personnelles de ce client ont été effacées. Les factures et les ordres gardent leur copie figée ; la fiche, elle, ne se retrouve plus par une recherche au nom.
                </p>
              </div>
            </AppPanelSection>

            <div v-if="client.telephone" class="fc-aside-pied">
              <a class="btn btn-secondary btn-block" :href="lienTelephone">
                <AppIcon name="i-ri-phone-line" /> Appeler {{ client.prenom }} · {{ client.telephone }}
              </a>
            </div>
          </section>
        </aside>
      </div>
    </template>

    <!-- ==================== Corriger les coordonnées ==================== -->
    <!-- Règle 4 : le poste de travail ne se quitte pas. On corrige un numéro
         sans perdre de vue l'historique qu'on est en train de lire au
         téléphone — une modale, elle, voilerait précisément ça. -->
    <AppSidePanel
      :open="showEditClient"
      icon="i-ri-pencil-line"
      title="Corriger les coordonnées"
      :subtitle="client ? `${client.prenom} ${client.nom}` : ''"
      @close="showEditClient = false"
    >
      <form id="fc-form-client" class="fc-form" data-testid="fc-panneau-edition" @submit.prevent="saveClient">
        <AppPanelSection label="Identité">
          <div class="fc-duo">
            <div class="form-group">
              <label class="form-label" for="fc-prenom">Prénom</label>
              <input id="fc-prenom" v-model="editForm.prenom" class="form-input">
            </div>
            <div class="form-group">
              <label class="form-label" for="fc-nom">Nom</label>
              <input id="fc-nom" v-model="editForm.nom" class="form-input">
            </div>
          </div>
        </AppPanelSection>

        <AppPanelSection label="Joindre le client">
          <div class="form-group">
            <label class="form-label" for="fc-telephone">Téléphone</label>
            <input id="fc-telephone" v-model="editForm.telephone" class="form-input" type="tel">
            <p class="fc-aide">Sans numéro, aucun rappel de rendez-vous ne part et la restitution ne s’annonce pas.</p>
          </div>
          <div class="form-group">
            <label class="form-label" for="fc-email">Adresse e-mail</label>
            <input id="fc-email" v-model="editForm.email" class="form-input" type="email">
            <p class="fc-aide">C’est par là que partent le devis à signer et la facture.</p>
          </div>
          <div class="form-group">
            <label class="form-label" for="fc-adresse">Adresse postale</label>
            <input id="fc-adresse" v-model="editForm.adresse" class="form-input">
          </div>
        </AppPanelSection>

        <AppPanelSection label="Notes d’atelier">
          <div class="form-group">
            <label class="form-label" for="fc-notes">Ce qu’il faut savoir avant de décrocher</label>
            <textarea id="fc-notes" v-model="editForm.notes" class="form-input" rows="5" />
            <p class="fc-aide">Interne à l’atelier : rien de ce texte ne remonte au client.</p>
          </div>
        </AppPanelSection>
      </form>

      <template #footer>
        <button type="submit" form="fc-form-client" class="btn btn-primary btn-block" :disabled="savingClient">
          {{ savingClient ? 'Enregistrement…' : 'Enregistrer les coordonnées' }}
        </button>
        <button type="button" class="btn btn-ghost btn-block" @click="showEditClient = false">
          Fermer sans enregistrer
        </button>
      </template>
    </AppSidePanel>

    <!-- ==================== Effacement RGPD ==================== -->
    <!-- Règle 1 : montrer l'effet avant d'enregistrer. On nomme ce qui part,
         ce qui reste, et on dit que ça ne se rejoue pas — la boîte de dialogue
         du navigateur ne savait dire ni l'un ni l'autre. -->
    <AppModal v-model:open="confirmationEffacement" size="md">
      <template #header>
        <span class="fc-modale-titre">Effacer les données de {{ client?.prenom }} {{ client?.nom }}</span>
      </template>

      <template #content>
        <div class="fc-modale" data-testid="fc-modale-anonymisation">
          <div class="fc-modale-bloc fc-modale-bloc--part">
            <span class="fc-surtitre">Ce qui part</span>
            <p>Le nom, le prénom, le téléphone, l’adresse e-mail et l’adresse postale. Les motos se détachent de la fiche.</p>
          </div>
          <div class="fc-modale-bloc fc-modale-bloc--reste">
            <span class="fc-surtitre">Ce qui reste</span>
            <p>Les factures et les ordres de réparation gardent la copie figée établie le jour de l’intervention : la comptabilité reste en règle.</p>
          </div>
          <p class="fc-modale-arret">
            Il n’y a pas de retour en arrière, et la fiche ne se retrouvera plus par une recherche au nom.
          </p>
        </div>
      </template>

      <template #footer>
        <button type="button" class="btn btn-ghost" @click="confirmationEffacement = false">
          Garder la fiche telle quelle
        </button>
        <button type="button" class="btn fc-btn-danger" data-testid="fc-confirmer-anonymisation" @click="anonymiser">
          Effacer définitivement les données personnelles
        </button>
      </template>
    </AppModal>
  </div>
</template>

<script setup lang="ts">
const route = useRoute()
const api = useApi()
const toast = useToast()
const { open: openRdvDetail } = useRdvDetailModal()
const loading = ref(true)
const erreur = ref('')
const client = ref<any>(null)
const clientRdvs = ref<any[]>([])
const showEditClient = ref(false)
const savingClient = ref(false)
const exporting = ref(false)
const confirmationEffacement = ref(false)
const editForm = reactive({ prenom: '', nom: '', telephone: '', email: '', adresse: '', notes: '' })

/** Moto sur laquelle l'historique est replié. `null` = tous les passages. */
const motoFiltree = ref<number | null>(null)
const toutAfficher = ref(false)

/** Au-delà, la fiche devient un mur : le reste passe derrière un repli nommé. */
const PASSAGES_AVANT_REPLI = 8

/**
 * Une moto est PHYSIQUEMENT à l'atelier du check-in jusqu'à la restitution.
 * « Terminé » en fait partie : le travail est fini, la moto est encore là — et
 * c'est justement le cas où le client appelle.
 */
const STATUTS_A_L_ATELIER = ['reception', 'en_cours', 'termine']

const caTotal = computed(() => {
  return clientRdvs.value.reduce((sum, r) => sum + (r.montant_total || r.total_ttc || 0), 0)
})

const initiales = computed(() => {
  const p = String(client.value?.prenom ?? '').trim().charAt(0)
  const n = String(client.value?.nom ?? '').trim().charAt(0)
  return `${p}${n}`.toLocaleUpperCase('fr-FR') || '—'
})

// Mot de statut : capitales admises, à la différence du reste de l'interface.
const segmentAffiche = computed(() => {
  const s = String(client.value?.segment ?? '').trim()
  return s ? s.toLocaleUpperCase('fr-FR') : ''
})

const lienTelephone = computed(() => {
  const brut = String(client.value?.telephone ?? '').replace(/[^\d+]/g, '')
  return brut ? `tel:${brut}` : undefined
})

const anneeArrivee = computed(() => {
  const brut = client.value?.created_at || client.value?.createdAt
  if (!brut) return 0
  const d = new Date(brut)
  return Number.isNaN(d.getTime()) ? 0 : d.getFullYear()
})

const notePassages = computed(() =>
  anneeArrivee.value ? `depuis ${anneeArrivee.value}` : 'depuis l’ouverture de la fiche',
)

const noteDepense = computed(() => {
  if (!clientRdvs.value.length) return 'aucun passage facturé'
  if (caTotal.value > 0) return `${formatCurrency(caTotal.value / clientRdvs.value.length)} en moyenne par passage`
  // Honnête plutôt que flatteur : le montant n'est pas nul, il n'est pas remonté.
  return 'aucun montant remonté sur ces passages'
})

/** Les rendez-vous en cours de séjour, du plus récent au plus ancien. */
const rdvsEnAtelier = computed(() =>
  clientRdvs.value.filter(r => STATUTS_A_L_ATELIER.includes(String(r.status))),
)

const motosAAtelier = computed(() => {
  const ids = rdvsEnAtelier.value.map(r => r.vehicule_id).filter(Boolean)
  return Array.from(new Set(ids))
})

const noteMotos = computed(() => {
  const n = motosAAtelier.value.length
  if (n === 0) return 'aucune à l’atelier'
  if (n === 1) return 'une est à l’atelier'
  return `${n} sont à l’atelier`
})

const historique = computed(() => {
  if (motoFiltree.value === null) return clientRdvs.value
  return clientRdvs.value.filter(r => r.vehicule_id === motoFiltree.value)
})

const historiqueVisible = computed(() =>
  toutAfficher.value ? historique.value : historique.value.slice(0, PASSAGES_AVANT_REPLI),
)

const restantHistorique = computed(() => historique.value.length - historiqueVisible.value.length)

const dateLaPlusAncienne = computed(() => historique.value[historique.value.length - 1]?.date_rdv ?? '')

const nomMotoFiltree = computed(() => {
  const v = (client.value?.vehicules ?? []).find((x: any) => x.id === motoFiltree.value)
  return v ? `${v.marque ?? ''} ${v.modele ?? ''}`.trim() || v.plaque : 'Cette moto'
})

/** Le gabarit de filtre veut un chiffre : ce que retirer la moto ferait revenir. */
const suggestionFiltre = computed(() => {
  if (!clientRdvs.value.length) return null
  return {
    filtre: nomMotoFiltree.value,
    nombre: clientRdvs.value.length,
    objet: clientRdvs.value.length > 1 ? 'passages' : 'passage',
  }
})

const dernierPassageGlobal = computed(() => {
  const passe = passagesPasses(clientRdvs.value)
  return passe.length ? formatDate(passe[0].date_rdv) : 'jamais venu'
})

function normalizeRdv(r: any) {
  const v = r.vehicule
  const rawDate = String(r.date_rdv ?? r.dateRdv ?? '')
  const rawTime = String(r.heure_rdv ?? r.heureRdv ?? '')
  const timeMatch = rawTime.match(/(\d{2}):(\d{2})/)

  return {
    ...r,
    status: r.statut ?? r.status,
    date_rdv: rawDate ? rawDate.slice(0, 10) : '',
    heure_debut: timeMatch ? `${timeMatch[1]}:${timeMatch[2]}` : '',
    type_intervention: r.type_intervention ?? r.typeIntervention ?? '',
    vehicule_info: v ? `${v.marque} ${v.modele}` : r.vehicule_info ?? '',
    vehicule_id: v?.id ?? r.vehicule_id,
    mecanicien_nom: r.mecanicien ? `${r.mecanicien.prenom ?? ''} ${r.mecanicien.nom ?? ''}`.trim() : '',
  }
}

function formatDate(d: string) {
  if (!d) return '—'
  try { return new Date(d).toLocaleDateString('fr-FR') } catch { return d }
}

function formatCurrency(v: number) {
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(v || 0)
}

/** 28 412 km — groupement français, et l'unité collée par une espace insécable. */
function formatKm(km: number) {
  return `${new Intl.NumberFormat('fr-FR').format(km)} km`
}

function vehicleRdvCount(vehicleId: number) {
  return clientRdvs.value.filter(r => r.vehicule_id === vehicleId).length
}

function vehicleRdvs(vehicleId: number) {
  return clientRdvs.value.filter(r => r.vehicule_id === vehicleId)
}

/**
 * La liste arrive du plus récent au plus ancien, rendez-vous À VENIR compris :
 * prendre [0] tel quel afficherait une date future sous « dernier passage ».
 * On écarte donc ce qui n'a pas encore eu lieu.
 */
function passagesPasses(passages: any[]) {
  const aujourdHui = new Date().toISOString().slice(0, 10)
  return passages.filter(r => r.date_rdv && r.date_rdv <= aujourdHui)
}

function dernierPassage(vehicleId: number) {
  const passages = passagesPasses(vehicleRdvs(vehicleId))
  return passages.length ? formatDate(passages[0].date_rdv) : 'jamais'
}

function estAAtelier(vehicleId: number) {
  return motosAAtelier.value.includes(vehicleId)
}

function basculerMoto(vehicleId: number) {
  motoFiltree.value = motoFiltree.value === vehicleId ? null : vehicleId
  toutAfficher.value = false
}

/** Pont, mécanicien : ce qu'on cherche quand on décroche et qu'on doit situer la moto. */
function ligneAtelier(rdv: any) {
  const morceaux: string[] = []
  if (rdv.pont?.nom) morceaux.push(rdv.pont.nom)
  if (rdv.type_intervention) morceaux.push(rdv.type_intervention)
  if (rdv.mecanicien_nom) morceaux.push(`confié à ${rdv.mecanicien_nom}`)
  return morceaux.join(' · ')
}

function sousLigne(rdv: any) {
  const morceaux: string[] = []
  if (rdv.commentaire) morceaux.push(String(rdv.commentaire))
  if (rdv.mecanicien_nom) morceaux.push(`confié à ${rdv.mecanicien_nom}`)
  return morceaux.join(' · ')
}

function ouvrirEdition() {
  showEditClient.value = true
}

async function saveClient() {
  savingClient.value = true
  try {
    await api.put(`/clients/${route.params.id}`, editForm)
    client.value = { ...client.value, ...editForm }
    showEditClient.value = false
    toast.add({ title: 'Coordonnées enregistrées', color: 'success' })
  } catch (e: any) {
    toast.add({
      title: 'Enregistrement impossible',
      description: messageErreur(e, 'les coordonnées restent celles d’avant'),
      color: 'error',
    })
  } finally {
    savingClient.value = false
  }
}

async function exportClient() {
  exporting.value = true
  try {
    const data = await api.get(`/clients/${route.params.id}/export`)
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `client-${route.params.id}-export.json`
    a.click()
    URL.revokeObjectURL(url)
    toast.add({ title: 'Export téléchargé', color: 'success' })
  } catch (e: any) {
    toast.add({
      title: 'Export impossible',
      description: messageErreur(e, 'aucun fichier n’a été produit'),
      color: 'error',
    })
  } finally {
    exporting.value = false
  }
}

function demanderAnonymisation() {
  confirmationEffacement.value = true
}

async function anonymiser() {
  confirmationEffacement.value = false
  try {
    await api.post(`/clients/${route.params.id}/anonymize`)
    toast.add({
      title: 'Données personnelles effacées',
      description: 'Les factures et les ordres gardent leur copie figée.',
      color: 'success',
    })
    const c = await api.get(`/clients/${route.params.id}`)
    client.value = c
  } catch (e: any) {
    toast.add({
      title: 'Effacement impossible',
      description: messageErreur(e, 'la fiche est restée intacte'),
      color: 'error',
    })
  }
}

async function charger() {
  loading.value = true
  erreur.value = ''
  try {
    const [c, rdvData] = await Promise.all([
      api.get(`/clients/${route.params.id}`),
      api.getAll(`/rendez-vous?client.id=${route.params.id}&order[dateRdv]=desc`),
    ])
    client.value = c
    Object.assign(editForm, { prenom: c.prenom, nom: c.nom, telephone: c.telephone, email: c.email || '', adresse: c.adresse || '', notes: c.notes || '' })
    const raw = rdvData?.['hydra:member'] ?? rdvData?.member ?? (Array.isArray(rdvData) ? rdvData : [])
    clientRdvs.value = raw.map(normalizeRdv)
  } catch (e: any) {
    // Sans ça, un échec laissait la page vide et muette : ni cause, ni issue.
    erreur.value = messageErreur(e, 'la fiche n’a pas pu être ouverte')
  } finally {
    loading.value = false
  }
}

onMounted(charger)
</script>

<style scoped>
.fc { display: flex; flex-direction: column; gap: 16px; color: var(--pk-ink); }

/* ---- Retour ---- */
/* La fiche s'ouvre aussi depuis un lien collé : le retour vit dans la page. */
.fc-retour {
  display: inline-flex; align-items: center; gap: 8px; align-self: flex-start;
  min-height: var(--pk-target-desk); padding: 0 4px;
  font-size: 13px; font-weight: 600; color: var(--pk-ink-quiet); text-decoration: none;
}
.fc-retour:hover { color: var(--pk-ink); }

/* ---- En-tête d'identité ---- */
.fc-tete {
  display: flex; align-items: flex-start; gap: 22px; flex-wrap: wrap;
  padding: 18px 22px; background: var(--pk-surface);
  border: 1px solid var(--pk-border); border-radius: var(--pk-radius-card);
}
.fc-initiales {
  flex-shrink: 0; width: 56px; height: 56px;
  display: flex; align-items: center; justify-content: center;
  border-radius: var(--pk-radius-pill); background: var(--pk-canvas);
  font-size: 19px; font-weight: 600;
}
.fc-identite { display: flex; flex-direction: column; gap: 8px; min-width: 0; flex: 1 1 320px; }
.fc-nom-ligne { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.fc-nom { margin: 0; font-size: 26px; font-weight: 500; letter-spacing: -0.015em; line-height: 1.1; }
.fc-segment {
  padding: 3px 10px; border: 1px solid var(--pk-border-control); border-radius: var(--pk-radius-pill);
  font-size: 11px; font-weight: 700; letter-spacing: 0.04em; color: var(--pk-ink-quiet);
}
.fc-contact { display: flex; flex-wrap: wrap; gap: 6px 18px; font-size: 13px; color: var(--pk-ink-quiet); }
.fc-contact-item { display: inline-flex; align-items: center; gap: 6px; }
.fc-contact-lien { color: var(--pk-ink-quiet); text-decoration: none; }
.fc-contact-lien:hover { color: var(--pk-ink); text-decoration: underline; }
/* Un numéro manquant n'est pas une case vide : c'est un rappel qui ne partira pas. */
.fc-contact-absent { color: var(--pk-warning-ink); }
.fc-actions { display: flex; flex-wrap: wrap; gap: var(--pk-target-gap); margin-top: 2px; }

/* ---- Les trois mesures ---- */
.fc-mesures { display: flex; flex-wrap: wrap; gap: 12px; margin-left: auto; }
.fc-mesure {
  display: flex; flex-direction: column; gap: 3px; min-width: 132px; padding: 12px 18px;
  background: var(--pk-surface-raised); border: 1px solid var(--pk-border); border-radius: var(--pk-radius-card);
}
/* La moto présente est le fait qui commande l'appel : la mesure le porte. */
.fc-mesure--attention { background: var(--pk-accent-soft); border-color: var(--pk-accent); }
.fc-mesure--attention .fc-surtitre,
.fc-mesure--attention .fc-mesure-valeur,
.fc-mesure--attention .fc-mesure-note { color: var(--pk-accent-ink); }
.fc-mesure-valeur { font-size: 22px; font-weight: 700; line-height: 1; }
.fc-mesure-note { font-size: 11px; color: var(--pk-ink-muted); }
.fc-surtitre {
  font-size: 11px; font-weight: 700; letter-spacing: 0.08em;
  text-transform: uppercase; color: var(--pk-ink-muted);
}

/* ---- Corps : une seule page, deux colonnes, aucun onglet ---- */
.fc-corps {
  display: grid; grid-template-columns: minmax(0, 1fr) minmax(300px, 344px);
  gap: 16px; align-items: start;
}
.fc-colonne, .fc-aside { display: flex; flex-direction: column; gap: 14px; min-width: 0; }

/* ---- Bandeau « à l'atelier » ---- */
/* La maquette pose un aplat noir ; aucun token ne le porte, et le seul noir
   disponible (--pk-border-strong) s'inverse dans le thème d'atelier. La surface
   d'attention du design system dit la même chose et tient dans les deux thèmes. */
.fc-atelier {
  display: flex; align-items: center; gap: 12px; flex-wrap: wrap; padding: 13px 16px;
  background: var(--pk-accent-soft); border: 1px solid var(--pk-accent); border-radius: var(--pk-radius-card);
}
.fc-atelier-icone { font-size: 20px; color: var(--pk-accent-ink); flex-shrink: 0; }
.fc-atelier-texte { flex: 1 1 260px; min-width: 0; }
.fc-atelier-titre { margin: 0; font-size: 15px; font-weight: 600; color: var(--pk-accent-ink); }
.fc-atelier-detail { margin: 2px 0 0; font-size: 13px; color: var(--pk-accent-ink); }

/* ---- Cartes ---- */
.fc-carte {
  display: flex; flex-direction: column; background: var(--pk-surface);
  border: 1px solid var(--pk-border); border-radius: var(--pk-radius-card); overflow: hidden;
}
.fc-carte-tete {
  display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
  padding: 6px 16px; min-height: 52px; border-bottom: 1px solid var(--pk-border);
}
.fc-carte-icone { font-size: 17px; color: var(--pk-ink-quiet); }
.fc-carte-titre { margin: 0; font-size: 14px; font-weight: 600; }
.fc-carte-note { margin-left: auto; font-size: 12px; color: var(--pk-ink-quiet); }
.fc-carte-lien { margin-left: auto; min-height: var(--pk-target-desk); }
.fc-carte-corps { padding: 16px; }
.fc-compteur {
  min-width: 20px; height: 20px; padding: 0 6px;
  display: inline-flex; align-items: center; justify-content: center;
  border-radius: var(--pk-radius-pill); background: var(--pk-canvas);
  border: 1px solid var(--pk-border); font-size: 11px; font-weight: 700;
}
.fc-filtre {
  display: inline-flex; align-items: center; gap: 6px; min-height: var(--pk-target-desk); padding: 4px 12px;
  border: 1px solid var(--pk-accent); border-radius: var(--pk-radius-pill);
  background: var(--pk-accent-soft); color: var(--pk-accent-ink);
  font-size: 12px; font-weight: 600; font-family: inherit; cursor: pointer;
}

/* ---- Ses motos ---- */
.fc-motos { display: flex; flex-direction: column; }
.fc-moto {
  display: flex; align-items: center; gap: 14px; width: 100%;
  min-height: var(--pk-target-desk); padding: 10px 16px;
  border: none; border-bottom: 1px solid var(--pk-border-quiet);
  background: transparent; color: inherit; font-family: inherit; text-align: left;
  cursor: pointer; transition: background var(--pk-duration-state) var(--pk-easing);
}
.fc-moto:last-child { border-bottom: none; }
.fc-moto:hover { background: var(--pk-surface-raised); }
/* Le filet gauche dit laquelle commande l'historique du dessous. */
.fc-moto--retenue { background: var(--pk-surface-raised); box-shadow: inset 3px 0 0 var(--pk-accent); }
.fc-moto-icone { font-size: 22px; color: var(--pk-ink-quiet); flex-shrink: 0; }
.fc-moto-identite { display: flex; flex-direction: column; gap: 1px; flex: 1 1 200px; min-width: 0; }
.fc-moto-nom { font-size: 14px; font-weight: 600; }
.fc-moto-immat { font-size: 12px; color: var(--pk-ink-muted); }
.fc-moto-col { display: flex; flex-direction: column; gap: 1px; width: 132px; flex-shrink: 0; }
.fc-moto-etiquette { font-size: 12px; color: var(--pk-ink-muted); }
.fc-moto-valeur { font-size: 13px; }
.fc-moto-chevron { font-size: 18px; color: var(--pk-ink-muted); flex-shrink: 0; }
.fc-jeton {
  flex-shrink: 0; padding: 4px 10px; border-radius: var(--pk-radius-pill);
  font-size: 11px; font-weight: 700; white-space: nowrap;
}
.fc-jeton--atelier { background: var(--pk-accent-soft); border: 1px solid var(--pk-accent); color: var(--pk-accent-ink); }
.fc-jeton--dehors { border: 1px solid var(--pk-border); color: var(--pk-ink-quiet); }

/* ---- Historique fusionné ---- */
.fc-lignes { display: flex; flex-direction: column; }
.fc-ligne {
  display: grid; grid-template-columns: 118px minmax(0, 1fr) 168px auto 24px;
  align-items: center; gap: 12px; width: 100%;
  min-height: var(--pk-target-desk); padding: 9px 16px;
  border: none; border-bottom: 1px solid var(--pk-border-quiet);
  background: transparent; color: inherit; font-family: inherit; text-align: left;
  cursor: pointer; transition: background var(--pk-duration-state) var(--pk-easing);
}
.fc-ligne:nth-child(odd) { background: var(--pk-surface-raised); }
.fc-ligne:hover { background: var(--pk-canvas); }
.fc-ligne-quand { display: flex; flex-direction: column; gap: 1px; }
.fc-ligne-date { font-size: 13px; font-weight: 600; }
.fc-ligne-heure { font-size: 12px; color: var(--pk-ink-muted); font-variant-numeric: tabular-nums; }
.fc-ligne-objet { display: flex; flex-direction: column; gap: 1px; min-width: 0; }
.fc-ligne-titre { font-size: 13px; font-weight: 600; }
.fc-ligne-sous { font-size: 12px; color: var(--pk-ink-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.fc-ligne-moto { font-size: 12px; color: var(--pk-ink-quiet); }
.fc-ligne-chevron { font-size: 18px; color: var(--pk-ink-muted); }
/* Un repli, pas un onglet : il annonce ce qu'il cache et jusqu'où ça remonte. */
.fc-plus {
  display: flex; align-items: center; gap: 10px; width: 100%;
  min-height: var(--pk-target-desk); padding: 11px 16px;
  border: none; border-top: 1px solid var(--pk-border); background: var(--pk-surface-raised);
  color: inherit; font-family: inherit; text-align: left; cursor: pointer;
}
.fc-plus:hover { background: var(--pk-canvas); }
.fc-plus-libelle { font-size: 13px; font-weight: 600; color: var(--pk-link); }
.fc-plus-note { margin-left: auto; font-size: 12px; color: var(--pk-ink-quiet); }

/* ---- Colonne de droite : ce qui ne sort pas de l'atelier ---- */
.fc-note {
  margin: 0; padding: 12px 18px; background: var(--pk-accent-soft);
  border-bottom: 1px solid var(--pk-border-quiet);
  font-size: 13px; line-height: 1.45; color: var(--pk-accent-ink); white-space: pre-wrap;
}
.fc-note--vide { background: transparent; color: var(--pk-ink-quiet); }
.fc-fait { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; font-size: 13px; }
.fc-fait-cle { color: var(--pk-ink-quiet); }
.fc-fait-donnee { font-weight: 600; text-align: right; }
.fc-aide { margin: 0; font-size: 12px; line-height: 1.45; color: var(--pk-ink-quiet); }
.fc-pile { display: flex; flex-direction: column; gap: var(--pk-target-gap); }
.fc-btn-danger { background: var(--pk-error-surface); border-color: var(--pk-error-line); color: var(--pk-error-ink); }
.fc-btn-danger:hover {
  background: var(--pk-error-surface); border-color: var(--pk-error-line);
  color: var(--pk-error-ink); text-decoration: underline;
}
.fc-efface {
  display: flex; align-items: flex-start; gap: 10px; padding: 12px;
  background: var(--pk-error-surface); border: 1px solid var(--pk-error-line); border-radius: var(--pk-radius-card);
}
.fc-efface-icone { font-size: 18px; color: var(--pk-error-line); flex-shrink: 0; }
.fc-efface-texte { margin: 0; font-size: 13px; line-height: 1.45; color: var(--pk-error-ink); }
.fc-aside-pied { padding: 14px 18px; }

/* ---- Panneau de correction ---- */
.fc-form { display: flex; flex-direction: column; }
.fc-duo { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; }
.fc-form .form-group { display: flex; flex-direction: column; gap: 5px; }

/* ---- Effacement RGPD ---- */
.fc-modale-titre { font-size: 15px; font-weight: 600; }
.fc-modale { display: flex; flex-direction: column; gap: 12px; font-size: 13px; line-height: 1.5; }
.fc-modale p { margin: 4px 0 0; }
.fc-modale-bloc { display: flex; flex-direction: column; padding: 12px 14px; border-radius: var(--pk-radius-card); }
.fc-modale-bloc--part { background: var(--pk-error-surface); border: 1px solid var(--pk-error-line); color: var(--pk-error-ink); }
.fc-modale-bloc--part .fc-surtitre { color: var(--pk-error-ink); }
.fc-modale-bloc--reste { background: var(--pk-success-surface); border: 1px solid var(--pk-success-line); color: var(--pk-success-ink); }
.fc-modale-bloc--reste .fc-surtitre { color: var(--pk-success-ink); }
.fc-modale-arret { font-weight: 600; color: var(--pk-ink); }

/* ---- Cibles et focus ---- */
/* Les sélecteurs partent de l'élément, jamais d'un ancêtre : le panneau de
   travail et la modale sont téléportés dans <body>, où `.fc` n'est plus
   au-dessus d'eux. La marque de portée, elle, suit l'élément partout.

   Et 44 px, parce que `.btn` de la feuille commune s'arrête à 38 : on relève
   la cible ici plutôt que de toucher une feuille partagée. */
.btn { min-height: var(--pk-target-desk); }
a:focus-visible,
button:focus-visible,
input:focus-visible,
textarea:focus-visible {
  outline: var(--pk-focus-width) solid var(--pk-focus-ring);
  outline-offset: var(--pk-focus-offset);
}

@media (max-width: 1100px) {
  .fc-corps { grid-template-columns: minmax(0, 1fr); }
  .fc-mesures { margin-left: 0; }
}

/* Sous 780 px la ligne d'historique perd ses colonnes secondaires plutôt que
   de se tasser : la date, l'objet et le statut restent lisibles. */
@media (max-width: 780px) {
  .fc-ligne { grid-template-columns: minmax(0, 1fr) auto; row-gap: 4px; }
  .fc-ligne-moto, .fc-ligne-chevron, .fc-moto-col { display: none; }
}
</style>
