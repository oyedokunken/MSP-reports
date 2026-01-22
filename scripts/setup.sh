#!/usr/bin/env bash
#
# MSP Reports - Setup Script
#
# Usage: ./setup.sh [options]
#
# Run ./setup.sh --help for options.
#
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKEND_DIR="$ROOT_DIR/backend"
FRONTEND_DIR="$ROOT_DIR/frontend"
EMBEDDING_DIR="$ROOT_DIR/services/embedding"
SYSTEMD_DIR="$ROOT_DIR/systemd"

INSTALL_SYSTEM_DEPS=true
INSTALL_EMBEDDING=true
INSTALL_OLLAMA=true
DB_DRIVER="pgsql"   # sqlite | pgsql
RUN_MIGRATIONS=true
RUN_SEED=false
BUILD_FRONTEND=false

usage() {
  cat <<'USAGE'
Usage: ./setup.sh [options]

Options:
  --install-system-deps   Install OS packages via apt (requires sudo)
  --db=sqlite|pgsql       Configure backend DB driver (default: sqlite)
  --no-migrate            Do not run migrations
  --seed                  Run database seeders after migrate
  --build-frontend        Build the frontend (for production-like setup)
  --embedding             Set up the Python embedding service
  --ollama                Install Ollama for LLM agent matching
  -h, --help              Show help

Notes:
  - This script is intended for local development.
  - It is idempotent: safe to re-run without overwriting .env files.
USAGE
}

for arg in "$@"; do
  case "$arg" in
    --install-system-deps) INSTALL_SYSTEM_DEPS=true ;;
    --db=*) DB_DRIVER="${arg#*=}" ;;
    --no-migrate) RUN_MIGRATIONS=false ;;
    --seed) RUN_SEED=true ;;
    --build-frontend) BUILD_FRONTEND=true ;;
    --embedding) INSTALL_EMBEDDING=true ;;
    --ollama) INSTALL_OLLAMA=true ;;
    -h|--help) usage; exit 0 ;;
    *) echo "Unknown option: $arg"; usage; exit 2 ;;
  esac
done

if [[ "$DB_DRIVER" != "sqlite" && "$DB_DRIVER" != "pgsql" ]]; then
  echo "Invalid --db value: $DB_DRIVER (expected sqlite or pgsql)" >&2
  exit 2
fi

# --- Helpers ---

info()  { echo "==> $*"; }
warn()  { echo "WARNING: $*" >&2; }

need_cmd() {
  if ! command -v "$1" >/dev/null 2>&1; then
    echo "Missing required command: $1" >&2
    return 1
  fi
}

upsert_env() {
  local file="$1" key="$2" val="$3"
  if grep -q "^${key}=" "$file"; then
    sed -i "s#^${key}=.*#${key}=${val}#" "$file"
  else
    echo "${key}=${val}" >> "$file"
  fi
}

apt_install() {
  sudo apt-get install -y "$@"
}

# --- System dependencies (optional) ---

install_system_deps() {
  need_cmd sudo
  need_cmd apt-get || { echo "apt-get not found. Only Debian/Ubuntu supported." >&2; exit 1; }

  info "Updating apt cache"
  sudo apt-get update

  info "Installing base packages"
  apt_install ca-certificates curl gnupg lsb-release

  if ! dpkg -l | grep -qE '^ii\s+php8\.3-cli\b'; then
    info "Adding ondrej/php PPA"
    sudo add-apt-repository ppa:ondrej/php -y
    sudo apt-get update
  fi

  info "Installing PHP 8.3 and extensions"
  apt_install \
    php8.3-cli php8.3-fpm php8.3-common php8.3-pgsql php8.3-redis php8.3-gd php8.3-curl \
    php8.3-xml php8.3-mbstring php8.3-zip php8.3-bcmath php8.3-ldap php8.3-intl

  if ! command -v composer >/dev/null 2>&1; then
    info "Installing Composer"
    curl -sS https://getcomposer.org/installer | php
    sudo mv composer.phar /usr/local/bin/composer
  fi

  if ! command -v node >/dev/null 2>&1; then
    info "Installing Node.js 20"
    curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
    apt_install nodejs
  fi

  info "Installing nginx and redis-server"
  apt_install nginx redis-server

  info "Installing Python 3.11+ for embedding service"
  apt_install python3 python3-pip python3-venv

  if [[ "$DB_DRIVER" == "pgsql" ]]; then
    if ! systemctl is-active --quiet postgresql 2>/dev/null; then
      info "Installing PostgreSQL 16"
      apt_install postgresql-16 postgresql-contrib-16
    fi

    if ! dpkg -l | grep -q timescaledb-2-postgresql-16; then
      info "Installing TimescaleDB"
      curl -s https://packagecloud.io/install/repositories/timescale/timescaledb/script.deb.sh | sudo bash
      apt_install timescaledb-2-postgresql-16
      command -v timescaledb-tune >/dev/null && sudo timescaledb-tune --quiet --yes || true
      sudo systemctl restart postgresql || true
    fi
  fi
}

configure_backend_env() {
  info "Configuring backend .env"
  if [[ ! -f "$BACKEND_DIR/.env" ]]; then
    cp "$BACKEND_DIR/.env.example" "$BACKEND_DIR/.env"
  fi

  if [[ "$DB_DRIVER" == "sqlite" ]]; then
    if [[ ! -f "$BACKEND_DIR/database/database.sqlite" ]]; then
      mkdir -p "$BACKEND_DIR/database"
      : > "$BACKEND_DIR/database/database.sqlite"
    fi

    if grep -q '^DB_CONNECTION=' "$BACKEND_DIR/.env"; then
      sed -i 's/^DB_CONNECTION=.*/DB_CONNECTION=sqlite/' "$BACKEND_DIR/.env"
    else
      echo 'DB_CONNECTION=sqlite' >> "$BACKEND_DIR/.env"
    fi

    sed -i 's/^DB_HOST=.*/# DB_HOST=127.0.0.1/' "$BACKEND_DIR/.env" || true
    sed -i 's/^DB_PORT=.*/# DB_PORT=5432/' "$BACKEND_DIR/.env" || true
    sed -i 's/^DB_DATABASE=.*/# DB_DATABASE=reporting/' "$BACKEND_DIR/.env" || true
    sed -i 's/^DB_USERNAME=.*/# DB_USERNAME=postgres/' "$BACKEND_DIR/.env" || true
    sed -i 's/^DB_PASSWORD=.*/# DB_PASSWORD=/' "$BACKEND_DIR/.env" || true
  fi

  if [[ "$DB_DRIVER" == "pgsql" ]]; then
    if grep -q '^DB_CONNECTION=' "$BACKEND_DIR/.env"; then
      sed -i 's/^DB_CONNECTION=.*/DB_CONNECTION=pgsql/' "$BACKEND_DIR/.env"
    else
      echo 'DB_CONNECTION=pgsql' >> "$BACKEND_DIR/.env"
    fi

    upsert_env "$BACKEND_DIR/.env" "DB_HOST" "127.0.0.1"
    upsert_env "$BACKEND_DIR/.env" "DB_PORT" "5432"
    upsert_env "$BACKEND_DIR/.env" "DB_DATABASE" "reporting"
    upsert_env "$BACKEND_DIR/.env" "DB_USERNAME" "reporting_user"
    upsert_env "$BACKEND_DIR/.env" "DB_PASSWORD" "reporting_password"
  fi
}

configure_frontend_env() {
  info "Configuring frontend .env"
  if [[ ! -f "$FRONTEND_DIR/.env" ]]; then
    cp "$FRONTEND_DIR/.env.example" "$FRONTEND_DIR/.env"
  fi
}

setup_pgsql() {
  info "Setting up PostgreSQL database"
  need_cmd psql

  if ! systemctl is-active --quiet postgresql 2>/dev/null; then
    warn "PostgreSQL service is not running. Start it: sudo systemctl start postgresql"
    exit 1
  fi

  if ! sudo -n true 2>/dev/null; then
    warn "PostgreSQL setup needs sudo (for creating db/user via postgres role)."
    warn "Re-run with sudo, or create DB/user manually and update backend/.env."
    exit 1
  fi

  if sudo -u postgres psql -lqt | cut -d '|' -f1 | tr -d ' ' | grep -qx reporting; then
    info "Database 'reporting' already exists"
  else
    info "Creating database 'reporting'"
    sudo -u postgres createdb reporting
  fi

  info "Creating user 'reporting_user' (if not exists)"
  sudo -u postgres psql -c "DO \$\$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'reporting_user') THEN CREATE ROLE reporting_user LOGIN PASSWORD 'reporting_password'; END IF; END \$\$;"
  sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE reporting TO reporting_user;"

  info "Granting schema privileges (PostgreSQL 15+ requirement)"
  sudo -u postgres psql -d reporting -c "GRANT ALL ON SCHEMA public TO reporting_user;"
  sudo -u postgres psql -d reporting -c "ALTER SCHEMA public OWNER TO reporting_user;"

  info "Enabling TimescaleDB extension"
  sudo -u postgres psql -d reporting -c "CREATE EXTENSION IF NOT EXISTS timescaledb CASCADE;" || true
}

backend_steps() {
  info "Setting up backend"
  need_cmd php
  need_cmd composer

  configure_backend_env

  if [[ "$DB_DRIVER" == "pgsql" ]]; then
    setup_pgsql
  fi

  info "Installing Composer dependencies"
  (cd "$BACKEND_DIR" && composer install)

  info "Generating application key"
  (cd "$BACKEND_DIR" && php artisan key:generate)

  info "Creating storage symlink"
  (cd "$BACKEND_DIR" && php artisan storage:link || true)

  if [[ "$RUN_MIGRATIONS" == true ]]; then
    info "Running database migrations"
    if [[ "$RUN_SEED" == true ]]; then
      (cd "$BACKEND_DIR" && php artisan migrate --seed)
    else
      (cd "$BACKEND_DIR" && php artisan migrate)
    fi
  fi
}

frontend_steps() {
  info "Setting up frontend"
  need_cmd npm

  configure_frontend_env

  info "Installing npm dependencies"
  (cd "$FRONTEND_DIR" && npm ci)

  if [[ "$BUILD_FRONTEND" == true ]]; then
    info "Building frontend for production"
    (cd "$FRONTEND_DIR" && npm run build)
  fi
}

install_ollama() {
  info "Installing Ollama for LLM agent matching"
  
  if command -v ollama >/dev/null 2>&1; then
    info "Ollama already installed"
  else
    info "Downloading and installing Ollama"
    curl -fsSL https://ollama.com/install.sh | sh
  fi
  
  info "Pulling qwen2.5:7b model (this may take a while)"
  ollama pull qwen2.5:7b || warn "Failed to pull model. Run 'ollama pull qwen2.5:7b' manually."
  
  info "Ollama installation complete"
}

embedding_steps() {
  info "Setting up embedding service"
  need_cmd python3
  
  if [[ ! -d "$EMBEDDING_DIR" ]]; then
    warn "Embedding service directory not found: $EMBEDDING_DIR"
    return 1
  fi
  
  # Create virtual environment if it doesn't exist
  if [[ ! -d "$EMBEDDING_DIR/venv" ]]; then
    info "Creating Python virtual environment"
    python3 -m venv "$EMBEDDING_DIR/venv"
  fi
  
  info "Installing Python dependencies"
  "$EMBEDDING_DIR/venv/bin/pip" install --upgrade pip
  "$EMBEDDING_DIR/venv/bin/pip" install -r "$EMBEDDING_DIR/requirements.txt"
  
  # Pre-download the SentenceTransformer model
  info "Pre-downloading SentenceTransformer model (all-MiniLM-L6-v2)"
  "$EMBEDDING_DIR/venv/bin/python" -c "from sentence_transformers import SentenceTransformer; SentenceTransformer('all-MiniLM-L6-v2')" || warn "Model download failed, will download on first use"
  
  # Create log directory
  if [[ ! -d "/var/log/mspreports" ]]; then
    info "Creating log directory /var/log/mspreports"
    sudo mkdir -p /var/log/mspreports
    sudo chown "$USER:$USER" /var/log/mspreports || true
  fi
  
  # Install systemd service if running as root or with sudo
  if [[ -f "$SYSTEMD_DIR/mspreports-embedding.service" ]]; then
    if sudo -n true 2>/dev/null; then
      info "Installing embedding systemd service"
      sudo cp "$SYSTEMD_DIR/mspreports-embedding.service" /etc/systemd/system/
      sudo systemctl daemon-reload
      sudo systemctl enable mspreports-embedding
      info "Start the service with: sudo systemctl start mspreports-embedding"
    else
      warn "Skipping systemd service installation (no sudo). Install manually:"
      warn "  sudo cp $SYSTEMD_DIR/mspreports-embedding.service /etc/systemd/system/"
      warn "  sudo systemctl daemon-reload && sudo systemctl enable --now mspreports-embedding"
    fi
  fi
  
  info "Embedding service setup complete"
  info "Test with: $EMBEDDING_DIR/venv/bin/python $EMBEDDING_DIR/embedding_service.py"
}

main() {
  if [[ "$INSTALL_SYSTEM_DEPS" == true ]]; then
    install_system_deps
  fi

  backend_steps
  frontend_steps

  if [[ "$INSTALL_EMBEDDING" == true ]]; then
    embedding_steps
  fi

  if [[ "$INSTALL_OLLAMA" == true ]]; then
    install_ollama
  fi

  cat <<DONE

Setup complete.

Next steps:
  - Start workers/scheduler: ./start-dev.sh
  - Start backend API:       (cd backend && php artisan serve --port=8000)
  - Start frontend:          (cd frontend && npm run dev)
DONE

  if [[ "$INSTALL_EMBEDDING" == true ]]; then
    cat <<EMBEDDING

Embedding Service:
  - Start manually:  $EMBEDDING_DIR/venv/bin/python $EMBEDDING_DIR/embedding_service.py
  - Start service:   sudo systemctl start mspreports-embedding
  - Service URL:     http://127.0.0.1:5050
EMBEDDING
  fi

  if [[ "$INSTALL_OLLAMA" == true ]]; then
    cat <<OLLAMA

Ollama (LLM Agent):
  - Start Ollama:    ollama serve
  - Test model:      ollama run qwen2.5:7b "Hello"
OLLAMA
  fi

  cat <<URLS

Default URLs:
  - Backend API:        http://localhost:8000
  - Frontend:           http://localhost:5173
  - Embedding Service:  http://localhost:5050
URLS
}

main
