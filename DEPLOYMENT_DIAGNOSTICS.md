# HTTP 502 Troubleshooting Guide - With Comprehensive Diagnostics

## Files Modified for Diagnostics

### 1. Dockerfile
- **Added**: `--verbose` flag to composer install to show download progress
- **Added**: Check that `vendor/autoload.php` exists after composer install (fails build if missing)
- **Added**: `grep` command to verify PHP-FPM listen, user, group settings during build
- **Purpose**: Ensure PHP-FPM config is set to `127.0.0.1:9000` and composer dependencies are installed

### 2. entrypoint.sh - Comprehensive Startup Diagnostics
Runs these checks before supervisord starts:
1. **NGINX CONFIG**: Prints full generated `/etc/nginx/nginx.conf` (shows all directives)
2. **NGINX TEST**: Runs `nginx -T` to validate config syntax
3. **PHP-FPM CONFIG**: Shows active listen setting from www.conf
4. **PHP-FPM TEST**: Runs `php-fpm -tt` to validate PHP-FPM config
5. **FILE EXISTENCE**: Checks all critical Laravel files exist
6. **PERMISSIONS**: Shows ownership and permissions on storage/bootstrap/cache
7. **PHP EXTENSIONS**: Lists loaded PHP extensions (PDO, json, filter, etc.)
8. **COMPOSER/VENDOR**: Counts PHP files in vendor, verifies autoload.php, verifies laravel/
9. **AUTOLOADER TEST**: Attempts to load `vendor/autoload.php` with PHP
10. **BOOTSTRAP TEST**: Attempts to load `bootstrap/app.php` and reports any errors
11. **FASTCGI TEST**: Attempts raw TCP connection to PHP-FPM on port 9000
12. **ENVIRONMENT**: Shows first 30 lines of .env, highlights APP_KEY, APP_DEBUG, APP_ENV

**Output Location**: All diagnostics print to stdout, visible in Railway container logs

### 3. supervisord.conf - Log Capture
All processes now log to stdout/stderr:
- **php-fpm**:  `stdout_logfile=/dev/stdout` and `stderr_logfile=/dev/stderr`
- **nginx**: `stdout_logfile=/dev/stdout` and `stderr_logfile=/dev/stderr`
- **websocket**: `stdout_logfile=/dev/stdout` and `stderr_logfile=/dev/stderr`
- **supervisord**: `logfile=/dev/stdout`
- All set to `*_logfile_maxbytes=0` to disable file rotation

**Purpose**: All error messages appear in Railway container logs

### 4. nginx.conf - Debug Logging
- **error_log**: Changed to `/dev/stdout debug` (verbose error logging)
- **access_log**: Changed to `/dev/stdout combined` (all HTTP requests)

**Purpose**: Capture every nginx action and error

### 5. public/health.php - Health Check Endpoint
- Simple PHP endpoint at `/health` that tests Laravel bootstrap
- Returns JSON with:
  - PHP version
  - Laravel file existence checks
  - Storage permissions
  - Laravel bootstrap status
- Returns HTTP 500 if Laravel bootstrap fails
- Can be tested via: `GET https://your-railway-url/health`

## Expected Successful Startup Output in Railway Logs

```
[Init] Nginx config generated with PORT=8000
[Init] Copied .env.example to .env
[Init] Generating APP_KEY...
[Init] Setting APP_ENV to production...
[Init] Running migrations on Railway...

========================================
      STARTUP DIAGNOSTICS - FULL
========================================

[1] GENERATED NGINX CONFIGURATION:
user www-data;
worker_processes auto;
error_log /dev/stdout debug;
...
listen 8000;
server_name _;
root /var/www/html/public;
fastcgi_pass 127.0.0.1:9000;
...

[2] NGINX CONFIGURATION TEST (nginx -T):
nginx: the configuration file /etc/nginx/nginx.conf syntax is ok
nginx: configuration file /etc/nginx/nginx.conf test is successful

[3] PHP-FPM CONFIGURATION:
listen = 127.0.0.1:9000

[4] PHP-FPM CONFIGURATION TEST (php-fpm -tt):
[...] NOTICE: configuration file test is successful

[5] FILE EXISTENCE CHECKS:
public/index.php: EXISTS
storage: EXISTS
bootstrap/cache: EXISTS
.env: EXISTS
artisan: EXISTS

[6] PERMISSIONS AND OWNERSHIP:
drwxr-xr-x ... storage
drwxr-xr-x ... bootstrap/cache

[9] PHP EXTENSIONS LOADED:
PDO
Core
json
filter
...

[10] COMPOSER/VENDOR STATUS:
vendor/ directory EXISTS
PHP files in vendor: 12345
✓ vendor/autoload.php EXISTS
✓ vendor/laravel/ EXISTS

[11] AUTOLOADER TEST:
Autoloader loaded successfully

[12] LARAVEL BOOTSTRAP TEST:
Bootstrap loaded successfully
App class: Illuminate\Foundation\Application

========================================
   END STARTUP DIAGNOSTICS
========================================

[start-nginx] php-fpm check complete, starting nginx

... supervisor logs ...
INFO spawned: 'php-fpm' with pid ...
INFO spawned: 'nginx' with pid ...
INFO spawned: 'websocket' with pid ...
```

## If You See HTTP 502 Despite Successful Startup

### Check Nginx Error Log
Look for entries like:
- `upstream timed out (110: Connection timed out)`
- `connection refused`
- `connect() failed (111: Connection refused)`
- `segmentation fault`

These indicate PHP-FPM is not responding or crashed.

### Check Nginx Access Log
Look for entries like:
```
127.0.0.1 - - [date] "GET / HTTP/1.1" 502 ...
```

The 502 indicates FastCGI communication failed.

### Check PHP-FPM Logs
Look for:
- `PHP Fatal error`
- `Allowed memory size exceeded`
- `Call stack` entries
- `Segmentation fault`
- `php-fpm: [error]`

These indicate PHP crashed while processing a request.

### Check Laravel Logs (storage/logs/laravel.log)
If Laravel can bootstrap, errors will be logged here.

## Next Steps to Deploy

1. **Commit and push** the modified files to your repository
2. **Trigger Railway rebuild** from the Railway dashboard or via `git push`
3. **Watch Railway container logs** for the startup diagnostics output
4. **Verify all SUCCESS indicators** appear in the logs
5. **Test the endpoint**:
   - Health check: `curl https://your-railway-url/health`
   - Should return JSON with Laravel bootstrap success
6. **If still 502**:
   - Look for error messages in the nginx error_log output
   - Check if PHP-FPM is still running or has crashed
   - Share the full container logs with error details

## Key Configuration Values

| Setting | Value | Purpose |
|---------|-------|---------|
| `listen` | `127.0.0.1:9000` | PHP-FPM listens on TCP port |
| `fastcgi_pass` | `127.0.0.1:9000` | Nginx forwards to this port |
| `SCRIPT_FILENAME` | `$document_root$fastcgi_script_name` | Tells PHP-FPM the script location |
| `root` | `/var/www/html/public` | Nginx root directory |
| `PORT` | `8000` (or Railway provided) | External port nginx listens on |

## Verification Commands (for local testing before Railway)

If you want to test locally with Docker:

```bash
# Build the image
docker build -t sportssync .

# Run a container
docker run -it -p 8000:8000 -e PORT=8000 sportssync

# In another terminal, test
curl http://localhost:8000/health
curl http://localhost:8000/
```

All startup diagnostics will print to console as the container starts.
