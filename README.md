# Dugsi ERP

School ERP for a secondary school in Somaliland (Form 1–Form 4).

## Stack

| Layer | Choice |
|---|---|
| App | Laravel 13 (PHP) + Blade |
| CSS | Vite + Tailwind 4 (tokens match `/design-reference`) |
| Database | MySQL (`dugsierp`) |
| Hosting | Bluehost (Week 13) |
| SMS | Telesom (Week 9 / end) |

## Local setup (Week 0)

### Prerequisites

- PHP 8.2+ with extensions: `pdo_mysql`, `mysqli`, `mbstring`, `openssl`, `curl`, `fileinfo`
- Composer
- Node.js 20+
- MySQL (XAMPP MySQL is fine)

> On this machine, PHP 8.5 had `pdo_mysql` / `mysqli` **commented out** in `php.ini`. Uncomment:
> `extension=pdo_mysql` and `extension=mysqli`, then restart the terminal.
> Until then use the helper: `artisan-mysql.cmd migrate` (same as
> `php -d extension=mysqli -d extension=pdo_mysql artisan …`)

### Install

```bash
composer install
copy .env.example .env   # Windows
php artisan key:generate
php artisan storage:link
# Create DB if needed: mysql -u root -e "CREATE DATABASE dugsierp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan migrate
npm install
npm run build
php artisan serve
```

`storage:link` is required so student photos (and other public uploads) are served from `/storage/...`.

Open http://localhost:8000

### Environment

Copy `.env.example` → `.env`. Important keys:

- `APP_NAME="Dugsi ERP"`
- `DB_CONNECTION=mysql`, `DB_DATABASE=dugsierp`, `DB_USERNAME`, `DB_PASSWORD`
- Telesom placeholders (`TELESOM_SMS_*`) — fill in Week 9

Never commit `.env`.

## Git workflow

Even solo, use branches so Week N work can be rolled back cleanly.

| Branch | Purpose |
|---|---|
| `main` | Stable, deployable baseline |
| `feature/week-N-short-name` | One week/module of work |

### Commit style

```
feat(week1): add login and role middleware
fix(fees): correct sibling discount calculation
chore: update .env.example for Telesom placeholders
```

### Routine

1. `git checkout main && git pull`
2. `git checkout -b feature/week-1-auth`
3. Work → commit often at logical checkpoints
4. Merge to `main` when the week milestone is solid
5. Tag optional: `git tag week-1-done`

Local-only (gitignored): `CONTEXT.md`, `dugsi-erp-weekly-plan.md`, `design-reference/`

## Docs (local)

- `CONTEXT.md` — roles, schema, UI rules (re-read before each feature)
- `dugsi-erp-weekly-plan.md` — weekly build sequence
- `design-reference/` — authoritative Figma UI export

## Status

**Week 5 complete** — Attendance marking, history, print register, absence SMS stub (logged for Week 9).  
Next: **Week 7 — Fees & Finance**.

### Demo logins (password: `password`)

| Role | Email |
|---|---|
| Super Admin | `superadmin@dugsi.edu.sl` |
| Admin | `admin@dugsi.edu.sl` |
| Finance | `finance@dugsi.edu.sl` |
| Teacher | `teacher@dugsi.edu.sl` |

Phone login also works (seeded `+252634000001` … `004`).
