# PBN Automation Software – App Flow & All Features

This document describes the **whole application flow**, **every feature**, and **all related files** (models, controllers, migrations, jobs, queues, services, views, routes).

---

## 1. Application Overview

The app is an **admin panel** for managing:

- **Domains** and **Domain Categories** (and Domain Sets)
- **Articles** and **Article Categories / Languages / Sets**
- **Campaigns** that publish content or links to remote WordPress sites via external APIs

**Campaign types:**

| # | Feature | Description | API / Behaviour |
|---|---------|-------------|------------------|
| 1 | **PBN Post Campaign** | Full blog posts to domains; run immediately or dripfeed | POST create post; bulk update; delete campaign |
| 2 | **Sidebar (Blogroll) Campaign** | Sidebar links (keyword + URL) on domains | Blogroll API: add/update/delete |
| 3 | **Hidden Links Campaign** | Hidden links in existing content on domains | Hidden links API |
| 4 | **Schedule Campaign** | Posts scheduled by date; scheduler dispatches jobs | Same as PBN post API; schedule_at preserved |
| 5 | **Schedule Sidebar Campaign** | Blogroll links scheduled by date | Blogroll API; date table optional |
| 6 | **WP Scheduled Campaign** | Posts scheduled on remote WordPress (native WP scheduler) | External API create/update/delete posts; sync status |
| 7 | **Sticky Post Campaign** | (UI exists; index/create) | — |

**Supporting:** Admin auth, Dashboard, Profile, Domain check job, Reports (public token-based).

**Flash alerts:** Success and error messages are shown via session keys `cus__success` and `cus__error` (e.g. `return back()->with('cus__success', '...')`). They are rendered in views as styled divs (green for success, red for error). **Auto-hide:** Any such alert inside `.main-content` (with `role="alert"` and `.bg-green-100` or `.bg-red-100`, or class `js-cus-alert`) is automatically hidden after **5 seconds** via `public/js/script.js` (fade out then remove).

---

## 2. Queues & Scheduler (routes/console.php)

All queue names and how they are processed:

| Queue name | Processed by (schedule command) | Used by (Jobs) |
|------------|---------------------------------|----------------|
| `scheduled_campaigns` | `queue:work --queue=scheduled_campaigns --stop-when-empty` (every minute) | PublishScheduledCampaignPostJob |
| `scheduled_sidebar_campaigns` | `queue:work --queue=scheduled_sidebar_campaigns --stop-when-empty` (every minute) | PublishScheduledSidebarBlogrollJob |
| `campaigns` | `queue:work --queue=campaigns --stop-when-empty` (every minute) | PublishCampaignPostJob |
| `sidebar_campaigns` | `queue:work --queue=sidebar_campaigns --stop-when-empty` (every minute) | PublishSidebarBlogrollJob |
| `hidden_links_campaigns` | `queue:work --queue=hidden_links_campaigns --stop-when-empty` (every minute) | PublishHiddenLinksJob |
| `domainCheck` | `queue:work --queue=domainCheck --stop-when-empty` (every minute) | CheckDomainStatus |
| `schedule_campaign_bulk_updates` | `queue:work --queue=schedule_campaign_bulk_updates --stop-when-empty` (every minute) | BulkUpdateScheduleCampaignPostsJob |
| `schedule_campaign_deletions` | `queue:work --queue=schedule_campaign_deletions --stop-when-empty` (every minute) | DeleteScheduleCampaignJob |
| `schedule_sidebar_bulk_updates` | `queue:work --queue=schedule_sidebar_bulk_updates --stop-when-empty` (every minute) | BulkUpdateScheduleSidebarBlogrollJob |
| `schedule_sidebar_deletions` | `queue:work --queue=schedule_sidebar_deletions --stop-when-empty` (every minute) | DeleteScheduleSidebarCampaignJob |
| `bulk_updates` | (manual or custom) | BulkUpdateCampaignPostsJob |
| `deletions` | (manual or custom) | DeleteCampaignJob, DeleteSidebarCampaignJob |
| `bulk_blogroll_updates` | (manual or custom) | BulkUpdateSidebarBlogrollJob |
| `wp_scheduled_campaigns` | (manual: `queue:work --queue=wp_scheduled_campaigns`) | PublishWpScheduledPostJob |
| `wp_scheduled_sync` | (manual) | SyncWpScheduledPostStatusJob |
| `wp_scheduled_campaign_bulk_updates` | (manual) | BulkUpdateWpScheduledPostsJob |
| `wp_scheduled_campaign_deletions` | (manual) | DeleteWpScheduledCampaignJob |
| `update_hidden_links` | (manual) | BulkUpdateHiddenLinksJob |
| `delete_hidden_links` | (manual) | BulkDeleteHiddenLinksJob |
| `delete_hidden_links_campaign` | (manual) | BulkDeleteHiddenLinkCampaignsJob |

**Scheduler (routes/console.php):**

- **Schedule::call – dispatch jobs (every minute):**
  - **dispatch_scheduled_campaign_posts**: selects `ScheduleCampaignPost` (status=queued, schedule_at <= now, lock ok), dispatches `PublishScheduledCampaignPostJob` to `scheduled_campaigns`.
  - **dispatch_scheduled_sidebar_campaign_tasks**: selects `ScheduleSidebarCampaignTask` (queued, schedule_at <= now, lock ok), dispatches `PublishScheduledSidebarBlogrollJob` to `scheduled_sidebar_campaigns`.
- **Schedule::command – process queues (every minute):**
  - `queue:work --queue=scheduled_campaigns ...` → **work_scheduled_campaigns_queue**
  - `queue:work --queue=scheduled_sidebar_campaigns ...` → **work_scheduled_sidebar_campaigns_queue**
  - `queue:work --queue=domainCheck ...` → **work_domain_check_queue**
  - `queue:work --queue=campaigns ...` → **work_campaigns_queue**
  - `queue:work --queue=sidebar_campaigns ...` → **work_sidebar_campaigns_queue**
  - `queue:work --queue=hidden_links_campaigns ...` → **hidden_links_campaigns**
  - `queue:work --queue=schedule_campaign_bulk_updates ...` → **work_schedule_campaign_bulk_updates**
  - `queue:work --queue=schedule_campaign_deletions ...` → **work_schedule_campaign_deletions**
  - `queue:work --queue=schedule_sidebar_bulk_updates ...` → **work_schedule_sidebar_bulk_updates**
  - `queue:work --queue=schedule_sidebar_deletions ...` → **work_schedule_sidebar_deletions**
- **Scheduled command (hourly):** `wp-scheduled:sync-status` → **wp_scheduled_sync_status** (syncs WP Scheduled post status from WordPress).

---

## 3. Feature-by-Feature: Files & Flow

### 3.1 PBN Post Campaign (Run Campaign / Dripfeed)

**Purpose:** Create a campaign (campaign_no, domain category, article category, post quantity, date range, keywords, domains). Posts are published to remote WordPress (full post content). Can run immediately or be dispatched by cron.

**Flow:** Create campaign → creates Campaign, CampaignArticle, CampaignDomain, CampaignPost (status queued). Controller or scheduler dispatches **PublishCampaignPostJob** per post. Job builds content, POSTs to remote; on success marks post completed. Bulk update: **BulkUpdateCampaignPostsJob**. Delete campaign: **DeleteCampaignJob**.

| Type | File |
|------|------|
| **Models** | `Campaign`, `CampaignArticle`, `CampaignDomain`, `CampaignPost` |
| **Controller** | `campaignController` |
| **Migrations** | `create_campaigns_table`, `create_campaign_articles_table`, `create_campaign_domains_table`, `create_campaign_posts_table`, `add_last_bulk_updated_at_to_campaigns_table`, `add_content_updated_at_to_campaign_posts_table` |
| **Jobs** | `PublishCampaignPostJob` (queue: `campaigns`), `BulkUpdateCampaignPostsJob` (`bulk_updates`), `DeleteCampaignJob` (`deletions`) |
| **Services** | `CampaignPostContentBuilder`, `RemotePostUpdateService` |
| **Views** | `admin/campaigns/pbn-post/` (create, index, view, edit, etc.) |
| **Routes** | `resource /campaign`, `campaign/retry`, `campaign/editpost`, `campaign/updatecampaignpost`, `campaign/deleteCampaignPost`, `campaign/bulk/update` |
| **Report** | `GET /campaign/report/{campaign_no}/{token}`, export same + `/export` |

---

### 3.2 Sidebar (Blogroll) Campaign

**Purpose:** Add sidebar links (keyword + URL) to domains. One task per domain+link. Run from UI or queue.

**Flow:** Create campaign → SidebarCampaign, SidebarCampaignLink, SidebarCampaignDomain, SidebarCampaignTask. **PublishSidebarBlogrollJob** calls Blogroll API (add). Edit campaign: bulk edit link batches; **BulkUpdateSidebarBlogrollJob** updates remote. Single edit/delete task. Delete campaign: **DeleteSidebarCampaignJob**.

| Type | File |
|------|------|
| **Models** | `SidebarCampaign`, `SidebarCampaignLink`, `SidebarCampaignDomain`, `SidebarCampaignTask` |
| **Controller** | `SidebarCampaignController` |
| **Migrations** | `create_sidebar_campaigns_table`, `create_sidebar_campaign_links_table`, `create_sidebar_campaign_domains_table`, `create_sidebar_campaign_tasks_table`, `add_last_bulk_updated_at_to_sidebar_campaigns_table`, `add_content_updated_at_to_sidebar_campaign_tasks_table` |
| **Jobs** | `PublishSidebarBlogrollJob` (`sidebar_campaigns`), `BulkUpdateSidebarBlogrollJob` (`bulk_blogroll_updates`), `DeleteSidebarCampaignJob` (`deletions`) |
| **Services** | `BlogrollApiService` |
| **Views** | `admin/campaigns/pbn-sidebar/` (sidebar-campaign, create, view-campaign, edit-sidebar-campaign, edit-sidebar-task, report) |
| **Routes** | `resource /sidebar/campaign`, `sidebar/campaign/retry-task`, `edit-task`, `update-task`, `delete-task`, `bulk-delete-tasks` |
| **Report** | `GET /sidebar/campaign/report/{campaign_no}/{token}`, export |

---

### 3.3 Hidden Links Campaign

**Purpose:** Insert hidden links into existing content on domains via external API.

**Flow:** Create campaign → HiddenLinksCampaign, HiddenLinksCampaignLinks, HiddenLinksCampaignDomains, HiddenLinksCampaignTasks. **PublishHiddenLinksJob** publishes one task. Bulk update: **BulkUpdateHiddenLinksJob**. Bulk delete tasks/campaigns: **BulkDeleteHiddenLinksJob**, **BulkDeleteHiddenLinkCampaignsJob**.

| Type | File |
|------|------|
| **Models** | `HiddenLinksCampaign`, `HiddenLinksCampaignLinks`, `HiddenLinksCampaignDomains`, `HiddenLinksCampaignTasks` |
| **Controller** | `HiddenLinkCampaignController` |
| **Migrations** | `create_hidden_links_campaigns_table`, `create_hidden_links_campaigns_links_table`, `create_hidden_links_campaigns_domains_table`, `create_hidden_links_campaigns_tasks_table`, `add_last_bulk_updated_at`, `add_content_updated_at_to_hidden_links_campaigns_tasks_table` |
| **Jobs** | `PublishHiddenLinksJob` (`hidden_links_campaigns`), `BulkUpdateHiddenLinksJob` (`update_hidden_links`), `BulkDeleteHiddenLinksJob` (`delete_hidden_links`), `BulkDeleteHiddenLinkCampaignsJob` (`delete_hidden_links_campaign`) |
| **Services** | `HiddenLinksApiService` |
| **Views** | `admin/campaigns/` (hidden link views) |
| **Routes** | `resource /hidden/link/campaign`, `edit-task`, `update-task`, `delete-task`, `bulk-delete-tasks`, `bulk-delete` |
| **Report** | `GET /hidden/link/campaign/report/{campaign_no}/{token}`, export |

---

### 3.4 Schedule Campaign (Dripfeed – scheduled posts)

**Purpose:** Schedule full blog posts by date (from/to or per-date table). Scheduler picks posts with `schedule_at <= now()` and dispatches **PublishScheduledCampaignPostJob**. Past dates allowed. Report shows **schedule_at** (selected date).

**Flow:** Create → ScheduleCampaign, ScheduleCampaignDate (optional), ScheduleCampaignArticle, ScheduleCampaignDomain, ScheduleCampaignPost. Every minute scheduler dispatches jobs for due posts → **PublishScheduledCampaignPostJob** (queue `scheduled_campaigns`). Edit campaign (campaign_no + keyword/URL batches) → **BulkUpdateScheduleCampaignPostsJob** (`schedule_campaign_bulk_updates`). Delete campaign → **DeleteScheduleCampaignJob** (`schedule_campaign_deletions`). Single post: edit (fetch from remote, update), retry, delete.

| Type | File |
|------|------|
| **Models** | `ScheduleCampaign`, `ScheduleCampaignDate`, `ScheduleCampaignArticle`, `ScheduleCampaignDomain`, `ScheduleCampaignPost` |
| **Controller** | `ScheduleCampaignController` |
| **Migrations** | `create_schedule_campaigns_table`, `create_schedule_campaign_dates_table`, `create_schedule_campaigns_articles_table`, `create_schedule_campaigns_domains_table`, `create_schedule_campaigns_posts_table`, `fix_schedule_campaigns_posts_schedule_at_preserve` |
| **Jobs** | `PublishScheduledCampaignPostJob` (`scheduled_campaigns`), `BulkUpdateScheduleCampaignPostsJob` (`schedule_campaign_bulk_updates`), `DeleteScheduleCampaignJob` (`schedule_campaign_deletions`) |
| **Services** | `ScheduleCampaignRemotePostUpdateService` |
| **Views** | `admin/campaigns/pbn-post/` (schedule-campaign, create-schedule-campaign, view-schedule-campaign, edit-schedule-campaign, edit-schedule-campaign-post, schedule-campaign-report) |
| **Routes** | `resource /campaign/post/schedule`, `bulk/update/{id}`, `edit-post/{postId}`, `update-post`, `retry-post`, `delete-post` |
| **Report** | `GET /schedule/campaign/report/{campaign_no}/{token}`, export |

---

### 3.5 Schedule Sidebar Campaign (Scheduled blogroll)

**Purpose:** Schedule sidebar links by date (from/to or per-date table). Scheduler dispatches **PublishScheduledSidebarBlogrollJob**. Past dates allowed. Report shows **schedule_at**.

**Flow:** Create → ScheduleSidebarCampaign, ScheduleSidebarCampaignDate (optional), ScheduleSidebarCampaignLink, ScheduleSidebarCampaignDomain, ScheduleSidebarCampaignTask. Scheduler dispatches due tasks → **PublishScheduledSidebarBlogrollJob** (`scheduled_sidebar_campaigns`). Edit campaign + bulk update batches → **BulkUpdateScheduleSidebarBlogrollJob** (`schedule_sidebar_bulk_updates`). Delete campaign → **DeleteScheduleSidebarCampaignJob** (`schedule_sidebar_deletions`). Single task: edit, retry, delete.

| Type | File |
|------|------|
| **Models** | `ScheduleSidebarCampaign`, `ScheduleSidebarCampaignDate`, `ScheduleSidebarCampaignLink`, `ScheduleSidebarCampaignDomain`, `ScheduleSidebarCampaignTask` |
| **Controller** | `ScheduleSidebarCampaignController` |
| **Migrations** | `create_schedule_sidebar_campaigns_table`, `create_schedule_sidebar_campaign_dates_table`, `create_schedule_sidebar_campaign_links_table`, `create_schedule_sidebar_campaign_domains_table`, `create_schedule_sidebar_campaign_tasks_table` |
| **Jobs** | `PublishScheduledSidebarBlogrollJob` (`scheduled_sidebar_campaigns`), `BulkUpdateScheduleSidebarBlogrollJob` (`schedule_sidebar_bulk_updates`), `DeleteScheduleSidebarCampaignJob` (`schedule_sidebar_deletions`) |
| **Services** | `BlogrollApiService` |
| **Views** | `admin/campaigns/pbn-sidebar/` (schedule-sidebar-campaign, create-schedule-sidebar-campaign, view-schedule-campaign, edit-schedule-sidebar-campaign, edit-schedule-sidebar-task, schedule-sidebar-campaign-report) |
| **Routes** | `resource /campaign/sidebar/schedule`, `bulk/update/{id}`, `edit-task`, `update-task`, `retry-task`, `delete-task` |
| **Report** | `GET /schedule/sidebar/campaign/report/{campaign_no}/{token}`, export |

---

### 3.6 WP Scheduled Campaign (WordPress-native scheduling)

**Purpose:** Create posts that are scheduled on the **remote** WordPress (WP cron). API: create/update/delete posts; sync status (future → publish, missed schedule). Bulk edit keyword/URL batches; single post edit/retry/delete; campaign delete.

**Flow:** Create → WpScheduledCampaign, WpScheduledCampaignDate, WpScheduledCampaignDomain, WpScheduledCampaignArticle, WpScheduledCampaignPost. User runs campaign or sync; **PublishWpScheduledPostJob** (`wp_scheduled_campaigns`). **SyncWpScheduledPostStatusJob** (`wp_scheduled_sync`). **BulkUpdateWpScheduledPostsJob** (`wp_scheduled_campaign_bulk_updates`). **DeleteWpScheduledCampaignJob** (`wp_scheduled_campaign_deletions`).

| Type | File |
|------|------|
| **Models** | `WpScheduledCampaign`, `WpScheduledCampaignDate`, `WpScheduledCampaignDomain`, `WpScheduledCampaignArticle`, `WpScheduledCampaignPost` |
| **Controller** | `WpScheduledCampaignController` |
| **Migrations** | `create_wp_scheduled_campaigns_table`, `create_wp_scheduled_campaign_dates_table`, `create_wp_scheduled_campaign_domains_table`, `create_wp_scheduled_campaign_articles_table`, `create_wp_scheduled_campaign_posts_table` |
| **Jobs** | `PublishWpScheduledPostJob` (`wp_scheduled_campaigns`), `SyncWpScheduledPostStatusJob` (`wp_scheduled_sync`), `BulkUpdateWpScheduledPostsJob` (`wp_scheduled_campaign_bulk_updates`), `DeleteWpScheduledCampaignJob` (`wp_scheduled_campaign_deletions`) |
| **Services** | `WpScheduledPostContentBuilder`, `WpScheduledRemotePostUpdateService` |
| **Views** | `admin/campaigns/wp-scheduled/` (create, index, show, edit, edit-post, report) |
| **Routes** | `resource /campaign/post/wp-schedule`, `run`, `sync`, `retry-post`, `sync-post`, `editpost`, `updatepost`, `deletepost`, `bulk/update` |
| **Report** | `GET /campaign/post/wp-schedule/report/{campaign_no}/{token}`, export |
| **Commands** | `SyncWpScheduledStatusCommand` (`wp-scheduled:sync-status`), `ResolveWpScheduledPermalinksCommand` (`wp-scheduled:resolve-permalinks`) |

---

### 3.7 Sticky Post Campaign

**Purpose:** UI only (create, index). No full flow documented in same detail.

| Type | File |
|------|------|
| **Controller** | `StickyPostCampaignController` |
| **Routes** | `GET /sticky/campaign/create`, `GET /sticky/campaign/` |

---

### 3.8 Domains & Domain Categories

| Type | File |
|------|------|
| **Models** | `Domain`, `DomainCategory`, `DomainSet` |
| **Controllers** | `DomainController`, `DomainCategoryController`, `DomainSetController` |
| **Migrations** | `create_domains_table`, `create_domain_categories_table`, `create_domain_sets_table` |
| **Job** | `CheckDomainStatus` (queue: `domainCheck`) |

---

### 3.9 Articles & Article Sets / Categories / Languages

| Type | File |
|------|------|
| **Models** | `Article`, `ArticleCategory`, `ArticleLanguage`, `ArticleSet` |
| **Controllers** | `ArticleController`, `ArticleCategoryController`, `ArticleLanguageController`, `ArticleSetController` |
| **Migrations** | `create_articles_table`, `create_article_categories_table`, `create_article_languages_table`, `create_article_sets_table`, `create_article_set_items_table`, `add_fulltext_to_articles_search_text` |

---

### 3.10 Admin Auth & Profile

| Type | File |
|------|------|
| **Controllers** | `AdminAuthenticatorController`, `AdminOtpController`, `AdminPasswordResetController`, `ProfileController` |
| **Migrations** | `create_admins_table`, `create_admin_otps_table`, `create_admin_password_resets_table` |

---

## 4. Queue Commands Summary (run manually if not in scheduler)

```bash
# Schedule Campaign (dripfeed posts)
php artisan queue:work --queue=scheduled_campaigns --sleep=1 --tries=3 --stop-when-empty

# Schedule Sidebar Campaign
php artisan queue:work --queue=scheduled_sidebar_campaigns --sleep=1 --tries=3 --stop-when-empty

# PBN Post Campaign
php artisan queue:work --queue=campaigns --sleep=1 --tries=3 --stop-when-empty

# Sidebar Campaign
php artisan queue:work --queue=sidebar_campaigns --sleep=1 --tries=3 --stop-when-empty

# Hidden Links
php artisan queue:work --queue=hidden_links_campaigns --sleep=1 --tries=3 --stop-when-empty

# Schedule Campaign bulk update + deletion
php artisan queue:work --queue=schedule_campaign_bulk_updates --stop-when-empty
php artisan queue:work --queue=schedule_campaign_deletions --stop-when-empty

# Schedule Sidebar bulk update + deletion
php artisan queue:work --queue=schedule_sidebar_bulk_updates --stop-when-empty
php artisan queue:work --queue=schedule_sidebar_deletions --stop-when-empty

# WP Scheduled (if not using a dedicated worker)
php artisan queue:work --queue=wp_scheduled_campaigns --stop-when-empty
php artisan queue:work --queue=wp_scheduled_sync --stop-when-empty
php artisan queue:work --queue=wp_scheduled_campaign_bulk_updates --stop-when-empty
php artisan queue:work --queue=wp_scheduled_campaign_deletions --stop-when-empty

# Domain check
php artisan queue:work --queue=domainCheck --stop-when-empty
```

---

## 5. External APIs (summary)

- **Posts (PBN / Schedule / WP Scheduled):** `POST .../wp-json/external/v1/posts/create`, `GET .../posts/{id}`, `POST .../posts/update/{id}`, `DELETE .../posts/delete/{id}`.
- **Blogroll (Sidebar / Schedule Sidebar):** `POST .../wp-json/external/v1/blogroll/add`, `GET .../blogroll`, `POST .../blogroll/update/{id}`, `DELETE .../blogroll/delete/{id}` (via `BlogrollApiService`).
- **Hidden links:** Custom API (HiddenLinksApiService).

---

## 6. Related docs

- `docs/SCHEDULE_CAMPAIGN_CHANGES.md` – Schedule Campaign changelog and file list.
- `docs/FEATURES_AND_CHANGES.md` – Features and app/database changes.
- `docs/WP_SCHEDULED_CAMPAIGNS_QUEUES_AND_FILES.md` – WP Scheduled queues and files.
- `docs/WP_SLUG_URL_FOR_REPORT.md` – Report slug URL behaviour.
