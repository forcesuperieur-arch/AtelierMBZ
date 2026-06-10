<template>
  <div class="public-card">
    <div class="public-card-header">
      <div style="margin-bottom:8px;">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#FFD200" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
          <line x1="16" y1="2" x2="16" y2="6" />
          <line x1="8" y1="2" x2="8" y2="6" />
          <line x1="3" y1="10" x2="21" y2="10" />
        </svg>
      </div>
      <h1 class="text-gradient" style="font-size:22px;font-weight:800;">Réserver un rendez-vous</h1>
      <p style="font-size:13px;color:#6B7280;margin-top:4px;">Parcours public complet avec estimation et créneaux réels</p>
    </div>

    <!-- Error banner -->
    <div v-if="errorMessage" style="margin-bottom:16px;padding:12px 14px;background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);border-radius:12px;color:#FCA5A5;font-size:13px;">
      {{ errorMessage }}
    </div>

    <!-- Atelier selection -->
    <div style="margin-bottom:18px;padding:14px;border:1px solid rgba(255,255,255,0.06);border-radius:12px;background:rgba(255,255,255,0.02);">
      <div style="font-size:13px;font-weight:800;color:#E8E9ED;margin-bottom:8px;">Lieu du rendez-vous</div>
      <div v-if="atelierOptions.length > 1">
        <select
          v-model.number="selectedAtelierId"
          style="width:100%;padding:10px 14px;background:#151621;border:1px solid rgba(255,255,255,0.08);border-radius:10px;color:#E8E9ED;font-size:14px;font-family:inherit;outline:none;"
        >
          <option v-for="atelier in atelierOptions" :key="atelier.id" :value="atelier.id">{{ atelier.nom }}</option>
        </select>
      </div>
      <div v-else style="padding:10px 14px;background:#151621;border:1px solid rgba(255,255,255,0.08);border-radius:10px;color:#D1D5DB;font-size:14px;">
        {{ atelierDisplayName }}
      </div>
    </div>

    <!-- Booking disabled -->
    <div v-if="!bookingEnabled && atelierSelected" style="padding:24px;border:1px solid rgba(255,255,255,0.06);border-radius:12px;background:rgba(255,255,255,0.02);text-align:center;">
      <div style="margin-bottom:8px;">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#FCA5A5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
          <path d="M7 11V7a5 5 0 0 1 10 0v4" />
        </svg>
      </div>
      <div style="font-size:15px;font-weight:700;color:#E8E9ED;margin-bottom:6px;">Prise de rendez-vous désactivée</div>
      <div style="font-size:13px;color:#9CA3AF;">La prise de rendez-vous en ligne est temporairement désactivée pour cet atelier. Merci de nous contacter directement.</div>
    </div>

    <!-- Wizard Stepper -->
    <div v-else-if="!confirmation" class="steps" style="margin-bottom:28px;">
      <div class="step-item" v-for="(s, i) in stepLabels" :key="i">
        <div class="step-dot" :class="{ active: step === i + 1, done: step > i + 1 }">
          <svg v-if="step > i + 1" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
          <span v-else>{{ i + 1 }}</span>
        </div>
        <div class="step-label" :style="step === i + 1 ? 'color:#FFD200;' : ''">{{ s }}</div>
        <div v-if="i < stepLabels.length - 1" class="step-connector" :class="{ done: step > i + 1 }"></div>
      </div>
    </div>

    <!-- STEP 1 — Véhicule -->
    <div v-if="step === 1 && !confirmation && bookingEnabled" style="display:flex;flex-direction:column;gap:18px;">
      <div style="padding:14px;border:1px solid rgba(255,255,255,0.06);border-radius:12px;background:rgba(255,255,255,0.02);">
        <div style="font-size:13px;font-weight:800;color:#E8E9ED;margin-bottom:12px;">1. Votre moto</div>

        <!-- Vehicle search -->
        <div class="form-label" style="margin-bottom:6px;color:#9CA3AF;font-size:12px;font-weight:600;letter-spacing:0.04em;text-transform:uppercase;">Immatriculation / VIN</div>
        <div style="display:flex;gap:10px;">
          <input
            v-model="vehiculeSearch"
            type="text"
            placeholder="Ex: AB-123-CD ou VIN"
            style="flex:1;padding:10px 14px;background:#151621;border:1px solid rgba(255,255,255,0.08);border-radius:10px;color:#E8E9ED;font-size:14px;font-family:inherit;outline:none;"
            @blur="vehiculeSearch = normalizeVehiculeQuery(vehiculeSearch)"
            @keydown.enter.prevent="searchVehicule"
          />
        </div>
        <button class="topbar-new-btn" style="margin-top:12px;" @click="searchVehicule">Rechercher mon véhicule</button>

        <div v-if="vehiculeFound" style="margin-top:16px;padding:12px 16px;background:rgba(16,185,129,0.06);border:1px solid rgba(16,185,129,0.2);border-radius:10px;">
          <div style="font-size:13px;color:#10B981;font-weight:600;margin-bottom:4px;">Véhicule trouvé</div>
          <div style="font-size:14px;color:#E8E9ED;font-weight:600;">{{ form.vehicule_marque }} {{ form.vehicule_modele }}</div>
          <div style="font-size:12px;color:#9CA3AF;">{{ form.vehicule_plaque }} — {{ form.vehicule_annee }}</div>
        </div>

        <div v-if="showManualVehicle" style="margin-top:16px;">
          <div style="font-size:13px;color:#6B7280;margin-bottom:12px;">Saisie manuelle du véhicule :</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div style="position:relative;">
              <div class="form-label" style="margin-bottom:4px;color:#9CA3AF;font-size:12px;font-weight:600;letter-spacing:0.04em;text-transform:uppercase;">Marque</div>
              <input v-model="form.vehicule_marque" type="text" placeholder="Ex: KAWASAKI" style="width:100%;padding:8px 12px;background:#151621;border:1px solid rgba(255,255,255,0.08);border-radius:10px;color:#E8E9ED;font-size:14px;font-family:inherit;outline:none;" @input="onMarqueInput" @blur="deferHideMarqueSuggestions" />
              <div v-if="marqueSuggestions.length" style="position:absolute;left:0;right:0;top:100%;z-index:10;background:#151621;border:1px solid rgba(255,255,255,0.08);border-radius:8px;max-height:150px;overflow-y:auto;">
                <div v-for="s in marqueSuggestions" :key="s" @mousedown.prevent="selectMarque(s)" style="padding:8px 12px;cursor:pointer;font-size:13px;color:#D1D5DB;border-bottom:1px solid rgba(255,255,255,0.04);">{{ s }}</div>
              </div>
            </div>
            <div style="position:relative;">
              <div class="form-label" style="margin-bottom:4px;color:#9CA3AF;font-size:12px;font-weight:600;letter-spacing:0.04em;text-transform:uppercase;">Modèle</div>
              <input v-model="form.vehicule_modele" type="text" placeholder="Ex: Z900" style="width:100%;padding:8px 12px;background:#151621;border:1px solid rgba(255,255,255,0.08);border-radius:10px;color:#E8E9ED;font-size:14px;font-family:inherit;outline:none;" @input="onModeleInput" @blur="deferHideModeleSuggestions" />
              <div v-if="modeleSuggestions.length" style="position:absolute;left:0;right:0;top:100%;z-index:10;background:#151621;border:1px solid rgba(255,255,255,0.08);border-radius:8px;max-height:150px;overflow-y:auto;">
                <div v-for="s in modeleSuggestions" :key="s.id || s.modele || s" @mousedown.prevent="selectModele(s)" style="padding:8px 12px;cursor:pointer;font-size:13px;color:#D1D5DB;border-bottom:1px solid rgba(255,255,255,0.04);">{{ typeof s === 'string' ? s : [s.modele, s.categorie_nom, s.cylindree_display || s.cylindree].filter(Boolean).join(' • ') }}</div>
              </div>
            </div>
            <div>
              <div class="form-label" style="margin-bottom:4px;color:#9CA3AF;font-size:12px;font-weight:600;letter-spacing:0.04em;text-transform:uppercase;">Plaque</div>
              <input v-model="form.vehicule_plaque" type="text" placeholder="AB-123-CD" style="width:100%;padding:8px 12px;background:#151621;border:1px solid rgba(255,255,255,0.08);border-radius:10px;color:#E8E9ED;font-size:14px;font-family:inherit;outline:none;" @blur="normalizePlateField" />
            </div>
            <div>
              <div class="form-label" style="margin-bottom:4px;color:#9CA3AF;font-size:12px;font-weight:600;letter-spacing:0.04em;text-transform:uppercase;">Année</div>
              <input v-model="form.vehicule_annee" type="number" placeholder="2024" style="width:100%;padding:8px 12px;background:#151621;border:1px solid rgba(255,255,255,0.08);border-radius:10px;color:#E8E9ED;font-size:14px;font-family:inherit;outline:none;" />
            </div>
            <div>
              <div class="form-label" style="margin-bottom:4px;color:#9CA3AF;font-size:12px;font-weight:600;letter-spacing:0.04em;text-transform:uppercase;">Cylindrée</div>
              <input v-model="form.vehicule_cylindree" type="number" placeholder="900" style="width:100%;padding:8px 12px;background:#151621;border:1px solid rgba(255,255,255,0.08);border-radius:10px;color:#E8E9ED;font-size:14px;font-family:inherit;outline:none;" />
            </div>
            <div>
              <div class="form-label" style="margin-bottom:4px;color:#9CA3AF;font-size:12px;font-weight:600;letter-spacing:0.04em;text-transform:uppercase;">Type moto</div>
              <select v-model="form.vehicule_type" style="width:100%;padding:8px 12px;background:#151621;border:1px solid rgba(255,255,255,0.08);border-radius:10px;color:#E8E9ED;font-size:14px;font-family:inherit;outline:none;">
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
          <button style="background:none;border:none;color:#9CA3AF;font-size:13px;cursor:pointer;text-decoration:underline;" @click="showManualVehicle = true">Saisie manuelle →</button>
        </div>
      </div>

      <div style="display:flex;justify-content:flex-end;gap:12px;">
        <button class="topbar-new-btn" @click="goStep(2)" :disabled="!canStep2">Suivant →</button>
      </div>
    </div>

    <!-- STEP 2 — Service -->
    <div v-if="step === 2 && !confirmation" style="display:flex;flex-direction:column;gap:18px;">
      <div style="padding:14px;border:1px solid rgba(255,255,255,0.06);border-radius:12px;background:rgba(255,255,255,0.02);">
        <div style="font-size:13px;font-weight:800;color:#E8E9ED;margin-bottom:12px;">2. Prestations</div>
        <div style="font-size:12px;color:#6B7280;margin-bottom:12px;">Choisissez une ou plusieurs prestations adaptées à votre moto.</div>

        <div v-if="loadingPrestas" style="height:120px;border-radius:10px;background:rgba(255,255,255,0.03);animation:shimmer 1.5s infinite;"></div>

        <div v-else style="display:flex;flex-direction:column;gap:8px;">
          <button
            v-for="p in prestations"
            :key="p.id"
            type="button"
            style="display:flex;align-items:center;gap:12px;padding:12px 16px;border-radius:10px;cursor:pointer;transition:all 0.15s;"
            :style="{
              background: selectedPrestas.includes(p.id) ? 'rgba(255,210,0,0.06)' : 'rgba(255,255,255,0.02)',
              border: selectedPrestas.includes(p.id) ? '1px solid rgba(255,210,0,0.3)' : '1px solid rgba(255,255,255,0.06)',
              color: '#E8E9ED',
            }"
            @click="togglePresta(p.id)"
          >
            <div style="width:20px;height:20px;border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:12px;"
              :style="{
                background: selectedPrestas.includes(p.id) ? '#FFD200' : 'rgba(255,255,255,0.06)',
                color: selectedPrestas.includes(p.id) ? '#111' : 'transparent',
              }"
            >
              <svg v-if="selectedPrestas.includes(p.id)" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <div style="flex:1;text-align:left;">
              <div style="font-size:14px;font-weight:600;color:#E8E9ED;">{{ p.nom }}</div>
              <div style="font-size:12px;color:#6B7280;">{{ p.description || p.categorie || '' }}</div>
            </div>
            <div style="text-align:right;">
              <div style="font-size:14px;font-weight:700;color:#FFD200;">{{ formatPrice(p.prix_base_ttc ?? p.prix_base_ht) }}</div>
              <div style="font-size:11px;color:#6B7280;">{{ formatMinutes(p.temps_estime_minutes ?? 60) }}</div>
            </div>
          </button>
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
          <div style="font-size:12px;color:#6B7280;margin-top:4px;">Durée estimée: {{ formatMinutes(dureeEstimee) }}</div>
        </div>
      </div>

      <div style="display:flex;justify-content:space-between;gap:12px;">
        <button class="btn btn-ghost" @click="goStep(1)">← Retour</button>
        <button class="topbar-new-btn" @click="goStep(3)" :disabled="!selectedPrestas.length">Suivant →</button>
      </div>
    </div>

    <!-- STEP 3 — Créneau -->
    <div v-if="step === 3 && !confirmation" style="display:flex;flex-direction:column;gap:18px;">
      <div style="padding:14px;border:1px solid rgba(255,255,255,0.06);border-radius:12px;background:rgba(255,255,255,0.02);">
        <div style="font-size:13px;font-weight:800;color:#E8E9ED;margin-bottom:4px;">3. Créneau</div>
        <div style="font-size:12px;color:#6B7280;margin-bottom:16px;">Sélectionnez un créneau disponible dans le planning.</div>

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
                  v-if="getSlotForCell(day.date, time)?.disponible && !isSlotPast(day.date, time)"
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
          <div style="font-size:12px;color:#BFDBFE;font-weight:700;">INFORMATION</div>
          <div style="font-size:12px;color:#CBD5E1;margin-top:4px;">Les créneaux proposés reflètent la capacité réelle de l'atelier. La pause déjeuner est bloquante : l'horaire de fin tient compte de la reprise après pause si nécessaire.</div>
        </div>

        <div v-if="form.date_rdv && form.heure_debut" style="margin-top:16px;padding:12px 16px;background:rgba(255,210,0,0.04);border:1px solid rgba(255,210,0,0.15);border-radius:10px;">
          <div style="font-size:12px;color:#9CA3AF;font-weight:600;">CRÉNEAU SÉLECTIONNÉ</div>
          <div style="font-size:15px;font-weight:700;color:#E8E9ED;margin-top:4px;">
            {{ formatDisplayDate(form.date_rdv) }} à {{ form.heure_debut }}
          </div>
          <div style="font-size:12px;color:#6B7280;margin-top:2px;">Durée: {{ formatMinutes(dureeEstimee) }}<span v-if="selectedSlotMeta?.heure_fin"> · Fin estimée {{ selectedSlotMeta.heure_fin }}</span></div>
          <div v-if="selectedSlotMeta?.pause_appliquee" style="font-size:12px;color:#FDE68A;margin-top:4px;">La pause atelier est déjà prise en compte dans l’horaire de fin.</div>
        </div>
      </div>

      <div style="display:flex;justify-content:space-between;gap:12px;">
        <button class="btn btn-ghost" @click="goStep(2)">← Retour</button>
        <button class="topbar-new-btn" @click="goStep(4)" :disabled="!form.date_rdv || !form.heure_debut">Suivant →</button>
      </div>
    </div>

    <!-- STEP 4 — Validation -->
    <div v-if="step === 4 && !confirmation" style="display:flex;flex-direction:column;gap:18px;">
      <div style="padding:14px;border:1px solid rgba(255,255,255,0.06);border-radius:12px;background:rgba(255,255,255,0.02);">
        <div style="font-size:13px;font-weight:800;color:#E8E9ED;margin-bottom:12px;">4. Validation</div>

        <!-- Client info -->
        <div style="font-size:12px;font-weight:700;color:#9CA3AF;margin-bottom:10px;letter-spacing:0.04em;text-transform:uppercase;">Vos coordonnées</div>
        <div v-if="clientMissingFields.length" style="margin-bottom:10px;padding:10px 12px;background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.24);border-radius:8px;font-size:12px;color:#FDE68A;">
          Merci de compléter : {{ clientMissingFields.join(', ') }}.
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div>
            <div class="form-label" style="margin-bottom:4px;color:#9CA3AF;font-size:12px;font-weight:600;letter-spacing:0.04em;text-transform:uppercase;">Prénom</div>
            <input v-model="form.client_prenom" type="text" required style="width:100%;padding:8px 12px;background:#151621;border:1px solid rgba(255,255,255,0.08);border-radius:10px;color:#E8E9ED;font-size:14px;font-family:inherit;outline:none;" />
          </div>
          <div>
            <div class="form-label" style="margin-bottom:4px;color:#9CA3AF;font-size:12px;font-weight:600;letter-spacing:0.04em;text-transform:uppercase;">Nom</div>
            <input v-model="form.client_nom" type="text" required style="width:100%;padding:8px 12px;background:#151621;border:1px solid rgba(255,255,255,0.08);border-radius:10px;color:#E8E9ED;font-size:14px;font-family:inherit;outline:none;" />
          </div>
          <div>
            <div class="form-label" style="margin-bottom:4px;color:#9CA3AF;font-size:12px;font-weight:600;letter-spacing:0.04em;text-transform:uppercase;">Téléphone</div>
            <input v-model="form.client_telephone" type="tel" required style="width:100%;padding:8px 12px;background:#151621;border:1px solid rgba(255,255,255,0.08);border-radius:10px;color:#E8E9ED;font-size:14px;font-family:inherit;outline:none;" />
          </div>
          <div>
            <div class="form-label" style="margin-bottom:4px;color:#9CA3AF;font-size:12px;font-weight:600;letter-spacing:0.04em;text-transform:uppercase;">Email</div>
            <input v-model="form.client_email" type="email" required style="width:100%;padding:8px 12px;background:#151621;border:1px solid rgba(255,255,255,0.08);border-radius:10px;color:#E8E9ED;font-size:14px;font-family:inherit;outline:none;" />
          </div>
        </div>

        <!-- Récap -->
        <div style="margin-top:20px;">
          <div style="font-size:12px;font-weight:700;color:#9CA3AF;margin-bottom:10px;letter-spacing:0.04em;text-transform:uppercase;">RÉCAPITULATIF</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div style="padding:14px;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06);border-radius:10px;">
              <div style="font-size:11px;font-weight:700;color:#6B7280;letter-spacing:0.06em;text-transform:uppercase;margin-bottom:8px;">CLIENT</div>
              <div style="font-size:14px;color:#E8E9ED;font-weight:600;">{{ form.client_prenom }} {{ form.client_nom }}</div>
              <div style="font-size:12px;color:#9CA3AF;margin-top:2px;">{{ form.client_telephone }}</div>
            </div>
            <div style="padding:14px;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06);border-radius:10px;">
              <div style="font-size:11px;font-weight:700;color:#6B7280;letter-spacing:0.06em;text-transform:uppercase;margin-bottom:8px;">VÉHICULE</div>
              <div style="font-size:14px;color:#E8E9ED;font-weight:600;">{{ form.vehicule_marque }} {{ form.vehicule_modele }}</div>
              <div style="font-size:12px;color:#9CA3AF;margin-top:2px;">{{ form.vehicule_plaque }}</div>
            </div>
            <div style="padding:14px;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06);border-radius:10px;">
              <div style="font-size:11px;font-weight:700;color:#6B7280;letter-spacing:0.06em;text-transform:uppercase;margin-bottom:8px;">CRÉNEAU</div>
              <div style="font-size:14px;color:#E8E9ED;font-weight:600;">{{ formatDisplayDate(form.date_rdv) }}</div>
              <div style="font-size:12px;color:#9CA3AF;margin-top:2px;">{{ form.heure_debut }}<span v-if="selectedSlotMeta?.heure_fin"> → {{ selectedSlotMeta.heure_fin }}</span> — Durée {{ formatMinutes(dureeEstimee) }}</div>
            </div>
            <div style="padding:14px;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06);border-radius:10px;">
              <div style="font-size:11px;font-weight:700;color:#6B7280;letter-spacing:0.06em;text-transform:uppercase;margin-bottom:8px;">SERVICES</div>
              <div v-for="p in selectedPrestaItems" :key="p.id" style="font-size:13px;color:#D1D5DB;">{{ p.nom }}</div>
              <div style="font-size:14px;color:#FFD200;font-weight:700;margin-top:4px;">{{ formatPrice(totalEstime) }}</div>
            </div>
          </div>
        </div>

        <!-- Remarques -->
        <div style="margin-top:16px;">
          <div class="form-label" style="margin-bottom:6px;color:#9CA3AF;font-size:12px;font-weight:600;letter-spacing:0.04em;text-transform:uppercase;">Description du problème</div>
          <textarea v-model="form.description_probleme" rows="3" placeholder="Exemple : vidange, frein avant bruyant, révision avant départ…" style="width:100%;padding:10px 14px;background:#151621;border:1px solid rgba(255,255,255,0.08);border-radius:10px;color:#E8E9ED;font-size:14px;font-family:inherit;outline:none;resize:vertical;"></textarea>
        </div>
      </div>

      <div style="display:flex;justify-content:space-between;gap:12px;">
        <button class="btn btn-ghost" @click="goStep(3)">← Retour</button>
        <button class="topbar-new-btn" :disabled="submitting || !canConfirm" @click="confirmBooking" style="padding:10px 24px;font-size:14px;">
          {{ submitting ? 'Envoi...' : 'Confirmer le rendez-vous' }}
        </button>
      </div>
    </div>

    <!-- Confirmation -->
    <div v-if="confirmation" style="margin-top:16px;padding:16px;background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.2);border-radius:12px;text-align:center;">
      <div style="margin-bottom:8px;">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#4ADE80" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
          <polyline points="22 4 12 14.01 9 11.01" />
        </svg>
      </div>
      <p style="font-weight:600;color:#86EFAC;">Rendez-vous confirmé</p>
      <p style="font-size:13px;color:#6EE7B7;margin-top:4px;">
        {{ confirmation.message || 'Une confirmation vous sera envoyée par email.' }}
      </p>
      <p v-if="confirmation.heure_fin" style="font-size:13px;color:#D1FAE5;margin-top:6px;">
        Fin estimée : <strong>{{ confirmation.heure_fin }}</strong>
      </p>
      <p style="font-size:13px;color:#6EE7B7;margin-top:4px;">
        Votre code de suivi : <strong>{{ confirmation.token_suivi }}</strong>
      </p>
      <NuxtLink :to="`/public/suivi?token=${confirmation.token_suivi}`" class="topbar-new-btn" style="display:inline-flex;margin-top:12px;font-size:12px;padding:6px 12px;">Suivre mon RDV</NuxtLink>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'public' })

const api = useApi()

const step = ref(1)
const stepLabels = ['Véhicule', 'Service', 'Créneau', 'Validation']
const submitting = ref(false)
const errorMessage = ref('')
const confirmation = ref<any>(null)
const bookingEnabled = ref(true)

// Atelier
const atelierOptions = ref<Array<{ id: number; nom: string; adresse?: string | null; ville?: string | null }>>([])
const selectedAtelierId = ref<number | null>(null)
const atelierDisplayName = computed(() => atelierOptions.value.find(a => a.id === selectedAtelierId.value)?.nom || 'Atelier Moto')
const atelierSelected = computed(() => !!selectedAtelierId.value)

// Vehicle
const vehiculeSearch = ref('')
const vehiculeFound = ref(false)
const showManualVehicle = ref(false)
const motoTypes = ['Roadster', 'Sportive', 'Trail', 'Custom', 'Scooter', 'Enduro']
const marqueSuggestions = ref<string[]>([])
const modeleSuggestions = ref<any[]>([])
const allMarques = ref<string[]>([])

// Prestations
const loadingPrestas = ref(false)
const prestations = ref<any[]>([])
const selectedPrestas = ref<number[]>([])

// Créneaux
const creneauxByDate = ref<Record<string, SlotItem[]>>({})
const loadingCreneaux = ref(false)

const form = reactive({
  atelier_id: null as number | null,
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
  date_rdv: toLocalISODate(new Date()),
  heure_debut: '',
  type_intervention: 'entretien',
  duree_estimee: 60,
  pont_id: null as number | null,
  mecanicien_id: null as number | null,
  description_probleme: '',
})

type SlotItem = {
  heure: string
  heure_fin?: string | null
  pause_appliquee?: boolean
  disponible: boolean
  pont_id?: number | null
  mecanicien_id?: number | null
}

const todayStr = toLocalISODate(new Date())
const weekStart = ref(startOfWeek(form.date_rdv || todayStr))

const vehicleMissingFields = computed(() => {
  const required = [
    ['vehicule_marque', 'marque'],
    ['vehicule_modele', 'modèle'],
    ['vehicule_plaque', 'plaque'],
  ] as const
  return required.filter(([key]) => !String((form as any)[key] ?? '').trim()).map(([, label]) => label)
})

const clientMissingFields = computed(() => {
  const required = [
    ['client_prenom', 'prénom'],
    ['client_nom', 'nom'],
    ['client_telephone', 'téléphone'],
    ['client_email', 'email'],
  ] as const
  return required.filter(([key]) => !String((form as any)[key] ?? '').trim()).map(([, label]) => label)
})

const canStep2 = computed(() => vehicleMissingFields.value.length === 0)
const canConfirm = computed(() => canStep2.value && selectedPrestas.value.length > 0 && !!form.date_rdv && !!form.heure_debut && clientMissingFields.value.length === 0)

const selectedPrestaItems = computed(() => prestations.value.filter(p => selectedPrestas.value.includes(p.id)))
const totalEstime = computed(() => selectedPrestaItems.value.reduce((s, p) => s + asNumber(p.prix_base_ttc ?? p.prix_base_ht), 0))
const dureeEstimee = computed(() => selectedPrestaItems.value.reduce((s, p) => s + asNumber(p.temps_estime_minutes ?? 60), 0) || form.duree_estimee)
const selectedSlotMeta = computed(() => (creneauxByDate.value[form.date_rdv] || []).find(c => c.heure === form.heure_debut) || null)

const planningDays = computed(() => {
  const start = new Date(`${weekStart.value}T00:00:00`)
  return Array.from({ length: 7 }, (_, index) => {
    const current = new Date(start)
    current.setDate(start.getDate() + index)
    const date = toLocalISODate(current)
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

function asNumber(value: unknown): number {
  const n = Number(value ?? 0)
  return Number.isFinite(n) ? n : 0
}

function toLocalISODate(d: Date): string {
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

function startOfWeek(dateStr: string): string {
  const date = new Date(`${dateStr}T00:00:00`)
  const day = date.getDay()
  const diff = day === 0 ? -6 : 1 - day
  date.setDate(date.getDate() + diff)
  return toLocalISODate(date)
}

function addDays(dateStr: string, days: number): string {
  const date = new Date(`${dateStr}T00:00:00`)
  date.setDate(date.getDate() + days)
  return toLocalISODate(date)
}

function isSlotPast(dateStr: string, heure: string): boolean {
  const now = new Date()
  const [h, m] = heure.split(':').map(Number)
  const slot = new Date(`${dateStr}T00:00:00`)
  slot.setHours(h, m, 0, 0)
  return slot < now
}

function getDefaultPlanningHours(): string[] {
  const hours: string[] = []
  for (let h = 8; h <= 18; h++) {
    for (const m of ['00', '15', '30', '45']) {
      if (h === 18 && m !== '00') continue
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

function formatPrice(v: number | string) {
  const amount = asNumber(v)
  if (!amount) return '0,00 €'
  return amount.toFixed(2).replace('.', ',') + ' €'
}

function formatMinutes(minutes: number) {
  const h = Math.floor(minutes / 60)
  const m = minutes % 60
  if (h > 0 && m > 0) return `${h}h ${m}min`
  if (h > 0) return `${h}h`
  return `${m}min`
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

function normalizeVehiculeQuery(value: string) {
  return formatRegistrationOrVin(value)
}

function normalizePlateField() {
  form.vehicule_plaque = formatRegistrationOrVin(form.vehicule_plaque)
}

// Ateliers
async function loadAteliers() {
  try {
    const data = await api.get('/public/ateliers').catch(() => null)
    const items = Array.isArray(data) ? data : (data?.['hydra:member'] ?? data?.member ?? [])
    atelierOptions.value = items
      .filter((a: any) => a?.actif !== false && a?.actif !== 0)
      .map((a: any) => ({ id: Number(a.id), nom: String(a.nom ?? `Atelier ${a.id}`), adresse: a.adresse ?? null, ville: a.ville ?? null }))

    if (!atelierOptions.value.length) {
      atelierOptions.value = [{ id: 1, nom: 'Atelier Moto' }]
    }

    selectedAtelierId.value = atelierOptions.value[0]?.id ?? null
    form.atelier_id = selectedAtelierId.value
  } catch {
    atelierOptions.value = [{ id: 1, nom: 'Atelier Moto' }]
    selectedAtelierId.value = 1
    form.atelier_id = 1
  }
}

// Vehicle lookup
async function searchVehicule() {
  const query = normalizeVehiculeQuery(vehiculeSearch.value)
  if (!query) {
    showManualVehicle.value = true
    vehiculeFound.value = false
    return
  }
  vehiculeSearch.value = query
  try {
    const data = await api.get(`/public/vehicule-lookup/${encodeURIComponent(query)}`).catch(() => null)
    if (data?.found) {
      form.vehicule_marque = data.marque || ''
      form.vehicule_modele = data.modele || ''
      form.vehicule_plaque = formatRegistrationOrVin(data.plaque || query)
      form.vehicule_annee = data.annee || ''
      form.vehicule_cylindree = data.cylindree || ''
      form.vehicule_type = data.type_moto || ''
      vehiculeFound.value = true
      showManualVehicle.value = vehicleMissingFields.value.length > 0
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

// Moto autocomplete
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
    marqueSuggestions.value = allMarques.value.filter(m => String(m).toLowerCase().includes(q.toLowerCase())).slice(0, 8)
  }, 200)
}

function selectMarque(m: string) {
  form.vehicule_marque = m
  marqueSuggestions.value = []
  modeleSuggestions.value = []
}

function selectModele(modele: any) {
  const item = typeof modele === 'string'
    ? (modeleSuggestions.value.find((entry: any) => (entry?.modele || '') === modele) ?? { modele })
    : modele
  form.vehicule_modele = item?.modele || ''
  if (item?.marque && !form.vehicule_marque) form.vehicule_marque = item.marque
  if ((item?.cylindree_min || item?.cylindree_display) && !form.vehicule_cylindree) {
    form.vehicule_cylindree = String(item.cylindree_min || item.cylindree_display)
  }
  if ((item?.annee_fin || item?.annee_debut) && !form.vehicule_annee) {
    form.vehicule_annee = String(item.annee_fin || item.annee_debut)
  }
  if (item?.categorie_nom && !form.vehicule_type) {
    form.vehicule_type = item.categorie_nom
  }
  modeleSuggestions.value = []
  marqueSuggestions.value = []
}

function deferHideMarqueSuggestions() {
  setTimeout(() => { marqueSuggestions.value = [] }, 200)
}

function deferHideModeleSuggestions() {
  setTimeout(() => { modeleSuggestions.value = [] }, 200)
}

let modeleTimeout: ReturnType<typeof setTimeout>
function onModeleInput() {
  clearTimeout(modeleTimeout)
  modeleTimeout = setTimeout(async () => {
    const marque = form.vehicule_marque.trim()
    const query = form.vehicule_modele.trim()
    if (query.length < 1 && marque.length < 1) { modeleSuggestions.value = []; return }
    try {
      const params = new URLSearchParams({ limit: '10' })
      if (marque) params.set('marque', marque)
      if (query) params.set('query', query)
      const data = await api.get(`/motos/autocomplete?${params.toString()}`)
      modeleSuggestions.value = Array.isArray(data) ? data : []
    } catch { modeleSuggestions.value = [] }
  }, 250)
}

// Prestations
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

async function loadPrestations() {
  loadingPrestas.value = true
  try {
    const atelierId = selectedAtelierId.value ?? form.atelier_id ?? null
    const params = new URLSearchParams()
    if (atelierId) params.set('atelier_id', String(atelierId))
    const query = params.toString()
    const data = await api.get(`/public/prestations${query ? `?${query}` : ''}`).catch(() => null)
    const items = Array.isArray(data) ? data : (data?.['hydra:member'] ?? data?.member ?? [])
    prestations.value = items
      .map((p: any) => normalizePrestation(p))
      .filter((p: any) => p.is_active !== false && p.is_active !== 0)
      .filter((p: any) => prestationMatchesVehicle(p))
    selectedPrestas.value = selectedPrestas.value.filter(id => prestations.value.some(p => p.id === id))
  } catch {
    prestations.value = []
    selectedPrestas.value = []
  } finally {
    loadingPrestas.value = false
  }
}

function togglePresta(id: number) {
  const idx = selectedPrestas.value.indexOf(id)
  if (idx >= 0) selectedPrestas.value.splice(idx, 1)
  else selectedPrestas.value.push(id)
}

// Créneaux
async function loadCreneaux() {
  loadingCreneaux.value = true
  errorMessage.value = ''
  try {
    const duree = dureeEstimee.value || 60
    const start = weekStart.value || form.date_rdv || todayStr
    const end = addDays(start, 6)
    const atelierParam = selectedAtelierId.value ? `&atelier_id=${selectedAtelierId.value}` : ''
    const data = await api.get(`/public/slots?date_debut=${start}&date_fin=${end}&temps_minutes=${duree}${atelierParam}`)
    bookingEnabled.value = data?.bookingEnabled !== false
    const rawSlots = Array.isArray(data) ? data : (data?.slots || {})
    const byDate = Array.isArray(rawSlots) ? { [start]: rawSlots } : (rawSlots || {})

    creneauxByDate.value = Object.fromEntries(
      planningDays.value.map((day) => [
        day.date,
        normalizeSlots(byDate[day.date] || []).map((slot) => ({
          ...slot,
          disponible: slot.disponible && !isSlotPast(day.date, slot.heure),
        })),
      ]),
    )

    const preferredDate = form.date_rdv && creneauxByDate.value[form.date_rdv]?.length ? form.date_rdv : ''
    form.date_rdv = preferredDate || planningDays.value.find(day => day.availableCount > 0)?.date || start
    syncSelectedDaySlots()
  } catch (e: any) {
    creneauxByDate.value = Object.fromEntries(planningDays.value.map((day) => [day.date, []]))
    form.heure_debut = ''
    form.pont_id = null
    form.mecanicien_id = null
    errorMessage.value = e?.message || 'Impossible de charger les créneaux.'
  } finally {
    loadingCreneaux.value = false
  }
}

function syncSelectedDaySlots() {
  const list = creneauxByDate.value[form.date_rdv] || []
  const selected = list.find(slot => slot.heure === form.heure_debut && slot.disponible)
    || list.find(slot => slot.disponible)
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

function selectPlanningSlot(date: string, slot: SlotItem) {
  form.date_rdv = date
  selectCreneau(slot)
}

function selectCreneau(c: SlotItem) {
  if (!c.disponible) return
  form.heure_debut = c.heure
  form.pont_id = c.pont_id ?? null
  form.mecanicien_id = c.mecanicien_id ?? null
}

// Wizard
function goStep(n: number) {
  step.value = n
  if (n === 2) loadPrestations()
  if (n === 3) {
    weekStart.value = startOfWeek(form.date_rdv || todayStr)
    loadCreneaux()
  }
}

watch(selectedAtelierId, (value) => {
  form.atelier_id = value
  selectedPrestas.value = []
  prestations.value = []
  if (step.value >= 2) loadPrestations()
  if (step.value >= 3) loadCreneaux()
})

watch(() => `${form.vehicule_type}|${form.vehicule_cylindree}`, () => {
  if (step.value >= 2) loadPrestations()
})

watch(dureeEstimee, (value, previousValue) => {
  const normalizedDuration = Number(value) || 60
  form.duree_estimee = normalizedDuration
  if (step.value >= 3 && form.date_rdv && normalizedDuration !== previousValue) {
    loadCreneaux()
  }
}, { immediate: true })

// Submit
async function confirmBooking() {
  errorMessage.value = ''
  submitting.value = true
  try {
    normalizePlateField()

    if (vehicleMissingFields.value.length) {
      throw new Error(`Véhicule incomplet : ${vehicleMissingFields.value.join(', ')}`)
    }
    if (clientMissingFields.value.length) {
      throw new Error(`Client incomplet : ${clientMissingFields.value.join(', ')}`)
    }

    const phone = form.client_telephone.replace(/[\s\-\.]+/g, '')
    if (!/^\+?[0-9]{10,15}$/.test(phone)) {
      throw new Error('Numéro de téléphone invalide.')
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.client_email)) {
      throw new Error('Email invalide.')
    }

    const typeIntervention = selectedPrestaItems.value.map(p => p.nom).join(', ') || form.type_intervention
    const res = await fetch(`${useRuntimeConfig().public.apiBase}/public/booking`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({
        prenom: form.client_prenom.trim(),
        nom: form.client_nom.trim(),
        telephone: form.client_telephone.trim(),
        email: form.client_email.trim(),
        marque: form.vehicule_marque,
        modele: form.vehicule_modele,
        plaque: form.vehicule_plaque.toUpperCase(),
        annee: form.vehicule_annee ? Number(form.vehicule_annee) : null,
        cylindree: form.vehicule_cylindree ? Number(form.vehicule_cylindree) : null,
        type_moto: form.vehicule_type,
        date_rdv: form.date_rdv,
        heure_rdv: form.heure_debut,
        type_intervention: typeIntervention,
        commentaire: form.description_probleme,
        atelier_id: selectedAtelierId.value ?? form.atelier_id ?? 1,
        prix_estime: totalEstime.value,
        duree_estimee: dureeEstimee.value,
        pont_id: form.pont_id,
        mecanicien_id: form.mecanicien_id,
      }),
    })
    const data = await res.json().catch(() => ({}))
    if (!res.ok) throw new Error(data?.error || 'Erreur lors de la réservation.')
    confirmation.value = data
  } catch (e: any) {
    errorMessage.value = e?.message || 'Erreur lors de la réservation. Veuillez réessayer.'
  } finally {
    submitting.value = false
  }
}

onMounted(async () => {
  await loadAteliers()
})
</script>

<style scoped>
.public-card {
  width: 100%;
  max-width: 900px;
  margin: 0 auto;
  padding: 24px;
}
.public-card-header {
  text-align: center;
  margin-bottom: 24px;
}
.text-gradient {
  background: linear-gradient(135deg, #E8E9ED 0%, #FFD200 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}
.steps {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0;
}
.step-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  position: relative;
}
.step-dot {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 700;
  background: rgba(255, 255, 255, 0.06);
  color: #6B7280;
  border: 1px solid rgba(255, 255, 255, 0.08);
  transition: all 0.2s;
}
.step-dot.active {
  background: #FFD200;
  color: #111;
  border-color: #FFD200;
}
.step-dot.done {
  background: rgba(16, 185, 129, 0.2);
  color: #10B981;
  border-color: rgba(16, 185, 129, 0.4);
}
.step-label {
  font-size: 11px;
  color: #6B7280;
  margin-top: 6px;
  font-weight: 600;
  white-space: nowrap;
}
.step-connector {
  width: 40px;
  height: 2px;
  background: rgba(255, 255, 255, 0.06);
  margin: 0 8px;
  position: relative;
  top: -14px;
}
.step-connector.done {
  background: rgba(16, 185, 129, 0.4);
}

.topbar-new-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  background: #FFD200;
  color: #111;
  border: none;
  border-radius: 10px;
  font-weight: 700;
  font-size: 13px;
  cursor: pointer;
  transition: all 0.15s;
}
.topbar-new-btn:hover:not(:disabled) {
  background: #ffe033;
}
.topbar-new-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-ghost {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  background: rgba(255, 255, 255, 0.04);
  color: #D1D5DB;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 10px;
  font-weight: 600;
  font-size: 13px;
  cursor: pointer;
  transition: all 0.15s;
}
.btn-ghost:hover:not(:disabled) {
  background: rgba(255, 255, 255, 0.08);
}
.btn-ghost:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

@keyframes shimmer {
  0% { background-position: -200px 0; }
  100% { background-position: calc(200px + 100%) 0; }
}
.loading-shimmer {
  background: linear-gradient(90deg, rgba(255,255,255,0.03) 25%, rgba(255,255,255,0.06) 50%, rgba(255,255,255,0.03) 75%);
  background-size: 200px 100%;
  animation: shimmer 1.5s infinite;
}
</style>
