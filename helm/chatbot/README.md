# GPT Chatbot Boilerplate - Helm Chart

This Helm chart deploys the GPT Chatbot Boilerplate application on a Kubernetes cluster.

## Prerequisites

- Kubernetes 1.19+
- Helm 3.0+
- PersistentVolume provisioner support in the underlying infrastructure
- MySQL database (can be deployed with this chart or use external)
- Redis (optional, for caching and rate limiting)

## Installation

### Quick Start

1. **Add secrets** (create `secrets.yaml`):

```yaml
secrets:
  openaiApiKey: "<base64-encoded-openai-key>"
  dbPassword: "<base64-encoded-db-password>"
  adminToken: "<base64-encoded-admin-token>"
```

2. **Install the chart**:

```bash
helm install chatbot ./helm/chatbot \
  -f helm/chatbot/values.yaml \
  -f secrets.yaml \
  --namespace chatbot \
  --create-namespace
```

### Custom Installation

Create a `custom-values.yaml` file:

```yaml
ingress:
  hosts:
    - host: your-domain.com
      paths:
        - path: /
          pathType: Prefix
  tls:
    - secretName: your-tls-secret
      hosts:
        - your-domain.com

env:
  OPENAI_API_KEY: "your-key-here"
  DB_HOST: "your-mysql-host"
  DB_NAME: "your-database"
  DB_USER: "your-user"
  DB_PASSWORD: "your-password"

mysql:
  enabled: false  # Use external database

resources:
  limits:
    cpu: 2000m
    memory: 4Gi
  requests:
    cpu: 1000m
    memory: 2Gi
```

Install with custom values:

```bash
helm install chatbot ./helm/chatbot \
  -f custom-values.yaml \
  --namespace chatbot
```

## Configuration

### Key Parameters

| Parameter | Description | Default |
|-----------|-------------|---------|
| `replicaCount` | Number of application replicas | `2` |
| `image.repository` | Container image repository | `ghcr.io/suporterfid/gpt-chatbot-boilerplate` |
| `image.tag` | Container image tag | `latest` |
| `service.type` | Kubernetes service type | `ClusterIP` |
| `ingress.enabled` | Enable ingress controller resource | `true` |
| `ingress.className` | Ingress class name | `nginx` |
| `persistence.enabled` | Enable persistent storage | `true` |
| `persistence.size` | Size of persistent volume | `10Gi` |
| `autoscaling.enabled` | Enable horizontal pod autoscaling | `true` |
| `autoscaling.minReplicas` | Minimum number of replicas | `2` |
| `autoscaling.maxReplicas` | Maximum number of replicas | `10` |
| `mysql.enabled` | Deploy MySQL database | `true` |
| `redis.enabled` | Deploy Redis cache | `true` |
| `worker.enabled` | Enable background worker | `true` |
| `backup.enabled` | Enable automated backups | `true` |

### Environment Variables

See `values.yaml` for complete list of environment variables.

## Upgrading

```bash
# Upgrade to new version
helm upgrade chatbot ./helm/chatbot \
  -f custom-values.yaml \
  --namespace chatbot

# Rollback to previous version
helm rollback chatbot --namespace chatbot
```

## Scaling

### Manual Scaling

```bash
kubectl scale deployment chatbot --replicas=5 -n chatbot
```

### Autoscaling

Autoscaling is enabled by default. Adjust parameters in `values.yaml`:

```yaml
autoscaling:
  enabled: true
  minReplicas: 2
  maxReplicas: 10
  targetCPUUtilizationPercentage: 70
  targetMemoryUtilizationPercentage: 80
```

## Monitoring

### Prometheus Integration

The chart includes ServiceMonitor and PrometheusRule resources for Prometheus Operator:

```yaml
monitoring:
  enabled: true
  serviceMonitor:
    enabled: true
    interval: 30s
  prometheusRule:
    enabled: true
```

### Grafana Dashboards

Import dashboards from `/observability/dashboards/` directory.

## Backup and Recovery

### Automated Backups

Enabled by default via CronJob:

```yaml
backup:
  enabled: true
  schedule: "0 2 * * *"  # Daily at 2 AM
  retention: 7  # days
```

### Manual Backup

```bash
kubectl create job --from=cronjob/chatbot-backup manual-backup-$(date +%Y%m%d) -n chatbot
```

### Restore from Backup

```bash
# Copy backup to pod
kubectl cp backup.tar.gz chatbot-pod:/tmp/backup.tar.gz -n chatbot

# Exec into pod and restore
kubectl exec -it chatbot-pod -n chatbot -- bash
cd /tmp
./scripts/restore_all.sh backup.tar.gz
```

## Troubleshooting

### View Logs

```bash
# Application logs
kubectl logs -f deployment/chatbot -n chatbot

# Worker logs
kubectl logs -f deployment/chatbot-worker -n chatbot

# Previous logs (if crashed)
kubectl logs -p deployment/chatbot -n chatbot
```

### Check Pod Status

```bash
kubectl get pods -n chatbot
kubectl describe pod <pod-name> -n chatbot
```

### Database Connection Issues

```bash
# Test database connectivity
kubectl exec -it deployment/chatbot -n chatbot -- php tests/test_db_connection.php

# Check MySQL pod
kubectl logs -f statefulset/chatbot-mysql -n chatbot
```

### Ingress Issues

```bash
# Check ingress
kubectl get ingress -n chatbot
kubectl describe ingress chatbot -n chatbot

# Check ingress controller logs
kubectl logs -f -n ingress-nginx deployment/ingress-nginx-controller
```

## Security

### Secrets Management

**Never commit secrets to Git!**

Use one of these approaches:

1. **Kubernetes Secrets** (created separately):
```bash
kubectl create secret generic chatbot-secrets \
  --from-literal=openai-api-key=sk-... \
  --from-literal=db-password=... \
  --from-literal=admin-token=... \
  -n chatbot
```

2. **External Secrets Operator**:
```yaml
apiVersion: external-secrets.io/v1beta1
kind: ExternalSecret
metadata:
  name: chatbot-secrets
spec:
  secretStoreRef:
    name: aws-secrets-manager
    kind: SecretStore
  target:
    name: chatbot-secrets
  data:
    - secretKey: openai-api-key
      remoteRef:
        key: chatbot/openai-api-key
```

3. **Sealed Secrets**:
```bash
kubeseal --format=yaml < secrets.yaml > sealed-secrets.yaml
kubectl apply -f sealed-secrets.yaml
```

### Network Policies

Apply network policies to restrict traffic:

```yaml
apiVersion: networking.k8s.io/v1
kind: NetworkPolicy
metadata:
  name: chatbot-netpol
spec:
  podSelector:
    matchLabels:
      app.kubernetes.io/name: chatbot
  policyTypes:
    - Ingress
    - Egress
  ingress:
    - from:
        - podSelector:
            matchLabels:
              app.kubernetes.io/name: nginx-ingress
  egress:
    - to:
        - podSelector:
            matchLabels:
              app.kubernetes.io/name: mysql
```

## High Availability

### Multi-Region Setup

For disaster recovery, deploy to multiple regions:

```bash
# Deploy to region 1
helm install chatbot ./helm/chatbot \
  --namespace chatbot \
  --kube-context region1

# Deploy to region 2 with replication
helm install chatbot ./helm/chatbot \
  --namespace chatbot \
  --kube-context region2 \
  --set mysql.replication.enabled=true
```

### Database Replication

Configure MySQL replication in `values.yaml`:

```yaml
mysql:
  architecture: replication
  replication:
    enabled: true
  secondary:
    replicaCount: 2
```

## Performance Tuning

### Resource Limits

Adjust based on load:

```yaml
resources:
  limits:
    cpu: 2000m
    memory: 4Gi
  requests:
    cpu: 1000m
    memory: 2Gi

worker:
  resources:
    limits:
      cpu: 1000m
      memory: 2Gi
    requests:
      cpu: 500m
      memory: 1Gi
```

### Database Optimization

```yaml
mysql:
  primary:
    configuration: |-
      [mysqld]
      max_connections=500
      innodb_buffer_pool_size=2G
      innodb_log_file_size=512M
```

### Caching

Enable Redis for better performance:

```yaml
redis:
  enabled: true
  master:
    persistence:
      size: 5Gi
```

## Cost Optimization

### Spot Instances

Use spot instances for non-critical workloads:

```yaml
nodeSelector:
  node.kubernetes.io/instance-type: spot

tolerations:
  - key: "node.kubernetes.io/unreliable"
    operator: "Exists"
    effect: "NoSchedule"
```

### Resource Requests

Set appropriate requests to avoid over-provisioning:

```yaml
resources:
  requests:
    cpu: 100m  # Minimum required
    memory: 256Mi
```

## Uninstallation

```bash
helm uninstall chatbot --namespace chatbot

# Delete persistent volumes (if needed)
kubectl delete pvc -l app.kubernetes.io/name=chatbot -n chatbot

# Delete namespace
kubectl delete namespace chatbot
```

## Support

- **Documentation**: See `/docs` directory
- **Issues**: https://github.com/suporterfid/gpt-chatbot-boilerplate/issues
- **Community**: [TBD]

## License

See repository LICENSE file.
