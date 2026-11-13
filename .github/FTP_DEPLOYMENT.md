# UtilityFlow FTP deployment

UtilityFlow uses the same split Laravel/public-root deployment pattern as the sibling `ispbilling` app. GitHub Actions builds the PHP dependencies and Vite assets, uploads the Laravel application and the public document root to separate FTP directories, clears stale bootstrap cache, then runs migrations after a successful main-branch deploy.

## GitHub Actions secrets

Create these under the UtilityFlow repository’s **Settings → Secrets and variables → Actions**. Do not commit credentials or production `.env` values.

| Secret | Value |
| --- | --- |
| `FTP_SERVER` | FTP hostname, with or without a protocol prefix |
| `FTP_USERNAME` | FTP account username |
| `FTP_PASSWORD` | FTP account password |
| `FTP_SERVER_DIR` | UtilityFlow Laravel root on the server, including a trailing `/` |
| `FTP_PUBLIC_DIR` | This site’s document root on the server, including a trailing `/` |
| `FTP_PROTOCOL` | Usually `ftps`; supported options are `ftps`, `ftp`, `ftps-legacy`, `sftp` |
| `FTP_PORT` | Usually `21` for explicit FTPS/FTP, `990` for implicit FTPS, or `22` for SFTP |
| `DEPLOY_SITE_URL` | Public origin for this app, e.g. `https://water.example.com` (include a subdirectory if hosted below the domain root) |
| `DEPLOY_EXTRACT_TOKEN` | A long random bearer secret; set the same value in the production `.env` |

`FTP_SERVER_DIR` and `FTP_PUBLIC_DIR` are deliberately required rather than guessed. Confirm both directories in the hosting control panel; keep the app directory private and point the domain document root at the public directory. This avoids deploying into `ispbilling` or another site’s web root.

## One-time server setup

1. Create the UtilityFlow database and database user in the hosting control panel.
2. Create the Laravel app directory and the domain document root. Upload or deploy UtilityFlow so the app root contains `artisan`, `bootstrap/`, `vendor/` and `.env`; the document root contains `index.php` and `.htaccess`.
3. In the app root, create a production `.env` from `.env.example`. Set `APP_ENV=production`, `APP_DEBUG=false`, the real `APP_URL`, a generated `APP_KEY`, the database connection and credentials, mail settings, and any provider configuration. Never upload `.env` through this workflow.
4. Set `DEPLOY_EXTRACT_TOKEN` in that production `.env` to the exact random value stored in GitHub Actions. The public helper scripts only run when a POST request has the matching `Authorization: Bearer` header.
5. Ensure the host runs PHP 8.4 or newer with Laravel’s required extensions. Make `storage/` and `bootstrap/cache/` writable by PHP. Enable HTTPS for the domain and ensure the hosting firewall permits the selected FTP mode.
6. Confirm the document root’s `index.php` can find the app root. The committed front controller supports the standard `app/public` layout and the cPanel sibling layout where the private directory is named `utilityflow`.

## First deployment and ongoing updates

1. Add the secrets above and push to `main`, or open **Actions → Deploy UtilityFlow to production (FTP) → Run workflow**.
2. On the first deployment, run **Actions → Upload UtilityFlow production vendor dependencies (FTP) → Run workflow**. Repeat after `composer.lock` changes. This keeps large Composer files out of every normal deploy and extracts them only through the token-protected endpoint.
3. After the first vendor upload, manually run **Actions → Run UtilityFlow production migrations → Run workflow** once. Later successful normal deploys run migrations automatically.
4. For a failed or interrupted deployment, inspect the GitHub Actions logs before retrying. You can manually run **Run UtilityFlow production migrations** after verifying the files on the server, or **UtilityFlow production maintenance** to clear stale bootstrap cache.

## Included workflows

- `deploy.yml`: builds production assets, uploads app and public files, then clears stale bootstrap cache.
- `deploy-vendor.yml`: manually builds, uploads in 5 MB chunks, and extracts production Composer dependencies.
- `migrate.yml`: automatically follows successful deploys and can also be run manually.
- `maintenance.yml`: manually clears bootstrap cache through the protected endpoint.

The deployment helpers (`deploy-migrate.php`, `deploy-clear-cache.php`, and `deploy-extract-vendor.php`) are public PHP endpoints protected by the bearer token. Keep the token private and rotate both the server `.env` value and GitHub secret together if it needs to change.
