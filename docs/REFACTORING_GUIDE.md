# PBN Automation Software - Refactoring Guide

**Companion to:** PROJECT_ANALYSIS_REPORT.md  
**Purpose:** Actionable refactoring examples and implementation guide

---

## PRIORITY MATRIX

### 🔴 CRITICAL (Fix Immediately - Week 1)

| Issue | Impact | Effort | Files Affected |
|-------|--------|--------|----------------|
| IDOR Vulnerabilities | HIGH | LOW | All controllers |
| Missing Authorization | HIGH | MEDIUM | All controllers |
| SQL Injection Risks | HIGH | LOW | Search queries |

### 🟠 HIGH PRIORITY (Fix Within Month 1)

| Issue | Impact | Effort | Files Affected |
|-------|--------|--------|----------------|
| God Controllers | MEDIUM | HIGH | Campaign controllers |
| Code Duplication | MEDIUM | HIGH | All campaign features |
| Missing Indexes | HIGH | LOW | Database migrations |
| N+1 Queries | MEDIUM | MEDIUM | Controllers with relationships |
| No Tests | HIGH | HIGH | Entire codebase |

### 🟡 MEDIUM PRIORITY (Fix Within Months 2-3)

| Issue | Impact | Effort | Files Affected |
|-------|--------|--------|----------------|
| Repository Pattern | MEDIUM | HIGH | All models |
| Request Validation | MEDIUM | MEDIUM | All controllers |
| Event System | LOW | MEDIUM | Campaign operations |
| Caching Layer | MEDIUM | MEDIUM | Dashboard, lookups |
| Error Handling | MEDIUM | MEDIUM | All controllers |

### 🟢 LOW PRIORITY (Fix Within Months 4-6)

| Issue | Impact | Effort | Files Affected |
|-------|--------|--------|----------------|
| Documentation | LOW | HIGH | Entire codebase |
| Naming Consistency | LOW | MEDIUM | All files |
| Type Hints | LOW | MEDIUM | All methods |
| DTOs | LOW | HIGH | Data transfer layers |

---

## REFACTORING EXAMPLES

### Example 1: Fix IDOR Vulnerability

**BEFORE (Vulnerable):**
```php
// campaignController.php
public function deleteCampaignPost(string $id)
{
    $campaignPost = CampaignPost::find($id);
    
    if (!$campaignPost) {
        return back()->with('cus__error', 'Post not found');
    }
    
    // VULNERABLE: No ownership check!
    $campaignPost->delete();
    
    return back()->with('cus__success', 'Post deleted');
}
```

**AFTER (Secure):**
```php
// campaignController.php
public function deleteCampaignPost(string $id)
{
    $campaignPost = CampaignPost::with('campaign')->findOrFail($id);
    
    // Authorization check
    $this->authorize('delete', $campaignPost);
    
    // Or manual check:
    $admin = Auth::guard('admin')->user();
    if (!$admin->isSuperAdmin() && $campaignPost->campaign->admin_id !== $admin->id) {
        abort(403, 'Unauthorized action.');
    }
    
    $campaignPost->delete();
    
    return back()->with('cus__success', 'Post deleted');
}
```

**Create Policy:**
```php
// app/Policies/CampaignPostPolicy.php
<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Admin\CampaignPost;

class CampaignPostPolicy
{
    public function delete(Admin $admin, CampaignPost $post): bool
    {
        // Super admin can delete anything
        if ($admin->isSuperAdmin()) {
            return true;
        }
        
        // Regular admin can only delete their own
        return $post->campaign->admin_id === $admin->id;
    }
}
```

---

### Example 2: Extract Service Class

**BEFORE (God Controller):**
```php
// campaignController.php (1550 lines)
public function store(Request $request)
{
    // 200+ lines of validation, business logic, DB operations
    $validated = $request->validate([...]);
    
    $campaign = DB::transaction(function () use ($request) {
        // Complex campaign creation logic
        // Article locking logic
        // Domain pairing logic
        // Job dispatching logic
    });
    
    return redirect()->route('admin.campaign.create');
}
```

**AFTER (Clean Controller):**
```php
// campaignController.php
public function store(StoreCampaignRequest $request, CampaignService $campaignService)
{
    try {
        $campaign = $campaignService->createCampaign(
            $request->validated(),
            Auth::guard('admin')->user()
        );
        
        return redirect()
            ->route('admin.campaign.create')
            ->with('cus__success', "Campaign {$campaign->campaign_no} created successfully.");
            
    } catch (CampaignCreationException $e) {
        return back()
            ->with('cus__error', $e->getMessage())
            ->withInput();
    }
}
```

**Service Class:**
```php
// app/Services/CampaignService.php
<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Admin\Campaign;
use App\Exceptions\CampaignCreationException;
use Illuminate\Support\Facades\DB;

class CampaignService
{
    public function __construct(
        private ArticleLockService $articleLockService,
        private CampaignDomainService $domainService,
        private CampaignJobDispatcher $jobDispatcher
    ) {}
    
    public function createCampaign(array $data, Admin $admin): Campaign
    {
        return DB::transaction(function () use ($data, $admin) {
            // Validate business rules
            $this->validateCampaignData($data);
            
            // Create campaign
            $campaign = $this->createCampaignRecord($data, $admin);
            
            // Lock articles
            $this->articleLockService->lockArticles($data['article_ids']);
            
            // Create campaign relationships
            $this->domainService->attachDomains($campaign, $data['domain_ids']);
            $this->attachArticles($campaign, $data);
            
            // Dispatch jobs
            $this->jobDispatcher->dispatchCampaignJobs($campaign);
            
            return $campaign;
        });
    }
    
    private function validateCampaignData(array $data): void
    {
        if (count($data['article_ids']) !== $data['post_quantity']) {
            throw new CampaignCreationException(
                'Article count must match post quantity'
            );
        }
        
        // More validation...
    }
    
    // More methods...
}
```

**Request Class:**
```php
// app/Http/Requests/StoreCampaignRequest.php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')->can('create', Campaign::class);
    }
    
    public function rules(): array
    {
        return [
            'campaign_no' => 'required|string|max:191',
            'domain_category' => 'nullable|integer|exists:domain_categories,id',
            'post_quantity' => 'required|integer|min:1|max:1000',
            'article_niche' => 'nullable|integer|exists:article_categories,id',
            'sel_articles_opt' => 'required|in:own_article,system_article,language_article',
            'selected_articles_val' => 'required|string',
            'keywordmethod' => 'nullable|string|in:normal,bulk,multiple,multi_bulk',
            'keywordsDataHolder' => 'required|json',
            'sel_domains' => 'required|integer|in:0,1,2',
            'campaigns_domains' => 'required|json',
            'is_sticky' => 'nullable|boolean',
        ];
    }
    
    public function messages(): array
    {
        return [
            'campaign_no.required' => 'Campaign name is required',
            'post_quantity.min' => 'At least one post is required',
            'keywordsDataHolder.json' => 'Invalid keywords format',
        ];
    }
}
```

---

### Example 4: Add Database Indexes

**Migration:**
```php
// database/migrations/xxxx_add_performance_indexes.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Campaigns table
        Schema::table('campaigns', function (Blueprint $table) {
            $table->index('campaign_no'); // Frequently searched
            $table->index('admin_id'); // Filtered by owner
            $table->index('status'); // Filtered by status
            $table->index(['admin_id', 'status']); // Composite for dashboard
            $table->index('created_at'); // Sorted by date
        });
        
        // Campaign posts table
        Schema::table('campaign_posts', function (Blueprint $table) {
            $table->index('campaign_id'); // Foreign key
            $table->index('status'); // Filtered frequently
            $table->index(['campaign_id', 'status']); // Composite
            $table->index('remote_id'); // Looked up for updates
        });
        
        // Domains table
        Schema::table('domains', function (Blueprint $table) {
            $table->index('domain_category_id'); // Foreign key
            $table->index('admin_id'); // Filtered by owner
            $table->index('status'); // Filtered by connectivity
            $table->index('name'); // Searched
        });
        
        // Articles table
        Schema::table('articles', function (Blueprint $table) {
            $table->index('admin_id'); // Filtered by owner
            $table->index('lock_at'); // Filtered for available articles
            $table->index('status'); // Filtered by usage
            $table->index(['admin_id', 'lock_at', 'status']); // Composite for article selection
        });
    }
    
    public function down()
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropIndex(['campaign_no']);
            $table->dropIndex(['admin_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['admin_id', 'status']);
            $table->dropIndex(['created_at']);
        });
        
        // Drop other indexes...
    }
};
```

---

### Example 5: Fix N+1 Query Problem

**BEFORE (N+1 Problem):**
```php
// DashboardController.php
public function getRecentCampaigns(Admin $admin, string $type = 'post'): array
{
    $campaigns = Campaign::query()
        ->when(!$admin->isSuperAdmin(), fn($q) => $q->where('admin_id', $admin->id))
        ->latest()
        ->take(10)
        ->get(); // N+1 problem here!
    
    return $campaigns->map(fn($c) => [
        'campaign' => $c->campaign_no,
        'domain' => $c->campaignDomain->name ?? 'N/A', // Extra query per campaign!
        'quantity' => $c->total_targets,
    ])->toArray();
}
```

**AFTER (Optimized):**
```php
// DashboardController.php
public function getRecentCampaigns(Admin $admin, string $type = 'post'): array
{
    $campaigns = Campaign::query()
        ->when(!$admin->isSuperAdmin(), fn($q) => $q->where('admin_id', $admin->id))
        ->with('domainCategory:id,name') // Eager load!
        ->withCount('campaignArticles', 'campaignDomains') // Aggregate counts
        ->latest()
        ->take(10)
        ->get();
    
    return $campaigns->map(fn($c) => [
        'campaign' => $c->campaign_no,
        'domain' => $c->domainCategory->name ?? 'N/A', // No extra query!
        'quantity' => $c->total_targets,
        'articles' => $c->campaign_articles_count,
        'domains' => $c->campaign_domains_count,
    ])->toArray();
}
```

---

### Example 6: Implement Repository Pattern

**Repository Interface:**
```php
// app/Repositories/Contracts/CampaignRepositoryInterface.php
<?php

namespace App\Repositories\Contracts;

use App\Models\Admin\Campaign;
use Illuminate\Pagination\LengthAwarePaginator;

interface CampaignRepositoryInterface
{
    public function findById(int $id): ?Campaign;
    
    public function findByNumber(string $campaignNo): ?Campaign;
    
    public function getPaginated(array $filters, int $perPage = 100): LengthAwarePaginator;
    
    public function create(array $data): Campaign;
    
    public function update(Campaign $campaign, array $data): bool;
    
    public function delete(Campaign $campaign): bool;
    
    public function getByAdmin(int $adminId, array $filters = []): LengthAwarePaginator;
}
```

**Repository Implementation:**
```php
// app/Repositories/CampaignRepository.php
<?php

namespace App\Repositories;

use App\Models\Admin\Campaign;
use App\Repositories\Contracts\CampaignRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class CampaignRepository implements CampaignRepositoryInterface
{
    public function findById(int $id): ?Campaign
    {
        return Campaign::with(['domainCategory', 'articleCategory'])
            ->find($id);
    }
    
    public function findByNumber(string $campaignNo): ?Campaign
    {
        return Campaign::where('campaign_no', $campaignNo)->first();
    }
    
    public function getPaginated(array $filters, int $perPage = 100): LengthAwarePaginator
    {
        $query = Campaign::query()
            ->with(['domainCategory'])
            ->withCount(['campaignArticles', 'campaignDomains']);
        
        if (isset($filters['search'])) {
            $query->where('campaign_no', 'LIKE', "%{$filters['search']}%");
        }
        
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        if (isset($filters['admin_id'])) {
            $query->where('admin_id', $filters['admin_id']);
        }
        
        return $query->orderByDesc('id')->paginate($perPage);
    }
    
    public function create(array $data): Campaign
    {
        return Campaign::create($data);
    }
    
    public function update(Campaign $campaign, array $data): bool
    {
        return $campaign->update($data);
    }
    
    public function delete(Campaign $campaign): bool
    {
        return $campaign->delete();
    }
    
    public function getByAdmin(int $adminId, array $filters = []): LengthAwarePaginator
    {
        $filters['admin_id'] = $adminId;
        return $this->getPaginated($filters);
    }
}
```

**Service Provider Registration:**
```php
// app/Providers/RepositoryServiceProvider.php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\Contracts\CampaignRepositoryInterface;
use App\Repositories\CampaignRepository;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind(
            CampaignRepositoryInterface::class,
            CampaignRepository::class
        );
    }
}
```

**Usage in Controller:**
```php
// campaignController.php
public function __construct(
    private CampaignRepositoryInterface $campaignRepository
) {}

public function index(Request $request)
{
    $filters = [
        'search' => $request->input('search'),
        'status' => $request->input('status'),
    ];
    
    $admin = Auth::guard('admin')->user();
    
    if (!$admin->isSuperAdmin()) {
        $campaigns = $this->campaignRepository->getByAdmin($admin->id, $filters);
    } else {
        $campaigns = $this->campaignRepository->getPaginated($filters);
    }
    
    return view('admin.campaigns.pbn-post.campaign', compact('campaigns'));
}
```

---

### Example 7: Add Comprehensive Tests

**Feature Test:**
```php
// tests/Feature/CampaignTest.php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Admin;
use App\Models\Admin\Campaign;
use App\Models\Admin\Domain;
use App\Models\Admin\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CampaignTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_admin_can_create_campaign()
    {
        $admin = Admin::factory()->create();
        $domains = Domain::factory()->count(3)->create();
        $articles = Article::factory()->count(3)->create(['admin_id' => $admin->id]);
        
        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.campaign.store'), [
                'campaign_no' => 'Test Campaign',
                'post_quantity' => 3,
                'selected_articles_val' => $articles->pluck('id')->implode(','),
                'campaigns_domains' => json_encode($domains->pluck('id')->toArray()),
                'keywordsDataHolder' => json_encode([
                    ['keyword' => 'test1', 'url' => 'https://example.com/1'],
                    ['keyword' => 'test2', 'url' => 'https://example.com/2'],
                    ['keyword' => 'test3', 'url' => 'https://example.com/3'],
                ]),
                'sel_articles_opt' => 'own_article',
                'sel_domains' => 2,
            ]);
        
        $response->assertRedirect();
        $this->assertDatabaseHas('campaigns', [
            'admin_id' => $admin->id,
            'total_targets' => 3,
        ]);
    }
    
    public function test_admin_cannot_delete_other_admin_campaign()
    {
        $admin1 = Admin::factory()->create();
        $admin2 = Admin::factory()->create();
        
        $campaign = Campaign::factory()->create(['admin_id' => $admin1->id]);
        
        $response = $this->actingAs($admin2, 'admin')
            ->delete(route('admin.campaign.destroy', $campaign->id));
        
        $response->assertForbidden();
        $this->assertDatabaseHas('campaigns', ['id' => $campaign->id]);
    }
    
    public function test_super_admin_can_delete_any_campaign()
    {
        $superAdmin = Admin::factory()->superAdmin()->create();
        $regularAdmin = Admin::factory()->create();
        
        $campaign = Campaign::factory()->create(['admin_id' => $regularAdmin->id]);
        
        $response = $this->actingAs($superAdmin, 'admin')
            ->delete(route('admin.campaign.destroy', $campaign->id));
        
        $response->assertRedirect();
        // Campaign deletion is queued, so check job was dispatched
    }
}
```

**Unit Test:**
```php
// tests/Unit/Services/CampaignServiceTest.php
<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\CampaignService;
use App\Models\Admin;
use App\Models\Admin\Article;
use App\Exceptions\CampaignCreationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CampaignServiceTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_validates_article_count_matches_post_quantity()
    {
        $service = app(CampaignService::class);
        $admin = Admin::factory()->create();
        
        $data = [
            'post_quantity' => 5,
            'article_ids' => [1, 2, 3], // Only 3 articles
        ];
        
        $this->expectException(CampaignCreationException::class);
        $this->expectExceptionMessage('Article count must match post quantity');
        
        $service->createCampaign($data, $admin);
    }
    
    public function test_locks_articles_when_creating_campaign()
    {
        $service = app(CampaignService::class);
        $admin = Admin::factory()->create();
        $articles = Article::factory()->count(3)->create([
            'admin_id' => $admin->id,
            'lock_at' => null,
        ]);
        
        $data = [
            'campaign_no' => 'Test',
            'post_quantity' => 3,
            'article_ids' => $articles->pluck('id')->toArray(),
            'domain_ids' => [1, 2, 3],
            'keywords' => [
                ['keyword' => 'test1', 'url' => 'https://example.com/1'],
                ['keyword' => 'test2', 'url' => 'https://example.com/2'],
                ['keyword' => 'test3', 'url' => 'https://example.com/3'],
            ],
        ];
        
        $campaign = $service->createCampaign($data, $admin);
        
        foreach ($articles as $article) {
            $article->refresh();
            $this->assertNotNull($article->lock_at);
        }
    }
}
```

---

### Example 8: Implement Caching

**BEFORE (No Caching):**
```php
// DashboardController.php
public function index()
{
    $domainCategories = DomainCategory::all(); // Fetched every request
    $articleLanguages = ArticleLanguage::withCount('articles')->get(); // Expensive query
    
    return view('admin.welcome', compact('domainCategories', 'articleLanguages'));
}
```

**AFTER (With Caching):**
```php
// DashboardController.php
use Illuminate\Support\Facades\Cache;

public function index()
{
    $admin = Auth::guard('admin')->user();
    
    // Cache domain categories for 1 hour
    $domainCategories = Cache::remember('domain_categories', 3600, function () {
        return DomainCategory::orderBy('name')->get();
    });
    
    // Cache article languages per admin for 30 minutes
    $cacheKey = "article_languages_admin_{$admin->id}";
    $articleLanguages = Cache::remember($cacheKey, 1800, function () use ($admin) {
        return ArticleLanguage::withCount(['articles' => function ($query) use ($admin) {
            $query->where('admin_id', $admin->id)
                ->whereNull('lock_at');
        }])->having('articles_count', '>', 0)->get();
    });
    
    return view('admin.welcome', compact('domainCategories', 'articleLanguages'));
}

// Clear cache when data changes
public function storeDomainCategory(Request $request)
{
    $category = DomainCategory::create($request->validated());
    
    Cache::forget('domain_categories'); // Clear cache
    
    return redirect()->back();
}
```

**Cache Service:**
```php
// app/Services/CacheService.php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class CacheService
{
    const DOMAIN_CATEGORIES_KEY = 'domain_categories';
    const ARTICLE_LANGUAGES_KEY = 'article_languages_admin_';
    
    const TTL_HOUR = 3600;
    const TTL_30_MIN = 1800;
    
    public function getDomainCategories()
    {
        return Cache::remember(
            self::DOMAIN_CATEGORIES_KEY,
            self::TTL_HOUR,
            fn() => DomainCategory::orderBy('name')->get()
        );
    }
    
    public function getArticleLanguages(int $adminId)
    {
        return Cache::remember(
            self::ARTICLE_LANGUAGES_KEY . $adminId,
            self::TTL_30_MIN,
            fn() => ArticleLanguage::withCount(['articles' => function ($query) use ($adminId) {
                $query->where('admin_id', $adminId)->whereNull('lock_at');
            }])->having('articles_count', '>', 0)->get()
        );
    }
    
    public function clearDomainCategories(): void
    {
        Cache::forget(self::DOMAIN_CATEGORIES_KEY);
    }
    
    public function clearArticleLanguages(int $adminId): void
    {
        Cache::forget(self::ARTICLE_LANGUAGES_KEY . $adminId);
    }
}
```

---

## IMPLEMENTATION ROADMAP

### Week 1: Critical Security Fixes
- [ ] Day 1-2: Add authorization checks to all controllers
- [ ] Day 3: Add database indexes
- [ ] Day 4-5: Security audit and testing

### Week 2-4: Code Quality Improvements
- [ ] Week 2: Extract service classes for campaigns
- [ ] Week 3: Create request validation classes
- [ ] Week 4: Implement repository pattern

### Month 2: Testing & Monitoring
- [ ] Week 5-6: Write feature tests
- [ ] Week 7: Write unit tests
- [ ] Week 8: Add queue monitoring and logging

### Month 3: Performance & Architecture
- [ ] Week 9: Fix N+1 queries
- [ ] Week 10: Implement caching layer
- [ ] Week 11: Add event system
- [ ] Week 12: Performance testing and optimization

---

## TESTING CHECKLIST

### Security Tests
- [ ] Test IDOR protection on all resources
- [ ] Test authorization on all routes
- [ ] Test CSRF protection
- [ ] Test input sanitization
- [ ] Test SQL injection prevention

### Feature Tests
- [ ] Test campaign creation flow
- [ ] Test campaign editing flow
- [ ] Test campaign deletion flow
- [ ] Test job dispatching
- [ ] Test retry logic
- [ ] Test bulk operations
- [ ] Test report generation

### Integration Tests
- [ ] Test WordPress API integration
- [ ] Test queue processing
- [ ] Test email notifications
- [ ] Test file exports

### Performance Tests
- [ ] Test with 1000+ campaigns
- [ ] Test with 10000+ articles
- [ ] Test concurrent job processing
- [ ] Test database query performance

---

## MONITORING SETUP

### Queue Monitoring
```php
// Install Laravel Horizon
composer require laravel/horizon

// config/horizon.php
'environments' => [
    'production' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['campaigns', 'sidebar_campaigns', 'hidden_links_campaigns'],
            'balance' => 'auto',
            'processes' => 10,
            'tries' => 3,
        ],
    ],
],
```

### Logging Setup
```php
// config/logging.php
'channels' => [
    'campaign' => [
        'driver' => 'daily',
        'path' => storage_path('logs/campaign.log'),
        'level' => 'info',
        'days' => 14,
    ],
    'api' => [
        'driver' => 'daily',
        'path' => storage_path('logs/api.log'),
        'level' => 'info',
        'days' => 30,
    ],
],

// Usage in jobs
Log::channel('campaign')->info('Publishing campaign post', [
    'campaign_id' => $campaign->id,
    'post_id' => $post->id,
    'domain' => $domain->name,
]);
```

---

## DEPLOYMENT CHECKLIST

### Pre-Deployment
- [ ] Run all tests
- [ ] Check code coverage (target: 80%+)
- [ ] Run static analysis (PHPStan/Psalm)
- [ ] Review security checklist
- [ ] Backup database
- [ ] Test rollback procedure

### Deployment
- [ ] Put application in maintenance mode
- [ ] Pull latest code
- [ ] Run migrations
- [ ] Clear caches
- [ ] Restart queue workers
- [ ] Run smoke tests
- [ ] Take application out of maintenance mode

### Post-Deployment
- [ ] Monitor error logs
- [ ] Monitor queue processing
- [ ] Check application performance
- [ ] Verify critical features
- [ ] Monitor API success rates

---

**End of Refactoring Guide**
