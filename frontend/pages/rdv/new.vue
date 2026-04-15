x<template>
  <div>
    <div class="page-header">
      <div style="display:flex;align-items:center;gap:12px;">
        <NuxtLink to="/rdv" style="color:#6B7280;text-decoration:none;font-size:18px;">◀</NuxtLink>
        <div>
          <div class="page-title">Prise de RDV</div>
          <div class="page-sub">Parcours intégré (identique public) + recherche client interne</div>
        </div>
      </div>
    </div>

    <!-- Client search (internal) -->
    <div style="background:var(--dark2);border:1px solid var(--glass-border);border-radius:var(--radius-lg);padding:20px;margin-bottom:24px;">
      <div style="font-size:14px;font-weight:700;color:#E8E9ED;margin-bottom:12px;">Client existant (base interne)</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div>
          <div class="form-label" style="margin-bottom:6px;">LIEU DU RDV (ATELIER)</div>
          <select
            v-if="canSelectAtelier"
            v-model.number="selectedAtelierId"
            style="width:100%;padding:10px 14px;background:var(--dark3);border:1px solid rgba(255,255,255,0.08);border-radius:var(--radius-sm);color:#E8E9ED;font-size:14px;font-family:inherit;outline:none;"
          >
            <option v-for="atelier in atelierOptions" :key="atelier.id" :value="atelier.id">{{ atelier.nom }}</option>
          </select>
          <div v-else style="padding:10px 14px;background:var(--dark3);border:1px solid rgba(255,255,255,0.08);border-radius:var(--radius-sm);color:#D1D5DB;font-size:14px;">{{ atelierDisplayName }}</div>
          <div style="font-size:11px;color:#6B7280;margin-top:4px;">Les créneaux et prestations suivent toujours l'atelier sélectionné.</div>
        </div>
        <div></div>
      </div>
      <div style="margin-top:12px;">
        <div class="form-label" style="margin-bottom:6px;">RECHERCHE CLIENT</div>
        <input
          v-model="clientSearch"
          type="text"
          placeholder="Nom, prénom, téléphone ou email..."
          style="width:100%;padding:10px 14px;background:var(--dark3);border:1px solid rgba(255,255,255,0.08);border-radius:var(--radius-sm);color:#E8E9ED;font-size:14px;font-family:inherit;outline:none;"
          @input="searchClients"
        />
        <div style="font-size:11px;color:#6B7280;margin-top:4px;">Tapez au moins 2 caractères pour rechercher un client existant.</div>
      </div>
      <div v-if="clientResults.length" style="border:1px solid rgba(255,255,255,0.06);border-radius:10px;max-height:160px;overflow-y:auto;margin-top:8px;">
        <div
          v-for="c in clientResults"
          :key="c.id"
          style="padding:10px 14px;font-size:13px;color:#D1D5DB;cursor:pointer;border-bottom:1px solid rgba(255,255,255,0.04);transition:background 0.15s;"
          @click="selectClient(c)"
          @mouseenter="($event.target! as HTMLElement).style.background='rgba(255,255,255,0.03)'"
          @mouseleave="($event.target! as HTMLElement).style.background='transparent'"
        >
          <span style="font-weight:600;">{{ c.prenom }} {{ c.nom }}</span> — {{ c.telephone || c.email }}
        </div>
      </div>
      <div v-if="selectedClient" style="display:flex;align-items:center;gap:8px;margin-top:8px;padding:8px 12px;background:rgba(255,210,0,0.06);border:1px solid rgba(255,210,0,0.2);border-radius:8px;">
        <span style="font-size:13px;color:#FFD200;font-weight:600;">✓ {{ selectedClient.prenom }} {{ selectedClient.nom }}</span>
        <button class="btn btn-ghost" style="margin-left:auto;min-height:30px;padding:4px 10px;font-size:12px;" @click="clearClient">✕ Changer</button>
      </div>
    </div>

    <!-- Wizard Stepper -->
    <div class="steps" style="margin-bottom:28px;">
      <div class="step-item" v-for="(s, i) in stepLabels" :key="i">
        <div class="step-dot" :class="{ active: step === i + 1, done: step > i + 1 }">{{ step > i + 1 ? '✓' : i + 1 }}</div>
        <div class="step-label" :style="step === i + 1 ? 'color:#FFD200;' : ''">{{ s }}</div>
        <div v-if="i < stepLabels.length - 1" class="step-connector" :class="{ done: step > i + 1 }"></div>
      </div>
    </div>

    <!-- STEP 1 — Véhicule -->
    <div v-if="step === 1" style="max-width:680px;">
      <div style="background:var(--dark2);border:1px solid var(--glass-border);border-radius:var(--radius-lg);padding:24px;">
        <div style="font-size:16px;font-weight:700;color:#E8E9ED;margin-bottom:4px;">Identification moto</div>
        <div style="font-size:12px;color:#6B7280;margin-bottom:20px;">Entrez votre plaque d'immatriculation ou VIN</div>

        <div class="form-label" style="margin-bottom:6px;">IMMATRICULATION / VIN</div>
        <div style="display:flex;gap:10px;">
          <input
            v-model="vehiculeSearch"
            type="text"
            placeholder="Ex: AB-123-CD ou VIN"
            style="flex:1;padding:10px 14px;background:var(--dark3);border:1px solid rgba(255,255,255,0.08);border-radius:var(--radius-sm);color:#E8E9ED;font-size:14px;font-family:inherit;outline:none;"
            @blur="vehiculeSearch = normalizeVehiculeQuery(vehiculeSearch)"
            @keydown.enter.prevent="searchVehicule"
          />
        </div>
        <button
          class="topbar-new-btn"
          style="margin-top:12px;"
          @click="searchVehicule"
        >Rechercher mon véhicule</button>

        <div v-if="vehiculeFound" style="margin-top:16px;padding:12px 16px;background:rgba(16,185,129,0.06);border:1px solid rgba(16,185,129,0.2);border-radius:10px;">
          <div style="font-size:13px;color:#10B981;font-weight:600;margin-bottom:4px;">Véhicule trouvé</div>
          <div style="font-size:14px;color:#E8E9ED;font-weight:600;">{{ form.vehicule_marque }} {{ form.vehicule_modele }}</div>
          <div style="font-size:12px;color:#9CA3AF;">{{ form.vehicule_plaque }} — {{ form.vehicule_annee }}</div>
        </div>

        <div v-if="showManualVehicle" style="margin-top:16px;">
          <div style="font-size:13px;color:#6B7280;margin-bottom:12px;">Saisie manuelle du véhicule :</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div style="position:relative;">
              <div class="form-label" style="margin-bottom:4px;">MARQUE</div>
              <input v-model="form.vehicule_marque" type="text" placeholder="Ex: KAWASAKI" style="width:100%;padding:8px 12px;background:var(--dark3);border:1px solid rgba(255,255,255,0.08);border-radius:var(--radius-sm);color:#E8E9ED;font-size:14px;font-family:inherit;outline:none;" @input="onMarqueInput" @blur="deferHideMarqueSuggestions" />
              <div v-if="marqueSuggestions.length" style="position:absolute;left:0;right:0;top:100%;z-index:10;background:var(--dark3);border:1px solid rgba(255,255,255,0.08);border-radius:8px;max-height:150px;overflow-y:auto;">
                <div v-for="s in marqueSuggestions" :key="s" @mousedown.prevent="selectMarque(s)" style="padding:8px 12px;cursor:pointer;font-size:13px;color:#D1D5DB;border-bottom:1px solid rgba(255,255,255,0.04);">{{ s }}</div>
              </div>
            </div>
            <div style="position:relative;">
              <div class="form-label" style="margin-bottom:4px;">MODÈLE</div>
              <input v-model="form.vehicule_modele" type="text" placeholder="Ex: Z900" style="width:100%;padding:8px 12px;background:var(--dark3);border:1px solid rgba(255,255,255,0.08);border-radius:var(--radius-sm);color:#E8E9ED;font-size:14px;font-family:inherit;outline:none;" @input="onModeleInput" @blur="deferHideModeleSuggestions" />
              <div v-if="modeleSuggestions.length" style="position:absolute;left:0;right:0;top:100%;z-index:10;background:var(--dark3);border:1px solid rgba(255,255,255,0.08);border-radius:8px;max-height:150px;overflow-y:auto;">
                <div v-for="s in modeleSuggestions" :key="s" @mousedown.prevent="selectModele(s)" style="padding:8px 12px;cursor:pointer;font-size:13px;color:#D1D5DB;border-bottom:1px solid rgba(255,255,255,0.04);">{{ s }}</div>
              </div>
            </div>
            <div>
              <div class="form-label" style="margin-bottom:4px;">PLAQUE</div>
              <input v-model="form.vehicule_plaque" type="text" placeholder="AB-123-CD" style="width:100%;padding:8px 12px;background:var(--dark3);border:1px solid rgba(255,255,255,0.08);border-radius:var(--radius-sm);color:#E8E9ED;font-size:14px;font-family:inherit;outline:none;" @blur="normalizePlateField" />
            </div>
            <div>
              <div class="form-label" style="margin-bottom:4px;">ANNÉE</div>
              <input v-model="form.vehicule_annee" type="number" placeholder="2024" style="width:100%;padding:8px 12px;background:var(--dark3);border:1px solid rgba(255,255,255,0.08);border-radius:var(--radius-sm);color:#E8E9ED;font-size:14px;font-family:inherit;outline:none;" />
            </div>
            <div>
              <div class="form-label" style="margin-bottom:4px;">CYLINDRÉE</div>
              <input v-model="form.vehicule_cylindree" type="number" placeholder="900" style="width:100%;padding:8px 12px;background:var(--dark3);border:1px solid rgba(255,255,255,0.08);border-radius:var(--radius-sm);color:#E8E9ED;font-size:14px;font-family:inherit;outline:none;" />
            </div>
            <div>
              <div class="form-label" style="margin-bottom:4px;">TYPE MOTO</div>
              <select v-model="form.vehicule_type" style="width:100%;padding:8px 12px;background:var(--dark3);border:1px solid rgba(255,255,255,0.08);border-radius:var(--radius-sm);color:#E8E9ED;font-size:14px;font-family:inherit;outline:none;">
                <option value="">— Choisir —</option>
                <option v-for="t in motoTypes" :key="t" :value="t">{{ t }}</option>
              </select>
            </div>
          </div>
        </div>

        <div v-if="vehicleMissingFields.length" style="margin-top:16px;padding:12px 16px;background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.24);border-radius:10px;">
          <div style="font-size:12px;color:#FDE68A;font-weight:700;">CHAMPS OBLIGATOIRES À COMPLÉTER</div>
          <div style="font-size:12px;color:#E5E7EB;margin-top:4px;">{{ vehicleMissingFields.join(', ') }}</div>
        </div>

        <div v-if="!showManualVehicle && !vehiculeFound" style="margin-top:12px;">
          <button class="btn-link" @click="showManualVehicle = true">
            Saisie manuelle →
          </button>
        </div>
      </div>

      <div style="display:flex;justify-content:flex-end;gap:12px;margin-top:20px;">
        <button class="topbar-new-btn" @click="goStep(2)" :disabled="!canStep2">Suivant →</button>
      </div>
    </div>

    <!-- STEP 2 — Service -->
    <div v-if="step === 2" style="max-width:680px;">
      <div style="background:var(--dark2);border:1px solid var(--glass-border);border-radius:var(--radius-lg);padding:24px;">
        <div style="font-size:16px;font-weight:700;color:#E8E9ED;margin-bottom:4px;">Sélection des prestations</div>
        <div style="font-size:12px;color:#6B7280;margin-bottom:20px;">Choisissez une ou plusieurs prestations actives, filtrées selon le type de moto.</div>

        <div v-if="loadingPrestas" class="loading-shimmer" style="height:120px;border-radius:10px;"></div>

        <div v-else style="display:flex;flex-direction:column;gap:8px;">
          <div
            v-for="p in prestations"
            :key="p.id"
            style="display:flex;align-items:center;gap:12px;padding:12px 16px;border-radius:10px;cursor:pointer;transition:all 0.15s;"
            :style="{
              background: selectedPrestas.includes(p.id) ? 'rgba(255,210,0,0.06)' : 'rgba(255,255,255,0.02)',
              border: selectedPrestas.includes(p.id) ? '1px solid rgba(255,210,0,0.3)' : '1px solid rgba(255,255,255,0.06)',
            }"
            @click="togglePresta(p.id)"
          >
            <div style="width:20px;height:20px;border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:12px;"
              :style="{
                background: selectedPrestas.includes(p.id) ? '#FFD200' : 'rgba(255,255,255,0.06)',
                color: selectedPrestas.includes(p.id) ? '#111' : 'transparent',
              }"
            >✓</div>
            <div style="flex:1;">
              <div style="font-size:14px;font-weight:600;color:#E8E9ED;">{{ p.nom }}</div>
              <div style="font-size:12px;color:#6B7280;">{{ p.description || p.categorie || '' }}</div>
            </div>
            <div style="text-align:right;">
              <div style="font-size:14px;font-weight:700;color:#FFD200;">{{ formatPrice(p.prix_base_ttc ?? p.prix_base_ht) }}</div>
              <div style="font-size:11px;color:#6B7280;">{{ p.temps_estime_minutes ?? 60 }} min</div>
            </div>
          </div>
          <div v-if="!prestations.length" style="padding:20px;text-align:center;color:#6B7280;font-size:13px;">Aucune prestation active pour ce type de moto dans cet atelier.</div>
        </div>

        <!-- Recap -->
        <div v-if="selectedPrestas.length" style="margin-top:20px;padding:16px;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06);border-radius:10px;">
          <div style="font-size:13px;font-weight:700;color:#9CA3AF;margin-bottom:8px;">RÉCAPITULATIF</div>
          <div v-for="p in selectedPrestaItems" :key="p.id" style="display:flex;justify-content:space-between;font-size:13px;color:#D1D5DB;padding:4px 0;">
            <span>{{ p.nom }}</span>
            <span style="font-weight:600;">{{ formatPrice(p.prix_base_ttc ?? p.prix_base_ht) }}</span>
          </div>
          <div style="display:flex;justify-content:space-between;padding-top:8px;margin-top:8px;border-top:1px solid rgba(255,255,255,0.06);font-size:14px;font-weight:700;">
            <span style="color:#E8E9ED;">Total estimé</span>
            <span style="color:#FFD200;">{{ formatPrice(totalEstime) }}</span>
          </div>
          <div style="font-size:12px;color:#6B7280;margin-top:4px;">Durée estimée: {{ dureeEstimee }} min</div>
        </div>
      </div>

      <div style="display:flex;justify-content:space-between;gap:12px;margin-top:20px;">
        <button class="btn btn-ghost" @click="goStep(1)">← Retour</button>
        <button class="topbar-new-btn" @click="goStep(3)" :disabled="!selectedPrestas.length">Suivant →</button>
      </div>
    </div>

    <!-- STEP 3 — Créneau -->
    <div v-if="step === 3" style="max-width:1200px;">
      <div style="background:var(--dark2);border:1px solid var(--glass-border);border-radius:var(--radius-lg);padding:24px;">
        <div style="font-size:16px;font-weight:700;color:#E8E9ED;margin-bottom:4px;">Planning atelier sur 1 semaine</div>
        <div style="font-size:12px;color:#6B7280;margin-bottom:20px;">Vue planning réelle sur la semaine. Chaque case correspond à un créneau disponible en base, selon la configuration atelier.</div>

        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px;">
          <button class="btn btn-ghost" @click="changeWeek(-7)" :disabled="!canGoPrevWeek">← Semaine précédente</button>
          <div style="font-size:13px;color:#D1D5DB;font-weight:600;">{{ planningRangeLabel }}</div>
          <button class="btn btn-ghost" @click="changeWeek(7)">Semaine suivante →</button>
        </div>

        <div style="overflow-x:auto;border:1px solid rgba(255,255,255,0.06);border-radius:14px;">
          <div style="display:grid;grid-template-columns:80px repeat(7, minmax(120px, 1fr));min-width:980px;background:rgba(255,255,255,0.02);">
            <div style="padding:10px;border-bottom:1px solid rgba(255,255,255,0.06);"></div>
            <div
              v-for="day in planningDays"
              :key="`head-${day.date}`"
              style="padding:10px;border-left:1px solid rgba(255,255,255,0.06);border-bottom:1px solid rgba(255,255,255,0.06);text-align:center;"
              :style="{ background: form.date_rdv === day.date ? 'rgba(255,210,0,0.06)' : 'transparent' }"
            >
              <div style="font-size:11px;color:#9CA3AF;text-transform:uppercase;">{{ day.weekday }}</div>
              <div style="font-size:15px;font-weight:700;color:#E8E9ED;">{{ day.dayLabel }}</div>
              <div style="font-size:11px;margin-top:4px;" :style="{ color: day.availableCount ? '#86EFAC' : '#FCA5A5' }">
                {{ day.availableCount ? `${day.availableCount} dispo` : 'fermé / complet' }}
              </div>
            </div>

            <template v-for="time in planningTimeLabels" :key="time">
              <div style="padding:10px 8px;border-bottom:1px solid rgba(255,255,255,0.04);font-size:12px;color:#9CA3AF;font-weight:600;display:flex;align-items:center;justify-content:center;">
                {{ time }}
              </div>
              <div
                v-for="day in planningDays"
                :key="`${day.date}-${time}`"
                style="min-height:52px;padding:6px;border-left:1px solid rgba(255,255,255,0.04);border-bottom:1px solid rgba(255,255,255,0.04);display:flex;align-items:center;justify-content:center;"
                :style="{ background: form.date_rdv === day.date ? 'rgba(255,255,255,0.015)' : 'transparent' }"
              >
                <button
                  v-if="getSlotForCell(day.date, time)?.disponible"
                  type="button"
                  @click="selectPlanningSlot(day.date, getSlotForCell(day.date, time)!)"
                  style="width:100%;padding:8px 6px;border-radius:8px;font-size:12px;font-weight:700;transition:all 0.15s;"
                  :style="{
                    background: form.date_rdv === day.date && form.heure_debut === time ? 'rgba(255,210,0,0.16)' : 'rgba(16,185,129,0.10)',
                    border: form.date_rdv === day.date && form.heure_debut === time ? '1px solid rgba(255,210,0,0.45)' : '1px solid rgba(16,185,129,0.28)',
                    color: form.date_rdv === day.date && form.heure_debut === time ? '#FFD200' : '#A7F3D0'
                  }"
                >
                  {{ time }}
                </button>
                <span v-else style="font-size:11px;color:#4B5563;">—</span>
              </div>
            </template>
          </div>
        </div>

        <div style="margin-top:14px;padding:12px 16px;background:rgba(59,130,246,0.06);border:1px solid rgba(59,130,246,0.18);border-radius:10px;">
          <div style="font-size:12px;color:#BFDBFE;font-weight:700;">ASSIGNATION AUTO</div>
          <div style="font-size:12px;color:#CBD5E1;margin-top:4px;">Aucun choix manuel ici : seuls les ponts avec technicien affecté sont pris en compte, et les propositions reflètent la capacité réelle disponible.</div>
          <div style="font-size:12px;color:#CBD5E1;margin-top:4px;">Pour aujourd'hui, les créneaux commencent au minimum à H+2. La pause déjeuner reste bloquante et le rendez-vous reprend automatiquement après la pause si nécessaire.</div>
        </div>

        <div v-if="form.date_rdv && form.heure_debut" style="margin-top:16px;padding:12px 16px;background:rgba(255,210,0,0.04);border:1px solid rgba(255,210,0,0.15);border-radius:10px;">
          <div style="font-size:12px;color:#9CA3AF;font-weight:600;">CRÉNEAU SÉLECTIONNÉ</div>
          <div style="font-size:15px;font-weight:700;color:#E8E9ED;margin-top:4px;">
            {{ formatDisplayDate(form.date_rdv) }} à {{ form.heure_debut }}
          </div>
          <div style="font-size:12px;color:#6B7280;margin-top:2px;">Durée: {{ dureeEstimee }} min<span v-if="selectedSlotMeta?.heure_fin"> · Fin estimée {{ selectedSlotMeta.heure_fin }}</span></div>
          <div v-if="selectedSlotMeta?.pause_appliquee" style="font-size:12px;color:#FDE68A;margin-top:4px;">La pause atelier est déjà prise en compte dans l’horaire de fin.</div>
        </div>
      </div>

      <div style="display:flex;justify-content:space-between;gap:12px;margin-top:20px;">
        <button class="btn btn-ghost" @click="goStep(2)">← Retour</button>
        <button class="topbar-new-btn" @click="goStep(4)" :disabled="!form.date_rdv || !form.heure_debut">Suivant →</button>
      </div>
    </div>

    <!-- STEP 4 — Validation -->
    <div v-if="step === 4" style="max-width:680px;">
      <div style="background:var(--dark2);border:1px solid var(--glass-border);border-radius:var(--radius-lg);padding:24px;">
        <div style="font-size:16px;font-weight:700;color:#E8E9ED;margin-bottom:16px;">Récapitulatif du rendez-vous</div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
          <!-- Client -->
          <div style="padding:14px;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06);border-radius:10px;">
            <div style="font-size:11px;font-weight:700;color:#6B7280;letter-spacing:0.06em;text-transform:uppercase;margin-bottom:8px;">CLIENT</div>
            <div v-if="selectedClient" style="font-size:14px;color:#E8E9ED;font-weight:600;">{{ selectedClient.prenom }} {{ selectedClient.nom }}</div>
            <div v-else style="font-size:14px;color:#E8E9ED;font-weight:600;">{{ form.client_prenom }} {{ form.client_nom }}</div>
            <div style="font-size:12px;color:#9CA3AF;margin-top:2px;">{{ selectedClient?.telephone || form.client_telephone }}</div>
          </div>
          <!-- Véhicule -->
          <div style="padding:14px;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06);border-radius:10px;">
            <div style="font-size:11px;font-weight:700;color:#6B7280;letter-spacing:0.06em;text-transform:uppercase;margin-bottom:8px;">VÉHICULE</div>
            <div style="font-size:14px;color:#E8E9ED;font-weight:600;">{{ form.vehicule_marque }} {{ form.vehicule_modele }}</div>
            <div style="font-size:12px;color:#9CA3AF;margin-top:2px;">{{ form.vehicule_plaque }}</div>
          </div>
          <!-- Créneau -->
          <div style="padding:14px;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06);border-radius:10px;">
            <div style="font-size:11px;font-weight:700;color:#6B7280;letter-spacing:0.06em;text-transform:uppercase;margin-bottom:8px;">CRÉNEAU</div>
            <div style="font-size:14px;color:#E8E9ED;font-weight:600;">{{ formatDisplayDate(form.date_rdv) }}</div>
            <div style="font-size:12px;color:#9CA3AF;margin-top:2px;">{{ form.heure_debut }}<span v-if="selectedSlotMeta?.heure_fin"> → {{ selectedSlotMeta.heure_fin }}</span> — Durée {{ dureeEstimee }} min</div>
            <div v-if="selectedSlotMeta?.pause_appliquee" style="font-size:12px;color:#FDE68A;margin-top:4px;">Pause déjeuner incluse dans l'horaire de fin.</div>
            <div style="font-size:12px;color:#9CA3AF;margin-top:4px;">Lieu : {{ atelierDisplayName }}</div>
          </div>
          <!-- Services -->
          <div style="padding:14px;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06);border-radius:10px;">
            <div style="font-size:11px;font-weight:700;color:#6B7280;letter-spacing:0.06em;text-transform:uppercase;margin-bottom:8px;">SERVICES</div>
            <div v-for="p in selectedPrestaItems" :key="p.id" style="font-size:13px;color:#D1D5DB;">{{ p.nom }}</div>
            <div style="font-size:14px;color:#FFD200;font-weight:700;margin-top:4px;">{{ formatPrice(totalEstime) }}</div>
          </div>
        </div>

        <!-- Client info (mandatory completion) -->
        <div v-if="!selectedClient || clientMissingFields.length" style="margin-top:16px;">
          <div style="font-size:13px;font-weight:700;color:#9CA3AF;margin-bottom:10px;">INFORMATIONS CLIENT OBLIGATOIRES</div>
          <div v-if="clientMissingFields.length" style="margin-bottom:10px;padding:10px 12px;background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.24);border-radius:8px;font-size:12px;color:#FDE68A;">
            Merci de compléter : {{ clientMissingFields.join(', ') }}.
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div>
              <div class="form-label" style="margin-bottom:4px;">PRÉNOM</div>
              <input v-model="form.client_prenom" type="text" required style="width:100%;padding:8px 12px;background:var(--dark3);border:1px solid rgba(255,255,255,0.08);border-radius:var(--radius-sm);color:#E8E9ED;font-size:14px;font-family:inherit;outline:none;" />
            </div>
            <div>
              <div class="form-label" style="margin-bottom:4px;">NOM</div>
              <input v-model="form.client_nom" type="text" required style="width:100%;padding:8px 12px;background:var(--dark3);border:1px solid rgba(255,255,255,0.08);border-radius:var(--radius-sm);color:#E8E9ED;font-size:14px;font-family:inherit;outline:none;" />
            </div>
            <div>
              <div class="form-label" style="margin-bottom:4px;">TÉLÉPHONE</div>
              <input v-model="form.client_telephone" type="tel" required style="width:100%;padding:8px 12px;background:var(--dark3);border:1px solid rgba(255,255,255,0.08);border-radius:var(--radius-sm);color:#E8E9ED;font-size:14px;font-family:inherit;outline:none;" />
            </div>
            <div>
              <div class="form-label" style="margin-bottom:4px;">EMAIL</div>
              <input v-model="form.client_email" type="email" required style="width:100%;padding:8px 12px;background:var(--dark3);border:1px solid rgba(255,255,255,0.08);border-radius:var(--radius-sm);color:#E8E9ED;font-size:14px;font-family:inherit;outline:none;" />
            </div>
          </div>
        </div>

        <!-- Remarques -->
        <div style="margin-top:16px;">
          <div class="form-label" style="margin-bottom:6px;">REMARQUES</div>
          <textarea v-model="form.description_probleme" rows="3" placeholder="Notes ou description du problème..." style="width:100%;padding:10px 14px;background:var(--dark3);border:1px solid rgba(255,255,255,0.08);border-radius:var(--radius-sm);color:#E8E9ED;font-size:14px;font-family:inherit;outline:none;resize:vertical;"></textarea>
        </div>
      </div>

      <div style="display:flex;justify-content:space-between;gap:12px;margin-top:20px;">
        <button class="btn btn-ghost" @click="goStep(3)">← Retour</button>
        <button class="topbar-new-btn" :disabled="submitting || !canConfirm" @click="confirmRdv" style="padding:10px 24px;font-size:14px;">
          {{ submitting ? 'Création...' : 'Confirmer le rendez-vous' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
const api = useApi()
const rdvStore = useRdvStore()
const toast = useToast()
const atelierStore = useAtelierStore()
const authStore = useAuthStore()

type AtelierOption = {
  id: number
  nom: string
  adresse?: string | null
  ville?: string | null
}

const atelierOptions = ref<AtelierOption[]>([])
const selectedAtelierId = ref<number | null>(authStore.user?.atelier_id ?? null)
const selectedAtelier = computed(() => atelierOptions.value.find(atelier => atelier.id === selectedAtelierId.value) || null)
const canSelectAtelier = computed(() => atelierOptions.value.length > 1)
const atelierDisplayName = computed(() => {
  return selectedAtelier.value?.nom?.trim() || atelierStore.branding?.nom?.trim() || authStore.user?.atelier_nom?.trim() || 'Atelier Moto'
})

const step = ref(1)
const stepLabels = ['Véhicule', 'Service', 'Créneau', 'Validation']
const submitting = ref(false)

// Client
const clientSearch = ref('')
const clientResults = ref<any[]>([])
const selectedClient = ref<any>(null)

// Vehicle
const vehiculeSearch = ref('')
const vehiculeFound = ref(false)
const showManualVehicle = ref(false)
const motoTypes = ['Roadster', 'Sportive', 'Trail', 'Custom', 'Scooter', 'Enduro']
const marqueSuggestions = ref<string[]>([])
const modeleSuggestions = ref<string[]>([])
const allMarques = ref<string[]>([])

type SlotItem = {
  heure: string
  heure_fin?: string | null
  pause_appliquee?: boolean
  disponible: boolean
  pont_id?: number | null
  mecanicien_id?: number | null
}

// Créneaux
const creneauxList = ref<SlotItem[]>([])
const creneauxByDate = ref<Record<string, SlotItem[]>>({})
const loadingCreneaux = ref(false)
const weekStart = ref(new Date().toISOString().slice(0, 10))

// Prestations
const loadingPrestas = ref(false)
const prestations = ref<any[]>([])
const selectedPrestas = ref<number[]>([])

const todayStr = new Date().toISOString().slice(0, 10)

function asNumber(value: unknown): number {
  const n = Number(value ?? 0)
  return Number.isFinite(n) ? n : 0
}

function extractCollection<T = any>(data: any): T[] {
  return data?.['hydra:member'] ?? data?.member ?? (Array.isArray(data) ? data : [])
}

function normalizePrestation(p: any) {
  return {
    ...p,
    prix_base_ht: asNumber(p.prix_base_ht ?? p.prixBaseHt),
    prix_base_ttc: asNumber(p.prix_base_ttc ?? p.prixBaseTtc),
    temps_estime_minutes: asNumber(p.temps_estime_minutes ?? p.tempsEstimeMinutes) || 60,
    is_active: p.is_active ?? p.isActive ?? true,
    type_tarif: p.type_tarif ?? p.typeTarif ?? 'forfait',
  }
}

const form = reactive({
  atelier_id: authStore.user?.atelier_id ?? null as number | null,
  client_id: null as number | null,
  client_prenom: '',
  client_nom: '',
  client_telephone: '',
  client_email: '',
  vehicule_marque: '',
  vehicule_modele: '',
  vehicule_plaque: '',
  vehicule_annee: '',
  vehicule_cylindree: '',
  vehicule_type: '',
  date_rdv: todayStr,
  heure_debut: '09:00',
  type_intervention: 'entretien',
  duree_estimee: 60,
  pont_id: null as number | null,
  mecanicien_id: null as number | null,
  description_probleme: '',
})

const vehicleMissingFields = computed(() => {
  const required = [
    ['vehicule_marque', 'marque'],
    ['vehicule_modele', 'modèle'],
    ['vehicule_plaque', 'plaque'],
    ['vehicule_annee', 'année'],
    ['vehicule_cylindree', 'cylindrée'],
    ['vehicule_type', 'type moto'],
  ] as const

  return required
    .filter(([key]) => !String(form[key] ?? '').trim())
    .map(([, label]) => label)
})

const clientMissingFields = computed(() => {
  const required = [
    ['client_prenom', 'prénom'],
    ['client_nom', 'nom'],
    ['client_telephone', 'téléphone'],
    ['client_email', 'email'],
  ] as const

  return required
    .filter(([key]) => !String(form[key] ?? '').trim())
    .map(([, label]) => label)
})

const canStep2 = computed(() => vehicleMissingFields.value.length === 0)
const canConfirm = computed(() => canStep2.value && selectedPrestas.value.length > 0 && !!form.date_rdv && !!form.heure_debut && clientMissingFields.value.length === 0)

const selectedPrestaItems = computed(() => prestations.value.filter(p => selectedPrestas.value.includes(p.id)))
const totalEstime = computed(() => selectedPrestaItems.value.reduce((s, p) => s + asNumber(p.prix_base_ttc ?? p.prix_base_ht), 0))
const dureeEstimee = computed(() => selectedPrestaItems.value.reduce((s, p) => s + asNumber(p.temps_estime_minutes ?? 60), 0) || form.duree_estimee)
const selectedSlotMeta = computed(() => creneauxList.value.find(c => c.heure === form.heure_debut) || null)
const planningDays = computed(() => {
  const start = new Date(`${weekStart.value}T00:00:00`)
  return Array.from({ length: 7 }, (_, index) => {
    const current = new Date(start)
    current.setDate(start.getDate() + index)
    const date = current.toISOString().slice(0, 10)
    const availableCount = (creneauxByDate.value[date] || []).filter(slot => slot.disponible).length
    return {
      date,
      weekday: current.toLocaleDateString('fr-FR', { weekday: 'short' }),
      dayLabel: current.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' }),
      availableCount,
    }
  })
})
const planningRangeLabel = computed(() => {
  const dates = planningDays.value
  if (!dates.length) return ''
  const start = new Date(`${dates[0].date}T00:00:00`)
  const end = new Date(`${dates[dates.length - 1].date}T00:00:00`)
  return `${start.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long' })} → ${end.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })}`
})
const planningTimeLabels = computed(() => {
  const merged = Array.from(new Set(Object.values(creneauxByDate.value).flat().map(slot => slot.heure))).sort((a, b) => a.localeCompare(b))
  return merged.length ? merged : getDefaultPlanningHours()
})
const canGoPrevWeek = computed(() => weekStart.value > todayStr)

function formatPrice(v: number | string) {
  const amount = asNumber(v)
  if (!amount) return '0,00 €'
  return amount.toFixed(2).replace('.', ',') + ' €'
}

function formatDisplayDate(d: string) {
  if (!d) return ''
  const date = new Date(d + 'T00:00:00')
  return date.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
}

function normalizeText(value: unknown) {
  return String(value ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim()
}

function sanitizeAlphaNum(value: string) {
  return normalizeText(value).toUpperCase().replace(/[^A-Z0-9]/g, '')
}

function looksLikeVin(value: string) {
  return sanitizeAlphaNum(value).length >= 11
}

function formatRegistrationOrVin(value: string) {
  const cleaned = sanitizeAlphaNum(value)
  if (!cleaned) return ''
  if (looksLikeVin(cleaned)) return cleaned
  if (/^[A-Z]{2}\d{3}[A-Z]{2}$/.test(cleaned)) {
    return `${cleaned.slice(0, 2)}-${cleaned.slice(2, 5)}-${cleaned.slice(5, 7)}`
  }
  return cleaned
}

function normalizePlateField() {
  form.vehicule_plaque = formatRegistrationOrVin(form.vehicule_plaque)
}

function prestationMatchesVehicle(prestation: any) {
  const atelierId = Number(selectedAtelierId.value ?? form.atelier_id ?? 0)
  const prestationAtelierId = Number(prestation.atelier_id ?? prestation.atelierId ?? 0)
  const atelierMatches = !atelierId || !prestationAtelierId || prestationAtelierId === atelierId
  if (!atelierMatches) return false

  const vehicleType = normalizeText(form.vehicule_type)
  const rawType = normalizeText(prestation.type_vehicule ?? prestation.typeVehicule ?? 'tous')
  const allowedTypes = rawType.split(/[;,/|]+/).map((item: string) => item.trim()).filter(Boolean)
  const typeMatches = !vehicleType || !allowedTypes.length || allowedTypes.includes('tous') || allowedTypes.includes('tout') || allowedTypes.includes('all') || allowedTypes.some((item: string) => item === vehicleType || item.includes(vehicleType) || vehicleType.includes(item))

  const cylindree = asNumber(form.vehicule_cylindree)
  const min = asNumber(prestation.cylindree_min ?? prestation.cylindreeMin)
  const max = asNumber(prestation.cylindree_max ?? prestation.cylindreeMax)
  const cylindreeMatches = (!min || !cylindree || cylindree >= min) && (!max || !cylindree || cylindree <= max)

  return typeMatches && cylindreeMatches
}

async function loadAteliers() {
  try {
    const data = await api.get('/ateliers').catch(() => null)
    const items = extractCollection<AtelierOption>(data)
    atelierOptions.value = items
      .filter((atelier: any) => atelier?.actif !== false && atelier?.actif !== 0)
      .map((atelier: any) => ({
        id: Number(atelier.id),
        nom: String(atelier.nom ?? `Atelier ${atelier.id}`),
        adresse: atelier.adresse ?? null,
        ville: atelier.ville ?? null,
      }))

    if (!atelierOptions.value.length && authStore.user?.atelier_id) {
      atelierOptions.value = [{ id: authStore.user.atelier_id, nom: authStore.user.atelier_nom || 'Atelier Moto' }]
    }

    if (!atelierOptions.value.some(atelier => atelier.id === selectedAtelierId.value)) {
      selectedAtelierId.value = atelierOptions.value[0]?.id ?? authStore.user?.atelier_id ?? null
    }
    form.atelier_id = selectedAtelierId.value
  } catch {
    atelierOptions.value = authStore.user?.atelier_id
      ? [{ id: authStore.user.atelier_id, nom: authStore.user.atelier_nom || 'Atelier Moto' }]
      : []
  }
}

function goStep(n: number) {
  step.value = n
  if (n === 2 && !prestations.value.length) loadPrestations()
  if (n === 3) {
    weekStart.value = startOfWeek(form.date_rdv || todayStr)
    loadCreneaux()
  }
}

function addDays(dateStr: string, days: number) {
  const date = new Date(`${dateStr}T00:00:00`)
  date.setDate(date.getDate() + days)
  return date.toISOString().slice(0, 10)
}

function startOfWeek(dateStr: string) {
  const date = new Date(`${dateStr}T00:00:00`)
  const day = date.getDay()
  const diff = day === 0 ? -6 : 1 - day
  date.setDate(date.getDate() + diff)
  return date.toISOString().slice(0, 10)
}

function getDefaultPlanningHours() {
  const hours: string[] = []
  for (let h = 8; h <= 18; h++) {
    for (const m of ['00', '30']) {
      if (h === 18 && m === '30') continue
      hours.push(`${String(h).padStart(2, '0')}:${m}`)
    }
  }
  return hours
}

function normalizeSlots(raw: any): SlotItem[] {
  const list = Array.isArray(raw) ? raw : []
  const seen = new Set<string>()
  return list
    .map((slot: any) => ({
      heure: typeof slot === 'string' ? slot : slot?.heure || slot?.time || '',
      heure_fin: typeof slot === 'string' ? null : slot?.heure_fin || null,
      pause_appliquee: typeof slot === 'string' ? false : !!slot?.pause_appliquee,
      disponible: typeof slot === 'string' ? true : slot?.disponible !== false && slot?.available !== false,
      pont_id: typeof slot === 'string' ? null : slot?.pont_id ?? null,
      mecanicien_id: typeof slot === 'string' ? null : slot?.mecanicien_id ?? null,
    }))
    .filter((slot) => {
      if (!slot.heure || seen.has(slot.heure)) return false
      seen.add(slot.heure)
      return true
    })
    .sort((a, b) => a.heure.localeCompare(b.heure))
}

function getSlotForCell(date: string, heure: string) {
  return (creneauxByDate.value[date] || []).find(slot => slot.heure === heure) || null
}

function syncSelectedDaySlots() {
  creneauxList.value = creneauxByDate.value[form.date_rdv] || []
  const selected = creneauxList.value.find(slot => slot.heure === form.heure_debut && slot.disponible)
    || creneauxList.value.find(slot => slot.disponible)

  if (selected) {
    selectCreneau(selected)
  } else {
    form.heure_debut = ''
    form.pont_id = null
    form.mecanicien_id = null
  }
}

function changeWeek(days: number) {
  const candidate = addDays(weekStart.value, days)
  if (candidate < todayStr) return
  weekStart.value = candidate
  form.date_rdv = candidate
  loadCreneaux()
}

function selectPlanningDay(date: string) {
  form.date_rdv = date
  syncSelectedDaySlots()
}

function selectPlanningSlot(date: string, slot: SlotItem) {
  form.date_rdv = date
  creneauxList.value = creneauxByDate.value[date] || []
  selectCreneau(slot)
}

// Client search
let searchTimeout: ReturnType<typeof setTimeout>
function searchClients() {
  clearTimeout(searchTimeout)
  if (clientSearch.value.length < 2) { clientResults.value = []; return }
  searchTimeout = setTimeout(async () => {
    const data = await api.get(`/clients?search=${encodeURIComponent(clientSearch.value.trim())}`)
    clientResults.value = data?.['hydra:member'] ?? data?.member ?? (Array.isArray(data) ? data : [])
  }, 300)
}

function selectClient(c: any) {
  selectedClient.value = c
  form.client_id = c.id
  form.client_prenom = c.prenom || ''
  form.client_nom = c.nom || ''
  form.client_telephone = c.telephone || ''
  form.client_email = c.email || ''
  clientResults.value = []

  if (c.vehicules?.length) {
    const v = c.vehicules[0]
    form.vehicule_marque = v.marque || ''
    form.vehicule_modele = v.modele || ''
    form.vehicule_plaque = formatRegistrationOrVin(v.plaque || '')
    form.vehicule_annee = v.annee || ''
    form.vehicule_cylindree = v.cylindree || ''
    form.vehicule_type = v.type_moto || v.univers || ''
    vehiculeFound.value = true
    showManualVehicle.value = vehicleMissingFields.value.length > 0
  }

  if (clientMissingFields.value.length) {
    toast.add({ title: 'Compléter le client', description: `Champs manquants : ${clientMissingFields.value.join(', ')}`, color: 'warning' })
  }
}

function clearClient() {
  selectedClient.value = null
  form.client_id = null
  form.client_prenom = ''
  form.client_nom = ''
  form.client_telephone = ''
  form.client_email = ''
}

// Vehicle search
function normalizeVehiculeQuery(value: string) {
  return formatRegistrationOrVin(value)
}

async function searchVehicule() {
  const query = normalizeVehiculeQuery(vehiculeSearch.value)
  if (!query) {
    showManualVehicle.value = true
    vehiculeFound.value = false
    return
  }

  vehiculeSearch.value = query

  try {
    let data: any = null

    try {
      data = await api.get(`/vehicule/${encodeURIComponent(query)}`)
    } catch {
      const collection = await api.get(`/vehicules?plaque=${encodeURIComponent(query)}`).catch(() => null)
      const items = collection?.['hydra:member'] ?? collection?.member ?? (Array.isArray(collection) ? collection : [])
      data = items[0] ?? null
    }

    if (data && (data.marque || data.modele || data.plaque)) {
      form.vehicule_marque = data.marque || ''
      form.vehicule_modele = data.modele || ''
      form.vehicule_plaque = formatRegistrationOrVin(data.plaque || query)
      form.vehicule_annee = data.annee || ''
      form.vehicule_cylindree = data.cylindree || ''
      form.vehicule_type = data.type_moto || data.typeVehicule || data.univers || ''
      vehiculeFound.value = true
      showManualVehicle.value = vehicleMissingFields.value.length > 0

      if (vehicleMissingFields.value.length) {
        toast.add({ title: 'Compléter le véhicule', description: `Champs manquants : ${vehicleMissingFields.value.join(', ')}`, color: 'warning' })
      }
    } else {
      vehiculeFound.value = false
      showManualVehicle.value = true
      form.vehicule_plaque = formatRegistrationOrVin(query)
    }
  } catch {
    vehiculeFound.value = false
    showManualVehicle.value = true
    form.vehicule_plaque = formatRegistrationOrVin(query)
  }
}

// Prestations
async function loadPrestations() {
  loadingPrestas.value = true
  try {
    const data = await api.get('/prestations').catch(() => null)
    const items = extractCollection(data)
    prestations.value = items
      .map((p: any) => normalizePrestation(p))
      .filter((p: any) => p.is_active !== false && p.is_active !== 0)
      .filter((p: any) => prestationMatchesVehicle(p))

    selectedPrestas.value = selectedPrestas.value.filter(id => prestations.value.some(p => p.id === id))
  } finally {
    loadingPrestas.value = false
  }
}

function togglePresta(id: number) {
  const idx = selectedPrestas.value.indexOf(id)
  if (idx >= 0) selectedPrestas.value.splice(idx, 1)
  else selectedPrestas.value.push(id)
}

// Moto base autocomplete
let marqueTimeout: ReturnType<typeof setTimeout>
function onMarqueInput() {
  clearTimeout(marqueTimeout)
  marqueTimeout = setTimeout(async () => {
    const q = form.vehicule_marque.trim()
    if (q.length < 1) { marqueSuggestions.value = []; return }
    if (!allMarques.value.length) {
      try {
        const data = await api.get('/motos/marques')
        allMarques.value = Array.isArray(data) ? data : (data?.marques ?? [])
      } catch { allMarques.value = [] }
    }
    marqueSuggestions.value = allMarques.value.filter(m => m.toLowerCase().includes(q.toLowerCase())).slice(0, 8)
  }, 200)
}

function selectMarque(m: string) {
  form.vehicule_marque = m
  marqueSuggestions.value = []
  modeleSuggestions.value = []
}

function selectModele(modele: string) {
  form.vehicule_modele = modele
  modeleSuggestions.value = []
}

function deferHideMarqueSuggestions() {
  setTimeout(() => {
    marqueSuggestions.value = []
  }, 200)
}

function deferHideModeleSuggestions() {
  setTimeout(() => {
    modeleSuggestions.value = []
  }, 200)
}

let modeleTimeout: ReturnType<typeof setTimeout>
function onModeleInput() {
  clearTimeout(modeleTimeout)
  modeleTimeout = setTimeout(async () => {
    const q = form.vehicule_modele.trim()
    if (q.length < 1 || !form.vehicule_marque) { modeleSuggestions.value = []; return }
    try {
      const data = await api.get(`/motos/autocomplete?marque=${encodeURIComponent(form.vehicule_marque)}&query=${encodeURIComponent(q)}&limit=10`)
      modeleSuggestions.value = Array.isArray(data) ? data.map((d: any) => typeof d === 'string' ? d : d.modele || d.nom) : []
    } catch { modeleSuggestions.value = [] }
  }, 250)
}

// Créneaux disponibles
async function loadCreneaux() {
  loadingCreneaux.value = true
  try {
    const duree = dureeEstimee.value || 60
    const start = startOfWeek(weekStart.value || form.date_rdv || todayStr)
    weekStart.value = start
    const end = addDays(start, 6)
    const atelierParam = selectedAtelierId.value ? `&atelier_id=${selectedAtelierId.value}` : ''
    const slotsData = await api.get(`/slots?date_debut=${start}&date_fin=${end}&temps_minutes=${duree}${atelierParam}`)
    const byDate = Array.isArray(slotsData) ? { [start]: slotsData } : (slotsData || {})

    creneauxByDate.value = Object.fromEntries(
      planningDays.value.map((day) => [day.date, normalizeSlots(byDate[day.date] || [])]),
    )

    const preferredDate = form.date_rdv && creneauxByDate.value[form.date_rdv]?.length ? form.date_rdv : ''
    form.date_rdv = preferredDate || planningDays.value.find(day => day.availableCount > 0)?.date || start
    syncSelectedDaySlots()
  } catch {
    creneauxByDate.value = Object.fromEntries(planningDays.value.map((day) => [day.date, []]))
    creneauxList.value = []
    form.heure_debut = ''
  } finally {
    loadingCreneaux.value = false
  }
}

function selectCreneau(c: SlotItem) {
  if (!c.disponible) return
  form.heure_debut = c.heure
  form.pont_id = c.pont_id ?? null
  form.mecanicien_id = c.mecanicien_id ?? null
}

watch(dureeEstimee, (value, previousValue) => {
  const normalizedDuration = Number(value) || 60
  form.duree_estimee = normalizedDuration

  if (step.value >= 3 && form.date_rdv && normalizedDuration !== previousValue) {
    loadCreneaux()
  }
}, { immediate: true })

watch(selectedAtelierId, (value) => {
  form.atelier_id = value ?? authStore.user?.atelier_id ?? null
  if (step.value >= 2) loadPrestations()
  if (step.value >= 3) loadCreneaux()
}, { immediate: true })

watch(() => `${form.vehicule_type}|${form.vehicule_cylindree}`, () => {
  if (step.value >= 2) loadPrestations()
})

// Submit
async function confirmRdv() {
  submitting.value = true
  try {
    normalizePlateField()

    if (vehicleMissingFields.value.length) {
      throw new Error(`Véhicule incomplet : ${vehicleMissingFields.value.join(', ')}`)
    }

    if (clientMissingFields.value.length) {
      throw new Error(`Client incomplet : ${clientMissingFields.value.join(', ')}`)
    }

    const typeIntervention = selectedPrestaItems.value.map(p => p.nom).join(', ') || form.type_intervention
    const atelierId = selectedAtelierId.value ?? form.atelier_id ?? authStore.user?.atelier_id ?? null
    const payload = {
      ...form,
      atelier_id: atelierId,
      atelierId,
      vehicule_plaque: formatRegistrationOrVin(form.vehicule_plaque),
      client_prenom: form.client_prenom.trim(),
      client_nom: form.client_nom.trim(),
      client_telephone: form.client_telephone.trim(),
      client_email: form.client_email.trim(),
      type_intervention: typeIntervention,
      duree_estimee: dureeEstimee.value,
      temps_estime: dureeEstimee.value,
      prix_estime: totalEstime.value,
    }
    const rdv = await rdvStore.createRdv(payload)
    toast.add({ title: 'RDV créé avec succès', color: 'success' })
    navigateTo(`/rdv/${rdv.id}`)
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e.message, color: 'error' })
  } finally {
    submitting.value = false
  }
}

onMounted(async () => {
  await loadAteliers()
  if (step.value >= 3) {
    await loadCreneaux()
  }
})
</script>
