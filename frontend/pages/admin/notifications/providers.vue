<template>
  <div>
    <div class="page-header">
      <div style="display:flex;align-items:center;gap:12px;">
        <NuxtLink to="/admin" style="color:#6B7280;text-decoration:none;font-size:18px;">◀</NuxtLink>
        <div class="page-title">Notifications</div>
      </div>
    </div>

    <div style="display:flex;flex-direction:column;gap:20px;max-width:1100px;">
      <!-- ─── MES CANAUX ─── -->
      <UCard>
        <template #header>
          <span style="font-size:15px;font-weight:700;color:#E8E9ED;">📡 Mes canaux</span>
        </template>

        <div v-if="loadingProviders" style="text-align:center;padding:24px;color:#6B7280;">Chargement...</div>
        <div v-else style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;">
          <!-- SMS -->
          <div style="padding:16px;border-radius:12px;border:1px solid rgba(255,255,255,0.08);background:rgba(255,255,255,0.02);">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
              <span style="font-size:20px;">📱</span>
              <span style="font-size:15px;font-weight:700;color:#E8E9ED;">SMS</span>
              <span v-if="smsConfigured" style="font-size:12px;padding:3px 10px;border-radius:999px;background:rgba(16,185,129,0.15);color:#10B981;">✓ Configuré</span>
              <span v-else style="font-size:12px;padding:3px 10px;border-radius:999px;background:rgba(239,68,68,0.15);color:#EF4444;">Non configuré</span>
            </div>
            <div v-if="smsConfigured" style="display:flex;flex-direction:column;gap:8px;">
              <div style="font-size:13px;color:#D1D5DB;">Provider : <strong style="color:#E8E9ED;">{{ smsProviders[0]?.provider }}</strong></div>
              <div v-if="smsProviders[0]?.lastTestAt" style="font-size:12px;" :style="smsProviders[0]?.lastTestSuccess ? 'color:#10B981;' : 'color:#EF4444;'">
                Dernier test : {{ formatDate(smsProviders[0]?.lastTestAt) }} — {{ smsProviders[0]?.lastTestSuccess ? 'Succès' : 'Échec' }}
              </div>
              <div style="display:flex;gap:8px;margin-top:4px;">
                <button class="btn btn-ghost" style="font-size:12px;" @click="editProvider(smsProviders[0])">Modifier</button>
                <button class="btn btn-ghost" style="font-size:12px;" @click="testProviderModal(smsProviders[0])">Tester</button>
              </div>
            </div>
            <div v-else style="display:flex;flex-direction:column;gap:10px;">
              <div style="font-size:13px;color:#9CA3AF;">Aucun service SMS configuré. Les SMS ne pourront pas être envoyés.</div>
              <button class="btn btn-primary" style="font-size:12px;align-self:flex-start;" @click="openProviderModal('sms')">Configurer SMS</button>
            </div>
          </div>

          <!-- Email -->
          <div style="padding:16px;border-radius:12px;border:1px solid rgba(255,255,255,0.08);background:rgba(255,255,255,0.02);">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
              <span style="font-size:20px;">📧</span>
              <span style="font-size:15px;font-weight:700;color:#E8E9ED;">Email</span>
              <span v-if="emailConfigured" style="font-size:12px;padding:3px 10px;border-radius:999px;background:rgba(16,185,129,0.15);color:#10B981;">✓ Configuré</span>
              <span v-else style="font-size:12px;padding:3px 10px;border-radius:999px;background:rgba(239,68,68,0.15);color:#EF4444;">Non configuré</span>
            </div>
            <div v-if="emailConfigured" style="display:flex;flex-direction:column;gap:8px;">
              <div style="font-size:13px;color:#D1D5DB;">Provider : <strong style="color:#E8E9ED;">{{ emailProviders[0]?.provider }}</strong></div>
              <div v-if="emailProviders[0]?.lastTestAt" style="font-size:12px;" :style="emailProviders[0]?.lastTestSuccess ? 'color:#10B981;' : 'color:#EF4444;'">
                Dernier test : {{ formatDate(emailProviders[0]?.lastTestAt) }} — {{ emailProviders[0]?.lastTestSuccess ? 'Succès' : 'Échec' }}
              </div>
              <div style="display:flex;gap:8px;margin-top:4px;">
                <button class="btn btn-ghost" style="font-size:12px;" @click="editProvider(emailProviders[0])">Modifier</button>
                <button class="btn btn-ghost" style="font-size:12px;" @click="testProviderModal(emailProviders[0])">Tester</button>
              </div>
            </div>
            <div v-else style="display:flex;flex-direction:column;gap:10px;">
              <div style="font-size:13px;color:#9CA3AF;">Aucun service Email configuré. Les emails ne pourront pas être envoyés.</div>
              <button class="btn btn-primary" style="font-size:12px;align-self:flex-start;" @click="openProviderModal('email')">Configurer Email</button>
            </div>
          </div>
        </div>
      </UCard>

      <!-- ─── MES MESSAGES ─── -->
      <UCard>
        <template #header>
          <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
            <span style="font-size:15px;font-weight:700;color:#E8E9ED;">📝 Mes messages</span>
            <button class="btn btn-primary" style="font-size:12px;padding:6px 14px;" @click="openTemplateModal()">+ Nouveau message</button>
          </div>
        </template>

        <div v-if="loadingTemplates" style="text-align:center;padding:24px;color:#6B7280;">Chargement...</div>
        <div v-else-if="templates.length === 0" style="text-align:center;padding:24px;color:#9CA3AF;">
          Aucun message configuré.
        </div>
        <div v-else style="display:flex;flex-direction:column;gap:10px;">
          <div
            v-for="t in templates"
            :key="t.id"
            style="display:flex;align-items:center;flex-wrap:wrap;gap:10px;padding:12px;border-radius:8px;border:1px solid rgba(255,255,255,0.06);background:rgba(255,255,255,0.02);"
          >
            <div style="flex:1;min-width:200px;">
              <div style="display:flex;align-items:center;gap:8px;">
                <span style="font-size:13px;font-weight:700;color:#E8E9ED;">{{ t.libelle }}</span>
                <span style="font-size:11px;padding:2px 8px;border-radius:999px;background:rgba(139,92,246,0.14);color:#C4B5FD;">{{ t.channel }}</span>
                <span v-if="!t.isActive" style="font-size:11px;padding:2px 8px;border-radius:999px;background:rgba(107,114,128,0.15);color:#9CA3AF;">Inactif</span>
              </div>
              <div style="font-size:12px;color:#9CA3AF;margin-top:2px;">
                <code style="color:#60A5FA;">{{ t.code }}</code> — {{ t.sujet || 'Sans sujet' }}
              </div>
            </div>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
              <button class="btn btn-ghost" style="font-size:12px;color:#FFD200;" @click="openTemplateModal(t)">✏ Modifier</button>
              <button class="btn btn-ghost" style="font-size:12px;" @click="toggleTemplateActive(t)">
                {{ t.isActive ? '⏸ Désactiver' : '▶ Activer' }}
              </button>
              <button class="btn btn-ghost" style="font-size:12px;color:#EF4444;" @click="deleteTemplate(t)">🗑 Supprimer</button>
            </div>
          </div>
        </div>
      </UCard>

      <!-- ─── HISTORIQUE ─── -->
      <UCard>
        <template #header>
          <span style="font-size:15px;font-weight:700;color:#E8E9ED;">📋 Historique des envois</span>
        </template>

        <div style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap;">
          <UInput v-model="logSearch" placeholder="Rechercher..." icon="i-heroicons-magnifying-glass" style="flex:1;min-width:200px;" />
          <button class="btn" :class="logFilterChip === 'all' ? 'btn-primary' : 'btn-ghost'" style="font-size:12px;padding:6px 14px;" @click="logFilterChip = 'all'">Tous</button>
          <button class="btn" :class="logFilterChip === 'sms' ? 'btn-primary' : 'btn-ghost'" style="font-size:12px;padding:6px 14px;" @click="logFilterChip = 'sms'">📱 SMS</button>
          <button class="btn" :class="logFilterChip === 'email' ? 'btn-primary' : 'btn-ghost'" style="font-size:12px;padding:6px 14px;" @click="logFilterChip = 'email'">📧 Email</button>
          <button class="btn" :class="logFilterChip === 'failed' ? 'btn-primary' : 'btn-ghost'" style="font-size:12px;padding:6px 14px;" @click="logFilterChip = 'failed'">❌ Échoués</button>
          <button class="btn btn-ghost" style="font-size:12px;padding:6px 14px;" @click="fetchLogs()">🔄 Actualiser</button>
        </div>

        <div v-if="loadingLogs" style="text-align:center;padding:24px;color:#6B7280;">Chargement...</div>
        <div v-else-if="filteredLogs.length === 0" style="text-align:center;padding:24px;color:#9CA3AF;">Aucun envoi trouvé</div>
        <div v-else style="display:flex;flex-direction:column;gap:8px;">
          <div
            v-for="l in filteredLogs"
            :key="l.id"
            style="display:flex;align-items:center;flex-wrap:wrap;gap:10px;padding:10px 12px;border-radius:8px;border:1px solid rgba(255,255,255,0.04);background:rgba(255,255,255,0.01);"
          >
            <span style="font-size:14px;">{{ l.channel === 'sms' ? '📱' : '📧' }}</span>
            <div style="flex:1;min-width:180px;">
              <div style="font-size:13px;color:#E8E9ED;">{{ l.toRecipient }}</div>
              <div style="font-size:11px;color:#9CA3AF;">{{ l.templateCode || 'Sans template' }} — {{ formatDate(l.sentAt) }}</div>
            </div>
            <StatusBadge :status="l.status" />
            <span v-if="l.errorMessage" style="font-size:11px;color:#EF4444;max-width:200px;overflow:hidden;text-overflow:ellipsis;">{{ l.errorMessage }}</span>
          </div>
        </div>
      </UCard>
    </div>

    <!-- ─── MODAL PROVIDER ─── -->
    <AppModal
      :open="showProviderModal"
      size="lg"
      @update:open="(value) => { if (!value) closeProviderModal() }"
    >
      <template #content>
        <UCard>
          <template #header>
            <div style="display:flex;justify-content:space-between;align-items:center;">
              <span style="font-size:15px;font-weight:700;color:#E8E9ED;">{{ providerEditId ? 'Modifier' : 'Configurer' }} {{ providerForm.channel === 'sms' ? 'SMS' : 'Email' }}</span>
              <button @click="closeProviderModal" style="background:none;border:none;color:#9CA3AF;font-size:18px;cursor:pointer;">✕</button>
            </div>
          </template>

          <form @submit.prevent="saveProvider" style="display:flex;flex-direction:column;gap:12px;">
            <div v-if="!providerEditId" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;">
              <UFormField label="Canal">
                <select v-model="providerForm.channel" class="form-input" disabled>
                  <option value="sms">SMS</option>
                  <option value="email">Email</option>
                </select>
              </UFormField>
              <UFormField label="Service">
                <select v-model="providerForm.provider" class="form-input" required>
                  <option value="">Choisir…</option>
                  <optgroup v-if="providerForm.channel === 'sms'" label="SMS">
                    <option value="twilio">Twilio</option>
                    <option value="ovh">OVH</option>
                    <option value="log_sms">SMS journalisé (dev)</option>
                  </optgroup>
                  <optgroup v-if="providerForm.channel === 'email'" label="Email">
                    <option value="mailgun">Mailgun</option>
                    <option value="smtp_custom">SMTP Personnalisé</option>
                  </optgroup>
                </select>
              </UFormField>
            </div>

            <div v-else style="font-size:13px;color:#9CA3AF;">
              Service : <strong style="color:#E8E9ED;">{{ providerForm.provider }}</strong>
            </div>

            <UCard v-if="providerForm.provider" style="background:rgba(255,255,255,0.02);">
              <template #header><span style="font-size:13px;font-weight:600;color:#E8E9ED;">🔑 Identifiants</span></template>
              <div style="display:flex;flex-direction:column;gap:10px;">
                <template v-if="providerForm.provider === 'twilio'">
                  <UFormField label="Account SID"><UInput v-model="providerForm.config.account_sid" placeholder="ACxxxxx" /></UFormField>
                  <UFormField label="Auth Token"><UInput v-model="providerForm.config.auth_token" type="password" /></UFormField>
                  <UFormField label="Numéro d'envoi"><UInput v-model="providerForm.config.from" placeholder="+33600000000" /></UFormField>
                </template>
                <template v-else-if="providerForm.provider === 'ovh'">
                  <UFormField label="Application Key"><UInput v-model="providerForm.config.app_key" /></UFormField>
                  <UFormField label="Application Secret"><UInput v-model="providerForm.config.app_secret" type="password" /></UFormField>
                  <UFormField label="Consumer Key"><UInput v-model="providerForm.config.consumer_key" type="password" /></UFormField>
                  <UFormField label="Service Name"><UInput v-model="providerForm.config.service_name" /></UFormField>
                  <UFormField label="Expéditeur"><UInput v-model="providerForm.config.sender" placeholder="AtelierMoto" /></UFormField>
                </template>
                <template v-else-if="providerForm.provider === 'log_sms'">
                  <div style="font-size:12px;color:#9CA3AF;">Mode développement : les SMS sont journalisés localement.</div>
                </template>
                <template v-else-if="providerForm.provider === 'mailgun'">
                  <UFormField label="API Key"><UInput v-model="providerForm.config.api_key" type="password" /></UFormField>
                  <UFormField label="Domaine"><UInput v-model="providerForm.config.domain" placeholder="mg.example.com" /></UFormField>
                  <UFormField label="Email d'envoi"><UInput v-model="providerForm.config.from" type="email" placeholder="noreply@example.com" /></UFormField>
                </template>
                <template v-else-if="providerForm.provider === 'smtp_custom'">
                  <UFormField label="Hôte SMTP"><UInput v-model="providerForm.config.host" placeholder="smtp.example.com" /></UFormField>
                  <UFormField label="Port"><UInput v-model="providerForm.config.port" type="number" placeholder="587" /></UFormField>
                  <UFormField label="Utilisateur"><UInput v-model="providerForm.config.username" /></UFormField>
                  <UFormField label="Mot de passe"><UInput v-model="providerForm.config.password" type="password" /></UFormField>
                  <UFormField label="Email d'envoi"><UInput v-model="providerForm.config.from" type="email" /></UFormField>
                </template>
              </div>
            </UCard>

            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:8px;">
              <button type="button" class="btn btn-ghost" @click="closeProviderModal">Annuler</button>
              <button type="submit" class="btn btn-primary" :disabled="providerSaving">{{ providerSaving ? 'Enregistrement...' : 'Enregistrer' }}</button>
            </div>
          </form>
        </UCard>
      </template>
    </AppModal>

    <!-- ─── MODAL TEST ─── -->
    <AppModal v-model:open="showTestModal" size="sm">
      <template #content>
        <UCard>
          <template #header>
            <span style="font-size:15px;font-weight:700;color:#E8E9ED;">Tester {{ testTarget?.provider }}</span>
          </template>
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
              <button type="submit" class="btn btn-primary" :disabled="testing">{{ testing ? 'Envoi...' : 'Envoyer le test' }}</button>
            </div>
          </form>
        </UCard>
      </template>
    </AppModal>

    <!-- ─── MODAL TEMPLATE ─── -->
    <AppModal v-model:open="showTemplateModal" size="lg">
      <template #content>
        <UCard>
          <template #header>
            <div style="display:flex;justify-content:space-between;align-items:center;">
              <span style="font-size:15px;font-weight:700;color:#E8E9ED;">{{ templateEditId ? 'Modifier' : 'Nouveau' }} message</span>
              <button @click="closeTemplateModal" style="background:none;border:none;color:#9CA3AF;font-size:18px;cursor:pointer;">✕</button>
            </div>
          </template>

          <form @submit.prevent="saveTemplate" style="display:flex;flex-direction:column;gap:12px;">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;">
              <UFormField label="Code *">
                <UInput v-model="templateForm.code" required placeholder="rdv_confirme" :disabled="!!templateEditId" />
              </UFormField>
              <UFormField label="Canal *">
                <select v-model="templateForm.channel" class="form-input" required :disabled="!!templateEditId">
                  <option value="sms">📱 SMS</option>
                  <option value="email">📧 Email</option>
                </select>
              </UFormField>
            </div>

            <UFormField label="Nom du message *">
              <UInput v-model="templateForm.libelle" required placeholder="Confirmation de RDV" />
            </UFormField>

            <UFormField v-if="templateForm.channel === 'email'" label="Sujet">
              <UInput v-model="templateForm.sujet" placeholder="Votre RDV est confirmé" />
            </UFormField>

            <UFormField label="Message *">
              <UTextarea v-model="templateForm.corps" rows="5" required :placeholder="templateForm.channel === 'email' ? '<h1>Bonjour</h1><p>Votre RDV...</p>' : 'Bonjour {{client_prenom}}, votre RDV est confirmé pour le {{rdv_date}}.'" />
            </UFormField>

            <div v-if="templateForm.channel === 'email'" style="display:flex;align-items:center;gap:8px;">
              <input id="html-mode" v-model="templateHtmlMode" type="checkbox" />
              <label for="html-mode" style="font-size:13px;color:#9CA3AF;">Le message contient du HTML</label>
            </div>

            <div style="padding:10px 12px;border-radius:8px;background:rgba(96,165,250,0.08);border:1px solid rgba(96,165,250,0.15);">
              <div style="font-size:12px;font-weight:600;color:#93C5FD;margin-bottom:4px;">Variables disponibles</div>
              <div style="font-size:12px;color:#BFDBFE;display:flex;flex-wrap:wrap;gap:6px;">
                <code v-for="v in availableVariables" :key="v" style="background:rgba(255,255,255,0.08);padding:2px 8px;border-radius:4px;cursor:pointer;" @click="insertVariable(v)">{{ v }}</code>
              </div>
            </div>

            <div style="padding:10px 12px;border-radius:8px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);">
              <div style="font-size:12px;font-weight:600;color:#E8E9ED;margin-bottom:4px;">Prévisualisation</div>
              <div v-if="templateForm.channel === 'email' && templateHtmlMode" style="padding:16px;border-radius:6px;background:#ffffff;max-width:600px;overflow:auto;">
                <div style="color:#111827;font-family:Arial,sans-serif;font-size:14px;line-height:1.5;" v-html="previewTemplateHtml" />
              </div>
              <div v-else style="font-size:13px;color:#D1D5DB;white-space:pre-wrap;">{{ previewTemplate }}</div>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:8px;">
              <button type="button" class="btn btn-ghost" @click="closeTemplateModal">Annuler</button>
              <button type="submit" class="btn btn-primary" :disabled="templateSaving">{{ templateSaving ? 'Enregistrement...' : 'Enregistrer' }}</button>
            </div>
          </form>
        </UCard>
      </template>
    </AppModal>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'

const { apiFetch } = useApi()
const toast = useToast()

// ─── Providers ───
const providers = ref([])
const loadingProviders = ref(false)
const showProviderModal = ref(false)
const providerEditId = ref(null)
const providerSaving = ref(false)
const providerForm = ref(emptyProviderForm())

function emptyProviderForm() {
  return { channel: '', provider: '', config: {} }
}

const smsProviders = computed(() => providers.value.filter(p => p.channel === 'sms'))
const emailProviders = computed(() => providers.value.filter(p => p.channel === 'email'))
const smsConfigured = computed(() => smsProviders.value.length > 0 && smsProviders.value.some(p => p.hasConfig))
const emailConfigured = computed(() => emailProviders.value.length > 0 && emailProviders.value.some(p => p.hasConfig))

// ─── Templates ───
const templates = ref([])
const loadingTemplates = ref(false)
const showTemplateModal = ref(false)
const templateEditId = ref(null)
const templateSaving = ref(false)
const templateForm = ref(emptyTemplateForm())
const templateHtmlMode = ref(false)

function emptyTemplateForm() {
  return { code: '', channel: 'sms', libelle: '', sujet: '', corps: '', variables: [] }
}

const availableVariables = ['{{client_prenom}}', '{{client_nom}}', '{{rdv_date}}', '{{rdv_heure}}', '{{atelier_nom}}', '{{prestation_nom}}']

const previewTemplate = computed(() => {
  let text = templateForm.value.corps || ''
  const samples = {
    '{{client_prenom}}': 'Jean',
    '{{client_nom}}': 'Dupont',
    '{{rdv_date}}': '15/06/2026',
    '{{rdv_heure}}': '14h30',
    '{{atelier_nom}}': 'Paddock Paris',
    '{{prestation_nom}}': 'Vidange',
  }
  for (const [key, val] of Object.entries(samples)) {
    text = text.replace(new RegExp(key.replace(/[{}]/g, '\\$&'), 'g'), val)
  }
  return text || '—'
})

const previewTemplateHtml = computed(() => {
  let html = templateForm.value.corps || '<p>—</p>'
  const samples = {
    '{{client_prenom}}': 'Jean',
    '{{client_nom}}': 'Dupont',
    '{{rdv_date}}': '15/06/2026',
    '{{rdv_heure}}': '14h30',
    '{{atelier_nom}}': 'Paddock Paris',
    '{{prestation_nom}}': 'Vidange',
  }
  for (const [key, val] of Object.entries(samples)) {
    html = html.replace(new RegExp(key.replace(/[{}]/g, '\\$&'), 'g'), val)
  }
  return html
})

function insertVariable(v) {
  templateForm.value.corps += (templateForm.value.corps ? ' ' : '') + v
}

// ─── Logs ───
const logs = ref([])
const loadingLogs = ref(false)
const logSearch = ref('')
const logFilterChip = ref('all')

const filteredLogs = computed(() => {
  let items = logs.value
  if (logFilterChip.value === 'sms') items = items.filter(l => l.channel === 'sms')
  else if (logFilterChip.value === 'email') items = items.filter(l => l.channel === 'email')
  else if (logFilterChip.value === 'failed') items = items.filter(l => l.status === 'failed' || l.status === 'bounced')

  const q = logSearch.value.trim().toLowerCase()
  if (q) {
    items = items.filter(l =>
      (l.toRecipient || '').toLowerCase().includes(q) ||
      (l.templateCode || '').toLowerCase().includes(q) ||
      (l.provider || '').toLowerCase().includes(q)
    )
  }
  return items
})

// ─── Test ───
const showTestModal = ref(false)
const testTarget = ref(null)
const testRecipient = ref('')
const testResult = ref(null)
const testing = ref(false)

// ─── Fetch ───
async function fetchProviders() {
  loadingProviders.value = true
  try {
    providers.value = await apiFetch('/api/admin/notification-providers')
  } catch (e) { toast.add({ title: 'Erreur chargement canaux', description: e?.message, color: 'error' }) }
  loadingProviders.value = false
}

async function fetchTemplates() {
  loadingTemplates.value = true
  try {
    templates.value = await apiFetch('/api/admin/notification-templates')
  } catch (e) { toast.add({ title: 'Erreur chargement messages', description: e?.message, color: 'error' }) }
  loadingTemplates.value = false
}

async function fetchLogs() {
  loadingLogs.value = true
  try {
    const params = new URLSearchParams()
    if (logFilterChip.value === 'sms') params.set('channel', 'sms')
    if (logFilterChip.value === 'email') params.set('channel', 'email')
    if (logFilterChip.value === 'failed') params.set('status', 'failed')
    const data = await apiFetch(`/api/admin/notification-logs?${params.toString()}`)
    logs.value = data.items || []
  } catch (e) { toast.add({ title: 'Erreur chargement historique', description: e?.message, color: 'error' }) }
  loadingLogs.value = false
}

// ─── Provider actions ───
function openProviderModal(channel) {
  providerEditId.value = null
  providerForm.value = emptyProviderForm()
  providerForm.value.channel = channel
  showProviderModal.value = true
}

function editProvider(p) {
  providerEditId.value = p.id
  providerForm.value = {
    channel: p.channel,
    provider: p.provider,
    config: {},
  }
  showProviderModal.value = true
}

function closeProviderModal() {
  showProviderModal.value = false
  providerEditId.value = null
  providerForm.value = emptyProviderForm()
}

async function saveProvider() {
  providerSaving.value = true
  try {
    const payload = {
      channel: providerForm.value.channel,
      provider: providerForm.value.provider,
      config: providerForm.value.config,
      isPrimary: true,
      isFallback: false,
      priority: 1,
    }
    if (providerEditId.value) {
      await apiFetch(`/api/admin/notification-providers/${providerEditId.value}`, { method: 'PUT', body: payload })
    } else {
      await apiFetch('/api/admin/notification-providers', { method: 'POST', body: payload })
    }
    closeProviderModal()
    await fetchProviders()
    toast.add({ title: 'Canal sauvegardé', color: 'success' })
  } catch (e) {
    toast.add({ title: 'Erreur', description: e?.data?.error || e?.message || 'Erreur sauvegarde', color: 'error' })
  }
  providerSaving.value = false
}

function testProviderModal(p) {
  testTarget.value = p
  testRecipient.value = ''
  testResult.value = null
  showTestModal.value = true
}

async function runTest() {
  testing.value = true
  testResult.value = null
  try {
    const data = await apiFetch(`/api/admin/notification-providers/${testTarget.value.id}/test`, {
      method: 'POST',
      body: { recipient: testRecipient.value },
    })
    testResult.value = data
    await fetchProviders()
  } catch (e) {
    testResult.value = { success: false, error: e.data?.error || 'Erreur réseau' }
  }
  testing.value = false
}

// ─── Template actions ───
function openTemplateModal(t = null) {
  if (t) {
    templateEditId.value = t.id
    templateForm.value = {
      code: t.code,
      channel: t.channel,
      libelle: t.libelle,
      sujet: t.sujet || '',
      corps: t.corps || '',
      variables: t.variables || [],
    }
  } else {
    templateEditId.value = null
    templateForm.value = emptyTemplateForm()
  }
  showTemplateModal.value = true
}

function closeTemplateModal() {
  showTemplateModal.value = false
  templateEditId.value = null
  templateForm.value = emptyTemplateForm()
}

async function saveTemplate() {
  templateSaving.value = true
  try {
    const payload = {
      code: templateForm.value.code,
      channel: templateForm.value.channel,
      libelle: templateForm.value.libelle,
      sujet: templateForm.value.sujet || null,
      corps: templateForm.value.corps,
      variables: templateForm.value.variables,
      isActive: true,
    }
    if (templateEditId.value) {
      await apiFetch(`/api/admin/notification-templates/${templateEditId.value}`, { method: 'PUT', body: payload })
    } else {
      await apiFetch('/api/admin/notification-templates', { method: 'POST', body: payload })
    }
    closeTemplateModal()
    await fetchTemplates()
    toast.add({ title: 'Message sauvegardé', color: 'success' })
  } catch (e) {
    toast.add({ title: 'Erreur', description: e?.data?.error || e?.message || 'Erreur sauvegarde', color: 'error' })
  }
  templateSaving.value = false
}

async function toggleTemplateActive(t) {
  try {
    await apiFetch(`/api/admin/notification-templates/${t.id}`, {
      method: 'PUT',
      body: { isActive: !t.isActive },
    })
    await fetchTemplates()
    toast.add({ title: t.isActive ? 'Message désactivé' : 'Message activé', color: 'success' })
  } catch (e) {
    toast.add({ title: 'Erreur', description: e?.message, color: 'error' })
  }
}

async function deleteTemplate(t) {
  if (!confirm(`Supprimer le message "${t.libelle}" ?`)) return
  try {
    await apiFetch(`/api/admin/notification-templates/${t.id}`, { method: 'DELETE' })
    await fetchTemplates()
    toast.add({ title: 'Message supprimé', color: 'success' })
  } catch (e) {
    toast.add({ title: 'Erreur', description: e?.message, color: 'error' })
  }
}

// ─── Utils ───
function formatDate(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' })
}

onMounted(() => {
  fetchProviders()
  fetchTemplates()
  fetchLogs()
})
</script>

<style scoped>
.form-input {
  background: rgba(255,255,255,0.05);
  border: 1px solid rgba(255,255,255,0.1);
  color: #E8E9ED;
  border-radius: 6px;
  padding: 8px 10px;
  font-size: 13px;
  width: 100%;
}
</style>
