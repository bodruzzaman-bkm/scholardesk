#!/bin/sh
#
# Container start-up.
#
# The persistent volume is mounted at /data. Everything that must survive a
# redeploy lives there — the SQLite database and the uploaded PDFs — because a
# container's own filesystem is rebuilt from the image every time.
#
set -e

DATA_DIR=${DATA_DIR:-/data}
DB_FILE="$DATA_DIR/database.sqlite"
UPLOADS="$DATA_DIR/uploads"

echo "==> preparing persistent storage at $DATA_DIR"
mkdir -p "$UPLOADS"

# The database file has to exist before migrate will touch it.
if [ ! -f "$DB_FILE" ]; then
    echo "    creating a new database"
    touch "$DB_FILE"
fi

# Point storage/app/public at the volume, so uploads outlive the container.
# It is replaced rather than reused: the image ships a real directory there.
rm -rf storage/app/public
ln -sfn "$UPLOADS" storage/app/public

# public/storage is what the web server actually serves uploads from.
rm -rf public/storage
ln -sfn "$UPLOADS" public/storage

chown -R www-data:www-data "$DATA_DIR" storage bootstrap/cache 2>/dev/null || true

# Fail loudly and early rather than serving 500s with an unhelpful message.
if [ -z "$APP_KEY" ]; then
    echo "!!! APP_KEY is not set. Run: fly secrets set APP_KEY=\"\$(php artisan key:generate --show)\""
    exit 1
fi

echo "==> running migrations"
php artisan migrate --force --no-interaction

# Cached config/routes/views make each request measurably cheaper. Done at boot
# rather than build time because the cache bakes in environment values, which
# are only present now.
#
# Each is best-effort: caching is an optimisation, and a failure here (a
# closure route, say) must not stop the app from serving. `set -e` would
# otherwise turn a slow app into a dead one.
echo "==> caching configuration"
php artisan config:cache || echo "    (config cache skipped)"
php artisan route:cache  || echo "    (route cache skipped)"
php artisan view:cache   || echo "    (view cache skipped)"

echo "==> serving on 0.0.0.0:8080"
exec php artisan serve --host=0.0.0.0 --port=8080
