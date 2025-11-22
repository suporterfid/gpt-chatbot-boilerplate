/**
 * WordPress Blog Configuration Management UI
 *
 * Provides complete UI for managing blog configurations including:
 * - Configuration list view with search/filter
 * - Create/edit configuration form
 * - Internal links management
 * - Form validation
 * - API integration
 *
 * @requires admin.js (for API_ENDPOINT, showToast, showConfirmation)
 */

// ============================================================================
// State Management
// ============================================================================

const wpBlogConfigState = {
    configurations: [],
    currentConfig: null,
    internalLinks: [],
    isLoading: false,
    filters: {
        search: ''
    },
    formMode: 'list', // 'list', 'create', 'edit', 'links'
    lastError: null
};

// ============================================================================
// API Functions
// ============================================================================

async function wpBlogApiCall(action, options = {}) {
    const { method = 'GET', body = null, params = {} } = options;

    const queryParams = new URLSearchParams({
        action,
        ...params
    });

    const url = `${API_ENDPOINT}?${queryParams}`;

    const fetchOptions = {
        method,
        headers: {
            'Content-Type': 'application/json'
        },
        credentials: 'include'
    };

    if (body) {
        fetchOptions.body = JSON.stringify(body);
    }

    try {
        const response = await fetch(url, fetchOptions);
        const data = await response.json();

        if (!response.ok || data.error) {
            throw new Error(data.error || `HTTP ${response.status}`);
        }

        return data;
    } catch (error) {
        console.error(`API call failed: ${action}`, error);
        throw error;
    }
}

// Configuration API
async function fetchConfigurations() {
    const data = await wpBlogApiCall('wordpress_blog_list_configs');
    return data.configurations || [];
}

async function fetchConfiguration(id) {
    const data = await wpBlogApiCall('wordpress_blog_get_config', {
        params: { id }
    });
    return data.configuration;
}

async function createConfiguration(configData) {
    const data = await wpBlogApiCall('wordpress_blog_create_config', {
        method: 'POST',
        body: configData
    });
    return data.configuration;
}

async function updateConfiguration(id, configData) {
    const data = await wpBlogApiCall('wordpress_blog_update_config', {
        method: 'PUT',
        params: { id },
        body: configData
    });
    return data.configuration;
}

async function deleteConfiguration(id) {
    const data = await wpBlogApiCall('wordpress_blog_delete_config', {
        method: 'DELETE',
        params: { id }
    });
    return data;
}

// Internal Links API
async function fetchInternalLinks(configId) {
    const data = await wpBlogApiCall('wordpress_blog_list_internal_links', {
        params: { config_id: configId }
    });
    return data.links || [];
}

async function addInternalLink(configId, linkData) {
    const data = await wpBlogApiCall('wordpress_blog_add_internal_link', {
        method: 'POST',
        params: { config_id: configId },
        body: linkData
    });
    return data.link;
}

async function updateInternalLink(linkId, linkData) {
    const data = await wpBlogApiCall('wordpress_blog_update_internal_link', {
        method: 'PUT',
        params: { link_id: linkId },
        body: linkData
    });
    return data.link;
}

async function deleteInternalLink(linkId) {
    const data = await wpBlogApiCall('wordpress_blog_delete_internal_link', {
        method: 'DELETE',
        params: { link_id: linkId }
    });
    return data;
}

// ============================================================================
// UI Rendering Functions
// ============================================================================

function renderConfigurationList() {
    const { configurations, filters } = wpBlogConfigState;

    // Filter configurations
    const filtered = configurations.filter(config => {
        if (filters.search) {
            const searchLower = filters.search.toLowerCase();
            return config.config_name.toLowerCase().includes(searchLower) ||
                   config.wordpress_site_url.toLowerCase().includes(searchLower);
        }
        return true;
    });

    return `
        <div class="wp-blog-config-container">
            <div class="page-header">
                <h2>Blog Configurations</h2>
                <button onclick="wpBlogShowCreateForm()" class="btn btn-primary">
                    <span class="icon">➕</span> New Configuration
                </button>
            </div>

            <div class="filters-bar">
                <input
                    type="text"
                    placeholder="Search configurations..."
                    value="${filters.search}"
                    onkeyup="wpBlogFilterConfigs(this.value)"
                    class="search-input"
                />
            </div>

            ${filtered.length === 0 ? `
                <div class="empty-state">
                    <p>No configurations found.</p>
                    <p class="empty-state-hint">Create your first blog configuration to get started.</p>
                </div>
            ` : `
                <div class="config-grid">
                    ${filtered.map(config => renderConfigurationCard(config)).join('')}
                </div>
            `}
        </div>
    `;
}

function renderConfigurationCard(config) {
    const statusBadge = config.auto_publish
        ? '<span class="badge badge-success">Auto-Publish</span>'
        : '<span class="badge badge-secondary">Draft Mode</span>';

    return `
        <div class="config-card">
            <div class="config-card-header">
                <h3>${escapeHtml(config.config_name)}</h3>
                ${statusBadge}
            </div>
            <div class="config-card-body">
                <div class="config-detail">
                    <span class="label">WordPress Site:</span>
                    <span class="value">${escapeHtml(config.wordpress_site_url)}</span>
                </div>
                <div class="config-detail">
                    <span class="label">Target Words:</span>
                    <span class="value">${config.target_word_count || 'N/A'}</span>
                </div>
                <div class="config-detail">
                    <span class="label">Image Quality:</span>
                    <span class="value">${config.image_quality || 'standard'}</span>
                </div>
                <div class="config-detail">
                    <span class="label">Created:</span>
                    <span class="value">${formatDate(config.created_at)}</span>
                </div>
            </div>
            <div class="config-card-actions">
                <button onclick="wpBlogShowEditForm('${config.configuration_id}')" class="btn btn-sm btn-secondary">
                    Edit
                </button>
                <button onclick="wpBlogManageLinks('${config.configuration_id}')" class="btn btn-sm btn-secondary">
                    Links
                </button>
                <button onclick="wpBlogDeleteConfig('${config.configuration_id}')" class="btn btn-sm btn-danger">
                    Delete
                </button>
            </div>
        </div>
    `;
}

function renderConfigurationForm() {
    const { currentConfig, formMode } = wpBlogConfigState;
    const isEdit = formMode === 'edit' && currentConfig;

    return `
        <div class="wp-blog-config-form-container">
            <div class="page-header">
                <div>
                    <button onclick="wpBlogShowList()" class="btn btn-link">
                        ← Back to List
                    </button>
                    <h2>${isEdit ? 'Edit' : 'Create'} Configuration</h2>
                </div>
            </div>

            <form id="wp-blog-config-form" onsubmit="wpBlogSubmitForm(event)" class="wp-blog-form">
                <div class="form-section">
                    <h3>Basic Information</h3>

                    <div class="form-group">
                        <label for="config_name">Configuration Name *</label>
                        <input
                            type="text"
                            id="config_name"
                            name="config_name"
                            required
                            value="${isEdit ? escapeHtml(currentConfig.config_name) : ''}"
                            placeholder="e.g., My Tech Blog"
                        />
                        <small>A friendly name to identify this configuration</small>
                    </div>
                </div>

                <div class="form-section">
                    <h3>WordPress Settings</h3>

                    <div class="form-group">
                        <label for="wordpress_site_url">WordPress Site URL *</label>
                        <input
                            type="url"
                            id="wordpress_site_url"
                            name="wordpress_site_url"
                            required
                            value="${isEdit ? escapeHtml(currentConfig.wordpress_site_url) : ''}"
                            placeholder="https://myblog.com"
                        />
                    </div>

                    <div class="form-group">
                        <label for="wordpress_api_key">WordPress API Key *</label>
                        <input
                            type="password"
                            id="wordpress_api_key"
                            name="wordpress_api_key"
                            ${isEdit ? '' : 'required'}
                            placeholder="${isEdit ? '(hidden - leave blank to keep current)' : 'Enter API key'}"
                            autocomplete="new-password"
                        />
                        <small>WordPress REST API application password</small>
                    </div>
                </div>

                <div class="form-section">
                    <h3>OpenAI Settings</h3>

                    <div class="form-group">
                        <label for="openai_api_key">OpenAI API Key *</label>
                        <input
                            type="password"
                            id="openai_api_key"
                            name="openai_api_key"
                            ${isEdit ? '' : 'required'}
                            placeholder="${isEdit ? '(hidden - leave blank to keep current)' : 'sk-proj-...'}"
                            autocomplete="new-password"
                        />
                        <small>Used for content generation with GPT-4 and DALL-E 3</small>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Content Settings</h3>

                    <div class="form-group">
                        <label for="target_word_count">Target Word Count</label>
                        <input
                            type="number"
                            id="target_word_count"
                            name="target_word_count"
                            min="500"
                            max="10000"
                            value="${isEdit ? currentConfig.target_word_count || 2000 : 2000}"
                        />
                        <small>Approximate total word count for generated articles (500-10000)</small>
                    </div>

                    <div class="form-group">
                        <label for="image_quality">Image Quality</label>
                        <select id="image_quality" name="image_quality">
                            <option value="standard" ${isEdit && currentConfig.image_quality === 'standard' ? 'selected' : ''}>
                                Standard (Faster, Lower Cost)
                            </option>
                            <option value="hd" ${isEdit && currentConfig.image_quality === 'hd' ? 'selected' : ''}>
                                HD (Slower, Higher Cost)
                            </option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>
                            <input
                                type="checkbox"
                                id="auto_publish"
                                name="auto_publish"
                                ${isEdit && currentConfig.auto_publish ? 'checked' : ''}
                            />
                            Auto-publish articles
                        </label>
                        <small>If checked, articles will be published immediately. Otherwise, they'll be saved as drafts.</small>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Google Drive (Optional)</h3>

                    <div class="form-group">
                        <label for="google_drive_folder_id">Google Drive Folder ID</label>
                        <input
                            type="text"
                            id="google_drive_folder_id"
                            name="google_drive_folder_id"
                            value="${isEdit ? (currentConfig.google_drive_folder_id || '') : ''}"
                            placeholder="Leave blank to skip Google Drive backup"
                        />
                        <small>Optional: Store generated content and assets in Google Drive</small>
                    </div>

                    <div class="form-group">
                        <label for="google_drive_api_key">Google Drive API Key</label>
                        <input
                            type="password"
                            id="google_drive_api_key"
                            name="google_drive_api_key"
                            placeholder="${isEdit ? '(hidden - leave blank to keep current)' : 'Optional'}"
                            autocomplete="new-password"
                        />
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" onclick="wpBlogShowList()" class="btn btn-secondary">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        ${isEdit ? 'Update' : 'Create'} Configuration
                    </button>
                </div>
            </form>
        </div>
    `;
}

function renderInternalLinksManager() {
    const { currentConfig, internalLinks } = wpBlogConfigState;

    if (!currentConfig) {
        return '<div class="error">No configuration selected</div>';
    }

    return `
        <div class="wp-blog-links-container">
            <div class="page-header">
                <div>
                    <button onclick="wpBlogShowList()" class="btn btn-link">
                        ← Back to List
                    </button>
                    <h2>Internal Links - ${escapeHtml(currentConfig.config_name)}</h2>
                </div>
                <button onclick="wpBlogShowAddLinkForm()" class="btn btn-primary">
                    <span class="icon">➕</span> Add Link
                </button>
            </div>

            ${internalLinks.length === 0 ? `
                <div class="empty-state">
                    <p>No internal links configured.</p>
                    <p class="empty-state-hint">Add internal links to improve SEO by automatically linking keywords in your articles.</p>
                </div>
            ` : `
                <div class="links-table-container">
                    <table class="links-table">
                        <thead>
                            <tr>
                                <th>Anchor Text</th>
                                <th>Target URL</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${internalLinks.map(link => `
                                <tr>
                                    <td><strong>${escapeHtml(link.anchor_text)}</strong></td>
                                    <td>
                                        <a href="${escapeHtml(link.target_url)}" target="_blank" class="external-link">
                                            ${escapeHtml(link.target_url)}
                                        </a>
                                    </td>
                                    <td>${formatDate(link.created_at)}</td>
                                    <td class="actions">
                                        <button onclick="wpBlogEditLink('${link.link_id}')" class="btn btn-sm btn-secondary">
                                            Edit
                                        </button>
                                        <button onclick="wpBlogDeleteLink('${link.link_id}')" class="btn btn-sm btn-danger">
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `}
        </div>
    `;
}

// ============================================================================
// Event Handlers
// ============================================================================

async function wpBlogLoadConfigurations() {
    wpBlogConfigState.isLoading = true;

    try {
        const configurations = await fetchConfigurations();
        wpBlogConfigState.configurations = configurations;
        wpBlogConfigState.lastError = null;
        wpBlogRenderCurrentView();
    } catch (error) {
        wpBlogConfigState.lastError = error.message;
        showToast('Failed to load configurations: ' + error.message, 'error');
    } finally {
        wpBlogConfigState.isLoading = false;
    }
}

function wpBlogShowList() {
    wpBlogConfigState.formMode = 'list';
    wpBlogConfigState.currentConfig = null;
    wpBlogRenderCurrentView();
}

function wpBlogShowCreateForm() {
    wpBlogConfigState.formMode = 'create';
    wpBlogConfigState.currentConfig = null;
    wpBlogRenderCurrentView();
}

async function wpBlogShowEditForm(configId) {
    wpBlogConfigState.isLoading = true;

    try {
        const config = await fetchConfiguration(configId);
        wpBlogConfigState.currentConfig = config;
        wpBlogConfigState.formMode = 'edit';
        wpBlogRenderCurrentView();
    } catch (error) {
        showToast('Failed to load configuration: ' + error.message, 'error');
    } finally {
        wpBlogConfigState.isLoading = false;
    }
}

async function wpBlogSubmitForm(event) {
    event.preventDefault();

    const form = event.target;
    const formData = new FormData(form);
    const data = {};

    // Convert FormData to object
    for (const [key, value] of formData.entries()) {
        if (key === 'auto_publish') {
            data[key] = value === 'on';
        } else if (key === 'target_word_count') {
            data[key] = parseInt(value, 10);
        } else if (value) {
            // Only include non-empty values (for password fields in edit mode)
            data[key] = value;
        }
    }

    // Add checkbox if unchecked (FormData doesn't include unchecked boxes)
    if (!formData.has('auto_publish')) {
        data.auto_publish = false;
    }

    try {
        if (wpBlogConfigState.formMode === 'create') {
            await createConfiguration(data);
            showToast('Configuration created successfully!', 'success');
        } else {
            await updateConfiguration(wpBlogConfigState.currentConfig.configuration_id, data);
            showToast('Configuration updated successfully!', 'success');
        }

        await wpBlogLoadConfigurations();
        wpBlogShowList();
    } catch (error) {
        showToast('Failed to save configuration: ' + error.message, 'error');
    }
}

async function wpBlogDeleteConfig(configId) {
    const confirmed = await showConfirmation(
        'Delete Configuration',
        'Are you sure you want to delete this configuration? This action cannot be undone.',
        'danger'
    );

    if (!confirmed) return;

    try {
        await deleteConfiguration(configId);
        showToast('Configuration deleted successfully', 'success');
        await wpBlogLoadConfigurations();
    } catch (error) {
        showToast('Failed to delete configuration: ' + error.message, 'error');
    }
}

async function wpBlogManageLinks(configId) {
    wpBlogConfigState.isLoading = true;

    try {
        const config = await fetchConfiguration(configId);
        const links = await fetchInternalLinks(configId);

        wpBlogConfigState.currentConfig = config;
        wpBlogConfigState.internalLinks = links;
        wpBlogConfigState.formMode = 'links';
        wpBlogRenderCurrentView();
    } catch (error) {
        showToast('Failed to load internal links: ' + error.message, 'error');
    } finally {
        wpBlogConfigState.isLoading = false;
    }
}

async function wpBlogShowAddLinkForm() {
    const html = `
        <form id="wp-blog-link-form" onsubmit="wpBlogSubmitLinkForm(event)">
            <div class="form-group">
                <label for="anchor_text">Anchor Text *</label>
                <input type="text" id="anchor_text" name="anchor_text" required placeholder="e.g., best WordPress plugins" />
                <small>The text that will be linked in articles</small>
            </div>

            <div class="form-group">
                <label for="target_url">Target URL *</label>
                <input type="url" id="target_url" name="target_url" required placeholder="https://..." />
                <small>The URL this link will point to</small>
            </div>

            <div class="form-actions">
                <button type="button" onclick="closeModal()" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Link</button>
            </div>
        </form>
    `;

    showModal('Add Internal Link', html);
}

async function wpBlogEditLink(linkId) {
    const link = wpBlogConfigState.internalLinks.find(l => l.link_id === linkId);
    if (!link) return;

    const html = `
        <form id="wp-blog-link-form" onsubmit="wpBlogSubmitLinkForm(event, '${linkId}')">
            <div class="form-group">
                <label for="anchor_text">Anchor Text *</label>
                <input type="text" id="anchor_text" name="anchor_text" required value="${escapeHtml(link.anchor_text)}" />
            </div>

            <div class="form-group">
                <label for="target_url">Target URL *</label>
                <input type="url" id="target_url" name="target_url" required value="${escapeHtml(link.target_url)}" />
            </div>

            <div class="form-actions">
                <button type="button" onclick="closeModal()" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Link</button>
            </div>
        </form>
    `;

    showModal('Edit Internal Link', html);
}

async function wpBlogSubmitLinkForm(event, linkId = null) {
    event.preventDefault();

    const formData = new FormData(event.target);
    const data = {
        anchor_text: formData.get('anchor_text'),
        target_url: formData.get('target_url')
    };

    try {
        if (linkId) {
            await updateInternalLink(linkId, data);
            showToast('Link updated successfully!', 'success');
        } else {
            await addInternalLink(wpBlogConfigState.currentConfig.configuration_id, data);
            showToast('Link added successfully!', 'success');
        }

        closeModal();
        await wpBlogManageLinks(wpBlogConfigState.currentConfig.configuration_id);
    } catch (error) {
        showToast('Failed to save link: ' + error.message, 'error');
    }
}

async function wpBlogDeleteLink(linkId) {
    const confirmed = await showConfirmation(
        'Delete Link',
        'Are you sure you want to delete this internal link?',
        'danger'
    );

    if (!confirmed) return;

    try {
        await deleteInternalLink(linkId);
        showToast('Link deleted successfully', 'success');
        await wpBlogManageLinks(wpBlogConfigState.currentConfig.configuration_id);
    } catch (error) {
        showToast('Failed to delete link: ' + error.message, 'error');
    }
}

function wpBlogFilterConfigs(searchTerm) {
    wpBlogConfigState.filters.search = searchTerm;
    wpBlogRenderCurrentView();
}

// ============================================================================
// Render Controller
// ============================================================================

function wpBlogRenderCurrentView() {
    const container = document.getElementById('page-content');
    if (!container) return;

    let html = '';

    if (wpBlogConfigState.isLoading) {
        html = '<div class="loading-spinner">Loading...</div>';
    } else {
        switch (wpBlogConfigState.formMode) {
            case 'create':
            case 'edit':
                html = renderConfigurationForm();
                break;
            case 'links':
                html = renderInternalLinksManager();
                break;
            default:
                html = renderConfigurationList();
        }
    }

    container.innerHTML = html;
}

// ============================================================================
// Utility Functions
// ============================================================================

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

// ============================================================================
// Initialization
// ============================================================================

function initWordPressBlogConfigUI() {
    wpBlogLoadConfigurations();
}

// Export for testing
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        wpBlogConfigState,
        initWordPressBlogConfigUI,
        wpBlogLoadConfigurations,
        wpBlogShowList,
        wpBlogShowCreateForm,
        wpBlogShowEditForm,
        wpBlogSubmitForm,
        wpBlogDeleteConfig,
        wpBlogManageLinks
    };
}
