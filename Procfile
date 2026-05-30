web: /usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisord.conf
worker: php artisan queue:work --sleep=3 --tries=3 --timeout=60
scheduler: php artisan schedule:work
