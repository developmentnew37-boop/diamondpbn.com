# PBN Automation Software - Context

## Purpose
Laravel admin panel for Private Blog Network management. Automates article publishing campaigns across remote WordPress sites via HTTP APIs.

## Stack
- Laravel 12 (PHP 8.2+), MySQL 8+, Queue: database driver
- Frontend: Vite 7, Tailwind 4, ApexCharts
- Key: Sanctum (auth), Mews HTML Purifier, PHPOffice/PHPWord, Spatie Excel

## Structure
```
app/
├── Http/Controllers/Admin/  # 20 controllers + Concerns/ traits
├── Http/Controllers/Api/admin/
├── Jobs/                    # 22 queue jobs
├── Models/Admin/            # 34 models
├── Services/                # 10 services (API clients, content builders)
└── Middleware/Admin/        # Auth, roles, permissions
```

## Users & Auth
- **Admin** model: 3 roles (Super Admin=0, Admin=1, Member=2)
- OTP verification, role-based permissions
- Members: articles only | Admin/Super Admin: full access

## Core Entities

### Domains
- Fields: name, DA/DR/TF/SS metrics, IP, api_key, status, admin_id
- Relations: DomainCategory, DomainSet
- Status checks via queue, bulk ops

### Articles
- Types: Manual(0), File Upload(1), AI Generated(2)
- Status: Unused(0), Used(1), Archived(2)
- DOCX bulk import, fulltext search, soft deletes, locking mechanism
- Relations: ArticleCategory, ArticleLanguage, ArticleSet

## Campaign Types (7)

### 1. PBN Post (Standard)
- Tables: campaigns, campaign_articles, campaign_domains, campaign_posts
- Job: PublishCampaignPostJob (queue: campaigns)
- API: `/wp-json/external/v1/posts/create`
- Inserts keyword/URL pairs into articles, supports sticky posts

### 2. Sidebar/Blogroll
- Tables: sidebar_campaigns, sidebar_campaign_links, sidebar_campaign_domains, sidebar_campaign_tasks
- Job: PublishSidebarBlogrollJob (queue: sidebar_campaigns)
- API: `/wp-json/external/v1/blogroll/add`
- Multiple links per domain, nofollow/sponsored support

### 3. Hidden Links
- Tables: hidden_links_campaigns, hidden_links_campaigns_links, hidden_links_campaigns_domains, hidden_links_campaigns_tasks
- Job: PublishHiddenLinksJob (queue: hidden_links_campaigns)
- API: `/wp-json/external/v1/hidden-links/add`
- Auth: X-External-API-Key header

### 4. Schedule Post
- Tables: schedule_campaigns, schedule_campaign_dates, schedule_campaign_articles, schedule_campaign_domains, schedule_campaign_posts
- Job: PublishScheduledCampaignPostJob (queue: scheduled_campaigns)
- Time-distributed posts, supports sticky

### 5. Schedule Sidebar
- Tables: schedule_sidebar_campaigns, schedule_sidebar_campaign_dates, schedule_sidebar_campaign_links, schedule_sidebar_campaign_domains, schedule_sidebar_campaign_tasks
- Job: PublishScheduledSidebarBlogrollJob (queue: scheduled_sidebar_campaigns)

### 6. WP Scheduled
- Tables: wp_scheduled_campaigns, wp_scheduled_campaign_dates, wp_scheduled_campaign_articles, wp_scheduled_campaign_domains, wp_scheduled_campaign_posts
- Jobs: PublishWpScheduledPostJob, SyncWpScheduledPostStatusJob
- Uses WordPress native scheduling, sync command checks remote status

### 7. Sticky Post
- Variant of Schedule Post with `is_sticky_campaign=true`

## Campaign Patterns
- **campaign_no**: Unique ID (PBN-2026-xxxx, SB-2026-xxxx, HL-2026-xxxx)
- **report_token**: 64-char token for public reports (no auth)
- **Status flow**: queued → running → completed/semi_failed/failed/cancelled/paused
- **Progress**: total_targets, completed_targets, failed_targets
- **Timestamps**: started_at, finished_at, last_bulk_updated_at

## Key Services

### API Services
- **BlogrollApiService**: Sidebar link management
- **HiddenLinksApiService**: Hidden link management
- **CampaignPostContentBuilder**: Inserts keyword/URL pairs into article paragraphs
- Timeout: 60-180s, retry with exponential backoff (5 attempts)

### WP API Endpoints
- `/wp-json/external/v1/posts/create` - PBN posts
- `/wp-json/external/v1/blogroll/add|update|delete` - Sidebar
- `/wp-json/external/v1/hidden-links/add|update|delete` - Hidden links
- Auth: api_key in payload or X-External-API-Key header

## Queue Architecture
- **Named queues**: default, campaigns, deletions, bulk_updates, sidebar_campaigns, hidden_links_campaigns, scheduled_campaigns, wp_scheduled_campaigns, domainCheck
- **Job patterns**: Pessimistic locking (lock_token), retry logic, status transitions
- **Dev**: `composer run dev` (server + queue + Vite)

## Bulk Operations
- Update posts/links (keywords, URLs, content)
- Delete campaigns/tasks (queued)
- Multi-level keyword updates (JSON arrays)
- Purge local data (keep remote)

## Notable Patterns
1. Articles soft-deleted after use, snapshots in campaign_articles
2. Slug generation: Unicode-aware (Thai), emoji removal, collision handling
3. Job locking: UUID tokens prevent duplicate processing
4. Auto-completion: when completed + failed = total_targets
5. Domain selection: random, domain_set, or manual
6. Public reports: token-protected URLs bypass auth, Excel/CSV export

## File Counts
20 controllers | 34 models | 22 jobs | 10 services | 60+ migrations
