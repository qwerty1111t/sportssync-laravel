web: vendor/bin/heroku-php-apache2 public/
websocket: npm --prefix public/ws-server start
worker: php artisan queue:work --sleep=3 --tries=3 --timeout=60
scheduler: php artisan schedule:work
