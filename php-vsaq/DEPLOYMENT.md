# VSAQ PHP - Production Deployment Guide

## Security Notice

The PHP built-in development server (`php -S`) is **NOT SECURE** for production use. It serves static files directly, bypassing security controls in index.php.

**ALWAYS** use a production web server (Apache or Nginx) for deployment.

---

## Apache Deployment

### Requirements
- Apache 2.4+
- PHP 8.1+ with PDO SQLite extension
- mod_rewrite enabled
- mod_headers enabled (optional, for additional security headers)

### Setup

1. **Enable Required Modules:**
   ```bash
   sudo a2enmod rewrite
   sudo a2enmod headers
   sudo systemctl restart apache2
   ```

2. **Virtual Host Configuration:**
   ```apache
   <VirtualHost *:80>
       ServerName vsaq.example.com
       DocumentRoot /var/www/vsaq/php-vsaq

       <Directory /var/www/vsaq/php-vsaq>
           Options -Indexes +FollowSymLinks
           AllowOverride All
           Require all granted
       </Directory>

       ErrorLog ${APACHE_LOG_DIR}/vsaq_error.log
       CustomLog ${APACHE_LOG_DIR}/vsaq_access.log combined
   </VirtualHost>
   ```

3. **Set Permissions:**
   ```bash
   sudo chown -R www-data:www-data /var/www/vsaq/php-vsaq
   sudo chmod -R 755 /var/www/vsaq/php-vsaq
   sudo chmod 700 /var/www/vsaq/php-vsaq/src/data
   ```

4. **The .htaccess file is already configured** with security rules:
   - Blocks access to `/src/`, `/data/`, `/.git/`
   - Routes all requests through index.php
   - Adds security headers

---

## Nginx Deployment

### Requirements
- Nginx 1.18+
- PHP 8.1+ with PHP-FPM and PDO SQLite extension

### Setup

1. **Configure Server Block:**

   Copy the example configuration:
   ```bash
   sudo cp nginx.conf.example /etc/nginx/sites-available/vsaq
   ```

   Edit the configuration:
   ```bash
   sudo nano /etc/nginx/sites-available/vsaq
   ```

   Update paths and server_name to match your environment.

2. **Enable Site:**
   ```bash
   sudo ln -s /etc/nginx/sites-available/vsaq /etc/nginx/sites-enabled/
   sudo nginx -t
   sudo systemctl reload nginx
   ```

3. **Set Permissions:**
   ```bash
   sudo chown -R www-data:www-data /var/www/vsaq/php-vsaq
   sudo chmod -R 755 /var/www/vsaq/php-vsaq
   sudo chmod 700 /var/www/vsaq/php-vsaq/src/data
   ```

---

## Security Hardening

### 1. HTTPS Setup (Required for WebAuthn)

WebAuthn requires HTTPS in production. Use Let's Encrypt:

```bash
sudo apt install certbot python3-certbot-apache
# For Apache:
sudo certbot --apache -d vsaq.example.com

# For Nginx:
sudo apt install python3-certbot-nginx
sudo certbot --nginx -d vsaq.example.com
```

### 2. Database Security

The SQLite database is stored in `/src/data/vsaq.db`. Ensure:

- Directory permissions are 700 (only web server can access)
- Database file permissions are 600
- Regular backups are performed

```bash
chmod 700 /var/www/vsaq/php-vsaq/src/data
chmod 600 /var/www/vsaq/php-vsaq/src/data/vsaq.db
```

### 3. PHP Configuration

Update `php.ini` for production:

```ini
display_errors = Off
log_errors = On
error_log = /var/log/php/error.log
expose_php = Off
session.cookie_httponly = 1
session.cookie_secure = 1  ; If using HTTPS
session.cookie_samesite = Strict
```

### 4. File Permissions

Recommended permissions structure:

```
/var/www/vsaq/php-vsaq/
├── index.php (644)
├── .htaccess (644)
├── admin/ (755)
│   └── *.html, *.css, *.js (644)
├── public/ (755)
│   └── *.html, *.css, *.js (644)
└── src/ (700)
    ├── *.php (600)
    └── data/ (700)
        └── vsaq.db (600)
```

Apply:
```bash
find /var/www/vsaq/php-vsaq -type f -exec chmod 644 {} \;
find /var/www/vsaq/php-vsaq -type d -exec chmod 755 {} \;
chmod 700 /var/www/vsaq/php-vsaq/src
chmod 600 /var/www/vsaq/php-vsaq/src/*.php
chmod 700 /var/www/vsaq/php-vsaq/src/data
chmod 600 /var/www/vsaq/php-vsaq/src/data/vsaq.db
```

---

## Testing Deployment

### 1. Verify Security Headers

```bash
curl -I https://vsaq.example.com/admin/
```

Should show:
```
X-Frame-Options: DENY
X-Content-Type-Options: nosniff
X-XSS-Protection: 1; mode=block
Referrer-Policy: strict-origin-when-cross-origin
```

### 2. Test Path Traversal Protection

```bash
curl https://vsaq.example.com/admin/../src/Database.php
```

Should return `403 Forbidden` or `404 Not Found`.

### 3. Verify Direct Access Blocking

```bash
curl https://vsaq.example.com/src/Database.php
```

Should return `403 Forbidden`.

---

## Monitoring and Maintenance

### Log Files

Monitor these logs regularly:

- Apache: `/var/log/apache2/vsaq_error.log`
- Nginx: `/var/log/nginx/error.log`
- PHP: `/var/log/php/error.log`

### Database Backups

Set up automated backups:

```bash
#!/bin/bash
# /etc/cron.daily/vsaq-backup
cp /var/www/vsaq/php-vsaq/src/data/vsaq.db \
   /var/backups/vsaq-$(date +%Y%m%d).db
# Keep only last 30 days
find /var/backups/vsaq-*.db -mtime +30 -delete
```

Make executable:
```bash
sudo chmod +x /etc/cron.daily/vsaq-backup
```

---

## Troubleshooting

### WebAuthn Not Working

- Ensure HTTPS is enabled
- Check browser console for errors
- Verify `rpId` matches your domain

### 500 Internal Server Error

- Check PHP error logs
- Verify file permissions
- Ensure PDO SQLite extension is installed: `php -m | grep pdo_sqlite`

### Blank Pages

- Check that `.htaccess` is being read (Apache)
- Verify `AllowOverride All` is set
- Check PHP error logs

---

## Production Checklist

- [ ] Using Apache or Nginx (not PHP built-in server)
- [ ] HTTPS enabled with valid certificate
- [ ] Security headers configured
- [ ] File permissions properly set
- [ ] `/src/` and `/data/` directories blocked
- [ ] Database backups automated
- [ ] Error logging enabled
- [ ] `display_errors` set to Off
- [ ] Session cookies set to secure and httponly
- [ ] Regular security updates applied
