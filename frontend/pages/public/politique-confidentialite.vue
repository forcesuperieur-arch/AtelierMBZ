<template>
  <div style="max-width:800px;margin:40px auto;padding:0 20px;color:#D1D5DB;">
    <h1 style="color:#E8E9ED;font-size:24px;margin-bottom:24px;">Politique de Confidentialité</h1>

    <h2 style="color:#FFD200;font-size:16px;margin-top:24px;">1. Responsable du traitement</h2>
    <p>Le responsable du traitement des données est le gérant de l'atelier moto. Contactez-nous via les coordonnées disponibles sur la page d'accueil.</p>

    <h2 style="color:#FFD200;font-size:16px;margin-top:24px;">2. Données collectées</h2>
    <p>Nous collectons uniquement les données nécessaires à la gestion de votre véhicule et de vos rendez-vous :</p>
    <ul style="margin-left:20px;margin-top:8px;">
      <li>Nom, prénom</li>
      <li>Numéro de téléphone, adresse email</li>
      <li>Adresse postale (optionnelle)</li>
      <li>Informations véhicule (plaque, marque, modèle, année)</li>
      <li>Historique des interventions et factures</li>
    </ul>

    <h2 style="color:#FFD200;font-size:16px;margin-top:24px;">3. Finalités du traitement</h2>
    <ul style="margin-left:20px;margin-top:8px;">
      <li>Gestion des rendez-vous et interventions</li>
      <li>Établissement de devis et factures</li>
      <li>Suivi de l'avancement des réparations</li>
      <li>Rappels de rendez-vous par email</li>
    </ul>

    <h2 style="color:#FFD200;font-size:16px;margin-top:24px;">4. Base légale</h2>
    <ul style="margin-left:20px;margin-top:8px;">
      <li><strong>Exécution du contrat</strong> : traitement de votre véhicule, facturation</li>
      <li><strong>Obligation légale</strong> : conservation des factures (10 ans), ordres de réparation (5 ans)</li>
      <li><strong>Consentement</strong> : prise de rendez-vous en ligne, envoi de rappels par email</li>
    </ul>

    <h2 style="color:#FFD200;font-size:16px;margin-top:24px;">5. Durée de conservation</h2>
    <table style="width:100%;border-collapse:collapse;margin-top:8px;">
      <thead>
        <tr style="border-bottom:1px solid rgba(255,255,255,0.1);">
          <th style="text-align:left;padding:8px;color:#FFD200;">Donnée</th>
          <th style="text-align:left;padding:8px;color:#FFD200;">Durée</th>
        </tr>
      </thead>
      <tbody>
        <tr style="border-bottom:1px solid rgba(255,255,255,0.06);"><td style="padding:8px;">Factures</td><td style="padding:8px;">10 ans (obligation légale)</td></tr>
        <tr style="border-bottom:1px solid rgba(255,255,255,0.06);"><td style="padding:8px;">Ordres de réparation</td><td style="padding:8px;">5 ans</td></tr>
        <tr style="border-bottom:1px solid rgba(255,255,255,0.06);"><td style="padding:8px;">Devis refusés/expirés</td><td style="padding:8px;">1 an</td></tr>
        <tr style="border-bottom:1px solid rgba(255,255,255,0.06);"><td style="padding:8px;">Données client (sans facture active)</td><td style="padding:8px;">3 ans après dernière activité</td></tr>
        <tr style="border-bottom:1px solid rgba(255,255,255,0.06);"><td style="padding:8px;">Logs d'audit (IP)</td><td style="padding:8px;">6 mois</td></tr>
        <tr><td style="padding:8px;">Rappels email</td><td style="padding:8px;">1 an</td></tr>
      </tbody>
    </table>

    <h2 style="color:#FFD200;font-size:16px;margin-top:24px;">6. Destinataires</h2>
    <p>Vos données ne sont jamais transmises à des tiers. Elles sont accessibles uniquement au personnel de l'atelier dans le cadre de leurs fonctions.</p>

    <h2 style="color:#FFD200;font-size:16px;margin-top:24px;">7. Vos droits (RGPD Art. 15-21)</h2>
    <p>Vous disposez des droits suivants :</p>
    <ul style="margin-left:20px;margin-top:8px;">
      <li><strong>Droit d'accès</strong> : obtenir une copie de vos données</li>
      <li><strong>Droit de rectification</strong> : corriger des données inexactes</li>
      <li><strong>Droit à l'effacement</strong> : demander la suppression de vos données (sauf obligations légales)</li>
      <li><strong>Droit à la portabilité</strong> : recevoir vos données dans un format structuré (JSON)</li>
      <li><strong>Droit d'opposition</strong> : vous opposer au traitement de vos données</li>
    </ul>
    <p style="margin-top:8px;">Pour exercer ces droits, contactez le responsable de l'atelier. Nous répondrons dans un délai de 30 jours.</p>

    <h2 style="color:#FFD200;font-size:16px;margin-top:24px;">8. Sécurité</h2>
    <p>Les données sont protégées par :</p>
    <ul style="margin-left:20px;margin-top:8px;">
      <li>Authentification par jeton JWT (cookies HttpOnly)</li>
      <li>Isolation des données par atelier (multi-tenant)</li>
      <li>Connexion chiffrée (HTTPS)</li>
      <li>Audit de toutes les opérations sensibles</li>
    </ul>

    <h2 style="color:#FFD200;font-size:16px;margin-top:24px;">9. Réclamation</h2>
    <p>Si vous estimez que vos droits ne sont pas respectés, vous pouvez introduire une réclamation auprès de la <a href="https://www.cnil.fr" target="_blank" rel="noopener" style="color:#FFD200;">CNIL</a>.</p>

    <div style="margin-top:40px;padding-top:20px;border-top:1px solid rgba(255,255,255,0.06);font-size:12px;color:#6B7280;">
      Dernière mise à jour : avril 2026
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'public' })
</script>
