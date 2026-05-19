// AFIT VAS API Wrapper & Auth State Management

const API_BASE = (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')
    ? 'http://localhost:8000/api'
    : 'https://user:7e7ca8c6b38a2ed9e7ba836430d4af67@f3ef192e51d9-tunnel-ljgpjyuz.devinapps.com/api';

const Auth = {
    save(token, user) {
        localStorage.setItem('vas_token', token);
        localStorage.setItem('vas_user', JSON.stringify(user));
    },
    clear() {
        localStorage.removeItem('vas_token');
        localStorage.removeItem('vas_user');
    },
    getToken() {
        return localStorage.getItem('vas_token');
    },
    getUser() {
        const u = localStorage.getItem('vas_user');
        return u ? JSON.parse(u) : null;
    },
    isLoggedIn() {
        return !!this.getToken();
    },
    requireLogin(role = null) {
        if (!this.isLoggedIn()) {
            const isAdmin = window.location.pathname.includes('/admin');
            window.location.href = isAdmin ? '/admin/login.html' : '/login.html';
            return false;
        }
        if (role) {
            const user = this.getUser();
            if (role === 'admin' && !['admin', 'super_admin'].includes(user?.role)) {
                window.location.href = '/admin/login.html';
                return false;
            }
        }
        return true;
    }
};

const Api = {
    async get(path) {
        return this._request('GET', path);
    },
    async post(path, body = {}) {
        return this._request('POST', path, body);
    },
    async put(path, body = {}) {
        return this._request('PUT', path, body);
    },
    async delete(path) {
        return this._request('DELETE', path);
    },
    async upload(path, formData) {
        const token = Auth.getToken();
        const headers = {};
        if (token) headers['Authorization'] = `Bearer ${token}`;

        const res = await fetch(`${API_BASE}${path}`, {
            method: 'POST',
            headers,
            body: formData,
        });

        if (res.status === 401) {
            Auth.clear();
            const isAdmin = window.location.pathname.includes('/admin');
            window.location.href = isAdmin ? '/admin/login.html' : '/login.html';
            return null;
        }

        return res.json();
    },
    async _request(method, path, body = null) {
        const token = Auth.getToken();
        const headers = { 'Content-Type': 'application/json' };
        if (token) headers['Authorization'] = `Bearer ${token}`;

        const opts = { method, headers };
        if (body && method !== 'GET') {
            opts.body = JSON.stringify(body);
        }

        const res = await fetch(`${API_BASE}${path}`, opts);

        if (res.status === 401) {
            Auth.clear();
            const isAdmin = window.location.pathname.includes('/admin');
            window.location.href = isAdmin ? '/admin/login.html' : '/login.html';
            return null;
        }

        // Handle CSV download
        const contentType = res.headers.get('content-type') || '';
        if (contentType.includes('text/csv')) {
            const blob = await res.blob();
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `afit_export_${new Date().toISOString().slice(0,10)}.csv`;
            a.click();
            URL.revokeObjectURL(url);
            return { success: true };
        }

        return res.json();
    }
};

// Utility functions
function showAlert(container, message, type = 'danger') {
    const el = typeof container === 'string' ? document.getElementById(container) : container;
    el.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
}

function clearAlert(container) {
    const el = typeof container === 'string' ? document.getElementById(container) : container;
    el.innerHTML = '';
}

function statusBadge(status) {
    const cls = status.replace(/_/g, '-');
    const label = status.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
    return `<span class="badge badge-${cls}">${label}</span>`;
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('en-NG', {
        year: 'numeric', month: 'short', day: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });
}

function setNavUser(name) {
    const el = document.getElementById('nav-user-name');
    if (el) el.textContent = name;
}
