# AGENTS.md

## Project Overview

This is a **dual-mode PHP chatbot backend** with a JavaScript widget that supports both OpenAI Chat Completions and Responses APIs. The system features streaming via SSE/WebSocket, file uploads, persistent AI agent configurations, and a full admin interface for managing agents without code changes.

**Key Technologies:**
- Backend: PHP 8.1+, Apache with mod_headers
- Frontend: Vanilla JavaScript (chatbot-enhanced.js)
- Optional: Ratchet WebSocket server, Composer dependencies
- APIs: OpenAI Chat Completions & Responses APIs with streaming
- Storage: PHP sessions or JSON files for conversation history
- Admin: Token-based REST API and web UI

**Core Features:**
- Dynamic agent switching (chat vs responses API modes)
- Streaming with SSE (Server-Sent Events) or WebSocket fallback
- File upload support with validation
- Rate limiting and conversation history management
- Tool calling with vector stores and file search
- Admin UI for agent CRUD operations

---

## Environment & Setup

### Prerequisites
- **PHP:** 8.1 or higher with Apache (mod_rewrite, mod_headers enabled)
- **Composer:** For dependency management (Ratchet WebSocket optional)
- **OpenAI API Key:** Required for all agent operations
- **Docker (optional):** Pre-configured Dockerfile and docker-compose.yml available

### Installation

1. **Clone the repository:**
   ```
   git clone https://github.com/suporterfid/gpt-chatbot-boilerplate.git
   cd gpt-chatbot-boilerplate
   ```

2. **Install dependencies:**
   ```
   composer install
   ```

3. **Configure environment:**
   ```
   cp .env.example .env
   # Edit .env with your OpenAI API key and configuration
   ```

4. **Required environment variables:**
   ```
   OPENAI_API_KEY=sk-...                    # Required
   ADMIN_API_KEY=your-secure-token          # Required for admin access
   
   # Optional configurations
   WEBSOCKET_ENABLED=false                  # Enable WebSocket server
   WEBSOCKET_PORT=8080                      # WebSocket server port
   STORAGE_TYPE=session                     # session|file
   RATE_LIMIT_REQUESTS=10                   # Requests per window
   RATE_LIMIT_WINDOW=60                     # Window in seconds
   ```

5. **Set permissions (if using file storage):**
   ```
   chmod 755 storage/
   chmod 755 logs/
   ```

### Docker Setup (Alternative)

```
docker-compose up -d
# Access at http://localhost:8080
```

---

## Development Workflow

### Starting the Development Server

**Local Apache/PHP:**
```
# Ensure Apache is running with mod_headers enabled
php -S localhost:8000 -t public/
```

**Docker:**
```
docker-compose up
# Logs available at ./logs/
```

**WebSocket Server (Optional):**
```
composer run websocket
# Runs on port specified in .env (default 8080)
```

### File Structure

```
/
├── chat-unified.php          # Main HTTP entrypoint for chat requests
├── admin-api.php             # RESTful API for agent management
├── config.php                # Central configuration hub
├── includes/
│   ├── ChatHandler.php       # Core orchestration logic
│   ├── OpenAIClient.php      # OpenAI API transport layer
│   └── SessionManager.php    # Conversation persistence
├── public/
│   ├── admin/                # Admin UI (HTML/JS/CSS)
│   └── chatbot-enhanced.js   # Frontend widget
├── websocket-server.php      # Optional Ratchet WebSocket relay
├── storage/                  # JSON conversation files (if file storage)
├── logs/                     # Application logs
└── docs/                     # Comprehensive documentation
```

### Key Development Scripts

```
# Install/update dependencies
composer install

# Start WebSocket server
composer run websocket

# Run Docker in development mode
docker-compose up --build

# View logs
tail -f logs/app.log
```

---

## Build & Deployment

### Production Build

1. **Optimize Composer dependencies:**
   ```
   composer install --no-dev --optimize-autoloader
   ```

2. **Configure production environment:**
   ```
   # .env for production
   OPENAI_API_KEY=sk-prod-...
   ADMIN_API_KEY=complex-secure-token
   STORAGE_TYPE=file
   RATE_LIMIT_REQUESTS=20
   RATE_LIMIT_WINDOW=60
   ```

3. **Apache configuration:**
   - Enable `mod_headers` and `mod_rewrite`
   - Disable PHP output buffering for SSE
   - Set proper permissions on storage/ and logs/

### Docker Production Deployment

```
# Build production image
docker build -t chatbot-backend:latest .

# Run with production env
docker run -d \
  --env-file .env.production \
  -p 80:80 \
  -v $(pwd)/storage:/var/www/html/storage \
  -v $(pwd)/logs:/var/www/html/logs \
  chatbot-backend:latest
```

### CI/CD Considerations

- Keep `.env` out of version control (use secrets management)
- Validate environment variables on startup
- Monitor logs/ directory for errors
- Implement health check endpoint for load balancers
- Document any new config keys in `.env.example`

---

## Testing Instructions

### Manual Testing

1. **Test Chat Completions Agent:**
   ```
   curl -X POST http://localhost/chat-unified.php \
     -H "Content-Type: application/json" \
     -d '{
       "message": "Hello",
       "api_type": "chat",
       "conversation_id": "test-123"
     }'
   ```

2. **Test Responses Agent:**
   ```
   curl -X POST http://localhost/chat-unified.php \
     -H "Content-Type: application/json" \
     -d '{
       "message": "Search knowledge base",
       "api_type": "responses",
       "conversation_id": "test-456",
       "prompt_id": "prompt_xyz"
     }'
   ```

3. **Test Streaming (SSE):**
   ```
   curl -N http://localhost/chat-unified.php \
     -H "Accept: text/event-stream" \
     -d "message=Test&api_type=chat&conversation_id=test-789"
   ```

4. **Test Admin API:**
   ```
   # List agents
   curl http://localhost/admin-api.php?action=list_agents \
     -H "Authorization: Bearer YOUR_ADMIN_API_KEY"
   
   # Create agent
   curl -X POST http://localhost/admin-api.php?action=create_agent \
     -H "Authorization: Bearer YOUR_ADMIN_API_KEY" \
     -H "Content-Type: application/json" \
     -d '{
       "name": "Test Agent",
       "api_type": "chat",
       "model": "gpt-4o-mini"
     }'
   ```

### Validation Checklist

Before submitting changes:
- [ ] All API endpoints return proper JSON or SSE formats
- [ ] Rate limiting works correctly (test exceeding limits)
- [ ] File uploads validate size and type restrictions
- [ ] Conversation history persists and trims correctly
- [ ] WebSocket fallback works when SSE unavailable
- [ ] Admin UI CRUD operations succeed
- [ ] Error messages are logged to logs/app.log
- [ ] No secrets exposed in responses or logs

### Known Test Scenarios

- **File uploads:** Max 10MB per file, must be image/document types
- **Rate limiting:** Default 10 requests/60 seconds per IP
- **Message length:** Max 4000 characters (configurable)
- **History depth:** Max 50 messages per conversation (auto-trimmed)

---

## Coding Style & Conventions

### PHP Standards

- **PSR-12 coding style** for all PHP files
- Use type hints for function parameters and return types
- Document all public methods with PHPDoc blocks
- Namespace classes under appropriate directories
- Always sanitize user inputs before processing

**Example:**
```php
/**
 * Handle streaming chat completion request
 * @param array $messages Conversation history
 * @param callable $callback Stream event handler
 * @return void
 */
public function streamChatCompletion(array $messages, callable $callback): void
{
    // Implementation
}
```

### JavaScript Standards

- **ES6+ syntax** with clear variable naming
- Use `const`/`let`, avoid `var`
- Async/await for promises (no nested callbacks)
- Document complex functions with JSDoc
- Handle all error cases with try/catch

### File Naming

- PHP classes: PascalCase (e.g., `ChatHandler.php`)
- JavaScript: kebab-case (e.g., `chatbot-enhanced.js`)
- Config files: lowercase with dots (e.g., `.env.example`)
- Documentation: UPPERCASE or kebab-case (e.g., `README.md`, `api-guide.md`)

### Commit Conventions

Use **Conventional Commits** format:
```
<type>(<scope>): <description>

[optional body]
[optional footer]
```

**Types:**
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation only
- `refactor`: Code refactoring
- `test`: Adding tests
- `chore`: Maintenance tasks

**Examples:**
```
feat(agents): add vector store support to responses API
fix(streaming): resolve SSE connection timeout on slow networks
docs(readme): update docker setup instructions
refactor(chat): extract validation logic to separate method
```

---

## Pull Request Guidelines

### Before Opening a PR

1. **Test all affected endpoints:**
   - Run manual curl tests for modified APIs
   - Verify streaming works for both SSE and WebSocket
   - Test file uploads if storage logic changed
   - Validate admin UI still functions correctly

2. **Code quality checks:**
   - Review your own diff for debugging code, console.logs, or TODOs
   - Ensure all new functions have PHPDoc/JSDoc comments
   - Check that error handling covers edge cases
   - Validate that config.php defaults match .env.example

3. **Update documentation:**
   - Modify `docs/api.md` if API contracts changed
   - Update `README.md` if setup steps affected
   - Add entries to `CHANGELOG.md` for user-facing changes
   - Update `AGENTS.md` (this file) if architecture changed

### PR Title Format

```
[Component] Brief description of change

Examples:
[API] Add support for GPT-4 Turbo model
[Admin] Implement agent deletion endpoint
[WebSocket] Fix memory leak in connection handler
[Docs] Add deployment guide for AWS
```

### PR Description Template

```
## What does this PR do?
Brief summary of changes

## Type of change
- [ ] Bug fix
- [ ] New feature
- [ ] Breaking change
- [ ] Documentation update

## Testing
- [ ] Manual testing completed
- [ ] Admin UI tested
- [ ] Streaming endpoints verified
- [ ] File uploads validated

## Checklist
- [ ] Code follows project conventions
- [ ] Documentation updated
- [ ] No secrets or keys in code
- [ ] Error handling implemented
- [ ] Logs reviewed for issues

## Related Issues
Closes #123
```

### Review Requirements

- **At least one human reviewer** must approve before merge
- Address all review comments or explain why changes aren't needed
- Keep PRs focused (< 400 lines when possible)
- Split large features into multiple PRs with clear dependencies

---

## Security & Compliance

### Critical Security Rules

1. **Never commit secrets:**
   - API keys belong in `.env` only
   - Use `.gitignore` to exclude sensitive files
   - Rotate keys immediately if accidentally committed

2. **Input validation:**
   - Validate all user inputs in `ChatHandler.php`
   - Enforce message length limits
   - Validate file types/sizes before upload
   - Sanitize conversation_id to prevent path traversal

3. **Authentication:**
   - Admin API requires Bearer token in Authorization header
   - Token must be cryptographically random (min 32 chars)
   - Store admin token in `.env`, never hardcode

4. **Rate limiting:**
   - Enforce per-IP rate limits (default: 10 req/60s)
   - Adjust limits in `config.php` based on use case
   - Log rate limit violations for monitoring

5. **Error handling:**
   - Never expose stack traces to clients
   - Log detailed errors to `logs/app.log` only
   - Return generic error messages in API responses
   - Sanitize all error output before sending

### File Upload Security

- **Allowed types:** Images (jpg, png, gif), documents (pdf, txt, docx)
- **Size limit:** 10MB per file (configurable)
- **Validation:** Verify MIME type and file extension
- **Storage:** Files uploaded to OpenAI, not stored locally

### Audit & Compliance

- Review `composer.json` dependencies quarterly for vulnerabilities
- Keep PHP and OpenAI SDK versions updated
- Monitor `logs/app.log` for suspicious patterns
- Implement log rotation to prevent disk exhaustion
- Document all changes to authentication/authorization logic

---

## Known Issues & Gotchas

### Current Limitations

1. **EventSource (SSE) cannot POST with files:**
   - Widget automatically falls back to AJAX when files present
   - WebSocket is preferred transport for file uploads

2. **PHP session storage limitations:**
   - Sessions may expire on server restart
   - Use `file` storage type for persistence across restarts
   - Configure session garbage collection in php.ini

3. **WebSocket requires Ratchet:**
   - Must run `composer install` with dev dependencies
   - WebSocket server is separate process (must be started manually)
   - Not suitable for serverless deployments

4. **Rate limiting uses /tmp directory:**
   - May not persist across container restarts
   - Consider Redis/Memcached for production deployments

5. **Message history trimming:**
   - Conversations auto-trim to last 50 messages
   - Older messages lost permanently (no archive)
   - Adjust `max_messages` in config.php if needed

### Common Troubleshooting

**SSE not working:**
- Verify Apache `mod_headers` enabled
- Check PHP output buffering disabled (`output_buffering=Off`)
- Inspect browser console for connection errors

**File uploads failing:**
- Check file size < 10MB
- Verify file type in allowed list
- Ensure OpenAI API key has file upload permissions

**Admin UI not loading:**
- Validate `ADMIN_API_KEY` set in `.env`
- Check browser console for CORS errors
- Verify admin-api.php returns valid JSON

**WebSocket connection refused:**
- Ensure WebSocket server started (`composer run websocket`)
- Check `WEBSOCKET_ENABLED=true` and correct port
- Verify firewall allows connections on WebSocket port

### Reporting Issues

When opening issues:
1. Include PHP version, OS, and deployment method (Docker/Apache)
2. Provide relevant log excerpts from `logs/app.log`
3. Share curl command or API request that reproduces issue
4. Specify agent configuration (api_type, model, tools)
5. Include browser console errors for frontend issues

---

## Extra Agent Instructions

### Agent Architecture Specifics

**Two Agent Modes:**

1. **Chat Completions Agent (`api_type=chat`):**
   - Uses `/v1/chat/completions` endpoint
   - Loads conversation history from storage
   - Prepends system message to new conversations
   - Streams via SSE `message` events (`start`/`chunk`/`done`)
   - Configuration in `config.php` under `chat` section

2. **Responses Agent (`api_type=responses`):**
   - Uses `/v1/responses` endpoint with prompt references
   - Supports tool calling (file_search, etc.)
   - Auto-applies vector store defaults
   - Handles `required_action` for tool outputs
   - Emits `tool_call` SSE events with status
   - Configuration in `config.php` under `responses` section

### Creating Custom Agents

**Via Admin UI:**
1. Navigate to `/public/admin/`
2. Authenticate with admin token
3. Click "Create Agent" and configure:
   - Name, description, API type
   - Model (gpt-4o, gpt-4o-mini, etc.)
   - Temperature (0-2)
   - Tools (file_search, etc.)
   - Vector store IDs for knowledge bases
   - System message or prompt ID
4. Set as default if desired

**Via Admin API:**
```
curl -X POST "http://localhost/admin-api.php?action=create_agent" \
  -H "Authorization: Bearer ${ADMIN_API_KEY}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Knowledge Base Agent",
    "description": "Searches company knowledge base",
    "api_type": "responses",
    "model": "gpt-4o-mini",
    "temperature": 0.7,
    "tools": [{"type": "file_search"}],
    "vector_store_ids": ["vs_abc123"],
    "prompt_id": "prompt_xyz789",
    "is_default": false
  }'
```

### Frontend Integration Contract

**Request Format (chatbot-enhanced.js → backend):**
```javascript
{
  message: "User query",
  conversation_id: "uuid-v4",
  api_type: "chat" | "responses",
  
  // Responses-specific (optional):
  prompt_id: "prompt_xxx",
  model: "gpt-4o",
  temperature: 0.8,
  tools: [{type: "file_search"}],
  vector_store_ids: ["vs_yyy"],
  
  // File uploads (optional):
  file_data: [{
    name: "document.pdf",
    type: "application/pdf",
    data: "base64-encoded-content"
  }]
}
```

**SSE Event Types (backend → frontend):**
```javascript
// Stream start
{type: "start", response_id: "resp_xxx"}

// Text chunks
{type: "chunk", content: "partial text", delta: "new text"}

// Tool execution
{
  type: "tool_call",
  tool_name: "file_search",
  call_id: "call_xxx",
  arguments: {...},
  status: "in_progress" | "completed"
}

// System notices
{type: "notice", content: "Processing..."}

// Stream complete
{type: "done", full_content: "complete response"}

// Errors
{type: "error", error: "Error message"}
```

### Extending the System

**Adding New Agent Types:**
1. Create handler method in `includes/ChatHandler.php`
2. Add route in `chat-unified.php` for new API type
3. Extend `OpenAIClient.php` if transport differs
4. Update frontend in `chatbot-enhanced.js` to handle new event types
5. Document new API contract in `docs/api.md`

**Adding New Tools:**
1. Update tool merge logic in `ChatHandler::handleResponsesRequest()`
2. Add tool-specific validation if needed
3. Handle tool output submission in streaming loop
4. Update admin UI to expose new tool options
5. Document tool behavior and requirements

**Modifying Conversation Storage:**
1. Extend or replace `SessionManager.php`
2. Maintain compatibility with trim/load/save interface
3. Update `config.php` storage section
4. Test migration path from existing storage
5. Document any schema changes

---

## Reference Documentation

For detailed information, consult:

- **[README.md](README.md)** – Project overview, features, quick start
- **[docs/api.md](docs/api.md)** – Complete API reference with examples
- **[docs/deployment.md](docs/deployment.md)** – Production deployment guide
- **[docs/customization-guide.md](docs/customization-guide.md)** – Agent customization and configuration
- **[docs/GUIA_CRIACAO_AGENTES.md](docs/GUIA_CRIACAO_AGENTES.md)** – Step-by-step agent creation guide (Portuguese)
- **[docs/PHASE1_DB_AGENT.md](docs/PHASE1_DB_AGENT.md)** – Database schema and admin API documentation

### Quick Reference Links

- OpenAI API Documentation: https://platform.openai.com/docs
- Ratchet WebSocket Library: http://socketo.me
- SSE Specification: https://html.spec.whatwg.org/multipage/server-sent-events.html
- Conventional Commits: https://www.conventionalcommits.org

---

## Maintenance & Updates

**Keep these synchronized when making changes:**
- `.env.example` ↔ `config.php` (all config keys documented)
- `ChatHandler.php` ↔ `chatbot-enhanced.js` (SSE event types)
- `admin-api.php` ↔ Admin UI (API contract)
- `docs/api.md` ↔ Actual endpoint behavior
- `composer.json` ↔ `Dockerfile` (dependency versions)

**Regular maintenance tasks:**
- Review and rotate admin API tokens quarterly
- Update OpenAI SDK when new features released
- Monitor PHP/Apache security advisories
- Clean up old conversation files if using file storage
- Archive and rotate logs to prevent disk exhaustion

---

## Contact & Support

For questions, issues, or contributions:
- **Repository:** https://github.com/suporterfid/gpt-chatbot-boilerplate
- **Issues:** Open detailed issue reports on GitHub
- **Pull Requests:** Follow PR guidelines above
- **Documentation:** Refer to docs/ directory first

**When seeking help, always include:**
- PHP version and deployment environment
- Relevant log excerpts from `logs/app.log`
- Agent configuration (api_type, model, tools)
- Steps to reproduce the issue
- Expected vs actual behavior
