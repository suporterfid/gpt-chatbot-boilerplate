# GPT Codex Migration Tasks

1. **Add Responses API configuration and prompt support**  
   Expand configuration handling to support `API_TYPE=responses`, expose new environment variables (including the saved prompt ID), and ensure the frontend defaults to the Responses workflow when configured.

2. **Implement Responses API streaming in `OpenAIClient`**  
   Introduce a streaming client for `/responses`, adjust headers, align file-upload purposes, and retire assistant-specific helpers when the Responses path is active.

3. **Swap assistant workflow for Responses in `ChatHandler`**  
   Build a Responses-aware chat handler that constructs the payload from conversation history, relays SSE events using the existing envelope, manages tool calls, and drops assistant-only dependencies.

4. **Update frontend streaming for Responses events**  
   Adapt the chat widget to interpret the backend's Responses-style SSE payloads while remaining compatible with chat completions.

5. **Refresh docs and remove obsolete assistant scaffolding**  
   Update documentation for Responses mode, remove unused assistant helpers, and provide guidance for managing prompt IDs and fallbacks.
