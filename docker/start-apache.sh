#!/usr/bin/env bash
set -e

PORT_VALUE=${PORT:-8080}

# Aiven / MySQL SSL CA support (optional). Provide MYSQL_ATTR_SSL_CA_PEM to inject cert.
if [ -n "${MYSQL_ATTR_SSL_CA_PEM:-}" ] && [ -z "${MYSQL_ATTR_SSL_CA:-}" ]; then
  MYSQL_ATTR_SSL_CA=/tmp/mysql-ca.pem
  printf "%s" "$MYSQL_ATTR_SSL_CA_PEM" > "$MYSQL_ATTR_SSL_CA"
  export MYSQL_ATTR_SSL_CA
fi

# Run migrations at boot for single-instance demo deployments.
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
  php artisan migrate --force
  php artisan storage:link || true
fi

# Ensure only one MPM is enabled (prefork is safest with PHP mod)
a2dismod mpm_event mpm_worker >/dev/null 2>&1 || true
a2enmod mpm_prefork >/dev/null 2>&1 || true

sed -ri "s/Listen 80/Listen ${PORT_VALUE}/" /etc/apache2/ports.conf
sed -ri "s/:80>/:${PORT_VALUE}>/" /etc/apache2/sites-available/000-default.conf

exec apache2-foreground
