<template>
  <div>
    <div class="page-header">
      <div class="page-title">Cockpit SRC</div>
    </div>

    <UCard style="margin-bottom:16px;">
      <div style="display:flex;gap:8px;align-items:center;">
        <input
          v-model="query"
          class="form-input"
          placeholder="Nom, téléphone, email ou plaque…"
          style="flex:1;font-size:15px;"
          autofocus
          @input="onSearchInput"
        />
      </div>
      <div v-if="searching" style="margin-top:10px;color:var(--content-3);font-size:13px;">Recherche…</div>
      <div v-else-if="query.trim().length >= 2 && results.length === 0" style="margin-top:10px;color:var(--content-3);font-size:13px;">Aucun résultat.</div>
    </UCard>

    <UCard v-if="results.length && !selectedClientId">
      <div v-for="c in results" :key="c.id" class="hover-row" style="display:flex;align-items:center;gap:16px;padding:12px 8px;border-bottom:1px solid var(--border-2);cursor:pointer;" @click="openDossier(c.id)">
        <div style="flex:1;">
          <div style="font-weight:600;font-size:14px;">{{ c.prenom }} {{ c.nom }}</div>
          <div style="font-size:12px;color:var(--content-3);">{{ c.telephone }} <span v-if="c.email">· {{ c.email }}</span></div>
        </div>
        <div style="font-size:12px;color:var(--content-3);text-align:right;">
          <div><AppIcon name="i-ri-store-2-line" /> {{ c.atelier_nom || '—' }}</div>
          <div v-if="c.dernier_rdv"><StatusBadge :status="statusMap[c.dernier_rdv.statut] || 'en_attente'" /> {{ formatDate(c.dernier_rdv.date_rdv) }}</div>
        </div>
        <div v-if="c.vehicules?.length" style="font-size:12px;color:var(--content-3);min-width:140px;text-align:right;">
          {{ c.vehicules.map(v => v.plaque).join(', ') }}
        </div>
      </div>
    </UCard>

    <!-- Dossier client -->
    <div v-if="selectedClientId">
      <button class="btn btn-ghost" style="margin-bottom:12px;font-size:13px;" @click="closeDossier"><AppIcon name="i-ri-arrow-left-line" /> Retour à la recherche</button>

      <div v-if="loadingDossier" style="padding:40px;text-align:center;color:var(--content-3);">Chargement…</div>
      <div v-else-if="!dossier" style="padding:40px;text-align:center;color:var(--content-3);">Dossier introuvable ou hors de votre périmètre.</div>
      <div v-else>
        <UCard style="margin-bottom:16px;">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;">
            <div>
              <div style="font-size:18px;font-weight:700;">{{ dossier.prenom }} {{ dossier.nom }}</div>
              <div style="font-size:13px;color:var(--content-3);margin-top:4px;">
                <AppIcon name="i-ri-phone-line" /> {{ dossier.telephone }}
                <span v-if="dossier.email"> · <AppIcon name="i-ri-mail-line" /> {{ dossier.email }}</span>
              </div>
            </div>
          </div>
          <div v-if="dossier.vehicules?.length" style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">
            <span v-for="v in dossier.vehicules" :key="v.id" style="font-size:12px;padding:4px 10px;border-radius:6px;background:var(--overlay-hover);color:var(--content-1);">
              <AppIcon name="i-ri-motorbike-line" /> {{ v.marque }} {{ v.modele }} — {{ v.plaque }}
            </span>
          </div>
        </UCard>

        <UCard>
          <template #header><span style="font-size:13px;font-weight:600;color:var(--content-3);">Rendez-vous</span></template>
          <div v-if="!dossier.rdvs.length" style="padding:16px;color:var(--content-3);font-size:13px;">Aucun rendez-vous.</div>
          <div v-for="r in dossier.rdvs" :key="r.id" class="hover-row" style="display:flex;align-items:center;gap:16px;padding:10px 8px;border-bottom:1px solid var(--border-2);cursor:pointer;" @click="openRdv(r.id)">
            <div style="width:110px;font-size:13px;">{{ formatDate(r.date_rdv) }} {{ r.heure_rdv }}</div>
            <div style="flex:1;font-size:13px;">{{ r.type_intervention }}<span v-if="r.vehicule"> — {{ r.vehicule.marque }} {{ r.vehicule.modele }}</span></div>
            <StatusBadge :status="statusMap[r.statut] || 'en_attente'" />
            <AppIcon name="i-ri-arrow-right-s-line" />
          </div>
        </UCard>
      </div>
    </div>

    <!-- Détail RDV (modale simple) -->
    <div v-if="selectedRdvId" class="app-modal-overlay" @click.self="closeRdv">
      <div class="app-modal-card app-modal-lg">
        <div class="app-modal-header">
          <span style="font-weight:600;font-size:16px;">Détail du rendez-vous</span>
          <button @click="closeRdv" style="background:none;border:none;color:var(--content-3);font-size:18px;cursor:pointer;" aria-label="Fermer"><AppIcon name="i-ri-close-line" /></button>
        </div>
        <div class="app-modal-body">
          <div v-if="loadingRdv" style="padding:20px;text-align:center;color:var(--content-3);">Chargement…</div>
          <div v-else-if="!rdvDetail" style="padding:20px;text-align:center;color:var(--content-3);">Introuvable.</div>
          <div v-else style="font-size:13px;line-height:1.7;">
            <div><strong>{{ rdvDetail.client?.prenom }} {{ rdvDetail.client?.nom }}</strong> — {{ rdvDetail.vehicule?.marque }} {{ rdvDetail.vehicule?.modele }} ({{ rdvDetail.vehicule?.plaque }})</div>
            <div style="margin:8px 0;"><StatusBadge :status="statusMap[rdvDetail.statut] || 'en_attente'" /> — {{ rdvDetail.type_intervention }}</div>

            <div style="margin-top:16px;font-weight:600;color:var(--content-3);">Historique</div>
            <div v-for="(t, i) in rdvDetail.timeline" :key="i" style="padding:4px 0;border-bottom:1px solid var(--border-2);">
              {{ t.transition || t.statut }} — {{ formatDateTime(t.date) }}
            </div>

            <div v-if="rdvDetail.etat_des_lieux" style="margin-top:16px;">
              <span style="font-weight:600;color:var(--content-3);">État des lieux :</span> signé le {{ formatDateTime(rdvDetail.etat_des_lieux.signed_at) }}
            </div>

            <div v-if="rdvDetail.ordres_reparation?.length" style="margin-top:16px;">
              <div style="font-weight:600;color:var(--content-3);">Ordres de réparation</div>
              <div v-for="o in rdvDetail.ordres_reparation" :key="o.id">{{ o.numero_or }} — {{ o.statut }}</div>
            </div>

            <div v-if="rdvDetail.notifications?.length" style="margin-top:16px;">
              <div style="font-weight:600;color:var(--content-3);">Notifications envoyées</div>
              <div v-for="(n, i) in rdvDetail.notifications" :key="i" style="font-size:12px;color:var(--content-2);">
                {{ n.channel === 'sms' ? 'SMS' : 'Email' }} → {{ n.to }} ({{ n.status }}) — {{ formatDateTime(n.sent_at) }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
const api = useApi()

const query = ref('')
const results = ref<any[]>([])
const searching = ref(false)

const selectedClientId = ref<number | null>(null)
const dossier = ref<any>(null)
const loadingDossier = ref(false)

const selectedRdvId = ref<number | null>(null)
const rdvDetail = ref<any>(null)
const loadingRdv = ref(false)

const statusMap: Record<string, string> = {
  en_attente: 'en_attente', reserve: 'en_attente', confirme: 'en_cours', reception: 'en_cours',
  en_cours: 'en_cours', gardiennage: 'en_cours', signe: 'en_cours', restitue: 'termine',
  termine: 'termine', facture: 'termine', paye: 'termine', annule: 'annule', no_show: 'annule',
}

function formatDate(d: string) {
  if (!d) return ''
  try { return new Date(d).toLocaleDateString('fr-FR') } catch { return d }
}
function formatDateTime(d: string) {
  if (!d) return ''
  try { return new Date(d).toLocaleString('fr-FR') } catch { return d }
}

let searchTimer: any = null
function onSearchInput() {
  clearTimeout(searchTimer)
  const q = query.value.trim()
  if (q.length < 2) { results.value = []; return }
  searching.value = true
  searchTimer = setTimeout(async () => {
    try {
      const data = await api.get(`/cockpit/recherche?q=${encodeURIComponent(q)}`)
      results.value = data?.clients ?? []
    } catch {
      results.value = []
    } finally {
      searching.value = false
    }
  }, 300)
}

async function openDossier(id: number) {
  selectedClientId.value = id
  loadingDossier.value = true
  dossier.value = null
  try {
    dossier.value = await api.get(`/cockpit/clients/${id}`)
  } catch {
    dossier.value = null
  } finally {
    loadingDossier.value = false
  }
}

function closeDossier() {
  selectedClientId.value = null
  dossier.value = null
}

async function openRdv(id: number) {
  selectedRdvId.value = id
  loadingRdv.value = true
  rdvDetail.value = null
  try {
    rdvDetail.value = await api.get(`/cockpit/rdv/${id}`)
  } catch {
    rdvDetail.value = null
  } finally {
    loadingRdv.value = false
  }
}

function closeRdv() {
  selectedRdvId.value = null
  rdvDetail.value = null
}
</script>
