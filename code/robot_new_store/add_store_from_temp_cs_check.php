<?php
/*添加新store ,根据 temp cs表*/
include_once dirname(dirname(dirname(__FILE__))).DIRECTORY_SEPARATOR . 'etc/initiate.php';
$num = checkScriptProcessCount(basename(__FILE__));  //检测是否多开
if($num > 1) exit(basename(__FILE__).": catch ing !\n");

echo " start time:".date("Y-m-d H:i:s")."-\n";

set_time_limit(1000);

$sql="select ID,StoreUrl from cp_temp_competitor_store where CompetitorStoreId=0 limit 5000";
$db = new Mysql(DB_NAME, DB_HOST, DB_USER, DB_PWD);
$list=$db->getRows($sql);
foreach ($list as $vo){
	$ids=get_competitor_store_id_by_url($vo['StoreUrl']);
	$sql="update cp_temp_competitor_store set CompetitorStoreId={$ids} where ID={$vo['ID']}";
	$db->query($sql);
}
$db->close();
echo "sync end time:".date("Y-m-d H:i:s")."-\n";
function get_competitor_store_id_by_url($url){
	$sql="select ID from cp_competitor_store where Url='{$url}'";
	$csVo=$GLOBALS['db']->getFirstRow($sql);
	if(empty($csVo)){
		return -1;
	}else{
		return $csVo['ID'];
	}
}