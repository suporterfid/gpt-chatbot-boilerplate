# Security Model - GPT Chatbot Boilerplate

## Overview

This document describes the comprehensive security architecture of the GPT Chatbot Boilerplate platform, covering authentication, authorization, multi-tenant isolation, data protection, and security operations.

**Target Audience**: Security engineers, architects, compliance officers, developers  
**Last Updated**: 2025-11-08  
**Version**: 1.0

---

## Table of Contents

1. [Security Architecture](#security-architecture)
2. [Authentication](#authentication)
3. [Authorization (RBAC + ACL)](#authorization-rbac--acl)
4. [Multi-Tenant Isolation](#multi-tenant-isolation)
5. [Data Protection](#data-protection)
6. [API Security](#api-security)
7. [Threat Model](#threat-model)
8. [Security Operations](#security-operations)

---

## Security Architecture

### Defense in Depth

The platform implements multiple layers of security:

```
┌─────────────────────────────────────────────────────────────┐
│                     External Layer                           │
│  - WAF (Web Application Firewall)                           │
│  - DDoS Protection                                           │
│  - Rate Limiting (IP + Tenant)                              │
└────────────────────────┬────────────────────────────────────┘
                         │
┌────────────────────────▼────────────────────────────────────┐
│                   Transport Layer                            │
│  - TLS 1.3 Encryption                                       │
│  - Certificate Pinning                                       │
│  - Secure Headers (HSTS, CSP, X-Frame-Options)             │
└────────────────────────┬────────────────────────────────────┘
                         │
┌────────────────────────▼────────────────────────────────────┐
│                 Authentication Layer                         │
│  - API Key Authentication                                    │
│  - Bearer Token (JWT)                                        │
│  - Token Rotation & Expiration                              │
└────────────────────────┬────────────────────────────────────┘
                         │
┌────────────────────────▼────────────────────────────────────┐
│                 Authorization Layer                          │
│  - Role-Based Access Control (RBAC)                         │
│  - Resource-Level ACL                                        │
│  - Tenant Boundary Enforcement                              │
└────────────────────────┬────────────────────────────────────┘
                         │
┌────────────────────────▼────────────────────────────────────┐
│                    Business Logic                            │
│  - Input Validation                                          │
│  - SQL Injection Prevention                                  │
│  - XSS Protection                                            │
│  - CSRF Protection                                           │
└────────────────────────┬────────────────────────────────────┘
                         │
┌────────────────────────▼────────────────────────────────────┐
│                     Data Layer                               │
│  - Encryption at Rest                                        │
│  - Parameterized Queries                                     │
│  - Audit Logging                                             │
│  - Tenant Data Isolation                                     │
└─────────────────────────────────────────────────────────────┘
```

### Security Zones

| Zone | Components | Trust Level | Access Control |
|------|------------|-------------|----------------|
| Public | Chat widget, public APIs | Untrusted | Rate limiting, input validation |
| Admin | Admin UI, Admin API | Semi-trusted | Authentication + RBAC |
| Internal | Workers, cron jobs | Trusted | Internal auth, no external access |
| Data | Database, file storage | Restricted | Encrypted, access via services only |

---

## Authentication

### Supported Methods

#### 1. API Key Authentication

**Format**: `Authorization: Bearer sk_live_abc123...`

**Properties**:
- Unique per admin user
- Scoped to tenant
- Can be rotated without downtime
- Supports multiple keys per user

**Implementation**:
```php
// includes/AdminAuth.php
public function authenticate($bearerToken) {
    // Validate format
    if (!preg_match('/^sk_(live|test)_[a-zA-Z0-9]{32,}$/', $bearerToken)) {
        throw new Exception('Invalid API key format');
    }
    
    // Lookup key
    $key = $this->db->getOne(
        "SELECT ak.*, au.* FROM admin_api_keys ak 
         JOIN admin_users au ON ak.user_id = au.id 
         WHERE ak.key_hash = ? AND ak.expires_at > NOW()",
        [hash('sha256', $bearerToken)]
    );
    
    // Validate tenant status
    if ($key['tenant_status'] !== 'active') {
        throw new Exception('Tenant suspended');
    }
    
    // Update last used
    $this->db->execute(
        "UPDATE admin_api_keys SET last_used_at = NOW() WHERE id = ?",
        [$key['id']]
    );
    
    return $key;
}
```

**Security Features**:
- Keys are hashed (SHA-256) before storage
- Prefix indicates environment (live/test)
- Expiration timestamp enforced
- Last used timestamp for audit
- Automatic key rotation available

#### 2. Session-Based Authentication (Admin UI)

**Properties**:
- HTTP-only cookies
- Secure flag (HTTPS only)
- SameSite=Strict
- 24-hour expiration
- CSRF token protection

**Implementation**:
```php
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => true,
    'cookie_samesite' => 'Strict',
    'cookie_lifetime' => 86400,
    'use_strict_mode' => true
]);
```

### Token Management

#### Key Rotation

**Scheduled Rotation** (quarterly):
```bash
php scripts/rotate_tenant_keys.php --tenant-id=123
```

**Emergency Rotation** (security breach):
```bash
php scripts/rotate_all_keys.php --force
```

#### Key Storage

```sql
CREATE TABLE admin_api_keys (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    key_hash VARCHAR(64) NOT NULL,  -- SHA-256 hash, never store plaintext
    key_prefix VARCHAR(20) NOT NULL,  -- e.g., "sk_live_abc"
    description VARCHAR(255),
    expires_at TIMESTAMP NULL,
    last_used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (key_hash),
    INDEX (user_id),
    FOREIGN KEY (user_id) REFERENCES admin_users(id) ON DELETE CASCADE
);
```

---

## Authorization (RBAC + ACL)

### Two-Layer Authorization Model

```
Request → Authentication → RBAC Check → ACL Check → Resource Access
```

### Layer 1: Role-Based Access Control (RBAC)

**Roles**:

| Role | Permissions | Use Case |
|------|-------------|----------|
| `super-admin` | Full system access, cross-tenant operations | Platform administrators |
| `admin` | Full access within tenant | Tenant administrators |
| `editor` | Create/edit resources within tenant | Content managers |
| `viewer` | Read-only access within tenant | Analysts, support |

**Implementation**:
```php
// includes/AdminAuth.php
class AdminAuth {
    const ROLE_SUPER_ADMIN = 'super-admin';
    const ROLE_ADMIN = 'admin';
    const ROLE_EDITOR = 'editor';
    const ROLE_VIEWER = 'viewer';
    
    public function hasPermission($user, $permission) {
        $rolePermissions = [
            self::ROLE_SUPER_ADMIN => ['*'],  // All permissions
            self::ROLE_ADMIN => ['read', 'write', 'delete', 'manage'],
            self::ROLE_EDITOR => ['read', 'write'],
            self::ROLE_VIEWER => ['read']
        ];
        
        $allowed = $rolePermissions[$user['role']] ?? [];
        return in_array('*', $allowed) || in_array($permission, $allowed);
    }
}
```

**RBAC Check Example**:
```php
// In admin-api.php
requirePermission($authenticatedUser, 'write', $adminAuth);
```

### Layer 2: Resource-Level Access Control (ACL)

**Granular Permissions**:

| Permission | Action | Description |
|------------|--------|-------------|
| `read` | View resource details | Can read but not modify |
| `update` | Modify resource | Can edit fields |
| `delete` | Remove resource | Can delete permanently |
| `share` | Grant access to others | Can add/remove ACL entries |
| `admin` | Full control | All above + ownership |

**Resource Types**:
- `agent` - AI agents
- `prompt` - Prompt templates
- `vector_store` - Vector databases
- `file` - Uploaded files
- `conversation` - Chat histories
- `webhook` - Webhook configurations
- `job` - Background jobs
- `lead` - LeadSense leads

**Implementation**:
```php
// includes/ResourceAuthService.php
class ResourceAuthService {
    const ACTION_READ = 'read';
    const ACTION_UPDATE = 'update';
    const ACTION_DELETE = 'delete';
    
    public function requireResourceAccess($user, $resourceType, $resourceId, $action) {
        // Super-admins bypass all checks
        if ($user['role'] === AdminAuth::ROLE_SUPER_ADMIN) {
            return true;
        }
        
        // Check tenant ownership
        $resource = $this->getResource($resourceType, $resourceId);
        if ($resource['tenant_id'] !== $user['tenant_id']) {
            // Check for explicit permission grant
            if (!$this->hasExplicitPermission($user['id'], $resourceType, $resourceId, $action)) {
                $this->audit('access_denied', $user, $resourceType, $resourceId, $action);
                throw new Exception('Access denied', 403);
            }
        }
        
        return true;
    }
}
```

**ACL Database Schema**:
```sql
CREATE TABLE resource_permissions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT NOT NULL,
    resource_type VARCHAR(50) NOT NULL,
    resource_id BIGINT NOT NULL,
    grantee_user_id BIGINT NOT NULL,
    permission_level VARCHAR(20) NOT NULL,  -- read, update, delete, share, admin
    granted_by_user_id BIGINT NOT NULL,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_revoked BOOLEAN DEFAULT FALSE,
    revoked_at TIMESTAMP NULL,
    UNIQUE KEY uk_permission (tenant_id, resource_type, resource_id, grantee_user_id, permission_level),
    INDEX idx_tenant (tenant_id),
    INDEX idx_grantee (grantee_user_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);
```

### Authorization Flow Example

```php
// admin-api.php - Update agent endpoint
case 'update_agent':
    // 1. RBAC: Check role has 'write' permission
    requirePermission($authenticatedUser, 'write', $adminAuth);
    
    // 2. ACL: Check resource ownership or explicit permission
    $resourceAuth->requireResourceAccess(
        $authenticatedUser,
        ResourceAuthService::RESOURCE_AGENT,
        $agentId,
        ResourceAuthService::ACTION_UPDATE
    );
    
    // 3. Proceed with update
    $agentService->updateAgent($agentId, $data);
```

---

## Multi-Tenant Isolation

### Tenant Data Model

```sql
CREATE TABLE tenants (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    status ENUM('active', 'suspended', 'trial') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status)
);
```

### Isolation Guarantees

#### Database-Level Isolation

**All tenant-scoped tables include `tenant_id`**:

```sql
-- Example: agents table
CREATE TABLE agents (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT NOT NULL,
    name VARCHAR(255) NOT NULL,
    -- ... other fields
    INDEX idx_tenant (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);
```

**Mandatory tenant filtering**:
```php
// includes/AgentService.php
public function listAgents() {
    $sql = "SELECT * FROM agents WHERE tenant_id = ?";
    return $this->db->query($sql, [$this->tenantId]);
}
```

#### Application-Level Isolation

**Tenant Context Singleton**:
```php
// includes/TenantContext.php
class TenantContext {
    private static $instance = null;
    private $tenantId;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function setTenant($tenantId) {
        $this->tenantId = $tenantId;
    }
    
    public function getTenantId() {
        if ($this->tenantId === null) {
            throw new Exception('Tenant context not set');
        }
        return $this->tenantId;
    }
}
```

**Request Initialization**:
```php
// admin-api.php
$authenticatedUser = $adminAuth->authenticate($bearerToken);
TenantContext::getInstance()->setTenant($authenticatedUser['tenant_id']);

// All subsequent service calls use this context
$agentService = new AgentService($db, TenantContext::getInstance()->getTenantId());
```

#### File Storage Isolation

**Directory Structure**:
```
/data/uploads/
  ├── tenant_1/
  │   ├── files/
  │   └── vector_stores/
  ├── tenant_2/
  │   ├── files/
  │   └── vector_stores/
  └── tenant_3/
      ├── files/
      └── vector_stores/
```

**Access Control**:
```php
public function getFilePath($tenantId, $fileId) {
    // Validate tenant owns file
    $file = $this->db->getOne(
        "SELECT * FROM files WHERE id = ? AND tenant_id = ?",
        [$fileId, $tenantId]
    );
    
    if (!$file) {
        throw new Exception('File not found', 404);
    }
    
    return "/data/uploads/tenant_{$tenantId}/files/{$file['filename']}";
}
```

### Cross-Tenant Protection

#### Test Coverage

**Isolation Tests** (tests/test_multitenancy.php):
```php
public function testTenantIsolation() {
    // Create agents for two tenants
    $agent1 = $agentService1->createAgent(['name' => 'Agent 1']);
    $agent2 = $agentService2->createAgent(['name' => 'Agent 2']);
    
    // Tenant 1 should only see own agent
    $agents1 = $agentService1->listAgents();
    assert(count($agents1) === 1);
    assert($agents1[0]['id'] === $agent1['id']);
    
    // Tenant 2 should only see own agent
    $agents2 = $agentService2->listAgents();
    assert(count($agents2) === 1);
    assert($agents2[0]['id'] === $agent2['id']);
}
```

#### Audit Logging

**All tenant access logged**:
```php
// includes/AuditService.php
public function logAccess($userId, $tenantId, $resourceType, $resourceId, $action) {
    $this->db->insert(
        "INSERT INTO audit_events 
         (user_id, tenant_id, resource_type, resource_id, action, ip_address, timestamp) 
         VALUES (?, ?, ?, ?, ?, ?, NOW())",
        [$userId, $tenantId, $resourceType, $resourceId, $action, $_SERVER['REMOTE_ADDR']]
    );
}
```

---

## Data Protection

### Encryption

#### At Rest

**Database Encryption**:
- MySQL: `ENCRYPTION='Y'` for tables
- SQLite: Use SQLCipher for encryption
- Encrypted backups: `openssl enc -aes-256-cbc`

**File Encryption**:
```php
// Encrypt sensitive files
function encryptFile($source, $dest, $key) {
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt(
        file_get_contents($source),
        'aes-256-cbc',
        $key,
        0,
        $iv
    );
    file_put_contents($dest, $iv . $encrypted);
}
```

#### In Transit

**TLS Configuration** (nginx):
```nginx
ssl_protocols TLSv1.3 TLSv1.2;
ssl_ciphers 'ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256';
ssl_prefer_server_ciphers on;
ssl_session_cache shared:SSL:10m;
ssl_session_timeout 10m;
```

### PII Protection

**PIIRedactor Service** (includes/PIIRedactor.php):
```php
class PIIRedactor {
    public function redactPhone($text) {
        return preg_replace('/\+?[\d\s\-\(\)]{10,}/', '[PHONE]', $text);
    }
    
    public function redactEmail($text) {
        return preg_replace('/[\w\.-]+@[\w\.-]+\.\w+/', '[EMAIL]', $text);
    }
    
    public function redactCPF($text) {
        return preg_replace('/\d{3}\.\d{3}\.\d{3}-\d{2}/', '[CPF]', $text);
    }
}
```

**Tenant-Level Configuration**:
```sql
ALTER TABLE tenants ADD COLUMN pii_redaction_enabled BOOLEAN DEFAULT FALSE;
```

### Secure Deletion

**Right to Erasure** (GDPR/LGPD):
```php
public function deleteUserData($externalUserId, $tenantId) {
    $this->db->beginTransaction();
    
    try {
        // Delete conversations
        $this->db->execute(
            "DELETE FROM conversations WHERE external_user_id = ? AND tenant_id = ?",
            [$externalUserId, $tenantId]
        );
        
        // Delete messages
        $this->db->execute(
            "DELETE FROM channel_messages WHERE sender_id = ? AND tenant_id = ?",
            [$externalUserId, $tenantId]
        );
        
        // Delete consent records (preserve audit trail)
        $this->db->execute(
            "UPDATE user_consents SET status = 'deleted' WHERE external_user_id = ? AND tenant_id = ?",
            [$externalUserId, $tenantId]
        );
        
        // Log deletion
        $this->audit->logDataDeletion($externalUserId, $tenantId);
        
        $this->db->commit();
    } catch (Exception $e) {
        $this->db->rollback();
        throw $e;
    }
}
```

---

## API Security

### Rate Limiting

**Per-Tenant Limits**:
```php
// includes/TenantRateLimitService.php
public function checkRateLimit($tenantId, $resourceType, $limit, $windowSeconds) {
    // Sliding window algorithm
    $cacheKey = "ratelimit:{$tenantId}:{$resourceType}:{$windowSeconds}";
    $requests = $this->getRequestTimestamps($cacheKey);
    
    $now = time();
    $windowStart = $now - $windowSeconds;
    $requests = array_filter($requests, fn($ts) => $ts > $windowStart);
    
    if (count($requests) >= $limit) {
        return [
            'allowed' => false,
            'retry_after' => min($requests) + $windowSeconds - $now
        ];
    }
    
    // Record this request
    $requests[] = $now;
    $this->saveRequestTimestamps($cacheKey, $requests);
    
    return ['allowed' => true, 'remaining' => $limit - count($requests)];
}
```

**Response Headers**:
```http
HTTP/1.1 200 OK
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 42
X-RateLimit-Reset: 1699459200
```

### Input Validation

**Centralized Validation**:
```php
class Validator {
    public static function validateEmail($email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('Invalid email format');
        }
    }
    
    public static function validateTenantId($tenantId) {
        if (!is_numeric($tenantId) || $tenantId < 1) {
            throw new ValidationException('Invalid tenant ID');
        }
    }
    
    public static function sanitizeHtml($html) {
        return htmlspecialchars($html, ENT_QUOTES, 'UTF-8');
    }
}
```

### SQL Injection Prevention

**Always use parameterized queries**:
```php
// ✅ SAFE
$agents = $db->query(
    "SELECT * FROM agents WHERE tenant_id = ? AND name LIKE ?",
    [$tenantId, "%{$search}%"]
);

// ❌ UNSAFE - Never do this
$agents = $db->query("SELECT * FROM agents WHERE name LIKE '%{$search}%'");
```

### XSS Prevention

**Output Encoding**:
```php
// In templates
echo htmlspecialchars($userInput, ENT_QUOTES, 'UTF-8');

// In JSON responses
header('Content-Type: application/json; charset=utf-8');
echo json_encode($data, JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_APOS);
```

---

## Threat Model

### Identified Threats

| Threat | Likelihood | Impact | Mitigation |
|--------|------------|--------|------------|
| **Cross-Tenant Data Access** | Medium | Critical | RBAC + ACL + Tenant filtering |
| **API Key Compromise** | Medium | High | Key rotation, expiration, hashing |
| **SQL Injection** | Low | Critical | Parameterized queries, input validation |
| **DDoS Attack** | High | High | Rate limiting, CDN, WAF |
| **Session Hijacking** | Low | High | HTTP-only cookies, HTTPS, SameSite |
| **XSS Attack** | Medium | Medium | Output encoding, CSP headers |
| **CSRF Attack** | Low | Medium | CSRF tokens, SameSite cookies |
| **Data Breach** | Low | Critical | Encryption, access controls, audit logs |
| **Insider Threat** | Low | High | Audit logging, least privilege, separation of duties |
| **Supply Chain Attack** | Medium | High | Dependency scanning, SBOMs, trusted sources |

### Attack Scenarios & Defenses

#### Scenario 1: Cross-Tenant Data Leak

**Attack**: Malicious admin tries to access another tenant's agents

**Defense**:
1. Request arrives with valid API key (Tenant A)
2. TenantContext set to Tenant A
3. Admin requests agent from Tenant B
4. AgentService filters: `WHERE tenant_id = <Tenant A>`
5. Agent not found (403 Forbidden)
6. Audit log records attempt

#### Scenario 2: API Key Theft

**Attack**: API key stolen from logs/network

**Defense**:
1. Keys stored as SHA-256 hashes (irreversible)
2. Keys have expiration (auto-rotate quarterly)
3. Last-used timestamp tracked
4. Unusual usage triggers alert
5. Admin can rotate key immediately
6. Old key invalidated instantly

#### Scenario 3: SQL Injection

**Attack**: Malicious input attempts database manipulation

**Defense**:
1. All queries use parameterized statements
2. Input validation rejects suspicious patterns
3. Database user has limited privileges
4. WAF blocks common SQL injection patterns
5. CodeQL scans code for vulnerabilities

---

## Security Operations

### Security Monitoring

**Metrics to Track**:
- Failed authentication attempts (>10/min/IP)
- Cross-tenant access attempts (any)
- Unusual API usage patterns (>3σ from baseline)
- Database query failures (>5% error rate)
- Privilege escalation attempts (any)

**Alerting Rules** (Prometheus):
```yaml
- alert: SuspiciousAuthActivity
  expr: rate(chatbot_auth_failed_total[5m]) > 0.1
  for: 2m
  annotations:
    summary: "High authentication failure rate"

- alert: CrossTenantAccessAttempt
  expr: chatbot_tenant_isolation_violations_total > 0
  annotations:
    summary: "Cross-tenant access attempt detected"
```

### Vulnerability Management

**Scanning Tools**:
- CodeQL (static analysis)
- Dependabot (dependency vulnerabilities)
- docker scan (container vulnerabilities)
- OWASP ZAP (dynamic testing)

**Patching Process**:
1. Vulnerability identified
2. Assess severity (CVSS score)
3. If critical: Emergency patch within 24h
4. If high: Patch within 7 days
5. If medium/low: Include in next release
6. Update CHANGELOG.md
7. Notify affected customers

### Incident Response

**See [ops/disaster_recovery.md](ops/disaster_recovery.md) - Security Breach Response**

**Key Steps**:
1. Isolate affected systems
2. Preserve forensic evidence
3. Rotate all credentials
4. Investigate scope
5. Apply patches
6. Restore from clean backup
7. Notify affected parties
8. Post-mortem & prevention

### Security Audits

**Quarterly Security Review**:
- Review access logs for anomalies
- Validate RBAC/ACL configurations
- Test disaster recovery procedures
- Update threat model
- Penetration testing (if budget allows)
- Compliance assessment (GDPR/LGPD)

---

## Compliance

### GDPR/LGPD

- **Data Subject Rights**: Export, delete, correct data
- **Consent Management**: ConsentService tracks opt-in/out
- **Data Portability**: JSON export available
- **Right to Erasure**: Secure deletion with audit trail
- **Data Processing Agreement**: Template available
- **PII Redaction**: Configurable per tenant

### SOC 2 Considerations

- Access controls (RBAC + ACL)
- Audit logging (all operations)
- Encryption (at rest and in transit)
- Change management (version control)
- Incident response (documented procedures)
- Vendor management (OpenAI, payment processors)

---

## Appendix

### Security Checklist

**Before Deployment**:
- [ ] All API keys rotated
- [ ] TLS certificates valid
- [ ] Security headers configured
- [ ] Rate limiting enabled
- [ ] Audit logging active
- [ ] Backups tested
- [ ] Monitoring alerts configured
- [ ] Incident response plan ready

**Monthly Reviews**:
- [ ] Review failed auth attempts
- [ ] Check for unusual API usage
- [ ] Verify backup integrity
- [ ] Update dependencies
- [ ] Review audit logs
- [ ] Test key rotation
- [ ] Validate tenant isolation

### Security Contacts

| Role | Contact | Escalation |
|------|---------|------------|
| Security Lead | TBD | Immediate for P0 |
| Compliance Officer | TBD | For data breaches |
| Legal Counsel | TBD | For legal implications |
| External Auditor | TBD | Annual review |

---

## Document Control

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2025-11-08 | System | Initial creation |

**Review Cycle**: Quarterly  
**Next Review**: 2026-02-08  
**Owner**: Security Team
