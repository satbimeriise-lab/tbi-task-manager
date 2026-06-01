FROM php:8.2-apache

# CACHE_BUST=20260601_v4

# System dependencies + PHP extensions
RUN apt-get update && apt-get install -y \
    git zip unzip \
    libzip-dev libpng-dev libxml2-dev libcurl4-openssl-dev \
    libfreetype6-dev libjpeg62-turbo-dev libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install zip gd xml mbstring curl opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Project files
COPY . /var/www/html/
WORKDIR /var/www/html

# PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Fix Apache MPM: must use prefork with PHP mod
RUN a2dismod mpm_event mpm_worker 2>/dev/null || true
RUN a2enmod mpm_prefork rewrite headers deflate

# Apache virtualhost on port 80 (start.sh changes to $PORT at runtime)
RUN printf '<VirtualHost *:80>\n\
    DocumentRoot /var/www/html\n\
    <Directory /var/www/html>\n\
        AllowOverride All\n\
        Require all granted\n\
        Options -Indexes\n\
    </Directory>\n\
</VirtualHost>\n' > /etc/apache2/sites-available/000-default.conf

RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Uploads directory
RUN mkdir -p /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod 777 /var/www/html/uploads

# Start script
COPY docker/start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 80
CMD ["/start.sh"]
