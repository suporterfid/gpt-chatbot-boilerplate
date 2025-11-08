# Commercialization Readiness Technical Specification  
GPT Chatbot Boilerplate — SaaS Offering for Integrators (Chatbot + WhatsApp AI Agents)

Data: 2025-11-08  
Autor: Copilot (Assistente)  
Versão: 1.0

## 1. Objetivo

Estabelecer uma especificação técnica detalhada dos impedimentos atuais e das ações necessárias para tornar o repositório `suporterfid/gpt-chatbot-boilerplate` comercialmente viável para integradores de software que desejam vender agentes de IA omnichannel (inicialmente Web + WhatsApp). O foco é preparação SaaS multi‑tenant, segurança, escalabilidade, operação e conformidade.

## 2. Escopo

Inclui:  
- Multi‑tenancy e isolamento de dados  
- Controle de acesso por recurso (Resource‑level ACL)  
- Integração WhatsApp em nível de produção (Cloud API oficial)  
- Billing, metering e quotas por tenant  
- Observabilidade, monitoramento e alertas  
- Backup & Disaster Recovery (BCDR)  
- Rate limiting por tenant / proteção de custos  
- Packaging & Deploy para integradores (DevOps)  
- Compliance (LGPD/GDPR, opt-in, PII)  
- Testes, QA e cobertura para ambientes multi‑cliente  
- Roadmap e estimativas  

Excluídos (tratados separadamente futuramente):  
- Estratégia comercial detalhada, pricing, contratos legais  
- Suporte humano e processos de onboarding de suporte  

## 3. Visão Geral dos Impedimentos (Resumo Prioritário)

| Prioridade | Impedimento | Categoria | Status Atual | Impacto Comercial | Bloqueador |
|------------|-------------|----------|--------------|-------------------|------------|
| P0 | Multi‑tenancy ausente | Arquitetura | Single-tenant; sem `tenant_id` | Sem isolamento de clientes | Sim |
| P0 | Resource‑level ACL incompleto | Segurança | RBAC global apenas | Risco de acesso indevido | Sim |
| P0 | Billing & metering inexistente | Negócio / Custos | Sem tracking por cliente | Sem modelo de receita / controle | Sim |
| P0 | WhatsApp integração produção | Canal | Gateway não oficial parcial | Risco regulatório / falta de features | Sim |
| P1 | Observabilidade insuficiente | Operações | Logs locais e métricas básicas | Difícil escalar >1 cliente | Alto |
| P1 | Backup & DR inexistente | Operações | Sem rotina testada | Risco sério de perda de dados | Alto |
| P1 | Rate limiting por IP apenas | Custos / Abuse | Limites não por tenant | Exposição a abuso | Alto |
| P2 | Packaging / Infra-as-Code limitado | DevOps | Docker/compose básico | Dificulta adoção integradores | Médio |
| P2 | Compliance & PII parcial | Segurança | Mecanismos não sistematizados | Risco regulatório/contratual | Médio |
| P2 | Testes e e2e multi‑cliente | Qualidade | Testes parciais | Fragilidade sob escala | Médio |

## 4. Detalhamento Técnico dos Impedimentos e Requisitos

### 4.1 Multi‑Tenancy

#### Problema
Entidades (agents, prompts, vector stores, files, conversations, audit logs, jobs, webhook events) não possuem campo `tenant_id` e não há escopo automático nas queries.

#### Requisitos
- Adicionar coluna `tenant_id` a todas as tabelas persistentes (FK para tabela `tenants`).
- Criar tabela `tenants` com metadados (nome, slug, status, limites).
- Middleware de contexto: resolve `tenant_id` via token/API key.
- Todas as queries server-side devem aplicar filtro `WHERE tenant_id = :tenant_id`.
- Super-admin pode listar múltiplos tenants; usuários comuns restritos ao próprio.
- Suporte a “default tenant” para migração inicial.

#### Migrations (Exemplo Simplificado)
```sql
CREATE TABLE tenants (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  slug VARCHAR(100) NOT NULL UNIQUE,
  status ENUM('active','suspended') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE agents ADD COLUMN tenant_id BIGINT NOT NULL DEFAULT 0, ADD INDEX idx_agents_tenant (tenant_id);
-- Repetir para prompts, vector_stores, files, conversations, audit_log, admin_users, job_queue, webhook_events, channel_sessions, channel_messages.
```

#### Middleware (Pseudo)
```php
$token = extractBearerToken();
$auth = $adminAuth->authenticate($token); // retorna user + tenant_id
$GLOBALS['REQUEST_CONTEXT'] = [
  'tenant_id' => $auth['tenant_id'],
  'user_id'   => $auth['id'],
  'roles'     => $auth['roles'],
  'trace_id'  => $traceId
];
```

#### Acceptance Criteria
- Nenhuma query retorna dados de outro tenant em testes multi‑tenant.
- Falha explícita 403 para recursos de tenant diferente.
- Audit logs gravam `tenant_id`.
- Scripts de migração backfill completos (safe rollback).

---

### 4.2 Resource‑Level ACL

#### Problema
RBAC atual limita-se a roles globais (viewer/admin/super-admin); falta granularidade por recurso.

#### Requisitos
- Tabela `resource_acl` com (tenant_id, resource_type, resource_id, grantee_user_id, permission_level).
- Permissões: `view`, `edit`, `delete`, `share`, `admin`.
- Service: `ResourceAclService::hasPermission(tenant_id, user_id, resource_type, resource_id, required_perm)`.
- Middleware antes de operações CRUD que não sejam do próprio criador.
- Auditoria para grant/revoke.

#### Migrations
```sql
CREATE TABLE resource_acl (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT NOT NULL,
  resource_type VARCHAR(50) NOT NULL,
  resource_id BIGINT NOT NULL,
  grantee_user_id BIGINT NOT NULL,
  permission_level VARCHAR(20) NOT NULL,
  granted_by_user_id BIGINT NOT NULL,
  granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  is_revoked BOOLEAN DEFAULT FALSE,
  revoked_at TIMESTAMP NULL,
  UNIQUE KEY uk_acl (tenant_id, resource_type, resource_id, grantee_user_id),
  INDEX idx_acl_tenant (tenant_id)
);
```

#### Acceptance Criteria
- Testes: usuário sem permissão não acessa recurso; com permissão acessa.
- Revogação invalida acesso imediatamente.
- Audit registra cada alteração.

---

### 4.3 Billing & Metering

#### Problema
Sem tracking de uso → impossível cobrar e controlar custos (OpenAI, storage, vetores).

#### Requisitos
- Tabelas: `tenant_usage` (aggregates), `usage_events` (linha), `invoices`, `subscription_plans`, `tenant_subscriptions`.
- Eventos: `api_call`, `chat_message`, `vector_query`, `file_upload`.
- Quotas por plano: chamadas/mês, storage GB, vector queries.
- Alertas: approaching (80%), reached (100%), exceeded (>100%).
- Integração gateway (Stripe/Asaas) para subscription + invoices.
- Headers de resposta com contadores (ex: `X-Usage-Remaining`).

#### Exemplo Tabela Principal
```sql
CREATE TABLE usage_events (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT NOT NULL,
  event_type VARCHAR(50) NOT NULL,
  agent_id BIGINT NULL,
  units INT DEFAULT 1,
  cost DECIMAL(10,4) DEFAULT 0,
  metadata JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_usage_tenant (tenant_id, event_type, created_at)
);
```

#### Acceptance Criteria
- Dashboard retorna uso atual por tenant.
- Limite excedido gera erro 429 ou 403 conforme política (hard vs soft).
- Faturas geradas para período fechado.

---

### 4.4 Integração WhatsApp (Produção)

#### Problema
Implementação parcial; ausência de recursos oficiais da Meta: templates, status, opt-in/out formal, compliance, escalabilidade.

#### Requisitos
- Substituir/estender adapter para WhatsApp Cloud API.
- Endpoints para:
  - Enviar template message (HSM)
  - Receber webhook status (sent, delivered, read, failed)
  - Gerenciar mídias (download/upload)
- Tabelas: `channel_accounts` (armazenar credenciais + phone ID), `channel_templates`, `channel_message_status`.
- Rate limiting por número.
- Opt-in/out persistido com timestamp e origem.

#### Acceptance Criteria
- Mensagens template aprovadas enviadas e rastreadas.
- Retentativas em falha (ex: network glitch).
- Logs incluem phone_id e message_id.
- Conformidade: remover envio para números opt-out.

---

### 4.5 Observabilidade & Monitoramento

#### Problema
Logs locais e métricas simples → insuficiente para operação multi‑tenant.

#### Requisitos
- Logging estruturado JSON com campos: timestamp, level, trace_id, tenant_id, user_id, resource_type, action.
- Expor métricas Prometheus:
  - `http_requests_total{tenant_id,endpoint,status}`
  - `chat_tokens_used_total{tenant_id,model}`
  - `channel_messages_total{tenant_id,channel,type}`
  - `quota_approach_events_total{tenant_id,quota_type}`
- Tracing: OpenTelemetry instrumentation em:
  - admin-api.php (entrada)
  - ChatHandler (chain calls)
  - Workers / Jobs (background spans)
  - Webhooks (incoming spans)
- Dashboards: latência P95 por endpoint, erros por tenant, top 10 agentes por volume.

#### Acceptance Criteria
- Todos endpoints críticos em traces.
- 100% dos logs contêm tenant_id quando autenticado.
- Alertas configurados: erro 5xx > X/min, latência P95 > threshold, quota excedida.

---

### 4.6 Backup & Disaster Recovery

#### Problema
Sem automação de backup, sem RPO/RTO definidos.

#### Requisitos
- Classificação de dados:
  - Críticos: agents, prompts, vector stores metadata, conversations, audit logs.
  - Não críticos: caches, temp files.
- Backups:
  - DB dump incremental + full (ex: diário full, horário incremental).
  - Arquivos (uploads) replicados (objeto → S3/GCS).
  - Vector stores: snapshot + reindex script.
- RPO alvo: ≤ 15 min (metadados + conversas recentes).
- RTO alvo: ≤ 60 min.
- Script de restauração idempotente com validação checksum.
- Teste trimestral de restore (staging environment).

#### Acceptance Criteria
- Logs mostram sucesso dos jobs de backup.
- Procedimentos restauram ambiente completo em staging.
- Relatório DR test armazenado.

---

### 4.7 Rate Limiting por Tenant

#### Problema
IP-based pode penalizar NAT compartilhado e não isola consumo.

#### Requisitos
- Implementar limiter por (tenant_id, token) usando sliding window ou token bucket.
- Redis recomendado para contadores distribuídos.
- Cabeçalho retorno: `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`.
- Classes de limite: chat, admin, channel webhooks.

#### Acceptance Criteria
- Teste: tenant A saturando não afeta B.
- Ajuste dinâmico via config/env.

---

### 4.8 Packaging / Deploy

#### Problema
Faltam artefatos para entrega repetitiva.

#### Requisitos
- Helm chart: valores para DB, secrets, ingress, scaling.
- Terraform exemplos: provisionar DB + storage + bucket + Redis.
- CI pipelines: build, security scan, helm release, migrations, smoke tests.
- Scripts CLI: `./scripts/provision-tenant.sh`, `./scripts/rotate-key.sh`.

#### Acceptance Criteria
- Ambiente novo provisionado < 30 min usando documentação.
- Deploy automatizado: pipeline executa testes e publica versão.

---

### 4.9 Compliance & PII

#### Requisitos
- Flag de redaction ativável por tenant (remover e/ou mascarar dados sensíveis de logs).
- Endpoint para exportar / deletar histórico de conversas (Right to Erasure).
- Consent tracking: tabela `user_consent` (phone, tenant_id, status, timestamp).
- Política de retenção configurável (ex: conversas > 180 dias → arquivar).

#### Acceptance Criteria
- Solicitação de deleção remove registros ligados ao identificador (verificável).
- Logs não apresentam campos PII quando redaction ativo.

---

### 4.10 Testes / QA

#### Requisitos
- Unit tests (≥ 80% serviços core).
- Integration tests multi‑tenant (tenant A ≠ tenant B).
- Channel adapter mocks (WhatsApp, Telegram).
- Load test scripts (k6): escalabilidade horizontal.
- Security tests (SQL injection, auth bypass).
- Test matrix CI: PHP 8.x, DB (MySQL/Postgres), Redis optional.

#### Acceptance Criteria
- Pipeline bloqueia PR com cobertura < threshold.
- Teste de regressão de ACL e multi‑tenant executado em cada PR.

---

## 5. Roadmap Técnico (Sequência Recomendada)

| Fase | Conteúdo | Dependências | Duração Estimada |
|------|----------|--------------|------------------|
| F1 | Tenants + tenant_id + backfill básico | Nenhuma | 2 semanas |
| F2 | Resource ACL + middleware + testes | F1 | 2 semanas |
| F3 | Billing & metering infra + quotas | F1/F2 | 3 semanas |
| F4 | WhatsApp Cloud API adapter + templates + opt-in | F1 | 3–4 semanas |
| F5 | Observabilidade (logs/metrics/tracing) | F1–F2 | 2 semanas |
| F6 | Backup & DR + scripts + testes | F1 | 2 semanas |
| F7 | Rate limiting por tenant + custos | F3 | 1 semana |
| F8 | Packaging (Helm/Terraform/CI) | F1–F5 | 2 semanas |
| F9 | Compliance & PII tooling | F1 | 1–2 semanas |
| F10 | Testes avançados e hardening | Todas | Contínuo |

Total sequencial: 20–24 semanas (com paralelismo equipe 3–4 pessoas: 12–16 semanas).

## 6. Riscos e Mitigações

| Risco | Descrição | Mitigação |
|-------|-----------|-----------|
| Vazamento de dados cross‑tenant | Query sem filtro tenant_id | Linter estático + testes integração de isolamento |
| Custos OpenAI sem controle | Falta de billing/quota | Implementar contador + alerta early (80%) |
| Interrupção em migração | Alterações schema em produção | Estratégia com colunas shadow + backfill incremental + rollback snapshot |
| Rejeição pelo WhatsApp | Uso de gateway não oficial | Migrar para Cloud API oficial + seguir política de templates |
| Falta de rastreabilidade em incidentes | Logs não estruturados | Implantar JSON + trace_id + centralização |
| Restauração falha | Backup não verificado | Teste trimestral e checksums automatizados |

## 7. Métricas de Sucesso (KPIs)

| KPI | Meta Inicial |
|-----|--------------|
| Isolamento verificado (testes cross-tenant) | 100% sem acesso indevido |
| Cobertura testes serviços críticos | ≥ 80% |
| Tempo médio para provisionar novo tenant | < 10 min |
| RPO (dados críticos) | ≤ 15 min |
| RTO (falha total) | ≤ 60 min |
| Latência P95 Chat Endpoint (≤ n tokens) | < 1200 ms |
| Erros 5xx por 1000 req | < 5 |
| Alertas falsos positivos (mensal) | < 10% do total |

## 8. Artefatos a Produzir

- Migrations SQL (up/down) multi‑tenant e ACL  
- Services: `TenantService`, `ResourceAclService`, `BillingService`, `ChannelAdapterInterface`, `WhatsAppCloudAdapter`  
- Middleware: `MultiTenantMiddleware`, `RateLimitMiddleware`, `TracingMiddleware`  
- CLI scripts: provision tenant, rotate API key, backfill  
- Helm chart e values.yaml (config multi‑tenant)  
- Terraform exemplo (DB + Bucket + Redis)  
- Test suites (unit / integration / e2e / load)  
- Observability config (Prometheus scrape, Grafana dashboards, OpenTelemetry collector)  
- DR Runbook + Backup scripts  
- Compliance docs (PII handling, consent, data export/deletion)  

## 9. Exemplo de Interface de Canal (Adapter)

```php
interface ChannelAdapterInterface {
    public function sendMessage(ChannelContext $ctx, OutboundMessage $msg): SendResult;
    public function parseInbound(array $webhookPayload): InboundMessage;
    public function supportsMedia(): bool;
    public function fetchMedia(string $mediaId): MediaContent;
    public function getRateLimitKey(ChannelContext $ctx): string;
    public function validateTemplate(string $templateName): bool;
}
```

## 10. Exemplo de Registro de Evento de Uso

```php
$usageTracker->recordEvent(
  tenantId: $ctx->tenant_id,
  eventType: 'chat_message',
  metadata: [
    'agent_id' => $agent->id,
    'channel'  => 'whatsapp',
    'tokens'   => $tokenCount,
    'model'    => $config['openai']['model']
  ]
);
```

## 11. Checklist de Aceitação Final

- [ ] Todas as tabelas possuem `tenant_id` e índices relacionados  
- [ ] ACL implementada e testada (grant/revoke + negative cases)  
- [ ] Billing gera registros de uso + dashboard de consumo + limites aplicados  
- [ ] WhatsApp Cloud API funcional (templates, mídia, status, opt-in/out)  
- [ ] Logs estruturados centralizados + métricas + traces ativos  
- [ ] Backup rotineiro + restauração testada com relatório documentado  
- [ ] Rate limiter por tenant configurado e validado em carga  
- [ ] Helm/Terraform permitem provisionamento completo reprodutível  
- [ ] PII redaction configurável por tenant e auditável  
- [ ] Testes multi‑tenant e canais em CI com cobertura mínima atendida  
- [ ] Documentação operacional e compliance entregue  

## 12. Próximos Passos

1. Aprovar esta especificação.  
2. Criar epics/issues para cada seção (separar backlog).  
3. Priorizar F1–F3 em sprint inicial.  
4. Definir stack observability (Prometheus + Loki + Grafana + OTEL Collector).  
5. Iniciar modelagem detalhada WhatsApp Cloud adapter.

---

## 13. Apêndice — Estrutura de Diretórios Sugerida

```
/includes/
  TenantService.php
  ResourceAclService.php
  BillingService.php
  adapters/
    ChannelAdapterInterface.php
    WhatsAppCloudAdapter.php
    TelegramAdapter.php (futuro)
  middleware/
    MultiTenantMiddleware.php
    RateLimitMiddleware.php
    TracingMiddleware.php

/migrations/
  010_add_tenants.sql
  011_add_tenant_id_to_core.sql
  012_create_resource_acl.sql
  013_create_usage_events.sql
  014_create_billing_tables.sql

/scripts/
  backfill-tenants.php
  provision-tenant.php
  rotate-api-key.php
  generate-invoice.php

/helm/
  Chart.yaml
  values.yaml
  templates/*

/observability/
  prometheus-rules.yaml
  grafana-dashboards/
  otel-collector-config.yaml
```

---

## 14. Referências Internas a Melhorar

- Consolidar documentos README + IMPLEMENTATION_SUMMARY em uma “Architecture Overview”.
- Criar “SECURITY_MODEL.md” descrevendo RBAC + ACL + multi‑tenant boundaries.
- Criar “OPERATIONS_RUNBOOK.md” com DR, backup, incident response.

---

Se desejar, posso gerar agora as migrations completas, código base do `ResourceAclService` e o middleware de multi‑tenancy como ponto de partida.
