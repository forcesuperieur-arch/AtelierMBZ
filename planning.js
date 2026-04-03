<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion Mécaniciens & Ponts | Atelier Moto Pro</title>
    <link rel="stylesheet" href="/static/theme.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f8f9fa; }
        .card { background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .pont-libre { border-left: 4px solid #22c55e; }
        .pont-occupe { border-left: 4px solid #ef4444; }
        .pont-maintenance { border-left: 4px solid #f59e0b; }
    </style>
</head>
<body class="min-h-screen">
    <header class="bg-gray-900 text-white p-4">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            <h1 class="text-xl font-bold">🔧 Gestion Mécaniciens & Ponts</h1>
            <a href="/dashboard.html" class="text-gray-300 hover:text-white">← Retour</a>
        </div>
    </header>

    <main class="max-w-7xl mx-auto p-6">
        <!-- Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <div class="card p-4">
                <p class="text-sm text-gray-500">Mécaniciens actifs</p>
                <p class="text-2xl font-bold" id="count-meca">-</p>
            </div>
            <div class="card p-4">
                <p class="text-sm text-gray-500">Ponts disponibles</p>
                <p class="text-2xl font-bold" id="count-ponts-libres">-</p>
            </div>
            <div class="card p-4">
                <p class="text-sm text-gray-500">Ponts occupés</p>
                <p class="text-2xl font-bold" id="count-ponts-occ">-</p>
            </div>
            <div class="card p-4">
                <p class="text-sm text-gray-500">Absences</p>
                <p class="text-2xl font-bold" id="count-abs">-</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Mécaniciens -->
            <div class="card p-6">
                <div class="flex justify-between mb-4">
                    <h2 class="text-lg font-semibold">👨‍🔧 Mécaniciens</h2>
                    <button onclick="openMecaModal()" class="bg-yellow-500 text-white px-4 py-2 rounded-lg">+ Ajouter</button>
                </div>
                <div id="liste-meca" class="space-y-2"></div>
            </div>

            <!-- Ponts -->
            <div class="card p-6">
                <div class="flex justify-between mb-4">
                    <h2 class="text-lg font-semibold">🏗️ Ponts</h2>
                    <button onclick="openPontModal()" class="bg-yellow-500 text-white px-4 py-2 rounded-lg">+ Ajouter</button>
                </div>
                <div id="liste-ponts" class="grid grid-cols-2 gap-4"></div>
            </div>
        </div>

        <!-- Absences -->
        <div class="card p-6 mt-8">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold">📅 Absences</h2>
                <div class="flex gap-2">
                    <input type="date" id="absence-date" class="border rounded-lg px-3 py-2">
                    <button onclick="openAbsenceModal()" class="bg-yellow-500 text-white px-4 py-2 rounded-lg">+ Déclarer</button>
                </div>
            </div>
            <div id="liste-absences" class="space-y-2"></div>
        </div>
    </main>

    <!-- Modal Mécanicien -->
    <div id="modal-meca" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl p-6 max-w-md w-full mx-4">
            <h3 class="text-xl font-bold mb-4">Mécanicien</h3>
            <input type="hidden" id="meca-id">
            <div class="space-y-3">
                <input type="text" id="meca-nom" placeholder="Nom" class="w-full border rounded-lg px-3 py-2">
                <input type="text" id="meca-prenom" placeholder="Prénom" class="w-full border rounded-lg px-3 py-2">
                <input type="text" id="meca-spec" placeholder="Spécialités" class="w-full border rounded-lg px-3 py-2">
                <input type="color" id="meca-couleur" value="#3b82f6" class="w-full h-10 rounded-lg">
                <select id="meca-actif" class="w-full border rounded-lg px-3 py-2">
                    <option value="1">Actif</option>
                    <option value="0">Inactif</option>
                </select>
            </div>
            <div class="flex space-x-3 mt-6">
                <button onclick="closeMecaModal()" class="flex-1 bg-gray-200 py-2 rounded-lg">Annuler</button>
                <button onclick="saveMeca()" class="flex-1 bg-yellow-500 text-white py-2 rounded-lg">Enregistrer</button>
            </div>
        </div>
    </div>

    <!-- Modal Pont -->
    <div id="modal-pont" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl p-6 max-w-md w-full mx-4">
            <h3 class="text-xl font-bold mb-4">Pont</h3>
            <input type="hidden" id="pont-id">
            <div class="space-y-3">
                <input type="text" id="pont-nom" placeholder="Nom" class="w-full border rounded-lg px-3 py-2">
                <select id="pont-type" class="w-full border rounded-lg px-3 py-2">
                    <option value="moto">Moto</option>
                    <option value="scooter">Scooter</option>
                </select>
                <input type="number" id="pont-capacite" value="500" placeholder="Capacité kg" class="w-full border rounded-lg px-3 py-2">
                <select id="pont-meca" class="w-full border rounded-lg px-3 py-2">
                    <option value="">Non assigné</option>
                </select>
                <select id="pont-actif" class="w-full border rounded-lg px-3 py-2">
                    <option value="1">Actif</option>
                    <option value="0">Maintenance</option>
                </select>
            </div>
            <div class="flex space-x-3 mt-6">
                <button onclick="closePontModal()" class="flex-1 bg-gray-200 py-2 rounded-lg">Annuler</button>
                <button onclick="savePont()" class="flex-1 bg-yellow-500 text-white py-2 rounded-lg">Enregistrer</button>
            </div>
        </div>
    </div>

    <!-- Modal Absence -->
    <div id="modal-absence" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl p-6 max-w-md w-full mx-4">
            <h3 class="text-xl font-bold mb-4">Déclarer une absence</h3>
            <div class="space-y-3">
                <select id="absence-meca" class="w-full border rounded-lg px-3 py-2">
                    <option value="">Sélectionner un mécanicien</option>
                </select>
                <input type="date" id="absence-debut" class="w-full border rounded-lg px-3 py-2">
                <input type="date" id="absence-fin" class="w-full border rounded-lg px-3 py-2">
                <select id="absence-motif" class="w-full border rounded-lg px-3 py-2">
                    <option value="conge">Congé</option>
                    <option value="maladie">Maladie</option>
                    <option value="formation">Formation</option>
                    <option value="autre">Autre</option>
                </select>
                <textarea id="absence-notes" placeholder="Notes" class="w-full border rounded-lg px-3 py-2" rows="2"></textarea>
            </div>
            <div class="flex space-x-3 mt-6">
                <button onclick="closeAbsenceModal()" class="flex-1 bg-gray-200 py-2 rounded-lg">Annuler</button>
                <button onclick="saveAbsence()" class="flex-1 bg-yellow-500 text-white py-2 rounded-lg">Enregistrer</button>
            </div>
        </div>
    </div>

    <script src="/static/mecaniciens-v2.js"></script>
</body>
</html>
