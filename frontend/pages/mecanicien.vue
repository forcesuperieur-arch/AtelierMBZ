<template>
  <div>
    <div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
      <div style="display:flex;align-items:center;gap:14px;">
        <div style="width:48px;height:48px;border-radius:50%;background:rgba(139,92,246,0.15);border:2px solid rgba(139,92,246,0.3);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:18px;color:#C4B5FD;">{{ initials }}</div>
        <div>
          <div class="page-title">Espace Mécanicien</div>
          <div style="font-size:12px;color:#6B7280;">{{ todayLabel }}</div>
        </div>
      </div>
      <div style="display:flex;gap:8px;">
        <div class="stat-card" style="padding:8px 14px;min-width:auto;">
          <div style="font-size:10px;color:#6B7280;">EN COURS</div>
          <div style="font-size:18px;font-weight:700;color:#F59E0B;">{{ kpis.enCours }}</div>
        </div>
        <div class="stat-card" style="padding:8px 14px;min-width:auto;">
          <div style="font-size:10px;color:#6B7280;">À FAIRE</div>
          <div style="font-size:18px;font-weight:700;color:#E8E9ED;">{{ kpis.aFaire }}</div>
        </div>
        <div class="stat-card" style="padding:8px 14px;min-width:auto;">
          <div style="font-size:10px;color:#6B7280;">TERMINÉS</div>
          <div style="font-size:18px;font-weight:700;color:#10B981;">{{ kpis.termines }}</div>
        </div>
        <div class="stat-card" style="padding:8px 14px;min-width:auto;">
          <div style="font-size:10px;color:#6B7280;">JOURNÉE</div>
          <div style="font-size:18px;font-weight:700;color:#FFD200;">{{ kpis.pctDone }}%</div>
        </div>
      </div>
    </div>

    <div v-if="loading" style="display:flex;justify-content:center;padding:48px;">
      <span style="color:#6B7280;">Chargement...</span>
    </div>

    <div v-else>
      <!-- Priority card -->
      <div v-if="absenceToday" style="margin-bottom:20px;padding:14px;border-radius:12px;background:#FEF2F2;border:1px solid #FCA5A5;">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
          <span style="font-size:14px;">⚠️</span>
          <span style="font-size:13px;font-weight:600;color:#FCA5A5;">Absence aujourd'hui</span>
        </div>
        <p style="font-size:13px;color:#D1D5DB;">{{ absenceToday.motif }}</p>
      </div>

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
          <div style="display:flex;align-items:center;justify-content:space-between;">
            <span style="font-size:15px;font-weight:700;color:#F59E0B;">🔧 Intervention en cours</span>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
              <span v-if="activeRdv.client_telephone" style="font-size:12px;">
                <a :href="`tel:${activeRdv.client_telephone}`" style="color:#6B7280;text-decoration:none;">📞 Appeler</a>
              </span>
              <span v-if="activeOrId" style="font-size:12px;color:#9CA3AF;font-weight:600;">📋 OR #{{ activeOrId }}</span>
              <span style="font-size:11px;padding:4px 10px;border-radius:999px;font-weight:700;" :style="{ background: essaiRoutierValide ? 'rgba(16,185,129,0.12)' : 'rgba(245,158,11,0.12)', color: essaiRoutierValide ? '#6EE7B7' : '#FCD34D' }">{{ essaiStatusLabel }}</span>
              <span v-if="activeRdv.statut === 'en_pause'" style="font-size:11px;padding:4px 10px;border-radius:999px;font-weight:700;background:rgba(107,114,128,0.12);color:#9CA3AF;">⏸ En pause</span>
              <UButton v-if="activeRdv.statut === 'en_cours'" label="⏸ Pause" color="neutral" variant="outline" size="sm" @click="pauseWork" :loading="pausing" />
              <UButton v-if="activeRdv.statut === 'en_pause'" label="▶ Reprendre" color="warning" variant="outline" size="sm" @click="resumeWork" :loading="resuming" />
              <UButton label="💾 Checkup" color="info" variant="outline" size="sm" @click="persistWorkshopReport()" :loading="persistingCheckup" />
              <UButton label="🏍 Valider essai" color="warning" variant="outline" size="sm" @click="saveActiveRoadTest" :loading="savingRoadTest" :disabled="essaiRoutierValide || !canValidateRoadTest" />
              <UButton label="✅ Terminer" color="success" size="sm" @click="finishWork" :loading="finishing" :disabled="!essaiRoutierValide" />
            </div>
          </div>
        </template>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;font-size:13px;">
          <div><span style="color:#6B7280;">Client :</span> <span style="color:#D1D5DB;">{{ activeRdv.client_nom }}</span></div>
          <div><span style="color:#6B7280;">Véhicule :</span> <span style="color:#D1D5DB;">{{ activeRdv.vehicule_info }}</span></div>
          <div><span style="color:#6B7280;">Type :</span> <span style="color:#D1D5DB;">{{ activeRdv.type_intervention }}</span></div>
          <div><span style="color:#6B7280;">Pont :</span> <span style="color:#D1D5DB;">{{ activeRdv.pont_nom }}</span></div>
        </div>
        <div v-if="activeRdv.commentaire_client || activeRdv.description_probleme || activeRdv.commentaire" style="margin-top:12px;font-size:13px;">
          <span style="color:#6B7280;">Motif client :</span>
          <p style="color:#D1D5DB;">{{ activeRdv.commentaire_client || activeRdv.description_probleme || activeRdv.commentaire }}</p>
        </div>

        <div v-if="receptionPoints.length || receptionObservations || receptionFuelLevel || receptionPriority || activeRdv.vehicule_plaque || activeRdv.km_reception !== null" style="margin-top:14px;padding:12px;border-radius:10px;background:rgba(59,130,246,0.05);border:1px solid rgba(59,130,246,0.18);">
          <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;margin-bottom:8px;">
            <span style="font-size:13px;font-weight:600;color:#BFDBFE;">📥 Contexte réception</span>
            <span style="font-size:11px;padding:3px 8px;border-radius:999px;" :style="{ background: activeRdv.or_signe ? 'rgba(16,185,129,0.12)' : 'rgba(239,68,68,0.12)', color: activeRdv.or_signe ? '#6EE7B7' : '#FCA5A5' }">{{ activeRdv.or_signe ? 'OR signé' : 'OR à vérifier' }}</span>
          </div>
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:8px;font-size:12px;">
            <div v-if="activeRdv.vehicule_plaque"><span style="color:#6B7280;">Plaque :</span> <span style="color:#D1D5DB;">{{ activeRdv.vehicule_plaque }}</span></div>
            <div v-if="activeRdv.km_reception !== null"><span style="color:#6B7280;">Km réception :</span> <span style="color:#D1D5DB;">{{ activeRdv.km_reception }}</span></div>
            <div v-if="receptionPriority"><span style="color:#6B7280;">Priorité :</span> <span style="color:#D1D5DB;">{{ receptionPriority }}</span></div>
            <div v-if="receptionFuelLevel"><span style="color:#6B7280;">Carburant :</span> <span style="color:#D1D5DB;">{{ receptionFuelLevel }}</span></div>
          </div>
          <div v-if="receptionPoints.length" style="display:flex;flex-wrap:wrap;gap:6px;margin-top:10px;">
            <span v-for="(point, idx) in receptionPoints" :key="`${idx}-${point}`" style="padding:4px 8px;border-radius:999px;font-size:11px;background:rgba(255,255,255,0.05);color:#D1D5DB;">{{ point }}</span>
          </div>
          <div v-if="receptionObservations" style="margin-top:10px;font-size:12px;color:#D1D5DB;">
            <span style="color:#6B7280;">Observations :</span> {{ receptionObservations }}
          </div>
        </div>

        <!-- Live Chrono -->
        <div v-if="activeRdv.temps_estime" style="margin-top:16px;">
          <div style="display:flex;justify-content:space-between;align-items:center;font-size:12px;color:#9CA3AF;margin-bottom:6px;">
            <span>Chrono</span>
            <span style="font-family:monospace;font-size:16px;font-weight:700;" :style="{ color: progressPct > 100 ? '#EF4444' : '#FFD200' }">{{ chronoDisplay }}</span>
          </div>
          <div style="display:flex;justify-content:space-between;font-size:12px;color:#9CA3AF;margin-bottom:4px;">
            <span>Progression</span>
            <span :style="{ color: progressPct > 100 ? '#EF4444' : '#FFD200' }">{{ progressPct }}%</span>
          </div>
          <div style="background:var(--dark3,#171B24);border-radius:8px;height:10px;overflow:hidden;">
            <div :style="{ width: Math.min(progressPct, 100) + '%', height: '100%', background: progressPct > 100 ? '#EF4444' : '#FFD200', borderRadius: '8px', transition: 'width 1s ease' }"></div>
          </div>
          <div style="display:flex;justify-content:space-between;font-size:11px;color:#6B7280;margin-top:4px;">
            <span>{{ elapsedMin }}min écoulées</span>
            <span>{{ formatMinutes(activeRdv.temps_estime) }} estimées</span>
          </div>
          <div v-if="progressPct > 100" style="margin-top:8px;padding:8px 12px;border-radius:8px;background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.2);font-size:12px;color:#FCA5A5;">
            ⚠️ Dépassement +{{ elapsedMin - activeRdv.temps_estime }}min — intervention en retard
          </div>
        </div>

        <!-- Checkup Express -->
        <div style="margin-top:20px;border-top:1px solid rgba(255,255,255,0.06);padding-top:14px;">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
            <span style="font-size:13px;font-weight:600;color:#E8E9ED;">Checkup Express</span>
            <span style="font-size:11px;color:#6B7280;">{{ checkupDone }}/{{ checkupItems.length }} vérifiés</span>
          </div>
          <div style="font-size:11px;color:#6B7280;margin-bottom:8px;">Le rapport est enregistré dans le dossier atelier.</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;">
            <div v-for="item in checkupItems" :key="item.key" style="display:flex;align-items:center;gap:6px;padding:6px 10px;border-radius:6px;border:1px solid rgba(255,255,255,0.04);font-size:12px;cursor:pointer;" :style="{ background: checkup[item.key] === 'ok' ? 'rgba(16,185,129,0.06)' : checkup[item.key] === 'nok' ? 'rgba(239,68,68,0.06)' : 'transparent' }" @click="cycleCheckup(item.key)">
              <span v-if="checkup[item.key] === 'ok'" style="color:#10B981;">✅</span>
              <span v-else-if="checkup[item.key] === 'nok'" style="color:#EF4444;">❌</span>
              <span v-else style="color:#6B7280;">⬜</span>
              <span style="color:#D1D5DB;">{{ item.label }}</span>
            </div>
          </div>
        </div>

        <!-- Essai routier atelier -->
        <div style="margin-top:16px;border-top:1px solid rgba(255,255,255,0.06);padding-top:14px;">
          <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;margin-bottom:10px;">
            <label style="font-size:13px;font-weight:600;color:#E8E9ED;display:block;">🏍 Essai routier atelier</label>
            <span style="font-size:11px;color:#6B7280;">{{ essaiFilledCount }}/{{ essaiPoints.length }} points renseignés</span>
          </div>
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:8px;margin-bottom:10px;">
            <div>
              <label style="font-size:11px;color:#6B7280;display:block;margin-bottom:2px;">Km départ</label>
              <input v-model.number="essaiForm.kmDebut" type="number" class="form-input" />
            </div>
            <div>
              <label style="font-size:11px;color:#6B7280;display:block;margin-bottom:2px;">Km retour</label>
              <input v-model.number="essaiForm.kmFin" type="number" class="form-input" />
            </div>
            <div>
              <label style="font-size:11px;color:#6B7280;display:block;margin-bottom:2px;">Durée (min)</label>
              <input v-model.number="essaiForm.dureeMinutes" type="number" class="form-input" />
            </div>
          </div>
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:6px;">
            <button
              v-for="pt in essaiPoints" :key="`active-${pt.key}`"
              type="button"
              style="padding:6px 8px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;text-align:left;display:flex;align-items:center;gap:6px;"
              :style="{
                background: essaiForm.pointsControle[pt.key] === 'ok' ? 'rgba(16,185,129,0.08)' : essaiForm.pointsControle[pt.key] === 'nok' ? 'rgba(239,68,68,0.08)' : 'rgba(255,255,255,0.02)',
                border: essaiForm.pointsControle[pt.key] === 'ok' ? '1px solid rgba(16,185,129,0.25)' : essaiForm.pointsControle[pt.key] === 'nok' ? '1px solid rgba(239,68,68,0.25)' : '1px solid rgba(255,255,255,0.06)',
                color: essaiForm.pointsControle[pt.key] === 'ok' ? '#6EE7B7' : essaiForm.pointsControle[pt.key] === 'nok' ? '#FCA5A5' : '#9CA3AF',
              }"
              @click="cycleEssaiPoint(pt.key)"
            >
              <span>{{ essaiForm.pointsControle[pt.key] === 'ok' ? '✅' : essaiForm.pointsControle[pt.key] === 'nok' ? '❌' : '⬜' }}</span>
              {{ pt.label }}
            </button>
          </div>
          <div v-if="essaiHasNok" style="margin-top:8px;">
            <label style="font-size:11px;color:#6B7280;display:block;margin-bottom:2px;">Actions correctives</label>
            <textarea v-model="essaiForm.actionsCorrectives" class="form-input" rows="2" placeholder="Décrire les corrections effectuées…" />
          </div>
          <div style="display:flex;justify-content:space-between;gap:8px;align-items:center;flex-wrap:wrap;margin-top:10px;">
            <span style="font-size:11px;color:#6B7280;">Minimum requis : km départ/retour, durée et 5 points renseignés.</span>
            <button class="btn btn-ghost" style="font-size:12px;" @click="saveActiveRoadTest" :disabled="savingRoadTest || essaiRoutierValide || !canValidateRoadTest">{{ savingRoadTest ? 'Validation…' : (essaiRoutierValide ? 'Essai validé' : 'Valider l’essai') }}</button>
          </div>
        </div>

        <!-- Travaux supplémentaires -->
        <div style="margin-top:16px;border-top:1px solid rgba(255,255,255,0.06);padding-top:14px;">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
            <span style="font-size:13px;font-weight:600;color:#E8E9ED;">🔧 Travaux supplémentaires</span>
            <button class="btn btn-ghost" style="font-size:12px;" @click="showNewDemande = !showNewDemande">{{ showNewDemande ? 'Annuler' : '+ Signaler un problème' }}</button>
          </div>
          <div v-if="activeRdv.demandes_travaux_supp?.length" style="display:flex;flex-direction:column;gap:8px;margin-bottom:12px;">
            <div v-for="demande in activeRdv.demandes_travaux_supp" :key="demande.id" style="display:flex;align-items:center;justify-content:space-between;padding:8px 10px;border-radius:6px;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.04);font-size:12px;">
              <span style="color:#D1D5DB;">{{ demande.description }}</span>
              <span style="font-size:11px;padding:2px 8px;border-radius:999px;font-weight:600;" :style="demandeBadgeStyle(demande)">{{ demandeStatutLabel(demande.statut) }}</span>
            </div>
          </div>
          <div v-else style="font-size:12px;color:#6B7280;margin-bottom:12px;">Aucune demande complémentaire signalée.</div>
          <div v-if="showNewDemande" style="display:flex;flex-direction:column;gap:8px;">
            <textarea v-model="newDemande.description" class="form-input" rows="2" placeholder="Description du problème…" />
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
              <input v-model.number="newDemande.prix_estime" type="number" class="form-input" placeholder="Coût estimé (€)" />
              <input v-model.number="newDemande.temps_estime" type="number" class="form-input" placeholder="Temps estimé (min)" />
            </div>
            <div style="display:flex;gap:8px;align-items:center;">
              <label style="font-size:12px;color:#9CA3AF;">Urgence :</label>
              <select v-model="newDemande.urgence" class="form-input" style="width:auto;font-size:12px;">
                <option value="normal">Normal</option>
                <option value="urgent">Urgent</option>
              </select>
            </div>
            <button class="btn btn-primary" style="font-size:12px;" @click="submitDemande" :disabled="submittingDemande || !newDemande.description.trim()">
              {{ submittingDemande ? 'Envoi…' : 'Envoyer pour validation' }}
            </button>
          </div>
        </div>

        <!-- Intervention notes -->
        <div style="margin-top:16px;border-top:1px solid rgba(255,255,255,0.06);padding-top:14px;">
          <label style="font-size:13px;font-weight:600;color:#E8E9ED;margin-bottom:6px;display:block;">Notes intervention</label>
          <textarea v-model="interventionNotes" class="form-input" rows="2" placeholder="Notes techniques, observations…" />
          <div style="display:flex;justify-content:flex-end;margin-top:6px;">
            <button class="btn btn-ghost" style="font-size:12px;" @click="saveInterventionNotes" :disabled="savingNotes">{{ savingNotes ? 'Sauvegarde…' : 'Sauvegarder' }}</button>
          </div>
        </div>
      </UCard>

      <!-- Todo: RDVs to do -->
      <UCard style="margin-bottom:24px;">
        <template #header>
          <span style="font-size:15px;font-weight:700;color:#E8E9ED;">📋 À faire ({{ todoRdvs.length }})</span>
        </template>
        <div v-if="!todoRdvs.length" style="padding:16px;text-align:center;color:#6B7280;">
          Toutes les interventions sont terminées 🎉
        </div>
        <div v-else style="display:flex;flex-direction:column;gap:10px;">
          <div v-for="rdv in todoRdvs" :key="rdv.id" style="display:flex;align-items:center;justify-content:space-between;padding:12px;border-radius:10px;border:1px solid rgba(255,255,255,0.06);background:rgba(255,255,255,0.02);">
            <div>
              <p style="font-weight:600;color:#E8E9ED;font-size:13px;">{{ rdv.heure_debut?.slice(0, 5) }} — {{ rdv.client_nom }}</p>
              <p style="font-size:12px;color:#6B7280;">{{ rdv.vehicule_info }} — {{ rdv.type_intervention }}</p>
              <p v-if="rdv.temps_estime" style="font-size:11px;color:#9CA3AF;">⏱ {{ formatMinutes(rdv.temps_estime) }}</p>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
              <StatusBadge :status="rdv.status" />
              <UButton v-if="rdv.status === 'reception'" size="xs" label="🔧 Démarrer" @click="startWork(rdv.id)" />
            </div>
          </div>
        </div>
      </UCard>

      <!-- Done: Completed RDVs -->
      <UCard v-if="doneRdvs.length">
        <template #header>
          <span style="font-size:15px;font-weight:700;color:#10B981;">✅ Terminés ({{ doneRdvs.length }})</span>
        </template>
        <div style="display:flex;flex-direction:column;gap:8px;">
          <div v-for="rdv in doneRdvs" :key="rdv.id" style="display:flex;align-items:center;justify-content:space-between;padding:10px;border-radius:10px;border:1px solid rgba(255,255,255,0.04);font-size:13px;" :style="{ opacity: rdv.status === 'termine' ? 1 : 0.7 }">
            <div>
              <span style="color:#10B981;">✅</span>
              <span style="color:#D1D5DB;margin-left:8px;">{{ rdv.heure_debut?.slice(0, 5) }} — {{ rdv.client_nom }} · {{ rdv.type_intervention }}</span>
              <span v-if="rdv.status === 'termine'" style="display:inline-block;margin-left:8px;padding:2px 6px;border-radius:4px;background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.2);font-size:10px;color:#F59E0B;font-weight:600;">Rapport à compléter</span>
            </div>
            <div style="display:flex;gap:8px;align-items:center;">
              <UButton v-if="rdv.status === 'termine'" size="xs" label="📋 Rapport" color="warning" variant="outline" @click="openRapport(rdv.id)" />
              <button style="color:#FFD200;font-size:11px;background:none;border:none;cursor:pointer;font-weight:600;" @click="openRdvDetail(rdv)">Voir →</button>
            </div>
          </div>
        </div>
      </UCard>
    </div>

    <!-- Rapport d'intervention panel -->
    <div v-if="rapportRdvId" style="position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:50;overflow-y:auto;padding:16px;" @click.self="closeRapport">
      <div style="max-width:640px;margin:0 auto;background:#0F1117;border:1px solid rgba(255,255,255,0.1);border-radius:16px;padding:24px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
          <h2 style="font-size:17px;font-weight:700;color:#E8E9ED;">📋 Rapport d'intervention</h2>
          <button @click="closeRapport" style="font-size:18px;color:#6B7280;background:none;border:none;cursor:pointer;">✕</button>
        </div>

        <div v-if="rapportLoading" style="text-align:center;padding:32px;color:#6B7280;">Chargement…</div>
        <div v-else-if="rapportError" style="padding:12px;background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);border-radius:8px;color:#FCA5A5;font-size:13px;">{{ rapportError }}</div>

        <div v-else-if="rapport">
          <div v-if="rapport.is_signed_by_both" style="text-align:center;padding:24px;background:rgba(16,185,129,0.06);border:1px solid rgba(16,185,129,0.2);border-radius:12px;margin-bottom:16px;">
            <div style="font-size:32px;margin-bottom:8px;">✅</div>
            <p style="color:#6EE7B7;font-weight:700;">Rapport signé par les deux parties</p>
            <a :href="`${apiBase.replace('/api','')}/api/rapport/${rapport.id}/pdf`" target="_blank" style="display:inline-block;margin-top:12px;padding:6px 14px;border-radius:8px;background:rgba(255,210,0,0.1);border:1px solid rgba(255,210,0,0.2);color:#FFD200;font-size:12px;font-weight:600;text-decoration:none;">📄 Télécharger PDF</a>
          </div>

          <div v-else style="display:flex;flex-direction:column;gap:16px;">
            <!-- Travaux réalisés -->
            <div>
              <label style="font-size:12px;font-weight:600;color:#9CA3AF;display:block;margin-bottom:4px;">Travaux réalisés <span style="color:#EF4444;">*</span></label>
              <textarea v-model="rapportForm.travauxRealises" class="form-input" rows="4" placeholder="Décrire précisément les travaux effectués…" :disabled="!!rapport.signature_mecanicien" />
            </div>

            <!-- Alertes -->
            <div>
              <label style="font-size:12px;font-weight:600;color:#9CA3AF;display:block;margin-bottom:4px;">Alertes importantes</label>
              <textarea v-model="rapportForm.alertes" class="form-input" rows="2" placeholder="Points à surveiller, anomalies, recommandations urgentes…" :disabled="!!rapport.signature_mecanicien" />
            </div>

            <!-- Recommandations -->
            <div>
              <label style="font-size:12px;font-weight:600;color:#9CA3AF;display:block;margin-bottom:4px;">Recommandations prochaine visite <span style="color:#EF4444;">*</span></label>
              <textarea v-model="rapportForm.recommandations" class="form-input" rows="2" placeholder="Prochaine révision, pièces à prévoir…" :disabled="!!rapport.signature_mecanicien" />
            </div>

            <!-- Kilométrage restitution -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
              <div>
                <label style="font-size:12px;font-weight:600;color:#9CA3AF;display:block;margin-bottom:4px;">Km restitution</label>
                <input v-model.number="rapportForm.kilometrageRestitution" type="number" class="form-input" placeholder="ex: 24500" :disabled="!!rapport.signature_mecanicien" />
              </div>
            </div>

            <!-- Entretien fluides -->
            <div style="padding:10px;border-radius:8px;background:rgba(59,130,246,0.06);border:1px solid rgba(59,130,246,0.15);">
              <div style="font-size:11px;font-weight:700;color:#BFDBFE;margin-bottom:6px;">🔧 Entretien des fluides recommandé</div>
              <div style="font-size:12px;color:#D1D5DB;">Huile moteur — <strong style="color:#93C5FD;">tous les ans</strong></div>
              <div style="font-size:12px;color:#D1D5DB;">Liquide de frein — <strong style="color:#93C5FD;">tous les 2 ans</strong></div>
              <div style="font-size:12px;color:#D1D5DB;">Liquide de refroidissement — <strong style="color:#93C5FD;">tous les 3 ans</strong></div>
            </div>

            <!-- Essai routier -->
            <div style="padding:14px;border:1px solid rgba(255,255,255,0.06);border-radius:10px;">
              <div style="font-size:13px;font-weight:600;color:#E8E9ED;margin-bottom:12px;">🏍 Essai routier</div>
              <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:12px;">
                <div>
                  <label style="font-size:11px;color:#6B7280;display:block;margin-bottom:2px;">Km départ</label>
                  <input v-model.number="essaiForm.kmDebut" type="number" class="form-input" :disabled="!!rapport.signature_mecanicien" />
                </div>
                <div>
                  <label style="font-size:11px;color:#6B7280;display:block;margin-bottom:2px;">Km retour</label>
                  <input v-model.number="essaiForm.kmFin" type="number" class="form-input" :disabled="!!rapport.signature_mecanicien" />
                </div>
                <div>
                  <label style="font-size:11px;color:#6B7280;display:block;margin-bottom:2px;">Durée (min)</label>
                  <input v-model.number="essaiForm.dureeMinutes" type="number" class="form-input" :disabled="!!rapport.signature_mecanicien" />
                </div>
              </div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;">
                <button
                  v-for="pt in essaiPoints" :key="pt.key"
                  type="button"
                  :disabled="!!rapport.signature_mecanicien"
                  style="padding:6px 8px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;text-align:left;display:flex;align-items:center;gap:6px;"
                  :style="{
                    background: essaiForm.pointsControle[pt.key] === 'ok' ? 'rgba(16,185,129,0.08)' : essaiForm.pointsControle[pt.key] === 'nok' ? 'rgba(239,68,68,0.08)' : 'rgba(255,255,255,0.02)',
                    border: essaiForm.pointsControle[pt.key] === 'ok' ? '1px solid rgba(16,185,129,0.25)' : essaiForm.pointsControle[pt.key] === 'nok' ? '1px solid rgba(239,68,68,0.25)' : '1px solid rgba(255,255,255,0.06)',
                    color: essaiForm.pointsControle[pt.key] === 'ok' ? '#6EE7B7' : essaiForm.pointsControle[pt.key] === 'nok' ? '#FCA5A5' : '#9CA3AF',
                  }"
                  @click="cycleEssaiPoint(pt.key)"
                >
                  <span>{{ essaiForm.pointsControle[pt.key] === 'ok' ? '✅' : essaiForm.pointsControle[pt.key] === 'nok' ? '❌' : '⬜' }}</span>
                  {{ pt.label }}
                </button>
              </div>
              <div v-if="essaiHasNok" style="margin-top:8px;">
                <label style="font-size:11px;color:#6B7280;display:block;margin-bottom:2px;">Actions correctives</label>
                <textarea v-model="essaiForm.actionsCorrectives" class="form-input" rows="2" placeholder="Décrire les corrections effectuées…" :disabled="!!rapport.signature_mecanicien" />
              </div>
            </div>

            <!-- Save button (before signature) -->
            <button
              v-if="!rapport.signature_mecanicien"
              class="btn btn-primary"
              style="width:100%;padding:10px;"
              :disabled="rapportSaving"
              @click="saveRapport"
            >
              {{ rapportSaving ? 'Enregistrement…' : '💾 Enregistrer le rapport' }}
            </button>

            <!-- Signature mécanicien -->
            <div v-if="!rapport.signature_mecanicien" style="border-top:1px solid rgba(255,255,255,0.06);padding-top:16px;">
              <p style="font-size:13px;font-weight:600;color:#E8E9ED;margin-bottom:8px;">✍️ Signature mécanicien</p>
              <p style="font-size:12px;color:#9CA3AF;margin-bottom:10px;">En signant, vous certifiez que les travaux sont réalisés et l'essai routier effectué.</p>
              <canvas
                ref="sigRapportCanvas"
                @pointerdown="startRapportDraw" @pointermove="drawRapport" @pointerup="endRapportDraw" @pointerleave="endRapportDraw"
                style="width:100%;aspect-ratio:4/1;border-radius:10px;background:rgba(255,255,255,0.95);touch-action:none;cursor:crosshair;"
              ></canvas>
              <div style="display:flex;gap:8px;margin-top:8px;">
                <button class="btn btn-ghost" style="flex:1;font-size:12px;" @click="clearRapportSig">↺ Effacer</button>
                <button
                  class="btn btn-success"
                  style="flex:2;font-size:12px;"
                  :disabled="!rapportSigDrawn || rapportSigning"
                  @click="signRapport"
                >{{ rapportSigning ? 'Signature…' : '✓ Signer le rapport' }}</button>
              </div>
              <div v-if="rapportSignError" style="margin-top:8px;padding:8px 12px;background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);border-radius:8px;color:#FCA5A5;font-size:12px;">{{ rapportSignError }}</div>
            </div>

            <div v-else style="padding:12px;background:rgba(16,185,129,0.06);border:1px solid rgba(16,185,129,0.2);border-radius:10px;font-size:13px;color:#6EE7B7;">
              ✅ Rapport signé par le mécanicien — en attente de signature client lors de la restitution.
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
const api = useApi()
const config = useRuntimeConfig()
const apiBase = config.public.apiBase as string
const rdvStore = useRdvStore()
const toast = useToast()
const auth = useAuth()
const { open: openRdvDetail } = useRdvDetailModal()
const loading = ref(true)
const finishing = ref(false)
const pausing = ref(false)
const resuming = ref(false)
const savingNotes = ref(false)
const persistingCheckup = ref(false)
const savingRoadTest = ref(false)
const myRdvs = ref<any[]>([])
const absenceToday = ref<any>(null)
const interventionNotes = ref('')
const now = ref(Date.now())
let chronoTimer: ReturnType<typeof setInterval> | null = null

// --- Travaux supplémentaires ---
const showNewDemande = ref(false)
const submittingDemande = ref(false)
const newDemande = reactive({
  description: '',
  prix_estime: null as number | null,
  temps_estime: null as number | null,
  urgence: 'normal' as 'normal' | 'urgent',
})

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
  // prochaineRevisionKm retirée — trop variable selon marque/modèle en moto
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
  // rapportForm.prochaineRevisionKm retirée — trop variable selon marque/modèle en moto
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
    const rdvData = myRdvs.value.find(r => r.id === rdvId)
    const orId = rdvData?.or_id
    if (!orId) {
      rapportError.value = 'Aucun ordre de réparation trouvé pour ce RDV'
      return
    }
    rapport.value = await api.get(`/mecanicien/me/rapport/${orId}`)
    fillRapportForm(rapport.value)
  } catch (e: any) {
    rapportError.value = e.message || 'Impossible de charger le rapport'
  } finally {
    rapportLoading.value = false
  }
}

function closeRapport() {
  rapportRdvId.value = null
  rapport.value = null
  rapportSignError.value = ''
  clearRapportSig()
}

async function saveRapport() {
  if (!rapport.value) return
  rapportSaving.value = true
  try {
    await api.patch(`/mecanicien/me/rapport/${rapport.value.id}`, {
      travaux_realises: rapportForm.travauxRealises,
      alertes: rapportForm.alertes ? rapportForm.alertes.split('\n').map((s: string) => s.trim()).filter(Boolean) : [],
      recommandations: rapportForm.recommandations,
      kilometrage_restitution: rapportForm.kilometrageRestitution,
      // prochaine_revision_km retirée — trop variable selon marque/modèle en moto
    })
    await fetchMyRdvs()
    toast.add({ title: 'Rapport enregistré', color: 'success' })
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e.message, color: 'error' })
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
    const updated = await api.post(`/mecanicien/me/sign/${rapport.value.id}`, { signature: sig })
    rapport.value = updated
    await fetchMyRdvs()
    toast.add({ title: 'Intervention signée', color: 'success' })
  } catch (e: any) {
    rapportSignError.value = e.message || 'Erreur lors de la signature'
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
function initCanvas() {
  const canvas = sigRapportCanvas.value
  if (!canvas) return
  canvas.width = canvas.offsetWidth
  canvas.height = canvas.offsetWidth / 4
}

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
const checkup = reactive<Record<string, string>>({})
const checkupDone = computed(() => Object.values(checkup).filter(v => v === 'ok' || v === 'nok').length)

function cycleCheckup(key: string) {
  if (!checkup[key]) checkup[key] = 'ok'
  else if (checkup[key] === 'ok') checkup[key] = 'nok'
  else checkup[key] = ''
}

const initials = computed(() => {
  const u = auth.user?.value
  if (!u) return 'M'
  return ((u.prenom?.[0] || '') + (u.nom?.[0] || '')).toUpperCase() || 'M'
})

const todayLabel = computed(() => new Date().toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' }))

const activeRdv = computed(() => myRdvs.value.find(r => r.status === 'en_cours' || r.status === 'en_pause'))
const activeOrId = computed(() => activeRdv.value?.or_id ?? null)
const activeVehiculeState = computed(() => parseEtatVehicule(activeRdv.value?.etat_reception))
const essaiRoutierValide = computed(() => {
  const statut = activeRdv.value?.essai_routier_statut ?? rapport.value?.essaiRoutier?.statut
  return statut === 'valide' || statut === 'anomalie_detectee'
})
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
const canValidateRoadTest = computed(() => {
  const kmDebut = Number(essaiForm.kmDebut ?? 0)
  const kmFin = Number(essaiForm.kmFin ?? 0)
  const duree = Number(essaiForm.dureeMinutes ?? 0)
  return kmDebut > 0 && kmFin > kmDebut && duree > 0 && essaiFilledCount.value >= 5
})

const kpis = computed(() => ({
  enCours: activeRdv.value ? 1 : 0,
  aFaire: todoRdvs.value.length,
  termines: doneRdvs.value.length,
  pctDone: myRdvs.value.length ? Math.round(doneRdvs.value.length / myRdvs.value.length * 100) : 0,
}))

const priorityAction = computed(() => {
  const receptions = todoRdvs.value.filter(r => r.status === 'reception')
  if (receptions.length) return `Démarrer : ${receptions[0].client_nom} — ${receptions[0].vehicule_info}`
  if (activeRdv.value && progressPct.value > 100) return `⚠️ Intervention en cours en retard — terminer rapidement`
  if (activeRdv.value && !essaiRoutierValide.value) return 'Valider l’essai routier avant clôture'
  if (todoRdvs.value.length) return `Prochain RDV à ${todoRdvs.value[0].heure_debut?.slice(0, 5)} — ${todoRdvs.value[0].client_nom}`
  return null
})

const elapsedMin = computed(() => {
  const rdv = activeRdv.value
  if (!rdv) return 0
  const baseMin = rdv.temps_effectif_minutes ?? 0
  if (rdv.statut === 'en_pause') return baseMin
  const started = rdv.heure_debut_travail || rdv.heure_debut_travaux || rdv.started_at
  if (!started) return baseMin
  const startTime = new Date(started)
  if (isNaN(startTime.getTime())) return baseMin
  return baseMin + Math.round((now.value - startTime.getTime()) / 60000)
})

const progressPct = computed(() => {
  const rdv = activeRdv.value
  if (!rdv?.temps_estime) return 0
  return Math.round(elapsedMin.value / rdv.temps_estime * 100)
})

const chronoDisplay = computed(() => {
  const rdv = activeRdv.value
  const min = elapsedMin.value
  const h = Math.floor(min / 60)
  const m = min % 60
  if (rdv?.statut === 'en_pause') {
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:00`
  }
  const s = Math.floor(((now.value - getStartTime()) % 60000) / 1000)
  return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(Math.max(0, s)).padStart(2, '0')}`
})

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
  Object.keys(checkup).forEach((key) => { delete checkup[key] })
  const savedCheckup = activeRdv.value?.or_mechanic_checkup ?? {}
  Object.entries(savedCheckup).forEach(([key, value]) => {
    if (value) checkup[key] = String(value)
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
      mechanic_checkup: { ...checkup },
      mechanic_notes: interventionNotes.value,
    })

    myRdvs.value = myRdvs.value.map((rdv: any) => rdv.id === activeRdv.value?.id
      ? {
          ...rdv,
          or_mechanic_checkup: { ...checkup },
          or_mechanic_notes: interventionNotes.value,
        }
      : rdv)

    if (showToast) {
      toast.add({ title: 'Rapport atelier sauvegardé', color: 'success' })
    }
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e.message, color: 'error' })
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
  } catch (e: any) {
    toast.add({ title: 'Erreur essai routier', description: e.message, color: 'error' })
  } finally {
    savingRoadTest.value = false
  }
}

async function startWork(id: number) {
  try {
    await rdvStore.transitionRdv(id, 'start_travail')
    await fetchMyRdvs()
    toast.add({ title: 'Travaux démarrés', color: 'success' })
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e.message, color: 'error' })
  }
}

async function pauseWork() {
  if (!activeRdv.value) return
  pausing.value = true
  try {
    await rdvStore.transitionRdv(activeRdv.value.id, 'pause_travail')
    await fetchMyRdvs()
    toast.add({ title: 'Intervention mise en pause', color: 'warning' })
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e.message, color: 'error' })
  } finally {
    pausing.value = false
  }
}

async function resumeWork() {
  if (!activeRdv.value) return
  resuming.value = true
  try {
    await rdvStore.transitionRdv(activeRdv.value.id, 'reprendre_travail')
    await fetchMyRdvs()
    toast.add({ title: 'Intervention reprise', color: 'success' })
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e.message, color: 'error' })
  } finally {
    resuming.value = false
  }
}

async function finishWork() {
  if (!activeRdv.value) return
  if (!checkupDone.value && !interventionNotes.value.trim()) {
    toast.add({ title: 'Rapport atelier requis', description: 'Ajoutez au moins un point de contrôle ou une note avant de terminer.', color: 'warning' })
    return
  }
  if (!essaiRoutierValide.value) {
    toast.add({ title: 'Essai routier obligatoire', description: 'Validez l’essai routier dans le bloc atelier avant de terminer.', color: 'warning' })
    return
  }
  finishing.value = true
  try {
    await persistWorkshopReport(false)
    const terminatedId = activeRdv.value.id
    await rdvStore.transitionRdv(terminatedId, 'terminer')
    await fetchMyRdvs()
    toast.add({ title: 'Intervention terminée', color: 'success' })
    openRapport(terminatedId)
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e.message, color: 'error' })
  } finally {
    finishing.value = false
  }
}

function demandeStatutLabel(statut: string): string {
  return {
    en_attente: 'En attente',
    en_attente_validation: 'À valider',
    en_attente_decision_client: 'En attente client',
    accepte: 'Accepté',
    refuse: 'Refusé',
  }[statut] ?? statut
}

function demandeBadgeStyle(demande: any): Record<string, string> {
  const colors: Record<string, { bg: string; color: string }> = {
    en_attente: { bg: 'rgba(107,114,128,0.12)', color: '#9CA3AF' },
    en_attente_validation: { bg: 'rgba(245,158,11,0.12)', color: '#FCD34D' },
    en_attente_decision_client: { bg: 'rgba(59,130,246,0.12)', color: '#BFDBFE' },
    accepte: { bg: 'rgba(16,185,129,0.12)', color: '#6EE7B7' },
    refuse: { bg: 'rgba(239,68,68,0.12)', color: '#FCA5A5' },
  }
  const c = colors[demande.statut] ?? colors.en_attente
  return { background: c.bg, color: c.color }
}

async function submitDemande() {
  if (!activeRdv.value || !newDemande.description.trim()) return
  submittingDemande.value = true
  try {
    await api.post('/mecanicien/me/demande-complementaire', {
      rdv_id: activeRdv.value.id,
      description: newDemande.description.trim(),
      prix_estime: newDemande.prix_estime,
      temps_estime: newDemande.temps_estime,
      urgence: newDemande.urgence,
    })
    newDemande.description = ''
    newDemande.prix_estime = null
    newDemande.temps_estime = null
    newDemande.urgence = 'normal'
    showNewDemande.value = false
    await fetchMyRdvs()
    toast.add({ title: 'Demande envoyée', description: 'La réception va valider et contacter le client.', color: 'success' })
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e.message, color: 'error' })
  } finally {
    submittingDemande.value = false
  }
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
  const data = await api.get(`/mecanicien/me/rdvs?date=${today}`)

  absenceToday.value = data?.absence_today ?? null

  const items = Array.isArray(data?.rdvs) ? data.rdvs : (data?.['hydra:member'] ?? data?.member ?? [])
  myRdvs.value = items.map((r: any) => ({
    ...r,
    status: r.statut ?? r.status,
    heure_debut: r.heure_rdv ?? r.heure_debut,
    temps_estime: r.temps_estime ?? r.duree_estimee ?? 60,
    or_id: r.or_id ?? null,
    commentaire_client: r.commentaire_client ?? r.commentaire ?? '',
  }))
}

watch(activeRdv, (next, prev) => {
  if (next?.id !== prev?.id) {
    resetEssaiForm()
  }
  applySavedWorkshopReport()
})

watch(rapportRdvId, (id) => {
  if (id) {
    nextTick(() => {
      initCanvas()
    })
  }
})

onMounted(async () => {
  try {
    await fetchMyRdvs()
    applySavedWorkshopReport()
    chronoTimer = setInterval(() => { now.value = Date.now() }, 1000)
  } finally {
    loading.value = false
  }
})

onUnmounted(() => {
  if (chronoTimer) clearInterval(chronoTimer)
})
</script>
