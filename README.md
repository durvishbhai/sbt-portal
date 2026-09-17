# SBT Portal

Secure digital platform for **Super Boys Team** — team operations, member
roles, competitions, courses, elections, document verification, complaints,
an SBT Coins wallet and internal messaging, all in one place. Built with
plain **PHP 8 + MySQL/MariaDB (PDO)** so it runs on standard shared hosting
(cPanel) with no framework or Composer dependency required.

## What's implemented

The must-have feature list from the project requirements is fully working:

- Secure login & 10-role RBAC (session-based, CSRF-protected, password hashed with `password_hash`)
- Team registration & approval workflow
- Secure document vault with upload + admin verification
- Complaint & reporting system (open to members and the public, no login required)
- Election & voting system with one-vote-per-member enforcement and published results
- SBT Coins wallet & transaction tracking
- Internal messaging — direct messages, team announcements, site-wide broadcasts
- Admin panel — user/role management, activity & security logs (CSV export), emergency lockdown

The database schema (`database/schema.sql`) also includes tables for the
remaining custom modules from the requirements doc (ACF competitions,
courses/certificates, Husmukh Star rewards) so those can be built out next
without any schema changes.

## Requirements

- PHP 8.1+ with `pdo_mysql`
- MySQL 5.7+ or MariaDB 10.4+
- Any standard Apache/LiteSpeed shared hosting (cPanel) works — no shell access needed

## Deploying on shared hosting

1. Upload everything in this repo to your hosting account (e.g. `public_html/`).
2. Create a MySQL database and user in cPanel, and grant that user full privileges on it.
3. Import `database/schema.sql` via phpMyAdmin (or `mysql` CLI if you have it).
4. **Never put real credentials in `config/config.php` — it's committed to git.**
   Copy `config/config.local.example.php` to `config/config.local.php` (already
   git-ignored — see `.gitignore`) and fill in your real `SBT_DB_NAME`,
   `SBT_DB_USER`, `SBT_DB_PASS` and `SBT_APP_URL` there. `config.php` loads it
   automatically if it exists. If your host offers a proper environment-variable
   panel, set those same `SBT_DB_*` / `SBT_APP_URL` variables there instead and
   skip `config.local.php` entirely.
5. Leave `APP_DEBUG` off in production (it defaults to off unless
   `SBT_APP_DEBUG=1` is set).
6. Visit `https://yourdomain.com/install.php` once — this creates the first
   Main Team Maker (MTM) administrator account. The wizard refuses to run again
   once any user exists, so delete or leave `install.php` in place either way.
7. Log in at `/login.php` with the account you just created.

**If you ever commit real credentials by mistake** (even temporarily), treat
them as compromised: rotate the database password right away. Removing the
secret from the latest commit does not remove it from git history — anyone
with read access to the repo can still see it in an older commit.

New members who self-register via `/register.php` land with `status = pending`
until an MTM / TM / System Administrator activates them from **Admin → Manage
Users**. Team registrations similarly stay `pending` until approved on the
**Teams** page.

## Local development

```bash
php -S localhost:8000
```

Point it at a local MySQL/MariaDB instance by setting environment variables
before starting the server (or hard-coding them in `config/config.php`):

```bash
export SBT_DB_HOST=127.0.0.1
export SBT_DB_NAME=sbt_portal
export SBT_DB_USER=root
export SBT_DB_PASS=secret
export SBT_APP_DEBUG=1
php -S localhost:8000
```

Then import `database/schema.sql` into that database and open
`http://localhost:8000/install.php`.

## Project structure

```
config/          DB + app configuration (protected from direct web access)
includes/        Shared PHP: db connection, auth/RBAC, helpers, header/footer
database/        schema.sql — the full MySQL schema
assets/          CSS, JS, and the SBT logo
admin/           Admin-only pages: users, activity/security logs, settings
uploads/         Member-uploaded documents (never committed, see .gitignore)
*.php (root)     One file per page — landing, auth, dashboard, teams,
                 documents, complaints, elections, wallet, messaging
```

## Security notes

- All database queries use PDO prepared statements.
- Every state-changing form is protected by a per-session CSRF token.
- Passwords are hashed with PHP's `password_hash()` (bcrypt).
- Uploaded documents are validated by extension **and** MIME type, capped at
  5MB, stored outside of web-executable reach for PHP (`uploads/.htaccess`
  blocks `.php` execution), and served as static files.
- `config/`, `includes/`, and `database/` are blocked from direct browser
  access via `.htaccess` — make sure your host has `AllowOverride All` (or
  equivalent) enabled so these take effect, or replicate the same
  `Require all denied` rules in your host's own web server config if you're
  not on Apache.
- An **emergency lockdown** switch (Admin → Settings, MTM/System Administrator
  only) puts the whole portal into read-only mode for everyone except admins.

## Note on the logo

`assets/img/logo.svg` is a hand-built recreation of the Super Boys Team badge
(black circle, green wings, "TEAM / Super Boys" lettering) — the original
image file sent in chat couldn't be extracted as a local file for this build.
Swap in the real logo file at that path (keeping the filename, or updating
the `<img>` references in `includes/header.php`, `includes/footer.php` and
`index.php`) whenever you have the source asset.
