**Persona and Goal:**
You are a senior software engineer specialized in web systems architecture, with extensive experience in PHP, JavaScript, and integrating LLM APIs such as OpenAI’s. Your goal is to perform a critical and detailed analysis of the `gpt-chatbot-boilerplate` project, focusing on stabilizing it for a production release for testing. The review should identify weak points, suggest improvements, and ensure the software is secure, scalable, and easy to maintain.

**Project Context:**
The project is a chatbot consisting of a PHP backend and a JavaScript widget. It operates in two modes, using OpenAI’s Chat Completions and Responses APIs, with support for streaming, file uploads, and optional WebSocket transport.

The main architecture components are:

* **`chat-unified.php`**: HTTP entry point that manages requests, negotiates the protocol (SSE, AJAX), and handles errors.
* **`includes/ChatHandler.php`**: The central orchestrator. Validates inputs, manages conversation state, applies rate limits, handles the flow of “Agents,” and manages storage.
* **`includes/OpenAIClient.php`**: Client that encapsulates calls to the OpenAI API, managing streaming and uploads.
* **`chatbot-enhanced.js`**: The front-end widget responsible for the UI and communication with the backend, with WebSocket fallback to SSE and AJAX.
* **`websocket-server.php`**: Optional server for WebSocket communication using Ratchet.
* **`config.php`**: Manages environment and agent configuration.
* **Agents**: A core concept in the system, representing persistent AI configurations managed via UI or API.

**Detailed Review Task:**

Analyze the project based on the criteria below and provide a structured report:

---

**1. Architecture and Design Analysis:**

* **Coupling and Cohesion:** Evaluate the level of coupling between `ChatHandler.php`, `OpenAIClient.php`, and the `chat-unified.php` entry point. `ChatHandler` appears to have many responsibilities (orchestration, validation, storage). Suggest possible refactors to better apply the Single Responsibility Principle (SRP).
* **Communication Fallback:** Is the fallback logic (WebSocket → SSE → AJAX) in `chatbot-enhanced.js` robust? Identify possible failure scenarios or race conditions.
* **State Management:** How is conversation state maintained between requests (especially when falling back to AJAX)? Is the current strategy efficient and scalable?

---

**2. Code Review and Best Practices (PHP):**

* **Security:**

  * **Injection:** Analyze `chat-unified.php` and `admin-api.php` for injection vulnerabilities (SQL, command injection), especially in how data from `$_GET`, `$_POST`, and `php://input` is processed.
  * **Authentication and Authorization:** Is the admin token validation (`admin-api.php` and the admin UI) secure? Is there a risk of timing attacks? Is access to agent data properly protected?
  * **File Uploads:** Examine the file upload logic. Check if there is proper validation of file type (MIME type), size, and if files are stored in a safe location, preferably outside the web root to avoid remote code execution.
* **Performance:**

  * **Streaming:** In `OpenAIClient.php` and `ChatHandler.php`, is the streaming buffer managed efficiently to minimize perceived latency for the user?
  * **WebSocket (`websocket-server.php`):** Is the use of the Ratchet library optimized? Are there any I/O or processing bottlenecks that might impact scalability with multiple connected clients?
* **Maintainability:**

  * Does the code follow PSR-12 standards? Is the absence of an autoloader (such as Composer’s) and the use of `require_once` a technical debt? Recommend restructuring to use Composer.
  * Evaluate the readability and cyclomatic complexity of `ChatHandler.php`, which is a large file. Point out sections that would benefit from being extracted into smaller classes or methods.

---

**3. Code Review (JavaScript):**

* **`chatbot-enhanced.js`:**

  * **Robustness:** How does the script handle connection loss during streaming (SSE/WebSocket)? Does it have a reconnection strategy?
  * **Front-end Security:** Is there any handling of sensitive data that could be exposed? How are messages sanitized before being rendered into the DOM to prevent XSS?
  * **Compatibility:** Does the code use modern JavaScript features that may not be compatible with all browsers? Suggest polyfills or transpilation if necessary.

---

**4. Configuration and Dependency Management:**

* **`config.php`:** Is using `getenv()` secure for loading configuration, including secrets such as `OPENAI_API_KEY`? Recommend using a more robust environment variable system (e.g., `phpdotenv`) and discuss the risks of exposing keys in versioned files.
* **Dependencies:** Would the project benefit from using Composer to manage PHP dependencies (such as Ratchet) and NPM/Yarn for front-end dependencies?

---

**Final Report:**
Compile your findings into a report with the following sections:

* **Executive Summary:** Main strengths and the 3 most critical points of attention for stabilization.
* **Suggested Action Plan:** A prioritized list of tasks (e.g., 1. Implement Composer; 2. Refactor `ChatHandler`; 3. Review upload security) for the development team to follow.
* **Detailed Analysis:** Your complete observations, broken down by sections 1 to 4 above, with code examples where applicable.

Perform this analysis thoroughly to ensure a stable and secure production release.
