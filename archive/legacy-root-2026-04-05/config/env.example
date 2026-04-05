// utils.js - Utilitaires partages frontend

if (!window.showToast) {
    window.showToast = function(message, type) {
        var toastType = type || 'info';
        var container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.style.cssText = 'position:fixed;top:16px;right:16px;z-index:2000;display:flex;flex-direction:column;gap:8px;max-width:360px';
            document.body.appendChild(container);
        }

        var bg = '#3B82F6';
        if (toastType === 'success') bg = '#22C55E';
        if (toastType === 'warning') bg = '#F59E0B';
        if (toastType === 'error') bg = '#EF4444';

        var toast = document.createElement('div');
        toast.style.cssText = 'background:' + bg + ';color:#fff;padding:10px 12px;border-radius:8px;font-size:13px;box-shadow:0 6px 24px rgba(0,0,0,.25)';
        toast.textContent = String(message || '');
        container.appendChild(toast);
        setTimeout(function() { toast.remove(); }, 3200);
    };
}

if (!window._nativeAlert) {
    window._nativeAlert = window.alert.bind(window);
    window.alert = function(message) {
        window.showToast(message, 'warning');
    };
}

if (!window.escapeHtml) {
    window.escapeHtml = function(str) {
        if (str === null || str === undefined) return '';
        var s = String(str);
        return s.replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    };
}

if (!window.escapeAttr) {
    window.escapeAttr = function(str) {
        return window.escapeHtml(str);
    };
}

if (!window.formatDate) {
    window.formatDate = function(dateValue) {
        if (!dateValue) return '';
        var d = new Date(dateValue);
        if (Number.isNaN(d.getTime())) return '';
        return d.toLocaleDateString('fr-FR');
    };
}
