(function () {
    const DRAFT_STORAGE_KEY = 'agentWorkspaceDraft';

    const wizardState = {
        mode: 'create',
        agentId: null,
        stepIndex: 0,
        activeTab: 'configure',
        isLoading: false,
        resources: {
            prompts: [],
            vectorStores: [],
            models: []
        },
        data: getDefaultFormState(),
        channels: [],
        promptBuilder: {
            lastGenerated: null,
            isGenerating: false,
            hooksRegistered: false
        },
        testSession: getDefaultTestSessionState()
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
            slug: '',
            description: '',
            is_default: false,
            status: '',
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

    function getDefaultTestSessionState() {
        return {
            agentId: null,
            messages: [],
            input: '',
            isStreaming: false,
            eventSource: null,
            streamAbortController: null,
            error: null,
            statusNotice: null,
            promptMeta: {
                loading: false,
                data: null,
                error: null,
                lastUpdated: null
            },
            feedback: null,
            isUpdatingStatus: false,
            isMakingDefault: false,
            shouldFocusInput: false
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

    function closeTestStream() {
        const session = wizardState.testSession;
        if (!session) {
            return;
        }

        if (session.eventSource) {
            try {
                session.eventSource.close();
            } catch (error) {
                console.warn('Failed to close test stream', error);
            }
            session.eventSource = null;
        }

        if (session.streamAbortController) {
            if (session.isStreaming) {
                try {
                    session.streamAbortController.abort();
                } catch (error) {
                    console.warn('Failed to abort test stream', error);
                }
            }
            session.streamAbortController = null;
        }

        session.isStreaming = false;
    }

    function resetTestSession(agentId = null) {
        closeTestStream();
        wizardState.testSession = {
            ...getDefaultTestSessionState(),
            agentId
        };
    }

    function resetWizardState() {
        wizardState.stepIndex = 0;
        wizardState.activeTab = 'configure';
        wizardState.data = getDefaultFormState();
        wizardState.channels = [];
        wizardState.promptBuilder.lastGenerated = null;
        wizardState.promptBuilder.isGenerating = false;
        resetTestSession(wizardState.agentId);
    }

    function openAgentWorkspace(options = {}) {
        const { mode = 'create', agentId = null, agentData = null, initialTab = 'configure' } = options;

        wizardState.mode = mode;
        wizardState.agentId = agentId || null;
        wizardState.isLoading = true;
        resetWizardState();

        if (initialTab === 'test' && wizardState.agentId) {
            wizardState.activeTab = 'test';
            wizardState.testSession.shouldFocusInput = true;
        }

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

        const resourcePromises = [
            api.listPrompts(),
            api.listVectorStores(),
            api.listModels()
        ];

        const shouldLoadChannels = Boolean(wizardState.agentId);
        if (shouldLoadChannels) {
            resourcePromises.push(
                api.listAgentChannels(wizardState.agentId).catch(error => {
                    console.warn('Failed to load channels for workspace', error);
                    return [];
                })
            );
        }

        Promise.all(resourcePromises).then(results => {
            const prompts = results[0];
            const vectorStores = results[1];
            const models = results[2];
            const channels = shouldLoadChannels ? (results[3] || []) : [];
            wizardState.resources.prompts = prompts || [];
            wizardState.resources.vectorStores = vectorStores || [];
            wizardState.resources.models = models && Array.isArray(models.data) ? models.data : [];
            wizardState.channels = channels;

            if (wizardState.mode === 'create' && !agentData) {
                hydrateDraftFromStorage();
            }

            wizardState.isLoading = false;
            if (wizardState.agentId) {
                ensurePromptMetadataLoaded(true);
            }
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

        const tabs = [
            { id: 'configure', label: 'Configurar' },
            { id: 'test', label: 'Testar &amp; Publicar' }
        ];

        const tabsHtml = tabs.map(tab => {
            const isActive = wizardState.activeTab === tab.id;
            const isDisabled = tab.id === 'test' && !wizardState.agentId;
            const classes = ['workspace-tab', isActive ? 'active' : ''];
            return `
                <button type="button" class="${classes.filter(Boolean).join(' ')}" data-tab="${tab.id}" ${isDisabled ? 'disabled' : ''}>${tab.label}</button>
            `;
        }).join('');

        const currentStep = wizardSteps[wizardState.stepIndex];
        const mainContent = wizardState.activeTab === 'test'
            ? renderTestPublishTab()
            : renderConfigureTab(currentStep);

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
                    <div class="workspace-tabs">${tabsHtml}</div>
                    <div class="workspace-tab-panel">${mainContent}</div>
                </section>
            </div>
        `;

        bindWorkspaceTabs();

        if (wizardState.activeTab === 'test') {
            bindTestPanelEvents();
            updateTestConversationView();
            updatePromptMetadataView();
            updateTestStatusMessage();
            focusTesterInputIfNeeded();
        } else {
            bindStepEvents();
            bindFooterActions();
            updateReviewSummary();
            updatePromptBuilderPreview();
        }
    }

    function renderConfigureTab(currentStep) {
        return `
            <div class="wizard-panel">
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
            </div>
        `;
    }

    function renderTestPublishTab() {
        if (!wizardState.agentId) {
            return `
                <div class="tester-panel">
                    <div class="tester-empty-state">
                        <h3>Teste indisponível</h3>
                        <p class="text-muted">Publique o agente ou abra um existente para habilitar o painel de testes.</p>
                    </div>
                </div>
            `;
        }

        const session = wizardState.testSession;
        const trimmedInput = (session.input || '').trim();
        const agentStatus = wizardState.data && wizardState.data.status ? escapeHtml(wizardState.data.status) : null;
        const isDefault = Boolean(wizardState.data && wizardState.data.is_default);
        const isReady = (wizardState.data && typeof wizardState.data.status === 'string')
            ? wizardState.data.status.toLowerCase() === 'ready'
            : false;

        const summaryHtml = renderAgentSummarySection(wizardState.data, {
            title: 'Resumo do agente',
            layout: 'inline',
            compact: true,
            channels: wizardState.channels
        });
        const summaryBlock = summaryHtml
            ? `<div class="tester-card tester-summary-card">${summaryHtml}</div>`
            : '';

        return `
            <div class="tester-panel">
                <div class="tester-header">
                    <div>
                        <h3>Teste rápido</h3>
                        <p class="text-muted">Envie mensagens para validar o comportamento do agente antes de marcar como pronto.</p>
                    </div>
                    <div class="tester-agent-status">
                        ${agentStatus ? `<span class="badge tester-badge-status">${agentStatus}</span>` : ''}
                        ${isDefault ? '<span class="badge badge-success">Padrão</span>' : ''}
                    </div>
                </div>

                ${summaryBlock}

                <div class="tester-card">
                    <div class="tester-card-header">
                        <h4>Prompt ativo</h4>
                        <div class="tester-meta-actions">
                            <button class="btn btn-small btn-outline" data-action="refresh-prompt-meta" ${session.promptMeta.loading ? 'disabled' : ''}>
                                ${session.promptMeta.loading ? '<span class="spinner spinner-inline"></span>' : 'Atualizar metadados'}
                            </button>
                        </div>
                    </div>
                    <div id="tester-prompt-meta" class="tester-prompt-meta">
                        ${buildPromptMetadataHtml()}
                    </div>
                </div>

                <div class="tester-card tester-chat-card">
                    <div class="tester-card-header">
                        <h4>Histórico de teste</h4>
                    </div>
                    <div id="tester-chat-history" class="tester-chat-history">
                        ${buildTestMessagesHtml()}
                    </div>
                </div>

                <div class="tester-card tester-input-card">
                    <label class="form-label" for="tester-input">Mensagem de teste</label>
                    <textarea id="tester-input" class="form-textarea tester-input" rows="3" placeholder="Faça uma pergunta ou descreva um cenário">${escapeHtml(session.input)}</textarea>
                    <div class="tester-input-actions">
                        <button class="btn btn-secondary" data-action="reset-conversation" ${session.isStreaming || session.messages.length === 0 ? 'disabled' : ''}>Limpar conversa</button>
                        <div class="tester-send-group">
                            <div id="tester-status-message" class="tester-status-message">${buildStatusMessageHtml()}</div>
                            <button class="btn btn-primary" data-action="send-test-message" ${session.isStreaming || !trimmedInput ? 'disabled' : ''}>
                                ${session.isStreaming ? '<span class="spinner spinner-inline"></span> Enviando' : 'Enviar mensagem'}
                            </button>
                        </div>
                    </div>
                </div>

                <div class="tester-card tester-publish-card">
                    <div class="tester-card-header">
                        <h4>Publicação</h4>
                    </div>
                    <p class="text-muted">Finalize a configuração marcando o agente como pronto ou definindo-o como padrão.</p>
                    <div class="tester-publish-actions">
                        <button class="btn btn-success" data-action="mark-ready" ${session.isUpdatingStatus || isReady ? 'disabled' : ''}>
                            ${session.isUpdatingStatus ? '<span class="spinner spinner-inline"></span>' : ''} Marcar como pronto
                        </button>
                        <button class="btn btn-outline" data-action="make-default" ${isDefault || session.isMakingDefault ? 'disabled' : ''}>
                            ${session.isMakingDefault ? '<span class="spinner spinner-inline"></span>' : ''} Definir como padrão
                        </button>
                    </div>
                    <div id="tester-feedback" class="tester-feedback">${buildFeedbackHtml()}</div>
                </div>
            </div>
        `;
    }

    function bindWorkspaceTabs() {
        const tabs = document.querySelectorAll('.workspace-tabs [data-tab]');
        tabs.forEach(tab => {
            tab.addEventListener('click', event => {
                const target = event.currentTarget;
                const tabId = target.dataset.tab;
                if (!tabId || target.disabled) {
                    return;
                }
                if (wizardState.activeTab === tabId) {
                    return;
                }
                wizardState.activeTab = tabId;
                if (tabId === 'test') {
                    wizardState.testSession.shouldFocusInput = true;
                    ensurePromptMetadataLoaded();
                }
                renderWorkspace();
            });
        });
    }

    function bindTestPanelEvents() {
        const panel = document.querySelector('.tester-panel');
        if (!panel) {
            return;
        }

        const input = panel.querySelector('#tester-input');
        if (input) {
            input.addEventListener('input', handleTestInputChange);
            input.addEventListener('keydown', handleTestInputKeydown);
        }

        panel.addEventListener('click', event => {
            const actionElement = event.target?.closest?.('[data-action]');
            if (!actionElement) {
                return;
            }

            event.preventDefault();
            const action = actionElement.dataset.action;

            switch (action) {
                case 'send-test-message':
                    handleSendTestMessage();
                    break;
                case 'reset-conversation':
                    handleResetTestConversation();
                    break;
                case 'refresh-prompt-meta':
                    ensurePromptMetadataLoaded(true);
                    break;
                case 'mark-ready':
                    handleMarkAsReady();
                    break;
                case 'make-default':
                    handleMakeDefaultFromTest();
                    break;
                default:
                    break;
            }
        });
    }

    function handleTestInputChange(event) {
        wizardState.testSession.input = event.target.value;
    }

    function handleTestInputKeydown(event) {
        if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) {
            event.preventDefault();
            handleSendTestMessage();
        }
    }

    function handleSendTestMessage() {
        if (!wizardState.agentId || wizardState.testSession.isStreaming) {
            return;
        }

        const message = (wizardState.testSession.input || '').trim();
        if (!message) {
            return;
        }

        const session = wizardState.testSession;
        session.messages = [
            ...session.messages,
            { role: 'user', content: message },
            { role: 'assistant', content: '', streaming: true }
        ];
        session.input = '';
        session.isStreaming = true;
        session.statusNotice = { type: 'info', message: 'Gerando resposta...' };
        session.feedback = null;
        session.shouldFocusInput = true;

        renderWorkspace();

        const assistantMessage = session.messages[session.messages.length - 1];
        startTestStream(wizardState.agentId, assistantMessage, message);
    }

    function handleResetTestConversation() {
        if (wizardState.testSession.isStreaming) {
            return;
        }

        closeTestStream();
        wizardState.testSession.messages = [];
        wizardState.testSession.input = '';
        wizardState.testSession.statusNotice = null;
        wizardState.testSession.feedback = null;
        wizardState.testSession.shouldFocusInput = true;
        renderWorkspace();
    }

    function parseSSEEvent(rawEvent) {
        if (!rawEvent || !rawEvent.trim()) {
            return null;
        }

        const lines = rawEvent.split('\n');
        let eventType = 'message';
        const dataLines = [];

        for (const line of lines) {
            const trimmed = line.trim();
            if (!trimmed) {
                continue;
            }
            if (trimmed.startsWith('event:')) {
                eventType = trimmed.slice(6).trim() || eventType;
            } else if (trimmed.startsWith('data:')) {
                dataLines.push(trimmed.slice(5).trim());
            }
        }

        if (dataLines.length === 0) {
            return null;
        }

        const payload = dataLines.join('\n');

        if (payload === '[DONE]') {
            return { type: 'done' };
        }

        try {
            const parsed = JSON.parse(payload);
            if (parsed && typeof parsed === 'object' && !parsed.type) {
                parsed.type = eventType || 'message';
            }
            return parsed;
        } catch (error) {
            return {
                type: eventType || 'message',
                content: payload
            };
        }
    }

    async function startTestStream(agentId, assistantMessage, userMessage) {
        closeTestStream();

        let url = '';
        try {
            url = api.testAgent(agentId);
        } catch (error) {
            wizardState.testSession.isStreaming = false;
            wizardState.testSession.statusNotice = { type: 'error', message: error.message || 'Não foi possível iniciar o teste.' };
            renderWorkspace();
            return;
        }

        const payload = { message: userMessage };
        if (typeof tenantSelectionState !== 'undefined' && tenantSelectionState?.activeTenantId) {
            payload.tenant_id = tenantSelectionState.activeTenantId;
        }

        const controller = new AbortController();
        wizardState.testSession.streamAbortController = controller;

        try {
            const response = await authFetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'text/event-stream'
                },
                body: JSON.stringify(payload),
                signal: controller.signal
            });

            if (response.status === 401) {
                const unauthorizedError = typeof APIError === 'function'
                    ? new APIError('Authentication required', { status: 401 })
                    : new Error('Authentication required');
                if (typeof handleUnauthorized === 'function') {
                    handleUnauthorized(unauthorizedError);
                }
                throw unauthorizedError;
            }

            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`HTTP ${response.status}: ${errorText || 'Falha ao iniciar o teste do agente.'}`);
            }

            const reader = response.body?.getReader();
            if (!reader) {
                const text = await response.text();
                assistantMessage.content = text;
                assistantMessage.streaming = false;
                wizardState.testSession.isStreaming = false;
                wizardState.testSession.statusNotice = { type: 'success', message: 'Resposta recebida.' };
                renderWorkspace();
                return;
            }

            const decoder = new TextDecoder();
            let buffer = '';
            let shouldStop = false;

            while (!shouldStop) {
                const { value, done } = await reader.read();
                if (done) {
                    break;
                }

                buffer += decoder.decode(value, { stream: true });

                let boundary = buffer.indexOf('\n\n');
                while (boundary !== -1) {
                    const rawEvent = buffer.slice(0, boundary);
                    buffer = buffer.slice(boundary + 2);
                    const event = parseSSEEvent(rawEvent);

                    if (event) {
                        if (event.type === 'error' || event.type === 'done') {
                            shouldStop = true;
                        }
                        handleTestStreamEvent(event, assistantMessage);
                    }

                    boundary = buffer.indexOf('\n\n');
                }
            }

            if (!shouldStop && buffer.trim()) {
                const event = parseSSEEvent(buffer);
                if (event) {
                    handleTestStreamEvent(event, assistantMessage);
                }
            }

            wizardState.testSession.isStreaming = false;
        } catch (error) {
            if (error?.name === 'AbortError') {
                return;
            }

            console.error('Falha ao iniciar teste do agente', error);
            wizardState.testSession.isStreaming = false;
            wizardState.testSession.statusNotice = { type: 'error', message: error.message || 'Não foi possível iniciar o teste.' };
            assistantMessage.streaming = false;
            renderWorkspace();
        } finally {
            wizardState.testSession.streamAbortController = null;
        }
    }

    function handleTestStreamEvent(data, assistantMessage) {
        if (!data || typeof data.type !== 'string') {
            return;
        }

        switch (data.type) {
            case 'start':
                wizardState.testSession.statusNotice = {
                    type: 'info',
                    message: data.agent?.name ? `Testando ${data.agent.name}` : 'Teste iniciado'
                };
                updateTestStatusMessage();
                break;
            case 'chunk':
                assistantMessage.content += data.content || '';
                updateTestConversationView();
                break;
            case 'notice':
                if (data.message || data.content) {
                    wizardState.testSession.messages.push({
                        role: 'system',
                        content: data.message || data.content
                    });
                    updateTestConversationView();
                }
                break;
            case 'tool_call':
                wizardState.testSession.messages.push({
                    role: 'system',
                    content: `Tool call ${data.tool_name || ''}: ${JSON.stringify(data.arguments || {})}`
                });
                updateTestConversationView();
                break;
            case 'error':
                wizardState.testSession.isStreaming = false;
                wizardState.testSession.statusNotice = {
                    type: 'error',
                    message: data.message || 'Erro ao testar o agente.'
                };
                assistantMessage.streaming = false;
                closeTestStream();
                renderWorkspace();
                break;
            case 'done':
                wizardState.testSession.isStreaming = false;
                wizardState.testSession.statusNotice = {
                    type: 'success',
                    message: 'Resposta concluída.'
                };
                assistantMessage.streaming = false;
                closeTestStream();
                renderWorkspace();
                break;
            default:
                break;
        }
    }

    function buildTestMessagesHtml() {
        const messages = wizardState.testSession.messages || [];
        if (messages.length === 0) {
            return '<div class="tester-empty-hint"><p class="text-muted">Nenhuma mensagem enviada ainda.</p></div>';
        }

        return messages.map(message => {
            const role = message.role || 'assistant';
            let roleClass = 'chat-bubble-assistant';
            if (role === 'user') {
                roleClass = 'chat-bubble-user';
            } else if (role === 'system') {
                roleClass = 'chat-bubble-system';
            }
            const streamingClass = message.streaming ? 'streaming' : '';
            const content = escapeHtml(message.content || '').replace(/\n/g, '<br>');
            return `
                <div class="chat-row chat-row-${role}">
                    <div class="chat-bubble ${roleClass} ${streamingClass}">
                        ${content || '<span class="text-muted">...</span>'}
                        ${message.streaming ? '<span class="chat-stream-indicator"><span class="spinner spinner-inline"></span></span>' : ''}
                    </div>
                </div>
            `;
        }).join('');
    }

    function buildPromptMetadataHtml() {
        if (!wizardState.agentId) {
            return '<p class="text-muted">Disponível após publicar o agente.</p>';
        }

        const meta = wizardState.testSession.promptMeta;
        if (meta.loading) {
            return '<div class="tester-meta-loading"><span class="spinner spinner-inline"></span> Carregando metadados...</div>';
        }

        if (meta.error) {
            return `<p class="text-danger">${escapeHtml(meta.error)}</p>`;
        }

        const payload = meta.data?.data || meta.data;
        if (!payload) {
            return '<p class="text-muted">Nenhum dado disponível.</p>';
        }

        const versions = Array.isArray(payload.versions) ? payload.versions : [];
        if (versions.length === 0) {
            return '<p class="text-muted">Nenhum prompt gerado pelo Prompt Builder ainda.</p>';
        }

        const activeVersionNumber = payload.active_version;
        const activeVersion = versions.find(v => v.version === activeVersionNumber) || versions[0];
        const guardrails = (activeVersion?.guardrails || []).map(item => {
            if (typeof item === 'string') {
                return item;
            }
            return item?.key || item?.title || '';
        }).filter(Boolean);

        const updatedAt = activeVersion?.updated_at || activeVersion?.created_at;
        const formattedDate = updatedAt ? formatPromptDate(updatedAt) : '—';

        return `
            <dl class="tester-prompt-list">
                <div>
                    <dt>Versão ativa</dt>
                    <dd>${activeVersionNumber ? `v${escapeHtml(String(activeVersionNumber))}` : '—'}</dd>
                </div>
                <div>
                    <dt>Total de versões</dt>
                    <dd>${versions.length}</dd>
                </div>
                <div>
                    <dt>Atualizado em</dt>
                    <dd>${escapeHtml(formattedDate)}</dd>
                </div>
                <div>
                    <dt>Guardrails</dt>
                    <dd>${guardrails.length ? escapeHtml(guardrails.join(', ')) : 'Sem guardrails registrados'}</dd>
                </div>
            </dl>
        `;
    }

    function buildStatusMessageHtml() {
        const session = wizardState.testSession;
        if (session.isStreaming) {
            return '<span class="tester-status tester-status-streaming"><span class="spinner spinner-inline"></span> Gerando resposta...</span>';
        }
        if (session.statusNotice && session.statusNotice.message) {
            const typeClass = session.statusNotice.type ? `tester-status-${session.statusNotice.type}` : '';
            return `<span class="tester-status ${typeClass}">${escapeHtml(session.statusNotice.message)}</span>`;
        }
        return '<span class="tester-status text-muted">Envie uma mensagem para iniciar o teste.</span>';
    }

    function buildFeedbackHtml() {
        const feedback = wizardState.testSession.feedback;
        if (!feedback || !feedback.message) {
            return '';
        }
        const typeClass = feedback.type ? `is-${feedback.type}` : '';
        return `<div class="tester-feedback-message ${typeClass}">${escapeHtml(feedback.message)}</div>`;
    }

    function updateTestConversationView() {
        const container = document.getElementById('tester-chat-history');
        if (!container) {
            return;
        }
        container.innerHTML = buildTestMessagesHtml();
        container.scrollTop = container.scrollHeight;
    }

    function updatePromptMetadataView() {
        const container = document.getElementById('tester-prompt-meta');
        if (!container) {
            return;
        }
        container.innerHTML = buildPromptMetadataHtml();
    }

    function updateTestStatusMessage() {
        const container = document.getElementById('tester-status-message');
        if (!container) {
            return;
        }
        container.innerHTML = buildStatusMessageHtml();
    }

    function focusTesterInputIfNeeded() {
        if (!wizardState.testSession.shouldFocusInput) {
            return;
        }
        wizardState.testSession.shouldFocusInput = false;
        setTimeout(() => {
            const input = document.getElementById('tester-input');
            if (input) {
                input.focus();
                const length = input.value.length;
                try {
                    input.setSelectionRange(length, length);
                } catch (error) {
                    // Some browsers may not support setSelectionRange on certain input types
                }
            }
        }, 0);
    }

    function ensurePromptMetadataLoaded(force = false) {
        if (!wizardState.agentId || typeof api?.listPromptVersions !== 'function') {
            return;
        }

        const meta = wizardState.testSession.promptMeta;
        if (!force && meta.data && !meta.error) {
            return;
        }

        meta.loading = true;
        meta.error = null;
        if (wizardState.activeTab === 'test') {
            updatePromptMetadataView();
        }

        api.listPromptVersions(wizardState.agentId).then(result => {
            meta.loading = false;
            meta.error = null;
            meta.data = result;
            meta.lastUpdated = Date.now();
            if (wizardState.activeTab === 'test') {
                updatePromptMetadataView();
            }
        }).catch(error => {
            console.error('Falha ao carregar metadados do Prompt Builder', error);
            meta.loading = false;
            meta.error = error.message || 'Não foi possível carregar os metadados do prompt.';
            if (wizardState.activeTab === 'test') {
                updatePromptMetadataView();
            }
        });
    }

    async function handleMarkAsReady() {
        if (!wizardState.agentId || wizardState.testSession.isUpdatingStatus) {
            return;
        }

        wizardState.testSession.isUpdatingStatus = true;
        wizardState.testSession.feedback = null;
        renderWorkspace();

        try {
            const result = await api.updateAgent(wizardState.agentId, { status: 'ready' });
            wizardState.testSession.feedback = { type: 'success', message: 'Agente marcado como pronto.' };
            if (result && typeof result === 'object' && result.status) {
                wizardState.data.status = result.status;
            } else {
                wizardState.data.status = 'ready';
            }
        } catch (error) {
            console.error('Falha ao marcar agente como pronto', error);
            wizardState.testSession.feedback = { type: 'error', message: error.message || 'Não foi possível atualizar o status.' };
        } finally {
            wizardState.testSession.isUpdatingStatus = false;
            renderWorkspace();
        }
    }

    async function handleMakeDefaultFromTest() {
        if (!wizardState.agentId || wizardState.data.is_default || wizardState.testSession.isMakingDefault) {
            return;
        }

        wizardState.testSession.isMakingDefault = true;
        wizardState.testSession.feedback = null;
        renderWorkspace();

        try {
            await api.makeDefaultAgent(wizardState.agentId);
            wizardState.testSession.feedback = { type: 'success', message: 'Agente definido como padrão.' };
            wizardState.data.is_default = true;
        } catch (error) {
            console.error('Falha ao definir agente como padrão', error);
            wizardState.testSession.feedback = { type: 'error', message: error.message || 'Não foi possível definir como padrão.' };
        } finally {
            wizardState.testSession.isMakingDefault = false;
            renderWorkspace();
        }
    }

    function formatPromptDate(dateString) {
        if (typeof formatDate === 'function') {
            try {
                return formatDate(dateString);
            } catch (error) {
                console.warn('Falha ao formatar data com formatDate', error);
            }
        }

        try {
            const date = new Date(dateString);
            if (!Number.isNaN(date.getTime())) {
                return date.toLocaleString();
            }
        } catch (error) {
            console.warn('Falha ao interpretar data', error);
        }

        return dateString;
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
                        <label class="form-label" for="wizard-slug">Slug (identificador único)</label>
                        <input id="wizard-slug" data-field="slug" type="text" class="form-input" placeholder="Ex.: atendimento-premium" value="${escapeHtml(data.slug)}" pattern="[a-z0-9-]{1,64}" />
                        <small class="form-help">URL amigável para acessar este agente. Use apenas letras minúsculas, números e hífens (opcional).</small>
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

        const summaryHtml = renderAgentSummarySection(wizardState.data, {
            title: 'Resumo do agente',
            layout: 'stacked',
            compact: false,
            channels: wizardState.channels
        });

        container.innerHTML = summaryHtml;
    }

    function renderAgentSummarySection(agentData, options = {}) {
        const data = agentData || {};
        const channels = Array.isArray(options.channels) ? options.channels : Array.isArray(data.channels) ? data.channels : [];
        const vectorStoreLookup = options.vectorStoreLookup || (id => {
            const stores = wizardState.resources?.vectorStores || [];
            return stores.find(store => (store?.openai_store_id || '') === id);
        });

        const summaryPayload = {
            ...data,
            channels
        };

        if (window.AgentSummaryComponent && typeof window.AgentSummaryComponent.render === 'function') {
            return window.AgentSummaryComponent.render(summaryPayload, {
                title: options.title ?? 'Resumo do agente',
                layout: options.layout ?? 'stacked',
                compact: options.compact ?? false,
                showTitle: options.showTitle === false ? false : true,
                vectorStoreLookup
            });
        }

        return renderLegacyAgentSummary(summaryPayload, {
            title: options.title,
            showTitle: options.showTitle,
            vectorStoreLookup
        });
    }

    function renderLegacyAgentSummary(agentData, options = {}) {
        const data = agentData || {};
        const vectorStoreIds = parseVectorStoreIds(data.vector_store_ids);
        const vectorStoreLookup = options.vectorStoreLookup;
        const resolvedStores = vectorStoreIds.map(id => {
            const store = typeof vectorStoreLookup === 'function' ? vectorStoreLookup(id) : null;
            return store ? (store.name || store.display_name || store.openai_store_id || id) : id;
        });
        const channels = Array.isArray(data.channels) ? data.channels : [];
        const connectedChannels = channels.filter(channel => channel && channel.enabled).length;
        const channelSummary = channels.length
            ? `${connectedChannels}/${channels.length} canais conectados`
            : 'Nenhum canal configurado';

        const rows = [
            { label: 'Nome', value: data.name || '—' },
            { label: 'Descrição', value: data.description || '—' },
            { label: 'API', value: data.api_type || '—' },
            { label: 'Modelo', value: data.model || 'Padrão' },
            { label: 'Temperatura', value: data.temperature || '—' },
            { label: 'Top P', value: data.top_p || '—' },
            { label: 'Max Tokens', value: data.max_output_tokens || '—' },
            { label: 'Canais', value: channelSummary },
            { label: 'Vector stores', value: resolvedStores.length ? resolvedStores.join(', ') : '—' },
            { label: 'File Search', value: data.enable_file_search ? 'Ativado' : 'Desativado' }
        ];

        const title = options.showTitle === false ? '' : `<div class="agent-summary-title">${escapeHtml(options.title || 'Resumo do agente')}</div>`;
        const list = rows.map(row => `
                <div>
                    <dt>${escapeHtml(row.label)}</dt>
                    <dd>${escapeHtml(String(row.value))}</dd>
                </div>
            `).join('');

        return `
            <div class="agent-summary agent-summary-legacy">
                ${title}
                <dl class="wizard-summary-list">
                    ${list}
                </dl>
            </div>
        `;
    }

    function parseVectorStoreIds(value) {
        if (!value) {
            return [];
        }

        if (Array.isArray(value)) {
            return value.filter(Boolean);
        }

        if (typeof value === 'string') {
            return value.split(',').map(item => item.trim()).filter(Boolean);
        }

        return [];
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

    window.agentTester = window.agentTester || {};
    window.agentTester.start = async function startAgentTester(agentId) {
        if (!agentId) {
            showToast('Selecione um agente válido para testar.', 'error');
            return;
        }

        if (wizardState.agentId === agentId && !wizardState.isLoading) {
            wizardState.activeTab = 'test';
            wizardState.testSession.shouldFocusInput = true;
            ensurePromptMetadataLoaded();
            renderWorkspace();
            return;
        }

        if (typeof api?.getAgent !== 'function') {
            showToast('API de agentes indisponível.', 'error');
            return;
        }

        try {
            const agent = await api.getAgent(agentId);
            openAgentWorkspace({
                mode: 'edit',
                agentId,
                agentData: agent,
                initialTab: 'test'
            });
            wizardState.testSession.shouldFocusInput = true;
        } catch (error) {
            console.error('Falha ao carregar agente para teste', error);
            showToast('Não foi possível carregar o agente para teste: ' + (error.message || error), 'error');
        }
    };
})();
