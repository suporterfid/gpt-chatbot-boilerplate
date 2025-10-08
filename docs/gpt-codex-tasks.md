# GPT Codex Tasks

## Tasks:
## CI/CD pipeline tasks
1. **Create workflow skeleton**
   Add `.github/workflows/cicd.yml` triggered on pushes to `main` and all pull requests, with empty `quality`, `package`, and `deploy` jobs prepared for later steps.

2. **Implement quality checks**
   Configure the `quality` job to set up PHP 8.2 with required extensions, install Composer dependencies for development, lint all PHP files, and run PHPUnit when the `tests/` directory exists.

3. **Build production artifact**
   In the `package` job (only on `main`), install production dependencies with optimized autoloading, inject the `.env` content from the `PRODUCTION_ENV` secret, and stage deployable files into a temporary directory.

4. **Publish release archive**
   Compress the staged files into `chatbot-release.tar.gz` and upload it using `actions/upload-artifact@v4` so other jobs can reuse the bundle.

5. **Deploy via SFTP**
   Configure the `deploy` job to download the artifact, extract it, and publish the files with `SamKirkland/FTP-Deploy-Action@v4` using SFTP credentials stored in repository secrets.

6. **Secure production environment**
   Document required GitHub secrets—`PRODUCTION_ENV`, `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_PASSWORD` or `DEPLOY_KEY`, and `DEPLOY_PATH`—and gate deployment behind the `production` environment for manual approvals.

   



