# Terraform Infrastructure Examples

This directory contains Terraform configurations for provisioning infrastructure for the GPT Chatbot Boilerplate on various cloud providers.

## Available Providers

- **AWS** (`/aws`) - Complete infrastructure setup on Amazon Web Services
- **GCP** (`/gcp`) - Google Cloud Platform setup (basic example)

## Prerequisites

- [Terraform](https://www.terraform.io/downloads.html) >= 1.0
- Cloud provider CLI configured (AWS CLI, gcloud, etc.)
- Appropriate credentials and permissions

## Quick Start - AWS

### 1. Configure AWS Credentials

```bash
aws configure
# OR export environment variables
export AWS_ACCESS_KEY_ID="your-access-key"
export AWS_SECRET_ACCESS_KEY="your-secret-key"
export AWS_REGION="us-east-1"
```

### 2. Initialize Terraform

```bash
cd terraform/aws
terraform init
```

### 3. Create Variables File

```bash
cp terraform.tfvars.example terraform.tfvars
# Edit terraform.tfvars with your values
vi terraform.tfvars
```

### 4. Plan Infrastructure

```bash
terraform plan -out=tfplan
```

Review the plan output carefully to ensure it matches your expectations.

### 5. Apply Infrastructure

```bash
terraform apply tfplan
```

### 6. Save Outputs

```bash
terraform output > outputs.txt
```

Keep these outputs secure - they contain sensitive information like database endpoints.

## What Gets Created - AWS

### Networking
- VPC with public and private subnets across 3 availability zones
- Internet Gateway and NAT Gateways
- Route tables and security groups
- VPC Flow Logs

### Database
- RDS MySQL instance with:
  - Multi-AZ deployment (production)
  - Automated backups
  - Performance Insights
  - Enhanced monitoring
  - Encryption at rest

### Caching
- ElastiCache Redis cluster with:
  - Multi-node for high availability
  - Automatic failover
  - Encryption at rest and in transit
  - Authentication token

### Storage
- S3 buckets for:
  - File uploads (versioned, encrypted)
  - Backups (lifecycle policy, encrypted)
  - Public access blocked

### Security
- IAM roles for EC2/EKS instances
- Secrets Manager for sensitive data
- Security groups with least-privilege access

### Monitoring
- CloudWatch Log Groups
- RDS Enhanced Monitoring
- VPC Flow Logs

## Cost Estimation

### Development Environment (~$200-300/month)
- RDS: db.t3.medium (~$70/month)
- ElastiCache: cache.t3.medium (~$50/month)
- NAT Gateway (~$45/month)
- S3 storage (~$10/month)
- Data transfer (~$20/month)

### Production Environment (~$800-1200/month)
- RDS: db.t3.large Multi-AZ (~$300/month)
- ElastiCache: cache.t3.large x2 (~$200/month)
- NAT Gateway x3 (~$135/month)
- S3 storage (~$50/month)
- Data transfer (~$100/month)
- CloudWatch/monitoring (~$50/month)

Use [AWS Cost Calculator](https://calculator.aws) for accurate estimates.

## Managing Infrastructure

### View Current State

```bash
terraform show
```

### Update Infrastructure

```bash
# Edit variables or configuration
vi terraform.tfvars

# Plan changes
terraform plan

# Apply changes
terraform apply
```

### Destroy Infrastructure

**Warning**: This will delete all resources!

```bash
terraform destroy
```

## Best Practices

### 1. Remote State

Configure remote state for team collaboration:

```hcl
# main.tf
terraform {
  backend "s3" {
    bucket         = "your-terraform-state-bucket"
    key            = "chatbot/terraform.tfstate"
    region         = "us-east-1"
    encrypt        = true
    dynamodb_table = "terraform-lock"
  }
}
```

### 2. Workspaces

Use workspaces for multiple environments:

```bash
# Create workspace
terraform workspace new production
terraform workspace new staging

# Switch workspace
terraform workspace select production

# List workspaces
terraform workspace list
```

### 3. Secrets Management

**Never commit secrets to Git!**

Options:
1. Use environment variables
2. Use AWS Systems Manager Parameter Store
3. Use external secrets file (add to .gitignore)
4. Use Terraform Cloud/Enterprise

```bash
# Using environment variables
export TF_VAR_db_password="secure_password"
export TF_VAR_openai_api_key="sk-..."

terraform plan
```

### 4. Modular Design

Break large configurations into modules:

```
terraform/
├── modules/
│   ├── vpc/
│   ├── rds/
│   ├── redis/
│   └── s3/
└── environments/
    ├── dev/
    ├── staging/
    └── production/
```

### 5. Version Pinning

Pin provider versions to avoid breaking changes:

```hcl
terraform {
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "= 5.0.1"  # Exact version
    }
  }
}
```

## Integration with Kubernetes

After provisioning infrastructure, use outputs to configure Kubernetes:

```bash
# Get RDS endpoint
RDS_ENDPOINT=$(terraform output -raw rds_endpoint)

# Update Helm values
helm install chatbot ./helm/chatbot \
  --set env.DB_HOST=$RDS_ENDPOINT \
  --set env.DB_NAME=chatbot \
  --set-file secrets.dbPassword=<(terraform output -raw db_password)
```

## Troubleshooting

### State Locking Issues

```bash
# Force unlock (use carefully!)
terraform force-unlock <lock-id>
```

### Failed Apply

```bash
# Import existing resource
terraform import aws_s3_bucket.uploads chatbot-uploads-123456789012

# Refresh state
terraform refresh
```

### Debugging

```bash
# Enable detailed logging
export TF_LOG=DEBUG
terraform plan

# Disable logging
unset TF_LOG
```

## Security Considerations

### 1. Encrypt State Files

Always encrypt Terraform state files:

```hcl
backend "s3" {
  encrypt = true
  kms_key_id = "arn:aws:kms:us-east-1:123456789012:key/..."
}
```

### 2. Least Privilege IAM

Create minimal IAM policies for Terraform:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": [
        "ec2:*",
        "rds:*",
        "s3:*",
        "elasticache:*"
      ],
      "Resource": "*"
    }
  ]
}
```

### 3. Enable MFA Delete

For S3 buckets storing sensitive data:

```bash
aws s3api put-bucket-versioning \
  --bucket my-terraform-state \
  --versioning-configuration Status=Enabled,MFADelete=Enabled \
  --mfa "arn:aws:iam::123456789012:mfa/user 123456"
```

### 4. Regular Audits

```bash
# Check for drift
terraform plan -detailed-exitcode

# Exit code 2 means drift detected
```

## CI/CD Integration

Example GitHub Actions workflow:

```yaml
name: Terraform

on:
  push:
    branches: [main]
  pull_request:

jobs:
  terraform:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup Terraform
        uses: hashicorp/setup-terraform@v2
        with:
          terraform_version: 1.5.0
      
      - name: Terraform Init
        run: terraform init
        working-directory: ./terraform/aws
      
      - name: Terraform Plan
        run: terraform plan
        working-directory: ./terraform/aws
        env:
          TF_VAR_db_password: ${{ secrets.DB_PASSWORD }}
          TF_VAR_openai_api_key: ${{ secrets.OPENAI_API_KEY }}
      
      - name: Terraform Apply
        if: github.ref == 'refs/heads/main' && github.event_name == 'push'
        run: terraform apply -auto-approve
        working-directory: ./terraform/aws
```

## Support

- **Documentation**: See individual provider READMEs
- **Terraform Docs**: https://registry.terraform.io/providers/hashicorp/aws/latest/docs
- **Issues**: https://github.com/suporterfid/gpt-chatbot-boilerplate/issues

## License

See repository LICENSE file.
