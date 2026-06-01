FROM php:8.2-apache

# System dependencies + PHP extensions needed by the project
RUN apt-get update && apt-get install -y \
    git zip unzip \
    libzip-dev libpng-dev libxml2-dev libcurl4-openssl-dev \
    libfreetype6-dev libjpeg62-turbo-dev libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        zip gd xml mbstring curl opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Copy Composer binary from official image (faster than installer)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy project files
COPY . /var/www/html/
WORKDIR /var/www/html

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Enable Apache modules
RUN a2enmod rewrite headers deflate

# Apache virtualhost — use port 80 here; start.sh swaps it to $PORT at runtime
RUN echo '<VirtualHost *:80>\n\
    DocumentRoot /var/www/html\n\
    <Directory /var/www/html>\n\
        AllowOverride All\n\
        Require all granted\n\
        Options -Indexes\n\
    </Directory>\n\
    ErrorLog ${APACHE_LOG_DIR}/error.log\n\
    CustomLog ${APACHE_LOG_DIR}/access.log combined\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

# Suppress ServerName warning
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Ensure uploads directory exists and is writable
RUN mkdir -p /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod 777 /var/www/html/uploads

# Make start script executable
RUN chmod +x /var/www/html/docker/start.sh

CMD ["/var/www/html/docker/start.sh"]
