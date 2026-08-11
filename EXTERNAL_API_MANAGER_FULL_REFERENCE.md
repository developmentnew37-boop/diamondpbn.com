# External API Manager — full reference (functionality, routes, mechanisms)

> **Plugin name:** Diamond Pbn (External API Manager)  
> **Version:** 8.3.0  
> **Namespace:** `external/v1`  
> **Main file:** `external-api-manager.php`  
> **Last updated:** 2026-08-09

Complete reference for developers integrating **diamondpbn.com** or operating PBN sites with this agent installed.

---

## 1. What this plugin does

The agent turns each WordPress site into a **remote-controlled endpoint** for the Diamond PBN dashboard:

| Area | Capability |
|------|------------|
| **Health** | Check agent alive; verify API key |
| **Posts** | CRUD, sticky, draft/publish, schedule via `future` |
| **Scheduler queue** | Internal queue + WP Cron publishes queued posts |
| **Blogroll** | CRUD + draft/publish visibility on sidebar links |
| **Hidden links** | CRUD + draft/publish visibility on hidden menu links |
| **Plugin Manager** | Remote install/update/activate/deactivate/delete plugins from signed ZIP URLs |
| **Webhook** | Register domain with diamondpbn.com panel |
| **Auto-setup** | Install sidebar widget with blogroll + hidden links shortcodes |
| **Admin** | API keys, settings, endpoint docs, webhook status |

---

## 2. Base URL & REST contract

### URL pattern

```text
https://{domain}/wp-json/external/v1/{endpoint}
```

Legacy query form (same routes):

```text
https://{domain}/?rest_route=/external/v1/{endpoint}
```

### Authentication (most routes)

| Source | Key |
|--------|-----|
| Header (recommended) | `X-External-API-Key: {plaintext_key}` |
| Query string | `?api_key={key}` |
| JSON body | `"api_key": "{key}"` |

**Public routes (no key):** `GET /status`, `HEAD /status`, fallback `/?diamondpbn_status=1`

### HTTP methods for delete

| Resource | Delete method |
|----------|---------------|
| Posts, schedule jobs | **DELETE** |
| Blogroll, hidden links | **DELETE** |
| Plugin Manager `/plugins/delete` | **POST** |

### Response shapes

**Blogroll / hidden links** (wrapped):

```json
{
  "success": true,
  "message": "OK",
  "data": [ ... ]
}
```

**Posts** (many routes return flat objects):

```json
{
  "success": true,
  "post_id": 25,
  "status": "publish"
}
```

**Plugin Manager** (wrapped):

```json
{
  "success": true,
  "message": "...",
  "data": { ... }
}
```

**Errors:** WordPress `WP_Error` → JSON with `code`, `message`, HTTP status in `data.status` (403, 404, 424, etc.)

---

## 3. Architecture & bootstrap

### File layout

```text
external-api-manager/
├── external-api-manager.php      # Main class External_API_Manager
└── includes/
    ├── class-plugin-manager-api.php
    ├── class-plugin-manager-service.php
    ├── class-plugin-zip-validator.php
    └── class-quiet-upgrader-skin.php
```

### Init flow (`External_API_Manager::init()`)

1. **Activation / deactivation** hooks — keys, cron, webhook setup flags  
2. **Admin** — menu, settings, API key UI, docs table  
3. **`rest_api_init` priority 1** — `load_plugin_manager()` (includes + Plugin Manager routes)  
4. **`rest_api_init`** — `register_routes()` (main routes)  
5. **`rest_authentication_errors`** — allow public GET `/status`  
6. **`init` priority 0** — firewall fallback `/?diamondpbn_status=1`  
7. **Cron** — `eam_publish_queue` every 5 minutes  
8. **Widgets** — auto sidebar setup for blogroll/hidden shortcodes  

### Plugin Manager loading

`load_plugin_manager()` is **public** (required for WordPress hooks). It loads `includes/` once and calls `DiamondPBN_Plugin_Manager_API::init()`. Missing include files → admin error notice (no silent fatal).

---

## 4. Authentication mechanism

1. `auth_check($req)` reads key from header → param → `$req['api_key']`.  
2. Loads option **`eam_api_keys`** — array of key records.  
3. Each record: `hash` (bcrypt via `wp_hash_password`), optional `enc`+`iv` for admin reveal, `active`, `last_used`.  
4. Match with `wp_check_password($plain, $hash)`.  
5. On success: updates `last_used`, returns `true`.  
6. On failure: `403` — `no_key` or `bad_key`.

### Key lifecycle

- **On activate:** if no keys, generates default key, stores hash + encrypted copy, syncs `diamond_pbn_external_api_key`, shows key in transient 5 min.  
- **Admin:** create/revoke keys; AJAX `eam_reveal_key` decrypts for admins only.  
- **Encryption:** AES-256-CBC using `AUTH_KEY` + salts material.

---

## 5. Status & health check

### Mechanism

| Path | Method | Auth | Behavior |
|------|--------|------|----------|
| `/status` | GET, HEAD | None | JSON health payload |
| `/status/check` | POST | API key | Same payload + `"authenticated": true` |
| `/?diamondpbn_status=1` | GET | None | Bypass when `/wp-json/` blocked; exits before theme |

### Status payload (when Plugin Manager loaded)

```json
{
  "status": true,
  "message": "Connected",
  "authenticated": true,
  "plugin_version": "8.3.0",
  "plugin_slug": "external-api-manager",
  "plugin_manager_supported": true,
  "data": {
    "wp_version": "6.x",
    "php_version": "8.x",
    "filesystem_method": "direct"
  }
}
```

`plugin_manager_supported` is true when agent version ≥ **8.0.0**.

### Firewall bypass filter

`allow_public_status_route()` returns `true` for public status requests so WordPress REST does not require logged-in user. **API key routes still use `auth_check` on each route.**

---

## 6. Posts API

### Routes

| Route | Method | Description |
|-------|--------|-------------|
| `/posts` | GET | List posts (`post_type`, `status`, `limit`, `offset`) |
| `/posts/{id}` | GET | Single post object |
| `/posts/create` | POST | Create post |
| `/posts/update/{id}` | POST | Update post |
| `/posts/delete/{id}` | DELETE | Permanent delete |
| `/posts/sticky/{id}` | POST | Stick post |
| `/posts/unsticky/{id}` | POST | Unstick post |
| `/posts/publish/{id}` | POST | Set status → `publish` (date unchanged) |
| `/posts/draft/{id}` | POST | Set status → `draft` |

### Create / update body fields

| Field | Notes |
|-------|--------|
| `title`, `content` | Sanitized |
| `status` | Default `publish` on create |
| `post_type` | Default `post` |
| `categories`, `tags` | Create only |
| `is_sticky` | Create only (boolean) |
| `schedule_time` | If **future** datetime → `post_status=future`, sets `post_date` |

### Draft / publish mechanism

`posts_set_status()` → `wp_update_post(['post_status' => ...])` only.  
Returns `previous_status`, `status`, `action` (`published` / `drafted` / `skipped`).

**Dripfeed pattern:** `POST /posts/draft/{id}` then `POST /posts/update/{id}` with `schedule_time`.

---

## 7. Scheduler queue (Option B)

Separate from WordPress native `future` posts — uses internal option queue.

### Storage

Option: **`eam_schedule_queue`** — array of jobs with unique `id` (`sched_*`).

### Routes

| Route | Method | Description |
|-------|--------|-------------|
| `/schedule/add` | POST | Add job (`title`, `content`, `run_at`, `post_type`, `status`) |
| `/schedule/list` | GET | List all queued jobs |
| `/schedule/delete/{id}` | DELETE | Remove job by id |

### Cron mechanism

- Hook: **`eam_publish_queue`**  
- Interval: **`five_minutes`** (300s), registered via `cron_schedules` filter  
- **`process_queue()`:** for each job where `timestamp <= now`, runs `wp_insert_post()`; failed jobs stay in queue with `last_error`

---

## 8. Blogroll API

Depends on **Custom Blog roll Manager** for frontend display. Optional **`deps_ok('blogroll')`** when `enforce_dependencies` enabled in settings.

### Storage

- Canonical: **`custom_blogroll_links`**  
- Compat mirror: settings `blogroll_option` (default `custom_blogroll_links`)  
- Loader migrates legacy options if canonical missing  

### Item shape (stored)

```json
{
  "id": "blog_69847c5572d391.68435802",
  "keyword": "Anchor",
  "url": "https://example.com",
  "rel": ["nofollow", "sponsored"],
  "timestamp": 1721980800,
  "status": "publish"
}
```

Missing `status` → treated as **`publish`**.

### Routes

| Route | Method | Description |
|-------|--------|-------------|
| `/blogroll` | GET | List all items (includes drafts) |
| `/blogroll/add` | POST | Add link; auto sidebar setup if needed |
| `/blogroll/update/{id}` | POST | Update keyword/url/rel/status |
| `/blogroll/delete/{id}` | DELETE | Remove link |
| `/blogroll/publish/{id}` | POST | `status` → publish |
| `/blogroll/draft/{id}` | POST | `status` → draft (hidden on site with CBR 2.6+) |

### `rel` priority (add/update)

1. `rel_attr` — space-separated string  
2. `rel` — array or string  
3. Legacy booleans: `nofollow`, `sponsored`, `ugc`, `noopener`, `noreferrer`  

### ID targeting

Updates/deletes/draft/publish resolve by **`id` only** (not array index).

### Auto sidebar

On add, if widget not valid → **`setup_sidebar_widgets()`** installs combined Custom HTML / block widget with `[custom_blog_roll_with_menu menu='blog roll']` and `[hidden_links_menu]`.

---

## 9. Hidden links API

Same visibility pattern as blogroll. Depends on **Hidden Links Manager** for frontend.

### Storage

Option from settings **`hidden_links_option`** (default **`hlm_hidden_links`**). Migrates legacy `hidden_links_manager`, ensures `hid_*` ids.

### Item shape

```json
{
  "id": "hid_69847c5572d391.68435802",
  "keyword": "...",
  "url": "...",
  "rel": [],
  "status": "publish"
}
```

API output uses **`link`** (alias of `url`).

### Routes

| Route | Method | Description |
|-------|--------|-------------|
| `/hidden-links` | GET | List all (includes drafts) |
| `/hidden-links/add` | POST | Add link |
| `/hidden-links/update/{id}` | POST | Update fields / status |
| `/hidden-links/delete/{id}` | DELETE | Remove link |
| `/hidden-links/publish/{id}` | POST | Show link |
| `/hidden-links/draft/{id}` | POST | Hide link |

---

## 10. Plugin Manager API

Loaded from **`includes/`**. Requires agent **≥ 8.0.0**.

### Routes

| Route | Method | Description |
|-------|--------|-------------|
| `/plugins` | GET | List installed plugins |
| `/plugins/{slug}` | GET | Get one plugin (slug resolution) |
| `/plugins/install` | POST | Install from signed URL |
| `/plugins/update` | POST | Update/overwrite from ZIP |
| `/plugins/activate` | POST | Activate by slug |
| `/plugins/deactivate` | POST | Deactivate (agent protected) |
| `/plugins/delete` | POST | Delete (agent protected) |

### Install / update delivery (POST body)

| Field | Required | Notes |
|-------|----------|--------|
| `delivery` | Yes | Must be `"url"` |
| `download_url` | Yes | HTTPS only; host on allowlist |
| `expected_checksum_sha256` | Yes | SHA-256 of ZIP |
| `slug` | Yes | Dashboard library slug |
| `expected_slug` | No | WordPress folder name in ZIP |
| `target_version` / `plugin_file` | No | Disambiguation |
| `activate` | No | Activate after install/update |

### Security & limits

| Rule | Value |
|------|--------|
| Max ZIP size | **50 MB** |
| Download timeout | **300 s** (configurable) |
| Allowed hosts | `diamondpbn.com` (+ `DIAMONDPBN_PLUGIN_MANAGER_ALLOWED_HOSTS`) |
| No redirects on download | `redirection => 0` |
| Protected slug | **`external-api-manager`** — cannot delete; deactivate only with `force` + `acknowledge_agent_loss` |

### Mechanisms

- **ZIP validation:** root folder must match expected slug; plugin headers required  
- **Install:** `Plugin_Upgrader` + quiet skin  
- **Update custom plugins:** `install()` with `overwrite_package` (not WP.org transient)  
- **Deployment registry:** option `diamondpbn_plugin_deployments` maps library slug → `plugin_file`  
- **Slug resolution:** folder slug, registry, library slug match, version match, plugin family heuristics  

---

## 11. Webhook (panel registration)

### Purpose

Register the WordPress site domain with **diamondpbn.com** when admin saves webhook secret.

### Defaults

- URL: `https://diamondpbn.com/api/webhook/domains`  
- Secret stored encrypted: **`diamond_pbn_webhook_secret`**  
- Primary API key synced: **`diamond_pbn_external_api_key`**

### Mechanism

1. Admin saves webhook secret in plugin settings → **`register_via_webhook()`** POST to panel.  
2. Auto-retry up to **3** times on transport/5xx (`eam_webhook_retry` cron hook).  
3. Tracks pending domain id, last error, retries exhausted.  
4. Admin notices show registration success/failure.

Not exposed as REST route — **admin-driven only**.

---

## 12. Sidebar & shortcode auto-setup

### Purpose

Ensure blogroll and hidden links appear in site sidebar without manual widget config.

### Mechanism

1. **`maybe_setup_sidebar_widgets()`** on `widgets_init` (late priority).  
2. Detects shortcodes `custom_blog_roll_with_menu`, `hidden_links_menu`.  
3. **`install_combined_sidebar_widget()`** — single Custom HTML or block widget in first available sidebar.  
4. **`ensure_blogroll_nav_menu()`** — creates WP nav menu named **"blog roll"** if missing.  
5. **`filter_custom_html_shortcodes`** — runs `do_shortcode` on widget content for legacy themes.

Options: `diamondpbn_blogroll_display_installed`, `diamondpbn_hidden_links_display_installed`, pending flags set on activate.

---

## 13. Admin UI (WordPress)

**Menu:** Diamond Pbn (top-level)

| Feature | Description |
|---------|-------------|
| API keys | Generate, label, revoke, reveal (eye icon) |
| Webhook | Secret, URL, registration status |
| Settings | Dependency enforcement, plugin paths, option names |
| Endpoint docs | Full route table + JSON body examples |
| Activation | Auto key + setup pending flags |

---

## 14. Settings & WordPress options

| Option key | Purpose |
|------------|---------|
| `eam_api_keys` | Hashed API keys |
| `eam_settings` | Dependency + plugin paths |
| `eam_schedule_queue` | Scheduler jobs |
| `custom_blogroll_links` | Blogroll data |
| `hlm_hidden_links` (configurable) | Hidden links data |
| `diamondpbn_plugin_deployments` | Plugin Manager registry |
| `diamond_pbn_webhook_*` | Webhook state |
| `diamondpbn_*_pending_setup` | First-run widget flags |

### Dependency enforcement (`enforce_dependencies`)

When enabled, blogroll/hidden routes return **424** if companion plugin inactive **and** option missing.

---

## 15. Complete route index

```text
# Public
GET|HEAD  /status
GET       /?diamondpbn_status=1          (fallback, not REST path)

# Authenticated — health
POST      /status/check

# Posts
GET       /posts
GET       /posts/{id}
POST      /posts/create
POST      /posts/update/{id}
DELETE    /posts/delete/{id}
POST      /posts/sticky/{id}
POST      /posts/unsticky/{id}
POST      /posts/publish/{id}
POST      /posts/draft/{id}

# Scheduler
POST      /schedule/add
GET       /schedule/list
DELETE    /schedule/delete/{id}

# Blogroll
GET       /blogroll
POST      /blogroll/add
POST      /blogroll/update/{id}
DELETE    /blogroll/delete/{id}
POST      /blogroll/publish/{id}
POST      /blogroll/draft/{id}

# Hidden links
GET       /hidden-links
POST      /hidden-links/add
POST      /hidden-links/update/{id}
DELETE    /hidden-links/delete/{id}
POST      /hidden-links/publish/{id}
POST      /hidden-links/draft/{id}

# Plugin Manager
GET       /plugins
GET       /plugins/{slug}
POST      /plugins/install
POST      /plugins/update
POST      /plugins/activate
POST      /plugins/deactivate
POST      /plugins/delete
```

---

## 16. Common error codes

| HTTP | Code | Meaning |
|------|------|---------|
| 403 | `no_key` / `bad_key` | Auth failure |
| 404 | `not_found` | Post, queue job, or link id missing |
| 400 | `invalid` | Bad request body |
| 424 | `missing_dep` | Companion plugin/option missing |
| 409 | `plugin_already_installed` / `ambiguous_plugin` | Plugin Manager |
| 403 | `protected_plugin` | Agent self-delete/deactivate blocked |

---

## 17. Companion plugins (site-side)

| Plugin | Role |
|--------|------|
| **custom-blog-roll** 2.6+ | Renders blogroll; filters `status=draft` on frontend |
| **hidden-links-manager** | Renders hidden links; should filter by `status` |
| **external-api-manager** | This agent — API only |

The agent **stores** visibility; companion plugins **enforce** it on the public site.

---

## 18. Related documents

| File | Topic |
|------|--------|
| `EXTERNAL_API_MANAGER_CHANGES_AND_RATIONALE.md` | Why recent changes were made |
| `POST_STATUS_AND_DRIPFEED_API.md` | Post dripfeed workflows |
| `BLOGROLL_DRAFT_PUBLISH_IMPLEMENTATION.md` | Blogroll draft/publish spec |
| `PLUGIN_STATUS_DONE_AND_TODO.md` | Rollout checklist |
| `DIAMONDPBN_DASHBOARD_CHANGES.md` | Dashboard integration phases |

---

## Quick integration checklist (dashboard)

1. Store per-domain API key + use `POST /status/check`.  
2. Store WordPress ids: post id, `blog_*`, `hid_*`, schedule `sched_*`.  
3. Use correct HTTP method per resource (DELETE vs POST for plugin delete).  
4. Deploy full agent zip with `includes/`.  
5. Deploy companion plugins for link visibility on frontend.
