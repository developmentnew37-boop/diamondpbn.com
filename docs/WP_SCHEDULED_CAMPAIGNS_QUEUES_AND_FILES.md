# WP Scheduled Campaigns: Queues, Commands & Files

This document explains **how many queues and commands** are used for **WP Scheduled campaigns only**, what each does, and **which files are included** (and which are not).

---

## 1. Queues used for WP Scheduled campaigns (4 queues)

| Queue name                           | Purpose |
|--------------------------------------|--------|
| **wp_scheduled_campaigns**            | Sending posts to WordPress (create/schedule post via external API). |
| **wp_scheduled_sync**                | Syncing status from WordPress (future → publish, missed schedule) and updating `remote_url` to slug when possible. |
| **wp_scheduled_campaign_bulk_updates** | Updating already-published posts on remote WordPress after bulk keyword/URL edit (DB + remote). |
| **wp_scheduled_campaign_deletions**  | Deleting whole WP Scheduled campaigns (remote WP posts + local DB cleanup). |

**Important:** These queues are **only** for WP Scheduled campaigns. They are **not** used by:
- PBN Post campaigns (they use `campaigns` queue)
- Schedule Post campaigns (they use `scheduled_campaigns` queue)
- Sidebar / Hidden Links / Domain Check (their own queues)

---

## 2. Commands you run (Artisan + queue:work)

### 2.1 Artisan commands (WP Scheduled only)

| Command | When to use | What it does |
|--------|-------------|--------------|
| `php artisan wp-scheduled:sync-status` | **Automatic** (runs hourly via Laravel scheduler). Optional: run manually. | Finds posts with `remote_status = future` or null and dispatches **SyncWpScheduledPostStatusJob** for each (up to 100) onto **wp_scheduled_sync** queue. |
| `php artisan wp-scheduled:resolve-permalinks` | **One-off** when you want to replace old `?p=ID` links with slug URLs on the report. | Finds posts whose `remote_url` contains `?p=`, dispatches **SyncWpScheduledPostStatusJob** for each onto **wp_scheduled_sync** queue. |

### 2.2 Queue worker commands (you must run these to process jobs)

There is **no** scheduled `queue:work` in the app for WP Scheduled queues. You need to run workers yourself (e.g. in terminal or via Supervisor):

| Command | Processes |
|--------|-----------|
| `php artisan queue:work --queue=wp_scheduled_campaigns` | Jobs that **send** posts to WordPress (create/schedule). |
| `php artisan queue:work --queue=wp_scheduled_sync` | Jobs that **sync** status and slug URL from WordPress. |
| `php artisan queue:work --queue=wp_scheduled_campaign_bulk_updates` | Jobs that **update** published posts on remote WP after bulk keyword/URL edit. |
| `php artisan queue:work --queue=wp_scheduled_campaign_deletions` | Jobs that **delete** whole WP Scheduled campaigns (remote + local). |

To process publish + sync in one worker:

```bash
php artisan queue:work --queue=wp_scheduled_campaigns,wp_scheduled_sync
```

To process deletions (run when you delete campaigns):

```bash
php artisan queue:work --queue=wp_scheduled_campaign_deletions
```

---

## 3. Jobs (feature-specific queues)

| Job class | Queue | What it does |
|-----------|-------|--------------|
| **PublishWpScheduledPostJob** | `wp_scheduled_campaigns` | Sends one post to WordPress external API (create post with schedule date). Saves `remote_id`, `remote_status`, `remote_url` (prefers slug from API; if `?p=ID` then resolves via WP REST). |
| **SyncWpScheduledPostStatusJob** | `wp_scheduled_sync` | Fetches status (and permalink) from WordPress (external status API or WP REST). Updates `remote_status`, `published_at`, and `remote_url` (replaces `?p=ID` with slug when available). |
| **BulkUpdateWpScheduledPostsJob** | `wp_scheduled_campaign_bulk_updates` | After bulk keyword/URL edit: rebuilds title/content from updated DB and PATCHes each published post on remote WordPress. |
| **DeleteWpScheduledCampaignJob** | `wp_scheduled_campaign_deletions` | Deletes a whole WP Scheduled campaign: unlocks queued articles, deletes remote WordPress posts (publish/future) via external API, then removes local posts/articles/domains/dates and the campaign row. |

---

## 4. How WP Scheduled campaigns are handled (flow)

1. **Create campaign** (admin)  
   - Controller creates campaign, domains, articles, and **wp_scheduled_campaign_posts** rows (status `queued`).  
   - Right after store, it calls **dispatchQueuedPosts()** → dispatches **PublishWpScheduledPostJob** for each queued post to **wp_scheduled_campaigns**.

2. **Publish jobs run** (when you run `queue:work --queue=wp_scheduled_campaigns`)  
   - Each job calls WordPress external API to create/schedule the post, then saves `remote_id`, `remote_status`, `remote_url` (slug when API returns it or when resolvable from WP REST).

3. **Sync status** (optional / hourly)  
   - **Hourly:** `wp-scheduled:sync-status` dispatches **SyncWpScheduledPostStatusJob** for posts that are still `future` or unknown.  
   - **Manual:** “Sync status” on campaign or “Sync” on a single post dispatches the same job to **wp_scheduled_sync**.  
   - When you run `queue:work --queue=wp_scheduled_sync`, those jobs update Live/Missed/Scheduled and fix `remote_url` to slug when possible.

4. **Retry** (manual)  
   - “Retry” on a failed/queued post re-dispatches **PublishWpScheduledPostJob** to **wp_scheduled_campaigns** (same queue as step 2).

---

## 5. Files included in WP Scheduled campaigns

### 5.1 Jobs (all used for WP Scheduled)

- `app/Jobs/PublishWpScheduledPostJob.php` — **included** (queue: wp_scheduled_campaigns).
- `app/Jobs/SyncWpScheduledPostStatusJob.php` — **included** (queue: wp_scheduled_sync).
- `app/Jobs/BulkUpdateWpScheduledPostsJob.php` — **included** (queue: wp_scheduled_campaign_bulk_updates).
- `app/Jobs/DeleteWpScheduledCampaignJob.php` — **included** (queue: wp_scheduled_campaign_deletions).

### 5.2 Artisan commands (WP Scheduled only)

- `app/Console/Commands/SyncWpScheduledStatusCommand.php` — **included** (`wp-scheduled:sync-status`).
- `app/Console/Commands/ResolveWpScheduledPermalinksCommand.php` — **included** (`wp-scheduled:resolve-permalinks`).

### 5.2b Service (content building)

- `app/Services/WpScheduledPostContentBuilder.php` — **included** (builds title + HTML from article + keyword/URL; used by PublishWpScheduledPostJob and BulkUpdateWpScheduledPostsJob).

### 5.3 Controller & routes

- `app/Http/Controllers/Admin/WpScheduledCampaignController.php` — **included** (create, store, show, edit/update, bulk keyword/URL update, run, syncCampaign, retryPost, syncPost, single post edit/update/delete, destroy, report, reportExport).
- `routes/admin.php` — **included** (WP Schedule resource + run, sync, retry-post, sync-post, single post edit/update/delete, bulk update, report, report export).

### 5.4 Models (WP Scheduled only)

- `app/Models/Admin/WpScheduledCampaign.php` — **included**.
- `app/Models/Admin/WpScheduledCampaignPost.php` — **included**.
- `app/Models/Admin/WpScheduledCampaignArticle.php` — **included**.
- `app/Models/Admin/WpScheduledCampaignDomain.php` — **included**.
- `app/Models/Admin/WpScheduledCampaignDate.php` — **included**.

### 5.5 Views (WP Scheduled only)

- `resources/views/admin/campaigns/wp-scheduled/create.blade.php` — **included**.
- `resources/views/admin/campaigns/wp-scheduled/index.blade.php` — **included**.
- `resources/views/admin/campaigns/wp-scheduled/show.blade.php` — **included**.
- `resources/views/admin/campaigns/wp-scheduled/report.blade.php` — **included** (public report; uses `remote_url` for link).

### 5.6 Scheduler (cron)

- `routes/console.php` — **included** (registers `wp-scheduled:sync-status` to run **hourly**).  
  **Not included:** There is no `Schedule::command('queue:work ...')` for `wp_scheduled_campaigns` or `wp_scheduled_sync` — you run those workers yourself.

---

## 6. Files NOT used for WP Scheduled campaigns

- Other campaign jobs: `PublishCampaignPostJob`, `PublishScheduledCampaignPostJob`, `PublishSidebarBlogrollJob`, `PublishHiddenLinksJob`, etc. — **not used** for WP Scheduled (different queues/campaign types).
- Other commands: any command that does not start with `wp-scheduled:` — **not** part of WP Scheduled queue flow.
- Views under `pbn-post`, `pbn-sidebar`, `pbn-hidden-links`, `sticky-post` — **not** WP Scheduled; they are for other campaign types.
- `app/Jobs/PublishScheduledCampaignPostJob.php` — **not** WP Scheduled; used for “Schedule Post” campaigns (queue: `scheduled_campaigns`).

---

## 7. Quick reference: “What do I run?”

| Goal | What to run |
|------|-------------|
| Process new WP Scheduled posts (send to WordPress) | `php artisan queue:work --queue=wp_scheduled_campaigns` |
| Process sync jobs (status + slug URL) | `php artisan queue:work --queue=wp_scheduled_sync` |
| Process both (publish first, then sync) | `php artisan queue:work --queue=wp_scheduled_campaigns,wp_scheduled_sync` |
| Hourly sync (already scheduled) | Nothing; scheduler runs `wp-scheduled:sync-status` hourly. |
| Fix old report links (?p= → slug) one-off | `php artisan wp-scheduled:resolve-permalinks` then run `wp_scheduled_sync` worker. |
| Update published posts after bulk keyword/URL edit | Edit campaign → Update batches. Then run `php artisan queue:work --queue=wp_scheduled_campaign_bulk_updates`. |
| Delete a WP Scheduled campaign (remote + local) | Trigger delete in UI. Then run `php artisan queue:work --queue=wp_scheduled_campaign_deletions`. |

---

*Summary: WP Scheduled campaigns use **4 feature-specific queues**: **wp_scheduled_campaigns** (publish), **wp_scheduled_sync** (sync status/slug), **wp_scheduled_campaign_bulk_updates** (bulk keyword/URL → remote update), **wp_scheduled_campaign_deletions** (delete whole campaign). **4 jobs** use these queues. **2 Artisan commands**: wp-scheduled:sync-status, wp-scheduled:resolve-permalinks. No queue:work is scheduled in the app; you run the workers yourself.*
