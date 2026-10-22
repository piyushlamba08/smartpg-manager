#!/bin/sh
set -e

PORT="${PORT:-10000}"
sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

DATA_DIR="${DATA_DIR:-/data}"
if [ -d "$DATA_DIR" ]; then
  for f in tenants.data.php payments.data.php; do
    if [ ! -f "$DATA_DIR/$f" ]; then
      cp "/var/www/html/$f" "$DATA_DIR/$f"
    fi
    ln -sf "$DATA_DIR/$f" "/var/www/html/$f"
  done
  chown -R www-data:www-data "$DATA_DIR"
  echo "Persistent storage active: $DATA_DIR"
else
  echo "WARNING: $DATA_DIR nahi mila. Data redeploy pe reset ho jayega."
fi

exec apache2-foreground
