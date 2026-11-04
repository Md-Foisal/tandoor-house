#!/bin/zsh
# Runs the app, Reverb and a queue worker on this worktree's own database, with the clock on the evening service.
# usage: scripts/serve.sh [--reseed]
set -u
FILM_DIR=${0:A:h:h}
export FILM_APP_ROOT=${FILM_DIR:h:h}
export FILM_CLOCK=${FILM_CLOCK:-"$(date +%Y-%m-%d) 18:40"}
export FILM_CLOCK_EPOCH=$(date +%s)
export PHP_CLI_SERVER_WORKERS=8
PHP=(php -d auto_prepend_file=$FILM_DIR/scripts/clock-prepend.php)
mkdir -p $FILM_DIR/out
print "{\"clock\":\"$FILM_CLOCK\",\"epoch\":$FILM_CLOCK_EPOCH}" > $FILM_DIR/out/clock.json
cd $FILM_APP_ROOT
pkill -f "artisan serve --port=8020"; pkill -f "reverb:start --port=8021"; pkill -f "queue:work --sleep"; pkill -f "127.0.0.1:8020"; sleep 1
if [[ ${1:-} == --reseed ]]; then $PHP artisan migrate:fresh --seed --force > /dev/null; $PHP artisan cache:clear > /dev/null; sqlite3 database/database.sqlite 'PRAGMA journal_mode=WAL;' > /dev/null; fi
(cd public && nohup $PHP -S 127.0.0.1:8020 $FILM_DIR/scripts/router.php > /tmp/film-serve.log 2>&1 &)
nohup $PHP artisan reverb:start --port=8021 > /tmp/film-reverb.log 2>&1 &
nohup $PHP artisan queue:work --sleep=0.1 --tries=6 > /tmp/film-queue.log 2>&1 &
sleep 2
