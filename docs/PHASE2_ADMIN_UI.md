# Phase 2: Admin UI, Prompts & Vector Store Management

## Overview

Phase 2 extends the GPT Chatbot Boilerplate with a comprehensive Admin UI that enables non-technical users to manage Agents, Prompts, and Vector Stores without touching code.

## Features

### 1. Database Schema

Phase 2 adds the following tables:

- **`prompts`** - Stores prompt definitions and OpenAI prompt references
- **`prompt_versions`** - Tracks versions of prompts
- **`vector_stores`** - Manages vector store metadata
- **`vector_store_files`** - Tracks files uploaded to vector stores
- **`audit_log`** - Records all admin operations for security and compliance

### 2. OpenAI Admin Client

`OpenAIAdminClient.php` provides a robust interface to OpenAI's admin APIs:

**Prompts API**
- List prompts
- Get prompt details
- Create prompts
- Create prompt versions
- Delete prompts

**Vector Stores API**
- List vector stores
- Get vector store details
- Create vector stores
- Delete vector stores
- List files in a vector store
- Add files to vector stores
- Remove files from vector stores
- Get file ingestion status

**Files API**
- List files
- Upload files (from base64 or file path)
- Delete files
- Get file details

All methods include:
- Robust error handling with graceful degradation
- Request/response logging
- Support for paginated results
- Automatic retry logic for transient failures

### 3. Services Layer

**PromptService** (`includes/PromptService.php`)
- CRUD operations for prompts
- Sync prompts from OpenAI
- Manage prompt versions
- Store local copies of prompt content

**VectorStoreService** (`includes/VectorStoreService.php`)
- CRUD operations for vector stores
- File upload and management
- Ingestion status polling
- Sync vector stores from OpenAI

Both services:
- Persist OpenAI IDs in the database
- Support local-only records (when OpenAI API unavailable)
- Provide normalized data structures
- Handle errors gracefully

### 4. Admin API Endpoints

All endpoints require `Authorization: Bearer <ADMIN_TOKEN>` header.

**Agents**
- `GET /admin-api.php?action=list_agents` - List all agents
- `GET /admin-api.php?action=get_agent&id={id}` - Get agent details
- `POST /admin-api.php?action=create_agent` - Create new agent
- `POST /admin-api.php?action=update_agent&id={id}` - Update agent
- `POST /admin-api.php?action=delete_agent&id={id}` - Delete agent
- `POST /admin-api.php?action=make_default&id={id}` - Set default agent
- `POST /admin-api.php?action=test_agent&id={id}` - Test agent (SSE streaming)

**Prompts**
- `GET /admin-api.php?action=list_prompts` - List all prompts
- `GET /admin-api.php?action=get_prompt&id={id}` - Get prompt details
- `POST /admin-api.php?action=create_prompt` - Create new prompt
- `POST /admin-api.php?action=update_prompt&id={id}` - Update prompt
- `POST /admin-api.php?action=delete_prompt&id={id}` - Delete prompt
- `GET /admin-api.php?action=list_prompt_versions&id={id}` - List versions
- `POST /admin-api.php?action=create_prompt_version&id={id}` - Create version
- `POST /admin-api.php?action=sync_prompts` - Sync from OpenAI

**Vector Stores**
- `GET /admin-api.php?action=list_vector_stores` - List all stores
- `GET /admin-api.php?action=get_vector_store&id={id}` - Get store details
- `POST /admin-api.php?action=create_vector_store` - Create new store
- `POST /admin-api.php?action=update_vector_store&id={id}` - Update store
- `POST /admin-api.php?action=delete_vector_store&id={id}` - Delete store
- `GET /admin-api.php?action=list_vector_store_files&id={id}` - List files
- `POST /admin-api.php?action=add_vector_store_file&id={id}` - Upload file
- `POST /admin-api.php?action=delete_vector_store_file&id={id}&file_id={fid}` - Delete file
- `GET /admin-api.php?action=poll_file_status&file_id={id}` - Poll status
- `POST /admin-api.php?action=sync_vector_stores` - Sync from OpenAI

**Utility**
- `GET /admin-api.php?action=health` - System health check

### 5. Admin UI

The Admin UI is a single-page application located at `/public/admin/index.html`.

**Features:**
- Token-based authentication (uses localStorage)
- Responsive design
- Real-time updates
- Toast notifications
- Modal dialogs for forms
- SSE streaming for agent tests

**Pages:**

1. **Agents** - Create, edit, delete, and test agents
   - View all agents in a table
   - Create new agents with full configuration
   - Test agents with streaming responses
   - Set default agent

2. **Prompts** - Manage OpenAI prompts
   - Create prompts (stored on OpenAI if available)
   - View prompt versions
   - Sync existing prompts from OpenAI
   - Delete prompts

3. **Vector Stores** - Manage vector stores and files
   - Create vector stores
   - Upload files to stores
   - Monitor ingestion status
   - Delete stores and files
   - Sync existing stores from OpenAI

4. **Settings** - System configuration and health
   - View system health status
   - Database connection status
   - OpenAI API status
   - Clear admin token

## Setup

### 1. Database Migration

Migrations run automatically when the admin API is accessed. To run manually:

```bash
php -r "
require_once 'includes/DB.php';
\$db = new DB(['database_path' => 'data/chatbot.db']);
\$db->runMigrations(__DIR__ . '/db/migrations');
"
```

### 2. Environment Configuration

Add to your `.env` file:

```bash
# Admin Configuration (already present from Phase 1)
ADMIN_ENABLED=true
ADMIN_TOKEN=your_random_admin_token_here_min_32_chars
DATABASE_PATH=./data/chatbot.db
```

Generate a secure admin token:

```bash
openssl rand -hex 32
```

### 3. Access Admin UI

1. Navigate to `http://your-domain/public/admin/`
2. Enter your admin token when prompted
3. Token is stored in localStorage for convenience

### 4. Web Server Configuration

**Apache** - Add to your `.htaccess` or VirtualHost:

```apache
# Serve Admin UI
<Directory "/var/www/html/public/admin">
    Options Indexes FollowSymLinks
    AllowOverride None
    Require all granted
    
    # SPA routing
    RewriteEngine On
    RewriteBase /public/admin/
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule . /public/admin/index.html [L]
</Directory>
```

**Nginx** - Add to your server block:

```nginx
# Serve Admin UI
location /public/admin/ {
    alias /var/www/html/public/admin/;
    try_files $uri $uri/ /public/admin/index.html;
}
```

## Usage Examples

### Create a Prompt

```bash
curl -X POST "http://localhost/admin-api.php?action=create_prompt" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Customer Support Assistant",
    "description": "Helpful customer support prompt",
    "content": "You are a helpful customer support assistant. Be friendly and professional."
  }'
```

### Create a Vector Store

```bash
curl -X POST "http://localhost/admin-api.php?action=create_vector_store" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Knowledge Base"
  }'
```

### Upload File to Vector Store

```bash
# First, base64 encode your file
FILE_CONTENT=$(base64 -w 0 document.txt)

curl -X POST "http://localhost/admin-api.php?action=add_vector_store_file&id=STORE_ID" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d "{
    \"name\": \"document.txt\",
    \"file_data\": \"$FILE_CONTENT\",
    \"size\": $(wc -c < document.txt),
    \"mime_type\": \"text/plain\"
  }"
```

### Create Agent with Prompt and Vector Store

```bash
curl -X POST "http://localhost/admin-api.php?action=create_agent" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Support Agent",
    "api_type": "responses",
    "prompt_id": "pmpt_abc123",
    "model": "gpt-4o",
    "tools": [{"type": "file_search"}],
    "vector_store_ids": ["vs_xyz789"],
    "is_default": true
  }'
```

### Test an Agent

The test endpoint streams responses via SSE:

```javascript
const eventSource = new EventSource(
  'http://localhost/admin-api.php?action=test_agent&id=AGENT_ID',
  {
    headers: {
      'Authorization': 'Bearer YOUR_TOKEN'
    }
  }
);

eventSource.addEventListener('message', (event) => {
  const data = JSON.parse(event.data);
  
  if (data.type === 'start') {
    console.log('Test started for agent:', data.agent_name);
  } else if (data.type === 'chunk') {
    console.log('Response chunk:', data.content);
  } else if (data.type === 'done') {
    console.log('Test complete');
    eventSource.close();
  }
});
```

## Security

### Authentication

All admin API endpoints require a valid `Authorization: Bearer <token>` header. The token must match the `ADMIN_TOKEN` environment variable.

### Token Management

- Generate strong tokens (32+ characters)
- Rotate tokens periodically
- Never commit tokens to version control
- Store tokens securely (environment variables or secrets manager)

### Input Validation

All user inputs are validated:
- Agent names must be unique
- File sizes and types are validated
- Temperature and top_p ranges are enforced
- Required fields are checked

### Audit Logging

All admin operations are logged to the `audit_log` table with:
- Actor (admin token fingerprint)
- Action performed
- Resource type and ID
- Timestamp
- IP address

### Rate Limiting

The existing rate limiting from Phase 1 applies to admin endpoints, preventing abuse.

## Troubleshooting

### Admin UI won't load

1. Check web server configuration
2. Verify `public/admin/` directory exists and is accessible
3. Check browser console for JavaScript errors

### "Invalid admin token" error

1. Verify `ADMIN_TOKEN` is set in `.env`
2. Check that token in localStorage matches `.env` value
3. Clear browser localStorage and re-enter token

### OpenAI API errors

1. Check `OPENAI_API_KEY` is valid
2. Verify your OpenAI account has access to Prompts/Vector Stores APIs
3. Check API quotas and rate limits

### File upload failures

1. Verify file size is within limits
2. Check file type is allowed
3. Ensure sufficient disk space
4. Check OpenAI file upload quotas

### Ingestion stuck in "in_progress"

1. Use the poll file status endpoint to refresh
2. Check OpenAI dashboard for ingestion errors
3. Large files may take several minutes

## Migration from Phase 1

Phase 1 agents continue to work without changes. To take advantage of Phase 2:

1. Run new migrations (automatic on first admin API access)
2. Optionally migrate existing config.php settings to database agents
3. Use Admin UI to create new agents going forward

## API Reference

### Response Format

**Success:**
```json
{
  "data": {
    "id": "uuid",
    "name": "Resource Name",
    ...
  }
}
```

**Error:**
```json
{
  "error": {
    "message": "Error description",
    "code": "ERROR_CODE",
    "status": 400
  }
}
```

### Common HTTP Status Codes

- `200 OK` - Success
- `201 Created` - Resource created
- `400 Bad Request` - Invalid input
- `403 Forbidden` - Invalid/missing admin token
- `404 Not Found` - Resource not found
- `500 Internal Server Error` - Server error

## Best Practices

1. **Prompts** - Version your prompts as you iterate
2. **Vector Stores** - Organize files by topic/domain
3. **Agents** - Test agents before setting as default
4. **Security** - Rotate admin tokens regularly
5. **Backups** - Backup `data/chatbot.db` regularly
6. **Monitoring** - Use health endpoint for monitoring

## Limitations

1. **OpenAI API Availability** - Some features require OpenAI API access to Prompts/Vector Stores. Graceful fallbacks are provided.
2. **File Size** - Limited by OpenAI and server configuration
3. **Ingestion** - File ingestion is synchronous; large files may timeout
4. **Single Admin** - Phase 2 uses a single admin token; multi-user support planned for Phase 3

## Future Enhancements (Phase 3)

- Multi-user admin accounts with roles
- Background job processing for file ingestion
- Webhook support for OpenAI ingestion callbacks
- Advanced prompt editor with syntax highlighting
- Analytics dashboard for agent usage
- Conversation history viewer
- Export/import functionality for agents and prompts

## Support

For issues or questions:
1. Check this documentation
2. Review the code comments
3. Examine browser console and server logs
4. Create a GitHub issue with details

## License

Same as main project license.
