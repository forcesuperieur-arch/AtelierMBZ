<template>
  <div>
    <div class="page-header">
      <div class="page-title">Véhicules d'Occasion</div>
      <button class="topbar-new-btn" @click="openNewPurchase">+ Nouveau rachat</button>
      <button class="topbar-new-btn" style="margin-left:8px;background:#6366f1;" @click="openNewDepot">+ Dépôt-vente</button>
    </div>

    <!-- Stats cards -->
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px;" v-if="voStore.stats">
      <StatsCard title="En stock" :value="voStore.stats.en_stock" icon="🏍️" />
      <StatsCard title="Vendus" :value="voStore.stats.vendus" icon="✅" />
      <StatsCard title="Dépôts actifs" :value="voStore.stats.depots_actifs" icon="📋" />
      <StatsCard title="Alertes" :value="voStore.stats.alerts_count" icon="⚠️" :color="voStore.stats.alerts_count > 0 ? 'warning' : 'primary'" />
    </div>

    <!-- Tabs -->
    <div style="display:flex;gap:2px;margin-bottom:16px;">
      <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key"
        :style="{ padding:'8px 16px', borderRadius:'8px 8px 0 0', fontWeight:600, fontSize:'13px',
          background: activeTab === tab.key ? '#1a1a2e' : '#111827',
          color: activeTab === tab.key ? '#FFD200' : '#9CA3AF',
          border: activeTab === tab.key ? '1px solid #374151' : '1px solid transparent',
          borderBottom: activeTab === tab.key ? '1px solid #1a1a2e' : '1px solid #374151',
          cursor:'pointer' }">
        {{ tab.label }}
      </button>
    </div>

    <!-- TAB: Rachats -->
    <UCard v-if="activeTab === 'purchases'">
      <template #header>
        <div style="display:flex;justify-content:space-between;align-items:center;">
          <span style="font-size:15px;font-weight:700;color:#E8E9ED;">Rachats</span>
          <UInput v-model="searchPurchases" placeholder="Rechercher..." style="max-width:250px;" />
        </div>
      </template>
      <UTable :data="filteredPurchases" :columns="purchaseColumns" :loading="voStore.loading">
        <template #status-cell="{ row }">
          <StatusBadge :status="row.original.status" />
        </template>
        <template #purchasePrice-cell="{ row }">
          {{ formatPrice(row.original.purchasePrice) }}
        </template>
        <template #targetSalePrice-cell="{ row }">
          {{ formatPrice(row.original.targetSalePrice) }}
        </template>
        <template #actions-cell="{ row }">
          <div style="display:flex;gap:8px;">
            <button class="btn-action" @click="viewPurchase(row.original)">👁</button>
            <button class="btn-action" @click="editPurchase(row.original)">✏</button>
            <button v-if="row.original.status === 'brouillon'" class="btn-action btn-confirm" @click="confirmPurchase(row.original.id)">✓ Confirmer</button>
            <button v-if="['en_stock','en_vente','reserve'].includes(row.original.status)" class="btn-action btn-sell" @click="openSell(row.original, 'purchase')">💰 Vendre</button>
            <button class="btn-action" @click="downloadPvRachat(row.original.id)">📄</button>
          </div>
        </template>
      </UTable>
    </UCard>

    <!-- TAB: Dépôts-vente -->
    <UCard v-if="activeTab === 'depots'">
      <template #header>
        <span style="font-size:15px;font-weight:700;color:#E8E9ED;">Dépôts-vente</span>
      </template>
      <UTable :data="voStore.depots" :columns="depotColumns" :loading="voStore.loading">
        <template #status-cell="{ row }">
          <StatusBadge :status="row.original.status" />
        </template>
        <template #prixVenteSouhaite-cell="{ row }">
          {{ formatPrice(row.original.prixVenteSouhaite) }}
        </template>
        <template #actions-cell="{ row }">
          <div style="display:flex;gap:8px;">
            <button class="btn-action" @click="viewDepot(row.original)">👁</button>
            <button class="btn-action" @click="editDepot(row.original)">✏</button>
            <button v-if="row.original.status === 'actif'" class="btn-action btn-sell" @click="openSell(row.original, 'depot')">💰 Vendre</button>
            <button class="btn-action" @click="downloadContrat(row.original.id)">📄</button>
          </div>
        </template>
      </UTable>
    </UCard>

    <!-- TAB: Livre de Police -->
    <UCard v-if="activeTab === 'livrepolice'">
      <template #header>
        <div style="display:flex;justify-content:space-between;align-items:center;">
          <span style="font-size:15px;font-weight:700;color:#E8E9ED;">Livre de Police</span>
          <button class="topbar-new-btn" style="font-size:12px;" @click="downloadLivrePolice">📥 Exporter PDF</button>
        </div>
      </template>
      <UTable :data="voStore.livrePolice" :columns="lpColumns" :loading="voStore.loading">
        <template #prixAchat-cell="{ row }">
          {{ formatPrice(row.original.prixAchat) }}
        </template>
        <template #prixVente-cell="{ row }">
          {{ row.original.prixVente ? formatPrice(row.original.prixVente) : '—' }}
        </template>
      </UTable>
    </UCard>

    <!-- TAB: Factures VO -->
    <UCard v-if="activeTab === 'factures'">
      <template #header>
        <span style="font-size:15px;font-weight:700;color:#E8E9ED;">Factures VO</span>
      </template>
      <UTable :data="voStore.factures" :columns="factureColumns" :loading="voStore.loading">
        <template #totalTtc-cell="{ row }">
          {{ formatPrice(row.original.totalTtc) }}
        </template>
        <template #statut-cell="{ row }">
          <StatusBadge :status="row.original.statut" />
        </template>
        <template #actions-cell="{ row }">
          <button class="btn-action" @click="downloadFacture(row.original.id)">📄 PDF</button>
        </template>
      </UTable>
    </UCard>

    <!-- TAB: Documents -->
    <UCard v-if="activeTab === 'documents'">
      <template #header>
        <div style="display:flex;justify-content:space-between;align-items:center;">
          <span style="font-size:15px;font-weight:700;color:#E8E9ED;">Documents</span>
          <button class="topbar-new-btn" style="font-size:12px;" @click="showUpload = true">+ Ajouter</button>
        </div>
      </template>

      <!-- Alerts -->
      <div v-if="voStore.alerts.length" style="margin-bottom:12px;padding:10px;border-radius:8px;background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);">
        <div style="font-weight:700;color:#FCA5A5;margin-bottom:6px;">⚠ {{ voStore.alerts.length }} alerte(s)</div>
        <div v-for="(alert, i) in voStore.alerts.slice(0, 5)" :key="i" style="font-size:12px;color:#D1D5DB;padding:2px 0;">
          {{ alert.message }}
        </div>
      </div>

      <UTable :data="voStore.documents" :columns="docColumns" :loading="voStore.loading">
        <template #actions-cell="{ row }">
          <a :href="apiBase + row.original.filePath" target="_blank" class="btn-action">📥 Télécharger</a>
        </template>
      </UTable>
    </UCard>

    <!-- MODAL: New/Edit Purchase -->
    <AppModal v-model:open="showPurchaseForm" size="xl">
      <UCard>
        <template #header><span style="font-size:15px;font-weight:700;color:#E8E9ED;">{{ purchaseForm.id ? 'Modifier' : 'Nouveau' }} rachat</span></template>
        <form @submit.prevent="savePurchase" style="display:flex;flex-direction:column;gap:12px;">
          <div class="vo-flow-card">
            <div class="vo-flow-title">Vendeur existant (base interne)</div>
            <div class="form-group">
              <div class="form-label">RECHERCHE CLIENT</div>
              <input
                v-model="purchaseSellerSearch"
                type="text"
                class="form-input"
                placeholder="Nom, prénom, téléphone ou email..."
                @input="searchPurchaseSellers"
              />
              <div class="lookup-meta">Tapez au moins 2 caractères pour retrouver le vendeur comme dans la prise de RDV.</div>
            </div>
            <div v-if="purchaseSellerResults.length" class="lookup-results">
              <div
                v-for="client in purchaseSellerResults.slice(0, 6)"
                :key="client.id"
                class="lookup-item"
                @mousedown.prevent="selectPurchaseSeller(client)"
              >
                <span style="font-weight:600;">{{ client.prenom }} {{ client.nom }}</span> — {{ client.telephone || client.email || 'Sans contact' }}
              </div>
            </div>
            <div v-if="selectedPurchaseSeller" class="selected-pill">
              <span style="font-size:13px;color:#FFD200;font-weight:600;">✓ {{ selectedPurchaseSeller.prenom }} {{ selectedPurchaseSeller.nom }}</span>
              <button type="button" class="btn btn-ghost" style="margin-left:auto;min-height:30px;padding:4px 10px;font-size:12px;" @click="clearPurchaseSeller">✕ Changer</button>
            </div>
          </div>

          <div class="vo-flow-card">
            <div class="vo-flow-title">Véhicule</div>
            <div class="form-group">
              <div class="form-label">IMMATRICULATION / VIN</div>
              <div style="display:flex;gap:10px;">
                <input
                  v-model="purchaseVehicleSearch"
                  type="text"
                  class="form-input"
                  placeholder="Ex: AB-123-CD ou VIN"
                  @blur="purchaseVehicleSearch = formatRegistrationOrVin(purchaseVehicleSearch)"
                  @keydown.enter.prevent="lookupPurchaseVehicle"
                />
                <button type="button" class="btn btn-primary" @click="lookupPurchaseVehicle">Rechercher</button>
              </div>
              <div class="lookup-meta">Même recherche par plaque que dans le module RDV.</div>
            </div>
            <div v-if="purchaseVehiclePreview" class="vehicle-found-card">
              <div style="font-size:13px;color:#10B981;font-weight:600;margin-bottom:4px;">Véhicule trouvé</div>
              <div style="font-size:14px;color:#E8E9ED;font-weight:600;">{{ purchaseVehiclePreview.marque || '' }} {{ purchaseVehiclePreview.modele || '' }}</div>
              <div style="font-size:12px;color:#9CA3AF;">{{ purchaseVehiclePreview.plaque || 'Sans plaque' }} • {{ purchaseVehiclePreview.cylindree || 'Cylindrée n.c.' }}</div>
            </div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <UFormField label="Prix d'achat"><UInput v-model="purchaseForm.purchasePrice" type="number" step="0.01" required /></UFormField>
            <UFormField label="Prix de vente cible"><UInput v-model="purchaseForm.targetSalePrice" type="number" step="0.01" /></UFormField>
            <UFormField label="Date d'achat"><UInput v-model="purchaseForm.purchaseDate" type="date" /></UFormField>
            <UFormField label="Date non-gage"><UInput v-model="purchaseForm.nonGageDate" type="date" /></UFormField>
            <UFormField label="Régime TVA">
              <select v-model="purchaseForm.regimeTva" style="width:100%;padding:8px;border-radius:6px;background:#1a1a2e;color:#E8E9ED;border:1px solid #374151;">
                <option value="marge">TVA sur marge</option>
                <option value="normal">TVA normale</option>
              </select>
            </UFormField>
            <UFormField label="Expert (ID, optionnel)"><UInput v-model="purchaseForm.expertId" type="number" /></UFormField>
          </div>

          <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:#D1D5DB;">
            <input v-model="purchaseForm.controleTechniqueOk" type="checkbox" />
            Contrôle technique vérifié
          </label>

          <div style="font-weight:600;color:#9CA3AF;margin-top:8px;">Identité vendeur pour le livre de police</div>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
            <UFormField label="Type pièce"><UInput v-model="purchaseForm.sellerIdType" placeholder="CNI, Passeport..." /></UFormField>
            <UFormField label="Numéro"><UInput v-model="purchaseForm.sellerIdNumber" /></UFormField>
            <UFormField label="Date délivrance"><UInput v-model="purchaseForm.sellerIdDate" type="date" /></UFormField>
          </div>

          <div :class="purchaseChecklistMissing.length ? 'assistant-check warning' : 'assistant-check success'">
            <strong>{{ purchaseChecklistMissing.length ? 'À compléter avant confirmation' : 'Dossier prêt pour confirmation' }}</strong>
            <div style="font-size:12px;margin-top:4px;">
              {{ purchaseChecklistMissing.length ? purchaseChecklistMissing.join(', ') : 'Le rachat pourra générer automatiquement le PV et le registre.' }}
            </div>
          </div>

          <UFormField label="Notes"><UTextarea v-model="purchaseForm.notes" /></UFormField>

          <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:8px;">
            <button type="button" class="btn-cancel" @click="showPurchaseForm = false">Annuler</button>
            <button type="submit" class="topbar-new-btn">{{ purchaseForm.id ? 'Modifier' : 'Créer le dossier' }}</button>
          </div>
        </form>
      </UCard>
    </AppModal>

    <!-- MODAL: New Dépôt-vente -->
    <AppModal v-model:open="showDepotForm" size="xl">
      <UCard>
        <template #header><span style="font-size:15px;font-weight:700;color:#E8E9ED;">{{ depotForm.id ? 'Modifier' : 'Nouveau' }} dépôt-vente</span></template>
        <form @submit.prevent="saveDepot" style="display:flex;flex-direction:column;gap:12px;">
          <div class="vo-flow-card">
            <div class="vo-flow-title">Déposant existant (base interne)</div>
            <div class="form-group">
              <div class="form-label">RECHERCHE CLIENT</div>
              <input
                v-model="depotDeposantSearch"
                type="text"
                class="form-input"
                placeholder="Nom, prénom, téléphone ou email..."
                @input="searchDepotDeposants"
              />
              <div class="lookup-meta">Utilise la même recherche que la prise de RDV.</div>
            </div>
            <div v-if="depotDeposantResults.length" class="lookup-results">
              <div
                v-for="client in depotDeposantResults.slice(0, 6)"
                :key="client.id"
                class="lookup-item"
                @mousedown.prevent="selectDepotDeposant(client)"
              >
                <span style="font-weight:600;">{{ client.prenom }} {{ client.nom }}</span> — {{ client.telephone || client.email || 'Sans contact' }}
              </div>
            </div>
            <div v-if="selectedDepotDeposant" class="selected-pill">
              <span style="font-size:13px;color:#FFD200;font-weight:600;">✓ {{ selectedDepotDeposant.prenom }} {{ selectedDepotDeposant.nom }}</span>
              <button type="button" class="btn btn-ghost" style="margin-left:auto;min-height:30px;padding:4px 10px;font-size:12px;" @click="clearDepotDeposant">✕ Changer</button>
            </div>
          </div>

          <div class="vo-flow-card">
            <div class="vo-flow-title">Véhicule déposé</div>
            <div class="form-group">
              <div class="form-label">IMMATRICULATION / VIN</div>
              <div style="display:flex;gap:10px;">
                <input
                  v-model="depotVehicleSearch"
                  type="text"
                  class="form-input"
                  placeholder="Ex: AB-123-CD ou VIN"
                  @blur="depotVehicleSearch = formatRegistrationOrVin(depotVehicleSearch)"
                  @keydown.enter.prevent="lookupDepotVehicle"
                />
                <button type="button" class="btn btn-primary" @click="lookupDepotVehicle">Rechercher</button>
              </div>
            </div>
            <div v-if="depotVehiclePreview" class="vehicle-found-card">
              <div style="font-size:13px;color:#10B981;font-weight:600;margin-bottom:4px;">Véhicule trouvé</div>
              <div style="font-size:14px;color:#E8E9ED;font-weight:600;">{{ depotVehiclePreview.marque || '' }} {{ depotVehiclePreview.modele || '' }}</div>
              <div style="font-size:12px;color:#9CA3AF;">{{ depotVehiclePreview.plaque || 'Sans plaque' }} • {{ depotVehiclePreview.cylindree || 'Cylindrée n.c.' }}</div>
            </div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <UFormField label="Prix de vente souhaité"><UInput v-model="depotForm.prixVenteSouhaite" type="number" step="0.01" required /></UFormField>
            <UFormField label="Commission type">
              <select v-model="depotForm.commissionType" style="width:100%;padding:8px;border-radius:6px;background:#1a1a2e;color:#E8E9ED;border:1px solid #374151;">
                <option value="pourcentage">Pourcentage</option>
                <option value="forfait">Forfait</option>
              </select>
            </UFormField>
            <UFormField label="Commission valeur"><UInput v-model="depotForm.commissionValeur" type="number" step="0.01" /></UFormField>
            <UFormField label="Durée mandat (jours)"><UInput v-model="depotForm.dureeMandat" type="number" /></UFormField>
            <UFormField label="Date début"><UInput v-model="depotForm.dateDebut" type="date" /></UFormField>
            <UFormField label="Gestionnaire (ID, optionnel)"><UInput v-model="depotForm.gestionnaireId" type="number" /></UFormField>
          </div>

          <div style="font-weight:600;color:#9CA3AF;margin-top:8px;">Identité déposant</div>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
            <UFormField label="Type pièce"><UInput v-model="depotForm.deposantIdType" placeholder="CNI, Passeport..." /></UFormField>
            <UFormField label="Numéro"><UInput v-model="depotForm.deposantIdNumber" /></UFormField>
            <UFormField label="Date"><UInput v-model="depotForm.deposantIdDate" type="date" /></UFormField>
          </div>

          <div :class="depotChecklistMissing.length ? 'assistant-check warning' : 'assistant-check success'">
            <strong>{{ depotChecklistMissing.length ? 'À compléter avant validation' : 'Dossier prêt à générer le mandat' }}</strong>
            <div style="font-size:12px;margin-top:4px;">
              {{ depotChecklistMissing.length ? depotChecklistMissing.join(', ') : 'Le contrat dépôt-vente sera archivé automatiquement.' }}
            </div>
          </div>

          <UFormField label="Conditions restitution"><UTextarea v-model="depotForm.conditionsRestitution" /></UFormField>
          <UFormField label="Assurance / infos complémentaires"><UTextarea v-model="depotForm.assuranceInfo" /></UFormField>
          <UFormField label="Notes"><UTextarea v-model="depotForm.notes" /></UFormField>

          <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:8px;">
            <button type="button" class="btn-cancel" @click="showDepotForm = false">Annuler</button>
            <button type="submit" class="topbar-new-btn">{{ depotForm.id ? 'Modifier' : 'Créer le dossier' }}</button>
          </div>
        </form>
      </UCard>
    </AppModal>

    <!-- MODAL: Sell -->
    <AppModal v-model:open="showSellModal" size="md">
      <UCard>
        <template #header><span style="font-size:15px;font-weight:700;color:#E8E9ED;">Vente du véhicule</span></template>
        <form @submit.prevent="executeSell" style="display:flex;flex-direction:column;gap:12px;">
          <div class="vo-flow-card" style="padding:14px;">
            <div class="vo-flow-title">Acheteur existant (base interne)</div>
            <div class="form-group">
              <div class="form-label">RECHERCHE CLIENT</div>
              <input
                v-model="buyerSearch"
                type="text"
                class="form-input"
                placeholder="Nom, prénom, téléphone ou email..."
                @input="searchBuyers"
              />
            </div>
            <div v-if="buyerResults.length" class="lookup-results">
              <div
                v-for="client in buyerResults.slice(0, 6)"
                :key="client.id"
                class="lookup-item"
                @mousedown.prevent="selectBuyer(client)"
              >
                <span style="font-weight:600;">{{ client.prenom }} {{ client.nom }}</span> — {{ client.telephone || client.email || 'Sans contact' }}
              </div>
            </div>
            <div v-if="selectedBuyer" class="selected-pill">
              <span style="font-size:13px;color:#FFD200;font-weight:600;">✓ {{ selectedBuyer.prenom }} {{ selectedBuyer.nom }}</span>
              <button type="button" class="btn btn-ghost" style="margin-left:auto;min-height:30px;padding:4px 10px;font-size:12px;" @click="clearBuyer">✕ Changer</button>
            </div>
          </div>
          <UFormField label="Prix de vente"><UInput v-model="sellForm.salePrice" type="number" step="0.01" required /></UFormField>
          <UFormField label="Notes"><UTextarea v-model="sellForm.notes" /></UFormField>
          <div class="assistant-check success">
            <strong>Automatique après validation</strong>
            <div style="font-size:12px;margin-top:4px;">Facture VO générée et archivée dans le dossier documentaire.</div>
          </div>
          <div style="display:flex;gap:8px;justify-content:flex-end;">
            <button type="button" class="btn-cancel" @click="showSellModal = false">Annuler</button>
            <button type="submit" class="topbar-new-btn" style="background:#22c55e;">💰 Confirmer la vente</button>
          </div>
        </form>
      </UCard>
    </AppModal>

    <!-- MODAL: Upload Document -->
    <AppModal v-model:open="showUpload" size="md">
      <UCard>
        <template #header><span style="font-size:15px;font-weight:700;color:#E8E9ED;">Ajouter un document</span></template>
        <form @submit.prevent="submitUpload" style="display:flex;flex-direction:column;gap:12px;">
          <UFormField label="Type">
            <select v-model="uploadForm.type" required style="width:100%;padding:8px;border-radius:6px;background:#1a1a2e;color:#E8E9ED;border:1px solid #374151;">
              <option v-for="dt in docTypes" :key="dt.value" :value="dt.value">{{ dt.label }}</option>
            </select>
          </UFormField>
          <UFormField label="Rachat (ID, optionnel)"><UInput v-model="uploadForm.purchaseId" type="number" /></UFormField>
          <UFormField label="Dépôt-vente (ID, optionnel)"><UInput v-model="uploadForm.depotId" type="number" /></UFormField>
          <UFormField label="Date expiration"><UInput v-model="uploadForm.dateExpiration" type="date" /></UFormField>
          <UFormField label="Fichier">
            <input type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" @change="onFileChange" required
              style="color:#D1D5DB;font-size:13px;" />
          </UFormField>
          <div style="display:flex;gap:8px;justify-content:flex-end;">
            <button type="button" class="btn-cancel" @click="showUpload = false">Annuler</button>
            <button type="submit" class="topbar-new-btn">📤 Envoyer</button>
          </div>
        </form>
      </UCard>
    </AppModal>

    <!-- MODAL: View Purchase detail -->
    <AppModal v-model:open="showDetail" size="xl">
      <UCard v-if="detailData">
        <template #header>
          <span style="font-size:15px;font-weight:700;color:#E8E9ED;">
            Détail — {{ detailData.vehicule?.marque }} {{ detailData.vehicule?.modele }}
          </span>
        </template>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;font-size:13px;">
          <div>
            <div class="detail-label">Immatriculation</div>
            <div>{{ detailData.vehicule?.plaque }}</div>
          </div>
          <div>
            <div class="detail-label">Statut</div>
            <StatusBadge :status="detailData.status" />
          </div>
          <div>
            <div class="detail-label">Prix d'achat</div>
            <div>{{ formatPrice(detailData.purchasePrice) }}</div>
          </div>
          <div>
            <div class="detail-label">Prix de vente cible</div>
            <div>{{ formatPrice(detailData.targetSalePrice) }}</div>
          </div>
          <div>
            <div class="detail-label">Marge estimée</div>
            <div :style="{ color: parseFloat(detailData.margin || '0') >= 0 ? '#22c55e' : '#ef4444', fontWeight:'bold' }">
              {{ formatPrice(detailData.margin || '0') }}
            </div>
          </div>
          <div>
            <div class="detail-label">Total FRE</div>
            <div>{{ formatPrice(detailData.totalFre || '0') }}</div>
          </div>
          <div>
            <div class="detail-label">Vendeur</div>
            <div>{{ detailData.seller?.prenom }} {{ detailData.seller?.nom }}</div>
          </div>
          <div>
            <div class="detail-label">Régime TVA</div>
            <div>{{ detailData.regimeTva === 'marge' ? 'TVA sur marge' : 'TVA normale' }}</div>
          </div>
        </div>

        <!-- Missing documents alert -->
        <div v-if="detailData.missingDocuments?.length" style="margin-top:16px;padding:10px;border-radius:8px;background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);">
          <div style="font-weight:700;color:#FCA5A5;margin-bottom:4px;">Documents manquants</div>
          <div v-for="doc in detailData.missingDocuments" :key="doc" style="font-size:12px;color:#D1D5DB;">• {{ doc }}</div>
        </div>

        <!-- FRE table -->
        <div v-if="detailData.repairEstimates?.length" style="margin-top:16px;">
          <div style="font-weight:600;color:#9CA3AF;margin-bottom:8px;">Frais de Remise en État</div>
          <table style="width:100%;font-size:12px;">
            <tr v-for="(fre, i) in detailData.repairEstimates" :key="i" style="border-bottom:1px solid #374151;">
              <td style="padding:4px 8px;color:#D1D5DB;">{{ fre.label }}</td>
              <td style="padding:4px 8px;text-align:right;color:#E8E9ED;">{{ formatPrice(fre.amount) }}</td>
            </tr>
          </table>
        </div>
      </UCard>
    </AppModal>
  </div>
</template>

<script setup lang="ts">
import { useVoStore } from '~/stores/vo'

const voStore = useVoStore()
const api = useApi()
const toast = useToast()
const config = useRuntimeConfig()
const apiBase = config.public.apiBase as string

const activeTab = ref('purchases')
const searchPurchases = ref('')
const showPurchaseForm = ref(false)
const showDepotForm = ref(false)
const showSellModal = ref(false)
const showUpload = ref(false)
const showDetail = ref(false)
const detailData = ref<any>(null)
const sellTarget = ref<{ id: number; type: 'purchase' | 'depot' } | null>(null)
const uploadFile = ref<File | null>(null)
const purchaseVehicleSearch = ref('')
const purchaseVehiclePreview = ref<any | null>(null)
const purchaseSellerSearch = ref('')
const purchaseSellerResults = ref<any[]>([])
const selectedPurchaseSeller = ref<any | null>(null)
const depotVehicleSearch = ref('')
const depotVehiclePreview = ref<any | null>(null)
const depotDeposantSearch = ref('')
const depotDeposantResults = ref<any[]>([])
const selectedDepotDeposant = ref<any | null>(null)
const buyerSearch = ref('')
const buyerResults = ref<any[]>([])
const selectedBuyer = ref<any | null>(null)
let clientSearchTimeout: ReturnType<typeof setTimeout> | undefined

const tabs = [
  { key: 'purchases', label: '🏍️ Rachats' },
  { key: 'depots', label: '📋 Dépôts-vente' },
  { key: 'livrepolice', label: '📕 Livre de Police' },
  { key: 'factures', label: '💳 Factures' },
  { key: 'documents', label: '📁 Documents' },
]

const purchaseColumns = [
  { accessorKey: 'vehicule.plaque', header: 'Immat.' },
  { accessorKey: 'vehicule.marque', header: 'Marque' },
  { accessorKey: 'vehicule.modele', header: 'Modèle' },
  { accessorKey: 'purchasePrice', header: 'Prix achat' },
  { accessorKey: 'targetSalePrice', header: 'Prix vente' },
  { accessorKey: 'status', header: 'Statut' },
  { accessorKey: 'actions', header: '' },
]

const depotColumns = [
  { accessorKey: 'vehicule.plaque', header: 'Immat.' },
  { accessorKey: 'vehicule.marque', header: 'Marque' },
  { accessorKey: 'deposant.nom', header: 'Déposant' },
  { accessorKey: 'prixVenteSouhaite', header: 'Prix souhaité' },
  { accessorKey: 'status', header: 'Statut' },
  { accessorKey: 'actions', header: '' },
]

const lpColumns = [
  { accessorKey: 'numeroOrdre', header: 'N°' },
  { accessorKey: 'type', header: 'Type' },
  { accessorKey: 'dateAcquisition', header: 'Date acq.' },
  { accessorKey: 'descriptionBien', header: 'Description' },
  { accessorKey: 'immatriculation', header: 'Immat.' },
  { accessorKey: 'vendeurNom', header: 'Vendeur' },
  { accessorKey: 'prixAchat', header: 'Prix achat' },
  { accessorKey: 'prixVente', header: 'Prix vente' },
]

const factureColumns = [
  { accessorKey: 'numeroFacture', header: 'N° Facture' },
  { accessorKey: 'snapClientNom', header: 'Client' },
  { accessorKey: 'snapVehiculeMarque', header: 'Véhicule' },
  { accessorKey: 'totalTtc', header: 'Total TTC' },
  { accessorKey: 'statut', header: 'Statut' },
  { accessorKey: 'dateCreation', header: 'Date' },
  { accessorKey: 'actions', header: '' },
]

const docColumns = [
  { accessorKey: 'type', header: 'Type' },
  { accessorKey: 'originalFilename', header: 'Fichier' },
  { accessorKey: 'uploadedAt', header: 'Date upload' },
  { accessorKey: 'dateExpiration', header: 'Expiration' },
  { accessorKey: 'actions', header: '' },
]

const docTypes = [
  { value: 'cerfa_cession_achat', label: 'CERFA Cession (Achat)' },
  { value: 'cerfa_cession_vente', label: 'CERFA Cession (Vente)' },
  { value: 'carte_grise', label: 'Carte grise' },
  { value: 'non_gage', label: 'Certificat non-gage' },
  { value: 'controle_technique', label: 'Contrôle technique' },
  { value: 'piece_identite', label: 'Pièce d\'identité' },
  { value: 'contrat_depot_vente', label: 'Contrat dépôt-vente' },
  { value: 'facture_vo', label: 'Facture VO' },
  { value: 'pv_rachat', label: 'PV de rachat' },
  { value: 'notice_garantie', label: 'Notice de garantie' },
  { value: 'autre', label: 'Autre' },
]

// ── Forms ──

const defaultPurchaseForm = () => ({
  id: null as number | null,
  vehiculeId: '',
  sellerId: '',
  purchasePrice: '',
  targetSalePrice: '',
  purchaseDate: '',
  regimeTva: 'marge',
  expertId: '',
  nonGageDate: '',
  sellerIdType: '',
  sellerIdNumber: '',
  sellerIdDate: '',
  controleTechniqueOk: false,
  notes: '',
})

const defaultDepotForm = () => ({
  id: null as number | null,
  vehiculeId: '',
  deposantId: '',
  prixVenteSouhaite: '',
  commissionType: 'pourcentage',
  commissionValeur: '',
  dureeMandat: 90,
  dateDebut: new Date().toISOString().split('T')[0],
  gestionnaireId: '',
  deposantIdType: '',
  deposantIdNumber: '',
  deposantIdDate: '',
  conditionsRestitution: '',
  assuranceInfo: '',
  notes: '',
})

const purchaseForm = ref(defaultPurchaseForm())
const depotForm = ref(defaultDepotForm())
const sellForm = ref({ buyerId: '', salePrice: '', notes: '' })
const uploadForm = ref({ type: 'cerfa_cession_achat', purchaseId: '', depotId: '', dateExpiration: '' })

// ── Computed ──

const filteredPurchases = computed(() => {
  if (!searchPurchases.value) return voStore.purchases
  const q = searchPurchases.value.toLowerCase()
  return voStore.purchases.filter(p =>
    p.vehicule?.plaque?.toLowerCase().includes(q) ||
    p.vehicule?.marque?.toLowerCase().includes(q) ||
    p.vehicule?.modele?.toLowerCase().includes(q) ||
    p.seller?.nom?.toLowerCase().includes(q)
  )
})

const purchaseChecklistMissing = computed(() => {
  const missing: string[] = []
  if (!purchaseForm.value.vehiculeId) missing.push('véhicule')
  if (!purchaseForm.value.sellerId) missing.push('vendeur')
  if (!purchaseForm.value.purchasePrice) missing.push('prix d\'achat')
  if (!purchaseForm.value.sellerIdType || !purchaseForm.value.sellerIdNumber || !purchaseForm.value.sellerIdDate) {
    missing.push('identité vendeur')
  }
  return missing
})

const depotChecklistMissing = computed(() => {
  const missing: string[] = []
  if (!depotForm.value.vehiculeId) missing.push('véhicule')
  if (!depotForm.value.deposantId) missing.push('déposant')
  if (!depotForm.value.prixVenteSouhaite) missing.push('prix de vente')
  if (!depotForm.value.deposantIdType || !depotForm.value.deposantIdNumber || !depotForm.value.deposantIdDate) {
    missing.push('identité déposant')
  }
  return missing
})

// ── Data loading ──

async function loadTab() {
  if (activeTab.value === 'purchases') await voStore.fetchPurchases()
  else if (activeTab.value === 'depots') await voStore.fetchDepots()
  else if (activeTab.value === 'livrepolice') await voStore.fetchLivrePolice()
  else if (activeTab.value === 'factures') await voStore.fetchFactures()
  else if (activeTab.value === 'documents') {
    await voStore.fetchDocuments()
    await voStore.fetchAlerts()
  }
}

watch(() => activeTab.value, loadTab)

onMounted(async () => {
  await voStore.fetchStats()
  await loadTab()
})

// ── Actions ──

function formatPrice(v: string | number) {
  return parseFloat(String(v || '0')).toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' })
}

function extractCollection(data: any) {
  return data?.['hydra:member'] ?? data?.member ?? (Array.isArray(data) ? data : [])
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

async function findVehiculeByQuery(search: string) {
  const query = normalizeVehiculeQuery(search)
  if (!query) return null

  try {
    return await api.get(`/vehicule/${encodeURIComponent(query)}`)
  } catch {
    const collection = await api.get(`/vehicules?plaque=${encodeURIComponent(query)}`).catch(() => null)
    return extractCollection(collection)[0] ?? null
  }
}

function queueClientSearch(term: string, target: any) {
  clearTimeout(clientSearchTimeout)
  if (term.trim().length < 2) {
    target.value = []
    return
  }

  clientSearchTimeout = setTimeout(async () => {
    const data = await api.get(`/clients?search=${encodeURIComponent(term.trim())}`).catch(() => null)
    target.value = extractCollection(data)
  }, 250)
}

function searchPurchaseSellers() {
  queueClientSearch(purchaseSellerSearch.value, purchaseSellerResults)
}

function searchDepotDeposants() {
  queueClientSearch(depotDeposantSearch.value, depotDeposantResults)
}

function searchBuyers() {
  queueClientSearch(buyerSearch.value, buyerResults)
}

function selectPurchaseSeller(client: any) {
  selectedPurchaseSeller.value = client
  purchaseForm.value.sellerId = String(client.id)
  purchaseSellerSearch.value = `${client.prenom || ''} ${client.nom || ''}`.trim()
  purchaseSellerResults.value = []

  if (client.vehicules?.length) {
    const vehicle = client.vehicules[0]
    purchaseVehiclePreview.value = vehicle
    purchaseVehicleSearch.value = formatRegistrationOrVin(vehicle.plaque || '')
    purchaseForm.value.vehiculeId = String(vehicle.id || '')
  }
}

function selectDepotDeposant(client: any) {
  selectedDepotDeposant.value = client
  depotForm.value.deposantId = String(client.id)
  depotDeposantSearch.value = `${client.prenom || ''} ${client.nom || ''}`.trim()
  depotDeposantResults.value = []

  if (client.vehicules?.length) {
    const vehicle = client.vehicules[0]
    depotVehiclePreview.value = vehicle
    depotVehicleSearch.value = formatRegistrationOrVin(vehicle.plaque || '')
    depotForm.value.vehiculeId = String(vehicle.id || '')
  }
}

function selectBuyer(client: any) {
  selectedBuyer.value = client
  sellForm.value.buyerId = String(client.id)
  buyerSearch.value = `${client.prenom || ''} ${client.nom || ''}`.trim()
  buyerResults.value = []
}

async function lookupPurchaseVehicle() {
  const vehicule = await findVehiculeByQuery(purchaseVehicleSearch.value)
  if (!vehicule?.id) {
    purchaseVehiclePreview.value = null
    purchaseForm.value.vehiculeId = ''
    if (purchaseVehicleSearch.value.trim()) {
      toast.add({ title: 'Véhicule introuvable', description: 'Créez-le d\'abord dans le module clients/véhicules.', color: 'warning' })
    }
    return
  }

  purchaseVehiclePreview.value = vehicule
  purchaseVehicleSearch.value = vehicule.plaque || purchaseVehicleSearch.value
  purchaseForm.value.vehiculeId = String(vehicule.id)
}

async function lookupDepotVehicle() {
  const vehicule = await findVehiculeByQuery(depotVehicleSearch.value)
  if (!vehicule?.id) {
    depotVehiclePreview.value = null
    depotForm.value.vehiculeId = ''
    if (depotVehicleSearch.value.trim()) {
      toast.add({ title: 'Véhicule introuvable', description: 'Créez-le d\'abord dans le module clients/véhicules.', color: 'warning' })
    }
    return
  }

  depotVehiclePreview.value = vehicule
  depotVehicleSearch.value = vehicule.plaque || depotVehicleSearch.value
  depotForm.value.vehiculeId = String(vehicule.id)
}

function clearPurchaseSeller() {
  selectedPurchaseSeller.value = null
  purchaseForm.value.sellerId = ''
  purchaseSellerSearch.value = ''
  purchaseSellerResults.value = []
}

function clearDepotDeposant() {
  selectedDepotDeposant.value = null
  depotForm.value.deposantId = ''
  depotDeposantSearch.value = ''
  depotDeposantResults.value = []
}

function clearBuyer() {
  selectedBuyer.value = null
  sellForm.value.buyerId = ''
  buyerSearch.value = ''
  buyerResults.value = []
}

function resetPurchaseAssistant() {
  purchaseVehicleSearch.value = ''
  purchaseVehiclePreview.value = null
  clearPurchaseSeller()
}

function resetDepotAssistant() {
  depotVehicleSearch.value = ''
  depotVehiclePreview.value = null
  clearDepotDeposant()
}

function openNewPurchase() {
  purchaseForm.value = defaultPurchaseForm()
  resetPurchaseAssistant()
  showPurchaseForm.value = true
}

function openNewDepot() {
  depotForm.value = defaultDepotForm()
  resetDepotAssistant()
  showDepotForm.value = true
}

function editPurchase(p: any) {
  purchaseForm.value = {
    id: p.id,
    vehiculeId: p.vehicule?.id || '',
    sellerId: p.seller?.id || '',
    purchasePrice: p.purchasePrice,
    targetSalePrice: p.targetSalePrice,
    purchaseDate: p.purchaseDate?.split('T')[0] || '',
    regimeTva: p.regimeTva,
    expertId: p.expert?.id || '',
    nonGageDate: p.nonGageDate?.split('T')[0] || '',
    sellerIdType: p.sellerIdType || '',
    sellerIdNumber: p.sellerIdNumber || '',
    sellerIdDate: p.sellerIdDate?.split('T')[0] || '',
    controleTechniqueOk: Boolean(p.controleTechniqueOk),
    notes: p.notes || '',
  }
  purchaseVehicleSearch.value = p.vehicule?.plaque || ''
  purchaseVehiclePreview.value = p.vehicule || null
  selectedPurchaseSeller.value = p.seller || null
  purchaseSellerSearch.value = [p.seller?.prenom, p.seller?.nom].filter(Boolean).join(' ')
  purchaseSellerResults.value = []
  showPurchaseForm.value = true
}

function editDepot(d: any) {
  depotForm.value = {
    id: d.id,
    vehiculeId: d.vehicule?.id || '',
    deposantId: d.deposant?.id || '',
    prixVenteSouhaite: d.prixVenteSouhaite,
    commissionType: d.commissionType,
    commissionValeur: d.commissionValeur,
    dureeMandat: d.dureeMandat,
    dateDebut: d.dateDebut?.split('T')[0] || '',
    gestionnaireId: d.gestionnaire?.id || '',
    deposantIdType: d.deposantIdType || '',
    deposantIdNumber: d.deposantIdNumber || '',
    deposantIdDate: d.deposantIdDate?.split('T')[0] || '',
    conditionsRestitution: d.conditionsRestitution || '',
    assuranceInfo: d.assuranceInfo || '',
    notes: d.notes || '',
  }
  depotVehicleSearch.value = d.vehicule?.plaque || ''
  depotVehiclePreview.value = d.vehicule || null
  selectedDepotDeposant.value = d.deposant || null
  depotDeposantSearch.value = [d.deposant?.prenom, d.deposant?.nom].filter(Boolean).join(' ')
  depotDeposantResults.value = []
  showDepotForm.value = true
}

async function viewPurchase(p: any) {
  detailData.value = await voStore.fetchPurchase(p.id)
  showDetail.value = true
}

async function viewDepot(d: any) {
  detailData.value = await voStore.fetchDepot(d.id)
  showDetail.value = true
}

async function savePurchase() {
  try {
    const data: any = { ...purchaseForm.value }
    if (!data.vehiculeId || !data.sellerId) {
      throw new Error('Sélectionnez le véhicule et le vendeur depuis l\'assistant.')
    }

    data.vehiculeId = Number(data.vehiculeId)
    data.sellerId = Number(data.sellerId)
    data.purchasePrice = data.purchasePrice || '0'
    data.targetSalePrice = data.targetSalePrice || '0'
    if (data.expertId) data.expertId = Number(data.expertId)
    else delete data.expertId

    if (data.id) {
      await voStore.updatePurchase(data.id, data)
      toast.add({ title: 'Dossier rachat mis à jour', color: 'success' })
    } else {
      await voStore.createPurchase(data)
      toast.add({ title: 'Dossier rachat créé', description: 'Il pourra être confirmé en un clic.', color: 'success' })
    }
    showPurchaseForm.value = false
    await loadTab()
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e.message, color: 'error' })
  }
}

async function saveDepot() {
  try {
    const data: any = { ...depotForm.value }
    if (!data.vehiculeId || !data.deposantId) {
      throw new Error('Sélectionnez le véhicule et le déposant depuis l\'assistant.')
    }

    data.vehiculeId = Number(data.vehiculeId)
    data.deposantId = Number(data.deposantId)
    data.prixVenteSouhaite = data.prixVenteSouhaite || '0'
    data.commissionValeur = data.commissionValeur || '0'
    if (data.gestionnaireId) data.gestionnaireId = Number(data.gestionnaireId)
    else delete data.gestionnaireId

    if (data.id) {
      await voStore.updateDepot(data.id, data)
      toast.add({ title: 'Dépôt-vente mis à jour', color: 'success' })
    } else {
      await voStore.createDepot(data)
      toast.add({ title: 'Dépôt-vente créé', description: 'Le contrat est préparé automatiquement.', color: 'success' })
    }
    showDepotForm.value = false
    await loadTab()
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e.message, color: 'error' })
  }
}

async function confirmPurchase(id: number) {
  try {
    await voStore.confirmPurchase(id)
    toast.add({ title: 'Rachat confirmé', description: 'PV de rachat et livre de police archivés automatiquement.', color: 'success' })
    await loadTab()
    await voStore.fetchStats()
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e.message, color: 'error' })
  }
}

function openSell(item: any, type: 'purchase' | 'depot') {
  sellTarget.value = { id: item.id, type }
  sellForm.value = {
    buyerId: '',
    salePrice: type === 'purchase' ? item.targetSalePrice : item.prixVenteSouhaite,
    notes: '',
  }
  buyerSearch.value = ''
  buyerResults.value = []
  selectedBuyer.value = null
  showSellModal.value = true
}

async function executeSell() {
  if (!sellTarget.value) return
  try {
    if (!sellForm.value.buyerId) {
      throw new Error('Sélectionnez un acheteur.')
    }

    const data = {
      buyerId: Number(sellForm.value.buyerId),
      salePrice: sellForm.value.salePrice,
      notes: sellForm.value.notes,
    }
    if (sellTarget.value.type === 'purchase') {
      await voStore.sellPurchase(sellTarget.value.id, data)
    } else {
      await voStore.sellDepot(sellTarget.value.id, data)
    }
    toast.add({ title: 'Vente enregistrée', description: 'La facture VO a été générée et archivée.', color: 'success' })
    showSellModal.value = false
    await loadTab()
    await voStore.fetchStats()
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e.message, color: 'error' })
  }
}

function onFileChange(e: Event) {
  const input = e.target as HTMLInputElement
  uploadFile.value = input.files?.[0] || null
}

async function submitUpload() {
  if (!uploadFile.value) return
  const fd = new FormData()
  fd.append('file', uploadFile.value)
  fd.append('type', uploadForm.value.type)
  if (uploadForm.value.purchaseId) fd.append('purchaseId', uploadForm.value.purchaseId)
  if (uploadForm.value.depotId) fd.append('depotId', uploadForm.value.depotId)
  if (uploadForm.value.dateExpiration) fd.append('dateExpiration', uploadForm.value.dateExpiration)

  try {
    await voStore.uploadDocument(fd)
    toast.add({ title: 'Document ajouté', color: 'success' })
    showUpload.value = false
    await loadTab()
  } catch (e: any) {
    toast.add({ title: 'Erreur', description: e.message, color: 'error' })
  }
}

function downloadFacture(id: number) {
  window.open(`${apiBase}/vo/factures/${id}/pdf`, '_blank')
}

function downloadPvRachat(id: number) {
  window.open(`${apiBase}/vo/purchases/${id}/pv-rachat/pdf`, '_blank')
}

function downloadContrat(id: number) {
  window.open(`${apiBase}/vo/depots/${id}/contrat/pdf`, '_blank')
}

function downloadLivrePolice() {
  window.open(`${apiBase}/vo/livre-police/pdf`, '_blank')
}
</script>

<style scoped>
.btn-action {
  background: none;
  border: none;
  color: #9CA3AF;
  cursor: pointer;
  font-size: 12px;
  padding: 4px 8px;
  border-radius: 4px;
  text-decoration: none;
}
.btn-action:hover { background: #374151; color: #E8E9ED; }
.btn-confirm { color: #22c55e; font-weight: 600; }
.btn-sell { color: #FFD200; font-weight: 600; }
.btn-cancel {
  padding: 8px 16px;
  border-radius: 8px;
  background: #374151;
  color: #D1D5DB;
  border: none;
  cursor: pointer;
  font-weight: 600;
}
.detail-label { font-weight: 600; color: #9CA3AF; font-size: 11px; margin-bottom: 2px; }
.vo-flow-card {
  background: var(--dark2);
  border: 1px solid var(--glass-border);
  border-radius: var(--radius-lg);
  padding: 18px;
}
.vo-flow-title {
  font-size: 14px;
  font-weight: 700;
  color: #E8E9ED;
  margin-bottom: 10px;
}
.lookup-meta {
  font-size: 11px;
  color: #6B7280;
  margin-top: 4px;
}
.lookup-results {
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: 10px;
  max-height: 160px;
  overflow-y: auto;
  margin-top: 8px;
}
.lookup-item {
  padding: 10px 14px;
  font-size: 13px;
  color: #D1D5DB;
  cursor: pointer;
  border-bottom: 1px solid rgba(255,255,255,0.04);
  transition: background 0.15s;
}
.lookup-item:hover {
  background: rgba(255,255,255,0.03);
}
.selected-pill {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 8px;
  padding: 8px 12px;
  background: rgba(255,210,0,0.06);
  border: 1px solid rgba(255,210,0,0.2);
  border-radius: 8px;
}
.vehicle-found-card {
  margin-top: 12px;
  padding: 12px 16px;
  background: rgba(16,185,129,0.06);
  border: 1px solid rgba(16,185,129,0.2);
  border-radius: 10px;
}
.assistant-check {
  padding: 10px 12px;
  border-radius: 10px;
  font-size: 13px;
}
.assistant-check.success {
  background: rgba(34, 197, 94, 0.08);
  border: 1px solid rgba(34, 197, 94, 0.25);
  color: #dcfce7;
}
.assistant-check.warning {
  background: rgba(245, 158, 11, 0.08);
  border: 1px solid rgba(245, 158, 11, 0.25);
  color: #fde68a;
}
</style>
