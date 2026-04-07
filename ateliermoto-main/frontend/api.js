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
    var opts = Object.assign({}, options || {});
    var method = opts.method || 'GET';
    var timeoutMs = typeof opts.timeoutMs === 'number' ? opts.timeoutMs : 12000;
    var controller = (typeof AbortController !== 'undefined') ? new AbortController() : null;
    var timeoutId = null;

    opts.credentials = opts.credentials || 'include';
    if (controller) opts.signal = controller.signal;

    if (window.AtelierDebug && window.AtelierDebug.traceApi) {
        window.AtelierDebug.traceApi('request', { method: method, url: url });
    }

    if (controller && timeoutMs > 0) {
        timeoutId = setTimeout(function() {
            controller.abort();
        }, timeoutMs);
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
        if (error && error.name === 'AbortError') {
            error = new Error('Delai depasse pour ' + url);
        }
        if (window.AtelierDebug && window.AtelierDebug.traceApi) {
            window.AtelierDebug.traceApi('error', {
                method: method,
                url: url,
                message: error && error.message ? error.message : String(error)
            });
        }
        throw error;
    }).finally(function() {
        if (timeoutId) clearTimeout(timeoutId);
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

function openProtectedDocument(url, filename) {
    return apiRequest(url, {
        method: 'GET',
        headers: { 'X-Use-Cookie-Auth': '1' }
    }).then(function(response) {
        return response.blob().then(function(blob) {
            var pdfBlob = blob && blob.type ? blob : new Blob([blob], { type: 'application/pdf' });
            var objectUrl = URL.createObjectURL(pdfBlob);
            var popup = window.open(objectUrl, '_blank', 'noopener');

            if (!popup) {
                var link = document.createElement('a');
                link.href = objectUrl;
                link.target = '_blank';
                if (filename) link.download = filename;
                document.body.appendChild(link);
                link.click();
                link.remove();
            }

            setTimeout(function() {
                URL.revokeObjectURL(objectUrl);
            }, 60000);

            return objectUrl;
        });
    });
}
