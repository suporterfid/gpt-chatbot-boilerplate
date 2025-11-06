# Prompt Builder Overview (Admin Guide)

## What is Prompt Builder?

Prompt Builder is an AI-powered specification generator that helps you create comprehensive, safe, and effective agent prompts from simple ideas. Instead of manually crafting detailed instructions, you describe what you want your agent to do, and the Prompt Builder generates a structured Markdown specification with built-in guardrails.

## Key Features

### 🤖 AI-Powered Generation
- Transforms short agent ideas into comprehensive specifications
- Uses GPT-4 to expand your concept into structured Markdown
- Generates consistent, well-formatted specifications

### 🛡️ Built-in Guardrails
- **Mandatory guardrails** (always included):
  - Hallucination Prevention: Prevents the agent from inventing facts
  - Scope Restriction: Keeps the agent within its defined domain
- **Optional guardrails** (selectable):
  - Data Privacy: Protects user PII and sensitive information
  - Custom guardrails: Add your own templates as needed

### 📝 Version Control
- Every generated specification is saved as a versioned prompt
- View, edit, and activate any previous version
- Compare different iterations of your agent's behavior

### 🔒 Security & Privacy
- PII redaction applied to stored prompts (configurable)
- Optional encryption at rest
- Audit logging for all generation and activation events
- Admin-only access with rate limiting

## How It Works

### 1. Describe Your Agent Idea

Navigate to the Agents page and click the **✨ Prompt Builder** button next to any agent. Enter a brief description:

```
A customer support agent that helps users with product questions,
handles refunds, and escalates complex issues to human agents.
```

### 2. Select Guardrails

Choose which guardrails to include. Mandatory guardrails are pre-selected and cannot be removed:

- ☑️ **Hallucination Prevention** (required)
- ☑️ **Scope Restriction** (required)
- ☐ Data Privacy (optional)

### 3. Generate Specification

Click **Generate Specification**. The AI will create a structured prompt with these sections:

1. **Role**: Clear description of the agent's purpose
2. **Audience**: Target users and use cases
3. **Capabilities**: What the agent can do
4. **Tone & Style**: Communication style
5. **Out-of-Scope**: What the agent should NOT do
6. **Guardrails**: Safety and scope constraints

### 4. Review & Edit

The generated specification appears in a markdown editor. You can:
- Edit the specification directly
- Toggle preview to see rendered output
- Save as a new version if you make changes

### 5. Activate

Click **Activate This Version** to make the generated prompt active. The agent will immediately start using this specification for all conversations.

## Generated Specification Structure

Here's an example of what Prompt Builder generates:

```markdown
# Agent Specification

## 1. Role
You are a customer support assistant specializing in product inquiries,
refund processing, and issue escalation.

## 2. Audience
End customers seeking help with products, orders, and account issues.

## 3. Capabilities
- Answer common product questions using the knowledge base
- Process refund requests following company policy
- Escalate complex issues to human agents
- Track order status and shipment information

## 4. Tone & Style
Professional, empathetic, and solution-oriented. Use clear language
and maintain a helpful attitude.

## 5. Out-of-Scope
- Technical troubleshooting beyond product usage
- Legal advice or contract interpretation
- Medical or health-related questions

## 6. Guardrails — Hallucination Prevention
- Do not invent facts. Answer only with verifiable information.
- If uncertain, explicitly state the uncertainty.

## 7. Guardrails — Scope Restriction
- Stay within customer support domain.
- Redirect out-of-scope questions appropriately.
```

## Version Management

### Listing Versions

In the Prompt Builder modal, scroll to the **Version History** section to see all saved versions:

| Version | Created | Guardrails | Status | Actions |
|---------|---------|-----------|--------|---------|
| v3 | 2024-11-06 14:23 | hallucination_prevention, scope_restriction | **Active** | View |
| v2 | 2024-11-06 13:10 | hallucination_prevention, scope_restriction, data_privacy | | View, Activate, Delete |
| v1 | 2024-11-06 12:05 | hallucination_prevention, scope_restriction | | View, Activate, Delete |

### Activating a Version

1. Click **Activate** next to any version
2. Confirm the activation
3. The agent immediately starts using that specification

### Deactivating

Click **Deactivate Current Prompt** to stop using generated prompts. The agent will fall back to its manually configured system_message.

### Deleting Versions

You cannot delete the currently active version. Deactivate it first, then delete.

## Manual Editing

You can manually create or edit specifications:

1. Generate an initial specification (or view an existing one)
2. Edit the markdown content
3. Click **Save as New Version**
4. The edited version becomes a new entry in version history

## Best Practices

### Writing Agent Ideas

**Good**:
```
A sales qualification agent that asks discovery questions,
scores leads based on budget/timeline/authority, and
schedules demos with qualified prospects.
```

**Too vague**:
```
A helpful agent.
```

**Too detailed**:
```
You are a sales agent. First ask about budget. Then ask about
timeline. Then ask about decision authority. Calculate a score...
```
*(If you already have this level of detail, just write the prompt manually)*

### Choosing Guardrails

- **Always use mandatory guardrails**: They prevent common AI failure modes
- **Add Data Privacy** for agents handling user information
- **Create custom guardrails** for domain-specific constraints (e.g., medical disclaimers, legal boundaries)

### Iterating on Specifications

1. Generate an initial version
2. Test it with real conversations
3. Identify gaps or issues
4. Generate a new version with refined description
5. Compare versions to find what works best

## Configuration

### Admin Settings

Prompt Builder is configured in `config.php`:

```php
'prompt_builder' => [
    'enabled' => true,                    // Feature toggle
    'model' => 'gpt-4o-mini',            // Model for generation
    'timeout_ms' => 20000,                // Generation timeout
    'default_guardrails' => [             // Always included
        'hallucination_prevention',
        'scope_restriction'
    ],
    'encryption_at_rest' => false,        // Encrypt stored prompts
    'rate_limit_per_min' => 10,           // Admin user rate limit
    'audit_enabled' => true,              // Log all actions
]
```

### Environment Variables

```bash
PROMPT_BUILDER_ENABLED=true
PROMPT_BUILDER_MODEL=gpt-4o-mini
PROMPT_BUILDER_TIMEOUT_MS=20000
PROMPT_BUILDER_DEFAULT_GUARDRAILS=hallucination_prevention,scope_restriction
PROMPT_BUILDER_ENCRYPTION=false
PROMPT_BUILDER_RATE_LIMIT=10
PROMPT_BUILDER_AUDIT=true
```

## Adding Custom Guardrails

Create a new YAML file in `includes/PromptBuilder/templates/guardrails/`:

```yaml
# my_custom_guardrail.yaml
key: my_custom_guardrail
title: Custom Business Rule
snippet: |
  ## Guardrails — Custom Business Rule
  - Never promise discounts over 20%
  - Always mention terms and conditions
  - Escalate requests for expedited shipping
meta:
  mandatory: false
  description: Enforces company pricing and shipping policies
  priority: 10
```

The guardrail will automatically appear in the UI selection list.

## Troubleshooting

### Generation Fails

**Error**: "Failed to generate prompt"

**Causes**:
- OpenAI API key invalid or expired
- Rate limit exceeded
- Network connectivity issue

**Solutions**:
- Verify `OPENAI_API_KEY` in `.env`
- Wait a minute and try again (rate limit)
- Check server logs for detailed error

### Version Not Found

**Error**: "Version not found"

**Causes**:
- Version was deleted
- Database migration not run

**Solutions**:
- Run database migrations: `php scripts/migrate.php`
- Check that `agent_prompts` table exists

### Prompt Not Activating

**Symptom**: Changes don't affect agent behavior

**Causes**:
- Wrong agent being tested
- Cache issue
- Database connection problem

**Solutions**:
- Verify you're testing the correct agent
- Clear browser cache and reload
- Check database connection in admin settings

## Integration with LeadSense

Prompt Builder works seamlessly with LeadSense (commercial opportunity detection):

1. When generating a specification for a sales agent, mention "lead qualification" in the idea
2. The AI will include relevant instructions for capturing intent signals
3. LeadSense processes conversations using the active prompt specification
4. Optionally create a "lead_qualification" guardrail for consistent behavior

## API Access

For programmatic access, see [Prompt Builder API Documentation](prompt_builder_api.md).

## Security Considerations

### Access Control
- Only admin users can access Prompt Builder
- RBAC permissions apply (create, read, update, delete)
- All actions are audited

### Data Protection
- PII redaction available (configurable)
- Encryption at rest supported
- Prompts stored in secure database

### Rate Limiting
- 10 generations per minute per admin user (default)
- Prevents abuse and controls costs
- Configurable via environment variable

## Support

For issues or questions:
- Check server logs: `logs/chatbot.log`
- Review audit trail: Admin UI → Audit Log
- Contact your system administrator

---

**Next Steps**: Review [API Documentation](prompt_builder_api.md) and [Guardrails Reference](prompt_builder_guardrails.md)
