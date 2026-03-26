# Pre-Launch Security & Errors Report

**Generated for live server deployment.** Address all CRITICAL and HIGH items before going live.

---

## CRITICAL – Fix Before Deploy

### 1. **.env.example contains real credentials**
- **Risk:** Anyone with repo access (or if repo is ever public) gets live credentials.
- **Location:** `.env.example`
- **Details:** File contains real `APP_KEY`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `DEFAULT_ADMIN_EMAIL`, `DEFAULT_ADMIN_PASSWORD`.
- **Fix:** Use placeholders only in `.env.example`. Generate real values on the server and keep them only in `.env` (never committed). See applied fix in this repo.

### 2. **Production must not run with APP_DEBUG=true**
- **Risk:** Stack traces and env details exposed to users; information disclosure.
- **Fix:** On live server set in `.env`:
  ```env
  APP_ENV=production
  APP_DEBUG=false
  LOG_LEVEL=warning
  ```

### 3. **Dependency vulnerabilities (Composer audit)**
Run `composer audit` and address reported issues. Current known advisories:
- **phpoffice/math** (HIGH) – CVE-2025-48882: XXE when processing MathML. Update or remove if unused.
- **symfony/http-foundation** (HIGH) – CVE-2025-64500: PATH_INFO parsing / authorization bypass. Update Symfony (via `composer update`).
- **psy/psysh** (MEDIUM) – CVE-2026-25129: Local privilege escalation. Update (often via `laravel/tinker`).
- **symfony/process** (MEDIUM) – CVE-2026-24739: Windows argument escaping. Update Symfony.

**Action:** Run `composer update` and re-run `composer audit`. If phpoffice/math is not required, consider removing it.

---

## HIGH – Security & Hardening

### 4. **Mass assignment – models with `$guarded = []`**
- **Risk:** Request input could set any attribute (e.g. `role`, `type`, `admin_id`) if not validated elsewhere.
- **Models:** `Domain`, `ArticleCategory`, `ArticleSet`, `ArticleLanguage`, `DomainCategory`, `Admin`, `DomainSet`.
- **Fix:** Prefer `$fillable` with an explicit list of allowed attributes, or keep `$guarded` but add only specific guarded keys (e.g. `['id', 'admin_id']` for user-owned models). Ensure controllers use validated/whitelisted input when creating or updating.

### 5. **Unescaped output (XSS) – `{!! !!}` in Blade**
- **Risk:** If content is ever user-controlled or from API, it can execute script in the browser.
- **Locations:**
  - `resources/views/admin/welcome.blade.php` – `{!! $stat['icon'] !!}`
  - `resources/views/admin/article/upload/upload-article.blade.php` – `{!! session('cus__success') !!}`
  - `resources/views/admin/article/article-set/article-set-option.blade.php` – session success/error
  - `resources/views/admin/article/edit-article.blade.php` – `{!! $article->description !!}`
  - `resources/views/admin/article/view-article.blade.php` – `{!! $article->description !!}`
  - Edit post views: `edit-campaign-post.blade.php`, `edit-schedule-campaign-post.blade.php`, `edit-post.blade.php` – `{!! $fetchedData['post_content'] ?? '' !!}`
- **Fix:** Use `{{ }}` (or `@verbatim` where needed) for any content that might include user/API input. Use `{!! !!}` only for trusted HTML (e.g. from a WYSIWYG that you sanitize). Consider running HTML through a purifier (e.g. mews/purifier, already in project) before output if you must allow some HTML.

### 6. **HTTPS and cookies**
- **Risk:** Session hijacking if cookies sent over HTTP.
- **Fix:** On live, use HTTPS and in `.env` (or config):
  - `APP_URL=https://yourdomain.com`
  - `SESSION_SECURE_COOKIE=true` (or equivalent in `config/session.php` via env).

---

## MEDIUM – Recommended

### 7. **HTTP client – SSL verification disabled**
- **Locations:** Multiple `Http::withoutVerifying()` in Jobs and Services (e.g. `RemotePostUpdateService`, `BlogrollApiService`, `SyncWpScheduledPostStatusJob`, etc.).
- **Risk:** Man-in-the-middle on outbound requests to WordPress/APIs.
- **Fix:** Prefer enabling SSL verification. If you must call self-signed or internal endpoints, restrict to specific hosts and document the exception; do not disable verification globally.

### 8. **Default admin credentials**
- **Location:** `DEFAULT_ADMIN_EMAIL` and `DEFAULT_ADMIN_PASSWORD` in `.env` (or from env).
- **Risk:** If used to create a default admin, weak or known password is dangerous.
- **Fix:** Do not use default admin on production, or use a strong random password and change it after first login. Do not commit real defaults.

### 9. **Report routes are public (token-only)**
- **Status:** Report URLs use a long `report_token` and controller uses `firstOrFail()` with that token – acceptable for “share link” reports.
- **Recommendation:** Ensure `report_token` is long and random (e.g. 64 chars). Already using `Str::random(64)` in model. No change required if token is not guessable.

### 10. **API and admin auth**
- **Status:** Admin web routes use `admin.auth`; API routes use `admin.api.auth`. Report routes are token-based and do not require login. This is consistent and acceptable.

---

## LOW / Informational

### 11. **Laravel test command**
- **Detail:** `php artisan test` is not registered (no `Illuminate\Foundation\Testing` in default Laravel 12 setup or test command removed). Unit/feature tests can be run via `vendor\bin\phpunit` if PHPUnit is installed (e.g. `composer require --dev phpunit/phpunit` and configure `phpunit.xml`).
- **Action:** Optional: add PHPUnit and run tests in CI or before deploy.

### 12. **SQL and raw queries**
- **Status:** Uses of `whereRaw` / `selectRaw` / `orderByRaw` that were checked pass bound parameters or sanitized integers (e.g. `Api\admin\DomainSetController::setDomains` – `$domainIds` are cast to int). No obvious SQL injection found. Continue to use bound parameters for any new raw SQL.

### 13. **Session and cache**
- **Detail:** `SESSION_DRIVER=file`, `CACHE_STORE=database`. For a single-server live deploy this is fine. For multiple app servers, consider Redis (or database) for both and ensure session/cache config matches.

---

## Pre-Launch Checklist

- [ ] Replace all real credentials in `.env.example` with placeholders (DONE in repo).
- [ ] On live server: `APP_DEBUG=false`, `APP_ENV=production`, `LOG_LEVEL=warning`.
- [ ] Run `composer install --no-dev --optimize-autoloader` and `composer audit`; fix or accept remaining advisories.
- [ ] Use HTTPS and set `SESSION_SECURE_COOKIE=true` (or equivalent).
- [ ] Ensure `.env` on server is not in version control and has a strong `APP_KEY` and DB credentials.
- [ ] Harden models with `$guarded = []` (prefer `$fillable` or minimal `$guarded`).
- [ ] Review and fix XSS: replace unsafe `{!! !!}` with `{{ }}` or sanitized output.
- [ ] (Optional) Re-enable SSL verification for HTTP client where possible.
- [ ] (Optional) Add PHPUnit and run tests before deploy.

---

## Quick Commands (on server / before deploy)

```bash
composer install --no-dev --optimize-autoloader
composer audit
php artisan config:cache
php artisan route:cache
php artisan view:cache
# Ensure queue worker(s) and scheduler (if used) are running
```
