---
applyTo: "public/admin/**/*.{js,css,html}"
description: "Regras específicas para Admin UI (interface administrativa)"
---

# Instruções para Admin UI - gpt-chatbot-boilerplate

## Arquivos Alvo
- `public/admin/index.html` - Página principal da interface admin
- `public/admin/admin.js` - JavaScript principal da interface
- `public/admin/admin.css` - Estilos da interface
- `public/admin/agent-workspace.js` - Editor de agentes
- `public/admin/prompt-builder.js` - Construtor de prompts

## Filosofia da Admin UI

### Princípios
- **Single Page Application**: Navegação sem reloads, usando JavaScript
- **RESTful API**: Comunicação via `/admin-api.php`
- **User Experience**: Interface intuitiva e responsiva
- **Feedback Visual**: Loading states, success/error messages
- **Validação**: Client-side + server-side validation
- **Acessibilidade**: Navegação por teclado, ARIA labels

### Arquitetura
- **Vanilla JavaScript**: Sem frameworks, mantém simplicidade
- **Modular**: Funcionalidades organizadas em módulos
- **State Management**: Estado da aplicação gerenciado centralmente
- **Event-Driven**: Comunicação entre componentes via eventos

## Estrutura de Código

### Organização do admin.js
```javascript
// Estado global da aplicação
const AppState = {
    currentUser: null,
    currentView: 'dashboard',
    agents: [],
    selectedAgent: null,
    
    // Methods
    setCurrentUser(user) {
        this.currentUser = user;
        this.triggerUpdate();
    },
    
    triggerUpdate() {
        window.dispatchEvent(new CustomEvent('app-state-changed', {
            detail: { state: this }
        }));
    }
};

// API Client
const API = {
    baseURL: '/admin-api.php',
    
    async request(action, data = {}, method = 'POST') {
        const url = `${this.baseURL}?action=${action}`;
        
        try {
            const response = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: method === 'POST' ? JSON.stringify(data) : undefined
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            return await response.json();
        } catch (error) {
            console.error('API request failed:', error);
            throw error;
        }
    },
    
    // Métodos específicos
    async listAgents() {
        return this.request('list_agents', {}, 'GET');
    },
    
    async createAgent(agentData) {
        return this.request('create_agent', agentData);
    },
    
    async updateAgent(agentId, agentData) {
        return this.request('update_agent', { id: agentId, ...agentData });
    },
    
    async deleteAgent(agentId) {
        return this.request('delete_agent', { id: agentId });
    }
};

// UI Components
const UI = {
    showLoading(message = 'Loading...') {
        const loader = document.getElementById('loader');
        if (loader) {
            loader.querySelector('.loading-message').textContent = message;
            loader.classList.remove('hidden');
        }
    },
    
    hideLoading() {
        const loader = document.getElementById('loader');
        if (loader) {
            loader.classList.add('hidden');
        }
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
    },
    
    showError(message) {
        this.showNotification(message, 'error');
    },
    
    showSuccess(message) {
        this.showNotification(message, 'success');
    },
    
    confirmDialog(message) {
        return new Promise((resolve) => {
            const confirmed = confirm(message);
            resolve(confirmed);
        });
    }
};

// Views/Pages
const Views = {
    async showDashboard() {
        UI.showLoading('Loading dashboard...');
        
        try {
            const container = document.getElementById('main-content');
            container.innerHTML = `
                <div class="dashboard">
                    <h2>Dashboard</h2>
                    <!-- Dashboard content -->
                </div>
            `;
            
            // Load dashboard data
            await this.loadDashboardData();
            
        } catch (error) {
            UI.showError('Failed to load dashboard');
        } finally {
            UI.hideLoading();
        }
    },
    
    async showAgentsList() {
        UI.showLoading('Loading agents...');
        
        try {
            const agents = await API.listAgents();
            const container = document.getElementById('main-content');
            
            container.innerHTML = `
                <div class="agents-list">
                    <div class="page-header">
                        <h2>Agents</h2>
                        <button class="btn btn-primary" onclick="Views.showCreateAgent()">
                            Create Agent
                        </button>
                    </div>
                    <div class="agents-grid">
                        ${agents.map(agent => this.renderAgentCard(agent)).join('')}
                    </div>
                </div>
            `;
            
        } catch (error) {
            UI.showError('Failed to load agents');
        } finally {
            UI.hideLoading();
        }
    },
    
    renderAgentCard(agent) {
        return `
            <div class="agent-card" data-agent-id="${agent.id}">
                <h3>${escapeHtml(agent.name)}</h3>
                <p class="agent-model">${escapeHtml(agent.model)}</p>
                <div class="agent-actions">
                    <button onclick="Views.editAgent(${agent.id})">Edit</button>
                    <button onclick="Views.deleteAgent(${agent.id})">Delete</button>
                </div>
            </div>
        `;
    }
};

// Utilities
function escapeHtml(unsafe) {
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    initializeApp();
});

async function initializeApp() {
    try {
        // Verificar autenticação
        const user = await API.request('get_current_user', {}, 'GET');
        AppState.setCurrentUser(user);
        
        // Setup navigation
        setupNavigation();
        
        // Load initial view
        Views.showDashboard();
        
    } catch (error) {
        // Redirect to login
        window.location.href = '/login.html';
    }
}
```

## Padrões de UI

### Forms
```javascript
// Validação e submissão de formulário
async function submitAgentForm(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);
    
    // Validação client-side
    if (!data.name || data.name.trim() === '') {
        UI.showError('Agent name is required');
        return;
    }
    
    if (!data.model) {
        UI.showError('Model is required');
        return;
    }
    
    UI.showLoading('Creating agent...');
    
    try {
        const result = await API.createAgent(data);
        
        if (result.success) {
            UI.showSuccess('Agent created successfully');
            Views.showAgentsList();
        } else {
            UI.showError(result.error || 'Failed to create agent');
        }
    } catch (error) {
        UI.showError('Failed to create agent: ' + error.message);
    } finally {
        UI.hideLoading();
    }
}
```

### Tabelas com Dados
```javascript
function renderTable(data, columns) {
    const headers = columns.map(col => 
        `<th>${escapeHtml(col.label)}</th>`
    ).join('');
    
    const rows = data.map(row => {
        const cells = columns.map(col => {
            const value = row[col.field];
            const formatted = col.formatter ? col.formatter(value, row) : value;
            return `<td>${escapeHtml(String(formatted))}</td>`;
        }).join('');
        
        return `<tr>${cells}</tr>`;
    }).join('');
    
    return `
        <table class="data-table">
            <thead>
                <tr>${headers}</tr>
            </thead>
            <tbody>
                ${rows}
            </tbody>
        </table>
    `;
}

// Uso
const columns = [
    { field: 'name', label: 'Name' },
    { field: 'model', label: 'Model' },
    { 
        field: 'created_at', 
        label: 'Created',
        formatter: (value) => new Date(value).toLocaleDateString()
    },
    {
        field: 'id',
        label: 'Actions',
        formatter: (id) => `
            <button onclick="Views.editAgent(${id})">Edit</button>
            <button onclick="Views.deleteAgent(${id})">Delete</button>
        `
    }
];

const tableHTML = renderTable(agents, columns);
```

### Modal Dialogs
```javascript
const Modal = {
    create(title, content, actions) {
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.innerHTML = `
            <div class="modal-overlay" onclick="Modal.close()"></div>
            <div class="modal-content">
                <div class="modal-header">
                    <h3>${escapeHtml(title)}</h3>
                    <button class="modal-close" onclick="Modal.close()">×</button>
                </div>
                <div class="modal-body">
                    ${content}
                </div>
                <div class="modal-footer">
                    ${actions}
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Adicionar ao stack de modais
        this.stack = this.stack || [];
        this.stack.push(modal);
        
        return modal;
    },
    
    close() {
        if (this.stack && this.stack.length > 0) {
            const modal = this.stack.pop();
            modal.remove();
        }
    },
    
    confirm(title, message) {
        return new Promise((resolve) => {
            const modal = this.create(
                title,
                `<p>${escapeHtml(message)}</p>`,
                `
                    <button class="btn btn-secondary" onclick="Modal.closeWithResult(false)">
                        Cancel
                    </button>
                    <button class="btn btn-primary" onclick="Modal.closeWithResult(true)">
                        Confirm
                    </button>
                `
            );
            
            modal.resolve = resolve;
        });
    },
    
    closeWithResult(result) {
        if (this.stack && this.stack.length > 0) {
            const modal = this.stack[this.stack.length - 1];
            if (modal.resolve) {
                modal.resolve(result);
            }
            this.close();
        }
    }
};

// Uso
const confirmed = await Modal.confirm('Delete Agent', 'Are you sure?');
if (confirmed) {
    await API.deleteAgent(agentId);
}
```

## Estilos CSS

### Estrutura
```css
/* Variables */
:root {
    --primary-color: #007bff;
    --secondary-color: #6c757d;
    --success-color: #28a745;
    --danger-color: #dc3545;
    --warning-color: #ffc107;
    --info-color: #17a2b8;
    
    --text-color: #333;
    --bg-color: #f5f5f5;
    --card-bg: #ffffff;
    
    --spacing-xs: 4px;
    --spacing-sm: 8px;
    --spacing-md: 16px;
    --spacing-lg: 24px;
    --spacing-xl: 32px;
    
    --border-radius: 4px;
    --box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

/* Layout */
.admin-layout {
    display: flex;
    min-height: 100vh;
}

.sidebar {
    width: 250px;
    background: var(--card-bg);
    border-right: 1px solid #e0e0e0;
    padding: var(--spacing-lg);
}

.main-content {
    flex: 1;
    padding: var(--spacing-lg);
    background: var(--bg-color);
}

/* Components */
.btn {
    padding: var(--spacing-sm) var(--spacing-md);
    border: none;
    border-radius: var(--border-radius);
    cursor: pointer;
    font-size: 14px;
    transition: background-color 0.2s;
}

.btn-primary {
    background: var(--primary-color);
    color: white;
}

.btn-primary:hover {
    background: #0056b3;
}

.card {
    background: var(--card-bg);
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    padding: var(--spacing-md);
    margin-bottom: var(--spacing-md);
}

/* Notifications */
.notification {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: var(--spacing-md);
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    z-index: 1000;
    animation: slideIn 0.3s;
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.notification-success {
    background: var(--success-color);
    color: white;
}

.notification-error {
    background: var(--danger-color);
    color: white;
}

/* Responsive */
@media (max-width: 768px) {
    .admin-layout {
        flex-direction: column;
    }
    
    .sidebar {
        width: 100%;
        border-right: none;
        border-bottom: 1px solid #e0e0e0;
    }
}
```

## Segurança

### XSS Prevention
```javascript
// SEMPRE escapar HTML de fontes não confiáveis
function escapeHtml(unsafe) {
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// Usar ao renderizar dados do servidor
element.innerHTML = `<div>${escapeHtml(userData.name)}</div>`;

// Ou usar textContent quando possível
element.textContent = userData.name;
```

### CSRF Protection
```javascript
// Session cookie é automaticamente incluído com credentials: 'include'
fetch('/admin-api.php', {
    credentials: 'include',
    // ...
});
```

### Input Validation
```javascript
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function validateAgentData(data) {
    const errors = [];
    
    if (!data.name || data.name.trim().length === 0) {
        errors.push('Name is required');
    }
    
    if (!data.model) {
        errors.push('Model is required');
    }
    
    if (data.temperature && (data.temperature < 0 || data.temperature > 2)) {
        errors.push('Temperature must be between 0 and 2');
    }
    
    return errors;
}
```

## Acessibilidade

### ARIA Labels
```html
<button 
    aria-label="Delete agent"
    onclick="deleteAgent(123)">
    <i class="icon-delete"></i>
</button>

<div role="alert" aria-live="polite" class="notification">
    Agent created successfully
</div>
```

### Navegação por Teclado
```javascript
// Capturar atalhos de teclado
document.addEventListener('keydown', (e) => {
    // Ctrl/Cmd + S para salvar
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        saveCurrentForm();
    }
    
    // Esc para fechar modal
    if (e.key === 'Escape') {
        Modal.close();
    }
});
```

### Focus Management
```javascript
function showModal(content) {
    const modal = Modal.create('Title', content, 'Actions');
    
    // Focar primeiro elemento focável
    const firstInput = modal.querySelector('input, button, select, textarea');
    if (firstInput) {
        firstInput.focus();
    }
}
```

## Performance

### Debouncing
```javascript
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Uso: search com debounce
const searchInput = document.getElementById('search');
searchInput.addEventListener('input', debounce((e) => {
    performSearch(e.target.value);
}, 300));
```

### Lazy Loading
```javascript
// Carregar dados conforme necessário
async function loadAgentDetails(agentId) {
    const cached = AppState.agentCache[agentId];
    if (cached) {
        return cached;
    }
    
    const agent = await API.getAgent(agentId);
    AppState.agentCache[agentId] = agent;
    return agent;
}
```

## Testing

### Manual Testing Checklist
- [ ] Todas as funcionalidades funcionam
- [ ] Loading states aparecem adequadamente
- [ ] Error messages são claras
- [ ] Success feedback é mostrado
- [ ] Forms validam inputs corretamente
- [ ] Navegação funciona sem reloads
- [ ] Responsivo em mobile/tablet/desktop
- [ ] Funciona em Chrome, Firefox, Safari
- [ ] Navegação por teclado funciona
- [ ] Screen reader friendly (se possível)

## Checklist de Revisão

Antes de commitar mudanças na Admin UI:

- [ ] Código JavaScript organizado e modular
- [ ] Escapar HTML de fontes não confiáveis
- [ ] API calls tratam erros apropriadamente
- [ ] Loading states implementados
- [ ] Feedback visual para usuário
- [ ] Validação client-side implementada
- [ ] CSS responsivo
- [ ] Acessibilidade básica (aria-labels, navegação)
- [ ] `npm run lint` passa (se ESLint configurado)
- [ ] Testado em múltiplos navegadores
- [ ] Testado em diferentes tamanhos de tela
- [ ] Sem console.log desnecessários
- [ ] Comentários em lógica complexa
