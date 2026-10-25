#!/bin/sh
set -eu
case "${APP_ROLE:-api}" in
 api) exec curl -fsS --max-time 6 http://127.0.0.1:8080/healthz ;;
 migrate) test -f /tmp/opnform-migration-ok ;;
 jobs) test "$(pgrep -fc 'artisan queue:work redis')" -eq "${WORKER_COUNT:-4}" && php /opt/opnform/health.php ;;
 scheduler)
  php -r 'exit(!is_file("/tmp/opnform-scheduler-heartbeat") || time()-filemtime("/tmp/opnform-scheduler-heartbeat") > 90);'
  ;;
 *) exit 64 ;;
esac
