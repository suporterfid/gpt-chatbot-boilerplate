# GPT Codex Tasks

## Tasks:
## CI/CD pipeline tasks
- [x] **Create workflow skeleton** – Added `.github/workflows/cicd.yml` triggered on pushes to `main` and all pull requests with `quality`, `package`, and `deploy` jobs.

- [x] **Implement quality checks** – `quality` job now provisions PHP 8.2, installs Composer dependencies, lints all PHP files, and conditionally runs PHPUnit when the `tests/` directory is present.

- [x] **Build production artifact** – `package` job (restricted to `main`) installs optimized production dependencies, writes the `PRODUCTION_ENV` secret to `.env`, and stages deployable files into a temporary directory.

- [x] **Publish release archive** – The staged bundle is compressed into `chatbot-release.tar.gz` and uploaded with `actions/upload-artifact@v4` for downstream jobs.

- [x] **Deploy via SFTP** – The `deploy` job downloads and extracts the artifact, then publishes it using `SamKirkland/FTP-Deploy-Action@v4` over SFTP with repository secrets.

- [x] **Secure production environment** – Documented the required GitHub secrets and noted the `production` environment approval gate in `docs/deployment.md`.

   



