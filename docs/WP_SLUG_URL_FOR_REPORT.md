# Showing slug-based URLs (instead of ?p=ID) in WP Scheduled Report

The report shows **Remote URL** from the WordPress create response. To show **slug titles** (e.g. `https://domain.com/my-post-title/`) instead of `https://domain.com/?p=2174`, use one of the options below.

---

## Option 1: Return permalink from your WordPress create endpoint (recommended)

Edit the endpoint that handles **POST** `.../wp-json/external/v1/posts/create` so the **JSON response** includes the permalink (slug URL).

After you create the post in WordPress, get the permalink and add it to the response. The Laravel app already looks for these keys (in order):

- `link`  
- `permalink`  
- `url`  
- then falls back to `remote_url` (?p=ID)

**Example response from your WordPress API:**

```json
{
  "success": true,
  "post_id": 2174,
  "status": "future",
  "remote_url": "https://izmircasino148.com/?p=2174",
  "link": "https://izmircasino148.com/accessibility-features-inclusive-future-online-gaming/"
}
```

In PHP (WordPress), after creating the post you can do:

```php
$post_id = wp_insert_post( ... );
$permalink = get_permalink( $post_id );
// Then in your JSON response:
// 'link' => $permalink,   // or 'permalink' or 'url'
```

Once the create response includes `link` (or `permalink` or `url`) with the slug URL, the report will show that URL with no extra requests or WP settings.

---

## Option 2: WordPress settings + our fallback (no endpoint change)

If you **don’t** change the create endpoint, the app will still try to get a slug URL by calling:

**GET** `https://yourdomain.com/wp-json/wp/v2/posts/{post_id}`

and using the `link` field from that response. For this to work:

1. **WordPress permalink structure**  
   - **Settings → Permalinks**  
   - Use **“Post name”** (or another “pretty” structure), e.g.  
     `https://domain.com/sample-post/`  
   - Not “Plain” (?p=123), otherwise there is no slug URL to return.

2. **REST API readable**  
   - The route `/wp-json/wp/v2/posts/{id}` must be readable (e.g. public or with your API key), so the Laravel app can fetch the post and read `link`.  
   - If that endpoint is behind login or returns 401/403, the fallback will not get the permalink and the report will keep showing ?p=ID.

---

## Summary

| Goal                         | What to do |
|-----------------------------|------------|
| Slug URL in report          | Either return `link`/`permalink`/`url` from the **create** endpoint, or rely on our GET fallback to `/wp/v2/posts/{id}` and ensure permalinks + REST are set up. |
| Edit endpoint               | Add permalink to create response (Option 1). |
| Only WP config              | Set Permalinks to “Post name” and ensure REST posts are readable (Option 2). |

Recommended: **Option 1** — add the permalink to the create endpoint response so the report always gets the slug URL in one place, with no extra GET or REST visibility requirements.
