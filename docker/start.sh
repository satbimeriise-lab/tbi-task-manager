#!/bin/bash
set -e

# Railway and Render set $PORT at runtime (default 8080 on Railway, 10000 on Render)
PORT="${PORT:-80}"

echo "==> Configuring Apache on port ${PORT}"

# Replace port 80 → $PORT in Apache config
sed -i "s/Listen 80/Listen ${PORT}/g"         /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/g" \
        /etc/apache2/sites-available/000-default.conf

# Write credentials.json from base64 env var if file doesn't exist
if [ ! -f "/var/www/html/config/credentials.json" ] && [ -n "$GOOGLE_CREDENTIALS_BASE64" ]; then
    echo "==> Writing credentials.json from env var"
    echo "$GOOGLE_CREDENTIALS_BASE64" | base64 -d > /var/www/html/config/credentials.json
fi

echo "==> Starting Apache on port ${PORT}"
exec apache2-foreground
