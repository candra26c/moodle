<?php  // Moodle configuration file — injected by Docker build, values from env

unset($CFG);
global $CFG;
$CFG = new stdClass();

// ─────────────────────────────────────────────
// Database (PostgreSQL)
// ─────────────────────────────────────────────
$CFG->dbtype    = 'pgsql';
$CFG->dblibrary = 'native';
$CFG->dbhost    = getenv('MOODLE_DB_HOST');
$CFG->dbname    = getenv('MOODLE_DB_NAME');
$CFG->dbuser    = getenv('MOODLE_DB_USER');
$CFG->dbpass    = getenv('MOODLE_DB_PASSWORD');
$CFG->prefix    = 'mdl_';
$CFG->dboptions = [
    'dbpersist' => 0,
    'dbport'    => '',
    'dbsocket'  => '',
];

// ─────────────────────────────────────────────
// Site URL & data paths
// Moodle 5.1+: web root must point to public/ subdir
//
// Switches wwwroot based on incoming hostname:
// - local domain (MOODLE_LOCAL_HOST) → MOODLE_LOCAL_URL (http, no SSL)
// - public domain → MOODLE_URL (https)
//
// MOODLE_LOCAL_HOST = e.g. ujian.smamseven
// MOODLE_LOCAL_URL  = e.g. http://ujian.smamseven
// ─────────────────────────────────────────────
$_localHost = getenv('MOODLE_LOCAL_HOST') ?: '';
$_localUrl  = getenv('MOODLE_LOCAL_URL')  ?: '';

$CFG->wwwroot = ($_localHost && isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === $_localHost)
    ? rtrim($_localUrl, '/')
    : rtrim(getenv('MOODLE_URL'), '/');

$CFG->dataroot  = '/var/www/moodledata';
$CFG->admin     = 'admin';

// ─────────────────────────────────────────────
// Directory permissions
// 02777 = setgid bit + rwxrwxrwx — Moodle official default.
// The setgid bit ensures new files inherit the group, so
// CLI scripts (cron) and web (www-data) can both read/write.
// 0750 is too restrictive and causes "Invalid permissions" errors.
// ─────────────────────────────────────────────
$CFG->directorypermissions = 02777;

// ─────────────────────────────────────────────
// Additional data paths
// Explicit paths prevent Moodle from auto-detecting wrong dirs.
// localcachedir — Mustache/template cache (per-node, RAM-backed tmpfs).
// tempdir       — short-lived temp files.
// cachedir      — application-level MUC disk cache.
// localrequestdir — per-request temp files, uses OS temp.
// ─────────────────────────────────────────────
$CFG->localcachedir   = '/var/cache/moodle_localcache';
$CFG->tempdir         = '/var/www/moodledata/temp';
$CFG->cachedir        = '/var/www/moodledata/cache';
$CFG->localrequestdir = '/tmp/moodlerequest';

// ─────────────────────────────────────────────
// Reverse proxy (Coolify → Traefik → nginx → PHP-FPM)
// sslproxy = true: Moodle generates https:// URLs even
// when PHP-FPM receives http from nginx internally.
// ─────────────────────────────────────────────
$CFG->sslproxy           = true;
$CFG->reverseproxyignore = false;

// ─────────────────────────────────────────────
// X-Sendfile / X-Accel-Redirect
// nginx serves moodledata files directly (pluginfile.php, etc.)
// without PHP holding a connection open for the entire download.
// xsendfilepath must match the nginx internal location alias.
// ─────────────────────────────────────────────
$CFG->xsendfile     = 'X-Accel-Redirect';
$CFG->xsendfilepath = '/moodledata_internal';

// ─────────────────────────────────────────────
// Security
// upgradekey: protects /admin/upgrade.php from public access.
// Set MOODLE_UPGRADEKEY env var in Coolify — leave blank to skip.
// cookiehttponly: prevents JS from reading session cookies.
// cookiesecure: only send session cookie over HTTPS.
// ─────────────────────────────────────────────
$CFG->upgradekey     = getenv('MOODLE_UPGRADEKEY') ?: '';
$CFG->cookiehttponly = true;
$CFG->cookiesecure   = true;

// ─────────────────────────────────────────────
// Static file caching
// filelifetime: how long browsers cache static files (seconds).
// 86400 = 1 day. Reduces repeat page-load time significantly.
// ─────────────────────────────────────────────
$CFG->filelifetime = 86400;

// ─────────────────────────────────────────────
// Redis session handler
// Critical for high-concurrency exam scenarios
// ─────────────────────────────────────────────
$CFG->session_handler_class              = '\core\session\redis';
$CFG->session_redis_host                 = getenv('REDIS_HOST') ?: 'moodle-redis';
$CFG->session_redis_port                 = 6379;
$CFG->session_redis_database             = 0;
$CFG->session_redis_prefix               = 'mdl_sess_';
$CFG->session_redis_acquire_lock_timeout = 120;
$CFG->session_redis_lock_expire          = 7200;

// ─────────────────────────────────────────────
// Performance
// ─────────────────────────────────────────────
$CFG->cachejs         = true;
$CFG->langstringcache = true;

// ─────────────────────────────────────────────
// Bootstrap
// ─────────────────────────────────────────────
require_once(__DIR__ . '/lib/setup.php');