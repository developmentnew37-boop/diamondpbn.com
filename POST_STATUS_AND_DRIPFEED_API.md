# Post status & dripfeed API (External API Manager 8.3.0+)

State-only endpoints used when converting **live** PBN posts to dripfeed. Laravel must **not** call `POST /posts/create` for converted rows.

Base path: `/wp-json/external/v1/`

Authentication: header `X-External-API-Key` and/or JSON body `api_key`.

## Health

- `GET /status` — public reachability probe
- `POST /status/check` — authenticated; validates API key and returns `plugin_version`

Minimum agent version for conversion: **8.3.0**.

## Draft live post

`POST /posts/draft/{id}`

Body:

```json
{
  "api_key": "YOUR_KEY"
}
```

Success may include `action: "skipped"` when already draft — treat as success (idempotent).

## Publish existing post

`POST /posts/publish/{id}`

After publish + date update, Laravel **GET `/posts/{id}`** and only marks success when WordPress `post_status` is **`publish`** (public) or **`future`** (scheduled — URL may 404 until the slot). **`draft`** is treated as failure and the permalink copied from the old live post must be refreshed from the API response.

## Set post date (after publish)

`POST /posts/update/{id}`

Body (Laravel sends both keys for compatibility):

```json
{
  "api_key": "YOUR_KEY",
  "post_date": "2026-07-27 14:30:00",
  "schedule_time": "2026-07-27 14:30:00"
}
```

## Conversion date rules (dashboard)

| Assigned slot (calendar day) | WordPress action |
|-----------------------------|------------------|
| Before conversion day | Draft, then publish immediately with **post date = conversion day** |
| Conversion day | Draft, then publish with **post date = conversion day** |
| Future slot | Draft, remain draft until slot day; cron publish with **post date = slot day** |

See `CampaignLiveToDripfeedConversionService::resolvePublishDate()` and jobs `DraftConvertedLivePostsJob`, `ApplyConvertedPostScheduleJob`.
