# 05 — Database transport & the message bus worker

The `database` transport stores messages in the `message_bus_messages` table and is
consumed by the package's own worker, `php artisan message-bus:consume`. No Redis or queue
server required — great for small apps, shared hosting and as the **failure transport**.

## Setup

```bash
php artisan message-bus:install   # publishes config + migration, runs migrate
```

## Files

| File | Purpose |
|------|---------|
| `config/message-bus.php` | Three database transports (high/low priority + failed). |
| `app/Messages/ResizeImage.php` | High priority work. |
| `app/Messages/RebuildSearchIndex.php` | Low priority work. |
| `supervisor/message-bus-consume.conf` | Long running workers. |
| `deploy.sh` | What to run on every deploy. |

Several transports can share one table: each uses its own `queue` name.

## Consuming

```bash
# Transports are polled in the given order: "priority" is always emptied before "default"
php artisan message-bus:consume priority default

# Without arguments: every pull based transport except sync, queue and the failure transport
php artisan message-bus:consume

# Limits (the process exits cleanly, Supervisor restarts it)
php artisan message-bus:consume priority default \
    --limit=1000 --time-limit=3600 --memory-limit=256 --sleep=500

# Process everything that is waiting and exit (cron friendly)
php artisan message-bus:consume default --stop-when-empty
```

Output:

```
  INFO  Consuming messages from [priority, default].

  12:01:03 ResizeImage (priority) ................................. DONE
  12:01:04 RebuildSearchIndex (default) ........................... DONE
  12:01:09 ResizeImage (priority) ................................. FAIL
```

## Monitoring

```bash
php artisan message-bus:stats
+-----------+----------+---------+
| Transport | Driver   | Pending |
+-----------+----------+---------+
| sync      | sync     | n/a     |
| priority  | database | 3       |
| default   | database | 128     |
| failed    | database | 2       |
+-----------+----------+---------+
```

## Deploying

Workers keep your old code in memory. `message-bus:stop-workers` asks every worker to exit
after its current message (Supervisor starts fresh ones). See `deploy.sh`.

A message that is delivered but never acknowledged (worker killed mid-way) becomes visible
again after `redeliver_timeout` seconds.
