# Diamond PBN Automation — Project Overview

This document describes **what this project is**, **what it does**, and **where the code lives**: controllers, routes, endpoints, jobs, services, models, and helpers.

---

## 1. What this project is

**Diamond PBN Automation** is a Laravel 12 admin application for operating a Private Blog Network (PBN). Operators manage:

- A catalog of remote **WordPress sites (domains)** with API keys
- A library of **articles** (multilingual, categorized, grouped into sets)
- **Campaigns** that publish posts, sidebar/blogroll links, hidden links, and scheduled content to those sites

The Laravel app does **not** host the WordPress blogs. It **orchestrates publishing** over HTTP to a companion WordPress plugin:

**Diamond PBN (External API Manager) v7.8.0+**  
Base URL on each site: `https://{domain}/wp-json/external/v1/`

Full remote API contract: [`API-DATA-FLOW.md`](API-DATA-FLOW.md).

---

## 2. What it does (functionality)

| Area | What operators can do |
|------|------------------------|
| **Auth** | Admin login, 6-digit OTP, password reset |
| **Users** | Super Admin / Admin / Member roles; feature permissions |
| **Domains** | CRUD, categories, sets, bulk upload/delete, move between categories, extract lists |
| **Domain health** | Queued status checks, recheck disconnected sites, scheduled health probe |
| **Pending domains** | Inbound webhook from WordPress; approve/reject into inventory |
| **Plugin manager** | Upload WP plugin ZIPs and deploy them to many domains |
| **Articles** | CRUD, DOCX import, languages, categories, sets, trash, restore, purge |
| **Campaigns** | Create, run, retry, bulk-update keywords/URLs, replace domains, delete remote + local, or purge local-only |
| **Reports** | Token-protected public report + Excel export (no login) |
| **Conversion** | Convert live post/sidebar campaigns into dripfeed (scheduled) campaigns |
| **Billing** | Local clients, rate lists, per-category prices, invoices, payment tracking |
| **Webhooks** | Rotate secrets used by inbound domain registration |

### Campaign types

1. **PBN Post** (`campaigns`) — publish WordPress posts immediately  
2. **Sidebar / blogroll** (`sidebar_campaigns`) — add sidebar links via blogroll API  
3. **Hidden link** (`hidden_links_campaigns`) — non-visible links in posts  
4. **Schedule post** (`schedule_campaigns`) — Laravel-queued delayed posts (dripfeed)  
5. **Schedule sticky post** — same as schedule post, with `is_sticky_campaign`  
6. **Schedule sidebar** (`schedule_sidebar_campaigns`) — delayed blogroll links  
7. **WP scheduled** (`wp_scheduled_campaigns`) — WordPress-native `future` posts (remote WP cron)  
8. **Sticky post** — live post campaign with `is_sticky: true`

### Typical campaign flow

1. Operator selects domain category/set + article category/set + keyword/URL pairs.  
2. System creates **campaign articles** (snapshots + rel flags) and **campaign domains**.  
3. Cartesian product creates **tasks/posts** (one per domain × article).  
4. Jobs publish to WordPress over HTTP.  
5. After a successful post publish, the source article is **soft-deleted** (snapshot stays on the campaign).  
6. Status rolls up: `queued` → `running` → `completed` / `semi_failed` / `failed`.

---

## 3. Tech stack

| Layer | Choice |
|-------|--------|
| PHP | 8.2+ |
| Framework | Laravel 12 |
| Database | MySQL |
| Queue | Database driver (`jobs` table), named queues |
| Frontend | Blade, Vite 7, Tailwind CSS 4, ApexCharts, CKEditor 5 |
| Packages | Sanctum, Mews HTML Purifier, PHPWord, Spatie Simple Excel, DomPDF |

---

## 4. How routes are loaded

`bootstrap/app.php` registers:

- **Web** → `routes/web.php` (includes `routes/admin.php`)
- **API** → `routes/api.php` (prefix `/api`)
- **Commands / scheduler** → `routes/console.php`
- **Health** → `GET /up`

Middleware aliases:

| Alias | Class | Purpose |
|-------|--------|---------|
| `admin.auth` | `AdminAuth` | Logged-in admin |
| `admin.guest` | `AdminGuest` | Login/OTP/forgot pages |
| `admin.api.auth` | `ApiAdminAuth` | Session-auth for AJAX admin APIs |
| `role` | `CheckRole` | Role gate |
| `permission` | `CheckFeaturePermission` | Feature permission (e.g. local clients) |
| `can.create.campaigns` | `CanCreateCampaigns` | Super Admin + Admin only (Members blocked) |

**Roles:** Super Admin (`role_id` 1), Admin (2), Member (3). Members can manage articles but cannot create campaigns.

---

## 5. Laravel HTTP endpoints

### 5.1 Public / unauthenticated

| Method | Path | Name | Controller |
|--------|------|------|------------|
| GET | `/` | `home` | Closure → `home` view |
| GET | `/up` | — | Laravel health |
| GET | `/plugin-deployments/download/{uuid}` | `plugin-deployments.download` | `PluginPackageController@download` (signed, throttled) |

**Token-protected reports** (no login; treat tokens as secrets):

| Method | Path | Name |
|--------|------|------|
| GET | `/campaign/report/{campaign_no}/{token}` | `admin.campaign.report` |
| GET | `/campaign/report/{campaign_no}/{token}/export` | `admin.campaign.report.export` |
| GET | `/sidebar/campaign/report/{campaign_no}/{token}` | `admin.sidebar.campaign.report` |
| GET | `/sidebar/campaign/report/{campaign_no}/{token}/export` | `admin.sidebar.campaign.report.export` |
| GET | `/hidden/link/campaign/report/{campaign_no}/{token}` | `admin.hidden.link.campaign.report` |
| GET | `/hidden/link/campaign/report/{campaign_no}/{token}/export` | `admin.hidden.link.campaign.report.export` |
| GET | `/schedule/campaign/report/{campaign_no}/{token}` | `admin.schedule.campaign.report` |
| GET | `/schedule/campaign/report/{campaign_no}/{token}/export` | `admin.schedule.campaign.report.export` |
| GET | `/schedule/sidebar/campaign/report/{campaign_no}/{token}` | `admin.schedule.sidebar.campaign.report` |
| GET | `/schedule/sidebar/campaign/report/{campaign_no}/{token}/export` | `admin.schedule.sidebar.campaign.report.export` |
| GET | `/campaign/post/wp-schedule/report/{campaign_no}/{token}` | `admin.wp.schedule.campaign.report` |
| GET | `/campaign/post/wp-schedule/report/{campaign_no}/{token}/export` | `admin.wp.schedule.campaign.report.export` |
| GET | `/client/billing/{id}/{token}` | `admin.local-client.billing.report` |
| GET | `/client/billing/{id}/{token}/export` | `admin.local-client.billing.report.export` |

### 5.2 Admin guest (`/admin`, `admin.guest`)

| Method | Path | Name | Action |
|--------|------|------|--------|
| GET | `/admin/login` | `admin.login` | Show login |
| POST | `/admin/login/post` | `admin.loggedin` | Submit credentials |
| GET | `/admin/otp` | `admin.otp` | Show OTP |
| POST | `/admin/otp/post` | `admin.verify.otp` | Verify OTP |
| GET | `/admin/forgotpassword` | `admin.forgot` | Forgot password form |
| POST | `/admin/forgot-password/post` | `admin.forgot.post` | Send reset email |
| GET | `/admin/reset-password/{token}` | `admin.reset.form` | Reset form |
| POST | `/admin/reset-password` | `admin.reset` | Save new password |

### 5.3 Admin authenticated (`/admin`, `admin.auth`)

Laravel **resource** routes (`index`, `create`, `store`, `show`, `edit`, `update`, `destroy`) exist for:

| Prefix | Name prefix | Controller |
|--------|-------------|------------|
| `/admin/user` | `admin.user` | `AdminController` |
| `/admin/domain/category` | `admin.domain.category` | `DomainCategoryController` |
| `/admin/domain` | `admin.domain` | `DomainController` |
| `/admin/webhook-secrets` | `admin.webhook-secrets` | `WebhookSecretController` (no `show`) |
| `/admin/pending-domains` | `admin.pending-domains` | `PendingDomainController` (`index`, `show`, `destroy`) |
| `/admin/domains/set` | `admin.set` | `DomainSetController` |
| `/admin/article/category` | `admin.articles.category` | `ArticleCategoryController` |
| `/admin/article/language` | `admin.articles.language` | `ArticleLanguageController` |
| `/admin/article` | `admin.article` | `ArticleController` |
| `/admin/articles/set` | `admin.articles.set` | `ArticleSetController` |
| `/admin/campaign` | `admin.campaign` | `CampaignController` |
| `/admin/sidebar/campaign` | `admin.sidebar.campaign` | `SidebarCampaignController` |
| `/admin/hidden/link/campaign` | `admin.hidden.link.campaign` | `HiddenLinkCampaignController` |
| `/admin/campaign/post/schedule` | `admin.schedule.campaign` | `ScheduleCampaignController` |
| `/admin/campaign/post/wp-schedule` | `admin.wp.schedule.campaign` | `WpScheduledCampaignController` |
| `/admin/campaign/sidebar/schedule` | `admin.schedule.sidebar.campaign` | `ScheduleSidebarCampaignController` |
| `/admin/local-clients` | `admin.local-clients` | `LocalClientController` (permission) |
| `/admin/local-client-rate-lists` | `admin.local-client-rate-lists` | `LocalClientRateListController` |

#### Dashboard, profile, users

| Method | Path | Name |
|--------|------|------|
| POST | `/admin/admin/logout` | `admin.logout` |
| GET | `/admin/` | `admin.dashboard` |
| GET | `/admin/dashboard/campaigns` | `admin.dashboard.campaigns` |
| GET | `/admin/profile` | `admin.profile` |
| GET | `/admin/profile/{slug}` | `admin.profile.show` |
| PUT | `/admin/profile/update` | `admin.profile.update` |
| GET/POST | `/admin/reports/find-campaign` | lookup campaign by number or keyword/URL |

#### Domains (extra)

| Method | Path | Name | Purpose |
|--------|------|------|---------|
| GET | `/admin/domain/select/category` | `admin.select.category` | Category picker |
| POST | `/admin/domain/redirect/list` | `admin.redirect.to.list` | Jump to list |
| GET | `/admin/domain/extract` | `admin.domain.extract` | Export domains by category |
| GET/POST | `/admin/domain/move-category` | `admin.domain.move-category*` | Move domains |
| POST | `/admin/domain/delete` | `admin.domain.delete` | Bulk delete |
| POST | `/admin/domain/category/delete` | `admin.domain.category.delete` | Bulk delete categories |
| GET/POST | `/admin/domain/status-checker*` | `admin.domain.status-checker*` | Queued health check + progress |
| GET/POST | `/admin/domain/recheck-disconnected*` | `admin.domain.recheck-disconnected*` | Recheck down sites, export, cancel |

#### Plugin manager (`/admin/plugin-manager`, campaign-creator only)

| Method | Path | Name |
|--------|------|------|
| GET | `/admin/plugin-manager` | `admin.plugin-manager.index` |
| POST | `/admin/plugin-manager/packages` | `admin.plugin-manager.packages.store` |
| DELETE | `/admin/plugin-manager/packages/{uuid}` | `admin.plugin-manager.packages.destroy` |
| GET | `/admin/plugin-manager/packages/{uuid}/download` | `admin.plugin-manager.packages.download` |
| GET/POST | `/admin/plugin-manager/deploy` | create / start deployment |
| GET | `/admin/plugin-manager/deployments` | list |
| GET | `/admin/plugin-manager/deployments/{uuid}` | show |
| GET | `/admin/plugin-manager/deployments/{uuid}/progress` | JSON progress |
| POST | `/admin/plugin-manager/deployments/{uuid}/retry-failed` | retry |
| POST | `/admin/plugin-manager/deployments/{uuid}/cancel` | cancel |
| DELETE | `/admin/plugin-manager/deployments/{uuid}` | destroy |
| DELETE | `/admin/plugin-manager/deployments/bulk` | bulk destroy |
| DELETE | `/admin/plugin-manager/deployments/clear` | clear history |
| GET | `/admin/plugin-manager/deployments/export-history` | CSV history |
| GET | `/admin/plugin-manager/deployments/{uuid}/export-failures` | CSV failures |

#### Pending domains, transfers, webhooks

| Method | Path | Name |
|--------|------|------|
| POST | `/admin/pending-domains/sync-existing` | sync inventory |
| POST | `/admin/pending-domains/{pendingDomain}/sync-inventory` | sync one |
| POST | `/admin/pending-domains/{pendingDomain}/reject` | reject |
| POST | `/admin/pending-domains/bulk-reject` | bulk reject |
| GET | `/admin/transfer-domains/step1` | wizard step 1 |
| GET/POST | `/admin/transfer-domains/step2` | wizard step 2 |
| POST | `/admin/transfer-domains/create-category` | create category |
| POST | `/admin/transfer-domains/process` | transfer into inventory |
| POST | `/admin/webhook-secrets/rotation-settings` | rotation interval |
| POST | `/admin/webhook-secrets/{webhookSecret}/regenerate` | new secret |

#### Articles (extra)

| Method | Path | Name |
|--------|------|------|
| GET | `/admin/article/opt` | `admin.articles.opt` |
| GET | `/admin/article/upload/docx` | `admin.articles.upload.docx` |
| POST | `/admin/article/import` | `admin.articles.import` |
| POST | `/admin/article/delete` | `admin.articles.delete` |
| GET | `/admin/article/trashed` | trashed list |
| DELETE | `/admin/article/trashed/{id}` | force delete one |
| POST | `/admin/article/trashed/force-delete` | bulk force delete |
| POST | `/admin/article/trashed/queue-purge-all-used` | queued purge all used |
| POST | `/admin/article/trashed/queue-purge-by-quantity` | queued purge N |
| GET/POST | `/admin/article/restore-used*` | restore used articles (single/bulk/queued) |
| GET | `/admin/article/set/options` | set create options |
| POST | `/admin/article/set/add/` | add articles to set |
| POST | `/admin/article/set/import/` | import into set |
| DELETE | `/admin/article/set/destroy/{id}` | remove article from set |
| POST | `/admin/article/set/bulk/delete` | bulk remove from set |
| POST | `/admin/article/category/delete` | bulk delete categories |
| POST | `/admin/domains/set/delete` | bulk delete domain sets |

#### Live campaigns — extra actions

**PBN Post** (`CampaignController`):

| Method | Path | Name |
|--------|------|------|
| POST | `/admin/campaign/retry/{id}` | retry campaign |
| GET | `/admin/campaign/editpost/{id}` | edit one post |
| POST | `/admin/campaign/updatecampaignpost/{id}` | update post |
| POST | `/admin/campaign/deleteCampaignPost/{id}` | delete remote post |
| POST | `/admin/campaign/bulk/update/{id}` | bulk keyword/URL update |
| POST | `/admin/campaign/multi-keywords/{id}` | multi-level keyword update |
| POST | `/admin/campaign/update-post-keywords/{id}` | update one post keywords |
| POST | `/admin/campaign/{id}/purge-local` | local-only delete |
| POST | `/admin/campaign/bulk-purge-local` | bulk local purge |
| POST | `/admin/campaign/bulk-retry-failed` | retry all failed |

**Sidebar / Hidden link** — same pattern: retry task, edit/update/delete task, bulk delete tasks, purge local, bulk retry.

**Schedule post** extra: `publish-remaining` (publish leftover queued posts now).

**WP scheduled** extra: `run`, `sync` campaign, `sync-post`.

**Sticky**:

| Method | Path | Name |
|--------|------|------|
| GET | `/admin/sticky/campaign/` | `admin.sticky.campaign.index` |
| GET | `/admin/sticky/campaign/create` | `admin.sticky.campaign.create` |
| GET | `/admin/campaign/post/schedule-sticky` | sticky schedule index |
| GET | `/admin/campaign/post/schedule-sticky/create` | sticky schedule create |

#### Domain replacement (campaign-creator only)

Single-task and bulk replace for: live posts, sidebar, hidden links, schedule posts, schedule sidebar.

Examples:

- `GET/POST /admin/campaign/posts/{post}/replace-domain`
- `GET/POST /admin/{campaign}/bulk-replace-domains`
- `GET/POST /admin/campaign/sidebar/tasks/{task}/replace-domain`
- same pattern for hidden links and schedule types

#### Live → dripfeed conversion

**Posts** (`/admin/convert/post`): wizard steps 1–4, search, lookup by report URL, eligibility, preflight, store, converted list, conversion status, retry post, bulk retry.

**Sidebar** (`/admin/convert/sidebar`): same wizard + retry task.

#### Billing / invoices

| Method | Path | Name |
|--------|------|------|
| GET | `/admin/local-clients/{localClient}/estimate` | quote estimate |
| PATCH | `/admin/local-clients/payment/{billableType}/{id}` | mark payment |
| PATCH | `/admin/local-clients/client/{billableType}/{id}` | assign client |
| POST | `/admin/local-clients/billing-sync/{billableType}/{id}` | resync billing |
| GET | `/admin/local-clients/campaign-invoice/{billableType}/{id}` | campaign invoice |
| POST | `/admin/local-clients/{localClient}/toggle-active` | activate/deactivate |
| POST | `/admin/local-clients/{localClient}/mark-period-paid` | mark period paid |
| DELETE | `/admin/local-clients/{localClient}/billing-periods/{billingPeriod}` | delete period |
| POST | `/admin/local-clients/{localClient}/regenerate-token` | new report token |
| GET | `/admin/local-clients/{localClient}/price-matrix` | JSON prices |
| GET | `/admin/invoice/generator` | invoice UI |
| POST | `/admin/invoice/generate-pdf` | generate PDF |

### 5.4 JSON API (`/api`, `routes/api.php`)

| Method | Path | Name | Auth | Purpose |
|--------|------|------|------|---------|
| POST | `/api/webhook/domains` | `api.webhook.domains` | webhook secret | WordPress submits a pending domain (`domain_name`, `api_key`, `secret`). Throttled 120/min. |
| POST | `/api/admin/domain/category/{id}` | `admin.api.domain.category.update` | `admin.api.auth` | Update category via AJAX |
| POST | `/api/admin/domain/bulk/add` | `admin.api.domain.bulk.upload` | session | Bulk add domains |
| POST | `/api/admin/domain/set/initialize` | `admin.api.domain.set.initialize` | session | Build a domain set |
| GET | `/api/admin/domain/set/fetch/{id}` | `admin.api.fetch.set.domains` | session | List set domains |
| GET | `/api/admin/domains/{id}` | `admin.api.domains.list` | session | Domains in a category |
| POST | `/api/admin/domains/validate` | `admin.api.validate.domains` | session | Validate domain list |
| POST | `/api/admin/article/set/initialize` | `admin.api.article.set.initialize` | session | Build an article set |
| GET | `/api/admin/set/articles/{id}` | `admin.api.set.article` | session | Articles in a set |
| POST | `/api/admin/article/search` | `admin.api.articles.search` | session | Search unused articles |
| POST | `/api/admin/article/by-language` | `admin.api.articles.by.language` | session | Filter by language |

---

## 6. Controllers

All admin UI lives under `app/Http/Controllers/Admin/`. AJAX helpers under `app/Http/Controllers/Api/`.

### Auth & users

| Controller | Main methods | Role |
|------------|--------------|------|
| `AdminAuthenticatorController` | `show`, `login`, `logout` | Login |
| `AdminOtpController` | `show`, `verifyOtp` | 6-digit OTP |
| `AdminPasswordResetController` | `show`, `sendResetLink`, `showResetForm`, `resetPassword` | Email reset |
| `AdminController` | resource CRUD | Admin users |
| `ProfileController` | `index`, `show`, `update` | Profile + password |
| `DashboardController` | `index`, `getCampaignsByType` | Stats / charts |

### Inventory

| Controller | Main methods |
|------------|--------------|
| `DomainController` | resource + `selectDomainCategory`, `redirect__func`, `delete`, `extractByCategory`, `moveCategoryForm`, `processMoveCategory` |
| `DomainCategoryController` | resource + bulk `delete` |
| `DomainSetController` | resource + bulk `delete` |
| `DomainStatusCheckerController` | `index`, `start`, `progress` |
| `DomainRecheckDisconnectedController` | `index`, `start`, `progress`, `cancel`, `export` |
| `PendingDomainController` | `index`, `show`, `destroy`, sync/reject |
| `TransferDomainController` | `step1`, `step2`, `createCategory`, `process` |
| `WebhookSecretController` | resource + `regenerate`, `updateRotationSettings` |
| `PluginPackageController` | `index`, `store`, `destroy`, `adminDownload`, `download` |
| `PluginDeploymentController` | deploy lifecycle (start, progress, retry, cancel, export) |

### Articles

| Controller | Main methods |
|------------|--------------|
| `ArticleController` | resource, DOCX `import`, trash/restore/purge queues |
| `ArticleCategoryController` | resource + bulk `delete` |
| `ArticleLanguageController` | resource |
| `ArticleSetController` | resource, `option`, `createArticles`, `import`, `articleDestroy`, `deleteSetArticles` |

### Campaigns

| Controller | Extra (beyond resource) |
|------------|-------------------------|
| `CampaignController` | `report`, `exportReport`, `retry`, edit/update/delete post, bulk/multi keyword, purge, bulk retry |
| `SidebarCampaignController` | report/export, retry/edit/update/delete task, extract domains, purge, bulk retry |
| `HiddenLinkCampaignController` | report/export, task CRUD, bulk campaign delete, purge, bulk retry |
| `ScheduleCampaignController` | sticky index/create, publish remaining, post-level edit/retry/delete, multi-keyword |
| `ScheduleSidebarCampaignController` | report/export, bulk update, task retry/edit/update/delete |
| `WpScheduledCampaignController` | `run`, `syncCampaign`, `syncPost`, bulk/multi-keyword |
| `StickyPostCampaignController` | `index`, `create` (uses post campaign store) |
| `CampaignReportLookupController` | `index`, `find`, `findByKeywordUrl` |
| `CampaignDomainReplacementController` | `create`, `store` |
| `CampaignBulkDomainReplacementController` | `create`, `store` |
| `LiveTaskDomainReplacementController` | per-type create/store |
| `LiveTaskBulkDomainReplacementController` | per-type create/store |
| `CampaignPostConversionController` | 4-step wizard + preflight + retry |
| `SidebarCampaignConversionController` | same for sidebar |

### Billing

| Controller | Main methods |
|------------|--------------|
| `LocalClientController` | resource + estimate, toggle, token, periods, price matrix |
| `LocalClientRateListController` | resource except `show` |
| `LocalClientPaymentController` | `update`, `updateClient`, `syncBilling`, `invoice` |
| `LocalClientBillingReportController` | public `show`, `export` |
| `InvoiceController` | `create`, `generatePdf` |

### API

| Controller | Methods |
|------------|---------|
| `Api\DomainWebhookController` | `receiveDomain` |
| `Api\admin\DomainController` | `bulk_upload`, `domains`, `validateDomains` |
| `Api\admin\DomainCategoryController` | `update` |
| `Api\admin\DomainSetController` | `initialize`, `setDomains` |
| `Api\admin\ArticleController` | `search`, `getByLanguage` |
| `Api\admin\ArticleSetController` | `initialize`, `SetArticles` |

Shared traits in `app/Http/Controllers/Admin/Concerns/`:

- `AuthorizesAdminCampaign` — owner vs Super Admin
- `AppliesSuperAdminCampaignOwnerFilter`
- `AppliesCampaignListStatusFilter`
- `ValidatesBulkCampaignIds`
- `ProvidesLocalClientsForForms`

---

## 7. Remote WordPress API (plugin endpoints)

Called by jobs/services, **not** Laravel routes. Auth: header `X-External-API-Key` or `api_key` query/body.

Base: `/wp-json/external/v1/`

| Method | Endpoint | Used for |
|--------|----------|----------|
| GET | `/status` | Health (no auth) |
| POST | `/posts/create` | Publish / sticky / WP-schedule |
| POST | `/posts/update/{id}` | Bulk keyword updates |
| DELETE | `/posts/delete/{id}` | Delete remote post |
| GET | `/posts`, `/posts/{id}` | List / fetch |
| POST | `/posts/sticky/{id}`, `/posts/unsticky/{id}` | Sticky |
| POST | `/schedule/add` | Queue-based remote schedule |
| GET | `/blogroll` | Fetch sidebar items |
| POST | `/blogroll/add` | Add sidebar link |
| POST | `/blogroll/update/{id}` | Update by **remote id** (not array index) |
| DELETE | `/blogroll/delete/{id}` | Delete sidebar item |
| GET/POST/DELETE | `/hidden-links` (+ `/add`, `/update/{id}`, `/delete/{id}`) | Hidden links |

Remote ids look like `blog_678abc…` / `hid_678abc…`. Laravel services: `BlogrollApiService`, `HiddenLinksApiService`, `PostStatusApiService`, `RemotePostUpdateService`.

---

## 8. Models (`app/Models`)

### Core

| Model | Table | Notes |
|-------|-------|--------|
| `Admin` | `admins` | Guard `admin` |
| `Roles` | `roles` | Super Admin / Admin / Member |
| `AdminOtp` | OTPs | 5-minute expiry |
| `AdminPasswordReset` | reset tokens | |
| `Admin\AdminFeaturePermission` | feature flags | e.g. `local_clients.manage` |

### Domains / plugins / webhooks

`Domain`, `DomainCategory`, `DomainSet`, `PendingDomain`, `DomainStatusCheck`, `DomainStatusCheckItem`, `WebhookSecret`, `WebhookRotationSetting`, `PluginPackage`, `PluginDeployment`, `PluginDeploymentItem`

### Articles

`Article` (soft deletes), `ArticleCategory`, `ArticleLanguage`, `ArticleSet`

### Campaigns (each type has campaign + domains + articles/links + tasks/posts)

| Type | Campaign | Tasks |
|------|----------|-------|
| Post / sticky | `Campaign` | `CampaignPost` |
| Sidebar | `SidebarCampaign` | `SidebarCampaignTask` |
| Hidden | `HiddenLinksCampaign` | `HiddenLinksCampaignTasks` |
| Schedule post | `ScheduleCampaign` | `ScheduleCampaignPost` |
| Schedule sidebar | `ScheduleSidebarCampaign` | `ScheduleSidebarCampaignTask` |
| WP scheduled | `WpScheduledCampaign` | `WpScheduledCampaignPost` |

Related: `*Article`, `*Domain`, `*Link`, `*Date`, `*DomainReplacement`.

### Billing

`LocalClient`, `LocalClientRateList`, `LocalClientRateListPrice`, `LocalClientDomainCategoryPrice`, `LocalClientBillingPeriod`, `LocalClientBillLine`, `LocalClientPaymentEvent`, `BillingCampaignType`

---

## 9. Services (`app/Services`)

Business logic used by controllers and jobs.

| Service | Responsibility |
|---------|----------------|
| `CampaignPostContentBuilder` | Injects keyword anchors into article HTML; RTL wrap; `mb_*` for CJK/Arabic |
| `WpScheduledPostContentBuilder` | Same for WP-scheduled posts |
| `Utf8SanitizerService` | Repair UTF-8 before `json_encode` / WP posts |
| `BlogrollApiService` | Sidebar HTTP API |
| `HiddenLinksApiService` | Hidden-link HTTP API |
| `PostStatusApiService` / `BlogrollStatusApiService` | Remote status |
| `RemotePostUpdateService` | Update published posts |
| `CampaignArticleReservationService` | Reserve unused articles at campaign create |
| `CampaignKeywordPairValidator` | Keyword/URL validation |
| `CampaignDomainReplacementService` | Swap a live post’s domain |
| `CampaignBulkDomainReplacementService` | Bulk domain swap |
| `LiveTaskDomainReplacement/*` | Sidebar/hidden/schedule replacements |
| `CampaignLiveToDripfeedConversionService` | Live post → schedule campaign |
| `SidebarLiveToDripfeedConversionService` | Live sidebar → schedule sidebar |
| `CampaignConversionEligibilityService` / `PreflightService` | Can this campaign convert? |
| `ConvertedPostRemoteSyncService` | Sync dripfeed remote status |
| `DomainStatusCheckerService` | Probe domains |
| `PendingDomainTransferService` | Webhook → inventory |
| `PluginPackageService` / `PluginDeploymentService` | ZIP + remote install |
| `RemotePluginManagerService` | Talk to WP plugin manager |
| `WebhookSecretRotationService` | Rotate inbound secrets |
| `LocalClientBillingService` (+ period, report, invoice, payment, price matrix) | Client billing |
| `PurgeLocalCampaignDataService` | Delete local rows without touching WP |
| `CredentialBlindIndex` | Hash lookup for secrets/API keys |

---

## 10. Queue jobs (`app/Jobs`)

Jobs use **database locking** (`lockForUpdate`, `lock_token`) so two workers cannot publish the same task.

| Job | Queue | Does |
|-----|-------|------|
| `PublishCampaignPostJob` | `campaigns` | POST `/posts/create` |
| `BulkUpdateCampaignPostsJob` | `bulk_updates` | Update remote post content |
| `DeleteCampaignJob` | `deletions` | Delete remote posts + local campaign |
| `BulkRetryCampaignPostsJob` | `bulk_retry` | Re-queue failed posts |
| `PublishSidebarBlogrollJob` | `sidebar_campaigns` | Add blogroll item |
| `BulkUpdateSidebarBlogrollJob` | `bulk_blogroll_updates` | Update blogroll |
| `DeleteSidebarCampaignJob` | `sidebar_deletions` | Delete remote + local |
| `PublishHiddenLinksJob` | `hidden_links_campaigns` | Add hidden link |
| `BulkUpdateHiddenLinksJob` | `update_hidden_links` | Update hidden links |
| `BulkDeleteHiddenLinksJob` | `delete_hidden_links` | Delete selected tasks |
| `BulkDeleteHiddenLinkCampaignsJob` | `delete_hidden_links_campaign` | Delete whole campaigns |
| `PublishScheduledCampaignPostJob` | `scheduled_campaigns` | Dripfeed post |
| `PublishRemainingScheduleCampaignPostsJob` | `bulk_retry_scheduled_campaigns` | Publish leftover now |
| `BulkUpdateScheduleCampaignPostsJob` | `schedule_campaign_bulk_updates` | |
| `DeleteScheduleCampaignJob` | `schedule_campaign_deletions` | |
| `PublishScheduledSidebarBlogrollJob` | `scheduled_sidebar_campaigns` | |
| `BulkUpdateScheduleSidebarBlogrollJob` | `schedule_sidebar_bulk_updates` | |
| `DeleteScheduleSidebarCampaignJob` | `schedule_sidebar_deletions` | |
| `PublishWpScheduledPostJob` | `wp_scheduled_campaigns` | Create with `schedule_time` |
| `SyncWpScheduledPostStatusJob` | `wp_scheduled_sync` | future → publish |
| `BulkUpdateWpScheduledPostsJob` | `wp_scheduled_campaign_bulk_updates` | |
| `DeleteWpScheduledCampaignJob` | `wp_scheduled_campaign_deletions` | |
| `DraftConvertedLivePostsJob` / `ApplyConvertedPostScheduleJob` | `campaign_conversions` | Live→dripfeed |
| `DraftConvertedLiveSidebarTasksJob` / `ApplyConvertedSidebarScheduleJob` | `sidebar_campaign_conversions` | |
| `SyncConvertedCampaignRemoteStatusJob` | `campaign_conversions` | |
| `CheckDomainStatus` / `ProcessDomainStatusCheckChunkJob` | `domainCheck` | Probe sites |
| `RefreshTransferredDomainsStatusJob` | `domainHealthSync` | After transfer |
| `ProcessPluginDeploymentChunkJob` | `plugin_deployments` | Push plugin ZIP |
| `RefreshWebhookSecretsJob` | `webhook-secret` | Rotate secrets |
| `RestoreTrashedUsedArticlesJob` | `article_restore` | |
| `PermanentlyDeleteTrashedUsedArticlesJob` | `article_permanent_purge` | |
| `CleanupReplacedDomainRemoteContentJob` | `deletions` | Remove old domain’s content after replace |

Production workers must listen to **all** of those queues. The `composer run dev` script already includes the full list.

---

## 11. Artisan commands (`app/Console/Commands`)

| Command | Purpose |
|---------|---------|
| `domains:health-check` | Periodic inventory health (every 15 min) |
| `domains:probe` | Manual probe |
| `domain-status-checks:prune` | Delete old check runs |
| `domain-status-checks:cancel-stuck` | Unlock hung checks |
| `schedule-campaigns:carry-failed-posts` | Move failed dripfeed posts to next day |
| `schedule-campaigns:sync-counters` | Recalc schedule campaign stats |
| `schedule-sidebar-campaigns:sync-counters` | Recalc sidebar schedule stats |
| `wp-scheduled:sync-status` | Sync WP future/publish |
| `wp-scheduled:resolve-permalinks` | Fill missing remote URLs |
| `convert:reconcile-remote-status` | Fix converted dripfeed statuses |
| `credentials:encrypt` | Encrypt stored API keys |
| `test:utf8-sanitization` | UTF-8 self-test |
| `invoice:test` | Sample invoice PDF |
| `debug:sidebar-rel {task_id?}` | Debug rel attributes |

---

## 12. Scheduler (`routes/console.php`)

Runs when `php artisan schedule:run` is on cron (every minute in production):

- Every minute: dispatch due schedule posts/sidebar tasks; apply converted dripfeed slots
- Every 5 minutes: sync converted remote status
- Every 15 minutes: `domains:health-check`
- Hourly: `RefreshWebhookSecretsJob`
- Daily 00:15: carry failed schedule posts
- Daily 03:05: clear scheduler cache locks
- Daily 03:15: prune domain status checks

---

## 13. Global helpers (`app/helpers.php`)

| Function | Purpose |
|----------|---------|
| `cleanUtf8($text, $options)` | Sanitize before WP/JSON |
| `safeJsonEncode($data)` | JSON encode that won’t die on bad UTF-8 |
| `isValidUtf8($text)` | Validation |
| `isRtlText($text)` | Arabic/Persian/Hebrew/Urdu (>30% RTL chars) |
| `wrapRtlContent($html, $title)` | Wrap with `dir="rtl"` |
| `normalizeDomainName($input)` | `https://Example.com/` → `example.com` |
| `toDomainUrl($input)` | Bare host → `https://…` |
| `extractDomainExtension($domain)` | TLD |
| `parseIniSizeBytes($value)` | `8M` → bytes |
| `pluginManagerUploadLimits()` | ZIP size vs PHP ini |

---

## 14. Directory map

```
app/
  Console/Commands/          Artisan CLI
  Http/Controllers/Admin/    Admin UI + campaign HTTP
  Http/Controllers/Api/      Webhook + AJAX APIs
  Http/Middleware/Admin/     Auth, roles, permissions
  Jobs/                      Queue workers
  Models/                    Eloquent
  Services/                  Domain logic + WP HTTP
  helpers.php                Global UTF-8 / domain helpers
routes/
  web.php                    Home + includes admin.php
  admin.php                  Almost all UI + public reports
  api.php                    /api webhook + admin AJAX
  console.php                Scheduler
resources/views/admin/       Blade UI
public/js/create-campaign.js Campaign builder (keywords, rel checkboxes)
```

---

## 15. Related docs in this repo

| File | Contents |
|------|----------|
| [`README.md`](README.md) | Setup, queues, high-level features |
| [`CLAUDE.md`](CLAUDE.md) | Architecture notes for contributors |
| [`API-DATA-FLOW.md`](API-DATA-FLOW.md) | Full WordPress plugin API |
| [`POST_STATUS_AND_DRIPFEED_API.md`](POST_STATUS_AND_DRIPFEED_API.md) | Post status + dripfeed details |
| [`EXTERNAL_API_MANAGER_FULL_REFERENCE.md`](EXTERNAL_API_MANAGER_FULL_REFERENCE.md) | Plugin reference |
| [`deploy/supervisor/README.md`](deploy/supervisor/README.md) | Production queue workers |

---

## 16. Status vocabularies

**Campaign:** `queued` → `running` → `completed` | `semi_failed` | `failed` | `paused` | `cancelled`

**Task/post:** `queued` → `publishing` → `success` | `failed`

**Article:** `unused` until publish succeeds, then **soft-deleted**; snapshots remain on `campaign_articles`.
