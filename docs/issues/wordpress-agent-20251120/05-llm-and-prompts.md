# Task 5: Update LLM decisioning and prompt construction

## Status: Completed
## Completion Date: 2025-11-20

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

## Implementation Performed
- Updated LLM decisioning to bypass admin, queue, publishing, and monitoring steps while logging when creative generation is required.
- Enriched prompt construction with workflow configuration, queued article brief, chapter specs, CTA metadata, available tools, streaming guidance, and partial-completion safeguards.

## Related Commits
- 1268498
