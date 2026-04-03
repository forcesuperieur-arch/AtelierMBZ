<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace Technicien - Atelier Moto Pro</title>
    <link rel="stylesheet" href="/static/theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
        .header-dark { background: #1a1a1a; }
        .card { background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .btn-primary { background: #ffd700; color: #1a1a1a; font-weight: 600; }
        .btn-primary:hover { background: #e6c200; }
        .check-item { transition: all 0.2s; }
        .check-item:hover { background: #f8f9fa; }
        .check-item input:checked + label { text-decoration: line-through; color: #28a745; }
        .alert-badge { animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.7; } }
        .rdv-card { transition: transform 0.2s; cursor: pointer; }
        .rdv-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .rdv-card.active { border: 2px solid #ffd700; }
        .status-en_cours { border-left: 4px solid #007bff; }
        .status-termine { border-left: 4px solid #28a745; }
        .status-attente { border-left: 4px solid #ffc107; }

        /* Styles Mobile */
        @media (max-width: 768px) {
            .header-dark .container { padding: 0.5rem 1rem; }
            .header-dark h1 { font-size: 1rem; }
            .header-dark p { display: none; }
            .header-dark .flex.items-center.space-x-4 { flex-direction: column; gap: 0.5rem; }
            .header-dark a { padding: 0.5rem 1rem; font-size: 0.875rem; }

            .grid-cols-3 { grid-template-columns: 1fr; }
            .lg\\:col-span-1 { grid-column: span 1; }
            .lg\\:col-span-2 { grid-column: span 1; }

            .rdv-card { padding: 0.75rem; }
            .rdv-card .flex { flex-direction: column; gap: 0.5rem; }
            .rdv-card .text-right { text-align: left; }

            #rdv-detail { padding: 1rem; }
            #rdv-detail .grid-cols-2 { grid-template-columns: 1fr; }

            .check-item { padding: 0.75rem; }
            .check-item .flex { flex-direction: column; gap: 0.5rem; }
            .check-item .flex-1 { width: 100%; }

            .piece-item { flex-direction: column; gap: 0.5rem; }
            .piece-item .flex-1 { width: 100%; }

            .flex.gap-3 { flex-direction: column; }
            .flex.gap-3 button { width: 100%; }

            textarea, input[type="text"], input[type="number"] { font-size: 16px; }
        }

        /* Très petits écrans */
        @media (max-width: 480px) {
            .container { padding-left: 0.5rem; padding-right: 0.5rem; }
            .card { border-radius: 8px; }
            .btn-primary, .bg-green-600, .bg-orange-500, .bg-blue-600 { padding: 0.75rem 1rem; }
        }
    </style>
</head>
<body class="min-h-screen">
    <!-- Header -->
    <header class="header-dark text-white py-4">
        <div class="container mx-auto px-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background: #ffd700;">
                        <i class="fas fa-wrench text-xl" style="color: #1a1a1a;"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold">Espace Technicien</h1>
                        <p style="color: #9ca3af;" class="text-xs">Atelier Moto Pro</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <span id="current-date" class="text-sm"></span>
                    <a href="/planning.html" class="px-4 py-2 rounded-lg font-semibold" style="background: #ffd700; color: #1a1a1a;">
                        <i class="fas fa-calendar-alt mr-2"></i>Voir le planning
                    </a>
                    <button onclick="logout()" class="px-4 py-2 rounded-lg font-semibold" style="background: #dc2626; color: white;">
                        <i class="fas fa-sign-out-alt mr-2"></i>Déconnexion
                    </button>
                </div>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Colonne gauche : Liste des RDV -->
            <div class="lg:col-span-1">
                <div class="card p-4 mb-4">
                    <h2 class="text-lg font-bold mb-4">
                        <i class="fas fa-calendar-day mr-2" style="color: #ffd700;"></i>Mes RDV du jour
                    </h2>
                    <div id="rdv-list" class="space-y-3">
                        <div class="text-center py-8 text-gray-500">
                            <i class="fas fa-spinner fa-spin text-2xl mb-2"></i>
                            <p>Chargement...</p>
                        </div>
                    </div>
                </div>

                <!-- Stats rapides -->
                <div class="card p-4">
                    <h3 class="font-semibold mb-3">Aperçu</h3>
                    <div class="grid grid-cols-2 sm:grid-cols-2 gap-3">
                        <div class="text-center p-3 bg-blue-50 rounded-lg">
                            <div class="text-2xl font-bold text-blue-600" id="stat-en-cours">0</div>
                            <div class="text-xs text-gray-600">En cours</div>
                        </div>
                        <div class="text-center p-3 bg-green-50 rounded-lg">
                            <div class="text-2xl font-bold text-green-600" id="stat-termines">0</div>
                            <div class="text-xs text-gray-600">Terminés</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Colonne centrale et droite : Détails du RDV sélectionné -->
            <div class="lg:col-span-2">
                <div id="rdv-detail" class="card p-6">
                    <div class="text-center py-12 text-gray-500">
                        <i class="fas fa-hand-pointer text-4xl mb-4" style="color: #ffd700;"></i>
                        <p class="text-lg">Sélectionnez un RDV pour commencer</p>
                        <p class="text-sm mt-2">Cliquez sur un rendez-vous dans la liste de gauche</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Template pour les détails du RDV (caché initialement) -->
    <template id="rdv-detail-template">
        <div class="fade-in">
            <!-- En-tête -->
            <div class="flex justify-between items-start mb-6">
                <div>
                    <div class="flex items-center gap-3 mb-2">
                        <h2 class="text-2xl font-bold" id="detail-client"></h2>
                        <span id="detail-statut" class="px-3 py-1 rounded-full text-sm font-semibold"></span>
                    </div>
                    <p class="text-gray-600" id="detail-vehicule"></p>
                    <p class="text-sm text-gray-500" id="detail-plaque"></p>
                </div>
                <div class="text-right">
                    <div class="text-2xl font-bold" style="color: #ffd700;" id="detail-heure"></div>
                    <p class="text-sm text-gray-500" id="detail-duree"></p>
                </div>
            </div>

            <!-- OR Info -->
            <div class="bg-gray-50 rounded-lg p-4 mb-6">
                <h3 class="font-semibold mb-3"><i class="fas fa-clipboard-check mr-2 text-green-600"></i>Informations OR</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div><strong>Kilométrage:</strong> <span id="detail-km">-</span> km</div>
                    <div><strong>État à l'arrivée:</strong> <span id="detail-etat">-</span></div>
                </div>
            </div>

            <!-- Points de contrôle -->
            <div class="mb-6">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-2">
                    <h3 class="font-semibold text-lg"><i class="fas fa-tasks mr-2" style="color: #ffd700;"></i>Points de contrôle</h3>
                    <button onclick="toggleAllChecks()" class="text-sm text-blue-600 hover:text-blue-800">
                        Tout cocher
                    </button>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" id="checklist-container">
                    <!-- Généré dynamiquement -->
                </div>
            </div>

            <!-- Alertes -->
            <div class="mb-6">
                <h3 class="font-semibold text-lg mb-3"><i class="fas fa-exclamation-triangle mr-2 text-orange-500"></i>Alertes & Observations</h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Problèmes détectés</label>
                        <textarea id="tech-alertes" rows="2" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-yellow-400 focus:outline-none" placeholder="Décrivez les problèmes rencontrés..."></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Recommandations</label>
                        <textarea id="tech-recommandations" rows="2" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-yellow-400 focus:outline-none" placeholder="Recommandations pour le client..."></textarea>
                    </div>
                </div>
            </div>

            <!-- Travaux réalisés -->
            <div class="mb-6">
                <h3 class="font-semibold text-lg mb-3"><i class="fas fa-wrench mr-2" style="color: #ffd700;"></i>Travaux réalisés</h3>
                <textarea id="tech-travaux" rows="4" class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-yellow-400 focus:outline-none" placeholder="Détaillez les travaux effectués, les pièces changées, les réglages effectués..."></textarea>
            </div>

            <!-- Pièces utilisées -->
            <div class="mb-6">
                <h3 class="font-semibold text-lg mb-3"><i class="fas fa-cogs mr-2" style="color: #ffd700;"></i>Pièces utilisées</h3>
                <div id="pieces-list" class="space-y-2 mb-3">
                    <!-- Généré dynamiquement -->
                </div>
                <div class="flex flex-col sm:flex-row gap-2">
                    <input type="text" id="piece-nom" placeholder="Nom de la pièce" class="flex-1 px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-yellow-400 focus:outline-none">
                    <div class="flex gap-2">
                        <input type="number" id="piece-qty" placeholder="Qté" min="1" value="1" class="w-20 px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-yellow-400 focus:outline-none">
                        <button onclick="addPiece()" class="px-4 py-2 rounded-lg font-semibold whitespace-nowrap" style="background: #ffd700; color: #1a1a1a;">
                            <i class="fas fa-plus sm:mr-2"></i><span class="hidden sm:inline">Ajouter</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <!-- Suivi temps de travail -->
            <div id="temps-travail-section" class="bg-gray-50 rounded-lg p-4 mb-4 hidden">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                    <div>
                        <span class="text-sm text-gray-600">Temps de travail</span>
                        <div id="temps-travail-display" class="text-2xl font-bold text-gray-800">--:--</div>
                    </div>
                    <div class="flex gap-2 w-full sm:w-auto">
                        <button id="btn-demarrer" onclick="demarrerTravail()" class="flex-1 sm:flex-none px-4 py-2 rounded-lg font-semibold bg-green-600 text-white hover:bg-green-700">
                            <i class="fas fa-play mr-2"></i><span class="hidden sm:inline">Démarrer</span>
                        </button>
                        <button id="btn-terminer" onclick="terminerTravail()" class="flex-1 sm:flex-none px-4 py-2 rounded-lg font-semibold bg-red-600 text-white hover:bg-red-700 hidden">
                            <i class="fas fa-stop mr-2"></i><span class="hidden sm:inline">Terminer</span>
                        </button>
                    </div>
                </div>
                <div id="temps-travail-info" class="text-sm text-gray-500 mt-2 hidden"></div>
            </div>

            <div class="flex flex-col sm:flex-row gap-3 pt-4 border-t">
                <button onclick="saveTechReport()" class="flex-1 py-3 rounded-lg font-semibold" style="background: #ffd700; color: #1a1a1a;">
                    <i class="fas fa-save mr-2"></i>Sauvegarder
                </button>
                <button onclick="markAsDone(false)" class="flex-1 py-3 rounded-lg font-semibold bg-green-600 text-white hover:bg-green-700">
                    <i class="fas fa-check-circle mr-2"></i>Terminer
                </button>
                <button onclick="markAsDone(true)" class="flex-1 py-3 rounded-lg font-semibold bg-orange-500 text-white hover:bg-orange-600">
                    <i class="fas fa-clock mr-2"></i>Attente pièce
                </button>
                <button onclick="voirOR()" class="px-4 py-3 rounded-lg font-semibold bg-blue-600 text-white hover:bg-blue-700" title="Voir l'ordre de réparation">
                    <i class="fas fa-file-pdf mr-2"></i>OR
                </button>
            </div>
        </div>
    </template>

    <script src="/static/technicien.js?v=2"></script>
</body>
</html>