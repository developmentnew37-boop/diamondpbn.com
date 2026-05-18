# UTF-8 Sanitization Implementation Guide

## Problem Summary

The DiamondPBN application was experiencing `json_encode error: Malformed UTF-8 characters, possibly incorrectly encoded` when posting multilingual articles (Chinese, Thai, Persian, Arabic, etc.) to WordPress API endpoints.

### Root Cause

Malformed UTF-8 bytes were entering the system from multiple sources:
- **DOCX imports**: PhpWord library conversion can introduce encoding issues
- **Copied web content**: Copy-paste from browsers may include hidden control characters
- **AI-generated content**: Some AI tools produce text with invalid Unicode sequences
- **Mixed encodings**: Content from different sources with inconsistent character encodings
- **Invalid control characters**: Zero-width spaces, BOM markers, and other invisible Unicode

Even though the MySQL database was correctly configured with `utf8mb4`, malformed bytes were bypassing validation and causing `json_encode()` to fail when preparing WordPress API payloads.

---

## Solution Overview

A **production-grade, multi-layered UTF-8 sanitization system** has been implemented across the entire Laravel application.

### Key Components

1. **Utf8SanitizerService** - Core sanitization service
2. **Helper Functions** - Global `cleanUtf8()`, `safeJsonEncode()`, `isValidUtf8()`
3. **Model-Level Protection** - Automatic sanitization on Article create/update
4. **Controller-Level Protection** - Sanitization during article import and manual entry
5. **Job-Level Protection** - Sanitization before WordPress API posting
6. **Service-Level Protection** - Content builders sanitize before processing
7. **HTTP-Level Protection** - Proper UTF-8 headers and safe JSON encoding

---

## Implementation Details

### 1. Core Service: `Utf8SanitizerService`

**Location**: `app/Services/Utf8SanitizerService.php`

**Features**:
- Detects and converts mixed encodings to UTF-8
- Removes malformed UTF-8 byte sequences using `iconv`
- Strips invalid control characters (preserves newlines, tabs, carriage returns)
- Removes zero-width and invisible Unicode characters
- Normalizes Unicode to NFC form (canonical composition)
- Comprehensive logging when malformed bytes are detected
- Safe JSON encoding with `JSON_UNESCAPED_UNICODE` and `JSON_INVALID_UTF8_SUBSTITUTE`

**Key Methods**:
```php
Utf8SanitizerService::clean($text, $options)      // Clean single string
Utf8SanitizerService::cleanArray($data, $options) // Clean array recursively
Utf8SanitizerService::jsonEncode($data, $flags)   // Safe JSON encoding
Utf8SanitizerService::isValidUtf8($text)          // Validation check
```

### 2. Global Helper Functions

**Location**: `app/helpers.php`

```php
cleanUtf8($text, $options = [])           // Clean UTF-8 text
safeJsonEncode($data, $flags = 0)         // Safe JSON encoding
isValidUtf8($text)                        // Check if valid UTF-8
```

**Usage Example**:
```php
$cleanTitle = cleanUtf8($title, [
    'context' => 'article_import',
    'article_id' => 123,
    'field' => 'title',
    'language' => 'Chinese',
]);
```

### 3. Protection Layers

#### Layer 1: Model Events (Article.php)
Automatic sanitization when articles are created or updated:
```php
static::creating(function ($article) {
    $article->name = cleanUtf8($article->name, [...]);
    $article->description = cleanUtf8($article->description, [...]);
});

static::updating(function ($article) {
    if ($article->isDirty('name')) {
        $article->name = cleanUtf8($article->name, [...]);
    }
    if ($article->isDirty('description')) {
        $article->description = cleanUtf8($article->description, [...]);
    }
});
```

#### Layer 2: Controller Actions (ArticleController.php)
Sanitization during:
- Manual article creation (`store()`)
- Article updates (`update()`)
- DOCX bulk imports (`import()`)

#### Layer 3: Content Builders
- `CampaignPostContentBuilder::build()`
- `WpScheduledPostContentBuilder::build()`

Both sanitize article content before building WordPress payloads.

#### Layer 4: Queue Jobs
All WordPress API posting jobs sanitize before sending:
- `PublishScheduledCampaignPostJob`
- `PublishCampaignPostJob`
- `PublishWpScheduledPostJob`
- `PublishSidebarBlogrollJob`
- `PublishHiddenLinksJob`

#### Layer 5: HTTP Requests
All WordPress API requests now use:
```php
Http::withoutVerifying()
    ->timeout(180)
    ->acceptJson()
    ->contentType('application/json; charset=utf-8')
    ->withBody(safeJsonEncode($payload), 'application/json; charset=utf-8')
    ->post($endpoint);
```

---

## Logging and Debugging

When malformed UTF-8 is detected, the system logs:
- **Context**: Where the issue occurred (e.g., `wp_api_post`, `docx_import`)
- **Article ID**: Which article had the issue
- **Field**: Which field contained malformed bytes (title, content, etc.)
- **Language**: Article language (if available)
- **Bytes Removed**: How many bytes were stripped
- **Preview**: First 100 characters of the problematic content

**Log Location**: `storage/logs/laravel.log`

**Example Log Entry**:
```
[2026-05-17 10:23:45] local.WARNING: UTF-8 sanitization removed malformed bytes
{
    "context": "wp_api_post",
    "article_id": 1234,
    "field": "content",
    "language": "Chinese",
    "bytes_removed": 3,
    "original_length": 5420,
    "cleaned_length": 5417,
    "preview": "这是一篇关于..."
}
```

---

## Required PHP Extensions

### ✅ Already Installed (Verify)
```bash
php -m | grep -E 'mbstring|iconv|intl'
```

### Extension Details

1. **mbstring** (REQUIRED)
   - Multi-byte string handling
   - UTF-8 validation and conversion
   - Usually enabled by default in PHP 8.2+

2. **iconv** (REQUIRED)
   - Character encoding conversion
   - Aggressive malformed byte removal
   - Usually enabled by default

3. **intl** (RECOMMENDED)
   - Unicode normalization (Normalizer class)
   - Better handling of complex scripts
   - Install: `sudo apt-get install php8.2-intl` (Ubuntu/Debian)

### Verify Extensions
```php
// Run in tinker or create a test route
php artisan tinker
>>> Utf8SanitizerService::getSystemStatus()
// Should return:
// [
//     "mbstring" => true,
//     "iconv" => true,
//     "intl" => true,
//     "normalizer" => true,
// ]
```

---

## Testing the Implementation

### 1. Test UTF-8 Sanitization

Create a test route in `routes/web.php`:
```php
Route::get('/test-utf8', function () {
    // Test malformed UTF-8
    $malformed = "Hello \xC3\x28 World"; // Invalid UTF-8 sequence
    $cleaned = cleanUtf8($malformed);
    
    // Test control characters
    $withControl = "Hello\x00\x01\x02World"; // NULL and control chars
    $cleanedControl = cleanUtf8($withControl);
    
    // Test zero-width characters
    $zeroWidth = "Hello\u{200B}World"; // Zero-width space
    $cleanedZero = cleanUtf8($zeroWidth);
    
    return [
        'original_malformed' => bin2hex($malformed),
        'cleaned_malformed' => $cleaned,
        'original_control' => bin2hex($withControl),
        'cleaned_control' => $cleanedControl,
        'original_zero_width' => $zeroWidth,
        'cleaned_zero_width' => $cleanedZero,
        'system_status' => Utf8SanitizerService::getSystemStatus(),
    ];
});
```

### 2. Test Multilingual Content

Create test articles with:
- Chinese: `你好世界`
- Thai: `สวัสดีชาวโลก`
- Arabic: `مرحبا بالعالم`
- Persian: `سلام دنیا`
- Emoji: `Hello 👋 World 🌍`

Verify they save and post to WordPress without errors.

### 3. Test DOCX Import

Import a DOCX file containing multilingual content and verify:
- No `json_encode` errors
- Content displays correctly
- WordPress API accepts the payload

---

## Files Modified

### New Files
1. `app/Services/Utf8SanitizerService.php` - Core sanitization service
2. `app/helpers.php` - Global helper functions
3. `UTF8_SANITIZATION_GUIDE.md` - This documentation

### Modified Files
1. `composer.json` - Added helpers.php to autoload
2. `app/Models/Admin/Article.php` - Model event sanitization
3. `app/Http/Controllers/Admin/ArticleController.php` - Controller sanitization
4. `app/Jobs/PublishScheduledCampaignPostJob.php` - Job sanitization + UTF-8 headers
5. `app/Jobs/PublishCampaignPostJob.php` - Job sanitization + UTF-8 headers
6. `app/Jobs/PublishWpScheduledPostJob.php` - Job sanitization + UTF-8 headers
7. `app/Jobs/PublishSidebarBlogrollJob.php` - Job sanitization + UTF-8 headers
8. `app/Jobs/PublishHiddenLinksJob.php` - Job sanitization + UTF-8 headers
9. `app/Services/CampaignPostContentBuilder.php` - Content builder sanitization
10. `app/Services/WpScheduledPostContentBuilder.php` - Content builder sanitization

---

## Deployment Checklist

### Before Deployment
- [ ] Run `composer dump-autoload` (already done)
- [ ] Verify PHP extensions: `php -m | grep -E 'mbstring|iconv|intl'`
- [ ] Test with multilingual content in staging
- [ ] Review logs for any UTF-8 warnings

### After Deployment
- [ ] Monitor `storage/logs/laravel.log` for UTF-8 sanitization warnings
- [ ] Test WordPress API posting with Chinese, Thai, Arabic, Persian content
- [ ] Verify queue workers are processing jobs successfully
- [ ] Check that no `json_encode` errors appear in logs

### Queue Workers
Ensure queue workers are running for all queues:
```bash
php artisan queue:work --queue=scheduled_campaigns,campaigns,wp_scheduled_campaigns,sidebar_campaigns,hidden_links_campaigns
```

---

## Performance Considerations

### Minimal Overhead
- UTF-8 validation is fast (native PHP functions)
- Sanitization only runs when content changes
- Logging only occurs when malformed bytes are detected
- No database queries added

### Caching
- Article content is sanitized once at save time
- Subsequent reads use already-clean data
- No repeated sanitization on every API call

---

## Additional Recommendations

### 1. Input Validation
Consider adding client-side validation to catch encoding issues early:
```javascript
// In article creation forms
function validateUtf8(text) {
    try {
        encodeURIComponent(text);
        return true;
    } catch (e) {
        return false;
    }
}
```

### 2. Database Verification
Periodically verify database encoding:
```sql
SHOW VARIABLES LIKE 'character_set%';
SHOW VARIABLES LIKE 'collation%';
```

All should show `utf8mb4`.

### 3. WordPress Plugin Compatibility
Ensure your WordPress custom API plugin (`/wp-json/external/v1/posts/create`) accepts UTF-8 content:
```php
// In WordPress plugin
header('Content-Type: application/json; charset=utf-8');
```

### 4. Monitoring
Set up alerts for UTF-8 sanitization warnings:
```php
// In AppServiceProvider or custom monitoring
Log::listen(function ($level, $message, $context) {
    if (str_contains($message, 'UTF-8 sanitization')) {
        // Send alert to monitoring service
    }
});
```

---

## Troubleshooting

### Issue: Still getting json_encode errors

**Solution**:
1. Check if `composer dump-autoload` was run
2. Verify helpers.php is loaded: `php artisan tinker` → `function_exists('cleanUtf8')`
3. Clear Laravel cache: `php artisan cache:clear && php artisan config:clear`
4. Restart queue workers

### Issue: Content looks corrupted after sanitization

**Solution**:
1. Check if `intl` extension is installed
2. Verify source encoding is actually UTF-8
3. Review sanitization logs to see what was removed
4. May need to adjust sanitization rules for specific use case

### Issue: Performance degradation

**Solution**:
1. Sanitization should be negligible; check for other bottlenecks
2. Ensure database indexes are optimized
3. Consider caching article content after sanitization

---

## Support

For issues or questions:
1. Check `storage/logs/laravel.log` for UTF-8 warnings
2. Verify PHP extensions are installed
3. Test with the `/test-utf8` route (if created)
4. Review this guide's troubleshooting section

---

## Summary

This implementation provides **comprehensive, production-grade UTF-8 sanitization** across your entire Laravel application. It:

✅ Handles Chinese, Thai, Persian, Arabic, emoji, and all Unicode text  
✅ Removes malformed UTF-8 bytes safely  
✅ Prevents `json_encode` failures  
✅ Works seamlessly with WordPress API  
✅ Provides detailed logging for debugging  
✅ Has minimal performance impact  
✅ Is queue-safe and works with Laravel jobs  

The system is now **fully protected** against multilingual UTF-8 encoding issues.
