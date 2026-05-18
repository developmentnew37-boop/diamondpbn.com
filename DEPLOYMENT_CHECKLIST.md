# 🚀 Deployment Checklist - UTF-8 & RTL Support

**Implementation Date**: 2026-05-17  
**Status**: ✅ Ready for Production Deployment  
**Branch**: diamond-version-1

---

## 📦 What Was Implemented Today

### **1. UTF-8 Sanitization System**
- ✅ Production-grade UTF-8 cleaning service
- ✅ Multi-layered sanitization (model, controller, job, HTTP)
- ✅ Global helper functions
- ✅ Comprehensive logging
- ✅ Automated testing command

### **2. RTL Language Support**
- ✅ Automatic RTL language detection
- ✅ Auto-wrapping with `dir="rtl"` attributes
- ✅ ZWNJ/ZWJ character preservation for Persian/Arabic
- ✅ Smart mixed-content handling

### **3. Supported Languages**
- ✅ Arabic (العربية)
- ✅ Persian/Farsi (فارسی)
- ✅ Hebrew (עברית)
- ✅ Urdu (اردو)
- ✅ Chinese (中文)
- ✅ Thai (ไทย)
- ✅ English and all other languages
- ✅ Emoji support (🌍 👋 ✅)

---

## 📋 Files Modified (19 Total)

### **New Files (3)**
- [ ] `app/Services/Utf8SanitizerService.php`
- [ ] `app/helpers.php`
- [ ] `app/Console/Commands/TestUtf8Sanitization.php`

### **Modified Files (16)**
- [ ] `composer.json`
- [ ] `app/Models/Admin/Article.php`
- [ ] `app/Http/Controllers/Admin/ArticleController.php`
- [ ] `app/Http/Controllers/Admin/ScheduleCampaignController.php`
- [ ] `app/Http/Controllers/Admin/WpScheduledCampaignController.php`
- [ ] `app/Http/Controllers/Admin/campaignController.php`
- [ ] `app/Jobs/PublishCampaignPostJob.php`
- [ ] `app/Jobs/PublishHiddenLinksJob.php`
- [ ] `app/Jobs/PublishScheduledCampaignPostJob.php`
- [ ] `app/Jobs/PublishSidebarBlogrollJob.php`
- [ ] `app/Jobs/PublishWpScheduledPostJob.php`
- [ ] `app/Services/CampaignPostContentBuilder.php`
- [ ] `app/Services/WpScheduledPostContentBuilder.php`
- [ ] `app/Services/Utf8SanitizerService.php`

### **Documentation Files (3)**
- [ ] `RTL_LANGUAGE_SUPPORT.md` (Complete RTL implementation guide)
- [ ] `UTF8_SANITIZATION_GUIDE.md` (UTF-8 technical guide)
- [ ] `DEPLOYMENT_CHECKLIST.md` (This file)

---

## ✅ Pre-Deployment Checklist

### **Local Testing**
- [ ] All files saved and committed locally
- [ ] No syntax errors in PHP files
- [ ] Git status shows all changes staged

### **Backup**
- [ ] Production code backed up
- [ ] Database backed up (if needed)
- [ ] Backup location documented

---

## 🚀 Deployment Steps

### **Step 1: Deploy Code**

#### **Option A: Git Deployment (Recommended)**
```bash
# On local machine
git add .
git status  # Verify all 19 files are staged

git commit -m "Implement UTF-8 sanitization and RTL language support

Core Features:
- Add Utf8SanitizerService for production-grade UTF-8 cleaning
- Add 6 global helper functions (cleanUtf8, safeJsonEncode, isValidUtf8, isRtlText, wrapRtlContent)
- Implement multi-layered sanitization (model, controller, job, HTTP)
- Add automatic RTL language detection and wrapping
- Preserve ZWNJ/ZWJ characters for Persian/Arabic text joining
- Add UTF-8 safe headers to all WordPress API requests
- Support Arabic, Persian, Hebrew, Urdu, Chinese, Thai, emoji, and all Unicode
- Prevent json_encode failures in WordPress API posting

Technical Details:
- Modified 16 files, added 3 new files
- RTL auto-detection with 30% threshold
- BiDi marks (LRM, RLM, LRE, RLE) preserved
- Comprehensive logging for debugging
- Automated testing command included

Fixes: Malformed UTF-8 characters error in multilingual article posting
Enhances: WordPress display for RTL languages

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"

git push origin diamond-version-1
```

#### **Option B: Manual Upload**
- [ ] Upload all 19 files via FTP/SFTP
- [ ] Verify file permissions (644 for files, 755 for directories)
- [ ] Verify all files uploaded successfully

---

### **Step 2: Production Server Commands**

```bash
# SSH into production server
ssh user@your-server.com
cd /path/to/laravel/project

# 1. Backup current code (CRITICAL!)
cp -r . ../backup_$(date +%Y%m%d_%H%M%S)

# 2. Pull changes (if using Git)
git pull origin diamond-version-1

# 3. Regenerate autoloader (CRITICAL - loads helpers.php)
composer dump-autoload --optimize

# 4. Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear

# 5. Restart queue workers (CRITICAL - reloads job classes)
php artisan queue:restart

# 6. Verify installation
php artisan test:utf8-sanitization
```

**Expected Output**:
```
✅ All tests completed successfully!

📋 Summary:
  • PHP extensions: OK (mbstring ✅, iconv ✅, Normalizer ✅)
  • Helper functions: OK (cleanUtf8 ✅, safeJsonEncode ✅, isValidUtf8 ✅)
  • UTF-8 cleaning: OK (6/6 tests passed)
  • JSON encoding: OK (2/2 tests passed)
  • UTF-8 validation: OK (5/5 tests passed)

🚀 System is ready for multilingual content!
```

---

### **Step 3: Verify Helper Functions**

```bash
php artisan tinker
```

**Run these tests in Tinker**:
```php
// 1. Check all 6 helper functions exist
function_exists('cleanUtf8')      // Must return: true
function_exists('safeJsonEncode') // Must return: true
function_exists('isValidUtf8')    // Must return: true
function_exists('isRtlText')      // Must return: true
function_exists('wrapRtlContent') // Must return: true

// 2. Test UTF-8 cleaning
cleanUtf8('Hello 你好 🌍')  // Should return clean text

// 3. Test RTL detection
isRtlText('مرحبا بالعالم')  // Must return: true (Arabic)
isRtlText('Hello World')    // Must return: false (English)
isRtlText('سلام دنیا')      // Must return: true (Persian)
isRtlText('שלום עולם')      // Must return: true (Hebrew)

// 4. Test RTL wrapping
wrapRtlContent('<p>Test</p>', 'مرحبا')
// Should return: <div dir="rtl" style="text-align: right;"><p>Test</p></div>

// 5. Test safe JSON encoding
safeJsonEncode(['title' => '你好世界', 'emoji' => '🌍'])
// Should return valid JSON string

exit
```

**Checklist**:
- [ ] All 6 functions exist
- [ ] UTF-8 cleaning works
- [ ] RTL detection works for Arabic
- [ ] RTL detection works for Persian
- [ ] RTL detection works for Hebrew
- [ ] RTL detection returns false for English
- [ ] RTL wrapping adds `dir="rtl"`
- [ ] Safe JSON encoding works

---

### **Step 4: Check Queue Workers**

```bash
# Check if queue workers are running
ps aux | grep "queue:work"

# If not running, start them
php artisan queue:work --queue=scheduled_campaigns,campaigns,wp_scheduled_campaigns,sidebar_campaigns,hidden_links_campaigns --tries=3 --timeout=300 &

# Or if using Supervisor
sudo supervisorctl restart all
```

**Checklist**:
- [ ] Queue workers are running
- [ ] Workers restarted after deployment
- [ ] No errors in worker logs

---

## 🧪 Production Testing

### **Test 1: Create Arabic Article**
1. Go to: **Articles → Add New Article**
2. Enter:
   - **Title**: `مقالة عربية تجريبية`
   - **Description**: `هذا محتوى عربي للاختبار. يجب أن يظهر بشكل صحيح على ووردبريس.`
3. **Category**: Any
4. **Language**: Arabic
5. Click **Save**

**Expected Results**:
- [ ] Article saves successfully
- [ ] No errors in browser console
- [ ] No errors in Laravel logs

### **Test 2: Create Campaign with Arabic Article**
1. Go to: **Campaigns → Create Campaign**
2. Select the Arabic article created above
3. Add keywords and URLs
4. Schedule or publish immediately

**Expected Results**:
- [ ] Campaign created successfully
- [ ] Job queued successfully
- [ ] No `json_encode()` errors in logs

### **Test 3: Verify WordPress Post**
1. Check WordPress site
2. Find the posted article

**Expected Results**:
- [ ] Article posted successfully
- [ ] Title displays correctly (right-aligned)
- [ ] Content displays correctly (right-to-left)
- [ ] Text aligned to the right
- [ ] Proper reading order
- [ ] No encoding issues (no � characters)

### **Test 4: Create Persian Article**
1. Create article with Persian content
2. Include text with ZWNJ: `می‌خواهم`
3. Post to WordPress

**Expected Results**:
- [ ] ZWNJ preserved (word separation correct)
- [ ] RTL display correct
- [ ] No encoding errors

### **Test 5: Create English Article**
1. Create article with English content only
2. Post to WordPress

**Expected Results**:
- [ ] No RTL wrapping applied
- [ ] Normal left-to-right display
- [ ] No encoding issues

### **Test 6: Import DOCX with Multilingual Content**
1. Go to: **Articles → Upload DOCX**
2. Upload DOCX with Arabic/Persian/Chinese content
3. Import articles

**Expected Results**:
- [ ] All articles imported successfully
- [ ] No encoding errors
- [ ] RTL content detected and wrapped
- [ ] ZWNJ/ZWJ characters preserved

---

## 📊 Monitoring (First 24 Hours)

### **Watch Logs**
```bash
# Watch for UTF-8 sanitization events
tail -f storage/logs/laravel.log | grep "UTF-8"

# Watch for errors
tail -f storage/logs/laravel.log | grep "ERROR"

# Watch queue processing
tail -f storage/logs/laravel.log | grep "queue"
```

### **Check Queue Status**
```bash
# View queue statistics
php artisan queue:work --verbose

# Check failed jobs
php artisan queue:failed

# Retry failed jobs (if any)
php artisan queue:retry all
```

### **Monitor WordPress API Calls**
- [ ] Check WordPress API logs
- [ ] Verify no 400/500 errors
- [ ] Verify posts are created successfully

---

## ⚠️ Rollback Plan (If Issues Occur)

### **Quick Rollback**
```bash
# Stop queue workers
php artisan queue:restart

# Restore backup
cd /path/to/laravel
rm -rf project_current
cp -r ../backup_YYYYMMDD_HHMMSS project_current

# Restart queue workers
php artisan queue:restart
```

### **Partial Rollback (Keep UTF-8, Remove RTL)**
If RTL wrapping causes issues but UTF-8 sanitization works:

1. Comment out RTL wrapping in content builders:
```php
// $html = wrapRtlContent($html, $title);
```

2. Clear caches and restart workers:
```bash
php artisan cache:clear
php artisan queue:restart
```

---

## 🎯 Success Criteria

### **Must Have (Critical)**
- [ ] No `json_encode()` errors in logs
- [ ] Multilingual articles post successfully to WordPress
- [ ] Queue jobs complete without failures
- [ ] No PHP errors or exceptions

### **Should Have (Important)**
- [ ] RTL content displays correctly on WordPress
- [ ] Persian ZWNJ/ZWJ characters preserved
- [ ] English content unaffected (no RTL wrapping)
- [ ] DOCX imports work correctly

### **Nice to Have (Optional)**
- [ ] Detailed UTF-8 sanitization logs
- [ ] Performance metrics (no significant slowdown)
- [ ] Zero failed queue jobs

---

## 📞 Support & Documentation

### **If Issues Occur**

1. **Check logs first**:
   ```bash
   tail -100 storage/logs/laravel.log
   ```

2. **Verify helper functions loaded**:
   ```bash
   php artisan tinker
   function_exists('cleanUtf8')
   ```

3. **Restart queue workers**:
   ```bash
   php artisan queue:restart
   ```

4. **Clear all caches**:
   ```bash
   php artisan cache:clear
   php artisan config:clear
   php artisan view:clear
   ```

5. **Run test command**:
   ```bash
   php artisan test:utf8-sanitization
   ```

### **Documentation Files**
- **RTL_LANGUAGE_SUPPORT.md** - Complete RTL implementation guide
- **UTF8_SANITIZATION_GUIDE.md** - UTF-8 technical details
- **IMPLEMENTATION_SUMMARY.md** - Quick reference
- **FINAL_DELIVERY.md** - Complete delivery summary
- **DEPLOYMENT_CHECKLIST.md** - This file

---

## ✅ Post-Deployment Checklist

### **Immediate (Within 1 Hour)**
- [ ] All deployment commands executed successfully
- [ ] Helper functions verified in Tinker
- [ ] Test command passed
- [ ] Queue workers running
- [ ] No errors in logs

### **Short Term (Within 24 Hours)**
- [ ] Arabic article posted successfully
- [ ] Persian article posted successfully
- [ ] English article posted successfully
- [ ] DOCX import tested
- [ ] No `json_encode()` errors
- [ ] WordPress displays RTL content correctly
- [ ] No failed queue jobs

### **Medium Term (Within 1 Week)**
- [ ] Multiple multilingual campaigns posted
- [ ] Performance metrics normal
- [ ] No encoding-related support tickets
- [ ] System stable under production load

---

## 🎉 Summary

**Total Changes**: 19 files (3 new, 16 modified)  
**New Features**: 6 helper functions, RTL auto-detection, UTF-8 sanitization  
**Languages Supported**: Arabic, Persian, Hebrew, Urdu, Chinese, Thai, English, and all others  
**Expected Impact**: Zero `json_encode()` errors, proper RTL display on WordPress  

**Status**: ✅ Ready for Production Deployment

---

**Deployment Date**: _____________  
**Deployed By**: _____________  
**Deployment Method**: [ ] Git  [ ] Manual Upload  
**Rollback Plan Reviewed**: [ ] Yes  [ ] No  
**Backup Created**: [ ] Yes  [ ] No  
**Backup Location**: _____________  

---

**Document Version**: 1.0  
**Last Updated**: 2026-05-17  
**Next Review**: After 24 hours of production use
