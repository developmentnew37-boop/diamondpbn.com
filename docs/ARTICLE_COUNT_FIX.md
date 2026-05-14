# Article Count Fix - Summary

## Issue
After deployment, article counts were not showing correctly in the campaign creation page. Only 33 articles were visible (23 English + 10 Thai) instead of the expected 15,732 available articles.

## Root Cause
The `campaignController.php` was filtering articles, article sets, and domain sets by `admin_id`, which prevented admins and super admins from seeing articles belonging to other admins.

## Solution
Removed the `admin_id` filter from three queries in `app/Http/Controllers/Admin/campaignController.php`:

### 1. Article Languages Query (Line ~134-145)
**Before:**
```php
$articleLanguages = DB::table('article_languages')
    ->leftJoin('articles', function($join) use ($adminId) {
        $join->on('article_languages.id', '=', 'articles.article_language_id')
             ->where('articles.status', 0)
             ->where('articles.admin_id', $adminId)  // ❌ REMOVED
             ->whereNull('articles.deleted_at')
             ->whereNull('articles.lock_at');
    })
```

**After:**
```php
$articleLanguages = DB::table('article_languages')
    ->leftJoin('articles', function($join) {
        $join->on('article_languages.id', '=', 'articles.article_language_id')
             ->where('articles.status', 0)
             // admin_id filter removed ✅
             ->whereNull('articles.deleted_at')
             ->whereNull('articles.lock_at');
    })
```

### 2. Article Sets Query (Line ~115-126)
**Before:**
```php
->where('article_sets.admin_id', $adminId)  // ❌ REMOVED
```

**After:**
```php
// admin_id filter removed ✅
```

### 3. Domain Sets Query (Line ~129-131)
**Before:**
```php
$domainSets = DomainSet::select('id', 'name')
    ->where('admin_id', $adminId)  // ❌ REMOVED
    ->get();
```

**After:**
```php
$domainSets = DomainSet::select('id', 'name')->get();  // ✅
```

## Result
- ✅ All admins and super admins can now see articles from all admins
- ✅ Article languages dropdown shows correct counts (15,732 articles)
- ✅ Article sets show all available sets
- ✅ Domain sets show all available sets

## Files Modified
- `app/Http/Controllers/Admin/campaignController.php`

## Deployment Commands
```bash
# Upload the modified file, then run:
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear
```

## Date Fixed
2026-04-30

## Status
✅ **RESOLVED**
