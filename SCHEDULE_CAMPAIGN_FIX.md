# Schedule Campaign Publishing Fix

## Issue Summary

Schedule campaign posts were stuck in "publishing" status and never completing. Additionally, when posts did complete, they were published with **empty content** (only title, no body). This occurred after implementing rel attributes functionality in schedule post campaigns.

## Root Cause

**Primary Issue: Empty Content Due to Empty `<p>` Tag Detection**

The `buildContent` method in `PublishScheduledCampaignPostJob.php` had a critical bug when processing articles that use `<br>` tags instead of `<p>` tags:

1. Articles with `<br>` tags had a trailing empty `<p> </p>` tag
2. Regex `preg_match_all('/<p\b[^>]*>.*?<\/p>/is')` found this 1 empty paragraph
3. Since count > 0, code never entered the `<br>` splitting fallback logic
4. The loop processed the empty paragraph, skipped it (< 30 chars), placed no anchors
5. Final content was just `<p> </p>` → **published with empty body!**

**Secondary Issue: Infinite Loop Risk**

The original while loop had no safety mechanism:
- If ALL paragraphs were < 30 characters, loop would run forever
- Job would timeout silently, leaving posts stuck in "publishing" status

**Tertiary Issue: Missing Queue in Worker (Not Critical)**

The `composer.json` dev script didn't include `scheduled_campaigns` queue, but this only affects developers using `composer run dev`.

## Files Fixed

### 1. `app/Jobs/PublishScheduledCampaignPostJob.php`

**Fix 1: Detect and Handle Empty `<p>` Tags**
```php
// OLD: Only checked if count === 0
if (count($paragraphs) === 0) {
    // Split by <br> tags...
}

// NEW: Check if paragraphs have meaningful content
$hasMeaningfulContent = false;
foreach ($paragraphs as $p) {
    $stripped = trim(strip_tags($p));
    if (mb_strlen($stripped) > 10) { // At least 10 chars
        $hasMeaningfulContent = true;
        break;
    }
}

// Fallback if no paragraphs OR only empty paragraphs
if (count($paragraphs) === 0 || !$hasMeaningfulContent) {
    // Split by <br> tags and clean empty <p> tags
    $parts = preg_split('/<br\s*\/?>/i', $html);
    $paragraphs = [];
    foreach ($parts as $part) {
        $part = trim($part);
        // Remove empty <p></p> tags
        $part = preg_replace('/<p\b[^>]*>\s*<\/p>/i', '', $part);
        $part = trim($part);
        if ($part !== '') {
            $paragraphs[] = '<p>' . $part . '</p>';
        }
    }
}
```

**Fix 2: Infinite Loop Prevention (Multi-Pass System)**
```php
// NEW: Multi-pass with safety limit
$minLength = 30;
$maxPasses = 2;
$currentPass = 0;

while ($pairIndex < $anchorCount && $currentPass < $maxPasses) {
    $placedThisPass = false;
    
    foreach ($paraIndexes as $p) {
        // Pass 1: Prefer paragraphs >= 30 chars
        // Pass 2: Accept any paragraph with text
        if ($currentPass === 0 && $textLen < $minLength) {
            continue;
        }
        if ($textLen < 1) {
            continue;
        }
        
        $pairIndex++;
        $placedThisPass = true;
        // ... insert anchor ...
    }
    
    // If no anchors placed, try next pass with relaxed rules
    if (!$placedThisPass) {
        $currentPass++;
    }
}
```

### 2. `app/Jobs/PublishCampaignPostJob.php`

Applied the same empty `<p>` tag detection fix to maintain consistency across all campaign types.

### 3. `composer.json`

Added missing schedule campaign queues to dev worker (optional improvement for developers using `composer run dev`).

## Testing Performed

1. ✅ Identified 2 posts stuck in "publishing" status (posts 591, 592)
2. ✅ Discovered content being built as empty `<p> </p>` (8 chars)
3. ✅ Fixed empty `<p>` tag detection logic
4. ✅ Fixed infinite loop prevention logic
5. ✅ Tested buildContent method → generated 1747 chars with proper anchors
6. ✅ Manually executed job for post 591 → Success (Remote ID: 375)
7. ✅ Manually executed job for post 592 → Success (Remote ID: 321)
8. ✅ Campaign 62 status updated to "completed" (2/2 posts successful)

## Remediation of Already-Published Posts

Posts 591 and 592 were initially published with **empty content** due to the bug. After fixing the code, we updated the remote WordPress posts:

### Post 591 (blackbuddy.co.uk)
- **Remote ID:** 375
- **Original Content:** `<p> </p>` (empty)
- **Updated Content:** 1747 characters with proper anchor links
- **Update Status:** ✅ Success

### Post 592 (bitopower.co.uk)
- **Remote ID:** 321
- **Original Content:** `<p> </p>` (empty)
- **Updated Content:** 1727 characters with proper anchor links
- **Update Status:** ✅ Success

**How to Update Similar Cases:**

If you find other posts published with empty content, use this approach:

```php
// 1. Rebuild content with fixed method
$post = ScheduleCampaignPost::find($postId);
$post->load(['campaignDomain.domain', 'campaignArticle.article']);

$job = new PublishScheduledCampaignPostJob($postId);
$reflection = new ReflectionClass($job);
$method = $reflection->getMethod('buildContent');
$method->setAccessible(true);
[$title, $content] = $method->invoke($job, $post);

// 2. Update on WordPress via API
$endpoint = $domain . '/wp-json/external/v1/posts/update/' . $remoteId;
$payload = [
    'title' => cleanUtf8($title),
    'content' => cleanUtf8($content),
    'status' => 'publish',
    'api_key' => $apiKey,
];

Http::withoutVerifying()
    ->timeout(180)
    ->post($endpoint, $payload);
```

## How to Start Queue Worker Locally

Since you don't use `composer run dev`, start the queue worker manually with all required queues:

```bash
php artisan queue:work --queue=scheduled_campaigns,default,deletions,campaigns,sidebar_campaigns,scheduled_sidebar_campaigns,wp_scheduled_campaigns,wp_scheduled_sync --tries=3 --timeout=180
```

Or use queue:listen for auto-reload during development:

```bash
php artisan queue:listen --queue=scheduled_campaigns,default,deletions,campaigns,sidebar_campaigns --tries=3 --timeout=180
```

## Production Considerations

1. **Supervisor Configuration**: Ensure production workers listen to ALL scheduled campaign queues
2. **Timeout**: Set worker timeout to at least 180 seconds for WordPress API requests
3. **Retry Strategy**: Keep `--tries=3` to handle transient network issues
4. **Article Format**: System now handles both `<p>` tags and `<br>` tags in article content

## Related Code Patterns

This fix follows the same pattern used in:
- `app/Services/CampaignPostContentBuilder.php` (main service)
- `app/Services/WpScheduledPostContentBuilder.php` (WP scheduled posts)

All content building logic should:
1. Try to extract `<p>` tags first
2. Fall back to splitting by `<br>` tags
3. Use multi-pass anchor insertion with safety limits
4. Always use `mb_*` functions for UTF-8 safety

## Prevention

To prevent similar issues in the future:

### 1. Test with Various Article Formats
Ensure test data includes:
- Articles with only `<p>` tags
- Articles with only `<br>` tags
- Articles with mixed `<p>` and `<br>` tags
- Articles with empty `<p></p>` tags at the end
- Articles with very short paragraphs (< 30 chars)

### 2. Always Add Safety Limits to While Loops
When processing user content in jobs:
```php
// BAD: No exit condition for edge cases
while ($condition) {
    // Could loop forever
}

// GOOD: Maximum iterations or multi-pass system
$maxAttempts = 100;
$attempts = 0;
while ($condition && $attempts < $maxAttempts) {
    $attempts++;
    // Process...
}
```

### 3. Verify Published Content
After publishing, spot-check remote WordPress posts to ensure:
- Content is not empty
- Anchor links are present
- Formatting is preserved
- rel attributes are correct

### 4. Monitor Queue Health
Use Laravel's built-in queue monitoring:
```bash
# Check for stuck jobs
php artisan queue:monitor scheduled_campaigns --max=100

# Check failed jobs
php artisan queue:failed

# Restart workers after code changes
php artisan queue:restart
```

### 5. Add Logging for Content Building
Consider adding debug logging in buildContent:
```php
Log::info('Building content for post', [
    'post_id' => $post->id,
    'paragraphs_count' => count($paragraphs),
    'has_meaningful_content' => $hasMeaningfulContent,
    'pairs_count' => count($pairs),
    'final_content_length' => mb_strlen($html),
]);
```

## Related Code Patterns

This fix follows the same pattern used in:
- `app/Services/CampaignPostContentBuilder.php` (main service)
- `app/Services/WpScheduledPostContentBuilder.php` (WP scheduled posts)

All content building logic should:
1. Try to extract `<p>` tags first
2. **Check if extracted paragraphs have meaningful content**
3. Fall back to splitting by `<br>` tags if needed
4. Use multi-pass anchor insertion with safety limits
5. Always use `mb_*` functions for UTF-8 safety
6. Clean empty `<p></p>` tags before processing

## Summary of Changes

### Critical Fixes
1. **Empty Content Bug** - Posts were published with only `<p> </p>` because empty `<p>` tags were treated as valid content
2. **Infinite Loop Bug** - Jobs could timeout when all paragraphs were too short

### Code Changes
- `PublishScheduledCampaignPostJob.php` - Added meaningful content detection + multi-pass system
- `PublishCampaignPostJob.php` - Applied same fixes for consistency
- `composer.json` - Added schedule campaign queues (optional improvement)

### Remediation
- Posts 591 and 592 updated on WordPress with correct content
- Verified 1700+ character content with proper anchor links now live

### Impact
- **Before:** Posts published with empty bodies, stuck in publishing status
- **After:** Posts publish with full content, no timeouts, no stuck jobs

---

**Date Fixed:** 2026-06-17  
**Campaign ID Tested:** 62  
**Posts Fixed:** 591, 592  
**Posts Updated on WordPress:** 375 (blackbuddy.co.uk), 321 (bitopower.co.uk)
