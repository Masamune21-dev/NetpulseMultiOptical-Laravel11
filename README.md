# NetPulse MultiOptical

Network monitoring dashboard for multi‑vendor optical/SFP interfaces.  
Built on Laravel 11 with a map view, interface discovery, and security logs.

## Features
- Network map with nodes, links, and animated connections
- SFP/Interface monitoring (TX/RX, attenuation, status)
- Device inventory and discovery
- User management (admin/technician)
- Security log viewer (login success/failed)
- Theme customization

## Requirements
- PHP 8.2+
- Composer
- MySQL/MariaDB
- Apache/Nginx
- Node.js (optional, only if you rebuild assets)

## Quick Start
1. Install dependencies:
   ```bash
   composer install
   ```

2. Create `.env`:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. Configure DB in `.env`:
   ```
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=netpulse
   DB_USERNAME=...
   DB_PASSWORD=...
   ```

4. Migrate:
   ```bash
   php artisan migrate
   ```

5. Fix permissions (Linux):
   ```bash
   chown -R www-data:www-data storage bootstrap/cache
   chmod -R 775 storage bootstrap/cache
   ```

6. Run:
   ```bash
   php artisan serve
   ```

## Configuration
Key `.env` variables:
- `APP_URL`
- `APP_TIMEZONE`
- `DB_*`
- `SESSION_DRIVER` (default `file`)

## OLT Configuration (Local)
This project expects a local OLT config file:

1. Copy the example:
   ```bash
   cp config/olt.example.php config/olt.php
   ```

2. Edit `config/olt.php` to match your environment (IP/credentials/vendor options).

Notes:
- `config/olt.php` is intentionally ignored by git (see `.gitignore`).
- Generated polling output (example: `storage/app/olt/**.json`) is also ignored by git.

## Data Flow
1. SNMP discovery populates `interfaces` and `interface_stats`.
2. Map nodes and links are stored in `map_nodes` and `map_links`.
3. UI reads via `/api/*` endpoints.

## Core Routes
UI:
- `/dashboard`
- `/monitoring`
- `/map`
- `/devices`
- `/users`
- `/settings`

API (selected):
- `/api/devices`
- `/api/interfaces`
- `/api/monitoring_devices`
- `/api/monitoring_interfaces`
- `/api/interface_chart`
- `/api/map_nodes`
- `/api/map_links`
- `/api/map_devices`
- `/api/settings`
- `/api/logs`

## Security Logs
Login activity is written to:
- `storage/logs/security.log`

## Permissions (Linux)
```bash
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

## Cron / Scheduler
If you use scheduled discovery or polling, add:
```bash
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

## Deployment

### Nginx
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/project/public;

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### Apache
Enable `mod_rewrite`, then use:
```apache
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /path/to/project/public

    <Directory /path/to/project/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/netpulse-error.log
    CustomLog ${APACHE_LOG_DIR}/netpulse-access.log combined
</VirtualHost>
```

## Development Notes
- JS assets are cache‑busted via `?v=filemtime(...)`.
- Routes use clean endpoints without `.php`.

## License

This project is licensed under CC BY-NC 4.0.
Commercial use is prohibited without permission.


