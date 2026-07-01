# Plugin Manager — Diamond PBN Dashboard (Laravel) Changes

> **Last updated:** 2026-06-30  
> **Companion doc:** [PLUGIN_MANAGER_WORDPRESS_CHANGES.md](./PLUGIN_MANAGER_WORDPRESS_CHANGES.md)  
> **Required WordPress agent:** Diamond PBN **≥ 8.1.5**

---

## Overview

The Laravel dashboard at **diamondpbn.com** hosts plugin ZIPs and deploys them to ~3,000 remote WordPress sites by category or manual domain list. Each site runs the **Diamond PBN** agent plugin, which executes install/update/delete via REST API.

**Dashboard responsibilities:**

1. Upload ZIP once → Plugin Library  
2. Store metadata (`expected_slug`, version, checksum)  
3. Queue deploy jobs per domain  
4. Send signed download URL + correct payload to WordPress  
5. Log results in Deploy History  

---

## Critical: two slugs (most common bug source)

Every library package has **two different slugs**:

| Field | Example | Purpose |
|-------|---------|---------|
| **`library_slug`** | `pro-elements-version-410` | Dashboard catalog / deploy history |
| **`expected_slug`** | `pro-elements` | WordPress folder inside ZIP |

### Error when wrong

```
ZIP folder "pro-elements" does not match expected slug "pro-elements-version-410"
```

**Cause:** Dashboard sent library slug as `expected_slug`.  
**Fix:** Send `expected_slug: "pro-elements"` (folder from ZIP).

### More examples

| Plugin | `library_slug` | `expected_slug` (ZIP folder) |
|--------|----------------|--------------------------------|
| Diamond PBN | `external-api-manager` | `external-api-manager` |
| Custom Blogroll v1.6 | `custom-blogroll-version-1.6` | `custom-blog-roll` |
| Hidden Links | `hidden-links-manager` | `hidden-links-manager` |
| Pro Elements v4.1.0 | `pro-elements-version-410` | `pro-elements` |

---

## Phase 1 — Database & library package model

### Table: `plugin_packages` (or equivalent)

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| `id` | uuid | yes | Primary key |
| `library_slug` | string | yes | Dashboard identifier |
| `expected_slug` | string | yes | **ZIP root folder** — auto-detect on upload |
| `plugin_name` | string | no | From ZIP `Plugin Name` header |
| `version` | string | yes | From ZIP `Version` header |
| `checksum_sha256` | string | yes | SHA256 of stored ZIP |
| `file_path` | string | yes | e.g. `storage/app/plugin-packages/{uuid}/file.zip` |
| `file_size_bytes` | int | yes | Max 5 MB |
| `notes` | text | no | Admin notes |
| `created_at` | timestamp | yes | |

### On ZIP upload — auto-extract metadata

```php
// Pseudocode
$zip = openZip($uploadedFile);
$expectedSlug = $zip->getRootFolderName();     // e.g. pro-elements
$headers = readPluginHeaders($zip, $expectedSlug);

PluginPackage::create([
    'library_slug'      => $request->library_slug ?? "{$expectedSlug}-version-{$headers['Version']}",
    'expected_slug'     => $expectedSlug,       // REQUIRED — never use library_slug here
    'plugin_name'       => $headers['Name'],
    'version'           => $headers['Version'],
    'checksum_sha256'   => hash_file('sha256', $storedPath),
    'file_path'         => $storedPath,
]);
```

**Validation on upload:**

- ZIP ≤ 5 MB  
- Single root folder with at least one `.php` file  
- Valid `Plugin Name` and `Version` headers  
- Show `expected_slug` to admin in UI (read-only)

---

## Phase 2 — Deploy payload builder

Create `App\Services\PluginDeployPayloadBuilder` — used by all queue jobs.

### Auth (all requests)

```
Header: X-External-API-Key: {domain.api_key}
或
Body:   "api_key": "{domain.api_key}"
```

### Install / Update

```json
{
  "api_key": "SITE_KEY",
  "delivery": "url",
  "download_url": "https://diamondpbn.com/plugin-deployments/download/{uuid}?expires=...&signature=...",
  "expected_checksum_sha256": "a1b2c3...",
  "slug": "pro-elements-version-410",
  "expected_slug": "pro-elements",
  "target_version": "4.1.0",
  "activate": true
}
```

| Field | Source | Required |
|-------|--------|----------|
| `slug` | `library_slug` | yes |
| `expected_slug` | package.`expected_slug` | **yes** |
| `target_version` | package.`version` | **yes** (multi-version sites) |
| `download_url` | signed URL | yes |
| `expected_checksum_sha256` | package.`checksum_sha256` | yes |
| `activate` | deploy form | yes (default true) |

### Delete

```json
{
  "api_key": "SITE_KEY",
  "slug": "custom-blogroll-version-1.6",
  "expected_slug": "custom-blog-roll",
  "target_version": "1.6"
}
```

Optional fallback when ambiguous:

```json
{
  "plugin_file": "custom-blog-roll/index.php"
}
```

### Activate / Deactivate

```json
{
  "api_key": "SITE_KEY",
  "slug": "custom-blogroll-version-1.6",
  "expected_slug": "custom-blog-roll",
  "target_version": "1.6"
}
```

---

## Phase 3 — Pre-flight (per domain, before deploy)

### Step 1 — Agent health

```
GET https://{domain}/wp-json/external/v1/status
```

| Check | If fail |
|-------|---------|
| HTTP 200 | Mark domain offline |
| `plugin_manager_supported: true` | Skip — show "Agent outdated (< 8.0.0)" |

### Step 2 — Inventory

```
GET https://{domain}/wp-json/external/v1/plugins?api_key=KEY
```

Or single plugin:

```
GET https://{domain}/wp-json/external/v1/plugins/{expected_slug}?api_key=KEY&target_version=1.6
```

### Step 3 — Choose operation

| Intent | Site state | Call |
|--------|------------|------|
| Install/Update | Folder not found | `POST /plugins/install` |
| Install/Update | Folder exists, older version | `POST /plugins/update` |
| Install/Update | Same version | **Skip** (no API call) |
| Delete | Plugin found | `POST /plugins/delete` |
| Delete | Not found | Skip — log "already removed" |

```php
// Skip same version
if ($remoteVersion && version_compare($remoteVersion, $package->version, '>=')) {
    $log->markSkipped('Already at target version');
    return;
}
```

---

## Phase 4 — Queue jobs & endpoints

| Dashboard operation | HTTP | WordPress endpoint |
|--------------------|------|-------------------|
| Install | POST | `/wp-json/external/v1/plugins/install` |
| Update | POST | `/wp-json/external/v1/plugins/update` |
| Activate | POST | `/wp-json/external/v1/plugins/activate` |
| Deactivate | POST | `/wp-json/external/v1/plugins/deactivate` |
| Delete | POST | `/wp-json/external/v1/plugins/delete` |
| Inventory | GET | `/wp-json/external/v1/plugins` |

> **Delete uses POST**, not HTTP DELETE.

### Signed download URL

- Store: `storage/app/plugin-packages/`  
- Route: `GET /plugin-deployments/download/{uuid}?expires=...&signature=...`  
- Must be HTTPS  
- WordPress allowlists `diamondpbn.com`

### Deploy history — store per domain

| Field | Source |
|-------|--------|
| `version_before` | Pre-flight or `data.previous_version` |
| `version_after` | `data.new_version` |
| `action` | `installed`, `updated`, `skipped`, `deleted` |
| `plugin_file` | `data.plugin_file` |
| `resolved_via` | `data.resolved_via` |
| `error_code` | WP error code on failure |

---

## Phase 5 — Error handling

| HTTP | Code | Dashboard action |
|------|------|------------------|
| 404 | `plugin_not_found` | Use Install instead of Update/Delete |
| 409 | `plugin_already_installed` | Retry as Update |
| 409 | `ambiguous_plugin` | Send `target_version` or `plugin_file`; show `candidates` |
| 400 | `slug_mismatch` | Fix `expected_slug` on package |
| 400 | `checksum_mismatch` | Re-upload or fix checksum |
| 400 | `invalid_download_host` | Check signed URL domain |
| 403 | `protected_plugin` | Cannot delete/deactivate Diamond PBN agent |
| 424 | `filesystem_not_writable` | Hosting issue — show in report |
| 424 | `agent_outdated` | Manual agent upgrade required |
| 500 | `install_failed` | Show WordPress message |

### Ambiguous delete (409) — retry logic

Response includes `candidates` array. Dashboard should:

1. Match candidate where `version` === package.`version`  
2. Retry with `plugin_file` from that candidate  
3. If still fails, show both options in UI  

---

## Phase 6 — UI changes

### Plugin Library table

Show columns:

- Name / version  
- **Library slug**  
- **WP folder (`expected_slug`)** ← make visible  
- Size / uploaded date  
- Actions: Deploy, Download, Delete  

### Deploy form

When package selected, display:

```
Library slug:    pro-elements-version-410
WP folder:       pro-elements          ← from expected_slug
Version:         4.1.0
Operation:       auto (Install / Update / Delete per domain)
```

### Deploy History

Per-domain: operation, version before/after, status, error detail, `plugin_file`.

---

## Phase 7 — Laravel implementation checklist

### Database & models

- [ ] Migration: `expected_slug`, `version`, `checksum_sha256`, `plugin_name` on packages  
- [ ] Auto-parse ZIP on upload  
- [ ] Never save library slug as `expected_slug`  

### Services

- [ ] `PluginPackageUploadService` — ZIP validation + metadata  
- [ ] `PluginDeployPayloadBuilder` — always includes `expected_slug` + `target_version`  
- [ ] `RemotePluginClient` — HTTP to WordPress REST  
- [ ] `PluginDeployPreflightService` — status + inventory + operation pick  

### Queue jobs

- [ ] `DeployPluginInstallJob`  
- [ ] `DeployPluginUpdateJob`  
- [ ] `DeployPluginDeleteJob`  
- [ ] `DeployPluginActivateJob` / `DeployPluginDeactivateJob`  
- [ ] Log full request + response  

### Routes

- [ ] Signed download URL with expiry refresh for delayed jobs  

---

## Minimum viable fix (ship first)

1. Store **`expected_slug`** from ZIP folder on every upload  
2. Send **`expected_slug` + `target_version`** on every install/update/delete  
3. Pre-flight **`GET /plugins`** → Install vs Update vs Skip  
4. Handle **409 ambiguous_plugin** with `plugin_file` retry  

---

## Testing checklist

- [ ] Upload blogroll v1.6 — `expected_slug` = `custom-blog-roll`  
- [ ] Upload pro-elements — `expected_slug` = `pro-elements` (not library slug)  
- [ ] Install on clean site  
- [ ] Update v1.5 → v1.6 on manual site  
- [ ] Delete v1.6 when v2.3 also installed (only v1.6 removed)  
- [ ] Skip when same version  
- [ ] Self-update Diamond PBN agent  
- [ ] Category deploy — same signed URL for all domains in category  
- [ ] Agent outdated — skip site gracefully  

---

## Version compatibility

| WordPress agent | Dashboard support |
|-----------------|-------------------|
| < 8.0.0 | Manual only — skip in deploy |
| 8.0.0 – 8.1.1 | Update broken for manual plugins |
| 8.1.2+ | Update via ZIP overwrite |
| 8.1.3+ | Deployment registry |
| 8.1.4+ | Version-aware delete |
| **8.1.5+** | Auto-fix library slug sent as `expected_slug` |

**Recommendation:** Require agent **≥ 8.1.5** before category rollouts.

---

## Related

- [PLUGIN_MANAGER_WORDPRESS_CHANGES.md](./PLUGIN_MANAGER_WORDPRESS_CHANGES.md) — WordPress agent API & implementation
