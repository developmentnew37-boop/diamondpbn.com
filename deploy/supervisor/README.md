# Supervisor setup for Laravel queue worker (VPS)

## 1. Install Supervisor (Ubuntu/Debian)

```bash
sudo apt update
sudo apt install supervisor
```

## 2. Deploy the config

- Replace `/var/www/pbn_automation_software` with your actual project path on the VPS.
- Replace `user=www-data` with your web server user (e.g. `www-data`, `nginx`, or your deploy user).

Copy configs to Supervisor:

```bash
sudo cp deploy/supervisor/laravel-worker.conf /etc/supervisor/conf.d/laravel-worker.conf
sudo cp deploy/supervisor/laravel-webhook-worker.conf /etc/supervisor/conf.d/laravel-webhook-worker.conf
sudo cp deploy/supervisor/laravel-scheduler.conf /etc/supervisor/conf.d/laravel-scheduler.conf
```

**Production (diamondpbn VPS):** append the `[program:laravel-scheduler]` block from `laravel-scheduler.conf` to `/etc/supervisor/conf.d/diamondpbn.conf`, or use a separate conf file.

Edit if needed:

```bash
sudo nano /etc/supervisor/conf.d/laravel-worker.conf
```

## 3. Create log file (optional)

```bash
sudo touch /var/www/pbn_automation_software/storage/logs/worker.log
sudo chown www-data:www-data /var/www/pbn_automation_software/storage/logs/worker.log
```

## 4. Reload and start

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*
sudo supervisorctl start laravel-webhook-worker:*
```

## Webhook secret rotation (dedicated queue)

| Item | Value |
|------|--------|
| Schedule check | `routes/console.php` — **hourly** (`refresh_webhook_secrets`) |
| Rotation interval | Admin UI → Webhook Secrets → **8 hours** (or 1, 12, 24, etc.) stored in `webhook_rotation_settings` |
| Job | `RefreshWebhookSecretsJob` |
| Queue | `webhook-secret` |
| Worker | `webhook-secret-worker` in Supervisor (`queue:work --queue=webhook-secret`) |

**Server load:** Very low. The job only updates DB rows (no HTTP). One worker process is enough.

**Scheduler required** (dispatches the hourly check into the queue). Use **either**:

```bash
# Option A — Supervisor (recommended if you already use Supervisor for queues)
php artisan schedule:work   # see laravel-scheduler.conf

# Option B — system cron every minute
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

Without Option A or B, `webhook-secret-worker` sits idle and secrets never auto-rotate.

**What can load the server more** (not webhook): `scheduled_campaigns` dispatch every minute, large PBN publish queues, domain status checks with many parallel HTTP calls. Use separate Supervisor programs per queue group if needed.

## 5. Useful commands

```bash
# Status
sudo supervisorctl status laravel-worker:*

# Stop all workers
sudo supervisorctl stop laravel-worker:*

# Restart after code deploy
sudo supervisorctl restart laravel-worker:*
sudo supervisorctl restart laravel-webhook-worker:*

# Tail worker log
tail -f /var/www/pbn_automation_software/storage/logs/worker.log
```

## Options in the config

- **numprocs=2** – number of worker processes (increase if you have many jobs).
- **--queue=...** – **required**. The main worker listens to all named queues (campaigns, domain checks, plugin deploy, bulk updates, etc.). Without `--queue`, only `default` is processed.
- **--sleep=3** – seconds to sleep when no jobs.
- **--tries=3** – job retry attempts.
- **--max-time=3600** – restart worker after 1 hour (releases memory, picks up code changes).
- **stopwaitsecs=3600** – max seconds to wait for a job to finish on shutdown.

If you use **Redis** for queues, change the command to:

```bash
command=php /var/www/pbn_automation_software/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
```
