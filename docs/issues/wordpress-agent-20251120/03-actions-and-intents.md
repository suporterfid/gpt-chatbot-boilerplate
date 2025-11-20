# Task 3: Extend action surface and intent detection

## Goal
Align the agent''s action list with spec phases (queueing, structure generation, chapter writing, asset generation, assembly, publishing, monitoring, internal link management) and ensure user requests map to the right handler.

## Key Steps
- Define new action constants for each workflow phase plus monitoring/log retrieval.
- Expand `detectUserIntent()` with regex/keyword logic and add fallback heuristics using queue status/context clues.
- Update documentation/comments to describe each action''s purpose.

## Acceptance Criteria
- Incoming natural language commands for enqueueing, generating, publishing, or checking status resolve to the correct action constant.
- Intent detection returns `confidence` indicators and includes matches for debugging.
- Unknown intents still fall back to LLM with informative logging.

## Relevant Files
- `agents/wordpress/WordPressAgent.php`
