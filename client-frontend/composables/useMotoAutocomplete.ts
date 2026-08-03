/**
 * Filtre en cascade marque -> modèle sur le référentiel moto (ModeleMoto/CategorieMoto).
 * Porté depuis frontend/composables/useMotoAutocomplete.ts (même API /api/motos/*),
 * adapté à useClientApi() (apiFetch prend un chemin complet, pas de préfixe auto).
 */
type MotoAutocompleteState = Record<string, any>

type MotoAutocompleteOptions = {
  form: MotoAutocompleteState
  marqueKey: string
  modeleKey: string
  cylindreeKey?: string
  typeKey?: string
  anneeKey?: string
}

type MotoSuggestionItem = {
  id?: number | null
  marque?: string
  modele?: string
  categorie_nom?: string | null
  cylindree_min?: number | null
  cylindree_max?: number | null
  annee_debut?: number | null
  annee_fin?: number | null
}

export function useMotoAutocomplete(options: MotoAutocompleteOptions) {
  const { apiFetch } = useClientApi()
  const marqueSuggestions = ref<string[]>([])
  const modeleSuggestions = ref<MotoSuggestionItem[]>([])
  const allMarques = ref<string[]>([])

  let marqueTimer: ReturnType<typeof setTimeout> | null = null
  let modeleTimer: ReturnType<typeof setTimeout> | null = null

  function readField(key?: string) {
    return key ? String(options.form[key] ?? '') : ''
  }

  function writeField(key: string | undefined, value: any) {
    if (!key) return
    options.form[key] = value
  }

  function extractNumericCylindree(item: MotoSuggestionItem): string {
    return String(item.cylindree_min ?? '').match(/\d{2,5}/)?.[0] || ''
  }

  async function onMarqueInput() {
    if (marqueTimer) clearTimeout(marqueTimer)

    marqueTimer = setTimeout(async () => {
      const query = readField(options.marqueKey).trim()
      if (query.length < 1) {
        marqueSuggestions.value = []
        return
      }

      if (!allMarques.value.length) {
        try {
          const data: any = await apiFetch('/api/motos/marques')
          allMarques.value = Array.isArray(data) ? data : (data?.marques ?? [])
        } catch {
          allMarques.value = []
        }
      }

      marqueSuggestions.value = allMarques.value
        .filter(item => String(item).toLowerCase().includes(query.toLowerCase()))
        .slice(0, 8)
    }, 180)
  }

  async function onModeleInput() {
    if (modeleTimer) clearTimeout(modeleTimer)

    modeleTimer = setTimeout(async () => {
      const marque = readField(options.marqueKey).trim()
      const query = readField(options.modeleKey).trim()

      if (query.length < 1 && marque.length < 1) {
        modeleSuggestions.value = []
        return
      }

      try {
        const params = new URLSearchParams({ limit: '10' })
        if (marque) params.set('marque', marque)
        if (query) params.set('query', query)
        const data = await apiFetch(`/api/motos/autocomplete?${params.toString()}`)
        modeleSuggestions.value = Array.isArray(data) ? data : []
      } catch {
        modeleSuggestions.value = []
      }
    }, 220)
  }

  function selectMarque(value: string) {
    writeField(options.marqueKey, value)
    marqueSuggestions.value = []
    modeleSuggestions.value = []
  }

  function selectModele(item: MotoSuggestionItem) {
    writeField(options.marqueKey, item.marque || readField(options.marqueKey))
    writeField(options.modeleKey, item.modele || '')

    const cylindree = extractNumericCylindree(item)
    if (cylindree) writeField(options.cylindreeKey, cylindree)

    // annee_fin (dernière année du modèle) sert de valeur par défaut la plus probable ;
    // l'utilisateur ajuste si sa moto est un millésime antérieur.
    const annee = item.annee_fin || item.annee_debut || ''
    if (annee) writeField(options.anneeKey, String(annee))

    if (item.categorie_nom) writeField(options.typeKey, String(item.categorie_nom))

    modeleSuggestions.value = []
    marqueSuggestions.value = []
  }

  function deferHideMarqueSuggestions() {
    setTimeout(() => { marqueSuggestions.value = [] }, 160)
  }

  function deferHideModeleSuggestions() {
    setTimeout(() => { modeleSuggestions.value = [] }, 160)
  }

  function suggestionLabel(item: MotoSuggestionItem) {
    const cylindree = item.cylindree_min ? `${item.cylindree_min}cc` : ''
    const years = item.annee_debut || item.annee_fin
      ? `${item.annee_debut ?? '…'}-${item.annee_fin ?? '…'}`
      : ''

    return [item.modele, item.categorie_nom, cylindree, years].filter(Boolean).join(' • ')
  }

  return {
    marqueSuggestions,
    modeleSuggestions,
    onMarqueInput,
    onModeleInput,
    selectMarque,
    selectModele,
    deferHideMarqueSuggestions,
    deferHideModeleSuggestions,
    suggestionLabel,
  }
}
