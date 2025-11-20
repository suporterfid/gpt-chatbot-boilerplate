/**
 * LeadSense CRM - Kanban Board Interface
 * 
 * Manages the visual CRM board with drag-and-drop functionality
 */

window.LeadSenseCRM = (function() {
    // State management
    const state = {
        pipelines: [],
        currentPipeline: null,
        currentBoard: null,
        draggingLead: null,
        filters: {
            search: '',
            minScore: null
        }
    };

    // API helper
    const API = {
        baseURL: '/admin-api.php',
        
        async request(action, data = {}, method = 'GET') {
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
            } else if (method === 'GET' && data) {
                const params = new URLSearchParams(data);
                return fetch(`${url}&${params}`, options).then(r => r.json());
            }
            
            return fetch(url, options).then(r => r.json());
        },
        
        async listPipelines() {
            return this.request('leadsense.crm.list_pipelines');
        },
        
        async getPipeline(id) {
            return this.request('leadsense.crm.get_pipeline', { id });
        },
        
        async getLeadsBoard(pipelineId, filters = {}) {
            return this.request('leadsense.crm.list_leads_board', { 
                pipeline_id: pipelineId,
                ...filters 
            });
        },
        
        async moveLead(leadId, fromStageId, toStageId, pipelineId) {
            return this.request('leadsense.crm.move_lead', {
                lead_id: leadId,
                from_stage_id: fromStageId,
                to_stage_id: toStageId,
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
                text: text
            }, 'POST');
        }
    };

    // UI Components
    const UI = {
        showLoading() {
            const loader = document.getElementById('board-loading');
            const board = document.getElementById('kanban-board');
            const empty = document.getElementById('board-empty');
            
            if (loader) loader.style.display = 'flex';
            if (board) board.style.display = 'none';
            if (empty) empty.style.display = 'none';
        },
        
        hideLoading() {
            const loader = document.getElementById('board-loading');
            if (loader) loader.style.display = 'none';
        },
        
        showBoard() {
            const board = document.getElementById('kanban-board');
            const empty = document.getElementById('board-empty');
            
            if (board) board.style.display = 'flex';
            if (empty) empty.style.display = 'none';
        },
        
        showEmpty() {
            const board = document.getElementById('kanban-board');
            const empty = document.getElementById('board-empty');
            
            if (board) board.style.display = 'none';
            if (empty) empty.style.display = 'flex';
        },
        
        showNotification(message, type = 'info') {
            // Simple notification - could be enhanced
            console.log(`[${type.toUpperCase()}] ${message}`);
            alert(message);
        }
    };

    // Main functions
    async function init(rootElement) {
        console.log('Initializing LeadSense CRM...');
        
        // Load pipelines
        await loadPipelines();
        
        // Setup event listeners
        setupEventListeners();
        
        // Load default pipeline board
        if (state.pipelines.length > 0) {
            const defaultPipeline = state.pipelines.find(p => p.is_default) || state.pipelines[0];
            await loadBoard(defaultPipeline.id);
        } else {
            UI.showEmpty();
        }
    }

    async function loadPipelines() {
        try {
            const response = await API.listPipelines();
            
            if (response.error) {
                UI.showNotification('Failed to load pipelines: ' + response.error.message, 'error');
                return;
            }
            
            state.pipelines = response.data.pipelines || [];
            renderPipelineSelector();
            
        } catch (error) {
            console.error('Failed to load pipelines:', error);
            UI.showNotification('Failed to load pipelines', 'error');
        }
    }

    function renderPipelineSelector() {
        const select = document.getElementById('pipeline-select');
        if (!select) return;
        
        select.innerHTML = '';
        
        if (state.pipelines.length === 0) {
            select.innerHTML = '<option value="">No pipelines available</option>';
            return;
        }
        
        state.pipelines.forEach(pipeline => {
            const option = document.createElement('option');
            option.value = pipeline.id;
            option.textContent = pipeline.name;
            if (pipeline.is_default) {
                option.selected = true;
            }
            select.appendChild(option);
        });
    }

    async function loadBoard(pipelineId) {
        if (!pipelineId) {
            UI.showEmpty();
            return;
        }
        
        UI.showLoading();
        
        try {
            const filters = {};
            if (state.filters.search) {
                filters.q = state.filters.search;
            }
            if (state.filters.minScore !== null) {
                filters.min_score = state.filters.minScore;
            }
            
            const response = await API.getLeadsBoard(pipelineId, filters);
            
            if (response.error) {
                UI.showNotification('Failed to load board: ' + response.error.message, 'error');
                UI.hideLoading();
                return;
            }
            
            state.currentPipeline = pipelineId;
            state.currentBoard = response.data;
            
            renderBoard();
            UI.hideLoading();
            UI.showBoard();
            
        } catch (error) {
            console.error('Failed to load board:', error);
            UI.showNotification('Failed to load board', 'error');
            UI.hideLoading();
        }
    }

    function renderBoard() {
        const boardEl = document.getElementById('kanban-board');
        if (!boardEl || !state.currentBoard) return;
        
        boardEl.innerHTML = '';
        
        const stages = state.currentBoard.stages || [];
        
        stages.forEach(stage => {
            const columnEl = createStageColumn(stage);
            boardEl.appendChild(columnEl);
        });
    }

    function createStageColumn(stage) {
        const column = document.createElement('div');
        column.className = 'kanban-column';
        column.dataset.stageId = stage.id;
        
        // Header
        const header = document.createElement('div');
        header.className = 'column-header';
        header.style.borderTopColor = stage.color || '#6b7280';
        
        const title = document.createElement('div');
        title.className = 'column-title';
        title.textContent = stage.name;
        
        const count = document.createElement('div');
        count.className = 'column-count';
        count.textContent = `${stage.lead_count || 0} leads`;
        
        header.appendChild(title);
        header.appendChild(count);
        
        // Body
        const body = document.createElement('div');
        body.className = 'column-body';
        body.dataset.stageId = stage.id;
        
        // Enable drop
        body.addEventListener('dragover', handleDragOver);
        body.addEventListener('drop', handleDrop);
        body.addEventListener('dragleave', handleDragLeave);
        
        // Render leads
        (stage.leads || []).forEach(lead => {
            const card = createLeadCard(lead, stage.id);
            body.appendChild(card);
        });
        
        // Empty state
        if (!stage.leads || stage.leads.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'column-empty';
            empty.textContent = 'No leads in this stage';
            body.appendChild(empty);
        }
        
        column.appendChild(header);
        column.appendChild(body);
        
        return column;
    }

    function createLeadCard(lead, stageId) {
        const card = document.createElement('div');
        card.className = 'lead-card';
        card.draggable = true;
        card.dataset.leadId = lead.id;
        card.dataset.stageId = stageId;
        
        // Drag handlers
        card.addEventListener('dragstart', handleDragStart);
        card.addEventListener('dragend', handleDragEnd);
        
        // Header
        const cardHeader = document.createElement('div');
        cardHeader.className = 'card-header';
        
        const avatar = document.createElement('div');
        avatar.className = 'avatar';
        avatar.textContent = getInitials(lead.name || 'Unknown');
        
        const meta = document.createElement('div');
        meta.className = 'meta';
        
        const name = document.createElement('div');
        name.className = 'name';
        name.textContent = lead.name || 'Unknown Lead';
        
        const channel = document.createElement('div');
        channel.className = 'channel';
        channel.textContent = `${lead.owner?.name || 'Unassigned'} (${lead.source_channel || 'web'})`;
        
        meta.appendChild(name);
        meta.appendChild(channel);
        
        cardHeader.appendChild(avatar);
        cardHeader.appendChild(meta);
        
        // Body
        const cardBody = document.createElement('div');
        cardBody.className = 'card-body';
        
        if (lead.company) {
            const company = document.createElement('p');
            company.className = 'company';
            company.textContent = lead.company;
            cardBody.appendChild(company);
        }
        
        if (lead.email) {
            const email = document.createElement('p');
            email.className = 'email';
            email.textContent = lead.email;
            cardBody.appendChild(email);
        }
        
        // Footer
        const cardFooter = document.createElement('div');
        cardFooter.className = 'card-footer';
        
        const badges = document.createElement('div');
        badges.className = 'badges';
        
        // Status badge
        const statusBadge = document.createElement('span');
        statusBadge.className = `badge status-${lead.status || 'new'}`;
        statusBadge.textContent = lead.status || 'new';
        badges.appendChild(statusBadge);
        
        // Score badge
        const score = parseInt(lead.score || 0);
        const scoreClass = score >= 80 ? 'high' : score >= 60 ? 'medium' : 'low';
        const scoreBadge = document.createElement('span');
        scoreBadge.className = `badge score-${scoreClass}`;
        scoreBadge.textContent = `Score: ${score}`;
        badges.appendChild(scoreBadge);
        
        // Tags
        if (lead.tags && Array.isArray(lead.tags)) {
            lead.tags.forEach(tag => {
                const tagBadge = document.createElement('span');
                tagBadge.className = 'badge tag';
                tagBadge.textContent = tag;
                badges.appendChild(tagBadge);
            });
        }
        
        const timestamp = document.createElement('div');
        timestamp.className = 'timestamp';
        if (lead.last_activity_at) {
            timestamp.textContent = formatDate(lead.last_activity_at);
        } else {
            timestamp.textContent = formatDate(lead.created_at);
        }
        
        cardFooter.appendChild(badges);
        cardFooter.appendChild(timestamp);
        
        // Assemble card
        card.appendChild(cardHeader);
        card.appendChild(cardBody);
        card.appendChild(cardFooter);
        
        // Click to open details
        card.addEventListener('click', () => openLeadDetails(lead));
        
        return card;
    }

    // Drag and drop handlers
    function handleDragStart(e) {
        state.draggingLead = {
            leadId: e.currentTarget.dataset.leadId,
            fromStageId: e.currentTarget.dataset.stageId
        };
        
        e.currentTarget.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/html', e.currentTarget.innerHTML);
    }

    function handleDragEnd(e) {
        e.currentTarget.classList.remove('dragging');
        
        // Remove drag-over class from all columns
        document.querySelectorAll('.column-body').forEach(col => {
            col.classList.remove('drag-over');
        });
    }

    function handleDragOver(e) {
        if (e.preventDefault) {
            e.preventDefault();
        }
        
        e.dataTransfer.dropEffect = 'move';
        e.currentTarget.classList.add('drag-over');
        
        return false;
    }

    function handleDragLeave(e) {
        e.currentTarget.classList.remove('drag-over');
    }

    async function handleDrop(e) {
        if (e.stopPropagation) {
            e.stopPropagation();
        }
        
        e.currentTarget.classList.remove('drag-over');
        
        const toStageId = e.currentTarget.dataset.stageId;
        
        if (!state.draggingLead || !toStageId) {
            return false;
        }
        
        const { leadId, fromStageId } = state.draggingLead;
        
        if (fromStageId === toStageId) {
            return false;
        }
        
        // Move lead via API
        try {
            const response = await API.moveLead(
                leadId,
                fromStageId,
                toStageId,
                state.currentPipeline
            );
            
            if (response.error) {
                UI.showNotification('Failed to move lead: ' + response.error.message, 'error');
                return false;
            }
            
            // Reload board to reflect changes
            await loadBoard(state.currentPipeline);
            
        } catch (error) {
            console.error('Failed to move lead:', error);
            UI.showNotification('Failed to move lead', 'error');
        }
        
        return false;
    }

    function openLeadDetails(lead) {
        // Placeholder for lead details modal
        console.log('Opening lead details:', lead);
        UI.showNotification(`Lead: ${lead.name}\nEmail: ${lead.email}\nCompany: ${lead.company}\nScore: ${lead.score}`, 'info');
    }

    function openCreatePipelineModal() {
        const pipelineName = prompt('Enter a name for your new pipeline:');

        if (!pipelineName || pipelineName.trim() === '') {
            return;
        }

        createPipeline(pipelineName.trim());
    }

    async function createPipeline(name) {
        try {
            const response = await API.request('leadsense.crm.create_pipeline', {
                name: name
            }, 'POST');

            if (response.error) {
                UI.showNotification('Failed to create pipeline: ' + response.error.message, 'error');
                return;
            }

            UI.showNotification('Pipeline created successfully!', 'success');

            // Reload pipelines and switch to the new one
            await loadPipelines();
            if (response.data && response.data.id) {
                await loadBoard(response.data.id);

                // Update the select dropdown
                const pipelineSelect = document.getElementById('pipeline-select');
                if (pipelineSelect) {
                    pipelineSelect.value = response.data.id;
                }
            }

        } catch (error) {
            console.error('Failed to create pipeline:', error);
            UI.showNotification('Failed to create pipeline', 'error');
        }
    }

    function setupEventListeners() {
        // Pipeline selector
        const pipelineSelect = document.getElementById('pipeline-select');
        if (pipelineSelect) {
            pipelineSelect.addEventListener('change', (e) => {
                loadBoard(e.target.value);
            });
        }

        // Create Pipeline buttons
        const createPipelineBtn = document.getElementById('create-pipeline-btn');
        if (createPipelineBtn) {
            createPipelineBtn.addEventListener('click', () => {
                openCreatePipelineModal();
            });
        }

        const createFirstPipelineBtn = document.getElementById('create-first-pipeline-btn');
        if (createFirstPipelineBtn) {
            createFirstPipelineBtn.addEventListener('click', () => {
                openCreatePipelineModal();
            });
        }

        // Search
        const searchInput = document.getElementById('search-leads');
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    state.filters.search = e.target.value;
                    loadBoard(state.currentPipeline);
                }, 300);
            });
        }

        // Score filter
        const scoreFilter = document.getElementById('filter-score');
        if (scoreFilter) {
            scoreFilter.addEventListener('change', (e) => {
                state.filters.minScore = e.target.value ? parseInt(e.target.value) : null;
                loadBoard(state.currentPipeline);
            });
        }
    }

    // Utility functions
    function getInitials(name) {
        if (!name) return '?';
        const parts = name.trim().split(' ');
        if (parts.length >= 2) {
            return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
        }
        return name.substring(0, 2).toUpperCase();
    }

    function formatDate(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        
        if (diffMins < 60) {
            return `${diffMins}m ago`;
        }
        
        const diffHours = Math.floor(diffMins / 60);
        if (diffHours < 24) {
            return `${diffHours}h ago`;
        }
        
        const diffDays = Math.floor(diffHours / 24);
        if (diffDays < 7) {
            return `${diffDays}d ago`;
        }
        
        return date.toLocaleDateString();
    }

    // Public API
    return {
        init,
        loadBoard,
        loadPipelines,
        openLeadDetails
    };
})();
