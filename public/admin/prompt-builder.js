/**
 * Prompt Builder UI Module
 * Admin interface for generating AI agent specifications
 */

// Add Prompt Builder API methods to AdminAPI class
(function() {
    const originalAdminAPI = window.AdminAPI;
    
    window.AdminAPI = class extends originalAdminAPI {
        // Prompt Builder methods
        async generatePrompt(agentId, ideaText, guardrails = [], language = 'en', variables = {}, requestOptions = {}) {
            return this.request('prompt_builder_generate', {
                method: 'POST',
                params: `&agent_id=${agentId}`,
                body: { idea_text: ideaText, guardrails, language, variables },
                signal: requestOptions.signal
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
    currentGenerationController: null,
    versionsLoading: false,
    generatedPrompt: null,
    previouslyFocusedElement: null,
    focusTrapHandler: null
};

function setGeneratingState(isGenerating) {
    promptBuilderState.generating = Boolean(isGenerating);

    const modal = document.getElementById('prompt-builder-modal');
    const progress = document.getElementById('prompt-builder-progress');
    const cancelButton = document.getElementById('prompt-builder-cancel');
    const modalContent = modal ? modal.querySelector('.prompt-builder-modal-content') : null;

    if (progress) {
        progress.classList.toggle('hidden', !promptBuilderState.generating);
    }

    if (cancelButton) {
        cancelButton.classList.toggle('hidden', !promptBuilderState.generating);
    }

    if (modal) {
        modal.setAttribute('aria-busy', promptBuilderState.generating ? 'true' : 'false');
    }

    if (modalContent) {
        modalContent.classList.toggle('is-generating', promptBuilderState.generating);
    }

    const disableTargets = modal ? modal.querySelectorAll('[data-disable-while-generating]') : [];
    disableTargets.forEach(element => {
        if (promptBuilderState.generating) {
            element.setAttribute('disabled', 'disabled');
            element.setAttribute('aria-disabled', 'true');
        } else {
            element.removeAttribute('disabled');
            element.removeAttribute('aria-disabled');
        }
    });

    const generateButton = document.getElementById('generate-btn');
    if (generateButton) {
        const originalLabel = generateButton.dataset.originalLabel || generateButton.textContent.trim();
        if (!generateButton.dataset.originalLabel) {
            generateButton.dataset.originalLabel = originalLabel;
        }

        if (promptBuilderState.generating) {
            generateButton.textContent = 'Generating...';
        } else {
            generateButton.textContent = generateButton.dataset.originalLabel || 'Generate Specification';
        }
    }
}

function cancelPromptGeneration(options = {}) {
    if (!promptBuilderState.generating) {
        return;
    }

    if (promptBuilderState.currentGenerationController) {
        promptBuilderState.currentGenerationController.abort();
        promptBuilderState.currentGenerationController = null;
    }

    if (!options.silent) {
        showToast('Prompt generation cancelled.', 'info');
    }

    setGeneratingState(false);
}

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
        outputElement.addEventListener('input', () => {
            clearFieldError('prompt-builder-output');
            updateMarkdownPreview();
        });
        outputElement.dataset.validationBound = 'true';
    }
}

async function requestPromptGeneration(agentId, ideaText, guardrails = [], language = 'en', signal) {
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
        language,
        {},
        { signal }
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
    promptBuilderState.generatedPrompt = null;
    promptBuilderState.currentGenerationController = null;
    setGeneratingState(false);
    updateMarkdownPreview();
    void loadPromptVersions();

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
                    <div
                        id="prompt-builder-progress"
                        class="prompt-builder-progress hidden"
                        role="status"
                        aria-live="polite"
                    >
                        <div class="progress-indicator">
                            <span class="spinner spinner-inline" aria-hidden="true"></span>
                            <span class="progress-text">Generating specification...</span>
                        </div>
                        <button
                            type="button"
                            class="btn btn-link hidden"
                            id="prompt-builder-cancel"
                            onclick="cancelPromptGeneration()"
                        >
                            Cancel
                        </button>
                    </div>

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
                                data-disable-while-generating="true"
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
                            <select id="prompt-builder-language" class="form-control" data-disable-while-generating="true">
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
                            data-disable-while-generating="true"
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
                            <div class="markdown-editor-wrapper" id="markdown-editor-wrapper">
                                <textarea
                                    id="prompt-builder-output"
                                    class="form-control markdown-editor"
                                    rows="20"
                                    data-disable-while-generating="true"
                                ></textarea>
                                <div class="markdown-preview hidden" id="markdown-preview" role="region" aria-live="polite">
                                    <div class="markdown-preview-header">Preview</div>
                                    <div id="markdown-preview-content" class="markdown-content"></div>
                                </div>
                            </div>
                            <p id="prompt-builder-output-error" class="form-error-message hidden" role="alert"></p>
                        </div>

                        <div class="btn-group">
                            <button class="btn btn-secondary" onclick="toggleMarkdownPreview()" data-disable-while-generating="true">
                                Toggle Preview
                            </button>
                            <button class="btn btn-success" onclick="activateGeneratedPrompt()" data-disable-while-generating="true">
                                Activate This Version
                            </button>
                            <button class="btn btn-primary" onclick="saveGeneratedPromptManually()" data-disable-while-generating="true">
                                Save as New Version
                            </button>
                            <button class="btn btn-outline" onclick="startNewGeneration()" data-disable-while-generating="true">
                                Generate New
                            </button>
                        </div>
                    </div>

                    <!-- Version History -->
                    <div class="prompt-builder-history">
                        <h3>Version History</h3>
                        <p class="text-muted">Keep track of generated and manual prompt revisions.</p>
                        <div id="prompt-versions-loading" class="prompt-versions-loading hidden" aria-live="polite"></div>
                        <div id="prompt-versions-list" class="prompt-versions-list"></div>
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
                        ${isMandatory ? 'disabled' : 'data-disable-while-generating="true"'}
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
    setGeneratingState(promptBuilderState.generating);
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
    if (promptBuilderState.generating) {
        return;
    }

    const ideaElement = document.getElementById('prompt-builder-idea');
    const languageElement = document.getElementById('prompt-builder-language');
    const ideaText = ideaElement ? ideaElement.value.trim() : '';
    const language = languageElement ? languageElement.value : 'en';

    // Validate
    clearFieldError('prompt-builder-idea');
    if (ideaText.length < 10) {
        setFieldError('prompt-builder-idea', 'Please provide at least 10 characters describing your agent idea.');
        if (ideaElement) {
            ideaElement.focus();
        }
        return;
    }

    const controller = new AbortController();
    promptBuilderState.currentGenerationController = controller;
    setGeneratingState(true);

    try {
        const result = await requestPromptGeneration(
            promptBuilderState.currentAgentId,
            ideaText,
            promptBuilderState.selectedGuardrails,
            language,
            controller.signal
        );

        promptBuilderState.generatedPrompt = result;
        showGeneratedPrompt(result);
        toggleMarkdownPreview(true);
    } catch (error) {
        if (error && error.name === 'AbortError') {
            console.debug('Prompt generation aborted');
        } else {
            showToast('Failed to generate prompt: ' + error.message, 'error');
            console.error(error);
        }
    } finally {
        if (promptBuilderState.currentGenerationController === controller) {
            promptBuilderState.currentGenerationController = null;
        }
        setGeneratingState(false);
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
    updateMarkdownPreview();
    attachPromptBuilderValidationHandlers();
}

/**
 * Hide generated prompt view
 */
function hideGeneratedPrompt() {
    const wizard = document.getElementById('prompt-builder-wizard');
    const resultSection = document.getElementById('prompt-builder-result');
    const preview = document.getElementById('markdown-preview');
    const wrapper = document.getElementById('markdown-editor-wrapper');

    if (wizard) {
        wizard.classList.remove('hidden');
    }

    if (resultSection) {
        resultSection.classList.add('hidden');
    }

    if (preview) {
        preview.classList.add('hidden');
    }

    if (wrapper) {
        wrapper.classList.remove('preview-visible');
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
function toggleMarkdownPreview(force) {
    const preview = document.getElementById('markdown-preview');
    const wrapper = document.getElementById('markdown-editor-wrapper');

    if (!preview || !wrapper) {
        return;
    }

    const shouldShow = typeof force === 'boolean'
        ? force
        : preview.classList.contains('hidden');

    preview.classList.toggle('hidden', !shouldShow);
    wrapper.classList.toggle('preview-visible', shouldShow);

    if (shouldShow) {
        updateMarkdownPreview();
    }
}

function sanitizeUrl(url) {
    if (!url || typeof url !== 'string') {
        return '';
    }

    const trimmed = url.trim();
    if (!trimmed) {
        return '';
    }

    try {
        const decoded = decodeURIComponent(trimmed);
        if (/^(https?:|mailto:|tel:)/i.test(decoded)) {
            return trimmed.replace(/"/g, '%22');
        }
    } catch (error) {
        return '';
    }

    return '';
}

function markdownToHtml(markdown) {
    if (typeof markdown !== 'string') {
        return '';
    }

    const escaped = escapeHtml(markdown);
    const lines = escaped.split(/\r?\n/);
    const htmlParts = [];
    let inUl = false;
    let inOl = false;
    let inBlockquote = false;
    let inCodeBlock = false;
    let codeBlockLang = '';
    let codeBuffer = [];

    const closeLists = () => {
        if (inUl) {
            htmlParts.push('</ul>');
            inUl = false;
        }
        if (inOl) {
            htmlParts.push('</ol>');
            inOl = false;
        }
    };

    const closeBlockquote = () => {
        if (inBlockquote) {
            htmlParts.push('</blockquote>');
            inBlockquote = false;
        }
    };

    const renderInline = (text) => {
        let result = text;
        result = result.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        result = result.replace(/__(.+?)__/g, '<strong>$1</strong>');
        result = result.replace(/\*(.+?)\*/g, '<em>$1</em>');
        result = result.replace(/_(.+?)_/g, '<em>$1</em>');
        result = result.replace(/`([^`]+)`/g, '<code>$1</code>');
        result = result.replace(/!\[([^\]]*)\]\(([^\)]+)\)/g, (match, alt, url) => {
            const safeUrl = sanitizeUrl(url);
            if (!safeUrl) {
                return alt;
            }
            return `<img src="${safeUrl}" alt="${alt}">`;
        });
        result = result.replace(/\[([^\]]+)\]\(([^\)]+)\)/g, (match, label, url) => {
            const safeUrl = sanitizeUrl(url);
            if (!safeUrl) {
                return label;
            }
            return `<a href="${safeUrl}" target="_blank" rel="noopener noreferrer">${label}</a>`;
        });
        return result;
    };

    const flushCodeBuffer = () => {
        if (!inCodeBlock) {
            return;
        }

        const languageAttr = codeBlockLang ? ` data-language="${codeBlockLang}"` : '';
        htmlParts.push(`<pre><code${languageAttr}>${codeBuffer.join('\n')}</code></pre>`);
        codeBuffer = [];
        codeBlockLang = '';
        inCodeBlock = false;
    };

    lines.forEach(rawLine => {
        const line = rawLine;
        const trimmed = line.trim();

        if (trimmed.startsWith('```')) {
            if (inCodeBlock) {
                flushCodeBuffer();
                return;
            }

            closeLists();
            closeBlockquote();
            inCodeBlock = true;
            codeBlockLang = trimmed.slice(3).trim();
            return;
        }

        if (inCodeBlock) {
            codeBuffer.push(line);
            return;
        }

        if (!trimmed) {
            closeLists();
            closeBlockquote();
            htmlParts.push('<br>');
            return;
        }

        const headingMatch = trimmed.match(/^(#{1,6})\s+(.*)$/);
        if (headingMatch) {
            const level = headingMatch[1].length;
            closeLists();
            closeBlockquote();
            htmlParts.push(`<h${level}>${renderInline(headingMatch[2])}</h${level}>`);
            return;
        }

        const blockquoteMatch = trimmed.match(/^>\s?(.*)$/);
        if (blockquoteMatch) {
            closeLists();
            if (!inBlockquote) {
                htmlParts.push('<blockquote>');
                inBlockquote = true;
            }
            htmlParts.push(`<p>${renderInline(blockquoteMatch[1])}</p>`);
            return;
        }

        closeBlockquote();

        const orderedMatch = trimmed.match(/^(\d+)\.\s+(.*)$/);
        if (orderedMatch) {
            if (!inOl) {
                closeLists();
                htmlParts.push('<ol>');
                inOl = true;
            }
            htmlParts.push(`<li>${renderInline(orderedMatch[2])}</li>`);
            return;
        }

        const unorderedMatch = trimmed.match(/^[-*+]\s+(.*)$/);
        if (unorderedMatch) {
            if (!inUl) {
                closeLists();
                htmlParts.push('<ul>');
                inUl = true;
            }
            htmlParts.push(`<li>${renderInline(unorderedMatch[1])}</li>`);
            return;
        }

        closeLists();
        htmlParts.push(`<p>${renderInline(trimmed)}</p>`);
    });

    flushCodeBuffer();
    closeLists();
    closeBlockquote();

    return htmlParts.join('\n');
}

function updateMarkdownPreview() {
    const previewContent = document.getElementById('markdown-preview-content');
    const preview = document.getElementById('markdown-preview');
    const wrapper = document.getElementById('markdown-editor-wrapper');
    const textarea = document.getElementById('prompt-builder-output');

    if (!previewContent || !textarea) {
        return;
    }

    const html = markdownToHtml(textarea.value || '');
    if (html.trim()) {
        previewContent.innerHTML = html;
    } else {
        previewContent.innerHTML = '<p class="text-muted preview-empty">Start typing to see the preview.</p>';
    }

    if (preview && wrapper) {
        wrapper.classList.toggle('preview-visible', !preview.classList.contains('hidden'));
    }
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

function renderPromptVersionsLoadingState() {
    return `
        <div class="versions-loading-status">
            <span class="spinner spinner-inline" aria-hidden="true"></span>
            <span>Loading versions...</span>
        </div>
        <div class="versions-skeleton">
            <div class="skeleton-bar skeleton-bar--header"></div>
            <div class="skeleton-bar"></div>
            <div class="skeleton-bar"></div>
            <div class="skeleton-bar"></div>
        </div>
    `;
}

/**
 * Load prompt versions for current agent
 */
async function loadPromptVersions() {
    const listContainer = document.getElementById('prompt-versions-list');
    const loadingContainer = document.getElementById('prompt-versions-loading');

    if (!listContainer) {
        return null;
    }

    promptBuilderState.versionsLoading = true;

    if (loadingContainer) {
        loadingContainer.innerHTML = renderPromptVersionsLoadingState();
        loadingContainer.classList.remove('hidden');
    }

    listContainer.classList.add('hidden');
    listContainer.innerHTML = '';

    try {
        const data = await api.listPromptVersions(promptBuilderState.currentAgentId);
        const versions = data.versions || [];
        const activeVersion = data.active_version;
        const activeVersionNumber = Number.parseInt(activeVersion, 10);
        const normalizedActiveVersion = activeVersion === undefined || activeVersion === null
            ? null
            : (Number.isFinite(activeVersionNumber) ? String(activeVersionNumber) : String(activeVersion));

        if (versions.length === 0) {
            listContainer.innerHTML = '<p class="text-muted">No versions yet. Generate your first specification above.</p>';
            listContainer.classList.remove('hidden');
            setGeneratingState(promptBuilderState.generating);
            return data;
        }

        let html = '<table class="table prompt-versions-table"><thead><tr><th>Version</th><th>Created</th><th>Guardrails</th><th>Status</th><th class="actions-column">Actions</th></tr></thead><tbody>';

        versions.forEach(v => {
            const versionNumber = Number.parseInt(v.version, 10);
            const normalizedVersion = Number.isFinite(versionNumber) ? String(versionNumber) : String(v.version);
            const callArgument = JSON.stringify(Number.isFinite(versionNumber) ? versionNumber : v.version);
            const isActive = normalizedActiveVersion !== null && normalizedVersion === normalizedActiveVersion;
            const guardrails = (v.guardrails || []).map(g => g.key || g).filter(Boolean).join(', ');
            const createdAt = v.created_at ? new Date(v.created_at).toLocaleString() : '—';
            const statusCell = isActive
                ? '<span class="status-pill status-pill--active">Active</span>'
                : '<span class="status-pill status-pill--inactive">Inactive</span>';

            html += `
                <tr class="prompt-version-row ${isActive ? 'active-version' : ''}" ${isActive ? 'aria-current="true"' : ''}>
                    <td>
                        <div class="version-label">
                            <strong>v${escapeHtml(normalizedVersion)}</strong>
                        </div>
                    </td>
                    <td>${escapeHtml(createdAt)}</td>
                    <td><small>${guardrails ? escapeHtml(guardrails) : '—'}</small></td>
                    <td>${statusCell}</td>
                    <td class="actions-cell">
                        <button class="btn btn-sm" onclick="viewPromptVersion(${callArgument})" data-disable-while-generating="true">View</button>
                        ${!isActive ? `<button class="btn btn-sm btn-success" onclick="activatePromptVersionById(${callArgument})" data-disable-while-generating="true">Activate</button>` : ''}
                        ${!isActive ? `<button class="btn btn-sm btn-danger" onclick="deletePromptVersionById(${callArgument})" data-disable-while-generating="true">Delete</button>` : ''}
                    </td>
                </tr>
            `;
        });

        html += '</tbody></table>';

        if (activeVersion) {
            html += '<button class="btn btn-secondary" onclick="deactivateCurrentPrompt()" data-disable-while-generating="true">Deactivate Current Prompt</button>';
        }

        listContainer.innerHTML = html;
        listContainer.classList.remove('hidden');
        setGeneratingState(promptBuilderState.generating);
        return data;
    } catch (error) {
        listContainer.innerHTML = `<p class="text-error">Failed to load versions: ${escapeHtml(error.message || 'Unknown error')}</p>`;
        listContainer.classList.remove('hidden');
        console.error(error);
        setGeneratingState(promptBuilderState.generating);
        return null;
    } finally {
        promptBuilderState.versionsLoading = false;
        if (loadingContainer) {
            loadingContainer.classList.add('hidden');
            loadingContainer.innerHTML = '';
        }
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
        updateMarkdownPreview();
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
    cancelPromptGeneration({ silent: true });

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
