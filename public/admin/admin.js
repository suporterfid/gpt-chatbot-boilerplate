// Admin UI JavaScript

// Configuration
const API_BASE = window.location.origin;
const API_ENDPOINT = `${API_BASE}/admin-api.php`;

// State
let adminToken = localStorage.getItem('admin_token') || '';
let currentPage = 'agents';

// API Client
class AdminAPI {
    constructor(token) {
        this.token = token;
    }

    async request(action, options = {}) {
        const url = `${API_ENDPOINT}?action=${action}${options.params || ''}`;
        const headers = {
            'Content-Type': 'application/json',
        };

        if (this.token) {
            headers['Authorization'] = `Bearer ${this.token}`;
            headers['X-Admin-Token'] = this.token;
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

    testAgent(id, message) {
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
}

let api = new AdminAPI(adminToken);

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
function checkToken() {
    if (!adminToken) {
        document.getElementById('token-modal').style.display = 'flex';
        return false;
    }
    return true;
}

function saveToken() {
    const token = document.getElementById('token-input').value.trim();
    if (!token) {
        showToast('Please enter a valid token', 'error');
        return;
    }
    
    adminToken = token;
    localStorage.setItem('admin_token', token);
    api = new AdminAPI(token);
    
    document.getElementById('token-modal').style.display = 'none';
    
    // Test the token
    testConnection();
}

async function testConnection() {
    try {
        await api.health();
        document.getElementById('status-indicator').classList.remove('error');
        document.getElementById('status-text').textContent = 'Connected';
        showToast('Successfully connected to admin API', 'success');
        loadCurrentPage();
    } catch (error) {
        document.getElementById('status-indicator').classList.add('error');
        document.getElementById('status-text').textContent = 'Error';
        showToast('Failed to connect: ' + error.message, 'error');
        
        // Clear token if authentication failed
        if (error.message.includes('token') || error.message.includes('401') || error.message.includes('403')) {
            adminToken = '';
            localStorage.removeItem('admin_token');
            document.getElementById('token-modal').style.display = 'flex';
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
        'jobs': 'Background Jobs',
        'audit': 'Audit Log',
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
        'jobs': loadJobsPage,
        'audit': loadAuditPage,
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
    // Load prompts and vector stores for dropdowns
    let prompts = [];
    let vectorStores = [];
    
    try {
        prompts = await api.listPrompts();
        vectorStores = await api.listVectorStores();
    } catch (error) {
        console.error('Error loading resources:', error);
    }
    
    const promptOptions = prompts.map(p => `<option value="${p.openai_prompt_id || ''}">${p.name}</option>`).join('');
    const vectorStoreOptions = vectorStores.map(vs => `<option value="${vs.openai_store_id || ''}">${vs.name}</option>`).join('');
    
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
                <input type="text" name="model" class="form-input" placeholder="e.g., gpt-4o, gpt-4o-mini" />
                <small class="form-help">Leave empty to use default</small>
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
                    <button class="btn btn-danger mt-2" onclick="clearToken()">Clear Token</button>
                    <p class="form-help mt-1">You will need to re-enter your admin token after clearing it.</p>
                </div>
            </div>
        `;
        
        content.innerHTML = html;
    } catch (error) {
        content.innerHTML = `<div class="card"><div class="card-body">Error loading settings: ${error.message}</div></div>`;
        showToast('Failed to load settings: ' + error.message, 'error');
    }
}

function clearToken() {
    if (!confirm('Are you sure you want to clear the admin token? You will need to re-enter it.')) {
        return;
    }
    
    adminToken = '';
    localStorage.removeItem('admin_token');
    document.getElementById('token-modal').style.display = 'flex';
}

// ==================== Initialization ====================

document.addEventListener('DOMContentLoaded', function() {
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
