# MSP Reports - Nginx Configuration

Production-ready Nginx configuration for serving the MSP Reports platform.

## Quick Installation

```bash
# Copy config to nginx sites
sudo cp mspreports.conf /etc/nginx/sites-available/mspreports

# Edit the config to set your domain
sudo nano /etc/nginx/sites-available/mspreports
# Change: server_name reports.example.com -> your actual domain

# Enable the site
sudo ln -s /etc/nginx/sites-available/mspreports /etc/nginx/sites-enabled/

# Remove default site (optional)
sudo rm /etc/nginx/sites-enabled/default

# Test configuration
sudo nginx -t

# Reload nginx
sudo systemctl reload nginx
```

## Configuration Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `server_name` | `reports.example.com` | Your domain name |
| `root` | `/var/www/mspreports/backend/public` | Laravel public directory |
| `fastcgi_pass` | `unix:/var/run/php/php8.3-fpm.sock` | PHP-FPM socket |
| `client_max_body_size` | `50M` | Max upload size |

## SSL/HTTPS Setup

### Option 1: Let's Encrypt (Recommended)

```bash
# Install certbot
sudo apt install certbot python3-certbot-nginx

# Get certificate (will auto-configure nginx)
sudo certbot --nginx -d reports.yourcompany.com.au

# Auto-renewal is configured automatically
# Test renewal: sudo certbot renew --dry-run
```

### Option 2: Manual Certificate

1. Uncomment the HTTPS server block in `mspreports.conf`
2. Update certificate paths to your cert files
3. Reload nginx: `sudo systemctl reload nginx`

## Architecture

```
                    ┌─────────────────────────────────────────┐
                    │              Nginx                       │
                    │  (Reverse Proxy + Static File Server)   │
                    └─────────────────────────────────────────┘
                                      │
            ┌─────────────────────────┼─────────────────────────┐
            │                         │                         │
            ▼                         ▼                         ▼
    ┌───────────────┐       ┌───────────────┐       ┌───────────────┐
    │   /api/*      │       │   /storage/*  │       │   /* (SPA)    │
    │   /sanctum/*  │       │  Static Files │       │  React App    │
    │   /saml/*     │       │               │       │  index.html   │
    └───────────────┘       └───────────────┘       └───────────────┘
            │
            ▼
    ┌───────────────┐
    │   PHP-FPM     │
    │   Laravel     │
    └───────────────┘
```

## Routing

| Path | Handler | Description |
|------|---------|-------------|
| `/api/*` | Laravel | REST API endpoints |
| `/sanctum/*` | Laravel | CSRF cookie endpoint |
| `/saml/*` | Laravel | SAML authentication |
| `/oauth/*` | Laravel | OAuth callbacks |
| `/storage/*` | Static | Public file storage |
| `/*` | React SPA | Frontend application |

## Troubleshooting

### 502 Bad Gateway
- Check PHP-FPM is running: `systemctl status php8.3-fpm`
- Verify socket path matches: `ls -la /var/run/php/`

### 404 on API routes
- Ensure `try_files` directive includes `index.php`
- Check Laravel `.htaccess` isn't conflicting

### Large file upload fails
- Increase `client_max_body_size` in nginx config
- Also check `upload_max_filesize` and `post_max_size` in `php.ini`

### Slow report generation
- Increase `fastcgi_read_timeout` (default 300s)
- Check PHP-FPM pool settings

## Security Notes

- All `.env` and `.git` files are blocked
- Security headers are set (X-Frame-Options, X-Content-Type-Options, etc.)
- HSTS should be enabled after confirming HTTPS works
