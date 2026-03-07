Step 1: Create Your Git Repository
In your GitHub/GitLab repository, create these four files.
File 1: Dockerfile
Updated to use PHP 8.3 (recommended for Moodle 5.x) and strictly pulls the v5.1.3 Git tag.
code
Dockerfile
FROM php:8.3-fpm

# 1. Install system dependencies required by Moodle 5.1.x
RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libpq-dev libxml2-dev \
    libzip-dev libicu-dev libsodium-dev \
    zip unzip git curl tzdata rsync \
    && rm -rf /var/lib/apt/lists/*

# 2. Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install gd intl pgsql pdo_pgsql zip opcache soap exif sodium

# 3. Install Redis extension for Session/Cache handling
RUN pecl install redis && docker-php-ext-enable redis

# 4. PHP Tuning for 10GB RAM / 6 vCPUs (Burst Setup)
RUN echo "memory_limit = 512M" > /usr/local/etc/php/conf.d/moodle.ini \
    && echo "max_execution_time = 120" >> /usr/local/etc/php/conf.d/moodle.ini \
    && echo "max_input_vars = 5000" >> /usr/local/etc/php/conf.d/moodle.ini \
    && echo "opcache.enable = 1" >> /usr/local/etc/php/conf.d/moodle.ini \
    && echo "opcache.memory_consumption = 1024" >> /usr/local/etc/php/conf.d/moodle.ini \
    && echo "opcache.max_accelerated_files = 10000" >> /usr/local/etc/php/conf.d/moodle.ini

# 5. PHP-FPM High Concurrency Tuning
RUN echo "[www]\npm.max_children = 200\npm.start_servers = 20\npm.min_spare_servers = 20\npm.max_spare_servers = 50\npm.max_requests = 1000" >> /usr/local/etc/php-fpm.d/zz-moodle.conf

# 6. Git clone specific tag (v5.1.3) from official Moodle repository
RUN git clone --depth 1 --branch v5.1.3 git://git.moodle.org/moodle.git /var/www/html \
    && chown -R www-data:www-data /var/www/html

# 7. Inject our dynamic config
COPY config.php /var/www/html/config.php
RUN chown www-data:www-data /var/www/html/config.php

WORKDIR /var/www/html
File 2: docker-compose.yml
Resource limits optimized for 12 vCPU / 24GB RAM.
code
Yaml
version: '3.8'

services:
  moodle-web:
    image: nginx:alpine
    depends_on:
      - moodle-php
    volumes:
      - ./nginx.conf:/etc/nginx/conf.d/default.conf:ro
    ports:
      - "80" # Coolify Traefik handles the HTTPS/domain mapping
    logging:
      driver: "json-file"
      options:
        max-size: "10m"
        max-file: "3"
    deploy:
      resources:
        limits:
          cpus: '1.0'
          memory: 512M

  moodle-php:
    build: .
    volumes:
      - moodledata:/var/www/moodledata
    environment:
      - MOODLE_URL=${MOODLE_URL}
      - MOODLE_DB_HOST=moodle-db
      - MOODLE_DB_NAME=moodle
      - MOODLE_DB_USER=moodle
      - MOODLE_DB_PASSWORD=${MOODLE_DB_PASSWORD}
      - REDIS_HOST=moodle-redis
    logging:
      driver: "json-file"
      options:
        max-size: "20m"
        max-file: "3"
    deploy:
      resources:
        limits:
          cpus: '6.0'    
          memory: 10G    

  moodle-redis:
    image: redis:alpine
    command: redis-server --maxmemory 2gb --maxmemory-policy allkeys-lru
    logging:
      driver: "json-file"
      options:
        max-size: "10m"
        max-file: "3"
    deploy:
      resources:
        limits:
          cpus: '1.0'
          memory: 2.5G

  moodle-db:
    # Upgraded to PG 16 for better JSON/Query performance in Moodle 5.x
    image: postgres:16-alpine 
    environment:
      POSTGRES_DB: moodle
      POSTGRES_USER: moodle
      POSTGRES_PASSWORD: ${MOODLE_DB_PASSWORD}
    volumes:
      - moodle_db_data:/var/lib/postgresql/data
    command: postgres -c max_connections=400 -c shared_buffers=2GB -c work_mem=32MB
    logging:
      driver: "json-file"
      options:
        max-size: "20m"
        max-file: "3"
    deploy:
      resources:
        limits:
          cpus: '3.0'    
          memory: 6G     

volumes:
  moodledata:
  moodle_db_data:
File 3: config.php
Reads environment variables injected by Coolify.
code
PHP
<?php  // Moodle configuration file

unset($CFG);
global $CFG;
$CFG = new stdClass();

$CFG->dbtype    = 'pgsql';
$CFG->dblibrary = 'native';
$CFG->dbhost    = getenv('MOODLE_DB_HOST');
$CFG->dbname    = getenv('MOODLE_DB_NAME');
$CFG->dbuser    = getenv('MOODLE_DB_USER');
$CFG->dbpass    = getenv('MOODLE_DB_PASSWORD');
$CFG->prefix    = 'mdl_';
$CFG->dboptions = array('dbpersist' => 0, 'dbport' => '', 'dbsocket' => '');

$CFG->wwwroot   = getenv('MOODLE_URL');
$CFG->dataroot  = '/var/www/moodledata';
$CFG->admin     = 'admin';
$CFG->directorypermissions = 0777;

// MUST HAVE FOR EXAM WEEK: REDIS SESSIONS
$CFG->session_handler_class = '\core\session\redis';
$CFG->session_redis_host = getenv('REDIS_HOST');
$CFG->session_redis_port = 6379;
$CFG->session_redis_acquire_lock_timeout = 120;

// Tell Moodle it is behind Coolify's Reverse Proxy
$CFG->sslproxy = true;

require_once(__DIR__ . '/lib/setup.php');
File 4: nginx.conf
code
Nginx
server {
    listen 80;
    server_name _;
    root /var/www/html;
    index index.php index.html;

    client_max_body_size 500M;

    location / {
        try_files $uri $uri/ =404;
    }

    location ~ [^/]\.php(/|$) {
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass moodle-php:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;
        fastcgi_read_timeout 300;
    }
}
Step 2: Deploy in Coolify
Open Coolify -> Projects -> Add New Resource.
Select Git Repository and link the repo containing the 4 files above.
Select Docker Compose as your Build Pack.
DO NOT click Deploy yet.
Go to the Environment Variables tab in Coolify and add these two exact variables:
MOODLE_URL = https://exam.yourdomain.com (Must match the domain you intend to use)
MOODLE_DB_PASSWORD = YourSecureDatabasePassword123
Go to the Domains section of your moodle-web container in Coolify and type in https://exam.yourdomain.com.
Click Deploy.
Coolify will pull v5.1.3 directly from git.moodle.org, build the PHP image, and spin up the cluster.
Step 3: Run the Moodle Git CLI Installer
Because you pulled from the official Git repository, the database tables do not exist yet. We must initialize them using Moodle's built-in CLI tool.
In Coolify, click on the moodle-php service.
Go to the Terminal / Exec tab.
Run this command to install the Moodle 5.1.3 database structure:
code
Bash
php admin/cli/install_database.php \
  --lang=en \
  --adminuser=admin \
  --adminpass='ExamSecureAdmin123!' \
  --adminemail=your@email.com \
  --agree-license \
  --fullname="Exam Moodle Server" \
  --shortname="Exams"
(Make sure the password contains an uppercase letter, lowercase letter, number, and special character).
Wait a few minutes. Once it prints Success, your server is live at your URL.
Step 4: The Developer Workflow (Updating to newer Git Tags)
Because we tied the Dockerfile directly to v5.1.3, your environment is locked and stable.
When Moodle 5.1.4 comes out and you want to update following Moodle's Git docs:
Update your Git repository's Dockerfile. Change this line:
RUN git clone --depth 1 --branch v5.1.3 ...
to
RUN git clone --depth 1 --branch v5.1.4 ...
Go into Coolify and click Force Rebuild.
Once the container is running again, go to the Coolify Terminal for moodle-php and run the official database upgrade script:
code
Bash
php admin/cli/upgrade.php --non-interactive
Step 5: Final Exam Checklist (Do not skip!)
Even on Moodle 5.x, the rules for surviving 1,000 concurrent exam-takers remain the same:
Log in as Admin.
Go to Site administration -> Caching -> Configuration, add a Redis cache instance mapped to moodle-redis, and assign it to the Application Cache.
Go to Site administration -> Plugins -> Activity modules -> Quiz:
Change Autosave period to 5 minutes (Defaults to 1 min, which will choke your Postgres DB with 1000 users).
Ensure quizzes are authored with 5 to 10 questions per page.
You are now officially running the exact v5.1.3 source code, deployed statelessly via Docker Compose in Coolify, with perfect resource mapping for your upgraded Proxmox VM.