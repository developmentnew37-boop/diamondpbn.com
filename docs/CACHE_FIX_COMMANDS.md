# Cache Fix Commands for Live Server

## Run these commands on your live server:

```bash
# 1. Clear ALL caches (most important)
php artisan cache:clear

# 2. Clear config cache
php artisan config:clear

# 3. Clear compiled views
php artisan view:clear

# 4. Clear route cache
php artisan route:clear

# 5. Clear application cache (if using Redis/Memcached)
php artisan cache:flush

# 6. Restart queue workers (if running)
php artisan queue:restart

# 7. Optimize autoloader
composer dump-autoload

# 8. Re-cache optimizations
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Verify the Fix

After running the commands above, test:

1. **Go to Create Campaign page** (`/admin/campaign/create`)
2. **Check Article Languages dropdown** - should show languages with correct counts
3. **Check Article Sets** - should show correct article counts
4. **Try creating a campaign** - article selection should work properly

## If Still Not Working

If the issue persists, the cache might be stored in Redis/Memcached instead of file cache.

### Check your cache driver:
```bash
# Check .env file
grep CACHE_DRIVER .env
```

### If using Redis:
```bash
redis-cli FLUSHALL
```

### If using Memcached:
```bash
echo 'flush_all' | nc localhost 11211
```

## Manual Cache Key Deletion

If you need to delete specific cache keys:

```bash
php artisan tinker

# Delete campaign create cache for specific admin
Cache::forget('campaign_create_data_1');  // Replace 1 with admin ID
Cache::forget('campaign_create_data_2');  // For admin ID 2
// etc...

# Or delete all keys matching pattern (if using Redis)
Cache::flush();

exit
```

## Prevention

To avoid this in future deployments:

1. Always run `php artisan cache:clear` AFTER uploading new files
2. If using queue workers, always restart them: `php artisan queue:restart`
3. Consider adding this to your deployment script
