# Windows Development Environment Requirements

Install the following dependencies **before** running `setup-local.ps1`.

## Required Software

### 1. PHP 8.3+
- **Download:** https://windows.php.net/download/
- Use the **Thread Safe** version (VS16 x64)
- Extract to `C:\php` and add to system PATH

**Required PHP Extensions** (enable in `php.ini`):
```ini
extension=curl
extension=gd
extension=ldap
extension=mbstring
extension=pdo_pgsql
extension=pgsql
extension=zip
extension=bcmath
extension=openssl
extension=fileinfo
```

### 2. Composer
- **Download:** https://getcomposer.org/download/
- Run the installer (Composer-Setup.exe)

### 3. Node.js LTS
- **Download:** https://nodejs.org/
- Use the LTS version (includes npm)

### 4. PostgreSQL 16
- **Download:** https://www.postgresql.org/download/windows/
- Remember the password you set for the `postgres` user
- Add `C:\Program Files\PostgreSQL\16\bin` to system PATH

### 5. TimescaleDB
- **Download:** https://docs.timescale.com/self-hosted/latest/install/installation-windows/
- Run the installer and select your PostgreSQL 16 installation
- Restart PostgreSQL service after installation

### 6. Redis (Memurai)
- **Download:** https://www.memurai.com/
- Install and start the Memurai service
- Alternative: Run Redis in Docker

### 7. Docker Desktop (Optional - for PDF generation)
- **Download:** https://www.docker.com/products/docker-desktop
- Required for Gotenberg PDF generation service

---

## After Installation

1. **Clone the repository:**
   ```powershell
   git clone https://gitlab.athenanetworks.com.au/athenanetworks/reporting.git
   cd reporting
   ```

2. **Run the setup script:**
   ```powershell
   .\setup-local.ps1
   ```

3. **Start development:**
   ```powershell
   # Start queue workers
   .\start-dev.ps1

   # In separate terminals:
   cd backend; php artisan serve --host=0.0.0.0 --port=8000
   cd frontend; npm run dev
   ```

---

## Troubleshooting

### PHP Extensions Missing
If `composer install` fails with missing extensions:
1. Open your `php.ini` file (usually `C:\php\php.ini`)
2. Find and uncomment the required extensions (remove the `;` at the start)
3. Restart your terminal

### PostgreSQL Connection Failed
- Ensure PostgreSQL service is running
- Check the password for the `postgres` user
- Verify `C:\Program Files\PostgreSQL\16\bin` is in your PATH

### Redis/Memurai Not Running
- Open Services (services.msc)
- Find "Memurai" and start the service
