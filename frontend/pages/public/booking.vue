<template>
  <div :style="{ maxWidth: step === 3 ? '1200px' : '680px', margin: '0 auto', paddingBottom: '40px', transition: 'max-width 0.3s ease' }">
    <div class="public-card-header" style="margin-bottom:24px;">
      <div style="font-size:32px;margin-bottom:8px;">📅</div>
      <h1 class="text-gradient" style="font-size:22px;font-weight:800;">Réserver un rendez-vous</h1>
      <p style="font-size:13px;color:#6B7280;margin-top:4px;">Même parcours que la prise de RDV atelier, adapté au public.</p>
    </div>

    <div v-if="loadingAteliers" class="public-card" style="text-align:center;padding:32px;color:#6B7280;font-size:13px;">
      Chargement des ateliers…
    </div>

    <div v-else-if="ateliersList.length === 0" class="public-card" style="padding:20px;border:1px solid rgba(239,68,68,0.2);background:rgba(239,68,68,0.05);color:#FCA5A5;font-size:13px;text-align:center;">
      Aucun atelier n'est disponible pour la prise de rendez-vous en ligne pour le moment.
    </div>

    <template v-else>
      <div v-if="ateliersList.length > 1 && !selectedAtelierId" class="public-card" style="display:flex;flex-direction:column;gap:10px;">
        <div style="font-size:13px;font-weight:800;color:#E8E9ED;margin-bottom:4px;">Choisissez votre atelier</div>
        <button
          v-for="atelier in ateliersList"
          :key="atelier.id"
          type="button"
          style="display:flex;flex-direction:column;align-items:flex-start;padding:14px 16px;border-radius:12px;border:1px solid rgba(255,255,255,0.08);background:rgba(255,255,255,0.03);cursor:pointer;transition:all 0.15s;text-align:left;gap:4px;"
          @click="selectAtelier(atelier)"
        >
          <span style="font-size:15px;font-weight:700;color:#E8E9ED;">{{ atelier.nom }}</span>
          <span v-if="atelier.ville" style="font-size:12px;color:#9CA3AF;">{{ atelier.ville }}</span>
          <span v-if="atelier.telephone" style="font-size:12px;color:#9CA3AF;">{{ atelier.telephone }}</span>
        </button>
      </div>

      <template v-else-if="selectedAtelierId">
        <div v-if="confirmation" class="public-card" style="margin-top:16px;padding:16px;background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.2);text-align:center;">
          <div style="font-size:32px;margin-bottom:8px;">✅</div>
          <p style="font-weight:600;color:#86EFAC;">Demande envoyée</p>
          <p style="font-size:13px;color:#6EE7B7;margin-top:4px;">{{ confirmation.message || 'L\'atelier vous confirmera le créneau après validation.' }}</p>
          <p v-if="confirmation.heure_fin" style="font-size:13px;color:#D1FAE5;margin-top:6px;">Fin estimée : <strong>{{ confirmation.heure_fin }}</strong></p>
          <p style="font-size:13px;color:#6EE7B7;margin-top:4px;">Votre code de suivi : <strong>{{ confirmation.token_suivi }}</strong></p>
          <NuxtLink :to="`/public/suivi/${confirmation.token_suivi}`" class="topbar-new-btn" style="display:inline-flex;margin-top:12px;font-size:12px;padding:6px 12px;">Suivre mon RDV</NuxtLink>
        </div>

        <template v-else>
          <div class="public-card" style="margin-bottom:20px;padding:16px 18px;">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
              <div>
                <div style="font-size:11px;color:#6B7280;text-transform:uppercase;letter-spacing:0.08em;">Atelier sélectionné</div>
                <div style="font-size:16px;font-weight:700;color:#E8E9ED;">{{ atelierDisplayName }}</div>
                <div style="font-size:12px;color:#9CA3AF;margin-top:4px;">Les prestations et créneaux reflètent la capacité réelle de cet atelier.</div>
              </div>
              <button v-if="ateliersList.length > 1" type="button" class="btn btn-ghost" @click="changeAtelier">Changer</button>
            </div>
          </div>

          <div v-if="errorMessage" class="public-card" style="margin-bottom:16px;padding:12px 14px;background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);color:#FCA5A5;font-size:13px;">
            {{ errorMessage }}
          </div>

          <div class="steps" style="margin-bottom:28px;">
            <div v-for="(label, index) in stepLabels" :key="label" class="step-item">
              <div class="step-dot" :class="{ active: step === index + 1, done: step > index + 1 }">{{ step > index + 1 ? '✓' : index + 1 }}</div>
              <div class="step-label" :style="step === index + 1 ? 'color:#FFD200;' : ''">{{ label }}</div>
              <div v-if="index < stepLabels.length - 1" class="step-connector" :class="{ done: step > index + 1 }"></div>
            </div>
          </div>

          <div v-if="step === 1" class="public-card">
            <div style="font-size:16px;font-weight:700;color:#E8E9ED;margin-bottom:4px;">Identification moto</div>
            <div style="font-size:12px;color:#6B7280;margin-bottom:20px;">Saisissez votre véhicule avant la sélection des prestations.</div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
              <div style="position:relative;">
                <div class="form-label" style="margin-bottom:4px;">MARQUE</div>
                <input v-model="form.vehicule_marque" type="text" placeholder="Ex: YAMAHA" style="width:100%;padding:12px 14px;min-height:44px;background:var(--dark3);border:1px solid rgba(255,255,255,0.08);border-radius:var(--radius-sm);color:#E8E9ED;font-size:14px;font-family:inherit;outline:none;" @input="onMarqueInput" @blur="deferHideMarqueSuggestions" />
                <div v-if="marqueSuggestions.length" style="position:absolute;left:0;right:0;top:100%;z-index:10;background:var(--dark3);border:1px solid rgba(255,255,255,0.08);border-radius:8px;max-height:150px;overflow-y:auto;">
                  <div v-for="item in marqueSuggestions" :key="item" style="padding:12px 14px;min-height:44px;cursor:pointer;font-size:13px;color:#D1D5DB;border-bottom:1px solid rgba(255,255,255,0.04);" @mousedown.prevent="selectMarque(item)">{{ item }}</div>
                </div>
              </div>

              <div style="position:relative;">
                <div class="form-label" style="margin-bottom:4px;">MODÈLE</div>
                <input v-model="form.vehicule_modele" type="text" placeholder="Ex: MT-07" style="width:100%;padding:12px 14px;min-height:44px;background:var(--dark3);border:1px solid rgba(255,255,255,0.08);border-radius:var(--radius-sm);color:#E8E9ED;font-size:14px;font-family:inherit;outline:none;" @input="onModeleInput" @blur="deferHideModeleSuggestions" />
                <div v-if="modeleSuggestions.length" style="position:absolute;left:0;right:0;top:100%;z-index:10;background:var(--dark3);border:1px solid rgba(255,255,255,0.08);border-radius:8px;max-height:150px;overflow-y:auto;">
                  <div v-for="item in modeleSuggestions" :key="item.id || item.modele || item" style="padding:12px 14px;min-height:44px;cursor:pointer;font-size:13px;color:#D1D5DB;border-bottom:1px solid rgba(255,255,255,0.04);" @mousedown.prevent="selectModele(item)">{{ suggestionLabel(item) }}</div>
                </div>
              </div>

              <div>
                <div class="form-label" style="margin-bottom:4px;">PLAQUE</div>
                <input v-model="form.vehicule_plaque" type="text" placeholder="AA-123-AA" style="width:100%;padding:12px 14px;min-height:44px;background:var(--dark3);border:1px solid rgba(255,255,255,0.08);border-radius:var(--radius-sm);color:#E8E9ED;font-size:14px;font-family:inherit;outline:none;" @blur="normalizePlateField" />
              </div>

              <div>
                <div class="form-label" style="margin-bottom:4px;">ANNÉE</div>
                <input v-model="form.vehicule_annee" type="number" placeholder="2024" style="width:100%;padding:12px 14px;min-height:44px;background:var(--dark3);border:1px solid rgba(255,255,255,0.08);border-radius:var(--radius-sm);color:#E8E9ED;font-size:14px;font-family:inherit;outline:none;" />
              </div>

              <div>
                <div class="form-label" style="margin-bottom:4px;">CYLINDRÉE</div>
                <input v-model="form.vehicule_cylindree" type="number" placeholder="700" style="width:100%;padding:12px 14px;min-height:44px;background:var(--dark3);border:1px solid rgba(255,255,255,0.08);border-radius:var(--radius-sm);color:#E8E9ED;font-size:14px;font-family:inherit;outline:none;" />
              </div>

              <div>
                <div class="form-label" style="margin-bottom:4px;">TYPE MOTO</div>
                <select v-model="form.vehicule_type" style="width:100%;padding:12px 14px;min-height:44px;background:var(--dark3);border:1px solid rgba(255,255,255,0.08);border-radius:var(--radius-sm);color:#E8E9ED;font-size:14px;font-family:inherit;outline:none;">
                  <option value="">— Choisir —</option>
                  <option v-for="type in motoTypes" :key="type" :value="type">{{ type }}</option>
                </select>
              </div>
            </div>

            <div v-if="vehicleMissingFields.length" style="margin-top:16px;padding:12px 16px;background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.24);border-radius:10px;">
              <div style="font-size:12px;color:#FDE68A;font-weight:700;">CHAMPS OBLIGATOIRES À COMPLÉTER</div>
              <div style="font-size:12px;color:#E5E7EB;margin-top:4px;">{{ vehicleMissingFields.join(', ') }}</div>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:12px;margin-top:20px;">
              <button class="topbar-new-btn" @click="goStep(2)" :disabled="!canStep2">Suivant →</button>
            </div>
          </div>

          <div v-if="step === 2" class="public-card">
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
                  <div style="font-size:11px;color:#6B7280;">{{ formatDuration(p.temps_estime_minutes ?? 60) }}</div>
                </div>
              </div>
              <div v-if="!prestations.length" style="padding:20px;text-align:center;color:#6B7280;font-size:13px;">Aucune prestation active pour ce type de moto dans cet atelier.</div>
            </div>

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

            <div style="display:flex;justify-content:space-between;gap:12px;margin-top:20px;">
              <button class="btn btn-ghost" @click="goStep(1)">← Retour</button>
              <button class="topbar-new-btn" @click="goStep(3)" :disabled="!selectedPrestas.length">Suivant →</button>
            </div>
          </div>

          <div v-if="step === 3" class="public-card">
            <div style="font-size:16px;font-weight:700;color:#E8E9ED;margin-bottom:4px;">Planning atelier sur 1 semaine</div>
            <div style="font-size:12px;color:#6B7280;margin-bottom:20px;">Vue réelle des créneaux disponibles en base sur la semaine courante.</div>

            <div style="display:grid;grid-template-columns:minmax(0,1fr) auto minmax(0,1fr);align-items:center;gap:12px;margin-bottom:14px;">
              <div style="display:flex;justify-content:flex-start;">
                <button class="btn btn-ghost" @click="changeWeek(-7)" :disabled="!canGoPrevWeek">← Semaine précédente</button>
              </div>
              <div style="font-size:13px;color:#D1D5DB;font-weight:600;text-align:center;justify-self:center;">{{ planningRangeLabel }}</div>
              <div style="display:flex;justify-content:flex-end;">
                <button class="btn btn-ghost" @click="changeWeek(7)">Semaine suivante →</button>
              </div>
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
                      v-if="isSlotSelectable(day.date, time)"
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
              <div style="font-size:12px;color:#CBD5E1;margin-top:4px;">Aucun choix manuel ici : seuls les créneaux réellement disponibles sont proposés.</div>
              <div style="font-size:12px;color:#CBD5E1;margin-top:4px;">La pause déjeuner est déjà prise en compte dans l'horaire de fin si nécessaire.</div>
            </div>

            <div v-if="form.date_rdv && form.heure_debut" style="margin-top:16px;padding:12px 16px;background:rgba(255,210,0,0.04);border:1px solid rgba(255,210,0,0.15);border-radius:10px;">
              <div style="font-size:12px;color:#9CA3AF;font-weight:600;">CRÉNEAU SÉLECTIONNÉ</div>
              <div style="font-size:15px;font-weight:700;color:#E8E9ED;margin-top:4px;">{{ formatDisplayDate(form.date_rdv) }} à {{ form.heure_debut }}</div>
              <div style="font-size:12px;color:#6B7280;margin-top:2px;">Durée: {{ dureeEstimee }} min<span v-if="selectedSlotMeta?.heure_fin"> · Fin estimée {{ selectedSlotMeta.heure_fin }}</span></div>
              <div v-if="selectedSlotMeta?.pause_appliquee" style="font-size:12px;color:#FDE68A;margin-top:4px;">La pause atelier est déjà prise en compte dans l’horaire de fin.</div>
            </div>

            <div style="display:flex;justify-content:space-between;gap:12px;margin-top:20px;">
              <button class="btn btn-ghost" @click="goStep(2)">← Retour</button>
              <button class="topbar-new-btn" @click="goStep(4)" :disabled="!form.date_rdv || !form.heure_debut">Suivant →</button>
            </div>
          </div>

          <div v-if="step === 4" class="public-card">
            <div style="font-size:16px;font-weight:700;color:#E8E9ED;margin-bottom:16px;">Récapitulatif du rendez-vous</div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
              <div style="padding:14px;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06);border-radius:10px;">
                <div style="font-size:11px;font-weight:700;color:#6B7280;letter-spacing:0.06em;text-transform:uppercase;margin-bottom:8px;">ATELIER</div>
                <div style="font-size:14px;color:#E8E9ED;font-weight:600;">{{ atelierDisplayName }}</div>
                <div style="font-size:12px;color:#9CA3AF;margin-top:2px;">{{ selectedAtelier?.telephone || 'Contact à confirmer par l\'atelier' }}</div>
              </div>
              <div style="padding:14px;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06);border-radius:10px;">
                <div style="font-size:11px;font-weight:700;color:#6B7280;letter-spacing:0.06em;text-transform:uppercase;margin-bottom:8px;">VÉHICULE</div>
                <div style="font-size:14px;color:#E8E9ED;font-weight:600;">{{ form.vehicule_marque }} {{ form.vehicule_modele }}</div>
                <div style="font-size:12px;color:#9CA3AF;margin-top:2px;">{{ form.vehicule_plaque }}</div>
              </div>
              <div style="padding:14px;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06);border-radius:10px;">
                <div style="font-size:11px;font-weight:700;color:#6B7280;letter-spacing:0.06em;text-transform:uppercase;margin-bottom:8px;">CRÉNEAU</div>
                <div style="font-size:14px;color:#E8E9ED;font-weight:600;">{{ formatDisplayDate(form.date_rdv) }}</div>
                <div style="font-size:12px;color:#9CA3AF;margin-top:2px;">{{ form.heure_debut }}<span v-if="selectedSlotMeta?.heure_fin"> → {{ selectedSlotMeta.heure_fin }}</span> — Durée {{ dureeEstimee }} min</div>
                <div v-if="selectedSlotMeta?.pause_appliquee" style="font-size:12px;color:#FDE68A;margin-top:4px;">Pause déjeuner incluse dans l'horaire de fin.</div>
              </div>
              <div style="padding:14px;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06);border-radius:10px;">
                <div style="font-size:11px;font-weight:700;color:#6B7280;letter-spacing:0.06em;text-transform:uppercase;margin-bottom:8px;">SERVICES</div>
                <div v-for="p in selectedPrestaItems" :key="p.id" style="font-size:13px;color:#D1D5DB;">{{ p.nom }}</div>
                <div style="font-size:14px;color:#FFD200;font-weight:700;margin-top:4px;">{{ formatPrice(totalEstime) }}</div>
              </div>
            </div>

            <div style="margin-top:16px;">
              <div style="font-size:13px;font-weight:700;color:#9CA3AF;margin-bottom:10px;">INFORMATIONS CLIENT OBLIGATOIRES</div>
              <div v-if="clientMissingFields.length" style="margin-bottom:10px;padding:10px 12px;background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.24);border-radius:8px;font-size:12px;color:#FDE68A;">
                Merci de compléter : {{ clientMissingFields.join(', ') }}.
              </div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div>
                  <div class="form-label" style="margin-bottom:4px;">PRÉNOM</div>
                  <input v-model="form.client_prenom" type="text" required style="width:100%;padding:12px 14px;min-height:44px;background:var(--dark3);border:1px solid rgba(255,255,255,0.08);border-radius:var(--radius-sm);color:#E8E9ED;font-size:14px;font-family:inherit;outline:none;" />
                </div>
                <div>
                  <div class="form-label" style="margin-bottom:4px;">NOM</div>
                  <input v-model="form.client_nom" type="text" required style="width:100%;padding:12px 14px;min-height:44px;background:var(--dark3);border:1px solid rgba(255,255,255,0.08);border-radius:var(--radius-sm);color:#E8E9ED;font-size:14px;font-family:inherit;outline:none;" />
                </div>
                <div>
                  <div class="form-label" style="margin-bottom:4px;">TÉLÉPHONE</div>
                  <input v-model="form.client_telephone" type="tel" required style="width:100%;padding:12px 14px;min-height:44px;background:var(--dark3);border:1px solid rgba(255,255,255,0.08);border-radius:var(--radius-sm);color:#E8E9ED;font-size:14px;font-family:inherit;outline:none;" />
                </div>
                <div>
                  <div class="form-label" style="margin-bottom:4px;">EMAIL</div>
                  <input v-model="form.client_email" type="email" required style="width:100%;padding:12px 14px;min-height:44px;background:var(--dark3);border:1px solid rgba(255,255,255,0.08);border-radius:var(--radius-sm);color:#E8E9ED;font-size:14px;font-family:inherit;outline:none;" />
                </div>
              </div>
            </div>

            <div style="margin-top:16px;">
              <div class="form-label" style="margin-bottom:6px;">REMARQUES</div>
              <textarea v-model="form.description_probleme" rows="3" placeholder="Notes ou description du problème..." style="width:100%;padding:12px 14px;min-height:44px;background:var(--dark3);border:1px solid rgba(255,255,255,0.08);border-radius:var(--radius-sm);color:#E8E9ED;font-size:14px;font-family:inherit;outline:none;resize:vertical;"></textarea>
            </div>

            <div style="display:flex;justify-content:space-between;gap:12px;margin-top:20px;">
              <button class="btn btn-ghost" @click="goStep(3)">← Retour</button>
              <button class="topbar-new-btn" :disabled="submitting || !canConfirm" @click="confirmBooking" style="padding:10px 24px;font-size:14px;">
                {{ submitting ? 'Envoi...' : 'Réserver le créneau' }}
              </button>
            </div>
          </div>
        </template>
      </template>
    </template>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'public' })

const config = useRuntimeConfig()
const baseURL = config.public.apiBase as string
const { validateClientFields } = useValidation()
const { formatDuration } = useFormat()

type AtelierOption = {
  id: number
  nom: string
  ville?: string | null
  telephone?: string | null
  email?: string | null
}

type SlotItem = {
  heure: string
  heure_fin?: string | null
  pause_appliquee?: boolean
  disponible: boolean
  pont_id?: number | null
  mecanicien_id?: number | null
}

const loadingAteliers = ref(true)
const ateliersList = ref<AtelierOption[]>([])
const selectedAtelierId = ref<number | null>(null)
const selectedAtelier = computed(() => ateliersList.value.find(atelier => atelier.id === selectedAtelierId.value) || null)
const atelierDisplayName = computed(() => selectedAtelier.value?.nom || 'Atelier Moto')

const step = ref(1)
const stepLabels = ['Véhicule', 'Service', 'Créneau', 'Validation']
const submitting = ref(false)
const loadingPrestas = ref(false)
const loadingCreneaux = ref(false)
const confirmation = ref<any>(null)
const errorMessage = ref('')
const prestations = ref<any[]>([])
const selectedPrestas = ref<number[]>([])
const creneauxList = ref<SlotItem[]>([])
const creneauxByDate = ref<Record<string, SlotItem[]>>({})
const weekStart = ref('')
const nowTick = ref(Date.now())

const motoTypes = ['Roadster', 'Sportive', 'Trail', 'Custom', 'Scooter', 'Enduro']
let nowTimer: ReturnType<typeof setInterval> | null = null

function parseLocalDate(dateStr: string) {
  const [year, month, day] = String(dateStr || '').split('-').map(Number)
  if (!year || !month || !day) return new Date(Number.NaN)
  return new Date(year, month - 1, day)
}

function formatLocalDateKey(date: Date) {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

function createDateTime(dateStr: string, timeStr = '00:00') {
  const date = parseLocalDate(dateStr)
  const [hours, minutes] = String(timeStr || '00:00').split(':').map(Number)
  date.setHours(hours || 0, minutes || 0, 0, 0)
  return date
}

function addLocalDays(dateStr: string, days: number) {
  const date = parseLocalDate(dateStr)
  date.setDate(date.getDate() + days)
  return formatLocalDateKey(date)
}

function getLocalWeekStart(dateStr: string) {
  const date = parseLocalDate(dateStr)
  const day = date.getDay()
  const diff = day === 0 ? -6 : 1 - day
  date.setDate(date.getDate() + diff)
  return formatLocalDateKey(date)
}

function getTodayKey() {
  return formatLocalDateKey(new Date(nowTick.value))
}

const todayStr = computed(() => getTodayKey())

function asNumber(value: unknown): number {
  const n = Number(value ?? 0)
  return Number.isFinite(n) ? n : 0
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

const form = reactive({
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
  date_rdv: getTodayKey(),
  heure_debut: '09:00',
  type_intervention: 'entretien',
  duree_estimee: 60,
  pont_id: null as number | null,
  mecanicien_id: null as number | null,
  description_probleme: '',
})

weekStart.value = getLocalWeekStart(todayStr.value)
form.date_rdv = todayStr.value

const {
  marqueSuggestions,
  modeleSuggestions,
  onMarqueInput,
  onModeleInput,
  selectMarque,
  selectModele,
  deferHideMarqueSuggestions,
  deferHideModeleSuggestions,
  suggestionLabel,
} = useMotoAutocomplete({
  form,
  marqueKey: 'vehicule_marque',
  modeleKey: 'vehicule_modele',
})

const vehicleMissingFields = computed(() => {
  const required = [
    ['vehicule_marque', 'marque'],
    ['vehicule_modele', 'modèle'],
    ['vehicule_plaque', 'plaque'],
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
const totalEstime = computed(() => selectedPrestaItems.value.reduce((sum, p) => sum + asNumber(p.prix_base_ttc ?? p.prix_base_ht), 0))
const dureeEstimee = computed(() => selectedPrestaItems.value.reduce((sum, p) => sum + asNumber(p.temps_estime_minutes ?? 60), 0) || form.duree_estimee)
const selectedSlotMeta = computed(() => (creneauxByDate.value[form.date_rdv] || []).find(slot => slot.heure === form.heure_debut) || null)
const currentWeekStart = computed(() => getLocalWeekStart(todayStr.value))
const planningDays = computed(() => {
  const start = parseLocalDate(weekStart.value)
  return Array.from({ length: 7 }, (_, index) => {
    const current = new Date(start)
    current.setDate(start.getDate() + index)
    const date = formatLocalDateKey(current)
    const availableCount = (creneauxByDate.value[date] || []).filter(slot => isSlotSelectable(date, slot.heure)).length
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
  const start = parseLocalDate(dates[0].date)
  const end = parseLocalDate(dates[dates.length - 1].date)
  return `${start.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long' })} → ${end.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })}`
})
const planningTimeLabels = computed(() => {
  const merged = Array.from(
    new Set(
      Object.entries(creneauxByDate.value)
        .flatMap(([date, slots]) => slots.filter(slot => isSlotSelectable(date, slot.heure)).map(slot => slot.heure)),
    ),
  ).sort((a, b) => a.localeCompare(b))
  return merged.length ? merged : getDefaultPlanningHours()
})
const canGoPrevWeek = computed(() => weekStart.value > currentWeekStart.value)

function formatPrice(v: number | string) {
  const amount = asNumber(v)
  if (!amount) return '0,00 €'
  return amount.toFixed(2).replace('.', ',') + ' €'
}

function formatDisplayDate(d: string) {
  if (!d) return ''
  const date = parseLocalDate(d)
  return date.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
}

function normalizePlateField() {
  form.vehicule_plaque = formatRegistrationOrVin(form.vehicule_plaque)
}

function prestationMatchesVehicle(prestation: any) {
  const atelierId = Number(selectedAtelierId.value ?? 0)
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
  loadingAteliers.value = true
  try {
    const res = await fetch(`${baseURL}/public/ateliers`, { headers: { Accept: 'application/json' } })
    if (!res.ok) throw new Error()
    const data = await res.json()
    ateliersList.value = Array.isArray(data) ? data : []
    if (ateliersList.value.length === 1) {
      selectedAtelierId.value = ateliersList.value[0].id
    }
  } catch (err) {
    console.error('Erreur chargement ateliers:', err)
    ateliersList.value = []
  } finally {
    loadingAteliers.value = false
  }
}

function resetWorkflow() {
  step.value = 1
  selectedPrestas.value = []
  prestations.value = []
  creneauxList.value = []
  creneauxByDate.value = {}
  errorMessage.value = ''
  confirmation.value = null
  form.date_rdv = todayStr.value
  form.heure_debut = '09:00'
  form.pont_id = null
  form.mecanicien_id = null
  weekStart.value = getLocalWeekStart(todayStr.value)
}

function selectAtelier(atelier: AtelierOption) {
  selectedAtelierId.value = atelier.id
  resetWorkflow()
}

function changeAtelier() {
  selectedAtelierId.value = null
  resetWorkflow()
}

function goStep(n: number) {
  step.value = n
  if (n === 2 && !prestations.value.length) void loadPrestations()
  if (n === 3) {
    weekStart.value = getLocalWeekStart(form.date_rdv || todayStr.value)
    void loadCreneaux()
  }
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

function isSlotPast(date: string, heure: string) {
  const slotStart = createDateTime(date, heure)
  return slotStart.getTime() <= nowTick.value
}

function isSlotSelectable(date: string, heure: string) {
  const slot = getSlotForCell(date, heure)
  if (!slot?.disponible) return false
  return !isSlotPast(date, heure)
}

function selectCreneau(slot: SlotItem) {
  if (!slot.disponible) return
  form.heure_debut = slot.heure
  form.pont_id = slot.pont_id ?? null
  form.mecanicien_id = slot.mecanicien_id ?? null
}

function syncSelectedDaySlots() {
  creneauxList.value = (creneauxByDate.value[form.date_rdv] || []).filter(slot => isSlotSelectable(form.date_rdv, slot.heure))
  const selected = creneauxList.value.find(slot => slot.heure === form.heure_debut) || creneauxList.value.find(slot => slot.disponible)

  if (selected) {
    selectCreneau(selected)
  } else {
    form.heure_debut = ''
    form.pont_id = null
    form.mecanicien_id = null
  }
}

function changeWeek(days: number) {
  const candidate = addLocalDays(weekStart.value, days)
  if (candidate < currentWeekStart.value) return
  weekStart.value = candidate
  form.date_rdv = candidate
  void loadCreneaux()
}

function selectPlanningSlot(date: string, slot: SlotItem) {
  form.date_rdv = date
  creneauxList.value = creneauxByDate.value[date] || []
  selectCreneau(slot)
}

async function loadPrestations() {
  if (!selectedAtelierId.value) return
  loadingPrestas.value = true
  try {
    const params = new URLSearchParams()
    params.set('atelier_id', String(selectedAtelierId.value))
    if (form.vehicule_type) params.set('type_moto', String(form.vehicule_type))
    if (form.vehicule_cylindree) params.set('cylindree', String(form.vehicule_cylindree))
    const res = await fetch(`${baseURL}/public/prestations-catalogue?${params.toString()}`, { headers: { Accept: 'application/json' } })
    if (!res.ok) throw new Error()
    const raw = await res.json()
    prestations.value = (Array.isArray(raw) ? raw : [])
      .filter((p: any) => p.is_active !== false && p.is_active !== 0)
      .filter((p: any) => prestationMatchesVehicle(p))
    selectedPrestas.value = selectedPrestas.value.filter(id => prestations.value.some(p => p.id === id))
  } catch (err) {
    console.error('Erreur chargement prestations:', err)
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

async function loadCreneaux() {
  if (!selectedAtelierId.value) return
  loadingCreneaux.value = true
  errorMessage.value = ''
  try {
    const duree = dureeEstimee.value || 60
    const start = getLocalWeekStart(weekStart.value || form.date_rdv || todayStr.value)
    weekStart.value = start
    const end = addLocalDays(start, 6)
    const res = await fetch(`${baseURL}/public/slots?date_debut=${start}&date_fin=${end}&temps_minutes=${duree}&atelier_id=${selectedAtelierId.value}`, {
      headers: { Accept: 'application/json' },
    })
    if (!res.ok) throw new Error('Impossible de charger les créneaux disponibles.')
    const slotsData = await res.json()
    const byDate = Array.isArray(slotsData) ? { [start]: slotsData } : (slotsData || {})

    creneauxByDate.value = Object.fromEntries(
      planningDays.value.map((day) => [day.date, normalizeSlots(byDate[day.date] || [])]),
    )

    const preferredDate = form.date_rdv && (creneauxByDate.value[form.date_rdv] || []).some(slot => isSlotSelectable(form.date_rdv, slot.heure)) ? form.date_rdv : ''
    form.date_rdv = preferredDate || planningDays.value.find(day => day.availableCount > 0)?.date || start
    syncSelectedDaySlots()
  } catch (error: unknown) {
    creneauxByDate.value = Object.fromEntries(planningDays.value.map((day) => [day.date, []]))
    creneauxList.value = []
    form.heure_debut = ''
    errorMessage.value = (error instanceof Error ? error.message : 'Erreur inconnue') || 'Erreur lors du chargement des créneaux.'
  } finally {
    loadingCreneaux.value = false
  }
}

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

    const formatErrors = validateClientFields({
      telephone: form.client_telephone,
      email: form.client_email,
      plaque: form.vehicule_plaque,
    })
    if (formatErrors.length) {
      throw new Error(formatErrors.join(' — '))
    }

    const typeIntervention = selectedPrestaItems.value.map(p => p.nom).join(', ') || form.type_intervention
    const res = await fetch(`${baseURL}/public/booking`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({
        prenom: form.client_prenom.trim(),
        nom: form.client_nom.trim(),
        telephone: form.client_telephone.trim(),
        email: form.client_email.trim(),
        marque: form.vehicule_marque,
        modele: form.vehicule_modele,
        plaque: formatRegistrationOrVin(form.vehicule_plaque),
        date_rdv: form.date_rdv,
        heure_rdv: form.heure_debut,
        type_intervention: typeIntervention,
        commentaire: form.description_probleme,
        atelier_id: selectedAtelierId.value,
        prix_estime: totalEstime.value,
        duree_estimee: dureeEstimee.value,
      }),
    })
    const data = await res.json().catch(() => ({}))
    if (!res.ok) throw new Error(data?.error || 'Erreur lors de la réservation.')
    confirmation.value = data
  } catch (error: unknown) {
    errorMessage.value = (error instanceof Error ? error.message : 'Erreur inconnue') || 'Erreur lors de la réservation. Veuillez réessayer.'
  } finally {
    submitting.value = false
  }
}

watch(selectedAtelierId, async () => {
  if (!selectedAtelierId.value) return
  if (step.value >= 2) await loadPrestations()
  if (step.value >= 3) await loadCreneaux()
})

watch(() => `${form.vehicule_type}|${form.vehicule_cylindree}`, () => {
  if (step.value >= 2) void loadPrestations()
})

watch(dureeEstimee, (value, previousValue) => {
  form.duree_estimee = Number(value) || 60
  if (step.value >= 3 && form.date_rdv && value !== previousValue) {
    void loadCreneaux()
  }
}, { immediate: true })

watch(nowTick, () => {
  if (step.value >= 3 && form.date_rdv) {
    syncSelectedDaySlots()
  }
})

onMounted(async () => {
  nowTimer = window.setInterval(() => {
    nowTick.value = Date.now()
  }, 60000)
  await loadAteliers()
})

onBeforeUnmount(() => {
  if (nowTimer !== null) {
    clearInterval(nowTimer)
  }
})
</script>
