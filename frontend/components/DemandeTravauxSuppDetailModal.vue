<template>
  <AppModal :open="isOpen" @update:open="onOpenChange" size="lg">
    <template #header>
      <div style="display:flex;align-items:center;gap:12px;">
        <span style="font-size:16px;font-weight:700;color:var(--content-1);">
          {{ isEditing ? 'Modifier la demande #' : 'Demande travaux complémentaires #' }}{{ demande?.id }}
        </span>
        <span
          v-if="demande?.urgence === 'urgent'"
          style="font-size:11px;font-weight:700;padding:3px 10px;border-radius:999px;background:var(--error-soft);color:var(--error-content);"
        >URGENT</span>
      </div>
    </template>

    <div v-if="demande" style="display:flex;flex-direction:column;gap:16px;font-size:13px;color:var(--content-2);">
      <!-- Client & Véhicule -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div style="padding:12px;background:var(--overlay-soft);border-radius:8px;border:1px solid var(--border-2);">
          <div style="font-weight:600;color:var(--content-1);margin-bottom:6px;">{{ demande.client_nom || 'Client' }}</div>
          <div v-if="demande.vehicule_info" style="color:var(--content-3);"><AppIcon name="i-ri-motorbike-line" /> {{ demande.vehicule_info }}</div>
          <div v-if="demande.vehicule_plaque" style="color:var(--content-3);">{{ demande.vehicule_plaque }}</div>
        </div>
        <div style="padding:12px;background:var(--overlay-soft);border-radius:8px;border:1px solid var(--border-2);">
          <div style="font-weight:600;color:var(--content-1);margin-bottom:6px;">Récapitulatif</div>
          <div style="color:var(--accent-content);font-size:18px;font-weight:800;">{{ formatEuro(isEditing ? editedPrixTotal : demande.prix_estime) }}</div>
          <div style="color:var(--content-3);font-size:12px;">~{{ formatMinutes(isEditing ? editedTempsTotal : demande.temps_estime) }}</div>
          <div style="color:var(--content-3);font-size:11px;margin-top:4px;">RDV #{{ demande.rendez_vous_id }}</div>
        </div>
      </div>

      <!-- Description -->
      <div style="padding:12px;background:var(--overlay-soft);border-radius:8px;border:1px solid var(--border-2);">
        <div style="font-weight:600;color:var(--content-1);margin-bottom:6px;">Commentaire mécanicien</div>
        <div v-if="!isEditing" style="color:var(--content-3);white-space:pre-wrap;font-style:italic;">
          {{ demande.description ? `« ${demande.description} »` : 'Aucun commentaire' }}
        </div>
        <textarea
          v-else
          v-model="editedDescription"
          rows="2"
          style="width:100%;background:rgba(0,0,0,0.2);border:1px solid var(--border-1);border-radius:6px;padding:8px;color:var(--content-2);font-size:13px;resize:vertical;"
          placeholder="Commentaire du mécanicien…"
        />
      </div>

      <!-- Urgence -->
      <div v-if="isEditing" style="display:flex;align-items:center;gap:8px;">
        <span style="font-weight:600;color:var(--content-1);">Urgence :</span>
        <select v-model="editedUrgence" style="background:rgba(0,0,0,0.2);border:1px solid var(--border-1);border-radius:6px;padding:6px 10px;color:var(--content-2);font-size:13px;">
          <option value="normal">Normal</option>
          <option value="urgent">Urgent</option>
        </select>
      </div>

      <!-- Prestations -->
      <div style="padding:12px;background:var(--overlay-soft);border-radius:8px;border:1px solid var(--border-2);">
        <div style="font-weight:600;color:var(--content-1);margin-bottom:8px;">Prestations</div>

        <div v-if="!isEditing && demande.prestations?.length" style="display:flex;flex-direction:column;gap:6px;">
          <div
            v-for="(p, i) in demande.prestations"
            :key="i"
            style="display:flex;justify-content:space-between;align-items:center;padding:8px 10px;background:var(--overlay-soft);border-radius:6px;"
          >
            <span>{{ p.designation }}</span>
            <span style="color:var(--accent-content);font-weight:700;">{{ formatEuro(p.prix_ttc) }}</span>
          </div>
        </div>

        <div v-else-if="!isEditing" style="color:var(--content-3);font-style:italic;">Aucune prestation.</div>

        <!-- Mode édition -->
        <div v-else style="display:flex;flex-direction:column;gap:8px;">
          <div
            v-for="(p, i) in editedPrestations"
            :key="i"
            style="display:flex;gap:8px;align-items:center;padding:8px 10px;background:var(--overlay-soft);border-radius:6px;flex-wrap:wrap;"
          >
            <span style="flex:1;min-width:120px;">{{ p.designation }}</span>
            <input
              v-model="p.prix_ttc"
              type="number"
              step="0.01"
              min="0"
              style="width:80px;background:rgba(0,0,0,0.2);border:1px solid var(--border-1);border-radius:6px;padding:6px;color:var(--accent-content);font-weight:700;font-size:13px;"
              placeholder="Prix TTC"
            />
            <input
              v-model.number="p.temps_minutes"
              type="number"
              min="0"
              style="width:70px;background:rgba(0,0,0,0.2);border:1px solid var(--border-1);border-radius:6px;padding:6px;color:var(--content-2);font-size:13px;"
              placeholder="Min"
            />
            <button
              type="button"
              style="background:none;border:none;color:var(--error-content);font-size:16px;cursor:pointer;"
              title="Supprimer"
              @click="removePrestation(i)"
            ><AppIcon name="i-ri-delete-bin-line" /></button>
          </div>

          <!-- Ajouter prestation -->
          <div v-if="catalogPrestations.length" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:4px;padding-top:8px;border-top:1px solid var(--border-2);">
            <select v-model="selectedCatalogPrestation" style="flex:1;min-width:160px;background:rgba(0,0,0,0.2);border:1px solid var(--border-1);border-radius:6px;padding:6px 10px;color:var(--content-2);font-size:13px;">
              <option :value="null">+ Ajouter une prestation…</option>
              <option v-for="cp in availableCatalogPrestations" :key="cp.id" :value="cp">
                {{ cp.nom }} — {{ formatEuro(cp.prix_base_ttc) }}
              </option>
            </select>
            <button type="button" class="btn btn-primary" style="font-size:12px;padding:6px 12px;" :disabled="!selectedCatalogPrestation" @click="addPrestation">
              Ajouter
            </button>
          </div>
          <div v-else-if="isEditing" style="color:var(--content-3);font-style:italic;font-size:12px;">Chargement du catalogue…</div>
        </div>
      </div>

      <!-- Statut -->
      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <span
          :style="statutStyle(demande.statut)"
          style="font-size:11px;padding:4px 10px;border-radius:999px;font-weight:700;"
        >{{ labelStatut(demande.statut) }}</span>
        <span
          v-if="isSignatureEnAttente"
          data-testid="badge-signature-attente"
          style="font-size:11px;padding:4px 10px;border-radius:999px;background:var(--warning-soft);color:var(--warning-content);font-weight:700;"
        ><AppIcon name="i-ri-quill-pen-line" /> Signature en attente</span>
        <span v-if="demande.decision_client" style="font-size:11px;padding:4px 10px;border-radius:999px;background:var(--info-soft);color:var(--info-content);font-weight:700;">
          Décision client : <AppIcon :name="demande.decision_client === 'accepte' ? 'i-ri-checkbox-circle-line' : 'i-ri-close-circle-line'" /> {{ demande.decision_client === 'accepte' ? 'Accepté' : 'Refusé' }}
        </span>
        <span v-if="demande.or_complementaire_id" style="font-size:11px;padding:4px 10px;border-radius:999px;background:var(--success-soft);color:var(--success-content);font-weight:700;">
          OR complémentaire #{{ demande.or_complementaire_id }}
        </span>
      </div>

      <!-- Accord téléphonique en attente de signature -->
      <div
        v-if="isSignatureEnAttente && demande.decision_enregistree_par"
        style="font-size:12px;color:var(--content-3);"
      >
        Accord tél. enregistré{{ staffLabel(demande.decision_enregistree_par) }}<span v-if="demande.decision_client_at"> le {{ new Date(demande.decision_client_at).toLocaleString('fr-FR') }}</span>
      </div>
    </div>

    <template #footer>
      <div style="display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;">
        <button class="btn btn-ghost" @click="close">Fermer</button>

        <button
          v-if="canDecideParTelephone && !isEditing"
          class="btn btn-ghost"
          data-testid="btn-decision-telephone"
          @click="ouvrirDecisionTelephone"
        ><AppIcon name="i-ri-phone-line" /> Décision téléphonique</button>

        <button
          v-if="isSignatureEnAttente && demande?.token"
          class="btn btn-primary"
          data-testid="btn-faire-signer-comptoir"
          @click="faireSignerComptoir"
        ><AppIcon name="i-ri-quill-pen-line" /> Faire signer au comptoir</button>

        <button
          v-if="canEdit && !isEditing"
          class="btn btn-primary"
          @click="startEditing"
        ><AppIcon name="i-ri-pencil-line" /> Modifier</button>

        <button
          v-if="isEditing"
          class="btn btn-ghost"
          :disabled="saving"
          @click="cancelEditing"
        >Annuler</button>

        <button
          v-if="isEditing"
          class="btn btn-primary"
          :disabled="saving"
          @click="saveChanges"
        ><AppIcon v-if="!(saving)" name="i-ri-save-line" />{{ saving ? 'Sauvegarde…' : 'Sauvegarder' }}</button>

        <!-- Pas de page de détail OR : référence affichée, sans lien mort. -->
        <span
          v-if="demande?.or_complementaire_id"
          style="font-size:12px;color:var(--content-3);align-self:center;"
        >OR complémentaire n° {{ demande.or_complementaire_id }}</span>
      </div>
    </template>
  </AppModal>
</template>

<script setup lang="ts">
const { isOpen, demandeData: demande, close, updateData } = useDemandeTravauxSuppDetailModal()

// isOpen est readonly (composable) → relayer la fermeture vers close() plutôt
// que d'écrire dans la ref via v-model (no-op silencieux en prod).
function onOpenChange(value: boolean) {
  if (!value) close()
}
const telephoneModal = useDemandeTravauxSuppTelephoneModal()
const api = useApi()
const toast = useToast()

function ouvrirDecisionTelephone() {
  if (!demande.value) return
  telephoneModal.open(demande.value as DemandeTravauxSuppDetailData, (updated) => updateData(updated))
}

function faireSignerComptoir() {
  if (!demande.value?.token) return
  window.open(`${location.origin}/public/demande/${demande.value.token}`, '_blank')
}

function staffLabel(p: any): string {
  const nom = [p?.prenom, p?.nom].filter(Boolean).join(' ').trim()
  return nom ? ` par ${nom}` : ''
}

const isEditing = ref(false)
const saving = ref(false)
const catalogPrestations = ref<any[]>([])
const selectedCatalogPrestation = ref<any>(null)

// Editable fields
const editedDescription = ref('')
const editedUrgence = ref('normal')
const editedPrestations = ref<any[]>([])

const canEdit = computed(() => {
  return demande.value && ['en_attente', 'en_attente_validation'].includes(demande.value.statut)
})

const canDecideParTelephone = computed(() => {
  return demande.value && !['accepte', 'refuse'].includes(demande.value.statut || '')
})

const isSignatureEnAttente = computed(() => {
  return demande.value?.statut === 'accepte'
    && demande.value?.decision_canal === 'staff_telephone'
    && !demande.value?.signed_at
})

const editedPrixTotal = computed(() => {
  return editedPrestations.value.reduce((sum, p) => {
    const price = parseFloat(String(p.prix_ttc ?? 0)) || 0
    return sum + price
  }, 0).toFixed(2)
})

const editedTempsTotal = computed(() => {
  return editedPrestations.value.reduce((sum, p) => sum + (parseInt(String(p.temps_minutes ?? 0)) || 0), 0)
})

const availableCatalogPrestations = computed(() => {
  const existingIds = new Set(editedPrestations.value.map(p => p.prestation_id))
  return catalogPrestations.value.filter(cp => !existingIds.has(cp.id))
})

function formatEuro(value?: string | number) {
  if (value === undefined || value === null) return '—'
  const n = typeof value === 'string' ? parseFloat(value) : value
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(n)
}

function formatMinutes(value?: number) {
  if (!value) return '—'
  const h = Math.floor(value / 60)
  const m = value % 60
  if (h > 0 && m > 0) return `${h}h ${m}min`
  if (h > 0) return `${h}h`
  return `${m}min`
}

function labelStatut(statut?: string) {
  const labels: Record<string, string> = {
    en_attente: 'En attente',
    en_attente_validation: 'À envoyer',
    en_attente_decision_client: 'En attente client',
    accepte: 'Acceptée',
    refuse: 'Refusée',
  }
  return labels[statut || ''] || statut || '—'
}

function statutStyle(statut?: string) {
  const styles: Record<string, string> = {
    en_attente: 'background:var(--warning-soft);color:var(--warning-content);',
    en_attente_validation: 'background:var(--warning-soft);color:var(--warning-content);',
    en_attente_decision_client: 'background:var(--info-soft);color:var(--info-content);',
    accepte: 'background:var(--success-soft);color:var(--success-content);',
    refuse: 'background:var(--error-soft);color:var(--error-content);',
  }
  return styles[statut || ''] || 'background:var(--overlay-hover);color:var(--content-3);'
}

async function loadCatalog() {
  if (!demande.value) return
  const params = new URLSearchParams()
  if (demande.value.vehicule_type_moto) params.set('type_moto', String(demande.value.vehicule_type_moto))
  if (demande.value.vehicule_cylindree) params.set('cylindree', String(demande.value.vehicule_cylindree))
  if (demande.value.vehicule_categorie_id) params.set('categorie_id', String(demande.value.vehicule_categorie_id))

  try {
    const data = await api.get(`/rdv/prestations-catalogue?${params.toString()}`)
    catalogPrestations.value = data || []
  } catch (e) {
    console.warn('[DemandeModal] Failed to load catalog', e)
    catalogPrestations.value = []
  }
}

function startEditing() {
  if (!canEdit.value) return
  editedDescription.value = demande.value?.description || ''
  editedUrgence.value = demande.value?.urgence || 'normal'
  editedPrestations.value = JSON.parse(JSON.stringify(demande.value?.prestations || []))
  isEditing.value = true
  loadCatalog()
}

function cancelEditing() {
  isEditing.value = false
  editedPrestations.value = []
  catalogPrestations.value = []
  selectedCatalogPrestation.value = null
}

function addPrestation() {
  const cp = selectedCatalogPrestation.value
  if (!cp) return
  editedPrestations.value.push({
    prestation_id: cp.id,
    designation: cp.nom,
    prix_ht: String(cp.prix_base_ht ?? 0),
    prix_ttc: String(cp.prix_base_ttc ?? 0),
    temps_minutes: cp.temps_estime_minutes ?? 0,
    from_catalog: true,
  })
  selectedCatalogPrestation.value = null
}

function removePrestation(index: number) {
  editedPrestations.value.splice(index, 1)
}

async function saveChanges() {
  if (!demande.value) return
  saving.value = true
  try {
    const payload = {
      prestations: editedPrestations.value.map(p => ({
        prestation_id: p.prestation_id,
        designation: p.designation,
        prix_ht: String(p.prix_ht),
        prix_ttc: String(p.prix_ttc),
        temps_minutes: parseInt(String(p.temps_minutes)) || 0,
        from_catalog: p.from_catalog ?? true,
      })),
      description: editedDescription.value,
      urgence: editedUrgence.value,
    }

    const updated = await api.patch(`/demandes-travaux-supp/${demande.value.id}`, payload)
    toast.add({ title: 'Demande mise à jour', color: 'success' })

    // Update local data
    updateData(updated)
    isEditing.value = false
  } catch (e: any) {
    toast.add({ title: e.data?.error || 'Erreur lors de la sauvegarde', color: 'error' })
  } finally {
    saving.value = false
  }
}
</script>
