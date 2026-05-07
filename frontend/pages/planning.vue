<template>
  <div>
    <AppPageHeader title="Planning">
      <template #actions>
        <button class="btn btn-ghost" :disabled="loading || refreshing" @click="refreshPlanning">
          {{ refreshing ? 'Actualisation…' : '↻ Actualiser' }}
        </button>
        <button v-if="canCreateRdv" class="btn btn-primary" @click="openQuickCreate()">+ RDV rapide</button>
      </template>
    </AppPageHeader>

    <template v-if="loading">
      <div class="grid-4" style="margin-bottom:20px;">
        <AppSkeletonKpi v-for="i in 4" :key="i" />
      </div>
      <AppSkeletonTable :rows="8" :cols="7" />
    </template>

    <template v-else>
      <div class="grid-4" style="margin-bottom:20px;">
        <div class="planning-kpi primary">
          <div class="planning-kpi-label">CHARGE VISIBLE</div>
          <div class="planning-kpi-value">{{ kpis.charge }}</div>
          <div class="planning-kpi-sub">{{ kpis.chargeDetail }}</div>
        </div>
        <div class="planning-kpi" :style="kpis.conflicts > 0 ? 'border-color:rgba(239,68,68,0.3);' : ''">
          <div class="planning-kpi-label">CONFLITS</div>
          <div class="planning-kpi-value" :style="kpis.conflicts > 0 ? 'color:#FCA5A5;' : ''">{{ kpis.conflicts }}</div>
          <div class="planning-kpi-sub">{{ kpis.conflicts > 0 ? 'Attention' : 'Aucun conflit' }}</div>
        </div>
        <div class="planning-kpi">
          <div class="planning-kpi-label">SANS AFFECTATION</div>
          <div class="planning-kpi-value">{{ kpis.unassigned }}</div>
          <div class="planning-kpi-sub">RDV actifs non assignés</div>
        </div>
        <div class="planning-kpi" :style="kpis.late > 0 ? 'border-color:rgba(239,68,68,0.3);' : ''">
          <div class="planning-kpi-label">RETARDS</div>
          <div class="planning-kpi-value" :style="kpis.late > 0 ? 'color:#FCA5A5;' : ''">{{ kpis.late }}</div>
          <div class="planning-kpi-sub">{{ kpis.late > 0 ? 'En retard' : 'À l\'heure' }}</div>
        </div>
      </div>

      <div class="planning-legend-bar">
        <div class="planning-legend-items">
          <span class="planning-legend-label">Légende :</span>
          <span class="flex-center-gap-sm"><span class="legend-dot" style="background:rgba(107,114,128,0.18);border-color:rgba(148,163,184,0.35);"></span><span class="text-muted">À valider</span></span>
          <span class="flex-center-gap-sm"><span class="legend-dot" style="background:rgba(245,158,11,0.15);border-color:rgba(245,158,11,0.35);"></span><span class="text-muted">Créneau réservé</span></span>
          <span class="flex-center-gap-sm"><span class="legend-dot" style="background:rgba(59,130,246,0.15);border-color:rgba(59,130,246,0.35);"></span><span class="text-muted">Confirmé atelier</span></span>
          <span class="flex-center-gap-sm"><span class="legend-dot" style="background:rgba(20,184,166,0.15);border-color:rgba(20,184,166,0.4);"></span><span class="text-muted">En cours</span></span>
          <span class="flex-center-gap-sm"><span class="legend-dot" style="background:rgba(16,185,129,0.15);border-color:rgba(16,185,129,0.4);"></span><span class="text-muted">Terminé / historisé</span></span>
        </div>
        <div style="color:#CBD5E1;">
          Horaires atelier : <strong style="color:#F8FAFC;">{{ hourRangeLabel }}</strong> · {{ openDaysLabel }}
        </div>
      </div>

      <div v-if="mecaniciens.length" class="planning-meca-filter">
        <span class="planning-meca-label">Mécaniciens :</span>
        <button
          v-for="m in mecaniciens"
          :key="m.id"
          type="button"
          class="meca-chip"
          :class="{ active: activeMecas.includes(m.id) }"
          @click="toggleMeca(m.id)"
        >
          <span class="meca-chip-dot" :style="{ background: m.couleur || '#8B5CF6' }"></span>
          {{ m.prenom }} {{ m.nom?.charAt(0) }}.
        </button>
        <button
          v-if="activeMecas.length"
          type="button"
          class="meca-chip meca-chip-reset"
          @click="activeMecas = []"
        >
          ✕ Tous
        </button>
      </div>

      <AppEmptyState
        v-if="!activePlanningRdvs.length"
        icon="📭"
        title="Aucun rendez-vous"
        description="Il n'y a pas de RDV dans la période sélectionnée."
        :action-label="canCreateRdv ? 'Créer un RDV' : ''"
        @action="openQuickCreate()"
      />
      <div v-else-if="isPastWeek" class="planning-past-banner">
        <UIcon name="i-heroicons-archive-box" class="w-4 h-4" />
        <span>Journée terminée · {{ activePlanningRdvs.length }} RDV dans cette période</span>
      </div>

      <div style="display:flex;align-items:center;justify-content:flex-end;gap:8px;margin-bottom:12px;">
        <UButton
          size="xs"
          :variant="viewMode === 'grid' ? 'solid' : 'ghost'"
          color="neutral"
          icon="i-heroicons-table-cells"
          @click="viewMode = 'grid'"
        />
        <UButton
          size="xs"
          :variant="viewMode === 'list' ? 'solid' : 'ghost'"
          color="neutral"
          icon="i-heroicons-list-bullet"
          @click="viewMode = 'list'"
        />
      </div>

      <PlanningGrid
        v-if="viewMode === 'grid'"
        :ponts="ponts"
        :rdvs="activePlanningRdvs"
        :horaires="horaires"
        :mecaniciens="mecaniciens"
        :can-create="canCreateRdv"
        :can-drag="canEditRdv"
        @select-rdv="onSelectRdv"
        @move-rdv="onMoveRdv"
        @create-at="onCreateAt"
        @dates-changed="onWeekChanged"
      />
      <PlanningList
        v-else
        :rdvs="activePlanningRdvs"
        :mecaniciens="mecaniciens"
        :can-create="canCreateRdv"
        @select-rdv="onSelectRdv"
        @action-transition="onListTransition"
        @create-request="openQuickCreate"
      />
    </template>

    <!-- Quick Create Modal -->
    <AppModal v-model:open="showQuickCreateModal" size="xl">
      <template #content>
        <UCard>
          <template #header>
            <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
              <span style="font-weight:700;color:#E8E9ED;">Créer un RDV rapide</span>
              <UButton color="neutral" variant="ghost" icon="i-heroicons-x-mark" @click="showQuickCreateModal = false" />
            </div>
          </template>

          <div v-if="quickSubmitting" class="flex-col-gap-lg">
            <AppSkeletonCard :lines="4" />
            <AppSkeletonCard :lines="4" />
            <AppSkeletonCard :lines="3" />
          </div>

          <div v-else class="flex-col-gap-lg">
            <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;">
              <div class="form-group">
                <label class="form-label">Date</label>
                <input v-model="quickForm.date_rdv" type="date" class="form-input" required />
              </div>
              <div class="form-group">
                <label class="form-label">Heure début</label>
                <input v-model="quickForm.heure_debut" type="time" class="form-input" required />
              </div>
              <div class="form-group">
                <label class="form-label">Fin estimée</label>
                <div class="form-input form-input-static">{{ quickEstimatedEnd }}</div>
              </div>
            </div>

            <div class="panel-sm">
              <div class="form-label mb-1">Recherche client</div>
              <input
                v-model="quickClientSearch"
                type="text"
                class="form-input"
                placeholder="Nom, prénom, téléphone ou email..."
                @input="searchQuickClients"
              />
              <div v-if="quickClientResults.length" style="margin-top:8px;border:1px solid rgba(255,255,255,0.06);border-radius:10px;max-height:160px;overflow:auto;">
                <button
                  v-for="client in quickClientResults"
                  :key="`quick-client-${client.id}`"
                  type="button"
                  style="width:100%;text-align:left;padding:10px 12px;background:transparent;border:none;color:#D1D5DB;cursor:pointer;border-bottom:1px solid rgba(255,255,255,0.04);"
                  @click="selectQuickClient(client)"
                >
                  <strong>{{ client.prenom }} {{ client.nom }}</strong> · {{ client.telephone || client.email || '—' }}
                </button>
              </div>
              <div v-if="quickSelectedClient" style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:8px;padding:8px 10px;border-radius:8px;background:rgba(255,210,0,0.08);border:1px solid rgba(255,210,0,0.2);">
                <span style="font-size:12px;color:#FDE68A;">Client lié : {{ quickSelectedClient.prenom }} {{ quickSelectedClient.nom }}</span>
                <button type="button" class="btn btn-ghost" style="padding:4px 10px;min-height:30px;" @click="clearQuickClient">Changer</button>
              </div>
            </div>

            <div class="panel-sm">
              <div class="form-label mb-1">Recherche véhicule par plaque / VIN</div>
              <div style="display:grid;grid-template-columns:1fr auto;gap:10px;align-items:end;">
                <input
                  v-model="quickVehicleSearch"
                  type="text"
                  class="form-input"
                  placeholder="AA-123-BB ou VIN"
                  @blur="quickVehicleSearch = formatRegistrationOrVin(quickVehicleSearch)"
                  @keydown.enter.prevent="searchQuickVehicle"
                />
                <button type="button" class="btn btn-ghost" @click="searchQuickVehicle">Rechercher</button>
              </div>
              <div v-if="quickVehicleFound" class="mt-2 text-success">Véhicule trouvé et proposé.</div>
            </div>

            <div class="info-grid">
              <div class="form-group">
                <label class="form-label">Prénom client</label>
                <input v-model="quickForm.client_prenom" class="form-input" placeholder="Jean" />
              </div>
              <div class="form-group">
                <label class="form-label">Nom client</label>
                <input v-model="quickForm.client_nom" class="form-input" placeholder="Dupont" />
              </div>
              <div class="form-group">
                <label class="form-label">Téléphone</label>
                <input v-model="quickForm.client_telephone" class="form-input" placeholder="06…" />
              </div>
              <div class="form-group">
                <label class="form-label">Email</label>
                <input v-model="quickForm.client_email" type="email" class="form-input" placeholder="client@email.fr" />
              </div>
              <div class="form-group">
                <label class="form-label">Marque</label>
                <input v-model="quickForm.vehicule_marque" class="form-input" placeholder="Yamaha" @input="onQuickMarqueInput" @blur="hideQuickMarqueSuggestions" />
                <div v-if="quickMarqueSuggestions.length" class="mt-2" style="display:grid;gap:4px;">
                  <button
                    v-for="item in quickMarqueSuggestions"
                    :key="`quick-brand-${item}`"
                    type="button"
                    style="text-align:left;padding:7px 10px;border-radius:8px;border:1px solid rgba(255,255,255,0.06);background:rgba(255,255,255,0.03);color:#D1D5DB;font-size:12px;cursor:pointer;"
                    @mousedown.prevent="selectQuickMarque(item)"
                  >
                    {{ item }}
                  </button>
                </div>
              </div>
              <div class="form-group">
                <label class="form-label">Modèle</label>
                <input v-model="quickForm.vehicule_modele" class="form-input" placeholder="MT-07" @input="onQuickModeleInput" @blur="hideQuickModeleSuggestions" />
                <div v-if="quickModeleSuggestions.length" class="mt-2" style="display:grid;gap:4px;">
                  <button
                    v-for="item in quickModeleSuggestions"
                    :key="`quick-model-${item.id || item.modele}`"
                    type="button"
                    style="text-align:left;padding:7px 10px;border-radius:8px;border:1px solid rgba(255,255,255,0.06);background:rgba(255,255,255,0.03);color:#D1D5DB;font-size:12px;cursor:pointer;"
                    @mousedown.prevent="selectQuickModele(item)"
                  >
                    {{ quickSuggestionLabel(item) }}
                  </button>
                </div>
              </div>
              <div class="form-group">
                <label class="form-label">Plaque</label>
                <input v-model="quickForm.vehicule_plaque" class="form-input" placeholder="AA-123-BB" @blur="quickForm.vehicule_plaque = formatRegistrationOrVin(quickForm.vehicule_plaque)" />
              </div>
              <div class="form-group">
                <label class="form-label">Cylindrée</label>
                <input v-model="quickForm.vehicule_cylindree" class="form-input" placeholder="700" />
              </div>
              <div class="form-group">
                <label class="form-label">Type moto</label>
                <select v-model="quickForm.vehicule_type" class="form-input">
                  <option value="">Tous</option>
                  <option v-for="type in MOTO_TYPES" :key="`quick-type-${type}`" :value="type">{{ type }}</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Type / prestation libre</label>
                <input v-model="quickForm.type_intervention" class="form-input" placeholder="Révision / vidange" />
              </div>
              <div class="form-group">
                <label class="form-label">Pont</label>
                <select v-model.number="quickForm.pont_id" class="form-input">
                  <option :value="null">Choisir un pont</option>
                  <option v-for="p in assignablePonts" :key="`create-p-${p.id}`" :value="p.id">{{ p.nom }} · {{ getPontMecanicienLabel(p) }}</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Mécanicien affecté</label>
                <div class="form-input form-input-static">{{ quickAssignedMecanicienLabel }}</div>
              </div>
            </div>

            <div>
              <div class="form-label" style="margin-bottom:8px;">Prestations atelier <span class="text-sm-muted">· filtrées selon le type de moto</span></div>
              <div v-if="filteredQuickPrestations.length" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:8px;max-height:220px;overflow:auto;">
                <label
                  v-for="presta in filteredQuickPrestations"
                  :key="presta.id"
                  style="display:flex;gap:8px;align-items:flex-start;padding:10px 12px;border-radius:10px;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06);cursor:pointer;"
                >
                  <input v-model="quickSelectedPrestas" :value="presta.id" type="checkbox" style="margin-top:2px;accent-color:#FFD200;" />
                  <div>
                    <div style="font-size:13px;font-weight:700;color:#E8E9ED;">{{ presta.nom }}</div>
                    <div class="text-sm-muted">{{ formatCurrency(presta.prix_base_ttc ?? presta.prix_base_ht) }} · {{ formatDuration(presta.temps_estime_minutes || 60) }}</div>
                  </div>
                </label>
              </div>
              <div v-else class="panel-sm dashed text-md-muted">
                Aucune prestation ne correspond encore au type de moto renseigné.
              </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr auto;gap:12px;align-items:end;">
              <div class="form-group" style="margin:0;">
                <label class="form-label">Commentaire</label>
                <textarea v-model="quickForm.commentaire" class="form-input" rows="3" placeholder="Description du besoin client…"></textarea>
              </div>
              <div style="min-width:200px;padding:12px;border-radius:10px;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06);">
                <div class="text-sm-muted">Estimé</div>
                <div style="font-size:18px;font-weight:800;color:#FFD200;">{{ formatCurrency(quickEstimateTotal) }}</div>
                <div class="text-md-value">{{ quickEstimateDuration }} min</div>
                <div class="text-md-value">{{ quickForm.heure_debut || '—' }} → {{ quickEstimatedEnd }}</div>
              </div>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:10px;">
              <button class="btn btn-ghost" @click="showQuickCreateModal = false">Annuler</button>
              <button class="btn btn-primary" :disabled="quickSubmitting" @click="submitQuickCreate">
                {{ quickSubmitting ? 'Création…' : 'Créer le RDV' }}
              </button>
            </div>
          </div>
        </UCard>
      </template>
    </AppModal>


    <!-- Edit Modal (tabs) -->
    <AppModal v-model:open="showRdvModal" size="xl">
      <template #content>
        <UCard>
          <template #header>
            <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;">
              <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                <span style="font-weight:700;color:#E8E9ED;font-size:15px;">RDV #{{ selectedRdv?.id }}</span>
                <StatusBadge v-if="selectedRdv" :status="selectedRdv.status" />
                <span v-if="selectedIsHistorical" style="font-size:11px;color:#10B981;background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.2);padding:4px 10px;border-radius:999px;">Historisé · figé</span>
              </div>
              <UButton color="neutral" variant="ghost" icon="i-heroicons-x-mark" @click="showRdvModal = false" />
            </div>
          </template>

          <div v-if="modalLoading" class="panel-sm text-muted">Chargement du rendez-vous…</div>

          <div v-else-if="selectedRdv" class="flex-col-gap-lg">
            <!-- Custom tabs -->
            <div style="display:flex;gap:4px;border-bottom:1px solid rgba(255,255,255,0.06);padding-bottom:8px;">
              <button
                v-for="tab in rdvEditTabItems"
                :key="tab.value"
                style="padding:6px 14px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;background:transparent;border:none;"
                :style="editTab === tab.value ? 'color:#E8E9ED;background:rgba(255,255,255,0.06);' : 'color:#6B7280;'"
                @click="editTab = tab.value"
              >
                {{ tab.label }}
              </button>
            </div>

            <!-- TAB 0: GÉNÉRAL -->
            <div v-show="editTab === '0'" class="flex-col-gap-lg">
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:13px;">
                <div><span class="text-subtle">Date :</span> <span class="text-value">{{ formatDateDisplay(selectedRdv.date_rdv) }}</span></div>
                <div><span class="text-subtle">Heure :</span> <span class="text-value">{{ selectedRdv.heure_debut }}</span></div>
                <div><span class="text-subtle">Client :</span> <span class="text-value">{{ selectedRdv.client_nom || '—' }}</span><span v-if="selectedRdv.source === 'web'" style="margin-left:8px;padding:2px 7px;border-radius:4px;font-size:10px;font-weight:700;background:rgba(59,130,246,0.2);color:#93C5FD;border:1px solid rgba(59,130,246,0.3);letter-spacing:0.05em;">PUBLIC</span></div>
                <div><span class="text-subtle">Véhicule :</span> <span class="text-value">{{ selectedRdv.vehicule_info || '—' }}</span></div>
                <div><span class="text-subtle">Pont :</span> <span class="text-value">{{ selectedRdv.pont?.nom || selectedRdv.pont_nom || '—' }}</span></div>
                <div><span class="text-subtle">Mécanicien :</span> <span class="text-value">{{ selectedRdv.mecanicien_nom || '—' }}</span></div>
              </div>

              <div class="info-grid">
                <div class="panel-sm text-md-value">
                  <div class="header-md" style="margin-bottom:10px;">Client et véhicule</div>
                  <div class="info-grid-sm">
                    <div v-if="selectedRdv.client?.telephone || selectedRdv.client_telephone"><span class="text-subtle">Téléphone :</span> <span class="text-value">{{ selectedRdv.client?.telephone || selectedRdv.client_telephone }}</span></div>
                    <div v-if="selectedRdv.client?.email || selectedRdv.client_email"><span class="text-subtle">Email :</span> <span class="text-value">{{ selectedRdv.client?.email || selectedRdv.client_email }}</span></div>
                    <div v-if="selectedRdv.vehicule?.plaque || selectedRdv.vehicule_plaque"><span class="text-subtle">Plaque :</span> <span class="text-value">{{ selectedRdv.vehicule?.plaque || selectedRdv.vehicule_plaque }}</span></div>
                    <div v-if="selectedRdv.vehicule?.cylindree || selectedRdv.vehicule?.annee"><span class="text-subtle">Moto :</span> <span class="text-value">{{ [selectedRdv.vehicule?.cylindree ? `${selectedRdv.vehicule.cylindree}cc` : '', selectedRdv.vehicule?.annee || ''].filter(Boolean).join(' · ') }}</span></div>
                    <div v-if="selectedRdv.vehicule?.typeMoto || selectedRdv.vehicule_type"><span class="text-subtle">Type :</span> <span class="text-value">{{ selectedRdv.vehicule?.typeMoto || selectedRdv.vehicule_type }}</span></div>
                  </div>
                </div>

                <div class="panel-sm text-md-value">
                  <div class="header-md" style="margin-bottom:10px;">Lecture dossier</div>
                  <div class="info-grid-sm">
                    <div><span class="text-subtle">Statut métier :</span> <span class="text-value">{{ workflowStatusHint }}</span></div>
                    <div v-if="selectedRdv.kilometrage || selectedRdv.km_reception"><span class="text-subtle">Km réception :</span> <span class="text-value">{{ selectedRdv.kilometrage || selectedRdv.km_reception }}</span></div>
                    <div v-if="selectedReceptionState.observations"><span class="text-subtle">État réception :</span> <span class="text-value">{{ selectedReceptionState.observations }}</span></div>
                    <div v-if="selectedReceptionState.reception_notes"><span class="text-subtle">Notes réception :</span> <span class="text-value">{{ selectedReceptionState.reception_notes }}</span></div>
                    <div v-if="selectedRdv.ordresReparation?.length"><span class="text-subtle">OR :</span> <span class="text-value">{{ selectedRdv.ordresReparation.length }} dossier(s) lié(s)</span></div>
                  </div>
                </div>
              </div>

              <div class="panel-sm">
                <div class="header-md" style="margin-bottom:10px;">Édition rapide / affectation</div>
                <div class="info-grid">
                  <div class="form-group">
                    <label class="form-label">Date</label>
                    <input v-model="editForm.date_rdv" type="date" class="form-input" :disabled="selectedIsHistorical || !canEditRdv" />
                  </div>
                  <div class="form-group">
                    <label class="form-label">Heure début</label>
                    <input v-model="editForm.heure_debut" type="time" class="form-input" :disabled="selectedIsHistorical || !canEditRdv" />
                  </div>
                  <div class="form-group">
                    <label class="form-label">Prestation / type</label>
                    <input v-model="editForm.type_intervention" class="form-input" :disabled="selectedIsHistorical || !canEditRdv || prestationLocked" />
                  </div>
                  <div class="form-group">
                    <label class="form-label">Pont</label>
                    <select v-model.number="editForm.pont_id" class="form-input" :disabled="selectedIsHistorical || !canEditRdv">
                      <option :value="null">Non assigné</option>
                      <option v-for="p in assignablePonts" :key="`edit-p-${p.id}`" :value="p.id">{{ p.nom }} · {{ getPontMecanicienLabel(p) }}</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label class="form-label">Mécanicien affecté</label>
                    <div class="form-input form-input-static">{{ editAssignedMecanicienLabel }}</div>
                  </div>
                </div>
              </div>

              <!-- Reception Panel (déplacé du tab Détails vers Général) -->
              <div v-if="isReceptionEligible" style="padding:14px;border-radius:12px;background:rgba(255,210,0,0.04);border:1px solid rgba(255,210,0,0.15);">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                  <div style="font-size:14px;font-weight:700;color:#FFD200;">📥 Réception du véhicule</div>
                  <UButton color="warning" variant="ghost" size="sm" icon="i-heroicons-arrow-path" label="Statut PDA" @click="refreshCompanionStatus" />
                </div>
                <div style="display:flex;gap:12px;align-items:flex-start;margin-bottom:14px;padding:12px;border-radius:10px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);">
                  <div style="flex:1;">
                    <div style="font-size:12px;font-weight:600;color:#E8E9ED;margin-bottom:6px;">📱 Compagnon PDA</div>
                    <p style="font-size:11px;color:#9CA3AF;margin:0 0 8px;">Ouvrez ce lien sur le téléphone pour : photos, scan carte grise, checkup express, signature client.</p>
                    <div style="display:flex;gap:6px;align-items:center;">
                      <input :value="companionUrl" class="form-input" style="font-size:13px;flex:1;min-height:44px;" readonly @focus="($event.target as HTMLInputElement)?.select()" />
                      <UButton color="neutral" variant="ghost" icon="i-heroicons-clipboard-document" @click="copyCompanionUrl" style="min-height:44px;" />
                    </div>
                  </div>
                  <div v-if="companionQrUrl" style="min-width:100px;text-align:center;">
                    <img :src="companionQrUrl" alt="QR Code" style="width:100px;height:100px;border-radius:8px;background:white;padding:4px;" />
                    <div style="font-size:10px;color:#6B7280;margin-top:4px;">Scanner avec le tél.</div>
                  </div>
                </div>
                <div style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap;">
                  <div class="reception-status-pill" :class="{ done: companionStatus.photos_count > 0 }">
                    📸 {{ companionStatus.photos_count || 0 }} photo{{ (companionStatus.photos_count || 0) !== 1 ? 's' : '' }}
                  </div>
                  <div class="reception-status-pill" :class="{ done: companionStatus.checkup_done > 0 }">
                    🔎 Checkup {{ companionStatus.checkup_done || 0 }}/10
                  </div>
                  <div class="reception-status-pill" :class="{ done: companionStatus.has_signature }">
                    ✍️ Signature {{ companionStatus.has_signature ? '✓' : '✗' }}
                  </div>
                </div>
                <div class="info-grid">
                  <div class="form-group">
                    <label class="form-label">Kilométrage réception</label>
                    <input v-model="receptionForm.kilometrage" type="number" class="form-input" placeholder="km" :disabled="selectedIsHistorical" />
                  </div>
                  <div class="form-group">
                    <label class="form-label">Observations visuelles</label>
                    <input v-model="receptionForm.etat_vehicule" class="form-input" placeholder="Rayure, choc, état extérieur…" :disabled="selectedIsHistorical" />
                  </div>
                </div>
                <div class="form-group mt-3">
                  <label class="form-label">Notes réception</label>
                  <textarea v-model="receptionForm.notes_reception" class="form-input" rows="2" placeholder="Contexte comptoir, point de vigilance réception…" :disabled="selectedIsHistorical"></textarea>
                </div>
                <div v-if="!companionStatus.has_signature" style="margin-top:10px;padding:8px 12px;border-radius:8px;background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);font-size:12px;color:#FCA5A5;">
                  ⚠️ Signature client obligatoire pour valider la réception. Utilisez le compagnon PDA pour faire signer.
                </div>
              </div>
            </div>

            <!-- TAB 1: DÉTAILS -->
            <div v-show="editTab === '1'" class="flex-col-gap-lg">
              <div class="panel-sm">
                <div class="info-grid">
                  <div class="form-group">
                    <label class="form-label">Durée estimée</label>
                    <input v-model="editDureeHHMM" type="time" class="form-input" :disabled="selectedIsHistorical || !canEditRdv || prestationLocked" />
                  </div>
                  <div class="form-group">
                    <label class="form-label">Priorité</label>
                    <select v-model="editForm.priorite" class="form-input" :disabled="selectedIsHistorical || !canEditRdv">
                      <option value="haute">Haute</option>
                      <option value="normale">Normale</option>
                      <option value="basse">Basse</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label class="form-label">Tags</label>
                    <input v-model="editTagsInput" class="form-input" :disabled="selectedIsHistorical || !canEditRdv" placeholder="Urgent, garantie, client-fidèle…" />
                  </div>
                </div>
                <div class="form-group mt-3">
                  <label class="form-label">Commentaire</label>
                  <textarea v-model="editForm.commentaire" class="form-input" rows="3" :disabled="selectedIsHistorical || !canEditRdv" placeholder="Description du besoin exprimé par le client…"></textarea>
                </div>
                <div v-if="prestationLocked" style="margin-top:10px;padding:8px 12px;border-radius:8px;background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.2);font-size:12px;color:#FBBF24;">
                  Après la réception, la prestation et sa durée sont figées. Seules l'affectation, la note et le suivi restent modifiables.
                </div>
              </div>

            </div>

            <!-- TAB 2: PLANNING -->
            <div v-show="editTab === '2'" class="flex-col-gap-lg">
              <div v-if="!editForm.mecanicien_id && !selectedRdv?.mecanicien_id" class="panel-sm text-muted">
                Aucun mécanicien sélectionné. Choisissez un pont ou un mécanicien dans l'onglet Général pour voir son planning.
              </div>
              <div v-else>
                <div class="panel-sm" style="margin-bottom:12px;">
                  <div class="header-md" style="margin-bottom:8px;">Planning de {{ editAssignedMecanicienLabel }}</div>
                  <div class="text-sm-muted">Semaine du {{ currentViewRange.start }} au {{ currentViewRange.end }}</div>
                </div>
                <div v-if="!currentMecanicienRdvs.length" class="text-muted" style="padding:12px 0;">Aucun RDV assigné à ce mécanicien sur la période.</div>
                <div v-else style="display:flex;flex-direction:column;gap:8px;">
                  <div v-for="r in currentMecanicienRdvs" :key="r.id" style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;border-radius:8px;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06);">
                    <div>
                      <div style="font-size:13px;font-weight:600;color:#E8E9ED;">{{ r.type_intervention || '—' }}</div>
                      <div style="font-size:11px;color:#6B7280;">{{ r.client_nom || '—' }} · {{ formatDateDisplay(r.date_rdv) }} {{ r.heure_debut?.slice(0,5) }}</div>
                    </div>
                    <StatusBadge :status="r.status || r.statut" />
                  </div>
                </div>
              </div>
            </div>

            <!-- TAB 3: HISTORIQUE -->
            <div v-show="editTab === '3'" class="flex-col-gap-lg">
              <div v-if="selectedRdvHistoryLoading" class="panel-sm text-muted">
                Chargement de l'historique…
              </div>
              <div v-else-if="!selectedRdvHistory.length" class="panel-sm text-muted">
                Aucun historique de modifications disponible pour ce rendez-vous.
              </div>
              <div v-else style="display:flex;flex-direction:column;gap:8px;">
                <div v-for="(entry, idx) in selectedRdvHistory" :key="idx" style="padding:10px 12px;border-radius:8px;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06);font-size:12px;">
                  <div style="display:flex;justify-content:space-between;">
                    <span style="font-weight:600;color:#E8E9ED;">{{ entry.action || entry.type || 'Modification' }}</span>
                    <span style="color:#6B7280;">{{ entry.date || entry.created_at || entry.createdAt }}</span>
                  </div>
                  <div v-if="entry.details || entry.description" style="margin-top:4px;color:#9CA3AF;">{{ entry.details || entry.description }}</div>
                  <div v-if="entry.utilisateur || entry.user" style="margin-top:4px;color:#6B7280;">Par {{ entry.utilisateur || entry.user }}</div>
                </div>
              </div>
            </div>

            <!-- WORKFLOW ATELIER (always visible) -->
            <div class="panel-sm">
              <div class="header-md" style="margin-bottom:10px;">Workflow atelier</div>
              <div style="margin-bottom:10px;padding:10px 12px;border-radius:8px;background:rgba(255,255,255,0.03);font-size:12px;color:#CBD5E1;border:1px solid rgba(255,255,255,0.06);">
                {{ workflowTransitionHint }}
              </div>
              <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button
                  v-for="transition in primaryTransitions"
                  :key="transition.name"
                  class="btn"
                  :style="transitionButtonStyle(transition.color)"
                  :disabled="transitioning === transition.name || selectedIsHistorical"
                  @click="applyTransition(transition.name)"
                >
                  {{ transitioning === transition.name ? 'Traitement…' : transitionLabel(transition) }}
                </button>
              </div>
              <div v-if="secondaryTransitions.length" style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px;padding-top:8px;border-top:1px solid rgba(255,255,255,0.06);">
                <button
                  v-for="transition in secondaryTransitions"
                  :key="transition.name"
                  class="btn btn-ghost btn-sm"
                  style="font-size:11px;opacity:0.75;"
                  :disabled="transitioning === transition.name || selectedIsHistorical"
                  @click="applyTransition(transition.name)"
                >
                  {{ transitioning === transition.name ? 'Traitement…' : transitionLabel(transition) }}
                </button>
              </div>
            </div>

            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
              <div v-if="selectedRdv.commentaire_client || selectedRdv.commentaire" class="text-md-value">{{ selectedRdv.commentaire_client || selectedRdv.commentaire }}</div>
            </div>

            <!-- BOTTOM NAVIGATION -->
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:16px;padding-top:16px;border-top:1px solid rgba(255,255,255,0.06);flex-wrap:wrap;gap:10px;">
              <div style="display:flex;gap:8px;">
                <UButton v-if="editTab !== '0'" size="sm" variant="ghost" color="neutral" @click="prevTab">← Précédent</UButton>
                <UButton v-if="Number(editTab) < (selectedRdv?.id ? 3 : 2)" size="sm" variant="ghost" color="neutral" @click="nextTab">Suivant →</UButton>
              </div>
              <div style="display:flex;gap:10px;">
                <button class="btn btn-ghost" @click="showRdvModal = false">Fermer</button>
                <button v-if="canEditRdv && !selectedIsHistorical" class="btn btn-primary" :disabled="editSaving" @click="saveRdvChanges">
                  {{ editSaving ? 'Sauvegarde…' : 'Enregistrer' }}
                </button>
                <button v-if="canDeleteSelected" class="btn" style="background:rgba(239,68,68,0.14);color:#FCA5A5;border-color:rgba(239,68,68,0.28);" :disabled="deleting" @click="deleteSelectedRdv">
                  {{ deleting ? 'Suppression…' : 'Supprimer' }}
                </button>
              </div>
            </div>
          </div>
        </UCard>
      </template>
    </AppModal>



    <!-- Annulation Modal -->
    <AppModal v-model:open="showAnnulationModal" size="md">
      <template #default>
        <UCard>
          <template #header>
            <div class="flex items-center justify-between">
              <h3 class="text-xl font-bold">{{ selectedRdv?.statut === "en_attente" ? "Refuser la demande" : "Annuler le rendez-vous" }}</h3>
              <UButton color="neutral" variant="ghost" icon="i-heroicons-x-mark" @click="showAnnulationModal = false" />
            </div>
          </template>

          <form @submit.prevent="submitAnnulation" class="flex-col-gap-lg">
            <div class="form-group">
              <label class="form-label" style="margin-bottom:6px;display:block;">Motif d'annulation <span style="color:#EF4444;">*</span></label>
              <select v-model="annulationForm.motif" required style="width:100%;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.1);color:#fff;border-radius:10px;padding:10px;font-size:14px;outline:none;">
                <option v-for="motif in ANNULATION_MOTIFS" :key="motif.value" :value="motif.value">{{ motif.label }}</option>
              </select>
            </div>
            
            <div class="form-group">
              <label class="form-label" style="margin-bottom:6px;display:block;">Commentaire / Détails</label>
              <textarea v-model="annulationForm.commentaire" placeholder="Explications..." style="width:100%;min-height:80px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.1);color:#fff;border-radius:10px;padding:10px;font-size:14px;outline:none;resize:vertical;"></textarea>
            </div>
            
            <div style="margin-top:8px;" v-if="(selectedRdv?.status ?? selectedRdv?.statut) === 'en_attente'">
              <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:14px;color:#E8E9ED;">
                <input type="checkbox" v-model="annulationForm.proposer_alternatives" style="accent-color:#F59E0B;width:16px;height:16px;" />
                Proposer des créneaux alternatifs au client par email/SMS
              </label>
              
              <div v-if="annulationForm.proposer_alternatives" class="form-group mt-3">
                <label class="form-label" style="margin-bottom:6px;display:block;">Message / Créneaux proposés</label>
                <textarea v-model="annulationForm.creneaux_alternatifs" placeholder="Ex: le 15/05 à 10h ou 14h. Sinon merci de choisir un nouveau créneau via le lien de prise de RDV." style="width:100%;min-height:80px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.1);color:#fff;border-radius:10px;padding:10px;font-size:14px;outline:none;resize:vertical;"></textarea>
              </div>
            </div>

            <div style="margin-top:16px;display:flex;justify-content:flex-end;gap:8px;">
              <button type="button" class="btn btn-ghost" @click="showAnnulationModal = false">Fermer</button>
              <button type="submit" class="btn btn-error" :disabled="transitioning !== ''">
                Confirmer l'annulation
              </button>
            </div>
          </form>
        </UCard>
      </template>
    </AppModal>
  </div>
</template>

<script setup lang="ts">
const api = useApi()
const { formatDuration } = useFormat()
const toast = useToast()
const rdvStore = useRdvStore()
const route = useRoute()
const router = useRouter()
const { hasPerm } = useAuth()

const loading = ref(true)
const refreshing = ref(false)
const modalLoading = ref(false)
const quickSubmitting = ref(false)
const editSaving = ref(false)
const deleting = ref(false)
const transitioning = ref('')

const ponts = ref<any[]>([])
const rawRdvs = ref<any[]>([])
const mecaniciens = ref<any[]>([])
const horaires = ref<any[]>([])
const prestations = ref<any[]>([])
const activeMecas = ref<number[]>([])
const availableTransitions = ref<Array<{ name: string; label: string; color: string }>>([])
const primaryTransitions = computed(() => availableTransitions.value.filter(t => !SECONDARY_TRANSITIONS.has(t.name)))
const secondaryTransitions = computed(() => availableTransitions.value.filter(t => SECONDARY_TRANSITIONS.has(t.name)))

const showRdvModal = ref(false)
const showAnnulationModal = ref(false)
const annulationTempName = ref("annuler")
const editTab = ref('0')
const viewMode = ref<'grid' | 'list'>('grid')
const selectedRdvHistory = ref<any[]>([])
const selectedRdvHistoryLoading = ref(false)

const ANNULATION_MOTIFS = [
  { label: "Client décommandé", value: "client_desiste" },
  { label: "Atelier indisponible", value: "atelier_indisponible" },
  { label: "Erreur / Doublon", value: "erreur_saisie" },
  { label: "Véhicule vendu", value: "vehicule_vendu" },
  { label: "Conditions météo", value: "meteo" },
  { label: "Autre", value: "autre" }
]

const annulationForm = reactive({
  motif: "autre",
  commentaire: "",
  proposer_alternatives: false,
  creneaux_alternatifs: ""
})

function confirmAnnulation(name: string) {
  const status = selectedRdv.value?.status ?? selectedRdv.value?.statut
  annulationTempName.value = name
  annulationForm.motif = status === "en_attente" ? "atelier_indisponible" : "client_desiste"
  annulationForm.commentaire = ""
  annulationForm.proposer_alternatives = false
  annulationForm.creneaux_alternatifs = ""
  showRdvModal.value = false
  showAnnulationModal.value = true
}

async function submitAnnulation() {
  await applyTransition(annulationTempName.value, {
    motif: annulationForm.motif,
    commentaire: annulationForm.commentaire,
    proposer_alternatives: annulationForm.proposer_alternatives,
    creneaux_alternatifs: annulationForm.creneaux_alternatifs
  })
  showAnnulationModal.value = false
}

const showQuickCreateModal = ref(false)
const selectedRdv = ref<any | null>(null)
const quickSelectedPrestas = ref<number[]>([])
const quickClientSearch = ref('')
const quickClientResults = ref<any[]>([])
const quickSelectedClient = ref<any | null>(null)
const quickVehicleSearch = ref('')
const quickVehicleFound = ref(false)

const HISTORY_STATUSES = ['termine', 'restitue', 'facture', 'paye', 'annule']
const PRESTATION_LOCK_STATUSES = ['reception', 'en_cours', 'termine', 'restitue', 'facture', 'paye']
const DAY_LABELS = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim']
const MOTO_TYPES = ['Roadster', 'Sportive', 'Trail', 'Custom', 'Scooter', 'Enduro', 'Adventure', 'GT']

const editForm = reactive({
  date_rdv: '',
  heure_debut: '10:00',
  type_intervention: '',
  temps_estime: 60,
  commentaire: '',
  mecanicien_id: null as number | null,
  pont_id: null as number | null,
  priorite: 'normale' as 'haute' | 'normale' | 'basse',
  tags: [] as string[],
})

const editDureeHHMM = computed({
  get(): string {
    const total = Math.max(0, Math.round(Number(editForm.temps_estime ?? 60)))
    const h = Math.floor(total / 60)
    const m = total % 60
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`
  },
  set(val: string) {
    const [h, m] = val.split(':').map(Number)
    editForm.temps_estime = ((h ?? 0) * 60) + (m ?? 0) || 15
  },
})

const editTagsInput = computed({
  get: () => (editForm.tags || []).join(', '),
  set: (val: string) => { editForm.tags = val.split(',').map(s => s.trim()).filter(Boolean) }
})

const receptionForm = reactive({
  kilometrage: '',
  etat_vehicule: '',
  notes_reception: '',
})

const quickForm = reactive({
  date_rdv: new Date().toISOString().slice(0, 10),
  heure_debut: '10:00',
  client_id: null as number | null,
  client_prenom: '',
  client_nom: '',
  client_telephone: '',
  client_email: '',
  vehicule_marque: '',
  vehicule_modele: '',
  vehicule_plaque: '',
  vehicule_cylindree: '',
  vehicule_type: '',
  type_intervention: '',
  commentaire: '',
  mecanicien_id: null as number | null,
  pont_id: null as number | null,
})

const {
  marqueSuggestions: quickMarqueSuggestions,
  modeleSuggestions: quickModeleSuggestions,
  onMarqueInput: onQuickMarqueInput,
  onModeleInput: onQuickModeleInput,
  selectMarque: selectQuickMarque,
  selectModele: selectQuickModele,
  deferHideMarqueSuggestions: hideQuickMarqueSuggestions,
  deferHideModeleSuggestions: hideQuickModeleSuggestions,
  suggestionLabel: quickSuggestionLabel,
} = useMotoAutocomplete({
  form: quickForm,
  marqueKey: 'vehicule_marque',
  modeleKey: 'vehicule_modele',
  cylindreeKey: 'vehicule_cylindree',
  typeKey: 'vehicule_type',
})

const transitionCatalog: Record<string, { label: string; color: string }> = {
  reserver: { label: '📌 Réserver le créneau', color: 'neutral' },
  confirmer: { label: '✅ Valider et confirmer', color: 'primary' },
  reception: { label: '📥 Réceptionner', color: 'warning' },
  start_travail: { label: '🔧 Démarrer', color: 'warning' },
  terminer: { label: '✅ Terminer', color: 'success' },
  restituer: { label: '🚚 Restituer', color: 'info' },
  facturer: { label: '💶 Facturer', color: 'primary' },
  payer: { label: '💳 Encaisser', color: 'success' },
  annuler: { label: '❌ Annuler', color: 'error' },
  declarer_no_show: { label: '👻 Déclarer No Show', color: 'error' },
  reporter: { label: '📆 Reporter', color: 'neutral' },
  mettre_en_gardiennage: { label: '🔒 Gardiennage', color: 'warning' },
  sortir_gardiennage: { label: '🔓 Sortir gardiennage', color: 'success' },
}

const HIDDEN_RECEPTION_TRANSITIONS = [
  'start_travail', 
  'mettre_en_pause', 
  'reprendre', 
  'terminer',
  'mettre_en_attente_pieces', 
  'reprendre_apres_pieces', 
  'attendre_pieces', 
  'mettre_en_attente_reprise', 
  'reprendre_demain',
  'no_show' // using declarer_no_show instead
]

const SECONDARY_TRANSITIONS = new Set(['annuler', 'declarer_no_show', 'reporter', 'mettre_en_gardiennage', 'passer_gardiennage'])

const canCreateRdv = computed(() => hasPerm('rdv.create'))
const canEditRdv = computed(() => hasPerm('rdv.edit'))
const canDeleteRdv = computed(() => hasPerm('rdv.delete'))
const selectedIsHistorical = computed(() => isHistoricalStatus(selectedRdv.value?.status ?? selectedRdv.value?.statut))
const prestationLocked = computed(() => PRESTATION_LOCK_STATUSES.includes(selectedRdv.value?.status ?? selectedRdv.value?.statut ?? ''))
const canDeleteSelected = computed(() => canDeleteRdv.value && !!selectedRdv.value && !selectedIsHistorical.value)
const selectedReceptionState = computed(() => parseReceptionState(selectedRdv.value?.etat_vehicule_reception ?? selectedRdv.value?.etat_vehicule ?? selectedRdv.value?.etatVehicule))
const hasSchedulingEdits = computed(() => {
  if (!selectedRdv.value) return false
  return editForm.date_rdv !== (selectedRdv.value.date_rdv || '')
    || editForm.heure_debut !== (selectedRdv.value.heure_debut || '')
    || toNullableNumber(editForm.pont_id) !== toNullableNumber(selectedRdv.value.pont?.id ?? selectedRdv.value.pont_id)
})
const workflowStatusHint = computed(() => {
  switch (selectedRdv.value?.status ?? selectedRdv.value?.statut) {
    case 'en_attente':
      return 'Demande reçue, créneau pas encore bloqué'
    case 'reserve':
      return 'Créneau bloqué, validation comptoir encore requise'
    case 'confirme':
      return 'Disponibilité validée, prêt pour la réception'
    case 'reception':
      return 'Véhicule réceptionné, intervention prête à démarrer'
    default:
      return 'Suivi atelier en cours'
  }
})
const workflowTransitionHint = computed(() => {
  switch (selectedRdv.value?.status ?? selectedRdv.value?.statut) {
    case 'en_attente':
      return 'Demande entrante: ajuste le jour ou l’heure si nécessaire, puis réserve le créneau. Si la demande n’est pas retenue, refuse-la explicitement.'
    case 'reserve':
      return 'Le créneau est bloqué. Le réceptionnaire confirme si la disponibilité atelier est validée, ou reprogramme avant validation.'
    case 'confirme':
      return 'Le rendez-vous est validé. L’étape suivante normale est la réception physique du véhicule.'
    default:
      return 'Seules les transitions autorisées par le workflow sont proposées ici.'
  }
})

function normalizeText(value: unknown): string {
  return String(value ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim()
}

function sanitizeAlphaNum(value: string): string {
  return normalizeText(value).toUpperCase().replace(/[^A-Z0-9]/g, '')
}

function formatRegistrationOrVin(value: string): string {
  const cleaned = sanitizeAlphaNum(value)
  if (!cleaned) return ''
  if (cleaned.length >= 11) return cleaned
  if (/^[A-Z]{2}\d{3}[A-Z]{2}$/.test(cleaned)) {
    return `${cleaned.slice(0, 2)}-${cleaned.slice(2, 5)}-${cleaned.slice(5, 7)}`
  }
  return cleaned
}

function addMinutesToTime(time: string, minutesToAdd: number): string {
  const [hours, minutes] = String(time || '00:00').split(':').map(Number)
  const total = ((hours || 0) * 60) + (minutes || 0) + Math.max(15, toNumber(minutesToAdd, 60))
  return `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`
}

function getRelationId(value: any): number | null {
  if (typeof value === 'number' && Number.isFinite(value)) return value
  if (typeof value === 'string') {
    const parsed = Number(value.split('/').pop())
    return Number.isFinite(parsed) ? parsed : null
  }
  if (value && typeof value === 'object') {
    return getRelationId(value.id ?? value['@id'])
  }
  return null
}

function findPontById(value: any) {
  const id = toNullableNumber(value)
  if (!id) return null
  return ponts.value.find((pont: any) => Number(pont.id) === id) ?? null
}

function resolvePontMecanicienId(pontOrId: any): number | null {
  const pont = typeof pontOrId === 'object' ? pontOrId : findPontById(pontOrId)
  return toNullableNumber(pont?.mecanicien?.id ?? pont?.mecanicien_id ?? getRelationId(pont?.mecanicien))
}

function getMecanicienLabelById(value: any): string {
  const id = toNullableNumber(value)
  if (!id) return 'Non assigné'
  const mecanicien = mecaniciens.value.find((item: any) => Number(item.id) === id)
  return mecanicien ? `${mecanicien.prenom ?? ''} ${mecanicien.nom ?? ''}`.trim() : 'Non assigné'
}

function getPontMecanicienLabel(pontOrId: any): string {
  const pont = typeof pontOrId === 'object' ? pontOrId : findPontById(pontOrId)
  if (!pont) return 'Non assigné'
  if (pont.mecanicien && typeof pont.mecanicien === 'object') {
    const label = `${pont.mecanicien.prenom ?? ''} ${pont.mecanicien.nom ?? ''}`.trim()
    if (label) return label
  }
  return getMecanicienLabelById(resolvePontMecanicienId(pont))
}

function syncMecanicienFromPont(target: { pont_id: any; mecanicien_id: any }) {
  target.mecanicien_id = resolvePontMecanicienId(target.pont_id)
}

function prestationMatchesVehicle(prestation: any, source: any = quickForm) {
  const vehicleType = normalizeText(source.vehicule_type)
  const rawType = normalizeText(prestation.type_vehicule ?? prestation.typeVehicule ?? 'tous')
  const allowedTypes = rawType.split(/[;,/|]+/).map((item: string) => item.trim()).filter(Boolean)
  const typeMatches = !vehicleType
    || !allowedTypes.length
    || allowedTypes.includes('tous')
    || allowedTypes.includes('tout')
    || allowedTypes.includes('all')
    || allowedTypes.some((item: string) => item === vehicleType || item.includes(vehicleType) || vehicleType.includes(item))

  const cylindree = toNumber(source.vehicule_cylindree)
  const min = toNumber(prestation.cylindree_min ?? prestation.cylindreeMin)
  const max = toNumber(prestation.cylindree_max ?? prestation.cylindreeMax)
  const cylindreeMatches = (!min || !cylindree || cylindree >= min) && (!max || !cylindree || cylindree <= max)

  return typeMatches && cylindreeMatches
}

const assignablePonts = computed(() => {
  return [...ponts.value]
    .filter((pont: any) => Number(pont.isActive ?? pont.is_active ?? 1) !== 0)
    .sort((a: any, b: any) => String(a.nom || '').localeCompare(String(b.nom || '')))
})

const filteredQuickPrestations = computed(() => {
  return prestations.value.filter((presta: any) => prestationMatchesVehicle(presta, quickForm))
})

const quickSelectedPrestations = computed(() => filteredQuickPrestations.value.filter((presta: any) => quickSelectedPrestas.value.includes(Number(presta.id))))
const quickEstimateTotal = computed(() => quickSelectedPrestations.value.reduce((sum: number, presta: any) => sum + toNumber(presta.prix_base_ttc ?? presta.prix_base_ht), 0))
const quickEstimateDuration = computed(() => quickSelectedPrestations.value.reduce((sum: number, presta: any) => sum + toNumber(presta.temps_estime_minutes, 60), 0) || 60)
const quickEstimatedEnd = computed(() => addMinutesToTime(quickForm.heure_debut, quickEstimateDuration.value || 60))
const quickAssignedMecanicienLabel = computed(() => quickForm.pont_id ? getPontMecanicienLabel(quickForm.pont_id) : 'Affectation via le pont')
const editAssignedMecanicienLabel = computed(() => editForm.pont_id ? getPontMecanicienLabel(editForm.pont_id) : getMecanicienLabelById(editForm.mecanicien_id))

function unwrapList(data: any) {
  return data?.['hydra:member'] ?? data?.member ?? (Array.isArray(data) ? data : [])
}

async function withTimeout<T>(promise: Promise<T>, label: string, ms = 12000): Promise<T> {
  let timeoutId: ReturnType<typeof setTimeout> | null = null
  const timeoutPromise = new Promise<T>((_, reject) => {
    timeoutId = setTimeout(() => reject(new Error(`${label}_timeout`)), ms)
  })

  try {
    return await Promise.race([promise, timeoutPromise])
  } finally {
    if (timeoutId) clearTimeout(timeoutId)
  }
}

async function safeGet(path: string, fallback: any, label: string, timeoutMs = 12000) {
  try {
    return await withTimeout(api.get(path), label, timeoutMs)
  } catch {
    return fallback
  }
}

function toNumber(value: any, fallback = 0) {
  const parsed = Number(value ?? fallback)
  return Number.isFinite(parsed) ? parsed : fallback
}

function toNullableNumber(value: any) {
  const parsed = Number(value)
  return Number.isFinite(parsed) && parsed > 0 ? parsed : null
}

function normalizeDateValue(value: unknown): string {
  const raw = value ? String(value) : ''
  return raw ? raw.slice(0, 10) : ''
}

function normalizeTimeValue(value: unknown): string {
  const raw = value ? String(value) : ''
  const match = raw.match(/(\d{2}):(\d{2})/)
  return match ? `${match[1]}:${match[2]}` : ''
}

function normalizeHoraire(item: any) {
  return {
    ...item,
    jour_semaine: Number(item.jour_semaine ?? item.jourSemaine ?? 0),
    heure_ouverture: item.heure_ouverture ?? item.heureOuverture ?? '10:00',
    heure_fermeture: item.heure_fermeture ?? item.heureFermeture ?? '19:00',
    pause_debut: item.pause_debut ?? item.pauseDebut ?? null,
    pause_fin: item.pause_fin ?? item.pauseFin ?? null,
    is_ouvert: Number(item.is_ouvert ?? item.isOuvert ?? 1),
  }
}

function defaultHoraires() {
  return DAY_LABELS.map((_, index) => ({
    jour_semaine: index,
    heure_ouverture: '10:00',
    heure_fermeture: '19:00',
    pause_debut: index > 0 && index < 6 ? '12:00' : null,
    pause_fin: index > 0 && index < 6 ? '13:30' : null,
    is_ouvert: index !== 0 && index !== 6 ? 1 : 0,
  }))
}

function normalizeHoraires(items: any) {
  const source = Array.isArray(items) ? items : unwrapList(items)
  const fallback = defaultHoraires()
  if (!source.length) return fallback

  return DAY_LABELS.map((_, index) => {
    const found = source.find((h: any) => Number(h.jour_semaine ?? h.jourSemaine) === index)
    return found
      ? {
          ...fallback[index],
          ...normalizeHoraire(found),
          jour_semaine: index,
        }
      : fallback[index]
  })
}

function normalizePrestation(item: any) {
  return {
    ...item,
    prix_base_ht: toNumber(item.prix_base_ht ?? item.prixBaseHt),
    prix_base_ttc: toNumber(item.prix_base_ttc ?? item.prixBaseTtc),
    temps_estime_minutes: toNumber(item.temps_estime_minutes ?? item.tempsEstimeMinutes, 60),
  }
}

function normalizeRdv(r: any) {
  if (!r) return null
  return {
    ...r,
    status: r.statut ?? r.status,
    statut: r.statut ?? r.status,
    client_nom: r.client ? `${r.client.prenom ?? ''} ${r.client.nom ?? ''}`.trim() : (r.client_nom ?? ''),
    vehicule_info: r.vehicule ? `${r.vehicule.marque ?? ''} ${r.vehicule.modele ?? ''}`.trim() : (r.vehicule_info ?? ''),
    heure_debut: normalizeTimeValue(r.heure_rdv ?? r.heureRdv ?? r.heure_debut),
    pont_id: r.pont?.id ?? toNullableNumber(r.pont_id),
    date_rdv: normalizeDateValue(r.date_rdv ?? r.dateRdv),
    type_intervention: r.type_intervention ?? r.typeIntervention ?? '',
    temps_estime: toNumber(r.temps_estime ?? r.tempsEstime ?? r.duree_estimee, 60),
    mecanicien_id: r.mecanicien?.id ?? toNullableNumber(r.mecanicien_id),
    mecanicien_nom: r.mecanicien ? `${r.mecanicien.prenom ?? ''} ${r.mecanicien.nom ?? ''}`.trim() : (r.mecanicien_nom ?? ''),
    token_suivi: r.token_suivi ?? r.tokenSuivi ?? null,
  }
}

function isHistoricalStatus(status?: string) {
  return HISTORY_STATUSES.includes(String(status || ''))
}

const normalizedRdvs = computed(() => rawRdvs.value.map(normalizeRdv).filter(Boolean))

const activePlanningRdvs = computed(() => {
  const active = normalizedRdvs.value
  if (!activeMecas.value.length) return active
  return active.filter((rdv: any) => !rdv.mecanicien_id || activeMecas.value.includes(rdv.mecanicien_id))
})

const openDaysLabel = computed(() => {
  const openDays = horaires.value.filter((horaire: any) => Number(horaire.is_ouvert) === 1).map((horaire: any) => DAY_LABELS[horaire.jour_semaine])
  return openDays.length ? openDays.join(' · ') : 'jours non configurés'
})

const hourRangeLabel = computed(() => {
  const openHours = horaires.value.filter((horaire: any) => Number(horaire.is_ouvert) === 1)
  if (!openHours.length) return '10:00 → 19:00'
  const starts = openHours.map((horaire: any) => String(horaire.heure_ouverture || '10:00'))
  const ends = openHours.map((horaire: any) => String(horaire.heure_fermeture || '19:00'))
  return `${starts.sort()[0]} → ${ends.sort().slice(-1)[0]}`
})

const isPastWeek = computed(() => {
  const end = currentViewRange.value.end
  return end && end < new Date().toISOString().slice(0, 10)
})

const rdvEditTabItems = computed(() => [
  { label: 'Général', value: '0', icon: 'i-heroicons-user' },
  { label: 'Détails', value: '1', icon: 'i-heroicons-document-text' },
  { label: 'Planning', value: '2', icon: 'i-heroicons-calendar' },
  ...(selectedRdv.value?.id ? [{ label: 'Historique', value: '3', icon: 'i-heroicons-clock' }] : []),
])

const slideoverAccordionItems = computed(() => [
  { label: 'Infos RDV', value: 'infos', icon: 'i-heroicons-information-circle' },
  { label: 'Véhicule', value: 'vehicule', icon: 'i-heroicons-truck' },
  { label: 'Client', value: 'client', icon: 'i-heroicons-user' },
  { label: 'Actions rapides', value: 'actions', icon: 'i-heroicons-bolt' },
])

function prevTab() {
  const idx = Number(editTab.value)
  if (idx > 0) editTab.value = String(idx - 1)
}

function nextTab() {
  const max = (rdvEditTabItems.value.length || 4) - 1
  const idx = Number(editTab.value)
  if (idx < max) editTab.value = String(idx + 1)
}

function onListTransition(payload: { rdv: any; name: string }) {
  selectedRdv.value = normalizeRdv(payload.rdv)
  hydrateEditForms(selectedRdv.value)
  applyTransition(payload.name)
}

const kpis = computed(() => {
  const rdvs = activePlanningRdvs.value
  const today = new Date().toISOString().slice(0, 10)
  const todayRdvs = rdvs.filter((rdv: any) => rdv.date_rdv === today)
  const enCours = todayRdvs.filter((rdv: any) => rdv.status === 'en_cours').length
  const total = todayRdvs.length
  const unassigned = rdvs.filter((rdv: any) => !rdv.mecanicien_id || !rdv.pont_id).length
  const late = todayRdvs.filter((rdv: any) => {
    if (!['confirme', 'reserve', 'reception'].includes(rdv.status)) return false
    const [hour, minute] = String(rdv.heure_debut || '00:00').split(':').map(Number)
    const scheduled = new Date()
    scheduled.setHours(hour || 0, minute || 0, 0, 0)
    return Date.now() - scheduled.getTime() > 10 * 60 * 1000
  }).length

  let conflicts = 0
  for (let i = 0; i < rdvs.length; i++) {
    for (let j = i + 1; j < rdvs.length; j++) {
      const a = rdvs[i]
      const b = rdvs[j]
      if (a.date_rdv !== b.date_rdv) continue
      const startA = timeToMin(a.heure_debut)
      const startB = timeToMin(b.heure_debut)
      const endA = startA + toNumber(a.temps_estime, 60)
      const endB = startB + toNumber(b.temps_estime, 60)
      const overlap = startA < endB && startB < endA
      if (!overlap) continue
      if ((a.pont_id && a.pont_id === b.pont_id) || (a.mecanicien_id && a.mecanicien_id === b.mecanicien_id)) conflicts += 1
    }
  }

  return {
    charge: `${enCours}/${total}`,
    chargeDetail: `${total} RDV actifs du jour`,
    conflicts,
    unassigned,
    late,
  }
})

function transitionButtonStyle(color: string) {
  if (color === 'error') return 'background:rgba(239,68,68,0.14);color:#FCA5A5;border-color:rgba(239,68,68,0.28);'
  if (color === 'success') return 'background:rgba(16,185,129,0.14);color:#6EE7B7;border-color:rgba(16,185,129,0.28);'
  if (color === 'warning') return 'background:rgba(245,158,11,0.14);color:#FBBF24;border-color:rgba(245,158,11,0.28);'
  if (color === 'info') return 'background:rgba(59,130,246,0.14);color:#93C5FD;border-color:rgba(59,130,246,0.28);'
  return 'background:rgba(255,255,255,0.05);color:#E8E9ED;border-color:rgba(255,255,255,0.12);'
}

function transitionLabel(transition: { name: string; label: string }) {
  const status = selectedRdv.value?.status ?? selectedRdv.value?.statut
  if (transition.name === 'annuler' && status === 'en_attente') return '❌ Refuser la demande'
  if (transition.name === 'reserver' && status === 'en_attente' && hasSchedulingEdits.value) return '📌 Déplacer puis réserver'
  if (transition.name === 'reserver' && status === 'en_attente') return '📌 Réserver ce créneau'
  if (transition.name === 'confirmer' && status === 'reserve') return '✅ Confirmer le RDV'
  return transition.label
}

function formatCurrency(value: any) {
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(toNumber(value))
}

function formatDateDisplay(value: string) {
  if (!value) return '—'
  return new Date(`${value}T00:00:00`).toLocaleDateString('fr-FR', { weekday: 'short', day: '2-digit', month: '2-digit', year: 'numeric' })
}

function timeToMin(value: string) {
  const [hours, minutes] = String(value || '00:00').split(':').map(Number)
  return (hours || 0) * 60 + (minutes || 0)
}

function toggleMeca(id: number) {
  const idx = activeMecas.value.indexOf(id)
  if (idx >= 0) activeMecas.value.splice(idx, 1)
  else activeMecas.value.push(id)
}

function fallbackTransitionsForStatus(status?: string) {
  const byStatus: Record<string, string[]> = {
    en_attente: ['reserver', 'confirmer', 'annuler'],
    reserve: ['confirmer', 'annuler'],
    confirme: ['reception', 'annuler'],
    reception: ['start_travail'],
    en_cours: ['terminer'],
    termine: ['restituer', 'facturer'],
    restitue: ['facturer'],
    facture: ['payer'],
  }

  return (byStatus[String(status || '')] || []).map((name) => ({
    name,
    label: transitionCatalog[name]?.label ?? name,
    color: transitionCatalog[name]?.color ?? 'neutral',
  }))
}

function hydrateEditForms(rdv: any) {
  if (!rdv) return
  editForm.date_rdv = rdv.date_rdv || new Date().toISOString().slice(0, 10)
  editForm.heure_debut = rdv.heure_debut || '09:00'
  editForm.type_intervention = rdv.type_intervention || ''
  editForm.temps_estime = toNumber(rdv.temps_estime, 60)
  editForm.commentaire = rdv.commentaire || ''
  editForm.mecanicien_id = toNullableNumber(rdv.mecanicien?.id ?? rdv.mecanicien_id)
  editForm.pont_id = toNullableNumber(rdv.pont?.id ?? rdv.pont_id)
  editForm.priorite = rdv.priorite || 'normale'
  editForm.tags = Array.isArray(rdv.tags) ? rdv.tags : []
  receptionForm.kilometrage = rdv.kilometrage ? String(rdv.kilometrage) : ''
  const receptionState = parseReceptionState(rdv.etat_vehicule_reception ?? rdv.etat_vehicule ?? rdv.etatVehicule)
  receptionForm.etat_vehicule = receptionState.observations || ''
  receptionForm.notes_reception = receptionState.reception_notes || ''
}

function parseReceptionState(raw: any) {
  if (!raw) return {}
  if (typeof raw === 'string') {
    try {
      const parsed = JSON.parse(raw)
      return parsed && typeof parsed === 'object' ? parsed : { observations: raw }
    } catch {
      return { observations: raw }
    }
  }
  return typeof raw === 'object' ? raw : {}
}

function resetQuickForm(prefill?: { date?: string; time?: string; pontId?: number | null }) {
  quickForm.date_rdv = prefill?.date || new Date().toISOString().slice(0, 10)
  quickForm.heure_debut = prefill?.time || '10:00'
  quickForm.client_id = null
  quickForm.client_prenom = ''
  quickForm.client_nom = ''
  quickForm.client_telephone = ''
  quickForm.client_email = ''
  quickForm.vehicule_marque = ''
  quickForm.vehicule_modele = ''
  quickForm.vehicule_plaque = ''
  quickForm.vehicule_cylindree = ''
  quickForm.vehicule_type = ''
  quickForm.type_intervention = ''
  quickForm.commentaire = ''
  quickForm.mecanicien_id = null
  quickForm.pont_id = prefill?.pontId ?? null
  quickSelectedPrestas.value = []
  quickClientSearch.value = ''
  quickClientResults.value = []
  quickSelectedClient.value = null
  quickVehicleSearch.value = ''
  quickVehicleFound.value = false
}

function openQuickCreate(prefill?: { date?: string; time?: string; pontId?: number | null }) {
  resetQuickForm(prefill)
  syncMecanicienFromPont(quickForm)
  showQuickCreateModal.value = true
}

function consumeWorkshopQuickCreateQuery() {
  if (String(route.query.create ?? '') !== '1') return

  openQuickCreate({
    date: normalizeDateValue(route.query.date) || new Date().toISOString().slice(0, 10),
    time: normalizeTimeValue(route.query.time) || '10:00',
    pontId: toNullableNumber(route.query.pontId),
  })

  const nextQuery = { ...route.query }
  delete nextQuery.create
  delete nextQuery.date
  delete nextQuery.time
  delete nextQuery.pontId
  router.replace({ query: nextQuery }).catch(() => {})
}

async function openRdvFromQuery() {
  const requestedId = Number(route.query.openRdv || 0)
  if (!Number.isFinite(requestedId) || requestedId <= 0) return

  const existing = normalizedRdvs.value.find((rdv: any) => Number(rdv.id) === requestedId)
  try {
    await withTimeout(onSelectRdv(existing || { id: requestedId }), 'planning_open_rdv', 8000)
  } catch {
    // Keep planning usable even if RDV modal hydration times out.
  }

  const nextQuery = { ...route.query }
  delete nextQuery.openRdv
  router.replace({ query: nextQuery }).catch(() => {})
}

const debouncedSearchQuickClients = useDebounceFn(async () => {
  try {
    const data = await api.get(`/clients?search=${encodeURIComponent(quickClientSearch.value.trim())}`)
    quickClientResults.value = unwrapList(data)
  } catch {
    quickClientResults.value = []
  }
}, 250)

function searchQuickClients() {
  if (quickClientSearch.value.trim().length < 2) {
    quickClientResults.value = []
    debouncedSearchQuickClients.cancel()
    return
  }
  debouncedSearchQuickClients()
}

function selectQuickClient(client: any) {
  quickSelectedClient.value = client
  quickClientSearch.value = `${client?.prenom ?? ''} ${client?.nom ?? ''}`.trim()
  quickClientResults.value = []

  quickForm.client_id = toNullableNumber(client?.id)
  quickForm.client_prenom = client?.prenom ?? ''
  quickForm.client_nom = client?.nom ?? ''
  quickForm.client_telephone = client?.telephone ?? ''
  quickForm.client_email = client?.email ?? ''

  if (Array.isArray(client?.vehicules) && client.vehicules.length) {
    const vehicle = client.vehicules[0]
    quickForm.vehicule_marque = vehicle?.marque ?? ''
    quickForm.vehicule_modele = vehicle?.modele ?? ''
    quickForm.vehicule_plaque = formatRegistrationOrVin(vehicle?.plaque ?? '')
    quickForm.vehicule_cylindree = vehicle?.cylindree ?? ''
    quickForm.vehicule_type = vehicle?.type_moto ?? vehicle?.typeMoto ?? vehicle?.univers ?? ''
    quickVehicleSearch.value = quickForm.vehicule_plaque
    quickVehicleFound.value = !!(quickForm.vehicule_marque || quickForm.vehicule_modele || quickForm.vehicule_plaque)
  }
}

function clearQuickClient() {
  quickSelectedClient.value = null
  quickClientSearch.value = ''
  quickClientResults.value = []
  quickForm.client_id = null
  quickForm.client_prenom = ''
  quickForm.client_nom = ''
  quickForm.client_telephone = ''
  quickForm.client_email = ''
}

async function searchQuickVehicle() {
  const query = formatRegistrationOrVin(quickVehicleSearch.value || quickForm.vehicule_plaque || '')
  quickVehicleSearch.value = query
  quickForm.vehicule_plaque = query

  if (!query) {
    quickVehicleFound.value = false
    return
  }

  try {
    let data: any = null
    try {
      data = await api.get(`/vehicule/${encodeURIComponent(query)}`)
    } catch {
      const collection = await api.get(`/vehicules?plaque=${encodeURIComponent(query)}`).catch(() => null)
      data = unwrapList(collection)[0] ?? null
    }

    if (data && (data.marque || data.modele || data.plaque)) {
      quickForm.vehicule_marque = data.marque || ''
      quickForm.vehicule_modele = data.modele || ''
      quickForm.vehicule_plaque = formatRegistrationOrVin(data.plaque || query)
      quickForm.vehicule_cylindree = data.cylindree || ''
      quickForm.vehicule_type = data.type_moto || data.typeMoto || data.univers || ''
      quickVehicleFound.value = true
    } else {
      quickVehicleFound.value = false
    }
  } catch {
    quickVehicleFound.value = false
  }
}

async function refreshPlanning() {
  refreshing.value = true
  try {
    await loadPlanningData()
    if (selectedRdv.value?.id) {
      await reloadSelectedRdv(selectedRdv.value.id)
    }
  } finally {
    refreshing.value = false
  }
}

function getInitialWeekRange() {
  const d = new Date()
  const day = d.getDay() || 7
  d.setDate(d.getDate() - day + 1)
  const start = d.toISOString().slice(0, 10)
  d.setDate(d.getDate() + 6)
  const end = d.toISOString().slice(0, 10)
  return { start, end }
}

const currentViewRange = ref(getInitialWeekRange())

async function onWeekChanged(range: { start: string; end: string }) {
  currentViewRange.value = range
  if (!ponts.value.length && !mecaniciens.value.length) {
    // Initial load will handle RDVs inside loadPlanningData
  } else {
    // Week changed — mise à jour silencieuse des RDVs : NE PAS toucher `loading`
    // (loading=true démonterait PlanningGrid, dont le watch immediate re-émettrait dates-changed → boucle infinie)
    refreshing.value = true
    try {
      const r = await safeGet(
        `/rendez-vous?itemsPerPage=2000&order[createdAt]=desc&dateRdv[after]=${range.start}&dateRdv[before]=${range.end}`,
        [],
        'planning_week_rdvs',
      )
      rawRdvs.value = unwrapList(r)
    } finally {
      refreshing.value = false
    }
  }
}

async function loadPlanningData() {
  const [p, r, m, h, prestaData, configData] = await Promise.all([
    safeGet('/ponts', [], 'planning_ponts'),
    safeGet(
      `/rendez-vous?itemsPerPage=2000&order[createdAt]=desc&dateRdv[after]=${currentViewRange.value.start || '2000-01-01'}&dateRdv[before]=${currentViewRange.value.end || '2099-12-31'}`,
      [],
      'planning_rdvs',
    ),
    safeGet('/mecaniciens', [], 'planning_mecaniciens'),
    safeGet('/config/horaires', [], 'planning_horaires'),
    safeGet('/prestations?itemsPerPage=200', [], 'planning_prestations'),
    safeGet('/config', null, 'planning_config'),
  ])

  ponts.value = unwrapList(p)
  rawRdvs.value = unwrapList(r)
  mecaniciens.value = unwrapList(m)
  horaires.value = normalizeHoraires(unwrapList(h).length ? h : (configData as any)?.horaires ?? [])
  prestations.value = unwrapList(prestaData).map(normalizePrestation).filter((item: any) => item.is_active !== false && item.is_active !== 0)
}

async function loadAvailableTransitions(id: number) {
  try {
    const data = await api.get(`/rendez-vous/${id}/transitions`)
    const transitions = Array.isArray(data?.transitions) ? data.transitions : []
    availableTransitions.value = transitions
      .filter((name: string) => !HIDDEN_RECEPTION_TRANSITIONS.includes(name))
      .map((name: string) => ({
        name,
        label: transitionCatalog[name]?.label ?? name,
        color: transitionCatalog[name]?.color ?? 'neutral',
      }))
  } catch {
    availableTransitions.value = fallbackTransitionsForStatus(selectedRdv.value?.status)
      .filter((t: any) => !HIDDEN_RECEPTION_TRANSITIONS.includes(t.name))
  }
}

async function reloadSelectedRdv(id: number) {
  await rdvStore.fetchRdv(id)
  const fresh = normalizeRdv(rdvStore.currentRdv)
  if (fresh) {
    selectedRdv.value = fresh
    hydrateEditForms(fresh)
    await loadAvailableTransitions(id)
    selectedRdvHistory.value = []
    selectedRdvHistoryLoading.value = false
    if (editTab.value === '3') {
      await loadSelectedRdvHistory(id)
    }
  }
}

async function loadSelectedRdvHistory(id: number) {
  selectedRdvHistoryLoading.value = true
  try {
    const data = await api.get(`/rendez-vous/${id}/history`)
    selectedRdvHistory.value = Array.isArray(data?.items) ? data.items : []
  } catch {
    selectedRdvHistory.value = []
  } finally {
    selectedRdvHistoryLoading.value = false
  }
}

async function onSelectRdv(rdv: any) {
  editTab.value = '0'
  showRdvModal.value = true
  modalLoading.value = true
  selectedRdv.value = normalizeRdv(rdv)
  hydrateEditForms(selectedRdv.value)
  selectedRdvHistory.value = []
  selectedRdvHistoryLoading.value = false
  try {
    await reloadSelectedRdv(Number(rdv.id))
  } finally {
    modalLoading.value = false
  }
}

async function onMoveRdv(payload: { id: number; date: string; time: string }) {
  if (!canEditRdv.value) return
  try {
    await rdvStore.updateRdv(payload.id, {
      date_rdv: payload.date,
      dateRdv: payload.date,
      heure_rdv: payload.time,
      heure_debut: payload.time,
      heureRdv: `${payload.time}:00`,
    })
    toast.add({ title: 'RDV déplacé', color: 'success' })
    await refreshPlanning()
  } catch (e: unknown) {
    toast.add({ title: 'Déplacement impossible', description: (e instanceof Error ? e.message : 'Erreur inconnue') || 'Erreur inconnue', color: 'error' })
  }
}

function onCreateAt(payload: { date: string; time: string }) {
  if (!canCreateRdv.value) return
  openQuickCreate(payload)
}

async function submitQuickCreate() {
  if (!quickSelectedClient.value && !quickForm.client_nom.trim()) {
    toast.add({ title: 'Nom client requis', color: 'warning' })
    return
  }

  if (!toNullableNumber(quickForm.pont_id)) {
    toast.add({ title: 'Pont requis', description: 'Choisissez un pont pour affecter automatiquement le mécanicien.', color: 'warning' })
    return
  }

  quickSubmitting.value = true
  try {
    const typeIntervention = quickSelectedPrestations.value.map((item: any) => item.nom).join(', ') || quickForm.type_intervention || 'entretien'
    const resolvedPontId = toNullableNumber(quickForm.pont_id)
    const resolvedMecanicienId = resolvePontMecanicienId(resolvedPontId)

    const payload = {
      client_id: toNullableNumber(quickForm.client_id),
      date_rdv: quickForm.date_rdv,
      heure_debut: quickForm.heure_debut,
      client_prenom: quickForm.client_prenom.trim(),
      client_nom: quickForm.client_nom.trim(),
      client_telephone: quickForm.client_telephone.trim(),
      client_email: quickForm.client_email.trim(),
      vehicule_marque: quickForm.vehicule_marque.trim(),
      vehicule_modele: quickForm.vehicule_modele.trim(),
      vehicule_plaque: formatRegistrationOrVin(quickForm.vehicule_plaque),
      vehicule_cylindree: String(quickForm.vehicule_cylindree || '').trim(),
      vehicule_type: String(quickForm.vehicule_type || '').trim(),
      type_intervention: typeIntervention,
      description_probleme: quickForm.commentaire,
      commentaire: quickForm.commentaire,
      duree_estimee: quickEstimateDuration.value,
      temps_estime: quickEstimateDuration.value,
      prix_estime: quickEstimateTotal.value || null,
      mecanicien_id: resolvedMecanicienId,
      pont_id: resolvedPontId,
    }

    const created = await rdvStore.createRdv(payload)
    showQuickCreateModal.value = false
    toast.add({ title: 'Créneau réservé', color: 'success' })
    await refreshPlanning()
    if (created?.id) await onSelectRdv(created)
  } catch (e: unknown) {
    toast.add({ title: 'Création impossible', description: (e instanceof Error ? e.message : 'Erreur inconnue') || 'Erreur inconnue', color: 'error' })
  } finally {
    quickSubmitting.value = false
  }
}

async function saveRdvChanges() {
  if (!selectedRdv.value?.id || !canEditRdv.value || selectedIsHistorical.value) return

  editSaving.value = true
  try {
    const resolvedPontId = toNullableNumber(editForm.pont_id)
    const resolvedMecanicienId = resolvePontMecanicienId(resolvedPontId) ?? toNullableNumber(editForm.mecanicien_id)

    const payload: any = {
      date_rdv: editForm.date_rdv,
      dateRdv: editForm.date_rdv,
      heure_rdv: editForm.heure_debut,
      heure_debut: editForm.heure_debut,
      heureRdv: `${editForm.heure_debut}:00`,
      commentaire: editForm.commentaire,
      pont_id: resolvedPontId,
      mecanicien_id: resolvedMecanicienId,
      pont: resolvedPontId ? `/api/ponts/${resolvedPontId}` : null,
      mecanicien: resolvedMecanicienId ? `/api/mecaniciens/${resolvedMecanicienId}` : null,
    }

    if (!prestationLocked.value) {
      payload.type_intervention = editForm.type_intervention
      payload.typeIntervention = editForm.type_intervention
      payload.temps_estime = toNumber(editForm.temps_estime, 60)
      payload.tempsEstime = toNumber(editForm.temps_estime, 60)
    }

    await rdvStore.updateRdv(selectedRdv.value.id, payload)
    toast.add({ title: 'RDV mis à jour', color: 'success' })
    await refreshPlanning()
  } catch (e: unknown) {
    toast.add({ title: 'Modification impossible', description: (e instanceof Error ? e.message : 'Erreur inconnue') || 'Erreur inconnue', color: 'error' })
  } finally {
    editSaving.value = false
  }
}

async function applyTransition(name: string, options: any = {}) {
  if (!selectedRdv.value?.id || selectedIsHistorical.value) return
  const status = selectedRdv.value?.status ?? selectedRdv.value?.statut
  if (name === "annuler" && !options.motif) {
    confirmAnnulation(name)
    return
  }

  transitioning.value = name
  try {
    const resolvedPontId = toNullableNumber(editForm.pont_id)
    const resolvedMecanicienId = resolvePontMecanicienId(resolvedPontId) ?? toNullableNumber(editForm.mecanicien_id)

    if (name === 'reserver' && hasSchedulingEdits.value) {
      await rdvStore.updateRdv(selectedRdv.value.id, {
        date_rdv: editForm.date_rdv,
        dateRdv: editForm.date_rdv,
        heure_rdv: editForm.heure_debut,
        heure_debut: editForm.heure_debut,
        heureRdv: `${editForm.heure_debut}:00`,
        pont_id: resolvedPontId,
        mecanicien_id: resolvedMecanicienId,
        pont: resolvedPontId ? `/api/ponts/${resolvedPontId}` : null,
        mecanicien: resolvedMecanicienId ? `/api/mecaniciens/${resolvedMecanicienId}` : null,
      })
    }

    const payload: any = {
      pont_id: resolvedPontId,
      mecanicien_id: resolvedMecanicienId,
      ...options
    }

    if (name === 'reception') {
      if (receptionForm.kilometrage) payload.kilometrage = toNumber(receptionForm.kilometrage)
      const receptionState = parseReceptionState(selectedRdv.value?.etat_vehicule_reception ?? selectedRdv.value?.etat_vehicule ?? selectedRdv.value?.etatVehicule)
      if (receptionForm.etat_vehicule.trim()) receptionState.observations = receptionForm.etat_vehicule.trim()
      if (receptionForm.notes_reception.trim()) receptionState.reception_notes = receptionForm.notes_reception.trim()
      if (Object.keys(receptionState).length) payload.etat_vehicule = receptionState
    }

    await api.post(`/rendez-vous/${selectedRdv.value.id}/transition/${name}`, payload)
    const title = name === 'annuler' && status === 'en_attente'
      ? 'Demande refusée'
      : name === 'reserver' && hasSchedulingEdits.value
        ? 'Demande déplacée et créneau réservé'
        : 'Transition effectuée'
    toast.add({ title, color: 'success' })
    await refreshPlanning()
  } catch (e: unknown) {
    const err = e as any
    const data = err?.data
    // Erreur photos manquantes — message contextuel
    if (data?.missing_photos) {
      const mp = data.missing_photos
      const typeLabels: Record<string, string> = {
        reception: 'réception (via Companion)',
        apres_travaux: 'après travaux',
        restitution: 'restitution',
      }
      const typeLabel = typeLabels[mp.type] ?? mp.type
      toast.add({
        title: `Photos ${typeLabel} manquantes`,
        description: `${mp.missing} photo(s) requise(s) — demandez au client de les prendre via le Companion avant de passer à cette étape.`,
        color: 'error',
        duration: 8000,
      })
    } else {
      toast.add({ title: 'Transition impossible', description: (e instanceof Error ? e.message : 'Erreur inconnue') || 'Erreur inconnue', color: 'error' })
    }
  } finally {
    transitioning.value = ''
  }
}

const currentMecanicienRdvs = computed(() => {
  const mecaId = editForm.mecanicien_id ?? selectedRdv.value?.mecanicien_id
  if (!mecaId) return []
  const start = currentViewRange.value.start
  const end = currentViewRange.value.end
  return normalizedRdvs.value.filter((r: any) =>
    r.mecanicien_id === mecaId && r.date_rdv >= start && r.date_rdv <= end
  ).sort((a: any, b: any) => String(a.date_rdv + a.heure_debut).localeCompare(String(b.date_rdv + b.heure_debut)))
})

async function deleteSelectedRdv() {
  if (!selectedRdv.value?.id || !canDeleteSelected.value) return
  const confirmed = globalThis.confirm?.('Supprimer définitivement ce rendez-vous ?')
  if (confirmed === false) return

  deleting.value = true
  try {
    await api.del(`/rendez-vous/${selectedRdv.value.id}`)
    showRdvModal.value = false
    selectedRdv.value = null
    toast.add({ title: 'RDV supprimé', color: 'success' })
    await refreshPlanning()
  } catch (e: unknown) {
    toast.add({ title: 'Suppression impossible', description: (e instanceof Error ? e.message : 'Erreur inconnue') || 'Erreur inconnue', color: 'error' })
  } finally {
    deleting.value = false
  }
}

watch(() => quickForm.pont_id, () => {
  syncMecanicienFromPont(quickForm)
})

watch(() => editForm.pont_id, () => {
  syncMecanicienFromPont(editForm)
})

watch(() => `${quickForm.vehicule_type}|${quickForm.vehicule_cylindree}`, () => {
  quickSelectedPrestas.value = quickSelectedPrestas.value.filter((id: number) => filteredQuickPrestations.value.some((presta: any) => Number(presta.id) === Number(id)))
})

// --- Companion / Reception ---
const companionStatus = reactive({
  photos_count: 0,
  checkup_done: 0,
  has_signature: false,
})

const isReceptionEligible = computed(() => {
  const s = selectedRdv.value?.status ?? selectedRdv.value?.statut
  return ['confirme', 'reception'].includes(s)
})

const companionUrl = computed(() => {
  const token = selectedRdv.value?.token_suivi ?? selectedRdv.value?.tokenSuivi
  if (!token) return ''
  const origin = globalThis.location?.origin || ''
  return `${origin}/companion/reception/${token}`
})

const companionQrUrl = ref('')
watch(companionUrl, async (url: string) => {
  if (!url) { companionQrUrl.value = ''; return }
  const { generateQrDataUrl } = await import('~/composables/useQrCode')
  companionQrUrl.value = await generateQrDataUrl(url, 200)
}, { immediate: true })

function copyCompanionUrl() {
  if (companionUrl.value) {
    navigator.clipboard?.writeText(companionUrl.value)
    toast.add({ title: 'Lien PDA copié', color: 'success' })
  }
}

async function refreshCompanionStatus() {
  const token = selectedRdv.value?.token_suivi ?? selectedRdv.value?.tokenSuivi
  if (!token) return
  try {
    const data = await api.get(`/companion/${token}/status`)
    companionStatus.photos_count = data.photos_count || 0
    companionStatus.checkup_done = data.checkup_done || 0
    companionStatus.has_signature = !!data.has_signature
  } catch {}
}

let companionPollInterval: ReturnType<typeof setInterval>

watch(showRdvModal, (open: boolean) => {
  clearInterval(companionPollInterval)
  if (open && isReceptionEligible.value) {
    // Petit délai pour laisser selectedRdv être hydraté par reloadSelectedRdv
    setTimeout(() => {
      if (showRdvModal.value && isReceptionEligible.value) {
        refreshCompanionStatus()
        companionPollInterval = setInterval(refreshCompanionStatus, 4000)
      }
    }, 300)
  }
})

watch([editTab, () => selectedRdv.value?.id], async ([tab, id]) => {
  if (tab !== '3' || !id || selectedRdvHistoryLoading.value) {
    return
  }

  await loadSelectedRdvHistory(Number(id))
})

watch(isReceptionEligible, (eligible: boolean) => {
  clearInterval(companionPollInterval)
  if (eligible && showRdvModal.value) {
    refreshCompanionStatus()
    companionPollInterval = setInterval(refreshCompanionStatus, 4000)
  }
})

onMounted(async () => {
  try {
    await withTimeout(loadPlanningData(), 'planning_init', 15000)
    consumeWorkshopQuickCreateQuery()
    await openRdvFromQuery()
  } catch {
    toast.add({
      title: 'Chargement partiel du planning',
      description: 'Certaines données ont mis trop de temps à répondre. Le planning reste accessible.',
      color: 'warning',
    })
  } finally {
    loading.value = false
  }
})

watch(() => route.query.openRdv, async () => {
  if (!loading.value) {
    await openRdvFromQuery()
  }
})

onUnmounted(() => {
  clearInterval(companionPollInterval)
})
</script>

<style scoped>
.reception-status-pill {
  flex: 1;
  padding: 8px 12px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 600;
  text-align: center;
  background: rgba(255,255,255,0.03);
  border: 1px solid rgba(255,255,255,0.08);
  color: #6B7280;
}
.reception-status-pill.done {
  background: rgba(16,185,129,0.08);
  border-color: rgba(16,185,129,0.25);
  color: #6EE7B7;
}

.planning-view-toggle {
  display: inline-flex;
  align-items: center;
  background: rgba(255,255,255,0.03);
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: var(--radius-sm);
  overflow: hidden;
}
.view-toggle-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 6px 10px;
  background: transparent;
  border: none;
  color: #6B7280;
  cursor: pointer;
  transition: all 0.15s;
}
.view-toggle-btn.active {
  background: rgba(255,210,0,0.1);
  color: #FFD200;
}
.planning-legend-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;
  flex-wrap: wrap;
  padding: 10px 16px;
  background: rgba(255,255,255,0.02);
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: var(--radius);
  font-size: 12px;
}
.planning-legend-items {
  display: flex;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
}
.planning-legend-label {
  color: #6B7280;
  font-weight: 600;
}
.legend-dot {
  width: 10px;
  height: 10px;
  border-radius: 3px;
  border: 1px solid;
  display: inline-block;
}
.planning-meca-filter {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 16px;
  flex-wrap: wrap;
}
.planning-meca-label {
  font-size: 12px;
  font-weight: 600;
  color: #6B7280;
}
.meca-chip {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 5px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
  background: rgba(255,255,255,0.03);
  border: 1px solid rgba(255,255,255,0.06);
  color: #6B7280;
}
.meca-chip.active {
  background: rgba(139,92,246,0.12);
  border-color: rgba(139,92,246,0.3);
  color: #C4B5FD;
}
.meca-chip-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
}
.meca-chip-reset {
  padding: 5px 10px;
  font-size: 11px;
}
.planning-past-banner {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 16px;
  padding: 10px 14px;
  border-radius: var(--radius);
  background: rgba(59,130,246,0.06);
  border: 1px solid rgba(59,130,246,0.15);
  font-size: 13px;
  color: #93C5FD;
}

/* Modal tabs */
.rdv-modal {
  display: flex;
  flex-direction: column;
  max-height: 85vh;
}
.rdv-modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 16px 20px;
  border-bottom: 1px solid rgba(255,255,255,0.06);
}
.rdv-modal-header-title {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
  font-weight: 700;
  color: #E8E9ED;
  font-size: 15px;
}
.rdv-historical-badge {
  font-size: 11px;
  color: #10B981;
  background: rgba(16,185,129,0.08);
  border: 1px solid rgba(16,185,129,0.2);
  padding: 4px 10px;
  border-radius: 999px;
}
.rdv-tabs {
  flex: 1;
  overflow-y: auto;
  padding: 0 20px;
}
.rdv-tab-panel {
  padding: 16px 0;
  min-height: 200px;
}
.rdv-modal-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 12px 20px;
  border-top: 1px solid rgba(255,255,255,0.06);
  background: var(--dark2);
}
.rdv-modal-footer-nav {
  display: flex;
  gap: 8px;
}

/* Slideover */
.rdv-slideover {
  display: flex;
  flex-direction: column;
  height: 100%;
}
.rdv-slideover-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  padding: 16px 20px;
  border-bottom: 1px solid rgba(255,255,255,0.06);
}
.rdv-slideover-client {
  font-size: 16px;
  font-weight: 700;
  color: #E8E9ED;
  line-height: 1.2;
}
.rdv-slideover-vehicle {
  font-size: 13px;
  color: #9CA3AF;
  margin-top: 2px;
}
.rdv-slideover-meta {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 8px;
}
.rdv-slideover-web {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.05em;
  color: #93C5FD;
  background: rgba(59,130,246,0.12);
  border: 1px solid rgba(59,130,246,0.25);
  padding: 2px 8px;
  border-radius: 4px;
}
.rdv-slideover-body {
  flex: 1;
  overflow-y: auto;
  padding: 0 20px;
}
.rdv-slideover-footer {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  padding: 12px 20px;
  border-top: 1px solid rgba(255,255,255,0.06);
  background: var(--dark2);
}
.accordion-panel {
  padding: 12px 0;
  font-size: 13px;
}
.info-grid-sm {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px 16px;
}
@media (max-width: 640px) {
  .info-grid-sm {
    grid-template-columns: 1fr;
  }
}
.planning-mini-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.planning-mini-item {
  display: grid;
  grid-template-columns: 120px 60px 1fr 1fr;
  gap: 8px;
  align-items: center;
  padding: 8px 10px;
  border-radius: var(--radius-sm);
  background: rgba(255,255,255,0.02);
  border: 1px solid rgba(255,255,255,0.04);
  font-size: 12px;
}
.planning-mini-date { color: #CBD5E1; }
.planning-mini-time { color: #FFD200; font-weight: 700; }
.planning-mini-type { color: #E8E9ED; }
.planning-mini-client { color: #9CA3AF; text-align: right; }
.planning-history-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.planning-history-item {
  display: flex;
  gap: 12px;
  padding: 10px 12px;
  border-radius: var(--radius-sm);
  background: rgba(255,255,255,0.02);
  border: 1px solid rgba(255,255,255,0.04);
  font-size: 12px;
}
.planning-history-date { color: #6B7280; min-width: 90px; }
.planning-history-action { color: #E8E9ED; flex: 1; }
.planning-history-user { color: #9CA3AF; }
</style>
