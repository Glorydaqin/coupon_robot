<?php
define('INCLUDE_ROOT',dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR);
define('INCLUDE_LOG_ROOT',dirname(dirname(dirname(__FILE__))).DIRECTORY_SEPARATOR."log/");
date_default_timezone_set('PRC');

include_once(INCLUDE_ROOT.'etc/dbconfig.php');
include_once(INCLUDE_ROOT.'etc/const.php');
include_once(INCLUDE_ROOT.'etc/common.func.php');

//lib include
include_once(INCLUDE_ROOT.'lib/Class.Mysql.php');
include_once(INCLUDE_ROOT.'lib/Class.MyException.php');
include_once(INCLUDE_ROOT.'lib/Class.Cache.php');
include_once(INCLUDE_ROOT.'lib/Class.Selector.php');
include_once(INCLUDE_ROOT.'lib/Class.PdoMysql.php');

//composer include
require_once INCLUDE_ROOT.'vendor/autoload.php';
