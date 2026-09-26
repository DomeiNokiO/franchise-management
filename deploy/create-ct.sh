#!/usr/bin/env bash
# ============================================================================
# Buat CT Franchise Management di Proxmox (jalankan DI HOST PVE sebagai root):
#
#   ./create-ct.sh [CTID] [IP_STATIC] [GW] [STORAGE]
#
# Contoh:
#   ./create-ct.sh 210 192.168.1.50 192.168.1.1 local-lvm
#   ./create-ct.sh 210 192.168.1.50 192.168.1.1      # storage default
#
# Env untuk kredensial awal (bila tidak diisi, install berjalan interaktif
# saat Anda attach console ke CT):
#   FRANCHISE_OWNER_NAME / FRANCHISE_OWNER_EMAIL / FRANCHISE_OWNER_PASSWORD
#   FRANCHISE_DOMAIN / FRANCHISE_LE_EMAIL / USE_MYSQL
#
# CT dibuat UNPRIVILEGED, TANPA nesting — instalasi aplikasi native
# (PHP-FPM + Nginx + SQLite) tidak membutuhkan nesting sama sekali.
# ============================================================================
set -Eeuo pipefail
export LC_ALL=C.UTF-8 LANG=C.UTF-8

CTID="${1:-}"
IP="${2:-}"
GW="${3:-}"
STORAGE="${4:-}"
CPU=1
MEM=1024
DISK=8
TEMPLATE_OS="ubuntu"
BRIDGE="vmbr0"

REPO_URL="${REPO_URL:-https://github.com/DomeiNokiO/franchise-management.git}"
INSTALL_URL="${INSTALL_URL:-https://raw.githubusercontent.com/DomeiNokiO/franchise-management/refs/heads/main/deploy/install.sh}"

fail() { echo "[FATAL] $*" >&2; exit 1; }
log()  { echo "==> $*"; }

command -v pct >/dev/null || fail "Jalankan di host Proxmox (perlu perintah 'pct')."
[[ "${EUID}" -eq 0 ]] || fail "Jalankan sebagai root di host PVE."

if [[ -z "$CTID" ]]; then
    CTID="$(pvesh get /cluster/nextid 2>/dev/null | grep -oP 'nextid:\s*\K\d+' || true)"
    [[ -n "$CTID" ]] || CTID=9000
fi
pct status "$CTID" >/dev/null 2>&1 && fail "CTID $CTID sudah dipakai."

# IP: bila kosong = DHCP (install via console interaktif)
if [[ -n "$IP" ]]; then
    [[ "$IP" =~ ^([0-9]{1,3}\.){3}[0-9]{1,3}$ ]] || fail "IP tidak valid: $IP"
    [[ -n "$GW" ]] && [[ "$GW" =~ ^([0-9]{1,3}\.){3}[0-9]{1,3}$ ]] || fail "GW (arg 3) wajib jika IP statis diisi. GW: $GW"
    NET0="ip=$IP/24,gw=$GW"
else
    NET0="dhcp"
fi

# storage: default = storage pertama yang tersedia untuk CT
if [[ -z "$STORAGE" ]]; then
    STORAGE="$(pvesm status 2>/dev/null | awk '$5=="lxc" || $6=="lxc" {print $1; exit}')"
    [[ -n "$STORAGE" ]] || fail "Tidak ada storage CT terdeteksi. Beri argumen ke-4 (nama storage)."
fi

# template OS terbaru (unduh bila belum ada)
# Sumber data: pvesh get /cluster/templates (JSON tepercaya, mencakup semua storage)
log "Mencari template LXC Ubuntu di storage $STORAGE"
TPL_JSON_FILE="$(mktemp)"
trap 'rm -f "$TPL_JSON_FILE"' EXIT
find_tpl() {
    pvesh get /cluster/templates --output-format json > "$TPL_JSON_FILE" 2>/dev/null || { echo ""; return 1; }
    python3 - "$TPL_JSON_FILE" "$STORAGE" <<'PY'
import json, sys
path, st = sys.argv[1], sys.argv[2]
try:
    data = json.load(open(path))
except Exception:
    sys.exit(0)
hits = [t['name'] for t in data
        if t.get('ostype') == 'lxc'
        and t.get('storage') == st
        and t.get('name', '').startswith('ubuntu')
        and 'standard' in t.get('name', '')]
# pilih versi terbesar leksikografis = biasanya versi terbaru (24.04 > 22.04)
print(sorted(hits)[-1] if hits else '')
PY
}
TPL_NAME="$(find_tpl || true)"
if [[ -z "$TPL_NAME" ]]; then
    log "Template Ubuntu belum ada — mencari & mengunduh (bisa beberapa menit)…"
    pveam update >/dev/null
    TPL_NAME="$(pveam available --section system 2>/dev/null | awk '$2=="lxc" && $1 ~ /^ubuntu.*standard/ {print $1; exit}')"
    [[ -n "$TPL_NAME" ]] || fail "Template Ubuntu tidak tersedia di mirror PVE. Jalankan: pveam update && pveam available"
    log "Mengunduh $TPL_NAME ke $STORAGE"
    pveam download "$STORAGE" "$TPL_NAME"
    TPL_NAME="$(find_tpl || true)"
    [[ -n "$TPL_NAME" ]] || fail "Download template selesai tetapi template tidak ditemukan di storage $STORAGE."
fi
log "Template: $TPL_NAME ($STORAGE)"

# buat CT unprivileged
log "Membuat CT $CTID (1 vCPU / ${MEM} MB / ${DISK} GB, $STORAGE)"
pct create "$CTID" "storage:$TPL_NAME" \
    --name franchise-mgmt \
    --cores "$CPU" --memory "$MEM" \
    --disk "scsi0=$STORAGE:${DISK}" \
    --net0 "name=eth0,bridge=$BRIDGE,$NET0" \
    --onboot 1 \
    --unprivileged 1 \
    --features nesting=0,keyctl=1

pct set "$CTID" --arch amd64 --agent 1 >/dev/null 2>&1 || true
pct start "$CTID"

# tunggu jaringan CT siap (bukan sleep buta)
log "Menunggu jaringan CT siap…"
OK=""
for i in $(seq 1 45); do
    if pct exec "$CTID" -- bash -c 'getent hosts deb.debian.org >/dev/null 2>&1 || ping -c1 -W1 1.1.1.1 >/dev/null 2>&1' 2>/dev/null; then
        OK=1; break
    fi
    sleep 2
done
[[ "$OK" == "1" ]] || fail "Jaringan CT belum siap setelah 90 detik. Cek: pct exec $CTID -- ip a"

# installer di dalam CT
INSTALL_ENV=""
for v in FRANCHISE_OWNER_NAME FRANCHISE_OWNER_EMAIL FRANCHISE_OWNER_PASSWORD FRANCHISE_DOMAIN FRANCHISE_LE_EMAIL USE_MYSQL SKIP_TLS REPO_URL; do
    val="$(eval echo \"\${$v:-}\")"
    if [[ -n "$val" ]]; then
        INSTALL_ENV="$INSTALL_ENV $v='$val'"
    fi
done

log "Menjalankan installer di dalam CT $CTID…"
if [[ -n "$INSTALL_ENV" ]]; then
    # non-interaktif: semua kredensial via env
    pct exec "$CTID" -- bash -c "set -Eeuo pipefail; export LC_ALL=C.UTF-8 LANG=C.UTF-8 DEBIAN_FRONTEND=noninteractive; \
        apt-get update -qq && apt-get install -y -qq curl >/dev/null 2>&1; \
        curl -fsSL '$INSTALL_URL' -o /root/install-franchise.sh && \
        env $INSTALL_ENV bash /root/install-franchise.sh"
else
    # interaktif: tampilkan petunjuk attach console
    log "Kredensial belum di-set → installer berjalan INTERAKTIF di console CT."
    log "Buka console CT $CTID sekarang (PVE WebUI: Terminal CT) dan lanjutkan prompt installer."
fi

# verifikasi singkat dari sisi host
sleep 3
if pct exec "$CTID" -- curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1/login 2>/dev/null | grep -q '200'; then
    log "VERIFIKASI OK — aplikasi merespons di dalam CT."
else
    log "PERHATIAN: cek instalasi di console CT (mungkin menunggu input interaktif)."
fi

# IP untuk tampilkan
CT_IP="$(pct config "$CTID" 2>/dev/null | awk -F= '/^net0/ {print}' | grep -oP 'ip=\K[0-9.]+' | head -1)"
[[ -n "$CT_IP" ]] || CT_IP="$IP (DHCP — lihat console)"

echo
echo "============================================================"
echo "  CT FRANCHISE MANAGEMENT SIAP"
echo "============================================================"
echo "  CTID        : $CTID (franchise-mgmt)"
echo "  IP          : $CT_IP"
echo "  Akses       : http://$CT_IP"
echo "  Console     : PVE WebUI > $CTID > Terminal"
echo
if [[ -z "$INSTALL_ENV" ]]; then
echo "  ⚠ Installer interaktif: buka console CT untuk menyelesaikan."
fi
echo "  Update app  : pct exec $CTID -- /opt/franchise-management/deploy/update.sh"
echo "============================================================"
