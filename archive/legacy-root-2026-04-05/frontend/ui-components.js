<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiches Clients - Atelier Moto Pro</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <link rel="stylesheet" href="/static/theme.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom Styles -->
    <link rel="stylesheet" href="styles/design-system.css">
    <link rel="stylesheet" href="styles/components.css">
    <link rel="stylesheet" href="styles/layout.css">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    
    <style>
        .client-card {
            transition: all 0.2s ease;
        }
        
        .client-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .vehicle-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: #f1f5f9;
            border-radius: 8px;
            font-size: 0.875rem;
            color: #475569;
        }
    </style>
</head>
<body class="bg-gray-50">
    
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <a href="/dashboard.html" class="sidebar-brand">
                <div class="sidebar-brand-icon">
                    <i class="fas fa-motorcycle"></i>
                </div>
                <div class="sidebar-brand-text">
                    <span class="sidebar-brand-title">Atelier Moto Pro</span>
                    <span class="sidebar-brand-subtitle">Gestion atelier</span>
                </div>
            </a>
        </div>
        
        <nav class="sidebar-nav">
            <div class="sidebar-section">
                <p class="sidebar-section-title">Principal</p>
                <ul class="sidebar-menu">
                    <li class="sidebar-item">
                        <a href="/dashboard.html" class="sidebar-link">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a href="/rendez-vous.html" class="sidebar-link">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Rendez-vous</span>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a href="/planning.html" class="sidebar-link">
                            <i class="fas fa-clock"></i>
                            <span>Planning</span>
                        </a>
                    </li>
                </ul>
            </div>
            
            <div class="sidebar-section">
                <p class="sidebar-section-title">Gestion</p>
                <ul class="sidebar-menu">
                    <li class="sidebar-item">
                        <a href="/clients.html" class="sidebar-link active">
                            <i class="fas fa-users"></i>
                            <span>Clients</span>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a href="/stock.html" class="sidebar-link">
                            <i class="fas fa-boxes"></i>
                            <span>Stock</span>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a href="/fournisseurs.html" class="sidebar-link">
                            <i class="fas fa-truck"></i>
                            <span>Fournisseurs</span>
                        </a>
                    </li>
                </ul>
            </div>
            
            <div class="sidebar-section">
                <p class="sidebar-section-title">Rapports</p>
                <ul class="sidebar-menu">
                    <li class="sidebar-item">
                        <a href="/statistiques.html" class="sidebar-link">
                            <i class="fas fa-chart-line"></i>
                            <span>Statistiques</span>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a href="/factures.html" class="sidebar-link">
                            <i class="fas fa-file-invoice-dollar"></i>
                            <span>Factures</span>
                        </a>
                    </li>
                </ul>
            </div>
        </nav>
        
        <div class="sidebar-footer">
            <div class="user-card">
                <div class="user-avatar" id="user-avatar">A</div>
                <div class="user-info">
                    <div class="user-name" id="user-name">Admin</div>
                    <div class="user-role" id="user-role">Administrateur</div>
                </div>
                <button class="user-menu-btn" onclick="logout()" title="Déconnexion">
                    <i class="fas fa-sign-out-alt"></i>
                </button>
            </div>
        </div>
    </aside>
    
    <!-- Overlay for mobile -->
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>
    
    <!-- Main Content -->
    <div class="app-content">
        <!-- Header -->
        <header class="header">
            <div class="header-left">
                <button class="header-toggle" onclick="toggleSidebar()">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <h1 class="header-title">Clients</h1>
            </div>
            
            <div class="header-right">
                <button class="btn btn-primary btn-sm" onclick="openNewClientModal()">
                    <i class="fas fa-plus mr-2"></i>
                    Nouveau client
                </button>
            </div>
        </header>
        
        <!-- Main -->
        <main class="main-content">
            <!-- Breadcrumbs -->
            <nav class="breadcrumbs">
                <a href="/dashboard.html" class="breadcrumb-item">Dashboard</a>
                <span class="breadcrumb-separator">/</span>
                <span class="breadcrumb-item active">Clients</span>
            </nav>
            
            <!-- Page Header -->
            <div class="page-header">
                <div class="page-header-content">
                    <div>
                        <h2 class="page-title">Gestion des clients</h2>
                        <p class="page-subtitle">Consultez et gérez vos fiches clients</p>
                    </div>
                </div>
            </div>
            
            <!-- Search & Filters -->
            <div class="card mb-6">
                <div class="card-body">
                    <div class="flex flex-wrap items-center gap-4">
                        <div class="flex-1 min-w-[300px]">
                            <div class="input-group">
                                <i class="fas fa-search input-group-icon"></i>
                                <input 
                                    type="text" 
                                    id="search-input" 
                                    class="form-input" 
                                    placeholder="Rechercher par nom, téléphone, email ou plaque..."
                                    onkeyup="searchClients(this.value)"
                                >
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-3">
                            <select id="filter-rdv" class="form-input w-auto" onchange="loadClients()">
                                <option value="">Tous les clients</option>
                                <option value="avec-rdv">Avec RDV</option>
                                <option value="sans-rdv">Sans RDV</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Stats -->
            <div class="grid grid-4 gap-4 mb-6">
                <div class="stat-card">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center">
                            <i class="fas fa-users text-blue-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-500 text-xs">Total clients</p>
                            <p class="text-2xl font-bold text-gray-800" id="stat-total">0</p>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card success">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center">
                            <i class="fas fa-calendar-check text-green-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-500 text-xs">Avec RDV</p>
                            <p class="text-2xl font-bold text-gray-800" id="stat-avec-rdv">0</p>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card warning">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-xl bg-yellow-100 flex items-center justify-center">
                            <i class="fas fa-motorcycle text-yellow-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-500 text-xs">Véhicules</p>
                            <p class="text-2xl font-bold text-gray-800" id="stat-vehicules">0</p>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card info">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-xl bg-purple-100 flex items-center justify-center">
                            <i class="fas fa-euro-sign text-purple-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-500 text-xs">CA généré</p>
                            <p class="text-2xl font-bold text-gray-800" id="stat-ca">0€</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- List View -->
            <div id="view-list" class="card overflow-hidden">
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Contact</th>
                                <th>Véhicules</th>
                                <th>RDV</th>
                                <th>Dernier RDV</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="clients-list">
                            <tr>
                                <td colspan="6" class="text-center py-8">
                                    <div class="loading-spinner mx-auto mb-4"></div>
                                    <p class="text-gray-500">Chargement des clients...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Toast Container -->
    <div id="toast-container" class="toast-container"></div>
    
    <script src="js/ui-components.js"></script>
    <script>
        const API_URL = '';
        const token = localStorage.getItem('token');
        
        if (!token) {
            window.location.href = '/login.html';
        }
        
        let currentPage = 1;
        let clients = [];
        
        document.addEventListener('DOMContentLoaded', () => {
            loadUserInfo();
            loadClients();
            loadStats();
        });
        
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('active');
            document.querySelector('.sidebar-overlay').classList.toggle('active');
        }
        
        function logout() {
            localStorage.removeItem('token');
            window.location.href = '/login.html';
        }
        
        async function loadUserInfo() {
            try {
                const response = await fetch(`${API_URL}/api/auth/me`, {
                    headers: { 'Authorization': `Bearer ${token}` }
                });
                
                if (response.ok) {
                    const user = await response.json();
                    document.getElementById('user-name').textContent = user.prenom || user.username;
                    document.getElementById('user-avatar').textContent = (user.prenom?.[0] || user.username[0]).toUpperCase();
                    document.getElementById('user-role').textContent = user.role === 'admin' ? 'Administrateur' : 'Réception';
                }
            } catch (error) {
                console.error('Error loading user:', error);
            }
        }
        
        async function loadStats() {
            try {
                const response = await fetch(`${API_URL}/api/clients/stats`, {
                    headers: { 'Authorization': `Bearer ${token}` }
                });
                
                if (response.ok) {
                    const stats = await response.json();
                    document.getElementById('stat-total').textContent = stats.total || 0;
                    document.getElementById('stat-avec-rdv').textContent = stats.avec_rdv || 0;
                    document.getElementById('stat-vehicules').textContent = stats.vehicules || 0;
                    document.getElementById('stat-ca').textContent = formatMoney(stats.ca_total || 0);
                }
            } catch (error) {
                console.error('Error loading stats:', error);
            }
        }
        
        async function loadClients() {
            try {
                const search = document.getElementById('search-input').value;
                const filter = document.getElementById('filter-rdv').value;
                
                let url = `${API_URL}/api/clients?page=${currentPage}&limit=20`;
                if (search) url += `&search=${encodeURIComponent(search)}`;
                if (filter) url += `&filter=${filter}`;
                
                const response = await fetch(url, {
                    headers: { 'Authorization': `Bearer ${token}` }
                });
                
                if (!response.ok) throw new Error('Error loading clients');
                
                const data = await response.json();
                clients = data.items || data || [];
                
                renderClients();
                
            } catch (error) {
                console.error('Error:', error);
                toast.error('Erreur lors du chargement des clients');
            }
        }
        
        function renderClients() {
            const tbody = document.getElementById('clients-list');
            
            if (clients.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center py-12">
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <h3 class="empty-state-title">Aucun client trouvé</h3>
                                <p class="empty-state-text">Essayez de modifier votre recherche ou ajoutez un nouveau client.</p>
                                <button class="btn btn-primary" onclick="openNewClientModal()">
                                    <i class="fas fa-plus mr-2"></i>
                                    Nouveau client
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
                return;
            }
            
            tbody.innerHTML = clients.map(client => `
                <tr class="hover:bg-gray-50 cursor-pointer" onclick="showClientDetail(${client.id})">
                    <td>
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white font-semibold">
                                ${client.prenom?.[0] || ''}${client.nom?.[0] || ''}
                            </div>
                            <div>
                                <p class="font-semibold text-gray-800">${client.prenom || ''} ${client.nom || ''}</p>
                                <p class="text-sm text-gray-500">Client depuis ${formatDate(client.created_at, { year: 'numeric', month: 'short' })}</p>
                            </div>
                        </div>
                    </td>
                    <td>
                        <p class="text-sm"><i class="fas fa-phone mr-2 text-gray-400"></i>${formatPhone(client.telephone)}</p>
                        ${client.email ? `<p class="text-sm text-gray-500"><i class="fas fa-envelope mr-2 text-gray-400"></i>${client.email}</p>` : ''}
                    </td>
                    <td>
                        <div class="flex flex-wrap gap-2">
                            ${(client.vehicules || []).slice(0, 2).map(v => `
                                <span class="vehicle-badge">
                                    <i class="fas fa-motorcycle"></i>
                                    ${v.marque} ${v.modele}
                                </span>
                            `).join('')}
                            ${(client.vehicules || []).length > 2 ? `
                                <span class="vehicle-badge">+${client.vehicules.length - 2}</span>
                            ` : ''}
                        </div>
                    </td>
                    <td>
                        <span class="badge badge-primary">${client.nb_rdv || 0} RDV</span>
                    </td>
                    <td>
                        ${client.dernier_rdv ? `
                            <span class="text-sm text-gray-600">${formatDate(client.dernier_rdv)}</span>
                        ` : '<span class="text-sm text-gray-400">Aucun</span>'}
                    </td>
                    <td>
                        <div class="flex items-center gap-2">
                            <button class="btn btn-icon btn-sm" onclick="event.stopPropagation(); window.location.href='/rendez-vous.html?client_id=${client.id}'" title="Nouveau RDV">
                                <i class="fas fa-calendar-plus text-green-600"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `).join('');
        }
        
        function searchClients(query) {
            clearTimeout(window.searchTimeout);
            window.searchTimeout = setTimeout(() => {
                currentPage = 1;
                loadClients();
            }, 300);
        }
        
        function showClientDetail(id) {
            window.location.href = `/client-detail.html?id=${id}`;
        }
        
        function openNewClientModal() {
            window.location.href = '/client-form.html';
        }
    </script>
</body>
</html>