#!/bin/sh
#
# Container start-up.
#
# Two deployment shapes are supported, because free hosts and Fly differ in
# what they can keep:
#
#   volume   DB_CONNECTION=sqlite + a disk mounted at /data. The SQLite file
#            and the uploaded PDFs live there and survive a redeploy.
#            This is the Fly setup.
#
#   stateless
#            DB_CONNECTION=pgsql + FILESYSTEM_DISK=s3. Nothing is kept on the
#            container's own filesystem, which is rebuilt from the image every
#            time. This is the free-host setup, where a persistent disk is not
#            on offer.
#
# The mode is inferred rather than configured: if the database is Postgres
# there is no SQLite file to prepare, and if the filesystem disk is s3 there
# is no upload directory to link.
#
set -e

DATA_DIR=${DATA_DIR:-/data}
DB_FILE="$DATA_DIR/database.sqlite"
UPLOADS="$DATA_DIR/uploads"

DB_CONNECTION=${DB_CONNECTION:-sqlite}
FILESYSTEM_DISK=${FILESYSTEM_DISK:-public}

# --- Database --------------------------------------------------------------
if [ "$DB_CONNECTION" = "sqlite" ]; then
    echo "==> sqlite: preparing $DATA_DIR"
    mkdir -p "$DATA_DIR"

    # The database file has to exist before migrate will touch it.
    if [ ! -f "$DB_FILE" ]; then
        echo "    creating a new database"
        touch "$DB_FILE"
    fi
else
    echo "==> database: $DB_CONNECTION (managed, nothing to prepare)"
fi

# --- Uploads ---------------------------------------------------------------
if [ "$FILESYSTEM_DISK" = "s3" ]; then
    # PDFs go to object storage. storage/app/public stays a plain directory,
    # unused, and public/storage is not served — Storage::url() returns an
    # absolute S3/R2 URL instead.
    echo "==> uploads: s3 (object storage)"
    mkdir -p storage/app/public
else
    echo "==> uploads: local disk at $UPLOADS"
    mkdir -p "$UPLOADS"

    # Point storage/app/public at the volume, so uploads outlive the container.
    # It is replaced rather than reused: the image ships a real directory there.
    rm -rf storage/app/public
    ln -sfn "$UPLOADS" storage/app/public

    # public/storage is what the web server actually serves uploads from.
    rm -rf public/storage
    ln -sfn "$UPLOADS" public/storage
fi

chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
[ -d "$DATA_DIR" ] && chown -R www-data:www-data "$DATA_DIR" 2>/dev/null || true

# Fail loudly and early rather than serving 500s with an unhelpful message.
if [ -z "$APP_KEY" ]; then
    echo "!!! APP_KEY is not set."
    echo "    Fly:    fly secrets set APP_KEY=\"\$(php artisan key:generate --show)\""
    echo "    Render: add APP_KEY in the dashboard, value from: php artisan key:generate --show"
    exit 1
fi

# A Postgres deployment with no DB_URL is a misconfiguration that would
# otherwise surface as a connection refused several seconds later.
if [ "$DB_CONNECTION" = "pgsql" ] && [ -z "$DB_URL" ] && [ -z "$DB_HOST" ]; then
    echo "!!! DB_CONNECTION=pgsql but neither DB_URL nor DB_HOST is set."
    echo "    config/database.php reads DB_URL for the pgsql connection."
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

# $PORT is set by Render and most PaaS hosts; Fly uses the fixed 8080 in
# fly.toml. Defaulting keeps one entrypoint working for both.
PORT=${PORT:-8080}
echo "==> serving on 0.0.0.0:$PORT"
exec php artisan serve --host=0.0.0.0 --port="$PORT"
