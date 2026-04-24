<template>
  <div class="pb-24">
    <!-- Header de page amélioré -->
    <div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;margin-bottom:20px;">
      <div style="display:flex;align-items:center;gap:14px;">
        <div class="avatar-circle" style="width:56px;height:56px;font-size:18px;font-weight:700;">{{ initials }}</div>
        <div>
          <div style="font-size:18px;font-weight:700;color:#F9FAFB;">Espace Mécanicien</div>
          <div style="font-size:13px;color:#6B7280;">{{ todayLabel }}</div>
        </div>
      </div>
      <div style="display:flex;gap:8px;align-items:center;">
        <NuxtLink to="/profile" style="display:flex;align-items:center;justify-content:center;width:44px;height:44px;border-radius:10px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);color:var(--text-muted);" title="Mon profil">
          <UIcon name="i-heroicons-user-circle" class="w-5 h-5" />
        </NuxtLink>
      </div>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-3 w-full">
        <div class="stat-card flex-1 card-sm" style="min-width:70px;">
          <div class="text-xs-subtle mb-1">EN COURS</div>
          <div style="font-size:22px;font-weight:700;color:#F59E0B;">{{ kpis.enCours }}</div>
        </div>
        <div class="stat-card flex-1 card-sm" style="min-width:70px;">
          <div class="text-xs-subtle mb-1">À FAIRE</div>
          <div style="font-size:22px;font-weight:700;color:#E8E9ED;">{{ kpis.aFaire }}</div>
        </div>
        <div class="stat-card flex-1 card-sm" style="min-width:70px;">
          <div class="text-xs-subtle mb-1">TERMINÉS</div>
          <div style="font-size:22px;font-weight:700;color:#10B981;">{{ kpis.termines }}</div>
        </div>
        <div class="stat-card flex-1 card-sm" style="min-width:70px;">
          <div class="text-xs-subtle mb-1">JOURNÉE</div>
          <div style="font-size:22px;font-weight:700;color:#FFD200;">{{ kpis.pctDone }}%</div>
        </div>
      </div>
    </div>

    <!-- Skeleton state -->
    <div v-if="loading" class="space-y-4">
      <AppSkeletonCard :lines="3" />
      <AppSkeletonCard :lines="5" />
    </div>

    <div v-else>
      <!-- Priority card -->
      <div v-if="priorityAction" style="margin-bottom:20px;padding:14px;border-radius:12px;background:rgba(255,210,0,0.06);border:1px solid rgba(255,210,0,0.15);">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
          <span style="font-size:14px;">⚡</span>
          <span style="font-size:13px;font-weight:600;color:#FFD200;">Prochaine action</span>
        </div>
        <p style="font-size:13px;color:#D1D5DB;">{{ priorityAction }}</p>
      </div>

      <!-- Active intervention -->
      <UCard v-if="activeRdv" style="margin-bottom:24px;border-color:rgba(245,158,11,0.3);">
        <template #header>
          <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
            <span style="font-size:16px;font-weight:700;color:#F59E0B;min-height:44px;display:flex;align-items:center;">🔧 {{ activeRdvStatusTitle }}</span>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;min-height:44px;">
              <NuxtLink v-if="activeOrId" :to="`/ordres/${activeOrId}`" style="font-size:13px;color:#FFD200;text-decoration:none;font-weight:600;padding:12px 16px;background:rgba(255,210,0,0.1);border-radius:8px;">📋 Dossier atelier</NuxtLink>
              <span class="badge-pill" :style="activeRdvStatusStyle">{{ activeRdvStatusLabel }}</span>
              <span class="badge-pill" :style="{ background: essaiRoutierValide ? 'rgba(16,185,129,0.12)' : 'rgba(245,158,11,0.12)', color: essaiRoutierValide ? '#6EE7B7' : '#FCD34D' }">{{ essaiStatusLabel }}</span>
            </div>
          </div>
        </template>

        <!-- Navigation interne sticky -->
        <MecanicienNav v-if="activeRdv" v-model="activeMecaSection" :sections="mecaNavSections" />

        <!-- Section Intervention -->
        <MecanicienSection title="Intervention" section-key="meca-intervention" icon="🔧" :badge="navBadgeIntervention" :default-open="true">
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;font-size:13px;">
            <div><span class="text-subtle">Client :</span> <span class="text-value">{{ activeRdv.client_nom }}</span></div>
            <div><span class="text-subtle">Véhicule :</span> <span class="text-value">{{ activeRdv.vehicule_info }}</span></div>
            <div><span class="text-subtle">Type :</span> <span class="text-value">{{ activeRdv.type_intervention }}</span></div>
            <div><span class="text-subtle">Pont :</span> <span class="text-value">{{ activeRdv.pont_nom }}</span></div>
          </div>
          <div v-if="activeRdv.commentaire_client || activeRdv.description_probleme || activeRdv.commentaire" style="margin-top:12px;font-size:13px;">
            <span class="text-subtle">Motif client :</span>
            <p class="text-value">{{ activeRdv.commentaire_client || activeRdv.description_probleme || activeRdv.commentaire }}</p>
          </div>

          <div v-if="activeRdv.status !== 'en_cours'" style="margin-top:12px;padding:10px 12px;border-radius:10px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);font-size:12px;color:#D1D5DB;">
            <strong style="display:block;margin-bottom:4px;color:#F9FAFB;">{{ activeRdvStatusLabel }}</strong>
            <span>{{ activeRdvStatusHint }}</span>
          </div>

          <div v-if="receptionPoints.length || receptionObservations || receptionFuelLevel || receptionPriority || activeRdv.vehicule_plaque || activeRdv.km_reception !== null" style="margin-top:14px;padding:12px;border-radius:10px;background:rgba(59,130,246,0.05);border:1px solid rgba(59,130,246,0.18);">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;margin-bottom:8px;">
              <span style="font-size:13px;font-weight:600;color:#BFDBFE;">📥 Contexte réception</span>
              <span style="font-size:11px;padding:3px 8px;border-radius:999px;" :style="{ background: activeRdv.or_signe ? 'rgba(16,185,129,0.12)' : 'rgba(239,68,68,0.12)', color: activeRdv.or_signe ? '#6EE7B7' : '#FCA5A5' }">{{ activeRdv.or_signe ? 'OR signé' : 'OR à vérifier' }}</span>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:8px;font-size:12px;">
              <div v-if="activeRdv.vehicule_plaque"><span class="text-subtle">Plaque :</span> <span class="text-value">{{ activeRdv.vehicule_plaque }}</span></div>
              <div v-if="activeRdv.km_reception !== null"><span class="text-subtle">Km réception :</span> <span class="text-value">{{ activeRdv.km_reception }}</span></div>
              <div v-if="receptionPriority"><span class="text-subtle">Priorité :</span> <span class="text-value">{{ receptionPriority }}</span></div>
              <div v-if="receptionFuelLevel"><span class="text-subtle">Carburant :</span> <span class="text-value">{{ receptionFuelLevel }}</span></div>
            </div>
            <div v-if="receptionPoints.length" style="display:flex;flex-wrap:wrap;gap:6px;margin-top:10px;">
              <span v-for="(point, idx) in receptionPoints" :key="`${idx}-${point}`" class="badge-pill text-value">{{ point }}</span>
            </div>
            <div v-if="receptionObservations" style="margin-top:10px;font-size:12px;color:#D1D5DB;">
              <span class="text-subtle">Observations :</span> {{ receptionObservations }}
            </div>
          </div>

          <!-- Live Chrono visuel -->
          <MecanicienChrono
            v-if="activeRdv.temps_estime && activeRdv.status === 'en_cours'"
            :pct="chronoPct"
            :display-time="chronoDisplayTime"
            label="Temps écoulé"
            :elapsed-label="formatDuration(elapsedSeconds)"
            :total-label="formatDuration(plannedSeconds)"
            :overtime-label="formatDuration(overtimeSeconds)"
          />
        </MecanicienSection>

        <!-- Section Check-up -->
        <MecanicienSection title="Check-up" section-key="meca-checkup" icon="🔍" :badge="navBadgeCheckup">
          <div class="text-xs-subtle mb-3">Le rapport est enregistré dans le dossier atelier. {{ checkupDone }}/{{ checkupItems.length }} vérifiés</div>
          <MecanicienCheckupGrid
            :checkup="checkupForm"
            :items="checkupItems"
            :photos="{}"
            @toggle="toggleCheckupItem"
          />
          <UButton label="💾 Sauvegarder le Checkup" color="info" variant="outline" size="md" @click="persistWorkshopReport()" :loading="persistingCheckup" class="w-full justify-center mt-3 min-h-12 font-semibold" />
        </MecanicienSection>

        <!-- Section Essai -->
        <MecanicienSection title="Essai" section-key="meca-essai" icon="🏍" :badge="navBadgeEssai">
          <div class="flex-between-wrap flex-wrap-gap mb-2">
            <span class="text-xs-subtle">{{ essaiFilledCount }}/{{ essaiPoints.length }} points renseignés</span>
          </div>
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:8px;margin-bottom:10px;">
            <div>
              <label class="text-xs-subtle block-mb-2">Km départ</label>
              <input v-model.number="essaiForm.kmDebut" type="number" class="form-input" />
            </div>
            <div>
              <label class="text-xs-subtle block-mb-2">Km retour</label>
              <input v-model.number="essaiForm.kmFin" type="number" class="form-input" />
            </div>
            <div>
              <label class="text-xs-subtle block-mb-2">Durée (min)</label>
              <input v-model.number="essaiForm.dureeMinutes" type="number" class="form-input" />
            </div>
          </div>
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:6px;">
            <button
              v-for="pt in essaiPoints" :key="`active-${pt.key}`"
              type="button"
              class="btn btn-outline flex-center-gap min-h-11"
              :style="{
                background: essaiForm.pointsControle[pt.key] === 'ok' ? 'rgba(16,185,129,0.08)' : essaiForm.pointsControle[pt.key] === 'nok' ? 'rgba(239,68,68,0.08)' : 'rgba(255,255,255,0.05)',
                border: essaiForm.pointsControle[pt.key] === 'ok' ? '1px solid rgba(16,185,129,0.25)' : essaiForm.pointsControle[pt.key] === 'nok' ? '1px solid rgba(239,68,68,0.25)' : '1px solid rgba(255,255,255,0.1)',
                color: essaiForm.pointsControle[pt.key] === 'ok' ? '#6EE7B7' : essaiForm.pointsControle[pt.key] === 'nok' ? '#FCA5A5' : '#9CA3AF',
              }"
              @click="cycleEssaiPoint(pt.key)"
            >
              <span class="text-xl">{{ essaiForm.pointsControle[pt.key] === 'ok' ? '✅' : essaiForm.pointsControle[pt.key] === 'nok' ? '❌' : '⬜' }}</span>
              {{ pt.label }}
            </button>
          </div>
          <div v-if="essaiHasNok" style="margin-top:12px;">
            <label style="font-size:13px;color:#E8E9ED;font-weight:600;display:block;margin-bottom:6px;">Actions correctives</label>
            <textarea v-model="essaiForm.actionsCorrectives" class="form-input w-full" rows="3" placeholder="Décrire les corrections effectuées…" />
          </div>
          <div style="display:flex;justify-content:space-between;gap:8px;align-items:center;flex-wrap:wrap;margin-top:16px;">
            <span style="font-size:12px;color:#9CA3AF;line-height:1.4;">Minimum requis : km départ/retour, durée et 5 points renseignés.</span>
          </div>
        </MecanicienSection>

        <!-- Section Travaux -->
        <MecanicienSection title="Travaux" section-key="meca-travaux" icon="🔨" :badge="navBadgeTravaux">
          <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
            <span style="font-size:15px;font-weight:600;color:#E8E9ED;">Travaux complémentaires</span>
            <UButton :label="showSupplementaryForm ? 'Fermer' : 'Créer une demande'" color="neutral" variant="outline" size="sm" @click="showSupplementaryForm = !showSupplementaryForm" class="min-h-11" />
          </div>
          <div class="text-xs-subtle mb-2">Le mécanicien décrit le besoin et la priorité. Le chiffrage reste côté réception.</div>
          <div v-if="showSupplementaryForm" style="display:grid;gap:10px;padding:12px;border-radius:10px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);">
            <textarea v-model="supplementaryForm.description" class="form-input" rows="3" placeholder="Décrire le problème constaté, le risque et la recommandation technique…" />
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:8px;align-items:end;">
              <label class="flex-col-gap-sm">
                <span class="text-xs-subtle">Priorité</span>
                <select v-model="supplementaryForm.urgence" class="form-input">
                  <option v-for="option in supplementaryUrgencyOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
              </label>
              <div style="font-size:11px;color:#9CA3AF;">{{ activeProblemPhotoIds.length ? `${activeProblemPhotoIds.length} photo(s) problème jointes automatiquement.` : 'Ajoute des photos de type problème pour documenter la demande.' }}</div>
            </div>
            <div class="flex-end-gap mt-3">
              <UButton label="Envoyer à la réception" color="primary" variant="solid" size="md" @click="createSupplementaryRequest" :loading="creatingSupplementary" class="min-h-11" />
            </div>
          </div>
        </MecanicienSection>

        <!-- Section Photos -->
        <MecanicienSection title="Photos" section-key="meca-photos" icon="📷" :badge="navBadgePhotos">
          <div class="flex-between-wrap flex-wrap-gap mb-2">
            <span class="header-md">Photos d'intervention</span>
            <span class="text-xs-subtle">Après travaux: {{ afterWorkPhotosCount }}/2 · Restitution: {{ restitutionPhotosCount }}/3</span>
          </div>
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px;margin-bottom:10px;">
            <label class="flex-col-gap-sm">
              <span class="text-xs-subtle">Type</span>
              <select v-model="photoUploadType" class="form-input">
                <option v-for="type in mecanicienPhotoTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
              </select>
            </label>
            <label class="flex-col-gap-sm">
              <span class="text-xs-subtle">Description</span>
              <input v-model="photoDescription" class="form-input" placeholder="Ex: fuite observée" />
            </label>
          </div>
          <input id="meca-photo-input" ref="photoFileInput" type="file" accept="image/*" capture="environment" class="hidden" @change="handlePhotoUpload" />
          <label for="meca-photo-input" style="display:flex;align-items:center;justify-content:center;gap:8px;padding:16px;border-radius:12px;border:1px dashed rgba(255,210,0,0.4);background:rgba(255,210,0,0.06);font-size:15px;font-weight:700;color:#FDE68A;cursor:pointer;min-height:56px;">
            <span>{{ uploadingPhoto ? 'Upload en cours…' : '📷 Ajouter des photos (Caméra)' }}</span>
          </label>
          <div v-if="mechanicPhotoGroups.length" style="display:flex;flex-direction:column;gap:12px;margin-top:12px;">
            <div v-for="group in mechanicPhotoGroups" :key="group.type">
              <div style="font-size:11px;font-weight:700;color:#9CA3AF;margin-bottom:6px;text-transform:uppercase;letter-spacing:0.04em;">{{ photoTypeLabel(group.type) }} · {{ group.photos.length }}</div>
              <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(96px,1fr));gap:8px;">
                <a
                  v-for="photo in group.photos"
                  :key="photo.id"
                  :href="photoUrl(photo)"
                  target="_blank"
                  rel="noreferrer"
                  style="display:block;padding:6px;border-radius:10px;border:1px solid rgba(255,255,255,0.08);background:rgba(255,255,255,0.03);text-decoration:none;"
                >
                  <img :src="photoUrl(photo)" :alt="photo.description || 'Photo intervention'" class="w-full rounded-lg block" style="height:88px;object-fit:cover;" />
                  <div class="mt-1 text-xs-muted leading-snug">{{ photo.description || 'Sans description' }}</div>
                </a>
              </div>
            </div>
          </div>
          <div v-else style="margin-top:10px;font-size:12px;color:#6B7280;">Aucune photo d'intervention pour ce RDV.</div>
        </MecanicienSection>

        <!-- Section Notes -->
        <MecanicienSection title="Notes" section-key="meca-notes" icon="📝" :badge="navBadgeNotes">
          <label style="font-size:13px;font-weight:600;color:#E8E9ED;margin-bottom:6px;display:block;">Notes intervention</label>
          <textarea v-model="interventionNotes" class="form-input" rows="2" placeholder="Notes techniques, observations…" />
          <div class="flex-end-gap mt-3">
            <UButton label="Sauvegarder" color="neutral" variant="outline" size="md" @click="saveInterventionNotes" :loading="savingNotes" class="min-h-11" />
          </div>
        </MecanicienSection>
      </UCard>

      <!-- Todo: RDVs to do -->
      <UCard style="margin-bottom:24px;">
        <template #header>
          <span style="font-size:15px;font-weight:700;color:#E8E9ED;">📋 À faire ({{ todoRdvs.length }})</span>
        </template>
        <div v-if="!todoRdvs.length" class="empty-state-sub">
          Toutes les interventions sont terminées 🎉
        </div>
        <div v-else class="flex-col-gap">
          <div v-for="rdv in todoRdvs" :key="rdv.id" style="display:flex;flex-direction:column;gap:12px;padding:16px;border-radius:12px;border:1px solid rgba(255,255,255,0.06);background:rgba(255,255,255,0.02);">
            <div>
              <p style="font-weight:700;color:#E8E9ED;font-size:15px;">{{ rdv.heure_debut?.slice(0, 5) }} — {{ rdv.client_nom }}</p>
              <p style="font-size:14px;color:#9CA3AF;margin-top:4px;">{{ rdv.vehicule_info }} — {{ rdv.type_intervention }}</p>
              <p v-if="rdv.temps_estime" style="font-size:13px;color:#D1D5DB;margin-top:6px;">⏱ {{ formatDuration(rdv.temps_estime) }}</p>
            </div>
            <div style="display:flex;align-items:center;gap:12px;margin-top:6px;">
              <StatusBadge :status="rdv.status" />
              <UButton v-if="rdv.status === 'reception'" size="md" label="🔧 Démarrer" color="primary" @click="startWork(rdv.id)" class="min-h-11 flex-1 justify-center text-sm font-semibold" />
            </div>
          </div>
        </div>
      </UCard>

      <!-- Done: Completed RDVs -->
      <UCard v-if="doneRdvs.length">
        <template #header>
          <span style="font-size:15px;font-weight:700;color:#10B981;">✅ Terminés ({{ doneRdvs.length }})</span>
        </template>
        <div class="flex-col-gap">
          <div v-for="rdv in doneRdvs" :key="rdv.id" style="display:flex;flex-direction:column;padding:16px;border-radius:12px;border:1px solid rgba(255,255,255,0.04);font-size:14px;background:rgba(255,255,255,0.01);" :style="{ opacity: rdv.status === 'termine' ? 1 : 0.7 }">
            <div style="display:flex;align-items:flex-start;gap:8px;">
              <span style="color:#10B981;font-size:16px;">✅</span>
              <div>
                <div style="color:#E8E9ED;font-weight:600;margin-bottom:4px;">{{ rdv.heure_debut?.slice(0, 5) }} — {{ rdv.client_nom }}</div>
                <div style="color:#9CA3AF;font-size:13px;">{{ rdv.type_intervention }}</div>
                <div v-if="rdv.status === 'termine' && !rdv.rapport_mecanicien_signe" style="display:inline-block;margin-top:8px;padding:4px 8px;border-radius:6px;background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.2);font-size:12px;color:#F59E0B;font-weight:600;">Rapport à signer</div>
              </div>
            </div>
            <div style="display:flex;gap:12px;align-items:center;margin-top:16px;">
              <UButton v-if="rdv.status === 'termine'" size="md" :label="rdv.rapport_mecanicien_signe ? '📋 Rapport signé' : '📋 Rapport'" :color="rdv.rapport_mecanicien_signe ? 'success' : 'warning'" variant="outline" @click="openRapport(rdv.id)" class="min-h-11 flex-1 justify-center font-semibold" />
              <NuxtLink :to="`/planning?openRdv=${rdv.id}`" style="color:#000;background:#FFD200;font-size:13px;text-decoration:none;font-weight:700;padding:12px;border-radius:8px;text-align:center;min-height:44px;display:flex;align-items:center;justify-content:center;flex:1;">Détails (Planning)</NuxtLink>
            </div>
          </div>
        </div>
      </UCard>
    </div>

    <!-- Rapport d'intervention panel -->
    <div v-if="rapportRdvId" class="modal-overlay" @click.self="closeRapport">
      <div class="modal-card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
          <h2 style="font-size:17px;font-weight:700;color:#E8E9ED;">📋 Rapport d'intervention</h2>
          <button @click="closeRapport" style="font-size:18px;color:#6B7280;background:none;border:none;cursor:pointer;">✕</button>
        </div>

        <div v-if="rapportLoading" class="empty-state">Chargement…</div>
        <div v-else-if="rapportError" class="bg-danger-soft text-danger rounded-lg">{{ rapportError }}</div>

        <div v-else-if="rapport">
          <div v-if="rapport.isSignedByBoth" class="text-center bg-success-soft rounded-lg mb-4">
            <div style="font-size:32px;margin-bottom:8px;">✅</div>
            <p style="color:#6EE7B7;font-weight:700;">Rapport signé par les deux parties</p>
            <a :href="`${apiBase.replace('/api','')}/api/rapport/${rapport.id}/pdf`" target="_blank" style="display:inline-block;margin-top:12px;padding:6px 14px;border-radius:8px;background:rgba(255,210,0,0.1);border:1px solid rgba(255,210,0,0.2);color:#FFD200;font-size:12px;font-weight:600;text-decoration:none;">📄 Télécharger PDF</a>
          </div>

          <div v-else style="display:flex;flex-direction:column;gap:16px;">
            <!-- Travaux réalisés -->
            <div>
              <label class="form-label block-mb-1">Travaux réalisés <span class="text-danger">*</span></label>
              <textarea v-model="rapportForm.travauxRealises" class="form-input" rows="4" placeholder="Décrire précisément les travaux effectués…" :disabled="!!rapport.signatureMecanicien" />
            </div>

            <!-- Alertes -->
            <div>
              <label class="form-label block-mb-1">Alertes importantes</label>
              <textarea v-model="rapportForm.alertes" class="form-input" rows="2" placeholder="Points à surveiller, anomalies, recommandations urgentes…" :disabled="!!rapport.signatureMecanicien" />
            </div>

            <!-- Recommandations -->
            <div>
              <label class="form-label block-mb-1">Recommandations prochaine visite <span class="text-danger">*</span></label>
              <textarea v-model="rapportForm.recommandations" class="form-input" rows="2" placeholder="Prochaine révision, pièces à prévoir…" :disabled="!!rapport.signatureMecanicien" />
            </div>

            <!-- Kilométrage restitution + prochaine révision -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
              <div>
                <label class="form-label block-mb-1">Km restitution</label>
                <input v-model.number="rapportForm.kilometrageRestitution" type="number" class="form-input" placeholder="ex: 24500" :disabled="!!rapport.signatureMecanicien" />
              </div>
              <div>
                <label class="form-label block-mb-1">Prochaine révision (km)</label>
                <input v-model.number="rapportForm.prochaineRevisionKm" type="number" class="form-input" placeholder="ex: 26500" :disabled="!!rapport.signatureMecanicien" />
              </div>
            </div>

            <!-- Essai routier -->
            <div class="panel-sm">
              <div style="font-size:13px;font-weight:600;color:#E8E9ED;margin-bottom:12px;">🏍 Essai routier</div>
              <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:12px;">
                <div>
                  <label class="text-xs-subtle block-mb-2">Km départ</label>
                  <input v-model.number="essaiForm.kmDebut" type="number" class="form-input" :disabled="!!rapport.signatureMecanicien" />
                </div>
                <div>
                  <label class="text-xs-subtle block-mb-2">Km retour</label>
                  <input v-model.number="essaiForm.kmFin" type="number" class="form-input" :disabled="!!rapport.signatureMecanicien" />
                </div>
                <div>
                  <label class="text-xs-subtle block-mb-2">Durée (min)</label>
                  <input v-model.number="essaiForm.dureeMinutes" type="number" class="form-input" :disabled="!!rapport.signatureMecanicien" />
                </div>
              </div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                <button
                  v-for="pt in essaiPoints" :key="pt.key"
                  type="button"
                  :disabled="!!rapport.signatureMecanicien"
                  class="btn btn-outline flex-center-gap min-h-11"
                  :style="{
                    background: essaiForm.pointsControle[pt.key] === 'ok' ? 'rgba(16,185,129,0.08)' : essaiForm.pointsControle[pt.key] === 'nok' ? 'rgba(239,68,68,0.08)' : 'rgba(255,255,255,0.05)',
                    border: essaiForm.pointsControle[pt.key] === 'ok' ? '1px solid rgba(16,185,129,0.25)' : essaiForm.pointsControle[pt.key] === 'nok' ? '1px solid rgba(239,68,68,0.25)' : '1px solid rgba(255,255,255,0.1)',
                    color: essaiForm.pointsControle[pt.key] === 'ok' ? '#6EE7B7' : essaiForm.pointsControle[pt.key] === 'nok' ? '#FCA5A5' : '#9CA3AF',
                  }"
                  @click="cycleEssaiPoint(pt.key)"
                >
                  <span class="text-xl">{{ essaiForm.pointsControle[pt.key] === 'ok' ? '✅' : essaiForm.pointsControle[pt.key] === 'nok' ? '❌' : '⬜' }}</span>
                  {{ pt.label }}
                </button>
              </div>
              <div v-if="essaiHasNok" class="mt-2">
                <label class="text-xs-subtle block-mb-2">Actions correctives</label>
                <textarea v-model="essaiForm.actionsCorrectives" class="form-input" rows="2" placeholder="Décrire les corrections effectuées…" :disabled="!!rapport.signatureMecanicien" />
              </div>
            </div>

            <!-- Save button (before signature) -->
            <UButton
              v-if="!rapport.signatureMecanicien"
              label="💾 Enregistrer le rapport"
              color="primary"
              variant="solid"
              size="md"
              @click="saveRapport"
              :loading="rapportSaving"
              class="w-full justify-center min-h-12 text-base font-semibold"
            />

            <!-- Signature mécanicien -->
            <div v-if="!rapport.signatureMecanicien" style="border-top:1px solid rgba(255,255,255,0.06);padding-top:16px;">
              <p style="font-size:13px;font-weight:600;color:#E8E9ED;margin-bottom:8px;">✍️ Signature mécanicien</p>
              <p style="font-size:12px;color:#9CA3AF;margin-bottom:10px;">En signant, vous certifiez que les travaux sont réalisés et l'essai routier effectué.</p>
              <canvas
                ref="sigRapportCanvas"
                width="580" height="180"
                @pointerdown="startRapportDraw" @pointermove="drawRapport" @pointerup="endRapportDraw" @pointerleave="endRapportDraw"
                class="sig-canvas"
              ></canvas>
              <div style="display:flex;gap:8px;margin-top:12px;">
                <UButton label="↺ Effacer" color="neutral" variant="outline" size="md" @click="clearRapportSig" style="flex:1;min-height:48px;" />
                <UButton label="✓ Signer le rapport" color="success" variant="solid" size="md" @click="signRapport" :loading="rapportSigning" :disabled="!rapportSigDrawn" style="flex:2;font-size:15px;font-weight:700;min-height:48px;" />
              </div>
              <div v-if="rapportSignError" class="mt-2 bg-danger-soft rounded text-md-danger">{{ rapportSignError }}</div>
            </div>

            <div v-else class="bg-success-soft rounded text-md-primary text-success">
              ✅ Rapport signé par le mécanicien — en attente de signature client lors de la restitution.
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Bottom action bar améliorée -->
    <div v-if="activeRdv && !rapportRdvId" class="sticky-action-bar">
      <!-- Desktop -->
      <div class="hidden md:flex w-full items-center gap-3">
        <div class="flex items-center gap-2 flex-1">
          <UButton v-if="activeRdv.status === 'en_cours'" label="⏸ Pause" color="neutral" variant="outline" size="md" @click="pauseCurrentWork" class="whitespace-nowrap flex-1 justify-center min-h-12" />
          <UButton v-if="['en_cours', 'en_pause'].includes(activeRdv.status)" label="📦 Pièces" color="warning" variant="outline" size="md" @click="waitForParts" class="whitespace-nowrap flex-1 justify-center min-h-12" />
          <UButton v-if="activeRdv.status === 'en_pause' || activeRdv.status === 'en_attente_pieces'" label="▶️ Reprendre" color="primary" variant="solid" size="md" @click="resumeCurrentWork" class="whitespace-nowrap flex-1 justify-center min-h-12" />
          <UButton v-if="activeRdv.status === 'en_cours' && !essaiRoutierValide" label="🏍 Valider essai" color="warning" variant="solid" size="md" @click="saveActiveRoadTest" :loading="savingRoadTest" :disabled="!canValidateRoadTest" class="whitespace-nowrap flex-1 justify-center min-h-12" />
          <UButton v-if="canFinishCurrentRdv" label="✅ Terminer" color="success" variant="solid" size="md" @click="finishWork" :loading="finishing" :disabled="!essaiRoutierValide" class="whitespace-nowrap flex-1 justify-center min-h-12" />
        </div>
        <div class="w-px h-10 bg-white/10 shrink-0" />
        <div class="flex items-center gap-2 shrink-0">
          <NuxtLink v-if="activeOrId" :to="`/ordres/${activeOrId}`" class="inline-flex items-center justify-center gap-1 px-4 py-2 rounded-lg text-sm font-semibold min-h-12" style="background:rgba(255,210,0,0.1);border:1px solid rgba(255,210,0,0.2);color:#FFD200;text-decoration:none;">📋 OR</NuxtLink>
        </div>
        <div class="w-px h-10 bg-white/10 shrink-0" />
        <div class="flex items-center gap-2 shrink-0">
          <UButton label="📷 Photos" color="neutral" variant="outline" size="md" @click="scrollToSection('photos')" class="min-h-12 whitespace-nowrap" />
          <UButton label="➕" color="primary" variant="solid" size="md" @click="triggerPhotoUpload" class="min-h-12 px-3" />
        </div>
      </div>

      <!-- Mobile drawer -->
      <div class="md:hidden w-full">
        <button
          type="button"
          class="w-full flex items-center justify-center gap-2 py-3 text-sm font-semibold rounded-lg border"
          style="background:rgba(255,255,255,0.05);border-color:rgba(255,255,255,0.08);color:#D1D5DB;min-height:48px;"
          @click="bottomDrawerOpen = !bottomDrawerOpen"
        >
          <span>Actions</span>
          <UIcon :name="bottomDrawerOpen ? 'i-heroicons-chevron-down' : 'i-heroicons-chevron-up'" class="w-4 h-4" />
        </button>
        <Transition
          enter-active-class="transition-all duration-200 ease-out"
          enter-from-class="max-h-0 opacity-0"
          enter-to-class="max-h-[600px] opacity-100"
          leave-active-class="transition-all duration-200 ease-in"
          leave-from-class="max-h-[600px] opacity-100"
          leave-to-class="max-h-0 opacity-0"
        >
          <div v-show="bottomDrawerOpen" class="overflow-hidden mt-2 space-y-3">
            <!-- Transitions -->
            <div class="space-y-2">
              <div class="text-xs font-bold uppercase tracking-wider" style="color:#6B7280;">Transitions</div>
              <div class="grid grid-cols-2 gap-2">
                <UButton v-if="activeRdv.status === 'en_cours'" label="⏸ Pause" color="neutral" variant="outline" size="md" @click="pauseCurrentWork; bottomDrawerOpen = false" class="w-full justify-center min-h-12" />
                <UButton v-if="['en_cours', 'en_pause'].includes(activeRdv.status)" label="📦 Pièces" color="warning" variant="outline" size="md" @click="waitForParts; bottomDrawerOpen = false" class="w-full justify-center min-h-12" />
                <UButton v-if="activeRdv.status === 'en_pause' || activeRdv.status === 'en_attente_pieces'" label="▶️ Reprendre" color="primary" variant="solid" size="md" @click="resumeCurrentWork; bottomDrawerOpen = false" class="w-full justify-center min-h-12" />
                <UButton v-if="activeRdv.status === 'en_cours' && !essaiRoutierValide" label="🏍 Valider essai" color="warning" variant="solid" size="md" @click="saveActiveRoadTest; bottomDrawerOpen = false" :loading="savingRoadTest" :disabled="!canValidateRoadTest" class="w-full justify-center min-h-12" />
                <UButton v-if="canFinishCurrentRdv" label="✅ Terminer" color="success" variant="solid" size="md" @click="finishWork; bottomDrawerOpen = false" :loading="finishing" :disabled="!essaiRoutierValide" class="w-full justify-center min-h-12 col-span-2" />
              </div>
            </div>
            <!-- Documents -->
            <div class="space-y-2">
              <div class="text-xs font-bold uppercase tracking-wider" style="color:#6B7280;">Documents</div>
              <div class="grid grid-cols-2 gap-2">
                <NuxtLink v-if="activeOrId" :to="`/ordres/${activeOrId}`" class="inline-flex items-center justify-center gap-1 px-4 py-3 rounded-lg text-sm font-semibold min-h-12" style="background:rgba(255,210,0,0.1);border:1px solid rgba(255,210,0,0.2);color:#FFD200;text-decoration:none;" @click="bottomDrawerOpen = false">📋 OR</NuxtLink>
              </div>
            </div>
            <!-- Photos -->
            <div class="space-y-2">
              <div class="text-xs font-bold uppercase tracking-wider" style="color:#6B7280;">Photos</div>
              <div class="grid grid-cols-2 gap-2">
                <UButton label="📷 Voir photos" color="neutral" variant="outline" size="md" @click="scrollToSection('photos'); bottomDrawerOpen = false" class="w-full justify-center min-h-12" />
                <UButton label="➕ Ajouter photo" color="primary" variant="solid" size="md" @click="triggerPhotoUpload(); bottomDrawerOpen = false" class="w-full justify-center min-h-12" />
              </div>
            </div>
          </div>
        </Transition>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
const api = useApi()
const { formatDuration } = useFormat()
const config = useRuntimeConfig()
const apiBase = config.public.apiBase as string
const rdvStore = useRdvStore()
const toast = useToast()
const auth = useAuth()
const loading = ref(true)
const finishing = ref(false)
const savingNotes = ref(false)
const persistingCheckup = ref(false)
const savingRoadTest = ref(false)
const uploadingPhoto = ref(false)
const myRdvs = ref<any[]>([])
const rdvPhotos = ref<any[]>([])
const interventionNotes = ref('')
const showSupplementaryForm = ref(false)
const creatingSupplementary = ref(false)
const photoUploadType = ref('apres_travaux')
const photoDescription = ref('')
const pendingFinishTransition = ref(false)
const now = ref(Date.now())
let chronoTimer: ReturnType<typeof setInterval> | null = null

// --- Phase 4 UX additions ---
const activeMecaSection = ref('intervention')
const bottomDrawerOpen = ref(false)
const photoFileInput = ref<HTMLInputElement | null>(null)

const chronoRunning = computed(() => activeRdv.value?.status === 'en_cours' && elapsedMin.value > 0)
const checkupOkCount = computed(() => Object.values(checkupForm).filter(v => v === 'ok').length)
const essaiDone = computed(() => essaiRoutierValide.value)
const travauxSupp = computed(() => [])
const photos = computed(() => rdvPhotos.value)

const navBadgeIntervention = computed(() => {
  if (!activeRdv.value) return ''
  if (['en_pause', 'en_attente_pieces'].includes(activeRdv.value.status)) return '⚠️'
  return ''
})
const navBadgeCheckup = computed(() => {
  if (checkupDone.value === checkupItems.length) return '✓'
  if (checkupDone.value > 0) return '⚠️'
  return ''
})
const navBadgeEssai = computed(() => {
  if (essaiRoutierValide.value) return '✓'
  if (essaiFilledCount.value > 0) return '⚠️'
  return ''
})
const navBadgeTravaux = computed(() => showSupplementaryForm.value ? '⚠️' : '')
const navBadgePhotos = computed(() => rdvPhotos.value.length > 0 ? '✓' : '')
const navBadgeNotes = computed(() => interventionNotes.value.trim() ? '✓' : '')

const mecaNavSections = computed(() => [
  { id: 'intervention', label: 'Intervention', badge: chronoRunning.value ? '✓' : undefined },
  { id: 'checkup', label: 'Check-up', badge: `${checkupOkCount.value}/10` },
  { id: 'essai', label: 'Essai', badge: essaiDone.value ? '✓' : undefined },
  { id: 'travaux', label: 'Travaux', badge: travauxSupp.value.length > 0 ? String(travauxSupp.value.length) : undefined },
  { id: 'photos', label: 'Photos', badge: photos.value.length > 0 ? String(photos.value.length) : undefined },
  { id: 'notes', label: 'Notes' },
])

function scrollToSection(id: string) {
  activeMecaSection.value = id
  const el = document.getElementById(`section-meca-${id}`) || document.getElementById(`section-${id}`)
  if (el) {
    el.scrollIntoView({ behavior: 'smooth', block: 'start' })
  }
}

function triggerPhotoUpload() {
  photoFileInput.value?.click()
}

// --- Rapport d'intervention ---
const rapportRdvId = ref<number | null>(null)
const rapport = ref<any>(null)
const rapportLoading = ref(false)
const rapportError = ref('')
const rapportSaving = ref(false)
const rapportSigning = ref(false)
const rapportSigDrawn = ref(false)
const rapportSignError = ref('')
const sigRapportCanvas = ref<HTMLCanvasElement | null>(null)
let sigRapportDrawing = false
let sigLastX = 0
let sigLastY = 0

const rapportForm = reactive({
  travauxRealises: '',
  alertes: '',
  recommandations: '',
  kilometrageRestitution: null as number | null,
  prochaineRevisionKm: null as number | null,
})

const essaiPoints = [
  { key: 'freinage_avant', label: 'Freinage avant' },
  { key: 'freinage_arriere', label: 'Freinage arrière' },
  { key: 'direction', label: 'Direction' },
  { key: 'suspension', label: 'Suspension' },
  { key: 'embrayage', label: 'Embrayage' },
  { key: 'boite_vitesses', label: 'Boîte de vitesses' },
  { key: 'eclairage', label: 'Éclairage' },
  { key: 'avertisseur', label: 'Avertisseur' },
  { key: 'compteur', label: 'Compteur' },
  { key: 'bruits_anormaux', label: 'Bruits anormaux' },
]

const mecanicienPhotoTypes = [
  { value: 'en_cours', label: 'En cours' },
  { value: 'apres_travaux', label: 'Après travaux' },
  { value: 'restitution', label: 'Restitution' },
  { value: 'probleme', label: 'Problème constaté' },
]

const supplementaryUrgencyOptions = [
  { value: 'normal', label: 'Normal' },
  { value: 'urgent', label: 'Urgent' },
  { value: 'critique', label: 'Critique' },
]

const supplementaryForm = reactive({
  description: '',
  urgence: 'normal',
})

const essaiForm = reactive({
  kmDebut: null as number | null,
  kmFin: null as number | null,
  dureeMinutes: null as number | null,
  pointsControle: {} as Record<string, string>,
  actionsCorrectives: '',
})

const essaiHasNok = computed(() => Object.values(essaiForm.pointsControle).some(v => v === 'nok'))
const essaiFilledCount = computed(() => Object.values(essaiForm.pointsControle).filter(v => v === 'ok' || v === 'nok').length)

function cycleEssaiPoint(key: string) {
  const cur = essaiForm.pointsControle[key]
  essaiForm.pointsControle[key] = !cur ? 'ok' : cur === 'ok' ? 'nok' : ''
}

function resetEssaiForm() {
  essaiForm.kmDebut = activeRdv.value?.km_reception ?? null
  essaiForm.kmFin = null
  essaiForm.dureeMinutes = null
  essaiForm.actionsCorrectives = ''
  Object.keys(essaiForm.pointsControle).forEach((key) => { delete essaiForm.pointsControle[key] })
}

function photoTypeLabel(type: string) {
  return mecanicienPhotoTypes.find((item) => item.value === type)?.label ?? type
}

function photoUrl(photo: any) {
  return photo?.url?.startsWith('http') ? photo.url : `${apiBase}${photo?.url || `/photos/file/${photo?.filename}`}`
}

async function loadRdvPhotos(rdvId?: number | null) {
  if (!rdvId) {
    rdvPhotos.value = []
    return
  }

  try {
    rdvPhotos.value = await api.get(`/photos/rdv/${rdvId}`)
  } catch {
    rdvPhotos.value = []
  }
}

async function handlePhotoUpload(event: Event) {
  const input = event.target as HTMLInputElement
  const files = Array.from(input.files || [])
  if (!files.length || !activeRdv.value?.id) return

  uploadingPhoto.value = true
  try {
    for (const file of files) {
      const formData = new FormData()
      formData.append('photo', file)
      formData.append('rendez_vous_id', String(activeRdv.value.id))
      formData.append('type', photoUploadType.value)
      if (photoDescription.value.trim()) {
        formData.append('description', photoDescription.value.trim())
      }
      await api.upload('/photos/upload', formData)
    }
    photoDescription.value = ''
    await loadRdvPhotos(activeRdv.value.id)
    toast.add({ title: 'Photos ajoutées', description: `${files.length} photo(s) enregistrée(s).`, color: 'success' })
  } catch (e: unknown) {
    toast.add({ title: 'Erreur photo', description: e instanceof Error ? e.message : 'Erreur inconnue', color: 'error' })
  } finally {
    uploadingPhoto.value = false
    input.value = ''
  }
}

function normalizeEssaiPoints(raw: any): Record<string, string> {
  if (!raw) return {}
  if (Array.isArray(raw)) {
    return raw.reduce((acc: Record<string, string>, item: any) => {
      const key = String(item?.key ?? item?.label ?? '')
        .trim()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '_')
      const status = item?.statut ?? (item?.ok === true ? 'ok' : item?.ok === false ? 'nok' : '')
      if (key && status) acc[key] = status
      return acc
    }, {})
  }
  if (typeof raw === 'object') {
    return Object.entries(raw).reduce((acc: Record<string, string>, [key, value]) => {
      if (value === 'ok' || value === 'nok') acc[key] = value
      return acc
    }, {})
  }
  return {}
}

function buildEssaiCheckpoints() {
  return essaiPoints.map((point) => ({
    key: point.key,
    label: point.label,
    statut: essaiForm.pointsControle[point.key] || null,
  }))
}

function fillRapportForm(r: any) {
  rapportForm.travauxRealises = r.travauxRealises ?? ''
  rapportForm.alertes = r.alertes ?? ''
  rapportForm.recommandations = r.recommandations ?? ''
  rapportForm.kilometrageRestitution = r.kilometrageRestitution ?? null
  rapportForm.prochaineRevisionKm = r.prochaineRevisionKm ?? null
  resetEssaiForm()
  if (r.essaiRoutier) {
    essaiForm.kmDebut = r.essaiRoutier.kmDebut ?? null
    essaiForm.kmFin = r.essaiRoutier.kmFin ?? null
    essaiForm.dureeMinutes = r.essaiRoutier.dureeMinutes ?? null
    Object.assign(essaiForm.pointsControle, normalizeEssaiPoints(r.essaiRoutier.pointsControle ?? r.essaiRoutier.checkpoints))
    essaiForm.actionsCorrectives = r.essaiRoutier.actionsCorrectives ?? ''
  }
}

async function openRapport(rdvId: number) {
  rapportRdvId.value = rdvId
  rapportLoading.value = true
  rapportError.value = ''
  rapport.value = null
  try {
    rapport.value = await api.get(`/rdv/${rdvId}/rapport`)
    fillRapportForm(rapport.value)
  } catch (e: unknown) {
    rapportError.value = (e instanceof Error ? e.message : 'Erreur inconnue') || 'Impossible de charger le rapport'
  } finally {
    rapportLoading.value = false
  }
}

function closeRapport() {
  rapportRdvId.value = null
  rapport.value = null
  rapportSignError.value = ''
  pendingFinishTransition.value = false
  clearRapportSig()
}

async function saveRapport() {
  if (!rapport.value) return
  rapportSaving.value = true
  try {
    await Promise.all([
      api.put(`/rapport/${rapport.value.id}`, {
        travauxRealises: rapportForm.travauxRealises,
        alertes: rapportForm.alertes,
        recommandations: rapportForm.recommandations,
        kilometrageRestitution: rapportForm.kilometrageRestitution,
        prochaineRevisionKm: rapportForm.prochaineRevisionKm,
      }),
      api.post(`/rapport/${rapport.value.id}/essai`, {
        kmDebut: essaiForm.kmDebut,
        kmFin: essaiForm.kmFin,
        dureeMinutes: essaiForm.dureeMinutes,
        checkpoints: buildEssaiCheckpoints(),
        actionsCorrectives: essaiForm.actionsCorrectives,
        valider: canValidateRoadTest.value,
      }),
    ])
    await fetchMyRdvs()
    toast.add({ title: 'Rapport enregistré', color: 'success' })
  } catch (e: unknown) {
    toast.add({ title: 'Erreur', description: e instanceof Error ? e.message : 'Erreur inconnue', color: 'error' })
  } finally {
    rapportSaving.value = false
  }
}

async function signRapport() {
  if (!rapport.value || !sigRapportCanvas.value || !rapportSigDrawn.value) return
  rapportSigning.value = true
  rapportSignError.value = ''
  try {
    await saveRapport()
    const sig = sigRapportCanvas.value.toDataURL('image/png')
    const updated = await api.post(`/rapport/${rapport.value.id}/sign-mecanicien`, { signature: sig })
    rapport.value = updated
    await fetchMyRdvs()
    if (pendingFinishTransition.value && updated?.rdv_id) {
      await completeFinishTransition(updated.rdv_id)
      closeRapport()
    }
    toast.add({ title: 'Rapport signé', color: 'success' })
  } catch (e: unknown) {
    rapportSignError.value = (e instanceof Error ? e.message : 'Erreur inconnue') || 'Erreur lors de la signature'
  } finally {
    rapportSigning.value = false
  }
}

// Signature canvas helpers
function startRapportDraw(e: PointerEvent) {
  sigRapportDrawing = true
  rapportSigDrawn.value = true
  const canvas = sigRapportCanvas.value!
  const rect = canvas.getBoundingClientRect()
  sigLastX = (e.clientX - rect.left) * (canvas.width / rect.width)
  sigLastY = (e.clientY - rect.top) * (canvas.height / rect.height)
}
function drawRapport(e: PointerEvent) {
  if (!sigRapportDrawing) return
  const canvas = sigRapportCanvas.value!
  const ctx = canvas.getContext('2d')!
  const rect = canvas.getBoundingClientRect()
  const x = (e.clientX - rect.left) * (canvas.width / rect.width)
  const y = (e.clientY - rect.top) * (canvas.height / rect.height)
  ctx.beginPath(); ctx.moveTo(sigLastX, sigLastY); ctx.lineTo(x, y)
  ctx.strokeStyle = '#1a1a2e'; ctx.lineWidth = 2.5; ctx.lineCap = 'round'; ctx.stroke()
  sigLastX = x; sigLastY = y
}
function endRapportDraw() { sigRapportDrawing = false }
function clearRapportSig() {
  const canvas = sigRapportCanvas.value
  if (canvas) canvas.getContext('2d')!.clearRect(0, 0, canvas.width, canvas.height)
  rapportSigDrawn.value = false
}

const checkupItems = [
  { key: 'pneus', label: 'Pneus' },
  { key: 'freins', label: 'Freins' },
  { key: 'huile', label: 'Huile' },
  { key: 'eclairage', label: 'Éclairage' },
  { key: 'batterie', label: 'Batterie' },
  { key: 'chaine', label: 'Chaîne' },
  { key: 'liquides', label: 'Liquides' },
  { key: 'suspension', label: 'Suspension' },
  { key: 'cablerie', label: 'Câblerie' },
  { key: 'general', label: 'État général' },
]
const checkupForm = reactive<Record<string, string>>({})
const checkupDone = computed(() => Object.values(checkupForm).filter(v => v === 'ok' || v === 'nok').length)
const checkupPhotos = computed(() => {
  const map: Record<string, string> = {}
  return map
})

function toggleCheckupItem(key: string) {
  if (!checkupForm[key]) checkupForm[key] = 'ok'
  else if (checkupForm[key] === 'ok') checkupForm[key] = 'nok'
  else checkupForm[key] = ''
}

const initials = computed(() => {
  const u = auth.user?.value
  if (!u) return 'M'
  return ((u.prenom?.[0] || '') + (u.nom?.[0] || '')).toUpperCase() || 'M'
})

const todayLabel = computed(() => new Date().toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' }))

const activeRdv = computed(() => myRdvs.value.find(r => ['en_cours', 'en_pause', 'en_attente_pieces'].includes(r.status)))
const activeOrId = computed(() => activeRdv.value?.or_id ?? null)
const activeVehiculeState = computed(() => parseEtatVehicule(activeRdv.value?.etat_reception))
const canFinishCurrentRdv = computed(() => ['en_cours', 'en_pause'].includes(activeRdv.value?.status || ''))
const mechanicPhotoGroups = computed(() => {
  const grouped = new Map<string, any[]>()
  for (const photo of rdvPhotos.value) {
    const type = String(photo?.type || 'en_cours')
    if (!grouped.has(type)) grouped.set(type, [])
    grouped.get(type)!.push(photo)
  }
  return Array.from(grouped.entries()).map(([type, photos]) => ({ type, photos }))
})
const activeProblemPhotoIds = computed(() => rdvPhotos.value
  .filter((photo: any) => photo?.type === 'probleme')
  .map((photo: any) => Number(photo.id))
  .filter((id: number) => Number.isFinite(id) && id > 0))
const afterWorkPhotosCount = computed(() => rdvPhotos.value.filter((photo: any) => photo?.type === 'apres_travaux').length)
const restitutionPhotosCount = computed(() => rdvPhotos.value.filter((photo: any) => photo?.type === 'restitution').length)
const essaiRoutierValide = computed(() => Boolean(activeRdv.value?.essai_routier_valide || rapport.value?.essaiRoutier?.isValide))
const essaiStatusLabel = computed(() => {
  if (essaiRoutierValide.value) return 'Essai validé'
  if (essaiHasNok.value) return 'Anomalie détectée'
  if (canValidateRoadTest.value) return 'Prêt à valider'
  return 'Essai à renseigner'
})
const receptionPoints = computed(() => Array.isArray(activeVehiculeState.value?.points) ? activeVehiculeState.value.points : [])
const receptionObservations = computed(() => activeVehiculeState.value?.observations ?? '')
const receptionFuelLevel = computed(() => activeVehiculeState.value?.fuel_level ?? '')
const receptionPriority = computed(() => activeVehiculeState.value?.priority ?? '')
const todoRdvs = computed(() => myRdvs.value.filter(r => ['en_attente', 'reserve', 'confirme', 'reception'].includes(r.status)))
const doneRdvs = computed(() => myRdvs.value.filter(r => ['termine', 'restitue', 'facture', 'paye'].includes(r.status)))
const activeRdvStatusLabel = computed(() => {
  switch (activeRdv.value?.status) {
    case 'en_pause': return 'Intervention en pause'
    case 'en_attente_pieces': return 'En attente pièces'
    case 'en_cours': return 'Intervention active'
    default: return 'Intervention atelier'
  }
})
const activeRdvStatusTitle = computed(() => activeRdv.value?.status === 'en_attente_pieces' ? 'Intervention en attente pièces' : 'Intervention atelier')
const activeRdvStatusHint = computed(() => {
  switch (activeRdv.value?.status) {
    case 'en_pause':
      return 'Le travail est suspendu temporairement. Reprenez dès que la moto revient sur le pont.'
    case 'en_attente_pieces':
      return 'Le dossier reste côté atelier mais la reprise doit attendre la réception des pièces nécessaires.'
    default:
      return 'L\u2019intervention est en cours sur le pont.'
  }
})
const activeRdvStatusStyle = computed(() => {
  switch (activeRdv.value?.status) {
    case 'en_pause':
      return { background: 'rgba(245,158,11,0.12)', color: '#FCD34D' }
    case 'en_attente_pieces':
      return { background: 'rgba(239,68,68,0.12)', color: '#FCA5A5' }
    default:
      return { background: 'rgba(16,185,129,0.12)', color: '#6EE7B7' }
  }
})
const canValidateRoadTest = computed(() => {
  const kmDebut = Number(essaiForm.kmDebut ?? 0)
  const kmFin = Number(essaiForm.kmFin ?? 0)
  const duree = Number(essaiForm.dureeMinutes ?? 0)
  return kmDebut > 0 && kmFin > kmDebut && duree > 0 && essaiFilledCount.value >= 5
})

const kpis = computed(() => ({
  enCours: myRdvs.value.filter(r => ['en_cours', 'en_pause', 'en_attente_pieces'].includes(r.status)).length,
  aFaire: todoRdvs.value.length,
  termines: doneRdvs.value.length,
  pctDone: myRdvs.value.length ? Math.round(doneRdvs.value.length / myRdvs.value.length * 100) : 0,
}))

const priorityAction = computed(() => {
  const receptions = todoRdvs.value.filter(r => r.status === 'reception')
  if (receptions.length) return `Démarrer : ${receptions[0].client_nom} — ${receptions[0].vehicule_info}`
  if (activeRdv.value?.status === 'en_attente_pieces') return 'Pièces attendues: reprendre dès réception et validation comptoir'
  if (activeRdv.value?.status === 'en_pause') return 'Intervention en pause: reprendre ou basculer en attente pièces selon le cas réel'
  if (activeRdv.value && progressPct.value > 100) return `⚠️ Intervention en cours en retard — terminer rapidement`
  if (activeRdv.value && !essaiRoutierValide.value) return 'Valider l\u2019essai routier avant clôture'
  if (todoRdvs.value.length) return `Prochain RDV à ${todoRdvs.value[0].heure_debut?.slice(0, 5)} — ${todoRdvs.value[0].client_nom}`
  return null
})

const elapsedMin = computed(() => {
  const rdv = activeRdv.value
  if (!rdv) return 0
  const started = rdv.heure_debut_travail || rdv.heure_debut_travaux || rdv.started_at
  if (!started) return 0
  const startTime = new Date(started)
  if (isNaN(startTime.getTime())) return 0
  return Math.round((now.value - startTime.getTime()) / 60000)
})

const progressPct = computed(() => {
  const rdv = activeRdv.value
  if (!rdv?.temps_estime) return 0
  return Math.round(elapsedMin.value / rdv.temps_estime * 100)
})

const chronoDisplay = computed(() => {
  const min = elapsedMin.value
  const h = Math.floor(min / 60)
  const m = min % 60
  const s = Math.floor(((now.value - getStartTime()) % 60000) / 1000)
  return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(Math.max(0, s)).padStart(2, '0')}`
})

const chronoPct = computed(() => progressPct.value)
const chronoDisplayTime = computed(() => chronoDisplay.value)
const elapsedSeconds = computed(() => elapsedMin.value)
const plannedSeconds = computed(() => activeRdv.value?.temps_estime || 0)
const overtimeSeconds = computed(() => Math.max(0, elapsedMin.value - (activeRdv.value?.temps_estime || 0)))

function getStartTime(): number {
  const rdv = activeRdv.value
  if (!rdv) return Date.now()
  const started = rdv.heure_debut_travail || rdv.heure_debut_travaux || rdv.started_at
  if (!started) return Date.now()
  const t = new Date(started).getTime()
  return isNaN(t) ? Date.now() : t
}

function parseEtatVehicule(raw: any) {
  if (!raw) return {}
  if (typeof raw === 'string') {
    try { return JSON.parse(raw) } catch { return {} }
  }
  return raw
}

function applySavedWorkshopReport() {
  Object.keys(checkupForm).forEach((key) => { delete checkupForm[key] })
  const savedCheckup = activeRdv.value?.or_mechanic_checkup ?? {}
  Object.entries(savedCheckup).forEach(([key, value]) => {
    if (value) checkupForm[key] = String(value)
  })
  interventionNotes.value = activeRdv.value?.or_mechanic_notes ?? ''
  if (essaiForm.kmDebut == null && activeRdv.value?.km_reception != null) {
    essaiForm.kmDebut = Number(activeRdv.value.km_reception)
  }
}

async function persistWorkshopReport(showToast = true) {
  if (!activeRdv.value) return
  persistingCheckup.value = true
  try {
    const orId = activeOrId.value
    if (!orId) {
      toast.add({ title: 'OR introuvable', description: "L'ordre de réparation n'a pas encore été créé par la réception.", color: 'warning' })
      return
    }

    await api.patch(`/mecanicien/me/rapport/${orId}`, {
      mechanic_checkup: { ...checkupForm },
      mechanic_notes: interventionNotes.value,
    })

    myRdvs.value = myRdvs.value.map((rdv: any) => rdv.id === activeRdv.value?.id
      ? {
          ...rdv,
          or_mechanic_checkup: { ...checkupForm },
          or_mechanic_notes: interventionNotes.value,
        }
      : rdv)

    if (showToast) {
      toast.add({ title: 'Rapport atelier sauvegardé', color: 'success' })
    }
  } catch (e: unknown) {
    toast.add({ title: 'Erreur', description: e instanceof Error ? e.message : 'Erreur inconnue', color: 'error' })
  } finally {
    persistingCheckup.value = false
  }
}

async function saveActiveRoadTest() {
  if (!activeRdv.value) return
  if (!canValidateRoadTest.value) {
    toast.add({
      title: 'Essai routier incomplet',
      description: 'Renseignez km départ/retour, durée et au moins 5 points avant validation.',
      color: 'warning',
    })
    return
  }

  savingRoadTest.value = true
  try {
    const result = await api.post('/mecanicien/me/essai-routier', {
      rdv_id: activeRdv.value.id,
      km_debut: essaiForm.kmDebut,
      km_fin: essaiForm.kmFin,
      dureeMinutes: essaiForm.dureeMinutes,
      checkpoints: buildEssaiCheckpoints(),
      observations: interventionNotes.value || null,
      actions_correctives: essaiForm.actionsCorrectives || null,
      actionsCorrectives: essaiForm.actionsCorrectives || null,
      valider: true,
    })
    await fetchMyRdvs()
    toast.add({ title: result?.valide ? 'Essai routier validé' : 'Essai routier enregistré', color: 'success' })
  } catch (e: unknown) {
    toast.add({ title: 'Erreur essai routier', description: e instanceof Error ? e.message : 'Erreur inconnue', color: 'error' })
  } finally {
    savingRoadTest.value = false
  }
}

async function createSupplementaryRequest() {
  if (!activeRdv.value?.id) return
  if (supplementaryForm.description.trim().length < 10) {
    toast.add({ title: 'Description requise', description: 'Décrivez le problème constaté avec suffisamment de précision.', color: 'warning' })
    return
  }

  creatingSupplementary.value = true
  try {
    await api.post('/mecanicien/me/demandes-travaux-supp', {
      rdv_id: activeRdv.value.id,
      description: supplementaryForm.description.trim(),
      urgence: supplementaryForm.urgence,
      photos_ids: activeProblemPhotoIds.value,
    })
    supplementaryForm.description = ''
    supplementaryForm.urgence = 'normal'
    showSupplementaryForm.value = false
    toast.add({ title: 'Demande envoyée', description: 'La réception peut maintenant la qualifier et la transmettre au client.', color: 'success' })
  } catch (e: unknown) {
    toast.add({ title: 'Erreur demande complémentaire', description: e instanceof Error ? e.message : 'Erreur inconnue', color: 'error' })
  } finally {
    creatingSupplementary.value = false
  }
}

async function startWork(id: number) {
  try {
    await rdvStore.transitionRdv(id, 'start_travail')
    await fetchMyRdvs()
    toast.add({ title: 'Travaux démarrés', color: 'success' })
  } catch (e: unknown) {
    toast.add({ title: 'Erreur', description: e instanceof Error ? e.message : 'Erreur inconnue', color: 'error' })
  }
}

async function transitionCurrentWork(transition: string, successTitle: string) {
  if (!activeRdv.value?.id) return
  try {
    await rdvStore.transitionRdv(activeRdv.value.id, transition)
    await fetchMyRdvs()
    toast.add({ title: successTitle, color: 'success' })
  } catch (e: unknown) {
    toast.add({ title: 'Erreur', description: e instanceof Error ? e.message : 'Erreur inconnue', color: 'error' })
  }
}

async function pauseCurrentWork() {
  await transitionCurrentWork('mettre_en_pause', 'Intervention mise en pause')
}

async function waitForParts() {
  if (!activeRdv.value) return
  const transition = activeRdv.value.status === 'en_pause' ? 'mettre_en_attente_pieces' : 'attendre_pieces'
  await transitionCurrentWork(transition, 'Dossier passé en attente pièces')
}

async function resumeCurrentWork() {
  if (!activeRdv.value) return
  const transition = activeRdv.value.status === 'en_attente_pieces' ? 'reprendre_apres_pieces' : 'reprendre'
  await transitionCurrentWork(transition, 'Intervention reprise')
}

async function finishWork() {
  if (!activeRdv.value) return
  if (!canFinishCurrentRdv.value) {
    toast.add({ title: 'Reprise requise', description: 'Une intervention en attente pièces doit être reprise avant clôture.', color: 'warning' })
    return
  }
  if (!checkupDone.value && !interventionNotes.value.trim()) {
    toast.add({ title: 'Rapport atelier requis', description: 'Ajoutez au moins un point de contrôle ou une note avant de terminer.', color: 'warning' })
    return
  }
  if (!essaiRoutierValide.value) {
    toast.add({ title: 'Essai routier obligatoire', description: 'Validez l\u2019essai routier dans le bloc atelier avant de terminer.', color: 'warning' })
    return
  }
  finishing.value = true
  try {
    await persistWorkshopReport(false)
    const terminatedId = activeRdv.value.id
    await openRapport(terminatedId)
    if (!rapport.value?.signatureMecanicien) {
      pendingFinishTransition.value = true
      toast.add({ title: 'Signature mécanicien requise', description: 'Signez le rapport d\u2019intervention pour clôturer le RDV.', color: 'warning' })
      return
    }
    await completeFinishTransition(terminatedId)
  } catch (e: unknown) {
    toast.add({ title: 'Erreur', description: e instanceof Error ? e.message : 'Erreur inconnue', color: 'error' })
  } finally {
    finishing.value = false
  }
}

async function completeFinishTransition(rdvId: number) {
  await rdvStore.transitionRdv(rdvId, 'terminer')
  pendingFinishTransition.value = false
  await fetchMyRdvs()
  toast.add({ title: 'Intervention terminée', color: 'success' })
}

async function saveInterventionNotes() {
  if (!activeRdv.value) return
  savingNotes.value = true
  try {
    await persistWorkshopReport(false)
    toast.add({ title: 'Notes sauvegardées', color: 'success' })
  } finally {
    savingNotes.value = false
  }
}

async function fetchMyRdvs() {
  const today = new Date().toISOString().slice(0, 10)
  const rdvData = await api.get(`/rendez-vous/mecanicien?date=${today}`)

  const items = Array.isArray(rdvData) ? rdvData : (rdvData?.['hydra:member'] ?? rdvData?.member ?? [])
  myRdvs.value = items.map((r: any) => ({
    ...r,
    status: r.statut ?? r.status,
    heure_debut: r.heure_rdv ?? r.heure_debut,
    temps_estime: r.temps_estime ?? r.duree_estimee ?? 60,
    or_id: r.or_id ?? null,
    rapport_mecanicien_signe: !!r.rapport_mecanicien_signe,
    commentaire_client: r.commentaire_client ?? r.commentaire ?? '',
  }))
}

watch(activeRdv, (next, prev) => {
  if (next?.id !== prev?.id) {
    resetEssaiForm()
    showSupplementaryForm.value = false
    supplementaryForm.description = ''
    supplementaryForm.urgence = 'normal'
  }
  applySavedWorkshopReport()
  loadRdvPhotos(next?.id ?? null)
})

onMounted(async () => {
  try {
    await fetchMyRdvs()
    applySavedWorkshopReport()
    await loadRdvPhotos(activeRdv.value?.id ?? null)
    chronoTimer = setInterval(() => { now.value = Date.now() }, 1000)
  } finally {
    loading.value = false
  }
})

onUnmounted(() => {
  if (chronoTimer) clearInterval(chronoTimer)
})
</script>
