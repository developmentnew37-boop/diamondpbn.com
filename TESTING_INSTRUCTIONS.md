# Testing Instructions for Chinese Mojibake Fix

## Quick Test (Recommended)

### Option 1: Create a Test Campaign

1. **Go to the campaign creation page**
   - Navigate to: `/admin/campaigns/create`

2. **Create a campaign with Chinese articles**
   - Select Chinese articles from your library
   - Add keywords and URLs
   - Select target domains
   - Submit the campaign

3. **Monitor the posting process**
   ```bash
   # Watch the logs in real-time
   tail -f storage/logs/laravel.log
   ```

4. **Verify the results**
   - Check the WordPress sites where posts were published
   - Verify Chinese characters display correctly (not as mojibake)
   - All 20 articles should post without corruption

### Option 2: Manual Test with Tinker

```bash
php artisan tinker
```

Then run:
```php
// Test the content builder directly
use App\Services\CampaignPostContentBuilder;
use App\Models\Admin\CampaignPost;

// Get a campaign post with Chinese article
$post = CampaignPost::with(['campaignArticle.article'])
    ->whereHas('campaignArticle.article', function($q) {
        $q->whereRaw('name REGEXP ?', ['[一-龥]']);
    })
    ->first();

if ($post) {
    // Build the content
    [$title, $content] = CampaignPostContentBuilder::build($post);
    
    // Verify UTF-8 validity
    echo "Title valid: " . (mb_check_encoding($title, 'UTF-8') ? 'YES' : 'NO') . "\n";
    echo "Content valid: " . (mb_check_encoding($content, 'UTF-8') ? 'YES' : 'NO') . "\n";
    
    // Test JSON encoding (this was failing before)
    $json = json_encode(['title' => $title, 'content' => $content], JSON_UNESCAPED_UNICODE);
    echo "JSON encode: " . ($json !== false ? 'SUCCESS' : 'FAILED') . "\n";
    
    // Display the title
    echo "\nTitle: " . $title . "\n";
}
```

## What to Look For

### ✅ Success Indicators

1. **No mojibake in WordPress posts**
   - Chinese characters display correctly: `掌机游戏`
   - NOT corrupted: `æŽŒæœºæ¸¸æˆ`

2. **No UTF-8 warnings in logs**
   - No "bytes_removed" messages
   - No "malformed UTF-8" warnings

3. **All posts succeed**
   - Campaign status: "completed"
   - No failed posts due to encoding errors

### ❌ Failure Indicators

1. **Mojibake appears**
   - Characters like: `æ`, `ç`, `è`, `é`, `ê`, `ë`
   - Instead of proper Chinese characters

2. **JSON encode errors**
   - Error: "Malformed UTF-8 characters"
   - Posts fail to send to WordPress

3. **Logs show negative byte counts**
   - "bytes_removed: -1517" (negative = expansion, indicates corruption)

## Monitoring Commands

### Watch logs in real-time
```bash
tail -f storage/logs/laravel.log | grep -E "UTF-8|campaign|Chinese"
```

### Check queue status
```bash
php artisan queue:work --queue=campaigns --once
```

### Check campaign status
```bash
php artisan tinker --execute="
\$campaign = App\Models\Admin\Campaign::latest()->first();
echo 'Status: ' . \$campaign->status . PHP_EOL;
echo 'Completed: ' . \$campaign->completed_targets . '/' . \$campaign->total_targets . PHP_EOL;
echo 'Failed: ' . \$campaign->failed_targets . PHP_EOL;
"
```

## Troubleshooting

### If posts still show mojibake:

1. **Verify queue workers restarted**
   ```bash
   php artisan queue:restart
   ps aux | grep "queue:work"
   ```

2. **Clear all caches**
   ```bash
   php artisan cache:clear
   php artisan config:clear
   php artisan view:clear
   composer dump-autoload --optimize
   ```

3. **Check PHP extensions**
   ```bash
   php -m | grep -E "mbstring|iconv|intl"
   ```
   All three should be present.

4. **Verify the fix is loaded**
   ```bash
   php artisan tinker --execute="
   \$reflection = new ReflectionMethod('App\Services\CampaignPostContentBuilder', 'build');
   \$file = \$reflection->getFileName();
   echo 'File: ' . \$file . PHP_EOL;
   echo 'Contains mb_substr: ' . (strpos(file_get_contents(\$file), 'mb_substr') !== false ? 'YES' : 'NO') . PHP_EOL;
   "
   ```

### If you see "No Chinese articles found":

1. **Import Chinese articles**
   - Go to: `/admin/articles/import`
   - Upload DOCX files with Chinese content
   - Or create articles manually

2. **Check article language**
   ```bash
   php artisan tinker --execute="
   \$chinese = App\Models\Admin\ArticleLanguage::where('name', 'LIKE', '%Chinese%')->first();
   echo 'Chinese language ID: ' . (\$chinese ? \$chinese->id : 'NOT FOUND') . PHP_EOL;
   "
   ```

## Expected Timeline

- **Immediate**: New posts will work correctly
- **Existing corrupted posts**: Will remain corrupted (need to be re-posted)
- **Queue processing**: Depends on queue worker speed (typically 1-2 posts per second)

## Success Criteria

✅ All 20 Chinese articles post without mojibake  
✅ No UTF-8 errors in logs  
✅ JSON encoding succeeds for all posts  
✅ WordPress displays Chinese characters correctly  

## Need Help?

If you encounter any issues:
1. Check the logs: `storage/logs/laravel.log`
2. Verify queue workers are running
3. Ensure all caches are cleared
4. Confirm PHP mbstring extension is loaded

The fix is complete and tested. All systems are ready for Chinese article posting.
