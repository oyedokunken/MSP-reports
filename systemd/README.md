# MSP Reports - Systemd Services

This directory contains systemd service files for managing the Laravel queue workers and scheduler.

## Services

| Service | Description |
|---------|-------------|
| `mspreports-worker@.service` | Template for queue worker instances (run multiple) |
| `mspreports-worker.service` | Single queue worker (alternative to template) |
| `mspreports-scheduler.service` | Laravel scheduler (runs scheduled tasks every minute) |

## Quick Installation

```bash
sudo ./install-services.sh
```

## Manual Installation

1. **Copy service files to systemd:**
   ```bash
   sudo cp mspreports-*.service /etc/systemd/system/
   sudo systemctl daemon-reload
   ```

2. **Enable services to start on boot:**
   ```bash
   sudo systemctl enable mspreports-scheduler
   sudo systemctl enable mspreports-worker@1
   sudo systemctl enable mspreports-worker@2
   sudo systemctl enable mspreports-worker@3
   ```

3. **Start services:**
   ```bash
   sudo systemctl start mspreports-scheduler
   sudo systemctl start mspreports-worker@1
   sudo systemctl start mspreports-worker@2
   sudo systemctl start mspreports-worker@3
   ```

## Common Commands

### Check Status
```bash
# Scheduler status
systemctl status mspreports-scheduler

# Worker status (all instances)
systemctl status 'mspreports-worker@*'

# Specific worker instance
systemctl status mspreports-worker@1
```

### View Logs
```bash
# Worker logs (from log file)
tail -f /var/www/mspreports/backend/storage/logs/worker.log

# Scheduler logs
tail -f /var/www/mspreports/backend/storage/logs/scheduler.log

# Systemd journal logs
journalctl -u mspreports-scheduler -f
journalctl -u mspreports-worker@1 -f
```

### Restart Services
```bash
# Restart scheduler
sudo systemctl restart mspreports-scheduler

# Restart all workers
sudo systemctl restart 'mspreports-worker@*'

# Restart specific worker
sudo systemctl restart mspreports-worker@1
```

### Stop Services
```bash
sudo systemctl stop mspreports-scheduler
sudo systemctl stop 'mspreports-worker@*'
```

## Configuration

The service files assume:
- **Application path:** `/var/www/mspreports/backend`
- **User/Group:** `athena`
- **Log files:** `storage/logs/worker.log` and `storage/logs/scheduler.log`

To modify these, edit the `.service` files before installation.

## Troubleshooting

### Workers not processing jobs
1. Check Redis is running: `systemctl status redis`
2. Check worker logs: `tail -100 /var/www/mspreports/backend/storage/logs/worker.log`
3. Check queue has jobs: `php artisan queue:monitor high,default,low`

### Scheduler not running tasks
1. Check scheduler status: `systemctl status mspreports-scheduler`
2. Check scheduler logs: `tail -100 /var/www/mspreports/backend/storage/logs/scheduler.log`
3. Verify cron expressions in `routes/console.php`

### Service fails to start
1. Check journalctl: `journalctl -u mspreports-worker@1 -n 50`
2. Verify paths in service file match actual installation
3. Verify user `athena` exists and has permissions

## Why Systemd over Supervisor?

- **Native integration:** Works with system boot/shutdown
- **Better logging:** Integrates with journalctl
- **Process isolation:** Security hardening options
- **Simpler management:** Standard systemctl commands
- **Automatic restart:** Built-in restart policies
