# Remote Plugin Manager — WordPress Plugin (Diamond PBN) Changes

> **Last updated:** 2026-06-24  
> **Location:** Project root (easy reference for plugin development)  
> **Purpose:** Specification for changes required in the **Diamond PBN (External API Manager)** WordPress plugin so the Laravel dashboard can install, update, activate, deactivate, and delete plugins on remote sites.  
> **Companion doc:** [PLUGIN_MANAGER_DASHBOARD_CHANGES.md](./PLUGIN_MANAGER_DASHBOARD_CHANGES.md)

---

## Pre-development review (read before coding)

| # | Issue | Resolution in this doc |
|---|--------|------------------------|
| 1 | `DELETE /plugins/delete` with JSON body | Use **`POST /plugins/delete`** — WordPress/clients handle POST+JSON reliably |
| 2 | `/status` response shape change | **Additive only** — keep `status` + `message` at root; add optional `data` object |
| 3 | `Plugin_Upgrader::upgrade()` needs full `plugin_file` | Resolve from `get_plugins()`, e.g. `external-api-manager/external-api-manager.php` — never guess `{slug}/{slug}.php` |
| 4 | Dashboard host allowlist | Document wp-config or plugin setting — see **Dashboard host configuration** |
| 5 | Temp ZIP cleanup | Always `@unlink($tmp)` after install/update in `finally` block |
| 6 | Self-update while plugin running | Use `Plugin_Upgrader` only; do not manually delete agent files mid-request |
| 7 | Skip/no-op when same version | If dashboard still calls update, return success with `action: "skipped"`, same version |
| 8 | Standard response wrapper | Match existing API: `{ success, message, data }` for plugin manager endpoints |

**Build order:** Ship WordPress Phase 1 **before** dashboard deploy UI goes live.

---

## Overview

### Problem

The network has **~3,000 remote WordPress sites** across **14 domain categories**. When the Diamond PBN plugin or other plugins need updates, each site must be touched manually. That does not scale.

### Solution

Extend the Diamond PBN plugin with a **Plugin Manager API** under the existing REST namespace (`/wp-json/external/v1/`). The Laravel dashboard **hosts** plugin ZIPs on the app server (typically **single-file plugins under 1 MB**). The admin **uploads each version once**, then **deploys by category or manual domain list** — no re-upload per category.

### Bootstrap requirement (unchanged)

The Diamond PBN plugin must still be **installed and activated once per site** manually (or via onboarding). After that, it acts as the remote agent for all future plugin operations, including **self-updates**.

---

## How upload once + deploy by category works

This is the **WordPress-side view** of what happens when the dashboard rolls out a plugin.

```
┌─────────────────────────────────────────────────────────────────┐
│  DASHBOARD (once per version)                                   │
│  Admin uploads external-api-manager-8.0.0.zip → Plugin Library  │
│  File hosted: storage/app/plugin-packages/...                   │
└─────────────────────────────────────────────────────────────────┘
                              │
        ┌─────────────────────┼─────────────────────┐
        ▼                     ▼                     ▼
   Deploy Cat 1          Deploy Cat 2    ...   Deploy Cat 14
   (select v8.0.0)       (select v8.0.0)       (select v8.0.0)
        │                     │                     │
        ▼                     ▼                     ▼
   Queue jobs per domain in that category only
        │
        ▼
   Each job POSTs to THIS site's Diamond PBN plugin:
   { download_url, expected_checksum_sha256, slug, activate }
        │
        ▼
   Diamond PBN plugin on remote WordPress:
   1. GET signed URL → download same hosted ZIP (~< 1 MB)
   2. Verify SHA256
   3. Plugin_Upgrader install/update
   4. Return { previous_version, new_version, action }
```

**Key points for plugin developers:**

- The ZIP is **never re-uploaded** from the admin UI for each category — only the **same signed URL** (or refreshed signature) is sent per domain.
- Each remote site downloads the **exact same file** for that deployment run.
- Categories are a **dashboard concept** — WordPress only receives one API call per site.

---

## Plugin delivery model (Option A)

Plugins are **not** POSTed as multipart bodies in normal production flow.

1. Admin uploads ZIP **once** to dashboard → hosted at `storage/app/plugin-packages/`
2. Dashboard queue job sends each site a **signed HTTPS download URL** + checksum
3. Remote Diamond PBN plugin downloads the small ZIP and installs via `Plugin_Upgrader`

**No S3, CDN, or separate hosting service required** — the Laravel dashboard server stores and serves the file.

```
Laravel Dashboard (hosts plugin ZIP on disk)
    │
    │  1. Admin uploads plugin ZIP once (< 1 MB typical)
    │  2. File stored: storage/app/plugin-packages/{uuid}/{slug}-{version}.zip
    │  3. Admin deploys: selects library package + one category (or manual list)
    │  4. Queue jobs send signed download URL + checksum per domain
    ▼
Remote WordPress (Diamond PBN plugin)
    │
    │  GET signed URL → download hosted ZIP (~< 1 MB)
    │  Verify SHA256 checksum
    │  Use WordPress Plugin_Upgrader / native plugin functions
    ▼
wp-content/plugins/{slug}/
```

---

## Version handling (same plugin, multiple versions)

The dashboard library can hold **multiple versions of the same slug**. The WordPress plugin must support precise version reporting and update behaviour.

### Example library state (dashboard — not stored in WordPress)

| Slug | Version | Notes |
|------|---------|-------|
| `external-api-manager` | 7.8.0 | Old — kept for rollback |
| `external-api-manager` | 8.0.0 | Current deploy target |
| `custom-blogroll` | 1.2.0 | Other plugin |

### Per-site logic WordPress must support

When dashboard sends `POST /plugins/update` with a signed URL for **v8.0.0**:

| Site currently has | Dashboard operation | WordPress action |
|--------------------|---------------------|------------------|
| v7.8.0 | Update | Upgrade → return `previous_version: 7.8.0`, `new_version: 8.0.0` |
| v8.0.0 | Update + skip_if_same_version | Dashboard skips call OR WP returns success no-op |
| Not installed | Update | Return `plugin_not_found` — dashboard should use Install |
| v8.1.0 (newer) | Update if older | Dashboard skips — no API call |

### Response must always include versions

```json
{
  "success": true,
  "message": "Plugin updated successfully.",
  "data": {
    "slug": "external-api-manager",
    "previous_version": "7.8.0",
    "new_version": "8.0.0",
    "activated": true,
    "action": "updated"
  }
}
```

Dashboard logs `version_before` / `version_after` per domain for the deployment progress table.

---

## Other plugins (not only Diamond PBN)

The **same API endpoints** handle Diamond PBN and any other plugin. The `slug` (WordPress plugin folder name) distinguishes them.

| Plugin | Slug | Install endpoint | Update endpoint |
|--------|------|------------------|-----------------|
| Diamond PBN | `external-api-manager` | `/plugins/install` | `/plugins/update` |
| Custom Blogroll | `custom-blogroll` | `/plugins/install` | `/plugins/update` |
| Hidden Links helper | `hidden-links-manager` | `/plugins/install` | `/plugins/update` |

### Install vs update

| Operation | Pre-condition on site | WordPress behaviour |
|-----------|----------------------|---------------------|
| **Install** | Plugin slug NOT in `get_plugins()` | `Plugin_Upgrader::install($zip)` |
| **Update** | Plugin slug EXISTS | `Plugin_Upgrader::upgrade($plugin_file)` |
| **Delete** | Plugin slug EXISTS (except protected) | `deactivate_plugins()` + `delete_plugins()` |

### Diamond PBN special rules

| Action | Slug `external-api-manager` |
|--------|----------------------------|
| Self-update | **Allowed** — primary use case |
| Deactivate | **Blocked** unless `force` + `acknowledge_agent_loss` |
| Delete | **Blocked** — return `403 protected_plugin` |

Other plugins have normal activate/deactivate/delete behaviour.

---

## Design principles

1. **Reuse existing auth** — Same `X-External-API-Key` / `api_key` pattern as posts, blogroll, hidden links.
2. **WordPress-native operations** — Use `Plugin_Upgrader`, `get_plugins()`, `activate_plugin()`, etc.
3. **Clear error codes** — Structured errors so dashboard can show per-domain messages in category rollouts.
4. **Safe by default** — Host allowlist for download URLs; checksum required.
5. **Dashboard-hosted delivery** — Download from signed URL (primary). Multipart upload is debug/fallback only.
6. **Version reporting** — `/status` and `/plugins/{slug}` must return accurate `version` for skip-if-same logic.
7. **Idempotent updates** — Re-running same version should not break site; return clear success or no-op.

---

## New REST endpoints

All endpoints under namespace `external/v1`. Authentication required except `/status`.

| Method | Route | Purpose |
|--------|-------|---------|
| `GET` | `/plugins` | List installed plugins with metadata |
| `GET` | `/plugins/{slug}` | Single plugin details + **version** (used before update) |
| `POST` | `/plugins/install` | Install plugin from signed URL |
| `POST` | `/plugins/update` | Update existing plugin from signed URL |
| `POST` | `/plugins/activate` | Activate plugin by slug |
| `POST` | `/plugins/deactivate` | Deactivate plugin by slug |
| `POST` | `/plugins/delete` | Delete plugin files (use POST, not DELETE) |

### Extend `GET /status` (backward compatible)

Existing clients expect `{ "status": true, "message": "Connected" }`. **Do not remove those keys.** Add optional fields:

```json
{
  "status": true,
  "message": "Connected",
  "plugin_version": "8.0.0",
  "plugin_slug": "external-api-manager",
  "plugin_manager_supported": true,
  "data": {
    "wp_version": "6.5.2",
    "php_version": "8.2.12",
    "filesystem_method": "direct"
  }
}
```

| Field | Description |
|-------|-------------|
| `plugin_version` | Diamond PBN version on this site (top-level for easy parsing) |
| `plugin_manager_supported` | `false` on sites with agent < 8.0.0 — dashboard skips deploy |
| `data.filesystem_method` | From `get_filesystem_method()` — diagnose rollout failures |

---

## Endpoint specifications

### `GET /plugins`

List all installed plugins. Dashboard uses this for inventory scans (Phase 2).

```json
{
  "success": true,
  "data": {
    "plugins": [
      {
        "slug": "external-api-manager",
        "name": "Diamond Pbn",
        "version": "7.8.0",
        "active": true,
        "plugin_file": "external-api-manager/external-api-manager.php"
      }
    ],
    "total": 1
  }
}
```

---

### `GET /plugins/{slug}`

**Critical for version-aware deploys.** Dashboard calls this before update to get `version_before`.

```json
{
  "success": true,
  "data": {
    "slug": "external-api-manager",
    "name": "Diamond Pbn",
    "version": "7.8.0",
    "active": true,
    "plugin_file": "external-api-manager/external-api-manager.php"
  }
}
```

Not found → `404 plugin_not_found` (dashboard uses Install operation instead).

---

### `POST /plugins/install` and `POST /plugins/update`

#### Method A — Signed download URL (production — always use this)

```json
{
  "api_key": "YOUR_KEY",
  "delivery": "url",
  "download_url": "https://dashboard.example.com/plugin-deployments/download/{uuid}?expires=...&signature=...",
  "expected_slug": "external-api-manager",
  "expected_checksum_sha256": "a1b2c3...",
  "slug": "external-api-manager",
  "activate": true
}
```

**Remote site steps:**

1. Validate download URL host is allowlisted (dashboard domain)
2. `wp_remote_get()` the signed URL (~< 1 MB, 60s timeout)
3. Verify SHA256 === `expected_checksum_sha256`
4. Validate ZIP slug matches `expected_slug`
5. **Install:** `Plugin_Upgrader::install()` OR **Update:** `Plugin_Upgrader::upgrade()`
6. Optionally `activate_plugin()`
7. Return `previous_version`, `new_version`, `action`

#### Method B — Multipart upload (debug/fallback only)

Not used when dashboard hosts files. Document for local testing only.

---

### `POST /plugins/activate` / `deactivate`

```json
{ "api_key": "YOUR_KEY", "slug": "custom-blogroll" }
```

Block deactivation of `external-api-manager` without explicit force flags.

---

### `POST /plugins/delete`

```json
{ "api_key": "YOUR_KEY", "slug": "old-plugin" }
```

> **Important:** Use **POST**, not HTTP DELETE. Many HTTP clients and proxies strip DELETE request bodies.

- Block delete of `external-api-manager` → `403 protected_plugin`
- Deactivate then `delete_plugins()`

---

## Dashboard host configuration (required for Option A)

Remote sites must allow downloads only from your Laravel dashboard domain.

**Option 1 — wp-config.php (recommended for PBN sites):**

```php
define('DIAMONDPBN_PLUGIN_MANAGER_ALLOWED_HOSTS', 'your-dashboard.example.com');
```

**Option 2 — filter in plugin:**

```php
add_filter('diamondpbn_plugin_manager_allowed_hosts', function ($hosts) {
    return ['your-dashboard.example.com'];
});
```

**Option 3 — Diamond PBN plugin settings page (Phase 2):** Admin enters dashboard URL once; plugin extracts host.

Reject any `download_url` whose host is not in the allowlist → `400 invalid_download_host`.

---

## Single-file plugin ZIP validation

Typical packages are **one PHP file** in a folder, **under 1 MB**:

```
external-api-manager.zip
└── external-api-manager/
    └── external-api-manager.php
```

Validator must:

1. Locate main plugin PHP file in ZIP
2. Read `Plugin Name` and `Version` headers
3. Confirm folder name matches `expected_slug`
4. Reject ZIPs over 5 MB (dashboard config; normal uploads < 1 MB)

---

## WordPress implementation guide

### Required includes

```php
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
```

### Filesystem check (return clear error for dashboard)

```php
if (! WP_Filesystem() || ! $wp_filesystem->is_writable(WP_PLUGIN_DIR)) {
    return new WP_Error('filesystem_not_writable', '...', ['status' => 424]);
}
```

### Resolve plugin file for upgrade (critical)

```php
function diamondpbn_get_plugin_file_by_slug(string $slug): ?string
{
    if (! function_exists('get_plugins')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    foreach (get_plugins() as $plugin_file => $headers) {
        if (dirname($plugin_file) === $slug || ($slug . '/' . basename($plugin_file)) === $plugin_file) {
            return $plugin_file;
        }
    }
    return null;
}
// Plugin_Upgrader::upgrade($plugin_file) — NOT $slug alone
```

### Download from signed URL

```php
function diamondpbn_download_plugin_zip(string $url, ?string $expected_sha256): string|WP_Error
{
    $response = wp_remote_get($url, ['timeout' => 60, 'sslverify' => true]);
    // Save to temp file, verify SHA256, return path
}
```

### Host allowlist

```php
function diamondpbn_validate_download_url(string $url): bool
{
    $parsed = wp_parse_url($url);
    if (($parsed['scheme'] ?? '') !== 'https') return false;
    $allowed = apply_filters('diamondpbn_plugin_manager_allowed_hosts', ['your-dashboard.com']);
    return in_array($parsed['host'] ?? '', $allowed, true);
}
```

### Suggested class structure

```
external-api-manager/
├── external-api-manager.php
├── includes/
│   ├── class-plugin-manager-api.php      # REST callbacks
│   ├── class-plugin-manager-service.php  # Install/update/delete
│   └── class-plugin-zip-validator.php    # ZIP + header validation
```

---

## Error codes (standardized)

| Code | HTTP | When |
|------|------|------|
| `no_key` / `bad_key` | 403 | Auth failure |
| `plugin_not_found` | 404 | Update/delete but not installed |
| `plugin_already_installed` | 409 | Install but already exists |
| `protected_plugin` | 403 | Delete/deactivate Diamond PBN |
| `filesystem_not_writable` | 424 | Cannot write to plugins dir |
| `invalid_zip` | 400 | Bad ZIP |
| `slug_mismatch` | 400 | ZIP folder ≠ expected_slug |
| `checksum_mismatch` | 400 | SHA256 failed |
| `download_failed` | 502 | Could not fetch signed URL |
| `invalid_download_host` | 400 | URL host not allowlisted |
| `install_failed` | 500 | Plugin_Upgrader error |
| `agent_outdated` | 424 | `plugin_manager_supported` false (optional internal) |

---

## Category rollout — what WordPress sees

When admin deploys **v8.0.0** to **14 categories** one at a time:

| Deployment | Dashboard sends per domain | WordPress does |
|------------|---------------------------|----------------|
| Category 1 (120 sites) | Same signed URL for v8.0.0 | 120 downloads + updates |
| Category 2 (200 sites) | Same signed URL for v8.0.0 | 200 downloads + updates |
| … | … | … |
| Category 14 | Same signed URL for v8.0.0 | N downloads + updates |

WordPress plugin does **not** need category awareness — only handles one authenticated request per site.

---

## Phase alignment with dashboard

| Phase | WordPress plugin | Dashboard |
|-------|------------------|-----------|
| **1** | `/status` enhanced, `GET /plugins/{slug}`, `POST /plugins/update` | Library + deploy update + skip-if-same |
| **2** | `POST /plugins/install`, activate/deactivate/delete | All operations + all plugin slugs |
| **3** | Dependency warnings, audit log | Version drift report, email notify |

---

## Implementation phases

### Phase 1 — v8.0.0 (MVP)

- [ ] Enhanced `/status` with `plugin_version`, `plugin_manager_supported`
- [ ] `GET /plugins` and `GET /plugins/{slug}`
- [ ] `POST /plugins/update` (signed URL + checksum)
- [ ] Self-update for `external-api-manager`
- [ ] Return `previous_version` / `new_version` in all install/update responses

### Phase 2 — v8.1.0

- [ ] `POST /plugins/install` for other plugins
- [ ] `POST /plugins/activate` / `deactivate`
- [ ] `DELETE /plugins/delete`

### Phase 3

- [ ] Dependency warnings (blogroll / hidden links)
- [ ] Optional audit log

---

## Testing checklist

1. **Self-update** — v7.8 → v8.0 via signed URL; stays active; `/status` shows 8.0.0
2. **Version response** — Update returns correct `previous_version` and `new_version`
3. **GET /plugins/{slug}** — Returns version before update (dashboard pre-flight)
4. **Install other plugin** — New slug installs and activates
5. **Update other plugin** — Existing slug upgrades
6. **Protected agent** — Cannot delete/deactivate `external-api-manager`
7. **Checksum mismatch** — Rejected
8. **Bad download host** — Rejected (not on allowlist)
9. **filesystem_not_writable** — Clear 424 error
10. **Same version re-run** — Safe no-op or success without breakage

---

## Version compatibility matrix

| Diamond PBN plugin | Dashboard feature |
|--------------------|-------------------|
| < 8.0.0 | Manual update only; show "agent outdated" |
| ≥ 8.0.0 | Self-update via category/manual deploy |
| ≥ 8.1.0 | Install/update/delete any plugin |

---

## Related documentation

- [PLUGIN_MANAGER_DASHBOARD_CHANGES.md](./PLUGIN_MANAGER_DASHBOARD_CHANGES.md) — Laravel dashboard (upload library, category deploy)
- [API-DATA-FLOW.md](./API-DATA-FLOW.md) — Existing REST API conventions
