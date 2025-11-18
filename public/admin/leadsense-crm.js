/**
 * LeadSense CRM - Kanban Board Interface
 * 
 * Task 13: JavaScript Kanban Board
 * Task 14: Lead Detail Drawer
 */

(function() {
    'use strict';
    
    // Module state
    const State = {
        currentPipeline: null,
        pipelines: [],
        stages: [],
        leads: {},
        selectedLead: null,
        dragging: null,
        filters: {
            search: '',
            owner_id: null,
            min_score: null
        }
    };
    
    // API Client
    const API = {
        baseURL: '/admin-api.php',
        
        async request(action, data = null, method = 'GET') {
            const url = `${this.baseURL}?action=${action}`;
            const options = {
                method,
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json'
                }
            };
            
            if (method === 'POST' && data) {
                options.body = JSON.stringify(data);
            }
            
            try {
                const response = await fetch(url, options);
                if (!response.ok) {
                    const error = await response.json();
                    throw new Error(error.error || `HTTP ${response.status}`);
                }
                return await response.json();
            } catch (error) {
                console.error('API request failed:', error);
                throw error;
            }
        },
        
        async listPipelines() {
            return this.request('leadsense.crm.list_pipelines');
        },
        
        async getPipeline(id) {
            return this.request(`leadsense.crm.get_pipeline&id=${id}`);
        },
        
        async getLeadsBoard(pipelineId, filters = {}) {
            const params = new URLSearchParams({ pipeline_id: pipelineId, ...filters });
            return this.request(`leadsense.crm.list_leads_board&${params.toString()}`);
        },
        
        async moveLead(leadId, toStageId, fromStageId, pipelineId) {
            return this.request('leadsense.crm.move_lead', {
                lead_id: leadId,
                to_stage_id: toStageId,
                from_stage_id: fromStageId,
                pipeline_id: pipelineId
            }, 'POST');
        },
        
        async updateLead(leadId, data) {
            return this.request('leadsense.crm.update_lead_inline', {
                id: leadId,
                ...data
            }, 'POST');
        },
        
        async addNote(leadId, text) {
            return this.request('leadsense.crm.add_note', {
                lead_id: leadId,
                text
            }, 'POST');
        },
        
        async getLeadDetails(leadId) {
            return this.request(`get_lead&id=${leadId}`);
        }
    };
    
    // UI Helpers
    const UI = {
        showLoading(message = 'Loading...') {
            const container = document.getElementById('leadsense-crm-content');
            if (container) {
                container.innerHTML = `
                    <div class="loading-spinner">
                        <div class="spinner"></div>
                        <p>${escapeHtml(message)}</p>
                    </div>
                `;
            }
        },
        
        showError(message) {
            this.showNotification(message, 'error');
        },
        
        showSuccess(message) {
            this.showNotification(message, 'success');
        },
        
        showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.classList.add('fade-out');
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }
    };
    
    // Pipeline Selector
    function renderPipelineSelector() {
        const container = document.getElementById('crm-pipeline-selector');
        if (!container) return;
        
        const html = `
            <div class="pipeline-selector-wrapper">
                <label for="pipeline-select">Pipeline:</label>
                <select id="pipeline-select" class="pipeline-select">
                    ${State.pipelines.map(p => `
                        <option value="${p.id}" ${p.id === State.currentPipeline?.id ? 'selected' : ''}>
                            ${escapeHtml(p.name)}${p.is_default ? ' (Default)' : ''}
                        </option>
                    `).join('')}
                </select>
                <button class="btn btn-secondary btn-sm" id="refresh-board-btn" title="Refresh">
                    🔄
                </button>
            </div>
        `;
        
        container.innerHTML = html;
        
        // Event listeners
        document.getElementById('pipeline-select')?.addEventListener('change', function(e) {
            const pipelineId = e.target.value;
            const pipeline = State.pipelines.find(p => p.id === pipelineId);
            if (pipeline) {
                State.currentPipeline = pipeline;
                loadBoard(pipelineId);
            }
        });
        
        document.getElementById('refresh-board-btn')?.addEventListener('click', function() {
            if (State.currentPipeline) {
                loadBoard(State.currentPipeline.id);
            }
        });
    }
    
    // Kanban Board Rendering
    function renderBoard() {
        const container = document.getElementById('crm-board-container');
        if (!container) return;
        
        const stages = State.stages || [];
        
        if (stages.length === 0) {
            container.innerHTML = '<div class="empty-state">No stages found. Please create a pipeline with stages.</div>';
            return;
        }
        
        const html = `
            <div class="kanban-board">
                ${stages.map(stage => renderColumn(stage)).join('')}
            </div>
        `;
        
        container.innerHTML = html;
        
        // Attach drag-and-drop listeners
        attachDragAndDrop();
    }
    
    function renderColumn(stage) {
        const leads = State.leads[stage.id] || [];
        const leadCount = stage.lead_count || 0;
        
        return `
            <div class="kanban-column" data-stage-id="${stage.id}">
                <div class="kanban-column-header" style="${stage.color ? `border-top: 3px solid ${stage.color}` : ''}">
                    <div class="column-header-content">
                        <h3 class="column-title">${escapeHtml(stage.name)}</h3>
                        <span class="column-count">${leadCount} leads</span>
                    </div>
                </div>
                <div class="kanban-column-body" data-stage-id="${stage.id}">
                    ${leads.map(lead => renderLeadCard(lead, stage)).join('')}
                    ${leads.length === 0 ? '<div class="column-empty">No leads in this stage</div>' : ''}
                </div>
            </div>
        `;
    }
    
    function renderLeadCard(lead, stage) {
        const scoreClass = getScoreClass(lead.score || 0);
        const statusClass = lead.status === 'open' ? 'status-open' : 'status-resolved';
        const tags = typeof lead.tags === 'string' ? JSON.parse(lead.tags || '[]') : (lead.tags || []);
        const initials = getInitials(lead.name || 'Unknown');
        
        return `
            <div class="lead-card" 
                 draggable="true" 
                 data-lead-id="${lead.id}"
                 data-stage-id="${stage.id}">
                <div class="lead-card-header">
                    <div class="lead-avatar">${initials}</div>
                    <div class="lead-meta">
                        <div class="lead-name">${escapeHtml(lead.name || 'Unknown')}</div>
                        <div class="lead-channel">
                            ${lead.owner_id ? '👤 ' + escapeHtml(lead.owner_id) : ''}
                            ${lead.source_channel ? '(' + escapeHtml(lead.source_channel) + ')' : ''}
                        </div>
                    </div>
                    <button class="btn-icon btn-edit" data-lead-id="${lead.id}" title="View Details">
                        ✎
                    </button>
                </div>
                <div class="lead-card-body">
                    ${lead.company ? `<p class="lead-company">🏢 ${escapeHtml(lead.company)}</p>` : ''}
                    ${lead.interest ? `<p class="lead-snippet">${escapeHtml(lead.interest.substring(0, 100))}...</p>` : ''}
                </div>
                <div class="lead-card-footer">
                    <span class="badge ${statusClass}">${lead.status === 'open' ? 'Open' : 'Resolved'}</span>
                    <span class="badge score-badge ${scoreClass}">Score: ${lead.score || 0}</span>
                    ${lead.deal_value ? `<span class="badge deal-badge">💰 ${formatCurrency(lead.deal_value, lead.currency)}</span>` : ''}
                </div>
                ${tags.length > 0 ? `
                    <div class="lead-card-tags">
                        ${tags.map(tag => `<span class="tag">${escapeHtml(tag)}</span>`).join('')}
                    </div>
                ` : ''}
            </div>
        `;
    }
    
    // Drag and Drop
    function attachDragAndDrop() {
        const cards = document.querySelectorAll('.lead-card');
        const columns = document.querySelectorAll('.kanban-column-body');
        
        cards.forEach(card => {
            card.addEventListener('dragstart', handleDragStart);
            card.addEventListener('dragend', handleDragEnd);
        });
        
        columns.forEach(column => {
            column.addEventListener('dragover', handleDragOver);
            column.addEventListener('drop', handleDrop);
            column.addEventListener('dragenter', handleDragEnter);
            column.addEventListener('dragleave', handleDragLeave);
        });
        
        // Click to open drawer
        document.querySelectorAll('.btn-edit').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const leadId = this.getAttribute('data-lead-id');
                openLeadDrawer(leadId);
            });
        });
        
        document.querySelectorAll('.lead-card').forEach(card => {
            card.addEventListener('dblclick', function() {
                const leadId = this.getAttribute('data-lead-id');
                openLeadDrawer(leadId);
            });
        });
    }
    
    function handleDragStart(e) {
        const leadId = this.getAttribute('data-lead-id');
        const stageId = this.getAttribute('data-stage-id');
        
        State.dragging = { leadId, stageId };
        
        this.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/html', this.innerHTML);
    }
    
    function handleDragEnd(e) {
        this.classList.remove('dragging');
        
        // Remove all drag-over classes
        document.querySelectorAll('.kanban-column-body').forEach(col => {
            col.classList.remove('drag-over');
        });
    }
    
    function handleDragOver(e) {
        if (e.preventDefault) {
            e.preventDefault();
        }
        e.dataTransfer.dropEffect = 'move';
        return false;
    }
    
    function handleDragEnter(e) {
        this.classList.add('drag-over');
    }
    
    function handleDragLeave(e) {
        if (e.target === this) {
            this.classList.remove('drag-over');
        }
    }
    
    async function handleDrop(e) {
        if (e.stopPropagation) {
            e.stopPropagation();
        }
        
        this.classList.remove('drag-over');
        
        if (!State.dragging) return false;
        
        const toStageId = this.getAttribute('data-stage-id');
        const { leadId, stageId: fromStageId } = State.dragging;
        
        if (toStageId === fromStageId) {
            return false; // Same column, no move
        }
        
        try {
            UI.showLoading('Moving lead...');
            
            await API.moveLead(leadId, toStageId, fromStageId, State.currentPipeline.id);
            
            UI.showSuccess('Lead moved successfully');
            
            // Reload board to reflect changes
            await loadBoard(State.currentPipeline.id);
            
        } catch (error) {
            UI.showError('Failed to move lead: ' + error.message);
            console.error('Move lead error:', error);
        }
        
        State.dragging = null;
        return false;
    }
    
    // Lead Drawer (Task 14)
    async function openLeadDrawer(leadId) {
        try {
            const response = await API.getLeadDetails(leadId);
            const lead = response.lead || response;
            
            State.selectedLead = lead;
            
            renderLeadDrawer(lead);
            
            // Show drawer
            const drawer = document.getElementById('lead-drawer');
            if (drawer) {
                drawer.classList.add('open');
            }
            
        } catch (error) {
            UI.showError('Failed to load lead details: ' + error.message);
            console.error('Load lead error:', error);
        }
    }
    
    function renderLeadDrawer(lead) {
        let drawerHtml = document.getElementById('lead-drawer');
        
        if (!drawerHtml) {
            drawerHtml = document.createElement('div');
            drawerHtml.id = 'lead-drawer';
            drawerHtml.className = 'lead-drawer';
            document.body.appendChild(drawerHtml);
        }
        
        const tags = typeof lead.tags === 'string' ? JSON.parse(lead.tags || '[]') : (lead.tags || []);
        
        drawerHtml.innerHTML = `
            <div class="drawer-overlay" id="drawer-overlay"></div>
            <div class="drawer-content">
                <div class="drawer-header">
                    <h2>${escapeHtml(lead.name || 'Unknown Lead')}</h2>
                    <button class="drawer-close" id="drawer-close-btn">✕</button>
                </div>
                <div class="drawer-tabs">
                    <button class="drawer-tab active" data-tab="overview">Overview</button>
                    <button class="drawer-tab" data-tab="timeline">Timeline</button>
                </div>
                <div class="drawer-body">
                    <div class="drawer-tab-content active" id="drawer-tab-overview">
                        ${renderOverviewTab(lead)}
                    </div>
                    <div class="drawer-tab-content" id="drawer-tab-timeline">
                        ${renderTimelineTab(lead)}
                    </div>
                </div>
            </div>
        `;
        
        // Event listeners
        drawerHtml.querySelector('#drawer-close-btn')?.addEventListener('click', closeLeadDrawer);
        drawerHtml.querySelector('#drawer-overlay')?.addEventListener('click', closeLeadDrawer);
        
        // Tab switching
        drawerHtml.querySelectorAll('.drawer-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                const tabName = this.getAttribute('data-tab');
                switchTab(tabName);
            });
        });
        
        // Save button
        drawerHtml.querySelector('#save-lead-btn')?.addEventListener('click', saveLeadChanges);
        
        // Add note button
        drawerHtml.querySelector('#add-note-btn')?.addEventListener('click', addNoteToLead);
    }
    
    function renderOverviewTab(lead) {
        const dealValue = lead.deal_value || '';
        const currency = lead.currency || 'USD';
        const probability = lead.probability || '';
        const expectedClose = lead.expected_close_date || '';
        const tags = typeof lead.tags === 'string' ? JSON.parse(lead.tags || '[]') : (lead.tags || []);
        
        return `
            <div class="overview-section">
                <h3>Contact Information</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" id="lead-name" value="${escapeHtml(lead.name || '')}" />
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" id="lead-email" value="${escapeHtml(lead.email || '')}" />
                    </div>
                    <div class="form-group">
                        <label>Company</label>
                        <input type="text" id="lead-company" value="${escapeHtml(lead.company || '')}" />
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" id="lead-phone" value="${escapeHtml(lead.phone || '')}" />
                    </div>
                </div>
                
                <h3>Deal Information</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Deal Value</label>
                        <input type="number" id="lead-deal-value" value="${dealValue}" step="0.01" />
                    </div>
                    <div class="form-group">
                        <label>Currency</label>
                        <select id="lead-currency">
                            <option value="USD" ${currency === 'USD' ? 'selected' : ''}>USD</option>
                            <option value="BRL" ${currency === 'BRL' ? 'selected' : ''}>BRL</option>
                            <option value="EUR" ${currency === 'EUR' ? 'selected' : ''}>EUR</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Probability (%)</label>
                        <input type="number" id="lead-probability" value="${probability}" min="0" max="100" />
                    </div>
                    <div class="form-group">
                        <label>Expected Close Date</label>
                        <input type="date" id="lead-expected-close" value="${expectedClose}" />
                    </div>
                </div>
                
                <h3>Status & Score</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Status</label>
                        <select id="lead-status">
                            <option value="open" ${lead.status === 'open' ? 'selected' : ''}>Open</option>
                            <option value="closed" ${lead.status === 'closed' ? 'selected' : ''}>Closed</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Score</label>
                        <input type="number" id="lead-score" value="${lead.score || 0}" readonly />
                    </div>
                    <div class="form-group">
                        <label>Qualified</label>
                        <input type="checkbox" id="lead-qualified" ${lead.qualified ? 'checked' : ''} disabled />
                    </div>
                </div>
                
                <h3>Tags</h3>
                <div class="tags-input-wrapper">
                    <input type="text" id="tags-input" placeholder="Add tag and press Enter" />
                    <div class="tags-display" id="tags-display">
                        ${tags.map(tag => `
                            <span class="tag editable">
                                ${escapeHtml(tag)}
                                <button class="tag-remove" data-tag="${escapeHtml(tag)}">×</button>
                            </span>
                        `).join('')}
                    </div>
                </div>
                
                <div class="drawer-actions">
                    <button class="btn btn-primary" id="save-lead-btn">Save Changes</button>
                </div>
            </div>
        `;
    }
    
    function renderTimelineTab(lead) {
        // Load events via existing LeadSense API
        return `
            <div class="timeline-section">
                <div class="timeline-header">
                    <h3>Activity Timeline</h3>
                    <button class="btn btn-secondary btn-sm" id="add-note-btn">+ Add Note</button>
                </div>
                <div class="timeline-container" id="timeline-container">
                    <div class="loading-timeline">Loading events...</div>
                </div>
            </div>
        `;
    }
    
    function switchTab(tabName) {
        // Update tab buttons
        document.querySelectorAll('.drawer-tab').forEach(tab => {
            tab.classList.remove('active');
            if (tab.getAttribute('data-tab') === tabName) {
                tab.classList.add('active');
            }
        });
        
        // Update tab content
        document.querySelectorAll('.drawer-tab-content').forEach(content => {
            content.classList.remove('active');
        });
        
        const targetContent = document.getElementById(`drawer-tab-${tabName}`);
        if (targetContent) {
            targetContent.classList.add('active');
            
            // Load timeline if switching to that tab
            if (tabName === 'timeline' && State.selectedLead) {
                loadTimeline(State.selectedLead.id);
            }
        }
    }
    
    async function loadTimeline(leadId) {
        const container = document.getElementById('timeline-container');
        if (!container) return;
        
        try {
            // Use existing get_lead endpoint which includes events
            const response = await API.getLeadDetails(leadId);
            const events = response.events || [];
            
            if (events.length === 0) {
                container.innerHTML = '<div class="empty-timeline">No activity yet.</div>';
                return;
            }
            
            const html = events.map(event => {
                const payload = event.payload || (event.payload_json ? JSON.parse(event.payload_json) : {});
                return `
                    <div class="timeline-event">
                        <div class="timeline-icon">${getEventIcon(event.type)}</div>
                        <div class="timeline-content">
                            <div class="timeline-title">${getEventTitle(event.type)}</div>
                            <div class="timeline-details">${formatEventDetails(event.type, payload)}</div>
                            <div class="timeline-timestamp">${formatTimestamp(event.created_at)}</div>
                        </div>
                    </div>
                `;
            }).join('');
            
            container.innerHTML = html;
            
        } catch (error) {
            container.innerHTML = '<div class="error-timeline">Failed to load timeline.</div>';
            console.error('Load timeline error:', error);
        }
    }
    
    async function saveLeadChanges() {
        if (!State.selectedLead) return;
        
        const leadId = State.selectedLead.id;
        
        // Gather form data
        const data = {
            deal_value: parseFloat(document.getElementById('lead-deal-value')?.value) || null,
            currency: document.getElementById('lead-currency')?.value || 'USD',
            probability: parseInt(document.getElementById('lead-probability')?.value) || null,
            expected_close_date: document.getElementById('lead-expected-close')?.value || null,
            status: document.getElementById('lead-status')?.value || 'open',
            tags: getCurrentTags()
        };
        
        try {
            UI.showLoading('Saving changes...');
            
            await API.updateLead(leadId, data);
            
            UI.showSuccess('Lead updated successfully');
            
            // Reload board
            if (State.currentPipeline) {
                await loadBoard(State.currentPipeline.id);
            }
            
            closeLeadDrawer();
            
        } catch (error) {
            UI.showError('Failed to save changes: ' + error.message);
            console.error('Save lead error:', error);
        }
    }
    
    async function addNoteToLead() {
        if (!State.selectedLead) return;
        
        const text = prompt('Enter your note:');
        if (!text || text.trim() === '') return;
        
        try {
            await API.addNote(State.selectedLead.id, text.trim());
            
            UI.showSuccess('Note added');
            
            // Reload timeline
            await loadTimeline(State.selectedLead.id);
            
        } catch (error) {
            UI.showError('Failed to add note: ' + error.message);
            console.error('Add note error:', error);
        }
    }
    
    function closeLeadDrawer() {
        const drawer = document.getElementById('lead-drawer');
        if (drawer) {
            drawer.classList.remove('open');
        }
        State.selectedLead = null;
    }
    
    function getCurrentTags() {
        const tagsDisplay = document.getElementById('tags-display');
        if (!tagsDisplay) return [];
        
        const tags = [];
        tagsDisplay.querySelectorAll('.tag').forEach(tagEl => {
            const tagText = tagEl.textContent.replace('×', '').trim();
            if (tagText) tags.push(tagText);
        });
        return tags;
    }
    
    // Load Board
    async function loadBoard(pipelineId) {
        try {
            UI.showLoading('Loading board...');
            
            const result = await API.getLeadsBoard(pipelineId, State.filters);
            
            State.stages = result.stages || [];
            State.currentPipeline = result.pipeline || State.currentPipeline;
            
            // Group leads by stage
            State.leads = {};
            State.stages.forEach(stage => {
                State.leads[stage.id] = stage.leads || [];
            });
            
            renderBoard();
            
        } catch (error) {
            UI.showError('Failed to load board: ' + error.message);
            console.error('Load board error:', error);
        }
    }
    
    // Initialize
    async function initLeadSenseCRM() {
        try {
            UI.showLoading('Loading pipelines...');
            
            const result = await API.listPipelines();
            State.pipelines = result.pipelines || [];
            
            if (State.pipelines.length === 0) {
                UI.showError('No pipelines found. Please create a pipeline first.');
                return;
            }
            
            // Select default or first pipeline
            State.currentPipeline = State.pipelines.find(p => p.is_default) || State.pipelines[0];
            
            renderPipelineSelector();
            
            if (State.currentPipeline) {
                await loadBoard(State.currentPipeline.id);
            }
            
        } catch (error) {
            UI.showError('Failed to initialize CRM: ' + error.message);
            console.error('Init error:', error);
        }
    }
    
    // Utility functions
    function escapeHtml(unsafe) {
        if (!unsafe) return '';
        return String(unsafe)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
    
    function getInitials(name) {
        return name
            .split(' ')
            .map(n => n[0])
            .join('')
            .substring(0, 2)
            .toUpperCase();
    }
    
    function getScoreClass(score) {
        if (score >= 80) return 'score-high';
        if (score >= 50) return 'score-medium';
        return 'score-low';
    }
    
    function formatCurrency(value, currency = 'USD') {
        const formatter = new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: currency
        });
        return formatter.format(value);
    }
    
    function formatTimestamp(timestamp) {
        const date = new Date(timestamp);
        return date.toLocaleString();
    }
    
    function getEventIcon(type) {
        const icons = {
            'detected': '🔍',
            'updated': '✏️',
            'qualified': '✅',
            'notified': '🔔',
            'stage_changed': '➡️',
            'owner_changed': '👤',
            'pipeline_changed': '🔀',
            'deal_updated': '💰',
            'note': '📝'
        };
        return icons[type] || '📌';
    }
    
    function getEventTitle(type) {
        const titles = {
            'detected': 'Lead Detected',
            'updated': 'Lead Updated',
            'qualified': 'Lead Qualified',
            'notified': 'Notification Sent',
            'stage_changed': 'Stage Changed',
            'owner_changed': 'Owner Changed',
            'pipeline_changed': 'Pipeline Changed',
            'deal_updated': 'Deal Updated',
            'note': 'Note Added'
        };
        return titles[type] || type;
    }
    
    function formatEventDetails(type, payload) {
        if (type === 'stage_changed') {
            return `Moved from ${payload.old_stage_id || 'unknown'} to ${payload.new_stage_id || 'unknown'}`;
        }
        if (type === 'note' && payload.text) {
            return escapeHtml(payload.text);
        }
        return JSON.stringify(payload);
    }
    
    // Export to global scope
    window.LeadSenseCRM = {
        init: initLeadSenseCRM
    };
    
})();
