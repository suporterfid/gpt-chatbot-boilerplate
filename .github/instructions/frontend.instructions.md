---
applyTo: "**/*.{js,css,html}"
description: "Regras específicas para código frontend (JavaScript, CSS, HTML)"
---

# Instruções Frontend - gpt-chatbot-boilerplate

## Arquivos Alvo
- `chatbot-enhanced.js` - Widget principal do chatbot
- `public/admin/*.js` - Scripts da interface administrativa
- `*.css` - Arquivos de estilo
- `*.html` - Páginas HTML

## Padrões de Código JavaScript

### Estilo e Convenções
- **Vanilla JS First**: Não adicionar frameworks JS (React, Vue, Angular). O projeto usa JavaScript puro.
- **Compatibilidade**: Manter compatibilidade com navegadores modernos (ES6+).
- **Variáveis**: Usar `const` e `let` ao invés de `var`.
- **Igualdade**: Sempre usar strict equality (`===` / `!==`).
- **Nomenclatura**:
  - Classes/Constructors: `PascalCase`
  - Funções/métodos: `camelCase`
  - Constantes: `UPPER_SNAKE_CASE`

### Organização do Código
- **Módulos**: Usar padrão de módulo revelador ou IIFE para encapsular funcionalidade.
- **Eventos**: Usar event delegation quando apropriado para melhor performance.
- **Callbacks**: Preferir Promises ou async/await para operações assíncronas.

### Widget do Chatbot (chatbot-enhanced.js)
- **Inicialização**: Manter padrão `ChatBot.init(config)` para compatibilidade.
- **Configuração**: Validar todas as opções de configuração no início.
- **Transporte**: Suportar WebSocket → SSE → AJAX fallback automático.
- **Streaming**: Implementar processamento incremental de mensagens SSE.
- **Upload de Arquivos**: Validar tipo e tamanho antes de enviar.
- **Eventos Customizados**: Manter callbacks configuráveis (onMessage, onError, etc.).

### Admin UI (public/admin/)
- **Estado**: Gerenciar estado da aplicação de forma centralizada.
- **API Calls**: Usar funções helper reutilizáveis para chamadas à API.
- **Feedback**: Sempre fornecer feedback visual para ações do usuário (loading, success, error).
- **Validação**: Validar inputs no frontend antes de enviar ao backend.

## Padrões CSS

### Organização
- **BEM ou similar**: Usar metodologia consistente de nomenclatura.
- **Mobile-First**: Design responsivo começando de telas pequenas.
- **Variáveis CSS**: Usar custom properties para cores, espaçamentos, tipografia.
- **Prefixos**: Evitar prefixos vendor desnecessários (usar autoprefixer se necessário).

### Performance
- **Seletores**: Evitar seletores muito específicos ou aninhados profundamente.
- **Animações**: Usar `transform` e `opacity` para animações performáticas.
- **Critical CSS**: Considerar inlining de CSS crítico para páginas importantes.

## Testes Frontend

### Linting
```bash
npm run lint
```

### Validação Manual
- Testar em múltiplos navegadores (Chrome, Firefox, Safari).
- Verificar responsividade em diferentes tamanhos de tela.
- Testar funcionalidades de streaming e upload de arquivos.
- Validar acessibilidade básica (navegação por teclado, contraste).

## Segurança Frontend

### Práticas Obrigatórias
- **XSS Prevention**: Escapar todo conteúdo renderizado de fontes externas.
- **Secrets**: NUNCA expor chaves de API ou secrets no código frontend.
- **HTTPS**: Sempre usar HTTPS em produção para comunicação com backend.
- **Content Security Policy**: Respeitar CSP headers configurados.

### Dados Sensíveis
- Não armazenar dados sensíveis no localStorage/sessionStorage.
- Limpar dados sensíveis da memória após uso.
- Não logar informações confidenciais no console em produção.

## Integração com Backend

### Endpoints Principais
- `POST /chat-unified.php` - Endpoint unificado de chat (suporta SSE)
- `POST /admin-api.php` - API administrativa (requer autenticação)
- `GET /metrics.php` - Métricas (para dashboards)

### Formato de Requisições
- Sempre incluir `Content-Type: application/json` para payloads JSON.
- Incluir headers de autenticação quando necessário (session cookie ou API key).
- Tratar erros HTTP apropriadamente (4xx, 5xx).

### Server-Sent Events (SSE)
- Implementar reconexão automática em caso de desconexão.
- Processar eventos `data:`, `error:`, e `done:` corretamente.
- Usar `EventSource` quando disponível, fallback para fetch com ReadableStream.

## Comentários e Documentação

### Quando Comentar
- Lógica complexa ou não óbvia.
- Workarounds para bugs de navegador específicos.
- Decisões de design importantes.
- APIs públicas e configurações.

### JSDoc
- Adicionar JSDoc para funções públicas e APIs configuráveis.
- Documentar tipos de parâmetros e retornos.
- Incluir exemplos de uso quando apropriado.

## Performance

### Otimizações
- **Debounce/Throttle**: Usar para eventos frequentes (scroll, resize, input).
- **Lazy Loading**: Carregar recursos apenas quando necessário.
- **Bundle Size**: Manter JavaScript pequeno e modular.
- **Caching**: Aproveitar cache do navegador para assets estáticos.

### Monitoramento
- Usar Performance API para medir métricas críticas.
- Evitar memory leaks (remover event listeners, limpar timers).

## Exemplos

### Inicialização Básica do Widget
```javascript
ChatBot.init({
    mode: 'floating',
    apiType: 'chat',
    apiEndpoint: '/chat-unified.php',
    title: 'Suporte',
    assistant: {
        name: 'AssistenteBot',
        welcomeMessage: 'Olá! Como posso ajudar?'
    }
});
```

### Chamada à Admin API
```javascript
async function fetchAgents() {
    try {
        const response = await fetch('/admin-api.php?action=list_agents', {
            method: 'GET',
            credentials: 'include'
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        return await response.json();
    } catch (error) {
        console.error('Failed to fetch agents:', error);
        throw error;
    }
}
```

## Checklist de Revisão

Antes de finalizar mudanças em código frontend:

- [ ] Código segue convenções de estilo do projeto
- [ ] `npm run lint` passa sem erros
- [ ] Testado em Chrome, Firefox, Safari
- [ ] Responsivo em mobile, tablet, desktop
- [ ] Sem hardcoding de URLs ou secrets
- [ ] Feedback visual para ações do usuário
- [ ] Tratamento apropriado de erros
- [ ] Comentários em lógica complexa
- [ ] Sem console.log desnecessários em produção
- [ ] Acessibilidade básica verificada
