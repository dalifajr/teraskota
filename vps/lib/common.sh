#!/usr/bin/env bash
# language: bash, file: vps/lib/common.sh, target: Ubuntu 24/26 x86_64/arm64
# Shared functions for Teras Kota VPS installer and control panel

set -euo pipefail

# ── Colors ────────────────────────────────────────────────────────────────────
readonly C_RED='\033[0;31m'
readonly C_GREEN='\033[0;32m'
readonly C_YELLOW='\033[1;33m'
readonly C_BLUE='\033[0;34m'
readonly C_MAGENTA='\033[0;35m'
readonly C_CYAN='\033[0;36m'
readonly C_WHITE='\033[1;37m'
readonly C_DIM='\033[2m'
readonly C_BOLD='\033[1m'
readonly C_RESET='\033[0m'

# ── Paths ─────────────────────────────────────────────────────────────────────
readonly TERASKOTA_ROOT="/var/www/teraskota"
readonly TERASKOTA_CONFIG="/etc/teraskota"
readonly TERASKOTA_CONFIG_FILE="${TERASKOTA_CONFIG}/config"
readonly TERASKOTA_BACKUP_DIR="/var/backups/teraskota"
readonly TERASKOTA_LOG="/var/log/teraskota-install.log"

# ── Logging ───────────────────────────────────────────────────────────────────
log_info()    { echo -e "${C_CYAN}[INFO]${C_RESET}  $*"; echo "[INFO]  $(date '+%Y-%m-%d %H:%M:%S') $*" >> "${TERASKOTA_LOG}" 2>/dev/null || true; }
log_ok()      { echo -e "${C_GREEN}[  OK]${C_RESET}  $*"; echo "[  OK]  $(date '+%Y-%m-%d %H:%M:%S') $*" >> "${TERASKOTA_LOG}" 2>/dev/null || true; }
log_warn()    { echo -e "${C_YELLOW}[WARN]${C_RESET}  $*"; echo "[WARN]  $(date '+%Y-%m-%d %H:%M:%S') $*" >> "${TERASKOTA_LOG}" 2>/dev/null || true; }
log_error()   { echo -e "${C_RED}[ ERR]${C_RESET}  $*" >&2; echo "[ ERR]  $(date '+%Y-%m-%d %H:%M:%S') $*" >> "${TERASKOTA_LOG}" 2>/dev/null || true; }
log_step()    { echo -e "\n${C_BOLD}${C_MAGENTA}━━━ $* ━━━${C_RESET}"; echo "━━━ $(date '+%Y-%m-%d %H:%M:%S') $* ━━━" >> "${TERASKOTA_LOG}" 2>/dev/null || true; }
log_substep() { echo -e "  ${C_DIM}→${C_RESET} $*"; }

# ── Spinner ───────────────────────────────────────────────────────────────────
spinner() {
    local pid=$1
    local msg="${2:-Working...}"
    local frames=('⠋' '⠙' '⠹' '⠸' '⠼' '⠴' '⠦' '⠧' '⠇' '⠏')
    local i=0
    while kill -0 "$pid" 2>/dev/null; do
        printf "\r  ${C_CYAN}%s${C_RESET} %s" "${frames[$i]}" "$msg"
        i=$(( (i + 1) % ${#frames[@]} ))
        sleep 0.1
    done
    wait "$pid"
    local exit_code=$?
    printf "\r"
    return $exit_code
}

# Run command with spinner, suppress stdout, show stderr on failure
run_with_spinner() {
    local msg="$1"
    shift
    local tmplog
    tmplog=$(mktemp)

    "$@" > "$tmplog" 2>&1 &
    local pid=$!

    if spinner "$pid" "$msg"; then
        log_ok "$msg"
        rm -f "$tmplog"
        return 0
    else
        local code=$?
        log_error "$msg — exit code $code"
        echo -e "${C_DIM}$(tail -20 "$tmplog")${C_RESET}"
        rm -f "$tmplog"
        return $code
    fi
}

# ── System Detection ─────────────────────────────────────────────────────────
detect_arch() {
    local arch
    arch=$(dpkg --print-architecture 2>/dev/null || uname -m)
    case "$arch" in
        amd64|x86_64) echo "amd64" ;;
        arm64|aarch64) echo "arm64" ;;
        *) echo "$arch" ;;
    esac
}

detect_os_codename() {
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        echo "${VERSION_CODENAME:-unknown}"
    else
        lsb_release -sc 2>/dev/null || echo "unknown"
    fi
}

detect_os_version() {
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        echo "${VERSION_ID:-unknown}"
    else
        lsb_release -sr 2>/dev/null || echo "unknown"
    fi
}

detect_php_version() {
    if command -v php &>/dev/null; then
        php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || echo ""
    else
        echo ""
    fi
}

detect_total_ram_mb() {
    awk '/MemTotal/ {printf "%d", $2/1024}' /proc/meminfo
}

detect_disk_free_gb() {
    df -BG / | awk 'NR==2 {gsub("G",""); print $4}'
}

# ── Checks ────────────────────────────────────────────────────────────────────
require_root() {
    if [ "$(id -u)" -ne 0 ]; then
        log_error "Script harus dijalankan sebagai root. Gunakan: sudo $0"
        exit 1
    fi
}

require_ubuntu() {
    if [ ! -f /etc/os-release ]; then
        log_error "Tidak dapat mendeteksi OS. File /etc/os-release tidak ditemukan."
        exit 1
    fi
    . /etc/os-release
    if [[ "${ID:-}" != "ubuntu" ]]; then
        log_error "OS tidak didukung: ${ID:-unknown}. Script ini hanya untuk Ubuntu."
        exit 1
    fi
}

check_command() {
    command -v "$1" &>/dev/null
}

# ── Config Management ────────────────────────────────────────────────────────
save_config() {
    local key="$1" value="$2"
    mkdir -p "${TERASKOTA_CONFIG}"
    if grep -q "^${key}=" "${TERASKOTA_CONFIG_FILE}" 2>/dev/null; then
        sed -i "s|^${key}=.*|${key}=${value}|" "${TERASKOTA_CONFIG_FILE}"
    else
        echo "${key}=${value}" >> "${TERASKOTA_CONFIG_FILE}"
    fi
}

load_config() {
    local key="$1"
    local default="${2:-}"
    if [ -f "${TERASKOTA_CONFIG_FILE}" ]; then
        grep "^${key}=" "${TERASKOTA_CONFIG_FILE}" 2>/dev/null | cut -d'=' -f2- || echo "$default"
    else
        echo "$default"
    fi
}

# ── Service Helpers ───────────────────────────────────────────────────────────
service_status() {
    local svc="$1"
    if systemctl is-active --quiet "$svc" 2>/dev/null; then
        echo -e "${C_GREEN}●${C_RESET} $svc ${C_GREEN}active${C_RESET}"
    else
        echo -e "${C_RED}●${C_RESET} $svc ${C_RED}inactive${C_RESET}"
    fi
}

service_action() {
    local action="$1" svc="$2"
    if systemctl "$action" "$svc" 2>/dev/null; then
        log_ok "$svc → $action"
    else
        log_error "Gagal $action $svc"
        return 1
    fi
}

# ── Interactive Prompt ────────────────────────────────────────────────────────
# Use whiptail if available, else fallback to read
prompt_input() {
    local title="$1" prompt="$2" default="${3:-}"

    if check_command whiptail; then
        local result
        result=$(whiptail --inputbox "$prompt" 10 60 "$default" --title "$title" 3>&1 1>&2 2>&3) || true
        echo "$result"
    else
        local result
        if [ -n "$default" ]; then
            read -rp "$(echo -e "${C_CYAN}$prompt${C_RESET} [${default}]: ")" result
            echo "${result:-$default}"
        else
            read -rp "$(echo -e "${C_CYAN}$prompt${C_RESET}: ")" result
            echo "$result"
        fi
    fi
}

prompt_password() {
    local title="$1" prompt="$2"

    if check_command whiptail; then
        local result
        result=$(whiptail --passwordbox "$prompt" 10 60 --title "$title" 3>&1 1>&2 2>&3) || true
        echo "$result"
    else
        local result
        read -srp "$(echo -e "${C_CYAN}$prompt${C_RESET}: ")" result
        echo
        echo "$result"
    fi
}

prompt_yesno() {
    local title="$1" prompt="$2" default="${3:-y}"

    if check_command whiptail; then
        if whiptail --yesno "$prompt" 10 60 --title "$title" 3>&1 1>&2 2>&3; then
            return 0
        else
            return 1
        fi
    else
        local yn
        if [ "$default" = "y" ]; then
            read -rp "$(echo -e "${C_CYAN}$prompt${C_RESET} [Y/n]: ")" yn
            yn="${yn:-y}"
        else
            read -rp "$(echo -e "${C_CYAN}$prompt${C_RESET} [y/N]: ")" yn
            yn="${yn:-n}"
        fi
        [[ "$yn" =~ ^[Yy] ]]
    fi
}

# ── Banner ────────────────────────────────────────────────────────────────────
print_banner() {
    echo -e "${C_BOLD}${C_CYAN}"
    cat << 'EOF'
  ╔════════════════════════════════════════════════════╗
  ║                                                    ║
  ║   ████████╗███████╗██████╗  █████╗ ███████╗        ║
  ║   ╚══██╔══╝██╔════╝██╔══██╗██╔══██╗██╔════╝        ║
  ║      ██║   █████╗  ██████╔╝███████║███████╗        ║
  ║      ██║   ██╔══╝  ██╔══██╗██╔══██║╚════██║        ║
  ║      ██║   ███████╗██║  ██║██║  ██║███████║        ║
  ║      ╚═╝   ╚══════╝╚═╝  ╚═╝╚═╝  ╚═╝╚══════╝        ║
  ║           K O T A   B E R L I A N                  ║
  ║                                                    ║
  ╚════════════════════════════════════════════════════╝
EOF
    echo -e "${C_RESET}"
}

# ── Trap handler ──────────────────────────────────────────────────────────────
cleanup_on_error() {
    local exit_code=$?
    if [ $exit_code -ne 0 ]; then
        echo
        log_error "Instalasi gagal dengan exit code $exit_code"
        log_error "Periksa log: ${TERASKOTA_LOG}"
        echo -e "${C_DIM}Gunakan 'cat ${TERASKOTA_LOG}' untuk melihat detail error.${C_RESET}"
    fi
}
