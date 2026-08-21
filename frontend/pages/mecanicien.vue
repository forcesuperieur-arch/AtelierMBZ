<template>
  <div class="meca">
    <div class="meca-header">
      <div class="meca-header-id">
        <div class="meca-avatar">{{ initials }}</div>
        <div class="meca-header-texts">
          <div class="meca-page-title">Poste mécanicien</div>
          <div class="meca-date">
            {{ todayLabel }}<span v-if="pontsDuJour"> · {{ pontsDuJour }}</span><span v-if="tempsPointeJour"> · {{ dureeAtelier(tempsPointeJour) }} pointées</span>
          </div>
        </div>
      </div>
      <!-- L'heure en grand : au poste on la lit de loin, debout, pour savoir
           ce qui tient encore avant la pause. Format d'interface 08:30. -->
      <div class="meca-clock" aria-hidden="true">{{ heureCourante }}</div>
      <div class="meca-kpis">
        <div class="meca-kpi">
          <span class="meca-kpi-label">En cours</span>
          <span class="meca-kpi-value meca-kpi-value-warning">{{ kpis.enCours }}</span>
        </div>
        <div class="meca-kpi">
          <span class="meca-kpi-label">À prendre</span>
          <span class="meca-kpi-value">{{ kpis.aFaire }}</span>
        </div>
        <div class="meca-kpi">
          <span class="meca-kpi-label">Terminés</span>
          <span class="meca-kpi-value meca-kpi-value-success">{{ kpis.termines }}</span>
        </div>
        <div class="meca-kpi">
          <span class="meca-kpi-label">Avancement</span>
          <span class="meca-kpi-value meca-kpi-value-accent">{{ kpis.pctDone }} %</span>
        </div>
      </div>
    </div>

    <AppLoadingState
      v-if="loading"
      title="Feuille du jour en cours de chargement"
      :colonnes="3"
      :lignes="3"
    />

    <AppErrorState
      v-else-if="loadError"
      title="Feuille du jour introuvable"
      :description="loadError"
      consequence="Aucun pointage n'est perdu : ce qui a déjà été envoyé reste enregistré côté atelier."
      action-label="Recharger la feuille du jour"
      data-testid="meca-erreur-chargement"
      @retry="reload"
    />

    <div v-else>
      <div v-if="absenceToday" class="meca-banner meca-banner-error">
        <div class="meca-banner-head"><AppIcon name="i-ri-calendar-close-line" /> Absence posée sur la journée</div>
        <p>{{ absenceToday.motif }}</p>
      </div>

      <div v-if="priorityAction" class="meca-banner meca-banner-accent">
        <div class="meca-banner-head"><AppIcon name="i-ri-flashlight-line" /> Le geste suivant</div>
        <p>{{ priorityAction }}</p>
      </div>

      <!-- 29e : le bandeau dit d'abord ce qui MARCHE ENCORE. Hors réseau, le
           contrôle, l'essai et les notes se saisissent et partent tout seuls ;
           ce sont les transitions et la signature qui attendent. -->
      <AppOfflineBanner
        :hors-ligne="!offlineQueue.isOnline.value"
        :depuis="dureeHorsLigne"
        actions-possibles="cocher le contrôle, saisir l'essai routier et les notes : tout repart au retour du réseau"
        :en-attente="offlineQueue.pending.value.length"
        detail-attente="Contrôle atelier, notes et rapport"
        indisponible="Démarrer, mettre en pause, terminer, valider l'essai routier, signer le rapport"
      />
      <div v-if="offlineQueue.isOnline.value && offlineQueue.pending.value.length" class="meca-banner meca-banner-accent">
        <div class="meca-banner-head"><AppIcon name="i-ri-refresh-line" /> Envoi des saisies en attente</div>
        <p>{{ offlineQueue.pending.value.length }} sauvegarde{{ offlineQueue.pending.value.length > 1 ? 's' : '' }} repart{{ offlineQueue.pending.value.length > 1 ? 'ent' : '' }} vers le serveur. Rien n'est à ressaisir.</p>
      </div>

      <div v-if="lastFailedAction" class="meca-banner meca-banner-error">
        <div class="meca-banner-head"><AppIcon name="i-ri-error-warning-line" /> Action restée au poste</div>
        <p>« {{ lastFailedAction.label }} » n'a pas atteint le serveur. Rien n'a changé côté atelier.</p>
        <AppButton
          variant="primary" class="meca-btn-lg" icon="i-ri-refresh-line"
          :label="`Renvoyer · ${lastFailedAction.label}`"
          data-testid="meca-renvoyer"
          @click="retryLastFailedAction"
        />
      </div>

      <div class="meca-tabs" role="tablist">
        <button type="button" class="meca-tab" :class="{ 'is-active': activeTab === 'cours' }" role="tab" :aria-selected="activeTab === 'cours'" data-testid="meca-onglet-cours" @click="activeTab = 'cours'">
          <AppIcon name="i-ri-tools-line" /> En cours
          <span v-if="activeRdv" class="meca-tab-dot" />
        </button>
        <button type="button" class="meca-tab" :class="{ 'is-active': activeTab === 'faire' }" role="tab" :aria-selected="activeTab === 'faire'" data-testid="meca-onglet-faire" @click="activeTab = 'faire'">
          <AppIcon name="i-ri-clipboard-line" /> À prendre
          <span class="meca-tab-count">{{ todoRdvs.length }}</span>
        </button>
        <button type="button" class="meca-tab" :class="{ 'is-active': activeTab === 'termines' }" role="tab" :aria-selected="activeTab === 'termines'" data-testid="meca-onglet-termines" @click="activeTab = 'termines'">
          <AppIcon name="i-ri-checkbox-circle-line" /> Terminés
          <span class="meca-tab-count">{{ doneRdvs.length }}</span>
        </button>
      </div>

      <!-- ONGLET : Intervention en cours -->
      <div v-show="activeTab === 'cours'">
        <AppEmptyState
          v-if="!activeRdv"
          icon="i-ri-tools-line"
          title="Aucune intervention en cours"
          description="Une intervention démarre depuis « À prendre », sur un rendez-vous déjà réceptionné au comptoir."
          :action-label="todoRdvs.length ? `Voir les travaux à prendre · ${todoRdvs.length}` : ''"
          @action="activeTab = 'faire'"
        />

        <div v-else class="meca-card meca-active-card">
          <div class="meca-active-head">
            <span class="meca-active-title"><AppIcon name="i-ri-tools-line" /> Intervention en cours</span>
            <div class="meca-active-head-links">
              <a v-if="activeRdv.client_telephone" :href="`tel:${activeRdv.client_telephone}`" class="meca-call-link">
                <AppIcon name="i-ri-phone-line" /> Appeler le client
              </a>
              <button v-if="activeRdv.vehicule_id" type="button" class="meca-call-link" @click="showHistorique = true">
                <AppIcon name="i-ri-history-line" /> Passages précédents de la moto
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
              variant="secondary" class="meca-btn-lg" icon="i-ri-pause-line" label="Mettre en pause"
              :loading="pausing" data-testid="meca-pause" @click="pauseWork"
            />
            <AppButton
              v-if="activeRdv.statut === 'en_pause'"
              variant="secondary" class="meca-btn-lg" icon="i-ri-play-line" label="Reprendre les travaux"
              :loading="resuming" data-testid="meca-reprendre" @click="resumeWork"
            />
            <AppButton
              variant="primary" class="meca-btn-lg meca-btn-finish" icon="i-ri-checkbox-circle-line"
              label="Terminer et ouvrir le rapport"
              :loading="finishing" :disabled="!essaiRoutierValide" data-testid="meca-terminer" @click="finishWork"
            />
          </div>

          <div class="meca-info-grid">
            <div><span class="meca-info-label">Client</span> {{ activeRdv.client_nom }}</div>
            <div><span class="meca-info-label">Moto</span> {{ activeRdv.vehicule_info }}</div>
            <div><span class="meca-info-label">Prestation</span> {{ activeRdv.type_intervention }}</div>
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
              <span><AppIcon name="i-ri-inbox-line" /> Ce que dit la réception</span>
              <span class="meca-badge" :class="activeRdv.or_signe ? 'meca-badge-success' : 'meca-badge-error'">{{ activeRdv.or_signe ? 'OR signé' : 'OR non signé' }}</span>
            </div>
            <div class="meca-reception-grid">
              <div v-if="activeRdv.vehicule_plaque"><span class="meca-info-label">Immat</span> {{ activeRdv.vehicule_plaque }}</div>
              <div v-if="activeRdv.km_reception !== null"><span class="meca-info-label">Compteur à l'entrée</span> {{ kilometrage(activeRdv.km_reception) }}</div>
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
              <span class="meca-info-label">Pointage en cours</span>
              <span class="meca-chrono-value" :class="progressPct > 100 ? 'is-late' : ''">{{ chronoDisplay }}</span>
            </div>
            <div class="meca-chrono-row meca-chrono-row-sm">
              <span>Avancement sur le temps vendu</span>
              <span :class="progressPct > 100 ? 'is-late' : ''">{{ progressPct }} %</span>
            </div>
            <div class="meca-progress-bar">
              <div class="meca-progress-fill" :class="progressPct > 100 ? 'is-late' : ''" :style="{ width: Math.min(progressPct, 100) + '%' }"></div>
            </div>
            <div class="meca-chrono-row meca-chrono-row-sm">
              <span>{{ dureeAtelier(elapsedMin) }} pointées</span>
              <span>{{ dureeAtelier(activeRdv.temps_estime) }} vendues</span>
            </div>
            <!-- Règle 6 : l'écart vendu / pointé se lit ici pour la prestation en
                 cours, jamais pour juger celui qui la fait. -->
            <div v-if="progressPct > 100" class="meca-late-warning">
              <AppIcon name="i-ri-error-warning-line" /> {{ dureeAtelier(elapsedMin - activeRdv.temps_estime) }} au-delà du temps vendu. Le forfait se recale par prestation, pas par mécanicien.
            </div>
          </div>

          <SectionAccordion
            title="Contrôle atelier"
            icon="i-ri-checkbox-circle-line"
            :badge="`${checkupDone}/${checkupItems.length} points`"
            v-model="sections.checkup"
          >
            <p class="meca-section-hint">Un point coché part dans le dossier atelier de la moto, et se retrouve sur le rapport remis au client.</p>
            <CheckpointGrid :items="checkupItems" :values="checkup" @toggle="cycleCheckup" />
            <CheckpointPhotoPanel
              :items="checkupItems" :values="checkup" :photos-by-key="checkupPhotos"
              @add-photo="(key: string, file: File) => uploadCheckpointPhoto('checkup', key, file, activeRdv?.id)"
              @remove-photo="(key: string, id: number) => removeCheckpointPhoto('checkup', key, id)"
            />
            <div class="meca-section-actions">
              <AppButton variant="secondary" class="meca-btn-lg" icon="i-ri-save-line" label="Enregistrer le contrôle" :loading="persistingCheckup" data-testid="meca-enregistrer-controle" @click="persistWorkshopReport()" />
            </div>
          </SectionAccordion>

          <SectionAccordion
            title="Essai routier"
            icon="i-ri-motorbike-line"
            :badge="`${essaiFilledCount}/${essaiPoints.length} points`"
            v-model="sections.essai"
          >
            <div class="meca-form-grid">
              <div class="meca-field">
                <label>Compteur au départ</label>
                <input v-model.number="essaiForm.kmDebut" type="number" inputmode="numeric" class="form-input meca-input-lg" />
              </div>
              <div class="meca-field">
                <label>Compteur au retour</label>
                <input v-model.number="essaiForm.kmFin" type="number" inputmode="numeric" class="form-input meca-input-lg" />
              </div>
              <div class="meca-field">
                <label>Durée de l'essai, en minutes</label>
                <input v-model.number="essaiForm.dureeMinutes" type="number" inputmode="numeric" class="form-input meca-input-lg" />
              </div>
            </div>
            <CheckpointGrid :items="essaiPoints" :values="essaiForm.pointsControle" @toggle="cycleEssaiPoint" />
            <CheckpointPhotoPanel
              :items="essaiPoints" :values="essaiForm.pointsControle" :photos-by-key="essaiPhotos"
              @add-photo="(key: string, file: File) => uploadCheckpointPhoto('essai_routier', key, file, activeRdv?.id)"
              @remove-photo="(key: string, id: number) => removeCheckpointPhoto('essai_routier', key, id)"
            />
            <div v-if="essaiHasNok" class="meca-field meca-field-mt">
              <label>Corrections faites après l'essai</label>
              <textarea v-model="essaiForm.actionsCorrectives" class="form-input meca-input-lg" rows="2" placeholder="Ce qui a été repris sur la moto après le tour d'essai" />
            </div>
            <div class="meca-section-actions meca-section-actions-split">
              <span class="meca-section-hint">Sans les deux compteurs, la durée et 5 points renseignés, l'essai ne peut pas être validé — et sans essai validé, l'intervention ne peut pas être terminée.</span>
              <AppButton
                variant="primary" class="meca-btn-lg" icon="i-ri-motorbike-line"
                :label="savingRoadTest ? 'Validation en cours…' : (essaiRoutierValide ? 'Essai routier validé' : 'Valider l’essai routier')"
                :disabled="savingRoadTest || essaiRoutierValide || !canValidateRoadTest"
                data-testid="meca-valider-essai"
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
            <div v-else class="meca-section-hint">Rien de signalé sur cet OR. Un travail non prévu se déclare ici : le comptoir demande l'accord du client avant que la moto reparte.</div>

            <AppButton
              variant="secondary" class="meca-btn-lg meca-btn-report"
              :icon="showNewDemande ? 'i-ri-close-line' : 'i-ri-hammer-line'"
              :label="showNewDemande ? 'Abandonner la déclaration' : 'Déclarer un travail supplémentaire'"
              data-testid="meca-declarer-travaux"
              @click="showNewDemande = !showNewDemande"
            />
            <div v-if="showNewDemande" class="meca-demande-form">
              <textarea v-model="newDemande.description" class="form-input meca-input-lg" rows="2" placeholder="Ce qui a été constaté sur la moto, et ce que ça demande" />
              <div class="meca-form-grid">
                <div class="meca-field">
                  <label>Coût estimé, en euros</label>
                  <input v-model.number="newDemande.prix_estime" type="number" inputmode="decimal" class="form-input meca-input-lg" />
                </div>
                <div class="meca-field">
                  <label>Temps estimé, en minutes</label>
                  <input v-model.number="newDemande.temps_estime" type="number" inputmode="numeric" class="form-input meca-input-lg" />
                </div>
              </div>
              <div class="meca-field-inline">
                <label>Urgence</label>
                <select v-model="newDemande.urgence" class="form-input meca-input-lg">
                  <option value="normal">La moto peut attendre l'accord</option>
                  <option value="urgent">La moto ne doit pas repartir sans</option>
                </select>
              </div>
              <AppButton
                variant="primary" class="meca-btn-lg meca-btn-block"
                :label="submittingDemande ? 'Envoi en cours…' : 'Envoyer au comptoir pour accord client'"
                :disabled="submittingDemande || !newDemande.description.trim()"
                data-testid="meca-envoyer-travaux"
                @click="submitDemande"
              />
            </div>
          </SectionAccordion>

          <SectionAccordion title="Notes d’intervention" icon="i-ri-file-text-line" v-model="sections.notes">
            <textarea v-model="interventionNotes" class="form-input meca-input-lg" rows="3" placeholder="Ce qu'il faut savoir la prochaine fois : pièce montée, jeu relevé, écrou à recontrôler" />
            <div class="meca-section-actions">
              <AppButton variant="secondary" class="meca-btn-lg" icon="i-ri-save-line" :label="savingNotes ? 'Enregistrement en cours…' : 'Enregistrer les notes'" :disabled="savingNotes" data-testid="meca-enregistrer-notes" @click="saveInterventionNotes" />
            </div>
          </SectionAccordion>
        </div>
      </div>

      <!-- ONGLET : à prendre -->
      <div v-show="activeTab === 'faire'" class="meca-card">
        <AppNothingToDo
          v-if="!todoRdvs.length"
          title="Plus rien à prendre aujourd’hui"
          description="Tous les rendez-vous de la feuille du jour sont démarrés ou terminés."
        />
        <div v-else class="meca-todo-list">
          <div v-for="rdv in todoRdvs" :key="rdv.id" class="meca-todo-row">
            <div class="meca-todo-texts">
              <p class="meca-todo-name">{{ rdv.heure_debut?.slice(0, 5) }} · {{ rdv.client_nom }}</p>
              <p class="meca-todo-sub">{{ rdv.vehicule_info }} · {{ rdv.type_intervention }}</p>
              <p v-if="rdv.temps_estime" class="meca-todo-sub"><AppIcon name="i-ri-timer-line" /> {{ dureeAtelier(rdv.temps_estime) }} vendues<span v-if="rdv.pont_nom"> · {{ rdv.pont_nom }}</span></p>
            </div>
            <div class="meca-todo-actions">
              <StatusBadge :status="rdv.status" />
              <!-- Seul un rendez-vous réceptionné se démarre : tant que la moto
                   n'est pas entrée, il n'y a rien à pointer. Les autres lignes
                   restent visibles, sans bouton, plutôt qu'avec un bouton mort. -->
              <AppButton
                v-if="rdv.status === 'reception'"
                variant="primary" class="meca-btn-lg" icon="i-ri-tools-line"
                :label="libelleDemarrer(rdv)"
                data-testid="meca-demarrer"
                @click="startWork(rdv.id)"
              />
              <span v-else class="meca-todo-attente">Démarrable dès que la moto est réceptionnée au comptoir</span>
            </div>
          </div>
        </div>
      </div>

      <!-- ONGLET : Terminés -->
      <div v-show="activeTab === 'termines'" class="meca-card">
        <AppEmptyState
          v-if="!doneRdvs.length"
          icon="i-ri-checkbox-circle-line"
          title="Aucune intervention terminée aujourd’hui"
          description="Une intervention rejoint cette liste au moment où elle est terminée au poste, avec son rapport à compléter."
        />
        <div v-else class="meca-done-list">
          <div v-for="rdv in doneRdvs" :key="rdv.id" class="meca-done-row" :class="{ 'is-past': rdv.status !== 'termine' }">
            <div class="meca-done-info">
              <span class="meca-done-check"><AppIcon name="i-ri-checkbox-circle-line" /></span>
              <span>{{ rdv.heure_debut?.slice(0, 5) }} · {{ rdv.client_nom }} · {{ rdv.type_intervention }}</span>
              <span v-if="rdv.status === 'termine'" class="meca-badge meca-badge-warning">Rapport à compléter</span>
            </div>
            <div class="meca-done-actions">
              <AppButton v-if="rdv.status === 'termine'" variant="secondary" class="meca-btn-lg" icon="i-ri-clipboard-line" label="Compléter et signer le rapport" data-testid="meca-ouvrir-rapport" @click="openRapport(rdv.id)" />
              <button class="meca-link-btn" @click="openRdvDetail(rdv)">Ouvrir le rendez-vous</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Rapport d'intervention panel -->
    <div v-if="rapportRdvId" class="meca-modal-overlay" @click.self="closeRapport">
      <div class="meca-modal" role="dialog" aria-modal="true" aria-labelledby="meca-modal-titre">
        <div class="meca-modal-head">
          <h2 id="meca-modal-titre"><AppIcon name="i-ri-clipboard-line" /> Rapport d'intervention</h2>
          <button aria-label="Fermer le rapport" class="meca-modal-close" @click="closeRapport"><AppIcon name="i-ri-close-line" /></button>
        </div>

        <AppLoadingState v-if="rapportLoading" title="Rapport en cours de chargement" compact :colonnes="2" :lignes="4" />
        <AppErrorState
          v-else-if="rapportError"
          title="Rapport introuvable"
          :description="rapportError"
          consequence="L'intervention reste terminée : seul le rapport n'a pas pu être ouvert."
          action-label="Réessayer d’ouvrir le rapport"
          @retry="rapportRdvId && openRapport(rapportRdvId)"
        />

        <div v-else-if="rapport">
          <div v-if="rapport.is_signed_by_both" class="meca-signed-panel">
            <div class="meca-signed-icon"><AppIcon name="i-ri-checkbox-circle-line" /></div>
            <p>Rapport signé par l'atelier et par le client</p>
            <a :href="`${apiBase.replace('/api', '')}/api/rapport/${rapport.id}/pdf`" target="_blank" class="btn btn-primary meca-btn-lg meca-pdf-link">
              <AppIcon name="i-ri-file-text-line" /> Télécharger le rapport en PDF
            </a>
          </div>

          <div v-else class="meca-rapport-form">
            <div class="meca-field">
              <label>Travaux réalisés</label>
              <p class="meca-field-why">Sans ce texte, le client repart sans savoir ce qui a été fait sur sa moto.</p>
              <textarea v-model="rapportForm.travauxRealises" class="form-input meca-input-lg" rows="4" placeholder="Pièces déposées, réglages, valeurs relevées" :disabled="!!rapport.signature_mecanicien" />
            </div>

            <div class="meca-field">
              <label>Réserves à signaler</label>
              <p class="meca-field-why">Ce qui a été vu mais pas traité : la restitution le reprend à voix haute.</p>
              <textarea v-model="rapportForm.alertes" class="form-input meca-input-lg" rows="2" placeholder="Un point par ligne" :disabled="!!rapport.signature_mecanicien" />
            </div>

            <div class="meca-field">
              <label>À prévoir au prochain passage</label>
              <p class="meca-field-why">Sans ça, la prochaine prise de rendez-vous repart de zéro.</p>
              <textarea v-model="rapportForm.recommandations" class="form-input meca-input-lg" rows="2" placeholder="Prochaine révision, pièces à commander" :disabled="!!rapport.signature_mecanicien" />
            </div>

            <div class="meca-form-grid">
              <div class="meca-field">
                <label>Compteur à la sortie, en kilomètres</label>
                <input v-model.number="rapportForm.kilometrageRestitution" type="number" inputmode="numeric" class="form-input meca-input-lg" placeholder="24500" :disabled="!!rapport.signature_mecanicien" />
              </div>
            </div>

            <div class="meca-panel-box">
              <label class="meca-checkbox-label">
                <input type="checkbox" :checked="rapportForm.vidangePrevue" :disabled="!!rapport.signature_mecanicien" @change="toggleVidangePrevue" />
                Poser la prochaine vidange
              </label>
              <div v-if="rapportForm.vidangePrevue" class="meca-form-grid meca-form-grid-mt">
                <div class="meca-field">
                  <label>Vers, en kilomètres</label>
                  <input v-model.number="rapportForm.prochaineRevisionKm" type="number" inputmode="numeric" class="form-input meca-input-lg" :disabled="!!rapport.signature_mecanicien" />
                </div>
                <div class="meca-field">
                  <label>Ou avant le</label>
                  <input v-model="rapportForm.prochaineRevisionDate" type="date" class="form-input meca-input-lg" :disabled="!!rapport.signature_mecanicien" />
                </div>
              </div>
            </div>

            <div class="meca-panel-box meca-panel-box-info">
              <div class="meca-panel-box-title"><AppIcon name="i-ri-tools-line" /> Périodicité des fluides</div>
              <div>Huile moteur — <strong>tous les ans</strong></div>
              <div>Liquide de frein — <strong>tous les 2 ans</strong></div>
              <div>Liquide de refroidissement — <strong>tous les 3 ans</strong></div>
            </div>

            <div class="meca-panel-box">
              <div class="meca-panel-box-title"><AppIcon name="i-ri-motorbike-line" /> Essai routier</div>
              <div class="meca-form-grid">
                <div class="meca-field">
                  <label>Compteur au départ</label>
                  <input v-model.number="essaiForm.kmDebut" type="number" inputmode="numeric" class="form-input meca-input-lg" :disabled="!!rapport.signature_mecanicien" />
                </div>
                <div class="meca-field">
                  <label>Compteur au retour</label>
                  <input v-model.number="essaiForm.kmFin" type="number" inputmode="numeric" class="form-input meca-input-lg" :disabled="!!rapport.signature_mecanicien" />
                </div>
                <div class="meca-field">
                  <label>Durée de l'essai, en minutes</label>
                  <input v-model.number="essaiForm.dureeMinutes" type="number" inputmode="numeric" class="form-input meca-input-lg" :disabled="!!rapport.signature_mecanicien" />
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
                <label>Corrections faites après l'essai</label>
                <textarea v-model="essaiForm.actionsCorrectives" class="form-input meca-input-lg" rows="2" placeholder="Ce qui a été repris sur la moto après le tour d'essai" :disabled="!!rapport.signature_mecanicien" />
              </div>
            </div>

            <AppButton
              v-if="!rapport.signature_mecanicien"
              variant="primary" class="meca-btn-lg meca-btn-block" icon="i-ri-save-line"
              :label="rapportSaving ? 'Enregistrement en cours…' : 'Enregistrer le rapport'"
              :disabled="rapportSaving"
              data-testid="meca-enregistrer-rapport"
              @click="saveRapport"
            />

            <div v-if="!rapport.signature_mecanicien" class="meca-sign-block">
              <p class="meca-sign-title"><AppIcon name="i-ri-quill-pen-line" /> Signature de l'atelier</p>
              <p class="meca-section-hint">La signature engage l'atelier sur les travaux décrits et sur l'essai routier effectué. Le client signe à son tour à la restitution.</p>
              <canvas
                ref="sigRapportCanvas"
                class="meca-sign-canvas"
                @pointerdown="startRapportDraw" @pointermove="drawRapport" @pointerup="endRapportDraw" @pointerleave="endRapportDraw"
              ></canvas>
              <div class="meca-sign-actions">
                <AppButton variant="secondary" class="meca-btn-lg" icon="i-ri-eraser-line" label="Effacer le tracé" @click="clearRapportSig" />
                <AppButton
                  variant="primary" class="meca-btn-lg meca-btn-sign"
                  :label="rapportSigning ? 'Signature en cours…' : 'Signer et clore le rapport'"
                  :disabled="!rapportSigDrawn || rapportSigning"
                  data-testid="meca-signer-rapport"
                  @click="signRapport"
                />
              </div>
              <AppFieldError v-if="rapportSignError" :message="rapportSignError" />
            </div>

            <div v-else class="meca-signed-inline">
              <AppIcon name="i-ri-checkbox-circle-line" /> Rapport signé par l'atelier. Le client signe à la restitution, la moto peut sortir dès qu'il l'a fait.
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
          variant="secondary" class="meca-btn-lg" icon="i-ri-pause-line" label="Mettre en pause"
          :loading="pausing" data-testid="meca-pause-barre" @click="pauseWork"
        />
        <AppButton
          v-if="activeRdv.statut === 'en_pause'"
          variant="secondary" class="meca-btn-lg" icon="i-ri-play-line" label="Reprendre les travaux"
          :loading="resuming" data-testid="meca-reprendre-barre" @click="resumeWork"
        />
        <AppButton
          v-if="!essaiRoutierValide"
          variant="primary" class="meca-btn-lg" icon="i-ri-motorbike-line"
          :label="savingRoadTest ? 'Validation en cours…' : 'Valider l’essai routier'"
          :disabled="savingRoadTest || !canValidateRoadTest"
          data-testid="meca-valider-essai-barre"
          @click="saveActiveRoadTest"
        />
        <AppButton
          variant="primary" class="meca-btn-lg meca-btn-finish" icon="i-ri-checkbox-circle-line"
          label="Terminer et ouvrir le rapport"
          :loading="finishing" :disabled="!essaiRoutierValide" data-testid="meca-terminer-barre" @click="finishWork"
        />
      </div>
    </div>

    <MotoHistoriqueModal v-model:open="showHistorique" :vehicule-id="activeRdv?.vehicule_id ?? null" />
  </div>
</template>

<script setup lang="ts">
// Thème sombre imposé (tour 45b) : au poste, le sombre ne désigne pas une
// préférence mais un LIEU — un écran debout, dans l'atelier, sous néon ou en
// plein jour. C'est le seul écran du front avec l'affichage mural à le faire ;
// `@nuxtjs/color-mode` rend la main au reste de l'application en sortant d'ici.
definePageMeta({ layout: 'kiosk', colorMode: 'dark' })

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

// --- Écriture des nombres, à la française ---
// `formatMinutes` rend « 01:40 », qui se lit comme une heure d'horloge et non
// comme une durée. Au poste on annonce « 1 h 40 » ; l'espace est insécable
// pour que la valeur ne se coupe jamais en fin de ligne.
const INSECABLE = ' '

function dureeAtelier(minutes: number | string | null | undefined): string {
  const total = Math.max(0, Math.round(Number(minutes ?? 0)))
  const h = Math.floor(total / 60)
  const m = total % 60
  if (!h) return `${m}${INSECABLE}min`
  return `${h}${INSECABLE}h${INSECABLE}${String(m).padStart(2, '0')}`
}

function kilometrage(km: number | string | null | undefined): string {
  const valeur = Number(km ?? 0)
  if (!Number.isFinite(valeur)) return ''
  return `${valeur.toLocaleString('fr-FR')}${INSECABLE}km`
}

// Heure d'interface en 08:30, adossée au même battement que le chrono : pas de
// second minuteur pour la même seconde.
const heureCourante = computed(() => {
  const d = new Date(now.value)
  return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`
})

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

// 29e : le bandeau hors ligne doit dire DEPUIS QUAND. La file en mémoire ne
// retient pas cet instant — on l'horodate ici, au moment où le réseau tombe.
const horsLigneDepuis = ref<number | null>(null)

watch(() => offlineQueue.isOnline.value, (enLigne) => {
  horsLigneDepuis.value = enLigne ? null : Date.now()
}, { immediate: true })

const dureeHorsLigne = computed(() => {
  if (!horsLigneDepuis.value) return 'quelques instants'
  const minutes = Math.floor((now.value - horsLigneDepuis.value) / 60000)
  return minutes < 1 ? 'moins d’une minute' : dureeAtelier(minutes)
})

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
      rapportError.value = "La réception n'a pas encore créé l'ordre de réparation de ce rendez-vous. Le rapport s'ouvrira dès que l'OR existe."
      return
    }
    rapport.value = await api.get(`/mecanicien/me/rapport/${orId}`)
    fillRapportForm(rapport.value)
    await loadCheckpointPhotos(rdvId)
  } catch (e: any) {
    rapportError.value = messageErreur(e, "le rapport n'a pas pu être ouvert")
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
      toast.add({ title: 'Rapport gardé au poste', description: 'Le réseau est coupé : le rapport repart tout seul dès qu’il revient. La signature, elle, attend le réseau.', color: 'warning' })
      return false
    }

    await fetchMyRdvs()
    toast.add({ title: 'Rapport enregistré', color: 'success' })
    return true
  } catch (e: any) {
    toast.add({ title: 'Rapport non enregistré', description: messageErreur(e, "le rapport n'a pas été enregistré"), color: 'error' })
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
      rapportSignError.value = "Le serveur n'a pas répondu : le rapport n'est pas enregistré, donc rien n'est signé. Réessayez l'enregistrement avant de signer."
      return
    }
    const sig = sigRapportCanvas.value.toDataURL('image/png')
    const updated = await api.post(`/mecanicien/me/sign/${rapport.value.id}`, { signature: sig })
    rapport.value = updated
    await fetchMyRdvs()
    toast.add({ title: 'Rapport signé par l’atelier', color: 'success' })
  } catch (e: any) {
    if (offlineQueue.isNetworkError(e)) {
      offerRetry('Signer le rapport', signRapport)
      rapportSignError.value = 'La connexion est tombée pendant la signature : le rapport n’est pas signé et la moto ne peut pas être restituée. Le tracé reste à l’écran, il suffit de resigner au retour du réseau.'
    } else {
      rapportSignError.value = messageErreur(e, "le rapport n'a pas été signé")
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
  // Un canvas ne résout pas `var()` : la valeur littérale était ignorée et le
  // tracé retombait silencieusement sur le noir par défaut. On lit la couleur
  // calculée du pavé, où la feuille a déjà résolu le token.
  ctx.strokeStyle = getComputedStyle(canvas).color
  ctx.lineWidth = 2.5; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.stroke()
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
    toast.add({ title: 'Photo non jointe', description: messageErreur(e, "la photo n'a pas été jointe au point de contrôle"), color: 'error' })
  }
}

async function removeCheckpointPhoto(source: 'checkup' | 'essai_routier', key: string, photoId: number) {
  const target = source === 'checkup' ? checkupPhotos : essaiPhotos
  try {
    await api.del(`/photos/${photoId}`)
    if (target[key]) target[key] = target[key].filter(p => p.id !== photoId)
  } catch (e: any) {
    toast.add({ title: 'Photo toujours au dossier', description: messageErreur(e, "la photo n'a pas été retirée du point de contrôle"), color: 'error' })
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

// Les ponts de la journée, énumérés comme on les dit à l'oral : « Pont 2 et 5 ».
const pontsDuJour = computed(() => {
  const noms = [...new Set(myRdvs.value.map((r: any) => r.pont_nom).filter(Boolean))] as string[]
  if (!noms.length) return ''
  if (noms.length === 1) return noms[0]
  return `${noms.slice(0, -1).join(', ')} et ${noms[noms.length - 1]}`
})

// Total pointé du jour : la somme des temps déjà effectifs, plus le temps qui
// tourne sur l'intervention en cours. Affiché seulement s'il y a quelque chose
// à afficher — un « 0 h 00 » en tête d'écran n'apprend rien.
const tempsPointeJour = computed(() => {
  const cumul = myRdvs.value.reduce((total: number, rdv: any) => {
    if (rdv.id === activeRdv.value?.id) return total
    return total + Number(rdv.temps_effectif_minutes ?? 0)
  }, 0)
  return cumul + (activeRdv.value ? elapsedMin.value : 0)
})

// Le bouton dit le résultat en entier, et le point médian sépare l'action de
// sa donnée : « Démarrer · Pont 2 ».
function libelleDemarrer(rdv: any): string {
  return rdv?.pont_nom ? `Démarrer · ${rdv.pont_nom}` : 'Démarrer les travaux'
}

const priorityAction = computed(() => {
  const receptions = todoRdvs.value.filter(r => r.status === 'reception')
  if (receptions.length) {
    const pont = receptions[0].pont_nom ? ` sur le ${String(receptions[0].pont_nom).toLowerCase()}` : ''
    return `La ${receptions[0].vehicule_info} de ${receptions[0].client_nom} est réceptionnée : les travaux peuvent démarrer${pont}.`
  }
  if (activeRdv.value && progressPct.value > 100) {
    return `Le temps vendu est dépassé de ${dureeAtelier(elapsedMin.value - activeRdv.value.temps_estime)}. Le comptoir n'est pas prévenu tant que rien n'est signalé.`
  }
  if (activeRdv.value && !essaiRoutierValide.value) {
    return 'L’essai routier reste à valider : sans lui, l’intervention ne peut pas être terminée.'
  }
  if (todoRdvs.value.length) {
    return `Prochaine moto attendue à ${todoRdvs.value[0].heure_debut?.slice(0, 5)} : ${todoRdvs.value[0].vehicule_info} de ${todoRdvs.value[0].client_nom}.`
  }
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
      toast.add({ title: 'Aucun OR sur ce rendez-vous', description: "La réception n'a pas encore créé l'ordre de réparation : le contrôle n'a nulle part où s'enregistrer. Il reste à l'écran en attendant.", color: 'warning' })
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
      toast.add({ title: 'Saisie gardée au poste', description: 'Le réseau est coupé : le contrôle et les notes repartent tout seuls dès qu’il revient. Rien à ressaisir.', color: 'warning' })
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
      toast.add({ title: 'Contrôle atelier enregistré', color: 'success' })
    }
    return true
  } catch (e: any) {
    toast.add({ title: 'Contrôle non enregistré', description: messageErreur(e, "le contrôle et les notes n'ont pas été enregistrés"), color: 'error' })
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
      description: "Il manque les deux compteurs, la durée ou 5 points de contrôle. Sans essai validé, l'intervention ne peut pas être terminée.",
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
    toast.add({ title: result?.valide ? 'Essai routier validé' : 'Essai routier enregistré', description: result?.valide ? 'L’intervention peut être terminée.' : undefined, color: 'success' })
  } catch (e: any) {
    if (offlineQueue.isNetworkError(e)) {
      offerRetry('Valider l’essai routier', saveActiveRoadTest)
      toast.add({ title: 'Essai routier resté au poste', description: 'Le serveur n’a pas répondu : l’essai n’est pas validé, l’intervention reste ouverte. Le bandeau « Action restée au poste » le renvoie.', color: 'error' })
    } else {
      toast.add({ title: 'Essai routier non validé', description: messageErreur(e, 'l’essai routier n’a pas été enregistré'), color: 'error' })
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
    toast.add({ title: 'Travaux démarrés · pointage lancé', color: 'success' })
  } catch (e: any) {
    if (offlineQueue.isNetworkError(e)) {
      offerRetry('Démarrer', () => startWork(id))
      toast.add({ title: 'Démarrage resté au poste', description: 'Le serveur n’a pas répondu : le pointage n’a pas commencé, le rendez-vous reste à prendre.', color: 'error' })
    } else {
      toast.add({ title: 'Travaux non démarrés', description: messageErreur(e, 'les travaux n’ont pas démarré'), color: 'error' })
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
    toast.add({ title: 'Pointage en pause', color: 'warning' })
  } catch (e: any) {
    if (offlineQueue.isNetworkError(e)) {
      offerRetry('Mettre en pause', pauseWork)
      toast.add({ title: 'Pause restée au poste', description: 'Le serveur n’a pas répondu : le pointage continue de courir.', color: 'error' })
    } else {
      toast.add({ title: 'Intervention toujours en cours', description: messageErreur(e, 'l’intervention n’a pas été mise en pause'), color: 'error' })
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
    toast.add({ title: 'Pointage repris', color: 'success' })
  } catch (e: any) {
    if (offlineQueue.isNetworkError(e)) {
      offerRetry('Reprendre', resumeWork)
      toast.add({ title: 'Reprise restée au poste', description: 'Le serveur n’a pas répondu : le pointage est toujours en pause.', color: 'error' })
    } else {
      toast.add({ title: 'Intervention toujours en pause', description: messageErreur(e, 'l’intervention n’a pas repris'), color: 'error' })
    }
  } finally {
    resuming.value = false
  }
}

async function finishWork() {
  if (!activeRdv.value) return
  if (!checkupDone.value && !interventionNotes.value.trim()) {
    toast.add({ title: 'Rien de consigné sur l’intervention', description: 'Il faut au moins un point de contrôle coché ou une note : sans trace, la restitution n’a rien à dire au client.', color: 'warning' })
    return
  }
  if (!essaiRoutierValide.value) {
    toast.add({ title: 'Essai routier à valider d’abord', description: 'La moto ne peut pas être annoncée prête tant que l’essai n’est pas validé. Il se renseigne dans le bloc « Essai routier ».', color: 'warning' })
    return
  }
  finishing.value = true
  try {
    await persistWorkshopReport(false)
    const terminatedId = activeRdv.value.id
    await rdvStore.transitionRdv(terminatedId, 'terminer')
    await fetchMyRdvs()
    clearFailedAction('Terminer')
    toast.add({ title: 'Intervention terminée · rapport à signer', color: 'success' })
    openRapport(terminatedId)
  } catch (e: any) {
    if (offlineQueue.isNetworkError(e)) {
      offerRetry('Terminer', finishWork)
      toast.add({ title: 'Clôture restée au poste', description: 'Le serveur n’a pas répondu : l’intervention reste ouverte et le pointage continue.', color: 'error' })
    } else {
      toast.add({ title: 'Intervention non terminée', description: messageErreur(e, 'l’intervention n’a pas été terminée'), color: 'error' })
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
  // Trio de statut du design system : la surface et l'encre vont ensemble, et
  // basculent seules avec le thème d'atelier.
  const colors: Record<string, { bg: string; color: string }> = {
    en_attente: { bg: 'var(--pk-neutral-surface)', color: 'var(--pk-ink-quiet)' },
    en_attente_validation: { bg: 'var(--pk-warning-surface)', color: 'var(--pk-warning-ink)' },
    en_attente_decision_client: { bg: 'var(--pk-info-surface)', color: 'var(--pk-info-ink)' },
    accepte: { bg: 'var(--pk-success-surface)', color: 'var(--pk-success-ink)' },
    refuse: { bg: 'var(--pk-error-surface)', color: 'var(--pk-error-ink)' },
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
    toast.add({ title: 'Travail supplémentaire envoyé au comptoir', description: 'Le comptoir le chiffre et demande l’accord du client. Rien n’est engagé tant que la réponse n’est pas là.', color: 'success' })
  } catch (e: any) {
    if (offlineQueue.isNetworkError(e)) {
      offerRetry('Envoyer la demande complémentaire', submitDemande)
      toast.add({ title: 'Déclaration restée au poste', description: 'Le serveur n’a pas répondu : le comptoir n’a rien reçu, le client n’a pas été sollicité.', color: 'error' })
    } else {
      toast.add({ title: 'Travail supplémentaire non transmis', description: messageErreur(e, 'la demande n’est pas partie au comptoir'), color: 'error' })
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
      toast.add({ title: 'Notes enregistrées', color: 'success' })
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
      ? "Ce compte n'est relié à aucun profil mécanicien : aucun rendez-vous ne peut lui être rattaché. Le lien se fait dans Administration › Utilisateurs."
      : messageErreur(e, "la feuille du jour n'a pas pu être chargée")
    myRdvs.value = []
  } finally {
    loading.value = false
  }
}

// 45c : « Échap ferme le panneau. » Le poste est tactile, mais un clavier reste
// branché sur certains postes, et l'unique croix de fermeture est en haut à
// droite d'une modale qui descend sous la ligne de flottaison.
function fermerAuClavier(evenement: KeyboardEvent) {
  if (evenement.key === 'Escape' && rapportRdvId.value) closeRapport()
}

onMounted(async () => {
  await reload()
  chronoTimer = setInterval(() => { now.value = Date.now() }, 1000)
  chargerIntervalleVidange()
  window.addEventListener('keydown', fermerAuClavier)
})

onUnmounted(() => {
  if (chronoTimer) clearInterval(chronoTimer)
  window.removeEventListener('keydown', fermerAuClavier)
})
</script>

<style scoped>
/* ==========================================================================
   Poste mécanicien — l'écran qui vit dans l'atelier
   --------------------------------------------------------------------------
   Sombre par lieu et non par préférence (45b) : le thème est imposé par
   `definePageMeta({ colorMode: 'dark' })` plus haut, la couche `--pk-*` bascule
   avec lui sur ses valeurs d'atelier.

   Tout ce qui se touche fait `--pk-target-workshop` (56 px) : on le vise
   debout, gants aux mains, sur un iPad posé sur le plan de travail. Deux
   cibles aux effets opposés — mettre en pause / terminer, effacer / signer —
   gardent au moins `--pk-target-gap` entre elles. Aucune action essentielle
   n'est derrière un survol, un appui long ou un glissement : le survol
   n'existe pas au doigt, et l'appui long est déjà pris par le système.
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
  min-width: 0;
}

.meca-header-texts {
  min-width: 0;
}

.meca-avatar {
  width: 52px;
  height: 52px;
  border-radius: 50%;
  background: var(--pk-info-surface);
  border: 2px solid var(--pk-info-line);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 19px;
  color: var(--pk-info-ink);
  flex-shrink: 0;
}

.meca-page-title {
  font-family: var(--font-display);
  font-size: 22px;
  font-weight: 700;
  color: var(--pk-ink);
}

.meca-date {
  font-size: 14px;
  color: var(--pk-ink-quiet);
}

/* Seule la date porte une majuscule d'origine (« lundi »), pas le reste de la
   ligne : la capitalisation ne doit pas remonter sur les ponts ni sur l'heure. */
.meca-date::first-letter {
  text-transform: uppercase;
}

.meca-clock {
  font-size: 30px;
  font-weight: 700;
  color: var(--pk-accent-ink);
  font-variant-numeric: tabular-nums;
  letter-spacing: 0.01em;
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
  border-radius: var(--pk-radius-card);
  background: var(--pk-surface);
  border: 1px solid var(--pk-border);
  min-width: 84px;
}

/* Surtitre du design system : 11 px, 700, 0,08 em, capitales. C'est le seul
   endroit où les capitales sont permises, avec les mots de statut. */
.meca-kpi-label {
  font-size: 11px;
  color: var(--pk-ink-muted);
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.meca-kpi-value {
  font-size: 22px;
  font-weight: 800;
  color: var(--pk-ink);
  font-variant-numeric: tabular-nums;
}

.meca-kpi-value-warning { color: var(--pk-warning-ink); }
.meca-kpi-value-success { color: var(--pk-success-ink); }
.meca-kpi-value-accent { color: var(--pk-accent-ink); }

.meca-banner {
  margin-bottom: 16px;
  padding: 16px;
  border-radius: var(--pk-radius-card);
}

/* Trio de statut : surface + filet + encre s'emploient ensemble, jamais l'un
   sans les autres — sinon le bandeau perd son contour en thème sombre. */
.meca-banner-error {
  background: var(--pk-error-surface);
  border: 1px solid var(--pk-error-line);
}

.meca-banner-accent {
  background: var(--pk-accent-soft);
  border: 1px solid var(--pk-accent);
}

.meca-banner-head {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 6px;
  font-size: 14px;
  font-weight: 700;
}

.meca-banner-error .meca-banner-head { color: var(--pk-error-ink); }
.meca-banner-accent .meca-banner-head { color: var(--pk-accent-ink); }
.meca-banner p { font-size: 14px; color: var(--pk-ink-quiet); line-height: 1.45; }
.meca-banner .meca-btn-lg { margin-top: 10px; }

/* === Onglets === */
.meca-tabs {
  display: flex;
  gap: var(--pk-target-gap);
  margin-bottom: 18px;
  overflow-x: auto;
}

.meca-tab {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: var(--pk-target-workshop);
  padding: 0 20px;
  border-radius: var(--pk-radius-card);
  border: 1px solid var(--pk-border-control);
  background: var(--pk-surface);
  color: var(--pk-ink-quiet);
  font-family: inherit;
  font-size: 15px;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
  transition: background var(--pk-duration-state) var(--pk-easing),
    border-color var(--pk-duration-state) var(--pk-easing),
    color var(--pk-duration-state) var(--pk-easing);
}

.meca-tab.is-active {
  background: var(--pk-accent-soft);
  border-color: var(--pk-accent);
  color: var(--pk-accent-ink);
}

.meca-tab-count {
  min-width: 24px;
  height: 24px;
  padding: 0 7px;
  border-radius: var(--pk-radius-pill);
  background: var(--pk-neutral-surface);
  color: var(--pk-ink);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-variant-numeric: tabular-nums;
}

.meca-tab-dot {
  width: 9px;
  height: 9px;
  border-radius: 50%;
  background: var(--pk-warning-line);
}

.meca-card {
  background: var(--pk-surface);
  border: 1px solid var(--pk-border);
  border-radius: var(--pk-radius-card);
  padding: 20px;
}

/* L'intervention en cours est la seule chose qui bouge dans l'atelier : elle
   porte le filet accent, comme le bloc pointé du planning mural. */
.meca-active-card {
  background: var(--pk-surface-raised);
  border-width: 2px;
  border-color: var(--pk-accent);
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
  color: var(--pk-ink);
}

.meca-active-head-links {
  display: flex;
  gap: var(--pk-target-gap);
  flex-wrap: wrap;
}

.meca-call-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: var(--pk-target-workshop);
  padding: 0 18px;
  border-radius: var(--pk-radius-card);
  background: transparent;
  color: var(--pk-ink);
  border: 1px solid var(--pk-border-control);
  font-family: inherit;
  cursor: pointer;
  text-decoration: none;
  font-size: 14px;
  font-weight: 600;
  transition: background var(--pk-duration-state) var(--pk-easing);
}

.meca-badge-row {
  display: flex;
  gap: var(--pk-target-gap);
  flex-wrap: wrap;
  margin-bottom: 14px;
}

.meca-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 6px 12px;
  border-radius: var(--pk-radius-pill);
  font-size: 13px;
  font-weight: 700;
}

.meca-badge-neutral { background: var(--pk-neutral-surface); color: var(--pk-ink-quiet); }
.meca-badge-success { background: var(--pk-success-surface); color: var(--pk-success-ink); }
.meca-badge-warning { background: var(--pk-warning-surface); color: var(--pk-warning-ink); }
.meca-badge-error { background: var(--pk-error-surface); color: var(--pk-error-ink); }

/* « Terminer » est poussé à l'autre bout de la rangée : le geste qui clôt ne
   doit pas se trouver sous le pouce qui vise « Mettre en pause ». */
.meca-action-row {
  display: flex;
  gap: calc(var(--pk-target-gap) * 2);
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
  font-size: 15px;
  color: var(--pk-ink);
  margin-bottom: 8px;
}

.meca-info-label {
  color: var(--pk-ink-muted);
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  display: block;
  margin-bottom: 2px;
}

.meca-motif {
  margin-top: 12px;
  font-size: 15px;
}
.meca-motif p { color: var(--pk-ink-quiet); margin-top: 2px; line-height: 1.45; }

.meca-reception {
  margin-top: 16px;
  padding: 14px;
  border-radius: var(--pk-radius-card);
  background: var(--pk-info-surface);
  border: 1px solid var(--pk-info-line);
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
  color: var(--pk-info-ink);
}

.meca-reception-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 10px;
  font-size: 14px;
  color: var(--pk-ink);
}

.meca-chip-row {
  display: flex;
  flex-wrap: wrap;
  gap: var(--pk-target-gap);
  margin-top: 12px;
}

.meca-chip {
  padding: 6px 12px;
  border-radius: var(--pk-radius-pill);
  font-size: 12px;
  background: var(--pk-neutral-surface);
  color: var(--pk-ink);
}

.meca-reception-obs {
  margin-top: 12px;
  font-size: 14px;
  color: var(--pk-ink-quiet);
}

.meca-chrono {
  margin-top: 18px;
  padding-top: 16px;
  border-top: 1px solid var(--pk-border-quiet);
}

.meca-chrono-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  font-size: 13px;
  color: var(--pk-ink-quiet);
  margin-bottom: 6px;
}

.meca-chrono-row-sm { font-size: 12px; }

.meca-chrono-value {
  font-family: ui-monospace, 'SFMono-Regular', 'Menlo', monospace;
  font-size: 26px;
  font-weight: 700;
  color: var(--pk-accent-ink);
  font-variant-numeric: tabular-nums;
}
.meca-chrono-value.is-late, .meca-chrono-row-sm .is-late { color: var(--pk-error-ink); }

.meca-progress-bar {
  background: var(--pk-neutral-surface);
  border-radius: var(--pk-radius-block);
  height: 12px;
  overflow: hidden;
}

/* La jauge se déplace d'un cran par seconde : elle glisse sur la durée d'un
   état, pas sur une seconde entière — le design system proscrit ce qui
   clignote comme ce qui rampe. */
.meca-progress-fill {
  height: 100%;
  background: var(--pk-accent);
  border-radius: var(--pk-radius-block);
  transition: width var(--pk-duration-state) var(--pk-easing);
}
.meca-progress-fill.is-late { background: var(--pk-error-line); }

.meca-late-warning {
  margin-top: 10px;
  padding: 10px 14px;
  border-radius: var(--pk-radius-tile);
  background: var(--pk-error-surface);
  border: 1px solid var(--pk-error-line);
  font-size: 13px;
  line-height: 1.45;
  color: var(--pk-error-ink);
}

.meca-section-hint {
  font-size: 13px;
  line-height: 1.45;
  color: var(--pk-ink-quiet);
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
.meca-section-actions-split .meca-section-hint {
  margin-bottom: 0;
  flex: 1 1 260px;
}

.meca-form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 12px;
  margin-bottom: 12px;
}

.meca-form-grid-mt { margin-top: 12px; margin-bottom: 0; }

.meca-field label {
  font-size: 12px;
  color: var(--pk-ink-muted);
  font-weight: 700;
  display: block;
  margin-bottom: 4px;
}

/* La conséquence, pas la catégorie : « sans ça, le client repart sans savoir »
   plutôt que « champ obligatoire ». */
.meca-field-why {
  font-size: 12px;
  line-height: 1.45;
  color: var(--pk-ink-quiet);
  margin: 0 0 6px;
}

.meca-field-mt { margin-top: 10px; }

.meca-field-inline {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 4px;
  flex-wrap: wrap;
}
.meca-field-inline label { font-size: 13px; color: var(--pk-ink-muted); white-space: nowrap; }
.meca-field-inline select { max-width: 320px; }

/* Champ de saisie à la cible d'atelier : on tape dedans avec un gant, ou avec
   un doigt gras. La règle globale `.form-input` reste la définition visuelle. */
.meca-input-lg {
  min-height: var(--pk-target-workshop);
  font-size: 16px;
  border-radius: var(--pk-radius-card);
  border-color: var(--pk-border-control);
  /* `background-color` et non le raccourci : `select.form-input` dessine sa
     flèche en `background-image`, qu'un raccourci effacerait. */
  background-color: var(--pk-surface-raised);
  color: var(--pk-ink);
}

textarea.meca-input-lg {
  min-height: calc(var(--pk-target-workshop) * 2);
  padding-top: 12px;
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
  border-radius: var(--pk-radius-tile);
  background: var(--pk-surface);
  border: 1px solid var(--pk-border);
  font-size: 14px;
  color: var(--pk-ink);
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
  gap: 16px;
  flex-wrap: wrap;
  padding: 16px;
  border-radius: var(--pk-radius-card);
  border: 1px solid var(--pk-border);
  background: var(--pk-surface-raised);
}

.meca-todo-texts { min-width: 0; }

.meca-todo-name {
  font-weight: 700;
  color: var(--pk-ink);
  font-size: 16px;
}

.meca-todo-sub {
  font-size: 13px;
  color: var(--pk-ink-quiet);
  margin-top: 2px;
}

.meca-todo-actions {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

/* Règle 7 appliquée à la ligne : pas de bouton grisé. Ce qui n'est pas encore
   démarrable dit pourquoi, en toutes lettres. */
.meca-todo-attente {
  font-size: 12px;
  color: var(--pk-ink-muted);
  max-width: 260px;
  line-height: 1.35;
}

.meca-done-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  padding: 14px 16px;
  border-radius: var(--pk-radius-card);
  border: 1px solid var(--pk-border);
  font-size: 14px;
}
.meca-done-row.is-past { opacity: 0.7; }

.meca-done-info {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
  color: var(--pk-ink);
}

.meca-done-check { color: var(--pk-success-ink); font-size: 18px; }

.meca-done-actions {
  display: flex;
  align-items: center;
  gap: calc(var(--pk-target-gap) * 2);
  flex-wrap: wrap;
}

.meca-link-btn {
  min-height: var(--pk-target-workshop);
  padding: 0 12px;
  color: var(--pk-link);
  font-size: 14px;
  background: none;
  border: none;
  cursor: pointer;
  font-weight: 700;
}

/* === Boutons tactiles d'atelier (surchargent `.btn` de main.css) ===
   Le composant `AppButton` rend un unique élément racine : le sélecteur
   scoped du parent s'applique directement à cette racine, sans `:deep()`.
   Angle franc plutôt que pilule : c'est la forme « square » du kit, celle
   qu'emploie le poste — une pilule de 56 px de haut devient une gélule. */
.meca-btn-lg {
  min-height: var(--pk-target-workshop);
  padding: 0 22px;
  font-size: 16px;
  border-radius: var(--pk-radius-card);
}

/* === Cibles des composants partagés, remontées à la taille d'atelier ===
   `CheckpointGrid`, `CheckpointPhotoPanel`, `SectionAccordion` et les états
   `App*` sont dessinés pour le bureau (44 à 48 px). Ici on les remonte à 56 px
   depuis la page plutôt que d'en faire des variantes : c'est le CONTEXTE qui
   commande la cible, pas le composant. */
.meca :deep(.checkpoint-btn) {
  min-height: var(--pk-target-workshop);
  border-radius: var(--pk-radius-card);
  font-size: 15px;
}

.meca :deep(.cpp-add),
.meca :deep(.cpp-thumb-remove) {
  min-height: var(--pk-target-workshop);
  min-width: var(--pk-target-workshop);
}

.meca :deep(.meca-section-head) {
  min-height: var(--pk-target-workshop);
}

.meca :deep(.pk-offline) {
  margin-bottom: 16px;
  border: 1px solid var(--pk-warning-line);
  border-radius: var(--pk-radius-card);
}

.meca :deep(.pk-state-actions .btn),
.meca :deep(.pk-offline-toggle) {
  min-height: var(--pk-target-workshop);
  padding: 0 20px;
  border-radius: var(--pk-radius-card);
}

/* === Barre d'action collante ===
   Terminer / valider l'essai restent joignables même quand une section
   repliable (contrôle, essai routier…) a été ouverte plus haut dans la carte
   et qu'il faudrait sinon remonter tout en haut pour les atteindre. */
.meca-sticky-spacer {
  height: calc(var(--pk-target-workshop) + 28px);
}

.meca-sticky-bar {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 40;
  background: var(--pk-surface);
  border-top: 1px solid var(--pk-border);
  padding: 10px max(16px, env(safe-area-inset-right)) max(10px, env(safe-area-inset-bottom)) max(16px, env(safe-area-inset-left));
}

.meca-sticky-bar-inner {
  max-width: 1100px;
  margin: 0 auto;
  display: flex;
  gap: calc(var(--pk-target-gap) * 2);
  flex-wrap: wrap;
}

/* === Rapport d'intervention === */
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
  background: var(--pk-surface);
  border: 1px solid var(--pk-border);
  border-radius: var(--pk-radius-card);
  padding: 24px;
}

.meca-modal-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 20px;
}
.meca-modal-head h2 {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 19px;
  font-weight: 700;
  color: var(--pk-ink);
}

.meca-modal-close {
  min-width: var(--pk-target-workshop);
  min-height: var(--pk-target-workshop);
  font-size: 22px;
  color: var(--pk-ink-quiet);
  background: none;
  border: 1px solid var(--pk-border);
  cursor: pointer;
  border-radius: var(--pk-radius-card);
  flex-shrink: 0;
}

.meca-signed-panel {
  padding: 24px;
  background: var(--pk-success-surface);
  border: 1px solid var(--pk-success-line);
  border-radius: var(--pk-radius-card);
  margin-bottom: 16px;
}
.meca-signed-icon { font-size: 32px; margin-bottom: 8px; color: var(--pk-success-ink); }
.meca-signed-panel p { color: var(--pk-success-ink); font-weight: 700; font-size: 15px; }

.meca-pdf-link {
  margin-top: 14px;
}

.meca-rapport-form {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.meca-rapport-form .meca-field label {
  font-size: 13px;
  font-weight: 700;
  color: var(--pk-ink);
  text-transform: none;
  letter-spacing: normal;
}

.meca-panel-box {
  padding: 16px;
  border: 1px solid var(--pk-border);
  border-radius: var(--pk-radius-card);
}

.meca-panel-box-info {
  background: var(--pk-info-surface);
  border-color: var(--pk-info-line);
  font-size: 13px;
  line-height: 1.6;
  color: var(--pk-ink);
}
.meca-panel-box-title {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--pk-info-ink);
  margin-bottom: 8px;
  display: flex;
  align-items: center;
  gap: 6px;
}

.meca-checkbox-label {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 16px;
  font-weight: 700;
  color: var(--pk-ink);
  cursor: pointer;
  min-height: var(--pk-target-workshop);
}
.meca-checkbox-label input {
  width: 26px;
  height: 26px;
  accent-color: var(--pk-accent);
}

.meca-sign-block {
  border-top: 1px solid var(--pk-border-quiet);
  padding-top: 18px;
}
.meca-sign-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 15px;
  font-weight: 700;
  color: var(--pk-ink);
  margin-bottom: 8px;
}

/* Le pavé de signature est un DOCUMENT, pas une surface d'interface : encre
   noire sur papier blanc, quel que soit le thème de l'écran. Le PNG produit
   part tel quel dans le PDF remis au client — une signature claire, prise sur
   un fond sombre, y serait invisible. `color` est aussi ce que le tracé lit
   (`getComputedStyle(canvas).color`) : le canvas ne sait pas résoudre `var()`. */
.meca-sign-canvas {
  width: 100%;
  aspect-ratio: 3/1;
  border-radius: var(--pk-radius-tile);
  border: 1px solid var(--pk-border-control);
  background: var(--mb-white);
  color: var(--mb-black);
  touch-action: none;
}

/* Effacer et signer ont des effets opposés : deux fois l'écart minimal. */
.meca-sign-actions {
  display: flex;
  gap: calc(var(--pk-target-gap) * 2);
  margin-top: 10px;
  flex-wrap: wrap;
}
.meca-btn-sign { flex: 2 1 220px; }

.meca-signed-inline {
  padding: 14px;
  background: var(--pk-success-surface);
  border: 1px solid var(--pk-success-line);
  border-radius: var(--pk-radius-tile);
  font-size: 14px;
  line-height: 1.45;
  color: var(--pk-success-ink);
}

/* L'anneau de focus n'est jamais supprimé (45c) : il est déjà posé
   globalement, on ne le redéclare ici que pour garantir qu'aucune surcharge
   locale ne le rogne sur les contrôles écrits à la main. */
.meca-tab:focus-visible,
.meca-call-link:focus-visible,
.meca-link-btn:focus-visible,
.meca-modal-close:focus-visible {
  outline: var(--pk-focus-width) solid var(--pk-focus-ring);
  outline-offset: var(--pk-focus-offset);
}
</style>
