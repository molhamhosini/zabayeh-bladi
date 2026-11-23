#!/usr/bin/env bash
# Runs on the server (Hostinger shared hosting) after GitHub Actions has copied the code (with vendor/) to $APP_DIR.
# First run: backs up the site currently on the domain, writes .env, points public_html at backend/public,
# migrates and seeds. Later runs: migrate and refresh caches only.
# Needs env: DOMAIN, DB_DATABASE, DB_USERNAME, DB_PASSWORD (+ ADMIN_EMAIL, ADMIN_PASSWORD on the first run).
set -euo pipefail

DOMAIN_DIR="$HOME/domains/$DOMAIN"
APP_DIR="$DOMAIN_DIR/zabayeh-bladi"
BACKEND="$APP_DIR/backend"
PUBLIC_HTML="$DOMAIN_DIR/public_html"
STAMP="$(date +%Y%m%d-%H%M%S)"

# Hostinger exposes several PHP versions; use 8.3+.
PHP=php
for candidate in php /opt/alt/php84/usr/bin/php /opt/alt/php83/usr/bin/php /usr/bin/php8.4 /usr/bin/php8.3; do
  if command -v "$candidate" >/dev/null 2>&1 && "$candidate" -r 'exit(PHP_VERSION_ID >= 80300 ? 0 : 1);'; then PHP="$candidate"; break; fi
done
echo "Using $($PHP -v | head -1)"
"$PHP" -r 'exit(PHP_VERSION_ID >= 80300 ? 0 : 1);' || { echo "PHP 8.3+ is required. Choose PHP 8.3 in hPanel > Advanced > PHP Configuration."; exit 1; }

cd "$BACKEND"
FIRST_RUN=0
[ -f .env ] || FIRST_RUN=1

if [ "$FIRST_RUN" = 1 ]; then
  echo "== First deployment =="
  : "${ADMIN_EMAIL:?ADMIN_EMAIL secret is missing}"
  : "${ADMIN_PASSWORD:?ADMIN_PASSWORD secret is missing}"

  # 1) Back up whatever is on the domain today (files + its database when a Laravel .env is found).
  mkdir -p "$HOME/backups"
  if [ -e "$PUBLIC_HTML" ] && [ ! -L "$PUBLIC_HTML" ]; then
    tar czf "$HOME/backups/old-site-$DOMAIN-$STAMP.tar.gz" -C "$DOMAIN_DIR" --exclude=zabayeh-bladi . \
      && echo "Old site files saved to ~/backups/old-site-$DOMAIN-$STAMP.tar.gz"
    OLD_ENV="$(find "$PUBLIC_HTML" "$DOMAIN_DIR" -maxdepth 2 -name .env -not -path "$APP_DIR/*" 2>/dev/null | head -1 || true)"
    if [ -n "$OLD_ENV" ]; then
      get() { grep -E "^$1=" "$OLD_ENV" | head -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }
      if mysqldump -h "$(get DB_HOST)" -u "$(get DB_USERNAME)" -p"$(get DB_PASSWORD)" "$(get DB_DATABASE)" \
          > "$HOME/backups/old-db-$DOMAIN-$STAMP.sql" 2>/dev/null; then
        echo "Old database saved to ~/backups/old-db-$DOMAIN-$STAMP.sql"
      else
        echo "WARNING: could not dump the old database automatically; export it from hPanel > Databases > phpMyAdmin."
      fi
    fi
  fi

  # 2) Production .env
  cp .env.example .env
  set_env() { # key value: drop any existing line, append the value quoted so dotenv reads it literally
    local v="$2" q
    if [[ "$v" != *"'"* ]]; then q="'$v'"; else v="${v//\\/\\\\}"; v="${v//\"/\\\"}"; v="${v//\$/\\\$}"; q="\"$v\""; fi
    grep -vE "^#? ?$1=" .env > .env.tmp || true
    printf '%s=%s\n' "$1" "$q" >> .env.tmp && mv .env.tmp .env
  }
  set_env APP_ENV production
  set_env APP_DEBUG false
  set_env APP_URL "https://$DOMAIN"
  set_env DB_CONNECTION mysql
  set_env DB_HOST "${DB_HOST:-localhost}"
  set_env DB_PORT 3306
  set_env DB_DATABASE "$DB_DATABASE"
  set_env DB_USERNAME "$DB_USERNAME"
  set_env DB_PASSWORD "$DB_PASSWORD"
  set_env ADMIN_EMAIL "$ADMIN_EMAIL"
  set_env ADMIN_PASSWORD "$ADMIN_PASSWORD"
  set_env SESSION_SECURE_COOKIE true
  chmod 600 .env
  "$PHP" artisan key:generate --force

  # 3) Serve the app from the domain: public_html -> backend/public
  if [ -e "$PUBLIC_HTML" ] && [ ! -L "$PUBLIC_HTML" ]; then mv "$PUBLIC_HTML" "$DOMAIN_DIR/public_html_old_$STAMP"; fi
  ln -sfn "$BACKEND/public" "$PUBLIC_HTML"
fi

# From here on Laravel must read only .env, not the secrets passed in by the workflow.
unset DB_HOST DB_DATABASE DB_USERNAME DB_PASSWORD ADMIN_EMAIL ADMIN_PASSWORD

"$PHP" artisan down --retry=30 || true
"$PHP" artisan migrate --force
if [ "$FIRST_RUN" = 1 ]; then
  "$PHP" artisan db:seed --force
  # The admin password only needs to live in GitHub secrets; drop it from the server .env after seeding.
  sed -i -E 's|^ADMIN_PASSWORD=.*|ADMIN_PASSWORD=|' .env
fi
"$PHP" artisan storage:link --force || true
"$PHP" artisan optimize:clear
"$PHP" artisan optimize
"$PHP" artisan up
echo "Done: https://$DOMAIN/admin"
