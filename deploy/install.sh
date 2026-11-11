#!/usr/bin/env bash
# =============================================================================
#  Website installer for a Proxmox LXC (Debian 12/13, created with the
#  community "Debian" helper script).
#
#  Run as root inside the container:
#      bash install.sh
#
#  Installs: Nginx, PHP 8.4-FPM, MariaDB, Node 22, Composer, code-server
#  (VS Code in the browser), a queue worker, the scheduler and the `update`
#  command.
# =============================================================================
set -euo pipefail
export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:${PATH:-}"

APP_DIR=/var/www/site
APP_USER=www-data
APP_HOME=/var/www
PHP_VERSION=8.4
CODE_PORT=8443

c_green='\033[1;32m'; c_yellow='\033[1;33m'; c_red='\033[1;31m'; c_blue='\033[1;34m'; c_off='\033[0m'
step() { echo -e "\n${c_blue}==>${c_off} ${1}"; }
ok()   { echo -e "${c_green}✔${c_off} ${1}"; }
warn() { echo -e "${c_yellow}!${c_off} ${1}"; }
die()  { echo -e "${c_red}✘ ${1}${c_off}"; exit 1; }
as_app() { runuser -u "$APP_USER" -- env HOME="$APP_HOME" "$@"; }

[[ $EUID -eq 0 ]] || die "Run this script as root."
. /etc/os-release
[[ "$ID" == "debian" ]] || die "This installer supports Debian only (found: $ID)."

# ----------------------------------------------------------------------------
# Questions
# ----------------------------------------------------------------------------
echo -e "${c_green}"
cat <<'BANNER'
  ┌──────────────────────────────────────────────┐
  │   Laravel website installer for Proxmox LXC  │
  └──────────────────────────────────────────────┘
BANNER
echo -e "${c_off}"

# Each answer can also be passed as an environment variable (the Proxmox
# helper script does this), in which case the question is skipped.
ask() { # ask VAR "Question" "default"
    local var=$1 question=$2 default=${3:-} answer
    if [[ -z "${!var:-}" ]]; then
        read -rp "${question}${default:+ [$default]}: " answer
        printf -v "$var" '%s' "${answer:-$default}"
    fi
}

CT_IP=$(hostname -I | awk '{print $1}')
ask REPO_URL "GitHub repo SSH URL (e.g. git@github.com:you/website.git)"
[[ -n "$REPO_URL" ]] || die "A repository URL is required."
ask BRANCH "Branch" "main"
ask APP_URL "Public URL of the site" "http://${CT_IP}"
APP_URL=${APP_URL%/}
ask APP_NAME "Site name" "My Website"
ask ADMIN_EMAIL "Admin email"
[[ "$ADMIN_EMAIL" == *@* ]] || die "A valid admin email is required."
ask ADMIN_NAME "Admin name" "Admin"

ADMIN_PASSWORD=$(tr -dc 'A-Za-z0-9' </dev/urandom 2>/dev/null | head -c 20 || true)
DB_PASSWORD=$(tr -dc 'A-Za-z0-9' </dev/urandom 2>/dev/null | head -c 32 || true)

# ----------------------------------------------------------------------------
# System packages
# ----------------------------------------------------------------------------
step "Installing system packages (this takes a few minutes)…"
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq ca-certificates curl gnupg git unzip lsb-release cron openssh-client >/dev/null

if ! apt-cache show "php${PHP_VERSION}-fpm" >/dev/null 2>&1; then
    step "PHP ${PHP_VERSION} not in Debian ${VERSION_ID} repos — adding packages.sury.org"
    curl -fsSL https://packages.sury.org/php/apt.gpg -o /usr/share/keyrings/sury-php.gpg
    echo "deb [signed-by=/usr/share/keyrings/sury-php.gpg] https://packages.sury.org/php/ ${VERSION_CODENAME} main" > /etc/apt/sources.list.d/sury-php.list
    apt-get update -qq
fi

apt-get install -y -qq nginx mariadb-server \
    php${PHP_VERSION}-fpm php${PHP_VERSION}-cli php${PHP_VERSION}-mysql php${PHP_VERSION}-sqlite3 \
    php${PHP_VERSION}-mbstring php${PHP_VERSION}-xml php${PHP_VERSION}-curl php${PHP_VERSION}-zip \
    php${PHP_VERSION}-gd php${PHP_VERSION}-intl php${PHP_VERSION}-bcmath php${PHP_VERSION}-readline \
    php${PHP_VERSION}-opcache >/dev/null
ok "Nginx, MariaDB and PHP ${PHP_VERSION} installed"

if ! command -v composer >/dev/null; then
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    php /tmp/composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer
    rm /tmp/composer-setup.php
fi
ok "Composer $(composer --version 2>/dev/null | awk '{print $3}')"

if ! command -v node >/dev/null || [[ $(node -v | cut -d. -f1 | tr -d v) -lt 20 ]]; then
    curl -fsSL https://deb.nodesource.com/setup_22.x | bash - >/dev/null 2>&1
    apt-get install -y -qq nodejs >/dev/null 2>&1
fi
ok "Node $(node -v)"

# PHP tuning
for ini in /etc/php/${PHP_VERSION}/fpm/php.ini /etc/php/${PHP_VERSION}/cli/php.ini; do
    sed -i 's/^upload_max_filesize.*/upload_max_filesize = 64M/; s/^post_max_size.*/post_max_size = 64M/; s/^memory_limit.*/memory_limit = 512M/' "$ini"
done

# ----------------------------------------------------------------------------
# Database
# ----------------------------------------------------------------------------
step "Creating database…"
systemctl enable --now mariadb >/dev/null 2>&1
mysql -u root <<SQL
CREATE DATABASE IF NOT EXISTS website CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'website'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
ALTER USER 'website'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON website.* TO 'website'@'localhost';
FLUSH PRIVILEGES;
SQL
ok "Database 'website' ready"

# ----------------------------------------------------------------------------
# Deploy key + clone
# ----------------------------------------------------------------------------
step "Setting up GitHub access…"
mkdir -p "$APP_HOME/.ssh"
chown -R "$APP_USER:$APP_USER" "$APP_HOME"
chmod 700 "$APP_HOME/.ssh"
if [[ ! -f "$APP_HOME/.ssh/id_ed25519" ]]; then
    as_app ssh-keygen -t ed25519 -N "" -C "website-deploy@$(hostname)" -f "$APP_HOME/.ssh/id_ed25519" >/dev/null
fi
grep -q github.com "$APP_HOME/.ssh/known_hosts" 2>/dev/null \
    || as_app bash -c "ssh-keyscan -t ed25519 github.com >> ~/.ssh/known_hosts 2>/dev/null"

# Skip the key step if access already works (e.g. set up by the Proxmox helper script).
if ! as_app git ls-remote "$REPO_URL" >/dev/null 2>&1; then
    echo
    echo -e "${c_yellow}Add this deploy key to your GitHub repo:${c_off}"
    echo -e "  GitHub → your repo → Settings → Deploy keys → Add deploy key"
    echo -e "  Title: Proxmox server   ${c_yellow}✔ tick \"Allow write access\"${c_off} (so online edits can be pushed back)"
    echo
    cat "$APP_HOME/.ssh/id_ed25519.pub"
    echo
    read -rp "Press ENTER once the key is added… " _

    until as_app git ls-remote "$REPO_URL" >/dev/null 2>&1; do
        warn "Cannot reach the repo yet. Check the key / URL and press ENTER to retry (Ctrl+C to quit)."
        read -r _
    done
fi
ok "GitHub access works"

if [[ ! -d "$APP_DIR/.git" ]]; then
    mkdir -p "$APP_DIR" && chown "$APP_USER:$APP_USER" "$APP_DIR"
    as_app git clone --branch "$BRANCH" "$REPO_URL" "$APP_DIR"
fi
git config --system --add safe.directory "$APP_DIR"
as_app git -C "$APP_DIR" config user.name "Website server"
as_app git -C "$APP_DIR" config user.email "server@$(hostname)"
as_app git -C "$APP_DIR" config pull.rebase true
ok "Code cloned to $APP_DIR"

# ----------------------------------------------------------------------------
# Laravel
# ----------------------------------------------------------------------------
step "Configuring Laravel…"
cd "$APP_DIR"
if [[ ! -f .env ]]; then
    cat > .env <<ENV
APP_NAME="${APP_NAME}"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=${APP_URL}
APP_LOCALE=en
APP_FALLBACK_LOCALE=en

LOG_CHANNEL=daily
LOG_LEVEL=warning

DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=website
DB_USERNAME=website
DB_PASSWORD=${DB_PASSWORD}

SESSION_DRIVER=database
SESSION_LIFETIME=240
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local

# Email (needed for password reset / verification). Example for Gmail / any SMTP:
MAIL_MAILER=log
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS="${ADMIN_EMAIL}"
MAIL_FROM_NAME="\${APP_NAME}"

ADMIN_EMAIL="${ADMIN_EMAIL}"
ADMIN_NAME="${ADMIN_NAME}"
ENV
    chown "$APP_USER:$APP_USER" .env
    chmod 640 .env
fi

as_app composer install --no-dev --optimize-autoloader --no-interaction --quiet
grep -q '^APP_KEY=base64' .env || as_app php artisan key:generate --force
as_app npm ci --no-audit --no-fund --loglevel=error
as_app npm run build >/dev/null
as_app php artisan storage:link >/dev/null 2>&1 || true
as_app php artisan migrate --force
ADMIN_PASSWORD="$ADMIN_PASSWORD" as_app php artisan db:seed --force
as_app php artisan optimize
as_app php artisan filament:optimize >/dev/null 2>&1 || true
chown -R "$APP_USER:$APP_USER" "$APP_DIR"
ok "Laravel installed"

# ----------------------------------------------------------------------------
# code-server (VS Code in the browser) — only reachable through /code/ and
# only for admins (Nginx asks Laravel via /_code-auth before every request).
# ----------------------------------------------------------------------------
step "Installing code-server (VS Code in the browser)…"
if ! command -v code-server >/dev/null; then
    curl -fsSL https://code-server.dev/install.sh | sh -s -- --method=standalone --prefix=/usr/local >/dev/null
fi
mkdir -p "$APP_HOME/.local/share/code-server/User"
cat > "$APP_HOME/.local/share/code-server/User/settings.json" <<'JSON'
{
    "workbench.colorTheme": "Default Dark Modern",
    "telemetry.telemetryLevel": "off",
    "update.mode": "none",
    "editor.fontSize": 14,
    "files.exclude": { "**/node_modules": true, "**/vendor": true },
    "security.workspace.trust.enabled": false
}
JSON
chown -R "$APP_USER:$APP_USER" "$APP_HOME/.local"

cat > /etc/systemd/system/code-server-site.service <<UNIT
[Unit]
Description=code-server (VS Code) for the website
After=network.target

[Service]
User=${APP_USER}
Environment=HOME=${APP_HOME}
WorkingDirectory=${APP_DIR}
ExecStart=/usr/local/bin/code-server --bind-addr 127.0.0.1:${CODE_PORT} --auth none --disable-telemetry --disable-update-check ${APP_DIR}
Restart=always

[Install]
WantedBy=multi-user.target
UNIT
for ext in bmewburn.vscode-intelephense-client bradlc.vscode-tailwindcss onecentlab.laravel-blade; do
    as_app /usr/local/bin/code-server --install-extension "$ext" >/dev/null 2>&1 || warn "Could not install VS Code extension $ext (optional)"
done
ok "code-server installed"

# ----------------------------------------------------------------------------
# Nginx
# ----------------------------------------------------------------------------
step "Configuring Nginx…"
sed "s#__APP_DIR__#${APP_DIR}#g; s#__PHP__#${PHP_VERSION}#g; s#__CODE_PORT__#${CODE_PORT}#g" \
    "$APP_DIR/deploy/nginx.conf" > /etc/nginx/sites-available/website
ln -sf /etc/nginx/sites-available/website /etc/nginx/sites-enabled/website
rm -f /etc/nginx/sites-enabled/default
nginx -t >/dev/null 2>&1 || { nginx -t; die "Nginx config test failed"; }
ok "Nginx configured"

# ----------------------------------------------------------------------------
# Queue worker + scheduler
# ----------------------------------------------------------------------------
cat > /etc/systemd/system/website-queue.service <<UNIT
[Unit]
Description=Website queue worker
After=network.target mariadb.service

[Service]
User=${APP_USER}
WorkingDirectory=${APP_DIR}
ExecStart=/usr/bin/php ${APP_DIR}/artisan queue:work --sleep=3 --tries=3 --max-time=3600
Restart=always

[Install]
WantedBy=multi-user.target
UNIT
echo "* * * * * ${APP_USER} cd ${APP_DIR} && php artisan schedule:run >> /dev/null 2>&1" > /etc/cron.d/website-scheduler

systemctl daemon-reload
systemctl enable --now php${PHP_VERSION}-fpm nginx website-queue code-server-site cron >/dev/null 2>&1
systemctl restart php${PHP_VERSION}-fpm nginx
ok "Services running"

# ----------------------------------------------------------------------------
# The `update` command
# ----------------------------------------------------------------------------
cat > /usr/bin/update <<'CMD'
#!/usr/bin/env bash
# Pulls the latest code from GitHub and redeploys the website.
# The real logic lives in the repo (deploy/update.sh) so it updates itself.
set -e
tmp=$(mktemp)
cp /var/www/site/deploy/update.sh "$tmp"
bash "$tmp" "$@"
status=$?
rm -f "$tmp"
exit $status
CMD
chmod +x /usr/bin/update
ok "'update' command installed"

# ----------------------------------------------------------------------------
cat <<DONE

$(echo -e "${c_green}")══════════════════════════════════════════════════════════
  ✔ Installation complete!
══════════════════════════════════════════════════════════$(echo -e "${c_off}")

  Website:      ${APP_URL}
  Admin panel:  ${APP_URL}/admin
  VS Code:      ${APP_URL}/code/   (admins only)

  Admin login:  ${ADMIN_EMAIL}
  Password:     ${ADMIN_PASSWORD}
                (change it in Admin → your avatar → Profile)

  To deploy changes later, push to GitHub and run:   update

DONE
