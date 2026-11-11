#!/usr/bin/env bash
# =============================================================================
#  Puts the website online with HTTPS through a Cloudflare Tunnel.
#  No router port-forwarding needed; Cloudflare provides the certificate.
#
#  Run as root inside the website container:
#      bash /var/www/site/deploy/cloudflare-tunnel.sh
#  or non-interactive:
#      bash /var/www/site/deploy/cloudflare-tunnel.sh example.com <TUNNEL_TOKEN>
#
#  Get the token: Cloudflare dashboard → Zero Trust → Networks → Tunnels →
#  Create a tunnel → Cloudflared → copy the token from the install command.
# =============================================================================
set -euo pipefail
export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:${PATH:-}"

APP_DIR=/var/www/site
GN='\033[1;92m'; YW='\033[33m'; RD='\033[01;31m'; CL='\033[m'
ok()  { echo -e " ${GN}✔${CL} $1"; }
die() { echo -e " ${RD}✘ $1${CL}"; exit 1; }

[[ $EUID -eq 0 ]] || die "Run as root."
[[ -f "$APP_DIR/.env" ]] || die "Website not found in $APP_DIR."

DOMAIN=${1:-}
TOKEN=${2:-}
[[ -n "$DOMAIN" ]] || read -rp "Your domain (e.g. nikolajalbrechtsen.com): " DOMAIN
DOMAIN=${DOMAIN#https://}; DOMAIN=${DOMAIN#http://}; DOMAIN=${DOMAIN%%/*}
[[ "$DOMAIN" == *.* ]] || die "That doesn't look like a domain."
if [[ -z "$TOKEN" ]]; then
    echo -e "${YW}Paste the tunnel token (the long text after 'cloudflared service install'):${CL}"
    read -rp "> " TOKEN
fi
TOKEN=$(echo "$TOKEN" | sed -E 's/.*service install +//; s/[[:space:]]//g')
[[ ${#TOKEN} -gt 50 ]] || die "That token looks too short."

# --- Install cloudflared -----------------------------------------------------
if ! command -v cloudflared >/dev/null; then
    echo " Installing cloudflared…"
    mkdir -p --mode=0755 /usr/share/keyrings
    curl -fsSL https://pkg.cloudflare.com/cloudflare-main.gpg -o /usr/share/keyrings/cloudflare-main.gpg
    echo "deb [signed-by=/usr/share/keyrings/cloudflare-main.gpg] https://pkg.cloudflare.com/cloudflared any main" \
        > /etc/apt/sources.list.d/cloudflared.list
    apt-get update -qq && DEBIAN_FRONTEND=noninteractive apt-get install -y -qq cloudflared >/dev/null
fi
ok "cloudflared $(cloudflared --version 2>/dev/null | awk '{print $3}')"

# --- Connect the tunnel ------------------------------------------------------
if systemctl list-unit-files cloudflared.service >/dev/null 2>&1 && [[ -f /etc/systemd/system/cloudflared.service ]]; then
    cloudflared service uninstall >/dev/null 2>&1 || true
fi
cloudflared service install "$TOKEN" >/dev/null 2>&1 || die "Could not install the tunnel service — check the token."
systemctl enable --now cloudflared >/dev/null 2>&1
sleep 3
systemctl is-active --quiet cloudflared && ok "Tunnel connected" || die "Tunnel service is not running (journalctl -u cloudflared)."

# --- Point the website at https://DOMAIN --------------------------------------
sed -i "s#^APP_URL=.*#APP_URL=https://${DOMAIN}#" "$APP_DIR/.env"
runuser -u www-data -- php "$APP_DIR/artisan" optimize:clear >/dev/null
runuser -u www-data -- php "$APP_DIR/artisan" optimize >/dev/null
ok "Website address set to https://${DOMAIN}"

# --- Check -------------------------------------------------------------------
echo " Checking https://${DOMAIN} …"
for _ in $(seq 1 10); do
    code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 "https://${DOMAIN}/up" || true)
    [[ "$code" == "200" ]] && break
    sleep 3
done
if [[ "$code" == "200" ]]; then
    ok "https://${DOMAIN} is online 🎉"
else
    echo -e " ${YW}!${CL} Not reachable yet (HTTP ${code:-none}). In the Cloudflare tunnel, check the"
    echo -e "   Public Hostname: ${DOMAIN} → Service: HTTP → localhost:80"
fi

cat <<DONE

   Website:  https://${DOMAIN}
   Admin:    https://${DOMAIN}/admin
   VS Code:  https://${DOMAIN}/code/

   Social login callback URLs are now:
     https://${DOMAIN}/auth/google/callback
     https://${DOMAIN}/auth/github/callback
DONE
