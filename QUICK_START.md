# Quick Start Guide - 5 Minutes to Running

## Prerequisites Check
- [ ] Docker Desktop installed and running
- [ ] Git installed
- [ ] 8GB+ RAM allocated to Docker

## Setup (First Time Only)

```bash
# 1. Clone the repository
git clone https://gitlab.athenanetworks.com.au/athenanetworks/reporting.git
cd reporting

# 2. Run automated setup
make install
```

That's it! The `make install` command will:
- Start all Docker services
- Install Composer dependencies
- Copy `.env.example` to `.env`
- Generate application key
- Run database migrations
- Install frontend dependencies

## Access Your Application

| Service | URL | Purpose |
|---------|-----|---------|
| 🎨 Frontend | http://localhost:5173 | React development server |
| 🚀 API | http://localhost/api | Laravel backend |
| 📧 MailHog | http://localhost:8025 | Test emails |
| 🗄️ pgAdmin | http://localhost:5050 | Database management |
| 🔴 Redis | http://localhost:8081 | Redis management |

**pgAdmin Login**: admin@athenanetworks.com.au / admin

## Daily Development Commands

```bash
# Start working
make up              # Start all services
make logs            # View logs (Ctrl+C to exit)

# Common tasks
make shell           # Access app container
make migrate         # Run new migrations
make artisan CMD=... # Run any artisan command
make cache-clear     # Clear all caches

# When done
make down            # Stop all services
```

## Troubleshooting

**Services won't start?**
```bash
make down
make up
make logs
```

**Database issues?**
```bash
make migrate
make db-shell  # Access PostgreSQL directly
```

**Cache issues?**
```bash
make cache-clear
make restart
```

**Need fresh start?**
```bash
make fresh  # ⚠️ Wipes database and reseeds
```

## Full Command Reference

Run `make help` to see all available commands.

## Need More Help?

- **Detailed Setup**: See `DOCKER_SETUP.md`
- **Docker Overview**: See `DOCKER_SUMMARY.md`
- **Project Info**: See `README.md`
- **Full Architecture**: See `PROJECT_PLAN.md`

---

**Ready to code!** 🚀
