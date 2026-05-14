# PBN Automation Software - Developer Quick Reference

**Purpose:** Fast lookup guide for developers working on this codebase  
**Last Updated:** 2026-04-29

---

## 📁 PROJECT STRUCTURE

```
pbn_automation_software/
├── app/
│   ├── Http/Controllers/Admin/     # All admin controllers
│   │   ├── campaignController.php  # PBN post campaigns
│   │   ├── ScheduleCampaignController.php  # Scheduled posts
│   │   ├── SidebarCampaignController.php   # Blogroll campaigns
│   │   ├── HiddenLinkCampaignController.php # Hidden links
│   │   ├── DomainController.php    # Domain management
│   │   ├── ArticleController.php   # Article library
│   │   └── DashboardController.php # Dashboard & stats
│   ├── Models/Admin/               # All models
│   ├── Jobs/                       # Background jobs
│   ├── Services/                   # Business logic services
│   └── Exceptions/                 # Custom exceptions
├── resources/views/admin/          # Blade templates
├── public/js/                      # Frontend JavaScript
├── database/migrations/            # Database migrations
└── routes/
    ├── web.php                     # Public routes
    └── admin.php                   # Admin routes
```

---

## 🗺️ FEATURE-TO-FILE MAPPING

### Campaign Management (PBN Posts)

**Create Campaign:**
- Controller: `app/Http/Controllers/Admin/campaignController.php::store()`
- View: `resources/views/admin/campaigns/pbn-post/create-campaign.blade.php`
- JS: `public/js/create-campaign.js`
- Job: `app/Jobs/PublishCampaignPostJob.php`
- Service: `app/Services/CampaignPostContentBuilder.php`

**Edit Campaign:**
- Controller: `campaignController.php::edit()`, `update()`, `bulkUpdateCampaignPosts()`
- View: `resources/views/admin/campaigns/pbn-post/edit-campaign.blade.php`
- Job: `app/Jobs/BulkUpdateCampaignPostsJob.php`

**Campaign Reports:**
- Controller: `campaignController.php::report()`, `exportReport()`
- View: `resources/views/admin/campaigns/pbn-post/campaign-report.blade.php`

**Models:**
- `app/Models/Admin/Campaign.php`
- `app/Models/Admin/CampaignArticle.php`
- `app/Models/Admin/CampaignDomain.php`
- `app/Models/Admin/CampaignPost.php`

---

### Scheduled Campaigns (Drip Feed)

**Create Scheduled Campaign:**
- Controller: `app/Http/Controllers/Admin/ScheduleCampaignController.php::store()`
- View: `resources/views/admin/campaigns/pbn-post/create-schedule-campaign.blade.php`
- JS: `public/js/create-schedule-campaign.js`
- Job: `app/Jobs/PublishScheduledCampaignPostJob.php`

**Models:**
- `app/Models/Admin/ScheduleCampaign.php`
- `app/Models/Admin/ScheduleCampaignArticle.php`
- `app/Models/Admin/ScheduleCampaignDomain.php`
- `app/Models/Admin/ScheduleCampaignPost.php`
- `app/Models/Admin/ScheduleCampaignDate.php`

---

### Sidebar/Blogroll Campaigns

**Create Sidebar Campaign:**
- Controller: `app/Http/Controllers/Admin/SidebarCampaignController.php::store()`
- View: `resources/views/admin/campaigns/pbn-sidebar/create-sidebar-campaign.blade.php`
- JS: `public/js/create-sidebar-campaign.js`
- Job: `app/Jobs/PublishSidebarBlogrollJob.php`
- Service: `app/Services/BlogrollApiService.php`

**Models:**
- `app/Models/Admin/SidebarCampaign.php`
- `app/Models/Admin/SidebarCampaignDomain.php`
- `app/Models/Admin/SidebarCampaignLink.php`
- `app/Models/Admin/SidebarCampaignTask.php`

---

### Hidden Links Campaigns

**Create Hidden Links Campaign:**
- Controller: `app/Http/Controllers/Admin/HiddenLinkCampaignController.php::store()`
- View: `resources/views/admin/campaigns/pbn-hidden-links/create-hidden-links-campaign.blade.php`
- JS: `public/js/create-hidden-links-campaign.js`
- Job: `app/Jobs/PublishHiddenLinksJob.php`
- Service: `app/Services/HiddenLinksApiService.php`

**Models:**
- `app/Models/Admin/HiddenLinksCampaign.php`
- `app/Models/Admin/HiddenLinksCampaignDomains.php`
- `app/Models/Admin/HiddenLinksCampaignLinks.php`
- `app/Models/Admin/HiddenLinksCampaignTasks.php`

---

### Domain Management

**Files:**
- Controller: `app/Http/Controllers/Admin/DomainController.php`
- Models: `app/Models/Admin/Domain.php`, `DomainCategory.php`, `DomainSet.php`
- Views: `resources/views/admin/domains/*.blade.php`

**Key Methods:**
- `store()` - Add new domain
- `update()` - Edit domain
- `destroy()` - Delete domain
- `extractByCategory()` - Export domains to Excel

---

### Article Management

**Files:**
- Controller: `app/Http/Controllers/Admin/ArticleController.php`
- Models: `app/Models/Admin/Article.php`, `ArticleCategory.php`, `ArticleSet.php`, `ArticleLanguage.php`
- Views: `resources/views/admin/articles/*.blade.php`

**Key Concepts:**
- Articles are locked when used in campaigns (`lock_at` field)
- Articles are soft-deleted after publishing
- Article snapshots stored in campaign_articles table

---

## 🔧 COMMON TASKS

### How to Add Authorization Check

```php
// In controller method
public function destroy(string $id)
{
    $campaign = Campaign::findOrFail($id);
    
    // Add this check
    $admin = Auth::guard('admin')->user();
    if (!$admin->isSuperAdmin() && $campaign->admin_id !== $admin->id) {
        abort(403, 'Unauthorized action.');
    }
    
    // Rest of method...
}
```

### How to Fix N+1 Query

```php
// BEFORE (N+1)
$campaigns = Campaign::all();
foreach ($campaigns as $campaign) {
    echo $campaign->domainCategory->name; // Extra query!
}

// AFTER (Optimized)
$campaigns = Campaign::with('domainCategory')->all();
foreach ($campaigns as $campaign) {
    echo $campaign->domainCategory->name; // No extra query
}
```

### How to Add Database Index

```php
// Create migration: php artisan make:migration add_indexes_to_campaigns_table

public function up()
{
    Schema::table('campaigns', function (Blueprint $table) {
        $table->index('campaign_no');
        $table->index('admin_id');
        $table->index(['admin_id', 'status']); // Composite index
    });
}

public function down()
{
    Schema::table('campaigns', function (Blueprint $table) {
        $table->dropIndex(['campaign_no']);
        $table->dropIndex(['admin_id']);
        $table->dropIndex(['admin_id', 'status']);
    });
}
```

### How to Dispatch Background Job

```php
// Dispatch immediately
PublishCampaignPostJob::dispatch($postId)->onQueue('campaigns');

// Dispatch with delay
PublishCampaignPostJob::dispatch($postId)
    ->onQueue('campaigns')
    ->delay(now()->addMinutes(5));

// Dispatch multiple jobs
foreach ($postIds as $postId) {
    PublishCampaignPostJob::dispatch($postId)->onQueue('campaigns');
}
```

### How to Add Validation

```php
// In controller
$validated = $request->validate([
    'campaign_no' => 'required|string|max:191',
    'post_quantity' => 'required|integer|min:1|max:1000',
    'domain_category' => 'nullable|integer|exists:domain_categories,id',
]);

// Or create FormRequest
php artisan make:request StoreCampaignRequest
```

---

## 🔌 WORDPRESS API ENDPOINTS

### Post Management
```
POST   /wp-json/external/v1/posts/create
GET    /wp-json/external/v1/posts/{id}
POST   /wp-json/external/v1/posts/update/{id}
DELETE /wp-json/external/v1/posts/delete/{id}
GET    /wp-json/external/v1/status
```

### Blogroll Management
```
GET    /wp-json/external/v1/blogroll
POST   /wp-json/external/v1/blogroll/add
PATCH  /wp-json/external/v1/blogroll/update/{id}
POST   /wp-json/external/v1/blogroll/update/{id}
DELETE /wp-json/external/v1/blogroll/delete/{id}
```

### Hidden Links Management
```
GET    /wp-json/external/v1/hidden-links
POST   /wp-json/external/v1/hidden-links/add
POST   /wp-json/external/v1/hidden-links/update/{id}
DELETE /wp-json/external/v1/hidden-links/delete/{id}
```

**Authentication:** All endpoints require `api_key` parameter or `X-External-API-Key` header

---

## 🎯 QUEUE NAMES

```php
'campaigns'                    // Regular PBN post campaigns
'sidebar_campaigns'            // Sidebar/blogroll campaigns
'hidden_links_campaigns'       // Hidden links campaigns
'scheduled_campaigns'          // Scheduled post campaigns
'bulk_updates'                 // Bulk keyword/URL updates
'bulk_blogroll_updates'        // Bulk sidebar updates
'update_hidden_links'          // Hidden links updates
'deletions'                    // Campaign deletions
'sidebar_deletions'            // Sidebar campaign deletions
'schedule_campaign_deletions'  // Scheduled campaign deletions
```

**Run Queue Worker:**
```bash
php artisan queue:work --queue=campaigns,sidebar_campaigns,hidden_links_campaigns
```

---

## 🗄️ DATABASE TABLES

### Core Tables
- `admins` - User accounts
- `domains` - WordPress sites
- `domain_categories` - Domain grouping
- `domain_sets` - User-defined domain groups
- `articles` - Content library
- `article_categories` - Article grouping
- `article_sets` - User-defined article groups
- `article_languages` - Language classification

### Campaign Tables (PBN Posts)
- `campaigns` - Campaign master records
- `campaign_articles` - Article assignments
- `campaign_domains` - Domain assignments
- `campaign_posts` - Individual post tasks

### Scheduled Campaign Tables
- `schedule_campaigns` - Scheduled campaign master
- `schedule_campaign_articles` - Article assignments
- `schedule_campaign_domains` - Domain assignments
- `schedule_campaign_posts` - Individual scheduled posts
- `schedule_campaign_dates` - Date distribution

### Sidebar Campaign Tables
- `sidebar_campaigns` - Sidebar campaign master
- `sidebar_campaign_domains` - Domain assignments
- `sidebar_campaign_links` - Link definitions
- `sidebar_campaign_tasks` - Individual link tasks

### Hidden Links Campaign Tables
- `hidden_links_campaigns` - Hidden links master
- `hidden_links_campaigns_domains` - Domain assignments
- `hidden_links_campaigns_links` - Link definitions
- `hidden_links_campaigns_tasks` - Individual link tasks

---

## 🔍 DEBUGGING TIPS

### Check Queue Status
```bash
# List failed jobs
php artisan queue:failed

# Retry failed job
php artisan queue:retry {job-id}

# Retry all failed jobs
php artisan queue:retry all

# Clear failed jobs
php artisan queue:flush
```

### Check Logs
```bash
# Application logs
tail -f storage/logs/laravel.log

# Queue worker logs
tail -f storage/logs/worker.log
```

### Common Issues

**Issue:** Campaign posts stuck in "publishing" status  
**Cause:** Queue worker not running or job failed  
**Fix:** Check `php artisan queue:work` is running, check failed jobs

**Issue:** Articles showing as "locked" but not in any campaign  
**Cause:** Campaign was deleted without unlocking articles  
**Fix:** Manually update `articles.lock_at = NULL` for orphaned articles

**Issue:** Domain status shows "Not Connected"  
**Cause:** WordPress plugin not installed or API key incorrect  
**Fix:** Verify plugin installed, check API key, test endpoint manually

**Issue:** Keyword not appearing in published post  
**Cause:** HTML parsing issue or paragraph too short  
**Fix:** Check `CampaignPostContentBuilder::build()` logic

---

## 🧪 TESTING COMMANDS

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/CampaignTest.php

# Run with coverage
php artisan test --coverage

# Run specific test method
php artisan test --filter test_admin_can_create_campaign
```

---

## 🚀 DEPLOYMENT COMMANDS

```bash
# Put in maintenance mode
php artisan down

# Pull latest code
git pull origin main

# Install dependencies
composer install --no-dev --optimize-autoloader

# Run migrations
php artisan migrate --force

# Clear caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart queue workers
php artisan queue:restart

# Take out of maintenance mode
php artisan up
```

---

## 📊 USEFUL QUERIES

### Find Campaigns by Status
```sql
SELECT id, campaign_no, status, total_targets, completed_targets, failed_targets
FROM campaigns
WHERE status = 'queued'
ORDER BY created_at DESC;
```

### Find Stuck Jobs
```sql
SELECT * FROM campaign_posts
WHERE status = 'publishing'
AND locked_at < NOW() - INTERVAL 10 MINUTE;
```

### Find Available Articles
```sql
SELECT id, name, admin_id
FROM articles
WHERE lock_at IS NULL
AND deleted_at IS NULL
AND status = 0;
```

### Campaign Success Rate
```sql
SELECT 
    campaign_no,
    total_targets,
    completed_targets,
    failed_targets,
    ROUND((completed_targets / total_targets) * 100, 2) as success_rate
FROM campaigns
WHERE total_targets > 0
ORDER BY success_rate DESC;
```

---

## 🔐 SECURITY CHECKLIST

Before deploying any changes:

- [ ] Added authorization check for new routes
- [ ] Validated all user inputs
- [ ] Used parameterized queries (no raw SQL)
- [ ] Sanitized output in views
- [ ] Checked for IDOR vulnerabilities
- [ ] No sensitive data in logs
- [ ] CSRF protection enabled
- [ ] Rate limiting applied
- [ ] Error messages don't leak info

---

## 📝 CODE STYLE GUIDE

### Naming Conventions
```php
// Controllers: PascalCase with "Controller" suffix
class CampaignController extends Controller

// Methods: camelCase
public function createCampaign()

// Variables: camelCase
$campaignId = 123;

// Constants: UPPER_SNAKE_CASE
const MAX_ATTEMPTS = 5;

// Database tables: snake_case, plural
campaigns, campaign_posts

// Model properties: snake_case
$campaign->campaign_no
```

### Method Order in Controllers
1. Constructor
2. Index (list)
3. Create (show form)
4. Store (save)
5. Show (view single)
6. Edit (show form)
7. Update (save changes)
8. Destroy (delete)
9. Custom methods

---

## 🆘 WHO TO ASK

### For Questions About:
- **Campaign Logic:** Check `CampaignService.php` or `campaignController.php`
- **WordPress API:** Check `BlogrollApiService.php`, `HiddenLinksApiService.php`
- **Job Processing:** Check individual job files in `app/Jobs/`
- **Content Building:** Check `CampaignPostContentBuilder.php`
- **Database Schema:** Check migrations in `database/migrations/`

---

## 📚 ADDITIONAL RESOURCES

- **Main Analysis:** `PROJECT_ANALYSIS_REPORT.md`
- **Refactoring Guide:** `REFACTORING_GUIDE.md`
- **Executive Summary:** `EXECUTIVE_SUMMARY.md`
- **Security Checklist:** `SECURITY_AUDIT_CHECKLIST.md`

---

**Last Updated:** 2026-04-29  
**Maintainer:** Development Team  
**Questions?** Check the full analysis documents or ask the team lead.
