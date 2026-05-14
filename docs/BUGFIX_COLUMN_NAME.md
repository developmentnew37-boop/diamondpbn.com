# Bug Fix - Column Name Correction

## Issue
After deploying the performance optimizations, the following error occurred:

```
SQLSTATE[42S22]: Column not found: 1054 Unknown column 'articles.language_id' in 'ON'
```

## Root Cause
The articles table uses `article_language_id` as the foreign key column name, not `language_id`.

## Files Fixed

### 1. DashboardController.php
**Method:** `getArticlesByLanguage()`
**Line:** ~210

**Changed:**
```php
// BEFORE (WRONG)
->leftJoin('articles', 'article_languages.id', '=', 'articles.language_id')

// AFTER (CORRECT)
->leftJoin('articles', 'article_languages.id', '=', 'articles.article_language_id')
```

### 2. campaignController.php
**Method:** `create()`
**Line:** ~135

**Changed:**
```php
// BEFORE (WRONG)
$join->on('article_languages.id', '=', 'articles.language_id')

// AFTER (CORRECT)
$join->on('article_languages.id', '=', 'articles.article_language_id')
```

## Status
✅ **FIXED** - Both files have been corrected and tested.

## For Live Deployment
Make sure to use the LATEST versions of these files:
- `app/Http/Controllers/Admin/DashboardController.php`
- `app/Http/Controllers/Admin/campaignController.php`

After uploading, run:
```bash
php artisan cache:clear
php artisan config:clear
```
