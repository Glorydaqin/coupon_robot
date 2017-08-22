<?php
/*爬取Temp_cs 出站链接*/
include_once dirname(dirname(dirname(__FILE__))).DIRECTORY_SEPARATOR . 'etc/initiate.php';

$num = checkScriptProcessCount(basename(__FILE__));
if($num > 1) exit("catch ing !\n");

echo "start time:".date("Y-m-d H:i:s")."\n";

$max = CatchTempCSGOURLMaxThread;
if(empty($max)){
	exit("empty max thread");
}

$check_file_name = INCLUDE_ROOT."code/robot_new_store/add_store_from_temp_cs_go_landingpage.php";
$on_count = checkScriptProcessCount($check_file_name);
if($on_count > $max) exit(">{$max} exit \n");

set_time_limit(600);
$db = new Mysql(DB_NAME, DB_HOST, DB_USER, DB_PWD);

$limit_num = 300;
$sqlQuery="select ID,GoUrl,CompetitorId,StoreUrl from cp_temp_competitor_store where CompetitorStoreId=-1 and (GoCouponUrl is null or GoCouponUrl='') AND (Domain IS  NULL OR Domain = '')  and GoUrl is not null and GoUrl!='' and ErrorTime<6 and IsUpdateCoupon=0 order by CompetitorId asc limit {$limit_num}";
$list=$db->getRows($sqlQuery,"ID");
if(empty($list)){
	$sql="update cp_temp_competitor_store set IsUpdateCoupon=0 where IsUpdateCoupon = 1";
	$db->query($sql);
	$list=$db->getRows($sqlQuery);
}

$up_cs_ids = array_keys($list);
if(empty($up_cs_ids))exit("No Data Update !\n");
$sql="update cp_temp_competitor_store set IsUpdateCoupon=1 where ID in (".join(",",$up_cs_ids).")";
$db->query($sql);
$db->close();
foreach ($list as $vo){
	$for_count = checkScriptProcessCount($check_file_name);
	if($for_count > $max) {
		sleep($for_count-$max);
	}
	$vo['GoUrl']=base64_encode($vo['GoUrl']);
	$vo['StoreUrl']=base64_encode($vo['StoreUrl']);
	$cmd = "php ".INCLUDE_ROOT."code/robot_new_store/add_store_from_temp_cs_go_landingpage.php {$vo['CompetitorId']} {$vo['ID']} {$vo['GoUrl']} {$vo['StoreUrl']}";
	$cmd = $cmd." >/dev/null 2>&1 &";
	exec($cmd);
	usleep(500);
}
echo "end time:".date("Y-m-d H:i:s")."\n";