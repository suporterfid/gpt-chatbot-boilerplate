# WordPress Content Manager Agent

A specialized agent for managing WordPress content through the WordPress REST API.

## Features

- ✅ **Create Posts** - Generate and publish blog posts
- ✅ **Update Posts** - Modify existing content
- ✅ **Search Posts** - Find posts by keywords
- ✅ **Query Posts** - Retrieve specific posts by ID
- ✅ **Manage Categories** - List and organize categories
- ✅ **Manage Tags** - Add and organize tags
- ✅ **LLM Integration** - Uses LLM to generate content when needed
- ✅ **Intent Detection** - Automatically detects user intentions
- ✅ **Custom Tools** - LLM can call WordPress-specific functions

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
2. Uses LLM to generate content
3. Calls `create_wordpress_post` tool
4. Creates draft post in WordPress
5. Returns post URL and ID

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

The WordPress agent provides these custom tools that the LLM can call:

### `create_wordpress_post`

Creates a new WordPress post.

**Parameters:**
- `title` (string, required) - Post title
- `content` (string, required) - Post content (HTML allowed)
- `excerpt` (string, optional) - Post excerpt
- `status` (string, optional) - Post status: `draft`, `publish`, `pending`, `private`
- `categories` (array, optional) - Category slugs
- `tags` (array, optional) - Tag names

**Example:**
```json
{
  "title": "My New Post",
  "content": "<p>This is the post content.</p>",
  "status": "publish",
  "categories": ["technology", "ai"],
  "tags": ["machine-learning", "chatbots"]
}
```

### `update_wordpress_post`

Updates an existing post.

**Parameters:**
- `post_id` (integer, required) - Post ID to update
- `title` (string, optional) - New title
- `content` (string, optional) - New content
- `status` (string, optional) - New status

### `search_wordpress_posts`

Searches for posts.

**Parameters:**
- `search` (string, required) - Search query
- `per_page` (integer, optional) - Results per page (max 100)
- `status` (string, optional) - Filter by status

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
