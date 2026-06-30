# Remote Plugin Manager — Diamond PBN Dashboard (Laravel) Changes

> **Last updated:** 2026-06-30  
> **Location:** Project root (companion to WordPress plugin spec)  
> **Purpose:** Implementation guide for **diamondpbn.com dashboard** so it can install, update, activate, deactivate, and delete plugins on remote WordPress sites via the Diamond PBN agent.  
> **Companion doc:** [PLUGIN_MANAGER_WORDPRESS_CHANGES.md](./PLUGIN_MANAGER_WORDPRESS_CHANGES.md)  
> **Required WordPress agent version:** **≥ 8.1.4** (deploy registry, overwrite update, version-aware delete)

---

## Why dashboard changes are required

The WordPress agent is ready, but several production failures came from **what the dashboard sends**, not from WordPress alone:

| Symptom on remote site | Root cause | Dashboard fix |
|------------------------|------------|---------------|
| Update failed / `up_to_date` | Wrong WordPress upgrader path for custom ZIPs | Agent fixed in 8.1.2+; dashboard still must send full payload |
| Delete: *plugin not installed* | Sent **library slug** instead of **WordPress folder slug** | Store and send `expected_slug` |
| Delete: *multiple plugins match* | Site has v2.3 + v1.6 blogroll; no version in request | Send `target_version` |
| Install 409 on manual sites | Folder already exists | Pre-flight → use **Update** not Install |

---

## Critical concept: two different slugs

Every library package must track **two slugs**:

| Field | Example | Used for |
|-------|---------|----------|
| **`library_slug`** | `custom-blogroll-version-1.6` | Dashboard catalog, deploy history, UI labels |
| **`expected_slug`** | `custom-blog-roll` | WordPress plugin **folder name** inside the ZIP (`wp-content/plugins/{expected_slug}/`) |

**Never use `library_slug` alone** for install/update/delete on WordPress.  
WordPress resolves plugins by **folder name**, not library name.

### Real examples (this project)

| Plugin | Library slug (example) | `expected_slug` (ZIP folder) | Main file |
|--------|------------------------|--------------------------------|-----------|
| Diamond PBN agent | `external-api-manager` | `external-api-manager` | `external-api-manager.php` |
| Custom Blogroll v1.6 | `custom-blogroll-version-1.6` | `custom-blog-roll` | `index.php` |
| Hidden Links Manager | `hidden-links-manager` | `hidden-links-manager` | `hidden-links-manager.php` |
| Blogroll MU-plugin fix | `custom-blog-roll` | `custom-blog-roll` | (varies) |

---

## Phase 1 — Library package model (database)

### New / updated columns on `plugin_packages` (or equivalent)

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| `id` | uuid | yes | Primary key |
| `library_slug` | string | yes | Dashboard identifier (can include version in name) |
| `expected_slug` | string | yes | **Folder name inside ZIP** — auto-detect on upload |
| `plugin_name` | string | no | From ZIP `Plugin Name` header (display + matching) |
| `version` | string | yes | From ZIP `Version` header (e.g. `1.6`, `2.3`) |
| `checksum_sha256` | string | yes | SHA256 of stored ZIP file |
| `file_path` | string | yes | `storage/app/plugin-packages/{uuid}/{file}.zip` |
| `file_size_bytes` | int | yes | Max 5 MB enforced |
| `notes` | text | no | Admin notes |
| `created_at` | timestamp | yes | |

### On ZIP upload — auto-extract metadata

When admin uploads a ZIP to Plugin Library:

1. Validate size ≤ 5 MB
2. Open ZIP; read root folder name → save as **`expected_slug`**
3. Read main PHP file headers → save **`plugin_name`**, **`version`**
4. Compute **`checksum_sha256`**
5. Generate **`library_slug`** (suggested: `{expected_slug}-version-{version}` or admin-editable)
6. Reject if folder name ≠ `expected_slug` inside ZIP (consistency check)

```php
// Pseudocode — upload handler
$zip = openZip($uploadedFile);
$expectedSlug = $zip->getRootFolderName(); // e.g. custom-blog-roll
$headers = readPluginHeaders($zip, $expectedSlug);
$package = PluginPackage::create([
    'library_slug'  => $request->library_slug ?? "{$expectedSlug}-version-{$headers['Version']}",
    'expected_slug' => $expectedSlug,
    'plugin_name'   => $headers['Name'],
    'version'       => $headers['Version'],
    'checksum_sha256' => hash_file('sha256', $storedPath),
    ...
]);
```

---

## Phase 2 — Deploy job payload builder

Create a single service class, e.g. `App\Services\PluginDeployPayloadBuilder`, used by **all** queue jobs (install, update, delete, activate, deactivate).

### Base fields (every remote API call)

```json
{
  "api_key": "{domain.external_api_key}"
}
```

Auth: same as existing Diamond PBN API — header `X-External-API-Key` or body `api_key`.

### Install / Update payload

```json
{
  "api_key": "SITE_KEY",
  "delivery": "url",
  "download_url": "https://diamondpbn.com/plugin-deployments/download/{uuid}?expires=...&signature=...",
  "expected_checksum_sha256": "a1b2c3d4...",
  "slug": "custom-blogroll-version-1.6",
  "expected_slug": "custom-blog-roll",
  "target_version": "1.6",
  "activate": true
}
```

| Field | Source | Notes |
|-------|--------|-------|
| `slug` | `library_slug` | Registry key on WordPress after deploy |
| `expected_slug` | package.`expected_slug` | **Required** — WordPress folder |
| `target_version` | package.`version` | **Required** when multiple versions may exist |
| `download_url` | signed URL to hosted ZIP | HTTPS, from dashboard server |
| `expected_checksum_sha256` | package.`checksum_sha256` | Must match exactly |
| `activate` | deploy form checkbox | Default `true` for rollouts |

### Delete payload

```json
{
  "api_key": "SITE_KEY",
  "slug": "custom-blogroll-version-1.6",
  "expected_slug": "custom-blog-roll",
  "target_version": "1.6"
}
```

| Field | Required for delete? | Notes |
|-------|----------------------|-------|
| `expected_slug` | **Yes** | Primary folder lookup |
| `target_version` | **Yes** if same plugin name exists in multiple versions | e.g. blogroll v1.6 + v2.3 on same site |
| `plugin_file` | Optional fallback | Exact path from pre-flight, e.g. `custom-blog-roll/index.php` |

### Activate / Deactivate payload

```json
{
  "api_key": "SITE_KEY",
  "slug": "custom-blogroll-version-1.6",
  "expected_slug": "custom-blog-roll",
  "target_version": "1.6"
}
```

---

## Phase 3 — Pre-flight before deploy (per domain)

Before queueing install/update/delete, run pre-flight for each domain (can be in job or batch step):

### Step 1 — Agent health

```
GET https://{domain}/wp-json/external/v1/status
```

| Check | Action if fail |
|-------|----------------|
| `plugin_manager_supported` === `false` | Skip site; show "Agent outdated — manual upgrade required" |
| No response / not 200 | Skip; mark domain offline |

### Step 2 — Inventory (for update/delete)

```
GET https://{domain}/wp-json/external/v1/plugins?api_key=KEY
```

Or single plugin:

```
GET https://{domain}/wp-json/external/v1/plugins/{expected_slug}?api_key=KEY&target_version=1.6
```

### Step 3 — Choose operation

| Pre-flight result | Dashboard operation |
|-------------------|---------------------|
| No plugin with matching `expected_slug` + version | **Install** |
| Plugin exists, version < package version | **Update** |
| Plugin exists, version === package version | **Skip** (do not call API) |
| Plugin exists, version > package version | **Skip** (downgrade not supported unless explicit) |
| Delete requested, plugin found | **Delete** |
| Delete requested, plugin not found | Skip; log "already removed" |

### Step 4 — Skip-if-same (updates)

```php
if ($remoteVersion && version_compare($remoteVersion, $package->version, '>=')) {
    $deployment->markSkipped('Already at target version');
    return; // no API call
}
```

WordPress also returns `action: "skipped"` if update is called anyway — treat as success.

---

## Phase 4 — Queue jobs & endpoints

### Job → WordPress endpoint mapping

| Dashboard operation | HTTP | WordPress endpoint |
|--------------------|------|-------------------|
| Install | POST | `/wp-json/external/v1/plugins/install` |
| Update | POST | `/wp-json/external/v1/plugins/update` |
| Activate | POST | `/wp-json/external/v1/plugins/activate` |
| Deactivate | POST | `/wp-json/external/v1/plugins/deactivate` |
| Delete | POST | `/wp-json/external/v1/plugins/delete` |
| Inventory scan | GET | `/wp-json/external/v1/plugins` |

> **Important:** Delete uses **POST**, not HTTP DELETE.

### Signed download URL

- Host ZIP on dashboard: `storage/app/plugin-packages/`
- Route: `GET /plugin-deployments/download/{uuid}?expires=...&signature=...`
- Must be **HTTPS**
- Remote sites allowlist `diamondpbn.com` (or configured host)

### Response handling (deploy history table)

Store per domain:

| Field | Source |
|-------|--------|
| `version_before` | Pre-flight or response `data.previous_version` |
| `version_after` | Response `data.new_version` |
| `action` | Response `data.action` (`installed`, `updated`, `skipped`, `deleted`) |
| `plugin_file` | Response `data.plugin_file` (when present) |
| `resolved_via` | Response `data.resolved_via` (debug) |
| `http_status` | Response code |
| `error_code` | WP_Error code if failed |

### Success response examples

**Update:**
```json
{
  "success": true,
  "message": "Plugin updated successfully.",
  "data": {
    "slug": "custom-blogroll-version-1.6",
    "folder_slug": "custom-blog-roll",
    "plugin_file": "custom-blog-roll/index.php",
    "previous_version": "1.5",
    "new_version": "1.6",
    "activated": true,
    "action": "updated"
  }
}
```

**Delete:**
```json
{
  "success": true,
  "message": "Plugin deleted.",
  "data": {
    "slug": "custom-blogroll-version-1.6",
    "plugin_file": "custom-blog-roll/index.php",
    "deleted": true,
    "action": "deleted",
    "resolved_via": "version_match"
  }
}
```

---

## Phase 5 — Error handling

| HTTP | Code | Dashboard UI / job behavior |
|------|------|----------------------------|
| 404 | `plugin_not_found` | Install instead of update/delete; or mark "not on site" |
| 409 | `plugin_already_installed` | Retry as **Update** |
| 409 | `ambiguous_plugin` | Show `candidates` from response; retry with `plugin_file` or ensure `target_version` sent |
| 403 | `protected_plugin` | Block delete/deactivate of Diamond PBN agent |
| 400 | `invalid_download_host` | Config issue — check signed URL host |
| 400 | `checksum_mismatch` | Re-upload package or regenerate checksum |
| 400 | `slug_mismatch` | ZIP folder ≠ `expected_slug` — fix library package |
| 424 | `filesystem_not_writable` | Site hosting issue — show in rollout report |
| 424 | `agent_outdated` | Agent < 8.0.0 — manual upgrade path |
| 500 | `install_failed` | Show WordPress message from response |

### Ambiguous delete (409) — dashboard retry logic

When response includes `candidates`:

```json
{
  "code": "ambiguous_plugin",
  "data": {
    "candidates": [
      { "slug": "custom-blog-roll", "version": "2.3", "plugin_file": "custom-blog-roll/index.php", "active": true },
      { "slug": "custom-blogroll-old", "version": "1.6", "plugin_file": "custom-blogroll-old/index.php", "active": false }
    ]
  }
}
```

Dashboard should:

1. Prefer candidate where `version` === package.`version`
2. If exactly one match → retry delete with `plugin_file`
3. If still ambiguous → show admin both paths in deploy history

---

## Phase 6 — UI changes (Plugin Manager)

### Plugin Library table

Add / show columns:

- **Library slug** (internal)
- **WP folder (`expected_slug`)** — make visible to admins
- **Version**
- **Checksum** (truncated, copy button)

### Deploy Plugin form

When admin selects a library package, show read-only:

```
WordPress folder: custom-blog-roll
Version: 1.6
Operation: Install | Update | Delete (auto-detected per domain)
```

### Deploy History

Per domain row:

| Domain | Operation | Before | After | Status | Detail |
|--------|-----------|--------|-------|--------|--------|
| bitopower.co.uk | delete | 1.6 | — | ✅ deleted | custom-blog-roll/index.php |

Failed row should show WordPress error message + error code.

---

## Phase 7 — Laravel implementation checklist

### Database & models

- [ ] Migration: add `expected_slug`, ensure `version`, `checksum_sha256`, `plugin_name` on plugin packages
- [ ] Auto-parse ZIP on upload; populate `expected_slug` + headers
- [ ] Validate `library_slug` unique; `expected_slug` + `version` can repeat across packages

### Services

- [ ] `PluginPackageUploadService` — ZIP validation + metadata extraction
- [ ] `PluginDeployPayloadBuilder` — builds JSON for each operation (always includes `expected_slug` + `target_version`)
- [ ] `RemotePluginClient` — HTTP client to WordPress REST API
- [ ] `PluginDeployPreflightService` — status + inventory + operation selection

### Queue jobs

- [ ] `DeployPluginInstallJob`
- [ ] `DeployPluginUpdateJob`
- [ ] `DeployPluginDeleteJob`
- [ ] `DeployPluginActivateJob` / `DeployPluginDeactivateJob`
- [ ] All jobs: log request payload + full response to `plugin_deploy_logs`

### Routes (dashboard server)

- [ ] `GET /plugin-deployments/download/{uuid}` — signed URL (already exists or add)
- [ ] Signature expiry refresh if job delayed

### Deploy operation selector (critical logic)

```php
public function resolveOperation(Domain $domain, PluginPackage $package, string $intent): string
{
    $inventory = $this->client->getPlugins($domain);
    $match = collect($inventory)->first(function ($plugin) use ($package) {
        return $plugin['slug'] === $package->expected_slug
            && version_compare($plugin['version'], $package->version, '==');
    });

    return match ($intent) {
        'install' => $match ? 'skip' : ($this->hasFolder($inventory, $package->expected_slug) ? 'update' : 'install'),
        'update'  => $match ? 'skip' : ($this->hasFolder($inventory, $package->expected_slug) ? 'update' : 'install'),
        'delete'  => $match || $this->hasFolder($inventory, $package->expected_slug) ? 'delete' : 'skip',
        default   => throw new InvalidArgumentException(),
    };
}
```

---

## Phase 8 — Testing checklist (dashboard)

1. **Upload blogroll v1.6 ZIP** — verify `expected_slug` = `custom-blog-roll`, version = `1.6`
2. **Install** on clean site — success; registry created on WordPress
3. **Update** same package on same site — skip or success `action: skipped`
4. **Update** v1.5 → v1.6 on site with manual v1.5 — success `previous_version: 1.5`, `new_version: 1.6`
5. **Delete v1.6** on site with v1.6 + v2.3 blogroll — only v1.6 removed (`target_version` sent)
6. **Delete** without `target_version` — should fail with 409; dashboard retries with version
7. **Self-update** Diamond PBN agent — `expected_slug` = `external-api-manager`
8. **Skip outdated agents** — `/status` `plugin_manager_supported: false`
9. **Deploy by category** — same signed URL for all domains in category
10. **Checksum failure** — corrupt URL → 400, shown in history

---

## Minimum viable dashboard fix (do this first)

If implementing everything at once is too much, ship these **four changes** first:

1. **Store `expected_slug`** on every library package (from ZIP folder on upload)
2. **Include in every deploy request:** `expected_slug` + `target_version` (from package.version)
3. **Pre-flight:** `GET /plugins` → choose Install vs Update vs Skip
4. **Delete payload:** always send `expected_slug` + `target_version` (fixes blogroll v1.6 case)

---

## Version compatibility

| WordPress agent | Dashboard features supported |
|-----------------|------------------------------|
| < 8.0.0 | Manual only — skip in deploy UI |
| 8.0.0 – 8.1.1 | Install/update broken for manual plugins — upgrade agent first |
| 8.1.2+ | Update via ZIP overwrite works |
| 8.1.3+ | Deployment registry after install/update |
| **8.1.4+** | Version-aware delete, blogroll multi-version fix |

**Recommendation:** Require agent **≥ 8.1.4** before enabling Plugin Manager deploy UI for a category.

---

## Related documentation

- [PLUGIN_MANAGER_WORDPRESS_CHANGES.md](./PLUGIN_MANAGER_WORDPRESS_CHANGES.md) — WordPress agent API spec
- WordPress REST base: `https://{domain}/wp-json/external/v1/`
