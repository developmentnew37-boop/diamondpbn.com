# Multi-Bulk Keyword/URL Testing Guide

## What Was Fixed:

1. ✅ Added `multi_bulk` to validation rules in 3 controllers
2. ✅ Added `additional_links` processing to merge Pair 2-5 with Pair 1
3. ✅ Updated `$isMultiple` logic to treat `multi_bulk` same as `multiple`

## Testing Steps:

### Test 1: Create Campaign with 2 Pairs

1. **Go to:** Schedule Campaign → Create New
2. **Set Post Quantity:** 5
3. **Select Articles:** Choose 5 articles
4. **Select Domains:** Choose 5 domains
5. **Keywords Section:**
   - Click **Multi-bulk** tab
   - **Pair 1 (Required):**
     ```
     URLs (5 lines):
     https://example1.com
     https://example2.com
     https://example3.com
     https://example4.com
     https://example5.com
     
     Keywords (5 lines):
     keyword1
     keyword2
     keyword3
     keyword4
     keyword5
     ```
   - **Pair 2 (Optional):**
     ```
     URLs (5 lines):
     https://link2-1.com
     https://link2-2.com
     https://link2-3.com
     https://link2-4.com
     https://link2-5.com
     
     Keywords (5 lines):
     anchor2-1
     anchor2-2
     anchor2-3
     anchor2-4
     anchor2-5
     ```
6. **Click:** "Save change" button
7. **Verify Preview Table:** Should show both pairs with "[Additional]" label
8. **Submit Campaign**

### Test 2: Verify Database

After creating the campaign, check the database:

```sql
SELECT id, keyword, url, keyword_type, url_type 
FROM schedule_campaign_articles 
WHERE schedule_campaign_id = [YOUR_CAMPAIGN_ID]
LIMIT 1;
```

**Expected Result:**
- `keyword_type`: `json`
- `url_type`: `json`
- `keyword`: `["keyword1","anchor2-1"]` (JSON array with 2 items)
- `url`: `["https://example1.com","https://link2-1.com"]` (JSON array with 2 items)

### Test 3: Verify Published Post

1. Run queue worker: `php artisan queue:work --queue=scheduled_campaigns`
2. Wait for post to publish
3. Check WordPress post content
4. **Expected:** Post should contain **2 anchor links**:
   - `<a href="https://example1.com">keyword1</a>`
   - `<a href="https://link2-1.com">anchor2-1</a>`

## Common Issues:

### Issue 1: "Undefined using multi bulk"
**Cause:** JavaScript can't find multi-bulk elements
**Fix:** 
1. Hard refresh browser (Ctrl+Shift+R)
2. Clear browser cache
3. Check browser console (F12) for specific error

### Issue 2: Only 1 link appears in post
**Cause:** Controllers not processing additional_links
**Fix:** Already fixed in this update - make sure you pulled latest code

### Issue 3: Validation error "keywordmethod must be in..."
**Cause:** Controller validation doesn't include multi_bulk
**Fix:** Already fixed in this update

## Verification Checklist:

- [ ] Can select Multi-bulk tab
- [ ] Can fill Pair 1 with 5 URLs and 5 keywords
- [ ] Can fill Pair 2 with 5 URLs and 5 keywords
- [ ] Preview table shows both pairs with "[Additional]" label
- [ ] Campaign submits without validation errors
- [ ] Database shows JSON arrays with 2 items each
- [ ] Published post contains 2 anchor links

## If Still Getting Errors:

1. **Open Browser Console (F12)**
2. **Go to Console tab**
3. **Try creating campaign**
4. **Copy the full error message**
5. **Share the error with developer**

The error will look like:
```
Uncaught TypeError: Cannot read property 'value' of null
    at create-schedule-campaign.js:2817
```

This tells us exactly which line is failing.
