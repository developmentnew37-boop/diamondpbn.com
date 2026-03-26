# Schedule Campaign – Changes (Changelog)

This document lists **changes made** to the Schedule Campaign feature: new behaviour, database updates, and fixes.

---

## Schedule Campaign – Features and new changes (summary)

| # | Feature / change | Description |
|---|------------------|-------------|
| 1 | **Edit campaign** | Edit campaign number (unique slug) and **keyword/URL batches** (distinct batches; one batch = all posts sharing same keyword+url). |
| 2 | **Update campaign no** | Form on edit page: update `campaign_no` with uniqueness check (excluding current campaign id). |
| 3 | **Bulk update (keyword/URL)** | Form on edit page: update keyword/URL per batch; saves to DB and **queues remote post updates** for all published posts in that batch. Job fetches post from remote, applies new links, POSTs update. |
| 4 | **Campaign delete** | Delete whole campaign: **queue job** that deletes all posts on remote (for success posts), unlocks queued articles, then deletes all local posts, articles, domains, date rows, and campaign. |
| 5 | **Single post – Edit** | For **published** posts only (status `success`, `remote_id` set): fetch current title/content from remote, show form; submit updates post on remote site (POST update API). |
| 6 | **Single post – Retry** | For **queued / failed / publishing** posts: reset status to `queued`, clear error/lock, re-dispatch `PublishScheduledCampaignPostJob`. Rate limited: 3 minutes per post. |
| 7 | **Single post – Delete** | Delete one post: if published → DELETE on remote; if queued → unlock article; decrement campaign `total_targets` and `completed_targets`/`failed_targets`; delete only the post row (article/domain may be shared). |
| 8 | **Index actions** | On Schedule Campaigns list: View, **Edit campaign**, Copy report link, Open report, **Delete campaign** (with confirm). |
| 9 | **View campaign actions** | On campaign show (posts table): **View** (remote URL), **Edit** (single post, published only), **Retry** (queued/failed/publishing), **Delete** (single post). |
| 10 | **Queue workers** | Scheduler runs `queue:work` for `schedule_campaign_bulk_updates` and `schedule_campaign_deletions` every minute so bulk updates and campaign deletions run without manual `queue:work`. |

**Files added/updated for these features** (see Section 9 below).

---

## 1. Per-date quantity (like WP Scheduled)

**Requirement:** Allow defining how many posts run on each date (e.g. 5 on 10 Feb, 5 on 11 Feb) instead of only an even spread across a date range.

| Change | Details |
|--------|---------|
| **New table** | `schedule_campaign_dates`: `schedule_campaign_id`, `schedule_date`, `quantity`. One row per date with a non-zero quantity. |
| **Migration** | `2026_02_11_100000_create_schedule_campaign_dates_table.php` |
| **Model** | `App\Models\Admin\ScheduleCampaignDate`. `ScheduleCampaign` has many `dateRows()` ordered by `schedule_date`. |
| **Create form** | "Date distribution (optional)" section: From date, To date, **Generate date table** button. Table shows one row per day with a quantity input. Sum auto-fills Post Quantity. Hidden input `date_quantities` (JSON) sends `[{ date, quantity }, ...]`. |
| **Controller** | `ScheduleCampaignController::store()`: if `date_quantities` is present, uses `storeWithDateTable()` — creates `schedule_campaign_dates` rows and assigns posts so each date gets exactly its quantity, with `schedule_at` = that date (start of day). If `date_quantities` is empty, keeps legacy `storeWithDateRange()` (even spread from from/to). |
| **Past dates in form** | From/To date inputs no longer have `min="{{ date('Y-m-d') }}"` so past dates can be selected. |

---

## 2. Publish on the selected date (not always “today”)

**Requirement:** When posting to WordPress, use the post’s **scheduled date** so the remote post is dated correctly (past, today, or future).

| Change | Details |
|--------|---------|
| **Job** | `PublishScheduledCampaignPostJob::postToWordPress()`: sends **`schedule_time`** (from `$post->schedule_at`, format `Y-m-d H:i:s`) to the API. Sets **`status`**: `future` if `schedule_at` is in the future, `publish` otherwise (so past/today posts publish immediately; future posts can be scheduled by WordPress if the API supports it). |

---

## 3. Past dates allowed in the form

**Requirement:** Users must be able to choose past dates for scheduling (e.g. backdate posts).

| Change | Details |
|--------|---------|
| **Blade** | `create-schedule-campaign.blade.php`: removed `min` attribute from From date and To date inputs. |
| **JS (resources)** | `resources/js/create-schedule-campaign.js`: removed validation “From date must be today or a future date”; only “To date must be on or after From date” remains. |
| **JS (public)** | `public/js/create-schedule-campaign.js`: removed the same “From date must be today or a future date” check and the “minimum 1 day gap” rule for Schedule Post. |

---

## 4. Report: Scheduled At shows original schedule date

**Requirement:** The report must show the **original** date chosen at campaign creation (e.g. 10 Feb for past-dated posts), not the date when the post was actually published.

| Change | Details |
|--------|---------|
| **Report view** | `schedule-campaign-report.blade.php`: **Scheduled At** column uses only `$post->schedule_at` (format `d-M-Y`). Previously it showed `published_at` for Live posts, which overwrote the intended “scheduled” date with the publish date. |
| **Export** | Excel export already used `schedule_at` for “Scheduled At”; no change. |

---

## 5. Preserve `schedule_at` in the database (no auto-overwrite)

**Requirement:** When a post is updated (e.g. marked success, `published_at` set), the stored **schedule_at** must not change so the report can always show the original scheduled date.

| Change | Details |
|--------|---------|
| **Cause** | In MySQL, the first `TIMESTAMP` column can get `ON UPDATE CURRENT_TIMESTAMP`, so any `UPDATE` on the row (e.g. on publish) was overwriting `schedule_at` with the current date/time. |
| **Fix** | Migration `2026_02_12_100000_fix_schedule_campaigns_posts_schedule_at_preserve.php`: changed `schedule_at` from `TIMESTAMP` to **`DATETIME`** on `schedule_campaigns_posts`. `DATETIME` is not auto-updated, so the original value is preserved. |
| **Note** | Campaigns already published before this migration had `schedule_at` overwritten; their report will still show the (wrong) publish date unless data is restored from backup. New campaigns and any update after the migration preserve past dates correctly. |

---

## 6. Queue: dispatch and process Schedule Campaign jobs

**Requirement:** Schedule Campaign posts must be processed in the background. The controller does **not** dispatch jobs; the scheduler does. A worker must process the queue.

| Change | Details |
|--------|---------|
| **Dispatch** | Already in place: `routes/console.php` — every minute, `dispatch_scheduled_campaign_posts` selects posts with `status = 'queued'`, `schedule_at <= now()`, and (if applicable) lock not stale, then dispatches `PublishScheduledCampaignPostJob` to queue `scheduled_campaigns` (up to 50 per run). |
| **Process** | **Added** in `routes/console.php`: scheduled command `queue:work --queue=scheduled_campaigns --sleep=1 --tries=3 --stop-when-empty` every minute so dispatched jobs are actually run. Same for `scheduled_sidebar_campaigns` queue. |

---

## 7. Client requirement summary (past / today / future)

| Behaviour | Implementation |
|-----------|----------------|
| Past-dated and today-dated posts published as soon as the scheduler runs | Scheduler uses `schedule_at <= now()`; both past and today satisfy this. |
| Future-dated posts stay queued until that date | When `schedule_at` is in the future, `schedule_at <= now()` is false, so they are not dispatched. |
| Report shows original schedule date | Report and export use `schedule_at` only. `schedule_at` is set at creation and preserved (after the DATETIME migration). |
| WordPress post uses intended date | Job sends `schedule_time` from `$post->schedule_at` to the API. |

---

## 8. Files touched (summary)

| Area | Files |
|------|--------|
| **Migrations** | `2026_02_11_100000_create_schedule_campaign_dates_table.php`, `2026_02_12_100000_fix_schedule_campaigns_posts_schedule_at_preserve.php` |
| **Models** | `ScheduleCampaignDate.php` (new), `ScheduleCampaign.php` (added `dateRows()`) |
| **Controller** | `ScheduleCampaignController.php` (store: date_quantities, storeWithDateTable, storeWithDateRange) |
| **Job** | `PublishScheduledCampaignPostJob.php` (schedule_time, status future/publish) |
| **Views** | `create-schedule-campaign.blade.php` (date distribution block, no min on dates), `schedule-campaign-report.blade.php` (Scheduled At = schedule_at only) |
| **JS** | `resources/js/create-schedule-campaign.js`, `public/js/create-schedule-campaign.js` (past dates allowed) |
| **Scheduler** | `routes/console.php` (queue:work for scheduled_campaigns and scheduled_sidebar_campaigns) |

---

## 9. Campaign update, bulk update, delete & single post actions (files)

Features: edit campaign (campaign no + keyword/URL batches), bulk update batches on remote, delete whole campaign, single post edit / retry / delete.

| Area | File | Purpose |
|------|------|---------|
| **Service** | `app/Services/ScheduleCampaignRemotePostUpdateService.php` | Fetches post from remote (GET), applies keyword/URL from DB to content (replace anchors), returns [title, content] for update. |
| **Jobs** | `app/Jobs/BulkUpdateScheduleCampaignPostsJob.php` | Queue `schedule_campaign_bulk_updates`: for each post ID, fetch from remote, apply keyword/URL, POST update. |
| **Jobs** | `app/Jobs/DeleteScheduleCampaignJob.php` | Queue `schedule_campaign_deletions`: delete remote posts (success only), unlock queued articles, then delete all posts, articles, domains, dates, campaign. |
| **Controller** | `app/Http/Controllers/Admin/ScheduleCampaignController.php` | `edit()`, `update()`, `bulkUpdate()`, `destroy()`, `editPost()`, `updatePost()`, `retryPost()`, `deletePost()`; `generateUniqueCampaignNo($input, $excludeId)`. |
| **Routes** | `routes/admin.php` | `schedule.campaign.bulk.update`, `schedule.campaign.edit.post`, `schedule.campaign.update.post`, `schedule.campaign.retry.post`, `schedule.campaign.delete.post`. |
| **Views** | `resources/views/admin/campaigns/pbn-post/edit-schedule-campaign.blade.php` | Edit campaign: campaign no form + keyword/URL batches form (add/remove pair rows), submit bulk update. |
| **Views** | `resources/views/admin/campaigns/pbn-post/edit-schedule-campaign-post.blade.php` | Edit single post: fetch title/content from remote, form with Title + Description (CKEditor), submit updates remote. |
| **Views** | `resources/views/admin/campaigns/pbn-post/view-schedule-campaign.blade.php` | Campaign show: Edit campaign + Delete campaign buttons; per-post View, Edit, Retry, Delete. |
| **Views** | `resources/views/admin/campaigns/pbn-post/schedule-campaign.blade.php` | Index: per-campaign View, Edit campaign, Copy report, Open report, Delete campaign. |
| **Scheduler** | `routes/console.php` | `queue:work --queue=schedule_campaign_bulk_updates`, `queue:work --queue=schedule_campaign_deletions` every minute. |

---

For full schema and flow, see **SCHEDULE_CAMPAIGN_ANALYSIS.md**.
