<?php
define('DB_NAME', 'content');
define('DB_HOST', '10.105.36.205');
define('DB_USER', 'content_robot');
define('DB_PWD', 'content_robotpass');
define('DB_PORT', '3306');


define('MYSQL_ENCODING', 'utf8mb4');
define('DEBUG_MODE', false);

//Memcache Config
define('MEM_CACHE_DEBUG', false);
define('MEM_CACHE_PORT', 6379);
define('MEM_CACHE_SERVER_IP', "127.0.0.1");
define('MEM_CACHE_PRE', "cp_");
define('MEM_CACHE_EXP_TIME', 3600 * 24 * 30);
define('MEM_CACHE_IP_KEY', "c_ip_list");
