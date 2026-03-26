# Dispatch commands by controller

Quick reference: all job dispatches used in controller files, grouped by controller.

---

## 1. campaignController (PBN Post campaigns)

| Job class | Queue name | Typical use |
|-----------|------------|-------------|
| `PublishCampaignPostJob::dispatch($id)` | `campaigns` | Publish single campaign post (create + retry) |
| `BulkUpdateCampaignPostsJob::dispatch($postIds, $replacePairs, $removePairs, $addPairs)` | `bulk_updates` | Bulk update post content/links |
| `DeleteCampaignJob::dispatch($campaign->id)` | `deletions` | Delete campaign and its posts |

---

## 2. SidebarCampaignController (Sidebar blogroll campaigns)

| Job class | Queue name | Typical use |
|-----------|------------|-------------|
| `PublishSidebarBlogrollJob::dispatch($taskId)` | `sidebar_campaigns` | Publish single sidebar task (create + retry) |
| `BulkUpdateSidebarBlogrollJob::dispatch($chunk)` | `bulk_blogroll_updates` | Bulk update sidebar blogroll entries |
| `DeleteSidebarCampaignJob::dispatch($campaign->id)` | `sidebar_deletions` | Delete sidebar campaign and tasks |

---

## 3. HiddenLinkCampaignController (Hidden links campaigns)

| Job class | Queue name | Typical use |
|-----------|------------|-------------|
| `PublishHiddenLinksJob::dispatch($taskId)` | `hidden_links_campaigns` | Publish single hidden-link task (create + retry) |
| `BulkUpdateHiddenLinksJob::dispatch($chunk)` | `update_hidden_links` | Bulk update hidden-link tasks |
| `BulkDeleteHiddenLinksJob::dispatch($campaign->id, $taskIds)` | `delete_hidden_links` | Bulk delete selected tasks |
| `BulkDeleteHiddenLinkCampaignsJob::dispatch($found)` | `delete_hidden_links_campaign` | Bulk delete whole campaigns |

---

## 4. ScheduleCampaignController (Scheduled PBN post campaigns)

| Job class | Queue name | Typical use |
|-----------|------------|-------------|
| `PublishScheduledCampaignPostJob::dispatch($post->id)` | `scheduled_campaigns` | Publish single scheduled post (retry) |
| `BulkUpdateScheduleCampaignPostsJob::dispatch($postIdsToUpdateOnRemote)` | `schedule_campaign_bulk_updates` | Bulk update scheduled posts on remote |
| `DeleteScheduleCampaignJob::dispatch($campaign->id)` | `schedule_campaign_deletions` | Delete scheduled campaign and posts |

---

## 5. ScheduleSidebarCampaignController (Scheduled sidebar campaigns)

| Job class | Queue name | Typical use |
|-----------|------------|-------------|
| `PublishScheduledSidebarBlogrollJob::dispatch($task->id)` | `scheduled_sidebar_campaigns` | Publish single scheduled sidebar task (retry) |
| `BulkUpdateScheduleSidebarBlogrollJob::dispatch($updates)` | `schedule_sidebar_bulk_updates` | Bulk update scheduled sidebar entries |
| `DeleteScheduleSidebarCampaignJob::dispatch($campaign->id)` | `schedule_sidebar_deletions` | Delete scheduled sidebar campaign and tasks |

---

## 6. WpScheduledCampaignController (WP scheduled campaigns)

| Job class | Queue name | Typical use |
|-----------|------------|-------------|
| `PublishWpScheduledPostJob::dispatch($postId)` / `dispatch($post->id)` | `wp_scheduled_campaigns` | Publish single WP scheduled post |
| `SyncWpScheduledPostStatusJob::dispatch($postId)` / `dispatch($post->id)` | `wp_scheduled_sync` | Sync post status from WP |
| `BulkUpdateWpScheduledPostsJob::dispatch($postIdsToUpdateOnRemote)` | `wp_scheduled_campaign_bulk_updates` | Bulk update WP scheduled posts |
| `DeleteWpScheduledCampaignJob::dispatch($campaign->id)` | `wp_scheduled_campaign_deletions` | Delete WP scheduled campaign and posts |

---

## 7. Api\admin\DomainController (Domain check API)

| Job class | Queue name | Typical use |
|-----------|------------|-------------|
| `CheckDomainStatus::dispatch($name, $category, $admin_id, ...)` | `domainCheck` | Check/update domain metrics (DA, DR, etc.) |

---

## Queue names summary (for `php artisan queue:work`)

| Queue | Controller / feature |
|-------|----------------------|
| `campaigns` | campaignController – PBN post publish |
| `bulk_updates` | campaignController – PBN post bulk update |
| `deletions` | campaignController – PBN campaign delete |
| `sidebar_campaigns` | SidebarCampaignController – sidebar publish |
| `bulk_blogroll_updates` | SidebarCampaignController – sidebar bulk update |
| `sidebar_deletions` | SidebarCampaignController – sidebar campaign delete |
| `hidden_links_campaigns` | HiddenLinkCampaignController – hidden link publish |
| `update_hidden_links` | HiddenLinkCampaignController – hidden link bulk update |
| `delete_hidden_links` | HiddenLinkCampaignController – bulk delete tasks |
| `delete_hidden_links_campaign` | HiddenLinkCampaignController – bulk delete campaigns |
| `scheduled_campaigns` | ScheduleCampaignController – scheduled post publish |
| `schedule_campaign_bulk_updates` | ScheduleCampaignController – scheduled post bulk update |
| `schedule_campaign_deletions` | ScheduleCampaignController – scheduled campaign delete |
| `scheduled_sidebar_campaigns` | ScheduleSidebarCampaignController – scheduled sidebar publish |
| `schedule_sidebar_bulk_updates` | ScheduleSidebarCampaignController – scheduled sidebar bulk update |
| `schedule_sidebar_deletions` | ScheduleSidebarCampaignController – scheduled sidebar delete |
| `wp_scheduled_campaigns` | WpScheduledCampaignController – WP post publish |
| `wp_scheduled_sync` | WpScheduledCampaignController – WP post status sync |
| `wp_scheduled_campaign_bulk_updates` | WpScheduledCampaignController – WP post bulk update |
| `wp_scheduled_campaign_deletions` | WpScheduledCampaignController – WP campaign delete |
| `domainCheck` | Api\admin\DomainController – domain status check |

---

*Generated from controller files. StickyPostCampaignController has no job dispatches.*
