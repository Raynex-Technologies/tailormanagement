# Phone numbers and country codes

All editable contact phone fields use a country-code selector (Tanzania +255 first/default) and a national-number field with a numeric keyboard. This includes customer create/edit, Orders new customer, POS, public booking and WhatsApp contact, checkout, saved addresses, delivery-note recipient, suppliers, branches, business/storefront contacts, and the configured WhatsApp sender.

Server validation uses `giggsey/libphonenumber-for-php`. Letters, extensions, malformed country codes, and numbers invalid for their numbering plan are rejected. Optional phone fields remain optional. Stored phone values are strings, never numeric database columns. Valid numbering format does not prove that a number is assigned, reachable, or owned by the customer.

For example, a Tanzanian `0712345678` is saved as:

```text
phone = +255712345678
phone_country_code = +255
phone_national_number = 712345678
```

Each contact field has corresponding `_country_code` and `_national_number` columns. Existing full-number fields remain available to receipts, SMS, WhatsApp, searches, and integrations. Customer phone uniqueness stays branch-scoped, with canonicalization before validation. Metadata also detects clashes with backfilled legacy formatting. The unique full-phone database index continues to protect concurrent new customer inserts.

The migration only fills derived fields for valid historical numbers. It does not rewrite original values, merge customers, or delete invalid numbers. Invalid historical values retain null derived fields and must be corrected when edited; SMS/WhatsApp sending rejects them. Unrelated model edits preserve unchanged historical phone values. Historical logs and already-sent messages are unchanged.

## Deploy

Deploy the changed application files and both Composer files, then run:

```sh
composer install --no-dev --optimize-autoloader
php artisan migrate --path=database/migrations/2026_10_03_000002_add_phone_country_codes.php --force
php artisan config:cache
php artisan view:clear
```

Deploy code, dependencies and the schema together during a quiet deployment window. The migration scans contact tables in chunks of 200; back up the database first. No frontend asset build is needed for the Blade-only phone controls. Restart any existing long-running workers after deployment; the SMS cron worker loads current code each run.

Test a Tanzanian and an international customer, edit/reset a customer modal, switch country without changing the national number, reject letters/short numbers, reject a duplicate using a different format, and save a storefront address. Verify the split fields in the database. Keep provider delivery testing separate from form/validation testing.

Automated coverage exercises validation, Livewire saves, split storage, legacy migration behavior, duplicate checks, storefront request handling and invalid outbound SMS. Interactive browser acceptance must be performed on the deployed forms if the in-app browser is unavailable.
