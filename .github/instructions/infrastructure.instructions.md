---
applyTo: "{Dockerfile,docker-compose*.yml,helm/**/*,terraform/**/*,.github/workflows/*.yml}"
description: "Regras específicas para infraestrutura, CI/CD e deployment"
---

# Instruções para Infraestrutura e DevOps - gpt-chatbot-boilerplate

## Arquivos Alvo
- `Dockerfile` - Container definition
- `docker-compose*.yml` - Compose configurations
- `helm/**/*` - Kubernetes Helm charts
- `terraform/**/*` - Infrastructure as Code
- `.github/workflows/*.yml` - GitHub Actions CI/CD

## Filosofia DevOps

### Princípios
- **Infrastructure as Code**: Tudo versionado e reproduzível
- **Immutability**: Containers imutáveis, configuração via env vars
- **Observability**: Logging, metrics, tracing built-in
- **Security**: Least privilege, secrets management, scanning
- **Automation**: CI/CD completo, testes automatizados

## Docker

### Dockerfile Best Practices

#### Multi-Stage Builds
```dockerfile
# Build stage
FROM php:8.2-cli AS builder

WORKDIR /app

# Install dependencies
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader

# Copy source
COPY . .

# Runtime stage
FROM php:8.2-fpm-alpine

# Install only runtime dependencies
RUN apk add --no-cache \
    sqlite-libs \
    curl

# Copy from builder
COPY --from=builder /app /var/www/html

# Security: Run as non-root
RUN chown -R www-data:www-data /var/www/html
USER www-data

EXPOSE 9000

CMD ["php-fpm"]
```

#### Otimizações
- **Layer Caching**: Copiar dependency files antes do source code
- **Minimal Base Images**: Usar Alpine quando possível
- **.dockerignore**: Excluir arquivos desnecessários
- **Security Scanning**: Usar `docker scan` ou Trivy

#### .dockerignore
```
# Version control
.git
.gitignore

# Dependencies
node_modules
vendor

# Development
tests
*.md
.env.example

# Build artifacts
*.log
tmp/
.cache/

# IDE
.vscode
.idea
```

### Docker Compose

#### Estrutura de Serviços
```yaml
version: '3.8'

services:
  # Application
  app:
    build:
      context: .
      dockerfile: Dockerfile
      target: production
    environment:
      - DATABASE_URL=${DATABASE_URL}
      - OPENAI_API_KEY=${OPENAI_API_KEY}
    depends_on:
      - db
      - redis
    restart: unless-stopped
    networks:
      - chatbot-network
    volumes:
      - ./data:/var/www/html/data:rw

  # Database
  db:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
      MYSQL_DATABASE: ${DB_NAME}
      MYSQL_USER: ${DB_USER}
      MYSQL_PASSWORD: ${DB_PASSWORD}
    volumes:
      - db-data:/var/lib/mysql
      - ./db/migrations:/docker-entrypoint-initdb.d:ro
    restart: unless-stopped
    networks:
      - chatbot-network
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 10s
      timeout: 5s
      retries: 5

  # Web Server
  nginx:
    image: nginx:alpine
    ports:
      - "8088:80"
    volumes:
      - ./docker/nginx.conf:/etc/nginx/conf.d/default.conf:ro
      - ./public:/var/www/html/public:ro
    depends_on:
      - app
    restart: unless-stopped
    networks:
      - chatbot-network

  # Redis (opcional)
  redis:
    image: redis:7-alpine
    restart: unless-stopped
    networks:
      - chatbot-network

volumes:
  db-data:

networks:
  chatbot-network:
    driver: bridge
```

#### Health Checks
```yaml
healthcheck:
  test: ["CMD", "curl", "-f", "http://localhost/health"]
  interval: 30s
  timeout: 10s
  retries: 3
  start_period: 40s
```

#### Ambiente-Specific Configs
```bash
# Development
docker-compose up -d

# Production
docker-compose -f docker-compose.yml -f docker-compose.prod.yml up -d

# Testing
docker-compose -f docker-compose.test.yml run --rm tests
```

## Kubernetes / Helm

### Helm Chart Structure
```
helm/chatbot/
├── Chart.yaml
├── values.yaml
├── values-dev.yaml
├── values-prod.yaml
└── templates/
    ├── deployment.yaml
    ├── service.yaml
    ├── ingress.yaml
    ├── configmap.yaml
    ├── secret.yaml
    ├── hpa.yaml
    └── _helpers.tpl
```

### Deployment Best Practices

#### deployment.yaml
```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: {{ include "chatbot.fullname" . }}
  labels:
    {{- include "chatbot.labels" . | nindent 4 }}
spec:
  replicas: {{ .Values.replicaCount }}
  selector:
    matchLabels:
      {{- include "chatbot.selectorLabels" . | nindent 6 }}
  template:
    metadata:
      annotations:
        checksum/config: {{ include (print $.Template.BasePath "/configmap.yaml") . | sha256sum }}
      labels:
        {{- include "chatbot.selectorLabels" . | nindent 8 }}
    spec:
      securityContext:
        runAsNonRoot: true
        runAsUser: 1000
      containers:
      - name: {{ .Chart.Name }}
        image: "{{ .Values.image.repository }}:{{ .Values.image.tag }}"
        imagePullPolicy: {{ .Values.image.pullPolicy }}
        ports:
        - name: http
          containerPort: 8080
          protocol: TCP
        env:
        - name: DATABASE_URL
          valueFrom:
            secretKeyRef:
              name: {{ include "chatbot.fullname" . }}-secret
              key: database-url
        - name: OPENAI_API_KEY
          valueFrom:
            secretKeyRef:
              name: {{ include "chatbot.fullname" . }}-secret
              key: openai-api-key
        livenessProbe:
          httpGet:
            path: /health
            port: http
          initialDelaySeconds: 30
          periodSeconds: 10
        readinessProbe:
          httpGet:
            path: /ready
            port: http
          initialDelaySeconds: 5
          periodSeconds: 5
        resources:
          {{- toYaml .Values.resources | nindent 12 }}
```

#### HPA (Horizontal Pod Autoscaler)
```yaml
apiVersion: autoscaling/v2
kind: HorizontalPodAutoscaler
metadata:
  name: {{ include "chatbot.fullname" . }}
spec:
  scaleTargetRef:
    apiVersion: apps/v1
    kind: Deployment
    name: {{ include "chatbot.fullname" . }}
  minReplicas: {{ .Values.autoscaling.minReplicas }}
  maxReplicas: {{ .Values.autoscaling.maxReplicas }}
  metrics:
  - type: Resource
    resource:
      name: cpu
      target:
        type: Utilization
        averageUtilization: {{ .Values.autoscaling.targetCPUUtilizationPercentage }}
  - type: Resource
    resource:
      name: memory
      target:
        type: Utilization
        averageUtilization: {{ .Values.autoscaling.targetMemoryUtilizationPercentage }}
```

### Secrets Management
```yaml
# NUNCA commitar secrets em values.yaml
# Usar uma das opções:

# 1. External Secrets Operator
apiVersion: external-secrets.io/v1beta1
kind: ExternalSecret
metadata:
  name: chatbot-secrets
spec:
  secretStoreRef:
    name: aws-secrets-manager
  target:
    name: chatbot-secret
  data:
  - secretKey: openai-api-key
    remoteRef:
      key: prod/chatbot/openai-api-key

# 2. Sealed Secrets
# Encrypt: kubeseal < secret.yaml > sealed-secret.yaml
# Commit sealed-secret.yaml

# 3. Helm Secrets Plugin
# helm secrets install chatbot ./helm/chatbot -f secrets.yaml
```

## Terraform

### Structure
```
terraform/
├── main.tf
├── variables.tf
├── outputs.tf
├── versions.tf
├── modules/
│   ├── vpc/
│   ├── ecs/
│   └── rds/
└── environments/
    ├── dev/
    ├── staging/
    └── prod/
```

### Best Practices

#### State Management
```hcl
# backend.tf
terraform {
  backend "s3" {
    bucket         = "chatbot-terraform-state"
    key            = "prod/terraform.tfstate"
    region         = "us-east-1"
    encrypt        = true
    dynamodb_table = "terraform-lock"
  }
}
```

#### Module Example
```hcl
# modules/ecs/main.tf
resource "aws_ecs_cluster" "main" {
  name = var.cluster_name

  setting {
    name  = "containerInsights"
    value = "enabled"
  }

  tags = var.tags
}

resource "aws_ecs_service" "chatbot" {
  name            = "chatbot-service"
  cluster         = aws_ecs_cluster.main.id
  task_definition = aws_ecs_task_definition.chatbot.arn
  desired_count   = var.desired_count
  launch_type     = "FARGATE"

  network_configuration {
    subnets          = var.private_subnet_ids
    security_groups  = [aws_security_group.chatbot.id]
    assign_public_ip = false
  }

  load_balancer {
    target_group_arn = var.target_group_arn
    container_name   = "chatbot"
    container_port   = 8080
  }
}
```

#### Variables
```hcl
# variables.tf
variable "environment" {
  description = "Environment name (dev, staging, prod)"
  type        = string
}

variable "openai_api_key" {
  description = "OpenAI API Key"
  type        = string
  sensitive   = true
}

variable "instance_type" {
  description = "EC2 instance type"
  type        = string
  default     = "t3.medium"
}
```

## CI/CD (GitHub Actions)

### Workflow Structure
```yaml
name: CI/CD Pipeline

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main]

env:
  PHP_VERSION: '8.2'
  NODE_VERSION: '18'

jobs:
  # Linting and Static Analysis
  lint:
    name: Lint & Static Analysis
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ env.PHP_VERSION }}
          extensions: pdo, pdo_sqlite, curl, json
          
      - name: Install Composer dependencies
        run: composer install --prefer-dist --no-progress
        
      - name: Run PHPStan
        run: composer run analyze
        
      - name: Setup Node
        uses: actions/setup-node@v3
        with:
          node-version: ${{ env.NODE_VERSION }}
          
      - name: Install npm dependencies
        run: npm ci
        
      - name: Run ESLint
        run: npm run lint

  # Unit and Integration Tests
  test:
    name: Tests
    runs-on: ubuntu-latest
    needs: lint
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ env.PHP_VERSION }}
          
      - name: Install dependencies
        run: composer install --prefer-dist
        
      - name: Run migrations
        run: php scripts/run_migrations.php
        
      - name: Run tests
        run: php tests/run_tests.php
        
      - name: Run smoke tests
        run: bash scripts/smoke_test.sh

  # Security Scanning
  security:
    name: Security Scan
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Run Trivy vulnerability scanner
        uses: aquasecurity/trivy-action@master
        with:
          scan-type: 'fs'
          scan-ref: '.'
          format: 'sarif'
          output: 'trivy-results.sarif'
          
      - name: Upload Trivy results to GitHub Security
        uses: github/codeql-action/upload-sarif@v2
        with:
          sarif_file: 'trivy-results.sarif'

  # Build Docker Image
  build:
    name: Build Docker Image
    runs-on: ubuntu-latest
    needs: [test, security]
    if: github.ref == 'refs/heads/main'
    steps:
      - uses: actions/checkout@v3
      
      - name: Set up Docker Buildx
        uses: docker/setup-buildx-action@v2
        
      - name: Login to Container Registry
        uses: docker/login-action@v2
        with:
          registry: ghcr.io
          username: ${{ github.actor }}
          password: ${{ secrets.GITHUB_TOKEN }}
          
      - name: Build and push
        uses: docker/build-push-action@v4
        with:
          context: .
          push: true
          tags: |
            ghcr.io/${{ github.repository }}:latest
            ghcr.io/${{ github.repository }}:${{ github.sha }}
          cache-from: type=gha
          cache-to: type=gha,mode=max

  # Deploy to Staging
  deploy-staging:
    name: Deploy to Staging
    runs-on: ubuntu-latest
    needs: build
    environment: staging
    steps:
      - uses: actions/checkout@v3
      
      - name: Configure AWS credentials
        uses: aws-actions/configure-aws-credentials@v2
        with:
          aws-access-key-id: ${{ secrets.AWS_ACCESS_KEY_ID }}
          aws-secret-access-key: ${{ secrets.AWS_SECRET_ACCESS_KEY }}
          aws-region: us-east-1
          
      - name: Deploy to ECS
        run: |
          aws ecs update-service \
            --cluster chatbot-staging \
            --service chatbot-service \
            --force-new-deployment
            
      - name: Wait for deployment
        run: |
          aws ecs wait services-stable \
            --cluster chatbot-staging \
            --services chatbot-service

  # Deploy to Production (manual approval)
  deploy-production:
    name: Deploy to Production
    runs-on: ubuntu-latest
    needs: deploy-staging
    environment: production
    steps:
      - uses: actions/checkout@v3
      
      - name: Deploy to Production
        run: |
          # Production deployment steps
          echo "Deploying to production..."
```

### Secrets Management em CI
```yaml
# Usar GitHub Secrets
env:
  OPENAI_API_KEY: ${{ secrets.OPENAI_API_KEY }}
  DATABASE_URL: ${{ secrets.DATABASE_URL }}

# Nunca logar secrets
- name: Test API
  run: |
    # ERRADO: echo "Key: $OPENAI_API_KEY"
    # CORRETO:
    echo "Testing API connection..."
    php test_api.php
```

### Caching
```yaml
- name: Cache Composer dependencies
  uses: actions/cache@v3
  with:
    path: vendor
    key: ${{ runner.os }}-composer-${{ hashFiles('**/composer.lock') }}
    restore-keys: |
      ${{ runner.os }}-composer-

- name: Cache npm dependencies
  uses: actions/cache@v3
  with:
    path: node_modules
    key: ${{ runner.os }}-node-${{ hashFiles('**/package-lock.json') }}
```

## Monitoring e Observability

### Prometheus Metrics
```yaml
# prometheus/prometheus.yml
global:
  scrape_interval: 15s
  evaluation_interval: 15s

scrape_configs:
  - job_name: 'chatbot'
    static_configs:
      - targets: ['chatbot:9090']
    metrics_path: '/metrics.php'
```

### Grafana Dashboards
```json
{
  "dashboard": {
    "title": "Chatbot Metrics",
    "panels": [
      {
        "title": "Request Rate",
        "targets": [
          {
            "expr": "rate(http_requests_total[5m])"
          }
        ]
      }
    ]
  }
}
```

## Security

### Container Security
```dockerfile
# Scan de vulnerabilidades
RUN apk add --no-cache --upgrade \
    && rm -rf /var/cache/apk/*

# Non-root user
RUN addgroup -g 1000 appuser && \
    adduser -D -u 1000 -G appuser appuser
USER appuser

# Read-only filesystem
VOLUME /tmp
```

### Network Policies (K8s)
```yaml
apiVersion: networking.k8s.io/v1
kind: NetworkPolicy
metadata:
  name: chatbot-network-policy
spec:
  podSelector:
    matchLabels:
      app: chatbot
  policyTypes:
  - Ingress
  - Egress
  ingress:
  - from:
    - podSelector:
        matchLabels:
          app: nginx
    ports:
    - protocol: TCP
      port: 8080
  egress:
  - to:
    - podSelector:
        matchLabels:
          app: mysql
    ports:
    - protocol: TCP
      port: 3306
```

## Checklist de Revisão

Antes de commitar mudanças de infraestrutura:

- [ ] Dockerfile otimizado (multi-stage, layer caching)
- [ ] .dockerignore configurado
- [ ] Health checks implementados
- [ ] Secrets não hardcoded
- [ ] Resources limits definidos
- [ ] Security context configurado
- [ ] Logging configurado
- [ ] Metrics expostos
- [ ] Documentação atualizada
- [ ] Testado localmente
- [ ] CI/CD pipeline passa
- [ ] Backup strategy considerada
- [ ] Rollback strategy definida
