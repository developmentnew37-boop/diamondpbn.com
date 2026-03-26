# Supervisor setup for Laravel queue worker (VPS)

## 1. Install Supervisor (Ubuntu/Debian)

```bash
sudo apt update
sudo apt install supervisor
```

## 2. Deploy the config

- Replace `/var/www/pbn_automation_software` with your actual project path on the VPS.
- Replace `user=www-data` with your web server user (e.g. `www-data`, `nginx`, or your deploy user).

Copy the config to Supervisor:

```bash
sudo cp deploy/supervisor/laravel-worker.conf /etc/supervisor/conf.d/laravel-worker.conf
```

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
```

## 5. Useful commands

```bash
# Status
sudo supervisorctl status laravel-worker:*

# Stop all workers
sudo supervisorctl stop laravel-worker:*

# Restart after code deploy
sudo supervisorctl restart laravel-worker:*

# Tail worker log
tail -f /var/www/pbn_automation_software/storage/logs/worker.log
```

## Options in the config

- **numprocs=2** – number of worker processes (increase if you have many jobs).
- **--sleep=3** – seconds to sleep when no jobs.
- **--tries=3** – job retry attempts.
- **--max-time=3600** – restart worker after 1 hour (releases memory, picks up code changes).
- **stopwaitsecs=3600** – max seconds to wait for a job to finish on shutdown.

If you use **Redis** for queues, change the command to:

```bash
command=php /var/www/pbn_automation_software/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
```
