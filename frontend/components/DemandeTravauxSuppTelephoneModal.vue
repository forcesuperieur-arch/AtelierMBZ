<template>
  <AppModal :open="isOpen" size="md" @update:open="onOpenChange">
    <template #header>
      <span style="font-size:16px;font-weight:700;color:var(--content-1);">
        📞 Décision téléphonique — demande #{{ demande?.id }}
      </span>
    </template>

    <div v-if="demande" data-testid="modal-decision-telephone" style="display:flex;flex-direction:column;gap:14px;font-size:13px;color:var(--content-2);">
      <!-- Récapitulatif -->
      <div style="padding:12px;background:var(--overlay-soft);border-radius:8px;border:1px solid var(--border-2);">
        <div style="font-weight:600;color:var(--content-1);">{{ demande.client_nom || 'Client' }}</div>
        <div v-if="demande.vehicule_info || demande.vehicule_plaque" style="color:var(--content-3);font-size:12px;margin-top:2px;">
          {{ demande.vehicule_info }}<span v-if="demande.vehicule_plaque"> • {{ demande.vehicule_plaque }}</span>
        </div>
        <div style="margin-top:6px;color:var(--accent-content);font-size:16px;font-weight:800;">{{ formatEuro(demande.prix_estime) }}</div>
      </div>

      <!-- Avertissement demande non chiffrée -->
      <div
        v-if="!isChiffree"
        style="padding:10px 12px;border-radius:8px;background:var(--warning-soft);border:1px solid var(--warning);color:var(--warning-content);font-size:12px;"
      >
        ⚠️ Cette demande n'est pas chiffrée : un accord ne pourra pas être enregistré tant que les prestations n'ont pas été complétées.
      </div>

      <!-- Décision -->
      <div>
        <div style="font-weight:600;color:var(--content-1);margin-bottom:8px;">Décision du client au téléphone</div>
        <div style="display:flex;gap:16px;flex-wrap:wrap;">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:8px 14px;border-radius:8px;border:1px solid var(--border-2);" :style="decision === 'accepte' ? 'background:var(--success-soft);border-color:var(--success);' : ''">
            <input v-model="decision" type="radio" value="accepte" data-testid="radio-tel-accepte" />
            <span style="color:var(--success-content);font-weight:700;">✅ Accepté</span>
          </label>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:8px 14px;border-radius:8px;border:1px solid var(--border-2);" :style="decision === 'refuse' ? 'background:var(--error-soft);border-color:var(--error);' : ''">
            <input v-model="decision" type="radio" value="refuse" data-testid="radio-tel-refuse" />
            <span style="color:var(--error-content);font-weight:700;">❌ Refusé</span>
          </label>
        </div>
      </div>

      <!-- Canal d'envoi du lien (si accepté) -->
      <div v-if="decision === 'accepte'">
        <div style="font-weight:600;color:var(--content-1);margin-bottom:6px;">Envoi du lien de signature</div>
        <select
          v-model="canalEnvoi"
          data-testid="select-tel-canal"
          style="width:100%;background:rgba(0,0,0,0.2);border:1px solid var(--border-1);border-radius:6px;padding:8px 10px;color:var(--content-2);font-size:13px;"
        >
          <option value="email">📧 E-mail</option>
          <option value="sms">📱 SMS</option>
        </select>
        <div style="color:var(--content-3);font-size:11px;margin-top:6px;">
          Les travaux peuvent démarrer dès l'accord. Le client recevra un lien pour confirmer son accord en signant en ligne.
        </div>
      </div>

      <!-- Commentaire -->
      <div>
        <div style="font-weight:600;color:var(--content-1);margin-bottom:6px;">Commentaire (optionnel)</div>
        <textarea
          v-model="commentaire"
          rows="2"
          data-testid="input-tel-commentaire"
          style="width:100%;background:rgba(0,0,0,0.2);border:1px solid var(--border-1);border-radius:6px;padding:8px;color:var(--content-2);font-size:13px;resize:vertical;"
          placeholder="Ex. : accord donné par M. Dupont, rappeler avant toute pièce supplémentaire…"
        />
      </div>

      <!-- Erreur -->
      <div
        v-if="errorMessage"
        data-testid="erreur-decision-telephone"
        style="padding:10px 12px;border-radius:8px;background:var(--error-soft);border:1px solid var(--error);color:var(--error-content);font-size:12px;"
      >
        {{ errorMessage }}
      </div>
    </div>

    <template #footer>
      <div style="display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;">
        <button class="btn btn-ghost" :disabled="submitting" @click="close">Annuler</button>
        <button
          class="btn btn-primary"
          data-testid="btn-tel-confirmer"
          :disabled="!decision || submitting"
          @click="submit"
        >
          {{ submitting ? 'Enregistrement…' : 'Enregistrer la décision' }}
        </button>
      </div>
    </template>
  </AppModal>
</template>

<script setup lang="ts">
const { isOpen, demandeData: demande, close, notifyUpdated } = useDemandeTravauxSuppTelephoneModal()
const api = useApi()
const toast = useToast()

const decision = ref<'accepte' | 'refuse' | ''>('')
const canalEnvoi = ref<'email' | 'sms'>('email')
const commentaire = ref('')
const submitting = ref(false)
const errorMessage = ref('')

const isChiffree = computed(() => {
  if (!demande.value) return false
  const hasPrestations = (demande.value.prestations?.length ?? 0) > 0
  const prix = parseFloat(String(demande.value.prix_estime ?? 0)) || 0
  return hasPrestations || prix > 0
})

watch(isOpen, (open) => {
  if (open) {
    decision.value = ''
    canalEnvoi.value = 'email'
    commentaire.value = ''
    errorMessage.value = ''
  }
})

function onOpenChange(open: boolean) {
  if (!open) close()
}

function formatEuro(value?: string | number) {
  if (value === undefined || value === null) return '—'
  const n = typeof value === 'string' ? parseFloat(value) : value
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(n)
}

async function submit() {
  if (!demande.value || !decision.value || submitting.value) return

  const confirmMessage = decision.value === 'accepte'
    ? `Enregistrer l'accord téléphonique du client et lui envoyer le lien de signature par ${canalEnvoi.value === 'email' ? 'e-mail' : 'SMS'} ?`
    : 'Enregistrer le refus donné par le client au téléphone ?'
  if (!confirm(confirmMessage)) return

  submitting.value = true
  errorMessage.value = ''
  try {
    const body: Record<string, string> = { decision: decision.value }
    if (commentaire.value.trim()) body.commentaire = commentaire.value.trim()
    if (decision.value === 'accepte') body.canal_envoi = canalEnvoi.value

    const res = await api.post(`/demandes-travaux-supp/${demande.value.id}/decision-telephone`, body)

    if (decision.value === 'accepte') {
      if (res.envoye) {
        toast.add({
          title: 'Accord téléphonique enregistré',
          description: `Lien de signature envoyé par ${res.canal_envoi === 'sms' ? 'SMS' : 'e-mail'} à ${res.destinataire}`,
          color: 'success',
        })
      } else {
        toast.add({
          title: 'Accord enregistré, envoi du lien impossible',
          description: `${res.envoi_erreur || 'Erreur d\'envoi'} — le client pourra signer au comptoir.`,
          color: 'warning',
        })
      }
    } else {
      toast.add({ title: 'Refus enregistré', color: 'success' })
    }

    notifyUpdated(res)
    close()
  } catch (e: any) {
    errorMessage.value = e.data?.error || e.message || 'Erreur lors de l\'enregistrement'
  } finally {
    submitting.value = false
  }
}
</script>
