# Moodle 5.1 — Docker Compose for Coolify

Production-ready Moodle 5.1.3 deployment using Docker Compose, designed to run on [Coolify](https://coolify.io).

Moodle core is **not committed to this repo** — it is pulled directly from the official Moodle Git repository at build time, following the [Moodle Git for Administrators](https://docs.moodle.org/501/en/Git_for_Administrators) guide.

---

## Architecture

```
Internet
   ↓
Coolify (Traefik) — SSL termination, domain routing
   ↓
moodle-web (nginx:alpine) — static files, reverse proxy
   ↓
moodle-php (PHP 8.3-FPM) — Moodle application
   ├── moodle-db (PostgreSQL 16) — database
   ├── moodle-redis (Redis 7) — sessions & cache
   └── moodle-cron — background tasks (quiz, grades, notifications)
```

### Volumes

| Volume | Purpose |
|---|---|
| `moodle_app` | Moodle PHP codebase (shared between web, php, cron containers) |
| `moodledata` | User uploads, session files, temp data (persistent) |
| `moodle_db_data` | PostgreSQL database files (persistent) |

---

## Repository Structure

```
moodle/
├── Dockerfile              # Builds PHP-FPM image, clones Moodle v5.1.3
├── docker-compose.yml      # All services: nginx, php, postgres, redis, cron
├── config.php              # Moodle config — reads all values from env vars
├── nginx.conf              # Nginx config — serves Moodle 5.1 public/ subdir
├── cron/
│   └── moodle-cron.sh      # Runs php admin/cli/cron.php every 60 seconds
├── .env.example            # Template for required environment variables
├── .gitignore
└── README.md
```

---

## Deploying on Coolify

### Step 1 — Add New Resource

1. Open Coolify → your **Project** → **+ New Resource**
2. Select **Git Repository (Public or Private)**
3. Paste your repo URL: `https://github.com/candra26c/moodle.git`
4. Set **Branch** to `main`
5. Set **Build Pack** to **Docker Compose**
6. Set **Docker Compose Location** to `/docker-compose.yml`

### Step 2 — Set Environment Variables

Go to the **Environment Variables** tab and add the following. Do **not** use the `.env.example` file directly — enter values in Coolify's UI so they stay secret.

| Variable | Example Value | Notes |
|---|---|---|
| `MOODLE_URL` | `https://moodle.yourdomain.com` | Must match the domain you assign in Coolify. Include `https://`. |
| `MOODLE_DB_PASSWORD` | `SuperSecurePassword123!` | Use a strong password. |
| `MOODLE_DB_NAME` | `moodle` | Can leave as default |
| `MOODLE_DB_USER` | `moodle` | Can leave as default |

### Step 3 — Assign Domain

1. Go to the **moodle-web** service inside your Coolify deployment
2. Set the domain to match `MOODLE_URL` (e.g. `moodle.yourdomain.com`)
3. Enable **HTTPS** (Coolify/Traefik handles the certificate automatically)

### Step 4 — Deploy

Click **Deploy**. Coolify will:
1. Build the PHP-FPM image (this takes 3–5 minutes — it clones Moodle v5.1.3 from git.moodle.org)
2. Start all services: nginx, php-fpm, postgres, redis, cron
3. Assign your domain and provision the SSL certificate

### Step 5 — Initialize the Moodle Database

After the first deploy, the database is empty. You must run the installer once.

1. In Coolify, click on the **moodle-php** service
2. Go to the **Terminal** tab (or **Exec** tab)
3. Run:

```bash
php admin/cli/install_database.php \
  --lang=en \
  --adminuser=admin \
  --adminpass='YourAdminPassword123!' \
  --adminemail=admin@yourdomain.com \
  --agree-license \
  --fullname="Your School Moodle" \
  --shortname="Moodle"
```

if multiline command file fail (like showing > when you enter, use one lone command below)

```bash
php admin/cli/install_database.php --lang=en --adminuser=admin --adminpass='YourAdminPassword123!' --adminemail=admin@yourdomain.com --agree-license --fullname="Your School Moodle" --shortname="Moodle"
```

> **Password requirements:** must contain uppercase, lowercase, number, and special character.

4. Wait for the `Success` message (usually 1–2 minutes)
5. Visit your domain — Moodle should be live

---

## Post-Install Configuration

### Enable Redis Caching (Important for performance)

1. Log in as Admin
2. Go to **Site administration → Plugins → Caching → Configuration**
3. Under **Redis**, click **Add instance**:
   - Server: `moodle-redis:6379`
   - Prefix: `mdl_`
4. Assign the Redis instance to **Application**, **Session**, and **Request** stores

### Configure Quiz for High Concurrency (Exam use)

1. Go to **Site administration → Plugins → Activity modules → Quiz**
2. Set **Autosave period** to `5 minutes` (default 1 min hammers the DB with many users)
3. Ensure quizzes use **5–10 questions per page** to reduce load

---

## Updating Moodle (e.g. v5.1.3 → v5.1.4)

Moodle is pinned to a specific Git tag in the `Dockerfile`. To upgrade:

1. Edit `Dockerfile` — change the tag:
   ```dockerfile
   # Before
   RUN git clone --depth 1 --branch v5.1.3 https://github.com/moodle/moodle.git ...
   
   # After
   RUN git clone --depth 1 --branch v5.1.4 https://github.com/moodle/moodle.git ...
   ```
2. Push the change to your Git repo
3. In Coolify, click **Force Rebuild & Deploy**
4. Once containers are running, exec into `moodle-php` and run:
   ```bash
   php admin/cli/upgrade.php --non-interactive
   ```

> Always take a **database backup** before upgrading. See backup section below.

---

## Backup

### Database backup

Exec into `moodle-db` container in Coolify terminal:

```bash
pg_dump -U moodle moodle > /tmp/moodle-backup-$(date +%Y%m%d).sql
```

Then copy out with `docker cp` or use Coolify's backup features.

### Moodledata backup

The `moodledata` volume contains all user-uploaded files. Back it up via your server's volume snapshot or:

```bash
tar -czf moodledata-backup.tar.gz /var/www/moodledata
```

---

## Resource Requirements

The `docker-compose.yml` is configured for a server with approximately **16 GB RAM** and **8 vCPUs**. For smaller servers, adjust the `mem_limit` and `cpus` values per service:

| Service | Default RAM | Default CPU |
|---|---|---|
| moodle-web | 512 MB | 1.0 |
| moodle-php | 8 GB | 4.0 |
| moodle-cron | 1 GB | 1.0 |
| moodle-redis | 2.5 GB | 1.0 |
| moodle-db | 4 GB | 2.0 |

---

## Troubleshooting

**Build fails / git clone times out**
The Moodle clone from git.moodle.org can be slow. Try rebuilding. Alternatively, change the clone URL in `Dockerfile` to the GitHub mirror: `https://github.com/moodle/moodle.git`

**Moodle shows wrong URL or redirects to http://**
Ensure `MOODLE_URL` exactly matches the domain assigned in Coolify (with `https://`). The `config.php` sets `$CFG->sslproxy = true` to handle Traefik's SSL termination.

**502 Bad Gateway**
The `moodle-php` container may still be starting. Wait 30–60 seconds and refresh. Check the `moodle-php` container logs in Coolify.

**Database connection refused on first deploy**
PostgreSQL takes ~15 seconds to initialize on first run. The `moodle-php` container has a health check dependency — it will wait. If it fails, restart the `moodle-php` service from Coolify.

**Cron not running**
Check the `moodle-cron` container logs in Coolify. If it exited, the DB may not have been ready when it started. Restart the `moodle-cron` service.
