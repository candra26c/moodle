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
// ─────────────────────────────────────────────
$CFG->wwwroot  = rtrim(getenv('MOODLE_URL'), '/');
$CFG->dataroot = '/var/www/moodledata';
$CFG->admin    = 'admin';
$CFG->directorypermissions = 0755;

// ─────────────────────────────────────────────
// Reverse proxy (Coolify → Traefik → nginx → PHP-FPM)
// Must be set so Moodle generates correct https:// URLs
// ─────────────────────────────────────────────
$CFG->sslproxy     = true;
$CFG->reverseproxy = true;

// Trust forwarded headers from the nginx container only
$CFG->reverseproxyignore = false;

// ─────────────────────────────────────────────
// Redis session handler
// Critical for high-concurrency exam scenarios
// ─────────────────────────────────────────────
$CFG->session_handler_class         = '\core\session\redis';
$CFG->session_redis_host            = getenv('REDIS_HOST') ?: 'moodle-redis';
$CFG->session_redis_port            = 6379;
$CFG->session_redis_database        = 0;
$CFG->session_redis_prefix          = 'mdl_sess_';
$CFG->session_redis_acquire_lock_timeout = 120;
$CFG->session_redis_lock_expire     = 7200;

// ─────────────────────────────────────────────
// Performance
// ─────────────────────────────────────────────
$CFG->cachejs      = true;
$CFG->langstringcache = true;

// ─────────────────────────────────────────────
// Bootstrap
// ─────────────────────────────────────────────
require_once(__DIR__ . '/lib/setup.php');
