#!/usr/bin/env bash
# =============================================================================
#  Configures code-server (VS Code at /code/) — safe to run many times.
#  Called by install.sh and by every `update`.
#
#  - Gives the VS Code terminal a real bash shell (www-data's login shell is
#    /usr/sbin/nologin, which made the terminal close immediately)
#  - Opens the terminal in the website folder with handy aliases
#  - Lets the terminal run `update` (via sudo, only that one command)
# =============================================================================
set -euo pipefail
export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:${PATH:-}"

APP_DIR=/var/www/site
APP_USER=www-data
APP_HOME=/var/www
CODE_PORT=8443
CHANGED=0

command -v code-server >/dev/null || { echo "code-server not installed — skipping"; exit 0; }

# --- sudo for `update` --------------------------------------------------------
command -v sudo >/dev/null || DEBIAN_FRONTEND=noninteractive apt-get install -y -qq sudo >/dev/null
SUDOERS=/etc/sudoers.d/website-update
echo "${APP_USER} ALL=(root) NOPASSWD: /usr/bin/update" > "${SUDOERS}.tmp"
chmod 440 "${SUDOERS}.tmp"
if visudo -cf "${SUDOERS}.tmp" >/dev/null; then mv "${SUDOERS}.tmp" "$SUDOERS"; else rm -f "${SUDOERS}.tmp"; fi

# --- systemd service ----------------------------------------------------------
UNIT=/etc/systemd/system/code-server-site.service
NEW_UNIT=$(cat <<UNIT
[Unit]
Description=code-server (VS Code) for the website
After=network.target

[Service]
User=${APP_USER}
Environment=HOME=${APP_HOME}
Environment=SHELL=/bin/bash
Environment=PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
WorkingDirectory=${APP_DIR}
ExecStart=/usr/local/bin/code-server --bind-addr 127.0.0.1:${CODE_PORT} --auth none --disable-telemetry --disable-update-check ${APP_DIR}
Restart=always

[Install]
WantedBy=multi-user.target
UNIT
)
if [[ "$(cat "$UNIT" 2>/dev/null)" != "$NEW_UNIT" ]]; then
    echo "$NEW_UNIT" > "$UNIT"
    CHANGED=1
fi

# --- VS Code settings (merged, so your own settings are kept) -----------------
SETTINGS_DIR="$APP_HOME/.local/share/code-server/User"
mkdir -p "$SETTINGS_DIR"
php -r '
$file = $argv[1];
$s = is_file($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];
$defaults = [
    "workbench.colorTheme" => "Default Dark Modern",
    "telemetry.telemetryLevel" => "off",
    "update.mode" => "none",
    "editor.fontSize" => 14,
    "files.exclude" => ["**/node_modules" => true, "**/vendor" => true],
    "security.workspace.trust.enabled" => false,
];
$forced = [
    "terminal.integrated.defaultProfile.linux" => "bash",
    "terminal.integrated.profiles.linux" => ["bash" => ["path" => "/bin/bash", "args" => ["-l"], "icon" => "terminal-bash"]],
    "terminal.integrated.cwd" => "/var/www/site",
];
$s = array_merge($defaults, $s, $forced);
file_put_contents($file, json_encode($s, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
' "$SETTINGS_DIR/settings.json"

# --- Shell profile for the terminal -------------------------------------------
cat > "$APP_HOME/.bash_profile" <<'PROFILE'
# Managed by deploy/configure-code-server.sh
[ -f ~/.bashrc ] && . ~/.bashrc
PROFILE

if ! grep -q "# website-terminal" "$APP_HOME/.bashrc" 2>/dev/null; then
    cat >> "$APP_HOME/.bashrc" <<'RC'
# website-terminal — added by deploy/configure-code-server.sh
export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"
export EDITOR=nano
cd /var/www/site 2>/dev/null
PS1='\[\e[1;32m\]website\[\e[0m\]:\[\e[1;34m\]\w\[\e[0m\]\$ '
alias art='php artisan'
alias tinker='php artisan tinker'
alias logs='tail -f storage/logs/laravel*.log'
alias update='sudo /usr/bin/update'
alias ll='ls -lah'
echo -e "\e[2mWebsite terminal (user: www-data). Try: art migrate:status · git status · npm run build · update\e[0m"
RC
fi

chown -R "$APP_USER:$APP_USER" "$APP_HOME/.local" "$APP_HOME/.bashrc" "$APP_HOME/.bash_profile"

# --- Apply --------------------------------------------------------------------
systemctl daemon-reload
systemctl enable code-server-site >/dev/null 2>&1 || true
if [[ $CHANGED -eq 1 ]] || ! systemctl is-active --quiet code-server-site; then
    systemctl restart code-server-site
    echo "code-server configured and restarted (terminal enabled)"
fi
