#!/bin/sh
set -e

cd /var/www/html

mkdir -p \
    storage/app/private \
    storage/app/public \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

# Named volumes often remount as root; keep the app writable.
if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data storage bootstrap/cache || true
    chmod -R ug+rwx storage bootstrap/cache || true
fi

run_artisan() {
    if [ "$(id -u)" = "0" ]; then
        gosu www-data php artisan "$@"
    else
        php artisan "$@"
    fi
}

wait_for_database() {
    if [ -z "${DB_HOST:-}" ] || [ "${DB_CONNECTION:-}" = "sqlite" ]; then
        return 0
    fi

    echo "Waiting for database at ${DB_HOST}:${DB_PORT:-3306}..."

    php -r '
        $host = getenv("DB_HOST") ?: "mysql";
        $port = getenv("DB_PORT") ?: "3306";
        $database = getenv("DB_DATABASE") ?: "bedrock";
        $username = getenv("DB_USERNAME") ?: "bedrock";
        $password = getenv("DB_PASSWORD") ?: "";
        $maxAttempts = 60;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                new PDO(
                    sprintf("mysql:host=%s;port=%s;dbname=%s", $host, $port, $database),
                    $username,
                    $password,
                    [PDO::ATTR_TIMEOUT => 3]
                );
                fwrite(STDOUT, "Database is ready." . PHP_EOL);
                exit(0);
            } catch (Throwable $e) {
                fwrite(STDOUT, "Database not ready (attempt {$attempt}/{$maxAttempts})" . PHP_EOL);
                sleep(2);
            }
        }

        fwrite(STDERR, "Timed out waiting for database." . PHP_EOL);
        exit(1);
    '
}

role="${CONTAINER_ROLE:-app}"

wait_for_database

if [ "$role" = "app" ] && [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    echo "Running migrations..."
    run_artisan migrate --force --no-interaction
fi

run_artisan storage:link --force --no-interaction >/dev/null 2>&1 || true

if [ "${RUN_OPTIMIZE:-false}" = "true" ]; then
    run_artisan config:cache --no-interaction
    run_artisan route:cache --no-interaction
    run_artisan view:cache --no-interaction
fi

if [ "$role" = "app" ]; then
    touch /tmp/app-ready
fi

# php-fpm must start as root; artisan processes should run as www-data.
if [ "$(id -u)" = "0" ] && [ "$1" != "php-fpm" ]; then
    exec gosu www-data "$@"
fi

exec "$@"
