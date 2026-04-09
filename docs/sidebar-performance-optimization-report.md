# Sidebar Campaign Performance Optimization Report

Date: 2026-04-07

## Goal
Reduce page-open time, memory usage, and query cost for Sidebar Campaign flows without changing business behavior.

## Completed Optimizations

### 1) Sidebar index query (`SidebarCampaignController@index`)
- Replaced heavy eager loads with lightweight counts:
  - Before: eager-loaded full `domains`, `links`, `tasks` collections.
  - After: `withCount(['domains','links'])` + `with('domainCategory:id,name')`.
- Added explicit `select(...)` to fetch only columns needed by the listing table.
- Switched `paginate()` to `simplePaginate()` to remove expensive total-count query for large datasets.
- Normalized search once into `$search` and reused it (small but cleaner and predictable).

### 2) Sidebar index Blade (`sidebar-campaign.blade.php`)
- Replaced relation counting in Blade:
  - Before: `$campaign->links->count()` and `$campaign->domains->count()`.
  - After: `$campaign->links_count` and `$campaign->domains_count`.
- Prevents relation hydration and extra memory use at render time.

### 3) Sidebar show page (`SidebarCampaignController@show`)
- Reduced campaign payload to only required fields (`id`, `campaign_no`, `last_bulk_updated_at`).
- Reduced task payload with `select(...)` for only displayed columns.
- Added `hasPublishedLinks` as an `exists()` query instead of scanning paginated collection in Blade.

### 4) Sidebar show Blade (`view-campaign.blade.php`)
- Removed `contains(...)` scan over paginated tasks for "Bulk edit links" visibility.
- Uses controller-provided boolean (`$hasPublishedLinks`) for O(1) rendering decision.

### 5) Sidebar report page (`SidebarCampaignController@report`)
- Reduced task payload with explicit `select(...)`.
- Constrained eager-loaded relation columns for `domainRow`, `domain`, and `linkRow`.
- Keeps report output unchanged while reducing per-row data transfer.

### 6) Sidebar report export (`SidebarCampaignController@exportReport`)
- Reworked export memory profile:
  - Before: loaded all tasks with relations into memory (`->get()`).
  - After: streams rows using `chunkById(500, ...)`.
- Optimized nofollow detection:
  - Before: collection scan (`contains(...)`).
  - After: SQL `exists()` with `whereHas('linkRow', ...)`.
- Result: stable memory usage for large campaigns and faster first-byte for exports.

### 7) Sidebar bulk edit batching (`SidebarCampaignController@edit`)
- Removed N+1 count query pattern:
  - Before: one `COUNT(*)` query per distinct batch.
  - After: one grouped aggregate query by `sidebar_campaign_link_id`, then in-memory summation per batch.
- Greatly reduces query count when campaign has many distinct keyword/url batches.

## Impact Summary
- Lower DB round-trips in listing/edit flows.
- Lower RAM usage in index/report/export.
- Faster response time for high-row campaigns.
- Better scalability when tasks/links grow.

## Notes
- Existing behavior and UI output were preserved (same features, optimized internals).
- Existing DB indexes on `sidebar_campaign_tasks` are already good for worker/report paths.

## Next Optional Optimizations
- Add short-lived cache (30-60s) for sidebar index/report stats if traffic is high.
- Add async/queued export generation for very large reports (download link once ready).
- Add DB-level read replicas for report/export-heavy environments.
