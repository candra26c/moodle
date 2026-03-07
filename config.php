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