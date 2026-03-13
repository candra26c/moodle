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

$CFG->dataroot = '/var/www/moodledata';
$CFG->admin    = 'admin';
$CFG->directorypermissions = 0755;

// ─────────────────────────────────────────────
// Reverse proxy (Coolify → Traefik → nginx → PHP-FPM)
// sslproxy = true: Moodle generates https:// URLs even
// when PHP-FPM receives http from nginx internally.
// ─────────────────────────────────────────────
$CFG->sslproxy           = true;
$CFG->reverseproxyignore = false;

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
require_once(__DIR__ . '/lib/setup.php');<?php  // Moodle configuration file — injected by Docker build, values from env

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

$CFG->dataroot = '/var/www/moodledata';
$CFG->admin    = 'admin';
$CFG->directorypermissions = 0755;

// ─────────────────────────────────────────────
// Reverse proxy (Coolify → Traefik → nginx → PHP-FPM)
// sslproxy = true: Moodle generates https:// URLs even
// when PHP-FPM receives http from nginx internally.
// ─────────────────────────────────────────────
$CFG->sslproxy           = true;
$CFG->reverseproxyignore = false;

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