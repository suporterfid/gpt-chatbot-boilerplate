# Task 14: Create Lead Detail Drawer

## Objective
Create side panel/modal for viewing and editing lead details.

## Component
Part of `public/admin/js/leadsense-crm.js` or separate file

## Structure

### Drawer HTML
```html
<div id="lead-drawer" class="drawer" style="display: none;">
    <div class="drawer-overlay" onclick="closeLeadDrawer()"></div>
    <div class="drawer-content">
        <div class="drawer-header">
            <h2 id="lead-name">Lead Name</h2>
            <button onclick="closeLeadDrawer()">×</button>
        </div>
        
        <div class="drawer-tabs">
            <button class="tab active" data-tab="overview">Overview</button>
            <button class="tab" data-tab="timeline">Timeline</button>
        </div>
        
        <div class="drawer-body">
            <div id="tab-overview" class="tab-content active">
                <!-- Contact info, company, deal fields -->
            </div>
            <div id="tab-timeline" class="tab-content">
                <!-- Event timeline -->
            </div>
        </div>
    </div>
</div>
```

### Overview Tab
- Contact information (editable)
- Company details
- Pipeline & stage (with change controls)
- Owner assignment
- Deal value, probability, close date
- Tags
- Status

### Timeline Tab
```javascript
function renderTimeline(events) {
    return events.map(event => {
        const payload = JSON.parse(event.payload_json || '{}');
        return `
            <div class="timeline-event">
                <div class="event-icon">${getEventIcon(event.type)}</div>
                <div class="event-content">
                    <div class="event-title">${formatEventTitle(event)}</div>
                    <div class="event-description">${formatEventDescription(event, payload)}</div>
                    <div class="event-timestamp">${formatDate(event.created_at)}</div>
                </div>
            </div>
        `;
    }).join('');
}
```

### Inline Editing
```javascript
function enableInlineEditing() {
    document.querySelectorAll('.editable-field').forEach(field => {
        field.addEventListener('blur', async (e) => {
            const leadId = getCurrentLeadId();
            const fieldName = e.target.dataset.field;
            const newValue = e.target.value;
            
            await API.updateLead(leadId, { [fieldName]: newValue });
        });
    });
}
```

## Prerequisites
- Task 13: Kanban board
- Task 10: API endpoints

## Testing
- Test opening drawer
- Test inline editing
- Test timeline loading
