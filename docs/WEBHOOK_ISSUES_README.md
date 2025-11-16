# Webhook Infrastructure Implementation - Issue Creation Guide

This directory contains all the files needed to create GitHub issues for implementing the webhook infrastructure as specified in `docs/SPEC_WEBHOOK.md`.

## 📁 Files Generated

### Issue Templates
- **`docs/webhook-issues/*.md`** - Individual markdown files for each issue (23 total)
- **`docs/webhook-issues.json`** - JSON export of all issues for programmatic access
- **`scripts/create_webhook_issues.sh`** - Shell script to bulk-create all issues using GitHub CLI

### Documentation
- **`docs/WEBHOOK_IMPLEMENTATION_ISSUES.md`** - Comprehensive documentation of all issues
- **`scripts/create_webhook_issues.py`** - Python script that generates all the above files

## 🚀 Quick Start

### Option 1: Using GitHub CLI (Recommended)

If you have the [GitHub CLI](https://cli.github.com/) installed and authenticated:

```bash
cd /path/to/gpt-chatbot-boilerplate
./scripts/create_webhook_issues.sh
```

This will create all 23 issues in the repository with proper labels and descriptions.

### Option 2: Manual Creation via Web UI

1. Navigate to https://github.com/suporterfid/gpt-chatbot-boilerplate/issues/new
2. Open the issue template from `docs/webhook-issues/` directory
3. Copy the title from the script or documentation
4. Copy the description from the `.md` file
5. Add the appropriate labels (e.g., `task`, `webhook`, `phase-1`)
6. Click "Submit new issue"
7. Repeat for each issue

### Option 3: Using GitHub API

Use the JSON export for programmatic creation:

```bash
# Example using curl and jq
cat docs/webhook-issues.json | jq -c '.[]' | while read issue; do
  title=$(echo $issue | jq -r '.title')
  labels=$(echo $issue | jq -r '.labels | join(",")')
  body=$(echo $issue | jq -r '.description')
  
  gh issue create \
    --repo "suporterfid/gpt-chatbot-boilerplate" \
    --title "$title" \
    --label "$labels" \
    --body "$body"
done
```

### Option 4: Using GitHub Actions

Create a workflow file `.github/workflows/create-webhook-issues.yml`:

```yaml
name: Create Webhook Issues

on:
  workflow_dispatch:

jobs:
  create-issues:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Create issues
        env:
          GH_TOKEN: ${{ github.token }}
        run: |
          chmod +x ./scripts/create_webhook_issues.sh
          ./scripts/create_webhook_issues.sh
```

Then trigger it manually from the Actions tab.

## 📋 Issue Breakdown

### Phase 1: Inbound Webhook Infrastructure (3 issues)
- **wh-001a**: Bootstrap /webhook/inbound entrypoint
- **wh-001b**: Implement WebhookGateway orchestration service
- **wh-001c**: Connect gateway to agent/queue pipeline

### Phase 2: Security Service (2 issues)
- **wh-002a**: Build WebhookSecurityService
- **wh-002b**: Adopt shared security in all inbound routes

### Phase 3: Database & Repository Layer (3 issues)
- **wh-003a**: Author webhook_subscribers migrations (SQLite/MySQL/PostgreSQL)
- **wh-003b**: Implement WebhookSubscriberRepository
- **wh-003c**: Extend admin API/UI for subscriber management

### Phase 4: Logging Infrastructure (3 issues)
- **wh-004a**: Add webhook_logs migrations (SQLite/MySQL/PostgreSQL)
- **wh-004b**: Implement WebhookLogRepository
- **wh-004c**: Surface delivery history in observability/admin UI

### Phase 5: Outbound Dispatcher (3 issues)
- **wh-005a**: Implement WebhookDispatcher core service
- **wh-005b**: Refactor worker job handler for subscriber-aware deliveries
- **wh-005c**: Migrate existing webhook callers to dispatcher

### Phase 6: Retry Logic (2 issues)
- **wh-006a**: Extend JobQueue for attempt/backoff metadata
- **wh-006b**: Implement six-step exponential retry scheduler

### Phase 7: Configuration (2 issues)
- **wh-007a**: Add webhooks configuration block
- **wh-007b**: Document new config in .env.example and deployment guides

### Phase 8: Extensibility (3 issues)
- **wh-008a**: Introduce payload-transform and queue hooks
- **wh-008b**: Build webhook sandbox/testing utilities
- **wh-008c**: Enhance observability dashboards for webhook metrics

### Phase 9: Testing (2 issues)
- **wh-009a**: Add PHPUnit tests for inbound gateway & security
- **wh-009b**: Add PHPUnit tests for dispatcher, retries, and logging

## 🏷️ Labels Used

The following labels are used to categorize the issues:

- `task` - Implementation tasks
- `enhancement` - Feature enhancements
- `webhook` - Webhook-related work
- `security` - Security features
- `database` - Database migrations
- `repository` - Repository pattern implementations
- `admin` - Admin API/UI work
- `ui` - User interface changes
- `observability` - Monitoring and metrics
- `dispatcher` - Webhook dispatcher
- `worker` - Background worker
- `refactor` - Code refactoring
- `queue` - Job queue system
- `retry` - Retry logic
- `config` - Configuration
- `documentation` - Documentation updates
- `extensibility` - Extensibility features
- `testing` - Test implementation
- `phase-1` through `phase-9` - Implementation phases

## 📖 Implementation Order

The issues are organized in phases that should generally be implemented in order:

1. **Phase 1-2**: Core inbound infrastructure and security
2. **Phase 3-4**: Database layer and logging
3. **Phase 5-6**: Outbound dispatcher and retry logic
4. **Phase 7**: Configuration
5. **Phase 8**: Extensibility features
6. **Phase 9**: Comprehensive testing

However, some phases can be worked on in parallel by different team members.

## 🔗 Related Documentation

- **`docs/SPEC_WEBHOOK.md`** - Webhook specification
- **`docs/WEBHOOK_IMPLEMENTATION_ISSUES.md`** - Detailed issue descriptions
- **`docs/PHASE3_WORKERS_WEBHOOKS.md`** - Phase 3 implementation notes
- **`README.md`** - Main project documentation

## 🛠️ Regenerating Issue Files

If you need to regenerate the issue files (e.g., after updating the specification):

```bash
cd scripts
python3 create_webhook_issues.py
```

This will regenerate all markdown files, the shell script, and the JSON export.

## 📝 Customization

### Adding New Issues

Edit `scripts/create_webhook_issues.py` and add new issue definitions to the `issues` array:

```python
{
    "tag": "wh-010a-task",
    "title": "Your New Issue Title",
    "labels": ["task", "webhook"],
    "description": """Your issue description here..."""
}
```

Then run the script to regenerate all files.

### Modifying Existing Issues

1. Edit the issue definition in `scripts/create_webhook_issues.py`
2. Run `python3 create_webhook_issues.py` to regenerate files
3. If issues were already created in GitHub, you'll need to update them manually or close and recreate

## 🤝 Contributing

When implementing these issues:

1. Reference the issue number in commit messages
2. Update the CHANGELOG.md with your changes
3. Add tests for new functionality
4. Update relevant documentation
5. Follow the existing code style and patterns

## ❓ FAQ

**Q: Do I need to create all issues at once?**  
A: No, you can create them phase by phase or as needed.

**Q: Can I modify the issue descriptions?**  
A: Yes, the generated files are templates. Feel free to adapt them to your needs.

**Q: What if I don't have GitHub CLI?**  
A: Use Option 2 (manual creation) or Option 3 (GitHub API with curl).

**Q: Can I create issues in a different repository?**  
A: Yes, edit the `REPO` variable in `create_webhook_issues.sh` or use the `--repo` flag with `gh issue create`.

## 📞 Support

For questions about:
- **Webhook specification**: See `docs/SPEC_WEBHOOK.md`
- **Implementation guidance**: See individual issue descriptions
- **Project setup**: See main `README.md`
