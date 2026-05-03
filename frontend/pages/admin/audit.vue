<template>
  <div>
    <AppPageHeader title="Journal d'audit" back-to="/admin" />

    <!-- Filtres serveur -->
    <UCard class="mb-4">
      <div class="filter-grid">
        <div class="form-group">
          <label class="form-label">Date début</label>
          <input v-model="filters.dateFrom" type="date" class="form-input" @change="resetAndFetch" />
        </div>
        <div class="form-group">
          <label class="form-label">Date fin</label>
          <input v-model="filters.dateTo" type="date" class="form-input" @change="resetAndFetch" />
        </div>
        <div class="form-group">
          <label class="form-label">Action</label>
          <select v-model="filters.action" class="form-input" @change="resetAndFetch">
            <option value="">Toutes</option>
            <optgroup label="Général">
              <option value="create">Création</option>
              <option value="update">Modification</option>
              <option value="delete">Suppression</option>
              <option value="workflow_transition">Transition workflow</option>
            </optgroup>
            <optgroup label="Atelier / RDV">
              <option value="photo_upload">Upload photo</option>
              <option value="mecanicien_create_essai">Essai routier créé</option>
              <option value="mecanicien_save_rapport">Rapport sauvegardé</option>
              <option value="sign_rapport_mecanicien">Rapport signé</option>
              <option value="payment">Paiement</option>
              <option value="credit_note">Avoir</option>
              <option value="refund">Remboursement</option>
            </optgroup>
            <optgroup label="VO">
              <option value="create_vo_purchase">VO — Rachat créé</option>
              <option value="confirm_vo_purchase">VO — Rachat confirmé</option>
              <option value="update_vo_purchase">VO — Rachat modifié</option>
              <option value="sell_vo_purchase">VO — Vendu</option>
              <option value="prepare_siv_vo_purchase">VO — DA SIV préparée</option>
              <option value="siv_transition">VO — Transition SIV</option>
              <option value="create_vo_depot">VO — Dépôt créé</option>
              <option value="finalize_vo_depot">VO — Dépôt finalisé</option>
              <option value="restituer_vo_depot">VO — Dépôt restitué</option>
              <option value="create_vo_remise_en_etat">VO — Remise en état créée</option>
              <option value="update_vo_remise_en_etat">VO — Remise en état modifiée</option>
              <option value="add_vo_remise_en_etat_ligne">VO — Ligne FRE ajoutée</option>
              <option value="delete_vo_remise_en_etat_ligne">VO — Ligne FRE supprimée</option>
              <option value="sign_vo_remise_en_etat_document">VO — Document signé</option>
              <option value="lp_create_achat">LP — Entrée achat</option>
              <option value="lp_create_depot_vente">LP — Entrée dépôt-vente</option>
              <option value="lp_record_sale">LP — Sortie vente</option>
            </optgroup>
            <optgroup label="RGPD">
              <option value="rgpd_anonymize">RGPD — Anonymisation</option>
              <option value="rgpd_purge_identity">RGPD — Purge identité</option>
            </optgroup>
            <optgroup label="Admin">
              <option value="create_atelier">Atelier créé</option>
              <option value="email">Email envoyé</option>
            </optgroup>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Entité</label>
          <select v-model="filters.entityType" class="form-input" @change="resetAndFetch">
            <option value="">Toutes</option>
            <option value="rdv">RDV</option>
            <option value="OrdreReparation">Ordre de réparation</option>
            <option value="rapport_intervention">Rapport d'intervention</option>
            <option value="EssaiRoutier">Essai routier</option>
            <option value="PhotoIntervention">Photo intervention</option>
            <option value="facture">Facture</option>
            <option value="client">Client</option>
            <option value="VOPurchase">VO — Rachat</option>
            <option value="vo_purchase">VO — Rachat (legacy)</option>
            <option value="vo_depot">VO — Dépôt</option>
            <option value="vo_remise_en_etat">VO — Remise en état</option>
            <option value="VOLivrePolice">Livre de Police</option>
            <option value="VODocument">Document VO</option>
            <option value="atelier">Atelier</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Utilisateur</label>
          <input v-model="filters.username" class="form-input" placeholder="Nom d'utilisateur…" @keyup.enter="resetAndFetch" />
        </div>
        <div class="form-group">
          <label class="form-label">IP</label>
          <input v-model="filters.ip" class="form-input" placeholder="192.168…" @keyup.enter="resetAndFetch" />
        </div>
        <div class="filter-actions">
          <button class="btn btn-primary btn-sm flex-1" @click="resetAndFetch">Filtrer</button>
          <button class="btn btn-ghost btn-sm" title="Réinitialiser" @click="resetFilters">✕</button>
        </div>
      </div>
      <div v-if="totalItems > 0" class="results-count">
        {{ totalItems.toLocaleString('fr-FR') }} entrée{{ totalItems > 1 ? 's' : '' }} trouvée{{ totalItems > 1 ? 's' : '' }}
      </div>
    </UCard>

    <AppErrorState
      v-if="!loading && errorMessage && !logs.length"
      title="Journal d'audit indisponible"
      :description="errorMessage"
      @retry="fetchLogs"
    />

    <UCard v-else>
      <div v-if="loading" class="loading-center">Chargement…</div>
      <AppEmptyState
        v-else-if="!logs.length"
        icon="📜"
        title="Aucune entrée"
        description="Aucune action tracée pour les critères sélectionnés. Élargissez la période ou réinitialisez les filtres."
      />
      <div v-else>
        <!-- En-tête -->
        <div class="audit-header">
          <span>Date</span>
          <span>Action</span>
          <span>Utilisateur</span>
          <span>Entité</span>
          <span>Détails</span>
          <span>IP</span>
        </div>
        <!-- Lignes -->
        <template v-for="log in logs" :key="log.id">
          <div
            class="audit-row"
            :class="{ expanded: expandedId === log.id }"
            @click="toggleExpand(log.id)"
          >
            <span class="audit-date">{{ formatDate(log.createdAt) }}</span>
            <span>
              <AppStatusBadge :variant="actionVariant(log.action)" size="sm">
                {{ actionLabel(log.action) }}
              </AppStatusBadge>
            </span>
            <span class="audit-username" :title="log.username || ''">
              {{ log.username || '—' }}
            </span>
            <span class="audit-entity">
              <span v-if="log.entityType" class="audit-entity-type">{{ entityLabel(log.entityType) }}</span>
              <span v-if="log.entityId" class="audit-entity-id">#{{ log.entityId }}</span>
              <span v-if="!log.entityType && !log.entityId">—</span>
            </span>
            <span class="audit-details" :title="detailsPreview(log.details)">
              {{ detailsPreview(log.details) || '—' }}
            </span>
            <span class="audit-ip" :title="log.ipAddress || ''">
              {{ log.ipAddress || '—' }}
            </span>
          </div>
          <!-- Détails expandés -->
          <div v-if="expandedId === log.id && log.details" class="audit-expanded">
            <div class="audit-expanded-title">Détails complets</div>
            <pre class="audit-pre">{{ detailsFormatted(log.details) }}</pre>
          </div>
        </template>
      </div>
    </UCard>

    <!-- Pagination -->
    <div v-if="totalPages > 1" class="pagination">
      <button class="btn btn-ghost btn-sm" :disabled="page <= 1" @click="goToPage(page - 1)">← Préc.</button>
      <template v-for="p in paginationRange" :key="p">
        <span v-if="p === '...'" class="pagination-ellipsis">…</span>
        <button v-else class="btn pagination-btn" :class="page === p ? 'btn-primary' : 'btn-ghost'" @click="goToPage(Number(p))">{{ p }}</button>
      </template>
      <button class="btn btn-ghost btn-sm" :disabled="page >= totalPages" @click="goToPage(page + 1)">Suiv. →</button>
    </div>
  </div>
</template>

<script setup lang="ts">
const auth = useAuth()
const api = useApi()
const toast = useToast()
const loading = ref(true)
const logs = ref<any[]>([])
const errorMessage = ref('')
const page = ref(1)
const totalPages = ref(1)
const totalItems = ref(0)
const expandedId = ref<number | null>(null)
const ITEMS_PER_PAGE = 50

const filters = reactive({
  dateFrom: '',
  dateTo: '',
  action: '',
  entityType: '',
  username: '',
  ip: '',
})

function resetFilters() {
  filters.dateFrom = ''
  filters.dateTo = ''
  filters.action = ''
  filters.entityType = ''
  filters.username = ''
  filters.ip = ''
  resetAndFetch()
}

function resetAndFetch() {
  page.value = 1
  expandedId.value = null
  fetchLogs()
}

function goToPage(p: number) {
  page.value = p
  expandedId.value = null
  fetchLogs()
}

function toggleExpand(id: number) {
  expandedId.value = expandedId.value === id ? null : id
}

// Plage de pages pour la pagination (eviter trop de boutons)
const paginationRange = computed(() => {
  const total = totalPages.value
  const current = page.value
  if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1)
  const range: (number | string)[] = [1]
  if (current > 3) range.push('...')
  for (let i = Math.max(2, current - 1); i <= Math.min(total - 1, current + 1); i++) range.push(i)
  if (current < total - 2) range.push('...')
  range.push(total)
  return range
})

function formatDate(d: string) {
  if (!d) return ''
  try {
    return new Date(d).toLocaleString('fr-FR', {
      day: '2-digit', month: '2-digit', year: 'numeric',
      hour: '2-digit', minute: '2-digit', second: '2-digit',
    })
  } catch { return d }
}

const ACTION_LABELS: Record<string, string> = {
  create: 'Création',
  update: 'Modification',
  delete: 'Suppression',
  workflow_transition: 'Transition',
  photo_upload: 'Photo',
  mecanicien_create_essai: 'Essai routier',
  mecanicien_save_rapport: 'Rapport méca',
  sign_rapport_mecanicien: 'Signature méca',
  payment: 'Paiement',
  credit_note: 'Avoir',
  refund: 'Remboursement',
  email: 'Email',
  create_vo_purchase: 'VO rachat créé',
  confirm_vo_purchase: 'VO confirmé',
  update_vo_purchase: 'VO modifié',
  sell_vo_purchase: 'VO vendu',
  prepare_siv_vo_purchase: 'DA SIV',
  siv_transition: 'SIV transition',
  create_vo_depot: 'Dépôt créé',
  finalize_vo_depot: 'Dépôt finalisé',
  restituer_vo_depot: 'Dépôt restitué',
  create_vo_remise_en_etat: 'FRE créée',
  update_vo_remise_en_etat: 'FRE modifiée',
  add_vo_remise_en_etat_ligne: 'FRE ligne +',
  delete_vo_remise_en_etat_ligne: 'FRE ligne -',
  sign_vo_remise_en_etat_document: 'Doc signé',
  lp_create_achat: 'LP achat',
  lp_create_depot_vente: 'LP dépôt',
  lp_record_sale: 'LP vente',
  rgpd_anonymize: 'RGPD anon.',
  rgpd_purge_identity: 'RGPD purge',
  create_atelier: 'Atelier créé',
}

function actionLabel(a: string) {
  return ACTION_LABELS[a] || a
}

function actionVariant(a: string): 'success' | 'info' | 'error' | 'warning' | 'default' {
  if (a === 'create' || a === 'payment') return 'success'
  if (a?.startsWith('create_') || a?.startsWith('lp_create_')) return 'success'
  if (a === 'update' || a?.startsWith('update_')) return 'info'
  if (a === 'delete' || a === 'credit_note' || a === 'refund') return 'error'
  if (a?.startsWith('rgpd_')) return 'error'
  if (a === 'workflow_transition') return 'warning'
  if (a?.startsWith('siv_') || a === 'prepare_siv_vo_purchase') return 'warning'
  if (a?.startsWith('sign_')) return 'info'
  if (a === 'mecanicien_create_essai' || a === 'mecanicien_save_rapport') return 'info'
  return 'default'
}

const ENTITY_LABELS: Record<string, string> = {
  rdv: 'RDV',
  OrdreReparation: 'Ordre de réparation',
  rapport_intervention: 'Rapport intervention',
  EssaiRoutier: 'Essai routier',
  PhotoIntervention: 'Photo',
  facture: 'Facture',
  client: 'Client',
  VOPurchase: 'VO Rachat',
  vo_purchase: 'VO Rachat',
  vo_depot: 'VO Dépôt',
  vo_remise_en_etat: 'VO FRE',
  VOLivrePolice: 'Livre de Police',
  VODocument: 'Document VO',
  atelier: 'Atelier',
}

function entityLabel(t: string) {
  return ENTITY_LABELS[t] || t
}

function detailsPreview(d: string | null): string {
  if (!d) return ''
  try {
    const parsed = JSON.parse(d)
    // Extraire des champs informatifs selon la structure
    const keys = ['transition', 'from', 'to', 'amount', 'plaque', 'vin', 'reason', 'description', 'status', 'field']
    for (const k of keys) {
      if (parsed[k] !== undefined) return `${k}: ${String(parsed[k]).slice(0, 80)}`
    }
    // Fallback : premier champ non-nul
    for (const [k, v] of Object.entries(parsed)) {
      if (v !== null && v !== undefined && typeof v !== 'object') return `${k}: ${String(v).slice(0, 80)}`
    }
    return d.slice(0, 80)
  } catch {
    return d.slice(0, 80)
  }
}

function detailsFormatted(d: string | null): string {
  if (!d) return ''
  try {
    return JSON.stringify(JSON.parse(d), null, 2)
  } catch {
    return d
  }
}

async function fetchLogs() {
  loading.value = true
  errorMessage.value = ''
  try {
    const params = new URLSearchParams()
    params.set('page', String(page.value))
    params.set('itemsPerPage', String(ITEMS_PER_PAGE))
    if (filters.dateFrom) params.set('createdAt[after]', `${filters.dateFrom}T00:00:00`)
    if (filters.dateTo) params.set('createdAt[before]', `${filters.dateTo}T23:59:59`)
    if (filters.action) params.set('action', filters.action)
    if (filters.entityType) params.set('entityType', filters.entityType)
    if (filters.username) params.set('username', filters.username)
    if (filters.ip) params.set('ipAddress', filters.ip)
    params.set('order[createdAt]', 'desc')
    const data = await api.get(`/audit_logs?${params}`)
    const paginated = unwrapHydraPaginated(data)
    logs.value = paginated.items
    totalItems.value = paginated.totalItems
    totalPages.value = Math.max(1, Math.ceil(totalItems.value / ITEMS_PER_PAGE))
  } catch (e: unknown) {
    logs.value = []
    totalItems.value = 0
    errorMessage.value = (e instanceof Error ? e.message : 'Erreur inconnue') || 'Impossible de récupérer le journal d\'audit.'
    toast.add({ title: 'Erreur de chargement', description: errorMessage.value, color: 'error' })
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  if (!auth.canAccessAuditLogs()) {
    await navigateTo('/admin')
    return
  }
  await fetchLogs()
})
</script>

<style scoped>
.mb-4 { margin-bottom:16px; }
.filter-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:12px; align-items:flex-end; }
.filter-actions { display:flex; gap:8px; align-items:flex-end; }
.btn-sm { font-size:13px; }
.flex-1 { flex:1; }
.results-count { margin-top:10px; font-size:12px; color:#6B7280; }
.loading-center { padding:32px; text-align:center; color:var(--text-subtle); }
.audit-header { display:grid; grid-template-columns:155px 130px 130px 160px 1fr 36px; gap:10px; padding:8px 14px; border-bottom:1px solid rgba(255,255,255,0.08); font-size:11px; font-weight:600; color:#6B7280; text-transform:uppercase; letter-spacing:.05em; }
.audit-row { display:grid; grid-template-columns:155px 130px 130px 160px 1fr 36px; gap:10px; padding:10px 14px; border-bottom:1px solid rgba(255,255,255,0.04); font-size:12px; align-items:start; cursor:pointer; }
.audit-row.expanded { background:rgba(255,255,255,0.03); }
.audit-date { color:#6B7280; font-family:monospace; font-size:11px; white-space:nowrap; }
.audit-username { color:#E8E9ED; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.audit-entity { color:#9CA3AF; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.audit-entity-type { color:#E8E9ED; }
.audit-entity-id { color:#6B7280; margin-left:4px; }
.audit-details { color:#9CA3AF; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.audit-ip { color:#6B7280; font-family:monospace; font-size:10px; overflow:hidden; text-overflow:ellipsis; }
.audit-expanded { padding:10px 14px 14px; background:rgba(255,255,255,0.03); border-bottom:1px solid rgba(255,255,255,0.08); }
.audit-expanded-title { font-size:11px; font-weight:600; color:#6B7280; margin-bottom:6px; text-transform:uppercase; letter-spacing:.05em; }
.audit-pre { font-family:monospace; font-size:11px; color:#93C5FD; white-space:pre-wrap; word-break:break-all; background:rgba(0,0,0,0.3); padding:10px; border-radius:6px; max-height:300px; overflow-y:auto; }
.pagination { display:flex; justify-content:center; align-items:center; gap:6px; margin-top:16px; flex-wrap:wrap; }
.pagination-ellipsis { color:#6B7280; padding:0 4px; }
.pagination-btn { min-width:36px; padding:6px 10px; font-size:12px; }
</style>
