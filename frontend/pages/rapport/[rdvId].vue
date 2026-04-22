<template>
  <div>
    <div class="page-header">
      <div style="display:flex;align-items:center;gap:12px;">
        <NuxtLink :to="`/planning?openRdv=${rdvId}`" style="color:#6B7280;text-decoration:none;font-size:18px;">◀</NuxtLink>
        <div class="page-title">Rapport d'intervention — RDV #{{ rdvId }}</div>
      </div>
      <div style="display:flex;gap:8px;">
        <button v-if="rapport?.id" class="btn btn-ghost" @click="openPdf">📄 PDF</button>
        <button v-if="rapport?.isSignedByBoth" class="btn btn-ghost" style="color:#FCA5A5;" @click="askRectifier">♻️ Rectifier</button>
      </div>
    </div>

    <div v-if="loading" style="text-align:center;padding:32px;color:#9CA3AF;">Chargement…</div>

    <div v-else-if="!rapport" style="padding:24px;text-align:center;background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);border-radius:12px;color:#FCA5A5;">
      Aucun rapport trouvé. Le rapport est créé automatiquement lors de la transition « Terminer » du RDV.
    </div>

    <template v-else>
      <div v-if="rapport.isSignedByBoth" style="margin-bottom:16px;padding:12px 14px;background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.2);border-radius:10px;color:#6EE7B7;font-size:13px;">
        ✅ Rapport signé par le mécanicien et le client — figé.
        <span v-if="rapport.signedHash" style="font-family:monospace;font-size:11px;color:#9CA3AF;margin-left:8px;">hash: {{ rapport.signedHash.slice(0, 16) }}…</span>
      </div>
      <div v-else-if="rapport.signatureMecanicien" style="margin-bottom:16px;padding:12px 14px;background:rgba(251,191,36,0.08);border:1px solid rgba(251,191,36,0.2);border-radius:10px;color:#FCD34D;font-size:13px;">
        ⏳ Mécanicien a signé — en attente signature client.
      </div>

      <UCard style="margin-bottom:16px;">
        <template #header><div style="font-weight:700;color:#E8E9ED;">Travaux réalisés</div></template>
        <UFormField label="Description des travaux">
          <UTextarea v-model="form.travauxRealises" :rows="4" :disabled="readOnly" placeholder="Détail des travaux effectués…" />
        </UFormField>
        <UFormField label="Recommandations client" style="margin-top:12px;">
          <UTextarea v-model="form.recommandations" :rows="3" :disabled="readOnly" placeholder="Conseils, points à surveiller…" />
        </UFormField>
        <UFormField label="Garantie" style="margin-top:12px;">
          <UTextarea v-model="form.garantie" :rows="2" :disabled="readOnly" placeholder="Conditions de garantie…" />
        </UFormField>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-top:12px;">
          <UFormField label="Kilométrage restitution">
            <UInput v-model.number="form.kilometrageRestitution" type="number" :disabled="readOnly" />
          </UFormField>
          <UFormField label="Prochaine révision (km)">
            <UInput v-model.number="form.prochaineRevisionKm" type="number" :disabled="readOnly" />
          </UFormField>
          <UFormField label="Prochaine révision (date)">
            <UInput v-model="form.prochaineRevisionDate" type="date" :disabled="readOnly" />
          </UFormField>
        </div>
        <div style="margin-top:12px;">
          <div style="font-size:12px;color:#9CA3AF;margin-bottom:6px;">Alertes / points de vigilance</div>
          <div style="display:flex;flex-direction:column;gap:6px;">
            <div v-for="(a, i) in form.alertes" :key="i" style="display:flex;gap:8px;">
              <UInput v-model="form.alertes[i]" :disabled="readOnly" style="flex:1;" />
              <button v-if="!readOnly" class="btn btn-ghost" style="padding:4px 10px;" @click="form.alertes.splice(i, 1)">✖</button>
            </div>
            <button v-if="!readOnly" class="btn btn-ghost" style="align-self:flex-start;font-size:12px;" @click="form.alertes.push('')">+ Ajouter une alerte</button>
          </div>
        </div>
        <div v-if="!readOnly" style="display:flex;justify-content:flex-end;margin-top:16px;">
          <button class="btn btn-primary" :disabled="saving" @click="save">{{ saving ? 'Enregistrement…' : '💾 Enregistrer' }}</button>
        </div>
      </UCard>

      <!-- Essai routier -->
      <UCard style="margin-bottom:16px;">
        <template #header>
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <div style="font-weight:700;color:#E8E9ED;">Essai routier</div>
            <span v-if="essaiComplete" style="font-size:11px;padding:3px 10px;border-radius:999px;background:rgba(16,185,129,0.14);color:#6EE7B7;font-weight:700;">Complet</span>
            <span v-else style="font-size:11px;padding:3px 10px;border-radius:999px;background:rgba(251,191,36,0.14);color:#FCD34D;font-weight:700;">À compléter</span>
          </div>
        </template>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:12px;">
          <UFormField label="Km début"><UInput v-model.number="essai.kmDebut" type="number" :disabled="readOnly" /></UFormField>
          <UFormField label="Km fin"><UInput v-model.number="essai.kmFin" type="number" :disabled="readOnly" /></UFormField>
          <UFormField label="Distance (km)">
            <UInput :model-value="computedDistance" disabled />
          </UFormField>
          <UFormField label="Durée (min)"><UInput v-model.number="essai.dureeMinutes" type="number" :disabled="readOnly" /></UFormField>
        </div>

        <div style="margin-top:16px;">
          <div style="font-size:12px;color:#9CA3AF;margin-bottom:8px;">Points de contrôle (10)</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
            <div v-for="(pt, i) in essai.pointsControle" :key="i" style="padding:10px;border:1px solid rgba(255,255,255,0.06);border-radius:8px;background:rgba(255,255,255,0.02);">
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                <span style="font-size:12px;color:#E8E9ED;font-weight:600;">{{ pt.label }}</span>
                <div style="display:flex;gap:4px;">
                  <button type="button" class="btn" :class="pt.ok === true ? 'btn-primary' : 'btn-ghost'" style="font-size:10px;padding:2px 8px;" :disabled="readOnly" @click="pt.ok = true">✓</button>
                  <button type="button" class="btn" :class="pt.ok === false ? 'btn-primary' : 'btn-ghost'" style="font-size:10px;padding:2px 8px;color:#FCA5A5;" :disabled="readOnly" @click="pt.ok = false">✗</button>
                </div>
              </div>
              <UInput v-model="pt.commentaire" placeholder="Commentaire…" :disabled="readOnly" size="xs" />
            </div>
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px;">
          <UFormField label="Anomalies constatées">
            <UTextarea v-model="essai.anomalies" :rows="2" :disabled="readOnly" />
          </UFormField>
          <UFormField label="Actions correctives">
            <UTextarea v-model="essai.actionsCorrectives" :rows="2" :disabled="readOnly" />
          </UFormField>
        </div>

        <div v-if="!readOnly" style="display:flex;justify-content:flex-end;margin-top:16px;">
          <button class="btn btn-primary" :disabled="savingEssai" @click="saveEssai">{{ savingEssai ? 'Enregistrement…' : '💾 Enregistrer essai' }}</button>
        </div>
      </UCard>

      <!-- Signatures -->
      <UCard v-if="!rapport.isSignedByBoth">
        <template #header><div style="font-weight:700;color:#E8E9ED;">Signatures</div></template>
        <div v-if="!rapport.signatureMecanicien">
          <div style="font-size:13px;color:#E8E9ED;margin-bottom:8px;font-weight:700;">1. Signature mécanicien</div>
          <div style="border:1px solid rgba(255,255,255,0.08);border-radius:10px;background:#fff;overflow:hidden;">
            <canvas ref="sigMecaCanvas" width="600" height="160" style="width:100%;height:160px;display:block;touch-action:none;" />
          </div>
          <div style="display:flex;gap:8px;margin-top:10px;">
            <button class="btn btn-ghost" style="flex:1;" @click="clearSig('meca')">Effacer</button>
            <button class="btn btn-primary" style="flex:2;" :disabled="signing || !hasSignedMeca" @click="signMeca">{{ signing ? 'Signature…' : '✍️ Signer (mécanicien)' }}</button>
          </div>
        </div>
        <div v-else>
          <div style="font-size:13px;color:#E8E9ED;margin-bottom:8px;font-weight:700;">2. Signature client</div>
          <div style="border:1px solid rgba(255,255,255,0.08);border-radius:10px;background:#fff;overflow:hidden;">
            <canvas ref="sigClientCanvas" width="600" height="160" style="width:100%;height:160px;display:block;touch-action:none;" />
          </div>
          <div style="display:flex;gap:8px;margin-top:10px;">
            <button class="btn btn-ghost" style="flex:1;" @click="clearSig('client')">Effacer</button>
            <button class="btn btn-primary" style="flex:2;" :disabled="signing || !hasSignedClient" @click="signClient">{{ signing ? 'Signature…' : '✍️ Signer (client)' }}</button>
          </div>
        </div>
      </UCard>
    </template>

    <AppModal :open="showRectifier" @update:open="showRectifier = $event">
      <template #header><div style="font-weight:700;color:#FCA5A5;">Rectifier le rapport</div></template>
      <p style="font-size:13px;color:#D1D5DB;margin-bottom:12px;">Un nouveau rapport sera créé en version rectifiée. Le précédent reste archivé.</p>
      <UFormField label="Motif de rectification" required>
        <UInput v-model="rectifierMotif" placeholder="Ex: Erreur km restitution" />
      </UFormField>
      <template #footer>
        <div style="display:flex;gap:8px;justify-content:flex-end;">
          <button class="btn btn-ghost" @click="showRectifier = false">Annuler</button>
          <button class="btn btn-primary" :disabled="!rectifierMotif || rectifying" @click="doRectifier">{{ rectifying ? '…' : 'Rectifier' }}</button>
        </div>
      </template>
    </AppModal>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ title: "Rapport d'intervention" })

const route = useRoute()
const router = useRouter()
const api = useApi()
const toast = useToast()
const config = useRuntimeConfig()
const { openPdf: openPdfBlob } = usePdfDownload()

const rdvId = computed(() => Number(route.params.rdvId))
const loading = ref(true)
const saving = ref(false)
const savingEssai = ref(false)
const signing = ref(false)
const rectifying = ref(false)
const showRectifier = ref(false)
const rectifierMotif = ref('')
const rapport = ref<any>(null)

const form = reactive({
  travauxRealises: '',
  alertes: [] as string[],
  recommandations: '',
  garantie: '',
  kilometrageRestitution: null as number | null,
  prochaineRevisionKm: null as number | null,
  prochaineRevisionDate: '',
})

const essai = reactive<any>({
  kmDebut: null,
  kmFin: null,
  dureeMinutes: null,
  anomalies: '',
  actionsCorrectives: '',
  pointsControle: defaultPoints(),
})

const sigMecaCanvas = ref<HTMLCanvasElement | null>(null)
const sigClientCanvas = ref<HTMLCanvasElement | null>(null)
const hasSignedMeca = ref(false)
const hasSignedClient = ref(false)

const readOnly = computed(() => !!rapport.value?.isSignedByBoth || !!rapport.value?.signatureMecanicien)

const computedDistance = computed(() => {
  if (essai.kmDebut != null && essai.kmFin != null) return Math.max(0, essai.kmFin - essai.kmDebut)
  return ''
})

const essaiComplete = computed(() => {
  if (!essai.kmDebut || !essai.kmFin || !essai.dureeMinutes) return false
  return essai.pointsControle.every((p: any) => p.ok !== null && p.ok !== undefined)
})

function defaultPoints() {
  return [
    'Freinage', 'Direction', 'Suspension', 'Moteur/accélération', 'Boîte de vitesses',
    'Embrayage', 'Éclairage/clignotants', 'Bruits anormaux', 'Tableau de bord/voyants', 'Comportement général',
  ].map(label => ({ label, ok: null, commentaire: '' }))
}

async function load() {
  loading.value = true
  try {
    const r = await api.get(`/rdv/${rdvId.value}/rapport`)
    rapport.value = r
    form.travauxRealises = r.travauxRealises || ''
    form.alertes = Array.isArray(r.alertes) ? [...r.alertes] : []
    form.recommandations = r.recommandations || ''
    form.garantie = r.garantie || ''
    form.kilometrageRestitution = r.kilometrageRestitution ?? null
    form.prochaineRevisionKm = r.prochaineRevisionKm ?? null
    form.prochaineRevisionDate = r.prochaineRevisionDate || ''
    if (r.essaiRoutier) {
      essai.kmDebut = r.essaiRoutier.kmDebut
      essai.kmFin = r.essaiRoutier.kmFin
      essai.dureeMinutes = r.essaiRoutier.dureeMinutes
      essai.anomalies = r.essaiRoutier.anomalies || ''
      essai.actionsCorrectives = r.essaiRoutier.actionsCorrectives || ''
      if (Array.isArray(r.essaiRoutier.pointsControle) && r.essaiRoutier.pointsControle.length === 10) {
        essai.pointsControle = r.essaiRoutier.pointsControle.map((p: any) => ({ ...p }))
      }
    }
  } catch (e: any) {
    rapport.value = null
    if (!String(e?.message || '').includes('Aucun rapport')) {
      toast.add({ title: 'Erreur', description: e?.message, color: 'error' })
    }
  } finally {
    loading.value = false
  }
}

async function save() {
  if (!rapport.value) return
  saving.value = true
  try {
    await api.put(`/rapport/${rapport.value.id}`, {
      travauxRealises: form.travauxRealises,
      alertes: form.alertes.filter(a => a && a.trim()),
      recommandations: form.recommandations,
      garantie: form.garantie,
      kilometrageRestitution: form.kilometrageRestitution,
      prochaineRevisionKm: form.prochaineRevisionKm,
      prochaineRevisionDate: form.prochaineRevisionDate || undefined,
    })
    toast.add({ title: 'Rapport enregistré', color: 'success' })
    await load()
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e.message, color: 'error' })
  } finally {
    saving.value = false
  }
}

async function saveEssai() {
  if (!rapport.value) return
  savingEssai.value = true
  try {
    await api.post(`/rapport/${rapport.value.id}/essai`, {
      kmDebut: essai.kmDebut,
      kmFin: essai.kmFin,
      dureeMinutes: essai.dureeMinutes,
      pointsControle: essai.pointsControle,
      anomalies: essai.anomalies,
      actionsCorrectives: essai.actionsCorrectives,
    })
    toast.add({ title: 'Essai enregistré', color: 'success' })
    await load()
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e.message, color: 'error' })
  } finally {
    savingEssai.value = false
  }
}

function setupCanvas(canvas: HTMLCanvasElement, onDraw: () => void) {
  const ctx = canvas.getContext('2d')!
  ctx.fillStyle = '#fff'
  ctx.fillRect(0, 0, canvas.width, canvas.height)
  ctx.strokeStyle = '#111827'
  ctx.lineWidth = 2
  ctx.lineCap = 'round'
  let drawing = false
  const pos = (e: PointerEvent) => {
    const r = canvas.getBoundingClientRect()
    return { x: (e.clientX - r.left) * (canvas.width / r.width), y: (e.clientY - r.top) * (canvas.height / r.height) }
  }
  canvas.onpointerdown = (e) => { drawing = true; canvas.setPointerCapture(e.pointerId); const p = pos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y) }
  canvas.onpointermove = (e) => { if (!drawing) return; const p = pos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); onDraw() }
  canvas.onpointerup = () => { drawing = false }
  canvas.onpointerleave = () => { drawing = false }
}

function clearSig(which: 'meca' | 'client') {
  const canvas = which === 'meca' ? sigMecaCanvas.value : sigClientCanvas.value
  if (!canvas) return
  const ctx = canvas.getContext('2d')!
  ctx.fillStyle = '#fff'
  ctx.fillRect(0, 0, canvas.width, canvas.height)
  if (which === 'meca') hasSignedMeca.value = false; else hasSignedClient.value = false
}

async function signMeca() {
  if (!sigMecaCanvas.value || !rapport.value) return
  signing.value = true
  try {
    await api.post(`/rapport/${rapport.value.id}/sign-mecanicien`, { signature: sigMecaCanvas.value.toDataURL('image/png') })
    toast.add({ title: 'Signature mécanicien enregistrée', color: 'success' })
    await load()
    await nextTick()
    if (sigClientCanvas.value) setupCanvas(sigClientCanvas.value, () => hasSignedClient.value = true)
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e.message, color: 'error' })
  } finally {
    signing.value = false
  }
}

async function signClient() {
  if (!sigClientCanvas.value || !rapport.value) return
  signing.value = true
  try {
    await api.post(`/rapport/${rapport.value.id}/sign-client`, { signature: sigClientCanvas.value.toDataURL('image/png') })
    toast.add({ title: 'Rapport signé', color: 'success' })
    await load()
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e.message, color: 'error' })
  } finally {
    signing.value = false
  }
}

function openPdf() {
  if (!rapport.value) return
  openPdfBlob(`/rapport/${rapport.value.id}/pdf`)
}

function askRectifier() {
  rectifierMotif.value = ''
  showRectifier.value = true
}

async function doRectifier() {
  if (!rapport.value || !rectifierMotif.value) return
  rectifying.value = true
  try {
    const r = await api.post(`/rapport/${rapport.value.id}/rectifier`, { motif: rectifierMotif.value })
    toast.add({ title: 'Rapport rectifié', color: 'success' })
    showRectifier.value = false
    await load()
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e.message, color: 'error' })
  } finally {
    rectifying.value = false
  }
}

watch(() => rapport.value?.signatureMecanicien, async (sig) => {
  await nextTick()
  if (!sig && sigMecaCanvas.value) setupCanvas(sigMecaCanvas.value, () => hasSignedMeca.value = true)
  else if (sig && !rapport.value?.signatureClient && sigClientCanvas.value) setupCanvas(sigClientCanvas.value, () => hasSignedClient.value = true)
}, { immediate: false })

onMounted(async () => {
  await load()
  await nextTick()
  if (rapport.value && !rapport.value.signatureMecanicien && sigMecaCanvas.value) {
    setupCanvas(sigMecaCanvas.value, () => hasSignedMeca.value = true)
  } else if (rapport.value && rapport.value.signatureMecanicien && !rapport.value.signatureClient && sigClientCanvas.value) {
    setupCanvas(sigClientCanvas.value, () => hasSignedClient.value = true)
  }
})
</script>
