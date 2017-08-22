<?php
/*爬取temp_cs go url robot*/
include_once dirname(dirname(dirname(__FILE__))).DIRECTORY_SEPARATOR . 'etc/initiate.php';

$num = checkScriptProcessCount(basename(__FILE__));
if($num > 1) exit("robot go catch ing !\n");

echo "start time:".date("Y-m-d H:i:s")."\n";

$check_file_name = INCLUDE_ROOT."code/robot_new_store/add_store_from_temp_cs_go_url_base.php";
$on_count = checkScriptProcessCount($check_file_name);
if($on_count > CatchTempCSGOMaxThread) exit(">".CatchTempCSGOMaxThread." exit \n");

set_time_limit(3600);
$db = new Mysql(DB_NAME, DB_HOST, DB_USER, DB_PWD);

$sqlQuery="select ID,StoreUrl,CompetitorId from cp_temp_competitor_store where CompetitorStoreId=-1 and `Status`='Normal' and (GoUrl is null or GoUrl='') and ErrorTime<6 and IsUpdate=0 limit 3000";
$list=$db->getRows($sqlQuery,'ID');
if(empty($list)){
	$sql="update cp_temp_competitor_store set IsUpdate=0 where IsUpdate=1";
	$db->query($sql);
	$list=$db->getRows($sqlQuery,"ID");
}

$up_cs_ids = array_keys($list);
if(empty($up_cs_ids))exit("No Data Update !\n");
$sql="update cp_temp_competitor_store set IsUpdate=1 where ID in (".join(",",$up_cs_ids).")";
$db->query($sql);
$db->close();
foreach ($list as $vo){
	$for_count = checkScriptProcessCount($check_file_name);
	if($for_count > CatchTempCSGOMaxThread) {
		sleep($for_count-CatchTempCSGOMaxThread);
	}
	$vo['StoreUrl']=base64_encode($vo['StoreUrl']);
    $cmd = "php ".INCLUDE_ROOT."code/robot_new_store/add_store_from_temp_cs_go_url_base.php {$vo['CompetitorId']} {$vo['ID']} {$vo['StoreUrl']}";
    $cmd = $cmd." >/dev/null 2>&1 &";
    exec($cmd);

    usleep(500);
}
echo "end time:".date("Y-m-d H:i:s")."\n";