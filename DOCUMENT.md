<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration des Tarifs | Atelier Moto Pro</title>
    <link rel="stylesheet" href="/static/theme.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f8f9fa; }
        .card { background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    </style>
</head>
<body class="min-h-screen">
    <header class="bg-gray-900 text-white p-4">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            <h1 class="text-xl font-bold">⚙️ Configuration des Tarifs</h1>
            <a href="/dashboard.html" class="text-gray-300 hover:text-white">← Retour</a>
        </div>
    </header>

    <main class="max-w-7xl mx-auto p-6">
        <!-- Navigation par catégories -->
        <div class="mb-6">
            <h2 class="text-lg font-semibold mb-3">Catégorie de moto</h2>
            <div id="categories-list" class="flex flex-wrap gap-2">
                <button onclick="selectCategorie('all')" class="categorie-btn px-4 py-2 bg-yellow-500 text-white rounded-lg font-medium" data-id="all">Toutes</button>
                <!-- Les catégories seront chargées ici -->
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Liste des tarifs existants -->
            <div class="lg:col-span-2">
                <div class="card p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold">Grille des tarifs</h3>
                        <button onclick="openModalTarif()" class="bg-yellow-500 text-white px-4 py-2 rounded-lg font-medium hover:bg-yellow-600">+ Nouveau tarif</button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b">
                                    <th class="text-left py-3">Prestation</th>
                                    <th class="text-left py-3">Type</th>
                                    <th class="text-center py-3">Temps</th>
                                    <th class="text-right py-3">Prix HT</th>
                                    <th class="text-right py-3">Prix TTC</th>
                                    <th class="text-center py-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="tarifs-table">
                                <tr><td colspan="6" class="py-4 text-center text-gray-500">Chargement...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Info et aide -->
            <div class="card p-6">
                <h3 class="text-lg font-semibold mb-4">ℹ️ Informations</h3>
                <div class="space-y-3 text-sm text-gray-600">
                    <p>Les tarifs sont définis par <strong>catégorie de moto</strong> et par <strong>type d'intervention</strong>.</p>
                    <p>Le système calcule automatiquement le temps et le prix total lors de la prise de RDV.</p>
                    <div class="bg-yellow-50 p-3 rounded-lg mt-4">
                        <p class="font-medium text-yellow-800">Exemple :</p>
                        <p class="text-yellow-700">Vidange Roadster 125cc = 30min - 42€ TTC</p>
                        <p class="text-yellow-700">Vidange Roadster 650cc = 45min - 66€ TTC</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal Tarif -->
    <div id="modal-tarif" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl p-6 max-w-md w-full mx-4 max-h-[90vh] overflow-y-auto">
            <h3 class="text-xl font-bold mb-4" id="modal-title">Nouveau tarif</h3>
            <input type="hidden" id="tarif-id">
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Catégorie de moto</label>
                    <select id="tarif-categorie" class="w-full border rounded-lg px-3 py-2">
                        <option value="">Sélectionner...</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Type d'intervention</label>
                    <select id="tarif-type" class="w-full border rounded-lg px-3 py-2">
                        <option value="">Sélectionner...</option>
                        <option value="vidange">Vidange</option>
                        <option value="freins_avant">Freins avant</option>
                        <option value="freins_arriere">Freins arrière</option>
                        <option value="revision">Révision</option>
                        <option value="pneu">Changement pneu</option>
                        <option value="batterie">Batterie</option>
                        <option value="chaine">Chaîne/courroie</option>
                        <option value="carburateur">Carburateur/injection</option>
                        <option value="electricite">Électricité</option>
                        <option value="autre">Autre</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nom de la prestation</label>
                    <input type="text" id="tarif-nom" placeholder="Ex: Vidange 125cc" class="w-full border rounded-lg px-3 py-2">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea id="tarif-description" rows="2" placeholder="Description détaillée..." class="w-full border rounded-lg px-3 py-2"></textarea>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Temps (minutes)</label>
                        <input type="number" id="tarif-temps" value="30" min="15" step="15" class="w-full border rounded-lg px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Prix MO HT (€)</label>
                        <input type="number" id="tarif-prix-ht" value="35" min="0" step="0.5" class="w-full border rounded-lg px-3 py-2">
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Prix MO TTC (€)</label>
                        <input type="number" id="tarif-prix-ttc" value="42" min="0" step="0.5" class="w-full border rounded-lg px-3 py-2 bg-gray-50" readonly>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">TVA (20%)</label>
                        <input type="text" id="tarif-tva" value="7.00€" class="w-full border rounded-lg px-3 py-2 bg-gray-50" readonly>
                    </div>
                </div>
                
                <div>
                    <label class="flex items-center space-x-2">
                        <input type="checkbox" id="tarif-pieces" class="rounded">
                        <span class="text-sm">Pièces incluses dans le prix</span>
                    </label>
                </div>
            </div>
            
            <div class="flex space-x-3 mt-6">
                <button onclick="closeModalTarif()" class="flex-1 bg-gray-200 text-gray-900 py-2 rounded-lg hover:bg-gray-300">Annuler</button>
                <button onclick="saveTarif()" class="flex-1 bg-yellow-500 text-white py-2 rounded-lg hover:bg-yellow-600">Enregistrer</button>
            </div>
        </div>
    </div>

    <script>
        const API_URL = '';
        let categories = [];
        let tarifs = [];
        let categorieSelectionnee = 'all';

        function getToken() {
            return localStorage.getItem('token') || '';
        }

        async function apiGet(url) {
            const response = await fetch(`${API_URL}${url}`, {
                headers: { 'Authorization': `Bearer ${getToken()}` }
            });
            if (response.status === 401) {
                localStorage.removeItem('token');
                window.location.href = '/dashboard.html';
                throw new Error('Session expirée');
            }
            if (!response.ok) throw new Error(`Erreur ${response.status}`);
            return response.json();
        }

        async function apiPost(url, data) {
            const response = await fetch(`${API_URL}${url}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${getToken()}`
                },
                body: JSON.stringify(data)
            });
            if (response.status === 401) {
                localStorage.removeItem('token');
                window.location.href = '/dashboard.html';
                throw new Error('Session expirée');
            }
            if (!response.ok) throw new Error(`Erreur ${response.status}`);
            return response.json();
        }

        async function apiPut(url, data) {
            const response = await fetch(`${API_URL}${url}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${getToken()}`
                },
                body: JSON.stringify(data)
            });
            if (response.status === 401) {
                localStorage.removeItem('token');
                window.location.href = '/dashboard.html';
                throw new Error('Session expirée');
            }
            if (!response.ok) throw new Error(`Erreur ${response.status}`);
            return response.json();
        }

        async function apiDelete(url) {
            const response = await fetch(`${API_URL}${url}`, {
                method: 'DELETE',
                headers: { 'Authorization': `Bearer ${getToken()}` }
            });
            if (response.status === 401) {
                localStorage.removeItem('token');
                window.location.href = '/dashboard.html';
                throw new Error('Session expirée');
            }
            if (!response.ok) throw new Error(`Erreur ${response.status}`);
            return response;
        }

        // Chargement initial
        async function init() {
            if (!getToken()) {
                window.location.href = '/dashboard.html';
                return;
            }

            // Charger les catégories
            categories = await apiGet('/api/motos/categories');
            renderCategories();
            
            // Remplir le select du modal
            const select = document.getElementById('tarif-categorie');
            select.innerHTML = '<option value="">Sélectionner...</option>' +
                categories.map(c => `<option value="${c.id}">${c.nom}</option>`).join('');

            // Charger les tarifs
            chargerTarifs();

            // Calcul auto TTC
            document.getElementById('tarif-prix-ht').addEventListener('input', calculerTTC);
        }

        function renderCategories() {
            const container = document.getElementById('categories-list');
            const buttons = categories.map(c => `
                <button onclick="selectCategorie(${c.id})" class="categorie-btn px-4 py-2 bg-white text-gray-700 border rounded-lg font-medium hover:bg-gray-50" data-id="${c.id}">${c.nom}</button>
            `).join('');
            container.innerHTML = '<button onclick="selectCategorie(\'all\')" class="categorie-btn px-4 py-2 bg-yellow-500 text-white rounded-lg font-medium" data-id="all">Toutes</button>' + buttons;
        }

        function selectCategorie(id) {
            categorieSelectionnee = id;
            document.querySelectorAll('.categorie-btn').forEach(btn => {
                if (btn.dataset.id == id) {
                    btn.classList.remove('bg-white', 'text-gray-700', 'border');
                    btn.classList.add('bg-yellow-500', 'text-white');
                } else {
                    btn.classList.add('bg-white', 'text-gray-700', 'border');
                    btn.classList.remove('bg-yellow-500', 'text-white');
                }
            });
            chargerTarifs();
        }

        async function chargerTarifs() {
            try {
                const url = categorieSelectionnee === 'all' 
                    ? '/api/tarifs' 
                    : `/api/tarifs?categorie_id=${categorieSelectionnee}`;
                tarifs = await apiGet(url);
                renderTarifs();
            } catch (error) {
                console.error('Erreur:', error);
                document.getElementById('tarifs-table').innerHTML = '<tr><td colspan="6" class="py-4 text-center text-red-500">Erreur de chargement</td></tr>';
            }
        }

        function renderTarifs() {
            const tbody = document.getElementById('tarifs-table');
            if (tarifs.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="py-4 text-center text-gray-500">Aucun tarif défini</td></tr>';
                return;
            }

            tbody.innerHTML = tarifs.map(t => `
                <tr class="border-b hover:bg-gray-50">
                    <td class="py-3">
                        <div class="font-medium">${t.nom}</div>
                        <div class="text-sm text-gray-500">${t.categorie_nom || 'Toutes catégories'}</div>
                    </td>
                    <td class="py-3 text-sm capitalize">${t.type_intervention.replace('_', ' ')}</td>
                    <td class="py-3 text-center">${t.temps_formate}</td>
                    <td class="py-3 text-right">${t.prix_mo_ht.toFixed(2)}€</td>
                    <td class="py-3 text-right font-semibold">${t.prix_mo_ttc.toFixed(2)}€</td>
                    <td class="py-3 text-center">
                        <button onclick="editTarif(${t.id})" class="text-blue-600 hover:text-blue-800 mr-2">✏️</button>
                        <button onclick="deleteTarif(${t.id})" class="text-red-600 hover:text-red-800">🗑️</button>
                    </td>
                </tr>
            `).join('');
        }

        function calculerTTC() {
            const ht = parseFloat(document.getElementById('tarif-prix-ht').value) || 0;
            const ttc = ht * 1.20;
            const tva = ttc - ht;
            document.getElementById('tarif-prix-ttc').value = ttc.toFixed(2);
            document.getElementById('tarif-tva').value = tva.toFixed(2) + '€';
        }

        function openModalTarif(id = null) {
            document.getElementById('modal-title').textContent = id ? 'Modifier tarif' : 'Nouveau tarif';
            document.getElementById('tarif-id').value = id || '';
            
            if (id) {
                const t = tarifs.find(x => x.id === id);
                document.getElementById('tarif-categorie').value = t.categorie_moto_id;
                document.getElementById('tarif-type').value = t.type_intervention;
                document.getElementById('tarif-nom').value = t.nom;
                document.getElementById('tarif-description').value = t.description || '';
                document.getElementById('tarif-temps').value = t.temps_minutes;
                document.getElementById('tarif-prix-ht').value = t.prix_mo_ht;
                document.getElementById('tarif-prix-ttc').value = t.prix_mo_ttc;
                document.getElementById('tarif-pieces').checked = t.pieces_incluses;
                calculerTTC();
            } else {
                document.getElementById('tarif-categorie').value = '';
                document.getElementById('tarif-type').value = '';
                document.getElementById('tarif-nom').value = '';
                document.getElementById('tarif-description').value = '';
                document.getElementById('tarif-temps').value = '30';
                document.getElementById('tarif-prix-ht').value = '35';
                document.getElementById('tarif-prix-ttc').value = '42';
                document.getElementById('tarif-pieces').checked = false;
                calculerTTC();
            }
            
            document.getElementById('modal-tarif').classList.remove('hidden');
        }

        function closeModalTarif() {
            document.getElementById('modal-tarif').classList.add('hidden');
        }

        async function saveTarif() {
            const id = document.getElementById('tarif-id').value;
            const data = {
                categorie_moto_id: parseInt(document.getElementById('tarif-categorie').value),
                type_intervention: document.getElementById('tarif-type').value,
                nom: document.getElementById('tarif-nom').value,
                description: document.getElementById('tarif-description').value,
                temps_minutes: parseInt(document.getElementById('tarif-temps').value),
                prix_mo_ht: parseFloat(document.getElementById('tarif-prix-ht').value),
                prix_mo_ttc: parseFloat(document.getElementById('tarif-prix-ttc').value),
                pieces_incluses: document.getElementById('tarif-pieces').checked ? true : false
            };

            if (!data.categorie_moto_id || !data.type_intervention || !data.nom) {
                alert('Veuillez remplir tous les champs obligatoires');
                return;
            }

            try {
                if (id) {
                    await apiPut(`/api/tarifs/${id}`, data);
                } else {
                    await apiPost('/api/tarifs', data);
                }
                closeModalTarif();
                chargerTarifs();
            } catch (error) {
                alert('Erreur: ' + error.message);
            }
        }

        async function editTarif(id) {
            openModalTarif(id);
        }

        async function deleteTarif(id) {
            if (!confirm('Supprimer ce tarif ?')) return;
            try {
                await apiDelete(`/api/tarifs/${id}`);
                chargerTarifs();
            } catch (error) {
                alert('Erreur: ' + error.message);
            }
        }

        document.addEventListener('DOMContentLoaded', init);
    </script>
</body>
</html>
