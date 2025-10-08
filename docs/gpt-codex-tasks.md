# GPT Codex Tasks

## Tasks:
## CI/CD pipeline tasks
- [x] **Create workflow skeleton** – Added `.github/workflows/cicd.yml` triggered on pushes to `main` and all pull requests with `quality`, `package`, and `deploy` jobs.

- [x] **Implement quality checks** – `quality` job now provisions PHP 8.2, installs Composer dependencies, lints all PHP files, and conditionally runs PHPUnit when the `tests/` directory is present.

- [x] **Build production artifact** – `package` job (restricted to `main`) installs optimized production dependencies, writes the `PRODUCTION_ENV` secret to `.env`, and stages deployable files into a temporary directory.

- [x] **Publish release archive** – The staged bundle is compressed into `chatbot-release.tar.gz` and uploaded with `actions/upload-artifact@v4` for downstream jobs.

- [x] **Deploy via SFTP** – The `deploy` job downloads and extracts the artifact, then publishes it using `SamKirkland/FTP-Deploy-Action@v4` over SFTP with repository secrets.

- [x] **Secure production environment** – Documented the required GitHub secrets and noted the `production` environment approval gate in `docs/deployment.md`.

## Front-end & UX backlog
- [ ] **Expand widget layout modes and header controls** – Update `chatbot-enhanced.js` to expose configuration flags for floating/embedded modes, toggle button placement, avatar/title/status badges, API type pills, and maximize/close controls; pair with responsive sizing tokens in `chatbot.css`.

- [ ] **Enhance message timeline rendering** – Refine `chatbot-enhanced.js` rendering logic to differentiate user/assistant bubbles, support Markdown and fenced code blocks, show optional timestamps and API labels, render attachment previews/tool call transcripts, and ensure smooth autoscroll behavior; add matching visual treatments in `chatbot.css`.

- [ ] **Expose theming and branding hooks** – Surface CSS custom properties and initialization options so integrators can customize colors, typography, header/footer branding, and optional “Powered by” badges; document default tokens inside `chatbot.css` and initialization comments.

- [ ] **Provide real-time session feedback** – Implement typing indicators, connection state badges, active-mode chips, file preview/removal controls, and gentle animation states for streaming/tool-call/error events inside `chatbot-enhanced.js`, with complementary styles and keyframe definitions in `chatbot.css`.

- [ ] **Streamline message composer UX** – Rework the input component in `chatbot-enhanced.js` to auto-resize the textarea, allow submit via button or Enter (Shift+Enter for newline), validate attachments before queueing, and clear/reset state after send; reflect focus/disabled/error states visually in `chatbot.css`.

- [ ] **Improve accessibility and responsiveness** – Extend `chatbot.css` to support light/dark themes, high-contrast variants, mobile breakpoints, and reduced-motion preferences while ensuring `chatbot-enhanced.js` toggles the appropriate classes/ARIA attributes for assistive technologies.

- [ ] **Add configurable proactive prompts** – Introduce logic in `chatbot-enhanced.js` for CTA buttons and optional auto-open timers that respect suppression signals stored in `localStorage` or cookies, along with user-dismiss interactions and themable presentation in `chatbot.css`.

   



