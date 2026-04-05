/* ============================================
   ATELIER MOTO PRO - Layout & Navigation
   Header, sidebar, grid system
   ============================================ */

/* ============================================
   APP LAYOUT
   ============================================ */

.app {
  display: flex;
  min-height: 100vh;
}

.app-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  margin-left: 260px; /* Width of sidebar */
  transition: margin-left var(--transition-base);
}

.app-content.expanded {
  margin-left: 0;
}

.main-content {
  flex: 1;
  padding: var(--space-6);
  overflow-y: auto;
}

/* ============================================
   SIDEBAR
   ============================================ */

.sidebar {
  position: fixed;
  left: 0;
  top: 0;
  bottom: 0;
  width: 260px;
  background: linear-gradient(180deg, var(--color-secondary) 0%, var(--color-secondary-light) 100%);
  color: white;
  display: flex;
  flex-direction: column;
  z-index: var(--z-sticky);
  transition: transform var(--transition-base);
}

.sidebar-header {
  padding: var(--space-6);
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.sidebar-brand {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  text-decoration: none;
  color: white;
}

.sidebar-brand-icon {
  width: 44px;
  height: 44px;
  background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-light) 100%);
  border-radius: var(--radius-lg);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
}

.sidebar-brand-text {
  display: flex;
  flex-direction: column;
}

.sidebar-brand-title {
  font-size: 1.125rem;
  font-weight: 700;
  line-height: 1.2;
}

.sidebar-brand-subtitle {
  font-size: 0.75rem;
  color: rgba(255, 255, 255, 0.6);
}

.sidebar-nav {
  flex: 1;
  padding: var(--space-4);
  overflow-y: auto;
}

.sidebar-section {
  margin-bottom: var(--space-6);
}

.sidebar-section-title {
  font-size: 0.6875rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: rgba(255, 255, 255, 0.4);
  padding: 0 var(--space-3);
  margin-bottom: var(--space-2);
}

.sidebar-menu {
  list-style: none;
  margin: 0;
  padding: 0;
}

.sidebar-item {
  margin-bottom: var(--space-1);
}

.sidebar-link {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-3) var(--space-4);
  color: rgba(255, 255, 255, 0.7);
  text-decoration: none;
  border-radius: var(--radius-lg);
  transition: all var(--transition-fast);
  font-size: 0.875rem;
  font-weight: 500;
}

.sidebar-link:hover {
  color: white;
  background: rgba(255, 255, 255, 0.05);
}

.sidebar-link.active {
  color: white;
  background: var(--color-primary);
  box-shadow: 0 4px 14px rgba(37, 99, 235, 0.4);
}

.sidebar-link i {
  width: 20px;
  text-align: center;
  font-size: 1rem;
}

.sidebar-footer {
  padding: var(--space-4);
  border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.user-card {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-3);
  background: rgba(255, 255, 255, 0.05);
  border-radius: var(--radius-lg);
}

.user-avatar {
  width: 40px;
  height: 40px;
  background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-light) 100%);
  border-radius: var(--radius-full);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 0.875rem;
}

.user-info {
  flex: 1;
  min-width: 0;
}

.user-name {
  font-size: 0.875rem;
  font-weight: 600;
  color: white;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.user-role {
  font-size: 0.75rem;
  color: rgba(255, 255, 255, 0.5);
}

.user-menu-btn {
  padding: var(--space-2);
  color: rgba(255, 255, 255, 0.5);
  background: none;
  border: none;
  border-radius: var(--radius-md);
  cursor: pointer;
  transition: all var(--transition-fast);
}

.user-menu-btn:hover {
  color: white;
  background: rgba(255, 255, 255, 0.1);
}

/* ============================================
   HEADER
   ============================================ */

.header {
  height: 70px;
  background: white;
  border-bottom: 1px solid var(--color-gray-200);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 var(--space-6);
  position: sticky;
  top: 0;
  z-index: var(--z-sticky);
}

.header-left {
  display: flex;
  align-items: center;
  gap: var(--space-4);
}

.header-toggle {
  display: none;
  padding: var(--space-2);
  color: var(--color-gray-600);
  background: none;
  border: none;
  border-radius: var(--radius-md);
  cursor: pointer;
}

.header-title {
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--color-gray-800);
}

.header-right {
  display: flex;
  align-items: center;
  gap: var(--space-3);
}

.header-btn {
  position: relative;
  padding: var(--space-2);
  color: var(--color-gray-500);
  background: none;
  border: none;
  border-radius: var(--radius-lg);
  cursor: pointer;
  transition: all var(--transition-fast);
}

.header-btn:hover {
  color: var(--color-gray-700);
  background: var(--color-gray-100);
}

.header-btn-badge {
  position: absolute;
  top: 0;
  right: 0;
  width: 18px;
  height: 18px;
  background: var(--color-danger);
  color: white;
  font-size: 0.625rem;
  font-weight: 600;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

/* ============================================
   GRID SYSTEM
   ============================================ */

.grid {
  display: grid;
  gap: var(--space-6);
}

.grid-2 { grid-template-columns: repeat(2, 1fr); }
.grid-3 { grid-template-columns: repeat(3, 1fr); }
.grid-4 { grid-template-columns: repeat(4, 1fr); }
.grid-5 { grid-template-columns: repeat(5, 1fr); }
.grid-6 { grid-template-columns: repeat(6, 1fr); }

@media (max-width: 1280px) {
  .grid-6 { grid-template-columns: repeat(4, 1fr); }
  .grid-5 { grid-template-columns: repeat(3, 1fr); }
}

@media (max-width: 1024px) {
  .grid-4,
  .grid-5,
  .grid-6 { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 768px) {
  .grid-2,
  .grid-3,
  .grid-4,
  .grid-5,
  .grid-6 { grid-template-columns: 1fr; }
}

/* ============================================
   PAGE HEADER
   ============================================ */

.page-header {
  margin-bottom: var(--space-6);
}

.page-header-content {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-4);
}

.page-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--color-gray-800);
  margin: 0 0 var(--space-1);
}

.page-subtitle {
  font-size: 0.875rem;
  color: var(--color-gray-500);
  margin: 0;
}

.page-actions {
  display: flex;
  gap: var(--space-3);
  flex-shrink: 0;
}

/* Breadcrumbs */
.breadcrumbs {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  margin-bottom: var(--space-4);
  font-size: 0.875rem;
}

.breadcrumb-item {
  color: var(--color-gray-500);
  text-decoration: none;
  transition: color var(--transition-fast);
}

.breadcrumb-item:hover {
  color: var(--color-primary);
}

.breadcrumb-item.active {
  color: var(--color-gray-700);
  font-weight: 500;
}

.breadcrumb-separator {
  color: var(--color-gray-300);
}

/* ============================================
   SECTIONS
   ============================================ */

.section {
  margin-bottom: var(--space-8);
}

.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: var(--space-4);
}

.section-title {
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--color-gray-800);
  margin: 0;
}

/* ============================================
   EMPTY STATE
   ============================================ */

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: var(--space-12) var(--space-6);
  text-align: center;
}

.empty-state-icon {
  width: 80px;
  height: 80px;
  background: var(--color-gray-100);
  border-radius: var(--radius-full);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: var(--space-4);
  color: var(--color-gray-400);
  font-size: 2rem;
}

.empty-state-title {
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--color-gray-700);
  margin: 0 0 var(--space-2);
}

.empty-state-text {
  font-size: 0.875rem;
  color: var(--color-gray-500);
  margin: 0 0 var(--space-6);
  max-width: 400px;
}

/* ============================================
   DIVIDERS
   ============================================ */

.divider {
  height: 1px;
  background: var(--color-gray-200);
  margin: var(--space-6) 0;
}

.divider-vertical {
  width: 1px;
  height: auto;
  align-self: stretch;
  margin: 0 var(--space-6);
}

/* ============================================
   RESPONSIVE
   ============================================ */

@media (max-width: 1024px) {
  .sidebar {
    transform: translateX(-100%);
  }
  
  .sidebar.active {
    transform: translateX(0);
  }
  
  .app-content {
    margin-left: 0;
  }
  
  .header-toggle {
    display: block;
  }
  
  .sidebar-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: calc(var(--z-sticky) - 1);
    opacity: 0;
    visibility: hidden;
    transition: all var(--transition-base);
  }
  
  .sidebar-overlay.active {
    opacity: 1;
    visibility: visible;
  }
}

@media (max-width: 640px) {
  .main-content {
    padding: var(--space-4);
  }
  
  .header {
    padding: 0 var(--space-4);
  }
  
  .page-header-content {
    flex-direction: column;
  }
  
  .page-actions {
    width: 100%;
  }
  
  .page-actions .btn {
    flex: 1;
  }
}