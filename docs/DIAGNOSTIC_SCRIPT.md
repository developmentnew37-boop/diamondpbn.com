# Article Count Diagnostic Script

Run these commands on your **live server** to diagnose the issue:

## Step 1: Check Raw Database Data

```bash
php artisan tinker
```

Then run these queries inside tinker:

```php
// 1. Check total articles in database
$totalArticles = DB::table('articles')->count();
echo "Total articles: $totalArticles\n";

// 2. Check articles by status
$byStatus = DB::table('articles')
    ->select('status', DB::raw('COUNT(*) as count'))
    ->groupBy('status')
    ->get();
echo "Articles by status:\n";
print_r($byStatus->toArray());

// 3. Check articles with language assigned
$withLanguage = DB::table('articles')
    ->whereNotNull('article_language_id')
    ->count();
echo "Articles with language: $withLanguage\n";

// 4. Check available (unlocked) articles
$available = DB::table('articles')
    ->where('status', 0)
    ->whereNull('deleted_at')
    ->whereNull('lock_at')
    ->count();
echo "Available articles (status=0, not locked): $available\n";

// 5. Check article languages with counts (THIS IS THE KEY QUERY)
$adminId = 1; // Replace with your admin ID
$articleLanguages = DB::table('article_languages')
    ->leftJoin('articles', function($join) use ($adminId) {
        $join->on('article_languages.id', '=', 'articles.article_language_id')
             ->where('articles.status', 0)
             ->where('articles.admin_id', $adminId)
             ->whereNull('articles.deleted_at')
             ->whereNull('articles.lock_at');
    })
    ->select('article_languages.id', 'article_languages.name', DB::raw('COUNT(articles.id) as article_count'))
    ->groupBy('article_languages.id', 'article_languages.name')
    ->having('article_count', '>', 0)
    ->get();
    
echo "Article languages with counts:\n";
print_r($articleLanguages->toArray());

// 6. Check if cache exists
$cacheKey = 'campaign_create_data_' . $adminId;
$cached = Cache::has($cacheKey);
echo "Cache exists for admin $adminId: " . ($cached ? 'YES' : 'NO') . "\n";

if ($cached) {
    $data = Cache::get($cacheKey);
    echo "Cached article languages:\n";
    print_r($data['articleLanguages']->toArray());
}

exit
```

## Step 2: Clear Cache and Test

```bash
# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan view:clear

# Test the query again
php artisan tinker
```

```php
$adminId = 1; // Your admin ID
$cacheKey = 'campaign_create_data_' . $adminId;

// Verify cache is cleared
echo "Cache exists: " . (Cache::has($cacheKey) ? 'YES' : 'NO') . "\n";

// Run the query fresh
$articleLanguages = DB::table('article_languages')
    ->leftJoin('articles', function($join) use ($adminId) {
        $join->on('article_languages.id', '=', 'articles.article_language_id')
             ->where('articles.status', 0)
             ->where('articles.admin_id', $adminId)
             ->whereNull('articles.deleted_at')
             ->whereNull('articles.lock_at');
    })
    ->select('article_languages.id', 'article_languages.name', DB::raw('COUNT(articles.id) as article_count'))
    ->groupBy('article_languages.id', 'article_languages.name')
    ->having('article_count', '>', 0)
    ->get();
    
echo "Fresh query results:\n";
print_r($articleLanguages->toArray());

exit
```

## Step 3: Check Database Schema

```bash
php artisan tinker
```

```php
// Check if article_language_id column exists
$columns = DB::select("SHOW COLUMNS FROM articles LIKE 'article_language_id'");
echo "article_language_id column exists: " . (count($columns) > 0 ? 'YES' : 'NO') . "\n";

if (count($columns) > 0) {
    print_r($columns);
}

// Check for old column name (if migration wasn't run)
$oldColumns = DB::select("SHOW COLUMNS FROM articles LIKE 'language_id'");
echo "Old language_id column exists: " . (count($oldColumns) > 0 ? 'YES' : 'NO') . "\n";

exit
```

## Step 4: Test Article Sets Query

```bash
php artisan tinker
```

```php
$adminId = 1; // Your admin ID

// Test article sets query
$articleSet = DB::table('article_sets')
    ->leftJoin('article_set_items', 'article_sets.id', '=', 'article_set_items.article_set_id')
    ->leftJoin('articles', function($join) {
        $join->on('article_set_items.article_id', '=', 'articles.id')
             ->where('articles.status', '!=', 1)
             ->whereNull('articles.deleted_at')
             ->whereNull('articles.lock_at');
    })
    ->select('article_sets.id', 'article_sets.name', DB::raw('COUNT(articles.id) as articles_count'))
    ->where('article_sets.admin_id', $adminId)
    ->groupBy('article_sets.id', 'article_sets.name')
    ->get();

echo "Article sets with counts:\n";
print_r($articleSet->toArray());

exit
```

## Common Issues and Solutions

### Issue 1: Cache Not Cleared
**Symptom:** Old data still showing
**Solution:** Run `php artisan cache:clear` and refresh browser

### Issue 2: Wrong Column Name
**Symptom:** SQL error about column not found
**Solution:** Check if column is `article_language_id` or `language_id`

### Issue 3: All Articles Locked
**Symptom:** Zero articles available
**Solution:** Check `lock_at` column - unlock articles:
```sql
UPDATE articles SET lock_at = NULL, status = 0 WHERE lock_at IS NOT NULL;
```

### Issue 4: Articles Not Assigned to Languages
**Symptom:** Article languages dropdown empty
**Solution:** Assign languages to articles:
```sql
-- Check articles without language
SELECT COUNT(*) FROM articles WHERE article_language_id IS NULL;

-- Assign default language (example)
UPDATE articles SET article_language_id = 1 WHERE article_language_id IS NULL;
```

### Issue 5: Wrong Admin ID Filter
**Symptom:** No articles showing for logged-in admin
**Solution:** Check if articles belong to correct admin:
```sql
SELECT admin_id, COUNT(*) FROM articles GROUP BY admin_id;
```

## Expected Output

If everything is working correctly, you should see:

```
Article languages with counts:
Array
(
    [0] => Array
        (
            [id] => 1
            [name] => English
            [article_count] => 25
        )
    [1] => Array
        (
            [id] => 2
            [name] => Spanish
            [article_count] => 15
        )
)
```

## Next Steps

After running diagnostics, report back with:
1. Total articles count
2. Available articles count (status=0, not locked)
3. Article languages query results
4. Any error messages
