# Moodle 5.1 — Docker Compose for Coolify

Production-ready Moodle 5.1.3 deployment using Docker Compose, designed to run on [Coolify](https://coolify.io) with Traefik SSL termination.

Moodle core is **not committed to this repo** — it is pulled directly from the official Moodle GitHub mirror at build time, following the [Moodle Git for Administrators](https://docs.moodle.org/501/en/Git_for_Administrators) guide.

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
| tmpfs @ `/var/cache/moodle_localcache` | RAM-backed Mustache/template cache (512 MB, cleared on restart) |

---

## Repository Structure

```
moodle/
├── Dockerfile              # PHP-FPM image — inherits pre-built base, clones Moodle v5.1.3
├── Dockerfile.base         # Base image — compiles all PHP extensions (build once, reuse forever)
├── Dockerfile.nginx        # Nginx image — bakes nginx.conf into the image
├── docker-compose.yml      # All services: nginx, php, postgres, redis, cron
├── config.php              # Moodle config — reads all values from env vars
├── nginx.conf              # Nginx config — serves Moodle 5.1 public/ subdir
├── docker-entrypoint.sh    # Entrypoint — fixes permissions and syncs config on startup
├── cron/
│   └── moodle-cron.sh      # Runs php admin/cli/cron.php every 60 seconds
├── docs/
│   └── dual-access-setup.md  # Optional: LAN offline fallback via Pi-hole + BIND9
├── .env.example            # Template for required environment variables
├── .gitignore
└── README.md
```

---

## How This Repo Works

```
GitHub repo (this repo)
   │
   ├── Dockerfile.base  ──build once──▶  Docker Hub (candra003/moodle-php-base:8.3)
   │                                          │
   └── Dockerfile       ──pulls FROM──────────┘
        + clones Moodle v5.1.3 from GitHub
        + copies config.php, entrypoint, cron script
             │
             ▼
        Coolify pulls this repo → builds image → deploys all containers
```

**The base image** (`candra003/moodle-php-base:8.3`) is publicly available on Docker Hub.
It contains all compiled PHP extensions (`intl`, `gd`, `pgsql`, `sodium`, `redis` via PECL, etc.).
The `Dockerfile` inherits from it so Coolify builds complete in ~30 seconds instead of 5+ minutes.

### Option A — Use the existing public base image (quickest)

The `Dockerfile` already points to `candra003/moodle-php-base:8.3` which is public.
You can deploy immediately without building anything extra — just fork, set env vars, deploy.

### Option B — Build your own base image (recommended for long-term ownership)

If you want full control or plan to customise the PHP extensions:

```bash
# On your server or locally
git clone https://github.com/YOUR_USERNAME/moodle.git
cd moodle

# Build (takes 3–5 min, one-time only)
docker build -f Dockerfile.base -t YOUR_DOCKERHUB_USERNAME/moodle-php-base:8.3 .

# Push to Docker Hub
docker login
docker push YOUR_DOCKERHUB_USERNAME/moodle-php-base:8.3
```

Then update `Dockerfile` line 7:
```dockerfile
FROM YOUR_DOCKERHUB_USERNAME/moodle-php-base:8.3
```

> **Rebuild required** after this update — OPcache and JIT settings were added to `Dockerfile.base`. Run the build + push above, then redeploy in Coolify.
>
> You only need to rebuild the base image when changing PHP extensions or PHP tuning settings in `Dockerfile.base`.
> All other changes (Moodle version, config, nginx) use the fast `Dockerfile` only.

---

## Deploying on Coolify

### Step 1 — Add New Resource

1. Open Coolify → your **Project** → **+ New Resource**
2. Select **Git Repository (Public or Private)**
3. Paste your repo URL
4. Set **Branch** to `main`
5. Set **Build Pack** to **Docker Compose**
6. Set **Docker Compose Location** to `/docker-compose.yml`

### Step 2 — Set Environment Variables

Go to the **Environment Variables** tab and add the following. Do **not** commit real values — enter them in Coolify's UI.

| Variable | Example Value | Notes |
|---|---|---|
| `MOODLE_URL` | `https://moodle.yourdomain.com` | Must match your Coolify domain. Include `https://`. |
| `MOODLE_DB_PASSWORD` | `SuperSecurePassword123!` | Use a strong password. |
| `MOODLE_DB_NAME` | `moodle` | Can leave as default |
| `MOODLE_DB_USER` | `moodle` | Can leave as default |
| `MOODLE_LOCAL_URL` | `https://moodle.yourdomain.com` | Optional — for LAN offline access. See `docs/dual-access-setup.md`. |
| `MOODLE_LOCAL_HOST` | `moodle.yourdomain.com` | Optional — hostname only, no protocol. |
| `MOODLE_UPGRADEKEY` | `some-random-secret` | **Recommended** — blocks public access to `/admin/upgrade.php`. Set to any random string. |

### Step 3 — Assign Domain

1. Go to the **moodle-web** service inside your Coolify deployment
2. Set the domain to match `MOODLE_URL` (e.g. `moodle.yourdomain.com`)
3. Enable **HTTPS** (Coolify/Traefik handles the certificate automatically)

### Step 4 — Deploy

Click **Deploy**. Coolify will:
1. Pull the pre-built PHP base image (~5 seconds)
2. Clone Moodle v5.1.3 + copy configs (~30 seconds)
3. Start all services: nginx, php-fpm, postgres, redis, cron
4. Assign your domain and provision the SSL certificate

### Step 5 — Initialize the Moodle Database

After the first deploy, the database is empty. You must run the installer once.

1. In Coolify, click on the **moodle-php** service → **Terminal** tab
2. Run:

```bash
php admin/cli/install_database.php --lang=en --adminuser=admin --adminpass='YourAdminPassword123!' --adminemail=admin@yourdomain.com --agree-license --fullname="Your School Moodle" --shortname="Moodle"
```

> **Password requirements:** must contain uppercase, lowercase, number, and special character.

3. Wait for the `Success` message (usually 1–2 minutes)
4. Visit your domain — Moodle should be live

---

## Post-Install Configuration

### Configure Quiz for High Concurrency (Exam use)

1. Go to **Site administration → Plugins → Activity modules → Quiz**
2. Set **Autosave period** to `5 minutes` (default 1 min hammers the DB with many users)
3. Ensure quizzes use **5–10 questions per page** to reduce load

---

## Updating Moodle (e.g. v5.1.3 → v5.1.4)

Moodle is pinned to a specific Git tag in the `Dockerfile`. To upgrade:

1. Edit `Dockerfile` — change the branch tag:
   ```dockerfile
   --branch v5.1.4
   ```
2. Push the change to your Git repo
3. In Coolify, click **Redeploy**
4. Once containers are running, exec into `moodle-php` and run:
   ```bash
   php admin/cli/upgrade.php --non-interactive
   ```

> Always take a **database backup** before upgrading.

---

## Backup

### Database backup

Exec into `moodle-db` in Coolify terminal:

```bash
pg_dump -U moodle moodle > /tmp/moodle-backup-$(date +%Y%m%d).sql
```

Then copy out with `docker cp` or use Coolify's S3 backup features.

### Moodledata backup

```bash
tar -czf moodledata-backup.tar.gz /var/www/moodledata
```

---

## Performance Tuning

The following optimisations are baked in and active by default:

| Layer | Setting | Effect |
|---|---|---|
| PHP OPcache | `validate_timestamps=0` | Skips file stat() on every request — Docker code never changes at runtime |
| PHP OPcache | JIT `tracing` mode, 128 MB buffer | CPU speedup for Moodle's hot PHP execution paths |
| PHP OPcache | `interned_strings_buffer=32` | Reduces memory allocation for repeated string literals |
| PHP | `realpath_cache_size=4096K`, TTL 600s | Caches resolved file paths — Moodle resolves thousands per request |
| PHP-FPM | `pm.process_idle_timeout=10s` | Frees idle workers to reclaim RAM between request bursts |
| Moodle | `localcachedir` on tmpfs (512 MB RAM) | Mustache/template cache served from RAM instead of disk |
| Moodle | `filelifetime=86400` | Browsers cache static files for 1 day — reduces repeat page-load time |
| Moodle | `X-Accel-Redirect` | nginx serves file downloads directly — PHP-FPM is freed immediately |
| nginx | `open_file_cache max=2000` | Avoids repeated stat()/open() syscalls for static assets |

### Enable Redis Caching (Important for performance)

1. Log in as Admin
2. Go to **Site administration → Plugins → Caching → Configuration**
3. Under **Redis**, click **Add instance**:
   - Server: `moodle-redis:6379`
   - Prefix: `mdl_`
4. Assign the Redis instance to **Application**, **Session**, and **Request** stores

---

## Resource Requirements

Configured for a server with approximately **16 GB RAM** and **8 vCPUs**. Adjust `mem_limit` and `cpus` in `docker-compose.yml` for your server:

| Service | Default RAM | Default CPU |
|---|---|---|
| moodle-web | 512 MB | 1.0 |
| moodle-php | 8 GB | 4.0 |
| moodle-cron | 1 GB | 1.0 |
| moodle-redis | 2.5 GB | 1.0 |
| moodle-db | 4 GB | 2.0 |

---

## Troubleshooting

**Dashboard loads but content never appears (infinite skeleton)**
Nginx location order bug — the `.js` static rule was intercepting PHP-generated JS URLs (`/lib/javascript.php/...`) and returning 404 before PHP could handle them. This is already fixed in this repo: the PHP location block comes before static asset rules in `nginx.conf`.

**Build times out in Coolify**
Coolify's default build timeout may be too short for PHP extension compilation. Build the base image separately (`Dockerfile.base`) and push to Docker Hub. Subsequent deploys skip compilation entirely.

**"Invalid permissions detected when trying to create a directory" / "Failed opening required localcache/mustache/..."**
Fixed in this release. Root causes were:
1. `directorypermissions = 0750` — too restrictive for CLI cron. Now `02777` (Moodle official default with setgid bit).
2. `localcachedir` was not set — Moodle tried to auto-create it in a non-writable path.
3. Subdirectories (`temp/`, `cache/`, `localcache/`, `sessions/`, `backuptemp/`) were not pre-created.

The `docker-entrypoint.sh` now pre-creates all required subdirectories on every startup. If the error persists on an already-running container before redeploy:
```bash
docker exec <moodle-php-container> chown -R www-data:www-data /var/www/moodledata
docker exec <moodle-php-container> chmod -R 02777 /var/www/moodledata
```

**Moodle shows wrong URL or redirects to http://**
Ensure `MOODLE_URL` exactly matches the domain assigned in Coolify (with `https://`). The `config.php` sets `$CFG->sslproxy = true` to handle Traefik's SSL termination.

**502 Bad Gateway**
The `moodle-php` container may still be starting. Wait 30–60 seconds and refresh. Check the `moodle-php` container logs in Coolify.

**Cron not running**
Check the `moodle-cron` container logs in Coolify. If it exited, the DB may not have been ready. Restart the `moodle-cron` service.

**Change URL Address**

1. In Coolify → your deployment → Domains section → update the domain
2. Environment Variables → update `MOODLE_URL` to the new URL (with `https://`)
3. Click **Restart** (not redeploy)
4. In the `moodle-php` terminal, update the database:
```bash
php admin/cli/cfg.php --name=wwwroot --set=https://yournewdomain.com
```

---

## Offline LAN Access (Optional)

See [`docs/dual-access-setup.md`](docs/dual-access-setup.md) for a guide on setting up Pi-hole + BIND9 so Moodle remains accessible on your local network when the internet is down.
