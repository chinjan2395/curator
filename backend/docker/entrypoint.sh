#!/usr/bin/env bash
set -e

cd /var/www/html

PORT="${PORT:-8000}"

# Quote a value for dotenv so spaces / special chars stay valid.
dotenv_quote() {
    local val="$1"
    val="${val//\\/\\\\}"
    val="${val//\"/\\\"}"
    val="${val//$'\n'/\\n}"
    printf '"%s"' "$val"
}

# On Railway, variables are already injected into the process environment.
# Writing every shell env var into .env breaks dotenv when values contain
# spaces (APP_NAME, cert fingerprints, etc.) and causes boot crashes / 502s.
if [ -n "${RAILWAY_ENVIRONMENT:-}" ]; then
    echo "Railway runtime detected — using injected environment variables"

    # Drop any stale .env so Laravel reads process env only.
    rm -f .env

    if [ -z "${APP_KEY:-}" ]; then
        echo "APP_KEY missing — generating one"
        printf 'APP_KEY=\n' > .env
        php artisan key:generate --force
        # shellcheck disable=SC2155
        export APP_KEY="$(grep '^APP_KEY=' .env | cut -d= -f2- | tr -d '"' )"
        rm -f .env
    fi

    echo "Running database migrations"
    php artisan migrate --force

    echo "Starting Laravel on 0.0.0.0:${PORT}"
    exec php artisan serve --host=0.0.0.0 --port="${PORT}"
fi

# --- Local / non-Railway (docker compose) ---
if [ ! -f .env ]; then
    echo "No .env found — creating one from .env.example"
    cp .env.example .env
fi

while IFS='=' read -r name value; do
    if [ -z "$name" ]; then
        continue
    fi

    if [[ "$name" =~ ^[A-Z][A-Z0-9_]*$ ]]; then
        value="${!name}"

        if [ -z "$value" ]; then
            continue
        fi

        quoted="$(dotenv_quote "$value")"

        if grep -q "^${name}=" .env; then
            awk -v name="$name" -v val="$quoted" '
                BEGIN { FS=OFS="=" }
                $1==name { print name "=" val; next }
                { print }
            ' .env > .env.tmp && mv .env.tmp .env
        else
            printf '%s=%s\n' "$name" "$quoted" >> .env
        fi
    fi
done < <(env)

if ! grep -q "^APP_KEY=.\+" .env; then
    echo "Generating APP_KEY"
    php artisan key:generate --force
fi

echo "Running database migrations"
php artisan migrate --force

echo "Starting Laravel on 0.0.0.0:${PORT}"
exec php artisan serve --host=0.0.0.0 --port="${PORT}"
