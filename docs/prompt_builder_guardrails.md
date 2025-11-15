# Prompt Builder Guardrails Reference

## Overview

Guardrails are predefined templates that inject safety and behavioral constraints into generated agent specifications. They ensure agents behave predictably, safely, and within defined boundaries.

## Built-in Guardrails

### Hallucination Prevention

**Key**: `hallucination_prevention`  
**Status**: Mandatory (always included)  
**Priority**: 1

**Purpose**: Prevents the AI from generating false, unverifiable, or fabricated information.

**Generated Section**:
```markdown
## Guardrails — Hallucination Prevention
- Do not invent facts. Answer only with verifiable information.
- If uncertain or lacking context, explicitly state the uncertainty and ask a short clarifying question.
- Prefer citing provided documents or retrieved context; avoid speculation.
```

**Use Cases**:
- Customer support (product specifications)
- Legal/compliance agents (factual accuracy required)
- Educational agents (preventing misinformation)
- Any agent where accuracy is critical

**Example Impact**:

❌ Without guardrail:
> User: "What's the warranty on Product X?"  
> Agent: "Product X has a 5-year warranty with extended coverage options."  
> *(Invented - actual warranty is 1 year)*

✅ With guardrail:
> User: "What's the warranty on Product X?"  
> Agent: "I don't have the warranty information for Product X in my knowledge base. Let me look that up for you or connect you with someone who can help."

---

### Scope Restriction

**Key**: `scope_restriction`  
**Status**: Mandatory (always included)  
**Priority**: 2

**Purpose**: Keeps the agent strictly within its defined domain and role.

**Generated Section**:
```markdown
## Guardrails — Scope Restriction
- Stay strictly within the defined domain and objectives of this agent.
- If asked out-of-scope questions, respond:
  "Sorry, I'm not able to answer that — it's outside my defined area."
```

**Use Cases**:
- Specialized agents (prevent scope creep)
- Customer-facing agents (avoid liability)
- Domain-specific bots (maintain expertise)

**Example Impact**:

❌ Without guardrail:
> User: "Can you help me with my taxes?"  
> *Customer support agent*: "Sure! For your 2023 taxes, you should..."  
> *(Providing tax advice outside scope)*

✅ With guardrail:
> User: "Can you help me with my taxes?"  
> *Customer support agent*: "Sorry, I'm not able to answer that — it's outside my defined area. I'm here to help with product questions and orders. For tax questions, please consult a tax professional."

---

### Data Privacy

**Key**: `data_privacy`
**Status**: Optional (recommended for user-facing agents)
**Priority**: 3

**Purpose**: Protects user privacy and prevents the agent from requesting, storing, or disclosing sensitive information.

**Generated Section**:
```markdown
## Guardrails — Data Privacy
- Do not request, store, or disclose personal, medical, or financial information.
- Mask or omit any sensitive data encountered in conversation.
- Remind users not to share sensitive information if they attempt to do so.
```

**Use Cases**:
- Public-facing chatbots
- Agents handling user inquiries
- Support bots (pre-authentication)
- Any agent with privacy requirements

**Example Impact**:

❌ Without guardrail:
> User: "My credit card number is 1234-5678-9012-3456"  
> Agent: "Thanks! I've recorded your credit card 1234-5678-9012-3456..."

✅ With guardrail:
> User: "My credit card number is 1234-5678-9012-3456"
> Agent: "Please don't share sensitive information like credit card numbers in this chat. For payment issues, please use our secure payment portal or call our support line."

---

### Language Support

**Key**: `language_support`
**Status**: Optional (pair with mandatory guardrails)
**Priority**: 2
**Variables**:
- `supported_languages` (comma-separated list, e.g., `Portuguese, English, Spanish`)
- `default_language` (single fallback language, e.g., `Portuguese`)

**Purpose**: Restricts answers to approved languages and defines how the agent responds when the user switches languages or requests an unsupported one.

**Generated Section**:
```markdown
## Guardrails — Language Support
- Respond in the user's language whenever it is listed in {{supported_languages}}.
- Supported languages: {{supported_languages}}
- Default to {{default_language}} for narration, clarifications, or when the user's request is outside the supported languages.
- If a user requests an unsupported language, politely list the supported options and continue in {{default_language}}.
```

**Use Cases**:
- Multilingual customer support desks
- Global onboarding or training bots
- Agents that must stay compliant with localized messaging policies

**Implementation Tips**:
- Always populate both variables in the Prompt Builder UI before generating the prompt. Leaving them blank results in literal `{{supported_languages}}` placeholders.
- API-based flows should pass `"guardrails": ["language_support", ...]` plus a matching `variables` object. Align the request-level `language` (e.g., `"language": "pt"`) with your default to ensure any surrounding narration is emitted in the same language.

---

## Custom Guardrails

You can create custom guardrails for domain-specific constraints.

### File Location

```
includes/PromptBuilder/templates/guardrails/your_guardrail.yaml
```

### Template Format

```yaml
key: your_guardrail_key
title: Guardrail Display Name
snippet: |
  ## Guardrails — Your Guardrail Name
  - First constraint or rule
  - Second constraint or rule
  - Third constraint or rule
meta:
  mandatory: false          # true = always included, false = optional
  description: Brief description shown in UI
  priority: 10             # Lower = appears first in UI (1-999)
```

### Example: Medical Disclaimer

```yaml
key: medical_disclaimer
title: Medical Disclaimer
snippet: |
  ## Guardrails — Medical Disclaimer
  - You are not a licensed medical professional
  - Do not provide medical diagnoses or treatment recommendations
  - Always advise users to consult qualified healthcare providers for medical concerns
  - General health information only; not a substitute for professional advice
meta:
  mandatory: true
  description: Prevents providing medical advice or diagnoses
  priority: 4
```

### Example: Pricing Authority

```yaml
key: pricing_authority
title: Pricing Authority Limits
snippet: |
  ## Guardrails — Pricing Authority
  - Quote only published prices from the price list
  - Maximum discount you can approve: {{max_discount}}%
  - For larger discounts, escalate to: {{escalation_contact}}
  - Never promise pricing without current data
meta:
  mandatory: false
  description: Enforces pricing policy and discount limits
  priority: 8
```

**With Variables**:
When generating, provide variables in the API call:

```json
{
  "idea_text": "A sales agent...",
  "guardrails": ["pricing_authority"],
  "variables": {
    "max_discount": "15",
    "escalation_contact": "sales manager"
  }
}
```

The generated output will include:
```markdown
## Guardrails — Pricing Authority
- Quote only published prices from the price list
- Maximum discount you can approve: 15%
- For larger discounts, escalate to: sales manager
- Never promise pricing without current data
```

---

## Guardrail Design Guidelines

### 1. Be Specific and Actionable

❌ Vague:
```yaml
snippet: |
  ## Guardrails — Be Helpful
  - Help users effectively
  - Provide good service
```

✅ Specific:
```yaml
snippet: |
  ## Guardrails — Response Quality
  - Provide complete answers with 2-3 supporting details
  - Offer next steps or follow-up questions
  - Link to relevant documentation when available
```

### 2. Use Bullet Points

Guardrails should be scannable. Use concise bullet points instead of paragraphs.

### 3. Focus on Constraints

Guardrails define what NOT to do or WHERE boundaries are, not general instructions.

❌ General instruction:
```yaml
snippet: |
  ## Guardrails — Communication Style
  - Be polite and professional
  - Use clear language
```

✅ Boundary constraint:
```yaml
snippet: |
  ## Guardrails — Tone Boundaries
  - Never use slang, jargon, or technical terms without explanation
  - Avoid sarcasm, humor, or casual language
  - Maintain formal tone even if user is informal
```

### 4. Make Mandatory Guardrails Universal

Only mark a guardrail as `mandatory: true` if it should apply to **all** agents.

Examples of good mandatory candidates:
- Hallucination prevention
- Scope restriction
- Legal disclaimers (if all agents need them)

Examples that should be optional:
- Domain-specific rules
- Tone/style constraints
- Feature-specific boundaries

### 5. Use Sensible Priorities

**Priority ranges**:
- 1-5: Critical safety guardrails (hallucination, scope, privacy)
- 6-10: Domain constraints (legal, medical, financial)
- 11-20: Operational rules (pricing, escalation, process)
- 21+: Style and quality guidelines

---

## Guardrail Composition

Multiple guardrails combine additively:

**Request**:
```json
{
  "guardrails": [
    "hallucination_prevention",
    "scope_restriction",
    "data_privacy",
    "medical_disclaimer"
  ]
}
```

**Result**:
```markdown
# Agent Specification

## 1. Role
...

## 2-5. (other sections)
...

## 6. Guardrails — Hallucination Prevention
- Do not invent facts...

## 7. Guardrails — Scope Restriction
- Stay strictly within...

## 8. Guardrails — Data Privacy
- Do not request, store...

## 9. Guardrails — Medical Disclaimer
- You are not a licensed...
```

---

## Testing Guardrails

### Manual Testing

1. Generate a specification with the guardrail
2. Activate it for a test agent
3. Try conversations that should trigger the guardrail
4. Verify the agent respects the constraint

### Example Test Cases (Hallucination Prevention)

**Test 1**: Unknown information
> User: "What's the price of Product XYZ?"  
> **Expected**: Agent admits uncertainty or looks up information

**Test 2**: Ambiguous question
> User: "How does it work?"  
> **Expected**: Agent asks clarifying question instead of guessing

**Test 3**: Speculation request
> User: "What do you think will happen next?"  
> **Expected**: Agent declines to speculate

---

## Common Guardrail Patterns

### Escalation Pattern

```yaml
snippet: |
  ## Guardrails — Escalation Rules
  - Escalate to {{escalation_target}} if:
    - User is upset or frustrated
    - Request exceeds your authority
    - Technical issue requires engineering
  - Use escalation phrase: "{{escalation_phrase}}"
```

### Compliance Pattern

```yaml
snippet: |
  ## Guardrails — Regulatory Compliance
  - Include mandatory disclosure: "{{disclosure_text}}"
  - Record consent before {{action_requiring_consent}}
  - Follow {{regulation_name}} requirements
```

### Multi-language Pattern

```yaml
snippet: |
  ## Guardrails — Language Support
  - Respond in user's language when possible
  - Supported languages: {{supported_languages}}
  - For unsupported languages, respond in {{default_language}}
```

---

## Governance

### Who Owns Guardrails?

**Technical Guardrails** (hallucination, scope):
- Owned by: Engineering/AI team
- Changes require: Code review

**Compliance Guardrails** (legal, medical, financial):
- Owned by: Legal/Compliance team
- Changes require: Legal approval

**Business Guardrails** (pricing, escalation):
- Owned by: Business operations
- Changes require: Manager approval

### Version Control

All guardrail templates are version-controlled in Git:

```bash
git log includes/PromptBuilder/templates/guardrails/
```

Changes should follow your standard review process.

---

## Troubleshooting

### Guardrail Not Appearing in UI

**Cause**: YAML parsing error

**Solution**: Validate YAML syntax
```bash
php -r "var_dump(yaml_parse_file('includes/PromptBuilder/templates/guardrails/your_file.yaml'));"
```

### Guardrail Not Applied in Generation

**Cause**: Not selected or invalid key

**Solution**: Check `applied_guardrails` in API response

### Variables Not Interpolated

**Cause**: Variable name mismatch

**Solution**: Ensure variable keys in YAML match API request:
- YAML: `{{brand_name}}`
- API: `"variables": {"brand_name": "Acme"}`

---

## Future Enhancements

Potential additions (not yet implemented):

- **Conditional Guardrails**: Apply based on user role or context
- **Guardrail Inheritance**: Guardrail templates that extend others
- **Runtime Validation**: Check if agent adheres to guardrails
- **A/B Testing**: Compare agent behavior with/without specific guardrails

---

**See Also**:
- [Prompt Builder Overview](prompt_builder_overview.md)
- [API Documentation](prompt_builder_api.md)
