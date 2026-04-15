<template>
  <div>
    <div class="page-header">
      <div style="display:flex;align-items:center;gap:12px;">
        <NuxtLink to="/clients" style="color:#6B7280;text-decoration:none;font-size:18px;">◀</NuxtLink>
        <div class="page-title">{{ client?.prenom }} {{ client?.nom }}</div>
      </div>
      <button v-if="client" class="topbar-new-btn" @click="navigateTo('/rdv/new')">+ Planifier un RDV</button>
    </div>

    <div v-if="loading" style="display:flex;justify-content:center;padding:48px;">
      <span style="color:#6B7280;">Chargement...</span>
    </div>

    <div v-else-if="client" class="detail-layout">
      <div class="detail-main">
        <!-- Info -->
        <UCard>
          <template #header>
            <div style="display:flex;justify-content:space-between;align-items:center;">
              <span style="font-size:15px;font-weight:700;color:#E8E9ED;">Coordonnées</span>
              <button class="btn btn-ghost" style="font-size:12px;" @click="showEditClient = true">✏ Modifier</button>
            </div>
          </template>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:13px;">
            <div><span style="color:#6B7280;">Téléphone :</span> <span style="color:#D1D5DB;">{{ client.telephone }}</span></div>
            <div><span style="color:#6B7280;">Email :</span> <span style="color:#D1D5DB;">{{ client.email || '—' }}</span></div>
            <div style="grid-column:span 2;"><span style="color:#6B7280;">Adresse :</span> <span style="color:#D1D5DB;">{{ client.adresse || '—' }}</span></div>
          </div>
          <div v-if="client.notes" style="margin-top:12px;padding:10px;background:rgba(255,210,0,0.05);border:1px solid rgba(255,210,0,0.15);border-radius:8px;font-size:13px;">
            <span style="color:#FFD200;font-weight:600;">📌 Notes :</span>
            <p style="color:#D1D5DB;margin-top:4px;white-space:pre-wrap;">{{ client.notes }}</p>
          </div>
        </UCard>

        <!-- Carnet Moto -->
        <UCard>
          <template #header>
            <span style="font-size:15px;font-weight:700;color:#E8E9ED;">🏍 Carnet Moto</span>
          </template>
          <div v-if="client.vehicules?.length" style="display:flex;flex-direction:column;gap:16px;">
            <div v-for="v in client.vehicules" :key="v.id" style="border:1px solid rgba(255,255,255,0.06);border-radius:12px;overflow:hidden;">
              <!-- Vehicle header -->
              <div @click="toggleVehicle(v.id)" style="display:flex;align-items:center;justify-content:space-between;padding:14px;cursor:pointer;background:rgba(255,255,255,0.02);transition:background 0.15s;" class="hover-row">
                <div style="display:flex;align-items:center;gap:12px;">
                  <div style="width:36px;height:36px;border-radius:10px;background:rgba(255,210,0,0.08);display:flex;align-items:center;justify-content:center;font-size:16px;">🏍</div>
                  <div>
                    <div style="font-weight:600;color:#E8E9ED;font-size:14px;">{{ v.marque }} {{ v.modele }}</div>
                    <div style="font-size:12px;color:#6B7280;">{{ v.plaque }} · {{ v.annee }} · {{ v.cylindree ? v.cylindree + 'cc' : '' }}</div>
                  </div>
                </div>
                <div style="display:flex;align-items:center;gap:8px;">
                  <span style="font-size:11px;padding:3px 10px;border-radius:6px;background:rgba(59,130,246,0.1);color:#93C5FD;">{{ vehicleRdvCount(v.id) }} passage(s)</span>
                  <span style="font-size:14px;color:#6B7280;transition:transform 0.2s;" :style="{ transform: expandedVehicles.includes(v.id) ? 'rotate(180deg)' : '' }">▼</span>
                </div>
              </div>
              <!-- Expanded vehicle history -->
              <div v-if="expandedVehicles.includes(v.id)" style="padding:14px;border-top:1px solid rgba(255,255,255,0.04);">
                <div v-if="vehicleRdvs(v.id).length" style="display:flex;flex-direction:column;gap:8px;">
                  <div v-for="rdv in vehicleRdvs(v.id).slice(0, showAllHistory[v.id] ? undefined : 5)" :key="rdv.id" style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;background:rgba(255,255,255,0.02);border-radius:8px;font-size:13px;">
                    <div>
                      <span style="color:#6B7280;font-family:monospace;">{{ formatDate(rdv.date_rdv) }}</span>
                      <span style="margin-left:8px;color:#E8E9ED;">{{ rdv.type_intervention }}</span>
                      <span v-if="rdv.mecanicien_nom" style="margin-left:8px;color:#9CA3AF;">· {{ rdv.mecanicien_nom }}</span>
                    </div>
                    <div style="display:flex;align-items:center;gap:8px;">
                      <StatusBadge :status="rdv.status" />
                      <NuxtLink :to="`/rdv/${rdv.id}`" style="color:#FFD200;font-size:11px;font-weight:600;text-decoration:none;">Voir →</NuxtLink>
                    </div>
                  </div>
                  <button v-if="vehicleRdvs(v.id).length > 5 && !showAllHistory[v.id]" class="btn btn-ghost" style="font-size:12px;align-self:center;" @click="showAllHistory[v.id] = true">
                    +{{ vehicleRdvs(v.id).length - 5 }} passage(s) supplémentaire(s) ▼
                  </button>
                </div>
                <p v-else style="color:#6B7280;font-size:13px;">Aucun passage enregistré</p>
                <!-- Vehicle actions -->
                <div style="display:flex;gap:8px;margin-top:12px;padding-top:10px;border-top:1px solid rgba(255,255,255,0.04);">
                  <button class="btn btn-ghost" style="font-size:12px;" @click="navigateTo('/rdv/new')">📅 Planifier un RDV</button>
                </div>
              </div>
            </div>
          </div>
          <p v-else style="color:#6B7280;font-size:13px;">Aucun véhicule enregistré</p>
        </UCard>

        <!-- RDV History -->
        <UCard>
          <template #header><span style="font-size:15px;font-weight:700;color:#E8E9ED;">📅 Historique complet</span></template>
          <UTable :data="clientRdvs" :columns="rdvColumns">
            <template #date_rdv-cell="{ row }">
              {{ formatDate(row.original.date_rdv) }}
            </template>
            <template #status-cell="{ row }">
              <StatusBadge :status="row.original.status" />
            </template>
            <template #actions-cell="{ row }">
              <NuxtLink :to="`/rdv/${row.original.id}`" style="color:#FFD200;font-size:12px;font-weight:600;text-decoration:none;">Voir →</NuxtLink>
            </template>
          </UTable>
        </UCard>
      </div>

      <!-- Sidebar stats -->
      <div class="detail-side">
        <UCard>
          <template #header><span style="font-size:15px;font-weight:700;color:#E8E9ED;">Statistiques</span></template>
          <div style="display:flex;flex-direction:column;gap:14px;font-size:13px;">
            <div style="display:flex;justify-content:space-between;"><span style="color:#6B7280;">📅 Visites</span><span style="font-weight:700;font-size:18px;color:#E8E9ED;">{{ clientRdvs.length }}</span></div>
            <div style="display:flex;justify-content:space-between;"><span style="color:#6B7280;">🏍 Motos</span><span style="font-weight:700;font-size:18px;color:#E8E9ED;">{{ client.vehicules?.length || 0 }}</span></div>
            <div style="display:flex;justify-content:space-between;"><span style="color:#6B7280;">💰 CA Total</span><span style="font-weight:700;font-size:18px;color:#FFD200;">{{ formatCurrency(caTotal) }}</span></div>
            <div style="display:flex;justify-content:space-between;"><span style="color:#6B7280;">📆 Client depuis</span><span style="font-weight:600;color:#E8E9ED;">{{ formatDate(client.created_at || client.createdAt) }}</span></div>
          </div>
        </UCard>

        <!-- Yellow banner like legacy -->
        <div class="detail-banner">
          📋 Le planning pilote les RDV.<br>Cette fiche conserve la mémoire de l'atelier pour ce client.
        </div>
      </div>
    </div>

    <!-- Edit Client Modal -->
    <AppModal v-model:open="showEditClient" size="lg">
      <template #content>
        <UCard>
          <template #header>
            <div style="display:flex;justify-content:space-between;align-items:center;">
              <span style="font-weight:600;">Modifier le client</span>
              <button @click="showEditClient = false" style="background:none;border:none;color:#9CA3AF;font-size:18px;cursor:pointer;">✕</button>
            </div>
          </template>
          <form @submit.prevent="saveClient" style="display:flex;flex-direction:column;gap:12px;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
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
const loading = ref(true)
const client = ref<any>(null)
const clientRdvs = ref<any[]>([])
const expandedVehicles = ref<number[]>([])
const showAllHistory = reactive<Record<number, boolean>>({})
const showEditClient = ref(false)
const savingClient = ref(false)
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

onMounted(async () => {
  try {
    const [c, rdvData] = await Promise.all([
      api.get(`/clients/${route.params.id}`),
      api.get(`/rendez-vous?client.id=${route.params.id}&order[dateRdv]=desc&itemsPerPage=200`),
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
