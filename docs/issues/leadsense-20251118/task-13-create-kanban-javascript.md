# Task 13: Create JavaScript Kanban Board

## Objective
Create interactive Kanban board component in vanilla JavaScript.

## File
`public/admin/js/leadsense-crm.js`

## Component Structure

### 1. Main Module
```javascript
window.LeadSenseCRM = (function() {
    const state = {
        pipelineId: null,
        pipelines: [],
        stages: [],
        leadsByStage: {},
        dragging: null
    };
    
    const API = {
        listPipelines: async () => {},
        loadBoard: async (pipelineId) => {},
        moveLead: async (leadId, fromStage, toStage) => {},
        updateLead: async (leadId, data) => {},
        addNote: async (leadId, text) => {}
    };
    
    const UI = {
        renderPipelineSelector: () => {},
        renderBoard: () => {},
        renderColumn: (stage, leads) => {},
        renderLeadCard: (lead) => {},
        showLeadDrawer: (leadId) => {},
        showNotification: (message, type) => {}
    };
    
    function init(rootEl) {
        // Initialize component
    }
    
    return { init };
})();

document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('leadsense-crm-root');
    if (root) {
        LeadSenseCRM.init(root);
    }
});
```

### 2. Drag & Drop
```javascript
function attachDragAndDrop() {
    // Using HTML5 Drag and Drop API
    document.querySelectorAll('.lead-card').forEach(card => {
        card.draggable = true;
        card.addEventListener('dragstart', handleDragStart);
        card.addEventListener('dragend', handleDragEnd);
    });
    
    document.querySelectorAll('.column-body').forEach(column => {
        column.addEventListener('dragover', handleDragOver);
        column.addEventListener('drop', handleDrop);
    });
}
```

### 3. Lead Card Template
```javascript
function renderLeadCard(lead) {
    return `
        <div class="lead-card" draggable="true" data-lead-id="${lead.id}">
            <div class="card-header">
                <div class="avatar">${getInitials(lead.name)}</div>
                <div class="meta">
                    <div class="name">${escapeHtml(lead.name)}</div>
                    <div class="company">${escapeHtml(lead.company || '')}</div>
                </div>
                <button class="btn-icon" onclick="showLeadDrawer('${lead.id}')">
                    ⋮
                </button>
            </div>
            <div class="card-body">
                <p class="interest">${escapeHtml(lead.interest || '')}</p>
            </div>
            <div class="card-footer">
                <span class="badge score-${lead.intent_level}">${lead.score}</span>
                <span class="owner">${lead.owner?.name || 'Unassigned'}</span>
                <span class="timestamp">${formatDate(lead.created_at)}</span>
            </div>
        </div>
    `;
}
```

## Prerequisites
- Task 12: Page structure
- Task 10: API endpoints

## Testing
- Test drag and drop
- Test pipeline switching
- Test card updates
