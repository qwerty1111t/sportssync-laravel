web: /usr/local/bin/entrypoint.sh
worker: php artisan queue:work --sleep=3 --tries=3 --timeout=60
scheduler: php artisan schedule:work
