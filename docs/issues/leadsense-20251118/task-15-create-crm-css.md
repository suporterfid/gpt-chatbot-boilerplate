# Task 15: Create CRM UI Styling

## Objective
Create CSS for LeadSense CRM Kanban board and components.

## File
`public/admin/leadsense-crm.css`

## Key Styles

### 1. Kanban Board Layout
```css
.ls-crm-board {
    display: flex;
    gap: 16px;
    overflow-x: auto;
    padding: 24px;
    height: calc(100vh - 200px);
}

.ls-crm-column {
    flex: 0 0 320px;
    background: #f8f9fa;
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    max-height: 100%;
}

.ls-crm-column-header {
    padding: 16px;
    border-bottom: 1px solid #e0e0e0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.ls-crm-column-body {
    flex: 1;
    overflow-y: auto;
    padding: 12px;
}
```

### 2. Lead Cards
```css
.lead-card {
    background: white;
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    cursor: move;
    transition: box-shadow 0.2s;
}

.lead-card:hover {
    box-shadow: 0 4px 6px rgba(0,0,0,0.15);
}

.lead-card.dragging {
    opacity: 0.5;
}

.card-header {
    display: flex;
    gap: 12px;
    margin-bottom: 8px;
}

.avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #8b5cf6;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
}

.badge {
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.score-high { background: #dcfce7; color: #16a34a; }
.score-medium { background: #fef9c3; color: #ca8a04; }
.score-low { background: #fee2e2; color: #dc2626; }
```

### 3. Lead Drawer
```css
.drawer {
    position: fixed;
    top: 0;
    right: 0;
    bottom: 0;
    width: 500px;
    background: white;
    box-shadow: -2px 0 8px rgba(0,0,0,0.15);
    transform: translateX(100%);
    transition: transform 0.3s;
    z-index: 1000;
}

.drawer.open {
    transform: translateX(0);
}

.drawer-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 999;
}
```

### 4. Responsive Design
```css
@media (max-width: 1024px) {
    .ls-crm-column {
        flex: 0 0 280px;
    }
}

@media (max-width: 768px) {
    .ls-crm-board {
        flex-direction: column;
    }
    
    .ls-crm-column {
        flex: 1 0 auto;
        max-height: 400px;
    }
    
    .drawer {
        width: 100%;
    }
}
```

## Prerequisites
- Task 13: JavaScript components
- Task 14: Lead drawer

## Testing
- Test in different screen sizes
- Test dark mode (if applicable)
- Test drag states
