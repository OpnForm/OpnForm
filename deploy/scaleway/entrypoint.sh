#!/bin/sh
set -eu
cd /app
case "${APP_ROLE:-api}" in api|jobs|scheduler|migrate) ;; *) echo 'Unknown APP_ROLE' >&2; exit 64;; esac
rm -f bootstrap/cache/config.php
php /opt/opnform/preflight.php
# Compile only local runtime configuration. Never flush the shared cache here.
(
 umask 077
 php artisan config:cache --no-interaction
 php artisan route:cache --no-interaction
 php artisan event:cache --no-interaction
 php artisan view:cache --no-interaction
)
case "${APP_ROLE:-api}" in
 api) exec supervisord -c /etc/opnform/supervisor-api.conf ;;
 jobs) exec supervisord -c /etc/opnform/supervisor-jobs.conf ;;
 scheduler) exec php /opt/opnform/scheduler.php ;;
 migrate)
  rm -f /tmp/opnform-migration-ok
  if php artisan mcp:check-oauth-keys --environment-only --no-interaction && php artisan migrate --force --isolated=75 --no-interaction; then
   touch /tmp/opnform-migration-ok
  else
   echo 'Migration gate failed; resource will remain unhealthy' >&2
  fi
  exec sleep infinity ;;
esac
