/**
 * Atelier Moto Pro - UI Components JavaScript
 * Gestion des composants interactifs réutilisables
 */

// ============================================
// NOTIFICATIONS / TOASTS
// ============================================

class ToastManager {
  constructor() {
    this.container = this.createContainer();
    this.toasts = [];
  }

  createContainer() {
    let container = document.getElementById('toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toast-container';
      container.className = 'toast-container';
      document.body.appendChild(container);
    }
    return container;
  }

  show(message, type = 'info', duration = 4000) {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    
    const icons = {
      success: 'fa-check-circle',
      warning: 'fa-exclamation-triangle',
      danger: 'fa-times-circle',
      info: 'fa-info-circle'
    };
    
    toast.innerHTML = `
      <i class="fas ${icons[type]} text-lg"></i>
      <div class="flex-1">
        <p class="font-medium text-sm">${message}</p>
      </div>
      <button class="text-gray-400 hover:text-gray-600" onclick="this.parentElement.remove()">
        <i class="fas fa-times"></i>
      </button>
    `;
    
    this.container.appendChild(toast);
    
    // Auto-remove
    setTimeout(() => {
      toast.style.animation = 'fadeOut 0.3s ease-out forwards';
      setTimeout(() => toast.remove(), 300);
    }, duration);
    
    return toast;
  }

  success(message, duration) { return this.show(message, 'success', duration); }
  warning(message, duration) { return this.show(message, 'warning', duration); }
  error(message, duration) { return this.show(message, 'danger', duration); }
  info(message, duration) { return this.show(message, 'info', duration); }
}

// Instance globale
const toast = new ToastManager();

// ============================================
// MODALES
// ============================================

class ModalManager {
  constructor() {
    this.activeModal = null;
    this.setupKeyboardHandler();
  }

  open(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    
    this.activeModal = modal;
    modal.classList.remove('hidden');
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
    
    // Focus trap
    this.setupFocusTrap(modal);
  }

  close(modalId) {
    const modal = document.getElementById(modalId || this.activeModal?.id);
    if (!modal) return;
    
    modal.classList.remove('active');
    setTimeout(() => {
      modal.classList.add('hidden');
    }, 300);
    
    document.body.style.overflow = '';
    this.activeModal = null;
  }

  closeAll() {
    document.querySelectorAll('.modal-overlay').forEach(modal => {
      modal.classList.remove('active');
      setTimeout(() => modal.classList.add('hidden'), 300);
    });
    document.body.style.overflow = '';
    this.activeModal = null;
  }

  setupKeyboardHandler() {
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && this.activeModal) {
        this.close();
      }
    });
  }

  setupFocusTrap(modal) {
    const focusableElements = modal.querySelectorAll(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    );
    
    if (focusableElements.length === 0) return;
    
    const firstElement = focusableElements[0];
    const lastElement = focusableElements[focusableElements.length - 1];
    
    firstElement.focus();
    
    modal.addEventListener('keydown', (e) => {
      if (e.key !== 'Tab') return;
      
      if (e.shiftKey && document.activeElement === firstElement) {
        e.preventDefault();
        lastElement.focus();
      } else if (!e.shiftKey && document.activeElement === lastElement) {
        e.preventDefault();
        firstElement.focus();
      }
    });
  }
}

const modal = new ModalManager();

// ============================================
// CONFIRMATION DIALOG
// ============================================

function confirmDialog(options) {
  return new Promise((resolve) => {
    const { title = 'Confirmation', message, confirmText = 'Confirmer', cancelText = 'Annuler', type = 'warning' } = options;
    
    const dialogId = 'confirm-dialog-' + Date.now();
    const colors = {
      warning: 'btn-warning',
      danger: 'btn-danger',
      success: 'btn-success',
      info: 'btn-primary'
    };
    
    const overlay = document.createElement('div');
    overlay.id = dialogId;
    overlay.className = 'modal-overlay active';
    overlay.innerHTML = `
      <div class="modal animate-fade-in-scale">
        <div class="modal-body text-center">
          <div class="w-16 h-16 mx-auto mb-4 rounded-full flex items-center justify-center ${type === 'danger' ? 'bg-red-100 text-red-600' : type === 'warning' ? 'bg-yellow-100 text-yellow-600' : 'bg-blue-100 text-blue-600'}">
            <i class="fas ${type === 'danger' ? 'fa-exclamation-triangle' : type === 'warning' ? 'fa-exclamation-circle' : 'fa-question-circle'} text-2xl"></i>
          </div>
          <h3 class="text-xl font-bold text-gray-800 mb-2">${title}</h3>
          <p class="text-gray-600 mb-6">${message}</p>
          <div class="flex gap-3 justify-center">
            <button class="btn btn-secondary" data-action="cancel">${cancelText}</button>
            <button class="btn ${colors[type]}" data-action="confirm">${confirmText}</button>
          </div>
        </div>
      </div>
    `;
    
    document.body.appendChild(overlay);
    
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) {
        overlay.remove();
        resolve(false);
      }
    });
    
    overlay.querySelector('[data-action="cancel"]').addEventListener('click', () => {
      overlay.remove();
      resolve(false);
    });
    
    overlay.querySelector('[data-action="confirm"]').addEventListener('click', () => {
      overlay.remove();
      resolve(true);
    });
  });
}

// ============================================
// LOADING STATES
// ============================================

function showLoading(element, message = 'Chargement...') {
  const originalContent = element.innerHTML;
  element.dataset.originalContent = originalContent;
  element.disabled = true;
  element.innerHTML = `
    <span class="loading-spinner loading-spinner-sm"></span>
    <span>${message}</span>
  `;
  return originalContent;
}

function hideLoading(element) {
  const originalContent = element.dataset.originalContent;
  if (originalContent) {
    element.innerHTML = originalContent;
    element.disabled = false;
    delete element.dataset.originalContent;
  }
}

function showPageLoading() {
  let loader = document.getElementById('page-loader');
  if (!loader) {
    loader = document.createElement('div');
    loader.id = 'page-loader';
    loader.className = 'fixed inset-0 bg-white bg-opacity-90 z-50 flex items-center justify-center';
    loader.innerHTML = `
      <div class="text-center">
        <div class="loading-spinner loading-spinner-lg mx-auto mb-4"></div>
        <p class="text-gray-600">Chargement...</p>
      </div>
    `;
    document.body.appendChild(loader);
  }
  loader.classList.remove('hidden');
}

function hidePageLoading() {
  const loader = document.getElementById('page-loader');
  if (loader) {
    loader.classList.add('hidden');
  }
}

// ============================================
// SKELETON LOADING
// ============================================

function showSkeleton(container, rows = 3) {
  let html = '';
  for (let i = 0; i < rows; i++) {
    html += `
      <div class="flex items-center gap-4 p-4">
        <div class="skeleton w-10 h-10 rounded-full"></div>
        <div class="flex-1">
          <div class="skeleton skeleton-text w-1/3"></div>
          <div class="skeleton skeleton-text w-1/2"></div>
        </div>
      </div>
    `;
  }
  container.innerHTML = html;
}

// ============================================
// FORM VALIDATION
// ============================================

function validateForm(form) {
  const errors = [];
  const requiredFields = form.querySelectorAll('[required]');
  
  requiredFields.forEach(field => {
    field.classList.remove('border-red-500');
    
    if (!field.value.trim()) {
      field.classList.add('border-red-500');
      const label = field.closest('.form-group')?.querySelector('.form-label')?.textContent || field.name;
      errors.push(`Le champ "${label}" est requis`);
    }
  });
  
  // Validation email
  const emailFields = form.querySelectorAll('input[type="email"]');
  emailFields.forEach(field => {
    if (field.value && !isValidEmail(field.value)) {
      field.classList.add('border-red-500');
      errors.push(`L'adresse email n'est pas valide`);
    }
  });
  
  // Validation téléphone
  const telFields = form.querySelectorAll('input[type="tel"]');
  telFields.forEach(field => {
    if (field.value && !isValidPhone(field.value)) {
      field.classList.add('border-red-500');
      errors.push(`Le numéro de téléphone n'est pas valide`);
    }
  });
  
  return errors;
}

function isValidEmail(email) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function isValidPhone(phone) {
  return /^[\d\s\+\-\.\(\)]{10,}$/.test(phone.replace(/\s/g, ''));
}

function clearValidation(form) {
  form.querySelectorAll('.border-red-500').forEach(el => {
    el.classList.remove('border-red-500');
  });
}

// ============================================
// DROPDOWN MENUS
// ============================================

function initDropdowns() {
  document.addEventListener('click', (e) => {
    const dropdown = e.target.closest('[data-dropdown]');
    
    if (dropdown) {
      e.preventDefault();
      const menu = dropdown.querySelector('.dropdown-menu') || document.getElementById(dropdown.dataset.dropdown);
      
      // Close others
      document.querySelectorAll('.dropdown-menu.active').forEach(d => {
        if (d !== menu) d.classList.remove('active');
      });
      
      menu?.classList.toggle('active');
    } else {
      // Close all when clicking outside
      document.querySelectorAll('.dropdown-menu.active').forEach(d => {
        d.classList.remove('active');
      });
    }
  });
}

// ============================================
// TABS
// ============================================

function initTabs() {
  document.addEventListener('click', (e) => {
    const tab = e.target.closest('[data-tab]');
    if (!tab) return;
    
    const tabGroup = tab.closest('[data-tab-group]');
    if (!tabGroup) return;
    
    const groupName = tabGroup.dataset.tabGroup;
    const tabName = tab.dataset.tab;
    
    // Update tabs
    document.querySelectorAll(`[data-tab-group="${groupName}"] [data-tab]`).forEach(t => {
      t.classList.remove('active');
    });
    tab.classList.add('active');
    
    // Update panels
    document.querySelectorAll(`[data-tab-panel="${groupName}"]`).forEach(panel => {
      panel.classList.add('hidden');
    });
    const activePanel = document.querySelector(`[data-tab-panel="${groupName}"][data-tab-name="${tabName}"]`);
    activePanel?.classList.remove('hidden');
  });
}

// ============================================
// SIDEBAR MOBILE
// ============================================

function initSidebar() {
  const toggle = document.querySelector('.header-toggle');
  const sidebar = document.querySelector('.sidebar');
  
  if (toggle && sidebar) {
    toggle.addEventListener('click', () => {
      sidebar.classList.toggle('active');
    });
    
    // Close on outside click
    document.addEventListener('click', (e) => {
      if (!sidebar.contains(e.target) && !toggle.contains(e.target)) {
        sidebar.classList.remove('active');
      }
    });
  }
}

// ============================================
// FORMATTERS
// ============================================

function formatDate(date, options = {}) {
  const d = new Date(date);
  if (isNaN(d.getTime())) return '-';
  
  const defaultOptions = { day: '2-digit', month: 'short', year: 'numeric' };
  return d.toLocaleDateString('fr-FR', { ...defaultOptions, ...options });
}

function formatDateTime(date) {
  const d = new Date(date);
  if (isNaN(d.getTime())) return '-';
  
  return d.toLocaleString('fr-FR', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
}

function formatMoney(amount, currency = 'EUR') {
  if (amount === null || amount === undefined) return '-';
  
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: currency
  }).format(amount);
}

function formatNumber(num, decimals = 0) {
  if (num === null || num === undefined) return '-';
  
  return new Intl.NumberFormat('fr-FR', {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals
  }).format(num);
}

function formatPhone(phone) {
  if (!phone) return '-';
  
  // Format français: 06 12 34 56 78
  const cleaned = phone.replace(/\D/g, '');
  if (cleaned.length === 10) {
    return cleaned.replace(/(\d{2})(?=.)/g, '$1 ').trim();
  }
  return phone;
}

function timeAgo(date) {
  const d = new Date(date);
  const now = new Date();
  const seconds = Math.floor((now - d) / 1000);
  
  const intervals = {
    année: 31536000,
    mois: 2592000,
    semaine: 604800,
    jour: 86400,
    heure: 3600,
    minute: 60
  };
  
  for (const [unit, secondsInUnit] of Object.entries(intervals)) {
    const interval = Math.floor(seconds / secondsInUnit);
    if (interval >= 1) {
      return `il y a ${interval} ${unit}${interval > 1 ? 's' : ''}`;
    }
  }
  
  return 'à l\'instant';
}

// ============================================
// DEBOUNCE / THROTTLE
// ============================================

function debounce(func, wait) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}

function throttle(func, limit) {
  let inThrottle;
  return function executedFunction(...args) {
    if (!inThrottle) {
      func(...args);
      inThrottle = true;
      setTimeout(() => inThrottle = false, limit);
    }
  };
}

// ============================================
// INITIALIZATION
// ============================================

document.addEventListener('DOMContentLoaded', () => {
  initDropdowns();
  initTabs();
  initSidebar();
});

// Export for modules
if (typeof module !== 'undefined' && module.exports) {
  module.exports = { toast, modal, confirmDialog, formatDate, formatMoney, formatNumber, debounce, throttle };
}