# Diamond PBN API - Complete Data Flow Documentation

## Base Information

**Plugin Name:** Diamond Pbn (External API Manager)  
**Version:** 7.8.0  
**Base URL:** `https://your-site.com/wp-json/external/v1/`  
**Namespace:** `external/v1`

---

## Authentication

All endpoints (except `/status`) require API authentication using one of these methods:

### Method 1: HTTP Header (Recommended)
```
X-External-API-Key: YOUR_PLAINTEXT_KEY
```

### Method 2: Query Parameter
```
?api_key=YOUR_PLAINTEXT_KEY
```

### Authentication Response Errors
```json
{
  "code": "no_key",
  "message": "Missing API key",
  "data": {
    "status": 403
  }
}
```

```json
{
  "code": "bad_key",
  "message": "Invalid API key",
  "data": {
    "status": 403
  }
}
```

---

## Standard Response Format

Most endpoints return responses in this format:

```json
{
  "success": true,
  "message": "OK",
  "data": {...}
}
```

---

## Endpoints

### 1. Health Check

#### GET `/status`
Check API connection status.

**Authentication:** None required

**Request Example:**
```bash
curl -X GET "https://your-site.com/wp-json/external/v1/status"
```

**Response:**
```json
{
  "status": true,
  "message": "Connected"
}
```

---

## Posts Endpoints

### 2. List Posts

#### GET `/posts`
Fetch a list of posts with filtering options.

**Authentication:** Required

**Query Parameters:**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `post_type` | string | `post` | Post type to fetch |
| `status` | string | `any` | Post status (publish, draft, future, any) |
| `limit` | integer | `50` | Number of posts to retrieve |
| `offset` | integer | `0` | Offset for pagination |

**Request Example:**
```bash
curl -X GET "https://your-site.com/wp-json/external/v1/posts?limit=10&status=publish&api_key=YOUR_KEY"
```

**Response:**
```json
[
  {
    "ID": 123,
    "post_title": "Example Post",
    "post_content": "<p>Content here</p>",
    "post_status": "publish",
    "post_type": "post",
    "post_date": "2025-01-15 10:30:00",
    ...
  }
]
```

---

### 3. Get Single Post

#### GET `/posts/{id}`
Fetch a single post by ID.

**Authentication:** Required

**URL Parameters:**
- `id` (integer, required) - Post ID

**Request Example:**
```bash
curl -X GET "https://your-site.com/wp-json/external/v1/posts/25?api_key=YOUR_KEY"
```

**Response:**
```json
{
  "ID": 25,
  "post_title": "Single Post Title",
  "post_content": "<p>Post content</p>",
  "post_status": "publish",
  ...
}
```

**Error Response:**
```json
{
  "code": "not_found",
  "message": "Post not found",
  "data": {
    "status": 404
  }
}
```

---

### 4. Create Post

#### POST `/posts/create`
Create a new post with optional scheduling.

**Authentication:** Required

**Request Body:**
```json
{
  "title": "My Post Title",
  "content": "<p>This is the content</p>",
  "status": "publish",
  "post_type": "post",
  "categories": [3, 10],
  "tags": ["casino", "betting"],
  "schedule_time": "2025-01-20 12:30:00",
  "is_sticky": true
}
```

**Body Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `title` | string | Yes | Post title |
| `content` | string | Yes | Post content (HTML allowed) |
| `status` | string | No | Post status (default: `publish`) |
| `post_type` | string | No | Post type (default: `post`) |
| `categories` | array | No | Array of category IDs |
| `tags` | array | No | Array of tag names or IDs |
| `schedule_time` | string | No | Future datetime (Y-m-d H:i:s format) - will set status to `future` |
| `is_sticky` | boolean | No | Make post sticky (default: false) |

**Request Example:**
```bash
curl -X POST "https://your-site.com/wp-json/external/v1/posts/create" \
  -H "Content-Type: application/json" \
  -H "X-External-API-Key: YOUR_KEY" \
  -d '{
    "title": "New Casino Review",
    "content": "<p>Great casino site!</p>",
    "status": "publish",
    "categories": [5],
    "tags": ["casino", "review"],
    "is_sticky": false
  }'
```

**Success Response:**
```json
{
  "success": true,
  "post_id": 156,
  "status": "publish",
  "is_sticky": false,
  "remote_url": "https://your-site.com/2025/01/new-casino-review/"
}
```

---

### 5. Update Post

#### POST `/posts/update/{id}`
Update an existing post.

**Authentication:** Required

**URL Parameters:**
- `id` (integer, required) - Post ID to update

**Request Body:**
```json
{
  "title": "Updated Title",
  "content": "<p>Updated Content</p>",
  "status": "draft",
  "schedule_time": "2025-01-21 14:00:00"
}
```

**Body Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `title` | string | No | New post title |
| `content` | string | No | New post content |
| `status` | string | No | New post status |
| `schedule_time` | string | No | Future datetime for scheduling |

**Request Example:**
```bash
curl -X POST "https://your-site.com/wp-json/external/v1/posts/update/25" \
  -H "Content-Type: application/json" \
  -H "X-External-API-Key: YOUR_KEY" \
  -d '{
    "title": "Updated Post Title",
    "status": "publish"
  }'
```

**Success Response:**
```json
{
  "success": true,
  "post_id": 25,
  "status": "publish"
}
```

**Error Response:**
```json
{
  "code": "not_found",
  "message": "Post not found",
  "data": {
    "status": 404
  }
}
```

---

### 6. Delete Post

#### DELETE `/posts/delete/{id}`
Permanently delete a post.

**Authentication:** Required

**URL Parameters:**
- `id` (integer, required) - Post ID to delete

**Request Example:**
```bash
curl -X DELETE "https://your-site.com/wp-json/external/v1/posts/delete/25?api_key=YOUR_KEY"
```

**Success Response:**
```json
{
  "success": true
}
```

**Failure Response:**
```json
{
  "success": false
}
```

---

### 7. Make Post Sticky

#### POST `/posts/sticky/{id}`
Mark a post as sticky.

**Authentication:** Required

**URL Parameters:**
- `id` (integer, required) - Post ID

**Request Body:** Empty object `{}`

**Request Example:**
```bash
curl -X POST "https://your-site.com/wp-json/external/v1/posts/sticky/25" \
  -H "Content-Type: application/json" \
  -H "X-External-API-Key: YOUR_KEY" \
  -d '{}'
```

**Success Response:**
```json
{
  "success": true,
  "sticky": true
}
```

---

### 8. Remove Sticky from Post

#### POST `/posts/unsticky/{id}`
Remove sticky status from a post.

**Authentication:** Required

**URL Parameters:**
- `id` (integer, required) - Post ID

**Request Body:** Empty object `{}`

**Request Example:**
```bash
curl -X POST "https://your-site.com/wp-json/external/v1/posts/unsticky/25" \
  -H "Content-Type: application/json" \
  -H "X-External-API-Key: YOUR_KEY" \
  -d '{}'
```

**Success Response:**
```json
{
  "success": true,
  "sticky": false
}
```

---

## Scheduler Queue Endpoints

### 9. Add Scheduled Post

#### POST `/schedule/add`
Add a post to the scheduling queue (processed by cron every 5 minutes).

**Authentication:** Required

**Request Body:**
```json
{
  "title": "Scheduled Post Title",
  "content": "<p>Post content here</p>",
  "run_at": "2025-01-29 17:00:00",
  "status": "publish",
  "post_type": "post"
}
```

**Body Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `title` | string | Yes | Post title |
| `content` | string | Yes | Post content |
| `run_at` | string | Yes | When to publish (Y-m-d H:i:s format) |
| `status` | string | No | Post status (default: `publish`) |
| `post_type` | string | No | Post type (default: `post`) |

**Request Example:**
```bash
curl -X POST "https://your-site.com/wp-json/external/v1/schedule/add" \
  -H "Content-Type: application/json" \
  -H "X-External-API-Key: YOUR_KEY" \
  -d '{
    "title": "Future Post",
    "content": "<p>This will be published later</p>",
    "run_at": "2025-02-01 09:00:00",
    "status": "publish"
  }'
```

**Success Response:**
```json
{
  "success": true,
  "id": "sched_678abc123def456.78901234"
}
```

**Error Response:**
```json
{
  "code": "bad_time",
  "message": "Invalid run_at time",
  "data": {
    "status": 400
  }
}
```

---

### 10. List Scheduled Posts

#### GET `/schedule/list`
List all queued scheduled posts.

**Authentication:** Required

**Request Example:**
```bash
curl -X GET "https://your-site.com/wp-json/external/v1/schedule/list?api_key=YOUR_KEY"
```

**Response:**
```json
[
  {
    "id": "sched_678abc123def456.78901234",
    "title": "Future Post",
    "content": "<p>Content</p>",
    "run_at": "2025-02-01 09:00:00",
    "timestamp": 1738401600,
    "post_type": "post",
    "status": "publish",
    "created_at": "2025-01-15 08:30:00"
  }
]
```

---

### 11. Delete Scheduled Post

#### DELETE `/schedule/delete/{id}`
Remove a post from the scheduling queue.

**Authentication:** Required

**URL Parameters:**
- `id` (string, required) - Schedule ID from `/schedule/list`

**Request Example:**
```bash
curl -X DELETE "https://your-site.com/wp-json/external/v1/schedule/delete/sched_678abc123def456.78901234?api_key=YOUR_KEY"
```

**Success Response:**
```json
{
  "success": true
}
```

**Error Response:**
```json
{
  "code": "not_found",
  "message": "Queue item not found. Use the job id from GET /schedule/list.",
  "data": {
    "status": 404
  }
}
```

---

## Custom Blogroll Endpoints

### 12. Get Blogroll Items

#### GET `/blogroll`
Fetch all blogroll items.

**Authentication:** Required

**Dependency Check:** May require Custom Blogroll plugin active (configurable in settings)

**Request Example:**
```bash
curl -X GET "https://your-site.com/wp-json/external/v1/blogroll?api_key=YOUR_KEY"
```

**Response:**
```json
{
  "success": true,
  "message": "OK",
  "data": [
    {
      "id": "blog_678abc123def456.78901234",
      "keyword": "Example Keyword",
      "link": "https://example.com",
      "rel": ["nofollow", "sponsored"],
      "timestamp": 1737012345
    }
  ]
}
```

**Dependency Error:**
```json
{
  "code": "missing_dep",
  "message": "Required plugin 'Custom Blogroll' not installed/active (or option missing).",
  "data": {
    "status": 424
  }
}
```

---

### 13. Add Blogroll Item

#### POST `/blogroll/add`
Add a new blogroll item.

**Authentication:** Required

**Request Body:**
```json
{
  "keyword": "Example Keyword",
  "link": "https://example.com",
  "rel": ["nofollow", "sponsored"]
}
```

**Body Parameters (3-Priority rel System):**

| Parameter | Type | Priority | Description |
|-----------|------|----------|-------------|
| `keyword` | string | - | Required. Anchor text/keyword |
| `link` or `url` | string | - | Required. Target URL |
| `rel_attr` | string | 1 (Highest) | Space-separated rel values (supports ANY custom values)<br>Example: `"nofollow sponsored ugc"` |
| `rel` | array or string | 2 | Array of rel values or space-separated string<br>Example: `["nofollow", "sponsored"]` |
| `nofollow` or `no_follow` | boolean | 3 (Fallback) | Add `nofollow` to rel |
| `sponsored` or `sponsor` | boolean | 3 (Fallback) | Add `sponsored` to rel |
| `ugc` | boolean | 3 (Fallback) | Add `ugc` to rel |
| `noopener` | boolean | 3 (Fallback) | Add `noopener` to rel |
| `noreferrer` | boolean | 3 (Fallback) | Add `noreferrer` to rel |

**Priority System Explanation:**
1. If `rel_attr` is provided → Use it (supports custom values like "external", "bookmark")
2. Else if `rel` array/string is provided → Use it
3. Else build rel from individual boolean fields (backward compatibility)

**Request Examples:**

**Using rel_attr (Most Flexible - Priority 1):**
```bash
curl -X POST "https://your-site.com/wp-json/external/v1/blogroll/add" \
  -H "Content-Type: application/json" \
  -H "X-External-API-Key: YOUR_KEY" \
  -d '{
    "keyword": "Casino Site",
    "link": "https://casino-example.com",
    "rel_attr": "nofollow sponsored external"
  }'
```

**Using rel array (Priority 2):**
```bash
curl -X POST "https://your-site.com/wp-json/external/v1/blogroll/add" \
  -H "Content-Type: application/json" \
  -H "X-External-API-Key: YOUR_KEY" \
  -d '{
    "keyword": "Casino Site",
    "link": "https://casino-example.com",
    "rel": ["nofollow", "sponsored"]
  }'
```

**Using boolean fields (Priority 3 - Backward Compatibility):**
```bash
curl -X POST "https://your-site.com/wp-json/external/v1/blogroll/add" \
  -H "Content-Type: application/json" \
  -H "X-External-API-Key: YOUR_KEY" \
  -d '{
    "keyword": "Casino Site",
    "link": "https://casino-example.com",
    "nofollow": true,
    "sponsored": true
  }'
```

**Success Response:**
```json
{
  "success": true,
  "message": "OK",
  "data": [
    {
      "id": "blog_new123456.78901234",
      "keyword": "Casino Site",
      "link": "https://casino-example.com",
      "rel": ["nofollow", "sponsored"],
      "timestamp": 1737012345
    },
    ...
  ]
}
```

**Error Response:**
```json
{
  "code": "invalid",
  "message": "Keyword and URL are required",
  "data": {
    "status": 400
  }
}
```

---

### 14. Update Blogroll Item

#### POST `/blogroll/update/{id}`
Update an existing blogroll item.

**Authentication:** Required

**URL Parameters:**
- `id` (string, required) - Item ID from `/blogroll` response

**Request Body:**
```json
{
  "keyword": "New Keyword",
  "link": "https://newlink.com",
  "rel": ["nofollow", "ugc"]
}
```

**Body Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `keyword` | string | No | New keyword/anchor text |
| `link` or `url` | string | No | New URL |
| `rel_attr` | string | No | Priority 1: Space-separated rel values |
| `rel` | array/string | No | Priority 2: Array or space-separated string |
| Boolean fields | boolean | No | Priority 3: Individual rel flags |

**Note:** rel follows the same 3-priority system as Add endpoint. Only processes rel if at least one rel-related parameter is provided.

**Request Example:**
```bash
curl -X POST "https://your-site.com/wp-json/external/v1/blogroll/update/blog_678abc123def456.78901234" \
  -H "Content-Type: application/json" \
  -H "X-External-API-Key: YOUR_KEY" \
  -d '{
    "keyword": "Updated Casino",
    "rel": ["nofollow"]
  }'
```

**Success Response:**
```json
{
  "success": true,
  "message": "OK",
  "data": [
    {
      "id": "blog_678abc123def456.78901234",
      "keyword": "Updated Casino",
      "link": "https://example.com",
      "rel": ["nofollow"],
      "timestamp": 1737012345
    },
    ...
  ]
}
```

**Error Response:**
```json
{
  "success": false,
  "message": "Item not found. Use the item id from GET /blogroll.",
  "data": []
}
```

---

### 15. Delete Blogroll Item

#### DELETE `/blogroll/delete/{id}`
Delete a blogroll item.

**Authentication:** Required

**URL Parameters:**
- `id` (string, required) - Item ID from `/blogroll` response

**Request Example:**
```bash
curl -X DELETE "https://your-site.com/wp-json/external/v1/blogroll/delete/blog_678abc123def456.78901234?api_key=YOUR_KEY"
```

**Success Response:**
```json
{
  "success": true,
  "message": "Deleted successfully.",
  "data": []
}
```

**Error Response:**
```json
{
  "success": false,
  "message": "Item not found. Use the item id from GET /blogroll.",
  "data": []
}
```

---

## Hidden Links Endpoints

### 16. Get Hidden Links

#### GET `/hidden-links`
Fetch all hidden links.

**Authentication:** Required

**Dependency Check:** May require Hidden Links Manager plugin active (configurable in settings)

**Request Example:**
```bash
curl -X GET "https://your-site.com/wp-json/external/v1/hidden-links?api_key=YOUR_KEY"
```

**Response:**
```json
{
  "success": true,
  "message": "OK",
  "data": [
    {
      "id": "hid_678abc123def456.78901234",
      "keyword": "casino online",
      "link": "https://example.com",
      "rel": ["nofollow", "sponsored"]
    }
  ]
}
```

**Dependency Error:**
```json
{
  "code": "missing_dep",
  "message": "Required plugin 'Hidden Links Manager' not installed/active (or option missing).",
  "data": {
    "status": 424
  }
}
```

---

### 17. Add Hidden Link

#### POST `/hidden-links/add`
Add a new hidden link.

**Authentication:** Required

**Request Body:**
```json
{
  "keyword": "casino online",
  "link": "https://example.com",
  "rel": ["nofollow", "sponsored"]
}
```

**Body Parameters (Same 3-Priority System as Blogroll):**

| Parameter | Type | Priority | Description |
|-----------|------|----------|-------------|
| `keyword` | string | - | Required. Anchor text |
| `link` | string | - | Required. Target URL |
| `rel_attr` | string | 1 | Space-separated rel values (supports ANY custom values) |
| `rel` | array/string | 2 | Array of rel values or space-separated string |
| Boolean fields | boolean | 3 | Individual rel flags (nofollow, sponsored, ugc, etc.) |

**Request Example:**
```bash
curl -X POST "https://your-site.com/wp-json/external/v1/hidden-links/add" \
  -H "Content-Type: application/json" \
  -H "X-External-API-Key: YOUR_KEY" \
  -d '{
    "keyword": "slot gacor",
    "link": "https://slot-site.com",
    "rel_attr": "nofollow sponsored"
  }'
```

**Success Response:**
```json
{
  "success": true,
  "message": "OK",
  "data": [
    {
      "id": "hid_new123456.78901234",
      "keyword": "slot gacor",
      "link": "https://slot-site.com",
      "rel": ["nofollow", "sponsored"]
    },
    ...
  ]
}
```

---

### 18. Update Hidden Link

#### POST `/hidden-links/update/{id}`
Update an existing hidden link.

**Authentication:** Required

**URL Parameters:**
- `id` (string, required) - Item ID from `/hidden-links` response

**Request Body:**
```json
{
  "keyword": "slot online",
  "link": "https://newsitelink.com",
  "rel": ["nofollow"]
}
```

**Body Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `keyword` | string | No | New keyword |
| `link` | string | No | New URL |
| `rel_attr` | string | No | Priority 1: Space-separated rel values |
| `rel` | array/string | No | Priority 2: Array or space-separated string |
| Boolean fields | boolean | No | Priority 3: Individual rel flags |

**Request Example:**
```bash
curl -X POST "https://your-site.com/wp-json/external/v1/hidden-links/update/hid_678abc123def456.78901234" \
  -H "Content-Type: application/json" \
  -H "X-External-API-Key: YOUR_KEY" \
  -d '{
    "keyword": "Updated Slot",
    "rel": ["nofollow", "ugc"]
  }'
```

**Success Response:**
```json
{
  "success": true,
  "message": "OK",
  "data": {
    "id": "hid_678abc123def456.78901234",
    "keyword": "Updated Slot",
    "link": "https://example.com",
    "rel": ["nofollow", "ugc"]
  }
}
```

**Error Response:**
```json
{
  "success": false,
  "message": "Item not found. Use the item id from GET /hidden-links.",
  "data": []
}
```

---

### 19. Delete Hidden Link

#### DELETE `/hidden-links/delete/{id}`
Delete a hidden link.

**Authentication:** Required

**URL Parameters:**
- `id` (string, required) - Item ID from `/hidden-links` response

**Request Example:**
```bash
curl -X DELETE "https://your-site.com/wp-json/external/v1/hidden-links/delete/hid_678abc123def456.78901234?api_key=YOUR_KEY"
```

**Success Response:**
```json
{
  "success": true,
  "message": "Deleted successfully.",
  "data": []
}
```

**Error Response:**
```json
{
  "success": false,
  "message": "Item not found. Use the item id from GET /hidden-links.",
  "data": []
}
```

---

## Error Codes Reference

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `no_key` | 403 | API key is missing from request |
| `bad_key` | 403 | API key is invalid or inactive |
| `not_found` | 404 | Requested resource (post, item) not found |
| `missing_dep` | 424 | Required plugin not active (when enforcement enabled) |
| `invalid` | 400 | Invalid request parameters |
| `bad_time` | 400 | Invalid datetime format for scheduling |

---

## Background Processing

### Cron Job
The plugin runs a cron job **every 5 minutes** (`eam_publish_queue`) to:
- Process scheduled posts in the queue
- Publish posts when their `run_at` timestamp is reached
- Remove successfully published posts from queue
- Retain failed posts with error message in queue

---

## Data Storage

### API Keys
- **Option:** `eam_api_keys`
- **Structure:** Array of key objects with hash, encrypted copy, label, timestamps, and status

### Settings
- **Option:** `eam_settings`
- **Contains:** Dependency enforcement, plugin paths, option names

### Schedule Queue
- **Option:** `eam_schedule_queue`
- **Structure:** Array of queued post objects with unique IDs

### Blogroll Data
- **Primary Option:** `custom_blogroll_links` (canonical)
- **Compat Option:** `cbr_hidden_links` (synced)
- **Legacy Option:** `custom_blogroll` (migration source)

### Hidden Links Data
- **Option:** Configurable (default: `hlm_hidden_links`)
- **Legacy Option:** `hidden_links_manager` (migration source)

---

## Important Notes

1. **ID Usage:** Always use the `id` field from GET responses when updating/deleting items. Never use array indices.

2. **Scheduling:** Two methods available:
   - Immediate scheduling: Use `schedule_time` in `/posts/create` or `/posts/update`
   - Queue scheduling: Use `/schedule/add` for cron-based publishing

3. **Sticky Posts:** Can be set during creation (`is_sticky` param) or after creation using `/posts/sticky/{id}`

4. **rel Attribute Priority System:** 
   - Priority 1: `rel_attr` (string) - Most flexible, supports any custom values
   - Priority 2: `rel` (array/string) - Standard rel values
   - Priority 3: Boolean fields - Backward compatibility

5. **Dependency Enforcement:** When enabled in settings, Blogroll and Hidden Links endpoints check for plugin activation before processing.

6. **URL Key Compatibility:** Endpoints accept both `link` and `url` parameters for URLs to maintain backward compatibility.

---

## Testing Examples

### Complete Workflow Example

```bash
# 1. Check connection
curl -X GET "https://your-site.com/wp-json/external/v1/status"

# 2. Create a post
curl -X POST "https://your-site.com/wp-json/external/v1/posts/create" \
  -H "Content-Type: application/json" \
  -H "X-External-API-Key: abc123xyz456" \
  -d '{
    "title": "Casino Review 2025",
    "content": "<p>Best online casinos reviewed</p>",
    "status": "publish",
    "categories": [5],
    "tags": ["casino", "review"]
  }'

# 3. Add hidden link
curl -X POST "https://your-site.com/wp-json/external/v1/hidden-links/add" \
  -H "Content-Type: application/json" \
  -H "X-External-API-Key: abc123xyz456" \
  -d '{
    "keyword": "best casino",
    "link": "https://casino-site.com",
    "rel_attr": "nofollow sponsored"
  }'

# 4. Schedule a future post
curl -X POST "https://your-site.com/wp-json/external/v1/schedule/add" \
  -H "Content-Type: application/json" \
  -H "X-External-API-Key: abc123xyz456" \
  -d '{
    "title": "Upcoming Casino Launch",
    "content": "<p>New casino opening soon</p>",
    "run_at": "2025-02-01 10:00:00",
    "status": "publish"
  }'

# 5. List scheduled posts
curl -X GET "https://your-site.com/wp-json/external/v1/schedule/list?api_key=abc123xyz456"
```

---

## Change Log Summary

**Version 7.8.0 Features:**
- ✅ Full CRUD for Posts
- ✅ Sticky/Unsticky operations
- ✅ Post scheduling (two methods)
- ✅ Queue-based scheduler with cron processing
- ✅ Custom Blogroll management
- ✅ Hidden Links management
- ✅ Secure API key system (hash + encrypted copy)
- ✅ Key reveal/copy functionality
- ✅ Dependency checking
- ✅ Comprehensive admin UI documentation
- ✅ 3-Priority rel attribute system
- ✅ Backward compatibility with legacy options

---

**End of Documentation**
