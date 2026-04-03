<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atelier Moto Pro - Tableau de Bord Statistiques</title>
    <link rel="stylesheet" href="/static/theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js pour les graphiques -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .gradient-bg { background: #1a1a1a; }
        .card-shadow { box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
        .stat-card { transition: transform 0.2s, box-shadow 0.2s; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 15px 50px rgba(0,0,0,0.15); }
        .fade-in { animation: fadeIn 0.5s ease-in; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .progress-bar { transition: width 1s ease-in-out; }
        .chart-container { position: relative; height: 300px; }
        .chart-container-sm { position: relative; height: 200px; }
        .btn-primary { background: #ffd700; color: #1a1a1a; }
        .btn-primary:hover { background: #e6c200; }
        .border-green-500 { border-color: #28a745; }
        .border-blue-500 { border-color: #007bff; }
        .border-purple-500 { border-color: #6f42c1; }
        .border-orange-500 { border-color: #fd7e14; }
        .bg-green-100 { background: #d4edda; }
        .bg-blue-100 { background: #cce5ff; }
        .bg-purple-100 { background: #e2d4f0; }
        .bg-orange-100 { background: #fff3cd; }
        .text-green-600 { color: #155724; }
        .text-blue-600 { color: #004085; }
        .text-purple-600 { color: #4a148c; }
        .text-orange-600 { color: #856404; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Header -->
    <header class="gradient-bg text-white py-4">
        <div class="container mx-auto px-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background: #ffd700;">
                        <i class="fas fa-motorcycle text-xl" style="color: #1a1a1a;"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold">Atelier Moto Pro</h1>
                        <p style="color: #9ca3af;" class="text-xs">Tableau de Bord Statistiques</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <span id="current-date" class="text-sm"></span>
                    <a href="/dashboard.html" class="px-4 py-2 rounded-lg font-semibold hover:bg-gray-100" style="background: #ffd700; color: #1a1a1a;">
                        <i class="fas fa-arrow-left mr-2"></i>Retour
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-8">
        <!-- Titre et refresh -->
        <div class="flex justify-between items-center mb-8">
            <h2 class="text-3xl font-bold text-gray-800">
                <i class="fas fa-chart-line mr-3" style="color: #ffd700;"></i>Tableau de Bord
            </h2>
            <button onclick="loadDashboardStats()" class="px-4 py-2 rounded-lg flex items-center font-semibold" style="background: #ffd700; color: #1a1a1a;">
                <i class="fas fa-sync-alt mr-2" id="refresh-icon"></i>Actualiser
            </button>
        </div>

        <!-- Cartes CA -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- CA Jour -->
            <div class="stat-card bg-white rounded-xl card-shadow p-6 border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm font-medium">CA Aujourd'hui</p>
                        <p class="text-3xl font-bold text-gray-800" id="ca-jour">0 €</p>
                    </div>
                    <div class="bg-green-100 p-3 rounded-full">
                        <i class="fas fa-euro-sign text-2xl text-green-600"></i>
                    </div>
                </div>
                <div class="mt-4 text-sm text-gray-500">
                    <span id="rdv-jour">0</span> RDV aujourd'hui
                </div>
            </div>

            <!-- CA Semaine -->
            <div class="stat-card bg-white rounded-xl card-shadow p-6 border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm font-medium">Cette Semaine</p>
                        <p class="text-3xl font-bold text-gray-800" id="ca-semaine">0 €</p>
                    </div>
                    <div class="bg-blue-100 p-3 rounded-full">
                        <i class="fas fa-calendar-week text-2xl text-blue-600"></i>
                    </div>
                </div>
                <div class="mt-4 text-sm text-gray-500">
                    Depuis lundi
                </div>
            </div>

            <!-- CA Mois -->
            <div class="stat-card bg-white rounded-xl card-shadow p-6 border-l-4 border-purple-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm font-medium">Ce Mois</p>
                        <p class="text-3xl font-bold text-gray-800" id="ca-mois">0 €</p>
                    </div>
                    <div class="bg-purple-100 p-3 rounded-full">
                        <i class="fas fa-calendar-alt text-2xl text-purple-600"></i>
                    </div>
                </div>
                <div class="mt-4 text-sm text-gray-500">
                    Objectif mensuel
                </div>
            </div>

            <!-- CA Année -->
            <div class="stat-card bg-white rounded-xl card-shadow p-6 border-l-4 border-orange-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm font-medium">Cette Année</p>
                        <p class="text-3xl font-bold text-gray-800" id="ca-annee">0 €</p>
                    </div>
                    <div class="bg-orange-100 p-3 rounded-full">
                        <i class="fas fa-chart-bar text-2xl text-orange-600"></i>
                    </div>
                </div>
                <div class="mt-4 text-sm text-gray-500">
                    Cumul annuel
                </div>
            </div>
        </div>

        <!-- RDV du jour -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-xl card-shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm font-medium">RDV Aujourd'hui</p>
                        <p class="text-4xl font-bold text-blue-600" id="rdv-auj-total">0</p>
                    </div>
                    <div class="bg-blue-100 p-4 rounded-full">
                        <i class="fas fa-calendar-day text-3xl text-blue-600"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl card-shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm font-medium">En Cours</p>
                        <p class="text-4xl font-bold text-yellow-600" id="rdv-en-cours">0</p>
                    </div>
                    <div class="bg-yellow-100 p-4 rounded-full">
                        <i class="fas fa-wrench text-3xl text-yellow-600"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl card-shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm font-medium">Terminés</p>
                        <p class="text-4xl font-bold text-green-600" id="rdv-termines">0</p>
                    </div>
                    <div class="bg-green-100 p-4 rounded-full">
                        <i class="fas fa-check-circle text-3xl text-green-600"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Graphiques principaux -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Évolution mensuelle -->
            <div class="bg-white rounded-xl card-shadow p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-chart-line mr-2 text-blue-600"></i>Évolution du CA (6 mois)
                </h3>
                <div class="chart-container">
                    <canvas id="evolutionChart"></canvas>
                </div>
            </div>

            <!-- Top interventions -->
            <div class="bg-white rounded-xl card-shadow p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-tools mr-2 text-purple-600"></i>Top Interventions
                </h3>
                <div class="chart-container-sm">
                    <canvas id="interventionsChart"></canvas>
                </div>
                <div id="top-interventions-list" class="mt-4 space-y-2">
                    <!-- Liste dynamique -->
                </div>
            </div>
        </div>

        <!-- Occupation des ponts -->
        <div class="bg-white rounded-xl card-shadow p-6 mb-8">
            <h3 class="text-xl font-bold text-gray-800 mb-4">
                <i class="fas fa-car-garage mr-2 text-orange-600"></i>Taux d'Occupation des Ponts (Semaine)
            </h3>
            <div id="ponts-occupation" class="space-y-4">
                <!-- Barres de progression dynamiques -->
            </div>
        </div>

        <!-- Clients fidèles -->
        <div class="bg-white rounded-xl card-shadow p-6 mb-8">
            <h3 class="text-xl font-bold text-gray-800 mb-4">
                <i class="fas fa-users mr-2 text-green-600"></i>Clients Fidèles (Top 10)
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Client</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Téléphone</th>
                            <th class="px-4 py-3 text-center text-sm font-semibold text-gray-600">RDV</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-600">CA Total</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Dernier RDV</th>
                        </tr>
                    </thead>
                    <tbody id="clients-fideles-list" class="divide-y divide-gray-200">
                        <!-- Lignes dynamiques -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Graphique répartition RDV -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <div class="bg-white rounded-xl card-shadow p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-chart-pie mr-2 text-pink-600"></i>Répartition par Type
                </h3>
                <div class="chart-container-sm">
                    <canvas id="repartitionChart"></canvas>
                </div>
            </div>

            <div class="bg-white rounded-xl card-shadow p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-chart-bar mr-2 text-indigo-600"></i>RDV par Mois
                </h3>
                <div class="chart-container-sm">
                    <canvas id="rdvMoisChart"></canvas>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-6 mt-12">
        <div class="container mx-auto px-4 text-center">
            <p class="text-gray-400">Atelier Moto Pro - Tableau de Bord Statistiques</p>
            <p class="text-gray-500 text-sm mt-1">Dernière mise à jour: <span id="last-update">-</span></p>
        </div>
    </footer>

    <script>
        const API_URL = '';
        let charts = {};

        // Initialisation
        document.addEventListener('DOMContentLoaded', () => {
            updateCurrentDate();
            loadDashboardStats();
        });

        function updateCurrentDate() {
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            document.getElementById('current-date').textContent = new Date().toLocaleDateString('fr-FR', options);
        }

        async function loadDashboardStats() {
            // Animation du bouton refresh
            const refreshIcon = document.getElementById('refresh-icon');
            refreshIcon.classList.add('fa-spin');

            try {
                const response = await fetch(`${API_URL}/api/statistiques/dashboard`, {
                    headers: {
                        'Authorization': `Bearer ${localStorage.getItem('token') || ''}`
                    }
                });

                if (!response.ok) {
                    if (response.status === 401) {
                        // Rediriger vers l'app principale pour login
                        window.location.href = '/';
                        return;
                    }
                    throw new Error('Erreur de chargement');
                }

                const data = await response.json();
                updateDashboard(data);
                document.getElementById('last-update').textContent = new Date().toLocaleString('fr-FR');
            } catch (error) {
                console.error('Erreur:', error);
                // Afficher des données de démo si erreur
                showDemoData();
            } finally {
                refreshIcon.classList.remove('fa-spin');
            }
        }

        function updateDashboard(data) {
            // Mise à jour des cartes CA
            document.getElementById('ca-jour').textContent = formatMoney(data.ca.jour);
            document.getElementById('ca-semaine').textContent = formatMoney(data.ca.semaine);
            document.getElementById('ca-mois').textContent = formatMoney(data.ca.mois);
            document.getElementById('ca-annee').textContent = formatMoney(data.ca.annee);

            // Mise à jour des RDV - utiliser la bonne structure
            const rdvAujourdHui = data.rdv_par_statut ? 
                (data.rdv_par_statut.confirme || 0) + (data.rdv_par_statut.en_cours || 0) + (data.rdv_par_statut.termine || 0) : 
                (data.rdv ? data.rdv.aujourd_hui : 0);
            document.getElementById('rdv-auj-total').textContent = rdvAujourdHui;
            document.getElementById('rdv-en-cours').textContent = data.rdv_par_statut ? data.rdv_par_statut.en_cours : (data.rdv ? data.rdv.en_cours : 0);
            document.getElementById('rdv-termines').textContent = data.rdv_par_statut ? data.rdv_par_statut.termine : (data.rdv ? data.rdv.termines : 0);
            document.getElementById('rdv-jour').textContent = rdvAujourdHui;

            // Mise à jour des graphiques - adapter la structure
            const evolutionData = data.evolution && data.evolution.evolution ? data.evolution.evolution : 
                                 (data.evolution_mensuelle ? data.evolution_mensuelle.evolution : []);
            const topInterventionsData = data.top_interventions && data.top_interventions.top_interventions ? data.top_interventions.top_interventions : 
                                        (data.top_interventions || []);
            
            updateEvolutionChart(evolutionData);
            updateInterventionsChart(topInterventionsData);
            updateRepartitionChart(topInterventionsData);
            updateRdvMoisChart(evolutionData);

            // Mise à jour des ponts
            const pontsData = data.occupation_ponts && data.occupation_ponts.ponts ? data.occupation_ponts.ponts : 
                             (data.ponts ? data.ponts.ponts : []);
            updatePontsOccupation(pontsData);

            // Mise à jour des clients fidèles
            const clientsData = data.clients_fideles && data.clients_fideles.clients_fideles ? data.clients_fideles.clients_fideles : 
                               (data.clients_fideles || []);
            updateClientsFideles(clientsData);
        }

        function formatMoney(amount) {
            return new Intl.NumberFormat('fr-FR', {
                style: 'currency',
                currency: 'EUR'
            }).format(amount);
        }

        function formatDate(dateStr) {
            if (!dateStr) return '-';
            return new Date(dateStr).toLocaleDateString('fr-FR');
        }

        // Graphique d'évolution mensuelle
        function updateEvolutionChart(data) {
            const ctx = document.getElementById('evolutionChart').getContext('2d');
            
            if (charts.evolution) {
                charts.evolution.destroy();
            }

            charts.evolution = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map(d => d.mois_nom),
                    datasets: [{
                        label: 'Chiffre d\'affaires',
                        data: data.map(d => d.ca),
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 5,
                        pointBackgroundColor: '#3b82f6'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value + ' €';
                                }
                            }
                        }
                    }
                }
            });
        }

        // Graphique top interventions
        function updateInterventionsChart(data) {
            const ctx = document.getElementById('interventionsChart').getContext('2d');
            
            if (charts.interventions) {
                charts.interventions.destroy();
            }

            const colors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'];

            charts.interventions = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.map(d => d.type_intervention),
                    datasets: [{
                        data: data.map(d => d.count),
                        backgroundColor: colors,
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                boxWidth: 15,
                                font: {
                                    size: 11
                                }
                            }
                        }
                    }
                }
            });

            // Mettre à jour la liste
            const listContainer = document.getElementById('top-interventions-list');
            listContainer.innerHTML = data.map((item, index) => `
                <div class="flex items-center justify-between p-2 bg-gray-50 rounded">
                    <div class="flex items-center">
                        <span class="w-3 h-3 rounded-full mr-2" style="background-color: ${colors[index] || '#ccc'}"></span>
                        <span class="text-sm font-medium">${item.type_intervention}</span>
                    </div>
                    <div class="text-right">
                        <span class="text-sm font-bold">${item.count}</span>
                        <span class="text-xs text-gray-500 ml-1">(${formatMoney(item.ca_total)})</span>
                    </div>
                </div>
            `).join('');
        }

        // Graphique répartition
        function updateRepartitionChart(data) {
            const ctx = document.getElementById('repartitionChart').getContext('2d');
            
            if (charts.repartition) {
                charts.repartition.destroy();
            }

            charts.repartition = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: data.map(d => d.type_intervention),
                    datasets: [{
                        data: data.map(d => d.ca_total),
                        backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                font: {
                                    size: 10
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': ' + formatMoney(context.raw);
                                }
                            }
                        }
                    }
                }
            });
        }

        // Graphique RDV par mois
        function updateRdvMoisChart(data) {
            const ctx = document.getElementById('rdvMoisChart').getContext('2d');
            
            if (charts.rdvMois) {
                charts.rdvMois.destroy();
            }

            charts.rdvMois = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(d => d.mois_nom),
                    datasets: [{
                        label: 'Nombre de RDV',
                        data: data.map(d => d.nb_rdv),
                        backgroundColor: 'rgba(99, 102, 241, 0.8)',
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }

        // Mise à jour des barres d'occupation des ponts
        function updatePontsOccupation(ponts) {
            const container = document.getElementById('ponts-occupation');
            
            if (!ponts || ponts.length === 0) {
                container.innerHTML = '<p class="text-gray-500 text-center">Aucun pont configuré</p>';
                return;
            }

            container.innerHTML = ponts.map(pont => {
                const colorClass = pont.taux_occupation > 80 ? 'bg-red-500' : 
                                  pont.taux_occupation > 50 ? 'bg-yellow-500' : 'bg-green-500';
                
                return `
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="flex justify-between items-center mb-2">
                            <span class="font-semibold text-gray-700">${pont.pont_nom}</span>
                            <span class="text-sm font-medium ${pont.taux_occupation > 80 ? 'text-red-600' : pont.taux_occupation > 50 ? 'text-yellow-600' : 'text-green-600'}">
                                ${pont.taux_occupation}% - ${pont.nb_rdv} RDV
                            </span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-4">
                            <div class="${colorClass} h-4 rounded-full progress-bar" style="width: ${Math.min(pont.taux_occupation, 100)}%"></div>
                        </div>
                        <div class="flex justify-between text-xs text-gray-500 mt-1">
                            <span>${pont.heures_occupees}h / ${pont.heures_total}h</span>
                            <span>${pont.nb_rdv} interventions</span>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // Mise à jour de la liste des clients fidèles
        function updateClientsFideles(clients) {
            const tbody = document.getElementById('clients-fideles-list');
            
            if (!clients || clients.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">Aucun client enregistré</td></tr>';
                return;
            }

            tbody.innerHTML = clients.map((client, index) => `
                <tr class="hover:bg-gray-50 ${index < 3 ? 'bg-yellow-50' : ''}">
                    <td class="px-4 py-3">
                        <div class="flex items-center">
                            ${index < 3 ? `<span class="w-6 h-6 rounded-full bg-yellow-400 text-white text-xs flex items-center justify-center mr-2 font-bold">${index + 1}</span>` : ''}
                            <span class="font-medium">${client.prenom} ${client.nom}</span>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-sm">${client.telephone}</td>
                    <td class="px-4 py-3 text-center">
                        <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-semibold">${client.nb_rdv}</span>
                    </td>
                    <td class="px-4 py-3 text-right font-semibold text-green-600">${formatMoney(client.ca_total)}</td>
                    <td class="px-4 py-3 text-sm text-gray-500">${formatDate(client.dernier_rdv)}</td>
                </tr>
            `).join('');
        }

        // Données de démo si erreur
        function showDemoData() {
            const demoData = {
                ca: { jour: 1250, semaine: 8750, mois: 32500, annee: 185000 },
                rdv: { aujourd_hui: 8, en_cours: 3, termines: 4 },
                evolution_mensuelle: {
                    evolution: [
                        { mois_nom: 'Oct 2025', ca: 28000, nb_rdv: 45 },
                        { mois_nom: 'Nov 2025', ca: 31000, nb_rdv: 52 },
                        { mois_nom: 'Déc 2025', ca: 29000, nb_rdv: 48 },
                        { mois_nom: 'Jan 2026', ca: 32000, nb_rdv: 55 },
                        { mois_nom: 'Fév 2026', ca: 30000, nb_rdv: 50 },
                        { mois_nom: 'Mar 2026', ca: 32500, nb_rdv: 58 }
                    ]
                },
                top_interventions: {
                    top_interventions: [
                        { type_intervention: 'Révision', count: 45, ca_total: 13500 },
                        { type_intervention: 'Pneumatiques', count: 32, ca_total: 6400 },
                        { type_intervention: 'Freins', count: 28, ca_total: 5600 },
                        { type_intervention: 'Échappement', count: 15, ca_total: 4500 },
                        { type_intervention: 'Carburation', count: 12, ca_total: 3600 }
                    ]
                },
                ponts: {
                    ponts: [
                        { pont_nom: 'Pont 1', taux_occupation: 85, heures_occupees: 42, heures_total: 50, nb_rdv: 18 },
                        { pont_nom: 'Pont 2', taux_occupation: 72, heures_occupees: 36, heures_total: 50, nb_rdv: 15 },
                        { pont_nom: 'Pont 3', taux_occupation: 45, heures_occupees: 22.5, heures_total: 50, nb_rdv: 9 }
                    ]
                },
                clients_fideles: {
                    clients_fideles: [
                        { prenom: 'Jean', nom: 'Dupont', telephone: '06 12 34 56 78', nb_rdv: 12, ca_total: 8500, dernier_rdv: '2026-03-20' },
                        { prenom: 'Marie', nom: 'Martin', telephone: '06 23 45 67 89', nb_rdv: 10, ca_total: 7200, dernier_rdv: '2026-03-18' },
                        { prenom: 'Pierre', nom: 'Bernard', telephone: '06 34 56 78 90', nb_rdv: 8, ca_total: 5800, dernier_rdv: '2026-03-15' },
                        { prenom: 'Sophie', nom: 'Petit', telephone: '06 45 67 89 01', nb_rdv: 7, ca_total: 4900, dernier_rdv: '2026-03-10' },
                        { prenom: 'Lucas', nom: 'Robert', telephone: '06 56 78 90 12', nb_rdv: 6, ca_total: 4200, dernier_rdv: '2026-03-05' }
                    ]
                }
            };

            updateDashboard(demoData);
            document.getElementById('last-update').textContent = 'Données de démonstration';
        }
    </script>
</body>
</html>
