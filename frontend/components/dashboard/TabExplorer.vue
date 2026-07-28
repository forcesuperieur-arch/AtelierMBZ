<template>
  <div class="tab">
    <AppErrorState v-if="store.erreur" :description="store.erreur" @retry="lancer()" />

    <template v-else>
      <!-- Points de départ : une question métier en un clic, plutôt qu'un
           formulaire vide devant lequel on ne sait pas quoi demander. -->
      <div class="departs">
        <button
          v-for="depart in DEPARTS"
          :key="depart.key"
          type="button"
          class="depart"
          :class="{ 'depart--on': departActif === depart.key }"
          @click="choisirDepart(depart.key)"
        >
          <span class="depart-titre">{{ depart.titre }}</span>
          <span class="depart-desc">{{ depart.description }}</span>
        </button>
      </div>

      <!-- Barre de sélection : l'état complet de l'analyse, toujours visible. -->
      <div class="selection">
        <div class="selection-gauche">
          <span class="selection-label">Sélection</span>
          <template v-if="store.filtresActifs.length">
            <button
              v-for="filtre in store.filtresActifs"
              :key="filtre.field"
              type="button"
              class="puce"
              :title="`Retirer le filtre ${store.libelleAxe(filtre.field)}`"
              @click="store.retirerFiltre(filtre.field); lancer()"
            >
              <span class="puce-champ">{{ store.libelleAxe(filtre.field) }}</span>
              <span class="puce-valeurs">{{ resumerValeurs(filtre.values) }}</span>
              <span class="puce-croix" aria-hidden="true">✕</span>
            </button>
            <button type="button" class="tout-effacer" @click="store.toutEffacer(); lancer()">
              Tout effacer
            </button>
          </template>
          <span v-else class="selection-vide">aucun filtre — tous les rendez-vous de la période</span>
        </div>
        <div class="compteur" :class="{ 'compteur--filtre': store.filtresActifs.length }">
          <strong>{{ formatNombre(store.univers.selection) }}</strong>
          <span>sur {{ formatNombre(store.univers.periode) }} RDV</span>
        </div>
      </div>

      <DashboardSection
        title="Croisement"
        :subtitle="sousTitre"
      >
        <template #actions>
          <button type="button" class="lien-outil" @click="panneauOuvert = !panneauOuvert">
            {{ panneauOuvert ? 'Masquer les filtres' : 'Filtres' }}
          </button>
          <button type="button" class="lien-outil" @click="ouvrirEnregistrement">Enregistrer la vue</button>
        </template>

        <!-- Choix des axes et des mesures. Aucun bouton de validation :
             chaque changement relance la requête. -->
        <div class="reglages">
          <label class="champ">
            <span class="champ-label">Axe principal</span>
            <select v-model="axe1" class="champ-input" @change="lancer()">
              <option v-for="a in store.catalogueAxes" :key="a.key" :value="a.key">{{ a.libelle }}</option>
            </select>
          </label>
          <label class="champ">
            <span class="champ-label">Croisé avec</span>
            <select v-model="axe2" class="champ-input" @change="lancer()">
              <option value="">— aucun —</option>
              <option v-for="a in axesSecondaires" :key="a.key" :value="a.key">{{ a.libelle }}</option>
            </select>
          </label>
          <div class="champ champ--mesures">
            <span class="champ-label">Mesures ({{ store.mesures.length }}/6)</span>
            <div class="mesures">
              <button
                v-for="m in store.catalogueMesures"
                :key="m.key"
                type="button"
                class="mesure"
                :class="{ 'mesure--on': store.mesures.includes(m.key) }"
                :aria-pressed="store.mesures.includes(m.key)"
                @click="basculerMesure(m.key)"
              >
                {{ m.libelle }}
              </button>
            </div>
          </div>
        </div>

        <div v-if="panneauOuvert" class="panneau">
          <DashboardExplorerFacets @change="lancer()" />
        </div>

        <div class="resultat" :class="{ 'resultat--attente': store.chargement }">
          <DashboardExplorerTable
            :rows="store.rows"
            :total="store.total"
            :axes="axesActifs"
            :mesures="store.mesures"
            @selectionner="(champ, valeur) => { store.basculer(champ, valeur); lancer() }"
          />
        </div>
      </DashboardSection>

      <DashboardSection
        title="Les rendez-vous derrière ces chiffres"
        :count="store.detailOuvert ? store.detail.length : undefined"
        subtitle="Un agrégat qu'on ne peut pas ouvrir reste une affirmation invérifiable."
      >
        <template #actions>
          <button type="button" class="lien-outil" @click="basculerDetail">
            {{ store.detailOuvert ? 'Replier' : 'Voir la liste' }}
          </button>
        </template>

        <div v-if="store.detailOuvert">
          <ul v-if="store.detail.length" class="detail">
            <li v-for="ligne in store.detail" :key="ligne.rdv_id" class="detail-ligne">
              <div class="detail-principal">
                <span class="detail-plaque">{{ ligne.vehicule_plaque || 'Sans plaque' }}</span>
                <span class="detail-sep" aria-hidden="true">·</span>
                <span>{{ ligne.vehicule_info || 'Moto' }}</span>
                <span class="detail-sep" aria-hidden="true">·</span>
                <span>{{ ligne.client_nom || 'Client inconnu' }}</span>
                <span v-if="ligne.litige_signale" class="detail-litige">litige</span>
              </div>
              <div class="detail-meta">
                <span>{{ formatDate(ligne.date_rdv) }}</span>
                <span>{{ ligne.type_intervention || 'Type non précisé' }}</span>
                <span v-if="ligne.mecanicien_nom">{{ ligne.mecanicien_nom }}</span>
                <span v-if="ligne.temps_effectif">{{ formatMinutes(ligne.temps_effectif) }} pointées</span>
                <button type="button" class="detail-action" @click="ouvrirFiche(ligne)">Ouvrir la fiche →</button>
              </div>
            </li>
          </ul>
          <p v-else class="vide">Aucun rendez-vous dans cette sélection.</p>
          <p v-if="store.detail.length >= 300" class="detail-note">
            Liste limitée aux 300 rendez-vous les plus récents — affine la sélection pour la réduire.
          </p>
        </div>
      </DashboardSection>

      <DashboardSection v-if="store.vues.length" title="Mes vues" :count="store.vues.length">
        <div class="vues">
          <div v-for="vue in store.vues" :key="vue.nom" class="vue">
            <button type="button" class="vue-ouvrir" @click="store.ouvrirVue(vue.nom); lancer()">
              {{ vue.nom }}
            </button>
            <button type="button" class="vue-suppr" :title="`Supprimer ${vue.nom}`" @click="store.supprimerVue(vue.nom)">✕</button>
          </div>
        </div>
      </DashboardSection>
    </template>
  </div>
</template>

<script setup lang="ts">
/**
 * Onglet « Explorer » — analyse libre à la façon d'un outil décisionnel :
 * sélections cumulables qui refiltrent tout l'écran, croisement de deux axes,
 * et descente jusqu'aux rendez-vous concernés.
 */
import { DEPARTS } from '~/stores/explorer'

const store = useExplorerStore()
const dashboard = useDashboardStore()
const { formatNombre, formatMinutes } = useDashboardFormat()
const { formatDate } = useFormat()
const { open: openRdvDetail } = useRdvDetailModal()

const panneauOuvert = ref(true)
const departActif = ref<string | null>(null)

const axe1 = ref(store.axes[0] ?? 'type_intervention')
const axe2 = ref(store.axes[1] ?? '')

const axesActifs = computed(() => [axe1.value, axe2.value].filter(Boolean) as string[])
const axesSecondaires = computed(() => store.catalogueAxes.filter(a => a.key !== axe1.value))

const sousTitre = computed(() => {
  const mesures = store.mesures.map(m => store.mesure(m)?.libelle ?? m).join(', ')
  return axe2.value
    ? `${store.libelleAxe(axe1.value)} × ${store.libelleAxe(axe2.value)} — ${mesures}.`
    : `${store.libelleAxe(axe1.value)} — ${mesures}.`
})

function resumerValeurs(valeurs: string[]): string {
  if (valeurs.length === 1) return valeurs[0] || '(non renseigné)'
  return `${valeurs.length} valeurs`
}

/** Toute modification passe par ici : l'état est poussé au store, puis requête. */
function lancer() {
  store.axes = axesActifs.value
  store.relancer(dashboard.periode)
}

function basculerMesure(cle: string) {
  const dedans = store.mesures.includes(cle)
  if (dedans && store.mesures.length === 1) return // au moins une mesure
  if (!dedans && store.mesures.length >= 6) return // six suffit à saturer l'écran
  store.mesures = dedans ? store.mesures.filter(m => m !== cle) : [...store.mesures, cle]
  lancer()
}

function choisirDepart(cle: string) {
  store.appliquerDepart(cle)
  departActif.value = cle
  axe1.value = store.axes[0] ?? 'type_intervention'
  axe2.value = store.axes[1] ?? ''
  lancer()
}

function basculerDetail() {
  store.detailOuvert = !store.detailOuvert
  if (store.detailOuvert) store.chargerDetail(dashboard.periode)
}

function ouvrirFiche(ligne: any) {
  openRdvDetail({
    id: ligne.rdv_id,
    statut: ligne.statut_rdv,
    date_rdv: ligne.date_rdv,
    heure_debut: ligne.heure_rdv?.slice(0, 5),
    type_intervention: ligne.type_intervention || undefined,
    duree_estimee: ligne.temps_estime ? Number(ligne.temps_estime) : undefined,
    pont_nom: ligne.pont_nom || undefined,
    mecanicien_nom: ligne.mecanicien_nom || undefined,
    client_nom: ligne.client_nom || undefined,
    client_telephone: ligne.client_telephone || undefined,
    vehicule_info: ligne.vehicule_info || undefined,
    vehicule_plaque: ligne.vehicule_plaque || undefined,
  })
}

function ouvrirEnregistrement() {
  const nom = window.prompt('Nom de la vue (par exemple : révisions en dépassement)')
  if (nom) store.enregistrerVue(nom)
}

// La période est celle de la barre commune : la changer relance l'analyse.
watch(() => [dashboard.periode.from, dashboard.periode.to], () => lancer())

onMounted(async () => {
  await store.chargerCatalogue()
  store.chargerVues()
  // Les mesures par défaut peuvent viser un module désactivé.
  const dispo = new Set(store.catalogueMesures.map(m => m.key))
  store.mesures = store.mesures.filter(m => dispo.has(m))
  if (!store.mesures.length) store.mesures = ['count']
  lancer()
})
</script>

<style scoped>
.tab { display: flex; flex-direction: column; gap: 16px; }

.departs {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
  gap: 10px;
}
.depart {
  display: flex;
  flex-direction: column;
  gap: 4px;
  /* La feuille globale met les boutons en `nowrap` : sans ce rappel, la
     description d'un point de départ déborde sur la carte voisine. */
  white-space: normal;
  padding: 12px 14px;
  border-radius: var(--radius);
  border: 1px solid var(--glass-border);
  background: var(--glass-bg);
  text-align: left;
  font-family: inherit;
  cursor: pointer;
  transition: border-color var(--transition), background var(--transition);
}
.depart:hover { border-color: var(--border-hover); }
.depart--on { border-color: var(--accent-graphic); background: var(--accent-soft); }
.depart-titre { font-size: 13px; font-weight: 700; color: var(--ink); }
.depart--on .depart-titre { color: var(--accent-content); }
.depart-desc { font-size: 11px; line-height: 1.4; color: var(--ink-muted); white-space: normal; }

.selection {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
  padding: 10px 14px;
  border-radius: var(--radius);
  border: 1px solid var(--glass-border);
  background: var(--dark2);
  /* Toujours sous les yeux : c'est le contexte de tout ce qui est affiché. */
  position: sticky;
  top: 0;
  z-index: 4;
}
.selection-gauche { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; min-width: 0; }
.selection-label {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: var(--ink-muted);
}
.selection-vide { font-size: 12px; color: var(--ink-muted); }
.puce {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 26px;
  padding: 3px 9px;
  border-radius: 999px;
  border: 1px solid var(--accent);
  background: var(--accent-soft);
  color: var(--accent-content);
  font-family: inherit;
  font-size: 12px;
  cursor: pointer;
}
.puce:hover { background: var(--accent-soft); }
.puce-champ { color: var(--ink-muted); }
.puce-valeurs { font-weight: 700; }
.puce-croix { font-size: 10px; opacity: 0.8; }
.tout-effacer {
  border: 0;
  background: transparent;
  color: var(--ink-muted);
  font-family: inherit;
  font-size: 12px;
  text-decoration: underline;
  cursor: pointer;
}
.tout-effacer:hover { color: var(--ink); }
.compteur { display: flex; align-items: baseline; gap: 5px; font-size: 12px; color: var(--ink-muted); white-space: nowrap; }
.compteur strong { font-size: 17px; font-weight: 800; color: var(--ink); font-variant-numeric: tabular-nums; }
.compteur--filtre strong { color: var(--accent-content); }

.reglages {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: 14px 24px;
  margin-bottom: 16px;
}
.champ { display: flex; flex-direction: column; gap: 5px; }
.champ--mesures { flex: 1 1 340px; }
.champ-label {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: var(--ink-muted);
}
.champ-input {
  flex: 0 0 auto;
  min-width: 170px;
  padding: 7px 10px;
  border-radius: var(--radius-sm);
  border: 1px solid var(--glass-border);
  background: var(--overlay-soft);
  color: var(--ink-body);
  font-family: inherit;
  font-size: 13px;
}
.mesures { display: flex; flex-wrap: wrap; gap: 6px; }
.mesure {
  min-height: 28px;
  padding: 4px 10px;
  border-radius: 999px;
  border: 1px solid var(--glass-border);
  background: transparent;
  color: var(--ink-muted);
  font-family: inherit;
  font-size: 12px;
  cursor: pointer;
}
.mesure:hover { border-color: var(--border-hover); color: var(--ink-body); }
.mesure--on {
  border-color: var(--accent-graphic);
  background: var(--accent-soft);
  color: var(--accent-content);
  font-weight: 600;
}

.panneau {
  padding: 14px 0 16px;
  margin-bottom: 16px;
  border-top: 1px solid var(--glass-border);
  border-bottom: 1px solid var(--glass-border);
}

/* Pendant une requête, on garde le résultat précédent en retrait plutôt que de
   faire clignoter un squelette : la lecture n'est pas interrompue. */
.resultat { transition: opacity var(--transition); }
.resultat--attente { opacity: 0.5; }

.lien-outil {
  border: 1px solid var(--glass-border);
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--ink-body);
  font-family: inherit;
  font-size: 12px;
  font-weight: 600;
  min-height: 28px;
  padding: 4px 10px;
  cursor: pointer;
}
.lien-outil:hover { border-color: var(--border-hover); color: var(--ink); }

.detail { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 6px; }
.detail-ligne {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
  padding: 9px 12px;
  border-radius: var(--radius-sm);
  background: var(--glass-bg);
  border: 1px solid var(--glass-border);
}
.detail-principal { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; font-size: 13px; color: var(--ink-body); }
.detail-plaque { font-weight: 700; color: var(--ink); }
.detail-sep { color: var(--ink-muted); }
.detail-litige {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: 1px 7px;
  border-radius: 999px;
  background: var(--status-crit-soft);
  color: var(--error-content);
}
.detail-meta { display: flex; align-items: center; flex-wrap: wrap; gap: 12px; font-size: 12px; color: var(--ink-muted); }
.detail-action {
  min-height: 28px;
  padding: 4px 10px;
  border-radius: var(--radius-sm);
  border: 1px solid var(--accent);
  background: var(--accent-soft);
  color: var(--accent-content);
  font-family: inherit;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
}
.detail-action:hover { background: var(--accent-soft); }
.detail-note { margin: 10px 0 0; font-size: 11px; color: var(--ink-muted); }

.vues { display: flex; flex-wrap: wrap; gap: 8px; }
.vue {
  display: inline-flex;
  align-items: center;
  border-radius: var(--radius-sm);
  border: 1px solid var(--glass-border);
  overflow: hidden;
}
.vue-ouvrir, .vue-suppr {
  border: 0;
  background: transparent;
  color: var(--ink-body);
  font-family: inherit;
  font-size: 12px;
  min-height: 30px;
  padding: 5px 10px;
  cursor: pointer;
}
.vue-ouvrir:hover { background: var(--overlay-hover); color: var(--ink); }
.vue-suppr { color: var(--ink-muted); border-left: 1px solid var(--glass-border); }
.vue-suppr:hover { color: var(--error-content); }

.vide { margin: 0; font-size: 13px; color: var(--ink-muted); }
</style>
