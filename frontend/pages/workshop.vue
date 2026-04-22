<template>
  <div>
    <AppPageHeader title="Atelier" subtitle="Pilotage des ponts, affectations mécaniciens et charge du jour." />

    <AppLoadingState
      v-if="loading"
      title="Chargement de l’atelier"
      description="Les statuts des ponts et de l’équipe sont en cours de récupération."
    />

    <AppErrorState
      v-else-if="errorMessage"
      title="Atelier temporairement indisponible"
      :description="errorMessage"
      @retry="loadWorkshop"
    />

    <template v-else>
      <AppBanner
        v-if="partialIssues.length"
        variant="danger"
        icon="⚠️"
        title="Données atelier partielles"
        :description="`Certaines sections sont indisponibles : ${partialIssues.join(', ')}.`"
      />

      <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px;">
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <button class="btn btn-ghost" :disabled="refreshing" @click="refreshWorkshop">{{ refreshing ? 'Actualisation…' : '↻ Actualiser' }}</button>
          <NuxtLink class="btn btn-primary" to="/planning" style="text-decoration:none;">Ouvrir le planning</NuxtLink>
          <NuxtLink class="btn btn-ghost" :to="buildPlanningCreateLink()" style="text-decoration:none;">+ RDV rapide</NuxtLink>
        </div>
        <div style="font-size:12px;color:#9CA3AF;">Mis à jour {{ lastUpdatedAt || 'à l’instant' }}</div>
      </div>

      <!-- KPI Bar -->
      <div class="workshop-kpi-bar">
        <div class="workshop-kpi">
          <div class="workshop-kpi-label">OCCUPATION PONTS</div>
          <div class="workshop-kpi-value">{{ kpis.occupation }}%</div>
        </div>
        <div class="workshop-kpi">
          <div class="workshop-kpi-label">RDV AUJOURD'HUI</div>
          <div class="workshop-kpi-value">{{ kpis.rdvsToday }}</div>
        </div>
        <div class="workshop-kpi">
          <div class="workshop-kpi-label">MÉCANICIENS ACTIFS</div>
          <div class="workshop-kpi-value">{{ kpis.activeMecas }}</div>
        </div>
        <div class="workshop-kpi">
          <div class="workshop-kpi-label" :style="kpis.conflicts > 0 ? 'color:#FCA5A5;' : ''">CONFLITS</div>
          <div class="workshop-kpi-value" :style="kpis.conflicts > 0 ? 'color:#FCA5A5;' : ''">{{ kpis.conflicts }}</div>
        </div>
      </div>

      <!-- Tabs -->
      <div class="tabs">
        <button class="tab" :class="{ active: activeTab === 'ponts' }" @click="activeTab = 'ponts'">🔧 Ponts</button>
        <button class="tab" :class="{ active: activeTab === 'mecas' }" @click="activeTab = 'mecas'">👤 Mécaniciens</button>
        <button class="tab" :class="{ active: activeTab === 'alertes' }" @click="activeTab = 'alertes'">
          ⚠️ Alertes
          <span v-if="alertesTotalCount > 0" style="margin-left:6px;background:rgba(239,68,68,0.22);color:#FCA5A5;padding:1px 7px;border-radius:9999px;font-size:10px;font-weight:700;">{{ alertesTotalCount }}</span>
        </button>
        <button class="tab" :class="{ active: activeTab === 'gardiennage' }" @click="activeTab = 'gardiennage'">
          🅿 Gardiennage
          <span v-if="gardinnageRdvs.length" style="margin-left:6px;background:rgba(239,68,68,0.18);color:#FCA5A5;padding:1px 7px;border-radius:9999px;font-size:10px;font-weight:700;">{{ gardinnageRdvs.length }}</span>
        </button>
        <button class="tab" :class="{ active: activeTab === 'absences' }" @click="activeTab = 'absences'">📅 Absences</button>
      </div>

      <!-- PONTS TAB -->
      <div v-if="activeTab === 'ponts'">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:12px;">
          <p style="margin:0;color:#9CA3AF;font-size:13px;">Ici tu actives ou désactives un pont et tu changes le mécanicien rattaché sans passer par l’admin.</p>
          <NuxtLink to="/planning" style="color:#FFD200;font-size:12px;font-weight:700;text-decoration:none;">Voir le planning →</NuxtLink>
        </div>

        <div v-if="enrichedPonts.length" class="pont-grid">
          <div
            v-for="pont in enrichedPonts"
            :key="pont.id"
            class="pont-card"
            :class="!isActiveFlag(pont.is_active ?? pont.est_actif) ? 'pont-maintenance' : (pont.current_rdv || pont.day_schedule.length ? 'pont-occupe' : 'pont-libre')"
          >
            <div class="pont-card-header">
              <span class="pont-name">{{ pont.nom }}</span>
              <span class="status-badge" :style="pontBadgeStyle(pont)">{{ pontBadgeLabel(pont) }}</span>
            </div>

            <div style="display:flex;gap:8px;flex-wrap:wrap;font-size:11px;color:#9CA3AF;margin-bottom:10px;">
              <span>Type {{ (pont.type_pont || 'atelier').toString().toUpperCase() }}</span>
              <span>•</span>
              <span>{{ pont.capacite_kg ? `${pont.capacite_kg} kg` : 'Capacité n.c.' }}</span>
            </div>

            <div class="pont-card-body">
              <div style="padding:10px;border-radius:10px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);margin-bottom:10px;display:grid;gap:8px;">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;">
                  <div style="font-size:11px;font-weight:700;color:#9CA3AF;text-transform:uppercase;">Configuration pont</div>
                  <button
                    class="btn btn-ghost"
                    style="padding:5px 10px;min-height:32px;"
                    :disabled="pontSettingSaving[pont.id]"
                    @click="togglePontActivation(pont)"
                  >
                    {{ pontSettings[pont.id]?.is_active ? 'Désactiver' : 'Activer' }}
                  </button>
                </div>

                <div>
                  <div style="font-size:11px;color:#9CA3AF;margin-bottom:4px;">Mécanicien rattaché</div>
                  <select v-model="pontSettings[pont.id].mecanicien_id" class="form-input" style="min-height:38px;">
                    <option :value="null">Aucun</option>
                    <option v-for="m in activeMecaniciens" :key="`pont-meca-${pont.id}-${m.id}`" :value="m.id">{{ m.prenom }} {{ m.nom }}</option>
                  </select>
                </div>

                <div style="display:flex;justify-content:flex-end;">
                  <button class="btn btn-primary" style="padding:6px 12px;min-height:34px;" :disabled="pontSettingSaving[pont.id]" @click="savePontSettings(pont)">
                    {{ pontSettingSaving[pont.id] ? 'Enregistrement…' : 'Enregistrer l’affectation' }}
                  </button>
                </div>
              </div>

              <div style="padding:8px 10px;border-radius:8px;background:rgba(255,210,0,0.06);border:1px solid rgba(255,210,0,0.14);margin-bottom:10px;font-size:12px;color:#E8E9ED;">
                👤 {{ pont.assigned_meca ? `${pont.assigned_meca.prenom ?? ''} ${pont.assigned_meca.nom ?? ''}`.trim() : 'Aucun mécanicien assigné' }}
              </div>

              <div v-if="pont.current_rdv" style="margin-bottom:10px;">
                <p style="font-weight:700;color:#E8E9ED;font-size:14px;margin:0 0 4px 0;">{{ rdvClientName(pont.current_rdv) }}</p>
                <p style="color:#9CA3AF;font-size:12px;margin:0 0 2px 0;">{{ rdvVehicleLabel(pont.current_rdv) }}</p>
                <p style="color:#9CA3AF;font-size:12px;margin:0;">Intervention en cours · {{ pont.current_rdv.type_intervention || 'atelier' }}</p>
                <div style="margin-top:6px;"><StatusBadge :status="pont.current_rdv.status ?? pont.current_rdv.statut" /></div>
                <NuxtLink :to="`/planning?openRdv=${pont.current_rdv.id}`" style="display:inline-block;margin-top:8px;color:#FFD200;font-size:12px;font-weight:600;text-decoration:none;">Ouvrir le RDV →</NuxtLink>
                <div v-if="pont.current_rdv.temps_estime" style="margin-top:8px;">
                  <div style="background:var(--dark3,#171B24);border-radius:6px;height:6px;overflow:hidden;">
                    <div :style="{ width: Math.min(pontProgress(pont), 100) + '%', height: '100%', background: pontProgress(pont) > 100 ? '#EF4444' : '#FFD200', borderRadius: '6px' }"></div>
                  </div>
                  <div style="font-size:10px;color:#9CA3AF;margin-top:2px;">{{ pontProgress(pont) }}% · {{ formatDuration(pont.current_rdv.temps_estime) }} estimées</div>
                </div>
              </div>

              <div v-else-if="pont.next_rdv" style="margin-bottom:10px;">
                <p style="font-weight:700;color:#E8E9ED;font-size:14px;margin:0 0 4px 0;">Prochain passage à {{ formatHourLabel(pont.next_rdv.heure_rdv) }}</p>
                <p style="color:#9CA3AF;font-size:12px;margin:0 0 2px 0;">{{ rdvClientName(pont.next_rdv) }}</p>
                <p style="color:#9CA3AF;font-size:12px;margin:0;">{{ pont.next_rdv.type_intervention || 'atelier' }} · {{ rdvVehicleLabel(pont.next_rdv) }}</p>
              </div>

              <div v-else style="margin-bottom:10px;">
                <p style="color:#9CA3AF;font-size:13px;margin:0;">{{ isActiveFlag(pont.is_active ?? pont.est_actif) ? 'Aucun RDV planifié aujourd’hui sur ce pont' : 'Pont désactivé pour le moment' }}</p>
              </div>

              <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin-bottom:10px;">
                <div style="padding:8px;border-radius:8px;background:rgba(17,24,39,0.5);">
                  <div style="font-size:10px;color:#6B7280;text-transform:uppercase;">RDV jour</div>
                  <div style="font-size:16px;font-weight:700;color:#E8E9ED;">{{ pont.total_rdvs_today }}</div>
                </div>
                <div style="padding:8px;border-radius:8px;background:rgba(17,24,39,0.5);">
                  <div style="font-size:10px;color:#6B7280;text-transform:uppercase;">Charge</div>
                  <div style="font-size:16px;font-weight:700;color:#E8E9ED;">{{ pont.planned_minutes }} min</div>
                </div>
                <div style="padding:8px;border-radius:8px;background:rgba(17,24,39,0.5);">
                  <div style="font-size:10px;color:#6B7280;text-transform:uppercase;">File</div>
                  <div style="font-size:16px;font-weight:700;color:#E8E9ED;">{{ pont.next_count ?? 0 }}</div>
                </div>
              </div>

              <div v-if="pont.day_schedule.length" style="padding-top:8px;border-top:1px solid rgba(107,114,128,0.2);">
                <div style="font-size:11px;color:#9CA3AF;font-weight:700;margin-bottom:6px;">Planning du jour</div>
                <div v-for="rdv in pont.day_schedule.slice(0, 3)" :key="rdv.id" style="display:flex;justify-content:space-between;gap:8px;font-size:12px;margin-bottom:4px;">
                  <span style="color:#FFD200;min-width:42px;">{{ formatHourLabel(rdv.heure_rdv) }}</span>
                  <span style="flex:1;color:#E5E7EB;">{{ rdvClientName(rdv) }}</span>
                  <span style="color:#9CA3AF;white-space:nowrap;">{{ rdv.type_intervention || 'atelier' }}</span>
                </div>
              </div>

              <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;">
                <button
                  v-if="getPontQuickAction(pont)?.transition"
                  class="btn btn-primary"
                  style="flex:1;min-width:140px;"
                  :disabled="actioningByPont[pont.id] === getPontQuickAction(pont)?.transition"
                  @click="runPontQuickAction(pont)"
                >
                  {{ actioningByPont[pont.id] === getPontQuickAction(pont)?.transition ? 'Traitement…' : getPontQuickAction(pont)?.label }}
                </button>
                <NuxtLink
                  v-else-if="getPontQuickAction(pont)?.to"
                  :to="getPontQuickAction(pont)!.to"
                  class="btn btn-primary"
                  style="flex:1;min-width:140px;text-decoration:none;text-align:center;"
                >
                  {{ getPontQuickAction(pont)?.label }}
                </NuxtLink>
              </div>
            </div>

            <div class="pont-card-footer">
              {{ pont.total_rdvs_today ? `${pont.total_rdvs_today} RDV planifiés aujourd’hui` : 'Pont libre aujourd’hui' }}
            </div>
          </div>
        </div>
        <AppEmptyState
          v-else
          icon="🔧"
          title="Aucun pont visible"
          description="Aucun pont n’est remonté pour l’atelier actif. Vérifie la configuration atelier ou recharge la page."
        />
      </div>

      <!-- MECAS TAB -->
      <div v-if="activeTab === 'mecas'" class="meca-grid">
        <div v-for="m in enrichedMecas" :key="m.id" class="meca-card">
          <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
            <div style="width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;" :style="{ background: m.statusColor + '15', border: '1px solid ' + m.statusColor + '30', color: m.statusColor }">
              {{ (m.prenom?.[0] ?? '') + (m.nom?.[0] ?? '') }}
            </div>
            <div style="flex:1;">
              <div style="display:flex;align-items:center;gap:8px;">
                <span style="font-weight:700;color:#E8E9ED;">{{ m.prenom }} {{ m.nom }}</span>
                <span style="font-size:10px;font-weight:600;padding:2px 8px;border-radius:9999px;" :style="{ background: m.statusColor + '20', color: m.statusColor }">{{ m.statusLabel }}</span>
              </div>
              <div style="font-size:12px;color:#6B7280;">{{ m.specialite ?? 'Mécanicien' }}</div>
            </div>
          </div>
          <!-- Specialties -->
          <div v-if="m.specialites?.length" style="display:flex;flex-wrap:wrap;gap:4px;margin-bottom:10px;">
            <span v-for="s in m.specialites" :key="s" style="font-size:10px;padding:2px 8px;border-radius:9999px;background:rgba(139,92,246,0.12);color:#C4B5FD;">{{ s }}</span>
          </div>
          <!-- Current intervention -->
          <div v-if="m.currentRdv" style="padding:8px 10px;border-radius:8px;background:rgba(245,158,11,0.06);border:1px solid rgba(245,158,11,0.15);font-size:12px;margin-bottom:10px;">
            <div style="color:#F59E0B;font-weight:600;margin-bottom:4px;">🔧 En intervention</div>
            <div style="color:#D1D5DB;">{{ m.currentRdv.client_nom ?? 'Client' }} — {{ m.currentRdv.vehicule_info ?? m.currentRdv.type_intervention }}</div>
            <div v-if="m.currentRdv.temps_estime" style="margin-top:6px;">
              <div style="background:var(--dark3,#171B24);border-radius:6px;height:6px;overflow:hidden;">
                <div :style="{ width: Math.min(m.progressPct, 100) + '%', height: '100%', background: m.progressPct > 100 ? '#EF4444' : '#FFD200', borderRadius: '6px' }"></div>
              </div>
              <div style="font-size:10px;color:#9CA3AF;margin-top:2px;">{{ m.progressPct }}%</div>
            </div>
          </div>
          <div style="display:flex;justify-content:space-between;font-size:12px;color:#9CA3AF;">
            <span>📧 {{ m.email ?? '–' }}</span>
            <span>{{ m.rdvCount }} RDV aujourd'hui</span>
          </div>
        </div>
        <AppEmptyState
          v-if="!mecaniciens.length"
          icon="👤"
          title="Aucun mécanicien configuré"
          description="Ajoute un mécanicien depuis l’administration pour alimenter cette vue."
        />
      </div>

      <!-- ALERTES TAB -->
      <div v-if="activeTab === 'alertes'">
        <AppEmptyState
          v-if="alertesTotalCount === 0"
          icon="✅"
          title="Aucune alerte"
          description="Pas de dépassement, ni de no-show, ni de demande en attente sur l'atelier en ce moment."
        />

        <div v-else style="display:flex;flex-direction:column;gap:20px;">

          <!-- Dépassements -->
          <div v-if="alertesDepassements.length">
            <div style="font-size:11px;font-weight:700;color:#FCA5A5;text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px;">🔴 Dépassements en cours ({{ alertesDepassements.length }})</div>
            <div style="display:flex;flex-direction:column;gap:8px;">
              <div
                v-for="rdv in alertesDepassements"
                :key="`dep-${rdv.id}`"
                style="padding:12px 16px;border-radius:10px;background:rgba(239,68,68,0.07);border:1px solid rgba(239,68,68,0.2);display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;"
              >
                <div>
                  <div style="font-size:13px;font-weight:700;color:#E8E9ED;">{{ rdvClientName(rdv) }}</div>
                  <div style="font-size:12px;color:#9CA3AF;">{{ rdvVehicleLabel(rdv) }} — {{ rdv.type_intervention || 'atelier' }}</div>
                  <div style="font-size:11px;color:#FCA5A5;margin-top:2px;">+{{ formatDuration(rdvOverrunMinutes(rdv)) }} de dépassement</div>
                </div>
                <NuxtLink :to="`/planning?openRdv=${rdv.id}`" class="btn btn-ghost" style="font-size:12px;text-decoration:none;">Voir →</NuxtLink>
              </div>
            </div>
          </div>

          <!-- No-show du jour -->
          <div v-if="alertesNoShow.length">
            <div style="font-size:11px;font-weight:700;color:#FDBA74;text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px;">🟠 No-show du jour ({{ alertesNoShow.length }})</div>
            <div style="display:flex;flex-direction:column;gap:8px;">
              <div
                v-for="rdv in alertesNoShow"
                :key="`ns-${rdv.id}`"
                style="padding:12px 16px;border-radius:10px;background:rgba(245,158,11,0.06);border:1px solid rgba(245,158,11,0.18);display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;"
              >
                <div>
                  <div style="font-size:13px;font-weight:700;color:#E8E9ED;">{{ rdvClientName(rdv) }}</div>
                  <div style="font-size:12px;color:#9CA3AF;">{{ rdvVehicleLabel(rdv) }} — {{ formatHourLabel(rdv.heure_rdv) }}</div>
                </div>
                <NuxtLink :to="`/planning?openRdv=${rdv.id}`" class="btn btn-ghost" style="font-size:12px;text-decoration:none;">Ouvrir →</NuxtLink>
              </div>
            </div>
          </div>

          <!-- Retards réception -->
          <div v-if="alertesRetards.length">
            <div style="font-size:11px;font-weight:700;color:#FCD34D;text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px;">🟡 Retards de réception ({{ alertesRetards.length }})</div>
            <div style="display:flex;flex-direction:column;gap:8px;">
              <div
                v-for="rdv in alertesRetards"
                :key="`ret-${rdv.id}`"
                style="padding:12px 16px;border-radius:10px;background:rgba(250,204,21,0.05);border:1px solid rgba(250,204,21,0.15);display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;"
              >
                <div>
                  <div style="font-size:13px;font-weight:700;color:#E8E9ED;">{{ rdvClientName(rdv) }}</div>
                  <div style="font-size:12px;color:#9CA3AF;">{{ rdvVehicleLabel(rdv) }} — prévu à {{ formatHourLabel(rdv.heure_rdv) }}</div>
                  <div style="font-size:11px;color:#FCD34D;margin-top:2px;">+{{ formatDuration(rdvRetardMinutes(rdv)) }} sans réception</div>
                </div>
                <NuxtLink :to="`/planning?openRdv=${rdv.id}`" class="btn btn-ghost" style="font-size:12px;text-decoration:none;">Réceptionner →</NuxtLink>
              </div>
            </div>
          </div>

          <!-- Demandes travaux supp en attente -->
          <div v-if="demandesSupp.length">
            <div style="font-size:11px;font-weight:700;color:#C4B5FD;text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px;">🟣 Demandes travaux complémentaires ({{ demandesSupp.length }})</div>
            <div style="display:flex;flex-direction:column;gap:8px;">
              <div
                v-for="d in demandesSupp"
                :key="`ds-${d.id}`"
                style="padding:12px 16px;border-radius:10px;background:rgba(139,92,246,0.06);border:1px solid rgba(139,92,246,0.18);display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;"
              >
                <div style="flex:1;">
                  <div style="font-size:13px;font-weight:700;color:#E8E9ED;margin-bottom:2px;">
                    {{ d.rendezVous ? rdvClientName(d.rendezVous) : 'Client' }}
                  </div>
                  <div style="font-size:12px;color:#9CA3AF;margin-bottom:4px;">{{ d.description || 'Aucune description' }}</div>
                  <div style="font-size:10px;color:#6B7280;">Créée le {{ formatDateCourt(d.createdAt) }}</div>
                </div>
                <div style="display:flex;flex-direction:column;gap:6px;align-items:flex-end;">
                  <span style="font-size:10px;font-weight:700;padding:3px 10px;border-radius:9999px;background:rgba(139,92,246,0.18);color:#C4B5FD;">{{ d.statut }}</span>
                  <NuxtLink
                    v-if="d.rendezVous?.id"
                    :to="`/planning?openRdv=${d.rendezVous.id}`"
                    class="btn btn-ghost"
                    style="font-size:12px;text-decoration:none;"
                  >Voir le RDV →</NuxtLink>
                </div>
              </div>
            </div>
          </div>

          <!-- OR en attente de signature -->
          <div v-if="orAttente.length">
            <div style="font-size:11px;font-weight:700;color:#5EEAD4;text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px;">🔵 OR en attente de signature ({{ orAttente.length }})</div>
            <div style="display:flex;flex-direction:column;gap:8px;">
              <div
                v-for="or in orAttente"
                :key="`or-${or.id}`"
                style="padding:12px 16px;border-radius:10px;background:rgba(20,184,166,0.05);border:1px solid rgba(20,184,166,0.18);display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;"
              >
                <div>
                  <div style="font-size:13px;font-weight:700;color:#E8E9ED;">{{ or.snapClientNom ?? or.snap_client_nom ?? 'Client' }} {{ or.snapClientPrenom ?? or.snap_client_prenom ?? '' }}</div>
                  <div style="font-size:12px;color:#9CA3AF;">OR {{ or.numeroOr ?? or.numero_or }} — {{ or.snapVehiculeMarque ?? or.snap_vehicule_marque ?? '' }} {{ or.snapVehiculeModele ?? or.snap_vehicule_modele ?? '' }}</div>
                  <div v-if="or.motifRectification ?? or.motif_rectification" style="font-size:11px;color:#5EEAD4;margin-top:2px;">Motif : {{ or.motifRectification ?? or.motif_rectification }}</div>
                </div>
                <NuxtLink :to="`/ordres/${or.id}`" class="btn btn-ghost" style="font-size:12px;text-decoration:none;">Ouvrir l'OR →</NuxtLink>
              </div>
            </div>
          </div>

        </div>
      </div>

      <!-- GARDIENNAGE TAB -->
      <div v-if="activeTab === 'gardiennage'">
        <!-- Résumé -->
        <div v-if="gardinnageRdvs.length" style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:14px;">
          <div style="padding:12px 18px;border-radius:10px;background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.18);">
            <div style="font-size:11px;color:#9CA3AF;text-transform:uppercase;font-weight:600;letter-spacing:.04em;">Motos en gardiennage</div>
            <div style="font-size:22px;font-weight:700;color:#FCA5A5;">{{ gardinnageRdvs.length }}</div>
          </div>
          <div style="padding:12px 18px;border-radius:10px;background:rgba(245,158,11,0.06);border:1px solid rgba(245,158,11,0.15);">
            <div style="font-size:11px;color:#9CA3AF;text-transform:uppercase;font-weight:600;letter-spacing:.04em;">Montant total estimé</div>
            <div style="font-size:22px;font-weight:700;color:#FCD34D;">{{ gardiennageTotalEstime }}€</div>
          </div>
          <div v-if="gardinnageUrgents > 0" style="padding:12px 18px;border-radius:10px;background:rgba(239,68,68,0.15);border:1px solid rgba(239,68,68,0.3);">
            <div style="font-size:11px;color:#FCA5A5;text-transform:uppercase;font-weight:600;letter-spacing:.04em;">⚠ Actions requises</div>
            <div style="font-size:22px;font-weight:700;color:#FCA5A5;">{{ gardinnageUrgents }}</div>
          </div>
        </div>

        <AppEmptyState
          v-if="!gardinnageRdvs.length"
          icon="🅿"
          title="Aucun véhicule en gardiennage"
          description="Aucune moto n'est actuellement en gardiennage. Les véhicules non-récupérés après réparation apparaîtront ici."
        />

        <div v-else style="display:flex;flex-direction:column;gap:10px;">
          <div
            v-for="rdv in gardinnageSorted"
            :key="rdv.id"
            style="border-radius:12px;padding:16px;border:1px solid rgba(255,255,255,0.07);"
            :style="{ background: gardinnagePhase(rdv).bg }"
          >
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;">
              <!-- Infos principales -->
              <div style="flex:1;min-width:240px;">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                  <span
                    style="font-size:10px;font-weight:700;padding:3px 10px;border-radius:9999px;"
                    :style="{ background: gardinnagePhase(rdv).badgeBg, color: gardinnagePhase(rdv).badgeColor }"
                  >
                    {{ gardinnagePhase(rdv).label }}
                  </span>
                  <span style="font-size:12px;font-weight:700;color:#E8E9ED;">
                    J+{{ joursCalendaires(rdv.gardiennageDebutAt) }}
                  </span>
                  <span style="font-size:11px;color:#6B7280;">depuis le {{ formatDateCourt(rdv.gardiennageDebutAt) }}</span>
                </div>

                <div style="font-size:14px;font-weight:700;color:#E8E9ED;margin-bottom:2px;">
                  {{ rdvClientName(rdv) }}
                </div>
                <div style="font-size:12px;color:#9CA3AF;margin-bottom:2px;">
                  {{ rdvVehicleLabel(rdv) }}
                  <span v-if="rdv.vehicule?.plaque || rdv.vehicule_plaque" style="margin-left:6px;font-family:monospace;color:#FFD200;font-size:11px;">{{ rdv.vehicule?.plaque ?? rdv.vehicule_plaque }}</span>
                </div>
                <div v-if="rdv.gardiennageMotif" style="font-size:11px;color:#6B7280;margin-top:4px;font-style:italic;">{{ rdv.gardiennageMotif }}</div>
              </div>

              <!-- Montant estimé -->
              <div style="text-align:right;">
                <div style="font-size:10px;color:#9CA3AF;text-transform:uppercase;font-weight:600;margin-bottom:2px;">Montant estimé</div>
                <div style="font-size:18px;font-weight:700;color:#FCD34D;">{{ gardinnageMontantEstime(rdv) }}€</div>
                <div style="font-size:10px;color:#6B7280;">{{ joursCalendaires(rdv.gardiennageDebutAt) }}j × {{ workshopConfig?.tarifGardiennageJournalier ?? '5.00' }}€/j</div>
              </div>
            </div>

            <!-- Actions -->
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;padding-top:10px;border-top:1px solid rgba(255,255,255,0.06);">
              <NuxtLink
                :to="`/planning?openRdv=${rdv.id}`"
                class="btn btn-ghost"
                style="font-size:12px;text-decoration:none;"
              >
                Voir le dossier →
              </NuxtLink>
              <button
                class="btn btn-primary"
                style="font-size:12px;"
                :disabled="gardinnageActioning[rdv.id]"
                @click="sortirGardiennage(rdv)"
              >
                {{ gardinnageActioning[rdv.id] ? 'Traitement…' : '↩ Sortir du gardiennage' }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- ABSENCES TAB -->
      <div v-if="activeTab === 'absences'">
        <UCard>
          <UTable v-if="absences.length" :data="absences" :columns="absenceCols" />
          <AppEmptyState
            v-else
            icon="📅"
            title="Aucune absence enregistrée"
            description="L’équipe est complète sur la période affichée."
          />
        </UCard>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
const api = useApi()
const toast = useToast()
const { formatDuration } = useFormat()
const route = useRoute()
const router = useRouter()
const loading = ref(true)
const refreshing = ref(false)
const errorMessage = ref('')
const partialIssues = ref<string[]>([])
const validTabs = ['ponts', 'mecas', 'alertes', 'gardiennage', 'absences']
const activeTab = ref('ponts')
const ponts = ref<any[]>([])
const mecaniciens = ref<any[]>([])
const absences = ref<any[]>([])
const rdvs = ref<any[]>([])
const lastUpdatedAt = ref('')
const actioningByPont = reactive<Record<number, string>>({})
const pontSettings = reactive<Record<number, { mecanicien_id: number | null; is_active: boolean }>>({})
const pontSettingSaving = reactive<Record<number, boolean>>({})
const gardinnageRdvs = ref<any[]>([])
const workshopConfig = ref<any>(null)
const gardinnageActioning = reactive<Record<number, boolean>>({})
const demandesSupp = ref<any[]>([])
const orAttente = ref<any[]>([])

const absenceCols = [
  { key: 'mecanicien_nom', label: 'Mécanicien' },
  { key: 'date_debut', label: 'Début' },
  { key: 'date_fin', label: 'Fin' },
  { key: 'motif', label: 'Motif' },
]

function isActiveFlag(value: any): boolean {
  return value !== false && Number(value ?? 1) !== 0
}

function extractDateKey(value: any): string {
  if (!value) return ''
  if (typeof value === 'string') return value.slice(0, 10)
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? '' : date.toISOString().slice(0, 10)
}

function formatHourLabel(value: any): string {
  if (!value) return '--:--'
  if (typeof value === 'string') {
    const match = value.match(/(\d{2}):(\d{2})/)
    if (match) return `${match[1]}:${match[2]}`
  }
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? '--:--' : date.toISOString().slice(11, 16)
}

function getRdvStatus(rdv: any): string {
  return String(rdv?.status ?? rdv?.statut ?? '').toLowerCase()
}

function isFinalStatus(status: string): boolean {
  return ['termine', 'restitue', 'annule', 'facture', 'paye'].includes(status)
}

function rdvClientName(rdv: any): string {
  if (rdv?.client_nom) return rdv.client_nom
  const prenom = rdv?.client?.prenom ?? ''
  const nom = rdv?.client?.nom ?? ''
  return `${prenom} ${nom}`.trim() || 'Client non renseigné'
}

function rdvVehicleLabel(rdv: any): string {
  if (rdv?.vehicule_info) return rdv.vehicule_info
  const parts = [rdv?.vehicule?.marque, rdv?.vehicule?.modele, rdv?.vehicule_plaque ?? rdv?.vehicule?.plaque].filter(Boolean)
  return parts.join(' • ') || 'Véhicule non renseigné'
}

function asId(value: any): number | null {
  if (typeof value === 'number' && Number.isFinite(value)) return value
  if (typeof value === 'string') {
    const parsed = Number(value.split('/').pop())
    return Number.isFinite(parsed) && parsed > 0 ? parsed : null
  }
  if (value && typeof value === 'object') {
    return asId(value.id ?? value['@id'])
  }
  return null
}

function normalizeSpecialites(value: any): string[] {
  if (Array.isArray(value)) return value.filter(Boolean)
  if (typeof value === 'string') {
    return value.split(/[;,]/).map((item: string) => item.trim()).filter(Boolean)
  }
  return []
}

const activeMecaniciens = computed(() => {
  return mecaniciens.value
    .filter((m: any) => isActiveFlag(m.is_active ?? m.isActive))
    .sort((a: any, b: any) => `${a.prenom ?? ''} ${a.nom ?? ''}`.localeCompare(`${b.prenom ?? ''} ${b.nom ?? ''}`))
})

function syncPontSettings() {
  for (const pont of ponts.value) {
    pontSettings[pont.id] = {
      mecanicien_id: asId(pont?.mecanicien?.id ?? pont?.mecanicien_id),
      is_active: isActiveFlag(pont?.is_active ?? pont?.est_actif),
    }
  }
}

async function savePontSettings(pont: any) {
  if (!pont?.id) return
  const settings = pontSettings[pont.id] ?? {
    mecanicien_id: asId(pont?.mecanicien?.id ?? pont?.mecanicien_id),
    is_active: isActiveFlag(pont?.is_active ?? pont?.est_actif),
  }

  pontSettingSaving[pont.id] = true
  try {
    const mecanicienId = asId(settings.mecanicien_id)
    await api.patch(`/ponts/${pont.id}`, {
      mecanicien_id: mecanicienId,
      mecanicien: mecanicienId ? `/api/mecaniciens/${mecanicienId}` : null,
      est_actif: settings.is_active,
      is_active: settings.is_active ? 1 : 0,
    })
    toast.add({ title: 'Pont mis à jour', color: 'success' })
    await loadWorkshop()
  } catch (e: any) {
    toast.add({ title: 'Mise à jour impossible', description: e?.message || 'Vérifie les droits admin de l’atelier.', color: 'error' })
  } finally {
    pontSettingSaving[pont.id] = false
  }
}

async function togglePontActivation(pont: any) {
  const current = pontSettings[pont.id]
  if (!current) return
  current.is_active = !current.is_active
  await savePontSettings(pont)
}

function countConflicts(todayRdvs: any[]): number {
  const seen = new Map<string, number>()
  let conflicts = 0

  for (const rdv of todayRdvs) {
    const hour = formatHourLabel(rdv?.heure_rdv)
    const pontId = rdv?.pont?.id ?? rdv?.pont_id
    const mecaId = rdv?.mecanicien?.id ?? rdv?.mecanicien_id
    const keys = [pontId ? `pont:${pontId}:${hour}` : '', mecaId ? `meca:${mecaId}:${hour}` : ''].filter(Boolean)

    for (const key of keys) {
      const next = (seen.get(key) ?? 0) + 1
      seen.set(key, next)
      if (next === 2) conflicts += 1
    }
  }

  return conflicts
}

const enrichedPonts = computed(() => {
  const today = new Date().toISOString().slice(0, 10)
  const todayRdvs = rdvs.value
    .filter((r: any) => extractDateKey(r?.date_rdv) === today && !isFinalStatus(getRdvStatus(r)))
    .sort((a: any, b: any) => formatHourLabel(a?.heure_rdv).localeCompare(formatHourLabel(b?.heure_rdv)))

  return ponts.value.map((pont: any) => {
    const daySchedule = todayRdvs.filter((r: any) => (r?.pont?.id ?? r?.pont_id) === pont.id)
    const currentFromPlanning = daySchedule.find((r: any) => ['en_cours', 'reception'].includes(getRdvStatus(r)))
    const currentRdv = pont.current_rdv ?? currentFromPlanning ?? null
    const nextRdv = daySchedule.find((r: any) => r.id !== currentRdv?.id) ?? null
    const plannedMinutes = daySchedule.reduce((sum: number, r: any) => sum + Number(r?.temps_estime ?? 60), 0)
    const assignedMecaId = pont?.mecanicien?.id ?? pont?.mecanicien_id ?? null
    const assignedMeca = pont?.mecanicien ?? mecaniciens.value.find((m: any) => m.id === assignedMecaId) ?? null

    return {
      ...pont,
      current_rdv: currentRdv,
      next_rdv: nextRdv,
      day_schedule: daySchedule,
      total_rdvs_today: daySchedule.length,
      planned_minutes: plannedMinutes,
      assigned_meca: assignedMeca,
    }
  })
})

const kpis = computed(() => {
  const total = enrichedPonts.value.filter((p: any) => isActiveFlag(p.is_active ?? p.est_actif)).length
  const occupied = enrichedPonts.value.filter((p: any) => isActiveFlag(p.is_active ?? p.est_actif) && (p.current_rdv || p.day_schedule.length)).length
  const occupation = total ? Math.round(occupied / total * 100) : 0
  const today = new Date().toISOString().slice(0, 10)
  const todayRdvs = rdvs.value.filter((r: any) => extractDateKey(r?.date_rdv) === today && !isFinalStatus(getRdvStatus(r)))
  const absentIds = new Set(absences.value.filter((a: any) => {
    const start = extractDateKey(a?.date_debut)
    const end = extractDateKey(a?.date_fin)
    return start <= today && end >= today
  }).map((a: any) => a.mecanicien?.id ?? a.mecanicien_id))
  const activeMecas = mecaniciens.value.filter((m: any) => isActiveFlag(m.is_active ?? m.isActive) && !absentIds.has(m.id)).length

  return {
    occupation,
    rdvsToday: todayRdvs.length,
    activeMecas,
    conflicts: countConflicts(todayRdvs),
  }
})

const enrichedMecas = computed(() => {
  const today = new Date().toISOString().slice(0, 10)
  const todayRdvs = rdvs.value.filter((r: any) => extractDateKey(r?.date_rdv) === today && !isFinalStatus(getRdvStatus(r)))
  const absentIds = new Set(absences.value.filter((a: any) => {
    const start = extractDateKey(a?.date_debut)
    const end = extractDateKey(a?.date_fin)
    return start <= today && end >= today
  }).map((a: any) => a.mecanicien?.id ?? a.mecanicien_id))

  return mecaniciens.value.map((m: any) => {
    const mecaRdvs = todayRdvs.filter((r: any) => {
      const mid = r.mecanicien?.id ?? r.mecanicien_id
      return mid === m.id
    })
    const currentRdv = mecaRdvs.find((r: any) => ['en_cours', 'reception'].includes(getRdvStatus(r)))
    const isAbsent = absentIds.has(m.id)
    const isWorking = !!currentRdv
    let progressPct = 0
    if (currentRdv?.temps_estime && (currentRdv.heure_debut_travaux || currentRdv.started_at)) {
      const started = new Date(currentRdv.heure_debut_travaux || currentRdv.started_at)
      if (!isNaN(started.getTime())) {
        progressPct = Math.round((Date.now() - started.getTime()) / 60000 / currentRdv.temps_estime * 100)
      }
    }
    return {
      ...m,
      rdvCount: mecaRdvs.length,
      currentRdv,
      progressPct,
      statusLabel: isAbsent ? 'Absent' : isWorking ? 'En intervention' : 'Disponible',
      statusColor: isAbsent ? '#EF4444' : isWorking ? '#F59E0B' : '#22C55E',
      specialites: normalizeSpecialites(m.specialites ?? m.competences),
    }
  })
})

function pontBadgeLabel(pont: any): string {
  if (!isActiveFlag(pont.is_active ?? pont.est_actif)) return 'Hors service'
  if (pont.current_rdv) return 'En cours'
  if (pont.day_schedule?.length) return 'Planifié'
  return 'Disponible'
}

function pontBadgeStyle(pont: any) {
  if (!isActiveFlag(pont.is_active ?? pont.est_actif)) {
    return { background: 'rgba(239,68,68,0.12)', color: '#FCA5A5' }
  }
  if (pont.current_rdv) {
    return { background: 'rgba(20,184,166,0.12)', color: '#5EEAD4' }
  }
  if (pont.day_schedule?.length) {
    return { background: 'rgba(245,158,11,0.12)', color: '#FCD34D' }
  }
  return { background: 'rgba(34,197,94,0.12)', color: '#86EFAC' }
}

function pontProgress(pont: any) {
  const rdv = pont.current_rdv
  if (!rdv?.temps_estime) return 0
  if (typeof rdv?.temps_ecoule_minutes === 'number') {
    return Math.round(rdv.temps_ecoule_minutes / rdv.temps_estime * 100)
  }
  const started = rdv.heure_debut_travaux || rdv.started_at
  if (!started) return 0
  const startTime = new Date(started)
  if (isNaN(startTime.getTime())) return 0
  return Math.round((Date.now() - startTime.getTime()) / 60000 / rdv.temps_estime * 100)
}

function normalizeCollection(payload: any) {
  return Array.isArray(payload) ? payload : (payload?.['hydra:member'] ?? payload?.member ?? [])
}

function normalizePont(item: any) {
  return {
    ...item,
    is_active: item?.is_active ?? item?.est_actif ?? item?.isActive ?? 1,
    current_rdv: item?.current_rdv ?? null,
    next_count: Number(item?.next_count ?? 0),
  }
}

function addMinutesToTime(time: string, minutesToAdd: number): string {
  const [hours, minutes] = String(time || '09:00').split(':').map(Number)
  const total = ((hours || 0) * 60) + (minutes || 0) + Math.max(15, Number(minutesToAdd || 0))
  return `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`
}

function buildPlanningCreateLink(pont?: any): string {
  const today = new Date().toISOString().slice(0, 10)
  const suggestedTime = pont?.day_schedule?.length
    ? addMinutesToTime(formatHourLabel(pont.day_schedule[pont.day_schedule.length - 1]?.heure_rdv), Number(pont.day_schedule[pont.day_schedule.length - 1]?.temps_estime ?? 60))
    : '10:00'

  const params = new URLSearchParams({ create: '1', date: today, time: suggestedTime })
  if (pont?.id) params.set('pontId', String(pont.id))
  return `/planning?${params.toString()}`
}

function getPontQuickAction(pont: any): { label: string; transition?: string; to?: string } | null {
  const status = getRdvStatus(pont?.current_rdv)
  if (status === 'reserve' || status === 'confirme') return { label: 'Réceptionner', transition: 'reception' }
  if (status === 'reception') return { label: 'Démarrer', transition: 'start_travail' }
  if (status === 'en_cours') return { label: 'Voir intervention', to: `/planning?openRdv=${pont.current_rdv.id}` }
  if (pont?.next_rdv?.id) return { label: 'Ouvrir prochain', to: `/planning?openRdv=${pont.next_rdv.id}` }
  return { label: '+ Nouveau RDV', to: buildPlanningCreateLink(pont) }
}

async function runPontQuickAction(pont: any) {
  const action = getPontQuickAction(pont)
  if (!action?.transition || !pont?.current_rdv?.id) return

  actioningByPont[pont.id] = action.transition
  try {
    await api.post(`/rendez-vous/${pont.current_rdv.id}/transition/${action.transition}`, {
      pont_id: pont.id,
      mecanicien_id: pont.assigned_meca?.id ?? pont.mecanicien?.id ?? null,
    })
    toast.add({ title: 'Action atelier effectuée', color: 'success' })
    await loadWorkshop()
  } catch (e: any) {
    toast.add({ title: 'Action impossible', description: e?.message || 'Ouvre le dossier atelier pour compléter la transition.', color: 'error' })
  } finally {
    delete actioningByPont[pont.id]
  }
}

async function fetchPontsWithFallback() {
  const statusAttempt = await Promise.allSettled([api.get('/ponts/status')])
  if (statusAttempt[0].status === 'fulfilled') {
    const statusPonts = normalizeCollection(statusAttempt[0].value).map(normalizePont)
    if (statusPonts.length) {
      return statusPonts
    }
  }

  const rawAttempt = await Promise.allSettled([api.get('/ponts')])
  if (rawAttempt[0].status === 'fulfilled') {
    return normalizeCollection(rawAttempt[0].value).map((item: any) => normalizePont(item))
  }

  throw new Error('ponts')
}

async function loadWorkshop() {
  loading.value = true
  errorMessage.value = ''
  partialIssues.value = []

  const [p, m, a, r, g, cfg, ds, ora] = await Promise.allSettled([
    fetchPontsWithFallback(),
    api.get('/mecaniciens'),
    api.get('/absences'),
    api.get('/rendez-vous?itemsPerPage=2000&order[createdAt]=desc'),
    api.get('/rendez-vous?statut=en_gardiennage&itemsPerPage=200'),
    api.get('/config'),
    api.get('/demandes-travaux-supp?statut=en_attente&itemsPerPage=100'),
    api.get('/ordres-reparation?statut=en_attente_signature&itemsPerPage=100'),
  ])

  const issues: string[] = []

  if (p.status === 'fulfilled') {
    ponts.value = Array.isArray(p.value) ? p.value : normalizeCollection(p.value).map(normalizePont)
    if (!ponts.value.length) {
      issues.push('ponts')
    }
  } else {
    ponts.value = []
    issues.push('ponts')
  }

  if (m.status === 'fulfilled') {
    mecaniciens.value = normalizeCollection(m.value)
  } else {
    mecaniciens.value = []
    issues.push('mécaniciens')
  }

  if (a.status === 'fulfilled') {
    const absRaw = normalizeCollection(a.value)
    absences.value = absRaw.map((ab: any) => ({
      ...ab,
      mecanicien_nom: ab.mecanicien ? `${ab.mecanicien.prenom ?? ''} ${ab.mecanicien.nom ?? ''}`.trim() : '–',
    }))
  } else {
    absences.value = []
    issues.push('absences')
  }

  if (r.status === 'fulfilled') {
    rdvs.value = normalizeCollection(r.value)
  } else {
    rdvs.value = []
    issues.push('rendez-vous')
  }

  if (g.status === 'fulfilled') {
    gardinnageRdvs.value = normalizeCollection(g.value)
  } else {
    gardinnageRdvs.value = []
  }

  if (cfg.status === 'fulfilled') {
    workshopConfig.value = cfg.value
  } else {
    workshopConfig.value = null
  }

  if (ds.status === 'fulfilled') {
    demandesSupp.value = normalizeCollection(ds.value)
  } else {
    demandesSupp.value = []
  }

  if (ora.status === 'fulfilled') {
    orAttente.value = normalizeCollection(ora.value)
  } else {
    orAttente.value = []
  }

  if (issues.length === 4) {
    errorMessage.value = 'Aucune donnée atelier n’a pu être chargée. Vérifie la connexion API puis réessaie.'
  } else if (issues.length > 0) {
    partialIssues.value = issues
    toast.add({
      title: 'Chargement partiel',
      description: `Certaines sections sont indisponibles : ${issues.join(', ')}.`,
      color: 'warning',
    })
  }

  syncPontSettings()
  lastUpdatedAt.value = new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
  loading.value = false
}

// ── Alertes ──

const alertesDepassements = computed(() => {
  const today = new Date().toISOString().slice(0, 10)
  return rdvs.value.filter((rdv: any) => {
    if (getRdvStatus(rdv) !== 'en_cours') return false
    if (extractDateKey(rdv?.date_rdv) !== today) return false
    const tempsEstime = Number(rdv?.temps_estime)
    if (!tempsEstime) return false
    const started = rdv.heure_debut_travaux || rdv.started_at
    if (!started) return false
    const startTime = new Date(started)
    if (isNaN(startTime.getTime())) return false
    const elapsedMin = (Date.now() - startTime.getTime()) / 60000
    return elapsedMin > tempsEstime
  })
})

function rdvOverrunMinutes(rdv: any): number {
  const tempsEstime = Number(rdv?.temps_estime ?? 0)
  const started = rdv.heure_debut_travaux || rdv.started_at
  if (!started || !tempsEstime) return 0
  const startTime = new Date(started)
  if (isNaN(startTime.getTime())) return 0
  return Math.round((Date.now() - startTime.getTime()) / 60000 - tempsEstime)
}

const alertesNoShow = computed(() => {
  const today = new Date().toISOString().slice(0, 10)
  return rdvs.value.filter((rdv: any) =>
    getRdvStatus(rdv) === 'no_show' && extractDateKey(rdv?.date_rdv) === today
  )
})

const alertesRetards = computed(() => {
  const today = new Date().toISOString().slice(0, 10)
  const nowMin = new Date().getHours() * 60 + new Date().getMinutes()
  return rdvs.value.filter((rdv: any) => {
    if (getRdvStatus(rdv) !== 'confirme') return false
    if (extractDateKey(rdv?.date_rdv) !== today) return false
    const h = formatHourLabel(rdv?.heure_rdv)
    if (!h || h === '--:--') return false
    const [hh, mm] = h.split(':').map(Number)
    const rdvMin = (hh ?? 0) * 60 + (mm ?? 0)
    return (nowMin - rdvMin) > 30
  })
})

function rdvRetardMinutes(rdv: any): number {
  const h = formatHourLabel(rdv?.heure_rdv)
  if (!h || h === '--:--') return 0
  const [hh, mm] = h.split(':').map(Number)
  const rdvMin = (hh ?? 0) * 60 + (mm ?? 0)
  const nowMin = new Date().getHours() * 60 + new Date().getMinutes()
  return Math.max(0, nowMin - rdvMin)
}

const alertesTotalCount = computed(() =>
  alertesDepassements.value.length +
  alertesNoShow.value.length +
  alertesRetards.value.length +
  demandesSupp.value.length +
  orAttente.value.length
)

// ── Gardiennage ──

function joursCalendaires(debutAt: string | null): number {
  if (!debutAt) return 0
  const debut = new Date(debutAt)
  if (isNaN(debut.getTime())) return 0
  return Math.max(0, Math.floor((Date.now() - debut.getTime()) / 86400000))
}

function formatDateCourt(d: string | null): string {
  if (!d) return ''
  try { return new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' }) } catch { return d }
}

function gardinnagePhase(rdv: any): { label: string; bg: string; badgeBg: string; badgeColor: string; urgence: number } {
  const cfg = workshopConfig.value
  const r1 = Math.round((cfg?.delaiRelance1JoursOuvres ?? 15) * 1.4)
  const r2 = Math.round((cfg?.delaiRelance2JoursOuvres ?? 30) * 1.4)
  const ra = Math.round((cfg?.delaiProposeGardiennageJoursOuvres ?? 45) * 1.4)
  const rp = Math.round((cfg?.delaiProcedureAbandonJoursOuvres ?? 180) * 1.4)
  const jours = joursCalendaires(rdv.gardiennageDebutAt)
  if (jours >= rp) return { label: 'Procédure abandon', bg: 'rgba(127,0,0,0.12)', badgeBg: 'rgba(185,28,28,0.25)', badgeColor: '#FCA5A5', urgence: 4 }
  if (jours >= ra) return { label: 'Abandon possible', bg: 'rgba(239,68,68,0.08)', badgeBg: 'rgba(239,68,68,0.2)', badgeColor: '#FCA5A5', urgence: 3 }
  if (jours >= r2) return { label: 'Relance 2', bg: 'rgba(245,120,15,0.07)', badgeBg: 'rgba(234,88,12,0.2)', badgeColor: '#FDBA74', urgence: 2 }
  if (jours >= r1) return { label: 'Relance 1', bg: 'rgba(245,158,11,0.06)', badgeBg: 'rgba(245,158,11,0.2)', badgeColor: '#FCD34D', urgence: 1 }
  return { label: 'Récent', bg: 'rgba(255,255,255,0.03)', badgeBg: 'rgba(34,197,94,0.15)', badgeColor: '#86EFAC', urgence: 0 }
}

function gardinnageMontantEstime(rdv: any): string {
  const jours = joursCalendaires(rdv.gardiennageDebutAt)
  const tarif = parseFloat(workshopConfig.value?.tarifGardiennageJournalier ?? '5')
  return (jours * tarif).toFixed(2)
}

const gardinnageSorted = computed(() =>
  [...gardinnageRdvs.value].sort((a, b) => {
    const pa = gardinnagePhase(a).urgence
    const pb = gardinnagePhase(b).urgence
    if (pb !== pa) return pb - pa
    return joursCalendaires(b.gardiennageDebutAt) - joursCalendaires(a.gardiennageDebutAt)
  })
)

const gardinnageUrgents = computed(() =>
  gardinnageRdvs.value.filter(r => gardinnagePhase(r).urgence >= 2).length
)

const gardiennageTotalEstime = computed(() =>
  gardinnageRdvs.value.reduce((sum, rdv) => sum + parseFloat(gardinnageMontantEstime(rdv)), 0).toFixed(2)
)

async function sortirGardiennage(rdv: any) {
  if (!rdv?.id) return
  gardinnageActioning[rdv.id] = true
  try {
    await api.post(`/rendez-vous/${rdv.id}/transition/sortir_gardiennage`, {})
    toast.add({ title: 'Véhicule sorti du gardiennage', description: `${rdvClientName(rdv)} — le dossier repasse en cours.`, color: 'success' })
    await loadWorkshop()
  } catch (e: any) {
    toast.add({ title: 'Impossible', description: e?.message || 'Vérifie le statut du RDV dans le planning.', color: 'error' })
  } finally {
    gardinnageActioning[rdv.id] = false
  }
}

async function refreshWorkshop() {
  refreshing.value = true
  try {
    await loadWorkshop()
  } finally {
    refreshing.value = false
  }
}

onMounted(() => {
  const queryTab = typeof route.query.tab === 'string' ? route.query.tab : ''
  if (validTabs.includes(queryTab)) {
    activeTab.value = queryTab
  }
  loadWorkshop()
})

watch(activeTab, (tab) => {
  if (route.query.tab === tab) return
  router.replace({ query: { ...route.query, tab } })
})
</script>
