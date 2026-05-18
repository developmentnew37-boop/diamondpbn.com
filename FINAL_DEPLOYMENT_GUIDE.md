# Final Deployment Guide - Complete UTF-8 Fix

## ✅ All Changes Complete

All `cleanUtf8()` calls have been **restored** in:
- ✅ ArticleController (6 calls)
- ✅ Article Model (4 calls)
- ✅ All Jobs and Services (already had them)

## 🚀 Deployment Steps for Live Server

### Step 1: Deploy Code to Live Server

```bash
# Pull the latest code
git pull origin diamond-version-1
```

### Step 2: Load helpers.php (CRITICAL)

**Run this command on your live server:**

```bash
composer dump-autoload
```

**Expected output:**
```
Generating optimized autoload files
Generated optimized autoload files containing XXXX classes
```

This command loads the `app/helpers.php` file which contains the `cleanUtf8()` function.

### Step 3: Clear Caches

```bash
php artisan cache:clear
php artisan config:clear
php artisan view:clear
```

### Step 4: Restart Queue Workers (CRITICAL)

```bash
php artisan queue:restart
```

This ensures queue workers pick up the new code with `mb_substr()` fixes.

### Step 5: Verify helpers.php is Loaded

Test if the function is available:

```bash
php artisan tinker --execute="echo 'Test: ' . cleanUtf8('hello') . PHP_EOL;"
```

**Expected output:**
```
Test: hello
```

If you see this, the function is loaded correctly.

---

## 🧪 Testing After Deployment

### Test 1: Create Article
1. Go to: `https://diamondpbn.com/admin/article`
2. Create a new article
3. Should work without "undefined function" error

### Test 2: Chinese Article
1. Create an article with Chinese text: `掌机游戏与网络游戏`
2. Save it
3. Create a campaign with this article
4. Post to WordPress
5. Verify Chinese displays correctly (not mojibake)

### Test 3: Import DOCX
1. Import a DOCX file with Chinese content
2. Should work without errors
3. Articles should save correctly

---

## 📋 Complete Fix Summary

### What Was Fixed

1. **Chinese Mojibake Fix** (Main Fix)
   - Changed `substr()` → `mb_substr()` in 4 files
   - Changed `strlen()` → `mb_strlen()` in 4 files
   - Changed `strrpos()` → `mb_strrpos()` in 4 files
   - **Files**: CampaignPostContentBuilder, PublishCampaignPostJob, PublishScheduledCampaignPostJob, WpScheduledPostContentBuilder

2. **UTF-8 Sanitization** (Extra Protection)
   - Kept all `cleanUtf8()` calls in Controllers and Models
   - Provides additional protection against malformed UTF-8
   - **Files**: ArticleController, Article Model

### Files Modified (Total: 6 files)

**Core Mojibake Fix:**
1. `app/Services/CampaignPostContentBuilder.php`
2. `app/Jobs/PublishCampaignPostJob.php`
3. `app/Jobs/PublishScheduledCampaignPostJob.php`
4. `app/Services/WpScheduledPostContentBuilder.php`

**UTF-8 Sanitization:**
5. `app/Http/Controllers/Admin/ArticleController.php`
6. `app/Models/Admin/Article.php`

**Helper Functions:**
- `app/helpers.php` (already exists)
- `app/Services/Utf8SanitizerService.php` (already exists)

---

## ⚠️ CRITICAL: Must Run on Live Server

**YOU MUST RUN THIS COMMAND:**

```bash
composer dump-autoload
```

**Why it's critical:**
- Without this, `cleanUtf8()` function won't be available
- You'll get "Call to undefined function" errors
- Article creation/update/import will fail

**When to run it:**
- After deploying the code
- Before testing anything
- Only needs to be run once

---

## 🎯 Expected Results

### Before Fix
- ❌ Chinese text: `随着数字娱乐行业不断发展`
- ❌ After posting: `éšç€æ•°å­—å¨±ä¹è¡Œä¸šä¸æ–­å'å±•` (mojibake)

### After Fix
- ✅ Chinese text: `随着数字娱乐行业不断发展`
- ✅ After posting: `随着数字娱乐行业不断发展` (correct)
- ✅ No "undefined function" errors
- ✅ All 20 Chinese articles post correctly

---

## 📊 Verification Checklist

After deployment, verify:

- [ ] `composer dump-autoload` completed successfully
- [ ] `cleanUtf8()` function is available (test with tinker)
- [ ] Queue workers restarted
- [ ] Can create new article without errors
- [ ] Can update existing article without errors
- [ ] Can import DOCX without errors
- [ ] Chinese articles post without mojibake
- [ ] No UTF-8 errors in logs

---

## 🆘 Troubleshooting

### If you still get "undefined function cleanUtf8()"

1. **Verify composer.json has helpers.php:**
   ```bash
   grep -A 5 '"autoload"' composer.json
   ```
   Should show: `"app/helpers.php"`

2. **Run composer dump-autoload again:**
   ```bash
   composer dump-autoload --optimize
   ```

3. **Check if helpers.php exists:**
   ```bash
   ls -la app/helpers.php
   ```

4. **Test function availability:**
   ```bash
   php artisan tinker --execute="var_dump(function_exists('cleanUtf8'));"
   ```
   Should output: `bool(true)`

### If Chinese articles still show mojibake

1. **Verify queue workers restarted:**
   ```bash
   php artisan queue:restart
   ps aux | grep "queue:work"
   ```

2. **Check if mb_substr is being used:**
   ```bash
   grep "mb_substr" app/Services/CampaignPostContentBuilder.php
   ```
   Should show multiple matches

3. **Clear all caches:**
   ```bash
   php artisan cache:clear
   php artisan config:clear
   php artisan view:clear
   composer dump-autoload --optimize
   ```

---

## 📝 Summary

**Status**: ✅ All code changes complete  
**Next Step**: Run `composer dump-autoload` on live server  
**Testing**: Create/update/import articles with Chinese text  
**Expected**: No errors, Chinese displays correctly  

Date: 2026-05-17  
Fixed by: Claude Code (Opus 4.7)
