#!/bin/bash
# Configure Apache to use the PORT provided by the platform (Railway / Render)
PORT=${PORT:-80}

# Replace default Apache port
sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Set BASE_URL from env (leave empty when at domain root)
export APACHE_BASE_URL="${BASE_URL:-}"

echo "Starting Apache on port $PORT ..."
exec apache2-foreground
