# Quick Start Guide

Get up and running with the Multi-Tenant Reporting Platform in minutes!

## Prerequisites

Choose one:
- **Option A**: Docker & Docker Compose (Recommended)
- **Option B**: Manual installation (PHP 8.2+, Node.js 18+, PostgreSQL 16+)

## Installation

### Option A: Using Docker (Recommended)

1. **Clone and Setup**
   ```bash
   git clone https://gitlab.athenanetworks.com.au/athenanetworks/reporting.git
   cd reporting
   
   # Copy environment files
   cp backend/.env.example backend/.env
   cp frontend/.env.example frontend/.env
   ```

2. **Start Services**
   ```bash
   docker-compose up -d
   ```

3. **Install Dependencies**
   ```bash
   # Backend
   docker-compose exec backend composer install
   docker-compose exec backend php artisan key:generate
   docker-compose exec backend php artisan migrate --seed
   
   # Frontend
   docker-compose exec frontend npm install
   ```

4. **Access Application**
   - Frontend: http://localhost:5173
   - Backend API: http://localhost:8000

### Option B: Manual Installation

1. **Backend Setup**
   ```bash
   cd backend
   composer install
   cp .env.example .env
   
   # Update .env with your database credentials
   php artisan key:generate
   php artisan migrate --seed
   php artisan serve
   ```

2. **Frontend Setup** (new terminal)
   ```bash
   cd frontend
   npm install
   npm run dev
   ```

3. **Access Application**
   - Frontend: http://localhost:5173
   - Backend API: http://localhost:8000

## First Login

### Create Your First User

Using Laravel Tinker:
```bash
php artisan tinker

>>> $company = App\Models\Company::create([
    'name' => 'My Company',
    'status' => 'active'
]);

>>> $user = App\Models\User::create([
    'name' => 'Admin User',
    'email' => 'admin@example.com',
    'password' => bcrypt('password'),
    'company_id' => $company->id,
    'permissions' => [
        'integrations' => ['view' => true, 'manage' => true, 'sync' => true],
        'customers' => ['view' => true, 'edit' => true, 'map' => true],
        'reports' => ['view' => true, 'configure' => true, 'generate' => true, 'download' => true],
        'users' => ['view' => true, 'manage' => true],
        'company_settings' => ['view' => true, 'edit' => true]
    ]
]);
```

### Login

1. Navigate to http://localhost:5173/login
2. Email: `admin@example.com`
3. Password: `password`

## Next Steps

### 1. Configure Company Settings

1. Click user avatar → **Settings**
2. Update:
   - Company name
   - Contact information
   - Primary branding color
   - Email settings

### 2. Add Your First Integration

1. Navigate to **Integrations**
2. Click **Add New Integration**
3. Select integration type (e.g., Autotask)
4. Enter credentials
5. Click **Save & Sync**

### 3. Map Customers

1. Navigate to **Customers**
2. Review unmapped customers (if any)
3. Use auto-match suggestions or manually map

### 4. Configure Report Schedules

1. Navigate to **Customers**
2. Click **Setup Reports** on a customer card
3. Add report types
4. Set frequency and time
5. Configure email destination
6. **Save Configuration**

### 5. Generate First Report

1. On the report configuration page
2. Click **Generate Report Now**
3. Check **Reports** page for generated report
4. Download and verify

## Troubleshooting

### Frontend won't start
```bash
# Clear node modules and reinstall
cd frontend
rm -rf node_modules package-lock.json
npm install
npm run dev
```

### Backend errors
```bash
# Clear cache and config
cd backend
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

### Database connection issues
Check `.env` file:
```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1  # or 'postgres' if using Docker
DB_PORT=5432
DB_DATABASE=reporting
DB_USERNAME=reporting
DB_PASSWORD=secret
```

### Docker issues
```bash
# Restart all containers
docker-compose down
docker-compose up -d

# View logs
docker-compose logs -f
```

## Common Commands

### Development
```bash
# Backend
php artisan serve
php artisan migrate
php artisan test

# Frontend
npm run dev
npm run build
npm run lint
```

### Docker
```bash
# Start/stop
docker-compose up -d
docker-compose down

# View logs
docker-compose logs -f backend
docker-compose logs -f frontend

# Execute commands
docker-compose exec backend php artisan migrate
docker-compose exec frontend npm install
```

## What's Next?

- [Configuration Guide](Configuration) - Detailed configuration options
- [Integration Setup](Integration-Setup) - Configure integrations
- [User Guide](User-Guide) - Learn how to use all features
- [Development Setup](Development-Setup) - Set up for development

## Need Help?

- Check [Troubleshooting Guide](Troubleshooting)
- Review [FAQ](FAQ)
- Email: support@athenanetworks.com.au
- Issues: [GitLab Issues](https://gitlab.athenanetworks.com.au/athenanetworks/reporting/issues)
