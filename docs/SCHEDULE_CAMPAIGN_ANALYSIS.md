# Schedule Campaign – Analysis (Models, Database & App Flow)

This document describes the **Schedule Campaign** feature: database tables (`schedule_campaigns`, `schedule_campaigns_articles`, `schedule_campaigns_domains`, `schedule_campaigns_posts`), Eloquent models, relationships, and how they are used in the application.

---

## 1. Overview

**Schedule Campaign** lets admins create a campaign with a fixed number of posts, each assigned to an article, a domain, and a keyword/URL. Posts are distributed across a date range (`schedule_from_date`–`schedule_to_date`). Each post has a `schedule_at` timestamp. A **scheduler** (Laravel `Schedule::call` every minute) finds posts with `schedule_at <= now()` and status `queued`, and dispatches **PublishScheduledCampaignPostJob** to the `scheduled_campaigns` queue. The job builds title/content from the article + keyword/URL, publishes to the remote WordPress site via API, and on success soft-deletes the article and updates counters.

This is **different from WP Scheduled Campaigns**, which use tables `wp_scheduled_campaigns`, `wp_scheduled_campaign_posts`, etc., and different queues/jobs.

---

## 1.1 Client requirement: past / today / future dates and reporting

**Requirement:** A campaign can have posts scheduled for past, today, or future dates (e.g. 3 for 10 Feb, 3 for 11 Feb, 4 for 12 Feb).

- **Processing**
  - Posts whose **scheduled date is in the past or today** must be published as soon as the scheduler runs (they are eligible because `schedule_at <= now()`).
  - Posts whose **scheduled date is in the future** must stay in the queue until that date (they are not eligible until `schedule_at <= now()`).
- **Reporting**
  - The **Scheduled At** column must always show the **original schedule date** chosen at campaign creation (e.g. 10 Feb for the first 3 posts), not the date when the post was actually published.
  - So: past-dated posts are published on “today” when the job runs, but reports still show the original scheduled date for transparency.

**Where it is implemented**

| Requirement | Implementation |
|-------------|----------------|
| Past/today posts published immediately | `routes/console.php`: scheduler selects posts with `schedule_at <= now()` and dispatches the job. Past and today both satisfy this. |
| Future posts stay queued | Same condition: when `schedule_at` is in the future, `schedule_at <= now()` is false, so they are not dispatched until that date. |
| Report shows original schedule date | `schedule_campaign_report.blade.php`: “Scheduled At” uses `$post->schedule_at` only (never `published_at`). `schedule_at` is set at creation and never changed. |
| WordPress post date | `PublishScheduledCampaignPostJob`: sends `schedule_time` from `$post->schedule_at` to the API so the remote post can show the intended date. |

---

## 2. Database Tables

### 2.1 `schedule_campaigns`

| Column | Type | Null | Default | Description |
|--------|------|------|---------|-------------|
| `id` | bigint(20) UNSIGNED | No | — | Primary key, AUTO_INCREMENT |
| `campaign_no` | varchar(255) | No | — | Unique campaign identifier (e.g. SCH-20260106120000-1234) |
| `admin_id` | bigint(20) UNSIGNED | No | — | FK → admins.id |
| `report_token` | varchar(64) | Yes | NULL | Unique token for public report URL |
| `domain_category_id` | bigint(20) UNSIGNED | Yes | NULL | FK → domain_categories.id (optional filter) |
| `article_category_id` | bigint(20) UNSIGNED | Yes | NULL | FK → article_categories.id (optional filter) |
| `schedule_from_date` | date | No | — | Start of schedule window |
| `schedule_to_date` | date | No | — | End of schedule window |
| `status` | enum | No | 'queued' | queued, running, paused, completed, semi_failed, failed, cancelled |
| `total_targets` | int(10) UNSIGNED | No | — | Total number of posts (post quantity from UI) |
| `completed_targets` | int(10) UNSIGNED | No | 0 | Count of successfully published posts |
| `failed_targets` | int(10) UNSIGNED | No | 0 | Count of failed posts |
| `started_at` | timestamp | Yes | NULL | When first post started publishing |
| `finished_at` | timestamp | Yes | NULL | When campaign finished (all targets done) |
| `created_at` | timestamp | Yes | NULL | |
| `updated_at` | timestamp | Yes | NULL | |

**Indexes:** PRIMARY (`id`), UNIQUE (`campaign_no`), UNIQUE (`report_token`), FK indexes on `domain_category_id`, `article_category_id`, `status`, `admin_id`, (`admin_id`, `status`), (`schedule_from_date`, `schedule_to_date`).

---

### 2.2 `schedule_campaigns_articles`

Links a campaign to an article and stores **keyword/URL snapshot** (single or JSON).

| Column | Type | Null | Default | Description |
|--------|------|------|---------|-------------|
| `id` | bigint(20) UNSIGNED | No | — | Primary key, AUTO_INCREMENT |
| `schedule_campaign_id` | bigint(20) UNSIGNED | No | — | FK → schedule_campaigns.id |
| `article_id` | bigint(20) UNSIGNED | No | — | FK → articles.id |
| `keyword` | text | Yes | NULL | Keyword(s): string or JSON array |
| `url` | text | Yes | NULL | URL(s): string or JSON array |
| `keyword_type` | enum('single','json') | No | 'single' | How keyword is stored |
| `url_type` | enum('single','json') | No | 'single' | How url is stored |
| `nofollow` | tinyint(1) | No | 0 | Nofollow on links |
| `media` | varchar(255) | Yes | NULL | Optional media reference |
| `created_at` | timestamp | Yes | NULL | |
| `updated_at` | timestamp | Yes | NULL | |

**Unique:** (`schedule_campaign_id`, `article_id`) — one row per article per campaign.  
**Indexes:** PRIMARY (`id`), `schedule_campaign_id`, `article_id`.

---

### 2.3 `schedule_campaigns_domains`

Links a campaign to a domain (many-to-many pivot).

| Column | Type | Null | Default | Description |
|--------|------|------|---------|-------------|
| `id` | bigint(20) UNSIGNED | No | — | Primary key, AUTO_INCREMENT |
| `schedule_campaign_id` | bigint(20) UNSIGNED | No | — | FK → schedule_campaigns.id |
| `domain_id` | bigint(20) UNSIGNED | No | — | FK → domains.id |
| `created_at` | timestamp | Yes | NULL | |
| `updated_at` | timestamp | Yes | NULL | |

**Unique:** (`schedule_campaign_id`, `domain_id`) — same domain cannot be added twice to a campaign.  
**Indexes:** PRIMARY (`id`), `schedule_campaign_id`, `domain_id`.

---

### 2.4 `schedule_campaigns_posts`

One row per **scheduled post**: one (article, domain) pair per campaign with a `schedule_at` time. This is the unit that gets published by the job.

| Column | Type | Null | Default | Description |
|--------|------|------|---------|-------------|
| `id` | bigint(20) UNSIGNED | No | — | Primary key, AUTO_INCREMENT |
| `schedule_campaign_id` | bigint(20) UNSIGNED | No | — | FK → schedule_campaigns.id |
| `schedule_campaign_article_id` | bigint(20) UNSIGNED | No | — | FK → schedule_campaigns_articles.id |
| `schedule_campaign_domain_id` | bigint(20) UNSIGNED | No | — | FK → schedule_campaigns_domains.id |
| `schedule_at` | timestamp | No | — | When to publish (distributed across campaign date range) |
| `status` | enum | No | 'queued' | queued, publishing, success, failed |
| `attempt_count` | int(10) UNSIGNED | No | 0 | Publish attempt counter |
| `next_retry_at` | timestamp | Yes | NULL | Next retry after failure |
| `last_error` | text | Yes | NULL | Last error message |
| `locked_at` | timestamp | Yes | NULL | Lock time (job processing) |
| `lock_token` | varchar(255) | Yes | NULL | Lock token for safe update |
| `remote_id` | varchar(255) | Yes | NULL | Post ID on remote WordPress |
| `remote_title` | varchar(255) | Yes | NULL | Title on remote |
| `remote_url` | varchar(255) | Yes | NULL | URL of published post |
| `http_status` | int(11) | Yes | NULL | HTTP status from API |
| `remote_status` | varchar(255) | Yes | NULL | Remote post status (e.g. publish) |
| `remote_response` | json | Yes | NULL | Full API response |
| `published_at` | timestamp | Yes | NULL | When successfully published |
| `created_at` | timestamp | Yes | NULL | |
| `updated_at` | timestamp | Yes | NULL | |

**Unique:** (`schedule_campaign_article_id`, `schedule_campaign_domain_id`) — one post per (article, domain) per campaign.  
**Indexes:** PRIMARY (`id`), (`status`, `schedule_at`), (`status`, `next_retry_at`).

*Note:* In phpMyAdmin you may see `schedule_at` with a default/ON UPDATE; the migration in code does not set that. Application code always sets `schedule_at` explicitly when creating posts.

---

## 3. Eloquent Models

### 3.1 `App\Models\Admin\ScheduleCampaign`

- **Table:** `schedule_campaigns`
- **Fillable:** campaign_no, admin_id, domain_category_id, article_category_id, total_targets, schedule_from_date, schedule_to_date, status, completed_targets, failed_targets, started_at, finished_at
- **Casts:** schedule_from_date / schedule_to_date → date, started_at / finished_at → datetime
- **Relations:**
  - `articles()` → hasMany **ScheduleCampaignArticle**
  - `domains()` → hasMany **ScheduleCampaignDomain**
  - `posts()` → hasMany **ScheduleCampaignPost**
  - `domainCategory()` → belongsTo **DomainCategory**
  - `admin()` → belongsTo **Admin**
- **Boot:** Sets `report_token` to random 64-char string on create if empty.

---

### 3.2 `App\Models\Admin\ScheduleCampaignArticle`

- **Table:** `schedule_campaigns_articles`
- **Fillable:** schedule_campaign_id, article_id, keyword, url, keyword_type, url_type, media, nofollow
- **Relations:**
  - `campaign()` → belongsTo **ScheduleCampaign**
  - `article()` → belongsTo **Article**
  - `posts()` → hasMany **ScheduleCampaignPost** (foreign key: schedule_campaign_article_id)

---

### 3.3 `App\Models\Admin\ScheduleCampaignDomain`

- **Table:** `schedule_campaigns_domains`
- **Fillable:** schedule_campaign_id, domain_id
- **Relations:**
  - `campaign()` → belongsTo **ScheduleCampaign**
  - `domain()` → belongsTo **Domain**
  - `posts()` → hasMany **ScheduleCampaignPost** (foreign key: schedule_campaign_domain_id)

---

### 3.4 `App\Models\Admin\ScheduleCampaignPost`

- **Table:** `schedule_campaigns_posts`
- **Fillable:** schedule_campaign_id, schedule_campaign_article_id, schedule_campaign_domain_id, schedule_at, status, attempt_count, last_attempt_at, next_retry_at, last_error, locked_at, lock_token, remote_id, remote_title, remote_url, http_status, remote_status, remote_response, published_at
- **Casts:** schedule_at, last_attempt_at, next_retry_at, locked_at, locked_until, published_at → datetime; remote_response → array
- **Relations:**
  - `campaign()` / `scheduleCampaign()` → belongsTo **ScheduleCampaign**
  - `campaignArticle()` → belongsTo **ScheduleCampaignArticle**
  - `campaignDomain()` → belongsTo **ScheduleCampaignDomain**

*Note:* `last_attempt_at` and `locked_until` are in fillable/casts but **do not exist** in the migration; the table only has `locked_at`, `lock_token`, `next_retry_at`. Consider removing those from the model if not added to the DB.

---

## 4. Entity Relationship Summary

```
schedule_campaigns (1) ──< schedule_campaigns_articles (N)
        │                           │
        │                           └── article_id → articles
        │
        ├──< schedule_campaigns_domains (N)
        │           │
        │           └── domain_id → domains
        │
        └──< schedule_campaigns_posts (N)
                    │
                    ├── schedule_campaign_article_id → schedule_campaigns_articles
                    └── schedule_campaign_domain_id → schedule_campaigns_domains
```

- One **ScheduleCampaign** has many **ScheduleCampaignArticle** and many **ScheduleCampaignDomain**.
- **ScheduleCampaignPost** belongs to one campaign, one **ScheduleCampaignArticle** (and thus one Article + keyword/URL), and one **ScheduleCampaignDomain** (and thus one Domain). Unique (article, domain) per campaign.

---

## 5. Application Flow

### 5.1 Controller: `App\Http\Controllers\Admin\ScheduleCampaignController`

| Method | Route / name | Description |
|--------|----------------|-------------|
| `index` | GET campaign/post/schedule | List campaigns (paginated), filter by campaign_no, admin. |
| `create` | GET campaign/post/schedule/create | Create form: campaign_no, categories, post quantity, date range, articles (from sets/language), domains, keyword/URL. |
| `store` | POST campaign/post/schedule | Validates post_quantity, schedule_from_date/to, articles, domains, keywords. Creates campaign, domains, articles (with keyword/URL), then **posts** with `schedule_at` spread across the date range. Locks articles (`lock_at`). Does **not** dispatch jobs; scheduler does. |
| `show` | GET campaign/post/schedule/{id} | Campaign detail with paginated posts (domain, article, status, etc.). |
| `report` | GET report (campaign_no + token) | Public report: stats (total, success, queued, failed), success rate, post list. |
| `exportReport` | GET report export | Excel export of posts (domain, blog post URL, scheduled at, keywords/URLs, status, published date). |
| `edit` / `update` / `destroy` | — | Not implemented (empty). |

**Routes:** Resource `schedule.campaign`; separate routes for `admin.schedule.campaign.report` and `admin.schedule.campaign.report.export`.

### 5.2 Job: `App\Jobs\PublishScheduledCampaignPostJob`

- **Queue:** `scheduled_campaigns`
- **Constructor:** `postId` (ScheduleCampaignPost id)
- **Flow:**
  1. **Lock:** In a DB transaction, load post with `lockForUpdate`, check status (skip if success/failed), campaign not paused/cancelled, next_retry_at not in future, lock not stale. Set status = `publishing`, set `locked_at` and `lock_token`, set campaign `started_at` if null.
  2. **Build content:** Load post with `campaignDomain.domain`, `campaignArticle.article`. Build title and HTML from article name/description and keyword/URL from `schedule_campaigns_articles` (single or JSON), inject links into paragraphs (nofollow from campaign article). Logic is inline in `buildContent()` (no separate service class).
  3. **Publish:** Call `postToWordPress($post, $title, $content)` — HTTP POST to the domain’s WordPress API (create post).
  4. **Success:** In transaction, set post status, remote_id, remote_status, remote_title, remote_url, remote_response, published_at, clear lock, increment campaign `completed_targets`. **Soft-delete the article** (`Article::find(...)?->delete()`).
  5. **Failure (exception):** Increment attempt_count, set last_error; if attempts < 5, set next_retry_at and re-dispatch job with delay; else set status = failed and increment campaign failed_targets. Clear lock.
  6. **Finally:** `finalizeCampaignIfDone()` — if completed_targets + failed_targets >= total_targets, set campaign finished_at and status (completed / semi_failed / failed).

### 5.3 Scheduler: `routes/console.php`

- **Every minute:** `Schedule::call` selects up to 50 **ScheduleCampaignPost** rows where status = `queued`, `schedule_at` <= now(), and (locked_at is null or older than 5 minutes). Dispatches **PublishScheduledCampaignPostJob** for each to queue `scheduled_campaigns`.
- **Important:** There is **no** `Schedule::command('queue:work --queue=scheduled_campaigns ...')` in the file. So you must run a worker yourself, e.g. `php artisan queue:work --queue=scheduled_campaigns`, or add a scheduled `queue:work` for this queue.

---

## 6. Views (Schedule Campaign)

- `resources/views/admin/campaigns/pbn-post/schedule-campaign.blade.php` — List of campaigns.
- `resources/views/admin/campaigns/pbn-post/create-schedule-campaign.blade.php` — Create form.
- `resources/views/admin/campaigns/pbn-post/view-schedule-campaign.blade.php` — Campaign show (posts table).
- `resources/views/admin/campaigns/pbn-post/schedule-campaign-report.blade.php` — Public report (token-based).
- Report export is streamed Excel (no blade).

---

## 7. Migrations

- `2026_01_06_132625_create_schedule_campaigns_table.php`
- `2026_01_06_133825_create_schedule_campaigns_articles_table.php`
- `2026_01_06_134154_create_schedule_campaigns_domains_table.php`
- `2026_01_06_134441_create_schedule_campaigns_posts_table.php`

---

## 8. Summary

| Item | Detail |
|------|--------|
| **Tables** | schedule_campaigns, schedule_campaigns_articles, schedule_campaigns_domains, schedule_campaigns_posts |
| **Models** | ScheduleCampaign, ScheduleCampaignArticle, ScheduleCampaignDomain, ScheduleCampaignPost |
| **Controller** | ScheduleCampaignController (index, create, store, show, report, exportReport; edit/update/destroy stubbed) |
| **Job** | PublishScheduledCampaignPostJob (queue: scheduled_campaigns); builds content inline, posts to WP, soft-deletes article on success |
| **Dispatch** | Laravel scheduler every minute picks queued posts with schedule_at <= now() and dispatches job; worker must run `queue:work --queue=scheduled_campaigns` |
| **Article lifecycle** | Locked on campaign create (`lock_at`); soft-deleted after successful publish |

This completes the analysis of Schedule Campaign models, database, and app flow.
