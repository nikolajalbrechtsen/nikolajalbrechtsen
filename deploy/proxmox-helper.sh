#!/usr/bin/env bash
# =============================================================================
#  Proxmox VE helper script — creates an LXC container and installs the website.
#
#  Run on the PROXMOX HOST shell (not inside a container):
#      bash proxmox-helper.sh
#
#  What it does:
#    1. Asks a few questions (whiptail menus, like the community helper scripts)
#    2. Downloads the Debian 13 template and creates an unprivileged container
#    3. Creates a GitHub deploy key inside the container and waits for you to add it
#    4. Clones the repo and runs deploy/install.sh from it inside the container
#
#  Afterwards: push to GitHub and type `update` in the container console.
# =============================================================================
set -euo pipefail

# ---- Defaults (change if you like) ------------------------------------------
APP="Website"
DEFAULT_REPO="git@github.com:nikolajalbrechtsen/nikolajalbrechtsen.git"
DEFAULT_BRANCH="main"
DEFAULT_HOSTNAME="website"
DEFAULT_CORES=2
DEFAULT_RAM=2048
DEFAULT_DISK=10
DEFAULT_BRIDGE="vmbr0"

# ---- Look & feel -------------------------------------------------------------
YW='\033[33m'; GN='\033[1;92m'; RD='\033[01;31m'; BL='\033[36m'; CL='\033[m'; BOLD='\033[1m'
CM="${GN}✔${CL}"; CROSS="${RD}✘${CL}"; INFO="${BL}ℹ${CL}"
msg_info()  { echo -ne " ${YW}◌${CL} ${1}…\r"; }
msg_ok()    { echo -e "\033[2K ${CM} ${1}"; }
msg_error() { echo -e "\033[2K ${CROSS} ${RD}${1}${CL}"; }
die()       { msg_error "$1"; exit 1; }
BT="Proxmox VE Helper Script — ${APP}"

trap 'echo; msg_error "Aborted (line $LINENO)."' ERR

header() {
    clear
    echo -e "${GN}${BOLD}"
    cat <<'EOF'
 __        __   _         _ _
 \ \      / /__| |__  ___(_) |_ ___
  \ \ /\ / / _ \ '_ \/ __| | __/ _ \
   \ V  V /  __/ |_) \__ \ | ||  __/
    \_/\_/ \___|_.__/|___/_|\__\___|
     Laravel · Filament · code-server
EOF
    echo -e "${CL}"
}

# ---- Checks ------------------------------------------------------------------
header
[[ $EUID -eq 0 ]] || die "Run this as root on the Proxmox host."
command -v pct >/dev/null && command -v pveam >/dev/null || die "This must run on a Proxmox VE host (pct/pveam not found)."
command -v whiptail >/dev/null || die "whiptail is missing (apt install whiptail)."

input()  { whiptail --backtitle "$BT" --title "$1" --inputbox "$2" 10 70 "$3" 3>&1 1>&2 2>&3 || die "Cancelled."; }

whiptail --backtitle "$BT" --title "$APP LXC" --yesno \
"This will create a new Debian 13 LXC container and install:\n\n  • Nginx, PHP 8.4, MariaDB, Node 22\n  • the website + admin panel from GitHub\n  • VS Code in the browser (code-server)\n  • the 'update' command\n\nContinue?" 16 64 || die "Cancelled."

# ---- Container settings ------------------------------------------------------
CTID=$(pvesh get /cluster/nextid)
HN=$DEFAULT_HOSTNAME; CORES=$DEFAULT_CORES; RAM=$DEFAULT_RAM; DISK=$DEFAULT_DISK
BRIDGE=$DEFAULT_BRIDGE; NET="dhcp"; GATE=""; VLAN=""

if whiptail --backtitle "$BT" --title "Settings" --yesno \
"Use default settings?\n\n  Container ID:  ${CTID}\n  Hostname:      ${HN}\n  CPU cores:     ${CORES}\n  RAM:           ${RAM} MB\n  Disk:          ${DISK} GB\n  Network:       ${BRIDGE}, DHCP" 16 60 \
    --yes-button "Default" --no-button "Advanced"; then
    :
else
    while true; do
        CTID=$(input "Container ID" "Container ID:" "$CTID")
        if pct status "$CTID" &>/dev/null || qm status "$CTID" &>/dev/null; then
            whiptail --backtitle "$BT" --msgbox "ID $CTID is already in use." 8 50; continue
        fi
        break
    done
    HN=$(input "Hostname" "Hostname:" "$HN")
    CORES=$(input "CPU" "CPU cores:" "$CORES")
    RAM=$(input "RAM" "RAM in MB (min. 2048 recommended for building assets):" "$RAM")
    DISK=$(input "Disk" "Disk size in GB:" "$DISK")
    BRIDGE=$(input "Network" "Bridge:" "$BRIDGE")
    NET=$(input "Network" "IPv4 address in CIDR (e.g. 192.168.1.50/24) or 'dhcp':" "dhcp")
    if [[ "$NET" != "dhcp" ]]; then
        GATE=$(input "Network" "Gateway IP:" "")
    fi
    VLAN=$(input "Network" "VLAN tag (leave empty for none):" "")
fi

# ---- Storage -----------------------------------------------------------------
pick_storage() { # $1 = content type, $2 = label
    local list
    list=$(pvesm status -content "$1" 2>/dev/null | awk 'NR>1 {printf "%s %s ", $1, "free:"int($6/1048576)"GB"}')
    [[ -n "$list" ]] || die "No storage found that supports '$1'."
    # shellcheck disable=SC2086
    if [[ $(wc -w <<<"$list") -le 2 ]]; then
        awk '{print $1}' <<<"$list"
    else
        whiptail --backtitle "$BT" --title "Storage" --menu "Storage for $2:" 16 60 6 $list 3>&1 1>&2 2>&3 || die "Cancelled."
    fi
}
TEMPLATE_STORAGE=$(pick_storage vztmpl "the container template")
CT_STORAGE=$(pick_storage rootdir "the container disk")

# ---- Website settings --------------------------------------------------------
REPO_URL=$(input "GitHub" "Repository SSH URL:" "$DEFAULT_REPO")
BRANCH=$(input "GitHub" "Branch:" "$DEFAULT_BRANCH")
APP_NAME=$(input "Website" "Site name:" "Nikolaj Albrechtsen")
ADMIN_EMAIL=$(input "Admin" "Your admin email (login for the admin panel):" "")
[[ "$ADMIN_EMAIL" == *@* ]] || die "A valid admin email is required."
ADMIN_NAME=$(input "Admin" "Your name:" "Admin")
APP_URL=$(input "Website" "Public URL (e.g. https://example.com).\nLeave empty to use the container's IP:" "")

whiptail --backtitle "$BT" --title "Ready" --yesno \
"Create container ${CTID} (${HN}) on ${CT_STORAGE}\n${CORES} CPU · ${RAM} MB RAM · ${DISK} GB · ${BRIDGE} ${NET}\n\nRepo: ${REPO_URL} (${BRANCH})\nAdmin: ${ADMIN_EMAIL}" 14 70 || die "Cancelled."

header

# ---- Template ----------------------------------------------------------------
msg_info "Updating template list"
pveam update >/dev/null 2>&1 || true
ARCH=$(dpkg --print-architecture)   # amd64 on normal PCs/servers
find_template() { pveam available --section system | awk -v v="$1" -v a="_${ARCH}." '$2 ~ v && index($2, a) {print $2}' | sort -V | tail -1; }
TEMPLATE=$(find_template "debian-13-standard")
[[ -n "$TEMPLATE" ]] || TEMPLATE=$(find_template "debian-12-standard")
[[ -n "$TEMPLATE" ]] || die "No Debian template found for ${ARCH}."
msg_ok "Template: ${TEMPLATE}"

if ! pveam list "$TEMPLATE_STORAGE" | grep -q "$TEMPLATE"; then
    msg_info "Downloading template"
    pveam download "$TEMPLATE_STORAGE" "$TEMPLATE" >/dev/null || die "Template download failed."
    msg_ok "Template downloaded"
fi

# ---- Create container --------------------------------------------------------
ROOT_PW=$(tr -dc 'A-Za-z0-9' </dev/urandom | head -c 16 || true)
NET0="name=eth0,bridge=${BRIDGE},ip=${NET}"
[[ -n "$GATE" ]] && NET0+=",gw=${GATE}"
[[ -n "$VLAN" ]] && NET0+=",tag=${VLAN}"

msg_info "Creating LXC container ${CTID}"
pct create "$CTID" "${TEMPLATE_STORAGE}:vztmpl/${TEMPLATE}" \
    --hostname "$HN" \
    --cores "$CORES" \
    --memory "$RAM" \
    --swap 512 \
    --rootfs "${CT_STORAGE}:${DISK}" \
    --net0 "$NET0" \
    --ostype debian \
    --arch "$ARCH" \
    --unprivileged 1 \
    --features nesting=1 \
    --onboot 1 \
    --password "$ROOT_PW" \
    --tags "website;laravel" \
    --description "Laravel website. Deploy: push to GitHub, then run 'update' in the console." >/dev/null
msg_ok "Created LXC container ${CTID}"

# If anything fails from here on, remove the half-finished container so you can simply re-run.
cleanup_ct() {
    echo
    msg_error "Setup failed (line ${1}). Removing container ${CTID} so you can re-run the script."
    pct stop "$CTID" >/dev/null 2>&1 || true
    pct destroy "$CTID" --purge >/dev/null 2>&1 || true
    exit 1
}
trap 'cleanup_ct $LINENO' ERR

msg_info "Starting container"
pct start "$CTID"
for _ in $(seq 1 30); do
    pct exec "$CTID" -- getent hosts deb.debian.org >/dev/null 2>&1 && break
    sleep 2
done
pct exec "$CTID" -- getent hosts deb.debian.org >/dev/null 2>&1 || die "Container has no internet/DNS."
CT_IP=$(pct exec "$CTID" -- hostname -I | awk '{print $1}')
msg_ok "Container running at ${CT_IP}"

run() { pct exec "$CTID" -- env LANG=C.UTF-8 DEBIAN_FRONTEND=noninteractive bash -c "$1"; }

# ---- GitHub access -----------------------------------------------------------
msg_info "Preparing GitHub access"
run "apt-get update -qq && apt-get install -y -qq git openssh-client ca-certificates curl >/dev/null"
run "mkdir -p /var/www/.ssh && chown -R www-data:www-data /var/www && chmod 700 /var/www/.ssh
     [ -f /var/www/.ssh/id_ed25519 ] || runuser -u www-data -- ssh-keygen -q -t ed25519 -N '' -C 'website-deploy@${HN}' -f /var/www/.ssh/id_ed25519
     runuser -u www-data -- bash -c 'ssh-keyscan -t ed25519 github.com >> ~/.ssh/known_hosts 2>/dev/null'"
PUBKEY=$(pct exec "$CTID" -- cat /var/www/.ssh/id_ed25519.pub)
msg_ok "Deploy key created"

REPO_PATH=$(sed -E 's#^git@github.com:##; s#\.git$##' <<<"$REPO_URL")
echo
echo -e " ${INFO} ${BOLD}Add this deploy key to GitHub:${CL}"
echo -e "    https://github.com/${REPO_PATH}/settings/keys/new"
echo -e "    Title: ${HN} (Proxmox)    ${YW}✔ tick \"Allow write access\"${CL}"
echo
echo -e "${GN}${PUBKEY}${CL}"
echo
read -rp " Press ENTER once the key is added… " _

until run "runuser -u www-data -- env HOME=/var/www git ls-remote '${REPO_URL}' >/dev/null 2>&1"; do
    msg_error "GitHub refused the key (or wrong URL). Add it, then press ENTER to retry (Ctrl+C to stop)."
    read -r _
done
msg_ok "GitHub access works"

msg_info "Cloning repository"
run "mkdir -p /var/www/site && chown www-data:www-data /var/www/site &&
     runuser -u www-data -- env HOME=/var/www git clone -q --branch '${BRANCH}' '${REPO_URL}' /var/www/site"
msg_ok "Repository cloned"

# ---- Install -----------------------------------------------------------------
echo -e "\n ${INFO} Installing the website inside the container (5–10 minutes)…\n"
pct exec "$CTID" -- env LANG=C.UTF-8 \
    REPO_URL="$REPO_URL" BRANCH="$BRANCH" APP_URL="${APP_URL:-http://${CT_IP}}" \
    APP_NAME="$APP_NAME" ADMIN_EMAIL="$ADMIN_EMAIL" ADMIN_NAME="$ADMIN_NAME" \
    bash /var/www/site/deploy/install.sh </dev/null

trap - ERR
echo -e "
 ${CM} ${GN}${BOLD}${APP} LXC ${CTID} is ready!${CL}

    Website:       ${BL}${APP_URL:-http://${CT_IP}}${CL}
    Container IP:  ${CT_IP}
    Root password: ${ROOT_PW}   (console login: root)

    The admin login is shown above.
    Deploy changes: git push → open the container console → type ${GN}update${CL}
"
