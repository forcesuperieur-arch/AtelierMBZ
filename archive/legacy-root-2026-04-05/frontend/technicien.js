/* ============================================
   ATELIER MOTO PRO - Composants UI
   Boutons, cartes, formulaires, badges, etc.
   ============================================ */

/* ============================================
   BOUTONS
   ============================================ */

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  padding: var(--space-3) var(--space-5);
  font-size: 0.875rem;
  font-weight: 500;
  line-height: 1.5;
  border-radius: var(--radius-lg);
  border: none;
  cursor: pointer;
  transition: all var(--transition-fast);
  white-space: nowrap;
  text-decoration: none;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Variantes de boutons */
.btn-primary {
  background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-dark) 100%);
  color: white;
  box-shadow: 0 4px 14px rgba(37, 99, 235, 0.3);
}

.btn-primary:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(37, 99, 235, 0.4);
}

.btn-primary:active:not(:disabled) {
  transform: translateY(0);
}

.btn-secondary {
  background: white;
  color: var(--color-gray-700);
  border: 1px solid var(--color-gray-200);
}

.btn-secondary:hover:not(:disabled) {
  background: var(--color-gray-50);
  border-color: var(--color-gray-300);
}

.btn-success {
  background: linear-gradient(135deg, var(--color-success) 0%, #059669 100%);
  color: white;
  box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3);
}

.btn-success:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
}

.btn-danger {
  background: linear-gradient(135deg, var(--color-danger) 0%, #dc2626 100%);
  color: white;
  box-shadow: 0 4px 14px rgba(239, 68, 68, 0.3);
}

.btn-danger:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
}

.btn-warning {
  background: linear-gradient(135deg, var(--color-warning) 0%, #d97706 100%);
  color: white;
  box-shadow: 0 4px 14px rgba(245, 158, 11, 0.3);
}

.btn-ghost {
  background: transparent;
  color: var(--color-gray-600);
}

.btn-ghost:hover:not(:disabled) {
  background: var(--color-gray-100);
  color: var(--color-gray-800);
}

/* Tailles de boutons */
.btn-sm {
  padding: var(--space-2) var(--space-3);
  font-size: 0.75rem;
}

.btn-lg {
  padding: var(--space-4) var(--space-6);
  font-size: 1rem;
}

.btn-xl {
  padding: var(--space-4) var(--space-8);
  font-size: 1.125rem;
}

/* Bouton avec icône seule */
.btn-icon {
  padding: var(--space-2);
  border-radius: var(--radius-md);
}

.btn-icon.btn-sm { padding: var(--space-1); }
.btn-icon.btn-lg { padding: var(--space-3); }

/* ============================================
   CARTES
   ============================================ */

.card {
  background: white;
  border-radius: var(--radius-xl);
  box-shadow: var(--shadow-card);
  transition: all var(--transition-base);
  overflow: hidden;
}

.card:hover {
  box-shadow: var(--shadow-card-hover);
}

.card-interactive {
  cursor: pointer;
}

.card-interactive:hover {
  transform: translateY(-2px);
}

.card-header {
  padding: var(--space-5) var(--space-6);
  border-bottom: 1px solid var(--color-gray-100);
}

.card-body {
  padding: var(--space-6);
}

.card-footer {
  padding: var(--space-4) var(--space-6);
  background: var(--color-gray-50);
  border-top: 1px solid var(--color-gray-100);
}

/* Carte statistique */
.stat-card {
  background: white;
  border-radius: var(--radius-xl);
  padding: var(--space-5);
  box-shadow: var(--shadow-card);
  transition: all var(--transition-base);
  position: relative;
  overflow: hidden;
}

.stat-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: linear-gradient(90deg, var(--color-primary), var(--color-primary-light));
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-card-hover);
}

.stat-card.success::before {
  background: linear-gradient(90deg, var(--color-success), #34d399);
}

.stat-card.warning::before {
  background: linear-gradient(90deg, var(--color-warning), #fbbf24);
}

.stat-card.danger::before {
  background: linear-gradient(90deg, var(--color-danger), #f87171);
}

.stat-card.info::before {
  background: linear-gradient(90deg, var(--color-info), #60a5fa);
}

/* ============================================
   FORMULAIRES
   ============================================ */

.form-group {
  margin-bottom: var(--space-5);
}

.form-label {
  display: block;
  margin-bottom: var(--space-2);
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--color-gray-700);
}

.form-label-required::after {
  content: ' *';
  color: var(--color-danger);
}

.form-input,
.form-select,
.form-textarea {
  width: 100%;
  padding: var(--space-3) var(--space-4);
  font-size: 0.875rem;
  line-height: 1.5;
  color: var(--color-gray-800);
  background: white;
  border: 2px solid var(--color-gray-200);
  border-radius: var(--radius-lg);
  transition: all var(--transition-fast);
}

.form-input:hover,
.form-select:hover,
.form-textarea:hover {
  border-color: var(--color-gray-300);
}

.form-input:focus,
.form-select:focus,
.form-textarea:focus {
  outline: none;
  border-color: var(--color-primary);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.form-input:disabled,
.form-select:disabled,
.form-textarea:disabled {
  background: var(--color-gray-100);
  cursor: not-allowed;
}

.form-input::placeholder {
  color: var(--color-gray-400);
}

.form-textarea {
  min-height: 100px;
  resize: vertical;
}

/* Input avec icône */
.input-group {
  position: relative;
}

.input-group-icon {
  position: absolute;
  left: var(--space-4);
  top: 50%;
  transform: translateY(-50%);
  color: var(--color-gray-400);
  pointer-events: none;
}

.input-group .form-input {
  padding-left: var(--space-10);
}

/* Checkbox et Radio */
.form-check {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  cursor: pointer;
}

.form-check-input {
  width: 1.125rem;
  height: 1.125rem;
  border: 2px solid var(--color-gray-300);
  border-radius: var(--radius-sm);
  appearance: none;
  cursor: pointer;
  transition: all var(--transition-fast);
}

.form-check-input:checked {
  background: var(--color-primary);
  border-color: var(--color-primary);
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='white'%3E%3Cpath fill-rule='evenodd' d='M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z' clip-rule='evenodd'/%3E%3C/svg%3E");
}

.form-check-input:focus {
  outline: none;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

/* Switch/Toggle */
.switch {
  position: relative;
  display: inline-flex;
  align-items: center;
  cursor: pointer;
}

.switch-input {
  position: absolute;
  opacity: 0;
  width: 0;
  height: 0;
}

.switch-slider {
  position: relative;
  width: 44px;
  height: 24px;
  background: var(--color-gray-300);
  border-radius: var(--radius-full);
  transition: background var(--transition-fast);
}

.switch-slider::after {
  content: '';
  position: absolute;
  top: 2px;
  left: 2px;
  width: 20px;
  height: 20px;
  background: white;
  border-radius: 50%;
  transition: transform var(--transition-fast);
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.switch-input:checked + .switch-slider {
  background: var(--color-primary);
}

.switch-input:checked + .switch-slider::after {
  transform: translateX(20px);
}

/* ============================================
   BADGES
   ============================================ */

.badge {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  padding: var(--space-1) var(--space-2);
  font-size: 0.75rem;
  font-weight: 600;
  border-radius: var(--radius-full);
  white-space: nowrap;
}

.badge-primary {
  background: var(--color-primary-50);
  color: var(--color-primary-dark);
}

.badge-success {
  background: var(--color-success-light);
  color: #065f46;
}

.badge-warning {
  background: var(--color-warning-light);
  color: #92400e;
}

.badge-danger {
  background: var(--color-danger-light);
  color: #991b1b;
}

.badge-info {
  background: var(--color-info-light);
  color: #1e40af;
}

.badge-secondary {
  background: var(--color-gray-100);
  color: var(--color-gray-700);
}

/* Badge avec point */
.badge-dot::before {
  content: '';
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: currentColor;
}

/* ============================================
   ALERTES
   ============================================ */

.alert {
  display: flex;
  align-items: flex-start;
  gap: var(--space-3);
  padding: var(--space-4);
  border-radius: var(--radius-lg);
  font-size: 0.875rem;
}

.alert-success {
  background: var(--color-success-light);
  color: #065f46;
  border: 1px solid #a7f3d0;
}

.alert-warning {
  background: var(--color-warning-light);
  color: #92400e;
  border: 1px solid #fde68a;
}

.alert-danger {
  background: var(--color-danger-light);
  color: #991b1b;
  border: 1px solid #fecaca;
}

.alert-info {
  background: var(--color-info-light);
  color: #1e40af;
  border: 1px solid #bfdbfe;
}

/* ============================================
   TABLEAUX
   ============================================ */

.table-container {
  overflow-x: auto;
  border-radius: var(--radius-xl);
  box-shadow: var(--shadow-card);
}

.table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
  background: white;
}

.table th {
  padding: var(--space-4) var(--space-5);
  text-align: left;
  font-weight: 600;
  color: var(--color-gray-600);
  background: var(--color-gray-50);
  border-bottom: 1px solid var(--color-gray-200);
  white-space: nowrap;
}

.table td {
  padding: var(--space-4) var(--space-5);
  border-bottom: 1px solid var(--color-gray-100);
  color: var(--color-gray-700);
}

.table tbody tr:hover {
  background: var(--color-gray-50);
}

.table tbody tr:last-child td {
  border-bottom: none;
}

/* Tableau compact */
.table-compact th,
.table-compact td {
  padding: var(--space-3) var(--space-4);
}

/* ============================================
   MODALES
   ============================================ */

.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-4);
  z-index: var(--z-modal);
  opacity: 0;
  visibility: hidden;
  transition: all var(--transition-base);
}

.modal-overlay.active {
  opacity: 1;
  visibility: visible;
}

.modal {
  background: white;
  border-radius: var(--radius-2xl);
  width: 100%;
  max-width: 500px;
  max-height: calc(100vh - var(--space-8));
  overflow: hidden;
  transform: scale(0.95) translateY(10px);
  transition: transform var(--transition-base);
}

.modal-overlay.active .modal {
  transform: scale(1) translateY(0);
}

.modal-lg { max-width: 800px; }
.modal-xl { max-width: 1000px; }
.modal-full { max-width: none; margin: var(--space-4); }

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--space-5) var(--space-6);
  border-bottom: 1px solid var(--color-gray-100);
}

.modal-title {
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--color-gray-800);
}

.modal-close {
  padding: var(--space-2);
  color: var(--color-gray-400);
  background: none;
  border: none;
  border-radius: var(--radius-md);
  cursor: pointer;
  transition: all var(--transition-fast);
}

.modal-close:hover {
  color: var(--color-gray-600);
  background: var(--color-gray-100);
}

.modal-body {
  padding: var(--space-6);
  overflow-y: auto;
  max-height: calc(100vh - 200px);
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-3);
  padding: var(--space-4) var(--space-6);
  background: var(--color-gray-50);
  border-top: 1px solid var(--color-gray-100);
}

/* ============================================
   NAVIGATION
   ============================================ */

.nav-tabs {
  display: flex;
  gap: var(--space-1);
  padding: var(--space-1);
  background: var(--color-gray-100);
  border-radius: var(--radius-lg);
}

.nav-tab {
  padding: var(--space-2) var(--space-4);
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--color-gray-600);
  background: none;
  border: none;
  border-radius: var(--radius-md);
  cursor: pointer;
  transition: all var(--transition-fast);
}

.nav-tab:hover {
  color: var(--color-gray-800);
}

.nav-tab.active {
  color: var(--color-gray-800);
  background: white;
  box-shadow: var(--shadow-sm);
}

/* ============================================
   TOASTS / NOTIFICATIONS
   ============================================ */

.toast-container {
  position: fixed;
  top: var(--space-4);
  right: var(--space-4);
  z-index: var(--z-toast);
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.toast {
  display: flex;
  align-items: flex-start;
  gap: var(--space-3);
  padding: var(--space-4);
  background: white;
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-lg);
  min-width: 300px;
  max-width: 400px;
  animation: slideInRight 0.3s ease-out;
}

.toast-success { border-left: 4px solid var(--color-success); }
.toast-warning { border-left: 4px solid var(--color-warning); }
.toast-danger { border-left: 4px solid var(--color-danger); }
.toast-info { border-left: 4px solid var(--color-info); }

/* ============================================
   SKELETON LOADING
   ============================================ */

.skeleton {
  background: linear-gradient(90deg, var(--color-gray-200) 25%, var(--color-gray-100) 50%, var(--color-gray-200) 75%);
  background-size: 200% 100%;
  animation: skeleton-loading 1.5s ease-in-out infinite;
  border-radius: var(--radius-md);
}

@keyframes skeleton-loading {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

.skeleton-text {
  height: 1em;
  margin-bottom: var(--space-2);
}

.skeleton-text:last-child {
  width: 80%;
}