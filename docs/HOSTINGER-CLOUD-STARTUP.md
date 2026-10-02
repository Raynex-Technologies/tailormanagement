# Hostinger Cloud Startup: WhatsApp and queue setup

The Twilio integration can use Hostinger Cloud Startup's PHP, MySQL, HTTPS, and hPanel cron jobs. It does not require a VPS, Redis, Supervisor, or a permanently running queue process. Hostinger documents [Laravel support on Web and Cloud plans](https://www.hostinger.com/support/which-programming-languages-and-frameworks-are-supported-at-hostinger/) and [custom cron jobs](https://www.hostinger.com/support/1583465-how-to-set-up-a-cron-job-at-hostinger/).

## Runtime configuration

Use PHP 8.2 or newer compatible with the installed Composer dependencies, for both the website and cron CLI. Confirm the actual CLI executable with the hosting account; the `/usr/bin/php` path below follows Hostinger's documented Artisan example. Use `composer check-platform-reqs` to verify the deployed runtime and extensions.

Set the production environment, using the actual domain:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example
QUEUE_CONNECTION=database
DB_QUEUE=default
DB_QUEUE_RETRY_AFTER=180
TWILIO_TIMEOUT=30
```

Keep the deployed APP_KEY unchanged: saved Auth Tokens are encrypted with it. Configure the existing MySQL connection and deploy the required scoped WhatsApp migration. The normal Laravel `jobs` and `failed_jobs` tables must exist. Keep application files and `.env` private; expose only the application's `public` directory. The CLI user needs write access to `storage` and `bootstrap/cache`.

After environment changes, rebuild the deployment configuration cache using `php artisan config:cache`. The database connection and credentials are not included in this guide.

## hPanel cron jobs

Create two **Custom** cron jobs in Websites > Dashboard > Cron Jobs. Schedule each for every minute (all five schedule fields `*`). Enter only the command in the command field, not the five schedule fields. Replace all example account, domain, and application paths with the real absolute paths.

The existing scheduler handles reminders:

```sh
/usr/bin/php /home/u123456789/domains/domain.tld/app/artisan schedule:run
```

The new queue batch handles queued sending and webhook processing:

```sh
/usr/bin/php /home/u123456789/domains/domain.tld/app/artisan queue:work-batch
```

`app/artisan` is illustrative; use the actual location of the project's `artisan` file. Do not add a duplicate scheduler cron if one already exists. Do not run a separate long-lived queue worker alongside this cron deployment profile.

The batch calls Laravel's worker in the same PHP process, without shell spawning. It uses the configured database queue, including other application jobs already on that queue. It stops when empty, after 25 jobs, or after its 40-second budget is checked between jobs. An in-progress job can extend the total duration. Worker timeout is 45 seconds, and Twilio HTTP timeout is capped at 30 seconds. PHP PCNTL is needed for Laravel's process-level timeout enforcement; when unavailable, the HTTP timeout and between-job limits still apply. Verify the account's CLI execution limits and extensions during deployment. Long or blocking non-WhatsApp jobs sharing the default queue need their own runtime review.

A filesystem lock prevents overlapping batch invocations on this application filesystem. The operating system releases it on process exit. Never delete its lock file while a batch is running. It is not a distributed lock for multiple application servers with separate storage.

Cron processing introduces delivery and status-update delay: normally the next minute's batch when the queue is clear; slow jobs, backlog, and hosting resource limits can add delay. This is appropriate for order notifications, not a sub-second chat guarantee.

## Twilio settings and webhooks

Use [the Twilio setup guide](TAILOR-TWILIO-WHATSAPP.md) for credentials, sender registration, template submission, approval, notification mappings, and consent.

`APP_URL` must exactly match the public HTTPS origin and application base path. Twilio must reach the displayed webhook URL without login, redirects, maintenance interception, or an interactive CDN challenge. The application validates signed Twilio form requests; no public cron endpoint is required. The web request records a callback and queues processing, so cron must operate for inbound messages and delivery status to appear.

Template creation and approval requests are direct HTTPS calls from the administration page. Refresh Approvals is manual. Outbound sending uses the database queue. Keep `QUEUE_CONNECTION=database`; synchronous sending would put provider delays inside ordinary web requests.

## Deployment acceptance

Verify the cron output in hPanel; retain errors while commissioning instead of discarding them. Confirm that the queue drains, failed jobs are reviewed, the public webhook accepts a genuine signed callback, and an authorized test notification reaches its recipient. Account verification alone does not prove sender or delivery readiness.

Local verification covers worker options, actual lock exclusion/release, unsafe-configuration rejection, and an isolated database-queue smoke test. No Hostinger account was accessed or changed; live cron execution, host resource limits, and real Twilio delivery require deployment acceptance.
