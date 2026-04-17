<template>
  <UCard>
    <template #header>
      <div class="vo-card-head">
        <div>
          <div class="vo-card-title">Parcours PDA {{ partyRoleLabel }}</div>
          <div class="vo-card-subtitle">Lien tokenisé à flasher pour scanner les pièces, vérifier le véhicule et faire signer le client.</div>
        </div>
        <div class="vo-chip" :class="companion?.steps?.allComplete ? 'is-done' : 'is-pending'">
          {{ companion?.steps?.completedCount || 0 }}/{{ companion?.steps?.totalCount || 4 }} étapes
        </div>
      </div>
    </template>

    <div v-if="!companion?.publicPath" class="vo-info-box">
      <strong>Session PDA indisponible.</strong>
      <span>Recharge le dossier pour générer le lien public.</span>
    </div>

    <div v-else class="vo-companion-layout">
      <div class="vo-companion-left">
        <div class="vo-companion-linkbox">
          <label>Lien public PDA</label>
          <a :href="publicUrl" target="_blank" class="vo-companion-link">{{ publicUrl }}</a>
          <div class="vo-inline-actions split compact">
            <button type="button" class="vo-secondary-cta" @click="copyLink">Copier le lien</button>
            <a :href="publicUrl" target="_blank" class="vo-link-btn">Ouvrir le PDA</a>
          </div>
        </div>

        <div class="vo-generated-box">
          <div class="vo-generated-title">Documents générés automatiquement</div>
          <div class="vo-generated-subtitle">Les documents se pré-remplissent avec les infos du dossier et la signature PDA.</div>
          <div class="vo-generated-list">
            <a
              v-for="document in generatedDocuments"
              :key="document.type"
              :href="document.url"
              target="_blank"
              class="vo-generated-item"
            >
              <strong>{{ document.label }}</strong>
              <span>{{ document.description }}</span>
            </a>
          </div>
        </div>

        <div class="vo-step-grid">
          <div v-for="step in orderedSteps" :key="step.key" class="vo-step-card" :class="step.completed ? 'is-done' : 'is-pending'">
            <div class="vo-step-top">
              <strong>{{ step.label }}</strong>
              <span>{{ step.completed ? 'OK' : 'À faire' }}</span>
            </div>
            <div v-if="step.key === 'documents' && companion?.steps?.documents?.missing?.length" class="vo-step-meta">
              Restent: {{ companion.steps.documents.missing.map(documentLabel).join(', ') }}
            </div>
            <div v-else-if="step.key === 'signature' && companion?.signedAt" class="vo-step-meta">
              Signé le {{ formatDate(companion.signedAt) }}
            </div>
            <div v-else class="vo-step-meta">
              {{ step.completed ? 'Étape validée dans le parcours PDA.' : 'Étape encore ouverte côté vendeur.' }}
            </div>
          </div>
        </div>
      </div>

      <div class="vo-companion-right">
        <img v-if="qrCodeUrl" :src="qrCodeUrl" alt="QR Code PDA" class="vo-companion-qr">
        <div class="vo-qr-caption">Scanner depuis le PDA vendeur</div>
        <div class="vo-qr-meta" v-if="companion?.expiresAt">Expire le {{ formatDate(companion.expiresAt) }}</div>
      </div>
    </div>
  </UCard>
</template>

<script setup lang="ts">
const props = defineProps<{
  companion?: Record<string, any> | null
  generatedDocuments?: Array<{ type: string; label: string; url: string; description?: string }>
}>()

const toast = useToast()
const { buildQrCodeUrl, documentLabel, formatDate } = useVoHelpers()

const partyRoleLabel = computed(() => {
  const role = String(props.companion?.partyRole || '')
  if (!role) return 'client'
  return role
})

const publicUrl = computed(() => {
  const path = String(props.companion?.publicPath || '').trim()
  if (!path) return ''
  if (import.meta.client) {
    return new URL(path, window.location.origin).toString()
  }
  return path
})

const qrCodeUrl = computed(() => buildQrCodeUrl(publicUrl.value, 240))

const generatedDocuments = computed(() => props.generatedDocuments || props.companion?.generatedDocuments || [])

const orderedSteps = computed(() => {
  const steps = props.companion?.steps || {}

  return [
    { key: 'seller', label: steps.seller?.label || 'Vendeur', completed: !!steps.seller?.completed },
    { key: 'vehicle', label: steps.vehicle?.label || 'Vehicule', completed: !!steps.vehicle?.completed },
    { key: 'documents', label: steps.documents?.label || 'Documents', completed: !!steps.documents?.completed },
    { key: 'signature', label: steps.signature?.label || 'Signature', completed: !!steps.signature?.completed },
  ]
})

async function copyLink() {
  if (!publicUrl.value || !import.meta.client) return

  try {
    await navigator.clipboard.writeText(publicUrl.value)
    toast.add({ title: 'Lien copié', color: 'success' })
  } catch {
    toast.add({ title: 'Impossible de copier le lien', color: 'error' })
  }
}
</script>

<style scoped>
.vo-companion-layout {
  display: grid;
  grid-template-columns: minmax(0, 1.8fr) minmax(220px, 280px);
  gap: 18px;
  align-items: start;
}

.vo-companion-left {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.vo-companion-right {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 12px;
  border-radius: 18px;
  background: linear-gradient(180deg, rgba(255,255,255,0.06), rgba(255,255,255,0.02));
  border: 1px solid rgba(255,255,255,0.08);
}

.vo-companion-linkbox,
.vo-generated-box {
  padding: 14px;
  border-radius: 16px;
  background: rgba(255,255,255,0.03);
  border: 1px solid rgba(255,255,255,0.08);
}

.vo-companion-linkbox label,
.vo-generated-title {
  display: block;
  font-size: 12px;
  color: #9ca3af;
  margin-bottom: 8px;
}

.vo-companion-link {
  display: block;
  color: #f8fafc;
  word-break: break-all;
  font-size: 13px;
  text-decoration: none;
}

.vo-generated-subtitle {
  font-size: 12px;
  color: #9ca3af;
  margin-bottom: 10px;
}

.vo-generated-list {
  display: grid;
  gap: 10px;
}

.vo-generated-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 12px;
  border-radius: 14px;
  background: rgba(255,255,255,0.04);
  border: 1px solid rgba(255,255,255,0.08);
  color: #f8fafc;
  text-decoration: none;
}

.vo-generated-item span,
.vo-step-meta,
.vo-qr-caption,
.vo-qr-meta {
  font-size: 12px;
  color: #9ca3af;
}

.vo-step-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}

.vo-step-card {
  padding: 12px;
  border-radius: 14px;
  border: 1px solid rgba(255,255,255,0.08);
  background: rgba(255,255,255,0.03);
}

.vo-step-card.is-done {
  border-color: rgba(34,197,94,0.4);
  background: rgba(34,197,94,0.08);
}

.vo-step-card.is-pending {
  border-color: rgba(245,158,11,0.25);
  background: rgba(245,158,11,0.06);
}

.vo-step-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 8px;
  color: #f8fafc;
}

.vo-chip {
  padding: 6px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
}

.vo-chip.is-done {
  color: #14532d;
  background: rgba(134,239,172,0.9);
}

.vo-chip.is-pending {
  color: #713f12;
  background: rgba(253,224,71,0.9);
}

.vo-companion-qr {
  width: min(100%, 240px);
  border-radius: 18px;
  background: white;
  padding: 10px;
}

.compact {
  margin-top: 12px;
}

@media (max-width: 960px) {
  .vo-companion-layout {
    grid-template-columns: 1fr;
  }

  .vo-step-grid {
    grid-template-columns: 1fr;
  }
}
</style>