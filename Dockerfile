FROM php:8.4-fpm-alpine

# Install necessary packages without php8-pgsql
RUN apk add --no-cache nginx wget postgresql-dev

# Install PHP extensions for PostgreSQL
RUN docker-php-ext-install pdo pdo_pgsql pgsql

# Create necessary directories
RUN mkdir -p /run/nginx /app

# Copy Nginx configuration
COPY docker/nginx.conf /etc/nginx/nginx.conf

# Copy application source code
COPY . /app
COPY ./src /app

# Install Composer
RUN wget http://getcomposer.org/composer.phar && chmod a+x composer.phar && mv composer.phar /usr/local/bin/composer
RUN cd /app && composer install --no-dev

# Set ownership of the application
RUN chown -R www-data:www-data /app

# Wait for PostgreSQL before starting and run migrations
CMD sh /app/docker/startup.sh && php /app/artisan migrate
