/**
 * Prompt Builder UI Module
 * Admin interface for generating AI agent specifications
 */

// Add Prompt Builder API methods to AdminAPI class
(function() {
    const originalAdminAPI = window.AdminAPI;
    
    window.AdminAPI = class extends originalAdminAPI {
        // Prompt Builder methods
        async generatePrompt(agentId, ideaText, guardrails = [], language = 'en', variables = {}) {
            return this.request('prompt_builder_generate', {
                method: 'POST',
                params: `&agent_id=${agentId}`,
                body: { idea_text: ideaText, guardrails, language, variables }
            });
        }

        async listPromptVersions(agentId) {
            return this.request('prompt_builder_list', {
                params: `&agent_id=${agentId}`
            });
        }

        async getPromptVersion(agentId, version) {
            return this.request('prompt_builder_get', {
                params: `&agent_id=${agentId}&version=${version}`
            });
        }

        async activatePromptVersion(agentId, version) {
            return this.request('prompt_builder_activate', {
                method: 'POST',
                params: `&agent_id=${agentId}&version=${version}`
            });
        }

        async deactivatePrompt(agentId) {
            return this.request('prompt_builder_deactivate', {
                method: 'POST',
                params: `&agent_id=${agentId}`
            });
        }

        async saveManualPrompt(agentId, promptMd, guardrails = []) {
            return this.request('prompt_builder_save_manual', {
                method: 'POST',
                params: `&agent_id=${agentId}`,
                body: { prompt_md: promptMd, guardrails }
            });
        }

        async deletePromptVersion(agentId, version) {
            return this.request('prompt_builder_delete', {
                method: 'POST',
                params: `&agent_id=${agentId}&version=${version}`
            });
        }

        async getGuardrailsCatalog() {
            return this.request('prompt_builder_catalog');
        }
    };
    
    // Recreate the api instance with the extended AdminAPI class
    if (typeof api !== 'undefined') {
        api = new window.AdminAPI();
        window.api = api;
    } else {
        window.api = new window.AdminAPI();
    }
})();

// Prompt Builder State
let promptBuilderState = {
    currentAgentId: null,
    currentVersion: null,
    guardrailsCatalog: [],
    selectedGuardrails: [],
    generating: false,
    generatedPrompt: null,
    previouslyFocusedElement: null,
    focusTrapHandler: null
};

const promptBuilderHooks = {
    onGenerationStart: null,
    onPromptGenerated: null
};

function registerPromptBuilderHooks(hooks = {}) {
    promptBuilderHooks.onGenerationStart = typeof hooks.onGenerationStart === 'function' ? hooks.onGenerationStart : null;
    promptBuilderHooks.onPromptGenerated = typeof hooks.onPromptGenerated === 'function' ? hooks.onPromptGenerated : null;
}

function setFieldError(fieldId, message = '') {
    const field = document.getElementById(fieldId);
    const errorElement = document.getElementById(`${fieldId}-error`);
    const formGroup = field ? field.closest('.form-group') : null;

    if (!field || !errorElement) {
        return;
    }

    if (message) {
        field.classList.add('is-invalid');
        field.setAttribute('aria-invalid', 'true');
        if (formGroup) {
            formGroup.classList.add('has-error');
        }
        errorElement.textContent = message;
        errorElement.classList.remove('hidden');
    } else {
        field.classList.remove('is-invalid');
        field.removeAttribute('aria-invalid');
        if (formGroup) {
            formGroup.classList.remove('has-error');
        }
        errorElement.textContent = '';
        errorElement.classList.add('hidden');
    }
}

function clearFieldError(fieldId) {
    setFieldError(fieldId);
}

function attachPromptBuilderValidationHandlers() {
    const ideaElement = document.getElementById('prompt-builder-idea');
    if (ideaElement && !ideaElement.dataset.validationBound) {
        ideaElement.addEventListener('input', () => clearFieldError('prompt-builder-idea'));
        ideaElement.dataset.validationBound = 'true';
    }

    const outputElement = document.getElementById('prompt-builder-output');
    if (outputElement && !outputElement.dataset.validationBound) {
        outputElement.addEventListener('input', () => clearFieldError('prompt-builder-output'));
        outputElement.dataset.validationBound = 'true';
    }
}

async function requestPromptGeneration(agentId, ideaText, guardrails = [], language = 'en') {
    if (promptBuilderHooks.onGenerationStart) {
        try {
            promptBuilderHooks.onGenerationStart({ agentId, ideaText, guardrails, language });
        } catch (error) {
            console.warn('Prompt Builder hook onGenerationStart failed', error);
        }
    }

    const result = await api.generatePrompt(
        agentId,
        ideaText,
        guardrails,
        language
    );

    if (promptBuilderHooks.onPromptGenerated) {
        try {
            promptBuilderHooks.onPromptGenerated(result, { agentId, guardrails, language });
        } catch (error) {
            console.warn('Prompt Builder hook onPromptGenerated failed', error);
        }
    }

    return result;
}

/**
 * Show Prompt Builder modal for an agent
 */
async function showPromptBuilderModal(agentId, agentName) {
    promptBuilderState.currentAgentId = agentId;
    
    // Load guardrails catalog
    try {
        const catalogData = await api.getGuardrailsCatalog();
        promptBuilderState.guardrailsCatalog = catalogData.guardrails || [];
    } catch (error) {
        console.error('Failed to load guardrails catalog:', error);
        promptBuilderState.guardrailsCatalog = [];
    }
    
    // Pre-select mandatory guardrails
    promptBuilderState.selectedGuardrails = promptBuilderState.guardrailsCatalog
        .filter(g => g.mandatory)
        .map(g => g.key);
    
    // Ensure modal exists
    let modal = document.getElementById('prompt-builder-modal');
    if (!modal) {
        createPromptBuilderModal();
        // Get the modal again after creation
        modal = document.getElementById('prompt-builder-modal');
    }
    
    if (!modal) {
        console.error('Failed to create or find prompt-builder-modal');
        showToast('Failed to open Prompt Builder', 'error');
        return;
    }
    
    // Update modal title
    const titleElement = document.getElementById('prompt-builder-agent-name');
    if (titleElement) {
        titleElement.textContent = agentName;
    }

    // Reset form
    const ideaElement = document.getElementById('prompt-builder-idea');
    if (ideaElement) {
        ideaElement.value = '';
    }

    const languageElement = document.getElementById('prompt-builder-language');
    if (languageElement) {
        languageElement.value = 'en';
    }

    clearFieldError('prompt-builder-idea');
    clearFieldError('prompt-builder-output');

    renderGuardrailsCheckboxes();
    hideGeneratedPrompt();
    attachPromptBuilderValidationHandlers();

    // Show modal
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    if (document.body) {
        document.body.classList.add('modal-overlay-open');
    }

    // Store element that opened the modal to restore focus later
    promptBuilderState.previouslyFocusedElement = document.activeElement instanceof HTMLElement
        ? document.activeElement
        : null;

    const focusableSelectors = [
        'a[href]',
        'button:not([disabled])',
        'textarea:not([disabled])',
        'input:not([disabled])',
        'select:not([disabled])',
        '[tabindex]:not([tabindex="-1"])'
    ].join(', ');

    const getFocusableElements = () => {
        const elements = Array.from(modal.querySelectorAll(focusableSelectors));
        return elements.filter(element => {
            const style = window.getComputedStyle(element);
            const isHidden = style.display === 'none' || style.visibility === 'hidden';
            const withinHiddenParent = element.closest('.hidden');
            return !isHidden && !withinHiddenParent;
        });
    };

    if (promptBuilderState.focusTrapHandler) {
        modal.removeEventListener('keydown', promptBuilderState.focusTrapHandler);
    }

    const handleKeydown = (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            closePromptBuilderModal();
            return;
        }

        if (event.key !== 'Tab') {
            return;
        }

        const focusableElements = getFocusableElements();
        if (focusableElements.length === 0) {
            event.preventDefault();
            return;
        }

        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];
        const activeElement = document.activeElement;

        if (!focusableElements.includes(activeElement)) {
            event.preventDefault();
            if (event.shiftKey) {
                lastElement.focus();
            } else {
                firstElement.focus();
            }
            return;
        }

        if (event.shiftKey) {
            if (activeElement === firstElement || !modal.contains(activeElement)) {
                event.preventDefault();
                lastElement.focus();
            }
        } else if (activeElement === lastElement) {
            event.preventDefault();
            firstElement.focus();
        }
    };

    promptBuilderState.focusTrapHandler = handleKeydown;
    modal.addEventListener('keydown', handleKeydown);

    requestAnimationFrame(() => {
        const modalTitle = document.getElementById('prompt-builder-modal-title');
        const focusableElements = getFocusableElements();

        if (modalTitle) {
            modalTitle.focus();
        } else if (focusableElements.length > 0) {
            focusableElements[0].focus();
        }
    });
}

/**
 * Create Prompt Builder modal HTML
 */
function createPromptBuilderModal() {
    const modalHTML = `
        <div
            id="prompt-builder-modal"
            class="modal modal-overlay"
            role="dialog"
            aria-modal="true"
            aria-labelledby="prompt-builder-modal-title"
            aria-hidden="true"
        >
            <div class="modal-content prompt-builder-modal-content">
                <div class="modal-header">
                    <h2 id="prompt-builder-modal-title" tabindex="-1">Prompt Builder - <span id="prompt-builder-agent-name"></span></h2>
                    <button class="close-btn" type="button" onclick="closePromptBuilderModal()" aria-label="Close Prompt Builder">&times;</button>
                </div>
                <div class="modal-body">
                    <!-- Step 1: Generate from Idea -->
                    <div id="prompt-builder-wizard">
                        <h3>Generate Agent Specification</h3>
                        <p class="text-muted">Describe your agent idea briefly. Our AI will generate a comprehensive specification with guardrails.</p>
                        
                        <div class="form-group">
                            <label for="prompt-builder-idea">Agent Idea *</label>
                            <textarea
                                id="prompt-builder-idea"
                                class="form-control"
                                rows="4"
                                placeholder="e.g., A customer support agent that helps users with product questions, handles refunds, and escalates complex issues..."
                                maxlength="2000"
                            ></textarea>
                            <small class="text-muted">Minimum 10 characters, maximum 2000 characters</small>
                            <p id="prompt-builder-idea-error" class="form-error-message hidden" role="alert"></p>
                        </div>

                        <div class="form-group">
                            <label>Guardrails</label>
                            <div id="guardrails-list" class="guardrails-container">
                                <!-- Checkboxes will be rendered here -->
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="prompt-builder-language">Language</label>
                            <select id="prompt-builder-language" class="form-control">
                                <option value="en">English</option>
                                <option value="pt">Portuguese (Português)</option>
                                <option value="es">Spanish (Español)</option>
                                <option value="fr">French (Français)</option>
                                <option value="de">German (Deutsch)</option>
                            </select>
                        </div>
                        
                        <button 
                            id="generate-btn" 
                            class="btn btn-primary" 
                            onclick="generatePromptSpec()"
                        >
                            Generate Specification
                        </button>
                    </div>
                    
                    <!-- Step 2: Review & Edit Generated Prompt -->
                    <div id="prompt-builder-result" class="prompt-builder-section hidden">
                        <h3>Generated Specification</h3>
                        <p class="text-muted">Review and edit the specification below. You can save it as a new version or activate it immediately.</p>
                        
                        <div class="result-metadata">
                            <span class="badge">Version: <span id="result-version"></span></span>
                            <span class="badge">Latency: <span id="result-latency"></span>ms</span>
                            <span class="badge">Guardrails: <span id="result-guardrails"></span></span>
                        </div>
                        
                        <div class="form-group form-group-spaced">
                            <label>Specification (Markdown)</label>
                            <textarea
                                id="prompt-builder-output"
                                class="form-control markdown-editor"
                                rows="20"
                            ></textarea>
                            <p id="prompt-builder-output-error" class="form-error-message hidden" role="alert"></p>
                        </div>

                        <div class="markdown-preview hidden" id="markdown-preview">
                            <label>Preview</label>
                            <div id="markdown-preview-content" class="markdown-content"></div>
                        </div>
                        
                        <div class="btn-group">
                            <button class="btn btn-secondary" onclick="toggleMarkdownPreview()">
                                Toggle Preview
                            </button>
                            <button class="btn btn-success" onclick="activateGeneratedPrompt()">
                                Activate This Version
                            </button>
                            <button class="btn btn-primary" onclick="saveGeneratedPromptManually()">
                                Save as New Version
                            </button>
                            <button class="btn btn-outline" onclick="startNewGeneration()">
                                Generate New
                            </button>
                        </div>
                    </div>
                    
                    <!-- Version History -->
                    <div class="prompt-builder-history">
                        <h3>Version History</h3>
                        <div id="prompt-versions-list">
                            <button class="btn btn-secondary" onclick="loadPromptVersions()">Load Versions</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
}

/**
 * Render guardrails checkboxes
 */
function renderGuardrailsCheckboxes() {
    const container = document.getElementById('guardrails-list');
    if (!container) return;
    
    if (promptBuilderState.guardrailsCatalog.length === 0) {
        container.innerHTML = '<p class="text-muted">Loading guardrails...</p>';
        return;
    }
    
    let html = '';
    promptBuilderState.guardrailsCatalog.forEach(guardrail => {
        const isChecked = promptBuilderState.selectedGuardrails.includes(guardrail.key);
        const isMandatory = guardrail.mandatory;
        
        html += `
            <div class="guardrail-item">
                <label class="checkbox-label">
                    <input 
                        type="checkbox" 
                        value="${guardrail.key}"
                        ${isChecked ? 'checked' : ''}
                        ${isMandatory ? 'disabled' : ''}
                        onchange="toggleGuardrail('${guardrail.key}')"
                    />
                    <strong>${guardrail.title}</strong>
                    ${isMandatory ? '<span class="badge badge-required">Required</span>' : ''}
                </label>
                <p class="guardrail-description">${guardrail.description || ''}</p>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

/**
 * Toggle guardrail selection
 */
function toggleGuardrail(key) {
    const index = promptBuilderState.selectedGuardrails.indexOf(key);
    if (index > -1) {
        promptBuilderState.selectedGuardrails.splice(index, 1);
    } else {
        promptBuilderState.selectedGuardrails.push(key);
    }
}

/**
 * Generate prompt specification
 */
async function generatePromptSpec() {
    const ideaText = document.getElementById('prompt-builder-idea').value.trim();
    const language = document.getElementById('prompt-builder-language').value;

    // Validate
    clearFieldError('prompt-builder-idea');
    if (ideaText.length < 10) {
        setFieldError('prompt-builder-idea', 'Please provide at least 10 characters describing your agent idea.');
        document.getElementById('prompt-builder-idea').focus();
        return;
    }

    // Disable button and show loading
    const btn = document.getElementById('generate-btn');
    btn.disabled = true;
    btn.textContent = 'Generating...';
    
    promptBuilderState.generating = true;
    
    try {
        const result = await requestPromptGeneration(
            promptBuilderState.currentAgentId,
            ideaText,
            promptBuilderState.selectedGuardrails,
            language
        );

        promptBuilderState.generatedPrompt = result;
        showGeneratedPrompt(result);
    } catch (error) {
        showToast('Failed to generate prompt: ' + error.message, 'error');
        console.error(error);
    } finally {
        btn.disabled = false;
        btn.textContent = 'Generate Specification';
        promptBuilderState.generating = false;
    }
}

/**
 * Show generated prompt
 */
function showGeneratedPrompt(result) {
    const wizard = document.getElementById('prompt-builder-wizard');
    const resultSection = document.getElementById('prompt-builder-result');

    if (wizard) {
        wizard.classList.add('hidden');
    }

    if (resultSection) {
        resultSection.classList.remove('hidden');
    }

    // Populate result
    document.getElementById('result-version').textContent = result.version;
    document.getElementById('result-latency').textContent = result.latency_ms || 'N/A';
    document.getElementById('result-guardrails').textContent = result.applied_guardrails.join(', ');
    document.getElementById('prompt-builder-output').value = result.prompt_md;
    clearFieldError('prompt-builder-output');
    attachPromptBuilderValidationHandlers();
}

/**
 * Hide generated prompt view
 */
function hideGeneratedPrompt() {
    const wizard = document.getElementById('prompt-builder-wizard');
    const resultSection = document.getElementById('prompt-builder-result');
    const preview = document.getElementById('markdown-preview');

    if (wizard) {
        wizard.classList.remove('hidden');
    }

    if (resultSection) {
        resultSection.classList.add('hidden');
    }

    if (preview) {
        preview.classList.add('hidden');
    }

    clearFieldError('prompt-builder-output');
}

/**
 * Start new generation
 */
function startNewGeneration() {
    hideGeneratedPrompt();
    promptBuilderState.generatedPrompt = null;
    clearFieldError('prompt-builder-output');
}

/**
 * Toggle markdown preview
 */
function toggleMarkdownPreview() {
    const preview = document.getElementById('markdown-preview');
    const content = document.getElementById('markdown-preview-content');
    const markdown = document.getElementById('prompt-builder-output').value;

    if (preview.classList.contains('hidden')) {
        // Simple markdown to HTML (basic implementation)
        const html = markdownToHtml(markdown);
        content.innerHTML = html;
        preview.classList.remove('hidden');
    } else {
        preview.classList.add('hidden');
    }
}

/**
 * Simple markdown to HTML converter
 */
function markdownToHtml(markdown) {
    let html = markdown;
    
    // Headers
    html = html.replace(/^### (.*$)/gim, '<h3>$1</h3>');
    html = html.replace(/^## (.*$)/gim, '<h2>$1</h2>');
    html = html.replace(/^# (.*$)/gim, '<h1>$1</h1>');
    
    // Bold
    html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    
    // Italic
    html = html.replace(/\*(.*?)\*/g, '<em>$1</em>');
    
    // Lists
    html = html.replace(/^\- (.*$)/gim, '<li>$1</li>');
    html = html.replace(/(<li>.*<\/li>)/s, '<ul>$1</ul>');
    
    // Line breaks
    html = html.replace(/\n/g, '<br>');
    
    return html;
}

/**
 * Activate generated prompt
 */
async function activateGeneratedPrompt() {
    if (!promptBuilderState.generatedPrompt) return;

    const confirmed = await showConfirmationDialog({
        title: 'Activate generated prompt',
        message: 'Activate this version? It will be used for all future conversations with this agent.',
        confirmLabel: 'Activate',
        tone: 'primary'
    });

    if (!confirmed) {
        return;
    }

    try {
        await api.activatePromptVersion(
            promptBuilderState.currentAgentId,
            promptBuilderState.generatedPrompt.version
        );

        showToast('Prompt version activated successfully!', 'success');
        closePromptBuilderModal();
        loadCurrentPage(); // Refresh the agents page
    } catch (error) {
        showToast('Failed to activate prompt: ' + error.message, 'error');
        console.error(error);
    }
}

/**
 * Save generated prompt manually (after editing)
 */
async function saveGeneratedPromptManually() {
    const editedPrompt = document.getElementById('prompt-builder-output').value;

    clearFieldError('prompt-builder-output');
    if (!editedPrompt || editedPrompt.trim().length < 10) {
        setFieldError('prompt-builder-output', 'Prompt is too short');
        document.getElementById('prompt-builder-output').focus();
        return;
    }

    try {
        const result = await api.saveManualPrompt(
            promptBuilderState.currentAgentId,
            editedPrompt,
            promptBuilderState.selectedGuardrails
        );

        showToast(`Saved as version ${result.version}`, 'success');
        promptBuilderState.generatedPrompt = result;
        await loadPromptVersions();
        clearFieldError('prompt-builder-output');
    } catch (error) {
        showToast('Failed to save prompt: ' + error.message, 'error');
        console.error(error);
    }
}

/**
 * Load prompt versions for current agent
 */
async function loadPromptVersions() {
    const container = document.getElementById('prompt-versions-list');
    container.innerHTML = '<div class="spinner"></div>';
    
    try {
        const data = await api.listPromptVersions(promptBuilderState.currentAgentId);
        const versions = data.versions || [];
        const activeVersion = data.active_version;
        
        if (versions.length === 0) {
            container.innerHTML = '<p class="text-muted">No versions yet. Generate your first specification above.</p>';
            return;
        }
        
        let html = '<table class="table"><thead><tr><th>Version</th><th>Created</th><th>Guardrails</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
        
        versions.forEach(v => {
            const isActive = v.version === activeVersion;
            const guardrails = v.guardrails.map(g => g.key || g).join(', ');
            
            html += `
                <tr ${isActive ? 'class="active-version"' : ''}>
                    <td><strong>v${v.version}</strong></td>
                    <td>${new Date(v.created_at).toLocaleString()}</td>
                    <td><small>${guardrails}</small></td>
                    <td>
                        ${isActive ? '<span class="badge badge-success">Active</span>' : ''}
                    </td>
                    <td>
                        <button class="btn btn-sm" onclick="viewPromptVersion(${v.version})">View</button>
                        ${!isActive ? `<button class="btn btn-sm btn-success" onclick="activatePromptVersionById(${v.version})">Activate</button>` : ''}
                        ${!isActive ? `<button class="btn btn-sm btn-danger" onclick="deletePromptVersionById(${v.version})">Delete</button>` : ''}
                    </td>
                </tr>
            `;
        });
        
        html += '</tbody></table>';
        
        if (activeVersion) {
            html += `<button class="btn btn-secondary" onclick="deactivateCurrentPrompt()">Deactivate Current Prompt</button>`;
        }
        
        container.innerHTML = html;
    } catch (error) {
        container.innerHTML = '<p class="text-danger">Failed to load versions</p>';
        console.error(error);
    }
}

/**
 * View a specific prompt version
 */
async function viewPromptVersion(version) {
    try {
        const data = await api.getPromptVersion(promptBuilderState.currentAgentId, version);

        const wizard = document.getElementById('prompt-builder-wizard');
        const resultSection = document.getElementById('prompt-builder-result');

        if (wizard) {
            wizard.classList.add('hidden');
        }

        if (resultSection) {
            resultSection.classList.remove('hidden');
        }

        document.getElementById('result-version').textContent = data.version;
        document.getElementById('result-latency').textContent = 'N/A';
        document.getElementById('result-guardrails').textContent = data.guardrails.map(g => g.key || g).join(', ');
        document.getElementById('prompt-builder-output').value = data.prompt_md;

        promptBuilderState.generatedPrompt = data;
        clearFieldError('prompt-builder-output');
        attachPromptBuilderValidationHandlers();
        return data;
    } catch (error) {
        showToast('Failed to load version: ' + error.message, 'error');
        console.error(error);
        throw error;
    }
}

/**
 * Activate a version by ID
 */
async function activatePromptVersionById(version) {
    const confirmed = await showConfirmationDialog({
        title: 'Activate prompt version',
        message: `Activate version ${version}?`,
        confirmLabel: 'Activate',
        tone: 'primary'
    });

    if (!confirmed) return;

    try {
        await api.activatePromptVersion(promptBuilderState.currentAgentId, version);
        showToast('Version activated!', 'success');
        await loadPromptVersions();
        await viewPromptVersion(version).catch(() => {});
    } catch (error) {
        showToast('Failed to activate: ' + error.message, 'error');
        console.error(error);
    }
}

/**
 * Delete a version by ID
 */
async function deletePromptVersionById(version) {
    const confirmed = await showConfirmationDialog({
        title: 'Delete prompt version',
        message: `Delete version ${version}? This cannot be undone.`,
        confirmLabel: 'Delete',
        tone: 'danger'
    });

    if (!confirmed) return;

    try {
        await api.deletePromptVersion(promptBuilderState.currentAgentId, version);
        showToast('Version deleted', 'success');
        await loadPromptVersions();

        if (promptBuilderState.generatedPrompt && promptBuilderState.generatedPrompt.version === version) {
            startNewGeneration();
        }
    } catch (error) {
        showToast('Failed to delete: ' + error.message, 'error');
        console.error(error);
    }
}

/**
 * Deactivate current prompt
 */
async function deactivateCurrentPrompt() {
    const confirmed = await showConfirmationDialog({
        title: 'Deactivate prompt',
        message: 'Deactivate the current prompt? The agent will use its system_message instead.',
        confirmLabel: 'Deactivate',
        tone: 'danger'
    });

    if (!confirmed) return;

    try {
        await api.deactivatePrompt(promptBuilderState.currentAgentId);
        showToast('Prompt deactivated', 'success');
        await loadPromptVersions();
        closePromptBuilderModal();
        loadCurrentPage();
    } catch (error) {
        showToast('Failed to deactivate: ' + error.message, 'error');
        console.error(error);
    }
}

/**
 * Close Prompt Builder modal
 */
function closePromptBuilderModal() {
    const modal = document.getElementById('prompt-builder-modal');
    if (modal) {
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');

        if (promptBuilderState.focusTrapHandler) {
            modal.removeEventListener('keydown', promptBuilderState.focusTrapHandler);
            promptBuilderState.focusTrapHandler = null;
        }
    }

    if (document.body) {
        document.body.classList.remove('modal-overlay-open');
    }

    if (promptBuilderState.previouslyFocusedElement && typeof promptBuilderState.previouslyFocusedElement.focus === 'function') {
        promptBuilderState.previouslyFocusedElement.focus();
    }

    promptBuilderState.previouslyFocusedElement = null;

    // Reset state
    promptBuilderState.currentAgentId = null;
    promptBuilderState.generatedPrompt = null;
}

// Close modal on outside click
window.addEventListener('click', (e) => {
    const modal = document.getElementById('prompt-builder-modal');
    if (modal && modal.classList.contains('open') && e.target === modal) {
        closePromptBuilderModal();
    }
});

// Expose integration hooks
window.promptBuilder = window.promptBuilder || {};
window.promptBuilder.registerHooks = registerPromptBuilderHooks;
window.promptBuilder.generate = function(options = {}) {
    const { agentId, ideaText, guardrails = [], language = 'en' } = options;
    return requestPromptGeneration(agentId, ideaText, guardrails, language);
};
