# Rajawali Topup — Deployment Runbook

Production deployment guide for the `admin-dashboard` Laravel application
(the mobile app is distributed separately as the release APK).

---

## 1. Prerequisites

| Requirement | Notes |
| :--- | :--- |
| PHP | 8.4+ with `pdo`, `mbstring`, `openssl`, `ctype`, `fileinfo`, `curl`, `redis` (if Redis is used) |
| Composer | 2.x |
| Node.js | 20+ / npm 10+ (asset build only) |
| Database | MySQL 8 / PostgreSQL 14+ (production). SQLite is local-only. |
| Redis | Recommended for cache, sessions and queues |
| Web server | Nginx or Apache with HTTPS (TLS 1.2+) |
| Supervisor | Process manager for the queue worker |

---

## 2. Production `.env` values

Copy `.env.example` to `.env` and apply the production overrides below.
Everything is documented inline in `.env.example`.

| Key | Production value | Why |
| :--- | :--- | :--- |
| `APP_ENV` | `production` | Disables debug tooling |
| `APP_DEBUG` | `false` | **Never** leak stack traces / secrets |
| `APP_URL` | `https://your-domain` | Absolute URLs, QRIS callbacks |
| `APP_KEY` | generated once | `php artisan key:generate`. **Back it up**: losing it makes stored HRNs undecryptable |
| `LOG_LEVEL` | `warning` (or `error`) | Avoid verbose logs |
| `DB_CONNECTION` | `mysql` / `pgsql` | Real database with backups |
| `CACHE_STORE` | `redis` | Fast cache, shared across processes |
| `SESSION_DRIVER` | `redis` | Shared sessions across workers |
| `QUEUE_CONNECTION` | `redis` | Reliable async redeem jobs |
| `GOOGLE_MOCK` | `false` | **Mock must be off** |
| `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` | real OAuth credentials | Google sign-in |
| `TELKOMSEL_MOCK` | `false` | **Mock must be off** — real voucher redeem |
| `PAYMENT_PROVIDER` | `midtrans` | QRIS payment gateway |
| `MIDTRANS_IS_PRODUCTION` | `true` | `false` = sandbox |
| `MIDTRANS_SERVER_KEY` | `Mid-server-...` | Midtrans server key (Basic auth + signature) |
| `MIDTRANS_CLIENT_KEY` | `Mid-client-...` | Midtrans client key |
| `MIDTRANS_MERCHANT_ID` | `Gxxxx` | Midtrans merchant id |
| `SEED_*_PASSWORD` | strong, unique passwords | Seed admin/operator accounts |

Generate a webhook secret (only needed for non-Midtrans providers):

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

### Midtrans setup

1. Log in to the [Midtrans dashboard](https://dashboard.midtrans.com) (sandbox: `https://dashboard.sandbox.midtrans.com`).
2. Copy **Server Key**, **Client Key** and **Merchant ID** into the matching `MIDTRANS_*` variables.
3. Set **Settings → Configuration → Payment Notification URL** to:
   `https://<your-domain>/api/v1/webhooks/qris`
4. Midtrans does **not** use a shared webhook secret; each callback is
   authenticated with its own `signature_key` (SHA-512 of
   `order_id + status_code + gross_amount + server_key`), which the backend
   recomputes and compares. `PAYMENT_WEBHOOK_SECRET` is therefore unused for Midtrans.
5. QRIS orders are created via the Core API `/v2/charge` endpoint with
   `payment_type=qris`. The mobile app renders the QR from `qris_string`
   (local EMVCo) or `qris_url` (Midtrans-hosted image), and polls order status
   until the callback settles the payment.

---

## 3. Build & release steps

```bash
# 1. Fetch production dependencies only
composer install --no-dev --optimize-autoloader

# 2. Build frontend assets
npm ci
npm run build

# 3. Environment
cp .env.example .env
php artisan key:generate

# 4. Database
php artisan migrate --force

# 5. Seed initial admin/operator accounts (first deploy only)
php artisan db:seed --force

# 6. Warm caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

On each subsequent deploy re-run `composer install --no-dev`, `npm ci && npm run build`,
`php artisan migrate --force`, then `php artisan config:cache route:cache` again.

> **Warning:** `config:cache` freezes the current `.env`. Always rebuild the cache
> after changing any environment value, otherwise the old values stay active.

### Zero-downtime helpers

```bash
php artisan down --render="errors::503"   # maintenance mode
php artisan up
```

---

## 4. Queue worker (Supervisor)

Voucher redemption runs asynchronously via `ProcessVoucherRedeemJob`, so a worker
**must** be running.

`/etc/supervisor/conf.d/rajawali-worker.conf`:

```ini
[program:rajawali-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/rajawali/admin-dashboard/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/rajawali/admin-dashboard/storage/logs/worker.log
stopwaitsecs=3600
```

Apply and start:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start rajawali-worker:*
```

After each deploy, restart workers so they pick up new code:

```bash
php artisan queue:restart
```

### Failed jobs

```bash
php artisan queue:failed
php artisan queue:retry all
```

---

## 5. Scheduler (cron)

Expired unpaid orders are released back to stock by `orders:release-expired`,
registered in `routes/console.php` to run every minute. Drive it with a **single**
cron entry:

```cron
* * * * * cd /var/www/rajawali/admin-dashboard && php artisan schedule:run >> /dev/null 2>&1
```

For high-traffic setups use `schedule:work` under Supervisor instead of cron.

---

## 6. Storage permissions

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod 775 {} \;
sudo find storage bootstrap/cache -type f -exec chmod 664 {} \;
```

`storage/app` also holds uploaded bulk-import CSVs; it must be writable and
excluded from public web access.

---

## 7. HTTPS / SSL

- Terminate TLS at Nginx (Let's Encrypt / managed certificate).
- Redirect all HTTP to HTTPS; set `APP_URL` to the `https://` origin.
- Behind a load balancer, configure `TrustProxies` so generated URLs use HTTPS.
- Enable HSTS and secure cookies (`SESSION_SECURE_COOKIE=true`).
- The Android release build targets `https://api.rajawalitopup.com/api/v1/` — keep
  the certificate valid for that host.

Example Nginx server block (excerpt):

```nginx
server {
    listen 443 ssl http2;
    server_name api.rajawalitopup.com;
    root /var/www/rajawali/admin-dashboard/public;

    ssl_certificate     /etc/letsencrypt/live/api.rajawalitopup.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/api.rajawalitopup.com/privkey.pem;

    index index.php;
    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

---

## 8. Security checklist

- [ ] `APP_DEBUG=false` and `APP_ENV=production`.
- [ ] `GOOGLE_MOCK=false`, `TELKOMSEL_MOCK=false`, `PAYMENT_PROVIDER=midtrans`.
- [ ] `MIDTRANS_IS_PRODUCTION=true` with real server/client keys; notification URL points to `/api/v1/webhooks/qris`.
- [ ] Webhook signature verification enabled (Midtrans `signature_key`); invalid callbacks return 401.
- [ ] `APP_KEY` generated and backed up securely (HRN encryption depends on it).
- [ ] Voucher HRN stays encrypted at rest: never dump the `vouchers` table, and never
      expose `hrn` in API resources, Inertia props, or logs. The regression suite
      `tests/Feature/Security/HrnSecretExposureTest.php` guards this — run it in CI.
- [ ] `SEED_*` default passwords rotated; remove or rename default accounts.
- [ ] Database and Redis bound to private interfaces; strong passwords.
- [ ] Regular encrypted database backups + restore drill.
- [ ] Sanctum tokens / sessions use HTTPS-only and `SESSION_SECURE_COOKIE=true`.
- [ ] Rate limits (`throttle`) intact on auth, order and webhook routes.
- [ ] Dependencies audited: `composer audit` and `npm audit`.
- [ ] Log rotation configured for `storage/logs` and the worker log.
