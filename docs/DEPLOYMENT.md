# Deployment Guide

This document provides instructions for deploying the Tailoring Business Dashboard to production environments.

## Table of Contents
- [Environment Requirements](#environment-requirements)
- [Environment Variables](#environment-variables)
- [Shared Hosting (Hostinger)](#shared-hosting-hostinger)
- [VPS Deployment](#vps-deployment)
- [Post-Deployment](#post-deployment)
- [Backup & Recovery](#backup--recovery)
- [Troubleshooting](#troubleshooting)

---

## Environment Requirements

- **PHP**: 8.3+ with extensions: BCMath, Ctype, cURL, DOM, Fileinfo, JSON, Mbstring, OpenSSL, PCRE, PDO, Tokenizer, XML
- **MySQL**: 8.0+ or MariaDB 10.5+
- **Composer**: 2.x
- **Node.js**: 18+ (for building assets)

---

## Environment Variables

Copy `.env.example` to `.env` and configure these critical variables:

### Application
```env
APP_NAME="Tailor Dashboard"
APP_ENV=production
APP_KEY=  # Generate: php artisan key:generate
APP_DEBUG=false
APP_URL=https://yourdomain.com
```

### Database
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tailor_db
DB_USERNAME=your_db_user
DB_PASSWORD=your_secure_password
```

### Session & Cache
```env
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=sync  # Use 'database' if you need async jobs
```

### SMS (Beem Africa)
```env
BEEM_API_KEY=your_api_key
BEEM_SECRET_KEY=your_secret_key
BEEM_SENDER_ID=your_sender_id
SMS_ENABLED=true
```

### Mail (Optional)
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=587
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"
```

---

## Shared Hosting (Hostinger)

### 1. Upload Files

Upload all files to your hosting root (e.g., `public_html` or `htdocs`).

### 2. Configure Document Root

Point your domain's document root to the `public` folder:
- In Hostinger hPanel: Websites → Manage → Website folder → Change to `/public_html/public`

### 3. Create `.htaccess` in Root

Create `.htaccess` in the main folder (not public):
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
```

### 4. Set Permissions

```bash
chmod -R 755 storage bootstrap/cache
chmod -R 644 .env
```

### 5. Storage Symlink (Optional)

TailorPro image uploads do **not** rely on `storage:link` because images are served from `public/uploads/images`.

Only run `storage:link` if your deployment still needs legacy `/storage/*` assets for non-image files.

### 6. Configure Cron Job

In Hostinger hPanel → Cron Jobs:
```
* * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1
```

---

## VPS Deployment

### 1. Server Setup (Ubuntu/Debian)

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install required packages
sudo apt install -y nginx mysql-server php8.3-fpm php8.3-mysql \
    php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath \
    php8.3-gd composer nodejs npm git

# Configure MySQL
sudo mysql_secure_installation
```

### 2. Create Database

```sql
CREATE DATABASE tailor_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'tailor_user'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON tailor_db.* TO 'tailor_user'@'localhost';
FLUSH PRIVILEGES;
```

### 3. Clone & Configure Application

```bash
cd /var/www
git clone your-repo-url tailoring
cd tailoring

# Install dependencies
composer install --no-dev --optimize-autoloader
npm ci && npm run build

# Configure environment
cp .env.example .env
nano .env  # Edit with your values

# Generate key
php artisan key:generate
```

### 4. Set Permissions

```bash
sudo chown -R www-data:www-data /var/www/tailoring
sudo chmod -R 755 /var/www/tailoring
sudo chmod -R 775 storage bootstrap/cache
```

### 5. Nginx Configuration

Create `/etc/nginx/sites-available/tailoring`:
```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /var/www/tailoring/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Enable the site:
```bash
sudo ln -s /etc/nginx/sites-available/tailoring /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 6. SSL Certificate (Let's Encrypt)

```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d yourdomain.com
```

### 7. Cron Setup

```bash
sudo crontab -e
# Add:
* * * * * cd /var/www/tailoring && php artisan schedule:run >> /dev/null 2>&1
```

---

## Post-Deployment

### Run Migrations

```bash
php artisan migrate --force
```

### Seed Initial Data (First Deployment Only)

```bash
# ONLY in production for first deployment
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan db:seed --class=SuperAdminSeeder --force
```

### Optimize for Production

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan optimize
```

### Image Path Normalization

```bash
php artisan media:normalize-image-paths
```

---

## Backup & Recovery

### Database Backup

```bash
# Manual backup
mysqldump -u tailor_user -p tailor_db > backup_$(date +%Y%m%d_%H%M%S).sql

# Automated daily backup (add to crontab)
0 2 * * * mysqldump -u tailor_user -p'password' tailor_db | gzip > /backups/db_$(date +\%Y\%m\%d).sql.gz
```

### Application Backup

```bash
tar -czvf app_backup_$(date +%Y%m%d).tar.gz \
    --exclude=node_modules \
    --exclude=vendor \
    --exclude=storage/logs \
    /var/www/tailoring
```

### Restore Database

```bash
mysql -u tailor_user -p tailor_db < backup_file.sql
```

---

## Rollback Steps

If deployment fails:

1. **Revert Code**
```bash
git checkout HEAD~1  # Go back one commit
# OR
git checkout <previous-tag>
```

2. **Rollback Migration** (if needed)
```bash
php artisan migrate:rollback --step=1
```

3. **Clear Caches**
```bash
php artisan optimize:clear
```

4. **Restore Database** (if needed)
```bash
mysql -u tailor_user -p tailor_db < last_good_backup.sql
```

---

## Troubleshooting

### Common Issues

**500 Error / Blank Page**
```bash
tail -f storage/logs/laravel.log
chmod -R 775 storage bootstrap/cache
```

**Session/Cache Issues**
```bash
php artisan cache:clear
php artisan config:clear
```

**Database Connection Failed**
- Verify `.env` database credentials
- Check MySQL is running: `sudo systemctl status mysql`
- Test connection: `mysql -u user -p -h host database`

**Public Uploads Not Accessible**
```bash
chmod -R 755 public/uploads/images
```

### Health Check

Visit `/health` endpoint to verify the application is running:
```
GET https://yourdomain.com/health
Response: {"status":"ok","time":"2026-01-28T...","app":"Tailor Dashboard","version":"1.0.0"}
```

---

## Security Checklist

- [ ] `APP_DEBUG=false` in production
- [ ] Strong database password
- [ ] SSL certificate installed
- [ ] File permissions correct (755 folders, 644 files)
- [ ] `.env` file not accessible via web
- [ ] Firewall configured (only ports 80, 443, 22 open)
- [ ] Regular backups configured
- [ ] Monitoring/alerts configured
