<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factures - Atelier Moto Pro</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Inter',sans-serif; background:#0a0a0a; color:#e5e7eb; min-height:100vh; }
        .container { max-width:1400px; margin:0 auto; padding:24px; }

        /* Header */
        .page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; }
        .page-header h1 { font-size:24px; font-weight:700; color:#fff; }
        .page-header .back-btn { color:#9ca3af; text-decoration:none; font-size:14px; }
        .page-header .back-btn:hover { color:#fff; }

        /* Stat cards */
        .stats-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:24px; }
        .stat-card { background:#111; border:1px solid #222; border-radius:10px; padding:16px; }
        .stat-card .label { color:#9ca3af; font-size:11px; text-transform:uppercase; letter-spacing:0.5px; }
        .stat-card .value { font-size:22px; font-weight:700; margin-top:4px; }
        .stat-card.orange .value { color:#FFD200; }
        .stat-card.green .value { color:#22C55E; }
        .stat-card.red .value { color:#EF4444; }
        .stat-card.purple .value { color:#8B5CF6; }

        /* Filters */
        .filters { background:#111; border:1px solid #222; border-radius:10px; padding:16px; margin-bottom:24px; display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap; }
        .filter-group { display:flex; flex-direction:column; gap:4px; }
        .filter-group label { color:#9ca3af; font-size:11px; }
        .filter-group input, .filter-group select {
            background:#1a1a1a; border:1px solid #333; color:#e5e7eb; border-radius:6px; padding:8px 12px; font-size:13px; font-family:inherit;
        }
        .filter-group input:focus, .filter-group select:focus { outline:none; border-color:#FFD200; }
        .btn { padding:8px 16px; border-radius:6px; font-size:13px; font-weight:600; cursor:pointer; border:none; font-family:inherit; }
        .btn-primary { background:#FFD200; color:#111; }
        .btn-primary:hover { background:#FFC400; }
        .btn-ghost { background:transparent; color:#9ca3af; border:1px solid #333; }
        .btn-ghost:hover { color:#fff; border-color:#555; }

        /* Table */
        .table-wrap { background:#111; border:1px solid #222; border-radius:10px; overflow:hidden; }
        table { width:100%; border-collapse:collapse; }
        th { background:#1a1a1a; color:#9ca3af; font-size:11px; text-transform:uppercase; letter-spacing:0.5px; padding:12px 16px; text-align:left; font-weight:600; }
        td { padding:12px 16px; border-top:1px solid #1e1e1e; font-size:13px; }
        tr:hover { background:#1a1a1a; }

        /* Badges */
        .badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600; }
        .badge.emise { background:rgba(139,92,246,0.15); color:#8B5CF6; }
        .badge.payee { background:rgba(34,197,94,0.15); color:#22C55E; }
        .badge.partiellement_payee { background:rgba(255,210,0,0.15); color:#FFD200; }
        .badge.annulee { background:rgba(239,68,68,0.15); color:#EF4444; }

        .empty { text-align:center; color:#666; padding:40px; }

        @media (max-width:768px) {
            .stats-grid { grid-template-columns:repeat(2,1fr); }
            .filters { flex-direction:column; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-file-invoice-dollar" style="color:#FFD200;margin-right:8px"></i>Factures</h1>
            <a href="/" class="back-btn"><i class="fas fa-arrow-left"></i> Retour au planning</a>
        </div>

        <!-- Stats -->
        <div class="stats-grid" id="stats-grid">
            <div class="stat-card orange"><div class="label">CA Facture (mois)</div><div class="value" id="stat-ca-facture">--</div></div>
            <div class="stat-card green"><div class="label">CA Encaisse (mois)</div><div class="value" id="stat-ca-encaisse">--</div></div>
            <div class="stat-card red"><div class="label">Impayes</div><div class="value" id="stat-impayes">--</div></div>
            <div class="stat-card purple"><div class="label">Nb Factures (mois)</div><div class="value" id="stat-nb">--</div></div>
        </div>

        <!-- Filtres -->
        <div class="filters">
            <div class="filter-group">
                <label>Date debut</label>
                <input type="date" id="f-date-debut">
            </div>
            <div class="filter-group">
                <label>Date fin</label>
                <input type="date" id="f-date-fin">
            </div>
            <div class="filter-group">
                <label>Statut</label>
                <select id="f-statut">
                    <option value="">Tous</option>
                    <option value="emise">Emise</option>
                    <option value="payee">Payee</option>
                    <option value="partiellement_payee">Partiellement payee</option>
                    <option value="annulee">Annulee</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Client</label>
                <input type="text" id="f-search" placeholder="Nom du client...">
            </div>
            <button class="btn btn-primary" onclick="filtrerFactures()">Filtrer</button>
            <button class="btn btn-ghost" onclick="resetFiltres()">Reset</button>
        </div>

        <!-- Table -->
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>N° Facture</th>
                        <th>Date</th>
                        <th>Client</th>
                        <th>Vehicule</th>
                        <th>Total TTC</th>
                        <th>Paye</th>
                        <th>Reste</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="factures-tbody">
                    <tr><td colspan="9" class="empty">Chargement...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <script src="/static/utils.js"></script>
    <script src="/static/api.js"></script>
    <script>
        var API_URL = window.API_URL || '';

        // Init
        window.addEventListener('load', function() {
            apiGet('/api/auth/me').then(function(r) { return r.json(); }).then(function() {
                loadFactureStats();
                loadFactures();
            }).catch(function() {
                window.location.href = '/';
            });
        });

        function loadFactureStats() {
            apiGet('/api/factures/stats').then(function(r) { return r.json(); }).then(function(stats) {
                document.getElementById('stat-ca-facture').textContent = stats.ca_facture_mois.toFixed(2) + ' EUR';
                document.getElementById('stat-ca-encaisse').textContent = stats.ca_encaisse_mois.toFixed(2) + ' EUR';
                document.getElementById('stat-impayes').textContent = stats.impayes.toFixed(2) + ' EUR';
                document.getElementById('stat-nb').textContent = stats.nb_factures_mois;
            }).catch(function() {});
        }

        function loadFactures() {
            var params = [];
            var dd = document.getElementById('f-date-debut').value;
            var df = document.getElementById('f-date-fin').value;
            var st = document.getElementById('f-statut').value;
            var sr = document.getElementById('f-search').value;
            if (dd) params.push('date_debut=' + dd);
            if (df) params.push('date_fin=' + df);
            if (st) params.push('statut=' + st);
            if (sr) params.push('search=' + encodeURIComponent(sr));
            var url = '/api/factures' + (params.length ? '?' + params.join('&') : '');

            apiGet(url).then(function(r) { return r.json(); }).then(function(data) {
                var factures = data.factures || [];
                var tbody = document.getElementById('factures-tbody');

                if (!factures.length) {
                    tbody.innerHTML = '<tr><td colspan="9" class="empty">Aucune facture trouvee</td></tr>';
                    return;
                }

                var html = '';
                factures.forEach(function(f) {
                    var dateStr = f.date_creation ? new Date(f.date_creation).toLocaleDateString('fr-FR') : '-';
                    var badgeCls = f.statut;
                    var badgeLabel = { emise: 'Emise', payee: 'Payee', partiellement_payee: 'Partiel', annulee: 'Annulee' }[f.statut] || f.statut;

                    html += '<tr>';
                    html += '<td><b style="color:#FFD200">' + escapeHtml(f.numero_facture) + '</b></td>';
                    html += '<td>' + dateStr + '</td>';
                    html += '<td>' + (escapeHtml(f.client_prenom) || '') + ' ' + (escapeHtml(f.client_nom) || '') + '</td>';
                    html += '<td style="color:#9ca3af">' + (escapeHtml(f.vehicule_desc) || '-') + '</td>';
                    html += '<td><b>' + f.total_ttc.toFixed(2) + ' EUR</b></td>';
                    html += '<td style="color:#22C55E">' + f.montant_paye.toFixed(2) + ' EUR</td>';
                    html += '<td style="color:' + (f.montant_restant > 0 ? '#EF4444' : '#666') + '">' + f.montant_restant.toFixed(2) + ' EUR</td>';
                    html += '<td><span class="badge ' + badgeCls + '">' + badgeLabel + '</span></td>';
                    html += '<td style="display:flex;gap:4px">';
                    html += '<button class="btn btn-ghost" style="font-size:11px;padding:4px 8px" onclick="ouvrirPDF(' + f.id + ')">PDF</button>';
                    if (f.statut === 'emise' || f.statut === 'partiellement_payee') {
                        html += '<button class="btn btn-primary" style="font-size:11px;padding:4px 8px;background:var(--green,#22C55E)" onclick="ouvrirEncaissementFacture(' + f.id + ')">Encaisser</button>';
                    }
                    html += '</td>';
                    html += '</tr>';
                });
                tbody.innerHTML = html;
            }).catch(function(e) {
                document.getElementById('factures-tbody').innerHTML = '<tr><td colspan="9" class="empty">Erreur: ' + e.message + '</td></tr>';
            });
        }

        function filtrerFactures() { loadFactures(); }
        function resetFiltres() {
            document.getElementById('f-date-debut').value = '';
            document.getElementById('f-date-fin').value = '';
            document.getElementById('f-statut').value = '';
            document.getElementById('f-search').value = '';
            loadFactures();
        }

        function ouvrirPDF(factureId) {
            window.open(API_URL + '/api/factures/' + factureId + '/pdf', '_blank');
        }

        function ouvrirEncaissementFacture(factureId) {
            apiGet('/api/factures/' + factureId).then(function(r) { return r.json(); }).then(function(f) {
                var overlay = document.createElement('div');
                overlay.id = 'enc-overlay';
                overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);display:flex;align-items:center;justify-content:center;z-index:9999';

                var modal = document.createElement('div');
                modal.style.cssText = 'background:#1a1a1a;border:1px solid #333;border-radius:12px;padding:24px;width:450px;max-width:90vw';

                var html = '<h3 style="color:#fff;margin-bottom:16px">Encaisser - ' + escapeHtml(f.numero_facture) + '</h3>';
                html += '<div style="display:flex;justify-content:space-between;margin-bottom:12px"><span style="color:#9ca3af">Total TTC</span><span style="color:#FFD200;font-weight:bold;font-size:18px">' + f.total_ttc.toFixed(2) + ' EUR</span></div>';
                if (f.montant_paye > 0) {
                    html += '<div style="display:flex;justify-content:space-between;color:#22C55E;font-size:13px"><span>Deja paye</span><span>' + f.montant_paye.toFixed(2) + ' EUR</span></div>';
                }
                html += '<div style="display:flex;justify-content:space-between;color:#EF4444;font-weight:bold;margin-bottom:16px"><span>Reste a payer</span><span>' + f.montant_restant.toFixed(2) + ' EUR</span></div>';

                html += '<div style="margin-bottom:12px"><label style="color:#9ca3af;font-size:11px;display:block;margin-bottom:4px">Mode de paiement</label>';
                html += '<select id="enc2-mode" style="width:100%;background:#111;border:1px solid #333;color:#e5e7eb;border-radius:6px;padding:8px 12px;font-size:13px;font-family:inherit" onchange="document.getElementById(\'enc2-ref-group\').style.display=(this.value===\'cheque\'||this.value===\'virement\')?\'block\':\'none\'">';
                html += '<option value="cb">Carte bancaire</option><option value="especes">Especes</option><option value="cheque">Cheque</option><option value="virement">Virement</option><option value="differe">Differe</option></select></div>';

                html += '<div style="margin-bottom:12px"><label style="color:#9ca3af;font-size:11px;display:block;margin-bottom:4px">Montant</label>';
                html += '<input type="number" id="enc2-montant" value="' + f.montant_restant.toFixed(2) + '" style="width:100%;background:#111;border:1px solid #333;color:#e5e7eb;border-radius:6px;padding:8px 12px;font-size:13px;font-family:inherit"></div>';

                html += '<div id="enc2-ref-group" style="display:none;margin-bottom:12px"><label style="color:#9ca3af;font-size:11px;display:block;margin-bottom:4px">Reference</label>';
                html += '<input type="text" id="enc2-reference" placeholder="N° cheque / ref virement" style="width:100%;background:#111;border:1px solid #333;color:#e5e7eb;border-radius:6px;padding:8px 12px;font-size:13px;font-family:inherit"></div>';

                html += '<div style="display:flex;gap:8px;margin-top:16px">';
                html += '<button class="btn btn-ghost" style="flex:1" onclick="document.getElementById(\'enc-overlay\').remove()">Annuler</button>';
                html += '<button class="btn btn-primary" style="flex:1;background:#22C55E" onclick="doEncaisser(' + f.id + ')">Payer</button></div>';

                modal.innerHTML = html;
                overlay.appendChild(modal);
                overlay.addEventListener('click', function(e) { if (e.target === overlay) overlay.remove(); });
                document.body.appendChild(overlay);
            }).catch(function(e) { alert('Erreur: ' + e.message); });
        }

        function doEncaisser(factureId) {
            var montant = parseFloat(document.getElementById('enc2-montant').value);
            var mode = document.getElementById('enc2-mode').value;
            var ref = document.getElementById('enc2-reference') ? document.getElementById('enc2-reference').value : '';
            if (!montant || montant <= 0) { alert('Montant invalide'); return; }

            apiPost('/api/factures/' + factureId + '/encaisser', {
                montant: montant,
                mode_paiement: mode,
                reference: ref || null
            }).then(function(r) { return r.json(); }).then(function(data) {
                var overlay = document.getElementById('enc-overlay');
                if (overlay) overlay.remove();
                alert(data.statut === 'payee' ? 'Facture soldee !' : 'Paiement enregistre - Reste: ' + data.montant_restant.toFixed(2) + ' EUR');
                loadFactures();
                loadFactureStats();
            }).catch(function(e) { alert('Erreur: ' + e.message); });
        }
    </script>
</body>
</html>
