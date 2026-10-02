# Twilio WhatsApp integration

Implemented locally on 2026-10-02. Twilio is the application's active WhatsApp provider. No real Twilio account, template submission, or customer delivery was exercised.

## What changed

- Branch settings now accept Account SID, encrypted Auth Token, and a registered WhatsApp sender in E.164 format. A sender cannot be shared across branch configurations. Changing the account requires its token again. Secrets are not hydrated into Livewire or included in model serialization.
- Text templates support Utility and Marketing categories, numbered placeholders, representative examples, and internal variable mappings. Save Draft is local; Validate and Submit through Twilio are separate actions.
- Submission creates a Twilio Content resource and requests WhatsApp approval. The Content SID is retained when approval fails. Repeated submissions of the same submitted content do not create another resource. Uncertain creation outcomes block automatic recreation; the editor can recover a matching Content SID after verifying account, language, name, body, and examples against Twilio.
- Refresh Approvals checks this branch's locally linked Twilio content without overwriting drafts or mappings. Only explicitly approved, unchanged content belonging to the configured account can send. Revised submitted content must be duplicated with a new name. Deletion targets the Twilio Content SID and only changes local status after provider success.
- Notifications send `ContentSid` and JSON `ContentVariables`, without a free-form Body fallback. Queued messages snapshot provider, account, sender, Content SID, and resolved variables. The worker rechecks sender and template state. Existing customer and marketing consent gates remain. Template retries preserve template identity and variables, recheck consent, and reuse the same retry for repeated requests against an original failed message. Jobs are dispatched after the surrounding database transaction commits.
- Signed form callbacks verify the canonical public URL, all form parameters, account, and sender. Inbound text opens the customer service window for that exact Twilio account and sender; historical Meta contact timestamps do not authorize a Twilio free-form send. Delivery/read/failure callbacks update the existing message and linked notification log. Duplicate events reuse a durable event record. A callback arriving before the outgoing SID is persisted is retried by the worker.
- Historical Meta credentials, templates, statuses, messages, and logs remain. Old Meta webhooks return HTTP 410 while Twilio is active. Old Meta approvals and queued messages cannot authorize a Twilio send. Historical Meta log retries require a new Twilio notification.

## Local migration

Applied only:

```sh
php artisan migrate --path=database/migrations/2026_10_02_000001_add_twilio_whatsapp_provider.php --force
```

This additive migration introduces separate Twilio credentials and template identity/status columns. Existing Meta columns are not repurposed or deleted. Automatic destructive rollback is refused. No broad database reset was run. Local configuration cache was cleared, and runtime inspection confirmed `TwilioWhatsAppProvider`, zero configured Twilio integrations, zero templates, and zero messages.

## Setup before live use

For Hostinger Cloud Startup, follow [the shared-hosting queue and cron setup](HOSTINGER-CLOUD-STARTUP.md). Use `queue:work-batch` from hPanel cron; a permanently running worker is not required for this deployment profile.

1. Deploy the scoped migration and code together; pause WhatsApp workers during the provider switch. Rebuild assets and refresh deployment configuration/view caches; restart workers after verification.
2. Set `APP_URL` to the exact public HTTPS origin (and application base path if applicable). It is used for callback generation and signature validation.
3. Select the branch, open Administration > Twilio WhatsApp, enter the Twilio Account SID, Auth Token, and registered WhatsApp sender. Save and test the saved connection. This verifies account credentials, not sender registration or delivery.
4. Set the displayed incoming-message URL in that sender's Twilio Console settings, with HTTP POST. Outbound status callback URLs are attached to each message automatically.
5. Open Manage Templates, create a text template, add examples and mappings, save, validate, and submit through Twilio. Use Refresh Approvals until its status is Approved. Configure the matching notification template name/language and enable the desired WhatsApp notifications in the existing notification settings.
6. Verify customer consent and queue-worker operation. With an authorized test recipient, exercise a notification, delivery/read callback, and inbound reply. Only then claim live readiness.

## Boundaries

- This editor supports text bodies only. Rich media, headers, footers, buttons, and Authentication templates are blocked explicitly rather than silently altered. Existing rich Meta drafts remain readable and require explicit removal of unsupported formatting or a new text draft.
- Approval refresh is manual and limited to templates linked from this branch. Account-wide catalogue import and scheduled approval polling are not implemented.
- Incoming sender webhook setup is performed in Twilio Console. No automatic sender registration or account provisioning is attempted. Marking an inbound message read through Twilio is not implemented.
- HTTP 429 can retry through the queue. Ambiguous send timeouts/5xx responses are retained as delivery-unknown failures and require reconciliation rather than blind resend. No exactly-once guarantee is claimed across a process crash between remote acceptance and local database commit.
- Automated tests use isolated in-memory SQLite and simulated HTTP. Concurrent MySQL worker acceptance and live Twilio acceptance remain unverified.
- The browser runtime reported no available browser. Blade/Livewire rendering and asset compilation are distinct from visual browser acceptance.

## Provider references

- [Twilio Content API: creation, approval, status, deletion, and sending](https://www.twilio.com/docs/content/content-api-resources)
- [Twilio webhook signature validation](https://www.twilio.com/docs/usage/security)
- [WhatsApp sender and webhook overview](https://www.twilio.com/docs/whatsapp/api)

## Verification

- WhatsApp regression group: **59 tests / 236 assertions passed**, including the retained Meta provider regressions explicitly bound to Meta and the new Twilio default-provider tests.
- The broader messaging check had **57 passing tests and one template-editor rendering failure**. The failed rendering test was corrected and passed in isolation and in the 59-test WhatsApp run. Its failure was a Windows translation-file name collision: `Validation` resolved to the framework's translation array; the heading now uses `Template validation`. The SMS unit/settings/log checks in that run passed.
- Final Twilio-only rerun: **18 tests / 104 assertions passed**, including repeated-retry idempotence, exact account/sender service windows, and bounded placeholder validation. This overlaps the 59-test group and must not be added to it.
- Scoped local MySQL migration, runtime provider inspection, PHP syntax checks, Blade view cache, and Vite production build passed. Pint formatted the changed integration files. These checks do not prove browser appearance or live provider delivery.
