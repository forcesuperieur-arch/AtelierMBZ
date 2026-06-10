<template>
  <div>
    <div class="page-header">
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
        <div>
          <div class="page-title">Tableau de bord</div>
          <div class="page-sub">{{ todayDate }} · Pilotage opérationnel & prise de décision.</div>
        </div>
        <div style="display:flex;gap:8px;">
          <button class="btn btn-ghost" style="font-size:12px;padding:6px 12px;" @click="customizing = !customizing">
            {{ customizing ? '✓ Terminer' : '⚙ Personnaliser' }}
          </button>
          <div style="position:relative;">
            <button class="btn btn-ghost" style="font-size:12px;padding:6px 12px;" @click="showExportMenu = !showExportMenu">
              📥 Exporter
            </button>
            <div v-if="showExportMenu" style="position:absolute;top:100%;right:0;margin-top:4px;padding:6px;border-radius:10px;background:#1a1d26;border:1px solid rgba(255,255,255,0.08);z-index:50;min-width:160px;">
              <button style="display:block;width:100%;text-align:left;padding:8px 10px;border-radius:6px;background:transparent;color:#E5E7EB;font-size:12px;border:none;cursor:pointer;" @mouseenter="$event.target.style.background='rgba(255,255,255,0.06)'" @mouseleave="$event.target.style.background='transparent'" @click="exportDashboard('pdf')">📄 PDF (rapport visuel)</button>
              <button style="display:block;width:100%;text-align:left;padding:8px 10px;border-radius:6px;background:transparent;color:#E5E7EB;font-size:12px;border:none;cursor:pointer;" @mouseenter="$event.target.style.background='rgba(255,255,255,0.06)'" @mouseleave="$event.target.style.background='transparent'" @click="exportDashboard('excel')">📊 Excel (données brutes)</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Widget toggles -->
    <div v-if="customizing" style="margin-bottom:16px;padding:12px 14px;border-radius:12px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);">
      <div style="font-size:12px;font-weight:700;color:#E8E9ED;margin-bottom:8px;">Afficher / masquer les sections</div>
      <div style="display:flex;flex-wrap:wrap;gap:8px;">
        <label v-for="(label, key) in widgetLabels" :key="key" class="toggle-label" style="padding:6px 10px;border-radius:8px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.06);cursor:pointer;">
          <input v-model="visibleWidgets[key]" type="checkbox" style="accent-color:#FFD200;" />
          <span style="font-size:12px;color:#D1D5DB;">{{ label }}</span>
        </label>
      </div>
    </div>

    <!-- REALTIME BANNER -->
    <div v-if="visibleWidgets.realtime && realtimeData.now" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;margin-bottom:16px;">
      <div class="rt-card" :class="rtOrAlertClass">
        <div class="rt-label">OR EN COURS</div>
        <div class="rt-value">{{ realtimeData.or_en_cours?.length ?? 0 }}</div>
        <div class="rt-sub">{{ rtOrOverrunCount }} dépassement{{ rtOrOverrunCount > 1 ? 's' : '' }}</div>
      </div>
      <div class="rt-card">
        <div class="rt-label">PONTS OCCUPÉS</div>
        <div class="rt-value">{{ rtPontsOccupes }}/{{ realtimeData.ponts?.length ?? 0 }}</div>
        <div class="rt-sub">{{ rtPontsLibres }} libre{{ rtPontsLibres > 1 ? 's' : '' }}</div>
      </div>
      <div class="rt-card" :class="rtRestitutionAlertClass">
        <div class="rt-label">ATTENTE RESTITUTION</div>
        <div class="rt-value">{{ realtimeData.attente_restitution?.length ?? 0 }}</div>
        <div class="rt-sub">{{ rtRestitutionDelayed }} en retard</div>
      </div>
      <div class="rt-card">
        <div class="rt-label">CHARGE JOUR</div>
        <div class="rt-value">{{ formatMinutes(realtimeData.charge_jour?.planned_minutes ?? 0) }}</div>
        <div class="rt-sub">{{ formatMinutes(realtimeData.charge_jour?.actual_minutes ?? 0) }} réalisé · {{ realtimeData.charge_jour?.ratio ?? 0 }}%</div>
      </div>
      <div class="rt-card">
        <div class="rt-label">MÉCANOS ACTIFS</div>
        <div class="rt-value">{{ realtimeData.mecaniciens_actifs?.filter(m => m.nb_interventions > 0).length ?? 0 }}</div>
        <div class="rt-sub">sur {{ realtimeData.mecaniciens_actifs?.length ?? 0 }}</div>
      </div>
    </div>

    <!-- Period filters -->
    <UCard style="margin-bottom:16px;">
      <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;">
        <div style="display:flex;flex-wrap:wrap;gap:8px;">
          <button v-for="preset in periodPresets" :key="preset.key" type="button" class="topbar-btn" :style="{ background: selectedPeriod === preset.key ? 'rgba(255,210,0,0.14)' : 'rgba(255,255,255,0.04)', color: selectedPeriod === preset.key ? '#FFD200' : '#D1D5DB', borderColor: selectedPeriod === preset.key ? 'rgba(255,210,0,0.3)' : 'rgba(255,255,255,0.08)' }" @click="applyPreset(preset.key)">
            {{ preset.label }}
          </button>
        </div>
        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px;">
          <input v-model="filters.from" type="date" style="padding:8px 10px;border-radius:10px;border:1px solid rgba(255,255,255,0.08);background:rgba(255,255,255,0.04);color:#E5E7EB;" />
          <input v-model="filters.to" type="date" style="padding:8px 10px;border-radius:10px;border:1px solid rgba(255,255,255,0.08);background:rgba(255,255,255,0.04);color:#E5E7EB;" />
          <button type="button" class="topbar-new-btn" style="font-size:12px;padding:8px 12px;" @click="loadDashboard">Actualiser</button>
        </div>
      </div>
      <div style="margin-top:8px;font-size:12px;color:#9CA3AF;">
        Période analysée : {{ periodSummary }} · comparée automatiquement à la période précédente équivalente.
      </div>
    </UCard>

    <!-- KPI cards -->
    <div v-if="visibleWidgets.kpis" class="grid-4">
      <div class="stat-card">
        <div class="stat-label">RDV SUR PÉRIODE</div>
        <div class="stat-value">{{ Math.round(Number(comparison.rdvs?.current ?? 0)) }}</div>
        <div class="stat-delta" :style="{ color: metricDeltaColor(comparison.rdvs) }">{{ metricDeltaText(comparison.rdvs) }}</div>
        <div class="stat-bar"><div class="stat-bar-fill" :style="{ width: Math.min(Number(comparison.rdvs?.current ?? 0) / GAUGE_MAX_RDV * 100, 100) + '%', background: '#FFD200' }"></div></div>
      </div>
      <div v-if="hasFacturation" class="stat-card">
        <div class="stat-label">CA SUR PÉRIODE</div>
        <div class="stat-value">{{ formatCurrency(comparison.ca?.current ?? 0) }}</div>
        <div class="stat-delta" :style="{ color: metricDeltaColor(comparison.ca) }">{{ metricDeltaText(comparison.ca) }}</div>
        <div class="stat-bar"><div class="stat-bar-fill" :style="{ width: Math.min(Number(comparison.ca?.current ?? 0) / GAUGE_MAX_CA * 100, 100) + '%', background: '#14B8A6' }"></div></div>
      </div>
      <div v-if="hasFacturation" class="stat-card">
        <div class="stat-label">PANIER MOYEN</div>
        <div class="stat-value">{{ formatCurrency(comparison.avg_ticket?.current ?? 0) }}</div>
        <div class="stat-delta" :style="{ color: metricDeltaColor(comparison.avg_ticket) }">{{ metricDeltaText(comparison.avg_ticket) }}</div>
        <div class="stat-bar"><div class="stat-bar-fill" :style="{ width: Math.min(Number(comparison.avg_ticket?.current ?? 0) / GAUGE_MAX_PANIER * 100, 100) + '%', background: '#8B5CF6' }"></div></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">OCCUPATION CAPACITÉ</div>
        <div class="stat-value">{{ Math.round(Number(comparison.occupation?.current ?? occupationRate)) }}%</div>
        <div class="stat-delta" :style="{ color: metricDeltaColor(comparison.occupation) }">{{ pontsOccupes }}/{{ ponts.length }} ponts occupés</div>
        <div class="stat-bar"><div class="stat-bar-fill" :style="{ width: Math.round(Number(comparison.occupation?.current ?? occupationRate)) + '%', background: '#10B981' }"></div></div>
      </div>
    </div>

    <div v-if="visibleWidgets.kpis" class="grid-4" style="margin-top:14px;">
      <div class="stat-card">
        <div class="stat-label">CHARGE PLANIFIÉE</div>
        <div class="stat-value">{{ formatMinutes(comparison.planned_minutes?.current ?? plannedMinutes) }}</div>
        <div class="stat-delta" :style="{ color: metricDeltaColor(comparison.planned_minutes) }">{{ metricDeltaText(comparison.planned_minutes) }}</div>
        <div class="stat-bar"><div class="stat-bar-fill" :style="{ width: Math.min(Number(comparison.planned_minutes?.current ?? plannedMinutes) / GAUGE_MAX_PLANNED_MIN * 100, 100) + '%', background: '#8B5CF6' }"></div></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">DOSSIERS CLÔTURÉS</div>
        <div class="stat-value">{{ Math.round(Number(comparison.completed?.current ?? stats.restitutions ?? completedToday)) }}</div>
        <div class="stat-delta" :style="{ color: metricDeltaColor(comparison.completed) }">{{ metricDeltaText(comparison.completed) }}</div>
        <div class="stat-bar"><div class="stat-bar-fill" :style="{ width: Math.min(Number(comparison.completed?.current ?? stats.restitutions ?? completedToday) / GAUGE_MAX_COMPLETED * 100, 100) + '%', background: '#34D399' }"></div></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">RENDEMENT GLOBAL</div>
        <div class="stat-value">{{ performanceData.rendement_global?.ratio ?? 0 }}%</div>
        <div class="stat-delta" :style="{ color: (performanceData.rendement_global?.ratio ?? 0) >= (thresholds.rendement_target_percent ?? 85) ? '#34D399' : '#FCA5A5' }">effectif / estimé</div>
        <div class="stat-bar"><div class="stat-bar-fill" :style="{ width: Math.min((performanceData.rendement_global?.ratio ?? 0), 100) + '%', background: (performanceData.rendement_global?.ratio ?? 0) >= (thresholds.rendement_target_percent ?? 85) ? '#10B981' : '#EF4444' }"></div></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">TAUX RETOUR SAV</div>
        <div class="stat-value">{{ performanceData.taux_retour_sav?.taux_pct ?? 0 }}%</div>
        <div class="stat-delta" style="color:#FBBF24;">{{ performanceData.taux_retour_sav?.sav_count ?? 0 }} retours</div>
        <div class="stat-bar"><div class="stat-bar-fill" :style="{ width: Math.min((performanceData.taux_retour_sav?.taux_pct ?? 0) * 5, 100) + '%', background: '#FBBF24' }"></div></div>
      </div>
    </div>

    <!-- Trend + Revenue mix -->
    <div v-if="visibleWidgets.trend || (visibleWidgets.revenueMix && hasFacturation)" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;margin:24px 0;">
      <UCard v-if="visibleWidgets.trend">
        <template #header>
          <span style="font-size:15px;font-weight:700;color:#E8E9ED;">{{ hasFacturation ? 'Évolution CA & RDVs' : 'Évolution RDVs' }}</span>
        </template>
        <ChartsLineChart v-if="dailyTrend.length" :data="trendChartData" :options="{ scales: { y1: { position: 'right', grid: { display: false } } }, plugins: { legend: { labels: { color: '#9CA3AF' } } } }" />
        <AppEmptyState v-else icon="📉" title="Pas assez de volume" description="L'évolution se remplit automatiquement dès que les RDV sont historisés sur la période choisie." />
      </UCard>

      <UCard v-if="visibleWidgets.revenueMix && hasFacturation">
        <template #header>
          <span style="font-size:15px;font-weight:700;color:#E8E9ED;">Mix rentabilité</span>
        </template>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:center;">
          <ChartsDoughnutChart :data="revenueChartData" />
          <div style="display:flex;flex-direction:column;gap:10px;">
            <div style="padding:10px 12px;border-radius:12px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);">
              <div style="font-size:11px;color:#9CA3AF;">MO HT</div>
              <div style="font-size:18px;font-weight:700;color:#FFD200;">{{ formatEuro(revenueMix.mo_ht ?? 0) }}</div>
            </div>
            <div style="padding:10px 12px;border-radius:12px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);">
              <div style="font-size:11px;color:#9CA3AF;">Pièces HT</div>
              <div style="font-size:18px;font-weight:700;color:#14B8A6;">{{ formatEuro(revenueMix.pieces_ht ?? 0) }}</div>
            </div>
            <div style="padding:10px 12px;border-radius:12px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);">
              <div style="font-size:11px;color:#9CA3AF;">Factures</div>
              <div style="font-size:18px;font-weight:700;color:#E8E9ED;">{{ revenueMix.nb_factures ?? 0 }}</div>
            </div>
          </div>
        </div>
      </UCard>
    </div>

    <!-- Performance + Rentabilité -->
    <div v-if="visibleWidgets.performance || (visibleWidgets.rentabilite && hasFacturation)" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;margin:24px 0;">
      <UCard v-if="visibleWidgets.performance">
        <template #header>
          <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
            <span style="font-size:15px;font-weight:700;color:#E8E9ED;">Performance mécanos</span>
            <span class="badge-count">{{ mecanicienStats.length }}</span>
          </div>
        </template>
        <ChartsBarChart v-if="mecanicienStats.length" :data="mecaChartData" />
        <div v-if="mecanicienStats.length" style="margin-top:12px;display:flex;flex-direction:column;gap:8px;">
          <div v-for="meca in mecanicienStats.slice(0, 4)" :key="meca.id" style="display:flex;align-items:center;justify-content:space-between;padding:8px 10px;border-radius:10px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);">
            <span style="font-size:12px;color:#E8E9ED;font-weight:600;">{{ meca.nom }}</span>
            <span v-if="hasFacturation" style="font-size:12px;color:#FFD200;font-weight:700;">{{ formatEuro(meca.ca_genere ?? 0) }}</span>
          </div>
        </div>
        <AppEmptyState v-else icon="👨‍🔧" title="Pas assez d'historique méca" description="La perf des mécaniciens se remplira au fil des interventions clôturées." />
      </UCard>

      <UCard v-if="visibleWidgets.rentabilite && hasFacturation">
        <template #header>
          <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
            <span style="font-size:15px;font-weight:700;color:#E8E9ED;">Rentabilité par type</span>
            <span class="badge-count">{{ rentabiliteData.par_type?.length ?? 0 }}</span>
          </div>
        </template>
        <div v-if="rentabiliteData.par_type?.length" style="display:flex;flex-direction:column;gap:10px;">
          <div v-for="t in rentabiliteData.par_type.slice(0, 6)" :key="t.type" style="padding:10px 12px;border-radius:12px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;">
              <div>
                <div style="font-size:13px;font-weight:700;color:#E8E9ED;">{{ t.type }}</div>
                <div style="font-size:12px;color:#9CA3AF;">{{ t.nb_rdvs }} RDV · {{ formatCurrency(t.ca_ht) }} CA</div>
              </div>
              <div style="text-align:right;">
                <div style="font-size:12px;color:#FFD200;font-weight:700;">{{ t.taux_mo_pct }}% MO</div>
                <div style="font-size:10px;color:#6B7280;">{{ formatCurrency(t.avg_ticket) }}/ticket</div>
              </div>
            </div>
          </div>
        </div>
        <AppEmptyState v-else icon="📈" title="Aucune donnée de rentabilité" description="Les données apparaîtront dès que des factures seront émises sur la période." />
      </UCard>
    </div>

    <!-- Ecart temps + Dérives -->
    <div v-if="visibleWidgets.derives" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;margin:24px 0;">
      <UCard>
        <template #header>
          <span style="font-size:15px;font-weight:700;color:#E8E9ED;">Écarts temps réel vs planifié</span>
        </template>
        <div v-if="performanceData.ecarts_mecaniciens?.length" style="display:flex;flex-direction:column;gap:10px;">
          <div v-for="m in performanceData.ecarts_mecaniciens.slice(0, 6)" :key="m.id" style="padding:10px 12px;border-radius:12px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;">
              <div>
                <div style="font-size:13px;font-weight:700;color:#E8E9ED;">{{ m.prenom }} {{ m.nom }}</div>
                <div style="font-size:12px;color:#9CA3AF;">{{ m.nb_rdvs }} interventions clôturées</div>
              </div>
              <div style="text-align:right;">
                <div :style="{ fontSize: '12px', fontWeight: 700, color: Number(m.avg_ecart_min) > (thresholds.overrun_warning_minutes ?? 15) ? '#FCA5A5' : '#34D399' }">{{ m.avg_ecart_min > 0 ? '+' : '' }}{{ formatMinutes(Math.round(Number(m.avg_ecart_min))) }}</div>
                <div style="font-size:10px;color:#6B7280;">écart moyen</div>
              </div>
            </div>
          </div>
        </div>
        <AppEmptyState v-else icon="⏱" title="Pas assez de données" description="Les écarts temps se calculent dès que les mécaniciens saisissent leur temps effectif." />
      </UCard>

      <UCard>
        <template #header>
          <span style="font-size:15px;font-weight:700;color:#E8E9ED;">Dérives & délais</span>
        </template>
        <div style="display:flex;flex-direction:column;gap:12px;">
          <div style="padding:12px;border-radius:12px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);">
            <div style="display:flex;justify-content:space-between;font-size:12px;color:#D1D5DB;margin-bottom:6px;"><span>Délai moyen fin → restitution</span><span>{{ formatMinutes(Math.round(Number(performanceData.delai_restitution?.avg_minutes ?? 0))) }}</span></div>
            <div style="height:8px;border-radius:999px;background:rgba(255,255,255,0.06);overflow:hidden;"><div :style="{ width: Math.min((performanceData.delai_restitution?.avg_minutes ?? 0) / 120 * 100, 100) + '%', height: '100%', background: (performanceData.delai_restitution?.avg_minutes ?? 0) > (thresholds.restitution_warning_minutes ?? 15) ? '#EF4444' : '#10B981' }"></div></div>
          </div>
          <div style="padding:12px;border-radius:12px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);">
            <div style="display:flex;justify-content:space-between;font-size:12px;color:#D1D5DB;margin-bottom:6px;"><span>Productivité MO</span><span>{{ rentabiliteData.mo_analysis?.productivite_pct ?? 0 }}%</span></div>
            <div style="height:8px;border-radius:999px;background:rgba(255,255,255,0.06);overflow:hidden;"><div :style="{ width: Math.min((rentabiliteData.mo_analysis?.productivite_pct ?? 0), 100) + '%', height: '100%', background: (rentabiliteData.mo_analysis?.productivite_pct ?? 0) >= (thresholds.rendement_target_percent ?? 85) ? '#10B981' : '#F59E0B' }"></div></div>
          </div>
          <div style="padding:12px;border-radius:12px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);">
            <div style="display:flex;justify-content:space-between;font-size:12px;color:#D1D5DB;margin-bottom:6px;"><span>Taux MO dans le CA</span><span>{{ rentabiliteData.global?.taux_mo_pct ?? 0 }}%</span></div>
            <div style="height:8px;border-radius:999px;background:rgba(255,255,255,0.06);overflow:hidden;"><div :style="{ width: Math.min((rentabiliteData.global?.taux_mo_pct ?? 0) * 1.5, 100) + '%', height: '100%', background: '#3B82F6' }"></div></div>
          </div>
        </div>
      </UCard>
    </div>

    <!-- Services + Status -->
    <div v-if="(visibleWidgets.services && hasFacturation) || visibleWidgets.status" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;margin:24px 0;">
      <UCard v-if="visibleWidgets.services && hasFacturation">
        <template #header>
          <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
            <span style="font-size:15px;font-weight:700;color:#E8E9ED;">CA par prestation</span>
            <span class="badge-count">{{ topServices.length }}</span>
          </div>
        </template>
        <ChartsBarChart v-if="topServices.length" :data="servicesChartData" />
        <div v-if="topServices.length" style="margin-top:12px;display:flex;flex-wrap:wrap;gap:6px;">
          <span v-for="s in topServices.slice(0, 4)" :key="s.label" style="font-size:11px;padding:4px 8px;border-radius:6px;background:rgba(139,92,246,0.15);color:#C4B5FD;">{{ s.label }} · {{ s.count }}</span>
        </div>
        <AppEmptyState v-else icon="📈" title="Aucune catégorie dominante" description="Dès que l'atelier a du volume, tu vois ici les prestations qui pèsent vraiment dans l'activité." />
      </UCard>

      <UCard v-if="visibleWidgets.status">
        <template #header>
          <span style="font-size:15px;font-weight:700;color:#E8E9ED;">Répartition des statuts</span>
        </template>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:center;">
          <ChartsDoughnutChart :data="statusChartData" />
          <div style="display:flex;flex-direction:column;gap:8px;">
            <div v-for="item in statusBreakdown.slice(0, 5)" :key="item.label" style="display:flex;align-items:center;justify-content:space-between;padding:6px 10px;border-radius:8px;background:rgba(255,255,255,0.03);">
              <div style="display:flex;align-items:center;gap:8px;">
                <span :style="{ width: 8, height: 8, borderRadius: '50%', background: item.color }"></span>
                <span style="font-size:12px;color:#D1D5DB;">{{ item.label }}</span>
              </div>
              <span style="font-size:12px;color:#E8E9ED;font-weight:600;">{{ item.count }}</span>
            </div>
          </div>
        </div>
      </UCard>
    </div>

    <!-- Ponts + Synthèse -->
    <div v-if="visibleWidgets.ponts" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;margin-bottom:24px;">
      <UCard>
        <template #header>
          <span style="font-size:15px;font-weight:700;color:#E8E9ED;">Occupation ressources atelier</span>
        </template>
        <div v-if="ponts.length" class="pont-grid" style="margin:0;">
          <div v-for="pont in ponts" :key="pont.id" class="pont-card" :class="pont.current_rdv ? 'pont-occupe' : 'pont-libre'">
            <div class="pont-card-header">
              <div style="display:flex;align-items:center;gap:8px;">
                <span class="live-dot" :style="{ background: pont.current_rdv ? '#F59E0B' : '#10B981' }"></span>
                <span class="pont-name">{{ pont.nom }}</span>
              </div>
              <StatusBadge :status="pont.current_rdv ? 'en_cours' : (pont.is_active === 0 ? 'annule' : 'confirme')" />
            </div>
            <div class="pont-card-body">
              <div v-if="pont.current_rdv">
                <p style="font-weight:600;color:#E8E9ED;font-size:14px;">{{ pont.current_rdv.vehicule_info }}</p>
                <p style="color:#6B7280;font-size:12px;">{{ pont.current_rdv.client_nom }} · {{ pont.current_rdv.type_intervention }}</p>
              </div>
              <p v-else style="color:#6B7280;font-size:13px;">Aucune intervention en cours</p>
            </div>
            <div class="pont-card-footer">{{ pont.next_count ?? 0 }} RDV restants aujourd'hui</div>
          </div>
        </div>
        <AppEmptyState v-else icon="🔧" title="Aucun pont remonté" description="La vue live des ponts s'affichera dès que la configuration atelier sera disponible." />
      </UCard>
    </div>

    <UCard v-if="visibleWidgets.synthese">
      <template #header>
        <span style="font-size:15px;font-weight:700;color:#E8E9ED;">Synthèse pilotage</span>
      </template>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;">
        <div style="padding:12px;border-radius:12px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);">
          <div style="font-size:11px;color:#9CA3AF;">OR ouverts</div>
          <div style="font-size:22px;font-weight:700;color:#E8E9ED;">{{ stats.or_ouverts ?? 0 }}</div>
        </div>
        <div style="padding:12px;border-radius:12px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);">
          <div style="font-size:11px;color:#9CA3AF;">RDV en cours</div>
          <div style="font-size:22px;font-weight:700;color:#E8E9ED;">{{ stats.rdvs_en_cours ?? rdvsInProgress }}</div>
        </div>
        <div style="padding:12px;border-radius:12px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);">
          <div style="font-size:11px;color:#9CA3AF;">Restitutions période</div>
          <div style="font-size:22px;font-weight:700;color:#E8E9ED;">{{ stats.restitutions ?? completedToday }}</div>
        </div>
        <div style="padding:12px;border-radius:12px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);">
          <div style="font-size:11px;color:#9CA3AF;">Activité / jour</div>
          <div style="font-size:22px;font-weight:700;color:#E8E9ED;">{{ avgDailyRdvs }}</div>
        </div>
        <div v-if="hasFacturation" style="padding:12px;border-radius:12px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);">
          <div style="font-size:11px;color:#9CA3AF;">Factures sur période</div>
          <div style="font-size:22px;font-weight:700;color:#E8E9ED;">{{ revenueMix.nb_factures ?? 0 }}</div>
        </div>
        <div style="padding:12px;border-radius:12px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);">
          <div style="font-size:11px;color:#9CA3AF;">Charge / pont</div>
          <div style="font-size:22px;font-weight:700;color:#E8E9ED;">{{ ponts.length ? formatMinutes(Math.round(Number(comparison.planned_minutes?.current ?? 0) / ponts.length)) : '0 min' }}</div>
        </div>
      </div>
    </UCard>

    <UCard v-if="stockModuleEnabled && stockAlertes.length" style="margin-top:24px;border-color:rgba(239,68,68,0.2);">
      <template #header>
        <span style="font-size:15px;font-weight:700;color:#FCA5A5;">⚠ Alertes stock ({{ stockAlertes.length }})</span>
      </template>
      <div style="display:flex;flex-direction:column;gap:8px;">
        <div v-for="p in stockAlertes" :key="p.id" style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;border-radius:10px;border:1px solid rgba(239,68,68,0.15);background:rgba(239,68,68,0.05);font-size:13px;">
          <span style="color:#D1D5DB;">{{ p.designation }} ({{ p.reference }})</span>
          <span class="badge-count" style="background:rgba(239,68,68,0.12);color:#FCA5A5;">Stock: {{ p.quantite_stock }}</span>
        </div>
      </div>
    </UCard>

    <!-- CLIENT SEGMENTS -->
    <div v-if="visibleWidgets.clientSegments && stats.client_segments?.length" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;margin:24px 0;">
      <UCard>
        <template #header>
          <span style="font-size:15px;font-weight:700;color:#E8E9ED;">Segments clientèle</span>
        </template>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:center;">
          <ChartsDoughnutChart :data="segmentChartData" />
          <div style="display:flex;flex-direction:column;gap:8px;">
            <div v-for="seg in stats.client_segments" :key="seg.segment" style="display:flex;align-items:center;justify-content:space-between;padding:8px 10px;border-radius:10px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);">
              <div>
                <div style="font-weight:600;color:#E8E9ED;font-size:13px;">{{ seg.segment }}</div>
                <div style="font-size:11px;color:#9CA3AF;">{{ seg.clients }} client(s)</div>
              </div>
              <div v-if="hasFacturation" style="font-weight:700;color:#FFD200;font-size:14px;">{{ formatEuro(seg.ca) }}</div>
            </div>
          </div>
        </div>
      </UCard>
    </div>

    <!-- EXPLORE -->
    <div v-if="visibleWidgets.explore" style="margin:24px 0;">
      <UCard>
        <template #header>
          <span style="font-size:15px;font-weight:700;color:#E8E9ED;">🔍 Exploration libre</span>
        </template>
        <div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
          <div style="display:flex;flex-direction:column;gap:4px;">
            <label style="font-size:11px;color:#9CA3AF;font-weight:600;">Dimension</label>
            <select v-model="exploreDimension" style="padding:8px 10px;border-radius:10px;border:1px solid rgba(255,255,255,0.08);background:rgba(255,255,255,0.04);color:#E5E7EB;">
              <option value="type_intervention">Type d'intervention</option>
              <option value="statut_rdv">Statut RDV</option>
              <option value="mecanicien_nom">Mécanicien</option>
              <option value="client_segment">Segment client</option>
              <option value="vehicule_marque">Marque véhicule</option>
              <option value="pont_nom">Pont</option>
            </select>
          </div>
          <div style="display:flex;flex-direction:column;gap:4px;">
            <label style="font-size:11px;color:#9CA3AF;font-weight:600;">Métriques</label>
            <div style="display:flex;flex-wrap:wrap;gap:8px;max-width:420px;">
              <label v-for="m in exploreMetricOptions" :key="m.value" style="display:flex;align-items:center;gap:4px;padding:5px 8px;border-radius:8px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.06);cursor:pointer;font-size:12px;color:#E5E7EB;">
                <input v-model="exploreMetrics" type="checkbox" :value="m.value" style="accent-color:#FFD200;" />
                {{ m.label }}
              </label>
            </div>
          </div>
          <button type="button" class="topbar-new-btn" style="align-self:flex-end;font-size:12px;padding:8px 16px;" @click="loadExplore">Explorer</button>
        </div>
        <div v-if="exploreRows.length" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;">
          <div>
            <div style="font-size:12px;font-weight:600;color:#9CA3AF;margin-bottom:8px;">📊 Visualisation</div>
            <ChartsBarChart :data="exploreChartData" :options="{ indexAxis: 'y', plugins: { legend: { display: exploreMetrics.length > 1, labels: { color: '#9CA3AF', font: { size: 11 } } } } }" />
          </div>
          <div>
            <div style="font-size:12px;font-weight:600;color:#9CA3AF;margin-bottom:8px;">📋 Détail</div>
            <div style="display:flex;flex-direction:column;gap:8px;max-height:320px;overflow-y:auto;">
              <div v-for="(row, idx) in exploreRows" :key="idx" style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;border-radius:10px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);">
                <span style="font-size:13px;color:#E8E9ED;">{{ row.label || '(Non renseigné)' }}</span>
                <div style="display:flex;align-items:center;gap:12px;">
                  <span style="font-size:12px;color:#9CA3AF;">{{ row.count }} RDV</span>
                  <div style="display:flex;flex-direction:column;align-items:flex-end;gap:2px;">
                    <span v-for="m in exploreMetrics" :key="m" style="font-size:12px;font-weight:600;color:#FFD200;">{{ exploreMetricLabel(m) }}: {{ m === 'count' ? row[m] : formatEuro(row[m]) }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <AppEmptyState v-else icon="🔍" title="Aucune donnée" description="Sélectionnez une dimension et une ou plusieurs métriques, puis cliquez sur Explorer." />
      </UCard>
    </div>

    <!-- FORECAST WIDGET -->
    <div v-if="visibleWidgets.forecast && hasFacturation" style="margin:24px 0;">
      <UCard>
        <template #header>
          <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
            <span style="font-size:15px;font-weight:700;color:#E8E9ED;">🔮 Prévisions CA</span>
            <div style="display:flex;gap:6px;">
              <button v-for="d in [7,14,30]" :key="d" type="button" style="padding:4px 10px;border-radius:8px;border:1px solid rgba(255,255,255,0.08);font-size:11px;cursor:pointer;" :style="{ background: forecastDays === d ? 'rgba(255,210,0,0.14)' : 'rgba(255,255,255,0.04)', color: forecastDays === d ? '#FFD200' : '#D1D5DB' }" @click="forecastDays = d; loadForecast()">{{ d }}j</button>
            </div>
          </div>
        </template>
        <ChartsLineChart v-if="forecastData.historical.length || forecastData.forecast.length" :data="forecastChartData" :options="{ plugins: { legend: { display: true, labels: { color: '#9CA3AF', font: { size: 11 } } } }, scales: { y: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#6B7280' } }, x: { grid: { display: false }, ticks: { color: '#6B7280', maxTicksLimit: 8 } } } }" />
        <AppEmptyState v-else icon="🔮" title="Pas assez d'historique" description="Les prévisions nécessitent au moins 7 jours de données historiques." />
      </UCard>
    </div>
  </div>
</template>

<script setup lang="ts">
const api = useApi()
const atelierStore = useAtelierStore()
const hasFacturation = computed(() => atelierStore.isModuleEnabled('facturation'))
const loading = ref(true)
const stats = ref<any>({})
const todayRdvs = ref<any[]>([])
const stockAlertes = ref<any[]>([])
const ponts = ref<any[]>([])
const mecanicienStats = ref<any[]>([])
const realtimeData = ref<any>({})
const performanceData = ref<any>({})
const rentabiliteData = ref<any>({})
const thresholds = ref<any>({})
const stockModuleEnabled = computed(() => atelierStore.isModuleEnabled('stock'))
const selectedPeriod = ref('30d')
const filters = reactive({ from: '', to: '' })
const customizing = ref(false)
const showExportMenu = ref(false)
const exploreDimension = ref('type_intervention')
const exploreMetrics = ref<string[]>([])
const exploreRows = ref<any[]>([])
const forecastData = ref<any>({ historical: [], forecast: [] })
const forecastDays = ref(14)

const exploreMetricOptions = computed(() => [
  ...(hasFacturation.value
    ? [
        { value: 'ca_ht', label: 'CA HT' },
        { value: 'ca_mo_ht', label: 'CA MO HT' },
        { value: 'ca_pieces_ht', label: 'CA Pièces HT' },
      ]
    : []),
  { value: 'temps_estime', label: 'Temps estimé' },
  { value: 'temps_effectif', label: 'Temps effectif' },
  { value: 'count', label: 'Nombre' },
])
exploreMetrics.value = [hasFacturation.value ? 'ca_ht' : 'count']

function exploreMetricLabel(value: string): string {
  return exploreMetricOptions.value.find(o => o.value === value)?.label || value
}

const visibleWidgets = reactive({
  realtime: true,
  kpis: true,
  trend: true,
  revenueMix: true,
  performance: true,
  rentabilite: true,
  derives: true,
  services: true,
  status: true,
  ponts: true,
  synthese: true,
  clientSegments: true,
  explore: true,
  forecast: true,
})

const widgetLabels = computed<Record<string, string>>(() => {
  const labels: Record<string, string> = {
    forecast: 'Prévisions',
    realtime: 'Temps réel',
    kpis: 'KPIs période',
    trend: 'Évolution',
    revenueMix: 'Mix rentabilité',
    performance: 'Performance méca',
    rentabilite: 'Rentabilité',
    derives: 'Dérives & délais',
    services: 'Prestations',
    status: 'Statuts',
    ponts: 'Ponts',
    synthese: 'Synthèse',
    clientSegments: 'Segments clientèle',
    explore: 'Exploration',
  }
  if (!hasFacturation.value) {
    delete labels.revenueMix
    delete labels.rentabilite
    delete labels.services
    delete labels.forecast
  }
  return labels
})

const periodPresets = [
  { key: 'today', label: "Aujourd'hui" },
  { key: '7d', label: '7 jours' },
  { key: '30d', label: '30 jours' },
  { key: '90d', label: '90 jours' },
]

const todayDate = computed(() => {
  return new Date().toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
})

const comparison = computed(() => stats.value?.comparison ?? {})
const GAUGE_MAX_RDV = 40
const GAUGE_MAX_CA = 25000
const GAUGE_MAX_PANIER = 800
const GAUGE_MAX_PLANNED_MIN = 2400
const GAUGE_MAX_COMPLETED = 30

const revenueMix = computed(() => stats.value?.revenue_mix ?? {})
const dailyTrend = computed(() => Array.isArray(stats.value?.daily_trend) ? stats.value.daily_trend : [])
const dailyTrendMax = computed(() => Math.max(1, ...dailyTrend.value.map((item: any) => Number(item.rdvs ?? 0))))
const periodSummary = computed(() => {
  const from = stats.value?.period?.from ?? filters.from
  const to = stats.value?.period?.to ?? filters.to
  if (!from || !to) return 'Période en cours'
  return `${new Date(from).toLocaleDateString('fr-FR')} → ${new Date(to).toLocaleDateString('fr-FR')}`
})
const avgDailyRdvs = computed(() => {
  const total = Number(comparison.value?.rdvs?.current ?? 0)
  const days = Number(stats.value?.period?.days ?? 1) || 1
  return (total / days).toFixed(1)
})

const pontsOccupes = computed(() => ponts.value.filter(p => p.current_rdv).length)
const occupationRate = computed(() => {
  if (!ponts.value.length) return 0
  return Math.round(pontsOccupes.value / ponts.value.length * 100)
})
const rdvsInProgress = computed(() => todayRdvs.value.filter(r => ['reception', 'en_cours'].includes(r.status)).length)
const completedToday = computed(() => todayRdvs.value.filter(r => ['termine', 'restitue', 'facture', 'paye'].includes(r.status)).length)
const plannedMinutes = computed(() => Number(stats.value?.planned_minutes_today ?? todayRdvs.value.reduce((sum, rdv) => sum + Number(rdv.temps_estime ?? 60), 0)))

const statusBreakdown = computed(() => {
  const rows = Array.isArray(stats.value?.active_by_status) ? stats.value.active_by_status : []
  const total = rows.reduce((sum: number, row: any) => sum + Number(row.count ?? 0), 0) || 1
  const colors: Record<string, string> = {
    en_attente: '#6B7280', reserve: '#60A5FA', confirme: '#FBBF24', reception: '#A78BFA',
    en_cours: '#F59E0B', termine: '#34D399', restitue: '#14B8A6', facture: '#22C55E',
    paye: '#10B981', annule: '#EF4444',
  }
  return rows.map((row: any) => ({
    label: String(row.statut ?? 'atelier').replaceAll('_', ' '),
    count: Number(row.count ?? 0),
    percent: Math.round(Number(row.count ?? 0) / total * 100),
    color: colors[String(row.statut ?? '')] ?? '#9CA3AF',
  })).sort((a: any, b: any) => b.count - a.count)
})

const topServices = computed(() => {
  const source = Array.isArray(stats.value?.top_services) ? stats.value.top_services : []
  if (source.length) {
    return source.map((item: any, index: number) => ({
      label: item.label || 'Atelier', count: Number(item.count ?? 0),
      minutes: Number(item.minutes ?? 0), revenue: Number(item.revenue ?? 0), rank: index + 1,
    }))
  }
  const grouped = new Map<string, { label: string; count: number; minutes: number }>()
  for (const rdv of todayRdvs.value) {
    const label = rdv.type_intervention || 'Atelier'
    const existing = grouped.get(label) ?? { label, count: 0, minutes: 0 }
    existing.count += 1
    existing.minutes += Number(rdv.temps_estime ?? 60)
    grouped.set(label, existing)
  }
  return Array.from(grouped.values()).sort((a, b) => b.count - a.count || b.minutes - a.minutes).slice(0, 5).map((item, index) => ({ ...item, rank: index + 1 }))
})

// ── Chart data computed ──
const trendChartData = computed(() => {
  const rows = dailyTrend.value
  return {
    labels: rows.map((r: any) => r.date),
    datasets: [
      {
        label: 'RDVs',
        data: rows.map((r: any) => Number(r.rdvs ?? 0)),
        borderColor: '#FFD200',
        backgroundColor: '#FFD20020',
        fill: true,
        tension: 0.4,
        yAxisID: 'y',
      },
      ...(hasFacturation.value
        ? [{
            label: 'CA (€)',
            data: rows.map((r: any) => Number(r.revenue ?? 0)),
            borderColor: '#14B8A6',
            backgroundColor: '#14B8A620',
            fill: true,
            tension: 0.4,
            yAxisID: 'y1',
          }]
        : []),
    ],
  }
})

const revenueChartData = computed(() => {
  const rm = revenueMix.value
  return {
    labels: ['MO HT', 'Pièces HT'],
    datasets: [{
      data: [Number(rm.mo_ht ?? 0), Number(rm.pieces_ht ?? 0)],
      backgroundColor: ['#FFD200', '#14B8A6'],
      borderColor: 'transparent',
      borderWidth: 0,
    }],
  }
})

const mecaChartData = computed(() => {
  const mecas = Array.isArray(stats.value?.mecaniciens) ? stats.value.mecaniciens : []
  const top = mecas.slice(0, 8)
  return {
    labels: top.map((m: any) => m.nom || 'Mécano'),
    datasets: [{
      label: 'CA généré (€)',
      data: top.map((m: any) => Number(m.ca_genere ?? 0)),
      backgroundColor: '#FFD200',
      borderRadius: 6,
    }],
  }
})

const servicesChartData = computed(() => {
  const services = topServices.value.slice(0, 8)
  return {
    labels: services.map((s: any) => s.label),
    datasets: [{
      label: 'Chiffre d\'affaires (€)',
      data: services.map((s: any) => Number(s.revenue ?? 0)),
      backgroundColor: '#8B5CF6',
      borderRadius: 6,
    }],
  }
})

const statusChartData = computed(() => {
  const rows = Array.isArray(stats.value?.active_by_status) ? stats.value.active_by_status : []
  const colors: Record<string, string> = {
    en_attente: '#6B7280', reserve: '#60A5FA', confirme: '#FBBF24', reception: '#A78BFA',
    en_cours: '#F59E0B', termine: '#34D399', restitue: '#14B8A6', facture: '#22C55E',
    paye: '#10B981', annule: '#EF4444',
  }
  return {
    labels: rows.map((r: any) => String(r.statut ?? '').replaceAll('_', ' ')),
    datasets: [{
      data: rows.map((r: any) => Number(r.count ?? 0)),
      backgroundColor: rows.map((r: any) => colors[String(r.statut ?? '')] ?? '#9CA3AF'),
      borderColor: 'transparent',
      borderWidth: 0,
    }],
  }
})

const segmentChartData = computed(() => {
  const rows = Array.isArray(stats.value?.client_segments) ? stats.value.client_segments : []
  return {
    labels: rows.map((r: any) => r.segment || 'Inconnu'),
    datasets: [{
      data: rows.map((r: any) => Number(r.clients ?? 0)),
      backgroundColor: ['#FFD200', '#14B8A6', '#8B5CF6', '#F59E0B', '#EF4444'],
      borderColor: 'transparent',
      borderWidth: 0,
    }],
  }
})

const exploreChartData = computed(() => {
  const rows = exploreRows.value.slice(0, 12)
  const labelMap: Record<string, string> = {
    ca_ht: 'CA HT (€)', ca_mo_ht: 'CA MO HT (€)', ca_pieces_ht: 'CA Pièces HT (€)',
    temps_estime: 'Temps estimé', temps_effectif: 'Temps effectif', count: 'Nombre',
  }
  const colors = ['#FFD200', '#14B8A6', '#8B5CF6', '#F59E0B', '#EF4444', '#34D399']
  return {
    labels: rows.map((r: any) => r.label || '(Non renseigné)'),
    datasets: exploreMetrics.value.map((metric, idx) => ({
      label: labelMap[metric] || metric,
      data: rows.map((r: any) => Number(r[metric] ?? 0)),
      backgroundColor: colors[idx % colors.length],
      borderRadius: 6,
    })),
  }
})

const forecastChartData = computed(() => {
  const hist = forecastData.value.historical || []
  const fore = forecastData.value.forecast || []
  const labels = [
    ...hist.map((h: any) => h.date),
    ...fore.map((f: any) => f.date),
  ]
  return {
    labels,
    datasets: [
      {
        label: 'Historique CA HT',
        data: [...hist.map((h: any) => h.ca_ht), ...fore.map(() => null)],
        borderColor: '#FFD200',
        backgroundColor: '#FFD20020',
        fill: false,
        tension: 0.3,
        pointRadius: 2,
      },
      {
        label: 'Prédiction CA HT',
        data: [...hist.map(() => null), ...fore.map((f: any) => f.ca_ht)],
        borderColor: '#FFD200',
        backgroundColor: '#FFD20010',
        borderDash: [6, 4],
        fill: '+1',
        tension: 0.3,
        pointRadius: 0,
      },
      {
        label: 'Confiance basse',
        data: [...hist.map(() => null), ...fore.map((f: any) => f.ca_ht_lower)],
        borderColor: 'transparent',
        backgroundColor: '#FFD20008',
        fill: false,
        pointRadius: 0,
      },
    ],
  }
})

// Realtime computed helpers
const rtOrOverrunCount = computed(() => {
  if (!realtimeData.value.or_en_cours?.length) return 0
  return realtimeData.value.or_en_cours.filter((o: any) => {
    if (!o.heure_debut_travaux || !o.temps_estime) return false
    const elapsed = (Date.now() - new Date(o.heure_debut_travaux).getTime()) / 60000
    return elapsed > (thresholds.value.overrun_warning_minutes ?? 15)
  }).length
})
const rtOrAlertClass = computed(() => rtOrOverrunCount.value > 0 ? 'rt-alert' : '')
const rtPontsOccupes = computed(() => realtimeData.value.ponts?.filter((p: any) => p.rdv_id).length ?? 0)
const rtPontsLibres = computed(() => (realtimeData.value.ponts?.length ?? 0) - rtPontsOccupes.value)
const rtRestitutionDelayed = computed(() => {
  if (!realtimeData.value.attente_restitution?.length) return 0
  return realtimeData.value.attente_restitution.filter((r: any) => {
    if (!r.heure_fin_travaux) return false
    const wait = (Date.now() - new Date(r.heure_fin_travaux).getTime()) / 60000
    return wait > (thresholds.value.restitution_warning_minutes ?? 15)
  }).length
})
const rtRestitutionAlertClass = computed(() => rtRestitutionDelayed.value > 0 ? 'rt-alert' : '')

function formatCurrency(value: number | string): string {
  const amount = Number(value ?? 0)
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(amount)
}
function formatShortDay(value: string): string {
  const date = new Date(value)
  return date.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' })
}
function metricDeltaText(metric: any): string {
  const pct = Number(metric?.delta_pct ?? 0)
  const sign = pct > 0 ? '+' : ''
  return `${sign}${pct}% vs période préc.`
}
function metricDeltaColor(metric: any): string {
  return Number(metric?.delta ?? 0) >= 0 ? '#34D399' : '#FCA5A5'
}
function mixShare(value: number | string): number {
  const total = Number(revenueMix.value?.total_ht ?? 0)
  if (!total) return 0
  return Math.min(100, Math.round(Number(value ?? 0) / total * 100))
}
function toIsoDate(date: Date): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}
function applyPreset(key: string) {
  selectedPeriod.value = key
  const end = new Date()
  const start = new Date()
  if (key === 'today') { /* same day */ }
  else if (key === '7d') { start.setDate(start.getDate() - 6) }
  else if (key === '90d') { start.setDate(start.getDate() - 89) }
  else { start.setDate(start.getDate() - 29) }
  filters.from = toIsoDate(start)
  filters.to = toIsoDate(end)
  loadDashboard()
}

function normalizeRdv(r: any) {
  const c = r.client
  const v = r.vehicule
  return {
    ...r, status: r.statut ?? r.status,
    client_nom: c ? `${c.prenom} ${c.nom}` : (r.client_nom ?? ''),
    vehicule_info: v ? `${v.marque} ${v.modele}` : (r.vehicule_info ?? ''),
    heure_debut: r.heure_rdv ?? r.heure_debut,
    mecanicien_nom: r.mecanicien ? `${r.mecanicien.prenom} ${r.mecanicien.nom}` : (r.mecanicien_nom ?? ''),
    pont_nom: r.pont?.nom ?? '',
  }
}

onMounted(() => {
  applyPreset('30d')
  refreshInterval = setInterval(loadDashboard, 60000)
})
onUnmounted(() => { if (refreshInterval) clearInterval(refreshInterval) })

let refreshInterval: ReturnType<typeof setInterval> | null = null

function formatEuro(value: number | string | null): string {
  if (value == null) return '0 €'
  const n = typeof value === 'string' ? parseFloat(value) : value
  if (isNaN(n)) return '0 €'
  return n.toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' })
}

async function loadExplore() {
  try {
    const params = new URLSearchParams()
    params.set('dimension', exploreDimension.value)
    exploreMetrics.value.forEach(m => params.append('metrics[]', m))
    if (filters.from) params.set('from', filters.from)
    if (filters.to) params.set('to', filters.to)
    const query = params.toString() ? `?${params.toString()}` : ''
    const data = await api.get(`/analytics/explore${query}`)
    exploreRows.value = Array.isArray(data?.rows) ? data.rows : []
  } catch (e) {
    exploreRows.value = []
  }
}

async function loadForecast() {
  try {
    const params = new URLSearchParams()
    params.set('days', String(forecastDays.value))
    if (filters.from) params.set('from', filters.from)
    if (filters.to) params.set('to', filters.to)
    const query = params.toString() ? `?${params.toString()}` : ''
    const data = await api.get(`/analytics/forecast${query}`)
    forecastData.value = {
      historical: Array.isArray(data?.historical) ? data.historical : [],
      forecast: Array.isArray(data?.forecast) ? data.forecast : [],
    }
  } catch (e) {
    forecastData.value = { historical: [], forecast: [] }
  }
}

async function exportDashboard(format: 'pdf' | 'excel') {
  showExportMenu.value = false
  try {
    const params = new URLSearchParams()
    if (filters.from) params.set('from', filters.from)
    if (filters.to) params.set('to', filters.to)
    const query = params.toString() ? `?${params.toString()}` : ''
    const url = `/api/analytics/export/${format}${query}`
    const response = await fetch(url, { credentials: 'include' })
    if (!response.ok) throw new Error('Export failed')
    const blob = await response.blob()
    const ext = format === 'pdf' ? 'pdf' : 'xlsx'
    const filename = `rapport_analytics_${filters.from || ''}_${filters.to || ''}.${ext}`
    const link = document.createElement('a')
    link.href = URL.createObjectURL(blob)
    link.download = filename
    link.click()
    URL.revokeObjectURL(link.href)
  } catch (e) {
    alert('Échec de l\'export. Veuillez réessayer.')
  }
}

async function loadDashboard() {
  try {
    const today = toIsoDate(new Date())
    const params = new URLSearchParams()
    if (filters.from) params.set('from', filters.from)
    if (filters.to) params.set('to', filters.to)
    if (!filters.from || !filters.to) params.set('period', selectedPeriod.value)
    const query = params.toString() ? `?${params.toString()}` : ''

    const [s, rdvData, alertes, pontsData, rt, perf, renta, configData] = await Promise.all([
      api.get(`/analytics/dashboard${query}`),
      api.get(`/rendez-vous?dateRdv[after]=${today}&dateRdv[before]=${today}&itemsPerPage=200`),
      stockModuleEnabled.value ? api.get('/stock/alertes').catch(() => []) : Promise.resolve([]),
      api.get('/ponts/status').catch(() => []),
      api.get('/statistiques/realtime').catch(() => ({})),
      api.get(`/statistiques/performance${query}`).catch(() => ({})),
      api.get(`/statistiques/rentabilite${query}`).catch(() => ({})),
      api.get('/config').catch(() => null),
    ])

    stats.value = s
    const rawRdvs = rdvData?.['hydra:member'] ?? rdvData?.member ?? (Array.isArray(rdvData) ? rdvData : [])
    todayRdvs.value = rawRdvs.map(normalizeRdv)
    stockAlertes.value = alertes
    ponts.value = Array.isArray(pontsData) ? pontsData : (pontsData?.['hydra:member'] ?? pontsData?.member ?? [])
    mecanicienStats.value = Array.isArray(s?.mecaniciens) ? s.mecaniciens : []
    realtimeData.value = rt
    performanceData.value = perf
    rentabiliteData.value = renta
    thresholds.value = configData?.dashboardThresholds ?? {
      overrun_warning_minutes: 15, overrun_critical_minutes: 45,
      restitution_warning_minutes: 15, restitution_critical_minutes: 45,
      rendement_target_percent: 85,
    }
  } finally {
    loading.value = false
    loadForecast()
  }
}
</script>

<style scoped>
.grid-4 {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 14px;
}
.stat-card {
  background: rgba(255,255,255,0.03);
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: 12px;
  padding: 14px;
}
.stat-label {
  font-size: 11px;
  font-weight: 700;
  color: #9CA3AF;
  letter-spacing: 0.3px;
}
.stat-value {
  font-size: 22px;
  font-weight: 800;
  color: #E8E9ED;
  margin: 6px 0 4px;
}
.stat-delta {
  font-size: 12px;
  font-weight: 600;
}
.stat-bar {
  height: 5px;
  border-radius: 999px;
  background: rgba(255,255,255,0.06);
  overflow: hidden;
  margin-top: 10px;
}
.stat-bar-fill {
  height: 100%;
  border-radius: 999px;
  transition: width 0.5s ease;
}

/* Realtime banner */
.rt-card {
  background: rgba(255,255,255,0.03);
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: 12px;
  padding: 12px 14px;
  transition: all 0.2s;
}
.rt-card.rt-alert {
  border-color: rgba(239,68,68,0.3);
  background: rgba(239,68,68,0.06);
}
.rt-label {
  font-size: 10px;
  font-weight: 700;
  color: #9CA3AF;
  letter-spacing: 0.4px;
}
.rt-value {
  font-size: 20px;
  font-weight: 800;
  color: #E8E9ED;
  margin: 4px 0 2px;
}
.rt-sub {
  font-size: 11px;
  color: #6B7280;
}
.rt-alert .rt-value {
  color: #FCA5A5;
}
.rt-alert .rt-sub {
  color: #FCA5A5;
}

/* Pont grid reused from existing */
.pont-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 10px;
}
.pont-card {
  background: rgba(255,255,255,0.03);
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: 10px;
  padding: 12px;
}
.pont-occupe {
  border-color: rgba(245,158,11,0.25);
}
.pont-libre {
  border-color: rgba(16,185,129,0.2);
}
.pont-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
}
.pont-name {
  font-size: 13px;
  font-weight: 700;
  color: #E8E9ED;
}
.pont-card-body {
  margin-bottom: 8px;
}
.pont-card-footer {
  font-size: 11px;
  color: #6B7280;
}

.topbar-btn {
  padding: 8px 14px;
  border-radius: 10px;
  border: 1px solid;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  background: transparent;
  font-family: inherit;
  transition: all 0.15s;
}
.topbar-btn:hover {
  opacity: 0.85;
}
.badge-count {
  font-size: 11px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 999px;
  background: rgba(255,255,255,0.08);
  color: #D1D5DB;
}
</style>
