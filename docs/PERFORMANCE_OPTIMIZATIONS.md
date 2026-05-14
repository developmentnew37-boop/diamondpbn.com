# Performance Optimizations - Dashboard & Create Campaign Pages

## Problem
- Dashboard page: LCP ~4.7 seconds
- Create Campaign page: LCP ~4.8 seconds

## Solutions Implemented

### 1. Database Query Optimizations

#### Dashboard Controller (`app/Http/Controllers/Admin/DashboardController.php`)

**Before:** Multiple separate queries with N+1 problems
**After:** Optimized single queries with joins and conditional aggregation

##### Changes Made:

1. **getStats()** - Reduced 7+ queries to 3 queries
   - Combined campaign counts using conditional aggregation
   - Batched schedule campaign counts in single query
   - Optimized article count with proper WHERE clauses

2. **getDomainCategories()** - Eliminated N+1 query
   - Changed from `withCount('domains')` to direct JOIN query
   - Reduced from 11+ queries to 1 query

3. **getArticleUsage()** - Reduced 2 queries to 1 query
   - Used conditional aggregation: `SUM(CASE WHEN...)`
   - Single query calculates total and used articles

4. **getArticlesByLanguage()** - Eliminated N+1 query
   - Changed from `withCount(['Article'])` to direct JOIN query
   - Reduced from 10+ queries to 1 query

5. **index()** - Added caching layer
   - Cache duration: 5 minutes (300 seconds)
   - Cache key: `dashboard_data_{admin_id}_{role}`
   - Automatic cache invalidation via observers

#### Campaign Controller (`app/Http/Controllers/Admin/campaignController.php`)

**Before:** Multiple withCount queries causing N+1 problems
**After:** Direct JOIN queries with caching

##### Changes Made:

1. **create()** - Optimized data loading
   - Changed ArticleSet `withCount` to direct JOIN query
   - Changed ArticleLanguage `withCount` to direct JOIN query
   - Added caching: 10 minutes (600 seconds)
   - Cache key: `campaign_create_data_{admin_id}`
   - Load only necessary columns (id, name) for dropdowns

### 2. Database Indexes

Created migration: `2026_04_29_202400_add_performance_indexes_to_tables.php`

#### Indexes Added:

**articles table:**
- `idx_articles_admin_lock_deleted` on (admin_id, lock_at, deleted_at)
- `idx_articles_language_status` on (article_language_id, status)
- `idx_articles_status_lock` on (status, lock_at, deleted_at)

**campaigns table:**
- `idx_campaigns_admin_created` on (admin_id, created_at)
- `idx_campaigns_admin_sticky` on (admin_id, is_sticky_campaign)

**schedule_campaigns table:**
- `idx_schedule_campaigns_composite` on (admin_id, is_sticky_campaign, created_at)

**schedule_sidebar_campaigns table:**
- `idx_schedule_sidebar_admin_created` on (admin_id, created_at)

**sidebar_campaigns table:**
- `idx_sidebar_campaigns_admin_created` on (admin_id, created_at)

**hidden_links_campaigns table:**
- `idx_hidden_links_admin_created` on (admin_id, created_at)

**domain_categories table:**
- `idx_domain_categories_admin` on (admin_id)

**article_sets table:**
- `idx_article_sets_admin` on (admin_id)

**domain_sets table:**
- `idx_domain_sets_admin` on (admin_id)

### 3. Cache Invalidation System

Created three observers to automatically clear caches when data changes:

#### Observers Created:

1. **ArticleObserver** (`app/Observers/ArticleObserver.php`)
   - Clears cache on article create/update/delete
   - Invalidates both dashboard and campaign create caches

2. **CampaignObserver** (`app/Observers/CampaignObserver.php`)
   - Clears cache on campaign create/update/delete
   - Invalidates dashboard cache

3. **DomainObserver** (`app/Observers/DomainObserver.php`)
   - Clears cache on domain create/update/delete
   - Invalidates both dashboard and campaign create caches

#### Observer Registration:
Registered in `app/Providers/AppServiceProvider.php`:
```php
Article::observe(ArticleObserver::class);
Campaign::observe(CampaignObserver::class);
Domain::observe(DomainObserver::class);
```

## Expected Performance Improvements

### Dashboard Page:
- **Query Reduction:** ~30+ queries → ~8-10 queries (first load)
- **Cached Load:** ~8-10 queries → ~2-3 queries (subsequent loads within 5 min)
- **Expected LCP:** 4.7s → 1.5-2.0s (60-70% improvement)

### Create Campaign Page:
- **Query Reduction:** ~15+ queries → ~5-7 queries (first load)
- **Cached Load:** ~5-7 queries → ~2-3 queries (subsequent loads within 10 min)
- **Expected LCP:** 4.8s → 1.5-2.0s (60-70% improvement)

## Testing Recommendations

1. **Clear cache and test first load:**
   ```bash
   php artisan cache:clear
   ```

2. **Test dashboard page:**
   - Visit `/admin` (dashboard)
   - Check browser DevTools → Network tab
   - Measure LCP in Lighthouse

3. **Test create campaign page:**
   - Visit `/admin/campaign/create`
   - Check browser DevTools → Network tab
   - Measure LCP in Lighthouse

4. **Test cached performance:**
   - Reload the same pages within cache duration
   - Should be significantly faster

5. **Test cache invalidation:**
   - Create/update an article or campaign
   - Reload dashboard - should show updated data
   - Cache should be automatically cleared

## Additional Optimization Opportunities

If further optimization is needed:

1. **Implement Redis cache** instead of file cache
2. **Add database query result caching** for static data
3. **Implement lazy loading** for charts/widgets
4. **Add pagination** to recent campaigns table
5. **Optimize frontend assets** (minify JS/CSS, use CDN)
6. **Implement HTTP/2 Server Push** for critical resources
7. **Add database read replicas** for heavy read operations

## Monitoring

Monitor these metrics after deployment:
- Average page load time
- Database query count per request
- Cache hit rate
- Server response time (TTFB)
- Largest Contentful Paint (LCP)

## Rollback Instructions

If issues occur, rollback the migration:
```bash
php artisan migrate:rollback --step=1
```

Then clear cache:
```bash
php artisan cache:clear
php artisan config:clear
```
