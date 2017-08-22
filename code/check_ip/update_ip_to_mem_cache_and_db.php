<?php
/*test*/
include_once dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . 'etc/initiate.php';

$num = checkScriptProcessCount(basename(__FILE__));
if($num > 1) exit(basename(__FILE__)." catch ing !\n");

echo "start time:".date("Y-m-d H:i:s")."\n";
if(!MEM_CACHE_DEBUG){
	$cache_obj = new Cache();
	$cache_obj->update_memcache_ip_list_value();
}
echo "end time:".date("Y-m-d H:i:s")."\n";