#!/bin/sh

sed -i "s,LISTEN_PORT,$PORT,g" /etc/nginx/nginx.conf

php-fpm -D

# while ! nc -w 1 -z 127.0.0.1 9000; do sleep 0.1; done;
cd /app

# Run Laravel migrations
php artisan migrate --force

# Start Laravel Scheduler in the background
php artisan schedule:work &

nginx
