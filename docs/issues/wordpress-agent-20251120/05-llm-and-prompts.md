# Task 5: Update LLM decisioning and prompt construction

## Goal
Ensure the agent only invokes the LLM when necessary (outline/chapter writing, CTA polish) and supplies it with complete context from the blog workflow.

## Key Steps
- Revise `requiresLLM()` to skip LLM for admin/queue/publish/status operations but require it for creative work.
- Enhance `prepareLLMMessages()` to inject system guidance with config metadata, queued article brief, chapter specs, CTA rules, and available tools/retries instructions.
- Support streaming hints and record when an action has already partially completed to prevent duplicate content.

## Acceptance Criteria
- LLM receives structured prompts that satisfy spec requirements (word counts, tone, SEO keywords, CTA inclusion).
- Non-creative actions return direct data without hitting the LLM.
- Logging clearly indicates when/why LLM was triggered.

## Relevant Files
- `agents/wordpress/WordPressAgent.php`
