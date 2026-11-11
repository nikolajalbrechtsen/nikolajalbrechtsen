#!/usr/bin/env bash
# =============================================================================
#  `update` — deploy the latest code from GitHub.
#
#    update            pull + rebuild only what changed
#    update --full     rebuild everything (composer, npm, caches)
#
#  Files edited online (admin File editor or VS Code at /code/) are committed
#  and pushed to GitHub first, so nothing is ever lost and your PC can
#  `git pull` them.
# =============================================================================
set -uo pipefail

APP_DIR=/var/www/site
APP_USER=www-data
APP_HOME=/var/www
FULL=0
[[ "${1:-}" == "--full" ]] && FULL=1

c_green='\033[1;32m'; c_yellow='\033[1;33m'; c_red='\033[1;31m'; c_blue='\033[1;34m'; c_off='\033[0m'
step() { echo -e "${c_blue}==>${c_off} ${1}"; }
ok()   { echo -e "${c_green}✔${c_off} ${1}"; }
warn() { echo -e "${c_yellow}!${c_off} ${1}"; }
die()  { echo -e "${c_red}✘ ${1}${c_off}"; exit 1; }
as_app() { runuser -u "$APP_USER" -- env HOME="$APP_HOME" "$@"; }
artisan() { as_app php "$APP_DIR/artisan" "$@"; }

[[ $EUID -eq 0 ]] || die "Run 'update' as root."
cd "$APP_DIR" || die "$APP_DIR not found"

START=$(date +%s)
BRANCH=$(as_app git rev-parse --abbrev-ref HEAD)
OLD=$(as_app git rev-parse HEAD)

# --- 1. Save online edits -----------------------------------------------------
if [[ -n "$(as_app git status --porcelain)" ]]; then
    step "Saving files edited on the server…"
    as_app git status --short
    as_app git add -A
    as_app git commit -q -m "Online edits on server ($(date '+%Y-%m-%d %H:%M'))"
    ok "Committed online edits"
fi

# --- 2. Pull from GitHub ------------------------------------------------------
step "Pulling latest code from GitHub ($BRANCH)…"
if ! as_app git pull --rebase -q origin "$BRANCH"; then
    as_app git rebase --abort >/dev/null 2>&1
    die "Merge conflict between online edits and GitHub. Resolve it in VS Code (/code/) or on your PC, then run 'update' again."
fi

# Push online edits (if any) back to GitHub
if [[ -n "$(as_app git log "origin/$BRANCH..HEAD" --oneline 2>/dev/null)" ]]; then
    if as_app git push -q origin "$BRANCH"; then
        ok "Pushed online edits to GitHub — run 'git pull' on your PC to get them"
    else
        warn "Could not push online edits (does the deploy key have write access?). They are kept on the server."
    fi
fi

NEW=$(as_app git rev-parse HEAD)
changed() { [[ $FULL -eq 1 ]] && return 0; as_app git diff --name-only "$OLD" "$NEW" | grep -qE "$1"; }

if [[ "$OLD" == "$NEW" && $FULL -eq 0 ]]; then
    ok "Already up to date ($(as_app git log -1 --format='%h %s'))"
else
    as_app git log --oneline "$OLD..$NEW" 2>/dev/null | head -20 | sed 's/^/   • /'
fi

# --- 3. Maintenance mode while migrating --------------------------------------
artisan down --retry=10 --refresh=10 >/dev/null 2>&1 || true
trap 'artisan up >/dev/null 2>&1' EXIT

# --- 4. Dependencies ----------------------------------------------------------
if changed '^composer\.(json|lock)$' || [[ ! -d vendor ]]; then
    step "Installing PHP packages…"
    as_app composer install --no-dev --optimize-autoloader --no-interaction --quiet || die "composer install failed"
fi

if changed '^(package(-lock)?\.json|vite\.config\.js|resources/(css|js|views)/|tailwind)' || [[ ! -f public/build/manifest.json ]]; then
    if changed '^package(-lock)?\.json$' || [[ ! -d node_modules ]]; then
        step "Installing JS packages…"
        as_app npm ci --no-audit --no-fund --loglevel=error || die "npm ci failed"
    fi
    step "Building CSS/JS…"
    as_app npm run build >/dev/null || die "npm run build failed"
fi

# --- 5. Database + caches -----------------------------------------------------
step "Migrating database…"
artisan optimize:clear >/dev/null
artisan migrate --force || die "Migration failed — site stays in maintenance mode. Fix and run 'update' again."
artisan db:seed --force >/dev/null   # idempotent: adds new roles/permissions only

step "Optimizing…"
artisan storage:link >/dev/null 2>&1 || true
artisan optimize >/dev/null
artisan filament:optimize >/dev/null 2>&1 || true
artisan queue:restart >/dev/null

chown -R "$APP_USER:$APP_USER" "$APP_DIR"
systemctl reload "php*-fpm" 2>/dev/null || systemctl reload php8.4-fpm 2>/dev/null || true

artisan up >/dev/null
trap - EXIT

ok "Website updated in $(( $(date +%s) - START ))s — now at $(as_app git log -1 --format='%h %s')"
