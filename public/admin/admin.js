// Admin UI JavaScript

// Configuration
const API_BASE = window.location.origin;
const API_ENDPOINT = `${API_BASE}/admin-api.php`;
const TOKEN_STORAGE_KEY = 'adminToken';

// State
let adminToken = localStorage.getItem(TOKEN_STORAGE_KEY) || '';
let currentPage = 'agents';

// API Client
class AdminAPI {
    async request(action, options = {}) {
        const url = `${API_ENDPOINT}?action=${action}${options.params || ''}`;
        const headers = {
            'Content-Type': 'application/json',
        };

        const token = getStoredToken();

        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
            headers['X-Admin-Token'] = token;
        }

        const config = {
            method: options.method || 'GET',
            headers,
        };

        if (options.body) {
            config.body = JSON.stringify(options.body);
        }

        try {
            const response = await fetch(url, config);
            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error?.message || 'Request failed');
            }

            return data.data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
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
        const token = getStoredToken();
        const tokenParam = token ? `&token=${encodeURIComponent(token)}` : '';
        return `${API_ENDPOINT}?action=test_agent&id=${id}${tokenParam}`;
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
        return this.request('list_prompt_versions', { params: `&id=${id}` });
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
    
    // Billing & Usage
    getUsageStats(tenantId = null, filters = {}) {
        let params = '';
        if (tenantId) params += `&tenant_id=${tenantId}`;
        if (filters.start_date) params += `&start_date=${filters.start_date}`;
        if (filters.end_date) params += `&end_date=${filters.end_date}`;
        if (filters.resource_type) params += `&resource_type=${filters.resource_type}`;
        return this.request('get_usage_stats', { params });
    }
    
    getUsageTimeSeries(tenantId = null, filters = {}) {
        let params = '';
        if (tenantId) params += `&tenant_id=${tenantId}`;
        if (filters.start_date) params += `&start_date=${filters.start_date}`;
        if (filters.end_date) params += `&end_date=${filters.end_date}`;
        if (filters.resource_type) params += `&resource_type=${filters.resource_type}`;
        if (filters.interval) params += `&interval=${filters.interval}`;
        return this.request('get_usage_timeseries', { params });
    }
    
    listQuotas(tenantId = null) {
        let params = '';
        if (tenantId) params += `&tenant_id=${tenantId}`;
        return this.request('list_quotas', { params });
    }
    
    getQuotaStatus(tenantId = null) {
        let params = '';
        if (tenantId) params += `&tenant_id=${tenantId}`;
        return this.request('get_quota_status', { params });
    }
    
    setQuota(data) {
        return this.request('set_quota', { method: 'POST', body: data });
    }
    
    deleteQuota(id) {
        return this.request('delete_quota', { method: 'POST', params: `&id=${id}` });
    }
    
    getSubscription(tenantId = null) {
        let params = '';
        if (tenantId) params += `&tenant_id=${tenantId}`;
        return this.request('get_subscription', { params });
    }
    
    createSubscription(data) {
        return this.request('create_subscription', { method: 'POST', body: data });
    }
    
    updateSubscription(data) {
        return this.request('update_subscription', { method: 'POST', body: data });
    }
    
    cancelSubscription(tenantId, immediately = false) {
        return this.request('cancel_subscription', { method: 'POST', body: { tenant_id: tenantId, immediately } });
    }
    
    listInvoices(tenantId = null, filters = {}) {
        let params = '';
        if (tenantId) params += `&tenant_id=${tenantId}`;
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
    
    listNotifications(tenantId = null, filters = {}) {
        let params = '';
        if (tenantId) params += `&tenant_id=${tenantId}`;
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
    
    getUnreadCount(tenantId = null) {
        let params = '';
        if (tenantId) params += `&tenant_id=${tenantId}`;
        return this.request('get_unread_count', { params });
    }
}

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

function openModal(title, content) {
    const modal = document.getElementById('modal');
    document.getElementById('modal-title').textContent = title;
    document.getElementById('modal-body').innerHTML = content;
    modal.style.display = 'flex';
}

function closeModal() {
    document.getElementById('modal').style.display = 'none';
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
}

// Token Management
function getStoredToken() {
    const token = localStorage.getItem(TOKEN_STORAGE_KEY);
    if (token) {
        return token;
    }

    const legacyToken = localStorage.getItem('admin_token');
    if (legacyToken) {
        setStoredToken(legacyToken);
        localStorage.removeItem('admin_token');
        return legacyToken;
    }

    return '';
}

function setStoredToken(token) {
    localStorage.setItem(TOKEN_STORAGE_KEY, token);
    adminToken = token;
}

function clearStoredToken() {
    localStorage.removeItem(TOKEN_STORAGE_KEY);
    adminToken = '';
}

function showTokenModal() {
    const modal = document.getElementById('token-modal');
    const form = document.getElementById('token-form');
    const input = document.getElementById('token-input');

    if (!modal) {
        return;
    }

    if (form) {
        form.reset();
    }

    modal.style.display = 'flex';

    requestAnimationFrame(() => {
        if (input) {
            input.focus();
        }
    });
}

function hideTokenModal() {
    const modal = document.getElementById('token-modal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function checkToken() {
    adminToken = getStoredToken();
    if (!adminToken) {
        const indicator = document.getElementById('status-indicator');
        const statusText = document.getElementById('status-text');

        if (indicator) {
            indicator.classList.add('error');
        }

        if (statusText) {
            statusText.textContent = 'Token required';
        }

        showTokenModal();
        return false;
    }
    return true;
}

function saveToken(event) {
    if (event) {
        event.preventDefault();
    }

    const input = document.getElementById('token-input');
    const token = input ? input.value.trim() : '';

    if (!token) {
        showToast('Please enter a valid token', 'error');
        if (input) {
            input.focus();
        }
        return;
    }

    setStoredToken(token);
    api = new AdminAPI();

    hideTokenModal();

    // Test the token
    testConnection();
}

async function testConnection() {
    try {
        await api.health();
        document.getElementById('status-indicator').classList.remove('error');
        document.getElementById('status-text').textContent = 'Connected';
        showToast('Successfully connected to admin API', 'success');
        
        // Hide super-admin only features for non-super-admins
        // Try to access tenant list - if it fails with 403, user is not super-admin
        try {
            await api.listTenants();
            // User is super-admin, show all features
        } catch (error) {
            if (error.message.includes('403') || error.message.includes('super-admin')) {
                // Hide super-admin only navigation items
                const superAdminLinks = document.querySelectorAll('[data-super-admin-only="true"]');
                superAdminLinks.forEach(link => link.style.display = 'none');
            }
        }
        
        loadCurrentPage();
    } catch (error) {
        document.getElementById('status-indicator').classList.add('error');
        document.getElementById('status-text').textContent = 'Error';
        showToast('Failed to connect: ' + error.message, 'error');

        // Clear token if authentication failed
        if (error.message.includes('token') || error.message.includes('401') || error.message.includes('403')) {
            clearStoredToken();
            showTokenModal();
        }
    }
}

// Page Management
function navigateTo(page) {
    // Clear any existing intervals when navigating away from jobs page
    if (currentPage === 'jobs' && jobsRefreshInterval) {
        clearInterval(jobsRefreshInterval);
        jobsRefreshInterval = null;
    }
    
    currentPage = page;
    
    // Update navigation
    document.querySelectorAll('.nav-link').forEach(link => {
        link.classList.remove('active');
        if (link.dataset.page === page) {
            link.classList.add('active');
        }
    });
    
    // Update title
    const titles = {
        'agents': 'Agents',
        'prompts': 'Prompts',
        'vector-stores': 'Vector Stores',
        'whatsapp-templates': 'WhatsApp Templates',
        'consent-management': 'Consent Management',
        'jobs': 'Background Jobs',
        'tenants': 'Tenants',
        'billing': 'Billing & Usage',
        'audit': 'Audit Log',
        'audit-conversations': 'Audit Trails',
        'settings': 'Settings'
    };
    document.getElementById('page-title').textContent = titles[page] || page;
    
    // Load page content
    loadCurrentPage();
}

function loadCurrentPage() {
    if (!checkToken()) return;
    
    const pages = {
        'agents': loadAgentsPage,
        'prompts': loadPromptsPage,
        'vector-stores': loadVectorStoresPage,
        'whatsapp-templates': loadWhatsAppTemplatesPage,
        'consent-management': loadConsentManagementPage,
        'jobs': loadJobsPage,
        'tenants': loadTenantsPage,
        'billing': loadBillingPage,
        'audit': loadAuditPage,
        'audit-conversations': loadAuditConversationsPage,
        'settings': loadSettingsPage
    };
    
    if (pages[currentPage]) {
        pages[currentPage]();
    }
}

// ==================== Agents Page ====================

async function loadAgentsPage() {
    const content = document.getElementById('content');
    content.innerHTML = '<div class="spinner"></div>';
    
    try {
        const agents = await api.listAgents();
        
        let html = `
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Agents</h3>
                    <button class="btn btn-primary" onclick="showCreateAgentModal()">Create Agent</button>
                </div>
                <div class="card-body">
        `;
        
        if (agents.length === 0) {
            html += `
                <div class="empty-state">
                    <div class="empty-state-icon">🤖</div>
                    <div class="empty-state-text">No agents yet</div>
                    <button class="btn btn-primary mt-2" onclick="showCreateAgentModal()">Create Your First Agent</button>
                </div>
            `;
        } else {
            html += `
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Model</th>
                                <th>Status</th>
                                <th>Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            agents.forEach(agent => {
                const isDefault = agent.is_default ? '<span class="badge badge-primary">Default</span>' : '';
                const apiType = agent.api_type === 'responses' ? 
                    '<span class="badge badge-success">Responses</span>' : 
                    '<span class="badge badge-warning">Chat</span>';
                
                html += `
                    <tr>
                        <td><strong>${agent.name}</strong></td>
                        <td>${apiType}</td>
                        <td>${agent.model || 'Default'}</td>
                        <td>${isDefault}</td>
                        <td>${formatDate(agent.updated_at)}</td>
                        <td class="table-actions">
                            <button class="btn btn-small btn-secondary" onclick="editAgent('${agent.id}')">Edit</button>
                            <button class="btn btn-small btn-purple" onclick="showPromptBuilderModal('${agent.id}', '${agent.name}')">✨ Prompt Builder</button>
                            <button class="btn btn-small btn-info" onclick="manageChannels('${agent.id}', '${agent.name}')">Channels</button>
                            <button class="btn btn-small btn-primary" onclick="testAgent('${agent.id}')">Test</button>
                            ${!agent.is_default ? `<button class="btn btn-small btn-success" onclick="makeDefaultAgent('${agent.id}')">Make Default</button>` : ''}
                            <button class="btn btn-small btn-danger" onclick="deleteAgent('${agent.id}', '${agent.name}')">Delete</button>
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
        content.innerHTML = `<div class="card"><div class="card-body">Error loading agents: ${error.message}</div></div>`;
        showToast('Failed to load agents: ' + error.message, 'error');
    }
}

async function showCreateAgentModal() {
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
    
    const promptOptions = prompts.map(p => `<option value="${p.openai_prompt_id || ''}">${p.name}</option>`).join('');
    const vectorStoreOptions = vectorStores.map(vs => `<option value="${vs.openai_store_id || ''}">${vs.name}</option>`).join('');
    
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
        
        return sortedModels.map(m => `<option value="${m.id}">${m.id}</option>`).join('');
    };
    
    const modelOptions = buildModelOptions(models);
    
    // Build model input field (dropdown or text fallback)
    const modelInputHtml = modelOptions 
        ? `<select name="model" class="form-select"><option value="">Use default</option>${modelOptions}</select>`
        : `<input type="text" name="model" class="form-input" placeholder="e.g., gpt-4o, gpt-4o-mini" />`;
    
    const content = `
        <form id="agent-form" onsubmit="handleCreateAgent(event)">
            <div class="form-group">
                <label class="form-label">Name *</label>
                <input type="text" name="name" class="form-input" required />
            </div>
            
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-textarea"></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label">API Type *</label>
                <select name="api_type" class="form-select">
                    <option value="responses">Responses API</option>
                    <option value="chat">Chat Completions API</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Model</label>
                ${modelInputHtml}
                <small class="form-help">Select a model or leave as default</small>
            </div>
            
            <div class="form-group">
                <label class="form-label">Prompt ID</label>
                ${promptOptions ? `<select name="prompt_id" class="form-select"><option value="">Select Prompt</option>${promptOptions}</select>` : `<input type="text" name="prompt_id" class="form-input" />`}
            </div>
            
            <div class="form-group">
                <label class="form-label">Prompt Version</label>
                <input type="text" name="prompt_version" class="form-input" />
            </div>
            
            <div class="form-group">
                <label class="form-label">System Message</label>
                <textarea name="system_message" class="form-textarea" placeholder="You are a helpful assistant..."></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label">Temperature (0-2)</label>
                <input type="number" name="temperature" class="form-input" step="0.1" min="0" max="2" value="0.7" />
            </div>
            
            <div class="form-group">
                <label class="form-label">Top P (0-1)</label>
                <input type="number" name="top_p" class="form-input" step="0.05" min="0" max="1" value="1" />
            </div>
            
            <div class="form-group">
                <label class="form-label">Max Output Tokens</label>
                <input type="number" name="max_output_tokens" class="form-input" placeholder="e.g., 1024" />
            </div>
            
            <div class="form-group">
                <label class="form-label">Vector Store IDs</label>
                ${vectorStoreOptions ? `<select name="vector_store_ids" class="form-select" multiple>${vectorStoreOptions}</select>` : `<input type="text" name="vector_store_ids" class="form-input" placeholder="vs_abc,vs_def" />`}
                <small class="form-help">Select vector stores for file search</small>
            </div>
            
            <div class="form-group">
                <label class="form-checkbox">
                    <input type="checkbox" name="enable_file_search" />
                    Enable File Search Tool
                </label>
            </div>
            
            <div class="form-group">
                <label class="form-checkbox">
                    <input type="checkbox" name="is_default" />
                    Set as Default Agent
                </label>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Agent</button>
            </div>
        </form>
    `;
    
    openModal('Create Agent', content);
}

async function handleCreateAgent(event) {
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
        await api.createAgent(data);
        closeModal();
        showToast('Agent created successfully', 'success');
        loadAgentsPage();
    } catch (error) {
        showToast('Failed to create agent: ' + error.message, 'error');
    }
}

async function editAgent(id) {
    try {
        const agent = await api.getAgent(id);
        // Similar modal to create, but with pre-filled values
        showToast('Edit functionality coming soon', 'warning');
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

async function testAgent(id) {
    const message = prompt('Enter a test message:', 'Hello, can you help me?');
    if (!message) return;
    
    const content = `
        <div class="form-group">
            <label class="form-label">Test Message</label>
            <div style="padding: 1rem; background: #f3f4f6; border-radius: 0.375rem; margin-bottom: 1rem;">
                ${message}
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label">Agent Response</label>
            <div id="test-response" style="padding: 1rem; background: #f3f4f6; border-radius: 0.375rem; min-height: 100px; max-height: 400px; overflow-y: auto;">
                <div class="spinner"></div> Streaming response...
            </div>
        </div>
        
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal()">Close</button>
        </div>
    `;
    
    openModal('Test Agent', content);
    
    // Start streaming response
    const responseDiv = document.getElementById('test-response');
    responseDiv.innerHTML = '';
    
    try {
        const url = api.testAgent(id);
        const eventSource = new EventSource(url);
        
        let fullResponse = '';
        
        eventSource.addEventListener('message', (event) => {
            try {
                const data = JSON.parse(event.data);
                
                if (data.type === 'start') {
                    responseDiv.innerHTML = `<div style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.5rem;">Testing agent: ${data.agent_name}</div>`;
                } else if (data.type === 'chunk') {
                    fullResponse += data.content || '';
                    responseDiv.innerHTML += (data.content || '').replace(/\n/g, '<br>');
                    responseDiv.scrollTop = responseDiv.scrollHeight;
                } else if (data.type === 'done') {
                    eventSource.close();
                    responseDiv.innerHTML += '<div style="color: #10b981; font-size: 0.875rem; margin-top: 0.5rem;">✓ Response complete</div>';
                } else if (data.type === 'error') {
                    eventSource.close();
                    responseDiv.innerHTML += `<div style="color: #ef4444;">Error: ${data.message}</div>`;
                }
            } catch (error) {
                console.error('Error parsing SSE:', error);
            }
        });
        
        eventSource.onerror = () => {
            eventSource.close();
            responseDiv.innerHTML += '<div style="color: #ef4444;">Connection error</div>';
        };
    } catch (error) {
        responseDiv.innerHTML = `<div style="color: #ef4444;">Error: ${error.message}</div>`;
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
        const versions = await api.listPromptVersions(id);
        
        let content = `
            <div class="form-group">
                <label class="form-label">Versions</label>
                <div style="max-height: 300px; overflow-y: auto;">
        `;
        
        if (versions.length === 0) {
            content += '<p>No versions yet</p>';
        } else {
            content += '<table style="width: 100%;"><thead><tr><th>Version</th><th>Created</th></tr></thead><tbody>';
            versions.forEach(v => {
                content += `<tr><td>${v.version}</td><td>${formatDate(v.created_at)}</td></tr>`;
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
            api.getUsageStats(null, { 
                start_date: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString() 
            }).catch(() => ({ by_resource_type: [], totals: {} })),
            api.getSubscription().catch(() => null),
            api.listInvoices(null, { limit: 10 }).catch(() => []),
            api.listNotifications(null, { limit: 5, unread_only: true }).catch(() => [])
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
                    <h3 class="card-title">Admin Token</h3>
                </div>
                <div class="card-body">
                    <p>Current token is configured and active.</p>
                    <button class="btn btn-secondary mt-2" onclick="changeToken()">Change Token</button>
                    <p class="form-help mt-1">You will need to re-enter your admin token after changing it.</p>
                </div>
            </div>
        `;
        
        content.innerHTML = html;
    } catch (error) {
        content.innerHTML = `<div class="card"><div class="card-body">Error loading settings: ${error.message}</div></div>`;
        showToast('Failed to load settings: ' + error.message, 'error');
    }
}

function changeToken() {
    clearStoredToken();
    const indicator = document.getElementById('status-indicator');
    const statusText = document.getElementById('status-text');

    if (indicator) {
        indicator.classList.add('error');
    }

    if (statusText) {
        statusText.textContent = 'Token required';
    }

    showToast('Admin token cleared. Please enter a new token.', 'info');
    showTokenModal();
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
        <form id="whatsapp-config-form" onsubmit="handleSaveWhatsAppConfig(event, '${agentId}')">
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
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="manageChannels('${agentId}', 'Agent')">Back</button>
                <button type="submit" class="btn btn-primary">Save Configuration</button>
            </div>
        </form>
    `;
    
    openModal('Configure WhatsApp', content);
}

async function handleSaveWhatsAppConfig(event, agentId) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    
    const data = {
        enabled: formData.get('enabled') === 'on',
        whatsapp_business_number: formData.get('whatsapp_business_number'),
        zapi_instance_id: formData.get('zapi_instance_id'),
        zapi_token: formData.get('zapi_token'),
        zapi_base_url: formData.get('zapi_base_url'),
        zapi_timeout_ms: parseInt(formData.get('zapi_timeout_ms')),
        zapi_retries: parseInt(formData.get('zapi_retries')),
        reply_chunk_size: parseInt(formData.get('reply_chunk_size')),
        allow_media_upload: formData.get('allow_media_upload') === 'on',
        max_media_size_bytes: parseInt(formData.get('max_media_size_bytes')),
        allowed_media_types: formData.get('allowed_media_types').split(',').map(s => s.trim()).filter(s => s)
    };
    
    try {
        await api.upsertAgentChannel(agentId, 'whatsapp', data);
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
    const tokenForm = document.getElementById('token-form');
    if (tokenForm) {
        tokenForm.addEventListener('submit', saveToken);
    }

    const changeTokenButton = document.getElementById('change-token-button');
    if (changeTokenButton) {
        changeTokenButton.addEventListener('click', () => changeToken());
    }

    // Setup navigation
    document.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const page = this.dataset.page;
            window.location.hash = page;
            navigateTo(page);
        });
    });
    
    // Handle hash navigation
    window.addEventListener('hashchange', function() {
        const page = window.location.hash.substring(1) || 'agents';
        navigateTo(page);
    });
    
    // Check initial token
    if (checkToken()) {
        // Load initial page
        const initialPage = window.location.hash.substring(1) || 'agents';
        navigateTo(initialPage);
        
        // Test connection
        testConnection();
    }
});

// ========== Audit Conversations API Methods ==========

// Extend API class (already instantiated as 'api')
api.listAuditConversations = function(params = {}) {
    const query = new URLSearchParams(params).toString();
    return this.request('list_audit_conversations', { params: query ? '&' + query : '' });
};

api.getAuditConversation = function(conversationId, decrypt = false) {
    return this.request('get_audit_conversation', { 
        params: `&conversation_id=${encodeURIComponent(conversationId)}&decrypt=${decrypt}` 
    });
};

api.exportAuditData = function(params = {}) {
    const query = new URLSearchParams(params).toString();
    const url = `${this.baseUrl}?action=export_audit_data${query ? '&' + query : ''}`;
    window.open(url, '_blank');
};

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
    if (!checkToken()) return;
    
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
        const response = await fetch(`${API_BASE_URL}/admin-api.php?action=list_templates`, {
            headers: {
                'Authorization': `Bearer ${getToken()}`
            }
        });
        
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
        const response = await fetch(`${API_BASE_URL}/admin-api.php?action=create_template`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${getToken()}`,
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
        const response = await fetch(`${API_BASE_URL}/admin-api.php?action=get_template&id=${templateId}`, {
            headers: {
                'Authorization': `Bearer ${getToken()}`
            }
        });
        
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
        const response = await fetch(`${API_BASE_URL}/admin-api.php?action=submit_template&id=${templateId}`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${getToken()}`
            }
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
        const response = await fetch(`${API_BASE_URL}/admin-api.php?action=delete_template&id=${templateId}`, {
            method: 'DELETE',
            headers: {
                'Authorization': `Bearer ${getToken()}`
            }
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
    if (!checkToken()) return;
    
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
        const response = await fetch(`${API_BASE_URL}/admin-api.php?action=list_consents`, {
            headers: {
                'Authorization': `Bearer ${getToken()}`
            }
        });
        
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
        const response = await fetch(`${API_BASE_URL}/admin-api.php?action=get_consent_by_id&id=${consentId}`, {
            headers: {
                'Authorization': `Bearer ${getToken()}`
            }
        });
        
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
        const response = await fetch(`${API_BASE_URL}/admin-api.php?action=get_consent_audit&id=${consentId}`, {
            headers: {
                'Authorization': `Bearer ${getToken()}`
            }
        });
        
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
        const response = await fetch(`${API_BASE_URL}/admin-api.php?action=withdraw_consent_by_id&id=${consentId}`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${getToken()}`
            }
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
        
        const response = await fetch(`${API_BASE_URL}/admin-api.php?action=export_consents`, {
            headers: {
                'Authorization': `Bearer ${getToken()}`
            }
        });
        
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

