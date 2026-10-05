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
3. Select the branch, open Administration > Twilio WhatsApp, enter the Twilio Account SID, Auth Token, and registered WhatsApp sender. Click Test Connection to save the current form and immediately verify it with Twilio. Save Settings only stores the form. Test feedback replaces any earlier save notice. This verifies account credentials, not sender registration or delivery.
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

Connection-action follow-up: Test Connection now saves and validates the current form, calls Twilio immediately, and replaces prior flash notices with the API result. Unchanged settings preserve webhook verification. The focused Livewire action tests passed 3 tests / 28 assertions using simulated Twilio HTTP responses; no live account test is claimed.

Template route follow-up (2026-10-05): The template index/create/edit routes now use the same authentication, verification, and branch-context middleware as the administration area. Previously these routes omitted branch initialization, so fresh HTTP requests could fail at BranchContext::requireId even when a branch was saved in the session. The index shows a branch-required state when no active branch exists; its actions reject missing context without contacting Twilio. The editor rejects missing context with HTTP 403. Existing Livewire persistent middleware preserves the same checks on subsequent component requests.

Focused fresh-request and branch regression tests passed: 3 tests / 25 assertions. PHP syntax checks and Pint passed. No real Twilio messages were sent during this fix.

Template variable follow-up (2026-10-05): The WhatsApp editor uses the default SMS variable catalogue, combining `SmsTemplate::variableDefinitions()` with all category-specific options. Clickable variable options appear in the right column below Message preview, replacing the insertion dropdown and separate Insert button. Clicking an option appends a numbered placeholder and sets its mapping in one action; manually typed numbered placeholders expose a mapping selector. Samples remain editable for approval and preview. Use variables available to the notification that sends the template. Existing unsupported mappings remain visible without automatic substitution; Twilio validation and submission reject unsupported names. No stored templates were migrated or submitted.

The variable and affected editor regressions passed: 4 tests / 31 assertions, including saved mapping readback and the serialized Twilio `ContentVariables` request with actual notification values rather than approval samples. The other 17 Twilio tests passed in the initial run; the editor rendering failure in that run was corrected before the focused rerun. Tests used guarded in-memory SQLite and simulated HTTP. PHP syntax and Pint passed. Full-app `view:cache` was stopped after an unusually long run; no successful full-cache check is claimed for this change. Live delivery and browser appearance were not tested.

The subsequent one-click variable layout passed its focused regressions: 3 tests / 29 assertions, including rendered option placement, direct insertion, saved mappings, rejected unsupported variables, and simulated Twilio values. These overlap the earlier tests.

Name normalization follow-up: The editor automatically lowercases names, removes unsupported characters and replaces whitespace runs with underscores on field blur and before saving. Digits and existing underscores remain intact. For example, `Order READY_2!` becomes `order_ready_2`. An empty result remains a required-field error; cleanup does not bypass immutable-content or submission-validation checks. Stored templates are not bulk-renamed.

Automatic examples follow-up: `SmsTemplate::variableExamples()` now supplies stable representative samples for every default SMS variable, including category-specific options. Existing SMS definitions had variable names and descriptions but no sample values; these constructed samples are approval/preview data, not extracted customer records. Variable insertion and mapping selection fill samples automatically. Mapping changes update a previous default sample while preserving custom examples. Reopening an editable draft and Save and validate fill missing known samples without requiring staff entry. Existing Content SID and uncertain-creation templates are excluded from automatic example replacement. Reopening does not write to the database or preserve stale submission readiness. Actual notifications continue to resolve their own runtime values rather than approval samples.

Automatic-example verification: 9 focused tests / 145 assertions passed using guarded in-memory SQLite and simulated HTTP, covering complete variable coverage, default and custom samples, legacy draft reopening, immutable-content preservation, validation gating, and actual send-time values. Pint, PHP syntax and scoped diff checks passed. No live template submission or customer message was sent.

MySQL Submit visibility correction: A read-only check of the reported saved template confirmed `ready_to_submit`, successful validation and a matching persisted validation fingerprint, but the form fingerprint differed because MySQL reordered JSON object keys (`text`, `type`, `examples`). Definition comparison now recursively sorts object keys while preserving list order and strict values. The editor uses that comparison for restored validation, Submit visibility and unsaved-change detection; the immutable-content save guard uses it too. Existing stored fingerprints and provider provenance are unchanged. A second read-only check of the same local record returned `canSubmit: true` without resaving it. A regression reproduces the MySQL ordering in isolated SQLite, verifies Submit is shown, and confirms changed examples still block submission. Temporary diagnostics were removed; no real provider request was made.

Submit visibility verification: Workflow and Twilio regressions passed 23 tests / 173 assertions. Pint, scoped diff checks and PHP syntax checks passed. Actual local MySQL readiness was checked read-only; a live browser click and live Twilio submission were not performed.

Validation workflow and index follow-up (2026-10-05): The editor now presents one Save and validate action. Submit to Twilio is revealed only when persisted successful validation matches the current form and the template is eligible for submission; edits hide it again. Structural validation errors are mapped to Livewire fields (including each missing example), displayed inline with invalid input styling, and summarized above the form. Reopened invalid drafts retain those errors. Conflicting save-success and validation-failure flashes are cleared. The name field explains lowercase/underscore naming; empty samples leave visible placeholders in preview.

The index reuses the Orders hero, breadcrumbs and configured theme tokens. Template cards have right-aligned actions and separate local-workflow and Twilio approval badges. Update-only actions remain permission gated and all queries/actions retain branch scoping.

Verification: 10 focused workflow, variable, fresh-request branch and stale-submission regressions passed / 106 assertions. Pint, diff checks and Vite build passed. The built-in browser returned no available browser, so standalone Playwright Chromium rendered isolated application HTML from in-memory SQLite fixtures with local assets. Screenshots at 1440px and 390px plus the dark index were inspected with view_image against the supplied screenshot and existing Orders design. Checked header hierarchy, theme colors, action alignment, status readability, inline errors and staged button visibility; no horizontal overflow was detected. The screenshot comparison confirmed the requested changes, not a pixel-identical reproduction of the prior defective screen. Preview text, instructional copy and button labels changed deliberately. Browser fixtures used test branding/default colors and unavailable external webfonts fell back to local fonts; live user-session interaction and real Twilio submission were not exercised. Interactive saving and submission guards were verified in Livewire tests. Temporary HTML, screenshot and QA scripts were removed; no development data or provider resources were changed.
