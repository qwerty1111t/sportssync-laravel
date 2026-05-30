#!/usr/bin/env bash
set -e

HOST=127.0.0.1
PORT=9000
TIMEOUT=30
SLEEPTIME=1
COUNT=0

echo "[start-nginx] waiting for php-fpm on ${HOST}:${PORT}"
while ! bash -c "cat < /dev/tcp/${HOST}/${PORT}" >/dev/null 2>&1; do
  COUNT=$((COUNT+1))
  if [ ${COUNT} -ge ${TIMEOUT} ]; then
    echo "[start-nginx] timeout waiting for ${HOST}:${PORT} after ${TIMEOUT}s"
    echo "[start-nginx] php-fpm never became available; refusing to start nginx"
    exit 1
  fi
  sleep ${SLEEPTIME}
done

echo "[start-nginx] php-fpm check complete, starting nginx"
exec /usr/sbin/nginx -g 'daemon off;'
