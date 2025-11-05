# Authorization Header Fix for Admin API

## Problem
When accessing the admin panel in a Docker environment, requests to `/admin-api.php` were failing with:
```
GET http://localhost:8088/admin-api.php?action=health 403 (Forbidden)
API Error: Error: Authorization header required
```

## Root Cause
The Apache web server in Docker was not passing the `Authorization` header to PHP scripts. This is a common issue in Apache/PHP setups because:

1. By default, Apache strips the `Authorization` header for security reasons
2. The header needs to be explicitly configured to be passed through to PHP via CGI/FastCGI
3. Different Apache/PHP configurations require different approaches

## Solution
The fix involves configuring Apache to pass the `Authorization` header to PHP scripts using the `CGIPassAuth` directive (available in Apache 2.4.13+).

### Changes Made

1. **Dockerfile**: Added `CGIPassAuth On` to the global Apache configuration
```apache
# Enable CGI passthrough for Authorization header (Apache 2.4.13+)
RUN echo "CGIPassAuth On" >> /etc/apache2/apache2.conf
```

2. **.htaccess**: Added `CGIPassAuth On` as the primary method, with fallback rules for older Apache versions
```apache
# Pass Authorization header to PHP (Apache 2.4.13+)
CGIPassAuth On

# Fallback for older Apache versions
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
</IfModule>
```

## Testing

### Test Results ✅

**Verified working in Docker environment:**
- ✅ Without Authorization header: Returns "Authorization header required" (HTTP 403)
- ✅ With invalid token: Returns "Invalid authentication token" (HTTP 403) - proves header is received
- ✅ With valid token: Authentication succeeds and processes request (HTTP 200)

The fix has been tested and confirmed to work in the Docker/Apache/PHP environment.

### Automated Test
Run the authorization header passing test:
```bash
php tests/test_auth_header_passing.php
```

This test verifies that:
- Requests without the Authorization header get the expected "Authorization header required" error
- Requests with the Authorization header are properly received by PHP (token validation occurs)

### Manual Testing with Docker

1. **Rebuild the Docker container** (required for Dockerfile changes to take effect):
```bash
docker-compose build --no-cache
```

2. **Start the container**:
```bash
docker-compose up -d
```

3. **Access the admin panel**:
```
http://localhost:8080/public/admin/
```

4. **Enter your admin token** from the `.env` file (ADMIN_TOKEN)

5. **Verify the connection**:
   - The admin panel should show "Connected" in the status indicator
   - No "Authorization header required" errors should appear in the browser console

### Expected Behavior

**Before the fix:**
```
GET http://localhost:8088/admin-api.php?action=health 403 (Forbidden)
API Error: Error: Authorization header required
```

**After the fix:**
- With valid token: API requests succeed (HTTP 200)
- With invalid token: `Invalid authentication token` error (HTTP 403)
- Without token: `Authorization header required` error (HTTP 403)

## Additional Notes

### Why Multiple Methods?
The fix uses multiple methods for maximum compatibility:

1. **CGIPassAuth On**: Modern, clean solution for Apache 2.4.13+
2. **RewriteRule**: Fallback for older Apache versions
3. **SetEnvIf**: Additional fallback method

### Production Considerations

When deploying to production:

1. Ensure Apache 2.4.13+ is being used (check with `apache2 -v`)
2. Verify `mod_rewrite`, `mod_headers`, and `mod_setenvif` are enabled
3. Test authentication before deploying to production
4. Monitor logs for any authentication failures

### Troubleshooting

If you still see "Authorization header required" errors after applying the fix:

1. **Verify Docker rebuild**: Make sure you rebuilt the Docker image with `--no-cache`
2. **Check Apache version**: Run `docker exec <container> apache2 -v` to verify Apache 2.4.13+
3. **Check Apache modules**: Run `docker exec <container> apache2ctl -M` to verify required modules
4. **Review Apache logs**: Check `/var/log/apache2/error.log` for any configuration errors
5. **Test with curl**:
```bash
curl -H "Authorization: Bearer your_token_here" http://localhost:8080/admin-api.php?action=health
```

## References

- [Apache CGIPassAuth Documentation](https://httpd.apache.org/docs/2.4/mod/core.html#cgipassauth)
- [PHP Authorization Header Issues](https://www.php.net/manual/en/features.http-auth.php)
