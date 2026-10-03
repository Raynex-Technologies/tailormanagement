# SMS retries on Hostinger Cloud / shared hosting

The SMS Logs bulk action writes to `sms_retries`, a dedicated persistent database queue. It does not send messages inside the browser request and does not use Laravel's generic `jobs` queue. `sms:work-retries` is the only worker needed for this queue; existing WhatsApp queue workers remain separate. No Redis, Supervisor, or permanently running process is required.

## Deploy

1. Deploy the code and back up the database using your normal deployment process.
2. Run the scoped migration to add `sms_retries` (no existing log rows are changed):

   ```sh
   php artisan migrate --path=database/migrations/2026_10_03_000001_create_sms_retries_table.php --force
   ```
3. Keep `CACHE_STORE=database`. Ensure the existing `cache` and `cache_locks` tables are migrated; workers must share this cache for locking and pacing.
4. Run `php artisan config:cache` and `php artisan view:clear`.
5. Add an hPanel PHP/custom cron job, ideally every minute (`* * * * *`), using the actual PHP executable and absolute application path from your account:

   ```text
   /actual/path/to/php /home/ACCOUNT/domains/DOMAIN/application/artisan sms:work-retries
   ```

   Replace the placeholders; `artisan` must be the application's file, not a presumed `public_html` path. If the account only allows a longer interval, sending starts and resumes at that interval. Inspect cron output in hPanel. Do not add a second scheduler entry for the same worker.

6. Start with one known failed, still-relevant SMS. Queue it in SMS Logs, wait for cron, then inspect the attempt/provider result and confirm receipt on the recipient's phone. No real delivery was tested during implementation.

## Limits and controls

Optional environment settings (run `config:cache` after changing):

```dotenv
SMS_RETRY_MAX_MESSAGES=10
SMS_RETRY_MAX_SECONDS=40
SMS_RETRY_SPACING_SECONDS=2
```

Defaults process at most 10 entries, stop starting work after 40 seconds, and leave at least two seconds between retry sends. A started HTTP request may finish after the time budget. The retry worker uses a 10-second HTTP timeout and disables automatic HTTP POST retries. Runtime is bounded below the five-minute worker lock. The maximum configurable time budget is 45 seconds. Confirm your account's actual CLI limits. These limits cover this retry worker; other application SMS sends still use their existing flow.

The page polls every 15 seconds and displays cumulative queue state counts for the current branch scope. Pause/cancel affect waiting work only, not a request already claimed. Clear Logs cancels waiting retries in the same scope. Resume releases paused entries after the operator confirms the failure has been corrected. Queue again allows known failed/cancelled entries up to three actual attempts total; the panel shows the ten most recently updated entries requiring attention.

Failures pause waiting retries across branches because the Beem credentials are shared. Authorized operators resume only their own visible branch scope. An explicit HTTP 429 delays the worker for 5 then 10 minutes, capped at three attempts per entry. Other failures require review; fix credentials/balance/recipient issues before queuing again or resuming. Newly queued entries are distinct from paused entries; do not create more work while correcting a provider outage.

Unknown outcomes (network errors, HTTP 5xx, or an interrupted worker) are not automatically resent. A stale processing claim is marked unknown after five minutes on a subsequent cron run and waiting retries are paused. Reconcile these with the provider before any new notification; there is deliberately no blind resend button for unknown outcomes. Local duplicate protection cannot guarantee exactly-once delivery across a provider/network failure.

Bulk retries include Beem SMS only. Invalid phone numbers, known uncertain historical outcomes, and WhatsApp logs are excluded. Historical identical failures are grouped using branch, provider, recipient, message, template, and reference. Original SMS logs remain intact; a new attempt is written by the existing SMS service.

## Troubleshooting

Application logs rotate daily into `storage/logs/laravel-YYYY-MM-DD.log`, retaining 14 days by default. Deploy these production environment settings and run `php artisan config:cache`:

```dotenv
LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=info
LOG_DAILY_DAYS=14
```

Each claimed retry writes `sms.retry.started` and `sms.retry.completed`, with retry ID, branch, original/attempt log IDs, actor, attempt number, final queue status, reason, HTTP status when available, elapsed milliseconds, and next availability. A rate-limited attempt finishes with `status=pending` and a future `available_at`. Skips are recorded too. After a killed worker, the next recovery run writes `sms.retry.interrupted` for each stale claim. These structured entries exclude phone numbers, SMS text and credentials; existing provider/service logs retain their existing behavior. Search by `retry_id` or `attempt_log_id` to correlate events. Keep the logging level at `info` or `debug` to retain successful attempts. Previously created `laravel.log` files are not removed.

- Pending never changes: check cron output, actual PHP version/path, migration status, and database cache tables.
- Paused: inspect the failed attempt in SMS Logs, correct the cause, then resume waiting retries.
- Processing for more than five minutes: allow the next cron run to mark it unknown; verify delivery before resending.
- Do not use `queue:retry all` for these retries: they are stored in `sms_retries`, not `failed_jobs`.

Stop the cron job to disable processing immediately between requests. Pending work remains in the database. Keep the migration/table while any work or history must be retained.
