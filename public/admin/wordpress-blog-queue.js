/**
 * WordPress Blog Article Queue Manager UI
 *
 * Comprehensive UI for article queue management including:
 * - Queue dashboard with summary cards
 * - Queue table with filtering/sorting/pagination
 * - Add new article form
 * - Article detail view with status timeline
 * - Real-time status updates
 *
 * @requires admin.js (for API_ENDPOINT, showToast, showConfirmation)
 * @requires wordpress-blog-config.js (for configuration data)
 */

// ============================================================================
// State Management
// ============================================================================

const wpBlogQueueState = {
    articles: [],
    configurations: [],
    currentArticle: null,
    isLoading: false,
    filters: {
        status: 'all',
        configId: 'all',
        search: ''
    },
    pagination: {
        limit: 20,
        offset: 0,
        total: 0
    },
    viewMode: 'dashboard', // 'dashboard', 'detail'
    statistics: {},
    refreshInterval: null,
    lastError: null
};

// ============================================================================
// API Functions
// ============================================================================

async function wpQueueApiCall(action, options = {}) {
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

// Queue API Functions
async function fetchArticles(status = null, configId = null, limit = 20, offset = 0) {
    const params = { limit, offset };
    if (status && status !== 'all') params.status = status;
    if (configId && configId !== 'all') params.config_id = configId;

    const data = await wpQueueApiCall('wordpress_blog_list_articles', { params });
    return {
        articles: data.articles || [],
        statistics: data.statistics || {}
    };
}

async function fetchArticle(articleId) {
    const data = await wpQueueApiCall('wordpress_blog_get_article', {
        params: { id: articleId }
    });
    return data.article;
}

async function addArticle(articleData) {
    const data = await wpQueueApiCall('wordpress_blog_add_article', {
        method: 'POST',
        body: articleData
    });
    return data.article;
}

async function requeueArticle(articleId) {
    const data = await wpQueueApiCall('wordpress_blog_requeue_article', {
        method: 'POST',
        params: { id: articleId }
    });
    return data.article;
}

async function deleteArticle(articleId) {
    const data = await wpQueueApiCall('wordpress_blog_delete_article', {
        method: 'DELETE',
        params: { id: articleId }
    });
    return data;
}

async function getQueueStatus() {
    const data = await wpQueueApiCall('wordpress_blog_get_queue_status');
    return data.status || {};
}

async function getExecutionLog(articleId) {
    const data = await wpQueueApiCall('wordpress_blog_get_execution_log', {
        params: { article_id: articleId }
    });
    return data.execution_log;
}

// ============================================================================
// UI Rendering Functions
// ============================================================================

function renderQueueDashboard() {
    const { statistics, articles, filters } = wpBlogQueueState;

    return `
        <div class="wp-blog-queue-container">
            <div class="page-header">
                <h2>Article Queue Manager</h2>
                <button onclick="wpQueueShowAddForm()" class="btn btn-primary">
                    <span class="icon">➕</span> Queue New Article
                </button>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                ${renderStatisticsCards(statistics)}
            </div>

            <!-- Filters -->
            <div class="filters-bar">
                <select onchange="wpQueueFilterByStatus(this.value)" class="filter-select">
                    <option value="all" ${filters.status === 'all' ? 'selected' : ''}>All Statuses</option>
                    <option value="queued" ${filters.status === 'queued' ? 'selected' : ''}>Queued</option>
                    <option value="processing" ${filters.status === 'processing' ? 'selected' : ''}>Processing</option>
                    <option value="completed" ${filters.status === 'completed' ? 'selected' : ''}>Completed</option>
                    <option value="published" ${filters.status === 'published' ? 'selected' : ''}>Published</option>
                    <option value="failed" ${filters.status === 'failed' ? 'selected' : ''}>Failed</option>
                </select>

                <select onchange="wpQueueFilterByConfig(this.value)" class="filter-select">
                    <option value="all">All Configurations</option>
                    ${wpBlogQueueState.configurations.map(config => `
                        <option value="${config.configuration_id}" ${filters.configId === config.configuration_id ? 'selected' : ''}>
                            ${escapeHtml(config.config_name)}
                        </option>
                    `).join('')}
                </select>

                <input
                    type="text"
                    placeholder="Search keywords..."
                    value="${filters.search}"
                    onkeyup="wpQueueFilterBySearch(this.value)"
                    class="search-input"
                />
            </div>

            <!-- Articles Table -->
            ${articles.length === 0 ? `
                <div class="empty-state">
                    <p>No articles in queue.</p>
                    <p class="empty-state-hint">Queue your first article to start automated blog content generation.</p>
                </div>
            ` : `
                <div class="table-container">
                    <table class="queue-table">
                        <thead>
                            <tr>
                                <th>Keyword</th>
                                <th>Configuration</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Progress</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${articles.map(article => renderArticleRow(article)).join('')}
                        </tbody>
                    </table>
                </div>

                ${renderPagination()}
            `}
        </div>
    `;
}

function renderStatisticsCards(stats) {
    const total = Object.values(stats).reduce((sum, count) => sum + count, 0);
    const queued = stats.queued || 0;
    const processing = stats.processing || 0;
    const completed = stats.completed || 0;
    const published = stats.published || 0;
    const failed = stats.failed || 0;

    return `
        <div class="stat-card">
            <div class="stat-value">${total}</div>
            <div class="stat-label">Total Articles</div>
        </div>
        <div class="stat-card stat-queued">
            <div class="stat-value">${queued}</div>
            <div class="stat-label">Queued</div>
        </div>
        <div class="stat-card stat-processing">
            <div class="stat-value">${processing}</div>
            <div class="stat-label">Processing</div>
        </div>
        <div class="stat-card stat-completed">
            <div class="stat-value">${completed + published}</div>
            <div class="stat-label">Completed</div>
        </div>
        <div class="stat-card stat-failed">
            <div class="stat-value">${failed}</div>
            <div class="stat-label">Failed</div>
        </div>
    `;
}

function renderArticleRow(article) {
    const config = wpBlogQueueState.configurations.find(c => c.configuration_id === article.configuration_id);
    const statusBadge = getStatusBadge(article.status);

    return `
        <tr class="article-row" data-article-id="${article.article_id}">
            <td>
                <strong>${escapeHtml(article.seed_keyword)}</strong>
            </td>
            <td>${config ? escapeHtml(config.config_name) : 'Unknown'}</td>
            <td>${statusBadge}</td>
            <td>${formatDateTime(article.created_at)}</td>
            <td>${renderProgressIndicator(article)}</td>
            <td class="actions">
                <button onclick="wpQueueShowDetail('${article.article_id}')" class="btn btn-sm btn-secondary">
                    View
                </button>
                ${article.status === 'failed' ? `
                    <button onclick="wpQueueRetryArticle('${article.article_id}')" class="btn btn-sm btn-warning">
                        Retry
                    </button>
                ` : ''}
                ${article.status === 'queued' ? `
                    <button onclick="wpQueueDeleteArticle('${article.article_id}')" class="btn btn-sm btn-danger">
                        Delete
                    </button>
                ` : ''}
            </td>
        </tr>
    `;
}

function renderProgressIndicator(article) {
    const status = article.status;

    if (status === 'queued') {
        return '<span class="progress-text">Waiting...</span>';
    } else if (status === 'processing') {
        return `
            <div class="progress-bar">
                <div class="progress-fill processing-animation" style="width: 50%;"></div>
            </div>
        `;
    } else if (status === 'completed' || status === 'published') {
        return '<span class="progress-text">✓ Complete</span>';
    } else if (status === 'failed') {
        return '<span class="progress-text error">✗ Failed</span>';
    }

    return '';
}

function renderArticleDetail() {
    const { currentArticle } = wpBlogQueueState;

    if (!currentArticle) {
        return '<div class="error">Article not found</div>';
    }

    const config = wpBlogQueueState.configurations.find(
        c => c.configuration_id === currentArticle.configuration_id
    );

    return `
        <div class="wp-blog-article-detail">
            <div class="page-header">
                <div>
                    <button onclick="wpQueueShowDashboard()" class="btn btn-link">
                        ← Back to Queue
                    </button>
                    <h2>${escapeHtml(currentArticle.seed_keyword)}</h2>
                </div>
                <div class="header-actions">
                    ${currentArticle.status === 'failed' ? `
                        <button onclick="wpQueueRetryArticle('${currentArticle.article_id}')" class="btn btn-warning">
                            Retry
                        </button>
                    ` : ''}
                    ${currentArticle.wordpress_post_url ? `
                        <a href="${escapeHtml(currentArticle.wordpress_post_url)}" target="_blank" class="btn btn-primary">
                            View Post →
                        </a>
                    ` : ''}
                </div>
            </div>

            <div class="detail-grid">
                <div class="detail-section">
                    <h3>Article Information</h3>
                    <div class="detail-info">
                        <div class="info-row">
                            <span class="label">Status:</span>
                            <span class="value">${getStatusBadge(currentArticle.status)}</span>
                        </div>
                        <div class="info-row">
                            <span class="label">Configuration:</span>
                            <span class="value">${config ? escapeHtml(config.config_name) : 'Unknown'}</span>
                        </div>
                        <div class="info-row">
                            <span class="label">Seed Keyword:</span>
                            <span class="value"><strong>${escapeHtml(currentArticle.seed_keyword)}</strong></span>
                        </div>
                        <div class="info-row">
                            <span class="label">Created:</span>
                            <span class="value">${formatDateTime(currentArticle.created_at)}</span>
                        </div>
                        ${currentArticle.processing_started_at ? `
                            <div class="info-row">
                                <span class="label">Started:</span>
                                <span class="value">${formatDateTime(currentArticle.processing_started_at)}</span>
                            </div>
                        ` : ''}
                        ${currentArticle.processing_completed_at ? `
                            <div class="info-row">
                                <span class="label">Completed:</span>
                                <span class="value">${formatDateTime(currentArticle.processing_completed_at)}</span>
                            </div>
                        ` : ''}
                        ${currentArticle.wordpress_post_id ? `
                            <div class="info-row">
                                <span class="label">WordPress Post ID:</span>
                                <span class="value">${currentArticle.wordpress_post_id}</span>
                            </div>
                        ` : ''}
                        ${currentArticle.error_message ? `
                            <div class="info-row error-row">
                                <span class="label">Error:</span>
                                <span class="value error">${escapeHtml(currentArticle.error_message)}</span>
                            </div>
                        ` : ''}
                        ${currentArticle.retry_count > 0 ? `
                            <div class="info-row">
                                <span class="label">Retry Count:</span>
                                <span class="value">${currentArticle.retry_count}</span>
                            </div>
                        ` : ''}
                    </div>
                </div>

                <div class="detail-section">
                    <h3>Status Timeline</h3>
                    ${renderStatusTimeline(currentArticle)}
                </div>
            </div>

            <div id="execution-log-container">
                ${currentArticle.status === 'completed' || currentArticle.status === 'published' || currentArticle.status === 'failed' ? `
                    <button onclick="wpQueueLoadExecutionLog('${currentArticle.article_id}')" class="btn btn-secondary">
                        Load Execution Log
                    </button>
                ` : ''}
            </div>
        </div>
    `;
}

function renderStatusTimeline(article) {
    const timeline = [];

    timeline.push({
        status: 'queued',
        timestamp: article.created_at,
        active: true
    });

    if (article.processing_started_at) {
        timeline.push({
            status: 'processing',
            timestamp: article.processing_started_at,
            active: true
        });
    }

    if (article.processing_completed_at) {
        if (article.status === 'failed') {
            timeline.push({
                status: 'failed',
                timestamp: article.processing_completed_at,
                active: true
            });
        } else {
            timeline.push({
                status: 'completed',
                timestamp: article.processing_completed_at,
                active: true
            });

            if (article.status === 'published') {
                timeline.push({
                    status: 'published',
                    timestamp: article.updated_at,
                    active: true
                });
            }
        }
    }

    return `
        <div class="timeline">
            ${timeline.map((item, index) => `
                <div class="timeline-item ${item.active ? 'active' : ''}">
                    <div class="timeline-marker"></div>
                    <div class="timeline-content">
                        <div class="timeline-status">${getStatusLabel(item.status)}</div>
                        <div class="timeline-time">${formatDateTime(item.timestamp)}</div>
                    </div>
                </div>
            `).join('')}
        </div>
    `;
}

function renderPagination() {
    const { pagination } = wpBlogQueueState;
    const currentPage = Math.floor(pagination.offset / pagination.limit) + 1;
    const totalPages = Math.ceil(pagination.total / pagination.limit);

    if (totalPages <= 1) return '';

    return `
        <div class="pagination">
            <button
                onclick="wpQueueChangePage(${currentPage - 1})"
                ${currentPage === 1 ? 'disabled' : ''}
                class="btn btn-sm btn-secondary"
            >
                Previous
            </button>
            <span class="pagination-info">
                Page ${currentPage} of ${totalPages}
            </span>
            <button
                onclick="wpQueueChangePage(${currentPage + 1})"
                ${currentPage === totalPages ? 'disabled' : ''}
                class="btn btn-sm btn-secondary"
            >
                Next
            </button>
        </div>
    `;
}

// ============================================================================
// Event Handlers
// ============================================================================

async function wpQueueLoadArticles() {
    wpBlogQueueState.isLoading = true;

    try {
        const { filters, pagination } = wpBlogQueueState;

        const result = await fetchArticles(
            filters.status,
            filters.configId,
            pagination.limit,
            pagination.offset
        );

        wpBlogQueueState.articles = result.articles;
        wpBlogQueueState.statistics = result.statistics;
        wpBlogQueueState.pagination.total = result.articles.length;

        wpQueueRenderCurrentView();
    } catch (error) {
        wpBlogQueueState.lastError = error.message;
        showToast('Failed to load articles: ' + error.message, 'error');
    } finally {
        wpBlogQueueState.isLoading = false;
    }
}

function wpQueueShowDashboard() {
    wpBlogQueueState.viewMode = 'dashboard';
    wpQueueRenderCurrentView();
}

async function wpQueueShowDetail(articleId) {
    wpBlogQueueState.isLoading = true;

    try {
        const article = await fetchArticle(articleId);
        wpBlogQueueState.currentArticle = article;
        wpBlogQueueState.viewMode = 'detail';
        wpQueueRenderCurrentView();
    } catch (error) {
        showToast('Failed to load article: ' + error.message, 'error');
    } finally {
        wpBlogQueueState.isLoading = false;
    }
}

async function wpQueueShowAddForm() {
    if (wpBlogQueueState.configurations.length === 0) {
        showToast('Please create a configuration first', 'warning');
        return;
    }

    const html = `
        <form id="wp-queue-add-form" onsubmit="wpQueueSubmitArticle(event)">
            <div class="form-group">
                <label for="configuration_id">Configuration *</label>
                <select id="configuration_id" name="configuration_id" required>
                    <option value="">Select configuration...</option>
                    ${wpBlogQueueState.configurations.map(config => `
                        <option value="${config.configuration_id}">
                            ${escapeHtml(config.config_name)}
                        </option>
                    `).join('')}
                </select>
            </div>

            <div class="form-group">
                <label for="seed_keyword">Seed Keyword *</label>
                <input
                    type="text"
                    id="seed_keyword"
                    name="seed_keyword"
                    required
                    placeholder="e.g., Best WordPress Plugins 2025"
                />
                <small>The main topic/keyword for the article</small>
            </div>

            <div class="form-actions">
                <button type="button" onclick="closeModal()" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Queue Article</button>
            </div>
        </form>
    `;

    showModal('Queue New Article', html);
}

async function wpQueueSubmitArticle(event) {
    event.preventDefault();

    const formData = new FormData(event.target);
    const data = {
        configuration_id: formData.get('configuration_id'),
        seed_keyword: formData.get('seed_keyword')
    };

    try {
        await addArticle(data);
        showToast('Article queued successfully!', 'success');
        closeModal();
        await wpQueueLoadArticles();
    } catch (error) {
        showToast('Failed to queue article: ' + error.message, 'error');
    }
}

async function wpQueueRetryArticle(articleId) {
    try {
        await requeueArticle(articleId);
        showToast('Article requeued successfully', 'success');

        if (wpBlogQueueState.viewMode === 'detail') {
            await wpQueueShowDetail(articleId);
        } else {
            await wpQueueLoadArticles();
        }
    } catch (error) {
        showToast('Failed to requeue article: ' + error.message, 'error');
    }
}

async function wpQueueDeleteArticle(articleId) {
    const confirmed = await showConfirmation(
        'Delete Article',
        'Are you sure you want to delete this article from the queue?',
        'danger'
    );

    if (!confirmed) return;

    try {
        await deleteArticle(articleId);
        showToast('Article deleted successfully', 'success');
        await wpQueueLoadArticles();
    } catch (error) {
        showToast('Failed to delete article: ' + error.message, 'error');
    }
}

async function wpQueueLoadExecutionLog(articleId) {
    const container = document.getElementById('execution-log-container');
    container.innerHTML = '<div class="loading-spinner">Loading execution log...</div>';

    try {
        const log = await getExecutionLog(articleId);
        container.innerHTML = `
            <div class="execution-log">
                <h3>Execution Log</h3>
                <pre>${JSON.stringify(log, null, 2)}</pre>
            </div>
        `;
    } catch (error) {
        container.innerHTML = `
            <div class="error-message">
                Failed to load execution log: ${escapeHtml(error.message)}
            </div>
        `;
    }
}

function wpQueueFilterByStatus(status) {
    wpBlogQueueState.filters.status = status;
    wpBlogQueueState.pagination.offset = 0;
    wpQueueLoadArticles();
}

function wpQueueFilterByConfig(configId) {
    wpBlogQueueState.filters.configId = configId;
    wpBlogQueueState.pagination.offset = 0;
    wpQueueLoadArticles();
}

function wpQueueFilterBySearch(searchTerm) {
    wpBlogQueueState.filters.search = searchTerm;
    // Note: Client-side filtering only
    wpQueueRenderCurrentView();
}

function wpQueueChangePage(page) {
    wpBlogQueueState.pagination.offset = (page - 1) * wpBlogQueueState.pagination.limit;
    wpQueueLoadArticles();
}

// ============================================================================
// Real-time Updates
// ============================================================================

function wpQueueStartAutoRefresh() {
    if (wpBlogQueueState.refreshInterval) {
        clearInterval(wpBlogQueueState.refreshInterval);
    }

    wpBlogQueueState.refreshInterval = setInterval(async () => {
        // Only refresh if we're on the dashboard view and there are processing articles
        if (wpBlogQueueState.viewMode === 'dashboard') {
            const hasProcessing = wpBlogQueueState.articles.some(a => a.status === 'processing');
            if (hasProcessing) {
                await wpQueueLoadArticles();
            }
        }
    }, 10000); // 10 seconds
}

function wpQueueStopAutoRefresh() {
    if (wpBlogQueueState.refreshInterval) {
        clearInterval(wpBlogQueueState.refreshInterval);
        wpBlogQueueState.refreshInterval = null;
    }
}

// ============================================================================
// Render Controller
// ============================================================================

function wpQueueRenderCurrentView() {
    const container = document.getElementById('page-content');
    if (!container) return;

    let html = '';

    if (wpBlogQueueState.isLoading) {
        html = '<div class="loading-spinner">Loading...</div>';
    } else {
        switch (wpBlogQueueState.viewMode) {
            case 'detail':
                html = renderArticleDetail();
                break;
            default:
                html = renderQueueDashboard();
        }
    }

    container.innerHTML = html;
}

// ============================================================================
// Utility Functions
// ============================================================================

function getStatusBadge(status) {
    const badges = {
        queued: '<span class="badge badge-secondary">Queued</span>',
        processing: '<span class="badge badge-info">Processing</span>',
        completed: '<span class="badge badge-success">Completed</span>',
        published: '<span class="badge badge-success">Published</span>',
        failed: '<span class="badge badge-danger">Failed</span>'
    };
    return badges[status] || status;
}

function getStatusLabel(status) {
    const labels = {
        queued: 'Queued',
        processing: 'Processing',
        completed: 'Completed',
        published: 'Published',
        failed: 'Failed'
    };
    return labels[status] || status;
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDateTime(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// ============================================================================
// Initialization
// ============================================================================

async function initWordPressBlogQueueUI() {
    // Load configurations first
    try {
        const configs = await wpBlogApiCall('wordpress_blog_list_configs');
        wpBlogQueueState.configurations = configs.configurations || [];
    } catch (error) {
        console.error('Failed to load configurations:', error);
    }

    // Load articles
    await wpQueueLoadArticles();

    // Start auto-refresh
    wpQueueStartAutoRefresh();
}

// Export for testing
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        wpBlogQueueState,
        initWordPressBlogQueueUI,
        wpQueueLoadArticles,
        wpQueueShowDashboard,
        wpQueueShowDetail,
        wpQueueSubmitArticle,
        wpQueueRetryArticle,
        wpQueueDeleteArticle
    };
}
