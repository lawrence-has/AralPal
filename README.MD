# AralPal — Tutoring Platform

AralPal is a PHP-based tutoring platform where students discover and book sessions with tutors, manage active subscriptions, and access structured learning resources. Tutors list their availability, accept or decline booking requests, and track earnings. The platform enforces per-plan booking limits and feature access, processes subscription payments through PayMongo, and pulls learning content from an optional Strapi CMS backend.

---

## System Requirements

| Requirement | Version |
|---|---|
| PHP | 8.0 or higher |
| PHP extensions | PDO, PDO_MySQL, mbstring, openssl, curl |
| MySQL | 8.0+ or MariaDB 10.5+ |
| Web server | Apache or Nginx (Laragon recommended for Windows local dev) |
| Composer | Required — PHPMailer is already installed under `vendor/` |
| Node.js | **Not required** — the project uses vanilla CSS and JavaScript |

---

## Quick Start (Local — Laragon)

1. **Place the project** at `C:\laragon\www\AralPal` (clone or copy).
2. **Create the database** — open phpMyAdmin (or the MySQL client) and create a database named `aralpal`.
3. **Configure environment** — copy `.env.example` to `.env` and fill in real values (see [Environment Variables](#environment-variables-env) below).
4. **Import the schema** — run once via phpMyAdmin, or from a terminal:
   ```bash
   mysql -u root aralpal < database/full_schema.sql
   ```
5. **Seed default data** — visit `http://localhost/AralPal/setup.php` in your browser. This script runs once and creates the default subscription plans and other seed records.
6. **Open the site** — visit `http://localhost/AralPal/`.

---

## Environment Variables (`.env`)

Copy `.env.example` to `.env` and replace every placeholder with a real value. The `.env` file is excluded from Git (see `.gitignore`) — **never commit real credentials**.

| Variable | Description | Example |
|---|---|---|
| `STRAPI_URL` | Base URL of your Strapi CMS instance | `http://localhost:1337` |
| `STRAPI_TOKEN` | Strapi API token for authenticated content requests | `abc123...` |
| `PAYMONGO_SECRET_KEY` | PayMongo secret key (server-side only, never expose in frontend) | `sk_test_...` |
| `PAYMONGO_PUBLIC_KEY` | PayMongo public key (safe for frontend checkout initialization) | `pk_test_...` |
| `PAYMONGO_WEBHOOK_SECRET` | Signing secret for verifying PayMongo webhook payloads | `whsk_...` |

> **Note:** The database connection (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`) and `SITE_URL` are currently set as constants in `includes/config.php`. Edit that file directly for local overrides, or move them to `.env` before deploying to production.

---

## Admin Account Setup

After running `setup.php`, create the first admin user in one of two ways:

**Option A — setup-admin.php (if present):**
Visit `http://localhost/AralPal/setup-admin.php` (available if the file exists in the project root).

**Option B — direct SQL insert:**

1. Generate a bcrypt hash for your chosen password by running this in a terminal:
   ```bash
   php -r "echo password_hash('yourpassword', PASSWORD_DEFAULT);"
   ```
2. Insert the admin user:
   ```sql
   INSERT INTO users (full_name, email, password, role_name, status)
   VALUES ('Admin', 'admin@example.com', '<bcrypt hash>', 'Admin', 'active');
   ```

---

## PayMongo Integration

AralPal uses [PayMongo](https://dashboard.paymongo.com) for subscription payments.

**Required `.env` keys:** `PAYMONGO_SECRET_KEY`, `PAYMONGO_PUBLIC_KEY`, `PAYMONGO_WEBHOOK_SECRET`

**Setup steps:**
1. Log in at [https://dashboard.paymongo.com](https://dashboard.paymongo.com) → Developers → API Keys to retrieve your keys.
2. Register a webhook pointing to:
   ```
   https://yourdomain.com/AralPal/subscriptions/webhook.php
   ```
3. Subscribe the webhook to the `payment.paid` event.
4. Copy the webhook signing secret from the PayMongo dashboard into `PAYMONGO_WEBHOOK_SECRET`.

**Local testing:**
PayMongo must be able to reach `webhook.php` over the public internet. Use a tunnel such as [ngrok](https://ngrok.com) to expose your local server:
```bash
ngrok http 80
```
Register the resulting `https://` URL as the webhook in the PayMongo dashboard.

**Test card (test mode):**
- Card number: `4343434343434343`
- CVV: `123`
- Expiry: any future date

---

## Email (PHPMailer) Configuration

PHPMailer is installed under `vendor/phpmailer/`. Email is used for registration confirmation, booking notifications, and password-related messages.

Configure SMTP credentials directly in `includes/config.php` or by adding mail variables to `.env` and reading them with `getenv()`. The `.env.example` file does not currently define mail variables; add them to your own `.env` as needed (e.g. `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM`).

**Recommended local mail tools:**
- [Mailtrap](https://mailtrap.io) — captures outgoing mail without delivering it; free tier available.
- Laragon's built-in MailHog — available at `http://localhost:8025` when Laragon's mail service is running.

---

## Project Structure

```
AralPal/
├── admin/              # Admin panel pages and actions
├── assets/             # CSS, JavaScript, images, fonts
├── auth/               # Login, registration, logout
├── booking/            # Booking request, acceptance, cancellation
├── dashboard/          # Student and tutor dashboard pages
├── database/           # full_schema.sql and migration scripts
├── includes/           # config.php, DB helpers, shared partials
├── notifications/      # Notification list and mark-read handlers
├── pages/              # Public-facing pages (home, plans, about)
├── reviews/            # Tutor review submission and display
├── subscriptions/      # Plan pages, checkout, webhook handler
├── tutors/             # Tutor profiles, availability, search
├── vendor/             # Composer dependencies (PHPMailer, etc.)
└── .env.example        # Environment variable template
```

---

## Production Deployment Checklist

- [ ] Set `APP_ENV=production` in `.env` or the server environment.
- [ ] Set `display_errors = Off` in `php.ini`; enable `log_errors = On` with a writable log path.
- [ ] Serve the site over HTTPS; ensure `secure=true` is effective for session cookies (the config already detects HTTPS automatically).
- [ ] Set strong, unique values for all `.env` secrets — especially `PAYMONGO_SECRET_KEY`, `PAYMONGO_WEBHOOK_SECRET`, and any mail credentials.
- [ ] Point the webroot to the project root. Protect sensitive paths from direct HTTP access via `.htaccess` or Nginx rules:
  - `/.env` → return 403
  - `/database/` → return 403
  - `/vendor/` → return 403
- [ ] Import `database/full_schema.sql` on the production database (take a full backup first if any data already exists).
- [ ] Visit `/setup.php` once on first deploy to seed default data, then **remove or restrict access to `setup.php`** so it cannot be re-run.
- [ ] Remove or restrict any other debug or one-time scripts (`setup-admin.php`, etc.) after first use.
- [ ] Set file and directory permissions:
  - Directories: `755`
  - Files: `644`
  - `.env`: `600` (owner read/write only)
- [ ] Register the production webhook URL in the PayMongo dashboard and update `PAYMONGO_WEBHOOK_SECRET`.
- [ ] Configure a server cron job if subscription expiry needs to be processed automatically, for example:
  ```
  0 0 * * * php /path/to/AralPal/subscriptions/expire_subscriptions.php
  ```

---

## Database Notes

- **Full schema:** `database/full_schema.sql`
- Re-importing this file **drops and recreates all tables**. Do not run it on a production database that holds live data unless you have a verified backup.
- Key tables:

| Table | Purpose |
|---|---|
| `users` | All accounts (students, tutors, admins) |
| `tutors` | Tutor profiles and metadata |
| `subjects` | Subject catalogue |
| `bookings` | Booking requests and status |
| `subscription_plans` | Available plan definitions |
| `subscriptions` | Active and historical user subscriptions |
| `payments` | Payment records linked to subscriptions |
| `notifications` | In-app notification queue |
| `reviews` | Student reviews of tutors |
| `lesson_progress` | Per-student lesson completion tracking |
| `quiz_attempts` | Quiz results and scores |
| `favorites` | Student-saved tutor favourites |

---

## Contributing / License

This project is under active development. Contribution guidelines and a license file will be added before the first public release. For now, fork the repository and open a pull request with a clear description of your change.
