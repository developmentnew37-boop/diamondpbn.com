# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Diamond PBN Automation is a Laravel 12 application for managing Private Blog Network (PBN) campaigns. It orchestrates publishing content to remote WordPress sites via HTTP APIs. The system manages domain inventories, article libraries, and multiple campaign types with queued background job processing.

## Development Commands

### Setup
```bash
composer setup           # Full setup: install deps, copy .env, generate key, migrate, build assets
composer install         # Install PHP dependencies only
npm install              # Install Node dependencies
php artisan migrate      # Run database migrations
```

### Development Server
```bash
composer run dev         # Start ALL: server (8000) + queue worker + Vite (concurrent)
php artisan serve        # Server only (port 8000)
php artisan queue:listen --tries=1 --queue=default,deletions,campaigns,sidebar_campaigns
npm run dev              # Vite dev server only
```

The `composer run dev` command runs three processes concurrently:
- Laravel dev server on port 8000
- Queue worker listening to primary queues
- Vite for hot module replacement

### Testing & Code Quality
```bash
composer test            # Run PHPUnit test suite
./vendor/bin/pint        # Laravel Pint code formatter
php artisan test         # Alternative test command
```

### Build
```bash
npm run build            # Production Vite build (outputs to public/build/)
```

## Architecture

### Campaign Processing Flow

The core workflow involves four domain entities that interact through queued jobs:

1. **Domains** (`domains` table) — Remote WordPress sites with API keys
2. **Articles** (`articles` table) — Content library with multilingual support, soft-deletable
3. **Campaigns** (`campaigns`, `sidebar_campaigns`, etc.) — Publishing orchestration records
4. **Tasks/Posts** (`campaign_posts`, `sidebar_campaign_tasks`, etc.) — Individual publish jobs

**Campaign Creation Flow:**
- User creates campaign → selects domain category + article category/set + keywords/URLs
- System generates **campaign_articles** (article + keyword/url pairs) and **campaign_domains** (selected domains)
- Cartesian product creates **campaign_posts** (one per domain × article pair)
- Jobs dispatched to queues → each job publishes to one remote WordPress site
- Articles are **soft-deleted** after successful publish (snapshots preserved in `campaign_articles`)

### Queue Architecture

Jobs are dispatched to **named queues** by responsibility. Production workers must listen to all relevant queues:

**Primary queues** (in `composer run dev`):
- `default` — General tasks
- `campaigns` — PBN post campaign jobs
- `deletions` — Campaign and post deletions
- `sidebar_campaigns` — Sidebar/blogroll campaigns

**Additional queues** (not in dev script):
- `bulk_updates`, `bulk_blogroll_updates` — Bulk keyword/URL updates
- `sidebar_deletions`, `update_hidden_links`, `delete_hidden_links` — Sidebar/hidden link operations
- `hidden_links_campaigns`, `delete_hidden_links_campaign` — Hidden link campaigns
- `scheduled_campaigns`, `schedule_campaign_bulk_updates`, `schedule_campaign_deletions` — Schedule post campaigns
- `scheduled_sidebar_campaigns`, `schedule_sidebar_bulk_updates`, `schedule_sidebar_deletions` — Schedule sidebar campaigns
- `wp_scheduled_campaigns`, `wp_scheduled_sync`, `wp_scheduled_campaign_bulk_updates`, `wp_scheduled_campaign_deletions` — WP-native scheduled posts
- `domainCheck` — Domain status verification

**Queue Configuration:**
- Driver: `database` (default, see `config/queue.php`)
- Jobs table: `jobs`
- Retry strategy: Exponential backoff (60s base, max 3600s), up to 5 attempts in most publish jobs

### Campaign Types

Each campaign type has its own tables and job classes:

1. **PBN Post** (`campaigns` table) — Standard WordPress posts
   - Jobs: `PublishCampaignPostJob`, `BulkUpdateCampaignPostsJob`, `DeleteCampaignJob`
   - Tasks stored in: `campaign_posts`

2. **Sidebar/Blogroll** (`sidebar_campaigns`) — Sidebar links via blogroll API
   - Jobs: `PublishSidebarBlogrollJob`, `BulkUpdateSidebarBlogrollJob`, `DeleteSidebarCampaignJob`
   - Tasks: `sidebar_campaign_tasks`

3. **Hidden Link** (`hidden_links_campaigns`) — Non-visible links in posts
   - Jobs: `PublishHiddenLinksJob`, `BulkUpdateHiddenLinksJob`, `BulkDeleteHiddenLinkCampaignsJob`, `BulkDeleteHiddenLinksJob`
   - Tasks: `hidden_links_campaign_tasks`

4. **Schedule Post** (`schedule_campaigns`) — Time-delayed WordPress posts
   - Jobs: `PublishScheduledCampaignPostJob`, `BulkUpdateScheduleCampaignPostsJob`, `DeleteScheduleCampaignJob`
   - Tasks: `schedule_campaign_posts`

5. **Schedule Sidebar** (`schedule_sidebar_campaigns`) — Time-delayed sidebar links
   - Jobs: `PublishScheduledSidebarBlogrollJob`, `BulkUpdateScheduleSidebarBlogrollJob`, `DeleteScheduleSidebarCampaignJob`
   - Tasks: `schedule_sidebar_campaign_tasks`

6. **WP Scheduled** (`wp_scheduled_campaigns`) — WordPress-native scheduled posts (cron on remote WP)
   - Jobs: `PublishWpScheduledPostJob`, `SyncWpScheduledPostStatusJob`, `BulkUpdateWpScheduledPostsJob`, `DeleteWpScheduledCampaignJob`
   - Tasks: `wp_scheduled_campaign_posts`

7. **Sticky Post** — Uses `campaigns` table with `is_sticky_campaign` flag; posts published with `is_sticky: true`

### Services Layer

**`CampaignPostContentBuilder`** (`app/Services/CampaignPostContentBuilder.php`)
- Builds final post title and HTML content from article + keyword/URL pairs
- Injects anchor links into article paragraphs (distributed evenly, 20% into each paragraph)
- **Critical for multilingual content**: Uses `mb_*` functions throughout to handle multi-byte UTF-8 characters (Chinese, Thai, Arabic, Persian)
- Auto-wraps RTL content (Arabic, Persian, Hebrew, Urdu) with `dir="rtl"` attribute
- Handles both `<p>` tags and `<br>`-separated content
- Supports `rel` attributes: `nofollow`, `sponsored`, `ugc`, `noopener`, `noreferrer`

**`Utf8SanitizerService`** (`app/Services/Utf8SanitizerService.php`)
- **Critical service**: Cleans malformed UTF-8 bytes to prevent `json_encode()` failures when posting to WordPress API
- Preserves valid multilingual content (Chinese, Thai, Arabic, Persian, emoji, RTL characters)
- Repairs mojibake (double-encoded UTF-8, common with Chinese text)
- Preserves Zero-Width Non-Joiner (U+200C) and Zero-Width Joiner (U+200D) for Persian/Arabic text rendering
- Removes dangerous control characters and BOM markers
- Normalizes Unicode to NFC form
- **Usage**: Call `cleanUtf8($text, $options)` (global helper) before any WordPress API posting

**`BlogrollApiService`** (`app/Services/BlogrollApiService.php`)
- Communicates with remote WordPress blogroll/sidebar link API (`/wp-json/external/v1/blogroll`)
- **Authentication pattern**: GET requests pass `api_key` in URL query string; POST/PATCH/DELETE pass `api_key` in request body
- **Key methods**:
  - `fetchBlogroll($domain, $apiKey)` — GET all blogroll items, returns array with `id`, `keyword`, `link`, `rel`, `timestamp`
  - `updateEntryByRemoteId($domain, $apiKey, $remoteId, $keyword, $link, $rel)` — POST update by remote ID (preferred)
  - `deleteEntryByRemoteId($domain, $apiKey, $remoteId)` — DELETE by remote ID
  - `findIndexByRemoteId($data, $remoteId)` — Helper to find item in fetched array
- **Remote ID format**: String like `blog_678abc123def456.78901234` (NOT array index)
- **rel parameter**: Accepts array of tokens `['nofollow', 'sponsored', 'ugc']`, automatically expands to all required formats for API compatibility (sends both `rel` array and individual boolean flags)
- **Domain normalization**: Auto-prepends `https://` if scheme missing

**Global Helpers** (`app/helpers.php`)
- `cleanUtf8($text, $options)` — UTF-8 sanitization wrapper
- `safeJsonEncode($data, $flags, $depth)` — JSON encode with UTF-8 handling
- `isValidUtf8($text)` — UTF-8 validation check
- `isRtlText($text)` — Detect RTL languages (>30% RTL characters)
- `wrapRtlContent($html, $title)` — Wrap content with `dir="rtl"` for proper WordPress display

### Job Locking Pattern

Publish jobs use database-level locking to prevent duplicate execution:

```php
// 1. Acquire lock inside transaction
$post = DB::transaction(function () use ($lockToken) {
    $p = CampaignPost::lockForUpdate()->find($id);
    if ($p->status === 'success' || $p->locked_at->isFuture()) return null;
    $p->status = 'publishing';
    $p->locked_at = now();
    $p->lock_token = $lockToken;
    $p->save();
    return $p;
});

// 2. Perform HTTP request (outside transaction)
$remote = $this->postToWordPress($post, $title, $content);

// 3. Release lock and mark success
DB::transaction(function () use ($post, $remote) {
    $fresh = CampaignPost::lockForUpdate()->find($post->id);
    if ($fresh->lock_token !== $post->lock_token) return; // Another job claimed it
    $fresh->update(['status' => 'success', 'locked_at' => null, ...]);
});
```

This pattern appears in: `PublishCampaignPostJob`, `PublishSidebarBlogrollJob`, `PublishHiddenLinksJob`, and schedule variants.

### UTF-8 and Multilingual Support

**Critical for reliability**: The system handles multilingual content including Chinese, Thai, Arabic, Persian, Hebrew, Urdu, and emoji.

**Where UTF-8 sanitization is applied:**
1. **Model level** — Article model sanitizes on save (title, description)
2. **Content builder** — Before building final HTML (`CampaignPostContentBuilder::build()`)
3. **Job level** — Before WordPress API posting (`PublishCampaignPostJob::handle()`)
4. **API requests** — Uses `safeJsonEncode()` and UTF-8 content-type headers

**RTL Language Support:**
- Detected via `isRtlText()` helper (checks for Arabic, Persian, Hebrew, Urdu Unicode ranges)
- Content auto-wrapped with `<div dir="rtl" style="text-align: right;">` before posting
- Ensures proper text rendering in WordPress admin and frontend

**Character counting**: Always use `mb_strlen()`, `mb_substr()`, `mb_strpos()` instead of byte-based string functions when working with content. Byte-based functions will break multi-byte characters.

### Authentication & Authorization

**Admin Panel** (`admin` guard):
- Table: `admins`
- Roles: `Super Admin` (1), `Admin` (2), `Member` (3) — stored in `role_id`
- Login flow: Email/password → OTP verification (6-digit code, 5 min expiry)
- Password reset via email token

**Role Permissions:**
- **Super Admin**: Full access, can see all campaigns regardless of creator
- **Admin**: Can create campaigns, sees own campaigns only
- **Member**: Can only manage articles (add/edit/delete), **cannot** create campaigns

**Middleware:**
- `AdminAuth` — Requires authenticated admin
- `CanCreateCampaigns` — Restricts campaign creation to Super Admin + Admin only (blocks Members)
- Applied to all campaign routes except public token-protected reports

**Public Reports:**
- Campaign reports accessible via token: `/campaign/report/{campaign_no}/{token}`
- Token generated on campaign creation (`report_token` column, 64 random chars)
- Export to Excel also token-protected
- These routes bypass `CanCreateCampaigns` middleware via `withoutMiddleware()`

### Remote WordPress API Contract

Remote WordPress sites must implement the **Diamond PBN (External API Manager) plugin v7.8.0+** which provides REST API endpoints at `/wp-json/external/v1/`. See `API-DATA-FLOW.md` in repo root for complete documentation.

**Authentication:**
- Method 1 (Recommended): HTTP Header `X-External-API-Key: YOUR_KEY`
- Method 2: Query parameter `?api_key=YOUR_KEY`
- Laravel implementation: Passes `api_key` in request body for POST/PATCH/DELETE, in URL for GET requests

**Core Posts Endpoints:**
- `GET /status` — Health check (no auth required)
- `POST /posts/create` — Create post
  - Required: `title`, `content`
  - Optional: `status` (publish/draft), `post_type`, `categories` (array of IDs), `tags` (array), `schedule_time` (Y-m-d H:i:s), `is_sticky` (boolean)
  - Response: `{success: true, post_id: 123, status: "publish", remote_url: "..."}`
- `POST /posts/update/{id}` — Update existing post
- `DELETE /posts/delete/{id}` — Permanently delete post
- `GET /posts` — List posts (params: `post_type`, `status`, `limit`, `offset`)
- `GET /posts/{id}` — Get single post
- `POST /posts/sticky/{id}` — Make post sticky
- `POST /posts/unsticky/{id}` — Remove sticky status

**Scheduling (Two Methods):**
1. **WordPress-native scheduling**: Use `schedule_time` parameter in `/posts/create` or `/posts/update` (sets status to `future`)
2. **Queue-based scheduling**: Use `/schedule/add` endpoint, processed by remote WP cron every 5 minutes
   - `POST /schedule/add` — Add to queue (requires `title`, `content`, `run_at`)
   - `GET /schedule/list` — List queued posts
   - `DELETE /schedule/delete/{id}` — Remove from queue

**Blogroll/Sidebar Links Endpoints:**
- `GET /blogroll` — Fetch all blogroll items (returns array with `id`, `keyword`, `link`, `rel`, `timestamp`)
- `POST /blogroll/add` — Add new item
- `POST /blogroll/update/{id}` — Update by item ID (NOT array index)
- `DELETE /blogroll/delete/{id}` — Delete by item ID

**Hidden Links Endpoints:**
- `GET /hidden-links` — Fetch all hidden links
- `POST /hidden-links/add` — Add new hidden link
- `POST /hidden-links/update/{id}` — Update by item ID
- `DELETE /hidden-links/delete/{id}` — Delete by item ID

**3-Priority rel Attribute System** (Blogroll & Hidden Links):
Both blogroll and hidden links support flexible `rel` attribute configuration with a priority system:
1. **Priority 1** (Highest): `rel_attr` (string) — Space-separated rel values, supports ANY custom values
   - Example: `"rel_attr": "nofollow sponsored external bookmark"`
2. **Priority 2**: `rel` (array or space-separated string) — Standard rel values
   - Example: `"rel": ["nofollow", "sponsored"]` or `"rel": "nofollow sponsored"`
3. **Priority 3** (Fallback): Individual boolean fields for backward compatibility
   - Fields: `nofollow`, `no_follow`, `sponsored`, `sponsor`, `ugc`, `noopener`, `noreferrer`
   - Example: `"nofollow": true, "sponsored": true`

If `rel_attr` is provided, it takes precedence. If not, `rel` array/string is used. If neither, boolean fields are processed. Laravel code should send `rel` array to maintain flexibility.

**Response Format:**
- Success: `{success: true, message: "OK", data: {...}}`
- Auth error: `{code: "bad_key", message: "Invalid API key", data: {status: 403}}`
- Not found: `{code: "not_found", message: "...", data: {status: 404}}`
- Missing dependency: `{code: "missing_dep", message: "Required plugin not active", data: {status: 424}}`

**Important Notes:**
- Always use the `id` field from GET responses when updating/deleting, NOT array indices
- Remote IDs are generated strings like `blog_678abc123def456.78901234` or `hid_678abc123def456.78901234`
- Blogroll/Hidden Links may require additional plugins active on remote WP (enforced via settings)
- URL parameters accept both `link` and `url` for backward compatibility

**API Key Storage:**
- Stored in `domains.api_key` column (plain text in dev, should be encrypted in production)
- Laravel sends key in request body for POST/PATCH/DELETE operations
- Laravel sends key in URL query string for GET operations (per API convention)

**SSL Verification:**
- Currently disabled in development: `Http::withoutVerifying()`
- **Production**: Remove `withoutVerifying()` and ensure remote domains have valid SSL certificates

## rel Attribute System (Link Attributes)

The system uses **individual boolean fields** for link rel attributes throughout the campaign creation and publishing flow. This differs from the remote WordPress API's flexible 3-priority system.

**Flow Overview:**

1. **Frontend (Blade)** — Checkboxes for each rel attribute:
   - `nofollow_link` (checkbox, value="1")
   - `sponsored_link` (checkbox, value="1")
   - `ugc_link` (checkbox, value="1")
   - `noopener_link` (checkbox, value="1")
   - `noreferrer_link` (checkbox, value="1")

2. **Frontend (JavaScript)** — `public/js/create-campaign.js`:
   - Reads checkbox state: `document.getElementById("sponsored_link")?.checked`
   - Collects into keyword data object: `{keyword: "...", url: "...", nofollow: "1", sponsored: "1", ugc: "", ...}`
   - For `raw_html` method: Parses `rel` attribute from `<a>` tags, converts to individual boolean flags (lines 3182-3186)
   - Sends to backend as part of `keywordsDataHolder` JSON array

3. **Backend (Controller)** — `app/Http/Controllers/Admin/campaignController.php`:
   - Receives `$keywords` array from `keywordsDataHolder` JSON
   - Stores in `campaign_articles` table as **individual boolean columns** (lines 346-350):
     ```php
     'nofollow'    => ! empty($row['nofollow']),
     'sponsored'   => ! empty($row['sponsored']),
     'ugc'         => ! empty($row['ugc']),
     'noopener'    => ! empty($row['noopener']),
     'noreferrer'  => ! empty($row['noreferrer']),
     ```

4. **Content Builder** — `app/Services/CampaignPostContentBuilder.php`:
   - Reads boolean flags from `campaign_article` (lines 113-115)
   - Builds `$relTokens` array from truthy flags (lines 119-133)
   - Constructs `$relPart` string: ` rel="nofollow sponsored ugc"` (line 135)
   - Injects into anchor tags: `<a href="..." target="_blank" rel="nofollow sponsored">keyword</a>` (lines 152, 170)

5. **Job Publishing** — `app/Jobs/PublishCampaignPostJob.php`:
   - Uses `CampaignPostContentBuilder::build()` to generate final HTML
   - Sends complete HTML with embedded rel attributes to WordPress `/posts/create` API
   - The rel attributes are already in the HTML content, NOT sent as separate API parameters

**Important Notes:**

- **Laravel app uses boolean fields**: Stored in DB as individual `tinyint` columns
- **WordPress API supports 3-priority system**: `rel_attr` (string) > `rel` (array) > individual booleans
- **Current implementation**: HTML anchors contain rel in the `content` field sent to WordPress
- **For Blogroll/Sidebar campaigns**: The `BlogrollApiService` sends `rel` as an array to the WordPress API (different from post campaigns where rel is embedded in HTML)

**Database Schema:**
```
campaign_articles table:
- nofollow (boolean)
- sponsored (boolean)
- ugc (boolean)
- noopener (boolean)
- noreferrer (boolean)
```

**Adding New rel Attributes:**
1. Add checkbox in Blade view
2. Update JavaScript to collect the value
3. Add migration for new column in `campaign_articles` table
4. Update controller validation and storage logic
5. Update `CampaignPostContentBuilder` to include in `$relTokens`

## Key Patterns & Conventions

### Campaign Workflow States

**Campaign status** (`campaigns.status`):
- `queued` — Created, jobs dispatched but not started
- `running` — At least one job executing
- `completed` — All jobs succeeded
- `semi_failed` — Some succeeded, some failed
- `failed` — All jobs failed
- `paused` — Manually paused (jobs check this before executing)
- `cancelled` — Manually cancelled

**Task/Post status** (e.g., `campaign_posts.status`):
- `queued` — Waiting for queue worker
- `publishing` — Job actively executing (locked)
- `success` — Published to remote WordPress
- `failed` — Max retries exhausted

### Article Lifecycle

1. **Created** — `articles.status = 'unused'`
2. **Used in campaign** — Status remains `unused` until publish succeeds
3. **Published** — Article soft-deleted (`deleted_at` set), snapshot copied to `campaign_articles` table
4. **Trashed** — Visible in trashed articles UI (`/article/trashed`)
5. **Permanently deleted** — Hard delete from database (cascades protected by snapshots)

**Why soft-delete after publish?**
- Prevents article reuse across campaigns (SEO duplicate content concerns)
- Preserves content via `campaign_articles.article_title_snapshot` and `article_body_snapshot`
- Allows recovery if needed before permanent purge

### Bulk Operations

**Bulk keyword/URL updates:**
- User can update keywords/URLs for all posts in a campaign
- Jobs dispatched to `bulk_updates` queue (or type-specific queue)
- Each job refetches the remote post and updates content via WordPress update API
- Uses `CampaignPostContentBuilder::build()` to regenerate HTML with new keywords

**Bulk retries:**
- Controllers provide "Retry All Failed" functionality
- Filters `status = 'failed'`, resets to `queued`, dispatches fresh jobs
- Useful when remote site was temporarily down

**Local-only purge:**
- Deletes campaign records locally without touching remote WordPress posts
- Useful when remote deletion fails or remote posts should remain live

## Common Development Tasks

### Adding a new campaign type

1. Create migration for campaign table (see existing `*_campaigns` tables as examples)
2. Create task/post table with foreign key to campaign
3. Create Eloquent models in `app/Models/Admin/`
4. Create publish job in `app/Jobs/` (extends `ShouldQueue`, uses locking pattern)
5. Add job to appropriate queue in `composer.json` dev script
6. Create controller in `app/Http/Controllers/Admin/`
7. Add routes in `routes/admin.php` (remember public report routes)
8. Create views in `resources/views/admin/campaigns/`

### Testing queue jobs locally

```bash
# Option 1: Use dev script (recommended)
composer run dev

# Option 2: Run queue worker separately
php artisan queue:work --queue=campaigns,default --tries=1

# Option 3: Process one job synchronously
php artisan queue:work --once

# Option 4: Watch queue in real-time
php artisan queue:listen --tries=1 --queue=campaigns
```

### Debugging UTF-8 issues

1. Check if content is valid UTF-8: `isValidUtf8($text)`
2. Inspect raw bytes: `bin2hex(substr($text, 0, 100))`
3. Use test command: `php artisan test:utf8-sanitization`
4. Check logs for "UTF-8 sanitization removed malformed bytes" warnings
5. Verify `mb_*` functions used instead of byte-based string functions

### Running domain status checks

Domain status checks can be queued via the UI or run manually:

```bash
php artisan queue:work --queue=domainCheck
```

Jobs verify remote domain accessibility and update `domains.status` field.

## Database Notes

**Connection**: Default MySQL (`config/database.php`)

**Key relationships:**
- `campaigns` → `campaign_articles` (1:N) → `campaign_posts` (1:N)
- `campaign_articles` → `articles` (N:1, nullable after soft-delete)
- `campaign_posts` → `campaign_domains` (N:1) → `domains` (N:1)

**Indexes:**
- Full-text search on `articles.search_text` column (name + description combined)
- Indexes on status columns for queue job filtering

**Soft deletes**: `articles`, `article_sets` use Laravel soft delete (`deleted_at` timestamp)

## Environment Variables

See `.env.example` for full list. Critical variables:

- `DB_*` — Database connection
- `QUEUE_CONNECTION=database` — Queue driver
- `APP_URL` — Used in report links and emails
- `MAIL_*` — Required for OTP and password reset
- `DEFAULT_ADMIN_EMAIL` / `DEFAULT_ADMIN_PASSWORD` — Initial admin seeding

## Production Deployment

**Queue Workers:**
- Use Supervisor (see `deploy/supervisor/laravel-worker.conf` for example config)
- Run separate workers for high-priority queues (`campaigns`, `deletions`)
- Monitor queue depth: `php artisan queue:monitor`

**Assets:**
- Build before deploy: `npm run build`
- Assets output to `public/build/` (Vite manifest)

**Security:**
- Set `SESSION_SECURE_COOKIE=true` over HTTPS
- Never commit `.env` with production secrets
- Consider enabling SSL verification in `BlogrollApiService` and job HTTP clients
- Treat campaign report tokens as secrets (they grant public access)

**Logging:**
- UTF-8 sanitization warnings logged to `storage/logs/laravel.log`
- Monitor for frequent sanitization = potential source data quality issues
