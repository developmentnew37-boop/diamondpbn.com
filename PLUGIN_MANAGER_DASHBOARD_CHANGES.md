# Remote Plugin Manager — Dashboard (Laravel) Changes

> **Last updated:** 2026-06-24  
> **Location:** Project root (easy reference for dashboard development)  
> **Purpose:** Specification for the **Diamond PBN Laravel dashboard** — upload plugin ZIPs once, select from library, deploy by **category** or **manual domain list**.  
> **Companion doc:** [PLUGIN_MANAGER_WORDPRESS_CHANGES.md](./PLUGIN_MANAGER_WORDPRESS_CHANGES.md)

---

## Pre-development review (read before coding)

Issues found during spec review — **must be implemented as written below**, not from older draft fragments.

| # | Issue | Resolution in this doc |
|---|--------|------------------------|
| 1 | Routes omitted `/admin` prefix | All admin routes use `/admin/domain/plugin-manager/...` (see Routes) |
| 2 | `plugin_deployment_items` schema was incomplete | Full schema below |
| 3 | `plugin_deployments` missing retry/chunk columns | Added `phase`, `processed_count`, timestamps |
| 4 | Phase 1 vs “skip if same version” conflict | Phase 1 **includes** skip-if-same + pre-flight `GET /plugins/{slug}` |
| 5 | Signed download URL must be public | Route lives **outside** `admin.auth`; uses HMAC only |
| 6 | Queue worker not documented | Add `plugin_deployments` to `composer.json` dev + Supervisor |
| 7 | Delete deploy operation | Uses `slug` from selected package — no ZIP download |
| 8 | Version compare for “update if older” | Use PHP `version_compare()` on plugin header versions |
| 9 | Disconnected domains (`status = 0`) | Skip with message unless admin enables “include disconnected” (Phase 2) |
| 10 | Duplicate upload same slug+version | DB unique index + validation error |

**Build order:** WordPress plugin Phase 1 endpoints first → then dashboard Phase 1 (cannot deploy without agent API).

---

## Overview

### Problem

~3,000 domains in **14 categories** cannot be updated manually when plugins change. Operations must be **targeted by category or manual list** — never all sites at once without explicit scope.

### Solution — two separate actions

| Action | Page | How often |
|--------|------|-----------|
| **Upload** | Plugin Library | **Once per plugin version** |
| **Deploy** | Deploy wizard | **Many times** — pick library package + category |

```
Upload v8.0.0.zip once → saved in Plugin Library
                              │
         ┌────────────────────┼────────────────────┐
         ▼                    ▼                    ▼
   Deploy → Category 1   Deploy → Category 2  …  Category 14
   (select v8.0.0)       (select v8.0.0)         (select v8.0.0)
   NO re-upload          NO re-upload            NO re-upload
```

After upload, the ZIP stays hosted on the dashboard server. Every deploy **selects** that package from a dropdown — you never upload the same version again for each category.

---

## Plugin hosting (Option A)

All plugin files are **hosted on the Laravel app server** — no S3 or CDN required.

| Factor | Reality |
|--------|---------|
| Plugin size | Single-file plugins **under 1 MB** |
| Storage | ~10 versions × 1 MB ≈ 10 MB on disk |
| Per batch bandwidth | 500 domains × 1 MB ≈ 500 MB egress |
| Upload | Once per version → `storage/app/plugin-packages/` |
| Remote delivery | Signed download URL per domain |

```
Admin uploads ZIP once
        ↓
storage/app/plugin-packages/{uuid}/{slug}-{version}.zip
        ↓
Deploy to category → each remote site GETs signed URL → installs
```

---

## Upload once, deploy many times

### Step 1 — Upload (Plugin Library)

**Route:** `/admin/domain/plugin-manager`

Upload `external-api-manager-8.0.0.zip` **one time**.

Dashboard stores:

| Field | Example |
|-------|---------|
| Name | Diamond Pbn |
| Slug | external-api-manager |
| Version | 8.0.0 |
| Size | 620 KB |
| SHA256 | abc123… |
| Hosted path | storage/app/plugin-packages/… |

When **v8.1.0** is ready, upload again → library now has **both** 8.0.0 and 8.1.0. You choose which to deploy.

**You do NOT upload again when moving to the next category.**

### Step 2 — Deploy (by category or manual)

**Route:** `/admin/domain/plugin-manager/deploy`

1. **Operation:** Update / Install / Update if older / Activate / Deactivate / Delete  
2. **Package:** Select **Diamond Pbn v8.0.0** from library dropdown  
3. **Target:** **By category** → pick one of your 14 categories  
4. **Options:** Activate after · Skip if same version  
5. **Start** → queue runs for domains in that category only  

Repeat for categories 2–14: same package selection, change category only.

### Example: 14 categories rollout

| Step | Upload? | Package selected | Target |
|------|---------|------------------|--------|
| Upload once | Yes | — | — |
| Deploy 1 | No | Diamond Pbn v8.0.0 | Category 1 |
| Deploy 2 | No | Diamond Pbn v8.0.0 | Category 2 |
| … | No | Diamond Pbn v8.0.0 | … |
| Deploy 14 | No | Diamond Pbn v8.0.0 | Category 14 |

Later, upload v8.1.0 once → deploy v8.1.0 across categories the same way.

---

## Version handling

### Multiple versions in library

Each upload creates a **separate library row** (`slug` + `version`):

| Name | Slug | Version | Actions |
|------|------|---------|---------|
| Diamond Pbn | external-api-manager | 7.8.0 | Deploy · Delete |
| Diamond Pbn | external-api-manager | 8.0.0 | Deploy · Delete |
| Custom Blogroll | custom-blogroll | 1.2.0 | Deploy · Delete |

Deploy wizard always picks **one specific version**.

### Per-domain behaviour during deploy

| Site has | Operation | Result |
|----------|-----------|--------|
| v7.8.0 | Update → v8.0.0 | Update; log 7.8.0 → 8.0.0 |
| v8.0.0 | Update + skip same | **Skipped** — no download |
| Not installed | Update | Failed/skipped — use Install |
| v8.1.0 | Update if older → v8.0.0 | **Skipped** — already newer |

### Rollback

Keep old version in library (e.g. v7.8.0) → deploy Update with v7.8.0 to failed domains or manual list.

---

## Other plugins (same library, same flow)

Not limited to Diamond PBN. Any WordPress plugin ZIP:

1. **Upload once** → e.g. `custom-blogroll-1.2.0.zip` in library  
2. **Deploy many times** by category:
   - Install v1.2.0 → Category 3  
   - Install v1.2.0 → Category 7  

Same upload-once / select-from-library / deploy-by-category pattern.

---

## Reference: reuse Domain Status Checker patterns

| Existing | Reuse for Plugin Manager |
|----------|--------------------------|
| `DomainStatusCheckerService::parseDomainList()` | Manual domain textarea |
| `DomainStatusCheckerService::getDomainNamesFromInventory($categoryId)` | **14 category dropdown** |
| `DomainStatusCheck` + items | `PluginDeployment` + items |
| `ProcessDomainStatusCheckChunkJob` | `ProcessPluginDeploymentChunkJob` |
| `status-checker.blade.php` tabs | Manual / By category tabs |

Files:

- `app/Services/DomainStatusCheckerService.php`
- `app/Http/Controllers/Admin/DomainStatusCheckerController.php`
- `resources/views/admin/domains/status-checker.blade.php`

---

## User-facing features

### 1. Plugin Library (`/admin/domain/plugin-manager`)

| Action | Description |
|--------|-------------|
| **Upload ZIP** | Add new version or new plugin — **once per version** |
| **List packages** | All uploaded versions; group/filter by slug |
| **Deploy** | Shortcut to deploy wizard with this package pre-selected |
| **Delete** | Remove hosted file (if no active deployment) |
| **Download** | Admin audit copy |

**Validation:** `.zip` only, max **5 MB** (typical < 1 MB), valid plugin header, SHA256 computed.

### 2. Deploy wizard (`/admin/domain/plugin-manager/deploy`)

**Step 1 — Operation:** Install · Update · Update if older · Activate · Deactivate · Delete  

**Step 2 — Package:** Dropdown from library (e.g. "Diamond Pbn **v8.0.0**") — **not a file upload**  

**Step 3 — Target domains:**

#### Tab A: Manual list
- One domain per line
- Max 500 per deployment

#### Tab B: By category
- Dropdown of all **14 domain categories**
- Shows count: "142 domains in **Tier-1**"
- Processes only domains in that category

**Step 4 — Options:**
- Activate after install/update (default ON)
- Skip if same version (default ON)
- Chunk size (default 5)

**Step 5 — Confirm & start**

### 3. Progress & history

**Route:** `/admin/domain/plugin-manager/deployments/{uuid}`

**History:** `/admin/domain/plugin-manager/deployments`

| Column | Example |
|--------|---------|
| Domain | site1.com |
| Category | Tier-1 |
| Before | 7.8.0 |
| After | 8.0.0 |
| Status | success / failed / skipped |

**Actions:** Retry failed only · Export CSV · Cancel queued

---

## Database schema

### `plugin_packages`

```php
Schema::create('plugin_packages', function (Blueprint $table) {
    $table->id();
    $table->uuid('uuid')->unique();
    $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
    $table->string('slug');
    $table->string('name');
    $table->string('version');
    $table->string('original_filename');
    $table->string('storage_path');  // storage/app/plugin-packages/...
    $table->unsignedBigInteger('file_size_bytes');
    $table->string('checksum_sha256', 64);
    $table->boolean('is_diamond_pbn_agent')->default(false);
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->unique(['slug', 'version']);  // prevent duplicate library entries
});
```

### `plugin_deployments`

One row per deploy run (one category or one manual list per run).

```php
Schema::create('plugin_deployments', function (Blueprint $table) {
    $table->id();
    $table->uuid('uuid')->unique();
    $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
    $table->foreignId('plugin_package_id')->constrained('plugin_packages')->cascadeOnDelete();
    $table->string('operation', 20);
    $table->string('source', 20)->default('manual');  // manual|inventory
    $table->foreignId('domain_category_id')->nullable()->constrained('domain_categories')->nullOnDelete();
    $table->string('status', 20)->default('queued');   // queued|running|completed|failed|cancelled
    $table->string('phase', 20)->default('initial');   // initial|retry (mirror status checker)
    $table->unsignedInteger('total_count')->default(0);
    $table->unsignedInteger('processed_count')->default(0);
    $table->unsignedInteger('success_count')->default(0);
    $table->unsignedInteger('failed_count')->default(0);
    $table->unsignedInteger('skipped_count')->default(0);
    $table->boolean('activate_after')->default(true);
    $table->boolean('skip_if_same_version')->default(true);
    $table->text('status_message')->nullable();
    $table->timestamp('started_at')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->timestamps();

    $table->index(['admin_id', 'status']);
});
```

### `plugin_deployment_items`

Per-domain result (mirror `domain_status_check_items`).

```php
Schema::create('plugin_deployment_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('plugin_deployment_id')->constrained('plugin_deployments')->cascadeOnDelete();
    $table->string('domain');
    $table->unsignedInteger('sort_order')->default(0);
    $table->string('item_status', 20)->default('pending');  // pending|processing|success|failed|skipped
    $table->string('operation_result', 20)->nullable();    // installed|updated|activated|deleted|skipped
    $table->string('version_before')->nullable();
    $table->string('version_after')->nullable();
    $table->string('error_code')->nullable();
    $table->string('message')->nullable();
    $table->unsignedSmallInteger('attempts')->default(0);
    $table->unsignedInteger('response_time_ms')->nullable();
    $table->boolean('in_inventory')->default(false);
    $table->unsignedBigInteger('domain_id')->nullable();
    $table->string('category')->nullable();  // snapshot of domain category name
    $table->timestamp('processed_at')->nullable();
    $table->timestamps();

    $table->index(['plugin_deployment_id', 'item_status']);
    $table->index(['plugin_deployment_id', 'sort_order']);
});
```

---

## Application architecture

```
app/Http/Controllers/Admin/
  PluginPackageController.php      # Upload, list, signed download
  PluginDeploymentController.php   # Deploy, progress, retry

app/Services/
  PluginPackageService.php         # Store hosted ZIP, parse header
  PluginDeploymentService.php      # Category/manual resolution, chunks
  RemotePluginManagerService.php   # Calls WP /plugins/* API

app/Jobs/
  ProcessPluginDeploymentChunkJob.php   # Queue: plugin_deployments

config/plugin_manager.php
resources/views/admin/domains/plugin-manager/
public/js/plugin-manager.js
```

---

## Signed download URL

Remote WordPress downloads the **hosted file** — admin never re-uploads per deploy.

```
GET {APP_URL}/plugin-deployments/download/{packageUuid}?expires={unix}&signature={hmac}
```

- **Public route** (no admin session) — WordPress servers must reach `APP_URL` over HTTPS
- Streams from `storage_path` on Laravel disk (`Storage::disk(config('plugin_manager.storage_disk'))`)
- HMAC: `hash_hmac('sha256', "{uuid}:{expires}", signing_key)` where signing_key = `PLUGIN_MANAGER_SIGNING_KEY` or `APP_KEY`
- Reject expired or bad signature with 403
- Regenerate signature in queue job if deployment runs longer than TTL (same file, new `expires`)

**Security note:** Remote sites can only download during valid signature window. Optionally bind signature to `deployment_uuid` as well: `"{packageUuid}:{deploymentUuid}:{expires}"`.

Each queue job sends to WordPress (install/update only):

```php
Http::post($baseUrl . '/wp-json/external/v1/plugins/update', [
    'api_key'                  => $domain->api_key,
    'delivery'                 => 'url',
    'download_url'             => $signedUrl,
    'slug'                     => $package->slug,
    'expected_checksum_sha256' => $package->checksum_sha256,
    'activate'                 => true,
]);
```

**Pre-flight per domain (required in Phase 1):**

1. Resolve domain from inventory → need `api_key`; if missing → **skipped** `not_in_inventory`
2. `GET /wp-json/external/v1/status` — if `plugin_manager_supported !== true` → **skipped** `agent_outdated`
3. For update operations: `GET /plugins/{slug}` — read `version_before`
4. **Skip if same version:** `version_compare($remote, $package->version, '>=')` → **skipped** `already_current`
5. **Update if older:** skip when remote version is newer than package version

**Operations without ZIP download:** `activate`, `deactivate`, `delete` — POST slug only to WordPress; no signed URL.

**Version comparison:** Always use PHP `version_compare()` (WordPress semver-style), not string equality.

---

## Routes

Add to `routes/web.php` (public signed download — **outside** admin auth):

```php
Route::get('/plugin-deployments/download/{uuid}', [PluginPackageController::class, 'download'])
    ->middleware('throttle:300,1')
    ->name('plugin-deployments.download');
```

Add to `routes/admin.php` inside `Route::prefix('admin')->name('admin.')->middleware('admin.auth')` (same group as status checker):

```php
Route::prefix('domain/plugin-manager')
    ->name('domain.plugin-manager.')
    ->middleware(\App\Http\Middleware\Admin\CanCreateCampaigns::class)
    ->group(function () {
        Route::get('/', [PluginPackageController::class, 'index'])->name('index');
        Route::post('/packages', [PluginPackageController::class, 'store'])->name('packages.store');
        Route::delete('/packages/{uuid}', [PluginPackageController::class, 'destroy'])->name('packages.destroy');

        Route::get('/deploy', [PluginDeploymentController::class, 'create'])->name('deploy.create');
        Route::post('/deploy', [PluginDeploymentController::class, 'start'])
            ->middleware('throttle:10,1')
            ->name('deploy.start');

        Route::get('/deployments', [PluginDeploymentController::class, 'index'])->name('deployments.index');
        Route::get('/deployments/{uuid}', [PluginDeploymentController::class, 'show'])->name('deployments.show');
        Route::get('/deployments/{uuid}/progress', [PluginDeploymentController::class, 'progress'])
            ->middleware('throttle:120,1')
            ->name('deployments.progress');
        Route::post('/deployments/{uuid}/retry-failed', [PluginDeploymentController::class, 'retryFailed'])
            ->name('deployments.retry-failed');
        Route::post('/deployments/{uuid}/cancel', [PluginDeploymentController::class, 'cancel'])
            ->name('deployments.cancel');
    });
```

**Full URLs:** `/admin/domain/plugin-manager`, `/admin/domain/plugin-manager/deploy`, etc.

**Sidebar:** Add "Plugin Manager" under Domains menu (after Status Checker). Add `admin.domain.plugin-manager` to `$domainRoutes` in `sidebar.blade.php`.

---

## Configuration (`config/plugin_manager.php`)

```php
return [
    'max_domains_per_deployment' => (int) env('PLUGIN_MANAGER_MAX_DOMAINS', 500),
    'max_zip_mb'                 => (int) env('PLUGIN_MANAGER_MAX_ZIP_MB', 5),
    'storage_disk'               => env('PLUGIN_MANAGER_STORAGE_DISK', 'local'),
    'chunk_size'                 => (int) env('PLUGIN_MANAGER_CHUNK_SIZE', 5),
    'request_timeout'            => (int) env('PLUGIN_MANAGER_REQUEST_TIMEOUT', 180),
    'job_timeout'                => (int) env('PLUGIN_MANAGER_JOB_TIMEOUT', 300),
    'lock_seconds'               => (int) env('PLUGIN_MANAGER_LOCK_SECONDS', 360),
    'diamond_pbn_slug'           => 'external-api-manager',
    'download_url_ttl_hours'     => (int) env('PLUGIN_MANAGER_DOWNLOAD_TTL_HOURS', 24),
    'signing_key'                => env('PLUGIN_MANAGER_SIGNING_KEY'),  // fallback APP_KEY
    'keep_last_per_admin'        => (int) env('PLUGIN_MANAGER_KEEP_LAST', 10),
    'retention_days'             => (int) env('PLUGIN_MANAGER_RETENTION_DAYS', 30),
];
```

### Queue worker (required)

Add `plugin_deployments` to queue listeners:

- **`composer.json`** `dev` script: append `,plugin_deployments` to `--queue=...`
- **Supervisor** (`deploy/supervisor/laravel-worker.conf`): add queue name or run dedicated worker:

```ini
command=php ... artisan queue:work database --queue=plugin_deployments,default --sleep=3 --tries=2
```

### Category truncation (>500 domains)

When category has more domains than `max_domains_per_deployment`, show warning (same as status checker): process first 500; admin starts second deployment for remainder.

### `.env.example` additions

```env
PLUGIN_MANAGER_MAX_DOMAINS=500
PLUGIN_MANAGER_MAX_ZIP_MB=5
PLUGIN_MANAGER_CHUNK_SIZE=5
PLUGIN_MANAGER_REQUEST_TIMEOUT=180
PLUGIN_MANAGER_SIGNING_KEY=
PLUGIN_MANAGER_DOWNLOAD_TTL_HOURS=24
```

---

## UI mockup

```
┌─────────────────────────────────────────────────────────────┐
│  PLUGIN LIBRARY                                             │
│  [ Upload new ZIP ]                                         │
│  ┌──────────────────────────────────────────────────────┐   │
│  │ Diamond Pbn  v8.0.0  620KB  Jun 24  [Deploy] [Delete]│   │
│  │ Diamond Pbn  v7.8.0  580KB  May 10  [Deploy] [Delete]│   │
│  │ Custom Blogroll v1.2.0  45KB       [Deploy] [Delete] │   │
│  └──────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│  DEPLOY PLUGIN                                              │
│  Operation:  [ Update ▼ ]                                   │
│  Package:    [ Diamond Pbn v8.0.0 ▼ ]  ← from library      │
│                                                             │
│  Target:  [ Manual list ]  [ By category ● ]                │
│  Category: [ Category 3 ▼ ]  (187 domains)                  │
│                                                             │
│  ☑ Activate after update                                    │
│  ☑ Skip if already on this version                          │
│  [ Start deployment ]                                       │
└─────────────────────────────────────────────────────────────┘
```

---

## Workflow examples

### Roll out Diamond PBN v8.0.0 to all 14 categories

1. Upload `external-api-manager-8.0.0.zip` once  
2. Deploy → Update → select v8.0.0 → Category 1 → Start  
3. Wait for progress; retry failed if needed  
4. Deploy → Update → select **same v8.0.0** → Category 2 → Start  
5. Repeat for categories 3–14 — **no further uploads**

### Add helper plugin to 2 categories

1. Upload `custom-blogroll-1.2.0.zip` once  
2. Deploy Install v1.2.0 → Category 5  
3. Deploy Install v1.2.0 → Category 9  

### Partial failure in one category

- Category 6: 180 success, 5 failed  
- **Retry failed** — same v8.0.0 from library, 5 domains only  

---

## Authorization

Super Admin and Admin only. Members cannot access Plugin Manager.

---

## Implementation phases

### Phase 1 — MVP (aligns with WordPress plugin v8.0.0)
- [ ] Migrations: `plugin_packages`, `plugin_deployments`, `plugin_deployment_items`
- [ ] Plugin library (upload once, list, select, unique slug+version)
- [ ] Deploy by category + manual list (reuse `DomainStatusCheckerService` domain resolution)
- [ ] Operations: **update** + **update_if_older** (Diamond PBN slug only in UI)
- [ ] Pre-flight: `/status` + `/plugins/{slug}` + skip-if-same (`version_compare`)
- [ ] Signed download URL (public route + HMAC)
- [ ] `ProcessPluginDeploymentChunkJob` on `plugin_deployments` queue
- [ ] Progress page + retry failed + cancel queued
- [ ] Sidebar + `CanCreateCampaigns` middleware
- [ ] Update `composer.json` dev queue list

### Phase 2
- [ ] Install / activate / deactivate / delete operations
- [ ] All plugins in library (not only Diamond PBN)
- [ ] Deployments history index + export failures CSV
- [ ] `PrunePluginDeploymentsJob` scheduled cleanup

### Phase 3
- [ ] Version drift report per category
- [ ] Email on deployment complete

---

## Testing checklist

1. Upload v8.0.0 → appears in library  
2. Deploy to category 1 — no re-upload prompt  
3. Deploy same v8.0.0 to category 2 — selects from library  
4. Skip if same version works on re-run  
5. Upload v8.1.0 — both versions in library  
6. Manual list deploy works  
7. Signed URL downloads correctly on remote WP  
8. Retry failed uses same library package  

---

## Related documentation

- [PLUGIN_MANAGER_WORDPRESS_CHANGES.md](./PLUGIN_MANAGER_WORDPRESS_CHANGES.md) — WordPress plugin API
- [API-DATA-FLOW.md](./API-DATA-FLOW.md) — Existing REST conventions
- `app/Services/DomainStatusCheckerService.php` — Category/manual domain selection reference
