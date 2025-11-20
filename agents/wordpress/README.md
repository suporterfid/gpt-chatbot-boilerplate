# WordPress Content Manager Agent

A specialized agent for managing WordPress content through the WordPress REST API.

## Features

- ✅ **Automation-Ready Workflow** - Queue article briefs and trigger generation phases (outline, writing, assets, assembly, publish)
- ✅ **Execution Visibility** - Fetch execution logs and status snapshots for any queued article
- ✅ **Queue & Brief Management** - Update queued briefs, priorities, and manual required actions
- ✅ **Internal Link Intelligence** - Surface internal links per configuration for SEO-friendly drafts
- ✅ **LLM Integration** - Uses LLM for creative steps while skipping admin operations
- ✅ **Intent Detection** - Automatically detects workflow directives and creative requests
- ✅ **Custom Tools** - LLM can call workflow-focused functions with normalized responses

## Prerequisites

### WordPress Setup

1. **WordPress 5.6+** with REST API enabled (enabled by default)
2. **Application Password** for authentication:
   - Go to **Users → Profile** in WordPress admin
   - Scroll to **Application Passwords**
   - Enter a name (e.g., "Chatbot Agent")
   - Click **Add New Application Password**
   - **Copy the generated password** (format: `xxxx xxxx xxxx xxxx xxxx xxxx`)

3. **User Permissions**:
   - User must have `edit_posts` and `publish_posts` capabilities
   - Typically **Author**, **Editor**, or **Administrator** role

### Security

- Always use HTTPS for WordPress site
- Store Application Password securely (use environment variables)
- Never commit credentials to version control
- Regularly rotate Application Passwords

## Configuration

### 1. Create Agent

```bash
POST /admin-api.php?action=create_agent
```

```json
{
  "name": "WordPress Blog Manager",
  "agent_type": "wordpress",
  "description": "Manages WordPress blog content",
  "system_message": "You are a WordPress content manager assistant. You help users create and manage blog posts.",
  "model": "gpt-4o",
  "api_type": "responses"
}
```

### 2. Configure WordPress Settings

```bash
POST /admin-api.php?action=configure_specialized_agent
```

```json
{
  "agent_id": "agent-abc123",
  "agent_type": "wordpress",
  "config": {
    "wp_site_url": "https://yourblog.com",
    "wp_username": "your-username",
    "wp_app_password": "${WP_APP_PASSWORD}",
    "default_status": "draft",
    "auto_publish": false,
    "default_category": "blog",
    "default_author_id": 1,
    "max_posts_per_request": 10
  }
}
```

### 3. Set Environment Variable

```bash
# .env or environment
WP_APP_PASSWORD="xxxx xxxx xxxx xxxx xxxx xxxx"
```

## Usage Examples

### Example 1: Create a Blog Post

**User:**
> Create a blog post about "Top 10 AI Tools for 2024"

**Agent Response:**
> I've created a draft blog post titled "Top 10 AI Tools for 2024". The post includes an introduction to AI tools and their impact on productivity. Would you like me to publish it or make any changes?

**Behind the Scenes:**
1. Agent detects "create post" intent
2. Queues the request with `queue_article_request` including the brief and priorities
3. Triggers `run_generation_phase` for outline and writing steps
4. Publishes or leaves the post as draft based on configuration
5. Returns queue ID, article ID, and log URL for monitoring

### Example 2: Search Posts

**User:**
> Find all posts about machine learning

**Agent Response:**
> I found 5 posts about machine learning:
>
> • **Introduction to Machine Learning**
>   A beginner's guide to machine learning concepts and algorithms...
>   [View Post](https://yourblog.com/intro-ml)
>
> • **Machine Learning Best Practices**
>   Learn the best practices for implementing ML in production...
>   [View Post](https://yourblog.com/ml-best-practices)
>
> ...

### Example 3: Update a Post

**User:**
> Update post #123 to add a section about neural networks

**Agent Response:**
> I've updated post #123 to include a new section about neural networks covering the basics of artificial neural networks and their applications.

## Custom Tools

The WordPress agent exposes workflow-focused tools with structured payloads and normalized responses for SSE streaming:

### `queue_article_request`
- **Purpose:** Queue a new or refreshed article request tied to a blog configuration.
- **Required:** `configuration_id`, `seed_keyword`
- **Optional:** `target_audience`, `language`, `priority`, `schedule_at`, `metadata`, `queue_id`, `article_id`
- **Side Effects:** Creates/updates queue entries and returns execution log pointers.

### `update_article_brief`
- **Purpose:** Patch the queued article brief (topic, keywords, CTA, tone, language).
- **Required:** `queue_id`, `article_id`, `updates`
- **Side Effects:** Brief is updated and execution log context is refreshed.

### `run_generation_phase`
- **Purpose:** Trigger a workflow phase: `queue`, `structure`, `writing`, `assets`, `assembly`, `publish`, or `monitor`.
- **Required:** `queue_id`, `article_id`, `phase`
- **Optional:** `force`, `options`
- **Side Effects:** Advances queue status and logs the request.

### `submit_required_action_output`
- **Purpose:** Submit output for a manual requirement (client approval, CTA change, etc.).
- **Required:** `queue_id`, `article_id`, `action_name`, `payload`
- **Optional:** `notes`
- **Side Effects:** Unblocks or resumes the workflow.

### `fetch_execution_log`
- **Purpose:** Retrieve execution log pointers and last-known status.
- **Required:** `queue_id` **or** `article_id`
- **Optional:** `phase`
- **Side Effects:** Read-only.

### `list_internal_links`
- **Purpose:** Surface internal links for SEO-aware drafting.
- **Required:** `configuration_id`
- **Optional:** `match_keywords`, `limit`
- **Side Effects:** Read-only.

## Intent Detection

The agent automatically detects user intentions:

| Intent | Patterns | Example |
|--------|----------|---------|
| Create Post | "create", "write", "make", "publish" + "post" | "Create a post about AI" |
| Update Post | "update", "edit", "modify" + "post" | "Update post 123" |
| Search Posts | "search", "find", "look for" + "post" | "Find posts about ML" |
| Get Post | "get", "show", "display" + "post" | "Show me post 456" |
| List Categories | "list", "show" + "categories" | "List all categories" |

## Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `wp_site_url` | string | (required) | WordPress site URL |
| `wp_username` | string | (required) | WordPress username |
| `wp_app_password` | string | (required) | Application password |
| `default_status` | string | `"draft"` | Default post status |
| `default_category` | string | `"uncategorized"` | Default category slug |
| `default_author_id` | integer | `1` | Default author ID |
| `auto_publish` | boolean | `false` | Auto-publish without review |
| `post_type` | string | `"post"` | Post type (post/page) |
| `api_timeout_ms` | integer | `30000` | API timeout (ms) |
| `max_posts_per_request` | integer | `10` | Max search results |

## API Reference

### WordPress REST API Endpoints Used

- `POST /wp-json/wp/v2/posts` - Create post
- `POST /wp-json/wp/v2/posts/{id}` - Update post
- `GET /wp-json/wp/v2/posts` - List/search posts
- `GET /wp-json/wp/v2/posts/{id}` - Get specific post
- `DELETE /wp-json/wp/v2/posts/{id}` - Delete post
- `GET /wp-json/wp/v2/categories` - List categories
- `GET /wp-json/wp/v2/tags` - List tags

## Troubleshooting

### Authentication Errors

**Error:** `401 Unauthorized`

**Solution:**
- Verify Application Password is correct
- Check username matches WordPress user
- Ensure user has required permissions
- Confirm Application Passwords are enabled in WordPress

### Connection Errors

**Error:** `cURL error: Could not resolve host`

**Solution:**
- Verify `wp_site_url` is correct
- Ensure WordPress site is accessible
- Check DNS resolution
- Verify SSL certificate is valid (use HTTPS)

### Permission Errors

**Error:** `403 Forbidden - Sorry, you are not allowed to create posts`

**Solution:**
- Check user role has `edit_posts` capability
- Verify user is not restricted
- Confirm REST API is enabled

### Posts Not Created

**Solution:**
- Check `default_status` - may be set to `draft`
- Verify `auto_publish` setting
- Check WordPress spam/moderation settings
- Review WordPress error logs

## Security Best Practices

1. **Use Environment Variables**
   ```json
   {
     "wp_app_password": "${WP_APP_PASSWORD}"
   }
   ```

2. **Least Privilege**
   - Create dedicated WordPress user for the agent
   - Grant minimum required permissions
   - Use **Author** role if possible (not Administrator)

3. **HTTPS Only**
   - Always use HTTPS for WordPress site
   - Validate SSL certificates

4. **Rotate Passwords**
   - Regularly rotate Application Passwords
   - Revoke unused passwords

5. **Monitor Activity**
   - Enable WordPress audit logging
   - Monitor agent-created content
   - Set up alerts for unusual activity

## Limitations

- Maximum 100 posts per search request
- File uploads not yet implemented
- Custom post types limited to 'post' and 'page'
- No support for custom fields (coming soon)
- No media management yet

## Roadmap

- [ ] Custom post type support
- [ ] Featured image handling
- [ ] Media library integration
- [ ] Custom field management
- [ ] Bulk operations
- [ ] Post scheduling
- [ ] Revision management

## Support

- [WordPress REST API Documentation](https://developer.wordpress.org/rest-api/)
- [Application Passwords Guide](https://make.wordpress.org/core/2020/11/05/application-passwords-integration-guide/)
- [Main Agent Documentation](../../SPECIALIZED_AGENTS_SPECIFICATION.md)

## License

MIT License - See main project LICENSE file
