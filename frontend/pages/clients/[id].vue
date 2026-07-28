<template>
  <div>
    <div class="page-header">
      <div style="display:flex;align-items:center;gap:12px;">
        <NuxtLink to="/clients" style="color:var(--content-3);text-decoration:none;font-size:18px;" aria-label="Retour aux clients"><AppIcon name="i-ri-arrow-left-line" /></NuxtLink>
        <div class="page-title">{{ client?.prenom }} {{ client?.nom }}</div>
      </div>
      <button v-if="client" class="topbar-new-btn" @click="navigateTo('/rdv/new')">+ Planifier un RDV</button>
    </div>

    <div v-if="loading" style="display:flex;justify-content:center;padding:48px;">
      <span style="color:var(--content-3);">Chargement...</span>
    </div>

    <div v-else-if="client" class="detail-layout">
      <div class="detail-main">
        <!-- Info -->
        <UCard>
          <template #header>
            <div style="display:flex;justify-content:space-between;align-items:center;">
              <span style="font-size:15px;font-weight:700;color:var(--content-1);">Coordonnées</span>
              <button class="btn btn-ghost" style="font-size:12px;" @click="showEditClient = true"><AppIcon name="i-ri-pencil-line" /> Modifier</button>
            </div>
          </template>
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;font-size:13px;">
            <div><span style="color:var(--content-3);">Téléphone :</span> <span style="color:var(--content-2);">{{ client.telephone }}</span></div>
            <div><span style="color:var(--content-3);">Email :</span> <span style="color:var(--content-2);">{{ client.email || '—' }}</span></div>
            <div style="grid-column:span 2;"><span style="color:var(--content-3);">Adresse :</span> <span style="color:var(--content-2);">{{ client.adresse || '—' }}</span></div>
          </div>
          <div v-if="client.notes" style="margin-top:12px;padding:10px;background:var(--accent-soft);border:1px solid var(--accent);border-radius:8px;font-size:13px;">
            <span style="color:var(--accent-content);font-weight:600;"><AppIcon name="i-ri-pushpin-line" /> Notes :</span>
            <p style="color:var(--content-2);margin-top:4px;white-space:pre-wrap;">{{ client.notes }}</p>
          </div>
        </UCard>

        <!-- Carnet Moto -->
        <UCard>
          <template #header>
            <span style="font-size:15px;font-weight:700;color:var(--content-1);"><AppIcon name="i-ri-motorbike-line" /> Carnet Moto</span>
          </template>
          <div v-if="client.vehicules?.length" style="display:flex;flex-direction:column;gap:16px;">
            <div v-for="v in client.vehicules" :key="v.id" style="border:1px solid var(--border-2);border-radius:12px;overflow:hidden;">
              <!-- Vehicle header -->
              <div @click="toggleVehicle(v.id)" style="display:flex;align-items:center;justify-content:space-between;padding:14px;cursor:pointer;background:var(--overlay-soft);transition:background 0.15s;" class="hover-row">
                <div style="display:flex;align-items:center;gap:12px;">
                  <div style="width:36px;height:36px;border-radius:10px;background:var(--accent-soft);display:flex;align-items:center;justify-content:center;font-size:16px;"><AppIcon name="i-ri-motorbike-line" /></div>
                  <div>
                    <div style="font-weight:600;color:var(--content-1);font-size:14px;">{{ v.marque }} {{ v.modele }}</div>
                    <div style="font-size:12px;color:var(--content-3);">{{ v.plaque }} · {{ v.annee }} · {{ v.cylindree ? v.cylindree + 'cc' : '' }}</div>
                  </div>
                </div>
                <div style="display:flex;align-items:center;gap:8px;">
                  <span style="font-size:11px;padding:3px 10px;border-radius:6px;background:var(--info-soft);color:var(--info-content);">{{ vehicleRdvCount(v.id) }} passage(s)</span>
                  <span style="font-size:14px;color:var(--content-3);transition:transform 0.2s;" :style="{ transform: expandedVehicles.includes(v.id) ? 'rotate(180deg)' : '' }"><AppIcon name="i-ri-arrow-down-s-fill" /></span>
                </div>
              </div>
              <!-- Expanded vehicle history -->
              <div v-if="expandedVehicles.includes(v.id)" style="padding:14px;border-top:1px solid var(--border-2);">
                <div v-if="vehicleRdvs(v.id).length" style="display:flex;flex-direction:column;gap:8px;">
                  <div v-for="rdv in vehicleRdvs(v.id).slice(0, showAllHistory[v.id] ? undefined : 5)" :key="rdv.id" style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;background:var(--overlay-soft);border-radius:8px;font-size:13px;">
                    <div>
                      <span style="color:var(--content-3);font-family:monospace;">{{ formatDate(rdv.date_rdv) }}</span>
                      <span style="margin-left:8px;color:var(--content-1);">{{ rdv.type_intervention }}</span>
                      <span v-if="rdv.mecanicien_nom" style="margin-left:8px;color:var(--content-3);">· {{ rdv.mecanicien_nom }}</span>
                    </div>
                    <div style="display:flex;align-items:center;gap:8px;">
                      <StatusBadge :status="rdv.status" />
                      <button style="color:var(--accent-content);font-size:11px;font-weight:600;background:none;border:none;cursor:pointer;" @click="openRdvDetail(rdv)">Voir →</button>
                    </div>
                  </div>
                  <button v-if="vehicleRdvs(v.id).length > 5 && !showAllHistory[v.id]" class="btn btn-ghost" style="font-size:12px;align-self:center;" @click="showAllHistory[v.id] = true">
                    +{{ vehicleRdvs(v.id).length - 5 }} passage(s) supplémentaire(s) <AppIcon name="i-ri-arrow-down-s-fill" />
                  </button>
                </div>
                <p v-else style="color:var(--content-3);font-size:13px;">Aucun passage enregistré</p>
                <!-- Vehicle actions -->
                <div style="display:flex;gap:8px;margin-top:12px;padding-top:10px;border-top:1px solid var(--border-2);">
                  <button class="btn btn-ghost" style="font-size:12px;" @click="navigateTo('/rdv/new')"><AppIcon name="i-ri-calendar-line" /> Planifier un RDV</button>
                </div>
              </div>
            </div>
          </div>
          <p v-else style="color:var(--content-3);font-size:13px;">Aucun véhicule enregistré</p>
        </UCard>

        <!-- RDV History -->
        <UCard>
          <template #header><span style="font-size:15px;font-weight:700;color:var(--content-1);"><AppIcon name="i-ri-calendar-line" /> Historique complet</span></template>
          <UTable :data="clientRdvs" :columns="rdvColumns">
            <template #date_rdv-cell="{ row }">
              {{ formatDate(row.original.date_rdv) }}
            </template>
            <template #status-cell="{ row }">
              <StatusBadge :status="row.original.status" />
            </template>
            <template #actions-cell="{ row }">
              <button style="color:var(--accent-content);font-size:12px;font-weight:600;background:none;border:none;cursor:pointer;" @click="openRdvDetail(row.original)">Voir →</button>
            </template>
          </UTable>
        </UCard>
      </div>

      <!-- Sidebar stats -->
      <div class="detail-side">
        <UCard>
          <template #header><span style="font-size:15px;font-weight:700;color:var(--content-1);">Statistiques</span></template>
          <div style="display:flex;flex-direction:column;gap:14px;font-size:13px;">
            <div style="display:flex;justify-content:space-between;"><span style="color:var(--content-3);"><AppIcon name="i-ri-calendar-line" /> Visites</span><span style="font-weight:700;font-size:18px;color:var(--content-1);">{{ clientRdvs.length }}</span></div>
            <div style="display:flex;justify-content:space-between;"><span style="color:var(--content-3);"><AppIcon name="i-ri-motorbike-line" /> Motos</span><span style="font-weight:700;font-size:18px;color:var(--content-1);">{{ client.vehicules?.length || 0 }}</span></div>
            <div style="display:flex;justify-content:space-between;"><span style="color:var(--content-3);"><AppIcon name="i-ri-money-euro-box-line" /> CA Total</span><span style="font-weight:700;font-size:18px;color:var(--accent-content);">{{ formatCurrency(caTotal) }}</span></div>
            <div style="display:flex;justify-content:space-between;"><span style="color:var(--content-3);"><AppIcon name="i-ri-calendar-check-line" /> Client depuis</span><span style="font-weight:600;color:var(--content-1);">{{ formatDate(client.created_at || client.createdAt) }}</span></div>
          </div>
        </UCard>

        <!-- Yellow banner like legacy -->
        <div class="detail-banner">
          <AppIcon name="i-ri-clipboard-line" /> Le planning pilote les RDV.<br>Cette fiche conserve la mémoire de l'atelier pour ce client.
        </div>

        <!-- RGPD Actions -->
        <UCard v-if="!client.isAnonymized">
          <template #header><span style="font-size:15px;font-weight:700;color:var(--content-1);"><AppIcon name="i-ri-lock-line" /> RGPD</span></template>
          <div style="display:flex;flex-direction:column;gap:8px;">
            <button class="btn btn-ghost" style="font-size:12px;width:100%;" @click="exportClient" :disabled="exporting">
              <AppIcon name="i-ri-inbox-line" /> {{ exporting ? 'Export...' : 'Exporter les données (portabilité)' }}
            </button>
            <button class="btn" style="font-size:12px;width:100%;background:var(--error-soft);color:var(--error-content);border:1px solid var(--error);" @click="confirmAnonymize">
              <AppIcon name="i-ri-delete-bin-line" /> Anonymiser ce client
            </button>
          </div>
        </UCard>
        <UCard v-else>
          <div style="display:flex;align-items:center;gap:8px;padding:8px;background:var(--error-soft);border-radius:8px;">
            <span style="font-size:16px;"><AppIcon name="i-ri-error-warning-line" /></span>
            <span style="font-size:13px;color:var(--error-content);">Client anonymisé (RGPD)</span>
          </div>
        </UCard>
      </div>
    </div>

    <!-- Edit Client Modal -->
    <AppModal v-model:open="showEditClient" size="lg">
      <template #content>
        <UCard>
          <template #header>
            <div style="display:flex;justify-content:space-between;align-items:center;">
              <span style="font-weight:600;">Modifier le client</span>
              <button @click="showEditClient = false" style="background:none;border:none;color:var(--content-3);font-size:18px;cursor:pointer;" aria-label="Fermer"><AppIcon name="i-ri-close-line" /></button>
            </div>
          </template>
          <form @submit.prevent="saveClient" style="display:flex;flex-direction:column;gap:12px;">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;">
              <div class="form-group"><label class="form-label">Prénom</label><input v-model="editForm.prenom" class="form-input" /></div>
              <div class="form-group"><label class="form-label">Nom</label><input v-model="editForm.nom" class="form-input" /></div>
              <div class="form-group"><label class="form-label">Téléphone</label><input v-model="editForm.telephone" class="form-input" /></div>
              <div class="form-group"><label class="form-label">Email</label><input v-model="editForm.email" class="form-input" type="email" /></div>
            </div>
            <div class="form-group"><label class="form-label">Adresse</label><input v-model="editForm.adresse" class="form-input" /></div>
            <div class="form-group"><label class="form-label">Notes</label><textarea v-model="editForm.notes" class="form-input" rows="3" placeholder="Notes internes…" /></div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:12px;">
              <button type="button" class="btn btn-ghost" @click="showEditClient = false">Annuler</button>
              <button type="submit" class="btn btn-primary" :disabled="savingClient">{{ savingClient ? 'Enregistrement…' : 'Enregistrer' }}</button>
            </div>
          </form>
        </UCard>
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
const client = ref<any>(null)
const clientRdvs = ref<any[]>([])
const expandedVehicles = ref<number[]>([])
const showAllHistory = reactive<Record<number, boolean>>({})
const showEditClient = ref(false)
const savingClient = ref(false)
const exporting = ref(false)
const editForm = reactive({ prenom: '', nom: '', telephone: '', email: '', adresse: '', notes: '' })

const caTotal = computed(() => {
  return clientRdvs.value.reduce((sum, r) => sum + (r.montant_total || r.total_ttc || 0), 0)
})

const rdvColumns = [
  { key: 'date_rdv', label: 'Date' },
  { key: 'type_intervention', label: 'Type' },
  { key: 'vehicule_info', label: 'Véhicule' },
  { key: 'mecanicien_nom', label: 'Mécanicien' },
  { key: 'status', label: 'Statut' },
  { key: 'actions', label: '' },
]

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

function toggleVehicle(id: number) {
  const idx = expandedVehicles.value.indexOf(id)
  if (idx >= 0) expandedVehicles.value.splice(idx, 1)
  else expandedVehicles.value.push(id)
}

function vehicleRdvCount(vehicleId: number) {
  return clientRdvs.value.filter(r => r.vehicule_id === vehicleId).length
}

function vehicleRdvs(vehicleId: number) {
  return clientRdvs.value.filter(r => r.vehicule_id === vehicleId)
}

async function saveClient() {
  savingClient.value = true
  try {
    await api.put(`/clients/${route.params.id}`, editForm)
    client.value = { ...client.value, ...editForm }
    showEditClient.value = false
    toast.add({ title: 'Client modifié', color: 'success' })
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e?.message, color: 'error' })
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
    toast.add({ title: 'Erreur export', description: e?.message, color: 'error' })
  } finally {
    exporting.value = false
  }
}

async function confirmAnonymize() {
  if (!confirm('ATTENTION : Cette action est IRRÉVERSIBLE.\n\nToutes les données personnelles de ce client seront effacées.\nLes factures et ordres conserveront un snapshot conforme aux obligations légales.\n\nConfirmez-vous l\'anonymisation ?')) {
    return
  }
  try {
    await api.post(`/clients/${route.params.id}/anonymize`)
    toast.add({ title: 'Client anonymisé', description: 'Les données personnelles ont été effacées.', color: 'success' })
    const c = await api.get(`/clients/${route.params.id}`)
    client.value = c
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e?.message, color: 'error' })
  }
}

onMounted(async () => {
  try {
    const [c, rdvData] = await Promise.all([
      api.get(`/clients/${route.params.id}`),
      api.getAll(`/rendez-vous?client.id=${route.params.id}&order[dateRdv]=desc`),
    ])
    client.value = c
    Object.assign(editForm, { prenom: c.prenom, nom: c.nom, telephone: c.telephone, email: c.email || '', adresse: c.adresse || '', notes: c.notes || '' })
    const raw = rdvData?.['hydra:member'] ?? rdvData?.member ?? (Array.isArray(rdvData) ? rdvData : [])
    clientRdvs.value = raw.map(normalizeRdv)
  } finally {
    loading.value = false
  }
})
</script>
