<template>
  <div class="meca">
    <div class="meca-header">
      <div class="meca-header-id">
        <div class="meca-avatar">{{ initials }}</div>
        <div>
          <div class="meca-page-title">Espace Mécanicien</div>
          <div class="meca-date">{{ todayLabel }}</div>
        </div>
      </div>
      <div class="meca-kpis">
        <div class="meca-kpi">
          <span class="meca-kpi-label">En cours</span>
          <span class="meca-kpi-value" style="color:var(--warning-content);">{{ kpis.enCours }}</span>
        </div>
        <div class="meca-kpi">
          <span class="meca-kpi-label">À faire</span>
          <span class="meca-kpi-value">{{ kpis.aFaire }}</span>
        </div>
        <div class="meca-kpi">
          <span class="meca-kpi-label">Terminés</span>
          <span class="meca-kpi-value" style="color:var(--success-content);">{{ kpis.termines }}</span>
        </div>
        <div class="meca-kpi">
          <span class="meca-kpi-label">Journée</span>
          <span class="meca-kpi-value" style="color:var(--accent-content);">{{ kpis.pctDone }}%</span>
        </div>
      </div>
    </div>

    <div v-if="loading" class="meca-loading">Chargement...</div>

    <div v-else-if="loadError" class="meca-error-panel">
      <div class="meca-error-panel-head"><AppIcon name="i-ri-error-warning-line" /> Chargement impossible</div>
      <p>{{ loadError }}</p>
      <AppButton variant="primary" class="meca-btn-lg" label="Réessayer" @click="reload" />
    </div>

    <div v-else>
      <div v-if="absenceToday" class="meca-banner meca-banner-error">
        <div class="meca-banner-head"><AppIcon name="i-ri-error-warning-line" /> Absence aujourd'hui</div>
        <p>{{ absenceToday.motif }}</p>
      </div>

      <div v-if="priorityAction" class="meca-banner meca-banner-accent">
        <div class="meca-banner-head"><AppIcon name="i-ri-flashlight-line" /> Prochaine action</div>
        <p>{{ priorityAction }}</p>
      </div>

      <div v-if="!offlineQueue.isOnline.value" class="meca-banner meca-banner-error">
        <div class="meca-banner-head"><AppIcon name="i-ri-wifi-off-line" /> Hors connexion</div>
        <p>Les actions en cours seront renvoyées automatiquement au retour du réseau.</p>
      </div>
      <div v-else-if="offlineQueue.pending.value.length" class="meca-banner meca-banner-accent">
        <div class="meca-banner-head"><AppIcon name="i-ri-refresh-line" /> Synchronisation en cours</div>
        <p>{{ offlineQueue.pending.value.length }} sauvegarde(s) en attente d'envoi.</p>
      </div>

      <div v-if="lastFailedAction" class="meca-banner meca-banner-error">
        <div class="meca-banner-head"><AppIcon name="i-ri-error-warning-line" /> Action non envoyée</div>
        <p>« {{ lastFailedAction.label }} » n'a pas atteint le serveur.</p>
        <AppButton variant="primary" class="meca-btn-lg" icon="i-ri-refresh-line" label="Réessayer" @click="retryLastFailedAction" />
      </div>

      <div class="meca-tabs" role="tablist">
        <button type="button" class="meca-tab" :class="{ 'is-active': activeTab === 'cours' }" role="tab" @click="activeTab = 'cours'">
          <AppIcon name="i-ri-tools-line" /> En cours
          <span v-if="activeRdv" class="meca-tab-dot" />
        </button>
        <button type="button" class="meca-tab" :class="{ 'is-active': activeTab === 'faire' }" role="tab" @click="activeTab = 'faire'">
          <AppIcon name="i-ri-clipboard-line" /> À faire
          <span class="meca-tab-count">{{ todoRdvs.length }}</span>
        </button>
        <button type="button" class="meca-tab" :class="{ 'is-active': activeTab === 'termines' }" role="tab" @click="activeTab = 'termines'">
          <AppIcon name="i-ri-checkbox-circle-line" /> Terminés
          <span class="meca-tab-count">{{ doneRdvs.length }}</span>
        </button>
      </div>

      <!-- ONGLET : Intervention en cours -->
      <div v-show="activeTab === 'cours'">
        <div v-if="!activeRdv" class="meca-empty">
          Aucune intervention en cours. Démarrez un rendez-vous depuis l'onglet « À faire ».
        </div>

        <div v-else class="meca-card meca-active-card">
          <div class="meca-active-head">
            <span class="meca-active-title"><AppIcon name="i-ri-tools-line" /> Intervention en cours</span>
            <div class="meca-active-head-links">
              <a v-if="activeRdv.client_telephone" :href="`tel:${activeRdv.client_telephone}`" class="meca-call-link">
                <AppIcon name="i-ri-phone-line" /> Appeler
              </a>
              <button v-if="activeRdv.vehicule_id" type="button" class="meca-call-link" @click="showHistorique = true">
                <AppIcon name="i-ri-history-line" /> Historique moto
              </button>
            </div>
          </div>

          <div class="meca-badge-row">
            <span v-if="activeOrId" class="meca-badge meca-badge-neutral"><AppIcon name="i-ri-clipboard-line" /> OR #{{ activeOrId }}</span>
            <span class="meca-badge" :class="essaiRoutierValide ? 'meca-badge-success' : 'meca-badge-warning'">{{ essaiStatusLabel }}</span>
            <span v-if="activeRdv.statut === 'en_pause'" class="meca-badge meca-badge-neutral"><AppIcon name="i-ri-pause-line" /> En pause</span>
          </div>

          <div class="meca-action-row">
            <AppButton
              v-if="activeRdv.statut === 'en_cours'"
              variant="secondary" class="meca-btn-lg" icon="i-ri-pause-line" label="Pause"
              :loading="pausing" @click="pauseWork"
            />
            <AppButton
              v-if="activeRdv.statut === 'en_pause'"
              variant="secondary" class="meca-btn-lg" icon="i-ri-play-line" label="Reprendre"
              :loading="resuming" @click="resumeWork"
            />
            <AppButton
              variant="primary" class="meca-btn-lg meca-btn-finish" icon="i-ri-checkbox-circle-line" label="Terminer"
              :loading="finishing" :disabled="!essaiRoutierValide" @click="finishWork"
            />
          </div>

          <div class="meca-info-grid">
            <div><span class="meca-info-label">Client</span> {{ activeRdv.client_nom }}</div>
            <div><span class="meca-info-label">Véhicule</span> {{ activeRdv.vehicule_info }}</div>
            <div><span class="meca-info-label">Type</span> {{ activeRdv.type_intervention }}</div>
            <div><span class="meca-info-label">Pont</span> {{ activeRdv.pont_nom }}</div>
          </div>

          <div v-if="activeRdv.commentaire_client || activeRdv.description_probleme || activeRdv.commentaire" class="meca-motif">
            <span class="meca-info-label">Motif client</span>
            <p>{{ activeRdv.commentaire_client || activeRdv.description_probleme || activeRdv.commentaire }}</p>
          </div>

          <div
            v-if="receptionPoints.length || receptionObservations || receptionFuelLevel || receptionPriority || activeRdv.vehicule_plaque || activeRdv.km_reception !== null"
            class="meca-reception"
          >
            <div class="meca-reception-head">
              <span><AppIcon name="i-ri-inbox-line" /> Contexte réception</span>
              <span class="meca-badge" :class="activeRdv.or_signe ? 'meca-badge-success' : 'meca-badge-error'">{{ activeRdv.or_signe ? 'OR signé' : 'OR à vérifier' }}</span>
            </div>
            <div class="meca-reception-grid">
              <div v-if="activeRdv.vehicule_plaque"><span class="meca-info-label">Plaque</span> {{ activeRdv.vehicule_plaque }}</div>
              <div v-if="activeRdv.km_reception !== null"><span class="meca-info-label">Km réception</span> {{ activeRdv.km_reception }}</div>
              <div v-if="receptionPriority"><span class="meca-info-label">Priorité</span> {{ receptionPriority }}</div>
              <div v-if="receptionFuelLevel"><span class="meca-info-label">Carburant</span> {{ receptionFuelLevel }}</div>
            </div>
            <div v-if="receptionPoints.length" class="meca-chip-row">
              <span v-for="(point, idx) in receptionPoints" :key="`${idx}-${point}`" class="meca-chip">{{ point }}</span>
            </div>
            <div v-if="receptionObservations" class="meca-reception-obs">
              <span class="meca-info-label">Observations</span> {{ receptionObservations }}
            </div>
          </div>

          <div v-if="activeRdv.temps_estime" class="meca-chrono">
            <div class="meca-chrono-row">
              <span class="meca-info-label">Chrono</span>
              <span class="meca-chrono-value" :class="progressPct > 100 ? 'is-late' : ''">{{ chronoDisplay }}</span>
            </div>
            <div class="meca-chrono-row meca-chrono-row-sm">
              <span>Progression</span>
              <span :class="progressPct > 100 ? 'is-late' : ''">{{ progressPct }}%</span>
            </div>
            <div class="meca-progress-bar">
              <div class="meca-progress-fill" :class="progressPct > 100 ? 'is-late' : ''" :style="{ width: Math.min(progressPct, 100) + '%' }"></div>
            </div>
            <div class="meca-chrono-row meca-chrono-row-sm">
              <span>{{ elapsedMin }}min écoulées</span>
              <span>{{ formatMinutes(activeRdv.temps_estime) }} estimées</span>
            </div>
            <div v-if="progressPct > 100" class="meca-late-warning">
              <AppIcon name="i-ri-error-warning-line" /> Dépassement +{{ elapsedMin - activeRdv.temps_estime }}min — intervention en retard
            </div>
          </div>

          <SectionAccordion
            title="Checkup Express"
            icon="i-ri-checkbox-circle-line"
            :badge="`${checkupDone}/${checkupItems.length} vérifiés`"
            v-model="sections.checkup"
          >
            <p class="meca-section-hint">Le rapport est enregistré dans le dossier atelier.</p>
            <CheckpointGrid :items="checkupItems" :values="checkup" @toggle="cycleCheckup" />
            <CheckpointPhotoPanel
              :items="checkupItems" :values="checkup" :photos-by-key="checkupPhotos"
              @add-photo="(key: string, file: File) => uploadCheckpointPhoto('checkup', key, file, activeRdv?.id)"
              @remove-photo="(key: string, id: number) => removeCheckpointPhoto('checkup', key, id)"
            />
            <div class="meca-section-actions">
              <AppButton variant="secondary" class="meca-btn-lg" icon="i-ri-save-line" label="Sauvegarder" :loading="persistingCheckup" @click="persistWorkshopReport()" />
            </div>
          </SectionAccordion>

          <SectionAccordion
            title="Essai routier atelier"
            icon="i-ri-motorbike-line"
            :badge="`${essaiFilledCount}/${essaiPoints.length} points`"
            v-model="sections.essai"
          >
            <div class="meca-form-grid">
              <div class="meca-field">
                <label>Km départ</label>
                <input v-model.number="essaiForm.kmDebut" type="number" class="form-input meca-input-lg" />
              </div>
              <div class="meca-field">
                <label>Km retour</label>
                <input v-model.number="essaiForm.kmFin" type="number" class="form-input meca-input-lg" />
              </div>
              <div class="meca-field">
                <label>Durée (min)</label>
                <input v-model.number="essaiForm.dureeMinutes" type="number" class="form-input meca-input-lg" />
              </div>
            </div>
            <CheckpointGrid :items="essaiPoints" :values="essaiForm.pointsControle" @toggle="cycleEssaiPoint" />
            <CheckpointPhotoPanel
              :items="essaiPoints" :values="essaiForm.pointsControle" :photos-by-key="essaiPhotos"
              @add-photo="(key: string, file: File) => uploadCheckpointPhoto('essai_routier', key, file, activeRdv?.id)"
              @remove-photo="(key: string, id: number) => removeCheckpointPhoto('essai_routier', key, id)"
            />
            <div v-if="essaiHasNok" class="meca-field meca-field-mt">
              <label>Actions correctives</label>
              <textarea v-model="essaiForm.actionsCorrectives" class="form-input meca-input-lg" rows="2" placeholder="Décrire les corrections effectuées…" />
            </div>
            <div class="meca-section-actions meca-section-actions-split">
              <span class="meca-section-hint">Minimum requis : km départ/retour, durée et 5 points renseignés.</span>
              <AppButton
                variant="primary" class="meca-btn-lg" icon="i-ri-motorbike-line"
                :label="savingRoadTest ? 'Validation…' : (essaiRoutierValide ? 'Essai validé' : 'Valider l’essai')"
                :disabled="savingRoadTest || essaiRoutierValide || !canValidateRoadTest"
                @click="saveActiveRoadTest"
              />
            </div>
          </SectionAccordion>

          <SectionAccordion
            title="Travaux supplémentaires"
            icon="i-ri-hammer-line"
            :badge="activeRdv.demandes_travaux_supp?.length ? String(activeRdv.demandes_travaux_supp.length) : undefined"
            v-model="sections.travaux"
          >
            <div v-if="activeRdv.demandes_travaux_supp?.length" class="meca-demande-list">
              <div v-for="demande in activeRdv.demandes_travaux_supp" :key="demande.id" class="meca-demande-row">
                <span>{{ demande.description }}</span>
                <span class="meca-badge" :style="demandeBadgeStyle(demande)">{{ demandeStatutLabel(demande.statut) }}</span>
              </div>
            </div>
            <div v-else class="meca-section-hint">Aucune demande complémentaire signalée.</div>

            <AppButton
              variant="secondary" class="meca-btn-lg meca-btn-report"
              :label="showNewDemande ? 'Annuler' : '+ Signaler un problème'"
              @click="showNewDemande = !showNewDemande"
            />
            <div v-if="showNewDemande" class="meca-demande-form">
              <textarea v-model="newDemande.description" class="form-input meca-input-lg" rows="2" placeholder="Description du problème…" />
              <div class="meca-form-grid">
                <input v-model.number="newDemande.prix_estime" type="number" class="form-input meca-input-lg" placeholder="Coût estimé (€)" />
                <input v-model.number="newDemande.temps_estime" type="number" class="form-input meca-input-lg" placeholder="Temps estimé (min)" />
              </div>
              <div class="meca-field-inline">
                <label>Urgence</label>
                <select v-model="newDemande.urgence" class="form-input meca-input-lg">
                  <option value="normal">Normal</option>
                  <option value="urgent">Urgent</option>
                </select>
              </div>
              <AppButton
                variant="primary" class="meca-btn-lg meca-btn-block"
                :label="submittingDemande ? 'Envoi…' : 'Envoyer pour validation'"
                :disabled="submittingDemande || !newDemande.description.trim()"
                @click="submitDemande"
              />
            </div>
          </SectionAccordion>

          <SectionAccordion title="Notes intervention" icon="i-ri-file-text-line" v-model="sections.notes">
            <textarea v-model="interventionNotes" class="form-input meca-input-lg" rows="3" placeholder="Notes techniques, observations…" />
            <div class="meca-section-actions">
              <AppButton variant="secondary" class="meca-btn-lg" :label="savingNotes ? 'Sauvegarde…' : 'Sauvegarder'" :disabled="savingNotes" @click="saveInterventionNotes" />
            </div>
          </SectionAccordion>
        </div>
      </div>

      <!-- ONGLET : À faire -->
      <div v-show="activeTab === 'faire'" class="meca-card">
        <div v-if="!todoRdvs.length" class="meca-empty">Toutes les interventions sont terminées</div>
        <div v-else class="meca-todo-list">
          <div v-for="rdv in todoRdvs" :key="rdv.id" class="meca-todo-row">
            <div>
              <p class="meca-todo-name">{{ rdv.heure_debut?.slice(0, 5) }} — {{ rdv.client_nom }}</p>
              <p class="meca-todo-sub">{{ rdv.vehicule_info }} — {{ rdv.type_intervention }}</p>
              <p v-if="rdv.temps_estime" class="meca-todo-sub"><AppIcon name="i-ri-timer-line" /> {{ formatMinutes(rdv.temps_estime) }}</p>
            </div>
            <div class="meca-todo-actions">
              <StatusBadge :status="rdv.status" />
              <AppButton v-if="rdv.status === 'reception'" variant="primary" class="meca-btn-lg" icon="i-ri-tools-line" label="Démarrer" @click="startWork(rdv.id)" />
            </div>
          </div>
        </div>
      </div>

      <!-- ONGLET : Terminés -->
      <div v-show="activeTab === 'termines'" class="meca-card">
        <div v-if="!doneRdvs.length" class="meca-empty">Aucune intervention terminée aujourd'hui.</div>
        <div v-else class="meca-done-list">
          <div v-for="rdv in doneRdvs" :key="rdv.id" class="meca-done-row" :class="{ 'is-past': rdv.status !== 'termine' }">
            <div class="meca-done-info">
              <span class="meca-done-check"><AppIcon name="i-ri-checkbox-circle-line" /></span>
              <span>{{ rdv.heure_debut?.slice(0, 5) }} — {{ rdv.client_nom }} · {{ rdv.type_intervention }}</span>
              <span v-if="rdv.status === 'termine'" class="meca-badge meca-badge-warning">Rapport à compléter</span>
            </div>
            <div class="meca-done-actions">
              <AppButton v-if="rdv.status === 'termine'" variant="secondary" class="meca-btn-lg" icon="i-ri-clipboard-line" label="Rapport" @click="openRapport(rdv.id)" />
              <button class="meca-link-btn" @click="openRdvDetail(rdv)">Voir →</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Rapport d'intervention panel -->
    <div v-if="rapportRdvId" class="meca-modal-overlay" @click.self="closeRapport">
      <div class="meca-modal">
        <div class="meca-modal-head">
          <h2><AppIcon name="i-ri-clipboard-line" /> Rapport d'intervention</h2>
          <button aria-label="Fermer le rapport" class="meca-modal-close" @click="closeRapport"><AppIcon name="i-ri-close-line" /></button>
        </div>

        <div v-if="rapportLoading" class="meca-loading">Chargement…</div>
        <div v-else-if="rapportError" class="meca-error-panel">{{ rapportError }}</div>

        <div v-else-if="rapport">
          <div v-if="rapport.is_signed_by_both" class="meca-signed-panel">
            <div class="meca-signed-icon"><AppIcon name="i-ri-checkbox-circle-line" /></div>
            <p>Rapport signé par les deux parties</p>
            <a :href="`${apiBase.replace('/api', '')}/api/rapport/${rapport.id}/pdf`" target="_blank" class="meca-pdf-link">
              <AppIcon name="i-ri-file-text-line" /> Télécharger PDF
            </a>
          </div>

          <div v-else class="meca-rapport-form">
            <div class="meca-field">
              <label>Travaux réalisés <span class="meca-required">*</span></label>
              <textarea v-model="rapportForm.travauxRealises" class="form-input meca-input-lg" rows="4" placeholder="Décrire précisément les travaux effectués…" :disabled="!!rapport.signature_mecanicien" />
            </div>

            <div class="meca-field">
              <label>Alertes importantes</label>
              <textarea v-model="rapportForm.alertes" class="form-input meca-input-lg" rows="2" placeholder="Points à surveiller, anomalies, recommandations urgentes…" :disabled="!!rapport.signature_mecanicien" />
            </div>

            <div class="meca-field">
              <label>Recommandations prochaine visite <span class="meca-required">*</span></label>
              <textarea v-model="rapportForm.recommandations" class="form-input meca-input-lg" rows="2" placeholder="Prochaine révision, pièces à prévoir…" :disabled="!!rapport.signature_mecanicien" />
            </div>

            <div class="meca-form-grid">
              <div class="meca-field">
                <label>Km restitution</label>
                <input v-model.number="rapportForm.kilometrageRestitution" type="number" class="form-input meca-input-lg" placeholder="ex: 24500" :disabled="!!rapport.signature_mecanicien" />
              </div>
            </div>

            <div class="meca-panel-box">
              <label class="meca-checkbox-label">
                <input type="checkbox" :checked="rapportForm.vidangePrevue" :disabled="!!rapport.signature_mecanicien" @change="toggleVidangePrevue" />
                Prochaine vidange à prévoir
              </label>
              <div v-if="rapportForm.vidangePrevue" class="meca-form-grid meca-form-grid-mt">
                <div class="meca-field">
                  <label>Vers (km)</label>
                  <input v-model.number="rapportForm.prochaineRevisionKm" type="number" class="form-input meca-input-lg" :disabled="!!rapport.signature_mecanicien" />
                </div>
                <div class="meca-field">
                  <label>Ou avant le</label>
                  <input v-model="rapportForm.prochaineRevisionDate" type="date" class="form-input meca-input-lg" :disabled="!!rapport.signature_mecanicien" />
                </div>
              </div>
            </div>

            <div class="meca-panel-box meca-panel-box-info">
              <div class="meca-panel-box-title"><AppIcon name="i-ri-tools-line" /> Entretien des fluides recommandé</div>
              <div>Huile moteur — <strong>tous les ans</strong></div>
              <div>Liquide de frein — <strong>tous les 2 ans</strong></div>
              <div>Liquide de refroidissement — <strong>tous les 3 ans</strong></div>
            </div>

            <div class="meca-panel-box">
              <div class="meca-panel-box-title"><AppIcon name="i-ri-motorbike-line" /> Essai routier</div>
              <div class="meca-form-grid">
                <div class="meca-field">
                  <label>Km départ</label>
                  <input v-model.number="essaiForm.kmDebut" type="number" class="form-input meca-input-lg" :disabled="!!rapport.signature_mecanicien" />
                </div>
                <div class="meca-field">
                  <label>Km retour</label>
                  <input v-model.number="essaiForm.kmFin" type="number" class="form-input meca-input-lg" :disabled="!!rapport.signature_mecanicien" />
                </div>
                <div class="meca-field">
                  <label>Durée (min)</label>
                  <input v-model.number="essaiForm.dureeMinutes" type="number" class="form-input meca-input-lg" :disabled="!!rapport.signature_mecanicien" />
                </div>
              </div>
              <CheckpointGrid :items="essaiPoints" :values="essaiForm.pointsControle" :disabled="!!rapport.signature_mecanicien" @toggle="cycleEssaiPoint" />
              <CheckpointPhotoPanel
                :items="essaiPoints" :values="essaiForm.pointsControle" :photos-by-key="essaiPhotos"
                :disabled="!!rapport.signature_mecanicien"
                @add-photo="(key: string, file: File) => uploadCheckpointPhoto('essai_routier', key, file, rapportRdvId ?? undefined)"
                @remove-photo="(key: string, id: number) => removeCheckpointPhoto('essai_routier', key, id)"
              />
              <div v-if="essaiHasNok" class="meca-field meca-field-mt">
                <label>Actions correctives</label>
                <textarea v-model="essaiForm.actionsCorrectives" class="form-input meca-input-lg" rows="2" placeholder="Décrire les corrections effectuées…" :disabled="!!rapport.signature_mecanicien" />
              </div>
            </div>

            <AppButton
              v-if="!rapport.signature_mecanicien"
              variant="primary" class="meca-btn-lg meca-btn-block"
              :label="rapportSaving ? 'Enregistrement…' : 'Enregistrer le rapport'"
              :disabled="rapportSaving"
              @click="saveRapport"
            />

            <div v-if="!rapport.signature_mecanicien" class="meca-sign-block">
              <p class="meca-sign-title"><AppIcon name="i-ri-quill-pen-line" /> Signature mécanicien</p>
              <p class="meca-section-hint">En signant, vous certifiez que les travaux sont réalisés et l'essai routier effectué.</p>
              <canvas
                ref="sigRapportCanvas"
                class="meca-sign-canvas"
                @pointerdown="startRapportDraw" @pointermove="drawRapport" @pointerup="endRapportDraw" @pointerleave="endRapportDraw"
              ></canvas>
              <div class="meca-sign-actions">
                <AppButton variant="secondary" class="meca-btn-lg" icon="i-ri-eraser-line" label="Effacer" @click="clearRapportSig" />
                <AppButton
                  variant="primary" class="meca-btn-lg meca-btn-sign"
                  :label="rapportSigning ? 'Signature…' : 'Signer le rapport'"
                  :disabled="!rapportSigDrawn || rapportSigning"
                  @click="signRapport"
                />
              </div>
              <div v-if="rapportSignError" class="meca-error-panel">{{ rapportSignError }}</div>
            </div>

            <div v-else class="meca-signed-inline">
              <AppIcon name="i-ri-checkbox-circle-line" /> Rapport signé par le mécanicien — en attente de signature client lors de la restitution.
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="activeTab === 'cours' && activeRdv" class="meca-sticky-spacer" aria-hidden="true"></div>

    <div v-if="activeTab === 'cours' && activeRdv" class="meca-sticky-bar">
      <div class="meca-sticky-bar-inner">
        <AppButton
          v-if="activeRdv.statut === 'en_cours'"
          variant="secondary" class="meca-btn-lg" icon="i-ri-pause-line" label="Pause"
          :loading="pausing" @click="pauseWork"
        />
        <AppButton
          v-if="activeRdv.statut === 'en_pause'"
          variant="secondary" class="meca-btn-lg" icon="i-ri-play-line" label="Reprendre"
          :loading="resuming" @click="resumeWork"
        />
        <AppButton
          v-if="!essaiRoutierValide"
          variant="primary" class="meca-btn-lg" icon="i-ri-motorbike-line"
          :label="savingRoadTest ? 'Validation…' : 'Valider l’essai'"
          :disabled="savingRoadTest || !canValidateRoadTest"
          @click="saveActiveRoadTest"
        />
        <AppButton
          variant="primary" class="meca-btn-lg meca-btn-finish" icon="i-ri-checkbox-circle-line" label="Terminer"
          :loading="finishing" :disabled="!essaiRoutierValide" @click="finishWork"
        />
      </div>
    </div>

    <MotoHistoriqueModal v-model:open="showHistorique" :vehicule-id="activeRdv?.vehicule_id ?? null" />
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'kiosk' })

const api = useApi()
const config = useRuntimeConfig()
const apiBase = config.public.apiBase as string
const rdvStore = useRdvStore()
const toast = useToast()
const auth = useAuth()
const { open: openRdvDetail } = useRdvDetailModal()
const loading = ref(true)
const loadError = ref('')
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

// --- Navigation par onglets ---
const activeTab = ref<'cours' | 'faire' | 'termines'>('cours')
const showHistorique = ref(false)

// --- Résilience réseau ---
const offlineQueue = useOfflineQueue()
const lastFailedAction = ref<{ label: string; retry: () => void } | null>(null)

function offerRetry(label: string, retry: () => void) {
  lastFailedAction.value = { label, retry }
}

function clearFailedAction(label: string) {
  if (lastFailedAction.value?.label === label) lastFailedAction.value = null
}

function retryLastFailedAction() {
  lastFailedAction.value?.retry()
}

// --- Sections repliables de l'intervention active ---
const sections = reactive({
  checkup: false,
  essai: false,
  travaux: false,
  notes: false,
})

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
// Un simple tap sans tracé ne doit PAS activer « Signer le rapport »
const MIN_SIG_DISTANCE = 30
let sigDrawnDistance = 0

const rapportForm = reactive({
  travauxRealises: '',
  alertes: '',
  recommandations: '',
  kilometrageRestitution: null as number | null,
  vidangePrevue: false,
  prochaineRevisionKm: null as number | null,
  prochaineRevisionDate: '' as string,
})

// Intervalle par défaut (config atelier), pour préremplir la suggestion de
// prochaine vidange sans obliger le mécanicien à calculer lui-même.
const vidangeIntervalleKm = ref(7000)
const vidangeIntervalleMois = ref(12)

async function chargerIntervalleVidange() {
  try {
    const cfg = await api.get('/config')
    vidangeIntervalleKm.value = cfg?.vidange_intervalle_km ?? 7000
    vidangeIntervalleMois.value = cfg?.vidange_intervalle_mois ?? 12
  } catch {
    // Repli silencieux sur les valeurs par défaut : ce n'est qu'une suggestion.
  }
}

function suggererProchaineVidange() {
  if (rapportForm.kilometrageRestitution) {
    rapportForm.prochaineRevisionKm = rapportForm.kilometrageRestitution + vidangeIntervalleKm.value
  }
  const date = new Date()
  date.setMonth(date.getMonth() + vidangeIntervalleMois.value)
  rapportForm.prochaineRevisionDate = date.toISOString().slice(0, 10)
}

function toggleVidangePrevue() {
  rapportForm.vidangePrevue = !rapportForm.vidangePrevue
  if (rapportForm.vidangePrevue && !rapportForm.prochaineRevisionKm && !rapportForm.prochaineRevisionDate) {
    suggererProchaineVidange()
  }
}

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
  rapportForm.prochaineRevisionKm = r.prochaineRevisionKm ?? null
  rapportForm.prochaineRevisionDate = r.prochaineRevisionDate ? String(r.prochaineRevisionDate).slice(0, 10) : ''
  rapportForm.vidangePrevue = Boolean(rapportForm.prochaineRevisionKm || rapportForm.prochaineRevisionDate)
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
    await loadCheckpointPhotos(rdvId)
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

// Renvoie `false` si l'enregistrement a été mis en file (hors connexion) ou a
// échoué : `signRapport` s'en sert pour ne jamais signer par-dessus un
// rapport qui n'a pas réellement atteint le serveur.
async function saveRapport(): Promise<boolean> {
  if (!rapport.value) return false
  rapportSaving.value = true
  try {
    const outcome = await offlineQueue.runIdempotent(
      'Enregistrement du rapport',
      () => api.patch(`/mecanicien/me/rapport/${rapport.value.id}`, {
        travaux_realises: rapportForm.travauxRealises,
        alertes: rapportForm.alertes ? rapportForm.alertes.split('\n').map((s: string) => s.trim()).filter(Boolean) : [],
        recommandations: rapportForm.recommandations,
        kilometrage_restitution: rapportForm.kilometrageRestitution,
        prochaine_revision_km: rapportForm.vidangePrevue ? rapportForm.prochaineRevisionKm : null,
        prochaine_revision_date: rapportForm.vidangePrevue ? rapportForm.prochaineRevisionDate : null,
      }),
    )

    if (outcome.queued) {
      toast.add({ title: 'Hors connexion', description: 'Le rapport sera enregistré automatiquement au retour du réseau.', color: 'warning' })
      return false
    }

    await fetchMyRdvs()
    toast.add({ title: 'Rapport enregistré', color: 'success' })
    return true
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e.message, color: 'error' })
    return false
  } finally {
    rapportSaving.value = false
  }
}

async function signRapport() {
  if (!rapport.value || !sigRapportCanvas.value || !rapportSigDrawn.value) return
  rapportSigning.value = true
  rapportSignError.value = ''
  try {
    const saved = await saveRapport()
    if (!saved) {
      rapportSignError.value = "Le rapport n'a pas pu être enregistré (connexion). Réessayez avant de signer."
      return
    }
    const sig = sigRapportCanvas.value.toDataURL('image/png')
    const updated = await api.post(`/mecanicien/me/sign/${rapport.value.id}`, { signature: sig })
    rapport.value = updated
    await fetchMyRdvs()
    toast.add({ title: 'Intervention signée', color: 'success' })
  } catch (e: any) {
    if (offlineQueue.isNetworkError(e)) {
      offerRetry('Signer le rapport', signRapport)
      rapportSignError.value = 'Connexion perdue pendant la signature. Réessayez dès que la connexion revient.'
    } else {
      rapportSignError.value = e.message || 'Erreur lors de la signature'
    }
  } finally {
    rapportSigning.value = false
  }
}

// Signature canvas helpers
function startRapportDraw(e: PointerEvent) {
  sigRapportDrawing = true
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
  ctx.strokeStyle = 'var(--surface-2)'; ctx.lineWidth = 2.5; ctx.lineCap = 'round'; ctx.stroke()
  sigDrawnDistance += Math.hypot(x - sigLastX, y - sigLastY)
  sigLastX = x; sigLastY = y
  if (sigDrawnDistance >= MIN_SIG_DISTANCE) rapportSigDrawn.value = true
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
  sigDrawnDistance = 0
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

// --- Photos par point NOK (checkup + essai routier) ---
type CheckpointPhoto = { id: number; url: string }
const checkupPhotos = reactive<Record<string, CheckpointPhoto[]>>({})
const essaiPhotos = reactive<Record<string, CheckpointPhoto[]>>({})

function clearCheckpointPhotos() {
  Object.keys(checkupPhotos).forEach((k) => { delete checkupPhotos[k] })
  Object.keys(essaiPhotos).forEach((k) => { delete essaiPhotos[k] })
}

async function loadCheckpointPhotos(rdvId: number) {
  clearCheckpointPhotos()
  try {
    const photos = await api.get<any[]>(`/photos/rdv/${rdvId}`)
    for (const p of Array.isArray(photos) ? photos : []) {
      const key = p.checkpoint_key
      if (!key) continue
      const target = p.checkpoint_source === 'checkup' ? checkupPhotos : p.checkpoint_source === 'essai_routier' ? essaiPhotos : null
      if (!target) continue
      if (!target[key]) target[key] = []
      target[key].push({ id: p.id, url: p.url })
    }
  } catch {
    // Les photos ne sont qu'un complément visuel : une panne réseau ici ne
    // doit pas bloquer l'affichage du reste de l'intervention.
  }
}

async function uploadCheckpointPhoto(source: 'checkup' | 'essai_routier', key: string, file: File, rdvId?: number) {
  if (!rdvId) return
  const label = (source === 'checkup' ? checkupItems : essaiPoints).find(i => i.key === key)?.label ?? key
  const formData = new FormData()
  formData.append('photo', file)
  formData.append('rendez_vous_id', String(rdvId))
  formData.append('type', 'probleme')
  formData.append('checkpoint_source', source)
  formData.append('checkpoint_key', key)
  // Légende du rapport/PDF (PdfService::appendStoredPhoto priorise la
  // description) : sans elle, chaque photo s'affiche sous "Photo atelier"
  // plutôt que le point qu'elle illustre.
  formData.append('description', label)
  try {
    const result = await api.upload<{ id: number; filename: string }>('/photos/upload', formData)
    const target = source === 'checkup' ? checkupPhotos : essaiPhotos
    if (!target[key]) target[key] = []
    target[key].push({ id: result.id, url: `/api/photos/file/${result.filename}` })
  } catch (e: any) {
    toast.add({ title: 'Erreur photo', description: e.message, color: 'error' })
  }
}

async function removeCheckpointPhoto(source: 'checkup' | 'essai_routier', key: string, photoId: number) {
  const target = source === 'checkup' ? checkupPhotos : essaiPhotos
  try {
    await api.del(`/photos/${photoId}`)
    if (target[key]) target[key] = target[key].filter(p => p.id !== photoId)
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e.message, color: 'error' })
  }
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
  if (activeRdv.value && progressPct.value > 100) return `Intervention en cours en retard — terminer rapidement`
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

// Renvoie `true` seulement si la sauvegarde a réellement atteint le serveur
// (pas mise en file, pas en erreur) : `saveInterventionNotes` s'en sert pour
// ne pas annoncer "sauvegardé" alors que ce n'est qu'en attente de réseau.
async function persistWorkshopReport(showToast = true): Promise<boolean> {
  if (!activeRdv.value) return false
  persistingCheckup.value = true
  try {
    const orId = activeOrId.value
    if (!orId) {
      toast.add({ title: 'OR introuvable', description: "L'ordre de réparation n'a pas encore été créé par la réception.", color: 'warning' })
      return false
    }

    const checkupSnapshot = { ...checkup }
    const notesSnapshot = interventionNotes.value
    const outcome = await offlineQueue.runIdempotent(
      'Rapport atelier (checkup/notes)',
      () => api.patch(`/mecanicien/me/rapport/${orId}`, {
        mechanic_checkup: checkupSnapshot,
        mechanic_notes: notesSnapshot,
      }),
    )

    if (outcome.queued) {
      toast.add({ title: 'Hors connexion', description: 'La sauvegarde sera renvoyée automatiquement au retour du réseau.', color: 'warning' })
      return false
    }

    myRdvs.value = myRdvs.value.map((rdv: any) => rdv.id === activeRdv.value?.id
      ? {
          ...rdv,
          or_mechanic_checkup: checkupSnapshot,
          or_mechanic_notes: notesSnapshot,
        }
      : rdv)

    if (showToast) {
      toast.add({ title: 'Rapport atelier sauvegardé', color: 'success' })
    }
    return true
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e.message, color: 'error' })
    return false
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
    clearFailedAction('Valider l’essai routier')
    toast.add({ title: result?.valide ? 'Essai routier validé' : 'Essai routier enregistré', color: 'success' })
  } catch (e: any) {
    if (offlineQueue.isNetworkError(e)) {
      offerRetry('Valider l’essai routier', saveActiveRoadTest)
      toast.add({ title: 'Connexion perdue', description: 'L’essai routier n’a pas été envoyé. Réessayez dès que la connexion revient.', color: 'error' })
    } else {
      toast.add({ title: 'Erreur essai routier', description: e.message, color: 'error' })
    }
  } finally {
    savingRoadTest.value = false
  }
}

async function startWork(id: number) {
  try {
    await rdvStore.transitionRdv(id, 'start_travail')
    await fetchMyRdvs()
    activeTab.value = 'cours'
    clearFailedAction('Démarrer')
    toast.add({ title: 'Travaux démarrés', color: 'success' })
  } catch (e: any) {
    if (offlineQueue.isNetworkError(e)) {
      offerRetry('Démarrer', () => startWork(id))
      toast.add({ title: 'Connexion perdue', description: 'Le démarrage n’a pas été envoyé. Réessayez dès que la connexion revient.', color: 'error' })
    } else {
      toast.add({ title: 'Erreur', description: e.message, color: 'error' })
    }
  }
}

async function pauseWork() {
  if (!activeRdv.value) return
  pausing.value = true
  try {
    await rdvStore.transitionRdv(activeRdv.value.id, 'pause_travail')
    await fetchMyRdvs()
    clearFailedAction('Mettre en pause')
    toast.add({ title: 'Intervention mise en pause', color: 'warning' })
  } catch (e: any) {
    if (offlineQueue.isNetworkError(e)) {
      offerRetry('Mettre en pause', pauseWork)
      toast.add({ title: 'Connexion perdue', description: 'La mise en pause n’a pas été envoyée. Réessayez dès que la connexion revient.', color: 'error' })
    } else {
      toast.add({ title: 'Erreur', description: e.message, color: 'error' })
    }
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
    clearFailedAction('Reprendre')
    toast.add({ title: 'Intervention reprise', color: 'success' })
  } catch (e: any) {
    if (offlineQueue.isNetworkError(e)) {
      offerRetry('Reprendre', resumeWork)
      toast.add({ title: 'Connexion perdue', description: 'La reprise n’a pas été envoyée. Réessayez dès que la connexion revient.', color: 'error' })
    } else {
      toast.add({ title: 'Erreur', description: e.message, color: 'error' })
    }
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
    clearFailedAction('Terminer')
    toast.add({ title: 'Intervention terminée', color: 'success' })
    openRapport(terminatedId)
  } catch (e: any) {
    if (offlineQueue.isNetworkError(e)) {
      offerRetry('Terminer', finishWork)
      toast.add({ title: 'Connexion perdue', description: 'La clôture n’a pas été envoyée. Réessayez dès que la connexion revient.', color: 'error' })
    } else {
      toast.add({ title: 'Erreur', description: e.message, color: 'error' })
    }
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
    en_attente: { bg: 'var(--surface-3)', color: 'var(--content-3)' },
    en_attente_validation: { bg: 'var(--warning-soft)', color: 'var(--warning-content)' },
    en_attente_decision_client: { bg: 'var(--info-soft)', color: 'var(--info-content)' },
    accepte: { bg: 'var(--success-soft)', color: 'var(--success-content)' },
    refuse: { bg: 'var(--error-soft)', color: 'var(--error-content)' },
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
    clearFailedAction('Envoyer la demande complémentaire')
    toast.add({ title: 'Demande envoyée', description: 'La réception va valider et contacter le client.', color: 'success' })
  } catch (e: any) {
    if (offlineQueue.isNetworkError(e)) {
      offerRetry('Envoyer la demande complémentaire', submitDemande)
      toast.add({ title: 'Connexion perdue', description: 'La demande n’a pas été envoyée. Réessayez dès que la connexion revient.', color: 'error' })
    } else {
      toast.add({ title: 'Erreur', description: e.message, color: 'error' })
    }
  } finally {
    submittingDemande.value = false
  }
}

async function saveInterventionNotes() {
  if (!activeRdv.value) return
  savingNotes.value = true
  try {
    const saved = await persistWorkshopReport(false)
    if (saved) {
      toast.add({ title: 'Notes sauvegardées', color: 'success' })
    }
  } finally {
    savingNotes.value = false
  }
}

async function fetchMyRdvs() {
  const today = todayLocalISO()
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
  // Ne réhydrate depuis le serveur QUE sur changement de RDV : chaque refetch
  // (pause, demande complémentaire…) écrasait silencieusement le checkup et
  // les notes en cours de saisie.
  if (next?.id !== prev?.id) {
    resetEssaiForm()
    applySavedWorkshopReport()
    if (next?.id) loadCheckpointPhotos(next.id)
    // Rouvre l'essai routier par défaut s'il reste à faire : c'est l'étape
    // bloquante avant "Terminer", elle ne doit pas se cacher derrière un tap.
    sections.checkup = false
    sections.essai = !essaiRoutierValide.value
    sections.travaux = false
    sections.notes = false
    if (next) activeTab.value = 'cours'
  }
})

watch(rapportRdvId, (id) => {
  if (id) {
    nextTick(() => {
      initCanvas()
    })
  }
})

async function reload() {
  loading.value = true
  loadError.value = ''
  try {
    await fetchMyRdvs()
    applySavedWorkshopReport()
  } catch (e: any) {
    loadError.value = e?.data?.error === 'MECANICIEN_NOT_LINKED'
      ? "Votre compte n'est pas relié à un profil mécanicien. Contactez un administrateur."
      : (e?.data?.error || e?.message || "Impossible de charger vos rendez-vous. Vérifiez la connexion puis réessayez.")
    myRdvs.value = []
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  await reload()
  chronoTimer = setInterval(() => { now.value = Date.now() }, 1000)
  chargerIntervalleVidange()
})

onUnmounted(() => {
  if (chronoTimer) clearInterval(chronoTimer)
})
</script>

<style scoped>
/* ==========================================================================
   Espace Mécanicien — mode kiosque tactile (iPad)
   Cibles ≥44px, typographie agrandie, navigation par onglets + sections
   repliables pour limiter le scroll sur un écran d'atelier.
   ========================================================================== */

.meca-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
  margin-bottom: 20px;
}

.meca-header-id {
  display: flex;
  align-items: center;
  gap: 14px;
}

.meca-avatar {
  width: 52px;
  height: 52px;
  border-radius: 50%;
  background: var(--info-soft);
  border: 2px solid var(--info);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 19px;
  color: var(--info-content);
  flex-shrink: 0;
}

.meca-page-title {
  font-family: var(--font-display);
  font-size: 22px;
  font-weight: 700;
  color: var(--content-1);
}

.meca-date {
  font-size: 14px;
  color: var(--content-3);
  text-transform: capitalize;
}

.meca-kpis {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(84px, 1fr));
  gap: 10px;
}

.meca-kpi {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 8px 16px;
  border-radius: 12px;
  background: var(--surface-1);
  border: 1px solid var(--border-2);
  min-width: 84px;
}

.meca-kpi-label {
  font-size: 11px;
  color: var(--content-3);
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.meca-kpi-value {
  font-size: 22px;
  font-weight: 800;
  color: var(--content-1);
  font-variant-numeric: tabular-nums;
}

.meca-loading {
  display: flex;
  justify-content: center;
  padding: 48px;
  color: var(--content-3);
  font-size: 15px;
}

.meca-error-panel {
  margin: 24px 0;
  padding: 18px;
  border-radius: 12px;
  background: var(--error-soft);
  border: 1px solid var(--error);
  text-align: center;
  color: var(--error-content);
  font-size: 14px;
}

.meca-error-panel-head {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-bottom: 10px;
  font-size: 16px;
  font-weight: 600;
}

.meca-banner {
  margin-bottom: 16px;
  padding: 16px;
  border-radius: 12px;
}

.meca-banner-error { background: var(--error-soft); border: 1px solid var(--error); }
.meca-banner-accent { background: var(--accent-soft); border: 1px solid var(--accent); }

.meca-banner-head {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 6px;
  font-size: 14px;
  font-weight: 700;
}
.meca-banner-error .meca-banner-head { color: var(--error-content); }
.meca-banner-accent .meca-banner-head { color: var(--accent-content); }
.meca-banner p { font-size: 14px; color: var(--content-2); }
.meca-banner .meca-btn-lg { margin-top: 10px; }

/* === Onglets === */
.meca-tabs {
  display: flex;
  gap: 8px;
  margin-bottom: 18px;
  overflow-x: auto;
}

.meca-tab {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: 48px;
  padding: 0 18px;
  border-radius: 12px;
  border: 1px solid var(--border-2);
  background: var(--surface-1);
  color: var(--content-2);
  font-family: inherit;
  font-size: 15px;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
}

.meca-tab.is-active {
  background: var(--accent-soft);
  border-color: var(--accent-graphic);
  color: var(--accent-content);
}

.meca-tab-count {
  min-width: 22px;
  height: 22px;
  padding: 0 6px;
  border-radius: 999px;
  background: var(--overlay-hover);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
}

.meca-tab-dot {
  width: 9px;
  height: 9px;
  border-radius: 50%;
  background: var(--warning);
}

.meca-empty {
  padding: 32px 16px;
  text-align: center;
  color: var(--content-3);
  font-size: 15px;
}

.meca-card {
  background: var(--surface-1);
  border: 1px solid var(--border-2);
  border-radius: 16px;
  padding: 20px;
}

.meca-active-card {
  border-color: var(--warning);
}

.meca-active-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 12px;
}

.meca-active-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 17px;
  font-weight: 700;
  color: var(--warning-content);
}

.meca-active-head-links {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.meca-call-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 44px;
  padding: 0 14px;
  border-radius: 10px;
  background: var(--overlay-soft);
  color: var(--content-2);
  border: none;
  font-family: inherit;
  cursor: pointer;
  text-decoration: none;
  font-size: 14px;
  font-weight: 600;
}

.meca-badge-row {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  margin-bottom: 14px;
}

.meca-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 6px 12px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 700;
}

.meca-badge-neutral { background: var(--surface-3); color: var(--content-3); }
.meca-badge-success { background: var(--success-soft); color: var(--success-content); }
.meca-badge-warning { background: var(--warning-soft); color: var(--warning-content); }
.meca-badge-error { background: var(--error-soft); color: var(--error-content); }

.meca-action-row {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  margin-bottom: 18px;
}

.meca-btn-finish {
  margin-left: auto;
}

.meca-info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 14px;
  font-size: 14px;
  color: var(--content-2);
  margin-bottom: 8px;
}

.meca-info-label {
  color: var(--content-3);
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  display: block;
  margin-bottom: 2px;
}

.meca-motif {
  margin-top: 12px;
  font-size: 14px;
}
.meca-motif p { color: var(--content-2); margin-top: 2px; }

.meca-reception {
  margin-top: 16px;
  padding: 14px;
  border-radius: 12px;
  background: var(--info-soft);
  border: 1px solid var(--info);
}

.meca-reception-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
  margin-bottom: 10px;
  font-size: 14px;
  font-weight: 700;
  color: var(--info-content);
}

.meca-reception-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 10px;
  font-size: 13px;
  color: var(--content-2);
}

.meca-chip-row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 12px;
}

.meca-chip {
  padding: 6px 12px;
  border-radius: 999px;
  font-size: 12px;
  background: var(--overlay-hover);
  color: var(--content-2);
}

.meca-reception-obs {
  margin-top: 12px;
  font-size: 13px;
  color: var(--content-2);
}

.meca-chrono {
  margin-top: 18px;
  padding-top: 16px;
  border-top: 1px solid var(--border-2);
}

.meca-chrono-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 13px;
  color: var(--content-3);
  margin-bottom: 6px;
}

.meca-chrono-row-sm { font-size: 12px; }

.meca-chrono-value {
  font-family: monospace;
  font-size: 22px;
  font-weight: 700;
  color: var(--accent-content);
}
.meca-chrono-value.is-late, .meca-chrono-row-sm .is-late { color: var(--error-content); }

.meca-progress-bar {
  background: var(--surface-2);
  border-radius: 8px;
  height: 12px;
  overflow: hidden;
}

.meca-progress-fill {
  height: 100%;
  background: var(--accent);
  border-radius: 8px;
  transition: width 1s ease;
}
.meca-progress-fill.is-late { background: var(--error); }

.meca-late-warning {
  margin-top: 10px;
  padding: 10px 14px;
  border-radius: 10px;
  background: var(--error-soft);
  border: 1px solid var(--error);
  font-size: 13px;
  color: var(--error-content);
}

.meca-section-hint {
  font-size: 13px;
  color: var(--content-3);
  margin-bottom: 10px;
}

.meca-section-actions {
  display: flex;
  margin-top: 14px;
}

.meca-btn-report {
  margin-top: 14px;
}

.meca-section-actions-split {
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.meca-form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 12px;
  margin-bottom: 12px;
}

.meca-form-grid-mt { margin-top: 12px; margin-bottom: 0; }

.meca-field label {
  font-size: 12px;
  color: var(--content-3);
  font-weight: 600;
  display: block;
  margin-bottom: 4px;
}

.meca-field-mt { margin-top: 10px; }

.meca-field-inline {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 4px;
}
.meca-field-inline label { font-size: 13px; color: var(--content-3); white-space: nowrap; }
.meca-field-inline select { max-width: 200px; }

.meca-input-lg {
  min-height: 46px;
  font-size: 15px;
}

.meca-demande-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-bottom: 12px;
}

.meca-demande-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 12px 14px;
  border-radius: 10px;
  background: var(--overlay-soft);
  border: 1px solid var(--border-2);
  font-size: 14px;
  color: var(--content-2);
}

.meca-demande-form {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-top: 14px;
}

.meca-btn-block {
  width: 100%;
}

.meca-todo-list, .meca-done-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.meca-todo-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  padding: 16px;
  border-radius: 12px;
  border: 1px solid var(--border-2);
  background: var(--overlay-soft);
}

.meca-todo-name {
  font-weight: 700;
  color: var(--content-1);
  font-size: 15px;
}

.meca-todo-sub {
  font-size: 13px;
  color: var(--content-3);
  margin-top: 2px;
}

.meca-todo-actions {
  display: flex;
  align-items: center;
  gap: 10px;
}

.meca-done-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  padding: 14px 16px;
  border-radius: 12px;
  border: 1px solid var(--border-2);
  font-size: 14px;
}
.meca-done-row.is-past { opacity: 0.7; }

.meca-done-info {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
  color: var(--content-2);
}

.meca-done-check { color: var(--success-content); font-size: 18px; }

.meca-done-actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

.meca-link-btn {
  min-height: 44px;
  padding: 0 8px;
  color: var(--accent-content);
  font-size: 14px;
  background: none;
  border: none;
  cursor: pointer;
  font-weight: 700;
}

/* === Boutons tactiles agrandis (surchargent `.btn` de main.css) ===
   Le composant `AppButton` rend un unique élément racine : le sélecteur
   scoped du parent s'applique directement à cette racine, sans `:deep()`. */
.meca-btn-lg {
  min-height: 48px;
  padding: 0 20px;
  font-size: 15px;
}

/* === Barre d'action collante ===
   Terminer/Valider l'essai restent joignables même quand une section
   repliable (checkup, essai routier…) a été ouverte plus haut dans la carte
   et qu'il faudrait sinon remonter tout en haut pour les atteindre. */
.meca-sticky-spacer {
  height: 76px;
}

.meca-sticky-bar {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 40;
  background: var(--surface-1);
  border-top: 1px solid var(--border-2);
  box-shadow: var(--elevation-2);
  padding: 10px max(16px, env(safe-area-inset-right)) max(10px, env(safe-area-inset-bottom)) max(16px, env(safe-area-inset-left));
}

.meca-sticky-bar-inner {
  max-width: 1100px;
  margin: 0 auto;
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

/* === Modale Rapport === */
.meca-modal-overlay {
  position: fixed;
  inset: 0;
  background: var(--scrim);
  z-index: 50;
  overflow-y: auto;
  padding: 16px;
}

.meca-modal {
  max-width: 680px;
  margin: 0 auto;
  background: var(--surface-1);
  border: 1px solid var(--border-1);
  border-radius: 16px;
  padding: 24px;
}

.meca-modal-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
}
.meca-modal-head h2 {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 19px;
  font-weight: 700;
  color: var(--content-1);
}

.meca-modal-close {
  min-width: 44px;
  min-height: 44px;
  font-size: 20px;
  color: var(--content-3);
  background: none;
  border: none;
  cursor: pointer;
  border-radius: 10px;
}
.meca-modal-close:hover { background: var(--overlay-hover); }

.meca-signed-panel {
  text-align: center;
  padding: 28px;
  background: var(--success-soft);
  border: 1px solid var(--success);
  border-radius: 12px;
  margin-bottom: 16px;
}
.meca-signed-icon { font-size: 36px; margin-bottom: 8px; color: var(--success-content); }
.meca-signed-panel p { color: var(--success-content); font-weight: 700; font-size: 15px; }

.meca-pdf-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin-top: 14px;
  min-height: 48px;
  padding: 0 20px;
  border-radius: 10px;
  background: var(--accent);
  color: var(--accent-ink);
  font-size: 14px;
  font-weight: 700;
  text-decoration: none;
}

.meca-rapport-form {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.meca-rapport-form .meca-field label {
  font-size: 13px;
  font-weight: 700;
}

.meca-required { color: var(--error-content); }

.meca-panel-box {
  padding: 16px;
  border: 1px solid var(--border-2);
  border-radius: 12px;
}

.meca-panel-box-info {
  background: var(--info-soft);
  border-color: var(--info);
  font-size: 13px;
  color: var(--content-2);
}
.meca-panel-box-title {
  font-size: 12px;
  font-weight: 700;
  color: var(--info-content);
  margin-bottom: 8px;
  display: flex;
  align-items: center;
  gap: 6px;
}

.meca-checkbox-label {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 15px;
  font-weight: 700;
  color: var(--content-1);
  cursor: pointer;
  min-height: 44px;
}
.meca-checkbox-label input {
  width: 22px;
  height: 22px;
}

.meca-sign-block {
  border-top: 1px solid var(--border-2);
  padding-top: 18px;
}
.meca-sign-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 15px;
  font-weight: 700;
  color: var(--content-1);
  margin-bottom: 8px;
}

.meca-sign-canvas {
  width: 100%;
  aspect-ratio: 3/1;
  border-radius: 10px;
  background: var(--surface-2);
  touch-action: none;
}

.meca-sign-actions {
  display: flex;
  gap: 10px;
  margin-top: 10px;
}
.meca-btn-sign { flex: 2; }

.meca-signed-inline {
  padding: 14px;
  background: var(--success-soft);
  border: 1px solid var(--success);
  border-radius: 10px;
  font-size: 14px;
  color: var(--success-content);
}
</style>
