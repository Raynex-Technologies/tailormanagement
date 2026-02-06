# Operations Guide

This document provides operational procedures for the Tailoring Business Dashboard.

## Table of Contents
- [Daily Operations](#daily-operations)
- [Monthly Operations](#monthly-operations)
- [Capital Allocation Management](#capital-allocation-management)
- [Branch Management](#branch-management)
- [SMS Troubleshooting](#sms-troubleshooting)
- [Common Tasks](#common-tasks)

---

## Daily Operations

### Morning Checklist
1. Check `/health` endpoint is responding
2. Review any failed SMS notifications in SMS Logs
3. Check for pending stock requests
4. Review any overdue orders

### End of Day
1. Verify all payments have been recorded
2. Check inventory levels for low stock alerts
3. Review pending purchase requests

---

## Monthly Operations

### Start of Month
1. Close previous month's capital allocations
2. Create new capital allocations for accountants
3. Review and archive completed orders
4. Generate monthly reports

### End of Month
1. Export all reports for the month
2. Reconcile capital transactions
3. Verify all expenses are categorized
4. Backup database

---

## Capital Allocation Management

### Creating a New Allocation

1. Navigate to **Procurement > Capital**
2. Click **Create Allocation**
3. Fill in:
   - **Accountant**: Select the responsible accountant
   - **Period**: Start and end dates (typically monthly)
   - **Initial Amount**: The allocated budget
4. Click **Create**

### Closing an Allocation

Capital allocations should be closed at the end of their period:

1. Navigate to **Procurement > Capital**
2. Click on the allocation to close
3. Review all transactions and remaining balance
4. Click **Close Allocation**
5. Confirm closure

**Important Notes:**
- Closed allocations cannot have new expenses or purchases linked
- Any remaining balance should be documented
- Create a new allocation before closing the old one to maintain continuity

### Handling Overspend Scenarios

If an allocation runs out before month end:

1. Review remaining transactions
2. Either:
   - Extend the allocation amount (if permitted)
   - Create a new allocation for the remainder of the period
   - Wait until new allocation is created

### Monthly Reconciliation Process

1. **Export Transactions**: Reports > Capital Audit > Export Transactions
2. **Verify Totals**: Ensure spent amount matches sum of transactions
3. **Check Expenses**: Verify all linked expenses are properly categorized
4. **Document Discrepancies**: Note any discrepancies for review
5. **Close Allocation**: Once verified, close the allocation

---

## Branch Management

### Admin Branch Switching

Global admins (superadmin/admin roles) can view data from any branch:

1. The system requires an **active branch context**
2. To switch branches:
   - Use the branch selector in the dashboard (if implemented)
   - Or POST to `/admin/active-branch/{branch_id}`

### Setting Active Branch via API

```bash
# Set active branch
curl -X POST https://yourdomain.com/admin/active-branch/2 \
  -H "Authorization: Bearer {token}"

# Clear active branch (view all)
curl -X POST https://yourdomain.com/admin/active-branch \
  -H "Authorization: Bearer {token}"
```

### Branch-Specific Reports

When generating reports:
1. Ensure correct branch is selected
2. All data will be scoped to that branch
3. Export includes only that branch's data

### Creating a New Branch

1. This requires database access (no UI currently)
2. Insert into `branches` table:
```sql
INSERT INTO branches (code, name, phone, address, is_active, created_at, updated_at)
VALUES ('BR-XXX-01', 'New Branch Name', '+255xxxxxxxxx', 'Address', 1, NOW(), NOW());
```
3. Assign users to the new branch

---

## SMS Troubleshooting

### When SMS Fails

1. **Check SMS Logs**: Navigate to Administration > SMS Logs
2. **Review Error Messages**: Look for specific error codes
3. **Verify Configuration**: Check `.env` for correct Beem credentials

### Common SMS Issues

| Issue | Possible Cause | Solution |
|-------|---------------|----------|
| All SMS failing | Invalid API credentials | Verify `BEEM_API_KEY` and `BEEM_SECRET_KEY` |
| SMS not sending | `SMS_ENABLED=false` | Set `SMS_ENABLED=true` in `.env` |
| Specific number failing | Invalid phone format | Check phone number format (+255...) |
| Delivery pending | Network issues | Wait and check delivery status later |
| Insufficient balance | Beem account low | Top up Beem account |

### Re-sending Failed SMS

Currently, SMS cannot be automatically retried. To manually notify:
1. Note the customer phone from the failed log
2. Contact them directly
3. Or record a note on the order

### Phone Number Format

All phone numbers should be normalized to international format:
- Valid: `+255712345678`
- Invalid: `0712345678`, `712345678`

The system attempts to normalize Tanzanian numbers automatically.

---

## Common Tasks

### Resetting User Password

As admin:
1. Navigate to Administration > Users
2. Find the user
3. Click Edit
4. Enter new password
5. Save

Or via command line:
```bash
php artisan tinker
>>> $user = User::where('email', 'user@example.com')->first();
>>> $user->password = Hash::make('newpassword');
>>> $user->save();
```

### Viewing System Logs

```bash
# Recent errors
tail -100 storage/logs/laravel.log

# Search for specific errors
grep -i "error" storage/logs/laravel.log

# Clear old logs
truncate -s 0 storage/logs/laravel.log
```

### Clearing Application Cache

```bash
# Clear all caches
php artisan optimize:clear

# Clear specific caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Database Maintenance

```bash
# Optimize tables
php artisan db:monitor  # Check connection status

# Run in MySQL directly for optimization
OPTIMIZE TABLE orders, order_payments, inventory_transactions, expenses;
```

### Checking Application Health

1. **HTTP Check**: `GET /health`
2. **Laravel Check**: `GET /up` (built-in)
3. **Manual Check**:
```bash
php artisan about
php artisan route:list --count
```

---

## Emergency Procedures

### Application Down

1. Check web server: `sudo systemctl status nginx` (or apache2)
2. Check PHP-FPM: `sudo systemctl status php8.3-fpm`
3. Check database: `sudo systemctl status mysql`
4. Check logs: `tail -100 storage/logs/laravel.log`

### Database Connection Lost

1. Verify MySQL is running
2. Check `.env` credentials
3. Test connection: `php artisan tinker` then `DB::connection()->getPdo();`
4. Restart services if needed

### High Server Load

1. Check running processes: `top` or `htop`
2. Clear caches: `php artisan optimize:clear`
3. Check for runaway queries in MySQL: `SHOW PROCESSLIST;`
4. Consider restarting PHP-FPM: `sudo systemctl restart php8.3-fpm`

---

## Support Contacts

- **Technical Issues**: [your-email@domain.com]
- **SMS Provider (Beem)**: support@beem.africa
- **Hosting Support**: Refer to your hosting provider's support channels

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | Jan 2026 | Initial release |
