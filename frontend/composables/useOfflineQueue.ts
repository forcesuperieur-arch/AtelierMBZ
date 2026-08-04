// Résilience réseau pour l'espace mécanicien (wifi atelier capricieux).
//
// Deux régimes distincts, volontairement pas symétriques :
// - Sauvegardes IDEMPOTENTES (PATCH qui réécrit un état, rejouable sans
//   risque) : mises en file et rejouées automatiquement à la reconnexion.
// - Actions NON idempotentes (POST de validation, transition, signature) :
//   PAS de rejeu automatique — un retry aveugle pourrait dupliquer une
//   demande complémentaire ou une transition. L'appelant garde la main via
//   `isNetworkError` pour proposer un bouton "Réessayer" explicite.
//
// File en mémoire uniquement (pas de localStorage) : le problème réel est une
// coupure Wi-Fi pendant que l'onglet reste ouvert, pas un redémarrage
// d'application — persister des fonctions across un reload n'a pas de sens.

interface QueuedAction {
  id: number
  label: string
  run: () => Promise<unknown>
  attempts: number
}

const isOnline = ref(true)
const pending = ref<QueuedAction[]>([])
let nextId = 1
let listenersBound = false
let flushing = false

function isNetworkError(e: unknown): boolean {
  return e instanceof Error && /Connexion API impossible/i.test(e.message)
}

async function flushQueue() {
  if (flushing) return
  flushing = true
  try {
    // Copie : une action qui échoue à nouveau reste en file pour le prochain flush,
    // sans bloquer celles qui la suivent.
    for (const action of [...pending.value]) {
      try {
        await action.run()
        pending.value = pending.value.filter(a => a.id !== action.id)
      } catch (e) {
        action.attempts++
        if (!isNetworkError(e)) {
          // Erreur métier (ex: donnée devenue invalide) : rejouer indéfiniment
          // n'aiderait pas, et ça masquerait le problème. On abandonne la file
          // pour cette action ; le mécanicien la referra depuis l'écran.
          pending.value = pending.value.filter(a => a.id !== action.id)
        }
      }
    }
  } finally {
    flushing = false
  }
}

function bindListeners() {
  if (listenersBound || !process.client) return
  listenersBound = true
  isOnline.value = navigator.onLine
  window.addEventListener('online', () => {
    isOnline.value = true
    flushQueue()
  })
  window.addEventListener('offline', () => { isOnline.value = false })
}

export function useOfflineQueue() {
  bindListeners()

  async function runIdempotent<T>(
    label: string,
    run: () => Promise<T>,
  ): Promise<{ queued: true } | { queued: false; value: T }> {
    try {
      const value = await run()
      return { queued: false, value }
    } catch (e) {
      if (isNetworkError(e)) {
        pending.value.push({ id: nextId++, label, run, attempts: 0 })
        return { queued: true }
      }
      throw e
    }
  }

  return { isOnline, pending, isNetworkError, runIdempotent, flushQueue }
}
