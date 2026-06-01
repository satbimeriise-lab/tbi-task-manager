FROM php:8.2-apache

# System dependencies
RUN apt-get update && apt-get install -y \
    git zip unzip libzip-dev libcurl4-openssl-dev \
    && docker-php-ext-install zip curl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copy project files
COPY . /var/www/html/
WORKDIR /var/www/html

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Apache: enable mod_rewrite
RUN a2enmod rewrite headers deflate expires

# Apache virtual host — listens on $PORT (set by Railway/Render at runtime)
RUN echo '\
<VirtualHost *:${PORT}>\n\
    DocumentRoot /var/www/html\n\
    <Directory /var/www/html>\n\
        AllowOverride All\n\
        Require all granted\n\
        Options -Indexes\n\
    </Directory>\n\
    ErrorLog ${APACHE_LOG_DIR}/error.log\n\
    CustomLog ${APACHE_LOG_DIR}/access.log combined\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

# Set file permissions
RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type f -name "*.php" -exec chmod 644 {} \; \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && mkdir -p /var/www/html/uploads \
    && chmod 777 /var/www/html/uploads

# Startup script — sets Apache port from $PORT env var
COPY docker/start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE ${PORT:-80}
CMD ["/start.sh"]
