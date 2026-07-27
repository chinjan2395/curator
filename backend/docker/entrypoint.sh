#!/usr/bin/env bash
set -e

cd /var/www/html

# Railway only injects environment variables at runtime, not during the
# build/pre-deploy phase. Generate the .env file here, right before the
# app starts, so the variables Railway provides are actually available.
if [ ! -f .env ]; then
    echo "No .env found — creating one from .env.example"
    cp .env.example .env
fi

# Mirror every environment variable Railway has injected into .env so
# Laravel's config cache and artisan commands can see them. Existing
# keys are replaced, new keys are appended.
while IFS='=' read -r name value; do
    # Skip empty lines and lines without a variable name.
    if [ -z "$name" ]; then
        continue
    fi

    # Only sync variables that look like Laravel/.env keys (uppercase,
    # digits, underscores) to avoid polluting .env with unrelated
    # shell/system variables.
    if [[ "$name" =~ ^[A-Z][A-Z0-9_]*$ ]]; then
        value="${!name}"

        if [ -z "$value" ]; then
            continue
        fi

        escaped_value=$(printf '%s' "$value" | sed -e 's/[\/&]/\\&/g')

        if grep -q "^${name}=" .env; then
            sed -i "s/^${name}=.*/${name}=${escaped_value}/" .env
        else
            echo "${name}=${escaped_value}" >>.env
        fi
    fi
done < <(env)

# Generate an application key if one isn't already set.
if ! grep -q "^APP_KEY=.\+" .env; then
    echo "Generating APP_KEY"
    php artisan key:generate --force
fi

echo "Running database migrations"
php artisan migrate --force

echo "Starting Laravel development server"
exec php artisan serve --host=0.0.0.0 --port=8000
