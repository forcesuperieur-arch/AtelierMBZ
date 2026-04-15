<template>
  <div v-if="loading" style="padding:40px;text-align:center;color:#9CA3AF;">Chargement…</div>
  <div v-else-if="!ordre" style="padding:40px;text-align:center;color:#9CA3AF;">Ordre introuvable</div>
  <div v-else>
    <!-- Header -->
    <div class="page-header" style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
      <NuxtLink to="/ordres" style="color:#9CA3AF;text-decoration:none;font-size:20px;">←</NuxtLink>
      <div class="page-title">OR {{ ordre.numero_or }}</div>
      <StatusBadge :status="statusKey" />
      <span v-if="ordre.type_or" style="font-size:12px;padding:4px 10px;border-radius:6px;background:rgba(255,210,0,0.12);color:#FFD200;">{{ ordre.type_or }}</span>
      <div style="flex:1;" />
      <button v-if="canDownloadPdf" class="btn btn-ghost" @click="downloadPdf" style="font-size:13px;">📄 Télécharger PDF</button>
    </div>

    <!-- Workflow Timeline -->
    <UCard style="margin-bottom:20px;">
      <div style="display:flex;align-items:center;gap:0;justify-content:center;padding:12px 0;">
        <template v-for="(step, i) in workflowSteps" :key="step.key">
          <div style="display:flex;flex-direction:column;align-items:center;min-width:100px;">
            <div :style="{
              width:'36px',height:'36px',borderRadius:'50%',display:'flex',alignItems:'center',justifyContent:'center',fontSize:'16px',
              background: step.done ? 'var(--orange)' : step.active ? 'rgba(255,210,0,0.2)' : 'var(--dark3)',
              color: step.done ? '#090B10' : step.active ? '#FFD200' : '#6B7280',
              border: step.active ? '2px solid var(--orange)' : '2px solid transparent',
              fontWeight: '700'
            }">{{ step.done ? '✓' : step.icon }}</div>
            <div :style="{ fontSize:'11px', marginTop:'6px', color: step.done ? '#FFD200' : step.active ? '#E8E9ED' : '#6B7280', fontWeight: step.active ? '600' : '400' }">{{ step.label }}</div>
          </div>
          <div v-if="i < workflowSteps.length - 1" :style="{ height:'2px', flex:'1', minWidth:'30px', background: workflowSteps[i+1].done || workflowSteps[i+1].active ? 'var(--orange)' : 'rgba(255,255,255,0.08)' }" />
        </template>
      </div>
    </UCard>

    <!-- Info Cards Row -->
    <div class="detail-summary-grid">
      <!-- Client -->
      <UCard>
        <template #header><span style="font-size:13px;font-weight:600;color:#9CA3AF;">👤 Client</span></template>
        <div style="font-size:14px;line-height:1.8;">
          <div><strong>{{ clientNom }}</strong></div>
          <div v-if="client?.telephone">📞 {{ client.telephone }}</div>
          <div v-if="client?.email">✉️ {{ client.email }}</div>
        </div>
      </UCard>

      <!-- Véhicule -->
      <UCard>
        <template #header><span style="font-size:13px;font-weight:600;color:#9CA3AF;">🏍 Véhicule</span></template>
        <div style="font-size:14px;line-height:1.8;">
          <div><strong>{{ vehiculeMarque }}</strong></div>
          <div v-if="vehicule?.plaque">Plaque : {{ vehicule.plaque }}</div>
          <div v-if="ordre.kilometrage">Km : {{ ordre.kilometrage?.toLocaleString() }}</div>
        </div>
      </UCard>

      <!-- RDV -->
      <UCard>
        <template #header><span style="font-size:13px;font-weight:600;color:#9CA3AF;">📅 Rendez-vous</span></template>
        <div style="font-size:14px;line-height:1.8;">
          <div v-if="rdv?.date_rdv">{{ formatDate(rdv.date_rdv) }}</div>
          <div v-if="rdv?.heure_rdv">{{ rdv.heure_rdv }}</div>
          <div v-if="rdv?.mecanicien">Méca : {{ rdv.mecanicien.prenom }} {{ rdv.mecanicien.nom }}</div>
          <div v-if="rdv?.pont">Pont : {{ rdv.pont.nom }}</div>
        </div>
      </UCard>
    </div>

    <!-- Travaux -->
    <UCard v-if="ordre.travaux" style="margin-bottom:20px;">
      <template #header><span style="font-size:13px;font-weight:600;color:#9CA3AF;">🔧 Travaux prévus</span></template>
      <div style="font-size:14px;white-space:pre-wrap;color:#E8E9ED;">{{ ordre.travaux }}</div>
    </UCard>

    <!-- Travaux supplémentaires -->
    <UCard style="margin-bottom:20px;">
      <template #header>
        <div style="display:flex;align-items:center;justify-content:space-between;">
          <span style="font-size:13px;font-weight:600;color:#9CA3AF;">⚠️ Travaux supplémentaires ({{ travauxSupp.length }})</span>
          <button class="btn btn-ghost" style="font-size:12px;" @click="showTravauxSuppForm = !showTravauxSuppForm">{{ showTravauxSuppForm ? 'Annuler' : '+ Demander' }}</button>
        </div>
      </template>

      <!-- Create form -->
      <div v-if="showTravauxSuppForm" style="border:1px solid rgba(255,210,0,0.2);border-radius:10px;padding:14px;margin-bottom:14px;background:rgba(255,210,0,0.04);display:grid;gap:10px;">
        <div class="form-group">
          <label class="form-label">Description des travaux</label>
          <textarea v-model="newTravauxSupp.description" class="form-input" rows="2" placeholder="Décrire les travaux supplémentaires nécessaires…"></textarea>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;">
          <div class="form-group">
            <label class="form-label">Urgence</label>
            <select v-model="newTravauxSupp.urgence" class="form-input">
              <option value="normal">Normal</option>
              <option value="urgent">Urgent</option>
              <option value="critique">Critique</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Temps estimé (min)</label>
            <input v-model.number="newTravauxSupp.tempsEstime" type="number" class="form-input" placeholder="60" />
          </div>
          <div class="form-group">
            <label class="form-label">Prix estimé (€)</label>
            <input v-model.number="newTravauxSupp.prixEstime" type="number" step="0.01" class="form-input" placeholder="0.00" />
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Notes réceptionniste</label>
          <input v-model="newTravauxSupp.notesReceptionniste" class="form-input" placeholder="Optionnel" />
        </div>
        <div style="display:flex;justify-content:flex-end;gap:8px;">
          <button class="btn btn-ghost" @click="showTravauxSuppForm = false">Annuler</button>
          <button class="btn btn-primary" @click="submitTravauxSupp" :disabled="savingTravauxSupp || !newTravauxSupp.description?.trim()">
            {{ savingTravauxSupp ? 'Envoi…' : '📨 Soumettre la demande' }}
          </button>
        </div>
      </div>

      <!-- List -->
      <div v-if="!travauxSupp.length && !showTravauxSuppForm" style="padding:16px;text-align:center;color:#6B7280;">Aucune demande de travaux supplémentaires</div>
      <div v-for="ts in travauxSupp" :key="ts.id" style="padding:12px;border:1px solid rgba(255,255,255,0.06);border-radius:10px;margin-bottom:8px;">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
          <div style="flex:1;">
            <div style="font-size:13px;font-weight:600;color:#E8E9ED;">{{ ts.description }}</div>
            <div style="display:flex;gap:12px;margin-top:6px;font-size:12px;color:#9CA3AF;flex-wrap:wrap;">
              <span v-if="ts.urgence" :style="{ color: ts.urgence === 'critique' ? '#EF4444' : ts.urgence === 'urgent' ? '#FFD200' : '#9CA3AF' }">{{ ts.urgence }}</span>
              <span v-if="ts.temps_estime || ts.tempsEstime">⏱ {{ ts.temps_estime || ts.tempsEstime }} min</span>
              <span v-if="ts.prix_estime || ts.prixEstime">💰 {{ formatCurrency(Number(ts.prix_estime || ts.prixEstime)) }}</span>
            </div>
            <div v-if="ts.notes_receptionniste || ts.notesReceptionniste" style="font-size:12px;color:#6B7280;margin-top:4px;">📝 {{ ts.notes_receptionniste || ts.notesReceptionniste }}</div>
          </div>
          <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;">
            <span :style="{ fontSize:'11px', padding:'3px 10px', borderRadius:'999px', fontWeight:'700', background: tsStatusColor(ts.statut).bg, color: tsStatusColor(ts.statut).text }">{{ tsStatusLabel(ts.statut) }}</span>
            <div v-if="ts.statut === 'en_attente'" style="display:flex;gap:4px;">
              <button class="btn btn-primary" style="font-size:11px;padding:4px 10px;" @click="decideTravauxSupp(ts.id, 'approuve')">✅ Approuver</button>
              <button class="btn btn-ghost" style="font-size:11px;padding:4px 10px;color:#EF4444;" @click="decideTravauxSupp(ts.id, 'refuse')">❌ Refuser</button>
            </div>
          </div>
        </div>
      </div>
    </UCard>

    <!-- Rapport mécanicien -->
    <UCard v-if="etatVehicule?.mechanic_notes || Object.keys(etatVehicule?.mechanic_checkup || {}).length" style="margin-bottom:20px;">
      <template #header><span style="font-size:13px;font-weight:600;color:#9CA3AF;">🧰 Rapport mécanicien</span></template>
      <div style="display:grid;gap:12px;font-size:13px;">
        <div v-if="Object.keys(etatVehicule?.mechanic_checkup || {}).length">
          <span style="color:#9CA3AF;">Checkup atelier :</span>
          <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:6px;">
            <span v-for="(value, key) in etatVehicule.mechanic_checkup" :key="key" :style="{ background: value === 'ok' ? 'rgba(16,185,129,0.12)' : 'rgba(239,68,68,0.12)', color: value === 'ok' ? '#10B981' : '#EF4444', padding: '4px 10px', borderRadius: '999px', fontSize: '11px', fontWeight: '700' }">
              {{ value === 'ok' ? '✅' : '❌' }} {{ key }}
            </span>
          </div>
        </div>
        <div v-if="etatVehicule?.mechanic_notes">
          <span style="color:#9CA3AF;">Notes intervention :</span>
          <div style="margin-top:4px;color:#E8E9ED;white-space:pre-wrap;">{{ etatVehicule.mechanic_notes }}</div>
        </div>
      </div>
    </UCard>

    <!-- État véhicule -->
    <UCard v-if="etatVehicule || editingInspection" style="margin-bottom:20px;">
      <template #header>
        <div style="display:flex;align-items:center;justify-content:space-between;">
          <span style="font-size:13px;font-weight:600;color:#9CA3AF;">📋 État du véhicule</span>
          <button v-if="!editingInspection" class="btn btn-ghost" style="font-size:12px;" @click="startEditInspection">✏️ Modifier</button>
          <div v-else style="display:flex;gap:6px;">
            <button class="btn btn-ghost" style="font-size:12px;" @click="editingInspection = false">Annuler</button>
            <button class="btn btn-primary" style="font-size:12px;" @click="saveInspection" :disabled="savingInspection">{{ savingInspection ? 'Sauvegarde…' : '💾 Sauvegarder' }}</button>
          </div>
        </div>
      </template>

      <!-- Read-only mode -->
      <div v-if="!editingInspection" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;font-size:13px;">
        <div v-if="etatVehicule.priority">
          <span style="color:#9CA3AF;">Priorité :</span>
          <span :style="{ color: etatVehicule.priority === 'critique' ? '#EF4444' : etatVehicule.priority === 'urgent' ? '#FFD200' : '#E8E9ED', fontWeight:'600', marginLeft:'6px' }">{{ etatVehicule.priority }}</span>
        </div>
        <div v-if="etatVehicule.fuel_level != null">
          <span style="color:#9CA3AF;">Carburant :</span>
          <span style="margin-left:6px;">
            <span v-for="i in 4" :key="i" :style="{ display:'inline-block',width:'18px',height:'10px',borderRadius:'3px',border:'1px solid #4B5563',marginRight:'2px', background: i <= etatVehicule.fuel_level ? '#F59E0B' : 'transparent' }"></span>
            <span style="font-size:11px;color:#6B7280;margin-left:4px;">{{ etatVehicule.fuel_level }}/4</span>
          </span>
        </div>
        <!-- Inspection points -->
        <div v-if="etatVehicule.points?.length" style="grid-column:1/-1;">
          <span style="color:#9CA3AF;">Contrôle réception :</span>
          <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:6px;">
            <span v-for="p in etatVehicule.points" :key="p" style="background:rgba(249,115,22,0.1);color:#FB923C;padding:4px 10px;border-radius:999px;font-size:11px;font-weight:700;">✓ {{ p }}</span>
          </div>
        </div>
        <div v-if="etatVehicule.observations" style="grid-column:1/-1;">
          <span style="color:#9CA3AF;">Observations :</span>
          <div style="margin-top:4px;color:#E8E9ED;white-space:pre-wrap;">{{ etatVehicule.observations }}</div>
        </div>
        <div v-if="etatVehicule.body_damages?.length" style="grid-column:1/-1;">
          <span style="color:#9CA3AF;">Dommages constatés :</span>
          <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:6px;">
            <span v-for="d in etatVehicule.body_damages" :key="d" style="background:rgba(239,68,68,0.15);color:#EF4444;padding:3px 10px;border-radius:6px;font-size:12px;">{{ damageLabels[d] || d }}</span>
          </div>
        </div>
      </div>

      <!-- Edit mode -->
      <div v-else style="display:grid;gap:16px;font-size:13px;">
        <!-- Priority -->
        <div>
          <label style="color:#9CA3AF;font-size:12px;display:block;margin-bottom:4px;">Priorité</label>
          <select v-model="inspForm.priority" class="form-input" style="width:200px;">
            <option value="basse">Basse</option>
            <option value="standard">Standard</option>
            <option value="urgent">Urgent</option>
            <option value="critique">Critique</option>
          </select>
        </div>
        <!-- Fuel gauge -->
        <div>
          <label style="color:#9CA3AF;font-size:12px;display:block;margin-bottom:4px;">Niveau carburant</label>
          <div style="display:flex;align-items:center;gap:4px;">
            <span v-for="i in 4" :key="i" @click="inspForm.fuel_level = i" :style="{ display:'inline-block',width:'28px',height:'16px',borderRadius:'4px',border:'2px solid #4B5563',cursor:'pointer', background: i <= inspForm.fuel_level ? '#F59E0B' : 'transparent', transition: 'background 0.2s' }"></span>
            <span style="font-size:12px;color:#6B7280;margin-left:6px;">{{ inspForm.fuel_level }}/4</span>
          </div>
        </div>
        <!-- Inspection 12 points -->
        <div>
          <label style="color:#9CA3AF;font-size:12px;display:block;margin-bottom:6px;">Contrôle réception</label>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;">
            <label v-for="pt in INSPECTION_POINTS" :key="pt.key" style="display:flex;align-items:center;gap:8px;padding:6px 10px;border-radius:6px;border:1px solid rgba(255,255,255,0.06);cursor:pointer;" :style="{ background: inspForm.points.includes(pt.key) ? 'rgba(249,115,22,0.08)' : 'transparent' }">
              <input type="checkbox" :checked="inspForm.points.includes(pt.key)" @change="toggleInspPoint(pt.key)" style="accent-color:#F59E0B;" />
              <span style="color:#D1D5DB;">{{ pt.label }}</span>
            </label>
          </div>
        </div>
        <!-- Body damages -->
        <div>
          <label style="color:#9CA3AF;font-size:12px;display:block;margin-bottom:6px;">Dommages carrosserie</label>
          <div style="display:flex;flex-wrap:wrap;gap:6px;">
            <span v-for="(label, key) in damageLabels" :key="key" @click="toggleDamage(key)" style="padding:5px 10px;border-radius:999px;font-size:11px;font-weight:700;cursor:pointer;transition:all 0.2s;" :style="{ background: inspForm.body_damages.includes(key) ? 'rgba(239,68,68,0.2)' : 'rgba(255,255,255,0.04)', color: inspForm.body_damages.includes(key) ? '#EF4444' : '#6B7280', border: inspForm.body_damages.includes(key) ? '1px solid rgba(239,68,68,0.3)' : '1px solid rgba(255,255,255,0.08)' }">{{ label }}</span>
          </div>
        </div>
        <!-- Observations -->
        <div>
          <label style="color:#9CA3AF;font-size:12px;display:block;margin-bottom:4px;">Observations</label>
          <textarea v-model="inspForm.observations" class="form-input" rows="3" placeholder="Bruits, fuites ou comportements anormaux…"></textarea>
        </div>
      </div>
    </UCard>

    <!-- Estimation / Lignes -->
    <UCard style="margin-bottom:20px;">
      <template #header>
        <div style="display:flex;align-items:center;justify-content:space-between;">
          <span style="font-size:13px;font-weight:600;color:#9CA3AF;">💰 Estimation</span>
          <div style="display:flex;gap:6px;">
            <button v-if="!editingEstimate" class="btn btn-ghost" style="font-size:12px;" @click="startEditEstimate">✏️ Modifier</button>
            <template v-else>
              <button class="btn btn-ghost" style="font-size:12px;" @click="editingEstimate = false">Annuler</button>
              <button class="btn btn-primary" style="font-size:12px;" @click="saveEstimate" :disabled="savingEstimate">💾 Sauvegarder</button>
            </template>
          </div>
        </div>
      </template>
      <!-- Read-only -->
      <div v-if="!editingEstimate">
        <div v-if="!etatVehicule?.estimate_rows?.length" style="padding:16px;text-align:center;color:#6B7280;">Aucune ligne d'estimation</div>
        <table v-else style="width:100%;font-size:13px;border-collapse:collapse;">
          <thead>
            <tr style="border-bottom:1px solid rgba(255,255,255,0.08);">
              <th style="text-align:left;padding:8px;color:#9CA3AF;">Désignation</th>
              <th style="text-align:center;padding:8px;color:#9CA3AF;">Qté</th>
              <th style="text-align:right;padding:8px;color:#9CA3AF;">Montant</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, i) in etatVehicule.estimate_rows" :key="i" style="border-bottom:1px solid rgba(255,255,255,0.04);">
              <td style="padding:8px;color:#E8E9ED;">{{ row.label }}</td>
              <td style="text-align:center;padding:8px;color:#E8E9ED;">{{ row.qty }}</td>
              <td style="text-align:right;padding:8px;color:#FFD200;font-weight:600;">{{ formatCurrency(row.amount) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr style="border-top:2px solid rgba(255,255,255,0.08);">
              <td colspan="2" style="padding:8px;font-weight:600;color:#E8E9ED;">Total</td>
              <td style="text-align:right;padding:8px;font-weight:700;color:#FFD200;font-size:15px;">{{ formatCurrency(estimateTotal) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
      <!-- Edit mode -->
      <div v-else>
        <div v-for="(row, i) in estRows" :key="i" style="display:grid;grid-template-columns:1fr 80px 120px 40px;gap:8px;align-items:center;margin-bottom:8px;">
          <input v-model="row.label" class="form-input" placeholder="Désignation" />
          <input v-model.number="row.qty" type="number" min="1" class="form-input" style="text-align:center;" />
          <input v-model.number="row.amount" type="number" step="0.01" class="form-input" style="text-align:right;" placeholder="€" />
          <button @click="estRows.splice(i, 1)" style="background:none;border:none;color:#EF4444;font-size:16px;cursor:pointer;">✕</button>
        </div>
        <button @click="estRows.push({ label: '', qty: 1, amount: 0 })" class="btn btn-ghost" style="font-size:12px;">+ Ajouter ligne</button>
        <div style="margin-top:12px;text-align:right;font-size:15px;font-weight:700;color:#FFD200;">
          Total : {{ formatCurrency(estRows.reduce((s, r) => s + (Number(r.amount) || 0) * (Number(r.qty) || 1), 0)) }}
        </div>
      </div>
    </UCard>

    <!-- Signatures -->
    <UCard style="margin-bottom:20px;">
      <template #header><span style="font-size:13px;font-weight:600;color:#9CA3AF;">✍️ Signatures</span></template>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
        <div style="border:2px solid rgba(255,255,255,0.06);border-radius:12px;padding:12px;text-align:center;">
          <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#6B7280;margin-bottom:8px;">Le client</div>
          <div v-if="clientSignature" style="min-height:80px;">
            <img :src="clientSignature" alt="Signature client" style="max-width:100%;max-height:80px;object-fit:contain;" />
          </div>
          <canvas v-else ref="sigClientCanvas" width="300" height="100" style="border:1px dashed rgba(255,255,255,0.1);border-radius:8px;cursor:crosshair;background:rgba(255,255,255,0.02);width:100%;height:100px;" @mousedown="startDrawing('client', $event)" @mousemove="draw('client', $event)" @mouseup="stopDrawing('client')" @mouseleave="stopDrawing('client')"></canvas>
          <div v-if="!clientSignature" style="display:flex;gap:6px;justify-content:center;margin-top:6px;">
            <button class="btn btn-ghost" style="font-size:11px;" @click="clearSig('client')">Effacer</button>
            <button class="btn btn-primary" style="font-size:11px;" @click="saveSig('client')">Valider</button>
          </div>
          <div style="font-size:10px;color:#6B7280;margin-top:4px;">Bon pour accord</div>
        </div>
        <div style="border:2px solid rgba(255,255,255,0.06);border-radius:12px;padding:12px;text-align:center;">
          <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#6B7280;margin-bottom:8px;">Atelier / Expert</div>
          <div v-if="atelierSignature" style="min-height:80px;">
            <img :src="atelierSignature" alt="Signature atelier" style="max-width:100%;max-height:80px;object-fit:contain;" />
          </div>
          <canvas v-else ref="sigAtelierCanvas" width="300" height="100" style="border:1px dashed rgba(255,255,255,0.1);border-radius:8px;cursor:crosshair;background:rgba(255,255,255,0.02);width:100%;height:100px;" @mousedown="startDrawing('atelier', $event)" @mousemove="draw('atelier', $event)" @mouseup="stopDrawing('atelier')" @mouseleave="stopDrawing('atelier')"></canvas>
          <div v-if="!atelierSignature" style="display:flex;gap:6px;justify-content:center;margin-top:6px;">
            <button class="btn btn-ghost" style="font-size:11px;" @click="clearSig('atelier')">Effacer</button>
            <button class="btn btn-primary" style="font-size:11px;" @click="saveSig('atelier')">Valider</button>
          </div>
          <div style="font-size:10px;color:#6B7280;margin-top:4px;">Validation atelier</div>
        </div>
      </div>
    </UCard>

    <!-- Actions -->
    <UCard style="margin-bottom:20px;">
      <template #header><span style="font-size:13px;font-weight:600;color:#9CA3AF;">⚡ Actions</span></template>
      <div style="display:flex;flex-wrap:wrap;gap:10px;">
        <button v-for="t in availableTransitions.filter((t) => facturationEnabled || t !== 'facturer')" :key="t" class="btn" :class="transitionClass(t)" @click="applyTransition(t)" :disabled="transitioning">
          {{ transitionLabel(t) }}
        </button>
        <button class="btn btn-ghost" @click="printOR" style="font-size:13px;">🖨️ Imprimer A4</button>
        <span v-if="!availableTransitions.length" style="color:#6B7280;font-size:13px;">Aucune action disponible</span>
      </div>
    </UCard>
  </div>
</template>

<script setup lang="ts">
const route = useRoute()
const api = useApi()
const toast = useToast()
const atelierStore = useAtelierStore()
const facturationEnabled = computed(() => atelierStore.isModuleEnabled('facturation'))

const loading = ref(true)
const ordre = ref<any>(null)
const rdv = ref<any>(null)
const availableTransitions = ref<string[]>([])
const transitioning = ref(false)

// Travaux supplémentaires
const travauxSupp = ref<any[]>([])
const showTravauxSuppForm = ref(false)
const savingTravauxSupp = ref(false)
const newTravauxSupp = reactive({ description: '', urgence: 'normal', tempsEstime: null as number | null, prixEstime: null as number | null, notesReceptionniste: '' })

function tsStatusLabel(s: string) {
  const m: Record<string, string> = { en_attente: 'En attente', approuve: 'Approuvé', refuse: 'Refusé', en_cours: 'En cours', termine: 'Terminé' }
  return m[s] || s
}
function tsStatusColor(s: string) {
  const m: Record<string, { bg: string; text: string }> = {
    en_attente: { bg: 'rgba(251,191,36,0.15)', text: '#FBBF24' },
    approuve: { bg: 'rgba(16,185,129,0.15)', text: '#10B981' },
    refuse: { bg: 'rgba(239,68,68,0.15)', text: '#EF4444' },
    en_cours: { bg: 'rgba(59,130,246,0.15)', text: '#3B82F6' },
    termine: { bg: 'rgba(16,185,129,0.15)', text: '#10B981' },
  }
  return m[s] || { bg: 'rgba(255,255,255,0.06)', text: '#9CA3AF' }
}

async function loadTravauxSupp() {
  if (!rdv.value?.id) return
  try {
    const data = await api.get(`/demande_travaux_supps?rendezVous=${rdv.value.id}`)
    travauxSupp.value = Array.isArray(data) ? data : data?.['hydra:member'] ?? data?.member ?? []
  } catch { travauxSupp.value = [] }
}

async function submitTravauxSupp() {
  if (!rdv.value?.id || !newTravauxSupp.description?.trim()) return
  savingTravauxSupp.value = true
  try {
    await api.post('/demande_travaux_supps', {
      rendezVous: `/api/rendez-vous/${rdv.value.id}`,
      description: newTravauxSupp.description,
      urgence: newTravauxSupp.urgence,
      tempsEstime: newTravauxSupp.tempsEstime || undefined,
      prixEstime: newTravauxSupp.prixEstime ? String(newTravauxSupp.prixEstime) : undefined,
      notesReceptionniste: newTravauxSupp.notesReceptionniste || undefined,
    })
    toast.add({ title: 'Demande créée', color: 'success' })
    showTravauxSuppForm.value = false
    newTravauxSupp.description = ''
    newTravauxSupp.urgence = 'normal'
    newTravauxSupp.tempsEstime = null
    newTravauxSupp.prixEstime = null
    newTravauxSupp.notesReceptionniste = ''
    await loadTravauxSupp()
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e.message, color: 'error' })
  } finally {
    savingTravauxSupp.value = false
  }
}

async function decideTravauxSupp(tsId: number, decision: 'approuve' | 'refuse') {
  try {
    await api.patch(`/demande_travaux_supps/${tsId}`, {
      statut: decision,
      decisionClient: decision,
    })
    toast.add({ title: decision === 'approuve' ? 'Approuvé' : 'Refusé', color: decision === 'approuve' ? 'success' : 'warning' })
    await loadTravauxSupp()
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e.message, color: 'error' })
  }
}

// Inspection editing
const editingInspection = ref(false)
const savingInspection = ref(false)
const INSPECTION_POINTS = [
  { key: 'carrosserie_ok', label: 'Carrosserie OK' },
  { key: 'rayures', label: 'Rayures visibles' },
  { key: 'bosses', label: 'Bosses / chocs' },
  { key: 'freins_ok', label: 'Freins (impression)' },
  { key: 'pneus_av_ok', label: 'Pneu avant OK' },
  { key: 'pneus_ar_ok', label: 'Pneu arrière OK' },
  { key: 'eclairage_ok', label: 'Éclairage fonctionne' },
  { key: 'retros_ok', label: 'Rétroviseurs OK' },
  { key: 'clignotants_ok', label: 'Clignotants OK' },
  { key: 'compteur_ok', label: 'Tableau de bord OK' },
  { key: 'fuite_visible', label: 'Fuite visible' },
  { key: 'accessoires', label: 'Accessoires notés' },
]
const damageLabels: Record<string, string> = {
  avant: 'Avant', reservoir: 'Réservoir', flanc_gauche: 'Flanc gauche', flanc_droit: 'Flanc droit',
  arriere: 'Arrière', roue_av: 'Roue AV', roue_ar: 'Roue AR', selle: 'Selle'
}
const inspForm = reactive({ priority: 'standard', fuel_level: 0, points: [] as string[], body_damages: [] as string[], observations: '' })

function startEditInspection() {
  const e = etatVehicule.value || {}
  inspForm.priority = e.priority || 'standard'
  inspForm.fuel_level = e.fuel_level ?? 0
  inspForm.points = Array.isArray(e.points) ? [...e.points] : (Array.isArray(e.pointKeys) ? [...e.pointKeys] : [])
  inspForm.body_damages = Array.isArray(e.body_damages) ? [...e.body_damages] : []
  inspForm.observations = e.observations || ''
  editingInspection.value = true
}

function toggleInspPoint(key: string) {
  const idx = inspForm.points.indexOf(key)
  if (idx >= 0) inspForm.points.splice(idx, 1)
  else inspForm.points.push(key)
}

function toggleDamage(key: string) {
  const idx = inspForm.body_damages.indexOf(key)
  if (idx >= 0) inspForm.body_damages.splice(idx, 1)
  else inspForm.body_damages.push(key)
}

async function saveInspection() {
  if (!ordre.value?.id) return
  savingInspection.value = true
  try {
    const current = etatVehicule.value || {}
    const newEtat = {
      ...current,
      priority: inspForm.priority,
      fuel_level: inspForm.fuel_level,
      points: inspForm.points,
      body_damages: inspForm.body_damages,
      observations: inspForm.observations,
    }
    await api.put(`/ordres-reparation/${ordre.value.id}`, { etat_vehicule: newEtat })
    toast.add({ title: 'Inspection sauvegardée', color: 'success' })
    editingInspection.value = false
    await loadData()
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e.message, color: 'error' })
  } finally {
    savingInspection.value = false
  }
}

// Estimate editing
const editingEstimate = ref(false)
const savingEstimate = ref(false)
const estRows = ref<Array<{ label: string; qty: number; amount: number }>>([])

function startEditEstimate() {
  const rows = etatVehicule.value?.estimate_rows || []
  estRows.value = rows.length ? rows.map((r: any) => ({ label: r.label || '', qty: r.qty || 1, amount: r.amount ?? 0 })) : [{ label: '', qty: 1, amount: 0 }]
  editingEstimate.value = true
}

async function saveEstimate() {
  if (!ordre.value?.id) return
  savingEstimate.value = true
  try {
    const current = etatVehicule.value || {}
    const newEtat = { ...current, estimate_rows: estRows.value.filter(r => r.label.trim()) }
    await api.put(`/ordres-reparation/${ordre.value.id}`, { etat_vehicule: newEtat })
    toast.add({ title: 'Estimation sauvegardée', color: 'success' })
    editingEstimate.value = false
    await loadData()
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e.message, color: 'error' })
  } finally {
    savingEstimate.value = false
  }
}

// Signature pads
const sigClientCanvas = ref<HTMLCanvasElement | null>(null)
const sigAtelierCanvas = ref<HTMLCanvasElement | null>(null)
const clientSignature = ref<string | null>(null)
const atelierSignature = ref<string | null>(null)
const sigDrawing = reactive({ client: false, atelier: false })

function getCtx(who: 'client' | 'atelier') {
  const canvas = who === 'client' ? sigClientCanvas.value : sigAtelierCanvas.value
  return canvas?.getContext('2d') ?? null
}

function startDrawing(who: 'client' | 'atelier', e: MouseEvent) {
  sigDrawing[who] = true
  const ctx = getCtx(who)
  if (!ctx) return
  const rect = (e.target as HTMLCanvasElement).getBoundingClientRect()
  ctx.beginPath()
  ctx.moveTo(e.clientX - rect.left, e.clientY - rect.top)
}

function draw(who: 'client' | 'atelier', e: MouseEvent) {
  if (!sigDrawing[who]) return
  const ctx = getCtx(who)
  if (!ctx) return
  const rect = (e.target as HTMLCanvasElement).getBoundingClientRect()
  ctx.lineWidth = 2
  ctx.lineCap = 'round'
  ctx.strokeStyle = '#E8E9ED'
  ctx.lineTo(e.clientX - rect.left, e.clientY - rect.top)
  ctx.stroke()
}

function stopDrawing(who: 'client' | 'atelier') {
  sigDrawing[who] = false
}

function clearSig(who: 'client' | 'atelier') {
  const canvas = who === 'client' ? sigClientCanvas.value : sigAtelierCanvas.value
  const ctx = canvas?.getContext('2d')
  if (ctx && canvas) ctx.clearRect(0, 0, canvas.width, canvas.height)
}

async function saveSig(who: 'client' | 'atelier') {
  const canvas = who === 'client' ? sigClientCanvas.value : sigAtelierCanvas.value
  if (!canvas || !ordre.value?.id) return
  const data = canvas.toDataURL('image/png')
  try {
    const field = who === 'client' ? 'signature_client' : 'signature_atelier'
    await api.put(`/ordres-reparation/${ordre.value.id}`, { [field]: data })
    if (who === 'client') clientSignature.value = data
    else atelierSignature.value = data
    toast.add({ title: 'Signature enregistrée', color: 'success' })
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e.message, color: 'error' })
  }
}

// Print A4
function printOR() {
  const w = window.open('', '_blank')
  if (!w) { toast.add({ title: 'Autorisez les pop-ups', color: 'error' }); return }
  const c = client.value || {}
  const v = vehicule.value || {}
  const ev = etatVehicule.value || {}
  const rows = ev.estimate_rows || []
  const total = estimateTotal.value
  const orNum = ordre.value?.numero_or || `OR-${ordre.value?.id}`
  const dateStr = rdv.value?.date_rdv || ''
  const rowsHtml = rows.map((r: any) => `<tr><td style="padding:10px;border-bottom:1px solid #e2e8f0">${r.label || '-'}</td><td style="padding:10px;text-align:center;border-bottom:1px solid #e2e8f0">${r.qty || 1}</td><td style="padding:10px;text-align:right;border-bottom:1px solid #e2e8f0;font-weight:700">${formatCurrency(r.amount)}</td></tr>`).join('')
  const pointsHtml = (ev.points || []).map((p: string) => `<span style="display:inline-block;padding:4px 10px;border-radius:999px;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;font-size:11px;font-weight:700;margin:2px">✓ ${p}</span>`).join('') || '<span style="color:#94a3b8">Réception à compléter</span>'
  const html = `<!DOCTYPE html><html><head><meta charset="UTF-8"><title>${orNum}</title><style>*{print-color-adjust:exact;-webkit-print-color-adjust:exact}body{margin:0;padding:24px;font-family:Inter,Arial,sans-serif;background:#f1f5f9}@page{size:A4;margin:10mm}@media print{body{background:#fff;padding:0}.no-print{display:none}}</style></head><body>
<div style="max-width:800px;margin:auto;background:#fff;border-radius:16px;overflow:hidden;border:1px solid #e2e8f0">
  <div style="background:#0f172a;color:#fff;padding:24px"><div style="display:flex;justify-content:space-between;align-items:flex-start"><div><div style="font-size:10px;letter-spacing:.2em;text-transform:uppercase;color:#94a3b8">Ordre de Réparation</div><div style="font-size:28px;font-weight:900;margin-top:4px">PRO <span style="color:#f97316">MOTO</span></div></div><div style="text-align:right"><div style="font-size:24px;font-weight:900">${orNum}</div><div style="font-size:12px;color:#cbd5e1;margin-top:4px">Date: ${dateStr}</div></div></div></div>
  <div style="padding:24px;display:grid;gap:20px">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
      <div style="border-left:4px solid #f97316;padding-left:12px"><div style="font-size:10px;font-weight:800;text-transform:uppercase;color:#0f172a;margin-bottom:8px">Client</div><div style="font-size:13px;line-height:1.8"><div><strong>${c.prenom || ''} ${c.nom || ''}</strong></div><div>${c.telephone || '-'}</div><div>${c.email || '-'}</div></div></div>
      <div style="border-left:4px solid #0f172a;padding-left:12px"><div style="font-size:10px;font-weight:800;text-transform:uppercase;color:#0f172a;margin-bottom:8px">Véhicule</div><div style="font-size:13px;line-height:1.8"><div><strong>${v.marque || ''} ${v.modele || ''}</strong></div><div>Immat: ${v.plaque || '-'}</div><div>Km: ${ordre.value?.kilometrage || '-'}</div></div></div>
    </div>
    <div><div style="font-size:10px;font-weight:800;text-transform:uppercase;color:#ea580c;border-bottom:1px solid #fed7aa;padding-bottom:8px;margin-bottom:10px">Contrôle réception</div><div style="display:flex;flex-wrap:wrap;gap:4px">${pointsHtml}</div></div>
    <table style="width:100%;border-collapse:collapse;border:2px solid #0f172a;border-radius:12px;overflow:hidden"><thead style="background:#0f172a;color:#fff"><tr><th style="padding:10px;text-align:left">Opération</th><th style="padding:10px;text-align:center;width:60px">Qté</th><th style="padding:10px;text-align:right;width:120px">Montant</th></tr></thead><tbody>${rowsHtml}<tr style="background:#0f172a;color:#fff"><td colspan="2" style="padding:10px;text-align:right;font-weight:800;text-transform:uppercase;font-size:11px">Total TTC</td><td style="padding:10px;text-align:right;font-size:16px;font-weight:900;color:#fb923c">${formatCurrency(total)}</td></tr></tbody></table>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px"><div style="border:2px solid #e2e8f0;border-radius:12px;padding:16px;text-align:center;min-height:100px"><div style="font-size:10px;font-weight:800;text-transform:uppercase;color:#94a3b8;margin-bottom:16px">Le client</div>${clientSignature.value ? `<img src="${clientSignature.value}" style="max-width:100%;max-height:72px">` : '<div style="height:72px"></div>'}<div style="font-size:10px;color:#94a3b8;margin-top:10px">Bon pour accord</div></div><div style="border:2px solid #e2e8f0;border-radius:12px;padding:16px;text-align:center;min-height:100px"><div style="font-size:10px;font-weight:800;text-transform:uppercase;color:#94a3b8;margin-bottom:16px">Atelier</div>${atelierSignature.value ? `<img src="${atelierSignature.value}" style="max-width:100%;max-height:72px">` : '<div style="height:72px"></div>'}<div style="font-size:10px;color:#94a3b8;margin-top:10px">Validation atelier</div></div></div>
  </div>
</div>
<div class="no-print" style="max-width:800px;margin:18px auto 0;display:flex;justify-content:center;gap:10px"><button onclick="window.print()" style="border:none;border-radius:12px;padding:12px 20px;background:#0f172a;color:#fff;font-weight:700;cursor:pointer">Imprimer</button><button onclick="window.close()" style="border:1px solid #cbd5e1;border-radius:12px;padding:12px 20px;background:#fff;color:#334155;font-weight:700;cursor:pointer">Fermer</button></div>
</body></html>`
  w.document.open()
  w.document.write(html)
  w.document.close()
  w.focus()
}

const client = computed(() => rdv.value?.client)
const vehicule = computed(() => rdv.value?.vehicule)
const clientNom = computed(() => client.value ? `${client.value.prenom ?? ''} ${client.value.nom ?? ''}`.trim() : '—')
const vehiculeMarque = computed(() => vehicule.value ? `${vehicule.value.marque ?? ''} ${vehicule.value.modele ?? ''}`.trim() : '—')

const etatVehicule = computed(() => {
  if (!ordre.value?.etat_vehicule) return null
  const raw = ordre.value.etat_vehicule
  if (typeof raw === 'string') { try { return JSON.parse(raw) } catch { return null } }
  return raw
})

const estimateTotal = computed(() => {
  if (!etatVehicule.value?.estimate_rows) return 0
  return etatVehicule.value.estimate_rows.reduce((s: number, r: any) => s + (Number(r.amount) || 0) * (Number(r.qty) || 1), 0)
})

const statusKey = computed(() => {
  const s = rdv.value?.statut ?? ''
  if (['facture', 'paye', 'cloture'].includes(s)) return 'termine'
  if (['annule', 'non_presente'].includes(s)) return 'annule'
  if (['termine', 'restitue'].includes(s)) return 'termine'
  return 'en_cours'
})

const canDownloadPdf = computed(() => !!ordre.value?.id)

const workflowSteps = computed(() => {
  const statut = rdv.value?.statut ?? ''
  const order = ['reception', 'diagnostic', 'en_cours', 'controle', 'termine']
  const statusMap: Record<string, number> = {
    reserve: -1, confirme: -1, reception: 0, diagnostic: 1, en_cours: 2, en_attente: 2, controle: 3, termine: 4, restitue: 4, facture: 5, paye: 5, cloture: 5
  }
  const current = statusMap[statut] ?? -1
  const icons = ['📥', '🔍', '🔧', '✅', '🚀']
  const labels = ['Réception', 'Diagnostic', 'Intervention', 'Contrôle QC', 'Livraison']
  return order.map((key, i) => ({
    key,
    icon: icons[i],
    label: labels[i],
    done: current > i,
    active: current === i,
  }))
})

function formatDate(d: string) {
  try { return new Date(d).toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) } catch { return d }
}

function formatCurrency(v: number) {
  return v.toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' })
}

function transitionLabel(t: string) {
  const map: Record<string, string> = {
    reception: '📥 Réceptionner',
    diagnostic: '🔍 Lancer diagnostic',
    demarrer: '🔧 Démarrer intervention',
    mettre_en_attente: '⏸ Mettre en attente',
    reprendre: '▶ Reprendre',
    terminer: '✅ Terminer',
    restituer: '🚀 Restituer',
    facturer: '💶 Facturer',
    annuler: '❌ Annuler',
  }
  return map[t] || t
}

function transitionClass(t: string) {
  if (['annuler'].includes(t)) return 'btn-ghost'
  if (['terminer', 'restituer', 'facturer'].includes(t)) return 'btn-primary'
  return 'btn-secondary'
}

async function applyTransition(transition: string) {
  const rdvId = rdv.value?.id
  if (!rdvId) return
  if (transition === 'facturer' && !facturationEnabled.value) {
    toast.add({ title: 'Facturation désactivée pour cet atelier', color: 'warning' })
    return
  }
  transitioning.value = true
  try {
    await api.post(`/rendez-vous/${rdvId}/transition/${transition}`, {})
    toast.add({ title: 'Transition appliquée', color: 'success' })
    await loadData()
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e?.message || 'Échec transition', color: 'error' })
  } finally {
    transitioning.value = false
  }
}

async function downloadPdf() {
  try {
    const rdvId = rdv.value?.id
    const orId = ordre.value?.id
    window.open(`/api/ordres-reparation/${orId}/pdf`, '_blank')
  } catch {
    toast.add({ title: 'Erreur PDF', color: 'error' })
  }
}

function extractEntityId(ref: any): number | null {
  if (!ref) return null
  const raw = typeof ref === 'string' ? ref : ref?.id ?? ref?.['@id'] ?? null
  const parsed = Number(String(raw ?? '').split('/').pop())
  return Number.isFinite(parsed) ? parsed : null
}

async function loadData() {
  const id = route.params.id
  try {
    const data = await api.get(`/ordres-reparation/${id}`)
    ordre.value = data
    // Load RDV
    const rdvRef = data?.rendez_vous ?? data?.rendezVous
    const rdvId = extractEntityId(rdvRef)
    if (rdvId) {
      rdv.value = await api.get(`/rendez-vous/${rdvId}`)
      // Load transitions
      try {
        const trans = await api.get(`/rendez-vous/${rdvId}/transitions`)
        availableTransitions.value = Array.isArray(trans) ? trans : trans?.transitions ?? []
      } catch { availableTransitions.value = [] }
      // Load travaux supplementaires
      await loadTravauxSupp()
    }
  } catch {
    ordre.value = null
  }
}

onMounted(async () => {
  await loadData()
  // Initialize signatures from OR data
  if (ordre.value?.signature_client) clientSignature.value = ordre.value.signature_client
  if (ordre.value?.signature_atelier) atelierSignature.value = ordre.value.signature_atelier
  loading.value = false
})
</script>
