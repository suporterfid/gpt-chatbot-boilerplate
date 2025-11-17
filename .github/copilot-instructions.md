# GitHub Copilot Instructions – gpt-chatbot-boilerplate

You are the AI assistant for the repo `suporterfid/gpt-chatbot-boilerplate`.  
Your goal is to **help maintain a production-ready PHP chatbot boilerplate** with a clean architecture, strong tests and clear documentation.

These instructions apply to **all Copilot interactions in this repo**.

---

## 1. General Behavior

- Default language: **Portuguese (Brasil)** when the user writes in PT-BR; otherwise reply in the user’s language.
- Prefer **practical, incremental changes** rather than big rewrites.
- Always **respect existing architecture and abstractions**:
  - `chat-unified.php` as main chat entrypoint.
  - `includes/ChatHandler.php` as core orchestration.
  - `includes/OpenAIClient.php` as OpenAI transport.
  - `chatbot-enhanced.js` as the main frontend widget.
  - Admin API / Admin UI and agent model as described in `docs/PROJECT_DESCRIPTION.md`.
- When in doubt, **prefer consistency with existing code** over introducing new patterns.
- Avoid adding new dependencies unless clearly beneficial and aligned with this project’s goals.

---

## 2. Coding Standards

### PHP

- Follow **PSR-12** coding standards.
- Use **strict types** when possible (`declare(strict_types=1);` at the top of new core PHP files, unless the file style clearly avoids it).
- Names:
  - Classes: `PascalCase`.
  - Methods/functions: `camelCase`.
  - Constants: `UPPER_SNAKE_CASE`.
- Use **type hints** for parameters and return types when reasonable.
- Prefer **early returns** and small, focused methods.
- Avoid global state; use dependency injection or pass configuration explicitly.

### JavaScript

- Follow the existing **ESLint style** (see `package.json` / ESLint config).
- Use modern JS but remain compatible with the current widget style:
  - Prefer `const` and `let` instead of `var`.
  - Use strict equality (`===` / `!==`).
- Keep DOM manipulation and event handling patterns consistent with `chatbot-enhanced.js`.
- Avoid introducing frameworks; this project is **vanilla JS first**.

### Comments & Documentation

- Comment **complex logic, edge cases, and non-obvious decisions**.
- Keep docblocks up to date (especially in core services / public APIs).
- When adding new public functions or classes, add a short PHPDoc explaining purpose and main parameters.

---

## 3. Tests, Quality and Build Commands

Before suggesting a change as “ready”, ensure you consider or mention the following commands:

### Core Test / Quality Commands

```bash
# All unit/integration tests
php tests/run_tests.php

# Smoke tests (production readiness)
bash scripts/smoke_test.sh

# Static analysis (PHP)
composer run analyze

# Frontend linting (JavaScript)
npm run lint

# Load tests (optional)
k6 run tests/load/chat_api.js
```

If a change affects:

- **Database / schema / migrations**: mention `php scripts/run_migrations.php`.
- **Admin API / RBAC / authorization**: consider `php tests/test_resource_authorization.php`.

When generating code, **include or update tests** where appropriate:
- Add/extend tests in `tests/` to cover new behavior.
- Prefer small, focused tests close to the logic being changed.

---

## 4. Git / Commits / PRs

### Commit Message Conventions

When suggesting commit messages, prefer this style (from `docs/CONTRIBUTING.md`):

- `feat: Add new feature X`
- `fix: Resolve issue with Y`
- `docs: Update documentation for Z`
- `test: Add tests for feature X`
- `refactor: Improve code structure`
- `chore: Maintenance or tooling changes`

Guidelines:

- Be **clear and descriptive**, focusing on what changed and why.
- Group related changes into a single commit when possible.

### PR Expectations

For any non-trivial change, remind contributors to:

- Ensure:
  - All tests pass.
  - Static analysis passes (**PHPStan** via `composer run analyze`).
  - Linting passes (**ESLint** via `npm run lint`).
  - `scripts/smoke_test.sh` passes for production-oriented changes.
- Update relevant documentation:
  - Core docs in `docs/`.
  - `README.md` or `CHANGELOG.md` when user-facing behavior changes.

---

## 5. Architecture & Design Guidelines

When designing or modifying features, keep these principles:

- **Separation of concerns**:
  - Chat orchestration and validation stay in `ChatHandler`.
  - OpenAI API interaction stays in `OpenAIClient`.
  - Admin CRUD logic in services like `AgentService`, `PromptService`, `VectorStoreService`.
  - Frontend behavior localized to `chatbot-enhanced.js` and Admin UI files.
- **Dual API mode**:
  - Preserve the ability to switch between **Chat Completions** and **Responses API**.
  - Ensure streaming, tools, file uploads, and agent configuration work in both modes.
- **Config-driven behavior**:
  - Prefer configuration in `config.php` or database-driven agents over hardcoding values.
  - Use environment variables via `config.php` for secrets (OpenAI keys, DB config, etc.).
- **Backwards compatibility**:
  - Avoid breaking existing public endpoints (`chat-unified.php`, `admin-api.php`, `metrics.php`) without a strong reason.
  - If a breaking change is necessary, mention migration steps and update docs.

---

## 6. Security & Reliability

When Copilot proposes changes, it should:

- **Never expose secrets** (e.g., `OPENAI_API_KEY`) to the frontend or logs.
- Validate and sanitize:
  - User input (messages, filenames, uploaded content).
  - File uploads (size, type) according to existing helpers and limits.
- Preserve or improve:
  - Error handling and logging.
  - Rate limiting and resource constraints where present.
- Consider production-readiness:
  - Make sure new features don’t bypass the existing testing/monitoring/logging approach.
  - Respect the patterns in `scripts/smoke_test.sh` and monitoring docs under `docs/ops/`.

---

## 7. How Copilot Should Respond

- When asked for help with code:
  - Show **full function or minimal diff** as appropriate.
  - Reference the **exact files** to edit (with paths).
  - If the change is non-trivial, mention which tests/commands the user should run.
- When asked questions about architecture or behavior:
  - Refer to the main docs:
    - `README.md`
    - `docs/PROJECT_DESCRIPTION.md`
    - `docs/CONTRIBUTING.md`
    - Agent/DB specs and ops docs under `docs/`.
- If the answer depends on a detail not obvious from context:
  - Say what is uncertain and which file to inspect.

These instructions are **global defaults**. If a specific issue/PR defines extra rules, follow those in addition to this file.
