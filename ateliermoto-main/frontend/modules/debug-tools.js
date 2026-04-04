window.AtelierDebug = window.AtelierDebug || {
    enabled: false,
    _installed: false,
    _events: [],
    _maxEvents: 200,

    _push: function(level, message, meta) {
        this._events.push({
            ts: new Date().toISOString(),
            level: level,
            message: message,
            meta: meta || null
        });
        if (this._events.length > this._maxEvents) {
            this._events.shift();
        }
    },

    _print: function(level, message, meta) {
        var prefix = '[AtelierDebug] ' + message;
        if (level === 'error' && console && console.error) console.error(prefix, meta || '');
        else if (level === 'warn' && console && console.warn) console.warn(prefix, meta || '');
        else if (this.enabled && console && console.log) console.log(prefix, meta || '');
    },

    setEnabled: function(value) {
        this.enabled = !!value;
        try {
            if (this.enabled) window.localStorage.setItem('atelier-debug', '1');
            else window.localStorage.removeItem('atelier-debug');
        } catch (_) {}
        this._push('info', 'debug-mode:' + (this.enabled ? 'on' : 'off'));
        this._print('info', 'Debug mode ' + (this.enabled ? 'enabled' : 'disabled'));
        return this.enabled;
    },

    enable: function() {
        return this.setEnabled(true);
    },

    disable: function() {
        return this.setEnabled(false);
    },

    log: function(message, meta) {
        this._push('info', message, meta);
        this._print('info', message, meta);
    },

    warn: function(message, meta) {
        this._push('warn', message, meta);
        this._print('warn', message, meta);
    },

    error: function(message, meta) {
        this._push('error', message, meta);
        this._print('error', message, meta);
    },

    history: function() {
        return this._events.slice();
    },

    clear: function() {
        this._events = [];
    },

    snapshotApp: function() {
        var app = window.APP || {};
        return {
            currentSection: app.currentSection || null,
            currentUser: app.currentUser || null,
            planningWeekOffset: app.planningWeekOffset || 0,
            planningSelectedAtelierSlug: app.planningSelectedAtelierSlug || null,
            adminSelectedAtelierId: app.adminSelectedAtelierId || null,
            roleSections: app.roleSections || null,
            rolePermissions: app.rolePermissions || null,
            auth: window.AUTH || null,
            lastEvents: this.history().slice(-20)
        };
    },

    traceApi: function(stage, payload) {
        var info = payload || {};
        var label = 'api:' + stage + ' ' + (info.method || 'GET') + ' ' + (info.url || '');
        if (stage === 'error' || stage === 'http-error') this.error(label, info);
        else this.log(label, info);
    },

    callModule: function(moduleName, methodName, argsLike, fallbackValue) {
        var moduleRef = window[moduleName];
        var args = Array.prototype.slice.call(argsLike || []);
        if (!moduleRef) {
            this.warn('Module introuvable: ' + moduleName, { method: methodName, args: args });
            return fallbackValue;
        }
        if (typeof moduleRef[methodName] !== 'function') {
            this.warn('Methode introuvable: ' + moduleName + '.' + methodName, { args: args });
            return fallbackValue;
        }
        try {
            return moduleRef[methodName].apply(moduleRef, args);
        } catch (err) {
            this.error('Erreur module ' + moduleName + '.' + methodName, {
                message: err && err.message ? err.message : String(err),
                stack: err && err.stack ? err.stack : null,
                args: args
            });
            throw err;
        }
    },

    install: function() {
        if (this._installed) return;
        this._installed = true;
        try {
            if (window.location.search.indexOf('debug=1') !== -1 || window.localStorage.getItem('atelier-debug') === '1') {
                this.enabled = true;
            }
        } catch (_) {}
        var debug = this;
        window.addEventListener('error', function(event) {
            debug.error('Erreur JS globale', {
                message: event.message,
                source: event.filename,
                line: event.lineno,
                column: event.colno,
                stack: event.error && event.error.stack ? event.error.stack : null
            });
        });
        window.addEventListener('unhandledrejection', function(event) {
            var reason = event.reason;
            debug.error('Promise rejetee non geree', {
                message: reason && reason.message ? reason.message : String(reason),
                stack: reason && reason.stack ? reason.stack : null
            });
        });
        this.log('Debug tools ready', { enabled: this.enabled });
    }
};

window.AtelierDebug.install();
window.enableAtelierDebug = function() { return window.AtelierDebug.enable(); };
window.disableAtelierDebug = function() { return window.AtelierDebug.disable(); };
window.debugAppState = function() { return window.AtelierDebug.snapshotApp(); };
