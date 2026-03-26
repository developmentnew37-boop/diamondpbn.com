# Schedule Sidebar Report – Date Display (Context for Continuation)

This document summarizes **what we did** so you can continue from here tomorrow.

---

## 1. The Problem We Were Solving

- **Schedule Sidebar Campaign report**: When you pick a **past date** (e.g. 12 Feb) for links, the report correctly showed that date **before** the link went live.
- **After** the link went live (job ran), the report sometimes showed the **current date** (e.g. today) instead of the original scheduled date (12 Feb).
- **User need**: Report should **always** show the **original scheduled date** (the one you chose when creating the campaign), whether the link is still queued or already live.

---

## 2. What We Tried Before (Then Rolled Back)

- We added a column **`original_schedule_at`** on tasks and posts, set only at creation and never updated by jobs. Report used that for display.
- **Rollback**: You asked to roll this back because it seemed to affect when blogroll scheduled for 12 Feb were posted. We reverted:
  - Migration (removed `original_schedule_at` from both tables)
  - Models, controllers (create + export), report views, and jobs (back to `save()` instead of `update()`).
- The **migration file** we rolled back was: `2026_02_15_100000_add_original_schedule_at_to_scheduled_tables.php` (it still exists in `database/migrations/` but has been rolled back; you can delete it if you want).

---

## 3. Current Solution: Use the Date Table

We now use the **existing** table **`schedule_sidebar_campaign_dates`** as the source of truth for the “scheduled date” on the report.

- That table stores the dates and quantities you chose when creating the campaign.
- **Nothing** (no job, no publish) ever updates it — it’s only written at campaign creation.
- So if we **link each task to the date row** it came from, we can **show the date from that row** on the report. That date never changes when the link goes live.

---

## 4. What We Implemented (Current State)

### 4.1 Database

- **New column** on **`schedule_sidebar_campaign_tasks`**:
  - **`schedule_sidebar_campaign_date_id`** (nullable, FK to `schedule_sidebar_campaign_dates.id`).
- **Migration**: `database/migrations/2026_02_16_100000_add_schedule_sidebar_campaign_date_id_to_tasks.php`  
  - Already run (migration has been executed).

### 4.2 When Creating Tasks (Date Table Flow Only)

- **File**: `app/Http/Controllers/Admin/ScheduleSidebarCampaignController.php`
- **Method**: `storeWithDateTable(...)`
- When building **`$postRows`** for insert, we now set:
  - **`schedule_sidebar_campaign_date_id`** = `$dateRow->id`  
  so each task is linked to the date row it was created from.
- **Date range flow** (`storeWithDateRange`): we do **not** set this column (stays `null`), because that flow doesn’t use the date table.

### 4.3 Model

- **File**: `app/Models/Admin/ScheduleSidebarCampaignTask.php`
- **Fillable**: added `schedule_sidebar_campaign_date_id`.
- **Relationship**: added **`scheduleDate()`** → `belongsTo(ScheduleSidebarCampaignDate::class, 'schedule_sidebar_campaign_date_id')`.

### 4.4 Report (Page)

- **File**: `resources/views/admin/campaigns/pbn-sidebar/schedule-sidebar-campaign-report.blade.php`
- **Logic**: For “Scheduled At” and “Date” we use:
  - **If** task has a date row: **`$task->scheduleDate?->schedule_date`**
  - **Else**: **`$task->schedule_at`**
- So: **date from date table when present, otherwise task’s schedule_at** (same as before for campaigns that don’t use the date table).

### 4.5 Report Controller (Data for Report + Export)

- **File**: `app/Http/Controllers/Admin/ScheduleSidebarCampaignController.php`
- **Report method** `report(...)`: when loading tasks we **eager-load** `scheduleDate`:
  - `ScheduleSidebarCampaignTask::with(['domain.domain', 'link', 'scheduleDate'])->...`
- **Export method** `exportReport(...)`: same eager-load; “Scheduled At” column uses:
  - **`($task->scheduleDate?->schedule_date ?? $task->schedule_at)?->format('d M Y H:i') ?? '-'`**

### 4.6 Jobs

- **No changes** to jobs. They do **not** touch:
  - `schedule_sidebar_campaign_dates`
  - `schedule_sidebar_campaign_date_id` on tasks.
- So the “preserved” date in the date table is never overwritten by publishing.

---

## 5. Behaviour Summary

| Scenario | What report shows for “Scheduled At” / “Date” |
|----------|-----------------------------------------------|
| Campaign created **with date table** (per-date quantities) | Date from **schedule_sidebar_campaign_dates** for that task → **never changes** when link goes live. |
| Campaign created **with date range** (from–to, no date table) | Task’s **schedule_at** (as before). |
| **Existing tasks** (created before this change) | **schedule_at** (their `schedule_sidebar_campaign_date_id` is null). |

---

## 6. Files Touched (Quick Reference)

| File | What changed |
|------|------------------|
| `database/migrations/2026_02_16_100000_add_schedule_sidebar_campaign_date_id_to_tasks.php` | New migration: add FK column (already run). |
| `app/Models/Admin/ScheduleSidebarCampaignTask.php` | Fillable + `scheduleDate()` relationship. |
| `app/Http/Controllers/Admin/ScheduleSidebarCampaignController.php` | Set `schedule_sidebar_campaign_date_id` in `storeWithDateTable`; eager-load `scheduleDate` in report + export; export “Scheduled At” uses date table when present. |
| `resources/views/admin/campaigns/pbn-sidebar/schedule-sidebar-campaign-report.blade.php` | “Scheduled At” and “Date” use `scheduleDate?->schedule_date ?? schedule_at`. |

---

## 7. If You Need to Continue Tomorrow

- **Same bug on Schedule Campaign (posts)?**  
  We did **not** add a similar “date table link” for **Schedule Campaign posts**. The **Schedule Campaign** flow has **schedule_campaign_dates** and **schedule_campaigns_posts**. If you want the same “always show original date” behaviour there, you’d add **`schedule_campaign_date_id`** on **schedule_campaigns_posts**, set it in the controller when creating posts from the date table, and use it in the Schedule Campaign report/export the same way.

- **Existing campaigns (created before this change)**:  
  Their tasks have **schedule_sidebar_campaign_date_id = null**, so report still uses **schedule_at** for them. Only **new** campaigns created with the date table get the “preserved date” from the date table.

- **Rolled-back migration**:  
  `2026_02_15_100000_add_original_schedule_at_to_scheduled_tables.php` is still in the repo but has been rolled back. You can delete it to avoid confusion.

---

## 8. One-Line Summary

We **link each Schedule Sidebar task to the date row** it was created from and **show the date from that date table** on the report and export when available, so the scheduled date doesn’t change when the blogroll goes live.
