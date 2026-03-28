<div align="center">

# Diamond PBN Automation

**Laravel admin panel for managing domains, articles, and multi-type publishing campaigns across many WordPress sites.**

[![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=flat&logo=php&logoColor=white)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?style=flat&logo=laravel&logoColor=white)](https://laravel.com/)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

</div>

---

## Overview

Diamond PBN Automation is a **private network / outreach automation** application. Operators use it to:

- Maintain **domain inventories** (categories, sets, status checks).
- Manage **articles** (categories, languages, sets, DOCX import, usage tracking).
- Run **campaigns** that publish or schedule content on remote WordPress sites via HTTP APIs.

The stack is **Laravel 12**, **MySQL**, **queued background jobs**, and a **Vite + Tailwind** front-end for the admin UI. Analytics on the dashboard use **ApexCharts**.

---

## Features

| Area | Capabilities |
|------|----------------|
| **Authentication** | Admin login, OTP verification, password reset |
| **Users** | Role-based admins (Super Admin, Admin, Member) |
| **Domains** | CRUD, categories, domain sets, bulk actions, domain status checks (queued) |
| **Articles** | CRUD, locking & status (unused / used / archive), languages, categories, article sets, bulk import |
| **Reporting** | Dashboard with campaign analytics; **token-protected** public report & export links (no login) |
| **Campaigns** | Multiple campaign types with task/post management, retries, bulk updates, and deletions via queues |

### Campaign types

1. **PBN Post** — Standard post campaigns (`PublishCampaignPostJob`, bulk updates, deletions).
2. **Sidebar / blogroll** — Sidebar link campaigns.
3. **Hidden link** — Links not surfaced visibly on the site UI.
4. **Schedule post** — Time-scheduled post campaigns.
5. **Schedule sidebar** — Scheduled sidebar / blogroll campaigns.
6. **WP scheduled** — WordPress-native scheduled posts (run, sync, retry per post).
7. **Sticky post** — Sticky post campaign flows.

Remote sites are integrated through your **WordPress plugin / API contract** (see `api-format.png` in the repo root for endpoint reference).

---

## Tech stack

- **PHP** 8.2+
- **Laravel** 12
- **MySQL** (default DB)
- **Queues** — `database` driver by default (`config/queue.php`)
- **Frontend** — Vite 7, Tailwind CSS 4, ApexCharts
- **Other packages** — Laravel Sanctum, Mews HTML Purifier, PHPWord, Spatie Simple Excel

---

## Requirements

- PHP 8.2+ with extensions Laravel needs (openssl, pdo, mbstring, tokenizer, xml, ctype, json, etc.)
- Composer 2.x
- Node.js 20+ (recommended) and npm
- MySQL 8+ (or compatible)
- A running **queue worker** in production (see [Queues & Supervisor](#queues--supervisor))

---

## Quick start

```bash
git clone https://github.com/YOUR_USERNAME/pbn_automation_software.git
cd pbn_automation_software

cp .env.example .env
composer install
php artisan key:generate

# Configure .env: DB_*, APP_URL, MAIL_*, queue, etc.
php artisan migrate

npm install
npm run build

php artisan serve
```

**Development** (server + queue + Vite) in one terminal:

```bash
composer run dev
```

The `dev` script runs `queue:listen` with queues: `default`, `deletions`, `campaigns`, `sidebar_campaigns`. In production you should run **all** queues your jobs use (see below).

---

## Environment

Copy `.env.example` to `.env` and set at minimum:

| Variable | Purpose |
|----------|---------|
| `APP_URL` | Public URL of the app |
| `DB_*` | Database connection |
| `QUEUE_CONNECTION` | Usually `database` or `redis` |
| `MAIL_*` | OTP / notifications |
| `DEFAULT_ADMIN_EMAIL` / `DEFAULT_ADMIN_PASSWORD` | Initial seeding only; use strong secrets in production |
| `SESSION_SECURE_COOKIE` | `true` when serving over HTTPS |

---

## Queues & Supervisor

Jobs are dispatched to **named queues** (examples):

`default`, `campaigns`, `deletions`, `bulk_updates`, `sidebar_campaigns`, `sidebar_deletions`, `bulk_blogroll_updates`, `hidden_links_campaigns`, `update_hidden_links`, `delete_hidden_links`, `delete_hidden_links_campaign`, `scheduled_campaigns`, `schedule_campaign_bulk_updates`, `schedule_campaign_deletions`, `scheduled_sidebar_campaigns`, `schedule_sidebar_bulk_updates`, `schedule_sidebar_deletions`, `wp_scheduled_campaigns`, `wp_scheduled_sync`, `wp_scheduled_campaign_bulk_updates`, `wp_scheduled_campaign_deletions`, `domainCheck`

Example Supervisor config and VPS steps live in:

- [`deploy/supervisor/laravel-worker.conf`](deploy/supervisor/laravel-worker.conf)
- [`deploy/supervisor/README.md`](deploy/supervisor/README.md)

For production, either run **multiple** `queue:work` programs (one per queue or grouped), or use a single worker that listens to a **comma-separated** list of queues (highest-priority queue first).

---

## Background jobs (by domain)

Jobs live in `app/Jobs/`.

### PBN Post campaigns

- `PublishCampaignPostJob`
- `BulkUpdateCampaignPostsJob`
- `DeleteCampaignJob`

### Sidebar campaigns

- `PublishSidebarBlogrollJob`
- `BulkUpdateSidebarBlogrollJob`
- `DeleteSidebarCampaignJob`

### Hidden link campaigns

- `PublishHiddenLinksJob`
- `BulkUpdateHiddenLinksJob`
- `BulkDeleteHiddenLinkCampaignsJob`
- `BulkDeleteHiddenLinksJob`

### Schedule post campaigns

- `PublishScheduledCampaignPostJob`
- `BulkUpdateScheduleCampaignPostsJob`
- `DeleteScheduleCampaignJob`

### Schedule sidebar campaigns

- `PublishScheduledSidebarBlogrollJob`
- `BulkUpdateScheduleSidebarBlogrollJob`
- `DeleteScheduleSidebarCampaignJob`

### WP scheduled campaigns

- `PublishWpScheduledPostJob`
- `BulkUpdateWpScheduledPostsJob`
- `DeleteWpScheduledCampaignJob`
- `SyncWpScheduledPostStatusJob`

### Other

- `CheckDomainStatus`

---

## Project layout (high level)

```
app/
  Http/Controllers/Admin/   # Admin UI + campaign controllers
  Jobs/                     # Queue workers
  Models/                   # Eloquent models
resources/views/admin/      # Blade templates
routes/admin.php            # Admin + public report routes
deploy/supervisor/          # Production queue worker config
```

---

## Testing & code style

```bash
composer run test
./vendor/bin/pint          # Laravel Pint (if configured)
```

---

## Security notes for production

- Use **HTTPS** and set `SESSION_SECURE_COOKIE=true`.
- Never commit real `.env` secrets; keep `.env.example` free of production keys.
- Review **token-protected** public report URLs; treat share links like secrets.
- Run `composer audit` and keep dependencies updated.

---

## License

This project is open-sourced under the [MIT license](LICENSE).

---

## Acknowledgments

Built with [Laravel](https://laravel.com/) and the Laravel community packages listed in `composer.json`.
