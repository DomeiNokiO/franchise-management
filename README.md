# Franchise Management

Laravel 11 foundation for a multi-branch franchise system. The project uses MySQL, Docker Compose, AdminLTE 4 via CDN, and Spatie Permission.

## Current foundation

- Secure session login with CSRF protection and session regeneration.
- Roles: `full-owner`, `owner-mitra`, `karyawan-mitra`.
- Branch/user pivot for multi-branch access.
- Centralized company/brand settings.
- Ingredients, branch stock, reorder points, purchase orders, cash transactions, and audit-log schema.
- Middleware that denies central owners access to branch financial routes.
- Dashboard stock scope: central owner sees all branches; mitra users see assigned branches only.
- Production Docker image with PHP 8.3 FPM, Nginx, MySQL 8.4, persistent volumes, and supervisor.
- One-command VPS installer with random secrets, migrations, seed, Nginx reverse proxy, UFW, and optional Let's Encrypt TLS.

## VPS install

Point the domain DNS A record to the VPS first, then run:

```bash
git clone https://github.com/DomeiNokiO/franchise-management.git
cd franchise-management
sudo ./deploy/install.sh app.example.com admin@example.com
```

The installer creates `.env` locally with mode `600`. It does not commit or upload secrets. Use `SKIP_TLS=1` only for testing before DNS is ready. The generated owner password is printed once by the installer; store it in a password manager and rotate it after first login.

## Local development

Requires PHP 8.2+, Composer, Node 20+, Docker, and MySQL. The current Hermes host does not have PHP/Composer/MySQL or a running Docker daemon, so runtime tests must run in Docker or on the target VPS.

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
npm install && npm run build
php artisan serve
```

## Security notes

- Never set `APP_DEBUG=true` in production.
- Never commit `.env`, database dumps, or generated credentials.
- Put the VPS behind DNS and TLS before exposing it publicly.
- Add backups, monitoring, and a second admin account before production use.
- POS, recipe deduction, PO approval/receiving workflows, expenses, and full financial reports are the next implementation slices; the current commit is the secure deployable foundation, not a finished ERP.
