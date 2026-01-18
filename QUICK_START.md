# Quick Start Guide - 5 Minutes to Running

## Prerequisites

| Tool | Version | Download |
|------|---------|----------|
| PHP | 8.3+ | [windows.php.net](https://windows.php.net/download/) (Thread Safe) |
| Composer | 2.x | [getcomposer.org](https://getcomposer.org/download/) |
| Node.js | 20+ LTS | [nodejs.org](https://nodejs.org/) |
| PostgreSQL | 16+ | [postgresql.org](https://www.postgresql.org/download/) |

**Optional (for full features):**
- TimescaleDB 2.x - Time-series optimization
- Redis/Memurai - Better caching/queues
- Docker - For Gotenberg PDF generation

---

## Windows Setup (Native)

### Automated Setup

```powershell
git clone https://gitlab.athenanetworks.com.au/athenanetworks/reporting.git
cd reporting/scripts
.\setup-local.ps1
```

### Manual Setup

```powershell
# 1. Clone repository
git clone https://gitlab.athenanetworks.com.au/athenanetworks/reporting.git
cd reporting

# 2. Create database
$env:PGPASSWORD = "postgres"
createdb -U postgres -h 127.0.0.1 reporting

# 3. Backend setup
cd backend
Copy-Item .env.example .env
# Edit .env: DB_CONNECTION=pgsql, DB_HOST=127.0.0.1, DB_DATABASE=reporting
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve --host=0.0.0.0 --port=8000

# 4. Frontend setup (new terminal)
cd frontend
Copy-Item .env.example .env
npm install
npm run dev
```

---

## Linux/macOS Setup

```bash
git clone https://gitlab.athenanetworks.com.au/athenanetworks/reporting.git
cd reporting
./scripts/setup-local.sh
./scripts/start-dev.sh
```

---

## Access Your Application

| Service | URL |
|---------|-----|
| 🎨 Frontend | http://localhost:5173 |
| 🔐 Login Page | http://localhost:5173/login |
| 🚀 Backend API | http://localhost:8000/api |

### Default Login Credentials

After running `php artisan db:seed`:

| User | Email | Password | Role |
|------|-------|----------|------|
| Admin | `admin@tts.com.au` | `ChangeMe123!` | Full Administrator |
| Test User | `kristianbas14@gmail.com` | `password123` | Administrator |
| Viewer | `viewer@tts.com.au` | `password123` | Scoped Viewer |

> ⚠️ **Change default passwords after first login!**

---

## Daily Development Commands

### Windows (PowerShell)

```powershell
# Start backend
cd backend
php artisan serve --host=0.0.0.0 --port=8000

# Start frontend (new terminal)
cd frontend
npm run dev

# Start queue worker (new terminal)
cd backend
php artisan queue:work
```

### Linux/macOS

```bash
./scripts/start-dev.sh
```

---

## Optional Services

```powershell
# Gotenberg (PDF generation) - requires Docker
docker run -d -p 3000:3000 gotenberg/gotenberg:8

# Enable TimescaleDB (after installation)
psql -U postgres -d reporting -c "CREATE EXTENSION IF NOT EXISTS timescaledb CASCADE;"
```

---

## Troubleshooting

**Database connection failed?**
- Ensure PostgreSQL service is running
- Check `.env` has correct DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD

**Frontend can't reach API?**
- Check backend is running on port 8000
- Check `frontend/.env` has `VITE_API_BASE_URL=http://localhost:8000/api`

**Migrations fail?**
```powershell
php artisan migrate:fresh --seed  # ⚠️ Wipes database
```

---

## Need More Help?

- **Full README**: See `README.md`
- **Architecture**: See `PROJECT_PLAN.md`
- **Deployment**: See `docs/DEPLOYMENT_NATIVE.md`
- **Contributing**: See `CONTRIBUTING.md`

---

## Context7 (AI Documentation)

For AI-assisted development with up-to-date docs:

1. Visit https://context7.com/ and create account
2. Generate API key
3. Add to Windsurf MCP config

---

**Ready to code!** 🚀
