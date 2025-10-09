# Quick Start Guide - 5 Minutes to Running

## Context7
- Visit https://context7.com/
- Click Sign In
- Create an account (its free)
- Generate an API key
- Add the MCP to Windsurf similar to below:

```json
{
	"mcpServers": {
		"GitKraken": {
			"args": [
				"mcp",
				"--host=windsurf",
				"--source=gitlens",
				"--scheme=windsurf"
			],
			"command": "/Users/josh/Library/Application Support/Windsurf/User/globalStorage/eamodio.gitlens/gk",
			"type":    "stdio"
		},
		"context7": {
			"command": "npx",
			"args": ["-y", "@upstash/context7-mcp", "--api-key", "ctx7sk-049ecac0-cf31-4844-b92d-440ae4a86fa9"]
		  }	  
	}
}

Context7 will add up to date documentation to Claude, this will ensure its not making mistakes based on old training data. Just type to Claude normally and then at the end add: `use context7` to trigger the MCP.
```

## Multi-Developer Setup (Shared Server)

**If you're on a shared remote desktop server with other developers:**

```bash
# 1. Clone the repository
git clone https://gitlab.athenanetworks.com.au/athenanetworks/reporting.git
cd reporting

# 2. Configure your developer environment (avoids port conflicts)
./setup-dev-environment.sh
# Enter your developer ID when prompted (1-20)

# 3. Run automated setup
make install
```

The script will assign you unique ports to avoid conflicts with other developers.

## Solo Developer Setup (Local Machine)

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

### Solo Developer (Default Ports)

| Service | URL | Purpose |
|---------|-----|---------|
| 🎨 Frontend | http://localhost:5173 | React development server |
| 🚀 API | http://localhost/api | Laravel backend |
| 📧 MailHog | http://localhost:8025 | Test emails |
| 🗄️ pgAdmin | http://localhost:5050 | Database management |
| 🔴 Redis | http://localhost:8081 | Redis management |

### Multi-Developer (Custom Ports)

If you ran `./setup-dev-environment.sh`, your URLs were displayed after setup.

**Example for Developer 1:**
- Frontend: http://localhost:5174
- API: http://localhost:8001/api
- MailHog: http://localhost:8026
- pgAdmin: http://localhost:5051
- Redis Commander: http://localhost:8082

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

**Port conflict errors?**
```bash
# If on shared server, run the setup script
./setup-dev-environment.sh

# Then restart
make down
make up
```

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

- **Multi-Developer Setup**: See `docs/MULTI_DEVELOPER_SETUP.md`
- **Detailed Setup**: See `docs/DOCKER_SETUP.md`
- **Docker Overview**: See `docs/DOCKER_SUMMARY.md`
- **Project Info**: See `README.md`
- **Full Architecture**: See `PROJECT_PLAN.md`

---

**Ready to code!** 🚀
