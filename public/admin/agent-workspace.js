(function () {
    const DRAFT_STORAGE_KEY = 'agentWorkspaceDraft';

    const wizardState = {
        mode: 'create',
        agentId: null,
        stepIndex: 0,
        isLoading: false,
        resources: {
            prompts: [],
            vectorStores: [],
            models: []
        },
        data: getDefaultFormState(),
        promptBuilder: {
            lastGenerated: null,
            isGenerating: false,
            hooksRegistered: false
        }
    };

    const wizardSteps = [
        {
            id: 'identity',
            title: 'Identidade do agente',
            description: 'Nome, descrição e visibilidade.',
            render: renderIdentityStep
        },
        {
            id: 'runtime',
            title: 'Configuração técnica',
            description: 'API, modelo e parâmetros de execução.',
            render: renderRuntimeStep
        },
        {
            id: 'knowledge',
            title: 'Conhecimento & Ferramentas',
            description: 'Fontes de dados e ferramentas disponíveis.',
            render: renderKnowledgeStep
        },
        {
            id: 'behavior',
            title: 'Comportamento & Prompt',
            description: 'Mensagens do sistema, prompt e resumo final.',
            render: renderBehaviorStep
        }
    ];

    function getDefaultFormState() {
        return {
            name: '',
            description: '',
            is_default: false,
            api_type: 'responses',
            model: '',
            temperature: '0.7',
            top_p: '1',
            max_output_tokens: '',
            vector_store_ids: [],
            enable_file_search: false,
            prompt_id: '',
            prompt_version: '',
            system_message: ''
        };
    }

    function escapeHtml(value) {
        if (value === undefined || value === null) {
            return '';
        }
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function resetWizardState() {
        wizardState.stepIndex = 0;
        wizardState.data = getDefaultFormState();
        wizardState.promptBuilder.lastGenerated = null;
        wizardState.promptBuilder.isGenerating = false;
    }

    function openAgentWorkspace(options = {}) {
        const { mode = 'create', agentId = null, agentData = null } = options;

        wizardState.mode = mode;
        wizardState.agentId = agentId || null;
        wizardState.isLoading = true;
        resetWizardState();

        if (agentData) {
            wizardState.data = {
                ...getDefaultFormState(),
                ...agentData,
                temperature: agentData.temperature !== null && agentData.temperature !== undefined ? String(agentData.temperature) : '',
                top_p: agentData.top_p !== null && agentData.top_p !== undefined ? String(agentData.top_p) : '',
                max_output_tokens: agentData.max_output_tokens !== null && agentData.max_output_tokens !== undefined ? String(agentData.max_output_tokens) : '',
                vector_store_ids: Array.isArray(agentData.vector_store_ids) ? agentData.vector_store_ids : []
            };
            wizardState.data.enable_file_search = Array.isArray(agentData.tools) && agentData.tools.some(tool => tool.type === 'file_search');
        }

        const content = document.getElementById('content');
        content.innerHTML = '<div class="card"><div class="card-body"><div class="spinner"></div></div></div>';

        Promise.all([
            api.listPrompts(),
            api.listVectorStores(),
            api.listModels()
        ]).then(([prompts, vectorStores, models]) => {
            wizardState.resources.prompts = prompts || [];
            wizardState.resources.vectorStores = vectorStores || [];
            wizardState.resources.models = models && Array.isArray(models.data) ? models.data : [];

            if (wizardState.mode === 'create' && !agentData) {
                hydrateDraftFromStorage();
            }

            wizardState.isLoading = false;
            renderWorkspace();
            ensurePromptBuilderIntegration();
        }).catch(error => {
            console.error('Failed to load workspace resources', error);
            content.innerHTML = `<div class="card"><div class="card-body">Erro ao carregar recursos: ${escapeHtml(error.message || error)}</div></div>`;
            showToast('Falha ao carregar recursos do assistente', 'error');
            wizardState.isLoading = false;
        });
    }

    function renderWorkspace() {
        const content = document.getElementById('content');
        if (!content) {
            return;
        }

        if (wizardState.isLoading) {
            content.innerHTML = '<div class="card"><div class="card-body"><div class="spinner"></div></div></div>';
            return;
        }

        const progressHtml = wizardSteps.map((step, index) => {
            const classes = [
                'wizard-progress-item',
                index === wizardState.stepIndex ? 'active' : '',
                index < wizardState.stepIndex ? 'complete' : ''
            ].filter(Boolean).join(' ');

            return `
                <li class="${classes}">
                    <div class="wizard-progress-index">${index + 1}</div>
                    <div>
                        <div class="wizard-progress-title">${step.title}</div>
                        <div class="wizard-progress-description">${step.description}</div>
                    </div>
                </li>
            `;
        }).join('');

        const currentStep = wizardSteps[wizardState.stepIndex];

        content.innerHTML = `
            <div class="agent-workspace">
                <aside class="wizard-sidebar">
                    <h2 class="wizard-title">${wizardState.mode === 'create' ? 'Novo agente' : 'Editar agente'}</h2>
                    <ol class="wizard-progress">${progressHtml}</ol>
                    <div class="wizard-help">
                        <h3>Como funciona?</h3>
                        <p>Avance pelas etapas para configurar identidade, parâmetros técnicos, fontes de conhecimento e comportamento do agente.</p>
                        <p>Você pode salvar um rascunho a qualquer momento para continuar depois.</p>
                    </div>
                </aside>
                <section class="wizard-content">
                    <header class="wizard-step-header">
                        <div>
                            <div class="wizard-step-subtitle">Etapa ${wizardState.stepIndex + 1} de ${wizardSteps.length}</div>
                            <h3 class="wizard-step-title">${currentStep.title}</h3>
                            <p class="wizard-step-description">${currentStep.description}</p>
                        </div>
                    </header>
                    <div class="wizard-step-body">
                        ${currentStep.render()}
                    </div>
                    <footer class="wizard-footer">
                        <button class="btn btn-secondary" data-action="previous" ${wizardState.stepIndex === 0 ? 'disabled' : ''}>Anterior</button>
                        <div class="wizard-footer-actions">
                            <button class="btn btn-outline" data-action="save-draft">Salvar rascunho</button>
                            ${wizardState.stepIndex < wizardSteps.length - 1 ? `
                                <button class="btn btn-primary" data-action="next">Próximo</button>
                            ` : `
                                <button class="btn btn-primary" data-action="publish">Publicar</button>
                            `}
                        </div>
                    </footer>
                </section>
            </div>
        `;

        bindStepEvents();
        bindFooterActions();
        updateReviewSummary();
        updatePromptBuilderPreview();
    }

    function renderIdentityStep() {
        const data = wizardState.data;
        return `
            <div class="wizard-step-grid">
                <div class="wizard-card">
                    <h4>Informações básicas</h4>
                    <div class="form-group">
                        <label class="form-label" for="wizard-name">Nome *</label>
                        <input id="wizard-name" data-field="name" type="text" class="form-input" placeholder="Ex.: Atendimento Premium" value="${escapeHtml(data.name)}" required />
                        <small class="form-help">Este nome será exibido nas listagens e integrações.</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="wizard-description">Descrição</label>
                        <textarea id="wizard-description" data-field="description" class="form-textarea" rows="4" placeholder="Resumo da missão do agente">${escapeHtml(data.description)}</textarea>
                    </div>
                </div>
                <div class="wizard-card">
                    <h4>Visibilidade</h4>
                    <label class="form-checkbox">
                        <input type="checkbox" data-field="is_default" data-field-type="boolean" ${data.is_default ? 'checked' : ''} />
                        Definir como agente padrão
                    </label>
                    <p class="text-muted">O agente padrão será utilizado como fallback em canais que não especificarem uma configuração própria.</p>
                </div>
            </div>
        `;
    }

    function renderRuntimeStep() {
        const data = wizardState.data;
        return `
            <div class="wizard-step-grid">
                <div class="wizard-card">
                    <h4>API &amp; Modelo</h4>
                    <div class="form-group">
                        <label class="form-label" for="wizard-api-type">Tipo de API *</label>
                        <select id="wizard-api-type" class="form-select" data-field="api_type">
                            <option value="responses" ${data.api_type === 'responses' ? 'selected' : ''}>Responses API</option>
                            <option value="chat" ${data.api_type === 'chat' ? 'selected' : ''}>Chat Completions API</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="wizard-model">Modelo</label>
                        ${renderModelSelect('wizard-model', data.model)}
                        <small class="form-help">Selecione um modelo específico ou deixe em branco para usar o padrão.</small>
                    </div>
                </div>
                <div class="wizard-card">
                    <h4>Parâmetros de geração</h4>
                    <div class="form-group">
                        <label class="form-label" for="wizard-temperature">Temperature</label>
                        <input id="wizard-temperature" type="number" step="0.1" min="0" max="2" class="form-input" data-field="temperature" data-field-type="number" value="${escapeHtml(data.temperature)}" placeholder="0.7" />
                        <small class="form-help">Controle a criatividade das respostas. Valores altos geram resultados mais variados.</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="wizard-top-p">Top P</label>
                        <input id="wizard-top-p" type="number" step="0.05" min="0" max="1" class="form-input" data-field="top_p" data-field-type="number" value="${escapeHtml(data.top_p)}" placeholder="1" />
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="wizard-max-output">Máx. tokens de saída</label>
                        <input id="wizard-max-output" type="number" min="0" class="form-input" data-field="max_output_tokens" data-field-type="number" value="${escapeHtml(data.max_output_tokens)}" placeholder="ex.: 1024" />
                    </div>
                </div>
            </div>
        `;
    }

    function renderKnowledgeStep() {
        const data = wizardState.data;
        const hasVectorStores = wizardState.resources.vectorStores && wizardState.resources.vectorStores.length > 0;
        return `
            <div class="wizard-step-grid">
                <div class="wizard-card">
                    <h4>Fontes de conhecimento</h4>
                    <div class="form-group">
                        <label class="form-label" for="wizard-vector-stores">Vector Stores</label>
                        ${hasVectorStores ? `
                            <select id="wizard-vector-stores" class="form-select" multiple data-field="vector_store_ids" data-field-type="multiselect">
                                ${wizardState.resources.vectorStores.map(store => `
                                    <option value="${escapeHtml(store.openai_store_id || '')}" ${data.vector_store_ids.includes(store.openai_store_id || '') ? 'selected' : ''}>${escapeHtml(store.name)}</option>
                                `).join('')}
                            </select>
                        ` : `
                            <input id="wizard-vector-stores" type="text" class="form-input" placeholder="vs_abc,vs_def" data-field="vector_store_ids" />
                            <small class="form-help">Informe IDs separados por vírgula.</small>
                        `}
                        <small class="form-help">Selecione os repositórios que o agente poderá consultar durante o atendimento.</small>
                    </div>
                </div>
                <div class="wizard-card">
                    <h4>Ferramentas</h4>
                    <label class="form-checkbox">
                        <input type="checkbox" data-field="enable_file_search" data-field-type="boolean" ${data.enable_file_search ? 'checked' : ''} />
                        Habilitar File Search
                    </label>
                    <p class="text-muted">Permite que o agente consulte os documentos disponíveis nos vector stores selecionados.</p>
                </div>
            </div>
        `;
    }

    function renderBehaviorStep() {
        const data = wizardState.data;
        return `
            <div class="wizard-step-grid">
                <div class="wizard-card">
                    <h4>Prompt &amp; mensagens</h4>
                    <div class="form-group">
                        <label class="form-label" for="wizard-prompt-id">Prompt ID</label>
                        ${renderPromptSelect('wizard-prompt-id', data.prompt_id)}
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="wizard-prompt-version">Versão do Prompt</label>
                        <input id="wizard-prompt-version" type="text" class="form-input" data-field="prompt_version" value="${escapeHtml(data.prompt_version)}" placeholder="Última versão" />
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="wizard-system-message">Comportamento do agente</label>
                        <textarea id="wizard-system-message" class="form-textarea" rows="10" data-field="system_message">${escapeHtml(data.system_message)}</textarea>
                        <small class="form-help">Defina instruções claras sobre voz, limites e objetivos do agente.</small>
                    </div>
                </div>
                <div class="wizard-card">
                    <h4>Prompt Builder</h4>
                    <p>Use o Prompt Builder para gerar instruções estruturadas e aplicá-las automaticamente.</p>
                    <button class="btn btn-purple" data-action="open-prompt-builder">✨ Abrir Prompt Builder</button>
                    <div id="wizard-prompt-builder-status" class="wizard-prompt-builder-status"></div>
                </div>
                <div class="wizard-card">
                    <h4>Resumo</h4>
                    <div id="wizard-review-summary" class="wizard-review-summary"></div>
                </div>
            </div>
        `;
    }

    function renderModelSelect(id, selectedModel) {
        if (!wizardState.resources.models || wizardState.resources.models.length === 0) {
            return `<input id="${id}" type="text" class="form-input" data-field="model" value="${escapeHtml(selectedModel)}" placeholder="gpt-4o, gpt-4o-mini" />`;
        }

        const options = getSortedModels().map(model => `
            <option value="${escapeHtml(model.id)}" ${model.id === selectedModel ? 'selected' : ''}>${escapeHtml(model.id)}</option>
        `).join('');

        return `
            <select id="${id}" class="form-select" data-field="model">
                <option value="">Usar padrão</option>
                ${options}
            </select>
        `;
    }

    function renderPromptSelect(id, selectedPrompt) {
        if (!wizardState.resources.prompts || wizardState.resources.prompts.length === 0) {
            return `<input id="${id}" type="text" class="form-input" data-field="prompt_id" value="${escapeHtml(selectedPrompt)}" placeholder="prompt_123" />`;
        }

        const options = wizardState.resources.prompts.map(prompt => `
            <option value="${escapeHtml(prompt.openai_prompt_id || '')}" ${selectedPrompt === (prompt.openai_prompt_id || '') ? 'selected' : ''}>${escapeHtml(prompt.name)}</option>
        `).join('');

        return `
            <select id="${id}" class="form-select" data-field="prompt_id">
                <option value="">Selecionar prompt</option>
                ${options}
            </select>
        `;
    }

    function getSortedModels() {
        const priority = id => {
            if (id.startsWith('gpt-4')) return 1;
            if (id.startsWith('gpt-3.5')) return 2;
            return 3;
        };

        return [...wizardState.resources.models].sort((a, b) => {
            const diff = priority(a.id) - priority(b.id);
            return diff !== 0 ? diff : a.id.localeCompare(b.id);
        });
    }

    function bindStepEvents() {
        const body = document.querySelector('.wizard-step-body');
        if (!body) {
            return;
        }

        body.querySelectorAll('[data-field]').forEach(element => {
            const eventType = element.type === 'checkbox' || element.tagName === 'SELECT' ? 'change' : 'input';
            element.addEventListener(eventType, handleFieldChange);
            if (element.tagName === 'SELECT' && element.dataset.fieldType === 'multiselect') {
                element.addEventListener('change', handleFieldChange);
            }
        });

        body.addEventListener('click', event => {
            const action = event.target?.dataset?.action;
            if (!action) return;

            if (action === 'open-prompt-builder') {
                event.preventDefault();
                handleOpenPromptBuilder();
            }
        });
    }

    function bindFooterActions() {
        const content = document.getElementById('content');
        if (!content) return;

        const footer = content.querySelector('.wizard-footer');
        if (!footer) return;

        footer.addEventListener('click', event => {
            const action = event.target?.dataset?.action;
            if (!action) return;
            event.preventDefault();

            switch (action) {
                case 'previous':
                    if (wizardState.stepIndex > 0) {
                        wizardState.stepIndex -= 1;
                        renderWorkspace();
                    }
                    break;
                case 'next':
                    if (wizardState.stepIndex < wizardSteps.length - 1) {
                        wizardState.stepIndex += 1;
                        renderWorkspace();
                    }
                    break;
                case 'save-draft':
                    saveDraftToStorage();
                    break;
                case 'publish':
                    publishAgent();
                    break;
                default:
                    break;
            }
        });
    }

    function handleFieldChange(event) {
        const element = event.target;
        const field = element.dataset.field;
        if (!field) {
            return;
        }

        const type = element.dataset.fieldType || element.type;
        let value;

        if (type === 'boolean' || element.type === 'checkbox') {
            value = element.checked;
        } else if (type === 'multiselect') {
            value = Array.from(element.selectedOptions || [])
                .map(option => option.value)
                .filter(Boolean);
        } else {
            value = element.value;
        }

        wizardState.data[field] = value;

        if (field === 'name') {
            document.querySelector('.wizard-title')?.classList.toggle('has-name', Boolean(value));
        }

        if (wizardState.stepIndex === wizardSteps.length - 1) {
            updateReviewSummary();
        }
    }

    function handleOpenPromptBuilder() {
        if (!wizardState.agentId) {
            showToast('O Prompt Builder precisa de um agente existente. Publique o agente ou abra um existente para continuar.', 'info');
            return;
        }

        if (typeof showPromptBuilderModal !== 'function') {
            showToast('Prompt Builder indisponível no momento.', 'error');
            return;
        }

        const agentName = wizardState.data.name || 'Agente';
        showPromptBuilderModal(wizardState.agentId, agentName);
    }

    function saveDraftToStorage() {
        try {
            const payload = {
                data: wizardState.data,
                timestamp: Date.now()
            };
            localStorage.setItem(DRAFT_STORAGE_KEY, JSON.stringify(payload));
            showToast('Rascunho salvo com sucesso', 'success');
        } catch (error) {
            console.error('Failed to save draft', error);
            showToast('Não foi possível salvar o rascunho', 'error');
        }
    }

    function hydrateDraftFromStorage() {
        try {
            const raw = localStorage.getItem(DRAFT_STORAGE_KEY);
            if (!raw) {
                return;
            }
            const draft = JSON.parse(raw);
            if (draft && draft.data) {
                wizardState.data = {
                    ...getDefaultFormState(),
                    ...draft.data,
                    vector_store_ids: Array.isArray(draft.data.vector_store_ids) ? draft.data.vector_store_ids : []
                };
            }
        } catch (error) {
            console.warn('Failed to load draft', error);
        }
    }

    function clearDraftFromStorage() {
        localStorage.removeItem(DRAFT_STORAGE_KEY);
    }

    function buildAgentPayload() {
        const data = wizardState.data;
        const payload = {
            name: data.name?.trim(),
            description: data.description?.trim() || null,
            api_type: data.api_type,
            model: data.model ? data.model : null,
            prompt_id: data.prompt_id ? data.prompt_id : null,
            prompt_version: data.prompt_version ? data.prompt_version : null,
            system_message: data.system_message ? data.system_message : null,
            is_default: Boolean(data.is_default)
        };

        const temperature = data.temperature !== '' && data.temperature !== null && data.temperature !== undefined ? Number(data.temperature) : null;
        const topP = data.top_p !== '' && data.top_p !== null && data.top_p !== undefined ? Number(data.top_p) : null;
        const maxOutputTokens = data.max_output_tokens !== '' && data.max_output_tokens !== null && data.max_output_tokens !== undefined ? Number(data.max_output_tokens) : null;

        if (!Number.isNaN(temperature) && temperature !== null) {
            payload.temperature = temperature;
        }
        if (!Number.isNaN(topP) && topP !== null) {
            payload.top_p = topP;
        }
        if (!Number.isNaN(maxOutputTokens) && maxOutputTokens !== null) {
            payload.max_output_tokens = maxOutputTokens;
        }

        if (Array.isArray(data.vector_store_ids) && data.vector_store_ids.length > 0) {
            payload.vector_store_ids = data.vector_store_ids.filter(Boolean);
        } else if (typeof data.vector_store_ids === 'string' && data.vector_store_ids.trim()) {
            payload.vector_store_ids = data.vector_store_ids.split(',').map(item => item.trim()).filter(Boolean);
        }

        if (data.enable_file_search) {
            payload.tools = [{ type: 'file_search' }];
        }

        return payload;
    }

    async function publishAgent() {
        if (!wizardState.data.name || !wizardState.data.name.trim()) {
            showToast('Informe um nome para o agente antes de publicar.', 'error');
            wizardState.stepIndex = 0;
            renderWorkspace();
            return;
        }

        const payload = buildAgentPayload();
        const publishButton = document.querySelector('[data-action="publish"]');
        if (publishButton) {
            publishButton.disabled = true;
            publishButton.textContent = 'Publicando...';
        }

        try {
            if (wizardState.mode === 'create') {
                await api.createAgent(payload);
                clearDraftFromStorage();
                showToast('Agente criado com sucesso', 'success');
            } else if (wizardState.agentId) {
                await api.updateAgent(wizardState.agentId, payload);
                showToast('Agente atualizado com sucesso', 'success');
            }
            loadAgentsPage();
        } catch (error) {
            console.error('Failed to publish agent', error);
            showToast('Não foi possível publicar o agente: ' + (error.message || error), 'error');
        } finally {
            if (publishButton) {
                publishButton.disabled = false;
                publishButton.textContent = 'Publicar';
            }
        }
    }

    function updateReviewSummary() {
        const container = document.getElementById('wizard-review-summary');
        if (!container) {
            return;
        }

        const data = wizardState.data;
        const vectorStores = Array.isArray(data.vector_store_ids) ? data.vector_store_ids : (data.vector_store_ids || '').split(',').map(item => item.trim()).filter(Boolean);

        container.innerHTML = `
            <dl class="wizard-summary-list">
                <div>
                    <dt>Nome</dt>
                    <dd>${escapeHtml(data.name) || '—'}</dd>
                </div>
                <div>
                    <dt>Descrição</dt>
                    <dd>${escapeHtml(data.description) || '—'}</dd>
                </div>
                <div>
                    <dt>API</dt>
                    <dd>${escapeHtml(data.api_type)}</dd>
                </div>
                <div>
                    <dt>Modelo</dt>
                    <dd>${escapeHtml(data.model || 'Padrão')}</dd>
                </div>
                <div>
                    <dt>Temperatura</dt>
                    <dd>${escapeHtml(data.temperature || '—')}</dd>
                </div>
                <div>
                    <dt>Top P</dt>
                    <dd>${escapeHtml(data.top_p || '—')}</dd>
                </div>
                <div>
                    <dt>Max Tokens</dt>
                    <dd>${escapeHtml(data.max_output_tokens || '—')}</dd>
                </div>
                <div>
                    <dt>Vector Stores</dt>
                    <dd>${vectorStores.length ? escapeHtml(vectorStores.join(', ')) : '—'}</dd>
                </div>
                <div>
                    <dt>File Search</dt>
                    <dd>${data.enable_file_search ? 'Ativado' : 'Desativado'}</dd>
                </div>
            </dl>
        `;
    }

    function updatePromptBuilderPreview() {
        const container = document.getElementById('wizard-prompt-builder-status');
        if (!container) {
            return;
        }

        if (wizardState.promptBuilder.isGenerating) {
            container.innerHTML = '<p class="text-muted">Gerando instruções com o Prompt Builder...</p>';
            return;
        }

        const result = wizardState.promptBuilder.lastGenerated;
        if (!result) {
            container.innerHTML = '<p class="text-muted">Nenhum prompt gerado ainda.</p>';
            return;
        }

        container.innerHTML = `
            <div class="wizard-prompt-preview">
                <div class="wizard-prompt-meta">
                    <span class="badge">Versão ${escapeHtml(result.version || '?')}</span>
                    ${result.latency_ms ? `<span class="badge badge-muted">${escapeHtml(result.latency_ms)} ms</span>` : ''}
                </div>
                <p class="text-muted">Última geração aplicada automaticamente ao campo de comportamento.</p>
                <pre>${escapeHtml(result.prompt_md || '')}</pre>
            </div>
        `;
    }

    function ensurePromptBuilderIntegration() {
        if (wizardState.promptBuilder.hooksRegistered) {
            return;
        }

        const promptBuilder = window.promptBuilder;
        if (!promptBuilder || typeof promptBuilder.registerHooks !== 'function') {
            return;
        }

        promptBuilder.registerHooks({
            onGenerationStart: ({ agentId }) => {
                if (!wizardState.agentId || (agentId && agentId !== wizardState.agentId)) {
                    return;
                }
                wizardState.promptBuilder.isGenerating = true;
                updatePromptBuilderPreview();
            },
            onPromptGenerated: (result, context) => {
                if (!wizardState.agentId || (context?.agentId && context.agentId !== wizardState.agentId)) {
                    return;
                }
                wizardState.promptBuilder.isGenerating = false;
                wizardState.promptBuilder.lastGenerated = result;
                if (result?.prompt_md) {
                    wizardState.data.system_message = result.prompt_md;
                    const systemMessageField = document.getElementById('wizard-system-message');
                    if (systemMessageField) {
                        systemMessageField.value = result.prompt_md;
                    }
                }
                updatePromptBuilderPreview();
                updateReviewSummary();
                showToast('Prompt gerado aplicado ao agente.', 'success');
            }
        });

        wizardState.promptBuilder.hooksRegistered = true;
    }

    window.openAgentWorkspace = openAgentWorkspace;
})();
