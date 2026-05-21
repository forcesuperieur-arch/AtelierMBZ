<template>
  <div>
    <div class="page-header">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
        <div class="page-title">Planning</div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <button class="btn btn-ghost" :disabled="loading || refreshing" @click="refreshPlanning">
            {{ refreshing ? 'Actualisation…' : '↻ Actualiser' }}
          </button>
          <button v-if="canCreateRdv" class="btn btn-primary" @click="openQuickCreate()">+ RDV rapide</button>
        </div>
      </div>
    </div>

    <div v-if="loading" class="loading-shimmer" style="height:400px;border-radius:14px;"></div>

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

      <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:16px;flex-wrap:wrap;padding:10px 16px;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06);border-radius:10px;font-size:12px;">
        <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
          <span style="color:#6B7280;font-weight:600;">Légende :</span>
          <span style="display:flex;align-items:center;gap:4px;"><span style="width:10px;height:10px;border-radius:3px;background:rgba(245,158,11,0.15);border:1px solid rgba(245,158,11,0.35);"></span><span style="color:#9CA3AF;">Réservé</span></span>
          <span style="display:flex;align-items:center;gap:4px;"><span style="width:10px;height:10px;border-radius:3px;background:rgba(59,130,246,0.15);border:1px solid rgba(59,130,246,0.35);"></span><span style="color:#9CA3AF;">Confirmé</span></span>
          <span style="display:flex;align-items:center;gap:4px;"><span style="width:10px;height:10px;border-radius:3px;background:rgba(20,184,166,0.15);border:1px solid rgba(20,184,166,0.4);"></span><span style="color:#9CA3AF;">En cours</span></span>
          <span style="display:flex;align-items:center;gap:4px;"><span style="width:10px;height:10px;border-radius:3px;background:rgba(16,185,129,0.15);border:1px solid rgba(16,185,129,0.4);"></span><span style="color:#9CA3AF;">Terminé / historisé</span></span>
        </div>
        <div style="color:#CBD5E1;">
          Horaires atelier : <strong style="color:#F8FAFC;">{{ hourRangeLabel }}</strong> · {{ openDaysLabel }}
        </div>
      </div>

      <div v-if="mecaniciens.length" style="display:flex;align-items:center;gap:8px;margin-bottom:16px;flex-wrap:wrap;">
        <span style="font-size:12px;font-weight:600;color:#6B7280;">Mécaniciens :</span>
        <button
          v-for="m in mecaniciens"
          :key="m.id"
          style="display:flex;align-items:center;gap:6px;padding:5px 12px;border-radius:20px;font-size:12px;font-weight:600;cursor:pointer;transition:all 0.15s;"
          :style="{
            background: activeMecas.includes(m.id) ? 'rgba(139,92,246,0.12)' : 'rgba(255,255,255,0.03)',
            border: activeMecas.includes(m.id) ? '1px solid rgba(139,92,246,0.3)' : '1px solid rgba(255,255,255,0.06)',
            color: activeMecas.includes(m.id) ? '#C4B5FD' : '#6B7280',
          }"
          @click="toggleMeca(m.id)"
        >
          <span style="width:8px;height:8px;border-radius:50%;" :style="{ background: m.couleur || '#8B5CF6' }"></span>
          {{ m.prenom }} {{ m.nom?.charAt(0) }}.
        </button>
        <button
          v-if="activeMecas.length"
          style="padding:5px 10px;border-radius:20px;font-size:11px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);color:#6B7280;cursor:pointer;"
          @click="activeMecas = []"
        >
          ✕ Tous
        </button>
      </div>

      <PlanningGrid
        :ponts="ponts"
        :rdvs="activePlanningRdvs"
        :horaires="horaires"
        :can-create="canCreateRdv"
        :can-drag="canEditRdv"
        @select-rdv="onSelectRdv"
        @move-rdv="onMoveRdv"
        @create-at="onCreateAt"
      />

      <UCard v-if="historicalRdvs.length" style="margin-top:20px;">
        <template #header>
          <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;">
            <span style="font-size:15px;font-weight:700;color:#E8E9ED;">Historique figé</span>
            <span style="font-size:12px;color:#9CA3AF;">Les RDV terminés, facturés ou annulés sont verrouillés ici.</span>
          </div>
        </template>

        <div style="display:flex;flex-direction:column;gap:8px;">
          <button
            v-for="rdv in historicalRdvs"
            :key="`history-${rdv.id}`"
            style="display:flex;justify-content:space-between;gap:12px;align-items:center;padding:10px 12px;border-radius:10px;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06);text-align:left;cursor:pointer;"
            @click="onSelectRdv(rdv)"
          >
            <div>
              <div style="font-size:13px;font-weight:700;color:#E8E9ED;">{{ rdv.client_nom || 'Client' }} · {{ rdv.type_intervention }}</div>
              <div style="font-size:12px;color:#9CA3AF;">{{ formatDateDisplay(rdv.date_rdv) }} à {{ rdv.heure_debut }} · {{ rdv.vehicule_info || 'Véhicule non précisé' }}</div>
            </div>
            <StatusBadge :status="rdv.status" />
          </button>
        </div>
      </UCard>
    </template>

    <AppModal v-model:open="showQuickCreateModal" size="xl">
      <template #content>
        <UCard>
          <template #header>
            <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
              <span style="font-weight:700;color:#E8E9ED;">Créer un RDV rapide</span>
              <button style="background:none;border:none;color:#9CA3AF;font-size:18px;cursor:pointer;" @click="showQuickCreateModal = false">✕</button>
            </div>
          </template>

          <div style="display:flex;flex-direction:column;gap:16px;">
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
                <div class="form-input" style="display:flex;align-items:center;min-height:42px;color:#CBD5E1;">{{ quickEstimatedEnd }}</div>
              </div>
            </div>

            <div style="padding:12px;border-radius:10px;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06);">
              <div class="form-label" style="margin-bottom:6px;">Recherche client</div>
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

            <div style="padding:12px;border-radius:10px;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06);">
              <div class="form-label" style="margin-bottom:6px;">Recherche véhicule par plaque / VIN</div>
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
              <div v-if="quickVehicleFound" style="margin-top:8px;font-size:12px;color:#86EFAC;">Véhicule trouvé et prérempli.</div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
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
                <div v-if="quickMarqueSuggestions.length" style="margin-top:6px;display:grid;gap:4px;">
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
                <div v-if="quickModeleSuggestions.length" style="margin-top:6px;display:grid;gap:4px;">
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
                <div class="form-input" style="display:flex;align-items:center;min-height:42px;color:#CBD5E1;">{{ quickAssignedMecanicienLabel }}</div>
              </div>
            </div>

            <div>
              <div class="form-label" style="margin-bottom:8px;">Prestations atelier <span style="font-size:11px;color:#9CA3AF;">· filtrées selon le type de moto</span></div>
              <div v-if="filteredQuickPrestations.length" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:8px;max-height:220px;overflow:auto;">
                <label
                  v-for="presta in filteredQuickPrestations"
                  :key="presta.id"
                  style="display:flex;gap:8px;align-items:flex-start;padding:10px 12px;border-radius:10px;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06);cursor:pointer;"
                >
                  <input v-model="quickSelectedPrestas" :value="presta.id" type="checkbox" style="margin-top:2px;accent-color:#FFD200;" />
                  <div>
                    <div style="font-size:13px;font-weight:700;color:#E8E9ED;">{{ presta.nom }}</div>
                    <div style="font-size:11px;color:#9CA3AF;">{{ formatCurrency(presta.prix_base_ttc ?? presta.prix_base_ht) }} · {{ formatMinutes(presta.temps_estime_minutes || 60) }}</div>
                  </div>
                </label>
              </div>
              <div v-else style="padding:12px;border-radius:10px;background:rgba(255,255,255,0.02);border:1px dashed rgba(255,255,255,0.08);font-size:12px;color:#9CA3AF;">
                Aucune prestation ne correspond encore au type de moto renseigné.
              </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr auto;gap:12px;align-items:end;">
              <div class="form-group" style="margin:0;">
                <label class="form-label">Commentaire</label>
                <textarea v-model="quickForm.commentaire" class="form-input" rows="3" placeholder="Description du besoin client…"></textarea>
              </div>
              <div class="form-group" style="margin:0;">
                <label class="form-label">Numéros de commande</label>
                <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px;">
                  <span v-for="(cmd, idx) in quickForm.commandes" :key="idx" style="display:flex;align-items:center;gap:4px;font-size:12px;color:#FFD200;background:rgba(255,210,0,0.08);padding:4px 10px;border-radius:6px;border:1px solid rgba(255,210,0,0.15);">
                    #{{ cmd }}
                    <button type="button" style="background:none;border:none;color:#FFD200;font-size:14px;line-height:1;cursor:pointer;" @click="quickForm.commandes.splice(idx, 1)">×</button>
                  </span>
                </div>
                <input
                  v-model="quickCommandeInput"
                  class="form-input"
                  placeholder="N° commande (Entrée ou virgule pour ajouter)"
                  @keydown.enter.prevent="addQuickCommande"
                  @keydown.",.prevent="addQuickCommande"
                />
              </div>
              <div style="min-width:200px;padding:12px;border-radius:10px;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06);">
                <div style="font-size:11px;color:#9CA3AF;">Estimé</div>
                <div style="font-size:18px;font-weight:800;color:#FFD200;">{{ formatCurrency(quickEstimateTotal) }}</div>
                <div style="font-size:12px;color:#CBD5E1;">{{ formatMinutes(quickEstimateDuration) }}</div>
                <div style="font-size:12px;color:#CBD5E1;">{{ quickForm.heure_debut || '—' }} → {{ quickEstimatedEnd }}</div>
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

    <AppModal v-model:open="showMoveConfirmModal" size="sm">
      <template #content>
        <UCard>
          <template #header>
            <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
              <span style="font-weight:700;color:#E8E9ED;">Confirmer le déplacement</span>
              <button style="background:none;border:none;color:#9CA3AF;font-size:18px;cursor:pointer;" @click="cancelMove">✕</button>
            </div>
          </template>

          <div v-if="pendingMove?.rdv" style="display:flex;flex-direction:column;gap:12px;font-size:13px;color:#D1D5DB;">
            <div>
              <span style="color:#6B7280;">RDV :</span>
              <strong style="color:#E8E9ED;">{{ pendingMove.rdv.client_nom || 'Client' }} · {{ pendingMove.rdv.type_intervention }}</strong>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
              <span style="color:#6B7280;">De :</span>
              <span>{{ formatDateDisplay(pendingMove.rdv.date_rdv) }} à {{ pendingMove.rdv.heure_debut }}</span>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
              <span style="color:#6B7280;">Vers :</span>
              <span style="color:#FFD200;font-weight:700;">{{ formatDateDisplay(pendingMove.date) }} à {{ pendingMove.time }}</span>
            </div>
          </div>

          <template #footer>
            <div style="display:flex;justify-content:flex-end;gap:10px;">
              <button class="btn btn-ghost" @click="cancelMove">Annuler</button>
              <button class="btn btn-primary" @click="confirmMove">Confirmer le déplacement</button>
            </div>
          </template>
        </UCard>
      </template>
    </AppModal>

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
              <button style="background:none;border:none;color:#9CA3AF;font-size:18px;cursor:pointer;" @click="showRdvModal = false">✕</button>
            </div>
          </template>

          <div v-if="modalLoading" style="padding:16px;color:#9CA3AF;">Chargement du rendez-vous…</div>

          <div v-else-if="selectedRdv" style="display:flex;flex-direction:column;gap:16px;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:13px;">
              <div><span style="color:#6B7280;">Date :</span> <span style="color:#D1D5DB;">{{ formatDateDisplay(selectedRdv.date_rdv) }}</span></div>
              <div><span style="color:#6B7280;">Heure :</span> <span style="color:#D1D5DB;">{{ selectedRdv.heure_debut }}</span></div>
              <div><span style="color:#6B7280;">Client :</span> <span style="color:#D1D5DB;">{{ selectedRdv.client_nom || '—' }}</span></div>
              <div><span style="color:#6B7280;">Véhicule :</span> <span style="color:#D1D5DB;">{{ selectedRdv.vehicule_info || '—' }}</span></div>
              <div><span style="color:#6B7280;">Pont :</span> <span style="color:#D1D5DB;">{{ selectedRdv.pont?.nom || selectedRdv.pont_nom || '—' }}</span></div>
              <div><span style="color:#6B7280;">Mécanicien :</span> <span style="color:#D1D5DB;">{{ selectedRdv.mecanicien_nom || '—' }}</span></div>
              <div><span style="color:#6B7280;">Durée :</span> <span style="color:#D1D5DB;">{{ formatMinutes(selectedRdv.temps_estime ?? selectedRdv.duree_estimee) }}</span></div>
              <div><span style="color:#6B7280;">Type :</span> <span style="color:#D1D5DB;">{{ selectedRdv.type_intervention || '—' }}</span></div>
            </div>

            <div style="padding:12px;border-radius:10px;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06);">
              <div style="font-size:13px;font-weight:700;color:#E8E9ED;margin-bottom:10px;">Édition rapide / affectation</div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
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
                  <label class="form-label">Durée estimée</label>
                  <input
                    v-model="editForm.durationDisplay"
                    type="text"
                    class="form-input"
                    placeholder="hh:mm"
                    maxlength="5"
                    :disabled="selectedIsHistorical || !canEditRdv || prestationLocked"
                    @change="editForm.temps_estime = hhMmToMinutes(editForm.durationDisplay)"
                    @blur="editForm.durationDisplay = minutesToHhMm(editForm.temps_estime)"
                  />
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
                  <div class="form-input" style="display:flex;align-items:center;min-height:42px;color:#CBD5E1;">{{ editAssignedMecanicienLabel }}</div>
                </div>
              </div>

              <div class="form-group" style="margin-top:12px;">
                <label class="form-label">Commentaire</label>
                <textarea v-model="editForm.commentaire" class="form-input" rows="3" :disabled="selectedIsHistorical || !canEditRdv" placeholder="Notes réception / atelier…"></textarea>
              </div>

              <div class="form-group" style="margin-top:12px;">
                <label class="form-label">Numéros de commande</label>
                <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px;">
                  <span v-for="(cmd, idx) in editForm.commandes" :key="idx" style="display:flex;align-items:center;gap:4px;font-size:12px;color:#FFD200;background:rgba(255,210,0,0.08);padding:4px 10px;border-radius:6px;border:1px solid rgba(255,210,0,0.15);">
                    #{{ cmd }}
                    <button v-if="!selectedIsHistorical && canEditRdv" type="button" style="background:none;border:none;color:#FFD200;font-size:14px;line-height:1;cursor:pointer;" @click="editForm.commandes.splice(idx, 1)">×</button>
                  </span>
                </div>
                <input
                  v-if="!selectedIsHistorical && canEditRdv"
                  v-model="commandeInput"
                  class="form-input"
                  placeholder="N° commande (Entrée ou virgule pour ajouter)"
                  :disabled="selectedIsHistorical || !canEditRdv"
                  @keydown.enter.prevent="addCommande"
                  @keydown.",.prevent="addCommande"
                />
              </div>

              <div v-if="prestationLocked" style="margin-top:10px;padding:8px 12px;border-radius:8px;background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.2);font-size:12px;color:#FBBF24;">
                Après la réception, la prestation et sa durée sont figées. Seules l'affectation, la note et le suivi restent modifiables.
              </div>

              <!-- Enhanced Reception Panel -->
              <div v-if="isReceptionEligible" style="margin-top:12px;padding:14px;border-radius:12px;background:rgba(255,210,0,0.04);border:1px solid rgba(255,210,0,0.15);">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                  <div style="font-size:14px;font-weight:700;color:#FFD200;">📥 Réception du véhicule</div>
                  <button class="btn btn-ghost" style="font-size:11px;padding:4px 10px;min-height:auto;" @click="refreshCompanionStatus">↻ Statut PDA</button>
                </div>

                <!-- Companion QR + Link -->
                <div style="display:flex;gap:12px;align-items:flex-start;margin-bottom:14px;padding:12px;border-radius:10px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);">
                  <div style="flex:1;">
                    <div style="font-size:12px;font-weight:600;color:#E8E9ED;margin-bottom:6px;">📱 Compagnon PDA</div>
                    <p style="font-size:11px;color:#9CA3AF;margin:0 0 8px;">Ouvrez ce lien sur le téléphone pour : photos, scan carte grise, checkup express, signature client.</p>
                    <div style="display:flex;gap:6px;align-items:center;">
                      <input :value="companionUrl" class="form-input" style="font-size:11px;flex:1;" readonly @focus="($event.target as HTMLInputElement)?.select()" />
                      <button class="btn btn-ghost" style="padding:6px 10px;font-size:11px;min-height:auto;" @click="copyCompanionUrl">📋</button>
                    </div>
                  </div>
                  <div v-if="companionQrUrl" style="min-width:100px;text-align:center;">
                    <img :src="companionQrUrl" alt="QR Code" style="width:100px;height:100px;border-radius:8px;background:white;padding:4px;" />
                    <div style="font-size:10px;color:#6B7280;margin-top:4px;">Scanner avec le tél.</div>
                  </div>
                </div>

                <!-- Companion Live Status -->
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

                <!-- Reception fields -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                  <div class="form-group">
                    <label class="form-label">Kilométrage réception</label>
                    <input v-model="receptionForm.kilometrage" type="number" class="form-input" placeholder="km" :disabled="selectedIsHistorical" />
                  </div>
                  <div class="form-group">
                    <label class="form-label">État véhicule</label>
                    <input v-model="receptionForm.etat_vehicule" class="form-input" placeholder="Bon état / rayure…" :disabled="selectedIsHistorical" />
                  </div>
                </div>

                <!-- Signature warning -->
                <div v-if="!companionStatus.has_signature" style="margin-top:10px;padding:8px 12px;border-radius:8px;background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);font-size:12px;color:#FCA5A5;">
                  ⚠️ Signature client obligatoire pour valider la réception. Utilisez le compagnon PDA pour faire signer.
                </div>
              </div>

              <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:12px;flex-wrap:wrap;">
                <button class="btn btn-ghost" @click="showRdvModal = false">Fermer</button>
                <button v-if="canEditRdv && !selectedIsHistorical" class="btn btn-primary" :disabled="editSaving" @click="saveRdvChanges">
                  {{ editSaving ? 'Sauvegarde…' : 'Enregistrer les modifications' }}
                </button>
                <button v-if="canDeleteSelected" class="btn" style="background:rgba(239,68,68,0.14);color:#FCA5A5;border-color:rgba(239,68,68,0.28);" :disabled="deleting" @click="deleteSelectedRdv">
                  {{ deleting ? 'Suppression…' : 'Supprimer le RDV' }}
                </button>
              </div>
            </div>

            <!-- Document OR unique -->
            <div v-if="selectedRdv.ordres_reparation?.length" style="padding:12px;border-radius:10px;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06);">
              <div style="font-size:13px;font-weight:700;color:#E8E9ED;margin-bottom:10px;">📄 Ordre de Réparation</div>
              <div style="display:flex;flex-direction:column;gap:6px;">
                <div v-for="or in selectedRdv.ordres_reparation" :key="or.id" style="display:flex;flex-direction:column;gap:6px;padding:8px 10px;border-radius:8px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);">
                  <div style="display:flex;align-items:center;justify-content:space-between;">
                    <div style="font-size:12px;font-weight:600;color:#D1D5DB;">{{ or.numero_or }}</div>
                    <div style="font-size:11px;color:#6B7280;">{{ or.statut }}</div>
                  </div>
                  <!-- Signatures -->
                  <div style="display:flex;gap:6px;flex-wrap:wrap;font-size:11px;">
                    <span style="padding:3px 8px;border-radius:999px;" :style="or.signature_client ? 'background:rgba(16,185,129,0.08);color:#6EE7B7;' : 'background:rgba(255,255,255,0.03);color:#6B7280;'">Client réception {{ or.signature_client ? '✓' : '—' }}</span>
                    <span style="padding:3px 8px;border-radius:999px;" :style="or.signature_atelier_reception ? 'background:rgba(16,185,129,0.08);color:#6EE7B7;' : 'background:rgba(255,255,255,0.03);color:#6B7280;'">Atelier réception {{ or.signature_atelier_reception ? '✓' : '—' }}</span>
                    <span style="padding:3px 8px;border-radius:999px;" :style="or.signature_mecanicien ? 'background:rgba(16,185,129,0.08);color:#6EE7B7;' : 'background:rgba(255,255,255,0.03);color:#6B7280;'">Mécanicien {{ or.signature_mecanicien ? '✓' : '—' }}</span>
                    <span style="padding:3px 8px;border-radius:999px;" :style="or.signature_client_restitution ? 'background:rgba(16,185,129,0.08);color:#6EE7B7;' : 'background:rgba(255,255,255,0.03);color:#6B7280;'">Client restitution {{ or.signature_client_restitution ? '✓' : '—' }}</span>
                  </div>
                  <div v-if="or.travaux_realises" style="font-size:11px;color:#9CA3AF;">
                    <span style="color:#6B7280;">Travaux :</span> {{ or.travaux_realises.slice(0, 80) }}{{ or.travaux_realises.length > 80 ? '…' : '' }}
                  </div>

                  <!-- Toggle détail complet -->
                  <button
                    class="btn btn-ghost"
                    style="font-size:11px;padding:4px 10px;min-height:auto;margin-top:4px;align-self:flex-start;"
                    :disabled="orDetailLoading === or.id"
                    @click="loadOrDetail(or.id)"
                  >
                    {{ orDetailLoading === or.id ? 'Chargement…' : (orDetailOpen[or.id] ? '▲ Masquer le document' : '▶ Voir le document complet') }}
                  </button>

                  <!-- Panneau détail OR -->
                  <div v-if="orDetailOpen[or.id] && orDetails[or.id]" style="margin-top:8px;display:flex;flex-direction:column;gap:10px;">
                    <div v-if="orDetails[or.id].travaux" style="padding:10px;border-radius:8px;background:rgba(59,130,246,0.06);border:1px solid rgba(59,130,246,0.15);">
                      <div style="font-size:11px;font-weight:700;color:#BFDBFE;margin-bottom:4px;">📝 Travaux demandés</div>
                      <div style="font-size:12px;color:#D1D5DB;white-space:pre-wrap;">{{ orDetails[or.id].travaux }}</div>
                    </div>
                    <div v-if="orDetails[or.id].etat_vehicule" style="padding:10px;border-radius:8px;background:rgba(59,130,246,0.06);border:1px solid rgba(59,130,246,0.15);">
                      <div style="font-size:11px;font-weight:700;color:#BFDBFE;margin-bottom:4px;">🔍 État véhicule</div>
                      <div style="font-size:12px;color:#D1D5DB;white-space:pre-wrap;">{{ orDetails[or.id].etat_vehicule }}</div>
                    </div>

                    <!-- Intervention -->
                    <div v-if="orDetails[or.id].travaux_realises || orDetails[or.id].alertes || orDetails[or.id].recommandations || orDetails[or.id].garantie" style="padding:10px;border-radius:8px;background:rgba(16,185,129,0.06);border:1px solid rgba(16,185,129,0.15);">
                      <div style="font-size:11px;font-weight:700;color:#6EE7B7;margin-bottom:6px;">🔧 Intervention</div>
                      <div v-if="orDetails[or.id].travaux_realises" style="font-size:12px;color:#D1D5DB;margin-bottom:6px;white-space:pre-wrap;"><span style="color:#6B7280;">Travaux réalisés :</span> {{ orDetails[or.id].travaux_realises }}</div>
                      <div v-if="orDetails[or.id].alertes?.length" style="font-size:12px;color:#FCA5A5;margin-bottom:6px;"><span style="color:#EF4444;">⚠️ Alertes :</span> {{ Array.isArray(orDetails[or.id].alertes) ? orDetails[or.id].alertes.join(', ') : orDetails[or.id].alertes }}</div>
                      <div v-if="orDetails[or.id].recommandations" style="font-size:12px;color:#FBBF24;margin-bottom:6px;white-space:pre-wrap;"><span style="color:#F59E0B;">💡 Recommandations :</span> {{ orDetails[or.id].recommandations }}</div>
                      <div v-if="orDetails[or.id].garantie" style="font-size:12px;color:#6EE7B7;white-space:pre-wrap;"><span style="color:#10B981;">🛡️ Garantie :</span> {{ orDetails[or.id].garantie }}</div>
                      <div v-if="orDetails[or.id].kilometrage_restitution" style="font-size:12px;color:#D1D5DB;margin-top:4px;"><span style="color:#6B7280;">Km restitution :</span> {{ orDetails[or.id].kilometrage_restitution }} km</div>
                      <div v-if="orDetails[or.id].prochaine_revision_km" style="font-size:12px;color:#D1D5DB;"><span style="color:#6B7280;">Prochaine révision :</span> {{ orDetails[or.id].prochaine_revision_km }} km</div>
                      <div v-if="orDetails[or.id].prochaine_revision_date" style="font-size:12px;color:#D1D5DB;"><span style="color:#6B7280;">Date prochaine révision :</span> {{ orDetails[or.id].prochaine_revision_date }}</div>
                    </div>

                    <!-- Signatures images -->
                    <div style="display:flex;flex-direction:column;gap:8px;">
                      <div v-if="orDetails[or.id].signature_client" style="padding:10px;border-radius:8px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);">
                        <div style="font-size:11px;font-weight:700;color:#E8E9ED;margin-bottom:6px;">✍️ Signature client réception</div>
                        <img :src="orDetails[or.id].signature_client" style="max-width:200px;border-radius:6px;background:white;padding:4px;" />
                        <div v-if="orDetails[or.id].signed_at" style="font-size:10px;color:#6B7280;margin-top:4px;">{{ orDetails[or.id].signed_at }}</div>
                      </div>
                      <div v-if="orDetails[or.id].signature_atelier_reception" style="padding:10px;border-radius:8px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);">
                        <div style="font-size:11px;font-weight:700;color:#E8E9ED;margin-bottom:6px;">✍️ Signature atelier réception</div>
                        <img :src="orDetails[or.id].signature_atelier_reception" style="max-width:200px;border-radius:6px;background:white;padding:4px;" />
                        <div v-if="orDetails[or.id].signe_receptionniste_at" style="font-size:10px;color:#6B7280;margin-top:4px;">{{ orDetails[or.id].signe_receptionniste_at }}</div>
                      </div>
                      <div v-if="orDetails[or.id].signature_mecanicien" style="padding:10px;border-radius:8px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);">
                        <div style="font-size:11px;font-weight:700;color:#E8E9ED;margin-bottom:6px;">✍️ Signature mécanicien</div>
                        <img :src="orDetails[or.id].signature_mecanicien" style="max-width:200px;border-radius:6px;background:white;padding:4px;" />
                        <div v-if="orDetails[or.id].signe_mecanicien_at" style="font-size:10px;color:#6B7280;margin-top:4px;">{{ orDetails[or.id].signe_mecanicien_at }}</div>
                      </div>
                      <div v-if="orDetails[or.id].signature_client_restitution" style="padding:10px;border-radius:8px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);">
                        <div style="font-size:11px;font-weight:700;color:#E8E9ED;margin-bottom:6px;">✍️ Signature client restitution</div>
                        <img :src="orDetails[or.id].signature_client_restitution" style="max-width:200px;border-radius:6px;background:white;padding:4px;" />
                        <div v-if="orDetails[or.id].signe_client_restitution_at" style="font-size:10px;color:#6B7280;margin-top:4px;">{{ orDetails[or.id].signe_client_restitution_at }}</div>
                      </div>
                    </div>

                    <div v-if="orDetails[or.id].signed_hash" style="font-size:10px;color:#6B7280;word-break:break-all;">
                      Empreinte : {{ orDetails[or.id].signed_hash }}
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div style="padding:12px;border-radius:10px;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06);">
              <div style="font-size:13px;font-weight:700;color:#E8E9ED;margin-bottom:10px;">Workflow atelier</div>
              <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <!-- Bouton principal (prochaine étape) -->
                <button
                  v-for="transition in availableTransitions.filter(t => t.name !== 'annuler')"
                  :key="transition.name"
                  class="btn"
                  :style="transitionButtonStyle(transition.color)"
                  :disabled="transitioning === transition.name || selectedIsHistorical"
                  @click="applyTransition(transition.name)"
                >
                  {{ transitioning === transition.name ? 'Traitement…' : transition.label }}
                </button>
                <!-- Bouton annuler (discret) -->
                <button
                  v-for="transition in availableTransitions.filter(t => t.name === 'annuler')"
                  :key="transition.name"
                  style="background:none;border:none;color:#9CA3AF;font-size:11px;cursor:pointer;padding:4px 8px;"
                  :disabled="transitioning === transition.name || selectedIsHistorical"
                  @click="applyTransition(transition.name)"
                >
                  {{ transitioning === transition.name ? 'Traitement…' : transition.label }}
                </button>
              </div>
            </div>

            <div v-if="selectedRdv.commentaire" style="font-size:12px;color:#CBD5E1;">{{ selectedRdv.commentaire }}</div>
          </div>
        </UCard>
      </template>
    </AppModal>
  </div>
</template>

<script setup lang="ts">
const api = useApi()
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

const showRdvModal = ref(false)
const showQuickCreateModal = ref(false)
const showMoveConfirmModal = ref(false)
const commandeInput = ref('')
const quickCommandeInput = ref('')
const selectedRdv = ref<any | null>(null)
const quickSelectedPrestas = ref<number[]>([])
const quickClientSearch = ref('')
const quickClientResults = ref<any[]>([])
const quickSelectedClient = ref<any | null>(null)
const quickVehicleSearch = ref('')
const quickVehicleFound = ref(false)
const pendingMove = ref<{ id: number; date: string; time: string; rdv: any } | null>(null)

const HISTORY_STATUSES = ['termine', 'restitue', 'facture', 'paye', 'annule']
const PRESTATION_LOCK_STATUSES = ['reception', 'en_cours', 'termine', 'restitue', 'facture', 'paye']
const DAY_LABELS = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim']
const MOTO_TYPES = ['Roadster', 'Sportive', 'Trail', 'Custom', 'Scooter', 'Enduro', 'Adventure', 'GT']

function minutesToHhMm(minutes: number): string {
  const total = Math.max(0, Math.round(minutes))
  const h = Math.floor(total / 60)
  const m = total % 60
  return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`
}
function hhMmToMinutes(value: string): number {
  const [h, m] = value.split(':').map(Number)
  if (Number.isNaN(h) || Number.isNaN(m)) return 0
  return Math.max(0, h * 60 + m)
}

const editForm = reactive({
  date_rdv: '',
  heure_debut: '10:00',
  type_intervention: '',
  temps_estime: 60,
  durationDisplay: '01:00',
  commentaire: '',
  mecanicien_id: null as number | null,
  pont_id: null as number | null,
  commandes: [] as string[],
})

const receptionForm = reactive({
  kilometrage: '',
  etat_vehicule: '',
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
  commandes: [] as string[],
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
  reserver: { label: '📌 Réserver', color: 'neutral' },
  confirmer: { label: '✅ Confirmer', color: 'primary' },
  reception: { label: '📥 Réceptionner', color: 'warning' },
  start_travail: { label: '🔧 Démarrer', color: 'warning' },
  terminer: { label: '✅ Terminer', color: 'success' },
  restituer: { label: '🚚 Restituer', color: 'info' },
  facturer: { label: '💶 Facturer', color: 'primary' },
  payer: { label: '💳 Encaisser', color: 'success' },
  annuler: { label: '❌ Annuler', color: 'error' },
}

// Séquence de workflow : pour chaque statut, quelle est la prochaine transition attendue
// start_travail et terminer sont volontairement exclus — ce sont les mécaniciens qui déclenchent ces statuts
const WORKFLOW_SEQUENCE: Record<string, { next?: string; cancel?: string }> = {
  en_attente: { next: 'reserver', cancel: 'annuler' },
  reserve: { next: 'confirmer', cancel: 'annuler' },
  confirme: { next: 'reception', cancel: 'annuler' },
  reception: {},
  en_cours: {},
  termine: { next: 'restituer' },
  restitue: { next: 'facturer' },
  facture: { next: 'payer' },
}

const canCreateRdv = computed(() => hasPerm('rdv.create'))
const canEditRdv = computed(() => hasPerm('rdv.edit'))
const canDeleteRdv = computed(() => hasPerm('rdv.delete'))
const selectedIsHistorical = computed(() => isHistoricalStatus(selectedRdv.value?.status ?? selectedRdv.value?.statut))
const prestationLocked = computed(() => PRESTATION_LOCK_STATUSES.includes(selectedRdv.value?.status ?? selectedRdv.value?.statut ?? ''))
const canDeleteSelected = computed(() => canDeleteRdv.value && !!selectedRdv.value && !selectedIsHistorical.value)

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
  const active = normalizedRdvs.value.filter((rdv: any) => !isHistoricalStatus(rdv.status))
  if (!activeMecas.value.length) return active
  return active.filter((rdv: any) => !rdv.mecanicien_id || activeMecas.value.includes(rdv.mecanicien_id))
})

const historicalRdvs = computed(() => {
  const all = normalizedRdvs.value.filter((rdv: any) => isHistoricalStatus(rdv.status))
  return all.sort((a: any, b: any) => `${b.date_rdv} ${b.heure_debut}`.localeCompare(`${a.date_rdv} ${a.heure_debut}`))
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
  const seq = WORKFLOW_SEQUENCE[String(status || '')]
  if (!seq) return []
  return [seq.next, seq.cancel].filter((n): n is string => !!n).map((name) => ({
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
  editForm.durationDisplay = minutesToHhMm(editForm.temps_estime)
  editForm.commentaire = rdv.commentaire || ''
  editForm.mecanicien_id = toNullableNumber(rdv.mecanicien?.id ?? rdv.mecanicien_id)
  editForm.pont_id = toNullableNumber(rdv.pont?.id ?? rdv.pont_id)
  editForm.commandes = Array.isArray(rdv.commandes) ? [...rdv.commandes] : []
  receptionForm.kilometrage = rdv.kilometrage ? String(rdv.kilometrage) : ''
  receptionForm.etat_vehicule = rdv.etat_vehicule || rdv.etatVehicule || ''
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
  quickForm.commandes = []
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

let quickClientSearchTimeout: ReturnType<typeof setTimeout>
function searchQuickClients() {
  clearTimeout(quickClientSearchTimeout)
  if (quickClientSearch.value.trim().length < 2) {
    quickClientResults.value = []
    return
  }

  quickClientSearchTimeout = setTimeout(async () => {
    try {
      const data = await api.get(`/clients?search=${encodeURIComponent(quickClientSearch.value.trim())}`)
      quickClientResults.value = unwrapList(data)
    } catch {
      quickClientResults.value = []
    }
  }, 250)
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

async function loadPlanningData() {
  const [p, r, m, h, prestaData, configData] = await Promise.all([
    api.get('/ponts?itemsPerPage=200').catch(() => []),
    api.get('/rendez-vous?itemsPerPage=200').catch(() => []),
    api.get('/mecaniciens').catch(() => []),
    api.get('/config/horaires').catch(() => []),
    api.get('/prestations?itemsPerPage=200').catch(() => []),
    api.get('/config').catch(() => null),
  ])

  ponts.value = unwrapList(p)
  rawRdvs.value = unwrapList(r)
  mecaniciens.value = unwrapList(m).filter((item: any) => item.is_active !== false && item.is_active !== 0)
  horaires.value = normalizeHoraires(unwrapList(h).length ? h : (configData as any)?.horaires ?? [])
  prestations.value = unwrapList(prestaData).map(normalizePrestation).filter((item: any) => item.is_active !== false && item.is_active !== 0)
}

async function loadAvailableTransitions(id: number) {
  try {
    const data = await api.get(`/rendez-vous/${id}/transitions`)
    const transitions = Array.isArray(data?.transitions) ? data.transitions : []
    const seq = WORKFLOW_SEQUENCE[selectedRdv.value?.status ?? '']
    const allowed = new Set([seq?.next, seq?.cancel].filter((n): n is string => !!n))
    availableTransitions.value = transitions
      .filter((name: string) => allowed.has(name))
      .map((name: string) => ({
        name,
        label: transitionCatalog[name]?.label ?? name,
        color: transitionCatalog[name]?.color ?? 'neutral',
      }))
  } catch {
    availableTransitions.value = fallbackTransitionsForStatus(selectedRdv.value?.status)
  }
}

async function reloadSelectedRdv(id: number) {
  await rdvStore.fetchRdv(id)
  const fresh = normalizeRdv(rdvStore.currentRdv)
  if (fresh) {
    selectedRdv.value = fresh
    hydrateEditForms(fresh)
    await loadAvailableTransitions(id)
  }
}

async function loadOrDetail(orId: number) {
  if (orDetails.value[orId]) {
    orDetailOpen.value[orId] = !orDetailOpen.value[orId]
    return
  }
  orDetailLoading.value = orId
  try {
    const data = await api.get(`/or/${orId}`)
    orDetails.value[orId] = data
    orDetailOpen.value[orId] = true
  } catch {
    toast.add({ title: 'Erreur', description: 'Impossible de charger le détail de l\'OR', color: 'error' })
  } finally {
    orDetailLoading.value = null
  }
}

async function onSelectRdv(rdv: any) {
  showRdvModal.value = true
  modalLoading.value = true
  selectedRdv.value = normalizeRdv(rdv)
  hydrateEditForms(selectedRdv.value)
  try {
    await reloadSelectedRdv(Number(rdv.id))
  } finally {
    modalLoading.value = false
  }
}

function onMoveRdv(payload: { id: number; date: string; time: string }) {
  if (!canEditRdv.value) return
  const rdv = activePlanningRdvs.value.find((r: any) => r.id === payload.id)
  pendingMove.value = { ...payload, rdv: rdv ?? null }
  showMoveConfirmModal.value = true
}

async function confirmMove() {
  if (!pendingMove.value) return
  showMoveConfirmModal.value = false
  try {
    await rdvStore.updateRdv(pendingMove.value.id, {
      dateRdv: pendingMove.value.date,
      heureRdv: `${pendingMove.value.time}:00`,
    })
    toast.add({ title: 'RDV déplacé', color: 'success' })
    await refreshPlanning()
  } catch (e: any) {
    toast.add({ title: 'Déplacement impossible', description: e?.message || 'Erreur inconnue', color: 'error' })
  } finally {
    pendingMove.value = null
  }
}

function cancelMove() {
  showMoveConfirmModal.value = false
  pendingMove.value = null
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
      commandes: quickForm.commandes,
    }

    const created = await rdvStore.createRdv(payload)
    showQuickCreateModal.value = false
    toast.add({ title: 'RDV créé', color: 'success' })
    await refreshPlanning()
    if (created?.id) await onSelectRdv(created)
  } catch (e: any) {
    toast.add({ title: 'Création impossible', description: e?.message || 'Erreur inconnue', color: 'error' })
  } finally {
    quickSubmitting.value = false
  }
}

function addCommande() {
  const val = commandeInput.value.trim()
  if (!val) return
  const nums = val.split(/[,;]+/).map(s => s.trim()).filter(Boolean)
  for (const num of nums) {
    if (!editForm.commandes.includes(num)) {
      editForm.commandes.push(num)
    }
  }
  commandeInput.value = ''
}

function addQuickCommande() {
  const val = quickCommandeInput.value.trim()
  if (!val) return
  const nums = val.split(/[,;]+/).map(s => s.trim()).filter(Boolean)
  for (const num of nums) {
    if (!quickForm.commandes.includes(num)) {
      quickForm.commandes.push(num)
    }
  }
  quickCommandeInput.value = ''
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

    // Sync commandes
    if (editForm.commandes.length || selectedRdv.value.commandes?.length) {
      await api.post(`/rendez-vous/${selectedRdv.value.id}/commandes`, { commandes: editForm.commandes })
    }

    toast.add({ title: 'RDV mis à jour', color: 'success' })
    await refreshPlanning()
  } catch (e: any) {
    toast.add({ title: 'Modification impossible', description: e?.message || 'Erreur inconnue', color: 'error' })
  } finally {
    editSaving.value = false
  }
}

async function applyTransition(name: string) {
  if (!selectedRdv.value?.id || selectedIsHistorical.value) return
  if (name === 'annuler' && !globalThis.confirm?.('Confirmer l\'annulation du rendez-vous ?')) return

  transitioning.value = name
  try {
    const resolvedPontId = toNullableNumber(editForm.pont_id)
    const resolvedMecanicienId = resolvePontMecanicienId(resolvedPontId) ?? toNullableNumber(editForm.mecanicien_id)

    const payload: any = {
      pont_id: resolvedPontId,
      mecanicien_id: resolvedMecanicienId,
    }

    if (name === 'reception') {
      if (receptionForm.kilometrage) payload.kilometrage = toNumber(receptionForm.kilometrage)
      if (receptionForm.etat_vehicule.trim()) payload.etat_vehicule = receptionForm.etat_vehicule.trim()
    }

    await api.post(`/rendez-vous/${selectedRdv.value.id}/transition/${name}`, payload)
    toast.add({ title: 'Transition effectuée', color: 'success' })
    await refreshPlanning()
  } catch (e: any) {
    toast.add({ title: 'Transition impossible', description: e?.message || 'Erreur inconnue', color: 'error' })
  } finally {
    transitioning.value = ''
  }
}

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
  } catch (e: any) {
    toast.add({ title: 'Suppression impossible', description: e?.message || 'Erreur inconnue', color: 'error' })
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
  quickSelectedPrestas.value = quickSelectedPrestas.value.filter((id) => filteredQuickPrestations.value.some((presta: any) => Number(presta.id) === Number(id)))
})

// --- Companion / Reception ---
const companionStatus = reactive({
  photos_count: 0,
  checkup_done: 0,
  has_signature: false,
})

const isReceptionEligible = computed(() => {
  const s = selectedRdv.value?.status ?? selectedRdv.value?.statut
  return ['confirme', 'reserve', 'reception'].includes(s)
})

const companionUrl = computed(() => {
  const token = selectedRdv.value?.token_suivi ?? selectedRdv.value?.tokenSuivi
  if (!token) return ''
  const origin = globalThis.location?.origin || ''
  return `${origin}/public/companion?token=${token}`
})

const companionQrUrl = ref('')
watch(companionUrl, async (url) => {
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

watch(showRdvModal, (open) => {
  clearInterval(companionPollInterval)
  if (open && isReceptionEligible.value) {
    refreshCompanionStatus()
    companionPollInterval = setInterval(refreshCompanionStatus, 4000)
  }
})

watch(isReceptionEligible, (eligible) => {
  clearInterval(companionPollInterval)
  if (eligible && showRdvModal.value) {
    refreshCompanionStatus()
    companionPollInterval = setInterval(refreshCompanionStatus, 4000)
  }
})

onMounted(async () => {
  try {
    await loadPlanningData()
    consumeWorkshopQuickCreateQuery()
  } finally {
    loading.value = false
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
</style>
