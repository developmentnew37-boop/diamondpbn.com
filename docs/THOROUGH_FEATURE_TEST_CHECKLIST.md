# Thorough Feature Test Checklist

Use this checklist to verify **every** feature: create, update, delete, and all small options.  
Base URL: `http://127.0.0.1:8000` (or your app URL). Log in as admin and complete OTP first.

---

## 1. PBN Post Campaign (`/admin/campaign`)

| Action | URL / How | Verified |
|--------|-----------|----------|
| **List** | GET `/admin/campaign` | ✅ Page loads; table with Campaign No, Type, Domain Category, Total/Completed/Fail; filters (select, Bulk actions), search. |
| **Create** | Click "Create Campaign" → `/admin/campaign/create` | ✅ Create form loads (domain category, article set, domain set, etc.). |
| **Store** | Submit create form with valid data | ⬜ Fill domain category, article category, article set, domain set, then Create. |
| **View** | Click visibility icon or GET `/admin/campaign/{id}` | ✅ View campaign with posts table; Back, refresh, per-post actions. |
| **Edit** | Click edit icon or GET `/admin/campaign/{id}/edit` | ✅ Edit page: Update campaign no, Keyword/URL batches, "Update batches & sync to remote posts". |
| **Update campaign no** | Change campaign no, click "Update campaign no" | ⬜ |
| **Update batches** | Add/remove keyword-URL rows, click "Update batches & sync to remote posts" | ⬜ |
| **Edit single post** | From view: link to edit post → `/admin/campaign/editpost/{postId}` | ⬜ |
| **Update single post** | Submit edit post form | ⬜ |
| **Delete single post** | GET `/admin/campaign/deleteCampaignPost/{id}` | ⬜ (campaign can be auto-deleted if last post). |
| **Bulk update posts** | From view: use bulk update form → POST `campaign/bulk/update/{id}` | ⬜ |
| **Retry campaign** | GET `/admin/campaign/retry/{id}` | ⬜ |
| **Delete campaign** | Delete button → DELETE `/admin/campaign/{id}` (queued via DeleteCampaignJob) | ⬜ Run queue worker to process. |
| **Bulk actions** | Select campaigns, choose Bulk actions (e.g. Delete), Apply | ⬜ |
| **Search** | Use "search here" on list | ⬜ |

---

## 2. Sidebar (Blogroll) Campaign (`/admin/sidebar/campaign`)

| Action | URL / How | Verified |
|--------|-----------|----------|
| **List** | GET `/admin/sidebar/campaign` | ⬜ |
| **Create** | GET `/admin/sidebar/campaign/create` | ⬜ |
| **Store** | Submit create form | ⬜ |
| **View** | GET `/admin/sidebar/campaign/{id}` | ⬜ |
| **Edit campaign** | GET `/admin/sidebar/campaign/{id}/edit` | ⬜ |
| **Edit task** | GET `/admin/sidebar/campaign/edit-task/{id}` | ⬜ |
| **Update task** | POST `update-task/{id}` | ⬜ |
| **Retry task** | GET `retry-task/{id}` | ⬜ |
| **Delete task** | GET `delete-task/{id}` | ⬜ |
| **Bulk delete tasks** | POST `{id}/bulk-delete-tasks` | ⬜ |
| **Delete campaign** | Resource destroy | ⬜ |
| **Report** | Public: `/sidebar/campaign/report/{campaign_no}/{token}` | ⬜ |
| **Export report** | `/sidebar/campaign/report/{campaign_no}/{token}/export` | ⬜ |

---

## 3. Hidden Link Campaign (`/admin/hidden/link/campaign`)

| Action | URL / How | Verified |
|--------|-----------|----------|
| **List** | GET `/admin/hidden/link/campaign` | ⬜ |
| **Create** | GET `/admin/hidden/link/campaign/create` | ⬜ |
| **Store** | Submit create | ⬜ |
| **View** | GET `…/campaign/{id}` | ⬜ |
| **Edit campaign** | GET `…/campaign/{id}/edit` | ⬜ |
| **Edit task** | GET `edit-task/{id}` | ⬜ |
| **Update task** | POST `update-task/{id}` | ⬜ |
| **Retry task** | GET `retry-task/{id}` | ⬜ |
| **Delete task** | GET `delete-task/{id}` | ⬜ |
| **Bulk delete tasks** | POST `{id}/bulk-delete-tasks` | ⬜ |
| **Bulk delete campaigns** | POST `bulk-delete` | ⬜ |
| **Delete campaign** | Resource destroy | ⬜ |
| **Report / Export** | Token-based report and export URLs | ⬜ |

---

## 4. Schedule Post Campaign (`/admin/campaign/post/schedule`)

| Action | URL / How | Verified |
|--------|-----------|----------|
| **List** | GET `/admin/campaign/post/schedule` | ✅ Page loads (Scheduled Campaigns). |
| **Create** | GET `…/schedule/create` | ⬜ |
| **Store** | Submit create | ⬜ |
| **View** | GET `…/schedule/{id}` | ⬜ |
| **Edit campaign** | GET `…/schedule/{id}/edit` | ⬜ |
| **Edit post** | GET `edit-post/{postId}` | ⬜ |
| **Update post** | POST `update-post/{postId}` | ⬜ |
| **Retry post** | POST `retry-post/{postId}` | ⬜ |
| **Delete post** | POST `delete-post/{postId}` | ⬜ |
| **Bulk update** | POST `bulk/update/{id}` | ⬜ |
| **Delete campaign** | Resource destroy | ⬜ |
| **Report / Export** | Token-based | ⬜ |

---

## 5. Schedule Blogroll Campaign (`/admin/campaign/sidebar/schedule`)

| Action | URL / How | Verified |
|--------|-----------|----------|
| **List** | GET `/admin/campaign/sidebar/schedule` | ✅ Sidebar Campaigns table, Create Campaign, filters. |
| **Create** | GET `…/schedule/create` | ⬜ |
| **Store** | Submit create | ⬜ |
| **View** | GET `…/schedule/{id}` | ⬜ |
| **Edit campaign** | GET `…/schedule/{id}/edit` | ⬜ |
| **Edit task** | GET `edit-task/{id}` | ⬜ |
| **Update task** | POST `update-task/{id}` | ⬜ |
| **Retry task** | POST `retry-task/{id}` | ⬜ |
| **Delete task** | GET `delete-task/{id}` | ⬜ |
| **Bulk update** | POST `bulk/update/{id}` | ⬜ |
| **Delete campaign** | Resource destroy | ⬜ |
| **Report / Export** | Token-based | ⬜ |

---

## 6. WP Scheduled Campaign (`/admin/campaign/post/wp-schedule`)

| Action | URL / How | Verified |
|--------|-----------|----------|
| **List** | GET `/admin/campaign/post/wp-schedule` | ⬜ |
| **Create** | GET `…/wp-schedule/create` | ⬜ |
| **Store** | Submit create | ⬜ |
| **View** | GET `…/wp-schedule/{id}` | ⬜ |
| **Edit** | GET `…/wp-schedule/{id}/edit` | ⬜ |
| **Run** | POST `run/{id}` | ⬜ |
| **Sync campaign** | POST `sync/{id}` | ⬜ |
| **Edit post** | GET `editpost/{postId}` | ⬜ |
| **Update post** | POST `updatepost/{postId}` | ⬜ |
| **Retry post** | POST `retry-post/{postId}` | ⬜ |
| **Sync post** | POST `sync-post/{postId}` | ⬜ |
| **Delete post** | POST `deletepost/{postId}` | ⬜ |
| **Bulk update** | POST `bulk/update/{id}` | ⬜ |
| **Delete campaign** | Resource destroy | ⬜ |
| **Report / Export** | Token-based | ⬜ |

---

## 7. Sticky Post Campaign (`/admin/sticky/campaign/`)

| Action | URL / How | Verified |
|--------|-----------|----------|
| **List** | GET `/admin/sticky/campaign/` | ⬜ (trailing slash required). |
| **Create** | GET `/admin/sticky/campaign/create` | ⬜ |
| **Store** | Uses same create flow as PBN Post (is_sticky=1) | ⬜ |

*Note: Sticky uses same campaign model; list/edit/delete same as PBN Post but filtered by `is_sticky_campaign`.*

---

## 8. Domain Category (`/admin/domain/category`)

| Action | URL / How | Verified |
|--------|-----------|----------|
| **List** | GET `/admin/domain/category` | ⬜ |
| **Create** | GET `…/category/create` | ⬜ |
| **Store** | Submit create | ⬜ |
| **View** | GET `…/category/{id}` | ⬜ |
| **Edit** | GET `…/category/{id}/edit` | ⬜ |
| **Update** | PUT/PATCH `…/category/{id}` | ⬜ |
| **Delete** | DELETE `…/category/{id}` | ⬜ |
| **Bulk delete** | POST `/admin/domain/category/delete` | ⬜ |

---

## 9. Domains (`/admin/domain`)

| Action | URL / How | Verified |
|--------|-----------|----------|
| **Select category** | GET `/admin/domain/select/category` | ⬜ |
| **Redirect to list** | POST `/admin/domain/redirect/list` (with category) | ⬜ |
| **List** | GET `/admin/domain` (after selecting category) | ⬜ |
| **Add** | GET `/admin/domain/create` | ⬜ |
| **Store** | POST `/admin/domain` | ⬜ |
| **Edit** | GET `/admin/domain/{id}/edit` | ⬜ |
| **Update** | PUT/PATCH | ⬜ |
| **Delete** | DELETE | ⬜ |
| **Bulk delete** | POST `/admin/domain/delete` | ⬜ |

---

## 10. Domain Set (`/admin/domains/set`)

| Action | URL / How | Verified |
|--------|-----------|----------|
| **List** | GET `/admin/domains/set` | ⬜ |
| **Create** | GET `…/set/create?id=…` (requires id) | ⬜ |
| **Store** | POST | ⬜ |
| **View (show)** | GET `…/set/{id}` (domain-set-detail) | ⬜ |
| **Edit** | GET `…/set/{id}/edit` | ⬜ |
| **Update** | PUT/PATCH | ⬜ |
| **Delete** | POST `/admin/domains/set/delete` (bulk) | ⬜ |

---

## 11. Article Category (`/admin/article/category`)

| Action | URL / How | Verified |
|--------|-----------|----------|
| **List** | GET `/admin/article/category` | ⬜ |
| **Create** | GET `…/category/create` | ⬜ |
| **Store** | POST | ⬜ |
| **View** | GET `…/category/{id}` | ⬜ |
| **Edit** | GET `…/category/{id}/edit` | ⬜ |
| **Update** | PUT/PATCH | ⬜ |
| **Delete** | DELETE | ⬜ |
| **Bulk delete** | POST `/admin/article/category/delete` | ⬜ |

---

## 12. Article Language (`/admin/article/language`)

| Action | URL / How | Verified |
|--------|-----------|----------|
| **List** | GET `/admin/article/language` | ⬜ (check page title: should be "Language", not "Add Category"). |
| **Create** | GET `…/language/create` | ⬜ |
| **Store** | POST | ⬜ |
| **View** | GET `…/language/{id}` | ⬜ |
| **Edit** | GET `…/language/{id}/edit` | ⬜ |
| **Update** | PUT/PATCH | ⬜ |
| **Delete** | DELETE | ⬜ |

---

## 13. Articles (`/admin/article`)

| Action | URL / How | Verified |
|--------|-----------|----------|
| **List** | GET `/admin/article` | ✅ Articles table, filters, search, bulk actions. |
| **Options** | GET `/admin/article/opt` | ⬜ |
| **Create** | GET `/admin/article/create` | ⬜ |
| **Store** | POST | ⬜ |
| **View** | GET `/admin/article/{id}` | ⬜ |
| **Edit** | GET `/admin/article/{id}/edit` | ⬜ |
| **Update** | PUT/PATCH | ⬜ |
| **Delete** | POST `/admin/article/delete` (bulk) | ⬜ |
| **Import** | POST `/admin/article/import` | ⬜ |
| **Upload docx** | GET `/admin/article/upload/docx` | ⬜ |

---

## 14. Article Set (`/admin/articles/set`)

| Action | URL / How | Verified |
|--------|-----------|----------|
| **List** | GET `/admin/articles/set` | ⬜ |
| **Options** | GET `/admin/article/set/options` | ⬜ |
| **Create (option)** | From options: create set flow | ⬜ |
| **Store** | POST | ⬜ |
| **View** | GET `…/set/{id}` (article-set-option / articles in set) | ⬜ |
| **Edit** | GET `…/set/{id}/edit` | ⬜ |
| **Update** | PUT/PATCH | ⬜ |
| **Add article** | POST `/admin/article/set/add/` | ⬜ |
| **Import** | POST `/admin/article/set/import/` | ⬜ |
| **Destroy article** | DELETE `/admin/article/set/destroy/{id}` | ⬜ |
| **Bulk delete articles** | POST `article/set/bulk/delete` | ⬜ |
| **Delete set** | Resource destroy | ⬜ |

---

## 15. Users (`/admin/user`)

| Action | URL / How | Verified |
|--------|-----------|----------|
| **List** | GET `/admin/user` | ✅ "Users Management" page loads. |
| **Create** | GET `/admin/user/create` | ⬜ |
| **Store** | POST | ⬜ |
| **View** | GET `/admin/user/{id}` | ⬜ |
| **Edit** | GET `/admin/user/{id}/edit` | ⬜ |
| **Update** | PUT/PATCH | ⬜ |
| **Delete** | DELETE | ⬜ |

---

## 16. Profile (`/admin/profile`)

| Action | URL / How | Verified |
|--------|-----------|----------|
| **View** | GET `/admin/profile` | ✅ "My Profile - Diamond PBN" loads. |
| **Show (slug)** | GET `/admin/profile/{slug}` | ⬜ |
| **Update** | PUT `/admin/profile/update` | ⬜ |

---

## 17. Dashboard

| Action | URL / How | Verified |
|--------|-----------|----------|
| **Dashboard** | GET `/admin` | ✅ Analytics, campaign type buttons, links. |
| **Campaigns by type** | GET `/admin/dashboard/campaigns?type=…` | ⬜ (type: post, sidebar, hidden, sticky, schedule, schedule_sidebar, wp_schedule). |

---

## 18. Reporting (public, token-based)

| Report | URL pattern | Verified |
|--------|--------------|----------|
| PBN Post | `/campaign/report/{campaign_no}/{token}` | ⬜ |
| PBN Post export | `…/export` | ⬜ |
| Sidebar | `/sidebar/campaign/report/…` | ⬜ |
| Hidden Link | `/hidden/link/campaign/report/…` | ⬜ |
| Schedule Post | `/schedule/campaign/report/…` | ⬜ |
| Schedule Sidebar | `/schedule/sidebar/campaign/report/…` | ⬜ |
| WP Scheduled | `/campaign/post/wp-schedule/report/…` | ⬜ |

Use a campaign’s `report_token` from DB or report link from campaign view.

---

## 19. Small options (apply across lists)

- **Search** on each list page.
- **Filters** (category, status, date range where present).
- **Bulk actions**: select rows, choose action (e.g. Delete), Apply.
- **Pagination**: next/previous where list is paginated.
- **Copy report link** where available.
- **Logout**: POST `/admin/admin/logout`.

---

## 20. Bugs / notes from automated run

1. **PBN Post** – List, Create, View, Edit pages load correctly. Delete is queued (run queue worker to process).
2. **Schedule Post** – List loads as "Scheduled Campaigns".
3. **Schedule Blogroll** – List loads as "Sidebar Campaigns".
4. **Users** – List loads as "Users Management".
5. **Profile** – Loads as "My Profile - Diamond PBN".
6. **Article language** – When opening `/admin/article/language`, page title was "Add Category" in one run; confirm it shows the correct title for Language list.
7. **Sticky** – Index is `/admin/sticky/campaign/` (trailing slash); create is `/admin/sticky/campaign/create`.

---

## How to use this checklist

1. Log in and complete OTP.
2. For each section, run **List → Create → View → Edit → Update → Delete** (and sub-actions like edit post, retry, bulk update) where applicable.
3. Test **filters, search, bulk actions, pagination** on list pages.
4. For **report/export**, use a real campaign’s report token (from DB or UI).
5. Mark **Verified** (✅/⬜) as you go; note any errors or UI bugs at the end of the doc or in section 20.

Running the queue worker is required for: campaign delete jobs, scheduled/sidebar publish jobs, and other queued tasks.
