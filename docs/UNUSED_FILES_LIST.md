# Unused Files in PBN Automation Software

Files listed here are **not referenced** by any route, controller, view, or asset. Safe to archive or remove after you confirm. Keep a backup before deleting.

---

## 1. Views (Blade) – never returned by any controller

| File | Reason |
|------|--------|
| `resources/views/admin/sticky/make-sticky-campaign.blade.php` | Sticky campaign uses `admin.campaigns.pbn-post.create-campaign` and `admin.campaigns.pbn-post.campaign`; this view is never returned. Also extends `layout.layout` (invalid – should be `admin.layout.layout`). |
| `resources/views/admin/pbn-campaigns/post.blade.php` | No controller returns `admin.pbn-campaigns.post`. |
| `resources/views/admin/campaigns/sticky-post/campaign.blade.php` | Sticky list uses `admin.campaigns.pbn-post.campaign`; this view is never returned. |
| `resources/views/admin/article/category.blade.php` | Article category uses `admin.article.category.category` and `admin.article.category.edit-category` (the ones in `category/` subfolder). This standalone `category.blade.php` is never returned. |
| `resources/views/admin/domains/view-domain-set.blade.php` | Domain set “show” uses `admin.domains.domain-set-detail`, not `view-domain-set`. |
| `resources/views/test.blade.php` | Dev/test view; extends `layout.layout` (invalid). Not used by any route. |
| `resources/views/admin/test.blade.php` | Dev/test view; extends `layout.layout` (invalid). Not used by any route. |

---

## 2. Non-Blade file in views (misplaced)

| File | Reason |
|------|--------|
| `resources/views/admin/utils/pagination.php` | Raw PHP class file in `views/`; not a Blade view and not referenced anywhere. App uses Laravel’s built-in paginator. |

---

## 3. Vendor pagination views (optional cleanup)

Laravel’s default pagination theme is usually **tailwind** or **bootstrap-5**. Only the theme in use is loaded. The rest are unused unless you explicitly switch theme.

| File | Note |
|------|------|
| `resources/views/vendor/pagination/default.blade.php` | Unused if default theme is not “default”. |
| `resources/views/vendor/pagination/bootstrap-4.blade.php` | Unused if not using Bootstrap 4 theme. |
| `resources/views/vendor/pagination/simple-bootstrap-4.blade.php` | Unused if not using simple Bootstrap 4. |
| `resources/views/vendor/pagination/simple-default.blade.php` | Unused if not using simple default. |
| `resources/views/vendor/pagination/semantic-ui.blade.php` | Unused if not using Semantic UI. |

**Check:** In `AppServiceProvider` or where you call `Paginator::useTailwind()` / `useBootstrapFive()` / etc., only that theme is used. You can remove the other vendor pagination views if you want to trim the repo.

---

## 4. Controllers / models / jobs

- **Controllers:** All 27 controller files are referenced in `routes/admin.php` or `routes/api.php`.
- **Models:** All listed models are used (imported or via relationships).
- **Jobs:** All 21 job classes are dispatched from controllers or other jobs.

No unused controllers, models, or jobs were found.

---

## 5. Duplicate / alternate path (cosmetic)

| Location | Note |
|----------|------|
| `app/Models/Admin/ScheduleSidebarCampaign.php` | Same class exists with both `App\Models\Admin\` and `App\Http\Controllers\Admin\` path style in different files. Only one physical file under `app/Models/Admin/` – the “duplicate” in grep was due to path normalization. No duplicate file to delete. |

---

## 6. Public assets

- **CKEditor:** Used by add/edit article and edit post views.
- **js/script.js, general.js, copy.js, updated_dynamic_dropdown.js, search-items.js, bulk-select/script.js:** Referenced in layout or specific views.
- **js/create-campaign.js, create-schedule-campaign.js, create-sidebar-campaign.js, create-schedule-sidebar-campaign.js, create-hidden-links-campaign.js:** Used on respective create campaign pages.
- **js/extract-excel-data.js:** Used on add-domains.
- **js/domain-set/*, js/article-set/*:** Used on domain set and article set pages.

No obviously unused public JS files were found. If you have other assets (e.g. images, old ZIPs like `js.zip`), you can remove them if not linked anywhere.

---

## 7. Summary – safe to remove (after backup)

| # | File path |
|---|-----------|
| 1 | `resources/views/admin/sticky/make-sticky-campaign.blade.php` |
| 2 | `resources/views/admin/pbn-campaigns/post.blade.php` |
| 3 | `resources/views/admin/campaigns/sticky-post/campaign.blade.php` |
| 4 | `resources/views/admin/article/category.blade.php` |
| 5 | `resources/views/admin/domains/view-domain-set.blade.php` |
| 6 | `resources/views/test.blade.php` |
| 7 | `resources/views/admin/test.blade.php` |
| 8 | `resources/views/admin/utils/pagination.php` |

**Optional:** Unused vendor pagination themes (see section 3) if you want to reduce view files.

---

## 8. Notes

- **layout.layout:** Views that extend `layout.layout` (e.g. test views, make-sticky-campaign) will error if rendered, because the correct layout is `admin.layout.layout`. Those views are unused and safe to remove.
- **Sticky campaign:** The app uses the same PBN post campaign views with `is_sticky=1`; the old sticky-specific views above are redundant.
- Before deleting, run a quick test (e.g. visit main admin pages and sticky/schedule sidebar list) to confirm nothing links to these views.
