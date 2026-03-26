# Features, App Changes & Database Changelog

This document lists **features created**, **application changes**, and **database changes** for the PBN Automation Software project (including WP Scheduled campaigns and related work).

---

## 1. Features Created

### 1.1 WP Scheduled Campaigns (WordPress-native scheduling)

- **Create campaign** – Admin creates a campaign with campaign number, domain category, article category, schedule date range, post quantity. Selects domains, articles, and keyword/URL per post (single or multiple pairs). System creates `wp_scheduled_campaigns`, `wp_scheduled_campaign_dates`, `wp_scheduled_campaign_domains`, `wp_scheduled_campaign_articles`, and `wp_scheduled_campaign_posts` (status `queued`).
- **Publish to WordPress** – Jobs send each post to the remote WordPress site via external API (`POST .../wp-json/external/v1/posts/create`) with title, content, schedule time. On success: save `remote_id`, `remote_status`, `remote_url`, and **soft-delete the article** (article no longer needed for that post).
- **Campaign show** – View campaign with posts table: status (Queued, Publishing, Live, Scheduled, Missed, Failed), scheduled date, domain, article, keyword/URL, remote URL. Actions: View, Edit, Sync, Retry, Delete (per post); Edit campaign; Delete campaign.
- **Edit campaign** – Edit campaign number and **batch keyword/URL** (one form section per distinct keyword/URL batch). Submit updates DB and, for already-published posts, queues **bulk update** jobs to update content on the remote site.
- **Bulk keyword/URL update (DB + remote)** – When batches are updated in the edit form, the app:
  - Updates `wp_scheduled_campaign_articles` (keyword, url, keyword_type, url_type).
  - For posts that are already on WordPress (`status = success`, `remote_id` set), dispatches **BulkUpdateWpScheduledPostsJob**. The job **fetches current post content from the remote site** (GET external API), applies the new keyword/URL to the fetched HTML (replaces link anchors), then **updates the post on the remote** (POST update API). This avoids using the local article (which may be soft-deleted or permanently removed).
- **Sync status** – Sync campaign or single post: dispatches **SyncWpScheduledPostStatusJob** to update `remote_status`, `published_at`, and `remote_url` from WordPress (future → publish, missed schedule; replace `?p=ID` with slug URL when possible).
- **Retry failed/queued post** – Re-dispatches **PublishWpScheduledPostJob** for that post.
- **Single post edit/delete** – Edit: fetch post from remote (GET), show form with title/content; submit updates remote (POST update). Delete: delete post on remote (DELETE API) and remove local post row.
- **Delete whole campaign** – Dispatches **DeleteWpScheduledCampaignJob**: deletes all remote WordPress posts for the campaign, then removes local posts, campaign articles, domains, dates, and the campaign.
- **Report** – Public report page (by report token) listing posts with Remote URL (prefers slug; see docs/WP_SLUG_URL_FOR_REPORT.md). Export to Excel.
- **Report URL** – Report uses `remote_url` from API (slug when returned); if API returns only `?p=ID`, the app can resolve permalink via WP REST (`/wp-json/wp/v2/posts/{id}`) or via sync job.

### 1.2 Queues & Commands (WP Scheduled)

- **Queues** – Feature-specific: `wp_scheduled_campaigns` (publish), `wp_scheduled_sync` (sync status/slug), `wp_scheduled_campaign_bulk_updates` (bulk keyword/URL → remote update), `wp_scheduled_campaign_deletions` (delete campaign).
- **Commands** – `wp-scheduled:sync-status` (hourly via scheduler), `wp-scheduled:resolve-permalinks` (one-off to replace ?p= links with slug on report).

### 1.3 Other Campaign / App Areas (if modified in same period)

- PBN Post campaigns, Schedule Post, Sidebar, Hidden Links, etc. – Any new columns or behaviour (e.g. `last_bulk_updated_at`, `content_updated_at`) are listed in **Section 3. Database changes** and **Section 2. App changes** where relevant.

---

## 2. Application Changes

### 2.1 New Files

| File | Purpose |
|------|--------|
| `app/Services/WpScheduledPostContentBuilder.php` | Builds post title + HTML from article + keyword/URL; used by **PublishWpScheduledPostJob** only (initial publish). |
| `app/Services/WpScheduledRemotePostUpdateService.php` | Fetches post from remote (GET external API), applies new keyword/URL to fetched content (replace anchors), returns [title, content] for **BulkUpdateWpScheduledPostsJob**. |
| `app/Jobs/PublishWpScheduledPostJob.php` | Sends one post to WordPress (create/schedule); on success soft-deletes the article. |
| `app/Jobs/SyncWpScheduledPostStatusJob.php` | Syncs status and slug URL from WordPress. |
| `app/Jobs/BulkUpdateWpScheduledPostsJob.php` | After bulk keyword/URL edit: fetches each post from remote, applies new keyword/URL, updates remote. |
| `app/Jobs/DeleteWpScheduledCampaignJob.php` | Deletes whole WP Scheduled campaign (remote + local). |
| `app/Console/Commands/SyncWpScheduledStatusCommand.php` | `wp-scheduled:sync-status`. |
| `app/Console/Commands/ResolveWpScheduledPermalinksCommand.php` | `wp-scheduled:resolve-permalinks`. |
| `app/Http/Controllers/Admin/WpScheduledCampaignController.php` | Full CRUD, run, sync, retry, edit/update/delete single post, bulk update, report, report export. |
| `app/Models/Admin/WpScheduledCampaign.php` | Model for `wp_scheduled_campaigns`. |
| `app/Models/Admin/WpScheduledCampaignPost.php` | Model for `wp_scheduled_campaign_posts`. |
| `app/Models/Admin/WpScheduledCampaignArticle.php` | Model for `wp_scheduled_campaign_articles`. |
| `app/Models/Admin/WpScheduledCampaignDomain.php` | Model for `wp_scheduled_campaign_domains`. |
| `app/Models/Admin/WpScheduledCampaignDate.php` | Model for `wp_scheduled_campaign_dates`. |
| `resources/views/admin/campaigns/wp-scheduled/create.blade.php` | Create campaign form. |
| `resources/views/admin/campaigns/wp-scheduled/index.blade.php` | Campaign list. |
| `resources/views/admin/campaigns/wp-scheduled/show.blade.php` | Campaign show with posts and actions (View, Edit, Sync, Retry, Delete). |
| `resources/views/admin/campaigns/wp-scheduled/edit.blade.php` | Edit campaign + batch keyword/URL. |
| `resources/views/admin/campaigns/wp-scheduled/edit-post.blade.php` | Edit single post (fetch from remote, edit title/content, update remote). |
| `resources/views/admin/campaigns/wp-scheduled/report.blade.php` | Public report + export. |
| `docs/WP_SCHEDULED_CAMPAIGNS_QUEUES_AND_FILES.md` | Queues, commands, and file list for WP Scheduled. |
| `docs/WP_SLUG_URL_FOR_REPORT.md` | How report shows slug URL instead of ?p=ID. |
| `docs/FEATURES_AND_CHANGES.md` | This file. |

### 2.2 Modified Files (notable)

| File | Changes |
|------|--------|
| `routes/admin.php` | Added WP Schedule resource routes + run, sync, retry-post, sync-post, edit-post, update-post, delete-post, bulk update, report, report export. |
| `routes/console.php` | Scheduled `wp-scheduled:sync-status` to run hourly. |
| (Others from git/context) | Admin auth, campaign controller, PublishCampaignPostJob, article-set opts, campaign views, welcome, etc. – any functional changes in those are project-specific. |

### 2.3 Routes Added (WP Scheduled)

- Resource: `GET/POST create`, `GET show`, `GET edit`, `PUT/PATCH update`, `DELETE destroy`.
- `POST run/{id}`, `POST sync/{id}`, `POST retry-post/{postId}`, `POST sync-post/{postId}`.
- `GET editpost/{postId}`, `POST updatepost/{postId}`, `POST deletepost/{postId}`.
- `POST bulk/update/{id}`.
- Report: `GET report`, `GET report/export`.

---

## 3. Database Changes

### 3.1 New Tables (WP Scheduled)

| Table | Description |
|-------|-------------|
| `wp_scheduled_campaigns` | Campaign header: campaign_no, admin_id, report_token, domain_category_id, article_category_id, schedule_from_date, schedule_to_date, total_targets, status (queued, running, paused, completed, semi_failed, failed, cancelled), completed_targets, failed_targets, started_at, finished_at. |
| `wp_scheduled_campaign_dates` | Per-date quantity: wp_scheduled_campaign_id, schedule_date, quantity. |
| `wp_scheduled_campaign_domains` | Campaign–domain: wp_scheduled_campaign_id, domain_id. |
| `wp_scheduled_campaign_articles` | Campaign–article + keyword/URL: wp_scheduled_campaign_id, article_id, keyword, url, keyword_type (single/json), url_type (single/json), nofollow, media. |
| `wp_scheduled_campaign_posts` | One row per (article, domain, scheduled_date): wp_scheduled_campaign_id, wp_scheduled_campaign_article_id, wp_scheduled_campaign_domain_id, scheduled_date, scheduled_at, status (queued, publishing, success, failed), attempt_count, next_retry_at, last_error, locked_at, lock_token, remote_id, remote_status, remote_scheduled_at, published_at, http_status, remote_title, remote_url, remote_response. |

**Migrations:**  
`2026_02_10_100000_create_wp_scheduled_campaigns_table.php`  
`2026_02_10_100001_create_wp_scheduled_campaign_dates_table.php`  
`2026_02_10_100002_create_wp_scheduled_campaign_domains_table.php`  
`2026_02_10_100003_create_wp_scheduled_campaign_articles_table.php`  
`2026_02_10_100004_create_wp_scheduled_campaign_posts_table.php`

### 3.2 Existing Tables Modified (other campaigns / app)

| Migration | Table | Change |
|-----------|--------|--------|
| `2026_02_07_000001_...` | `campaigns` | Added `last_bulk_updated_at` (timestamp, nullable). |
| `2026_02_07_000002_...` | `campaign_posts` | Added `content_updated_at` (timestamp, nullable). |
| `2026_02_07_000003_...` | `sidebar_campaign_tasks` | Added `content_updated_at` (timestamp, nullable). |
| `2026_02_07_000004_...` | `sidebar_campaigns` | Added `last_bulk_updated_at` (timestamp, nullable). |
| `2026_02_07_000005_...` | `hidden_links_campaigns` | Added `last_bulk_updated_at` (timestamp, nullable). |
| `2026_02_07_000006_...` | `hidden_links_campaigns_tasks` | Added `content_updated_at` (timestamp, nullable). |

### 3.3 Articles (existing behaviour, no new migration)

- `articles` table already has **soft deletes** (`deleted_at` from `$table->softDeletes()` in migration; model uses `SoftDeletes`).
- When a WP Scheduled post is **successfully published**, the app **soft-deletes** the corresponding article (`Article::find(...)?->delete()`). Bulk update does **not** use the article; it fetches content from the remote site and applies new keyword/URL there.

---

## 4. Quick Reference

- **WP Scheduled queues:** `wp_scheduled_campaigns`, `wp_scheduled_sync`, `wp_scheduled_campaign_bulk_updates`, `wp_scheduled_campaign_deletions`.
- **Bulk update flow:** DB updated on edit form submit → job runs → for each post: **fetch from remote** → replace anchors with new keyword/URL → **update remote**.
- **Report URL:** Prefer slug from API; fallback resolve via WP REST or sync job (see `docs/WP_SLUG_URL_FOR_REPORT.md`).
- **Full queue/command/file list:** `docs/WP_SCHEDULED_CAMPAIGNS_QUEUES_AND_FILES.md`.
