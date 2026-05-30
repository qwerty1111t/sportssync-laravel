#!/usr/bin/env bash
set -e

SOCKET=/var/run/php/php-fpm.sock
TIMEOUT=30
SLEEPTIME=1
COUNT=0

echo "[start-nginx] waiting for php-fpm socket ${SOCKET}"
while [ ! -S "${SOCKET}" ]; do
  COUNT=$((COUNT+1))
  if [ ${COUNT} -ge ${TIMEOUT} ]; then
    echo "[start-nginx] timeout waiting for ${SOCKET} after ${TIMEOUT}s"
    # still try to start nginx to capture errors
    break
  fi
  sleep ${SLEEPTIME}
done

echo "[start-nginx] socket check complete, starting nginx"
exec /usr/sbin/nginx -g 'daemon off;'
