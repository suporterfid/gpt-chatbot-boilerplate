// Admin UI JavaScript

// Configuration
const API_BASE = window.location.origin;
const API_ENDPOINT = `${API_BASE}/admin-api.php`;
const API_BASE_URL = API_BASE;

// State
let currentPage = 'agents';
const authState = {
    user: null,
    session: null,
    isAuthenticating: false,
    lastError: null
};

const tenantSelectionState = {
    activeTenantId: null,
    tenants: [],
    pendingRefresh: null,
    storageKey: 'gpt-admin.activeTenantId',
    loadPersistedSelection() {
        try {
            const stored = window.sessionStorage?.getItem(this.storageKey);
            if (stored === null || stored === undefined || stored === '') {
                return null;
            }
            return stored;
        } catch (error) {
            console.debug('Unable to read tenant selection from sessionStorage', error);
            return null;
        }
    },
    persistSelection(tenantId) {
        try {
            if (!tenantId) {
                window.sessionStorage?.removeItem(this.storageKey);
            } else {
                window.sessionStorage?.setItem(this.storageKey, tenantId);
            }
        } catch (error) {
            console.debug('Unable to persist tenant selection to sessionStorage', error);
        }
    },
    clear() {
        this.activeTenantId = null;
        this.tenants = [];
        this.pendingRefresh = null;
        this.persistSelection(null);
    }
};
let isLoginModalVisible = false;

const agentListState = {
    filters: {
        search: '',
        apiType: 'all',
        status: 'all'
    },
    agents: [],
    vectorStoreMap: {},
    isLoading: false,
    lastError: null
};

const usersPageState = {
    users: [],
    tenants: [],
    isLoading: false,
    lastError: null
};

const AgentSummaryComponent = (() => {
    const CHANNEL_LABELS = {
        whatsapp: 'WhatsApp'
    };

    const CHANNEL_ICONS = {
        whatsapp: '📱'
    };

    function normalizeVectorStoreIds(value) {
        if (!value) {
            return [];
        }
        if (Array.isArray(value)) {
            return value.filter(Boolean);
        }
        if (typeof value === 'string') {
            return value.split(',').map(item => item.trim()).filter(Boolean);
        }
        return [];
    }

    function resolveVectorStores(agent, options) {
        if (Array.isArray(agent.vectorStores) && agent.vectorStores.length) {
            return agent.vectorStores;
        }

        const ids = normalizeVectorStoreIds(agent.vector_store_ids);
        return ids.map(id => {
            if (options?.vectorStoreLookup) {
                const resolved = options.vectorStoreLookup(id);
                if (resolved) {
                    return resolved;
                }
            }
            if (options?.vectorStoreMap && options.vectorStoreMap[id]) {
                return options.vectorStoreMap[id];
            }
            return { openai_store_id: id, name: id };
        });
    }

    function renderChannelBadges(channels = []) {
        if (!channels || channels.length === 0) {
            return '<span class="agent-summary-empty">Nenhum canal configurado</span>';
        }

        return `<div class="agent-summary-badges">${channels.map(channel => {
            const enabled = Boolean(channel.enabled);
            const icon = CHANNEL_ICONS[channel.channel] || '🔌';
            const label = CHANNEL_LABELS[channel.channel] || channel.display_name || channel.channel || 'Canal';
            const stateClass = enabled ? 'connected' : 'disconnected';
            const stateLabel = enabled ? 'Conectado' : 'Desativado';
            return `
                <span class="channel-badge ${stateClass}" title="${stateLabel}">
                    <span class="channel-badge-dot"></span>
                    <span class="channel-badge-icon">${icon}</span>
                    <span>${escapeHtml(label)}</span>
                </span>
            `;
        }).join('')}</div>`;
    }

    function renderVectorStoreBadges(vectorStores = []) {
        if (!vectorStores || vectorStores.length === 0) {
            return '<span class="agent-summary-empty">Nenhum vector store associado</span>';
        }

        return `<div class="agent-summary-badges">${vectorStores.map(store => {
            const name = store.name || store.display_name || store.openai_store_id || 'Vector store';
            return `<span class="vector-store-badge">${escapeHtml(name)}</span>`;
        }).join('')}</div>`;
    }

    function render(agent, options = {}) {
        const channels = options.channels || agent.channels || [];
        const vectorStores = options.vectorStores || resolveVectorStores(agent, options);
        const title = options.title || 'Resumo do agente';
        const classes = ['agent-summary'];
        if (options.compact) {
            classes.push('agent-summary-compact');
        }
        if (options.layout === 'inline') {
            classes.push('agent-summary-inline');
        }

        return `
            <div class="${classes.join(' ')}">
                ${options.showTitle === false ? '' : `<div class="agent-summary-title">${title}</div>`}
                <div class="agent-summary-row">
                    <div class="agent-summary-label">Canais conectados</div>
                    <div class="agent-summary-value">${renderChannelBadges(channels)}</div>
                </div>
                <div class="agent-summary-row">
                    <div class="agent-summary-label">Vector stores</div>
                    <div class="agent-summary-value">${renderVectorStoreBadges(vectorStores)}</div>
                </div>
            </div>
        `;
    }

    return { render };
})();

window.AgentSummaryComponent = AgentSummaryComponent;

// API Client
class APIError extends Error {
    constructor(message, options = {}) {
        super(message);
        this.name = 'APIError';
        this.status = options.status;
        this.code = options.code;
        this.payload = options.payload;
    }
}

class AdminAPI {
    async request(action, options = {}) {
        const method = (options.method || 'GET').toUpperCase();
        let params = options.params || '';
        let body = options.body;

        const isSuperAdminSession = authState.user?.role === SUPER_ADMIN_ROLE;
        const shouldScopeTenant = isSuperAdminSession && hasActiveTenantSelection();
        const activeTenantId = shouldScopeTenant ? String(tenantSelectionState.activeTenantId) : null;

        if (shouldScopeTenant && activeTenantId) {
            const paramsHasTenant = typeof params === 'string' && params.includes('tenant_id=');
            let bodyHasTenant = false;

            if (body instanceof FormData) {
                bodyHasTenant = body.has('tenant_id');
            } else if (body && typeof body === 'object' && !Array.isArray(body)) {
                bodyHasTenant = Object.prototype.hasOwnProperty.call(body, 'tenant_id');
            }

            if (!paramsHasTenant && !bodyHasTenant) {
                if (method === 'GET') {
                    params += `&tenant_id=${encodeURIComponent(activeTenantId)}`;
                } else if (body instanceof FormData) {
                    body.set('tenant_id', activeTenantId);
                } else if (body && typeof body === 'object' && !Array.isArray(body)) {
                    body = { ...body, tenant_id: activeTenantId };
                } else if (body === undefined || body === null) {
                    body = { tenant_id: activeTenantId };
                }
            }
        }

        const url = `${API_ENDPOINT}?action=${action}${params}`;
        const headers = { ...(options.headers || {}) };
        const config = {
            method,
            headers,
            credentials: 'include'
        };

        if (options.signal) {
            config.signal = options.signal;
        }

        if (body !== undefined && body !== null) {
            if (body instanceof FormData) {
                config.body = body;
            } else if (method === 'GET') {
                // ignore
            } else {
                config.body = JSON.stringify(body);
                if (!config.headers['Content-Type']) {
                    config.headers['Content-Type'] = 'application/json';
                }
            }
        }

        if (!config.headers['Accept']) {
            config.headers['Accept'] = 'application/json';
        }

        try {
            const response = await fetch(url, config);
            const text = await response.text();
            let data = null;

            if (text) {
                try {
                    data = JSON.parse(text);
                } catch (parseError) {
                    throw new APIError('Invalid JSON response', {
                        status: response.status,
                        payload: text
                    });
                }
            }

            if (!response.ok) {
                const message = data?.error?.message || `Request failed with status ${response.status}`;
                const apiError = new APIError(message, {
                    status: response.status,
                    code: data?.error?.code,
                    payload: data
                });

                if (response.status === 401) {
                    handleUnauthorized(apiError);
                }

                throw apiError;
            }

            return data?.data ?? data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }

    // Authentication
    login(email, password) {
        return this.request('login', {
            method: 'POST',
            body: { email, password }
        });
    }

    logout() {
        return this.request('logout', {
            method: 'POST'
        });
    }

    currentUser() {
        return this.request('current_user');
    }

    // Agents
    listAgents() {
        return this.request('list_agents');
    }

    getAgent(id) {
        return this.request('get_agent', { params: `&id=${id}` });
    }

    createAgent(data) {
        return this.request('create_agent', { method: 'POST', body: data });
    }

    updateAgent(id, data) {
        return this.request('update_agent', { method: 'POST', params: `&id=${id}`, body: data });
    }

    deleteAgent(id) {
        return this.request('delete_agent', { method: 'POST', params: `&id=${id}` });
    }

    makeDefaultAgent(id) {
        return this.request('make_default', { method: 'POST', params: `&id=${id}` });
    }

    testAgent(id) {
        return `${API_ENDPOINT}?action=test_agent&id=${id}`;
    }

    // Prompts
    listPrompts() {
        return this.request('list_prompts');
    }

    getPrompt(id) {
        return this.request('get_prompt', { params: `&id=${id}` });
    }

    createPrompt(data) {
        return this.request('create_prompt', { method: 'POST', body: data });
    }

    updatePrompt(id, data) {
        return this.request('update_prompt', { method: 'POST', params: `&id=${id}`, body: data });
    }

    deletePrompt(id) {
        return this.request('delete_prompt', { method: 'POST', params: `&id=${id}` });
    }

    listPromptVersions(id) {
        return this.request('prompt_builder_list', { params: `&agent_id=${id}` });
    }

    createPromptVersion(id, data) {
        return this.request('create_prompt_version', { method: 'POST', params: `&id=${id}`, body: data });
    }

    syncPrompts() {
        return this.request('sync_prompts', { method: 'POST' });
    }

    // Vector Stores
    listVectorStores() {
        return this.request('list_vector_stores');
    }

    getVectorStore(id) {
        return this.request('get_vector_store', { params: `&id=${id}` });
    }

    createVectorStore(data) {
        return this.request('create_vector_store', { method: 'POST', body: data });
    }

    updateVectorStore(id, data) {
        return this.request('update_vector_store', { method: 'POST', params: `&id=${id}`, body: data });
    }

    deleteVectorStore(id) {
        return this.request('delete_vector_store', { method: 'POST', params: `&id=${id}` });
    }

    listVectorStoreFiles(id) {
        return this.request('list_vector_store_files', { params: `&id=${id}` });
    }

    addVectorStoreFile(id, data) {
        return this.request('add_vector_store_file', { method: 'POST', params: `&id=${id}`, body: data });
    }

    deleteVectorStoreFile(storeId, fileId) {
        return this.request('delete_vector_store_file', { method: 'POST', params: `&id=${storeId}&file_id=${fileId}` });
    }

    pollFileStatus(fileId) {
        return this.request('poll_file_status', { params: `&file_id=${fileId}` });
    }

    syncVectorStores() {
        return this.request('sync_vector_stores', { method: 'POST' });
    }

    // Jobs
    listJobs(status = null, limit = 50) {
        const params = status ? `&status=${status}&limit=${limit}` : `&limit=${limit}`;
        return this.request('list_jobs', { params });
    }

    getJob(id) {
        return this.request('get_job', { params: `&id=${id}` });
    }

    retryJob(id) {
        return this.request('retry_job', { method: 'POST', params: `&id=${id}` });
    }

    cancelJob(id) {
        return this.request('cancel_job', { method: 'POST', params: `&id=${id}` });
    }

    jobStats() {
        return this.request('job_stats');
    }

    // Audit Log
    listAuditLog(limit = 100) {
        return this.request('list_audit_log', { params: `&limit=${limit}` });
    }

    listAuditConversations(params = {}) {
        const searchParams = new URLSearchParams(params);
        const queryString = searchParams.toString();
        return this.request('list_audit_conversations', { params: queryString ? `&${queryString}` : '' });
    }

    getAuditConversation(conversationId, decrypt = false) {
        if (!conversationId) {
            throw new Error('conversationId is required');
        }

        const searchParams = new URLSearchParams({
            conversation_id: conversationId,
            decrypt: decrypt ? 'true' : 'false'
        });

        return this.request('get_audit_conversation', { params: `&${searchParams.toString()}` });
    }

    exportAuditData(params = {}) {
        const searchParams = new URLSearchParams(params);
        const token = getStoredToken();

        if (token) {
            searchParams.set('token', token);
        }

        const query = searchParams.toString();
        const url = `${API_ENDPOINT}?action=export_audit_data${query ? '&' + query : ''}`;

        window.open(url, '_blank');
    }

    // Utility
    health() {
        return this.request('health');
    }

    listModels() {
        return this.request('list_models');
    }

    // Channels
    listAgentChannels(agentId) {
        return this.request('list_agent_channels', { params: `&agent_id=${agentId}` });
    }

    getAgentChannel(agentId, channel) {
        return this.request('get_agent_channel', { params: `&agent_id=${agentId}&channel=${channel}` });
    }

    upsertAgentChannel(agentId, channel, data) {
        return this.request('upsert_agent_channel', { method: 'POST', params: `&agent_id=${agentId}&channel=${channel}`, body: data });
    }

    deleteAgentChannel(agentId, channel) {
        return this.request('delete_agent_channel', { method: 'POST', params: `&agent_id=${agentId}&channel=${channel}` });
    }

    testChannelSend(agentId, channel, data) {
        return this.request('test_channel_send', { method: 'POST', params: `&agent_id=${agentId}&channel=${channel}`, body: data });
    }

    listChannelSessions(agentId, channel = null, limit = 50, offset = 0) {
        let params = `&agent_id=${agentId}&limit=${limit}&offset=${offset}`;
        if (channel) params += `&channel=${channel}`;
        return this.request('list_channel_sessions', { params });
    }

    // Tenants
    listTenants(filters = {}) {
        let params = '';
        if (filters.status) params += `&status=${filters.status}`;
        if (filters.search) params += `&search=${encodeURIComponent(filters.search)}`;
        return this.request('list_tenants', { params });
    }
    
    getTenant(id) {
        return this.request('get_tenant', { params: `&id=${id}` });
    }
    
    createTenant(data) {
        return this.request('create_tenant', { method: 'POST', body: data });
    }
    
    updateTenant(id, data) {
        return this.request('update_tenant', { method: 'POST', params: `&id=${id}`, body: data });
    }
    
    deleteTenant(id) {
        return this.request('delete_tenant', { method: 'POST', params: `&id=${id}` });
    }
    
    suspendTenant(id) {
        return this.request('suspend_tenant', { method: 'POST', params: `&id=${id}` });
    }
    
    activateTenant(id) {
        return this.request('activate_tenant', { method: 'POST', params: `&id=${id}` });
    }
    
    getTenantStats(id) {
        return this.request('get_tenant_stats', { params: `&id=${id}` });
    }

    // Users (RBAC)
    listUsers() {
        return this.request('list_users');
    }

    createUser(data) {
        return this.request('create_user', { method: 'POST', body: data });
    }

    updateUserRole(id, role) {
        return this.request('update_user_role', { method: 'POST', params: `&id=${id}`, body: { role } });
    }

    deactivateUser(id) {
        return this.request('deactivate_user', { method: 'POST', params: `&id=${id}` });
    }
    
    // Billing & Usage
    getUsageStats(filters = {}) {
        let params = '';
        if (filters.start_date) params += `&start_date=${filters.start_date}`;
        if (filters.end_date) params += `&end_date=${filters.end_date}`;
        if (filters.resource_type) params += `&resource_type=${filters.resource_type}`;
        return this.request('get_usage_stats', { params });
    }

    getUsageTimeSeries(filters = {}) {
        let params = '';
        if (filters.start_date) params += `&start_date=${filters.start_date}`;
        if (filters.end_date) params += `&end_date=${filters.end_date}`;
        if (filters.resource_type) params += `&resource_type=${filters.resource_type}`;
        if (filters.interval) params += `&interval=${filters.interval}`;
        return this.request('get_usage_timeseries', { params });
    }

    listQuotas() {
        return this.request('list_quotas');
    }

    getQuotaStatus() {
        return this.request('get_quota_status');
    }
    
    setQuota(data) {
        return this.request('set_quota', { method: 'POST', body: data });
    }
    
    deleteQuota(id) {
        return this.request('delete_quota', { method: 'POST', params: `&id=${id}` });
    }
    
    getSubscription() {
        return this.request('get_subscription');
    }

    createSubscription(data) {
        return this.request('create_subscription', { method: 'POST', body: data });
    }

    updateSubscription(data) {
        return this.request('update_subscription', { method: 'POST', body: data });
    }

    cancelSubscription({ tenantId = null, immediately = false } = {}) {
        const body = { immediately };
        if (tenantId) {
            body.tenant_id = tenantId;
        }
        return this.request('cancel_subscription', { method: 'POST', body });
    }

    listInvoices(filters = {}) {
        let params = '';
        if (filters.status) params += `&status=${filters.status}`;
        if (filters.limit) params += `&limit=${filters.limit}`;
        if (filters.offset) params += `&offset=${filters.offset}`;
        return this.request('list_invoices', { params });
    }
    
    getInvoice(id) {
        return this.request('get_invoice', { params: `&id=${id}` });
    }
    
    createInvoice(data) {
        return this.request('create_invoice', { method: 'POST', body: data });
    }
    
    updateInvoice(id, data) {
        return this.request('update_invoice', { method: 'POST', params: `&id=${id}`, body: data });
    }
    
    listNotifications(filters = {}) {
        let params = '';
        if (filters.type) params += `&type=${filters.type}`;
        if (filters.status) params += `&status=${filters.status}`;
        if (filters.unread_only) params += `&unread_only=${filters.unread_only}`;
        if (filters.limit) params += `&limit=${filters.limit}`;
        if (filters.offset) params += `&offset=${filters.offset}`;
        return this.request('list_notifications', { params });
    }
    
    markNotificationRead(id) {
        return this.request('mark_notification_read', { method: 'POST', params: `&id=${id}` });
    }
    
    getUnreadCount() {
        return this.request('get_unread_count');
    }
}

// Expose AdminAPI to window for extension by other modules
window.AdminAPI = AdminAPI;

let api = new AdminAPI();

// UI Helpers
function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;

    const icon = type === 'success' ? '✓' : type === 'error' ? '✗' : 'ℹ';
    
    toast.innerHTML = `
        <div class="toast-icon">${icon}</div>
        <div class="toast-content">
            <div class="toast-message">${message}</div>
        </div>
    `;
    
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

const confirmationDialogState = {
    overlay: null,
    confirmButton: null,
    cancelButton: null,
    titleElement: null,
    messageElement: null,
    iconElement: null,
    resolve: null,
    keydownHandler: null,
    previouslyFocusedElement: null
};

function ensureConfirmationDialog() {
    if (confirmationDialogState.overlay) {
        return confirmationDialogState.overlay;
    }

    const dialogHTML = `
        <div
            id="admin-confirmation-overlay"
            class="modal modal-overlay confirmation-overlay"
            role="dialog"
            aria-modal="true"
            aria-labelledby="admin-confirmation-title"
            aria-describedby="admin-confirmation-message"
            aria-hidden="true"
        >
            <div class="modal-content confirmation-dialog" role="document">
                <div class="confirmation-header">
                    <div class="confirmation-icon" aria-hidden="true"></div>
                    <h3 id="admin-confirmation-title"></h3>
                </div>
                <p id="admin-confirmation-message"></p>
                <div class="confirmation-actions">
                    <button type="button" class="btn btn-outline" data-confirmation="cancel">Cancel</button>
                    <button type="button" class="btn btn-primary" data-confirmation="confirm">Confirm</button>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', dialogHTML);

    const overlay = document.getElementById('admin-confirmation-overlay');
    confirmationDialogState.overlay = overlay;
    confirmationDialogState.confirmButton = overlay.querySelector('[data-confirmation="confirm"]');
    confirmationDialogState.cancelButton = overlay.querySelector('[data-confirmation="cancel"]');
    confirmationDialogState.titleElement = document.getElementById('admin-confirmation-title');
    confirmationDialogState.messageElement = document.getElementById('admin-confirmation-message');
    confirmationDialogState.iconElement = overlay.querySelector('.confirmation-icon');

    return overlay;
}

function hideConfirmationDialog(result = false) {
    const {
        overlay,
        confirmButton,
        cancelButton,
        keydownHandler,
        resolve,
        previouslyFocusedElement
    } = confirmationDialogState;

    if (!overlay) return;

    overlay.classList.remove('open');
    overlay.setAttribute('aria-hidden', 'true');
    overlay.removeEventListener('click', handleOverlayClick);

    if (confirmButton) {
        confirmButton.removeEventListener('click', handleConfirmClick);
    }

    if (cancelButton) {
        cancelButton.removeEventListener('click', handleCancelClick);
    }

    if (keydownHandler) {
        overlay.removeEventListener('keydown', keydownHandler);
    }

    document.body.classList.remove('modal-overlay-open');

    if (typeof resolve === 'function') {
        resolve(result);
    }

    confirmationDialogState.resolve = null;
    confirmationDialogState.keydownHandler = null;

    if (previouslyFocusedElement && typeof previouslyFocusedElement.focus === 'function') {
        previouslyFocusedElement.focus();
    }

    confirmationDialogState.previouslyFocusedElement = null;
}

function handleConfirmClick(event) {
    event.preventDefault();
    hideConfirmationDialog(true);
}

function handleCancelClick(event) {
    event.preventDefault();
    hideConfirmationDialog(false);
}

function handleOverlayClick(event) {
    if (event.target === confirmationDialogState.overlay) {
        hideConfirmationDialog(false);
    }
}

function showConfirmationDialog(options = {}) {
    const overlay = ensureConfirmationDialog();

    const {
        title = 'Confirm action',
        message = 'Are you sure you want to continue?',
        confirmLabel = 'Confirm',
        cancelLabel = 'Cancel',
        tone = 'primary'
    } = options;

    const { confirmButton, cancelButton, titleElement, messageElement, iconElement } = confirmationDialogState;

    if (titleElement) {
        titleElement.textContent = title;
    }

    if (messageElement) {
        messageElement.textContent = message;
    }

    if (confirmButton) {
        confirmButton.textContent = confirmLabel;
        confirmButton.classList.remove('btn-primary', 'btn-danger', 'btn-success');
        const toneClass = tone === 'danger' ? 'btn-danger' : tone === 'success' ? 'btn-success' : 'btn-primary';
        confirmButton.classList.add(toneClass);
    }

    if (cancelButton) {
        cancelButton.textContent = cancelLabel;
    }

    if (iconElement) {
        iconElement.classList.remove('is-danger', 'is-success', 'is-primary');
        const toneClass = tone === 'danger' ? 'is-danger' : tone === 'success' ? 'is-success' : 'is-primary';
        iconElement.classList.add(toneClass);
        iconElement.innerHTML = tone === 'danger' ? '⚠️' : tone === 'success' ? '✅' : 'ℹ️';
    }

    return new Promise(resolve => {
        confirmationDialogState.resolve = resolve;
        confirmationDialogState.previouslyFocusedElement = document.activeElement instanceof HTMLElement
            ? document.activeElement
            : null;

        overlay.classList.add('open');
        overlay.setAttribute('aria-hidden', 'false');
        overlay.addEventListener('click', handleOverlayClick);
        document.body.classList.add('modal-overlay-open');

        if (confirmButton) {
            confirmButton.addEventListener('click', handleConfirmClick);
        }

        if (cancelButton) {
            cancelButton.addEventListener('click', handleCancelClick);
        }

        const keydownHandler = event => {
            if (event.key === 'Escape') {
                event.preventDefault();
                hideConfirmationDialog(false);
            } else if (event.key === 'Tab') {
                const focusableElements = [cancelButton, confirmButton].filter(Boolean);
                if (focusableElements.length === 0) {
                    return;
                }

                const currentIndex = focusableElements.indexOf(document.activeElement);
                if (event.shiftKey) {
                    if (currentIndex <= 0) {
                        event.preventDefault();
                        focusableElements[focusableElements.length - 1].focus();
                    }
                } else if (currentIndex === focusableElements.length - 1) {
                    event.preventDefault();
                    focusableElements[0].focus();
                }
            }
        };

        overlay.addEventListener('keydown', keydownHandler);
        confirmationDialogState.keydownHandler = keydownHandler;

        requestAnimationFrame(() => {
            if (confirmButton) {
                confirmButton.focus();
            }
        });
    });
}

window.showConfirmationDialog = showConfirmationDialog;

function openModal(title, content) {
    const modal = document.getElementById('modal');
    document.getElementById('modal-title').textContent = title;
    document.getElementById('modal-body').innerHTML = content;
    modal.style.display = 'flex';
}

// Alias for openModal
function showModal(title, content) {
    openModal(title, content);
}

function closeModal() {
    document.getElementById('modal').style.display = 'none';
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
}

function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.toString().replace(/[&<>"']/g, m => map[m]);
}

const SUPER_ADMIN_ROLE = 'super-admin';
const TENANT_SCOPED_PAGES = new Set([
    'agents',
    'prompts',
    'vector-stores',
    'whatsapp-templates',
    'consent-management',
    'jobs',
    'billing',
    'audit',
    'audit-conversations'
]);

function updateStatusIndicator(message, state = 'online') {
    const indicator = document.getElementById('status-indicator');
    const statusText = document.getElementById('status-text');

    if (!indicator || !statusText) {
        return;
    }

    indicator.classList.remove('error', 'offline', 'pending');

    if (state === 'error') {
        indicator.classList.add('error');
    } else if (state === 'offline') {
        indicator.classList.add('offline');
    } else if (state === 'pending') {
        indicator.classList.add('pending');
    }

    statusText.textContent = message;
}

function updateRoleVisibility(role) {
    document.body.classList.toggle('is-super-admin', role === SUPER_ADMIN_ROLE);

    const superAdminLinks = document.querySelectorAll('[data-super-admin-only="true"]');
    superAdminLinks.forEach(link => {
        if (role === SUPER_ADMIN_ROLE) {
            link.classList.remove('is-hidden');
        } else {
            link.classList.add('is-hidden');
        }
    });

    updateTenantNavState();
    renderTenantSelector();
}

function requiresTenantSelection(page) {
    return TENANT_SCOPED_PAGES.has(page);
}

function hasActiveTenantSelection() {
    const id = tenantSelectionState.activeTenantId;
    return id !== null && id !== undefined && id !== '';
}

function getActiveTenantDisplayName() {
    if (!authState.user) {
        return '—';
    }

    if (authState.user.role === SUPER_ADMIN_ROLE) {
        if (!hasActiveTenantSelection()) {
            return 'All tenants';
        }

        const activeTenantId = String(tenantSelectionState.activeTenantId);
        const tenants = tenantSelectionState.tenants || [];
        const match = tenants.find(tenant => tenant && String(tenant.id) === activeTenantId);
        if (match) {
            return getTenantDisplayName(match);
        }

        return activeTenantId;
    }

    return authState.user.tenant?.name
        || authState.user.tenant_name
        || authState.user.tenant_slug
        || authState.user.tenant_id
        || '—';
}

function updateTenantDisplay() {
    const tenantValueElement = document.getElementById('current-tenant-value');
    if (tenantValueElement) {
        tenantValueElement.textContent = getActiveTenantDisplayName();
    }
}

function updateTenantNavState() {
    const navLinks = document.querySelectorAll('.nav-link[data-page]');
    const isSuperAdmin = authState.user?.role === SUPER_ADMIN_ROLE;
    const hasTenantSelected = hasActiveTenantSelection();

    navLinks.forEach(link => {
        const page = link.dataset.page;
        const shouldDisable = Boolean(
            isSuperAdmin && requiresTenantSelection(page) && !hasTenantSelected
        );

        link.classList.toggle('is-disabled', shouldDisable);

        if (shouldDisable) {
            link.setAttribute('aria-disabled', 'true');
        } else {
            link.removeAttribute('aria-disabled');
        }
    });
}

function renderTenantSelector() {
    const wrapper = document.getElementById('tenant-selector-wrapper');
    const select = document.getElementById('tenant-selector');

    if (!wrapper || !select) {
        return;
    }

    if (!select.dataset.bound) {
        select.addEventListener('change', handleTenantSelectorChange);
        select.dataset.bound = 'true';
    }

    const tenants = tenantSelectionState.tenants || [];
    const hasSelection = hasActiveTenantSelection();
    const activeTenantId = hasSelection ? String(tenantSelectionState.activeTenantId) : '';

    const tenantOptions = tenants
        .filter(tenant => tenant && tenant.id)
        .map(tenant => `<option value="${String(tenant.id)}">${escapeHtml(getTenantDisplayName(tenant))}</option>`);

    const options = ['<option value="">All tenants</option>', ...tenantOptions].join('');

    select.innerHTML = options;
    select.value = hasSelection ? activeTenantId : '';

    wrapper.classList.toggle('has-selection', hasSelection);
}

function handleTenantSelectorChange(event) {
    const previousSelection = tenantSelectionState.activeTenantId;
    const rawValue = event.target.value;
    const nextSelection = rawValue ? rawValue : null;

    tenantSelectionState.activeTenantId = nextSelection;
    tenantSelectionState.persistSelection(nextSelection);

    renderTenantSelector();
    updateTenantDisplay();
    updateTenantNavState();

    if (previousSelection !== nextSelection) {
        loadCurrentPage();
    }
}

async function refreshTenantOptions({ silent = false } = {}) {
    if (authState.user?.role !== SUPER_ADMIN_ROLE) {
        tenantSelectionState.tenants = [];
        tenantSelectionState.pendingRefresh = null;
        renderTenantSelector();
        updateTenantDisplay();
        updateTenantNavState();
        return [];
    }

    if (tenantSelectionState.pendingRefresh) {
        return tenantSelectionState.pendingRefresh;
    }

    const refreshPromise = (async () => {
        try {
            const tenants = await api.listTenants();
            tenantSelectionState.tenants = Array.isArray(tenants) ? tenants : [];

            const hasSelection = hasActiveTenantSelection();
            const selectedId = hasSelection ? String(tenantSelectionState.activeTenantId) : null;
            const selectionAvailable = tenantSelectionState.tenants.some(
                tenant => tenant && hasSelection && String(tenant.id) === selectedId
            );

            if (hasSelection && !selectionAvailable) {
                tenantSelectionState.activeTenantId = null;
                tenantSelectionState.persistSelection(null);
                if (!silent) {
                    showToast('Previously selected tenant is no longer available. Showing all tenants.', 'warning');
                }
                if (requiresTenantSelection(currentPage)) {
                    loadCurrentPage();
                }
            }

            renderTenantSelector();
            updateTenantDisplay();
            updateTenantNavState();

            return tenantSelectionState.tenants;
        } catch (error) {
            if (!silent) {
                showToast('Failed to load tenants: ' + error.message, 'error');
            }
            throw error;
        } finally {
            if (tenantSelectionState.pendingRefresh === refreshPromise) {
                tenantSelectionState.pendingRefresh = null;
            }
        }
    })();

    tenantSelectionState.pendingRefresh = refreshPromise;
    return refreshPromise;
}

function setAuthenticatedUser(user, session = null) {
    authState.user = user || null;
    authState.session = session || null;

    const emailElement = document.getElementById('current-user-email');

    if (user) {
        document.body.classList.add('is-authenticated');
        if (emailElement) {
            emailElement.textContent = user.email || 'Unknown user';
        }
        updateStatusIndicator('Connected', 'online');
        const isSuperAdmin = user.role === SUPER_ADMIN_ROLE;

        if (isSuperAdmin) {
            tenantSelectionState.activeTenantId = tenantSelectionState.loadPersistedSelection();
            tenantSelectionState.tenants = [];
        } else {
            tenantSelectionState.activeTenantId = user.tenant_id !== undefined && user.tenant_id !== null
                ? String(user.tenant_id)
                : null;
            tenantSelectionState.tenants = [];
            tenantSelectionState.persistSelection(tenantSelectionState.activeTenantId);
        }

        updateRoleVisibility(user.role);
        updateTenantDisplay();

        if (isSuperAdmin) {
            refreshTenantOptions({ silent: true }).catch(error => {
                console.error('Failed to refresh tenant options', error);
            });
        }
    } else {
        document.body.classList.remove('is-authenticated');
        if (emailElement) {
            emailElement.textContent = 'Guest';
        }
        updateStatusIndicator('Sign in required', 'offline');
        tenantSelectionState.clear();
        updateRoleVisibility(null);
        updateTenantDisplay();
    }
}

function handleUnauthorized(error) {
    const hadUser = Boolean(authState.user);
    authState.lastError = error;
    setAuthenticatedUser(null, null);

    if (hadUser) {
        showToast('Your session has expired. Please sign in again.', 'warning');
    }

    if (!isLoginModalVisible) {
        const message = error?.status === 401 ? '' : (error?.message || 'Authentication required');
        showLoginModal(message);
    }
}

function showLoginModal(message = '') {
    const modal = document.getElementById('login-modal');
    const form = document.getElementById('login-form');
    const emailInput = document.getElementById('login-email');
    const passwordInput = document.getElementById('login-password');
    const errorElement = document.getElementById('login-error');

    if (!modal) {
        return;
    }

    if (form && !isLoginModalVisible) {
        form.reset();
    }

    if (errorElement) {
        errorElement.textContent = message;
    }

    modal.style.display = 'flex';
    isLoginModalVisible = true;

    requestAnimationFrame(() => {
        if (emailInput && !emailInput.value) {
            emailInput.focus();
        } else if (passwordInput) {
            passwordInput.focus();
        }
    });
}

function hideLoginModal() {
    const modal = document.getElementById('login-modal');
    const errorElement = document.getElementById('login-error');

    if (modal) {
        modal.style.display = 'none';
    }

    if (errorElement) {
        errorElement.textContent = '';
    }

    isLoginModalVisible = false;
}

async function refreshCurrentUser({ silent = false } = {}) {
    authState.isAuthenticating = true;

    if (!silent) {
        updateStatusIndicator('Checking session...', 'pending');
    }

    try {
        const data = await api.currentUser();
        setAuthenticatedUser(data?.user || null, data?.session || null);
        return authState.user;
    } catch (error) {
        if (error instanceof APIError && error.status === 401) {
            setAuthenticatedUser(null, null);
            if (!silent) {
                showLoginModal();
            }
        } else {
            updateStatusIndicator('Connection error', 'error');
            authState.lastError = error;
            if (!silent) {
                showToast('Failed to validate session: ' + error.message, 'error');
            }
        }
        throw error;
    } finally {
        authState.isAuthenticating = false;
    }
}

async function handleLoginSubmit(event) {
    if (event) {
        event.preventDefault();
    }

    const form = document.getElementById('login-form');
    if (!form) {
        return;
    }

    const formData = new FormData(form);
    const email = (formData.get('email') || '').toString().trim();
    const password = (formData.get('password') || '').toString();
    const errorElement = document.getElementById('login-error');
    const submitButton = document.getElementById('login-submit');

    if (!email || !password) {
        if (errorElement) {
            errorElement.textContent = 'Email and password are required.';
        }
        return;
    }

    if (errorElement) {
        errorElement.textContent = '';
    }

    if (submitButton) {
        submitButton.disabled = true;
    }

    updateStatusIndicator('Signing in...', 'pending');

    try {
        const data = await api.login(email, password);
        setAuthenticatedUser(data?.user || null, data?.session || null);
        hideLoginModal();
        showToast('Welcome back!', 'success');

        const initialPage = window.location.hash.substring(1) || 'agents';
        navigateTo(initialPage);
        loadCurrentPage();
    } catch (error) {
        if (error instanceof APIError && error.status === 401) {
            if (errorElement) {
                errorElement.textContent = 'Invalid email or password.';
            }
        } else if (errorElement) {
            errorElement.textContent = error.message || 'Unable to sign in.';
        }

        updateStatusIndicator('Sign in required', 'offline');
    } finally {
        if (submitButton) {
            submitButton.disabled = false;
        }
    }
}

async function handleLogout(event) {
    if (event) {
        event.preventDefault();
    }

    try {
        await api.logout();
    } catch (error) {
        console.warn('Logout request failed', error);
    }

    window.location.hash = '#agents';
    currentPage = 'agents';
    setAuthenticatedUser(null, null);
    showLoginModal();
    showToast('You have been signed out.', 'info');
}

async function initializeAuthentication() {
    updateStatusIndicator('Checking session...', 'pending');

    try {
        const user = await refreshCurrentUser({ silent: true });
        if (user) {
            if (user.role === SUPER_ADMIN_ROLE) {
                try {
                    await refreshTenantOptions({ silent: true });
                } catch (error) {
                    console.warn('Failed to preload tenant options', error);
                }
            }
            const initialPage = window.location.hash.substring(1) || 'agents';
            navigateTo(initialPage);
            loadCurrentPage();
        } else {
            showLoginModal();
        }
    } catch (error) {
        if (!(error instanceof APIError && error.status === 401)) {
            console.error('Failed to bootstrap authentication', error);
        }
        showLoginModal();
    }
}

function authFetch(url, options = {}) {
    const headers = {
        Accept: 'application/json',
        ...(options.headers || {})
    };

    return fetch(url, {
        ...options,
        headers,
        credentials: 'include'
    }).then(response => {
        if (response.status === 401) {
            handleUnauthorized(new APIError('Authentication required', { status: 401 }));
        }
        return response;
    });
}

// Page Management
function navigateTo(page) {
    if (page === 'logout') {
        handleLogout();
        return;
    }

    if (!authState.user) {
        showLoginModal();
        return;
    }

    if (page === 'users' && authState.user.role !== SUPER_ADMIN_ROLE) {
        showToast('Only super-admins can access Users.', 'warning');
        page = 'agents';
    }

    if (
        authState.user.role === SUPER_ADMIN_ROLE &&
        requiresTenantSelection(page) &&
        !hasActiveTenantSelection()
    ) {
        showToast('Select a tenant to access this section.', 'warning');
        return;
    }

    if (currentPage === 'jobs' && jobsRefreshInterval) {
        clearInterval(jobsRefreshInterval);
        jobsRefreshInterval = null;
    }

    currentPage = page;
    if (window.location.hash !== `#${page}`) {
        window.location.hash = `#${page}`;
    }

    document.querySelectorAll('.nav-link[data-page]').forEach(link => {
        link.classList.toggle('active', link.dataset.page === page);
    });

    const titles = {
        'agents': 'Agents',
        'prompts': 'Prompts',
        'vector-stores': 'Vector Stores',
        'whatsapp-templates': 'WhatsApp Templates',
        'consent-management': 'Consent Management',
        'jobs': 'Background Jobs',
        'tenants': 'Tenants',
        'users': 'Users',
        'billing': 'Billing & Usage',
        'audit': 'Audit Log',
        'audit-conversations': 'Audit Trails',
        'settings': 'Settings'
    };

    const pageTitle = document.getElementById('page-title');
    if (pageTitle) {
        pageTitle.textContent = titles[page] || page;
    }

    loadCurrentPage();
}

function loadCurrentPage() {
    if (!authState.user) {
        return;
    }

    const content = document.getElementById('content');

    if (
        content &&
        authState.user.role === SUPER_ADMIN_ROLE &&
        requiresTenantSelection(currentPage) &&
        !hasActiveTenantSelection()
    ) {
        content.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">🏢</div>
                <div class="empty-state-text">Select a tenant from the header to view this section.</div>
            </div>
        `;
        return;
    }

    const pages = {
        'agents': loadAgentsPage,
        'prompts': loadPromptsPage,
        'vector-stores': loadVectorStoresPage,
        'whatsapp-templates': loadWhatsAppTemplatesPage,
        'consent-management': loadConsentManagementPage,
        'leadsense-crm': loadLeadSenseCRMPage,
        'jobs': loadJobsPage,
        'webhook-testing': loadWebhookTestingPage,
        'tenants': loadTenantsPage,
        'users': loadUsersPage,
        'billing': loadBillingPage,
        'audit': loadAuditPage,
        'audit-conversations': loadAuditConversationsPage,
        'settings': loadSettingsPage
    };

    const loader = pages[currentPage];
    if (typeof loader === 'function') {
        loader();
    }
}

// ==================== Agents Page ====================

async function loadAgentsPage() {
    agentListState.isLoading = true;
    agentListState.lastError = null;
    renderAgentsList();

    try {
        const agentsPromise = api.listAgents();
        const vectorStoresPromise = api.listVectorStores().catch(error => {
            console.warn('Failed to load vector stores', error);
            return [];
        });

        const agents = await agentsPromise;

        const [vectorStoresRaw, channelsByAgent] = await Promise.all([
            vectorStoresPromise,
            fetchAgentChannelsInBatches(agents).catch(error => {
                console.warn('Failed to load agent channels in batch', error);
                return {};
            })
        ]);

        const vectorStoreMap = buildVectorStoreMap(vectorStoresRaw);

        const enrichedAgents = agents.map(agent => {
            const channels = channelsByAgent[agent.id] || [];
            const vectorStores = resolveAgentVectorStores(agent, vectorStoreMap);
            return {
                ...agent,
                channels,
                vectorStores
            };
        });

        agentListState.agents = enrichedAgents;
        agentListState.vectorStoreMap = vectorStoreMap;
    } catch (error) {
        agentListState.lastError = error;
        showToast('Failed to load agents: ' + error.message, 'error');
    } finally {
        agentListState.isLoading = false;
        renderAgentsList();
    }
}

async function fetchAgentChannelsInBatches(agents = [], batchSize = 5) {
    if (!Array.isArray(agents) || agents.length === 0) {
        return {};
    }

    const result = {};
    const safeBatchSize = Math.max(1, Number(batchSize) || 5);

    for (let index = 0; index < agents.length; index += safeBatchSize) {
        const batch = agents.slice(index, index + safeBatchSize).filter(agent => agent && agent.id);
        if (batch.length === 0) {
            continue;
        }

        const responses = await Promise.allSettled(batch.map(agent => api.listAgentChannels(agent.id)));
        responses.forEach((response, offset) => {
            const agentId = batch[offset]?.id;
            if (!agentId) {
                return;
            }

            if (response.status === 'fulfilled') {
                result[agentId] = Array.isArray(response.value) ? response.value : [];
            } else {
                console.warn('Failed to load channels for agent', agentId, response.reason);
                result[agentId] = [];
            }
        });
    }

    return result;
}

function buildVectorStoreMap(vectorStoresRaw = []) {
    if (!Array.isArray(vectorStoresRaw)) {
        return {};
    }

    return vectorStoresRaw.reduce((acc, store) => {
        if (store && store.openai_store_id) {
            acc[store.openai_store_id] = store;
        }
        return acc;
    }, {});
}

function resolveAgentVectorStores(agent, vectorStoreMap = {}) {
    if (!agent) {
        return [];
    }

    if (Array.isArray(agent.vectorStores) && agent.vectorStores.length > 0) {
        return agent.vectorStores;
    }

    const ids = Array.isArray(agent.vector_store_ids)
        ? agent.vector_store_ids
        : typeof agent.vector_store_ids === 'string'
            ? agent.vector_store_ids.split(',').map(item => item.trim()).filter(Boolean)
            : [];

    return ids.map(id => vectorStoreMap[id] || { openai_store_id: id, name: id });
}

function renderAgentsList() {
    const content = document.getElementById('content');
    if (!content) {
        return;
    }

    const filters = agentListState.filters;

    const filterControls = `
        <div class="agent-filters">
            <label class="filter-field">
                <span>Buscar</span>
                <input type="text" class="form-input" placeholder="Nome, descrição ou prompt" value="${escapeHtml(filters.search)}" oninput="handleAgentFilterChange('search', this.value)" />
            </label>
            <label class="filter-field">
                <span>Tipo de API</span>
                <select class="form-select" onchange="handleAgentFilterChange('apiType', this.value)">
                    <option value="all" ${filters.apiType === 'all' ? 'selected' : ''}>Todos</option>
                    <option value="responses" ${filters.apiType === 'responses' ? 'selected' : ''}>Responses API</option>
                    <option value="chat" ${filters.apiType === 'chat' ? 'selected' : ''}>Chat Completions</option>
                </select>
            </label>
            <label class="filter-field">
                <span>Status</span>
                <select class="form-select" onchange="handleAgentFilterChange('status', this.value)">
                    <option value="all" ${filters.status === 'all' ? 'selected' : ''}>Todos</option>
                    <option value="ready" ${filters.status === 'ready' ? 'selected' : ''}>Pronto</option>
                    <option value="needs-channel" ${filters.status === 'needs-channel' ? 'selected' : ''}>Sem canal</option>
                    <option value="draft" ${filters.status === 'draft' ? 'selected' : ''}>Rascunho</option>
                    <option value="default" ${filters.status === 'default' ? 'selected' : ''}>Padrão</option>
                </select>
            </label>
        </div>
    `;

    let bodyHtml = '';

    if (agentListState.isLoading) {
        bodyHtml = '<div class="card-loading"><div class="spinner"></div></div>';
    } else if (agentListState.lastError) {
        bodyHtml = `<div class="empty-state"><div class="empty-state-icon">⚠️</div><div class="empty-state-text">${escapeHtml(agentListState.lastError.message || 'Erro ao carregar')}</div></div>`;
    } else {
        const filteredAgents = applyAgentFilters(agentListState.agents);

        if (filteredAgents.length === 0) {
            bodyHtml = `
                <div class="empty-state">
                    <div class="empty-state-icon">🤖</div>
                    <div class="empty-state-text">${agentListState.agents.length === 0 ? 'Nenhum agente cadastrado' : 'Nenhum agente corresponde aos filtros atuais'}</div>
                    <button class="btn btn-primary mt-2" onclick="showCreateAgentModal()">Criar agente</button>
                </div>
            `;
        } else {
            const cards = filteredAgents.map(agent => {
                const statusMeta = getAgentStatusMeta(agent);
                const apiTypeBadge = agent.api_type === 'responses'
                    ? '<span class="badge badge-success">Responses API</span>'
                    : '<span class="badge badge-warning">Chat API</span>';
                const promptLabel = getAgentPromptLabel(agent);
                const connectedChannels = (agent.channels || []).filter(channel => channel.enabled).length;
                const totalChannels = (agent.channels || []).length;
                const channelSummary = totalChannels === 0 ? 'Nenhum canal configurado' : `${connectedChannels}/${totalChannels} conectados`;
                const updatedAt = agent.updated_at ? formatDate(agent.updated_at) : '—';
                const summaryHtml = AgentSummaryComponent.render(agent, {
                    showTitle: false,
                    compact: true,
                    vectorStoreMap: agentListState.vectorStoreMap
                });

                return `
                    <article class="agent-card">
                        <header class="agent-card-head">
                            <div>
                                <h4>${escapeHtml(agent.name)}</h4>
                                <p>${escapeHtml(agent.description || 'Sem descrição')}</p>
                            </div>
                            <div class="agent-card-tags">
                                ${statusMeta ? `<span class="badge ${statusMeta.badgeClass}">${escapeHtml(statusMeta.label)}</span>` : ''}
                                ${agent.is_default ? '<span class="badge badge-primary">Padrão</span>' : ''}
                                ${apiTypeBadge}
                            </div>
                        </header>
                        <dl class="agent-card-meta">
                            <div>
                                <dt>Prompt ativo</dt>
                                <dd>${escapeHtml(promptLabel)}</dd>
                            </div>
                            <div>
                                <dt>Canais</dt>
                                <dd>${escapeHtml(channelSummary)}</dd>
                            </div>
                            <div>
                                <dt>Atualizado</dt>
                                <dd>${escapeHtml(updatedAt)}</dd>
                            </div>
                        </dl>
                        ${summaryHtml}
                        <div class="agent-card-actions">
                            <button class="btn btn-small btn-secondary" onclick="editAgent('${agent.id}')">Editar</button>
                            <button class="btn btn-small btn-purple" onclick="showPromptBuilderModal('${agent.id}', '${agent.name}')">✨ Prompt Builder</button>
                            <button class="btn btn-small btn-info" onclick="manageChannels('${agent.id}', '${agent.name}')">Canais</button>
                            <button class="btn btn-small btn-primary" onclick="testAgent('${agent.id}')">Testar</button>
                            ${!agent.is_default ? `<button class="btn btn-small btn-success" onclick="makeDefaultAgent('${agent.id}')">Tornar padrão</button>` : ''}
                            <button class="btn btn-small btn-danger" onclick="deleteAgent('${agent.id}', '${agent.name}')">Excluir</button>
                        </div>
                    </article>
                `;
            }).join('');

            bodyHtml = `<div class="agent-grid">${cards}</div>`;
        }
    }

    content.innerHTML = `
        <div class="card agent-page-card">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Agentes</h3>
                    <p class="card-subtitle">Visualize status, canais conectados e fontes de conhecimento</p>
                </div>
                <button class="btn btn-primary" onclick="showCreateAgentModal()">Criar agente</button>
            </div>
            <div class="card-body">
                ${filterControls}
                ${bodyHtml}
            </div>
        </div>
    `;
}

function handleAgentFilterChange(filterKey, value) {
    if (!(filterKey in agentListState.filters)) {
        return;
    }
    const normalized = typeof value === 'string' ? value : '';
    if (agentListState.filters[filterKey] === normalized) {
        return;
    }
    agentListState.filters[filterKey] = normalized;
    renderAgentsList();
}

function applyAgentFilters(list) {
    const search = (agentListState.filters.search || '').toLowerCase();
    const apiType = agentListState.filters.apiType;
    const status = agentListState.filters.status;

    return list.filter(agent => {
        if (apiType !== 'all' && agent.api_type !== apiType) {
            return false;
        }

        if (status !== 'all') {
            const meta = getAgentStatusMeta(agent);
            if (!meta || meta.value !== status) {
                return false;
            }
        }

        if (search) {
            const haystack = [agent.name, agent.description, agent.prompt_id, agent.system_message]
                .filter(Boolean)
                .map(value => value.toLowerCase());
            const matches = haystack.some(field => field.includes(search));
            if (!matches) {
                return false;
            }
        }

        return true;
    });
}

function getAgentStatusMeta(agent) {
    if (!agent) {
        return null;
    }

    if (agent.is_default) {
        return { label: 'Padrão', value: 'default', badgeClass: 'badge-primary' };
    }

    const hasPrompt = Boolean(agent.prompt_id || agent.system_message);
    const connectedChannels = (agent.channels || []).filter(channel => channel.enabled).length;

    if (hasPrompt && connectedChannels > 0) {
        return { label: 'Pronto', value: 'ready', badgeClass: 'badge-success' };
    }

    if (hasPrompt) {
        return { label: 'Sem canal', value: 'needs-channel', badgeClass: 'badge-warning' };
    }

    return { label: 'Rascunho', value: 'draft', badgeClass: 'badge-secondary' };
}

function getAgentPromptLabel(agent) {
    if (agent.prompt_id) {
        return agent.prompt_version ? `${agent.prompt_id} · v${agent.prompt_version}` : agent.prompt_id;
    }
    if (agent.system_message) {
        return 'System message personalizada';
    }
    return 'Não configurado';
}

function showCreateAgentModal() {
    openAgentWorkspace({ mode: 'create' });
}

async function handleUpdateAgent(event, id) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    
    const data = {
        name: formData.get('name'),
        description: formData.get('description'),
        api_type: formData.get('api_type'),
        model: formData.get('model') || null,
        prompt_id: formData.get('prompt_id') || null,
        prompt_version: formData.get('prompt_version') || null,
        system_message: formData.get('system_message') || null,
        temperature: parseFloat(formData.get('temperature')) || null,
        top_p: parseFloat(formData.get('top_p')) || null,
        max_output_tokens: parseInt(formData.get('max_output_tokens')) || null,
        is_default: formData.get('is_default') === 'on',
    };
    
    // Handle vector store IDs
    const vectorStoreIds = formData.getAll('vector_store_ids');
    if (vectorStoreIds.length > 0) {
        data.vector_store_ids = vectorStoreIds;
    } else {
        const manualIds = formData.get('vector_store_ids');
        if (manualIds) {
            data.vector_store_ids = manualIds.split(',').map(s => s.trim()).filter(s => s);
        }
    }
    
    // Handle tools
    const tools = [];
    if (formData.get('enable_file_search') === 'on') {
        tools.push({ type: 'file_search' });
    }
    if (tools.length > 0) {
        data.tools = tools;
    }
    
    try {
        await api.updateAgent(id, data);
        closeModal();
        showToast('Agent updated successfully', 'success');
        loadAgentsPage();
    } catch (error) {
        showToast('Failed to update agent: ' + error.message, 'error');
    }
}

async function editAgent(id) {
    try {
        const agent = await api.getAgent(id);
        
        // Load prompts, vector stores, and models for dropdowns
        let prompts = [];
        let vectorStores = [];
        let models = [];
        
        try {
            prompts = await api.listPrompts();
            vectorStores = await api.listVectorStores();
            models = await api.listModels();
        } catch (error) {
            console.error('Error loading resources:', error);
        }
        
        const promptOptions = prompts.map(p => `<option value="${p.openai_prompt_id || ''}" ${agent.prompt_id === p.openai_prompt_id ? 'selected' : ''}>${p.name}</option>`).join('');
        const vectorStoreOptions = vectorStores.map(vs => `<option value="${vs.openai_store_id || ''}" ${agent.vector_store_ids && agent.vector_store_ids.includes(vs.openai_store_id) ? 'selected' : ''}>${vs.name}</option>`).join('');
        
        // Build model options with priority sorting
        const buildModelOptions = (modelsData) => {
            if (!modelsData || !modelsData.data || modelsData.data.length === 0) {
                return '';
            }
            
            // Priority map for model prefixes
            const getPriority = (modelId) => {
                if (modelId.startsWith('gpt-4')) return 1;
                if (modelId.startsWith('gpt-3.5')) return 2;
                return 3;
            };
            
            const sortedModels = modelsData.data.sort((a, b) => {
                const priorityDiff = getPriority(a.id) - getPriority(b.id);
                return priorityDiff !== 0 ? priorityDiff : a.id.localeCompare(b.id);
            });
            
            return sortedModels.map(m => `<option value="${m.id}" ${agent.model === m.id ? 'selected' : ''}>${m.id}</option>`).join('');
        };
        
        const modelOptions = buildModelOptions(models);
        
        // Build model input field (dropdown or text fallback)
        const modelInputHtml = modelOptions 
            ? `<select name="model" class="form-select"><option value="">Use default</option>${modelOptions}</select>`
            : `<input type="text" name="model" class="form-input" placeholder="e.g., gpt-4o, gpt-4o-mini" value="${agent.model || ''}" />`;
        
        // Check if file_search tool is enabled
        const hasFileSearch = agent.tools && agent.tools.some(t => t.type === 'file_search');
        
        const content = `
            <form id="agent-form" onsubmit="handleUpdateAgent(event, '${id}')">
                <div class="form-group">
                    <label class="form-label">Name *</label>
                    <input type="text" name="name" class="form-input" value="${agent.name || ''}" required />
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-textarea">${agent.description || ''}</textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">API Type *</label>
                    <select name="api_type" class="form-select">
                        <option value="responses" ${agent.api_type === 'responses' ? 'selected' : ''}>Responses API</option>
                        <option value="chat" ${agent.api_type === 'chat' ? 'selected' : ''}>Chat Completions API</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Model</label>
                    ${modelInputHtml}
                    <small class="form-help">Select a model or leave as default</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Prompt ID</label>
                    ${promptOptions ? `<select name="prompt_id" class="form-select"><option value="">Select Prompt</option>${promptOptions}</select>` : `<input type="text" name="prompt_id" class="form-input" value="${agent.prompt_id || ''}" />`}
                </div>
                
                <div class="form-group">
                    <label class="form-label">Prompt Version</label>
                    <input type="text" name="prompt_version" class="form-input" value="${agent.prompt_version || ''}" />
                </div>
                
                <div class="form-group">
                    <label class="form-label">System Message</label>
                    <textarea name="system_message" class="form-textarea" placeholder="You are a helpful assistant...">${agent.system_message || ''}</textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Temperature (0-2)</label>
                    <input type="number" name="temperature" class="form-input" step="0.1" min="0" max="2" value="${agent.temperature !== null && agent.temperature !== undefined ? agent.temperature : '0.7'}" />
                </div>
                
                <div class="form-group">
                    <label class="form-label">Top P (0-1)</label>
                    <input type="number" name="top_p" class="form-input" step="0.05" min="0" max="1" value="${agent.top_p !== null && agent.top_p !== undefined ? agent.top_p : '1'}" />
                </div>
                
                <div class="form-group">
                    <label class="form-label">Max Output Tokens</label>
                    <input type="number" name="max_output_tokens" class="form-input" placeholder="e.g., 1024" value="${agent.max_output_tokens || ''}" />
                </div>
                
                <div class="form-group">
                    <label class="form-label">Vector Store IDs</label>
                    ${vectorStoreOptions ? `<select name="vector_store_ids" class="form-select" multiple>${vectorStoreOptions}</select>` : `<input type="text" name="vector_store_ids" class="form-input" placeholder="vs_abc,vs_def" value="${agent.vector_store_ids ? agent.vector_store_ids.join(',') : ''}" />`}
                    <small class="form-help">Select vector stores for file search</small>
                </div>
                
                <div class="form-group">
                    <label class="form-checkbox">
                        <input type="checkbox" name="enable_file_search" ${hasFileSearch ? 'checked' : ''} />
                        Enable File Search Tool
                    </label>
                </div>
                
                <div class="form-group">
                    <label class="form-checkbox">
                        <input type="checkbox" name="is_default" ${agent.is_default ? 'checked' : ''} />
                        Set as Default Agent
                    </label>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Agent</button>
                </div>
            </form>
        `;
        
        openModal('Edit Agent', content);
    } catch (error) {
        showToast('Failed to load agent: ' + error.message, 'error');
    }
}

async function deleteAgent(id, name) {
    if (!confirm(`Are you sure you want to delete agent "${name}"?`)) {
        return;
    }
    
    try {
        await api.deleteAgent(id);
        showToast('Agent deleted successfully', 'success');
        loadAgentsPage();
    } catch (error) {
        showToast('Failed to delete agent: ' + error.message, 'error');
    }
}

async function makeDefaultAgent(id) {
    try {
        await api.makeDefaultAgent(id);
        showToast('Default agent updated', 'success');
        loadAgentsPage();
    } catch (error) {
        showToast('Failed to set default agent: ' + error.message, 'error');
    }
}

function testAgent(id) {
    if (window.agentTester && typeof window.agentTester.start === 'function') {
        window.agentTester.start(id);
    } else {
        showToast('Painel de teste indisponível no momento.', 'error');
    }
}

// ==================== Prompts Page ====================

async function loadPromptsPage() {
    const content = document.getElementById('content');
    content.innerHTML = '<div class="spinner"></div>';
    
    try {
        const prompts = await api.listPrompts();
        
        let html = `
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Prompts</h3>
                    <div class="flex gap-1">
                        <button class="btn btn-secondary" onclick="syncPrompts()">Sync from OpenAI</button>
                        <button class="btn btn-primary" onclick="showCreatePromptModal()">Create Prompt</button>
                    </div>
                </div>
                <div class="card-body">
        `;
        
        if (prompts.length === 0) {
            html += `
                <div class="empty-state">
                    <div class="empty-state-icon">📝</div>
                    <div class="empty-state-text">No prompts yet</div>
                    <button class="btn btn-primary mt-2" onclick="showCreatePromptModal()">Create Your First Prompt</button>
                </div>
            `;
        } else {
            html += `
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>OpenAI ID</th>
                                <th>Description</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            prompts.forEach(prompt => {
                html += `
                    <tr>
                        <td><strong>${prompt.name}</strong></td>
                        <td>${prompt.openai_prompt_id || '<span style="color: #6b7280;">Local only</span>'}</td>
                        <td>${prompt.description || '-'}</td>
                        <td>${formatDate(prompt.created_at)}</td>
                        <td class="table-actions">
                            <button class="btn btn-small btn-secondary" onclick="viewPromptVersions('${prompt.id}')">Versions</button>
                            <button class="btn btn-small btn-danger" onclick="deletePrompt('${prompt.id}', '${prompt.name}')">Delete</button>
                        </td>
                    </tr>
                `;
            });
            
            html += `
                        </tbody>
                    </table>
                </div>
            `;
        }
        
        html += `
                </div>
            </div>
        `;
        
        content.innerHTML = html;
    } catch (error) {
        content.innerHTML = `<div class="card"><div class="card-body">Error loading prompts: ${error.message}</div></div>`;
        showToast('Failed to load prompts: ' + error.message, 'error');
    }
}

async function showCreatePromptModal() {
    const content = `
        <form id="prompt-form" onsubmit="handleCreatePrompt(event)">
            <div class="form-group">
                <label class="form-label">Name *</label>
                <input type="text" name="name" class="form-input" required />
            </div>
            
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-textarea"></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label">Prompt Content *</label>
                <textarea name="content" class="form-textarea" rows="10" required placeholder="You are a helpful assistant that..."></textarea>
                <small class="form-help">This will be used as the initial prompt content</small>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Prompt</button>
            </div>
        </form>
    `;
    
    openModal('Create Prompt', content);
}

async function handleCreatePrompt(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    
    const data = {
        name: formData.get('name'),
        description: formData.get('description'),
        content: formData.get('content'),
    };
    
    try {
        await api.createPrompt(data);
        closeModal();
        showToast('Prompt created successfully', 'success');
        loadPromptsPage();
    } catch (error) {
        showToast('Failed to create prompt: ' + error.message, 'error');
    }
}

async function viewPromptVersions(id) {
    try {
        const response = await api.listPromptVersions(id);
        const versions = Array.isArray(response) ? response : (response?.versions || []);
        const activeVersion = Array.isArray(response) ? null : response?.active_version ?? null;

        let content = `
            <div class="form-group">
                <label class="form-label">Versions</label>
                <div style="max-height: 300px; overflow-y: auto;">
        `;

        if (versions.length === 0) {
            content += '<p>No versions yet</p>';
        } else {
            content += '<table style="width: 100%;"><thead><tr><th>Version</th><th>Created</th><th>Status</th></tr></thead><tbody>';
            versions.forEach(v => {
                const createdAt = v.created_at ? formatDate(v.created_at) : '—';
                const isActive = activeVersion !== null && String(v.version) === String(activeVersion);
                const statusCell = isActive ? '<span class="status-pill status-pill--active">Active</span>' : '';
                content += `<tr${isActive ? ' class="active"' : ''}><td>${v.version}</td><td>${createdAt}</td><td>${statusCell}</td></tr>`;
            });
            content += '</tbody></table>';
        }

        content += `
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal()">Close</button>
            </div>
        `;

        openModal('Prompt Versions', content);
    } catch (error) {
        showToast('Failed to load versions: ' + error.message, 'error');
    }
}

async function deletePrompt(id, name) {
    if (!confirm(`Are you sure you want to delete prompt "${name}"?`)) {
        return;
    }
    
    try {
        await api.deletePrompt(id);
        showToast('Prompt deleted successfully', 'success');
        loadPromptsPage();
    } catch (error) {
        showToast('Failed to delete prompt: ' + error.message, 'error');
    }
}

async function syncPrompts() {
    try {
        const result = await api.syncPrompts();
        showToast(`Synced ${result.synced} prompts from OpenAI`, 'success');
        loadPromptsPage();
    } catch (error) {
        showToast('Failed to sync prompts: ' + error.message, 'error');
    }
}

// ==================== Vector Stores Page ====================

async function loadVectorStoresPage() {
    const content = document.getElementById('content');
    content.innerHTML = '<div class="spinner"></div>';
    
    try {
        const stores = await api.listVectorStores();
        
        let html = `
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Vector Stores</h3>
                    <div class="flex gap-1">
                        <button class="btn btn-secondary" onclick="syncVectorStores()">Sync from OpenAI</button>
                        <button class="btn btn-primary" onclick="showCreateVectorStoreModal()">Create Vector Store</button>
                    </div>
                </div>
                <div class="card-body">
        `;
        
        if (stores.length === 0) {
            html += `
                <div class="empty-state">
                    <div class="empty-state-icon">📚</div>
                    <div class="empty-state-text">No vector stores yet</div>
                    <button class="btn btn-primary mt-2" onclick="showCreateVectorStoreModal()">Create Your First Vector Store</button>
                </div>
            `;
        } else {
            html += `
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>OpenAI ID</th>
                                <th>Status</th>
                                <th>Files</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            stores.forEach(store => {
                const statusBadge = store.status === 'ready' ? 
                    '<span class="badge badge-success">Ready</span>' : 
                    '<span class="badge badge-warning">' + store.status + '</span>';
                
                html += `
                    <tr>
                        <td><strong>${store.name}</strong></td>
                        <td>${store.openai_store_id || '<span style="color: #6b7280;">Local only</span>'}</td>
                        <td>${statusBadge}</td>
                        <td>${store.file_count || 0}</td>
                        <td>${formatDate(store.created_at)}</td>
                        <td class="table-actions">
                            <button class="btn btn-small btn-primary" onclick="viewVectorStoreFiles('${store.id}', '${store.name}')">Manage Files</button>
                            <button class="btn btn-small btn-danger" onclick="deleteVectorStore('${store.id}', '${store.name}')">Delete</button>
                        </td>
                    </tr>
                `;
            });
            
            html += `
                        </tbody>
                    </table>
                </div>
            `;
        }
        
        html += `
                </div>
            </div>
        `;
        
        content.innerHTML = html;
    } catch (error) {
        content.innerHTML = `<div class="card"><div class="card-body">Error loading vector stores: ${error.message}</div></div>`;
        showToast('Failed to load vector stores: ' + error.message, 'error');
    }
}

async function showCreateVectorStoreModal() {
    const content = `
        <form id="vector-store-form" onsubmit="handleCreateVectorStore(event)">
            <div class="form-group">
                <label class="form-label">Name *</label>
                <input type="text" name="name" class="form-input" required />
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Vector Store</button>
            </div>
        </form>
    `;
    
    openModal('Create Vector Store', content);
}

async function handleCreateVectorStore(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    
    const data = {
        name: formData.get('name'),
    };
    
    try {
        await api.createVectorStore(data);
        closeModal();
        showToast('Vector store created successfully', 'success');
        loadVectorStoresPage();
    } catch (error) {
        showToast('Failed to create vector store: ' + error.message, 'error');
    }
}

async function viewVectorStoreFiles(storeId, storeName) {
    try {
        const files = await api.listVectorStoreFiles(storeId);
        
        let content = `
            <div class="form-group">
                <label class="form-label">Files in ${storeName}</label>
                <div style="max-height: 400px; overflow-y: auto;">
        `;
        
        if (files.length === 0) {
            content += '<p>No files yet</p>';
        } else {
            content += '<table style="width: 100%;"><thead><tr><th>Name</th><th>Status</th><th>Size</th><th>Actions</th></tr></thead><tbody>';
            files.forEach(file => {
                const statusBadge = file.ingestion_status === 'completed' ? 
                    '<span class="badge badge-success">Completed</span>' :
                    file.ingestion_status === 'in_progress' ?
                    '<span class="badge badge-warning">In Progress</span>' :
                    '<span class="badge badge-danger">' + file.ingestion_status + '</span>';
                
                content += `
                    <tr>
                        <td>${file.name}</td>
                        <td>${statusBadge}</td>
                        <td>${file.size ? (file.size / 1024).toFixed(1) + ' KB' : '-'}</td>
                        <td><button class="btn btn-small btn-danger" onclick="deleteVectorStoreFile('${storeId}', '${file.id}', '${file.name}')">Delete</button></td>
                    </tr>
                `;
            });
            content += '</tbody></table>';
        }
        
        content += `
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Upload New File</label>
                <input type="file" id="file-upload-input" class="form-input" />
                <button class="btn btn-primary mt-1" onclick="uploadFileToVectorStore('${storeId}')">Upload</button>
            </div>
            
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal(); loadVectorStoresPage();">Close</button>
            </div>
        `;
        
        openModal(`Manage Files - ${storeName}`, content);
    } catch (error) {
        showToast('Failed to load files: ' + error.message, 'error');
    }
}

async function uploadFileToVectorStore(storeId) {
    const fileInput = document.getElementById('file-upload-input');
    const file = fileInput.files[0];
    
    if (!file) {
        showToast('Please select a file', 'error');
        return;
    }
    
    // Convert to base64
    const reader = new FileReader();
    reader.onload = async function(e) {
        const base64Data = e.target.result.split(',')[1];
        
        const data = {
            name: file.name,
            file_data: base64Data,
            size: file.size,
            mime_type: file.type,
        };
        
        try {
            showToast('Uploading file...', 'warning');
            await api.addVectorStoreFile(storeId, data);
            showToast('File uploaded successfully', 'success');
            
            // Reload the files list
            const store = await api.getVectorStore(storeId);
            viewVectorStoreFiles(storeId, store.name);
        } catch (error) {
            showToast('Failed to upload file: ' + error.message, 'error');
        }
    };
    
    reader.readAsDataURL(file);
}

async function deleteVectorStoreFile(storeId, fileId, fileName) {
    if (!confirm(`Are you sure you want to delete file "${fileName}"?`)) {
        return;
    }
    
    try {
        await api.deleteVectorStoreFile(storeId, fileId);
        showToast('File deleted successfully', 'success');
        
        // Reload the files list
        const store = await api.getVectorStore(storeId);
        viewVectorStoreFiles(storeId, store.name);
    } catch (error) {
        showToast('Failed to delete file: ' + error.message, 'error');
    }
}

async function deleteVectorStore(id, name) {
    if (!confirm(`Are you sure you want to delete vector store "${name}"?`)) {
        return;
    }
    
    try {
        await api.deleteVectorStore(id);
        showToast('Vector store deleted successfully', 'success');
        loadVectorStoresPage();
    } catch (error) {
        showToast('Failed to delete vector store: ' + error.message, 'error');
    }
}

async function syncVectorStores() {
    try {
        const result = await api.syncVectorStores();
        showToast(`Synced ${result.synced} vector stores from OpenAI`, 'success');
        loadVectorStoresPage();
    } catch (error) {
        showToast('Failed to sync vector stores: ' + error.message, 'error');
    }
}

// ==================== Jobs Page ====================

let jobsRefreshInterval = null;

async function loadJobsPage() {
    const content = document.getElementById('content');
    content.innerHTML = '<div class="spinner"></div>';
    
    // Clear existing interval if any
    if (jobsRefreshInterval) {
        clearInterval(jobsRefreshInterval);
        jobsRefreshInterval = null;
    }
    
    await refreshJobsPage();
    
    // Auto-refresh every 5 seconds
    jobsRefreshInterval = setInterval(refreshJobsPage, 5000);
}

async function refreshJobsPage() {
    const content = document.getElementById('content');
    
    // Save scroll position before refresh (handle both window scroll and content scroll)
    const contentScrollTop = content.scrollTop;
    const windowScrollY = window.scrollY || window.pageYOffset || document.documentElement.scrollTop;
    const windowScrollX = window.scrollX || window.pageXOffset || document.documentElement.scrollLeft;
    
    try {
        const [stats, pendingJobs, runningJobs, recentJobs] = await Promise.all([
            api.jobStats(),
            api.listJobs('pending', 20),
            api.listJobs('running', 20),
            api.listJobs(null, 20)
        ]);
        
        let html = `
            <!-- Stats Cards -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
                <div class="card">
                    <div class="card-body" style="text-align: center;">
                        <div style="font-size: 2rem; font-weight: bold; color: #f59e0b;">${stats.pending || 0}</div>
                        <div style="color: #6b7280; margin-top: 0.5rem;">Pending</div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body" style="text-align: center;">
                        <div style="font-size: 2rem; font-weight: bold; color: #3b82f6;">${stats.running || 0}</div>
                        <div style="color: #6b7280; margin-top: 0.5rem;">Running</div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body" style="text-align: center;">
                        <div style="font-size: 2rem; font-weight: bold; color: #10b981;">${stats.completed || 0}</div>
                        <div style="color: #6b7280; margin-top: 0.5rem;">Completed</div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body" style="text-align: center;">
                        <div style="font-size: 2rem; font-weight: bold; color: #ef4444;">${stats.failed || 0}</div>
                        <div style="color: #6b7280; margin-top: 0.5rem;">Failed</div>
                    </div>
                </div>
            </div>
            
            <!-- Pending Jobs -->
            <div class="card" style="margin-bottom: 1.5rem;">
                <div class="card-header">
                    <h3 class="card-title">Pending Jobs</h3>
                    <span class="badge badge-warning">${pendingJobs.length}</span>
                </div>
                <div class="card-body">
                    ${renderJobsTable(pendingJobs, 'No pending jobs')}
                </div>
            </div>
            
            <!-- Running Jobs -->
            <div class="card" style="margin-bottom: 1.5rem;">
                <div class="card-header">
                    <h3 class="card-title">Running Jobs</h3>
                    <span class="badge badge-primary">${runningJobs.length}</span>
                </div>
                <div class="card-body">
                    ${renderJobsTable(runningJobs, 'No running jobs')}
                </div>
            </div>
            
            <!-- Recent Jobs -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Recent Jobs</h3>
                </div>
                <div class="card-body">
                    ${renderJobsTable(recentJobs, 'No jobs yet')}
                </div>
            </div>
        `;
        
        content.innerHTML = html;
        
        // Restore scroll position after refresh
        content.scrollTop = contentScrollTop;
        window.scrollTo(windowScrollX, windowScrollY);
    } catch (error) {
        content.innerHTML = `<div class="card"><div class="card-body">Error loading jobs: ${error.message}</div></div>`;
        showToast('Failed to load jobs: ' + error.message, 'error');
    }
}

function renderJobsTable(jobs, emptyMessage) {
    if (!jobs || jobs.length === 0) {
        return `<p style="color: #6b7280; text-align: center; padding: 2rem;">${emptyMessage}</p>`;
    }
    
    return `
        <div style="overflow-x: auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Attempts</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${jobs.map(job => `
                        <tr>
                            <td>
                                <code style="font-size: 0.875rem;">${job.type}</code>
                            </td>
                            <td>
                                <span class="badge ${getJobStatusBadge(job.status)}">
                                    ${job.status}
                                </span>
                            </td>
                            <td>${job.attempts}/${job.max_attempts}</td>
                            <td style="font-size: 0.875rem;">${formatDate(job.created_at)}</td>
                            <td>
                                <button class="btn btn-sm" onclick="viewJobDetails('${job.id}')" title="View Details">
                                    👁️
                                </button>
                                ${job.status === 'failed' ? `
                                    <button class="btn btn-sm btn-warning" onclick="retryJobAction('${job.id}')" title="Retry">
                                        🔄
                                    </button>
                                ` : ''}
                                ${job.status === 'pending' || job.status === 'running' ? `
                                    <button class="btn btn-sm btn-danger" onclick="cancelJobAction('${job.id}')" title="Cancel">
                                        ❌
                                    </button>
                                ` : ''}
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
}

function getJobStatusBadge(status) {
    // Note: 'cancelled' is not a valid status in the database schema (jobs table).
    // Cancelled jobs are stored as 'failed' with error_text = 'Cancelled by user'.
    // The 'cancelled' mapping is kept here for future schema enhancement.
    const badges = {
        'pending': 'badge-warning',
        'running': 'badge-primary',
        'completed': 'badge-success',
        'failed': 'badge-danger',
        'cancelled': 'badge-secondary'  // Reserved for future use
    };
    return badges[status] || 'badge-secondary';
}

async function viewJobDetails(jobId) {
    try {
        const job = await api.getJob(jobId);
        
        const content = `
            <div class="form-group">
                <label class="form-label">Job ID</label>
                <code>${job.id}</code>
            </div>
            <div class="form-group">
                <label class="form-label">Type</label>
                <code>${job.type}</code>
            </div>
            <div class="form-group">
                <label class="form-label">Status</label>
                <span class="badge ${getJobStatusBadge(job.status)}">${job.status}</span>
            </div>
            <div class="form-group">
                <label class="form-label">Attempts</label>
                <div>${job.attempts} / ${job.max_attempts}</div>
            </div>
            <div class="form-group">
                <label class="form-label">Created</label>
                <div>${formatDate(job.created_at)}</div>
            </div>
            ${job.completed_at ? `
                <div class="form-group">
                    <label class="form-label">Completed</label>
                    <div>${formatDate(job.completed_at)}</div>
                </div>
            ` : ''}
            ${job.payload_json ? `
                <div class="form-group">
                    <label class="form-label">Payload</label>
                    <pre style="background: #1f2937; color: #e5e7eb; padding: 1rem; border-radius: 0.375rem; overflow-x: auto;">${JSON.stringify(JSON.parse(job.payload_json), null, 2)}</pre>
                </div>
            ` : ''}
            ${job.result_json ? `
                <div class="form-group">
                    <label class="form-label">Result</label>
                    <pre style="background: #1f2937; color: #e5e7eb; padding: 1rem; border-radius: 0.375rem; overflow-x: auto;">${JSON.stringify(JSON.parse(job.result_json), null, 2)}</pre>
                </div>
            ` : ''}
            ${job.error_text ? `
                <div class="form-group">
                    <label class="form-label">Error</label>
                    <div style="color: #ef4444;">${job.error_text}</div>
                </div>
            ` : ''}
        `;
        
        openModal('Job Details', content);
    } catch (error) {
        showToast('Failed to load job details: ' + error.message, 'error');
    }
}

async function retryJobAction(jobId) {
    if (!confirm('Retry this job?')) return;
    
    try {
        await api.retryJob(jobId);
        showToast('Job queued for retry', 'success');
        refreshJobsPage();
    } catch (error) {
        showToast('Failed to retry job: ' + error.message, 'error');
    }
}

async function cancelJobAction(jobId) {
    if (!confirm('Cancel this job?')) return;
    
    try {
        await api.cancelJob(jobId);
        showToast('Job cancelled', 'success');
        refreshJobsPage();
    } catch (error) {
        showToast('Failed to cancel job: ' + error.message, 'error');
    }
}

// ==================== Webhook Testing Page ====================

async function loadWebhookTestingPage() {
    const content = document.getElementById('content');
    
    const html = `
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <h3 class="card-title">🔧 Webhook Testing Tools</h3>
            </div>
            <div class="card-body">
                <p style="color: #6b7280; margin-bottom: 1.5rem;">
                    Test webhook deliveries, validate signatures, and inspect delivery logs.
                </p>
            </div>
        </div>
        
        <!-- Send Test Webhook -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <h3 class="card-title">📤 Send Test Webhook</h3>
            </div>
            <div class="card-body">
                <form id="sendWebhookForm" style="max-width: 800px;">
                    <div class="form-group">
                        <label for="webhookUrl">Target URL</label>
                        <input type="url" id="webhookUrl" class="form-control" 
                               placeholder="https://example.com/webhook" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="webhookEvent">Event Type</label>
                        <input type="text" id="webhookEvent" class="form-control" 
                               placeholder="ai.response" value="ai.response" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="webhookData">Event Data (JSON)</label>
                        <textarea id="webhookData" class="form-control" rows="6" 
                                  placeholder='{"message": "test"}'>{
  "message": "Test webhook from admin UI",
  "timestamp": ${Date.now()}
}</textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="webhookSecret">HMAC Secret (Optional)</label>
                        <input type="text" id="webhookSecret" class="form-control" 
                               placeholder="Leave empty for no signature">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <span class="btn-icon">📤</span> Send Test Webhook
                    </button>
                </form>
                
                <div id="webhookTestResult" style="margin-top: 1.5rem; display: none;">
                    <h4 style="margin-bottom: 1rem;">Response</h4>
                    <div id="webhookTestResultContent"></div>
                </div>
            </div>
        </div>
        
        <!-- Validate Signature -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <h3 class="card-title">🔐 Validate Signature</h3>
            </div>
            <div class="card-body">
                <form id="validateSignatureForm" style="max-width: 800px;">
                    <div class="form-group">
                        <label for="signatureBody">Request Body</label>
                        <textarea id="signatureBody" class="form-control" rows="4" 
                                  placeholder='{"event":"test","timestamp":123456789}'></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="signatureSecret">HMAC Secret</label>
                        <input type="text" id="signatureSecret" class="form-control" 
                               placeholder="your-webhook-secret" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="signatureProvided">Provided Signature</label>
                        <input type="text" id="signatureProvided" class="form-control" 
                               placeholder="sha256=..." required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <span class="btn-icon">🔐</span> Validate Signature
                    </button>
                </form>
                
                <div id="signatureValidationResult" style="margin-top: 1.5rem; display: none;">
                    <div id="signatureValidationResultContent"></div>
                </div>
            </div>
        </div>
        
        <!-- Webhook Metrics -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <h3 class="card-title">📊 Webhook Metrics</h3>
                <button class="btn btn-secondary btn-sm" onclick="refreshWebhookMetrics()">
                    <span class="btn-icon">🔄</span> Refresh
                </button>
            </div>
            <div class="card-body">
                <div id="webhookMetricsContent">
                    <div class="spinner"></div>
                </div>
            </div>
        </div>
        
        <!-- Recent Logs -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">📜 Recent Delivery Logs</h3>
                <button class="btn btn-secondary btn-sm" onclick="refreshWebhookLogs()">
                    <span class="btn-icon">🔄</span> Refresh
                </button>
            </div>
            <div class="card-body">
                <div id="webhookLogsContent">
                    <div class="spinner"></div>
                </div>
            </div>
        </div>
    `;
    
    content.innerHTML = html;
    
    // Initialize event listeners
    document.getElementById('sendWebhookForm').addEventListener('submit', handleSendTestWebhook);
    document.getElementById('validateSignatureForm').addEventListener('submit', handleValidateSignature);
    
    // Load initial data
    refreshWebhookMetrics();
    refreshWebhookLogs();
}

async function handleSendTestWebhook(e) {
    e.preventDefault();
    
    const url = document.getElementById('webhookUrl').value;
    const event = document.getElementById('webhookEvent').value;
    const dataText = document.getElementById('webhookData').value;
    const secret = document.getElementById('webhookSecret').value;
    
    const resultDiv = document.getElementById('webhookTestResult');
    const resultContent = document.getElementById('webhookTestResultContent');
    
    try {
        // Parse JSON data
        const data = JSON.parse(dataText);
        
        // Build payload
        const payload = {
            event: event,
            timestamp: Math.floor(Date.now() / 1000),
            agent_id: 'admin_ui_test',
            data: data
        };
        
        const body = JSON.stringify(payload);
        
        // Generate signature if secret provided
        let signature = null;
        if (secret) {
            const encoder = new TextEncoder();
            const keyData = encoder.encode(secret);
            const messageData = encoder.encode(body);
            
            const cryptoKey = await crypto.subtle.importKey(
                'raw',
                keyData,
                { name: 'HMAC', hash: 'SHA-256' },
                false,
                ['sign']
            );
            
            const signatureBuffer = await crypto.subtle.sign('HMAC', cryptoKey, messageData);
            const signatureArray = Array.from(new Uint8Array(signatureBuffer));
            const signatureHex = signatureArray.map(b => b.toString(16).padStart(2, '0')).join('');
            signature = 'sha256=' + signatureHex;
        }
        
        // Send request
        const startTime = performance.now();
        const headers = {
            'Content-Type': 'application/json',
            'User-Agent': 'AI-Agent-Admin-UI/1.0'
        };
        
        if (signature) {
            headers['X-Agent-Signature'] = signature;
        }
        
        const response = await fetch(url, {
            method: 'POST',
            headers: headers,
            body: body
        });
        
        const duration = performance.now() - startTime;
        const responseText = await response.text();
        
        // Try to parse as JSON
        let responseBody;
        try {
            responseBody = JSON.parse(responseText);
        } catch {
            responseBody = responseText;
        }
        
        // Display result
        const statusClass = response.ok ? 'success' : 'error';
        const statusIcon = response.ok ? '✓' : '✗';
        
        resultContent.innerHTML = `
            <div class="alert alert-${statusClass}">
                ${statusIcon} HTTP ${response.status} - ${duration.toFixed(2)}ms
            </div>
            
            ${signature ? `<div style="margin-bottom: 1rem;">
                <strong>Signature Sent:</strong>
                <code style="display: block; padding: 0.5rem; background: #f3f4f6; border-radius: 4px; margin-top: 0.5rem; word-break: break-all;">
                    ${signature}
                </code>
            </div>` : ''}
            
            <div>
                <strong>Response Body:</strong>
                <pre style="background: #f3f4f6; padding: 1rem; border-radius: 4px; margin-top: 0.5rem; overflow-x: auto;">${
                    typeof responseBody === 'object' 
                        ? JSON.stringify(responseBody, null, 2) 
                        : responseBody
                }</pre>
            </div>
        `;
        
        resultDiv.style.display = 'block';
        
    } catch (error) {
        resultContent.innerHTML = `
            <div class="alert alert-error">
                ✗ Error: ${error.message}
            </div>
        `;
        resultDiv.style.display = 'block';
    }
}

async function handleValidateSignature(e) {
    e.preventDefault();
    
    const body = document.getElementById('signatureBody').value;
    const secret = document.getElementById('signatureSecret').value;
    const providedSignature = document.getElementById('signatureProvided').value;
    
    const resultDiv = document.getElementById('signatureValidationResult');
    const resultContent = document.getElementById('signatureValidationResultContent');
    
    try {
        // Generate expected signature
        const encoder = new TextEncoder();
        const keyData = encoder.encode(secret);
        const messageData = encoder.encode(body);
        
        const cryptoKey = await crypto.subtle.importKey(
            'raw',
            keyData,
            { name: 'HMAC', hash: 'SHA-256' },
            false,
            ['sign']
        );
        
        const signatureBuffer = await crypto.subtle.sign('HMAC', cryptoKey, messageData);
        const signatureArray = Array.from(new Uint8Array(signatureBuffer));
        const signatureHex = signatureArray.map(b => b.toString(16).padStart(2, '0')).join('');
        const expectedSignature = 'sha256=' + signatureHex;
        
        // Compare signatures
        const isValid = expectedSignature === providedSignature;
        
        resultContent.innerHTML = `
            <div class="alert alert-${isValid ? 'success' : 'error'}">
                ${isValid ? '✓ Signature is VALID' : '✗ Signature is INVALID'}
            </div>
            
            <div style="margin-top: 1rem;">
                <strong>Expected Signature:</strong>
                <code style="display: block; padding: 0.5rem; background: #f3f4f6; border-radius: 4px; margin-top: 0.5rem; word-break: break-all;">
                    ${expectedSignature}
                </code>
            </div>
            
            <div style="margin-top: 1rem;">
                <strong>Provided Signature:</strong>
                <code style="display: block; padding: 0.5rem; background: #f3f4f6; border-radius: 4px; margin-top: 0.5rem; word-break: break-all;">
                    ${providedSignature}
                </code>
            </div>
        `;
        
        resultDiv.style.display = 'block';
        
    } catch (error) {
        resultContent.innerHTML = `
            <div class="alert alert-error">
                ✗ Error: ${error.message}
            </div>
        `;
        resultDiv.style.display = 'block';
    }
}

async function refreshWebhookMetrics() {
    const metricsContent = document.getElementById('webhookMetricsContent');
    
    try {
        const response = await fetch(`${API_BASE}/webhook/metrics.php?format=json`);
        const metrics = await response.json();
        
        if (!response.ok) {
            throw new Error(metrics.message || 'Failed to load metrics');
        }
        
        const deliveries = metrics.deliveries || {};
        const latency = metrics.latency || {};
        const retries = metrics.retries || {};
        
        metricsContent.innerHTML = `
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
                <div class="card">
                    <div class="card-body" style="text-align: center;">
                        <div style="font-size: 2rem; font-weight: bold; color: #3b82f6;">${deliveries.total || 0}</div>
                        <div style="color: #6b7280; margin-top: 0.5rem;">Total Deliveries</div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body" style="text-align: center;">
                        <div style="font-size: 2rem; font-weight: bold; color: #10b981;">${deliveries.success || 0}</div>
                        <div style="color: #6b7280; margin-top: 0.5rem;">Successful</div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body" style="text-align: center;">
                        <div style="font-size: 2rem; font-weight: bold; color: #ef4444;">${deliveries.failed || 0}</div>
                        <div style="color: #6b7280; margin-top: 0.5rem;">Failed</div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body" style="text-align: center;">
                        <div style="font-size: 2rem; font-weight: bold; color: #f59e0b;">${deliveries.success_rate || 0}%</div>
                        <div style="color: #6b7280; margin-top: 0.5rem;">Success Rate</div>
                    </div>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <div class="card">
                    <div class="card-body" style="text-align: center;">
                        <div style="font-size: 1.5rem; font-weight: bold;">${(latency.avg || 0).toFixed(3)}s</div>
                        <div style="color: #6b7280; margin-top: 0.5rem;">Avg Latency</div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body" style="text-align: center;">
                        <div style="font-size: 1.5rem; font-weight: bold;">${(latency.p95 || 0).toFixed(3)}s</div>
                        <div style="color: #6b7280; margin-top: 0.5rem;">P95 Latency</div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body" style="text-align: center;">
                        <div style="font-size: 1.5rem; font-weight: bold;">${retries.total_retries || 0}</div>
                        <div style="color: #6b7280; margin-top: 0.5rem;">Total Retries</div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body" style="text-align: center;">
                        <div style="font-size: 1.5rem; font-weight: bold;">${metrics.queue_depth || 0}</div>
                        <div style="color: #6b7280; margin-top: 0.5rem;">Queue Depth</div>
                    </div>
                </div>
            </div>
            
            ${deliveries.by_event_type && Object.keys(deliveries.by_event_type).length > 0 ? `
                <div style="margin-top: 1.5rem;">
                    <h4 style="margin-bottom: 1rem;">Deliveries by Event Type</h4>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Event Type</th>
                                    <th style="text-align: right;">Count</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${Object.entries(deliveries.by_event_type)
                                    .sort((a, b) => b[1] - a[1])
                                    .map(([event, count]) => `
                                        <tr>
                                            <td><code>${event}</code></td>
                                            <td style="text-align: right;"><strong>${count}</strong></td>
                                        </tr>
                                    `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            ` : ''}
        `;
        
    } catch (error) {
        metricsContent.innerHTML = `
            <div class="alert alert-error">
                Failed to load metrics: ${error.message}
            </div>
        `;
    }
}

async function refreshWebhookLogs() {
    const logsContent = document.getElementById('webhookLogsContent');
    
    // For now, show placeholder - this would need a backend endpoint
    logsContent.innerHTML = `
        <div style="color: #6b7280; text-align: center; padding: 2rem;">
            <p>📝 Webhook delivery logs can be inspected using the CLI tool:</p>
            <code style="display: block; padding: 1rem; background: #f3f4f6; border-radius: 4px; margin-top: 1rem;">
                php scripts/test_webhook.php inspect-logs --limit 20
            </code>
        </div>
    `;
}

// ==================== Tenants Page ====================

async function loadTenantsPage() {
    const content = document.getElementById('content');
    content.innerHTML = '<div class="spinner"></div>';
    
    try {
        const tenants = await api.listTenants();
        
        content.innerHTML = `
            <div class="page-header">
                <h2>Tenants</h2>
                <button class="btn btn-primary" onclick="showCreateTenantModal()">
                    + Create Tenant
                </button>
            </div>
            
            <div class="search-filters">
                <input type="search" id="tenant-search" placeholder="Search tenants..." 
                       onkeyup="filterTenants()">
                <select id="tenant-status-filter" onchange="filterTenants()">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="suspended">Suspended</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Slug</th>
                            <th>Status</th>
                            <th>Plan</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="tenants-list">
                        ${tenants.map(tenant => `
                            <tr data-tenant-id="${tenant.id}" 
                                data-tenant-slug="${tenant.slug}"
                                data-tenant-status="${tenant.status}">
                                <td><strong>${escapeHtml(tenant.name)}</strong></td>
                                <td><code>${escapeHtml(tenant.slug)}</code></td>
                                <td><span class="status-badge status-${tenant.status}">${tenant.status}</span></td>
                                <td>${tenant.plan || '-'}</td>
                                <td>${formatDate(tenant.created_at)}</td>
                                <td>
                                    <button class="btn btn-sm" onclick="viewTenantStats('${tenant.id}')">
                                        Stats
                                    </button>
                                    <button class="btn btn-sm" onclick="editTenant('${tenant.id}')">
                                        Edit
                                    </button>
                                    ${tenant.status === 'active' 
                                        ? `<button class="btn btn-sm btn-warning" onclick="suspendTenant('${tenant.id}')">Suspend</button>`
                                        : `<button class="btn btn-sm btn-success" onclick="activateTenant('${tenant.id}')">Activate</button>`
                                    }
                                    <button class="btn btn-sm btn-danger" onclick="deleteTenant('${tenant.id}')">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    } catch (error) {
        content.innerHTML = `
            <div class="error-message">
                Failed to load tenants: ${error.message}
            </div>
        `;
    }
}

function filterTenants() {
    const search = document.getElementById('tenant-search').value.toLowerCase();
    const statusFilter = document.getElementById('tenant-status-filter').value;
    const rows = document.querySelectorAll('#tenants-list tr');
    
    rows.forEach(row => {
        const slug = row.dataset.tenantSlug.toLowerCase();
        const status = row.dataset.tenantStatus;
        const name = row.querySelector('td strong').textContent.toLowerCase();
        
        const matchesSearch = search === '' || name.includes(search) || slug.includes(search);
        const matchesStatus = statusFilter === '' || status === statusFilter;
        
        row.style.display = matchesSearch && matchesStatus ? '' : 'none';
    });
}

function showCreateTenantModal() {
    showModal('Create Tenant', `
        <form id="create-tenant-form" onsubmit="createTenant(event)">
            <div class="form-group">
                <label for="tenant-name">Name *</label>
                <input type="text" id="tenant-name" required 
                       placeholder="Acme Corporation">
            </div>
            
            <div class="form-group">
                <label for="tenant-slug">Slug *</label>
                <input type="text" id="tenant-slug" required 
                       pattern="[a-z0-9-]+" 
                       placeholder="acme"
                       title="Lowercase letters, numbers, and hyphens only">
                <small>URL-safe identifier (lowercase, hyphens allowed)</small>
            </div>
            
            <div class="form-group">
                <label for="tenant-billing-email">Billing Email</label>
                <input type="email" id="tenant-billing-email" 
                       placeholder="billing@acme.com">
            </div>
            
            <div class="form-group">
                <label for="tenant-plan">Plan</label>
                <select id="tenant-plan">
                    <option value="">None</option>
                    <option value="starter">Starter</option>
                    <option value="pro">Pro</option>
                    <option value="enterprise">Enterprise</option>
                </select>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">
                    Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    Create Tenant
                </button>
            </div>
        </form>
    `);
}

async function createTenant(event) {
    event.preventDefault();
    
    const data = {
        name: document.getElementById('tenant-name').value,
        slug: document.getElementById('tenant-slug').value,
        billing_email: document.getElementById('tenant-billing-email').value || null,
        plan: document.getElementById('tenant-plan').value || null,
        status: 'active'
    };
    
    try {
        await api.createTenant(data);
        closeModal();
        showToast('Tenant created successfully');
        loadTenantsPage();
    } catch (error) {
        showToast('Failed to create tenant: ' + error.message, 'error');
    }
}

async function editTenant(id) {
    try {
        const tenant = await api.getTenant(id);
        
        showModal('Edit Tenant', `
            <form id="edit-tenant-form" onsubmit="updateTenant(event, '${id}')">
                <div class="form-group">
                    <label for="edit-tenant-name">Name *</label>
                    <input type="text" id="edit-tenant-name" required 
                           value="${escapeHtml(tenant.name)}">
                </div>
                
                <div class="form-group">
                    <label for="edit-tenant-slug">Slug *</label>
                    <input type="text" id="edit-tenant-slug" required 
                           pattern="[a-z0-9-]+" 
                           value="${escapeHtml(tenant.slug)}">
                </div>
                
                <div class="form-group">
                    <label for="edit-tenant-billing-email">Billing Email</label>
                    <input type="email" id="edit-tenant-billing-email" 
                           value="${tenant.billing_email || ''}">
                </div>
                
                <div class="form-group">
                    <label for="edit-tenant-plan">Plan</label>
                    <select id="edit-tenant-plan">
                        <option value="">None</option>
                        <option value="starter" ${tenant.plan === 'starter' ? 'selected' : ''}>Starter</option>
                        <option value="pro" ${tenant.plan === 'pro' ? 'selected' : ''}>Pro</option>
                        <option value="enterprise" ${tenant.plan === 'enterprise' ? 'selected' : ''}>Enterprise</option>
                    </select>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        Update Tenant
                    </button>
                </div>
            </form>
        `);
    } catch (error) {
        showToast('Failed to load tenant: ' + error.message, 'error');
    }
}

async function updateTenant(event, id) {
    event.preventDefault();
    
    const data = {
        name: document.getElementById('edit-tenant-name').value,
        slug: document.getElementById('edit-tenant-slug').value,
        billing_email: document.getElementById('edit-tenant-billing-email').value || null,
        plan: document.getElementById('edit-tenant-plan').value || null
    };
    
    try {
        await api.updateTenant(id, data);
        closeModal();
        showToast('Tenant updated successfully');
        loadTenantsPage();
    } catch (error) {
        showToast('Failed to update tenant: ' + error.message, 'error');
    }
}

async function viewTenantStats(id) {
    try {
        const stats = await api.getTenantStats(id);
        const tenant = await api.getTenant(id);
        
        showModal(`Tenant Statistics: ${tenant.name}`, `
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Agents</h3>
                    <p class="stat-value">${stats.agents}</p>
                </div>
                <div class="stat-card">
                    <h3>Prompts</h3>
                    <p class="stat-value">${stats.prompts}</p>
                </div>
                <div class="stat-card">
                    <h3>Vector Stores</h3>
                    <p class="stat-value">${stats.vector_stores}</p>
                </div>
                <div class="stat-card">
                    <h3>Users</h3>
                    <p class="stat-value">${stats.users}</p>
                </div>
                <div class="stat-card">
                    <h3>Conversations</h3>
                    <p class="stat-value">${stats.conversations}</p>
                </div>
                <div class="stat-card">
                    <h3>Leads</h3>
                    <p class="stat-value">${stats.leads}</p>
                </div>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">
                    Close
                </button>
            </div>
        `);
    } catch (error) {
        showToast('Failed to load tenant stats: ' + error.message, 'error');
    }
}

async function suspendTenant(id) {
    if (!confirm('Are you sure you want to suspend this tenant? Their services will be disabled.')) {
        return;
    }
    
    try {
        await api.suspendTenant(id);
        showToast('Tenant suspended successfully');
        loadTenantsPage();
    } catch (error) {
        showToast('Failed to suspend tenant: ' + error.message, 'error');
    }
}

async function activateTenant(id) {
    try {
        await api.activateTenant(id);
        showToast('Tenant activated successfully');
        loadTenantsPage();
    } catch (error) {
        showToast('Failed to activate tenant: ' + error.message, 'error');
    }
}

async function deleteTenant(id) {
    const tenant = await api.getTenant(id);
    const stats = await api.getTenantStats(id);
    
    if (stats.total_resources > 0) {
        if (!confirm(`WARNING: This tenant has ${stats.total_resources} resources that will be permanently deleted. This action cannot be undone. Are you sure?`)) {
            return;
        }
    } else {
        if (!confirm('Are you sure you want to delete this tenant? This action cannot be undone.')) {
            return;
        }
    }
    
    try {
        await api.deleteTenant(id);
        showToast('Tenant deleted successfully');
        loadTenantsPage();
    } catch (error) {
        showToast('Failed to delete tenant: ' + error.message, 'error');
    }
}

// ==================== Audit Log Page ====================

async function loadAuditPage() {
    const content = document.getElementById('content');
    content.innerHTML = '<div class="spinner"></div>';
    
    try {
        const auditLogs = await api.listAuditLog(100);
        
        let html = `
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Audit Log</h3>
                    <button class="btn btn-primary" onclick="exportAuditLog()">Export CSV</button>
                </div>
                <div class="card-body">
        `;
        
        if (!auditLogs || auditLogs.length === 0) {
            html += `<p style="color: #6b7280; text-align: center; padding: 2rem;">No audit logs yet</p>`;
        } else {
            html += `
                <div style="overflow-x: auto;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>Actor</th>
                                <th>Action</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${auditLogs.map(log => `
                                <tr>
                                    <td style="white-space: nowrap; font-size: 0.875rem;">
                                        ${formatDate(log.created_at)}
                                    </td>
                                    <td>
                                        <code style="font-size: 0.875rem;">${log.actor}</code>
                                    </td>
                                    <td>
                                        <span class="badge badge-secondary">${log.action}</span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm" onclick="viewAuditDetails(${log.id})" title="View Details">
                                            👁️
                                        </button>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        }
        
        html += `
                </div>
            </div>
        `;
        
        content.innerHTML = html;
    } catch (error) {
        content.innerHTML = `<div class="card"><div class="card-body">Error loading audit log: ${error.message}</div></div>`;
        showToast('Failed to load audit log: ' + error.message, 'error');
    }
}

// Store audit logs for export
let cachedAuditLogs = [];

async function viewAuditDetails(logId) {
    try {
        const auditLogs = await api.listAuditLog(100);
        const log = auditLogs.find(l => l.id === logId);
        
        if (!log) {
            showToast('Audit log not found', 'error');
            return;
        }
        
        let payload = {};
        try {
            payload = JSON.parse(log.payload_json || '{}');
        } catch (e) {
            payload = { raw: log.payload_json };
        }
        
        const content = `
            <div class="form-group">
                <label class="form-label">Timestamp</label>
                <div>${formatDate(log.created_at)}</div>
            </div>
            <div class="form-group">
                <label class="form-label">Actor</label>
                <code>${log.actor}</code>
            </div>
            <div class="form-group">
                <label class="form-label">Action</label>
                <span class="badge badge-secondary">${log.action}</span>
            </div>
            <div class="form-group">
                <label class="form-label">Payload</label>
                <pre style="background: #1f2937; color: #e5e7eb; padding: 1rem; border-radius: 0.375rem; overflow-x: auto;">${JSON.stringify(payload, null, 2)}</pre>
            </div>
        `;
        
        openModal('Audit Log Details', content);
    } catch (error) {
        showToast('Failed to load audit details: ' + error.message, 'error');
    }
}

async function exportAuditLog() {
    try {
        const auditLogs = await api.listAuditLog(1000); // Get more records for export
        
        if (!auditLogs || auditLogs.length === 0) {
            showToast('No audit logs to export', 'info');
            return;
        }
        
        // Create CSV content
        const headers = ['Timestamp', 'Actor', 'Action', 'Payload'];
        const rows = auditLogs.map(log => [
            log.created_at,
            log.actor,
            log.action,
            log.payload_json || '{}'
        ]);
        
        const csv = [
            headers.join(','),
            ...rows.map(row => row.map(cell => `"${String(cell).replace(/"/g, '""')}"`).join(','))
        ].join('\n');
        
        // Download CSV
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `audit-log-${new Date().toISOString().split('T')[0]}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        
        showToast('Audit log exported successfully', 'success');
    } catch (error) {
        showToast('Failed to export audit log: ' + error.message, 'error');
    }
}

// ==================== Billing Page ====================

async function loadBillingPage() {
    const content = document.getElementById('content');
    content.innerHTML = '<div class="spinner"></div>';
    
    try {
        // Load data in parallel
        const [quotaStatus, usageStats, subscription, invoices, notifications] = await Promise.all([
            api.getQuotaStatus().catch(() => []),
            api.getUsageStats({
                start_date: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString()
            }).catch(() => ({ by_resource_type: [], totals: {} })),
            api.getSubscription().catch(() => null),
            api.listInvoices({ limit: 10 }).catch(() => []),
            api.listNotifications({ limit: 5, unread_only: true }).catch(() => [])
        ]);
        
        let html = `
            <div style="display: grid; gap: 1.5rem;">
                <!-- Summary Cards -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                    <div class="card">
                        <div class="card-body">
                            <h4 style="margin: 0 0 0.5rem 0; color: #6b7280; font-size: 0.875rem;">Current Plan</h4>
                            <div style="font-size: 1.5rem; font-weight: 600;">${subscription ? subscription.plan_type : 'No Plan'}</div>
                            ${subscription ? `<div style="color: #6b7280; font-size: 0.875rem; margin-top: 0.25rem;">
                                ${subscription.billing_cycle} • ${subscription.status}
                            </div>` : ''}
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-body">
                            <h4 style="margin: 0 0 0.5rem 0; color: #6b7280; font-size: 0.875rem;">Total Usage (30d)</h4>
                            <div style="font-size: 1.5rem; font-weight: 600;">${usageStats.totals.total_quantity || 0}</div>
                            <div style="color: #6b7280; font-size: 0.875rem; margin-top: 0.25rem;">
                                ${usageStats.totals.total_events || 0} events
                            </div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-body">
                            <h4 style="margin: 0 0 0.5rem 0; color: #6b7280; font-size: 0.875rem;">Active Quotas</h4>
                            <div style="font-size: 1.5rem; font-weight: 600;">${quotaStatus.length}</div>
                            <div style="color: #6b7280; font-size: 0.875rem; margin-top: 0.25rem;">
                                ${quotaStatus.filter(q => !q.allowed).length} exceeded
                            </div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-body">
                            <h4 style="margin: 0 0 0.5rem 0; color: #6b7280; font-size: 0.875rem;">Notifications</h4>
                            <div style="font-size: 1.5rem; font-weight: 600;">${notifications.length}</div>
                            <div style="color: #6b7280; font-size: 0.875rem; margin-top: 0.25rem;">Unread alerts</div>
                        </div>
                    </div>
                </div>
                
                <!-- Quota Status -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Quota Status</h3>
                        <button class="btn btn-primary btn-small" onclick="showCreateQuotaModal()">Add Quota</button>
                    </div>
                    <div class="card-body">
        `;
        
        if (quotaStatus.length === 0) {
            html += `<p style="color: #6b7280; text-align: center; padding: 2rem;">No quotas configured</p>`;
        } else {
            html += `
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Resource</th>
                                <th>Period</th>
                                <th>Usage/Limit</th>
                                <th>Progress</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${quotaStatus.map(quota => {
                                const progressColor = quota.percentage >= 90 ? '#ef4444' : 
                                                     quota.percentage >= 80 ? '#f59e0b' : 
                                                     '#10b981';
                                return `
                                    <tr>
                                        <td><code>${quota.resource_type}</code></td>
                                        <td>${quota.period}</td>
                                        <td>${quota.current}/${quota.limit} ${quota.is_hard_limit ? '🔒' : ''}</td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                <div style="flex: 1; height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden;">
                                                    <div style="height: 100%; background: ${progressColor}; width: ${Math.min(quota.percentage, 100)}%;"></div>
                                                </div>
                                                <span style="font-size: 0.875rem; min-width: 3rem; text-align: right;">${quota.percentage.toFixed(1)}%</span>
                                            </div>
                                        </td>
                                        <td>
                                            <button class="btn btn-small btn-danger" onclick="deleteQuota('${quota.id}')">Delete</button>
                                        </td>
                                    </tr>
                                `;
                            }).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        }
        
        html += `
                    </div>
                </div>
                
                <!-- Usage Stats -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Usage (Last 30 Days)</h3>
                    </div>
                    <div class="card-body">
        `;
        
        if (usageStats.by_resource_type.length === 0) {
            html += `<p style="color: #6b7280; text-align: center; padding: 2rem;">No usage data available</p>`;
        } else {
            html += `
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Resource Type</th>
                                <th>Events</th>
                                <th>Total Quantity</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${usageStats.by_resource_type.map(stat => `
                                <tr>
                                    <td><code>${stat.resource_type}</code></td>
                                    <td>${stat.event_count}</td>
                                    <td>${stat.total_quantity}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        }
        
        html += `</div></div></div>`;
        content.innerHTML = html;
    } catch (error) {
        console.error('Error loading billing page:', error);
        content.innerHTML = `<div class="card"><div class="card-body">Error: ${error.message}</div></div>`;
        showToast('Failed to load billing: ' + error.message, 'error');
    }
}

function showCreateQuotaModal() {
    const content = `
        <form onsubmit="createQuota(event)" id="create-quota-form">
            <div class="form-group">
                <label for="quota-resource-type">Resource Type *</label>
                <select id="quota-resource-type" name="resource_type" required class="form-control">
                    <option value="message">Message</option>
                    <option value="completion">Completion</option>
                    <option value="file_upload">File Upload</option>
                    <option value="vector_query">Vector Query</option>
                </select>
            </div>
            <div class="form-group">
                <label for="quota-limit">Limit *</label>
                <input type="number" id="quota-limit" name="limit_value" min="1" required class="form-control">
            </div>
            <div class="form-group">
                <label for="quota-period">Period *</label>
                <select id="quota-period" name="period" required class="form-control">
                    <option value="hourly">Hourly</option>
                    <option value="daily">Daily</option>
                    <option value="monthly">Monthly</option>
                </select>
            </div>
            <div class="form-group">
                <label><input type="checkbox" id="quota-hard-limit" name="is_hard_limit"> Hard Limit</label>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Create</button>
            </div>
        </form>
    `;
    openModal('Create Quota', content);
}

async function createQuota(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    
    const data = {
        resource_type: formData.get('resource_type'),
        limit_value: parseInt(formData.get('limit_value')),
        period: formData.get('period'),
        is_hard_limit: formData.get('is_hard_limit') === 'on',
        notification_threshold: 80
    };
    
    try {
        await api.setQuota(data);
        closeModal();
        showToast('Quota created', 'success');
        loadBillingPage();
    } catch (error) {
        showToast('Error: ' + error.message, 'error');
    }
}

async function deleteQuota(id) {
    if (!confirm('Delete this quota?')) return;
    try {
        await api.deleteQuota(id);
        showToast('Quota deleted', 'success');
        loadBillingPage();
    } catch (error) {
        showToast('Error: ' + error.message, 'error');
    }
}

// ==================== Settings Page ====================

async function loadSettingsPage() {
    const content = document.getElementById('content');
    content.innerHTML = '<div class="spinner"></div>';
    
    try {
        const health = await api.health();
        const sessionEmail = escapeHtml(authState.user?.email || '');

        const html = `
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">System Health</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Overall Status</label>
                        <div>
                            <span class="badge ${health.status === 'ok' ? 'badge-success' : 'badge-warning'}">
                                ${health.status.toUpperCase()}
                            </span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Database</label>
                        <div>
                            <span class="badge ${health.database ? 'badge-success' : 'badge-danger'}">
                                ${health.database ? 'Connected' : 'Disconnected'}
                            </span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">OpenAI API</label>
                        <div>
                            <span class="badge ${health.openai ? 'badge-success' : 'badge-warning'}">
                                ${health.openai ? 'Connected' : 'Not Available'}
                            </span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Last Check</label>
                        <div>${formatDate(health.timestamp)}</div>
                    </div>
                </div>
            </div>
            
            ${health.worker ? `
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Background Worker</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <div>
                                <span class="badge ${health.worker.enabled ? 'badge-success' : 'badge-warning'}">
                                    ${health.worker.enabled ? 'Enabled' : 'Disabled'}
                                </span>
                            </div>
                        </div>
                        
                        ${health.worker.stats ? `
                            <div class="form-group">
                                <label class="form-label">Queue Depth</label>
                                <div>${health.worker.queue_depth || 0} jobs</div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Job Statistics</label>
                                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.5rem; margin-top: 0.5rem;">
                                    <div style="padding: 0.5rem; background: #f3f4f6; border-radius: 0.375rem;">
                                        <div style="font-size: 0.75rem; color: #6b7280;">Pending</div>
                                        <div style="font-size: 1.25rem; font-weight: bold;">${health.worker.stats.pending || 0}</div>
                                    </div>
                                    <div style="padding: 0.5rem; background: #f3f4f6; border-radius: 0.375rem;">
                                        <div style="font-size: 0.75rem; color: #6b7280;">Running</div>
                                        <div style="font-size: 1.25rem; font-weight: bold;">${health.worker.stats.running || 0}</div>
                                    </div>
                                    <div style="padding: 0.5rem; background: #f3f4f6; border-radius: 0.375rem;">
                                        <div style="font-size: 0.75rem; color: #6b7280;">Completed</div>
                                        <div style="font-size: 1.25rem; font-weight: bold; color: #10b981;">${health.worker.stats.completed || 0}</div>
                                    </div>
                                    <div style="padding: 0.5rem; background: #f3f4f6; border-radius: 0.375rem;">
                                        <div style="font-size: 0.75rem; color: #6b7280;">Failed</div>
                                        <div style="font-size: 1.25rem; font-weight: bold; color: #ef4444;">${health.worker.stats.failed || 0}</div>
                                    </div>
                                </div>
                            </div>
                        ` : ''}
                        
                        <div style="margin-top: 1rem;">
                            <a href="#jobs" class="btn btn-primary" onclick="navigateTo('jobs')">View Jobs</a>
                        </div>
                    </div>
                </div>
            ` : ''}

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Sessions</h3>
                </div>
                <div class="card-body">
                    <p>Authentication uses email and password. Use the logout option below to end your current session.</p>
                    ${sessionEmail ? `<p class="form-help">Signed in as <strong>${sessionEmail}</strong>.</p>` : ''}
                    <button class="btn btn-secondary mt-2" data-action="logout-now">Sign out</button>
                </div>
            </div>
        `;

        content.innerHTML = html;
        const logoutNowButton = content.querySelector('[data-action="logout-now"]');
        if (logoutNowButton) {
            logoutNowButton.addEventListener('click', handleLogout);
        }
    } catch (error) {
        content.innerHTML = `<div class="card"><div class="card-body">Error loading settings: ${error.message}</div></div>`;
        showToast('Failed to load settings: ' + error.message, 'error');
    }
}

// ==================== Channel Management ====================

async function manageChannels(agentId, agentName) {
    try {
        const channels = await api.listAgentChannels(agentId);
        
        // Currently only WhatsApp is supported
        const whatsapp = channels.find(c => c.channel === 'whatsapp');
        
        const content = `
            <div class="channel-management">
                <h4>Agent: ${agentName}</h4>
                <p class="text-muted">Configure communication channels for this agent</p>
                
                <div class="channel-section">
                    <div class="channel-header">
                        <h5>📱 WhatsApp (via Z-API)</h5>
                        <button class="btn btn-small btn-primary" onclick="configureWhatsApp('${agentId}', ${whatsapp ? `'${whatsapp.id}'` : 'null'})">
                            ${whatsapp ? 'Edit Configuration' : 'Configure WhatsApp'}
                        </button>
                    </div>
                    
                    ${whatsapp ? `
                        <div class="channel-status">
                            <p><strong>Status:</strong> ${whatsapp.enabled ? '<span class="badge badge-success">Enabled</span>' : '<span class="badge badge-secondary">Disabled</span>'}</p>
                            <p><strong>Business Number:</strong> ${whatsapp.config.whatsapp_business_number || 'Not configured'}</p>
                            <p><strong>Instance ID:</strong> ${whatsapp.config.zapi_instance_id || 'N/A'}</p>
                            <p><strong>Webhook URL:</strong> <code>${window.location.origin}/channels/whatsapp/${agentId}/webhook</code></p>
                            
                            <div class="channel-actions">
                                <button class="btn btn-small btn-secondary" onclick="testWhatsAppMessage('${agentId}')">Send Test Message</button>
                                <button class="btn btn-small btn-info" onclick="viewChannelSessions('${agentId}', 'whatsapp')">View Sessions</button>
                                <button class="btn btn-small btn-danger" onclick="deleteChannelConfig('${agentId}', 'whatsapp')">Remove</button>
                            </div>
                        </div>
                    ` : `
                        <p class="text-muted">WhatsApp channel is not configured for this agent.</p>
                    `}
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Close</button>
                </div>
            </div>
        `;
        
        openModal('Manage Channels', content);
        
    } catch (error) {
        showToast('Failed to load channels: ' + error.message, 'error');
    }
}

async function configureWhatsApp(agentId, channelId) {
    // Load existing config if editing
    let config = {
        enabled: false,
        whatsapp_business_number: '',
        zapi_instance_id: '',
        zapi_token: '',
        zapi_base_url: 'https://api.z-api.io',
        zapi_timeout_ms: 30000,
        zapi_retries: 3,
        reply_chunk_size: 4000,
        allow_media_upload: true,
        max_media_size_bytes: 10485760,
        allowed_media_types: ['image/jpeg', 'image/png', 'application/pdf']
    };
    
    if (channelId) {
        try {
            const existing = await api.getAgentChannel(agentId, 'whatsapp');
            config = {...config, ...existing.config, enabled: existing.enabled};
        } catch (error) {
            console.error('Error loading channel config:', error);
        }
    }
    
    const content = `
        <form id="whatsapp-config-form" data-validation-status="idle" onsubmit="handleSaveWhatsAppConfig(event, '${agentId}')" oninput="resetWhatsAppValidationState(this)" onchange="resetWhatsAppValidationState(this)">
            <div class="form-group">
                <label class="form-checkbox">
                    <input type="checkbox" name="enabled" ${config.enabled ? 'checked' : ''} />
                    Enable WhatsApp Channel
                </label>
            </div>
            
            <div class="form-group">
                <label class="form-label">WhatsApp Business Number *</label>
                <input type="text" name="whatsapp_business_number" class="form-input" 
                       value="${config.whatsapp_business_number}" 
                       placeholder="+5511999999999" required />
                <small class="form-help">E.164 format (e.g., +5511999999999)</small>
            </div>
            
            <div class="form-group">
                <label class="form-label">Z-API Instance ID *</label>
                <input type="text" name="zapi_instance_id" class="form-input" 
                       value="${config.zapi_instance_id}" required />
            </div>
            
            <div class="form-group">
                <label class="form-label">Z-API Token *</label>
                <input type="password" name="zapi_token" class="form-input" 
                       value="${config.zapi_token}" required />
                <small class="form-help">Keep this secret!</small>
            </div>
            
            <div class="form-group">
                <label class="form-label">Z-API Base URL</label>
                <input type="text" name="zapi_base_url" class="form-input" 
                       value="${config.zapi_base_url}" />
            </div>
            
            <div class="form-group">
                <label class="form-label">Timeout (ms)</label>
                <input type="number" name="zapi_timeout_ms" class="form-input" 
                       value="${config.zapi_timeout_ms}" min="1000" />
            </div>
            
            <div class="form-group">
                <label class="form-label">Retries</label>
                <input type="number" name="zapi_retries" class="form-input" 
                       value="${config.zapi_retries}" min="1" max="10" />
            </div>
            
            <div class="form-group">
                <label class="form-label">Reply Chunk Size</label>
                <input type="number" name="reply_chunk_size" class="form-input" 
                       value="${config.reply_chunk_size}" min="100" />
                <small class="form-help">Long messages will be split into chunks</small>
            </div>
            
            <div class="form-group">
                <label class="form-checkbox">
                    <input type="checkbox" name="allow_media_upload" ${config.allow_media_upload ? 'checked' : ''} />
                    Allow Media Upload
                </label>
            </div>
            
            <div class="form-group">
                <label class="form-label">Max Media Size (bytes)</label>
                <input type="number" name="max_media_size_bytes" class="form-input" 
                       value="${config.max_media_size_bytes}" min="1" />
                <small class="form-help">10485760 = 10MB</small>
            </div>
            
            <div class="form-group">
                <label class="form-label">Allowed Media Types</label>
                <input type="text" name="allowed_media_types" class="form-input"
                       value="${config.allowed_media_types.join(', ')}" />
                <small class="form-help">Comma-separated MIME types</small>
            </div>

            <div class="form-group">
                <label class="form-label">Número para teste das credenciais</label>
                <input type="text" name="test_recipient" class="form-input" value="${config.whatsapp_business_number}" placeholder="+5511999999999" />
                <small class="form-help">Usado ao validar as credenciais antes de salvar.</small>
            </div>

            <div class="validation-message" id="whatsapp-validation-status"></div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="manageChannels('${agentId}', 'Agent')">Back</button>
                <button type="button" class="btn btn-outline" onclick="validateWhatsAppCredentialsFromForm('${agentId}')">Testar credenciais</button>
                <button type="submit" class="btn btn-primary">Save Configuration</button>
            </div>
        </form>
    `;
    
    openModal('Configure WhatsApp', content);
}

function resetWhatsAppValidationState(form) {
    if (!form || form.dataset.validationStatus === 'idle') {
        return;
    }
    form.dataset.validationStatus = 'idle';
    const statusEl = form.querySelector('.validation-message');
    if (statusEl) {
        statusEl.textContent = '';
        statusEl.className = 'validation-message';
    }
}

function collectWhatsAppFormData(form) {
    const formData = new FormData(form);
    const normalizeNumber = value => (value || '').trim();
    const allowedTypesInput = (formData.get('allowed_media_types') || '').split(',')
        .map(item => item.trim())
        .filter(Boolean);

    const data = {
        enabled: formData.get('enabled') === 'on',
        whatsapp_business_number: normalizeNumber(formData.get('whatsapp_business_number')),
        zapi_instance_id: normalizeNumber(formData.get('zapi_instance_id')),
        zapi_token: normalizeNumber(formData.get('zapi_token')),
        zapi_base_url: normalizeNumber(formData.get('zapi_base_url')) || 'https://api.z-api.io',
        zapi_timeout_ms: parseInt(formData.get('zapi_timeout_ms'), 10) || 30000,
        zapi_retries: parseInt(formData.get('zapi_retries'), 10) || 3,
        reply_chunk_size: parseInt(formData.get('reply_chunk_size'), 10) || 4000,
        allow_media_upload: formData.get('allow_media_upload') === 'on',
        max_media_size_bytes: parseInt(formData.get('max_media_size_bytes'), 10) || 10485760,
        allowed_media_types: allowedTypesInput,
        test_recipient: normalizeNumber(formData.get('test_recipient')) || normalizeNumber(formData.get('whatsapp_business_number'))
    };

    return data;
}

async function validateWhatsAppCredentialsFromForm(agentId) {
    const form = document.getElementById('whatsapp-config-form');
    if (!form) {
        return;
    }
    const data = collectWhatsAppFormData(form);
    await validateWhatsAppConfig(agentId, data, form);
}

async function validateWhatsAppConfig(agentId, data, form) {
    const statusEl = form.querySelector('.validation-message');
    const testRecipient = data.test_recipient;
    if (!testRecipient) {
        if (statusEl) {
            statusEl.textContent = 'Informe um número para teste antes de validar.';
            statusEl.className = 'validation-message error';
        }
        showToast('Informe um número para teste antes de validar.', 'error');
        return false;
    }

    if (statusEl) {
        statusEl.textContent = 'Validando credenciais…';
        statusEl.className = 'validation-message validating';
    }

    const payload = { ...data };
    delete payload.test_recipient;

    try {
        await api.testChannelSend(agentId, 'whatsapp', {
            to: testRecipient,
            message: 'Teste automático das credenciais do canal',
            config_override: { ...payload, enabled: true },
            validation_only: true
        });
        form.dataset.validationStatus = 'passed';
        if (statusEl) {
            statusEl.textContent = 'Credenciais validadas com sucesso';
            statusEl.className = 'validation-message success';
        }
        showToast('Credenciais verificadas. Agora você pode salvar.', 'success');
        return true;
    } catch (error) {
        form.dataset.validationStatus = 'failed';
        if (statusEl) {
            statusEl.textContent = `Falha na validação: ${error.message}`;
            statusEl.className = 'validation-message error';
        }
        showToast('Falha ao validar credenciais: ' + error.message, 'error');
        return false;
    }
}

async function handleSaveWhatsAppConfig(event, agentId) {
    event.preventDefault();

    const form = event.target;
    const data = collectWhatsAppFormData(form);
    const payload = { ...data };
    delete payload.test_recipient;

    if (form.dataset.validationStatus !== 'passed') {
        const validated = await validateWhatsAppConfig(agentId, data, form);
        if (!validated) {
            return;
        }
    }

    try {
        await api.upsertAgentChannel(agentId, 'whatsapp', payload);
        showToast('WhatsApp configuration saved successfully', 'success');
        closeModal();
        // Reopen the manage channels modal
        setTimeout(() => manageChannels(agentId, 'Agent'), 300);
    } catch (error) {
        showToast('Failed to save configuration: ' + error.message, 'error');
    }
}

async function testWhatsAppMessage(agentId) {
    const phone = prompt('Enter phone number to send test message (E.164 format, e.g., +5511999999999):');
    if (!phone) return;
    
    try {
        await api.testChannelSend(agentId, 'whatsapp', {
            to: phone,
            message: 'Test message from GPT Chatbot Admin'
        });
        showToast('Test message sent successfully!', 'success');
    } catch (error) {
        showToast('Failed to send test message: ' + error.message, 'error');
    }
}

async function viewChannelSessions(agentId, channel) {
    try {
        const sessions = await api.listChannelSessions(agentId, channel);
        
        let content = `
            <div class="channel-sessions">
                <h4>Active Sessions</h4>
                ${sessions.length === 0 ? '<p class="text-muted">No active sessions</p>' : ''}
                
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Conversation ID</th>
                            <th>Last Seen</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        sessions.forEach(session => {
            content += `
                <tr>
                    <td>${session.external_user_id}</td>
                    <td><code>${session.conversation_id}</code></td>
                    <td>${formatDate(session.last_seen_at)}</td>
                    <td>${formatDate(session.created_at)}</td>
                </tr>
            `;
        });
        
        content += `
                    </tbody>
                </table>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Close</button>
                </div>
            </div>
        `;
        
        openModal('Channel Sessions', content);
        
    } catch (error) {
        showToast('Failed to load sessions: ' + error.message, 'error');
    }
}

async function deleteChannelConfig(agentId, channel) {
    if (!confirm(`Are you sure you want to remove the ${channel} channel configuration?`)) {
        return;
    }
    
    try {
        await api.deleteAgentChannel(agentId, channel);
        showToast('Channel configuration removed successfully', 'success');
        closeModal();
    } catch (error) {
        showToast('Failed to remove channel: ' + error.message, 'error');
    }
}

// ==================== Initialization ====================

document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', handleLoginSubmit);
    }

    document.querySelectorAll('.nav-link[data-page]').forEach(link => {
        link.addEventListener('click', function(event) {
            event.preventDefault();
            const page = this.dataset.page;
            navigateTo(page);
        });
    });

    const logoutLink = document.getElementById('logout-link');
    if (logoutLink) {
        logoutLink.addEventListener('click', handleLogout);
    }

    window.addEventListener('hashchange', function() {
        const page = window.location.hash.substring(1) || 'agents';
        navigateTo(page);
    });

    updateRoleVisibility(authState.user?.role || null);
    initializeAuthentication();
});

// ========== Audit Conversations Page ==========

async function loadAuditConversationsPage() {
    const content = document.getElementById('content');
    
    try {
        const data = await api.listAuditConversations({ limit: 50 });
        const conversations = data.conversations || [];
        
        let html = `
            <div class="card">
                <div class="card-header">
                    <h3>Conversation Audit Trails</h3>
                    <div class="card-actions">
                        <button class="btn btn-small btn-secondary" onclick="exportAuditConversations()">Export CSV</button>
                        <button class="btn btn-small btn-secondary" onclick="loadAuditConversationsPage()">Refresh</button>
                    </div>
                </div>
                <div class="card-body">
        `;
        
        if (!conversations || conversations.length === 0) {
            html += `<p style="color: #6b7280; text-align: center; padding: 2rem;">No audit conversations yet</p>`;
        } else {
            html += `
                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Conversation ID</th>
                                <th>Agent</th>
                                <th>Channel</th>
                                <th>Started</th>
                                <th>Last Activity</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${conversations.map(conv => `
                                <tr>
                                    <td><code>${escapeHtml(conv.conversation_id)}</code></td>
                                    <td>${escapeHtml(conv.agent_id || 'N/A')}</td>
                                    <td>${escapeHtml(conv.channel)}</td>
                                    <td>${formatDate(conv.started_at)}</td>
                                    <td>${formatDate(conv.last_activity_at)}</td>
                                    <td>
                                        <button class="btn btn-small btn-secondary" onclick="viewAuditConversation('${escapeHtml(conv.conversation_id)}')">View</button>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        }
        
        html += `
                </div>
            </div>
        `;
        
        content.innerHTML = html;
    } catch (error) {
        console.error('Error loading audit conversations:', error);
        content.innerHTML = `<div class="card"><div class="card-body">Error loading audit conversations: ${error.message}</div></div>`;
        showToast('Failed to load audit conversations: ' + error.message, 'error');
    }
}

async function viewAuditConversation(conversationId) {
    try {
        const data = await api.getAuditConversation(conversationId, false);
        
        let modalContent = `
            <div style="max-height: 70vh; overflow-y: auto;">
                <h4>Conversation Details</h4>
                <div style="margin-bottom: 1rem;">
                    <strong>Conversation ID:</strong> <code>${escapeHtml(conversationId)}</code><br>
                    <strong>Agent ID:</strong> ${escapeHtml(data.conversation.agent_id || 'N/A')}<br>
                    <strong>Channel:</strong> ${escapeHtml(data.conversation.channel)}<br>
                    <strong>Started:</strong> ${formatDate(data.conversation.started_at)}<br>
                </div>
                
                <h4>Messages</h4>
                <div style="margin-bottom: 1rem;">
        `;
        
        if (data.messages && data.messages.length > 0) {
            data.messages.forEach(msg => {
                const bgColor = msg.role === 'user' ? '#f3f4f6' : '#e0f2fe';
                modalContent += `
                    <div style="padding: 0.75rem; margin-bottom: 0.5rem; background: ${bgColor}; border-radius: 4px;">
                        <strong>${escapeHtml(msg.role)}:</strong><br>
                        <div style="margin-top: 0.25rem; font-family: monospace; font-size: 0.875rem;">
                            ${msg.content ? escapeHtml(msg.content) : '[ENCRYPTED - request decryption for full content]'}<br>
                            <small style="color: #6b7280;">Hash: ${msg.content_hash ? msg.content_hash.substring(0, 16) + '...' : 'N/A'}</small>
                        </div>
                        ${msg.response_meta_json ? `
                            <details style="margin-top: 0.5rem;">
                                <summary style="cursor: pointer; color: #3b82f6;">Response Metadata</summary>
                                <pre style="margin-top: 0.5rem; font-size: 0.75rem; overflow-x: auto;">${escapeHtml(JSON.stringify(JSON.parse(msg.response_meta_json), null, 2))}</pre>
                            </details>
                        ` : ''}
                    </div>
                `;
            });
        } else {
            modalContent += `<p>No messages found.</p>`;
        }
        
        modalContent += `
                </div>
                
                <h4>Events</h4>
                <div>
        `;
        
        if (data.events && data.events.length > 0) {
            modalContent += `
                <table style="width: 100%; font-size: 0.875rem;">
                    <thead>
                        <tr style="background: #f3f4f6;">
                            <th style="padding: 0.5rem; text-align: left;">Type</th>
                            <th style="padding: 0.5rem; text-align: left;">Time</th>
                            <th style="padding: 0.5rem; text-align: left;">Details</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            data.events.forEach(evt => {
                modalContent += `
                    <tr>
                        <td style="padding: 0.5rem;"><code>${escapeHtml(evt.type)}</code></td>
                        <td style="padding: 0.5rem;">${formatDate(evt.created_at)}</td>
                        <td style="padding: 0.5rem;">
                            ${evt.payload_json ? `<details><summary style="cursor: pointer;">View</summary><pre style="font-size: 0.75rem; max-width: 400px; overflow-x: auto;">${escapeHtml(JSON.stringify(JSON.parse(evt.payload_json), null, 2))}</pre></details>` : 'N/A'}
                        </td>
                    </tr>
                `;
            });
            
            modalContent += `
                    </tbody>
                </table>
            `;
        } else {
            modalContent += `<p>No events found.</p>`;
        }
        
        modalContent += `
                </div>
            </div>
        `;
        
        showModal('Conversation Audit Trail', modalContent);
    } catch (error) {
        console.error('Error viewing audit conversation:', error);
        showToast('Failed to load conversation details: ' + error.message, 'error');
    }
}

function exportAuditConversations() {
    try {
        api.exportAuditData();
        showToast('Export started', 'success');
    } catch (error) {
        console.error('Error exporting audit data:', error);
        showToast('Failed to export audit data: ' + error.message, 'error');
    }
}

function formatDate(dateStr) {
    if (!dateStr) return 'N/A';
    try {
        const date = new Date(dateStr);
        return date.toLocaleString();
    } catch {
        return dateStr;
    }
}

// ==================== WhatsApp Templates Page ====================

async function loadWhatsAppTemplatesPage() {
    const content = document.getElementById('content');
    content.innerHTML = `
        <div class="page-header">
            <h2>WhatsApp Templates</h2>
            <button class="btn btn-primary" onclick="showCreateTemplateModal()">
                <span>➕</span> Create Template
            </button>
        </div>
        
        <div class="filters-bar">
            <select id="template-status-filter" class="filter-select" onchange="filterTemplates()">
                <option value="">All Status</option>
                <option value="draft">Draft</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
            </select>
            
            <select id="template-category-filter" class="filter-select" onchange="filterTemplates()">
                <option value="">All Categories</option>
                <option value="MARKETING">Marketing</option>
                <option value="UTILITY">Utility</option>
                <option value="AUTHENTICATION">Authentication</option>
                <option value="SERVICE">Service</option>
            </select>
            
            <input type="text" id="template-search" class="filter-input" placeholder="Search templates..." onkeyup="filterTemplates()">
        </div>
        
        <div id="templates-list" class="card">
            <div class="loading">Loading templates...</div>
        </div>
    `;
    
    loadTemplates();
}

async function loadTemplates() {
    try {
        const response = await authFetch(`${API_BASE_URL}/admin-api.php?action=list_templates`);

        if (!response.ok) {
            throw new Error('Failed to load templates');
        }
        
        const data = await response.json();
        displayTemplates(data || []);
    } catch (error) {
        console.error('Error loading templates:', error);
        document.getElementById('templates-list').innerHTML = `
            <div class="error-message">Failed to load templates: ${error.message}</div>
        `;
    }
}

function displayTemplates(templates) {
    const container = document.getElementById('templates-list');
    
    if (templates.length === 0) {
        container.innerHTML = '<div class="empty-state">No templates found</div>';
        return;
    }
    
    const html = `
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Language</th>
                    <th>Status</th>
                    <th>Quality</th>
                    <th>Usage</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                ${templates.map(t => `
                    <tr data-template-id="${t.id}" data-status="${t.status}" data-category="${t.template_category}" data-name="${escapeHtml(t.template_name)}">
                        <td><strong>${escapeHtml(t.template_name)}</strong></td>
                        <td>${t.template_category}</td>
                        <td>${t.language_code}</td>
                        <td><span class="badge badge-${getStatusBadgeClass(t.status)}">${t.status}</span></td>
                        <td>${t.quality_score || 'N/A'}</td>
                        <td>${t.usage_count || 0}</td>
                        <td>${formatDate(t.created_at)}</td>
                        <td>
                            <button class="btn btn-small" onclick="viewTemplate('${t.id}')">View</button>
                            ${t.status === 'draft' ? `<button class="btn btn-small btn-primary" onclick="submitTemplate('${t.id}')">Submit</button>` : ''}
                            ${t.status === 'draft' || t.status === 'rejected' ? `<button class="btn btn-small btn-danger" onclick="deleteTemplate('${t.id}')">Delete</button>` : ''}
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
    
    container.innerHTML = html;
}

function getStatusBadgeClass(status) {
    const classes = {
        'draft': 'secondary',
        'pending': 'warning',
        'approved': 'success',
        'rejected': 'danger',
        'paused': 'warning',
        'disabled': 'secondary'
    };
    return classes[status] || 'secondary';
}

function filterTemplates() {
    const statusFilter = document.getElementById('template-status-filter').value;
    const categoryFilter = document.getElementById('template-category-filter').value;
    const searchText = document.getElementById('template-search').value.toLowerCase();
    
    const rows = document.querySelectorAll('#templates-list tbody tr');
    rows.forEach(row => {
        const status = row.dataset.status;
        const category = row.dataset.category;
        const name = row.dataset.name.toLowerCase();
        
        const statusMatch = !statusFilter || status === statusFilter;
        const categoryMatch = !categoryFilter || category === categoryFilter;
        const searchMatch = !searchText || name.includes(searchText);
        
        row.style.display = (statusMatch && categoryMatch && searchMatch) ? '' : 'none';
    });
}

function showCreateTemplateModal() {
    const modalContent = `
        <form id="create-template-form" onsubmit="createTemplate(event)">
            <div class="form-group">
                <label for="template-name">Template Name *</label>
                <input type="text" id="template-name" name="template_name" required placeholder="welcome_message">
            </div>
            
            <div class="form-group">
                <label for="template-category">Category *</label>
                <select id="template-category" name="template_category" required>
                    <option value="UTILITY">Utility</option>
                    <option value="MARKETING">Marketing</option>
                    <option value="AUTHENTICATION">Authentication</option>
                    <option value="SERVICE">Service</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="template-language">Language *</label>
                <select id="template-language" name="language_code" required>
                    <option value="en">English</option>
                    <option value="pt_BR">Portuguese (Brazil)</option>
                    <option value="es">Spanish</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="template-content">Content *</label>
                <textarea id="template-content" name="content_text" rows="5" required 
                    placeholder="Hi {{1}}! Welcome to our service..."></textarea>
                <small>Use {{1}}, {{2}}, etc. for variables</small>
            </div>
            
            <div class="form-group">
                <label for="template-header">Header Text (Optional)</label>
                <input type="text" id="template-header" name="header_text" placeholder="Welcome!">
            </div>
            
            <div class="form-group">
                <label for="template-footer">Footer Text (Optional)</label>
                <input type="text" id="template-footer" name="footer_text" placeholder="Reply STOP to unsubscribe">
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Template</button>
            </div>
        </form>
    `;
    
    showModal('Create WhatsApp Template', modalContent);
}

async function createTemplate(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    try {
        const response = await authFetch(`${API_BASE_URL}/admin-api.php?action=create_template`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.error || 'Failed to create template');
        }
        
        showToast('Template created successfully', 'success');
        closeModal();
        loadTemplates();
    } catch (error) {
        console.error('Error creating template:', error);
        showToast('Failed to create template: ' + error.message, 'error');
    }
}

async function viewTemplate(templateId) {
    try {
        const response = await authFetch(`${API_BASE_URL}/admin-api.php?action=get_template&id=${templateId}`);
        
        if (!response.ok) {
            throw new Error('Failed to load template');
        }
        
        const template = await response.json();
        
        const modalContent = `
            <div class="template-details">
                <div class="detail-row">
                    <strong>Name:</strong> ${escapeHtml(template.template_name)}
                </div>
                <div class="detail-row">
                    <strong>Category:</strong> ${template.template_category}
                </div>
                <div class="detail-row">
                    <strong>Language:</strong> ${template.language_code}
                </div>
                <div class="detail-row">
                    <strong>Status:</strong> <span class="badge badge-${getStatusBadgeClass(template.status)}">${template.status}</span>
                </div>
                ${template.quality_score ? `
                <div class="detail-row">
                    <strong>Quality Score:</strong> ${template.quality_score}
                </div>
                ` : ''}
                ${template.rejection_reason ? `
                <div class="detail-row">
                    <strong>Rejection Reason:</strong> <span style="color: var(--danger-color);">${escapeHtml(template.rejection_reason)}</span>
                </div>
                ` : ''}
                <div class="detail-row">
                    <strong>Usage Count:</strong> ${template.usage_count || 0}
                </div>
                ${template.header_text ? `
                <div class="detail-row">
                    <strong>Header:</strong> ${escapeHtml(template.header_text)}
                </div>
                ` : ''}
                <div class="detail-row">
                    <strong>Content:</strong>
                    <pre style="margin-top: 0.5rem; padding: 1rem; background: var(--bg-secondary); border-radius: 4px; white-space: pre-wrap;">${escapeHtml(template.content_text)}</pre>
                </div>
                ${template.footer_text ? `
                <div class="detail-row">
                    <strong>Footer:</strong> ${escapeHtml(template.footer_text)}
                </div>
                ` : ''}
                <div class="detail-row">
                    <strong>Created:</strong> ${formatDate(template.created_at)}
                </div>
                ${template.submitted_at ? `
                <div class="detail-row">
                    <strong>Submitted:</strong> ${formatDate(template.submitted_at)}
                </div>
                ` : ''}
                ${template.approved_at ? `
                <div class="detail-row">
                    <strong>Approved:</strong> ${formatDate(template.approved_at)}
                </div>
                ` : ''}
            </div>
        `;
        
        showModal('Template Details', modalContent);
    } catch (error) {
        console.error('Error viewing template:', error);
        showToast('Failed to load template: ' + error.message, 'error');
    }
}

async function submitTemplate(templateId) {
    if (!confirm('Submit this template for WhatsApp approval?')) return;

    try {
        const response = await authFetch(`${API_BASE_URL}/admin-api.php?action=submit_template&id=${templateId}`, {
            method: 'POST'
        });
        
        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.error || 'Failed to submit template');
        }
        
        showToast('Template submitted for approval', 'success');
        loadTemplates();
    } catch (error) {
        console.error('Error submitting template:', error);
        showToast('Failed to submit template: ' + error.message, 'error');
    }
}

async function deleteTemplate(templateId) {
    if (!confirm('Delete this template? This action cannot be undone.')) return;

    try {
        const response = await authFetch(`${API_BASE_URL}/admin-api.php?action=delete_template&id=${templateId}`, {
            method: 'DELETE'
        });
        
        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.error || 'Failed to delete template');
        }
        
        showToast('Template deleted successfully', 'success');
        loadTemplates();
    } catch (error) {
        console.error('Error deleting template:', error);
        showToast('Failed to delete template: ' + error.message, 'error');
    }
}

// ==================== Consent Management Page ====================

async function loadConsentManagementPage() {
    const content = document.getElementById('content');
    content.innerHTML = `
        <div class="page-header">
            <h2>Consent Management</h2>
            <button class="btn btn-primary" onclick="exportConsents()">
                <span>📥</span> Export Consents
            </button>
        </div>
        
        <div class="filters-bar">
            <select id="consent-status-filter" class="filter-select" onchange="filterConsents()">
                <option value="">All Status</option>
                <option value="granted">Granted</option>
                <option value="withdrawn">Withdrawn</option>
                <option value="pending">Pending</option>
                <option value="denied">Denied</option>
            </select>
            
            <select id="consent-type-filter" class="filter-select" onchange="filterConsents()">
                <option value="">All Types</option>
                <option value="service">Service</option>
                <option value="marketing">Marketing</option>
                <option value="analytics">Analytics</option>
            </select>
            
            <select id="consent-channel-filter" class="filter-select" onchange="filterConsents()">
                <option value="">All Channels</option>
                <option value="whatsapp">WhatsApp</option>
            </select>
            
            <input type="text" id="consent-search" class="filter-input" placeholder="Search by user ID..." onkeyup="filterConsents()">
        </div>
        
        <div id="consents-list" class="card">
            <div class="loading">Loading consents...</div>
        </div>
    `;
    
    loadConsents();
}

async function loadConsents() {
    try {
        const response = await authFetch(`${API_BASE_URL}/admin-api.php?action=list_consents`);
        
        if (!response.ok) {
            throw new Error('Failed to load consents');
        }
        
        const data = await response.json();
        displayConsents(data || []);
    } catch (error) {
        console.error('Error loading consents:', error);
        document.getElementById('consents-list').innerHTML = `
            <div class="error-message">Failed to load consents: ${error.message}</div>
        `;
    }
}

function displayConsents(consents) {
    const container = document.getElementById('consents-list');
    
    if (consents.length === 0) {
        container.innerHTML = '<div class="empty-state">No consent records found</div>';
        return;
    }
    
    const html = `
        <table class="data-table">
            <thead>
                <tr>
                    <th>User ID</th>
                    <th>Channel</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Method</th>
                    <th>Granted At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                ${consents.map(c => `
                    <tr data-consent-id="${c.id}" data-status="${c.consent_status}" data-type="${c.consent_type}" data-channel="${c.channel}" data-user="${c.external_user_id}">
                        <td><code>${escapeHtml(c.external_user_id)}</code></td>
                        <td>${c.channel}</td>
                        <td>${c.consent_type}</td>
                        <td><span class="badge badge-${getConsentBadgeClass(c.consent_status)}">${c.consent_status}</span></td>
                        <td>${c.consent_method}</td>
                        <td>${formatDate(c.granted_at)}</td>
                        <td>
                            <button class="btn btn-small" onclick="viewConsent('${c.id}')">View</button>
                            <button class="btn btn-small" onclick="viewConsentAudit('${c.id}')">Audit</button>
                            ${c.consent_status === 'granted' ? `<button class="btn btn-small btn-danger" onclick="withdrawConsent('${c.id}')">Withdraw</button>` : ''}
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
    
    container.innerHTML = html;
}

function getConsentBadgeClass(status) {
    const classes = {
        'granted': 'success',
        'withdrawn': 'danger',
        'pending': 'warning',
        'denied': 'secondary'
    };
    return classes[status] || 'secondary';
}

function filterConsents() {
    const statusFilter = document.getElementById('consent-status-filter').value;
    const typeFilter = document.getElementById('consent-type-filter').value;
    const channelFilter = document.getElementById('consent-channel-filter').value;
    const searchText = document.getElementById('consent-search').value.toLowerCase();
    
    const rows = document.querySelectorAll('#consents-list tbody tr');
    rows.forEach(row => {
        const status = row.dataset.status;
        const type = row.dataset.type;
        const channel = row.dataset.channel;
        const userId = row.dataset.user.toLowerCase();
        
        const statusMatch = !statusFilter || status === statusFilter;
        const typeMatch = !typeFilter || type === typeFilter;
        const channelMatch = !channelFilter || channel === channelFilter;
        const searchMatch = !searchText || userId.includes(searchText);
        
        row.style.display = (statusMatch && typeMatch && channelMatch && searchMatch) ? '' : 'none';
    });
}

async function viewConsent(consentId) {
    try {
        const response = await authFetch(`${API_BASE_URL}/admin-api.php?action=get_consent_by_id&id=${consentId}`);
        
        if (!response.ok) {
            throw new Error('Failed to load consent');
        }
        
        const consent = await response.json();
        
        const modalContent = `
            <div class="consent-details">
                <div class="detail-row">
                    <strong>User ID:</strong> <code>${escapeHtml(consent.external_user_id)}</code>
                </div>
                <div class="detail-row">
                    <strong>Channel:</strong> ${consent.channel}
                </div>
                <div class="detail-row">
                    <strong>Type:</strong> ${consent.consent_type}
                </div>
                <div class="detail-row">
                    <strong>Status:</strong> <span class="badge badge-${getConsentBadgeClass(consent.consent_status)}">${consent.consent_status}</span>
                </div>
                <div class="detail-row">
                    <strong>Method:</strong> ${consent.consent_method}
                </div>
                <div class="detail-row">
                    <strong>Legal Basis:</strong> ${consent.legal_basis || 'N/A'}
                </div>
                ${consent.consent_text ? `
                <div class="detail-row">
                    <strong>Consent Text:</strong>
                    <div style="margin-top: 0.5rem; padding: 1rem; background: var(--bg-secondary); border-radius: 4px;">${escapeHtml(consent.consent_text)}</div>
                </div>
                ` : ''}
                <div class="detail-row">
                    <strong>Language:</strong> ${consent.consent_language}
                </div>
                <div class="detail-row">
                    <strong>Granted At:</strong> ${formatDate(consent.granted_at)}
                </div>
                ${consent.withdrawn_at ? `
                <div class="detail-row">
                    <strong>Withdrawn At:</strong> ${formatDate(consent.withdrawn_at)}
                </div>
                ` : ''}
                ${consent.expires_at ? `
                <div class="detail-row">
                    <strong>Expires At:</strong> ${formatDate(consent.expires_at)}
                </div>
                ` : ''}
                ${consent.ip_address ? `
                <div class="detail-row">
                    <strong>IP Address:</strong> <code>${escapeHtml(consent.ip_address)}</code>
                </div>
                ` : ''}
            </div>
        `;
        
        showModal('Consent Details', modalContent);
    } catch (error) {
        console.error('Error viewing consent:', error);
        showToast('Failed to load consent: ' + error.message, 'error');
    }
}

async function viewConsentAudit(consentId) {
    try {
        const response = await authFetch(`${API_BASE_URL}/admin-api.php?action=get_consent_audit&id=${consentId}`);
        
        if (!response.ok) {
            throw new Error('Failed to load consent audit');
        }
        
        const auditLog = await response.json();
        
        const modalContent = `
            <div class="audit-log">
                ${auditLog.length > 0 ? `
                    <table class="data-table" style="width: 100%;">
                        <thead>
                            <tr>
                                <th>Action</th>
                                <th>Previous Status</th>
                                <th>New Status</th>
                                <th>Reason</th>
                                <th>Triggered By</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${auditLog.map(log => `
                                <tr>
                                    <td>${log.action}</td>
                                    <td>${log.previous_status || 'N/A'}</td>
                                    <td>${log.new_status}</td>
                                    <td>${escapeHtml(log.reason || '')}</td>
                                    <td>${log.triggered_by}</td>
                                    <td>${formatDate(log.created_at)}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                ` : '<p>No audit log entries found.</p>'}
            </div>
        `;
        
        showModal('Consent Audit Log', modalContent);
    } catch (error) {
        console.error('Error viewing consent audit:', error);
        showToast('Failed to load consent audit: ' + error.message, 'error');
    }
}

async function withdrawConsent(consentId) {
    if (!confirm('Withdraw this consent? The user will no longer receive messages.')) return;
    
    try {
        const response = await authFetch(`${API_BASE_URL}/admin-api.php?action=withdraw_consent_by_id&id=${consentId}`, {
            method: 'POST'
        });
        
        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.error || 'Failed to withdraw consent');
        }
        
        showToast('Consent withdrawn successfully', 'success');
        loadConsents();
    } catch (error) {
        console.error('Error withdrawing consent:', error);
        showToast('Failed to withdraw consent: ' + error.message, 'error');
    }
}

async function exportConsents() {
    try {
        showToast('Preparing export...', 'info');

        const response = await authFetch(`${API_BASE_URL}/admin-api.php?action=export_consents`);
        
        if (!response.ok) {
            throw new Error('Failed to export consents');
        }
        
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `consents_export_${new Date().toISOString().split('T')[0]}.csv`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        
        showToast('Consents exported successfully', 'success');
    } catch (error) {
        console.error('Error exporting consents:', error);
        showToast('Failed to export consents: ' + error.message, 'error');
    }
}

// ==================== Users Page ====================

function getTenantDisplayName(tenant) {
    if (!tenant) {
        return '—';
    }
    return tenant.name || tenant.display_name || tenant.slug || tenant.id || '—';
}

function buildTenantMap(tenants = []) {
    const map = new Map();
    tenants.forEach(tenant => {
        if (tenant && tenant.id) {
            map.set(tenant.id, getTenantDisplayName(tenant));
        }
    });
    return map;
}

function renderUsersPage() {
    const content = document.getElementById('content');
    if (!content) {
        return;
    }

    if (usersPageState.isLoading && usersPageState.users.length === 0) {
        content.innerHTML = `
            <div class="card">
                <div class="card-body">
                    <div class="card-loading"><div class="spinner"></div></div>
                </div>
            </div>
        `;
        return;
    }

    if (usersPageState.lastError && usersPageState.users.length === 0) {
        content.innerHTML = `
            <div class="card">
                <div class="card-body">
                    <div class="empty-state">
                        <div class="empty-state-icon">⚠️</div>
                        <div class="empty-state-text">${escapeHtml(usersPageState.lastError.message || 'Failed to load users')}</div>
                        <button class="btn btn-primary mt-2" data-action="refresh-users">Try again</button>
                    </div>
                </div>
            </div>
        `;
        requestAnimationFrame(attachUsersPageEvents);
        return;
    }

    const tenants = usersPageState.tenants || [];
    const tenantMap = buildTenantMap(tenants);
    const currentUserId = authState.user?.id;

    let usersTableContent = '';

    if (usersPageState.isLoading && usersPageState.users.length > 0) {
        usersTableContent = '<div class="card-loading"><div class="spinner"></div></div>';
    } else if (!usersPageState.users || usersPageState.users.length === 0) {
        usersTableContent = `
            <div class="empty-state">
                <div class="empty-state-icon">🧑‍🤝‍🧑</div>
                <div class="empty-state-text">No users registered yet.</div>
            </div>
        `;
    } else {
        const rows = usersPageState.users.map(user => {
            const tenantLabel = user.role === SUPER_ADMIN_ROLE
                ? '<span class="badge badge-secondary">All tenants</span>'
                : escapeHtml(tenantMap.get(user.tenant_id) || '—');

            const statusClass = user.is_active ? 'active' : 'inactive';
            const statusLabel = user.is_active ? 'Active' : 'Deactivated';
            const canDeactivate = user.is_active && user.id !== currentUserId;
            const disableRoleChange = user.id === currentUserId;

            const roleOptions = [
                { value: 'viewer', label: 'Viewer' },
                { value: 'admin', label: 'Admin' },
                { value: 'super-admin', label: 'Super Admin' }
            ].map(option => `
                <option value="${option.value}" ${user.role === option.value ? 'selected' : ''}>${option.label}</option>
            `).join('');

            const createdAt = user.created_at ? formatDate(user.created_at) : '—';

            return `
                <tr data-user-id="${user.id}">
                    <td>
                        <div class="user-email">${escapeHtml(user.email)}</div>
                        <div class="user-meta">${user.role === SUPER_ADMIN_ROLE ? 'Global access' : 'Tenant scoped'}</div>
                    </td>
                    <td>
                        <select class="form-select user-role-select" data-user-role-select="true" data-user-id="${user.id}" data-original-role="${user.role}" data-user-email="${escapeHtml(user.email)}" ${disableRoleChange ? 'disabled' : ''}>
                            ${roleOptions}
                        </select>
                    </td>
                    <td>${tenantLabel}</td>
                    <td><span class="user-status-pill ${statusClass}">${statusLabel}</span></td>
                    <td>${escapeHtml(createdAt)}</td>
                    <td>
                        ${canDeactivate ? `<button class="btn btn-small btn-danger" data-action="deactivate-user" data-user-id="${user.id}" data-user-email="${escapeHtml(user.email)}">Deactivate</button>` : '<span class="text-muted">—</span>'}
                    </td>
                </tr>
            `;
        }).join('');

        usersTableContent = `
            <div class="table-wrapper">
                <table class="data-table users-table">
                    <thead>
                        <tr>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Tenant</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>
        `;
    }

    const tenantOptions = tenants.map(tenant => `
        <option value="${tenant.id}">${escapeHtml(getTenantDisplayName(tenant))}</option>
    `).join('');

    const errorMessage = usersPageState.lastError && usersPageState.users.length > 0
        ? `<div class="users-inline-error">${escapeHtml(usersPageState.lastError.message || 'Some data may be outdated.')} <button type="button" class="btn btn-small btn-secondary" data-action="refresh-users">Refresh</button></div>`
        : '';

    content.innerHTML = `
        <div class="users-page">
            <div class="page-header">
                <h2>Users</h2>
                <div class="page-actions">
                    <button class="btn btn-secondary" data-action="refresh-users">Refresh</button>
                </div>
            </div>
            ${errorMessage}
            <div class="users-grid">
                <section class="card users-card">
                    <div class="card-header">
                        <div>
                            <h3>Team members</h3>
                            <p class="card-subtitle">Manage access to the admin workspace.</p>
                        </div>
                        <button class="btn btn-small btn-secondary" data-action="refresh-users">Refresh</button>
                    </div>
                    <div class="card-body">
                        ${usersTableContent}
                    </div>
                </section>
                <section class="card create-user-card">
                    <div class="card-header">
                        <div>
                            <h3>Create user</h3>
                            <p class="card-subtitle">Invite a new admin or viewer for a tenant.</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <form id="create-user-form" class="create-user-form">
                            <div class="form-group">
                                <label class="form-label" for="create-user-email">Email</label>
                                <input type="email" id="create-user-email" name="email" class="form-input" placeholder="admin@example.com" required autocomplete="off" />
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="create-user-role">Role</label>
                                <select id="create-user-role" name="role" class="form-select" required>
                                    <option value="admin" selected>Admin</option>
                                    <option value="viewer">Viewer</option>
                                    <option value="super-admin">Super Admin</option>
                                </select>
                            </div>
                            <div class="form-group" id="create-user-tenant-group">
                                <label class="form-label" for="create-user-tenant">Tenant</label>
                                <select id="create-user-tenant" name="tenant_id" class="form-select">
                                    <option value="">Select a tenant</option>
                                    ${tenantOptions}
                                </select>
                                <small class="form-help">Required for admins and viewers.</small>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="create-user-password">Password</label>
                                <input type="password" id="create-user-password" name="password" class="form-input" placeholder="Temporary password" required />
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="create-user-password-confirm">Confirm password</label>
                                <input type="password" id="create-user-password-confirm" name="password_confirm" class="form-input" placeholder="Repeat password" required />
                            </div>
                            <p class="form-error" id="create-user-error" aria-live="assertive"></p>
                            <button type="submit" class="btn btn-primary btn-full" id="create-user-submit">Create user</button>
                        </form>
                    </div>
                </section>
            </div>
        </div>
    `;

    requestAnimationFrame(attachUsersPageEvents);
}

function attachUsersPageEvents() {
    const form = document.getElementById('create-user-form');
    if (form) {
        form.addEventListener('submit', handleCreateUserSubmit);
    }

    const roleSelect = document.getElementById('create-user-role');
    if (roleSelect) {
        roleSelect.addEventListener('change', event => toggleCreateUserTenantField(event.target.value));
        toggleCreateUserTenantField(roleSelect.value);
    }

    document.querySelectorAll('[data-user-role-select="true"]').forEach(select => {
        select.addEventListener('change', handleUserRoleChange);
    });

    document.querySelectorAll('[data-action="deactivate-user"]').forEach(button => {
        button.addEventListener('click', handleDeactivateUserClick);
    });

    document.querySelectorAll('[data-action="refresh-users"]').forEach(button => {
        button.addEventListener('click', () => loadUsersPage());
    });
}

function toggleCreateUserTenantField(role) {
    const tenantGroup = document.getElementById('create-user-tenant-group');
    const tenantSelect = document.getElementById('create-user-tenant');

    if (!tenantGroup || !tenantSelect) {
        return;
    }

    const requireTenant = role !== SUPER_ADMIN_ROLE;
    tenantGroup.classList.toggle('is-disabled', !requireTenant);
    tenantSelect.required = requireTenant;

    if (!requireTenant) {
        tenantSelect.value = '';
    }
}

async function loadUsersPage() {
    if (!authState.user || authState.user.role !== SUPER_ADMIN_ROLE) {
        const content = document.getElementById('content');
        if (content) {
            content.innerHTML = `
                <div class="card">
                    <div class="card-body">
                        <div class="empty-state">
                            <div class="empty-state-icon">🔒</div>
                            <div class="empty-state-text">Users management is limited to super-admins.</div>
                        </div>
                    </div>
                </div>
            `;
        }
        return;
    }

    usersPageState.isLoading = true;
    renderUsersPage();

    try {
        const [users, tenants] = await Promise.all([
            api.listUsers(),
            api.listTenants()
        ]);
        usersPageState.users = Array.isArray(users) ? users : [];
        usersPageState.tenants = Array.isArray(tenants) ? tenants : [];
        usersPageState.lastError = null;
    } catch (error) {
        usersPageState.lastError = error;
        console.error('Failed to load users:', error);
        showToast('Failed to load users: ' + error.message, 'error');
    } finally {
        usersPageState.isLoading = false;
        renderUsersPage();
    }
}

async function handleCreateUserSubmit(event) {
    event.preventDefault();

    const form = event.target;
    const formData = new FormData(form);
    const email = (formData.get('email') || '').toString().trim();
    const role = (formData.get('role') || 'admin').toString();
    const tenantIdRaw = (formData.get('tenant_id') || '').toString();
    const password = (formData.get('password') || '').toString();
    const passwordConfirm = (formData.get('password_confirm') || '').toString();
    const errorElement = document.getElementById('create-user-error');
    const submitButton = document.getElementById('create-user-submit');

    if (errorElement) {
        errorElement.textContent = '';
    }

    if (!email || !password) {
        if (errorElement) {
            errorElement.textContent = 'Email and password are required.';
        }
        return;
    }

    if (password !== passwordConfirm) {
        if (errorElement) {
            errorElement.textContent = 'Passwords do not match.';
        }
        return;
    }

    if (role !== SUPER_ADMIN_ROLE && !tenantIdRaw) {
        if (errorElement) {
            errorElement.textContent = 'Select a tenant for this user.';
        }
        return;
    }

    if (submitButton) {
        submitButton.disabled = true;
    }

    try {
        await api.createUser({
            email,
            password,
            role,
            tenant_id: role === SUPER_ADMIN_ROLE ? null : tenantIdRaw
        });
        showToast('User created successfully', 'success');
        form.reset();
        const roleSelect = document.getElementById('create-user-role');
        if (roleSelect) {
            roleSelect.value = 'admin';
            toggleCreateUserTenantField('admin');
        }
        await loadUsersPage();
    } catch (error) {
        console.error('Failed to create user:', error);
        if (errorElement) {
            errorElement.textContent = error.message || 'Failed to create user.';
        }
        showToast('Failed to create user: ' + error.message, 'error');
    } finally {
        if (submitButton) {
            submitButton.disabled = false;
        }
    }
}

async function handleUserRoleChange(event) {
    const select = event.target;
    const userId = select.dataset.userId;
    const originalRole = select.dataset.originalRole;
    const newRole = select.value;
    const email = select.dataset.userEmail || '';

    if (!userId || !newRole || newRole === originalRole) {
        return;
    }

    const confirmed = await showConfirmationDialog({
        title: 'Update user role',
        description: `Change role for <strong>${escapeHtml(email)}</strong> to ${escapeHtml(newRole)}?`,
        confirmLabel: 'Update role',
        tone: 'info'
    });

    if (!confirmed) {
        select.value = originalRole;
        return;
    }

    try {
        await api.updateUserRole(userId, newRole);
        select.dataset.originalRole = newRole;
        const user = usersPageState.users.find(item => item.id === userId);
        if (user) {
            user.role = newRole;
        }
        if (authState.user && authState.user.id === userId) {
            setAuthenticatedUser({ ...authState.user, role: newRole }, authState.session);
        }
        showToast('User role updated', 'success');
    } catch (error) {
        console.error('Failed to update role:', error);
        select.value = originalRole;
        showToast('Failed to update role: ' + error.message, 'error');
    }
}

async function handleDeactivateUserClick(event) {
    const button = event.currentTarget;
    const userId = button.dataset.userId;
    const email = button.dataset.userEmail || '';

    if (!userId) {
        return;
    }

    const confirmed = await showConfirmationDialog({
        title: 'Deactivate user',
        description: `The user <strong>${escapeHtml(email)}</strong> will lose access to the admin console. Continue?`,
        confirmLabel: 'Deactivate',
        tone: 'danger'
    });

    if (!confirmed) {
        return;
    }

    try {
        await api.deactivateUser(userId);
        showToast('User deactivated', 'success');
        await loadUsersPage();
    } catch (error) {
        console.error('Failed to deactivate user:', error);
        showToast('Failed to deactivate user: ' + error.message, 'error');
    }
}

// ==================== Agent Tester Module ====================

(function() {
    // Don't overwrite if already exists
    if (window.agentTester) {
        console.log('agentTester already initialized');
        return;
    }

    // Modal HTML template
    const modalHTML = `
        <div id="agent-tester-modal" class="modal" style="display: none;">
            <div class="modal-content" style="max-width: 800px; height: 80vh; display: flex; flex-direction: column;">
                <div class="modal-header">
                    <h2 id="agent-tester-title">Testar Agente</h2>
                    <button class="modal-close" id="agent-tester-close" aria-label="Fechar">&times;</button>
                </div>
                <div class="modal-body" style="flex: 1; display: flex; flex-direction: column; overflow: hidden;">
                    <div id="agent-tester-history" style="flex: 1; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 4px; padding: 1rem; margin-bottom: 1rem; background: #f9fafb;"></div>
                    <div style="display: flex; gap: 0.5rem; flex-direction: column;">
                        <textarea id="agent-tester-input" placeholder="Digite sua mensagem..." style="width: 100%; min-height: 80px; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; font-family: inherit; resize: vertical;"></textarea>
                        <div style="display: flex; gap: 0.5rem; justify-content: space-between;">
                            <div id="agent-tester-status" style="color: #6b7280; font-size: 0.875rem; display: flex; align-items: center;"></div>
                            <div style="display: flex; gap: 0.5rem;">
                                <button id="agent-tester-clear" class="btn btn-secondary btn-small">Limpar histórico</button>
                                <button id="agent-tester-send" class="btn btn-primary">Enviar mensagem</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Helper functions
    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.toString().replace(/[&<>"']/g, m => map[m]);
    }

    function appendMessageToHistory(role, content) {
        const history = document.getElementById('agent-tester-history');
        if (!history) return;

        const messageDiv = document.createElement('div');
        messageDiv.style.marginBottom = '1rem';
        messageDiv.style.padding = '0.75rem';
        messageDiv.style.borderRadius = '4px';
        messageDiv.style.backgroundColor = role === 'user' ? '#e0f2fe' : '#f3f4f6';
        messageDiv.innerHTML = `
            <div style="font-weight: 600; margin-bottom: 0.25rem; color: ${role === 'user' ? '#0369a1' : '#374151'};">
                ${role === 'user' ? 'Você' : 'Assistente'}
            </div>
            <div style="white-space: pre-wrap; word-wrap: break-word;">${escapeHtml(content)}</div>
        `;
        history.appendChild(messageDiv);
        history.scrollTop = history.scrollHeight;
    }

    function setStatus(message, type = 'info') {
        const status = document.getElementById('agent-tester-status');
        if (!status) return;

        const colors = {
            info: '#6b7280',
            success: '#10b981',
            error: '#ef4444',
            warning: '#f59e0b'
        };

        status.textContent = message;
        status.style.color = colors[type] || colors.info;
    }

    function clearHistory() {
        const history = document.getElementById('agent-tester-history');
        if (history) {
            history.innerHTML = '';
        }
        setStatus('');
    }

    async function sendMessageAndStream(agentId, message) {
        console.log('[agentTester] Enviando mensagem para agente:', agentId);
        console.log('[agentTester] Mensagem:', message);

        const sendButton = document.getElementById('agent-tester-send');
        const inputField = document.getElementById('agent-tester-input');

        if (sendButton) sendButton.disabled = true;
        if (inputField) inputField.disabled = true;
        setStatus('Enviando...', 'info');

        // Append user message to history
        appendMessageToHistory('user', message);

        try {
            // Get test URL from API
            const testUrl = api.testAgent(agentId);
            console.log('[agentTester] URL de teste:', testUrl);

            const requestBody = {
                message: message,
                stream: true
            };

            console.log('[agentTester] Enviando POST para:', testUrl);
            console.log('[agentTester] Body:', requestBody);

            const response = await authFetch(testUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'text/event-stream'
                },
                body: JSON.stringify(requestBody)
            });

            console.log('[agentTester] Response status:', response.status);
            console.log('[agentTester] Response headers:', Object.fromEntries(response.headers.entries()));

            if (response.status === 401) {
                handleUnauthorized(new APIError('Authentication required', { status: 401 }));
                throw new Error('Authentication required');
            }

            if (!response.ok) {
                const errorText = await response.text();
                console.error('[agentTester] Erro na resposta:', errorText);
                throw new Error(`HTTP ${response.status}: ${errorText}`);
            }

            // Check if streaming is supported
            const reader = response.body?.getReader();
            if (reader) {
                console.log('[agentTester] Usando streaming via reader');
                setStatus('Recebendo resposta...', 'info');

                const decoder = new TextDecoder();
                let assistantMessage = '';
                let buffer = '';

                while (true) {
                    const { done, value } = await reader.read();

                    if (done) {
                        console.log('[agentTester] Stream concluído');
                        break;
                    }

                    const chunk = decoder.decode(value, { stream: true });
                    console.log('[agentTester] Chunk recebido:', chunk.substring(0, 100));
                    buffer += chunk;

                    // Try to parse SSE events (data: {...})
                    const lines = buffer.split('\n');
                    buffer = lines.pop() || ''; // Keep incomplete line in buffer

                    for (const line of lines) {
                        if (line.startsWith('data: ')) {
                            const data = line.substring(6).trim();
                            if (data === '[DONE]') {
                                console.log('[agentTester] Recebido [DONE]');
                                continue;
                            }

                            try {
                                const event = JSON.parse(data);
                                console.log('[agentTester] Event parsed:', event.type || 'unknown');

                                if (event.type === 'chunk' && event.content) {
                                    assistantMessage += event.content;
                                } else if (event.content) {
                                    assistantMessage += event.content;
                                }
                            } catch (e) {
                                // Not JSON, treat as plain text
                                console.log('[agentTester] Texto não-JSON, adicionando ao conteúdo');
                                assistantMessage += data;
                            }
                        } else if (line.trim()) {
                            // Plain text line
                            assistantMessage += line + '\n';
                        }
                    }
                }

                if (assistantMessage.trim()) {
                    appendMessageToHistory('assistant', assistantMessage.trim());
                    setStatus('Resposta recebida', 'success');
                } else {
                    setStatus('Nenhum conteúdo recebido', 'warning');
                }
            } else {
                // Fallback to JSON or text
                console.log('[agentTester] Streaming não disponível, usando fallback');
                const contentType = response.headers.get('content-type');

                if (contentType && contentType.includes('application/json')) {
                    const data = await response.json();
                    console.log('[agentTester] Resposta JSON:', data);

                    let assistantMessage = '';
                    if (data.reply) {
                        assistantMessage = data.reply;
                    } else if (data.message) {
                        assistantMessage = data.message;
                    } else if (data.response) {
                        assistantMessage = data.response;
                    } else {
                        assistantMessage = JSON.stringify(data, null, 2);
                    }

                    appendMessageToHistory('assistant', assistantMessage);
                    setStatus('Resposta recebida', 'success');
                } else {
                    const text = await response.text();
                    console.log('[agentTester] Resposta texto:', text.substring(0, 200));
                    appendMessageToHistory('assistant', text);
                    setStatus('Resposta recebida', 'success');
                }
            }

        } catch (error) {
            console.error('[agentTester] Erro ao enviar mensagem:', error);
            setStatus('Erro: ' + error.message, 'error');
            appendMessageToHistory('assistant', '❌ Erro: ' + error.message);
        } finally {
            if (sendButton) sendButton.disabled = false;
            if (inputField) {
                inputField.disabled = false;
                inputField.value = '';
                inputField.focus();
            }
        }
    }

    function initModal() {
        // Check if modal already exists
        if (document.getElementById('agent-tester-modal')) {
            console.log('[agentTester] Modal já existe');
            return;
        }

        // Inject modal HTML
        const container = document.createElement('div');
        container.innerHTML = modalHTML;
        document.body.appendChild(container.firstElementChild);

        // Bind event listeners
        const closeBtn = document.getElementById('agent-tester-close');
        const sendBtn = document.getElementById('agent-tester-send');
        const clearBtn = document.getElementById('agent-tester-clear');
        const input = document.getElementById('agent-tester-input');
        const modal = document.getElementById('agent-tester-modal');

        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                if (modal) modal.style.display = 'none';
            });
        }

        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                clearHistory();
            });
        }

        let currentAgentId = null;

        if (sendBtn && input) {
            const sendMessage = () => {
                const message = input.value.trim();
                if (!message || !currentAgentId) return;
                sendMessageAndStream(currentAgentId, message);
            };

            sendBtn.addEventListener('click', sendMessage);

            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendMessage();
                }
            });
        }

        // Store agent ID for later use
        modal._setAgentId = (id) => {
            currentAgentId = id;
        };

        console.log('[agentTester] Modal inicializado');
    }

    // Public API
    window.agentTester = {
        start: function(agentId) {
            console.log('[agentTester] Iniciando teste do agente:', agentId);

            // Initialize modal if needed
            initModal();

            const modal = document.getElementById('agent-tester-modal');
            if (!modal) {
                console.error('[agentTester] Modal não encontrado');
                return;
            }

            // Set agent ID
            if (modal._setAgentId) {
                modal._setAgentId(agentId);
            }

            // Update title
            const title = document.getElementById('agent-tester-title');
            if (title) {
                title.textContent = `Testar Agente (ID: ${agentId})`;
            }

            // Clear history and show modal
            clearHistory();
            setStatus('Pronto para enviar mensagens', 'info');
            modal.style.display = 'flex';

            // Focus input
            const input = document.getElementById('agent-tester-input');
            if (input) {
                setTimeout(() => input.focus(), 100);
            }
        }
    };

    console.log('[agentTester] Módulo inicializado');
})();

// ==================== LeadSense CRM Page ====================

async function loadLeadSenseCRMPage() {
    const content = document.getElementById('content');
    if (!content) return;
    
    content.innerHTML = `
        <div class="leadsense-crm-page">
            <div class="crm-header">
                <div id="crm-pipeline-selector"></div>
            </div>
            <div id="leadsense-crm-content">
                <div class="loading-spinner">
                    <div class="spinner"></div>
                    <p>Loading CRM...</p>
                </div>
            </div>
            <div id="crm-board-container"></div>
        </div>
    `;
    
    // Load the CRM JavaScript module if not already loaded
    if (!window.LeadSenseCRM) {
        const script = document.createElement('script');
        script.src = 'leadsense-crm.js';
        script.onload = function() {
            if (window.LeadSenseCRM && typeof window.LeadSenseCRM.init === 'function') {
                window.LeadSenseCRM.init();
            }
        };
        document.head.appendChild(script);
    } else {
        // Already loaded, just initialize
        if (typeof window.LeadSenseCRM.init === 'function') {
            window.LeadSenseCRM.init();
        }
    }
}

