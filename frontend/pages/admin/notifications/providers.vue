<template>
  <div>
    <AppPageHeader title="Providers Notifications" back-to="/admin" />

    <!-- Tabs -->
    <div style="display:flex;gap:8px;margin-bottom:16px;">
      <button class="btn" :class="tab === 'providers' ? 'btn-primary' : 'btn-ghost'" @click="tab = 'providers'">
        📡 Providers
      </button>
      <button class="btn" :class="tab === 'templates' ? 'btn-primary' : 'btn-ghost'" @click="tab = 'templates'">
        📝 Templates
      </button>
      <button class="btn" :class="tab === 'logs' ? 'btn-primary' : 'btn-ghost'" @click="tab = 'logs'">
        📋 Historique envois
      </button>
    </div>

    <!-- Providers Tab -->
    <div v-if="tab === 'providers'">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <div style="font-size:14px;color:#9CA3AF;">
          Configurez vos fournisseurs SMS et Email avec fallback automatique.
        </div>
        <button class="btn btn-primary" @click="showAddModal = true">+ Ajouter un provider</button>
      </div>

      <div v-if="loadingProviders" style="text-align:center;padding:32px;color:#6B7280;">Chargement...</div>

      <AppErrorState
        v-else-if="providersError && !providers.length"
        title="Providers indisponibles"
        :description="providersError"
        @retry="fetchProviders"
      />

      <div v-else style="display:flex;flex-direction:column;gap:12px;">
        <!-- SMS Providers -->
        <UCard>
          <template #header>
            <span style="font-size:14px;font-weight:700;color:#E8E9ED;">📱 SMS</span>
          </template>
          <div v-if="smsProviders.length === 0" class="empty-state" style="padding:24px 16px;">
            <div class="empty-state-icon" style="font-size:28px;">📱</div>
            <div class="empty-state-sub">Aucun provider SMS configuré.</div>
            <button class="btn btn-primary empty-state-action" style="font-size:12px;" @click="form.channel = 'sms'; showAddModal = true">+ Configurer un provider SMS</button>
          </div>
          <div v-else style="display:flex;flex-direction:column;gap:8px;">
            <div v-for="p in smsProviders" :key="p.id" class="provider-row">
              <div style="display:flex;align-items:center;gap:10px;flex:1;">
                <span :class="['provider-badge', p.isActive ? 'active' : 'inactive']">
                  {{ p.provider }}
                </span>
                <span v-if="p.isPrimary" class="tag tag-primary">Principal</span>
                <span v-if="p.isFallback" class="tag tag-fallback">Fallback</span>
                <span style="color:#6B7280;font-size:12px;">Priorité: {{ p.priority }}</span>
                <span v-if="p.hasConfig" style="color:#10B981;font-size:12px;">✓ Configuré</span>
                <span v-else style="color:#EF4444;font-size:12px;">✗ Non configuré</span>
              </div>
              <div style="display:flex;gap:6px;">
                <button class="btn btn-ghost btn-sm" @click="editProvider(p)">⚙️</button>
                <button class="btn btn-ghost btn-sm" @click="testProviderModal(p)">🧪</button>
                <button class="btn btn-ghost btn-sm" style="color:#EF4444;" @click="deleteProvider(p)">🗑</button>
              </div>
              <div v-if="p.lastTestAt" style="width:100%;font-size:11px;margin-top:4px;" :style="{ color: p.lastTestSuccess ? '#10B981' : '#EF4444' }">
                Dernier test: {{ formatDate(p.lastTestAt) }} — {{ p.lastTestSuccess ? 'Succès' : 'Échec' }}
              </div>
            </div>
          </div>
        </UCard>

        <!-- Email Providers -->
        <UCard>
          <template #header>
            <span style="font-size:14px;font-weight:700;color:#E8E9ED;">📧 Email</span>
          </template>
          <div v-if="emailProviders.length === 0" class="empty-state" style="padding:24px 16px;">
            <div class="empty-state-icon" style="font-size:28px;">📧</div>
            <div class="empty-state-sub">Aucun provider Email configuré.</div>
            <button class="btn btn-primary empty-state-action" style="font-size:12px;" @click="form.channel = 'email'; showAddModal = true">+ Configurer un provider Email</button>
          </div>
          <div v-else style="display:flex;flex-direction:column;gap:8px;">
            <div v-for="p in emailProviders" :key="p.id" class="provider-row">
              <div style="display:flex;align-items:center;gap:10px;flex:1;">
                <span :class="['provider-badge', p.isActive ? 'active' : 'inactive']">
                  {{ p.provider }}
                </span>
                <span v-if="p.isPrimary" class="tag tag-primary">Principal</span>
                <span v-if="p.isFallback" class="tag tag-fallback">Fallback</span>
                <span style="color:#6B7280;font-size:12px;">Priorité: {{ p.priority }}</span>
                <span v-if="p.hasConfig" style="color:#10B981;font-size:12px;">✓ Configuré</span>
                <span v-else style="color:#EF4444;font-size:12px;">✗ Non configuré</span>
              </div>
              <div style="display:flex;gap:6px;">
                <button class="btn btn-ghost btn-sm" @click="editProvider(p)">⚙️</button>
                <button class="btn btn-ghost btn-sm" @click="testProviderModal(p)">🧪</button>
                <button class="btn btn-ghost btn-sm" style="color:#EF4444;" @click="deleteProvider(p)">🗑</button>
              </div>
            </div>
          </div>
        </UCard>
      </div>
    </div>

    <!-- Templates Tab -->
    <div v-if="tab === 'templates'">
      <div v-if="loadingTemplates" style="text-align:center;padding:32px;color:#6B7280;">Chargement...</div>
      <AppErrorState
        v-else-if="templatesError && !templates.length"
        title="Templates indisponibles"
        :description="templatesError"
        @retry="fetchTemplates"
      />
      <AppEmptyState
        v-else-if="templates.length === 0"
        icon="📝"
        title="Aucun template configuré"
        description="Les templates de notifications seront créés automatiquement dès la première synchronisation."
      />
      <UCard v-else>
        <table style="width:100%;font-size:13px;">
          <thead>
            <tr style="color:#9CA3AF;border-bottom:1px solid rgba(255,255,255,0.06);">
              <th style="padding:8px;text-align:left;">Code</th>
              <th style="padding:8px;text-align:left;">Canal</th>
              <th style="padding:8px;text-align:left;">Libellé</th>
              <th style="padding:8px;text-align:left;">Sujet</th>
              <th style="padding:8px;text-align:center;">Actif</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="t in templates" :key="t.id" style="border-bottom:1px solid rgba(255,255,255,0.04);">
              <td style="padding:8px;"><code style="font-size:12px;color:#60A5FA;">{{ t.code }}</code></td>
              <td style="padding:8px;">{{ t.channel === 'sms' ? '📱' : t.channel === 'email' ? '📧' : '🔔' }} {{ t.channel }}</td>
              <td style="padding:8px;color:#E8E9ED;">{{ t.libelle }}</td>
              <td style="padding:8px;color:#9CA3AF;">{{ t.sujet || '—' }}</td>
              <td style="padding:8px;text-align:center;">
                <span :style="{ color: t.isActive ? '#10B981' : '#EF4444' }">{{ t.isActive ? '✓' : '✗' }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </UCard>
    </div>

    <!-- Logs Tab -->
    <div v-if="tab === 'logs'">
      <div style="display:flex;gap:8px;margin-bottom:12px;">
        <select v-model="logFilter.channel" class="form-select" style="width:auto;font-size:12px;">
          <option value="">Tous canaux</option>
          <option value="sms">SMS</option>
          <option value="email">Email</option>
        </select>
        <select v-model="logFilter.status" class="form-select" style="width:auto;font-size:12px;">
          <option value="">Tous statuts</option>
          <option value="sent">Envoyé</option>
          <option value="delivered">Délivré</option>
          <option value="failed">Échoué</option>
          <option value="bounced">Rejeté</option>
        </select>
        <button class="btn btn-ghost btn-sm" @click="fetchLogs()">Actualiser</button>
      </div>

      <div v-if="loadingLogs" style="text-align:center;padding:32px;color:#6B7280;">Chargement...</div>
      <AppErrorState
        v-else-if="logsError && !logs.length"
        title="Historique indisponible"
        :description="logsError"
        @retry="fetchLogs"
      />
      <UCard v-else>
        <AppEmptyState
          v-if="logs.length === 0"
          icon="📋"
          title="Aucun envoi enregistré"
          description="L'historique apparaîtra dès le premier SMS ou email envoyé depuis l'atelier."
        />
        <table v-else style="width:100%;font-size:13px;">
          <thead>
            <tr style="color:#9CA3AF;border-bottom:1px solid rgba(255,255,255,0.06);">
              <th style="padding:8px;text-align:left;">Date</th>
              <th style="padding:8px;text-align:left;">Canal</th>
              <th style="padding:8px;text-align:left;">Provider</th>
              <th style="padding:8px;text-align:left;">Destinataire</th>
              <th style="padding:8px;text-align:left;">Template</th>
              <th style="padding:8px;text-align:center;">Statut</th>
              <th style="padding:8px;text-align:left;">Erreur</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="l in logs" :key="l.id" style="border-bottom:1px solid rgba(255,255,255,0.04);">
              <td style="padding:8px;white-space:nowrap;">{{ formatDate(l.sentAt) }}</td>
              <td style="padding:8px;">{{ l.channel === 'sms' ? '📱' : '📧' }}</td>
              <td style="padding:8px;">{{ l.provider }}</td>
              <td style="padding:8px;color:#E8E9ED;">{{ l.toRecipient }}</td>
              <td style="padding:8px;"><code style="font-size:11px;color:#60A5FA;">{{ l.templateCode || '—' }}</code></td>
              <td style="padding:8px;text-align:center;">
                <StatusBadge :status="l.status" />
              </td>
              <td style="padding:8px;color:#EF4444;font-size:11px;max-width:200px;overflow:hidden;text-overflow:ellipsis;">
                {{ l.errorMessage || '' }}
              </td>
            </tr>
          </tbody>
        </table>
      </UCard>
    </div>

    <!-- Add/Edit Provider Modal -->
    <AppModal
      :open="showAddModal || showEditModal"
      size="lg"
      @update:open="(value) => { if (!value) closeModals() }"
    >
      <template #header>{{ showEditModal ? 'Modifier le provider' : 'Ajouter un provider' }}</template>
      <form @submit.prevent="showEditModal ? saveEdit() : saveNew()" style="display:flex;flex-direction:column;gap:12px;">
        <div v-if="!showEditModal" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <UFormField label="Canal">
            <select v-model="form.channel" class="form-select" required>
              <option value="">Choisir…</option>
              <option value="sms">SMS</option>
              <option value="email">Email</option>
            </select>
          </UFormField>
          <UFormField label="Provider">
            <select v-model="form.provider" class="form-select" required>
              <option value="">Choisir…</option>
              <optgroup v-if="form.channel === 'sms'" label="SMS">
                <option value="twilio">Twilio</option>
                <option value="ovh">OVH</option>
                <option value="log_sms">SMS journalisé (dev)</option>
              </optgroup>
              <optgroup v-if="form.channel === 'email'" label="Email">
                <option value="mailgun">Mailgun</option>
                <option value="smtp_custom">SMTP Personnalisé</option>
              </optgroup>
            </select>
          </UFormField>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
          <UFormField label="Priorité">
            <UInput v-model.number="form.priority" type="number" min="1" max="10" />
          </UFormField>
          <UFormField label="Principal">
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
              <input type="checkbox" v-model="form.isPrimary" />
              <span style="font-size:13px;color:#E8E9ED;">Oui</span>
            </label>
          </UFormField>
          <UFormField label="Fallback">
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
              <input type="checkbox" v-model="form.isFallback" />
              <span style="font-size:13px;color:#E8E9ED;">Oui</span>
            </label>
          </UFormField>
        </div>

        <div
          v-if="showEditModal && form.hasExistingConfig"
          style="padding:10px 12px;border-radius:6px;background:rgba(96,165,250,0.08);color:#BFDBFE;font-size:12px;"
        >
          Des identifiants sont déjà enregistrés pour ce provider. Laissez un champ vide pour conserver la valeur actuelle.
        </div>

        <!-- Dynamic config fields based on provider -->
        <UCard v-if="form.provider" style="background:rgba(255,255,255,0.02);">
          <template #header><span style="font-size:13px;font-weight:600;color:#E8E9ED;">🔑 Clés API</span></template>
          <div style="display:flex;flex-direction:column;gap:10px;">
            <template v-if="form.provider === 'twilio'">
              <UFormField label="Account SID"><UInput v-model="form.config.account_sid" placeholder="ACxxxxx" /></UFormField>
              <UFormField label="Auth Token"><UInput v-model="form.config.auth_token" type="password" /></UFormField>
              <UFormField label="From (numéro)"><UInput v-model="form.config.from" placeholder="+33600000000" /></UFormField>
            </template>
            <template v-else-if="form.provider === 'ovh'">
              <UFormField label="Application Key"><UInput v-model="form.config.app_key" /></UFormField>
              <UFormField label="Application Secret"><UInput v-model="form.config.app_secret" type="password" /></UFormField>
              <UFormField label="Consumer Key"><UInput v-model="form.config.consumer_key" type="password" /></UFormField>
              <UFormField label="Service Name"><UInput v-model="form.config.service_name" /></UFormField>
              <UFormField label="Sender"><UInput v-model="form.config.sender" placeholder="AtelierMoto" /></UFormField>
            </template>
            <template v-else-if="form.provider === 'log_sms'">
              <div style="font-size:12px;color:#9CA3AF;">
                Mode développement : les SMS sont journalisés localement pour validation sans fournisseur externe.
              </div>
            </template>
            <template v-else-if="form.provider === 'mailgun'">
              <UFormField label="API Key"><UInput v-model="form.config.api_key" type="password" /></UFormField>
              <UFormField label="Domain"><UInput v-model="form.config.domain" placeholder="mg.example.com" /></UFormField>
              <UFormField label="From email"><UInput v-model="form.config.from" type="email" placeholder="noreply@example.com" /></UFormField>
            </template>
            <template v-else-if="form.provider === 'smtp_custom'">
              <UFormField label="Hôte SMTP"><UInput v-model="form.config.host" placeholder="smtp.example.com" /></UFormField>
              <UFormField label="Port"><UInput v-model="form.config.port" type="number" placeholder="587" /></UFormField>
              <UFormField label="Utilisateur"><UInput v-model="form.config.username" /></UFormField>
              <UFormField label="Mot de passe"><UInput v-model="form.config.password" type="password" /></UFormField>
              <UFormField label="From email"><UInput v-model="form.config.from" type="email" /></UFormField>
            </template>
          </div>
        </UCard>

        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:8px;">
          <button type="button" class="btn btn-ghost" @click="closeModals">Annuler</button>
          <button type="submit" class="btn btn-primary" :disabled="saving">
            {{ saving ? 'Enregistrement...' : 'Enregistrer' }}
          </button>
        </div>
      </form>
    </AppModal>

    <!-- Test Modal -->
    <AppModal v-model:open="showTestModal" size="sm">
      <template #header>Tester le provider {{ testTarget?.provider }}</template>
      <form @submit.prevent="runTest" style="display:flex;flex-direction:column;gap:12px;">
        <UFormField :label="testTarget?.channel === 'sms' ? 'Numéro de téléphone' : 'Adresse email'">
          <UInput v-model="testRecipient" :type="testTarget?.channel === 'email' ? 'email' : 'tel'" required
            :placeholder="testTarget?.channel === 'sms' ? '+33612345678' : 'test@example.com'" />
        </UFormField>
        <div v-if="testResult" :style="{ padding: '12px', borderRadius: '6px', background: testResult.success ? 'rgba(16,185,129,0.1)' : 'rgba(239,68,68,0.1)', color: testResult.success ? '#10B981' : '#EF4444', fontSize: '13px' }">
          {{ testResult.success ? '✓ Test réussi' : '✗ Échec: ' + testResult.error }}
        </div>
        <div style="display:flex;justify-content:flex-end;gap:8px;">
          <button type="button" class="btn btn-ghost" @click="showTestModal = false">Fermer</button>
          <button type="submit" class="btn btn-primary" :disabled="testing">
            {{ testing ? 'Envoi...' : 'Envoyer le test' }}
          </button>
        </div>
      </form>
    </AppModal>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
const { apiFetch } = useApi();
const toast = useToast();

const tab = ref('providers');
const providers = ref([]);
const templates = ref([]);
const logs = ref([]);
const loadingProviders = ref(false);
const loadingTemplates = ref(false);
const loadingLogs = ref(false);
const saving = ref(false);
const providersError = ref('');
const templatesError = ref('');
const logsError = ref('');

const smsProviders = computed(() => providers.value.filter(p => p.channel === 'sms'));
const emailProviders = computed(() => providers.value.filter(p => p.channel === 'email'));

// Provider form
const showAddModal = ref(false);
const showEditModal = ref(false);
const editId = ref(null);
const form = ref(emptyForm());

function emptyForm() {
  return { channel: '', provider: '', isPrimary: false, isFallback: false, priority: 1, config: {}, hasExistingConfig: false };
}

function closeModals() {
  showAddModal.value = false;
  showEditModal.value = false;
  editId.value = null;
  form.value = emptyForm();
}

// Test
const showTestModal = ref(false);
const testTarget = ref(null);
const testRecipient = ref('');
const testResult = ref(null);
const testing = ref(false);

// Logs filter
const logFilter = ref({ channel: '', status: '' });

async function fetchProviders() {
  loadingProviders.value = true;
  providersError.value = '';
  try {
    const data = await apiFetch('/api/admin/notification-providers');
    providers.value = data;
  } catch (e) { providersError.value = e?.message || 'Impossible de charger la configuration des providers.'; toast.add({ title: 'Erreur chargement providers', description: e?.message, color: 'error' }); }
  loadingProviders.value = false;
}

async function fetchTemplates() {
  loadingTemplates.value = true;
  templatesError.value = '';
  try {
    const data = await apiFetch('/api/admin/notification-templates');
    templates.value = data;
  } catch (e) { templatesError.value = e?.message || 'Impossible de charger les templates de notification.'; toast.add({ title: 'Erreur chargement templates', description: e?.message, color: 'error' }); }
  loadingTemplates.value = false;
}

async function fetchLogs() {
  loadingLogs.value = true;
  logsError.value = '';
  try {
    const params = new URLSearchParams();
    if (logFilter.value.channel) params.set('channel', logFilter.value.channel);
    if (logFilter.value.status) params.set('status', logFilter.value.status);
    const data = await apiFetch(`/api/admin/notification-logs?${params.toString()}`);
    logs.value = data.items || [];
  } catch (e) { logsError.value = e?.message || 'Impossible de charger l\'historique des envois.'; toast.add({ title: 'Erreur chargement logs', description: e?.message, color: 'error' }); }
  loadingLogs.value = false;
}

async function saveNew() {
  saving.value = true;
  try {
    await apiFetch('/api/admin/notification-providers', {
      method: 'POST',
      body: form.value,
    });
    closeModals();
    await fetchProviders();
  } catch (e) { toast.add({ title: 'Erreur', description: e?.data?.error || e?.message || 'Erreur création', color: 'error' }); }
  saving.value = false;
}

async function saveEdit() {
  saving.value = true;
  try {
    await apiFetch(`/api/admin/notification-providers/${editId.value}`, {
      method: 'PUT',
      body: form.value,
    });
    closeModals();
    await fetchProviders();
  } catch (e) { toast.add({ title: 'Erreur', description: e?.data?.error || e?.message || 'Erreur modification', color: 'error' }); }
  saving.value = false;
}

function editProvider(p) {
  editId.value = p.id;
  form.value = {
    isPrimary: p.isPrimary,
    isFallback: p.isFallback,
    priority: p.priority,
    config: {},
    hasExistingConfig: !!p.hasConfig,
    channel: p.channel,
    provider: p.provider,
  };
  showEditModal.value = true;
}

async function deleteProvider(p) {
  if (!confirm(`Supprimer le provider ${p.provider} (${p.channel}) ?`)) return;
  try {
    await apiFetch(`/api/admin/notification-providers/${p.id}`, { method: 'DELETE' });
    await fetchProviders();
  } catch (e) { toast.add({ title: 'Erreur suppression', description: e?.message, color: 'error' }); }
}

function testProviderModal(p) {
  testTarget.value = p;
  testRecipient.value = '';
  testResult.value = null;
  showTestModal.value = true;
}

async function runTest() {
  testing.value = true;
  testResult.value = null;
  try {
    const data = await apiFetch(`/api/admin/notification-providers/${testTarget.value.id}/test`, {
      method: 'POST',
      body: { recipient: testRecipient.value },
    });
    testResult.value = data;
    await fetchProviders(); // Refresh test status
  } catch (e) {
    testResult.value = { success: false, error: e.data?.error || 'Erreur réseau' };
  }
  testing.value = false;
}

function formatDate(iso) {
  if (!iso) return '—';
  return new Date(iso).toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' });
}

watch(tab, (val) => {
  if (val === 'templates' && templates.value.length === 0) fetchTemplates();
  if (val === 'logs' && logs.value.length === 0) fetchLogs();
});

watch(logFilter, () => { if (tab.value === 'logs') fetchLogs(); }, { deep: true });

onMounted(() => fetchProviders());
</script>

<style scoped>
.provider-row {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  padding: 10px 12px;
  border-radius: 6px;
  background: rgba(255,255,255,0.02);
  border: 1px solid rgba(255,255,255,0.06);
}
.provider-badge {
  font-size: 12px;
  font-weight: 700;
  padding: 3px 10px;
  border-radius: 12px;
  text-transform: uppercase;
}
.provider-badge.active {
  background: rgba(16,185,129,0.15);
  color: #10B981;
}
.provider-badge.inactive {
  background: rgba(239,68,68,0.15);
  color: #EF4444;
}
.tag {
  font-size: 11px;
  padding: 2px 8px;
  border-radius: 10px;
}
.tag-primary {
  background: rgba(96,165,250,0.15);
  color: #60A5FA;
}
.tag-fallback {
  background: rgba(251,191,36,0.15);
  color: #FBBF24;
}
.btn-sm {
  padding: 4px 8px;
  font-size: 12px;
}
.form-select {
  background: rgba(255,255,255,0.05);
  border: 1px solid rgba(255,255,255,0.1);
  color: #E8E9ED;
  border-radius: 6px;
  padding: 8px 10px;
  font-size: 13px;
  width: 100%;
}
</style>
