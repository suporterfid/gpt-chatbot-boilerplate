# Secrets & Token Management Guide

## Overview

This guide covers secure management of secrets, API keys, and tokens for the GPT Chatbot application, including rotation procedures and integration with secrets management systems.

## Secret Types

### Application Secrets

1. **OpenAI API Key** (`OPENAI_API_KEY`)
   - Rotation: Monthly or on suspected compromise
   - Storage: Secrets manager (production) or .env (development)

2. **Admin Token** (`ADMIN_TOKEN`)
   - Legacy authentication token
   - Rotation: Quarterly or on admin user change
   - Length: Minimum 32 characters

3. **Database Credentials** (`ADMIN_DATABASE_URL`)
   - Rotation: Quarterly
   - Include in credential vault

4. **Admin User API Keys**
   - Per-user authentication tokens
   - Expiration: Configurable (default: 1 year)
   - Revocable via Admin UI or API

## Token Rotation

### Admin Token Rotation (Super-Admin Only)

The application provides an endpoint for rotating the ADMIN_TOKEN without service interruption.

**Via Admin API:**

```bash
# Rotate admin token (super-admin permission required)
curl -X POST https://chatbot.example.com/admin-api.php/rotate_admin_token \
  -H "Authorization: Bearer YOUR_CURRENT_API_KEY" \
  -H "Content-Type: application/json"
```

**Response:**

```json
{
  "data": {
    "new_token": "newly_generated_secure_token_here",
    "old_token_revoked": true,
    "effective_immediately": true
  }
}
```

**Important:**
- Old token is revoked immediately
- Update .env file with new token
- Communicate new token to other administrators
- Legacy ADMIN_TOKEN is for backward compatibility only

### API Key Rotation (Per-User)

Admin users can rotate their API keys at any time:

**Via Admin UI:**
1. Navigate to Settings → Security
2. Click "Rotate API Key"
3. Copy new API key (shown only once)
4. Update integrations with new key

**Via Admin API:**

```bash
# Generate new API key for current user
curl -X POST https://chatbot.example.com/admin-api.php/generate_api_key \
  -H "Authorization: Bearer YOUR_CURRENT_API_KEY"
```

**Revoke API Key:**

```bash
# Revoke specific API key
curl -X POST https://chatbot.example.com/admin-api.php/revoke_api_key \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -d '{"key_id": "key-id-to-revoke"}'
```

### OpenAI API Key Rotation

**Procedure:**

1. **Generate new key** at https://platform.openai.com/api-keys

2. **Test new key:**
   ```bash
   curl https://api.openai.com/v1/models \
     -H "Authorization: Bearer NEW_API_KEY" | jq .
   ```

3. **Update environment:**
   ```bash
   # Update .env
   sudo nano /var/www/chatbot/.env
   # Replace OPENAI_API_KEY value
   
   # Reload application (zero-downtime)
   sudo systemctl reload php8.2-fpm
   ```

4. **Verify functionality:**
   ```bash
   # Test via health endpoint
   curl https://chatbot.example.com/admin-api.php/health | jq .checks.openai
   ```

5. **Revoke old key** at OpenAI platform

### Database Credential Rotation

**PostgreSQL:**

1. **Create new user:**
   ```sql
   CREATE USER chatbot_user_new WITH ENCRYPTED PASSWORD 'new_secure_password';
   GRANT ALL PRIVILEGES ON DATABASE chatbot_production TO chatbot_user_new;
   GRANT ALL ON ALL TABLES IN SCHEMA public TO chatbot_user_new;
   GRANT ALL ON ALL SEQUENCES IN SCHEMA public TO chatbot_user_new;
   ```

2. **Update connection string:**
   ```bash
   # Update .env
   ADMIN_DATABASE_URL=postgresql://chatbot_user_new:new_secure_password@localhost:5432/chatbot_production
   
   # Reload application
   sudo systemctl reload php8.2-fpm
   sudo systemctl restart chatbot-worker
   ```

3. **Test connectivity:**
   ```bash
   psql $ADMIN_DATABASE_URL -c "SELECT COUNT(*) FROM agents;"
   ```

4. **Drop old user:**
   ```sql
   DROP USER chatbot_user;
   ```

## Secrets Management Integration

### AWS Secrets Manager

**Setup:**

1. **Install AWS CLI:**
   ```bash
   sudo apt install -y awscli
   aws configure
   ```

2. **Create secrets:**
   ```bash
   # Create OpenAI API key secret
   aws secretsmanager create-secret \
     --name chatbot/production/openai-api-key \
     --secret-string "sk-proj-..."
   
   # Create database URL secret
   aws secretsmanager create-secret \
     --name chatbot/production/database-url \
     --secret-string "postgresql://..."
   
   # Create admin token secret
   aws secretsmanager create-secret \
     --name chatbot/production/admin-token \
     --secret-string "$(openssl rand -base64 32)"
   ```

3. **Create IAM policy:**
   ```json
   {
     "Version": "2012-10-17",
     "Statement": [
       {
         "Effect": "Allow",
         "Action": [
           "secretsmanager:GetSecretValue"
         ],
         "Resource": [
           "arn:aws:secretsmanager:us-east-1:ACCOUNT_ID:secret:chatbot/production/*"
         ]
       }
     ]
   }
   ```

4. **Update application to fetch secrets:**

   Create `includes/SecretsManager.php`:

   ```php
   <?php
   class SecretsManager {
       private static $cache = [];
       
       public static function getSecret($secretName) {
           if (isset(self::$cache[$secretName])) {
               return self::$cache[$secretName];
           }
           
           $cmd = "aws secretsmanager get-secret-value --secret-id " . escapeshellarg($secretName);
           $output = shell_exec($cmd);
           $data = json_decode($output, true);
           
           if (!$data || !isset($data['SecretString'])) {
               throw new Exception("Failed to retrieve secret: $secretName");
           }
           
           self::$cache[$secretName] = $data['SecretString'];
           return $data['SecretString'];
       }
   }
   ```

5. **Update config.php:**

   ```php
   if (getenv('USE_SECRETS_MANAGER') === 'true') {
       require_once 'includes/SecretsManager.php';
       $openaiKey = SecretsManager::getSecret('chatbot/production/openai-api-key');
       $adminToken = SecretsManager::getSecret('chatbot/production/admin-token');
       $databaseUrl = SecretsManager::getSecret('chatbot/production/database-url');
   } else {
       $openaiKey = getenv('OPENAI_API_KEY');
       $adminToken = getenv('ADMIN_TOKEN');
       $databaseUrl = getenv('ADMIN_DATABASE_URL');
   }
   ```

**Rotation with AWS Secrets Manager:**

```bash
# Rotate secret
aws secretsmanager rotate-secret \
  --secret-id chatbot/production/openai-api-key \
  --rotation-lambda-arn arn:aws:lambda:us-east-1:ACCOUNT_ID:function:RotateSecret

# Or manually update
aws secretsmanager update-secret \
  --secret-id chatbot/production/openai-api-key \
  --secret-string "sk-proj-NEW_KEY"

# Application will fetch new secret on next restart
sudo systemctl reload php8.2-fpm
```

### HashiCorp Vault

**Setup:**

1. **Install Vault:**
   ```bash
   wget https://releases.hashicorp.com/vault/1.15.0/vault_1.15.0_linux_amd64.zip
   unzip vault_1.15.0_linux_amd64.zip
   sudo mv vault /usr/local/bin/
   ```

2. **Start Vault (development mode for testing):**
   ```bash
   vault server -dev
   export VAULT_ADDR='http://127.0.0.1:8200'
   export VAULT_TOKEN='dev-token'
   ```

3. **Store secrets:**
   ```bash
   # Enable KV secrets engine
   vault secrets enable -path=chatbot kv-v2
   
   # Store secrets
   vault kv put chatbot/production \
     openai_api_key="sk-proj-..." \
     admin_token="$(openssl rand -base64 32)" \
     database_url="postgresql://..."
   ```

4. **Create policy:**
   ```hcl
   path "chatbot/data/production" {
     capabilities = ["read"]
   }
   ```

5. **Update application:**

   ```php
   class VaultClient {
       private $vaultAddr;
       private $vaultToken;
       
       public function __construct() {
           $this->vaultAddr = getenv('VAULT_ADDR');
           $this->vaultToken = getenv('VAULT_TOKEN');
       }
       
       public function getSecret($path) {
           $url = $this->vaultAddr . '/v1/chatbot/data/' . $path;
           
           $ch = curl_init($url);
           curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
           curl_setopt($ch, CURLOPT_HTTPHEADER, [
               'X-Vault-Token: ' . $this->vaultToken
           ]);
           
           $response = curl_exec($ch);
           $data = json_decode($response, true);
           
           return $data['data']['data'] ?? null;
       }
   }
   ```

**Rotation with Vault:**

```bash
# Update secret
vault kv put chatbot/production \
  openai_api_key="sk-proj-NEW_KEY" \
  admin_token="NEW_TOKEN" \
  database_url="postgresql://..."

# Application fetches new values on restart
```

## Environment-Specific Secrets

### Development

```bash
# .env.development
OPENAI_API_KEY=sk-proj-dev-key
ADMIN_TOKEN=dev_token_insecure
ADMIN_DATABASE_URL=sqlite:./data/admin.db
```

### Staging

```bash
# .env.staging
OPENAI_API_KEY=sk-proj-staging-key
ADMIN_TOKEN=$(openssl rand -base64 32)
ADMIN_DATABASE_URL=postgresql://chatbot_staging:password@db-staging:5432/chatbot_staging
```

### Production

```bash
# .env.production (or use secrets manager)
USE_SECRETS_MANAGER=true
SECRETS_PROVIDER=aws  # or 'vault'
AWS_REGION=us-east-1
# All secrets fetched from AWS Secrets Manager
```

## Security Best Practices

### Secret Generation

```bash
# Generate strong admin token
openssl rand -base64 32

# Generate UUID
uuidgen

# Generate password
pwgen -s 32 1
```

### Secret Storage

- ✅ **DO**: Store in encrypted secrets manager
- ✅ **DO**: Use environment variables in containers
- ✅ **DO**: Encrypt .env files at rest
- ❌ **DON'T**: Commit secrets to git
- ❌ **DON'T**: Store in application code
- ❌ **DON'T**: Log secrets
- ❌ **DON'T**: Send secrets in URLs

### Access Control

```bash
# Restrict .env file permissions
sudo chown root:www-data /var/www/chatbot/.env
sudo chmod 640 /var/www/chatbot/.env

# Audit who has access
sudo getfacl /var/www/chatbot/.env
```

## Audit & Compliance

### Audit Logs

All token operations are logged:

```bash
# View token rotation events
SELECT * FROM audit_log
WHERE action LIKE 'token.%'
ORDER BY created_at DESC;
```

### Compliance Requirements

**GDPR/SOC2:**
- Rotate secrets quarterly
- Log all access to secrets
- Encrypt secrets at rest and in transit
- Provide audit trail

**PCI DSS:**
- Rotate encryption keys annually
- Limit access to cryptographic keys
- Maintain key inventory

## Troubleshooting

### Invalid Token After Rotation

```bash
# Check current token in .env
grep ADMIN_TOKEN /var/www/chatbot/.env

# Verify token in database (if using AdminAuth)
SELECT * FROM admin_api_keys WHERE is_active = 1;

# Test token
curl -H "Authorization: Bearer TOKEN" \
  https://chatbot.example.com/admin-api.php/health
```

### Secrets Manager Connection Issues

```bash
# Test AWS Secrets Manager access
aws secretsmanager get-secret-value \
  --secret-id chatbot/production/openai-api-key

# Check IAM permissions
aws sts get-caller-identity

# Verify application can access
sudo -u www-data aws secretsmanager get-secret-value \
  --secret-id chatbot/production/openai-api-key
```

## See Also

- [Production Deployment](production-deploy.md)
- [Incident Runbook](incident_runbook.md)
- [Security Configuration](nginx-production.conf)
