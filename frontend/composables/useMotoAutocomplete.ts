type MotoAutocompleteState = Record<string, any>

type MotoAutocompleteOptions = {
  form: MotoAutocompleteState
  marqueKey: string
  modeleKey: string
  cylindreeKey?: string
  typeKey?: string
  anneeKey?: string
  categorieKey?: string
  typeTransform?: (value: string) => string
}

type MotoSuggestionItem = {
  id?: number | null
  marque?: string
  modele?: string
  categorie_id?: number | null
  categorie_nom?: string | null
  cylindree?: string | null
  cylindree_min?: number | null
  cylindree_max?: number | null
  annee_debut?: number | null
  annee_fin?: number | null
}

export function useMotoAutocomplete(options: MotoAutocompleteOptions) {
  const api = useApi()
  const marqueSuggestions = ref<string[]>([])
  const modeleSuggestions = ref<MotoSuggestionItem[]>([])
  const allMarques = ref<string[]>([])
  const selectedModele = ref<MotoSuggestionItem | null>(null)

  function readField(key?: string) {
    return key ? String(options.form[key] ?? '') : ''
  }

  function writeField(key: string | undefined, value: any) {
    if (!key) return
    options.form[key] = value
  }

  function extractNumericCylindree(item: MotoSuggestionItem): string {
    const direct = String(item.cylindree_min ?? item.cylindree ?? '').match(/\d{2,5}/)?.[0]
    return direct || ''
  }

  const onMarqueInput = useDebounceFn(async () => {
    const query = readField(options.marqueKey).trim()
    if (query.length < 1) {
      marqueSuggestions.value = []
      return
    }

    if (!allMarques.value.length) {
      try {
        const data = await api.get('/motos/marques')
        allMarques.value = Array.isArray(data) ? data : (data?.marques ?? [])
      } catch {
        allMarques.value = []
      }
    }

    marqueSuggestions.value = allMarques.value
      .filter(item => String(item).toLowerCase().includes(query.toLowerCase()))
      .slice(0, 8)
  }, 180)

  const onModeleInput = useDebounceFn(async () => {
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
      const data = await api.get(`/motos/autocomplete?${params.toString()}`)
      modeleSuggestions.value = Array.isArray(data) ? data : []
    } catch {
      modeleSuggestions.value = []
    }
  }, 220)

  function selectMarque(value: string) {
    writeField(options.marqueKey, value)
    marqueSuggestions.value = []
    modeleSuggestions.value = []
    selectedModele.value = null
  }

  function selectModele(value: MotoSuggestionItem | string) {
    const item = typeof value === 'string'
      ? (modeleSuggestions.value.find(entry => String(entry.modele || '') === value) ?? { modele: value, marque: readField(options.marqueKey) })
      : value

    selectedModele.value = item
    writeField(options.marqueKey, item.marque || readField(options.marqueKey))
    writeField(options.modeleKey, item.modele || '')

    const cylindree = extractNumericCylindree(item)
    if (cylindree && !readField(options.cylindreeKey)) {
      writeField(options.cylindreeKey, cylindree)
    }

    const annee = item.annee_fin || item.annee_debut || ''
    if (annee && !readField(options.anneeKey)) {
      writeField(options.anneeKey, String(annee))
    }

    if (item.categorie_id && !readField(options.categorieKey)) {
      writeField(options.categorieKey, String(item.categorie_id))
    }

    if (item.categorie_nom && !readField(options.typeKey)) {
      const resolvedType = options.typeTransform
        ? options.typeTransform(String(item.categorie_nom))
        : String(item.categorie_nom)
      writeField(options.typeKey, resolvedType)
    }

    modeleSuggestions.value = []
    marqueSuggestions.value = []
  }

  function deferHideMarqueSuggestions() {
    setTimeout(() => {
      marqueSuggestions.value = []
    }, 160)
  }

  function deferHideModeleSuggestions() {
    setTimeout(() => {
      modeleSuggestions.value = []
    }, 160)
  }

  function suggestionLabel(item: MotoSuggestionItem) {
    const cylindree = item.cylindree_min || item.cylindree_max ? `${item.cylindree_min || item.cylindree_max}cc` : ''
    const years = item.annee_debut || item.annee_fin
      ? `${item.annee_debut || '...'}-${item.annee_fin || '...'} `
      : ''

    return [item.modele, item.categorie_nom, cylindree, years.trim()].filter(Boolean).join(' • ')
  }

  return {
    allMarques,
    selectedModele,
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
