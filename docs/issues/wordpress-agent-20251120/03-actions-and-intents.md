# Task 3: Extend action surface and intent detection

## Status: Completed

## Completion Date: 2025-11-20

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

## Implementation Performed
- Added explicit workflow orchestration action constants that mirror the automation phases and documented their purposes.
- Expanded intent detection with richer regex coverage, queue-aware heuristics, and debug match metadata for routing user requests to the proper phase.
- Routed newly detected workflow directives to dedicated handlers so monitoring/log retrieval and phase-specific actions return structured status data.

## Related Commits
- ffdecb9 (workflow intent and action routing updates)
