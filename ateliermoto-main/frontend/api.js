// api.js - couche API unifiee
if (!window.API_URL) {
    window.API_URL = window.location.origin;
}

window.AUTH = window.AUTH || {
    role: null
};

function setAuthRole(role) {
    window.AUTH.role = role || null;
}

function getAuthRole() {
    return window.AUTH.role;
}

function clearAuthState() {
    window.AUTH.role = null;
}

function apiRequest(url, options) {
    var opts = options || {};
    var method = opts.method || 'GET';
    opts.credentials = opts.credentials || 'include';
    if (window.AtelierDebug && window.AtelierDebug.traceApi) {
        window.AtelierDebug.traceApi('request', { method: method, url: url });
    }
    return fetch(window.API_URL + url, opts).then(function(response) {
        if (response.status === 401) {
            if (window.AtelierDebug && window.AtelierDebug.traceApi) {
                window.AtelierDebug.traceApi('http-error', { method: method, url: url, status: response.status, detail: 'Session expiree' });
            }
            clearAuthState();
            if (typeof showLogin === 'function') showLogin();
            throw new Error('Session expiree');
        }
        if (!response.ok) {
            return response.text().then(function(txt) {
                var detail = '';
                try {
                    var parsed = txt ? JSON.parse(txt) : null;
                    detail = parsed && parsed.detail ? String(parsed.detail) : '';
                } catch (_) {
                    detail = txt || '';
                }
                if (window.AtelierDebug && window.AtelierDebug.traceApi) {
                    window.AtelierDebug.traceApi('http-error', {
                        method: method,
                        url: url,
                        status: response.status,
                        detail: detail || response.statusText
                    });
                }
                throw new Error('HTTP ' + response.status + ': ' + (detail || response.statusText));
            });
        }
        return response;
    }).catch(function(error) {
        if (window.AtelierDebug && window.AtelierDebug.traceApi) {
            window.AtelierDebug.traceApi('error', {
                method: method,
                url: url,
                message: error && error.message ? error.message : String(error)
            });
        }
        throw error;
    });
}

function apiGet(url) {
    return apiRequest(url, {
        method: 'GET',
        headers: { 'Content-Type': 'application/json', 'X-Use-Cookie-Auth': '1' }
    });
}

function apiPost(url, data) {
    return apiRequest(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Use-Cookie-Auth': '1' },
        body: JSON.stringify(data || {})
    });
}

function apiPut(url, data) {
    return apiRequest(url, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-Use-Cookie-Auth': '1' },
        body: JSON.stringify(data || {})
    });
}

function apiPatch(url, data) {
    return apiRequest(url, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'X-Use-Cookie-Auth': '1' },
        body: JSON.stringify(data || {})
    });
}

function apiDelete(url) {
    return apiRequest(url, {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json', 'X-Use-Cookie-Auth': '1' }
    });
}
