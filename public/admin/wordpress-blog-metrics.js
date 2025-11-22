/**
 * WordPress Blog Processing Metrics Dashboard
 *
 * Metrics dashboard with:
 * - Overview cards (processed, success rate, avg time, costs)
 * - Recent activity feed
 * - System health indicator
 * - Auto-refresh functionality
 *
 * @requires admin.js (for API_ENDPOINT, showToast)
 */

// ============================================================================
// State Management
// ============================================================================

const wpBlogMetricsState = {
    metrics: null,
    health: null,
    recentArticles: [],
    isLoading: false,
    refreshInterval: null,
    dateRangeDays: 7,
    lastError: null
};

// ============================================================================
// API Functions
// ============================================================================

async function wpMetricsApiCall(action, options = {}) {
    const { method = 'GET', params = {} } = options;

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

async function fetchMetrics(days = 7) {
    const data = await wpMetricsApiCall('wordpress_blog_get_metrics', {
        params: { days }
    });
    return data.metrics;
}

async function fetchHealthCheck() {
    const data = await wpMetricsApiCall('wordpress_blog_health_check');
    return data.health;
}

async function fetchQueueStatus() {
    const data = await wpMetricsApiCall('wordpress_blog_get_queue_status');
    return data.status;
}

async function fetchRecentArticles() {
    const data = await wpMetricsApiCall('wordpress_blog_list_articles', {
        params: { limit: 10, offset: 0 }
    });
    return data.articles || [];
}

// ============================================================================
// UI Rendering Functions
// ============================================================================

function renderMetricsDashboard() {
    const { metrics, health, recentArticles, dateRangeDays } = wpBlogMetricsState;

    if (!metrics || !health) {
        return '<div class="loading-spinner">Loading metrics...</div>';
    }

    return `
        <div class="wp-blog-metrics-container">
            <div class="page-header">
                <h2>Processing Metrics & Health</h2>
                <div class="header-actions">
                    <select onchange="wpMetricsChangeDateRange(this.value)" class="date-range-select">
                        <option value="7" ${dateRangeDays === 7 ? 'selected' : ''}>Last 7 Days</option>
                        <option value="14" ${dateRangeDays === 14 ? 'selected' : ''}>Last 14 Days</option>
                        <option value="30" ${dateRangeDays === 30 ? 'selected' : ''}>Last 30 Days</option>
                        <option value="90" ${dateRangeDays === 90 ? 'selected' : ''}>Last 90 Days</option>
                    </select>
                    <button onclick="wpMetricsRefresh()" class="btn btn-secondary">
                        🔄 Refresh
                    </button>
                </div>
            </div>

            <!-- System Health -->
            <div class="health-banner ${getHealthClass(health.status)}">
                ${renderHealthBanner(health)}
            </div>

            <!-- Overview Cards -->
            <div class="metrics-overview">
                ${renderOverviewCards(metrics)}
            </div>

            <!-- Two Column Layout -->
            <div class="metrics-grid">
                <!-- Status Breakdown -->
                <div class="metrics-section">
                    <h3>Status Breakdown</h3>
                    ${renderStatusBreakdown(metrics)}
                </div>

                <!-- Recent Activity -->
                <div class="metrics-section">
                    <h3>Recent Activity</h3>
                    ${renderRecentActivity(recentArticles)}
                </div>
            </div>

            <!-- Health Details -->
            <div class="metrics-section">
                <h3>System Component Status</h3>
                ${renderHealthDetails(health)}
            </div>
        </div>
    `;
}

function renderHealthBanner(health) {
    const icon = health.status === 'healthy' ? '✅' : health.status === 'degraded' ? '⚠️' : '❌';
    const status = health.status.toUpperCase();

    return `
        <div class="health-icon">${icon}</div>
        <div class="health-info">
            <div class="health-status">System Status: ${status}</div>
            <div class="health-timestamp">Last checked: ${formatDateTime(health.timestamp)}</div>
        </div>
    `;
}

function renderOverviewCards(metrics) {
    const total = metrics.total_articles || 0;
    const byStatus = metrics.by_status || {};

    const completed = (byStatus.completed?.count || 0) + (byStatus.published?.count || 0);
    const failed = byStatus.failed?.count || 0;
    const successRate = total > 0 ? ((completed / total) * 100).toFixed(1) : 0;

    // Calculate average duration
    let avgDuration = 0;
    let durationCount = 0;
    Object.values(byStatus).forEach(status => {
        if (status.avg_duration_seconds) {
            avgDuration += status.avg_duration_seconds;
            durationCount++;
        }
    });
    const avgDurationDisplay = durationCount > 0
        ? formatDuration(avgDuration / durationCount)
        : 'N/A';

    // Estimate costs (based on Phase 4 estimates: ~$1.38 per article)
    const estimatedCost = (completed * 1.38).toFixed(2);

    return `
        <div class="overview-card">
            <div class="overview-value">${total}</div>
            <div class="overview-label">Total Processed</div>
            <div class="overview-sublabel">${metrics.period_days} days</div>
        </div>
        <div class="overview-card">
            <div class="overview-value">${successRate}%</div>
            <div class="overview-label">Success Rate</div>
            <div class="overview-sublabel">${completed} completed</div>
        </div>
        <div class="overview-card">
            <div class="overview-value">${avgDurationDisplay}</div>
            <div class="overview-label">Avg Processing Time</div>
            <div class="overview-sublabel">Per article</div>
        </div>
        <div class="overview-card">
            <div class="overview-value">$${estimatedCost}</div>
            <div class="overview-label">Estimated Costs</div>
            <div class="overview-sublabel">OpenAI API</div>
        </div>
    `;
}

function renderStatusBreakdown(metrics) {
    const byStatus = metrics.by_status || {};

    if (Object.keys(byStatus).length === 0) {
        return '<div class="empty-state">No data available</div>';
    }

    const statuses = [
        { key: 'queued', label: 'Queued', color: '#6c757d' },
        { key: 'processing', label: 'Processing', color: '#0dcaf0' },
        { key: 'completed', label: 'Completed', color: '#198754' },
        { key: 'published', label: 'Published', color: '#0d6efd' },
        { key: 'failed', label: 'Failed', color: '#dc3545' }
    ];

    return `
        <div class="status-breakdown">
            ${statuses.map(status => {
                const data = byStatus[status.key];
                const count = data?.count || 0;
                const avgDuration = data?.avg_duration_seconds
                    ? formatDuration(data.avg_duration_seconds)
                    : 'N/A';

                return `
                    <div class="status-item">
                        <div class="status-bar" style="background-color: ${status.color}; width: ${Math.min(count * 10, 100)}%;"></div>
                        <div class="status-details">
                            <div class="status-label">${status.label}</div>
                            <div class="status-count">${count} articles</div>
                            ${avgDuration !== 'N/A' ? `
                                <div class="status-duration">Avg: ${avgDuration}</div>
                            ` : ''}
                        </div>
                    </div>
                `;
            }).join('')}
        </div>
    `;
}

function renderRecentActivity(articles) {
    if (!articles || articles.length === 0) {
        return '<div class="empty-state">No recent activity</div>';
    }

    return `
        <div class="activity-feed">
            ${articles.map(article => {
                const icon = getActivityIcon(article.status);
                const statusClass = `activity-${article.status}`;

                return `
                    <div class="activity-item ${statusClass}">
                        <div class="activity-icon">${icon}</div>
                        <div class="activity-content">
                            <div class="activity-title">${escapeHtml(article.seed_keyword)}</div>
                            <div class="activity-meta">
                                ${getStatusLabel(article.status)} · ${formatRelativeTime(article.updated_at)}
                            </div>
                        </div>
                    </div>
                `;
            }).join('')}
        </div>
    `;
}

function renderHealthDetails(health) {
    const checks = health.checks || {};

    return `
        <div class="health-checks">
            ${Object.entries(checks).map(([component, check]) => {
                const icon = check.status === 'ok' ? '✓' : check.status === 'warning' ? '⚠' : '✗';
                const statusClass = `health-${check.status}`;

                return `
                    <div class="health-check-item ${statusClass}">
                        <div class="health-check-icon">${icon}</div>
                        <div class="health-check-content">
                            <div class="health-check-name">${formatComponentName(component)}</div>
                            <div class="health-check-message">${escapeHtml(check.message)}</div>
                            ${check.statistics ? `
                                <div class="health-check-stats">
                                    ${Object.entries(check.statistics).map(([key, value]) =>
                                        `<span class="stat-badge">${key}: ${value}</span>`
                                    ).join('')}
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `;
            }).join('')}
        </div>
    `;
}

// ============================================================================
// Event Handlers
// ============================================================================

async function wpMetricsLoadAll() {
    wpBlogMetricsState.isLoading = true;

    try {
        const [metrics, health, articles] = await Promise.all([
            fetchMetrics(wpBlogMetricsState.dateRangeDays),
            fetchHealthCheck(),
            fetchRecentArticles()
        ]);

        wpBlogMetricsState.metrics = metrics;
        wpBlogMetricsState.health = health;
        wpBlogMetricsState.recentArticles = articles;
        wpBlogMetricsState.lastError = null;

        wpMetricsRender();
    } catch (error) {
        wpBlogMetricsState.lastError = error.message;
        showToast('Failed to load metrics: ' + error.message, 'error');
    } finally {
        wpBlogMetricsState.isLoading = false;
    }
}

async function wpMetricsRefresh() {
    showToast('Refreshing metrics...', 'info');
    await wpMetricsLoadAll();
    showToast('Metrics updated', 'success');
}

async function wpMetricsChangeDateRange(days) {
    wpBlogMetricsState.dateRangeDays = parseInt(days, 10);
    await wpMetricsLoadAll();
}

// ============================================================================
// Auto-refresh
// ============================================================================

function wpMetricsStartAutoRefresh() {
    if (wpBlogMetricsState.refreshInterval) {
        clearInterval(wpBlogMetricsState.refreshInterval);
    }

    wpBlogMetricsState.refreshInterval = setInterval(async () => {
        await wpMetricsLoadAll();
    }, 30000); // 30 seconds
}

function wpMetricsStopAutoRefresh() {
    if (wpBlogMetricsState.refreshInterval) {
        clearInterval(wpBlogMetricsState.refreshInterval);
        wpBlogMetricsState.refreshInterval = null;
    }
}

// ============================================================================
// Render Controller
// ============================================================================

function wpMetricsRender() {
    const container = document.getElementById('page-content');
    if (!container) return;

    const html = wpBlogMetricsState.isLoading && !wpBlogMetricsState.metrics
        ? '<div class="loading-spinner">Loading metrics...</div>'
        : renderMetricsDashboard();

    container.innerHTML = html;
}

// ============================================================================
// Utility Functions
// ============================================================================

function getHealthClass(status) {
    const classes = {
        healthy: 'health-healthy',
        degraded: 'health-degraded',
        unhealthy: 'health-unhealthy'
    };
    return classes[status] || 'health-unknown';
}

function getActivityIcon(status) {
    const icons = {
        queued: '📝',
        processing: '⚙️',
        completed: '✅',
        published: '🚀',
        failed: '❌'
    };
    return icons[status] || '•';
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

function formatComponentName(component) {
    return component.split('_')
        .map(word => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

function formatDuration(seconds) {
    if (!seconds || seconds < 0) return 'N/A';

    if (seconds < 60) {
        return `${Math.round(seconds)}s`;
    } else if (seconds < 3600) {
        const minutes = Math.floor(seconds / 60);
        const secs = Math.round(seconds % 60);
        return `${minutes}m ${secs}s`;
    } else {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        return `${hours}h ${minutes}m`;
    }
}

function formatRelativeTime(dateString) {
    if (!dateString) return 'N/A';

    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffSec = Math.floor(diffMs / 1000);
    const diffMin = Math.floor(diffSec / 60);
    const diffHour = Math.floor(diffMin / 60);
    const diffDay = Math.floor(diffHour / 24);

    if (diffSec < 60) {
        return 'just now';
    } else if (diffMin < 60) {
        return `${diffMin} minute${diffMin !== 1 ? 's' : ''} ago`;
    } else if (diffHour < 24) {
        return `${diffHour} hour${diffHour !== 1 ? 's' : ''} ago`;
    } else if (diffDay < 7) {
        return `${diffDay} day${diffDay !== 1 ? 's' : ''} ago`;
    } else {
        return formatDateTime(dateString);
    }
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

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ============================================================================
// Initialization
// ============================================================================

async function initWordPressBlogMetricsUI() {
    await wpMetricsLoadAll();
    wpMetricsStartAutoRefresh();
}

// Export for testing
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        wpBlogMetricsState,
        initWordPressBlogMetricsUI,
        wpMetricsLoadAll,
        wpMetricsRefresh,
        wpMetricsChangeDateRange
    };
}
