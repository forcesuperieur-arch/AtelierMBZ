import { defineStore } from 'pinia'

/**
 * État de l'Explorateur.
 *
 * Principe de sélection associative : il n'y a qu'UN état (période + axes +
 * mesures + pile de filtres) et TOUT le contenu de l'écran en découle. Cliquer
 * n'importe où ajoute un filtre à la pile ; le retirer remet l'univers en
 * place. Aucun bouton « lancer » : chaque changement relance la requête.
 */

export interface Filtre {
  field: string
  values: string[]
  op?: 'in' | 'not_in'
}

export interface AxeCatalogue { key: string, libelle: string, chronologique: boolean }
export interface MesureCatalogue {
  key: string
  libelle: string
  unite: 'nombre' | 'minutes' | 'pourcent' | 'euros' | 'decimal'
  bon: 'haut' | 'bas' | 'neutre'
}

/** Points de départ : une question métier → une configuration prête. */
export const DEPARTS = [
  {
    key: 'temps',
    titre: 'Où part le temps',
    description: 'Écart entre le temps pointé et le temps estimé, par type puis par mécanicien.',
    axes: ['type_intervention', 'mecanicien_nom'],
    mesures: ['count', 'ecart_moyen', 'taux_depassement', 'pointage_renseigne'],
  },
  {
    key: 'immobilisation',
    titre: 'Immobilisation des motos',
    description: 'Combien de temps la moto reste à l’atelier, et où le temps se perd.',
    axes: ['type_intervention'],
    mesures: ['count', 'duree_immobilisation', 'delai_prise_en_charge', 'delai_restitution'],
  },
  {
    key: 'clients',
    titre: 'Origine et fidélité',
    description: 'D’où viennent les rendez-vous, et lesquels des clients reviennent.',
    axes: ['origine', 'client_segment'],
    mesures: ['count', 'clients_uniques', 'rdv_par_client'],
  },
  {
    key: 'charge',
    titre: 'Charge et saisonnalité',
    description: 'Répartition de la charge dans la semaine et dans l’année.',
    axes: ['jour_semaine'],
    mesures: ['count', 'temps_estime_total', 'temps_estime_moyen'],
  },
] as const

interface VueEnregistree {
  nom: string
  axes: string[]
  mesures: string[]
  filtres: Filtre[]
}

const CLE_VUES = 'paddock:explorateur-vues'

export const useExplorerStore = defineStore('explorer', () => {
  const api = useApi()

  const catalogueAxes = ref<AxeCatalogue[]>([])
  const catalogueMesures = ref<MesureCatalogue[]>([])

  const axes = ref<string[]>(['type_intervention'])
  const mesures = ref<string[]>(['count', 'temps_estime_moyen'])
  const filtres = ref<Filtre[]>([])

  const rows = ref<any[]>([])
  const total = ref<Record<string, any>>({})
  const univers = ref<{ selection: number, periode: number }>({ selection: 0, periode: 0 })
  const facettes = ref<Record<string, Array<{ valeur: string, nb: number }>>>({})

  const detail = ref<any[]>([])
  const detailOuvert = ref(false)

  const chargement = ref(false)
  const erreur = ref<string | null>(null)
  const vues = ref<VueEnregistree[]>([])

  let debounce: ReturnType<typeof setTimeout> | null = null

  const libelleAxe = (key: string) => catalogueAxes.value.find(a => a.key === key)?.libelle ?? key
  const mesure = (key: string) => catalogueMesures.value.find(m => m.key === key)
  const filtresActifs = computed(() => filtres.value.filter(f => f.values.length > 0))

  async function chargerCatalogue() {
    if (catalogueAxes.value.length) return
    try {
      const data = await api.get('/analytics/catalogue')
      catalogueAxes.value = data?.axes ?? []
      catalogueMesures.value = data?.mesures ?? []
    } catch {
      erreur.value = "Le catalogue d'analyse n'a pas pu être chargé."
    }
  }

  /** Corps de requête : la même description sert l'agrégat et le détail. */
  function corps(periode: { from: string, to: string }, mode: 'agregat' | 'detail') {
    return {
      from: periode.from,
      to: periode.to,
      dimensions: mode === 'detail' ? [] : axes.value,
      measures: mesures.value,
      filters: filtresActifs.value,
      mode,
    }
  }

  async function interroger(periode: { from: string, to: string }) {
    chargement.value = true
    erreur.value = null
    try {
      const data = await api.post('/analytics/query', corps(periode, 'agregat'))
      rows.value = data?.rows ?? []
      total.value = data?.total ?? {}
      univers.value = data?.univers ?? { selection: 0, periode: 0 }
      facettes.value = data?.facettes ?? {}
      if (detailOuvert.value) await chargerDetail(periode)
    } catch (e: any) {
      erreur.value = e?.statusCode === 403
        ? "Cette analyse est réservée au responsable d'atelier et aux profils supérieurs."
        : "La requête n'a pas abouti. Réessaie dans un instant."
      rows.value = []
    } finally {
      chargement.value = false
    }
  }

  /** Relance groupée : plusieurs clics rapprochés = une seule requête. */
  function relancer(periode: { from: string, to: string }) {
    if (debounce) clearTimeout(debounce)
    debounce = setTimeout(() => interroger(periode), 140)
  }

  async function chargerDetail(periode: { from: string, to: string }) {
    try {
      const data = await api.post('/analytics/query', corps(periode, 'detail'))
      detail.value = data?.rows ?? []
    } catch {
      detail.value = []
    }
  }

  // ── Sélection ──

  /**
   * Bascule une valeur d'un champ : présente → retirée, absente → ajoutée.
   * C'est le seul geste de filtrage de l'écran, qu'on clique une barre, une
   * ligne de tableau croisé ou une valeur du panneau latéral.
   */
  function basculer(field: string, valeur: string) {
    const existant = filtres.value.find(f => f.field === field)
    if (!existant) {
      filtres.value = [...filtres.value, { field, values: [valeur], op: 'in' }]
      return
    }
    const values = existant.values.includes(valeur)
      ? existant.values.filter(v => v !== valeur)
      : [...existant.values, valeur]
    filtres.value = values.length
      ? filtres.value.map(f => (f.field === field ? { ...f, values } : f))
      : filtres.value.filter(f => f.field !== field)
  }

  function estSelectionnee(field: string, valeur: string): boolean {
    return filtres.value.find(f => f.field === field)?.values.includes(valeur) ?? false
  }

  function retirerFiltre(field: string) {
    filtres.value = filtres.value.filter(f => f.field !== field)
  }

  function toutEffacer() {
    filtres.value = []
  }

  function appliquerDepart(key: string) {
    const depart = DEPARTS.find(d => d.key === key)
    if (!depart) return
    // Les mesures d'un point de départ peuvent viser un module désactivé :
    // on ne garde que celles réellement proposées par le serveur.
    const dispo = new Set(catalogueMesures.value.map(m => m.key))
    axes.value = [...depart.axes]
    mesures.value = depart.mesures.filter(m => dispo.has(m))
    if (!mesures.value.length) mesures.value = ['count']
    filtres.value = []
  }

  // ── Vues enregistrées (mémoire locale du navigateur) ──

  function chargerVues() {
    if (!import.meta.client) return
    try {
      vues.value = JSON.parse(localStorage.getItem(CLE_VUES) || '[]')
    } catch {
      vues.value = []
    }
  }

  function enregistrerVue(nom: string) {
    const propre = nom.trim().slice(0, 60)
    if (!propre) return
    const vue: VueEnregistree = {
      nom: propre,
      axes: [...axes.value],
      mesures: [...mesures.value],
      filtres: JSON.parse(JSON.stringify(filtresActifs.value)),
    }
    vues.value = [...vues.value.filter(v => v.nom !== propre), vue]
    localStorage.setItem(CLE_VUES, JSON.stringify(vues.value))
  }

  function ouvrirVue(nom: string) {
    const vue = vues.value.find(v => v.nom === nom)
    if (!vue) return
    axes.value = [...vue.axes]
    mesures.value = [...vue.mesures]
    filtres.value = JSON.parse(JSON.stringify(vue.filtres))
  }

  function supprimerVue(nom: string) {
    vues.value = vues.value.filter(v => v.nom !== nom)
    localStorage.setItem(CLE_VUES, JSON.stringify(vues.value))
  }

  return {
    catalogueAxes, catalogueMesures, axes, mesures, filtres, filtresActifs,
    rows, total, univers, facettes, detail, detailOuvert,
    chargement, erreur, vues,
    libelleAxe, mesure,
    chargerCatalogue, interroger, relancer, chargerDetail,
    basculer, estSelectionnee, retirerFiltre, toutEffacer, appliquerDepart,
    chargerVues, enregistrerVue, ouvrirVue, supprimerVue,
  }
})
