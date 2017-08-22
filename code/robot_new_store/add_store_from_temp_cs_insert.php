<?php
/*添加新store ,根据 temp cs表*/
include_once dirname(dirname(dirname(__FILE__))).DIRECTORY_SEPARATOR . 'etc/initiate.php';
$num = checkScriptProcessCount(basename(__FILE__));  //检测是否多开
if($num > 1) exit(basename(__FILE__).": catch ing !\n");

echo " start time:".date("Y-m-d H:i:s")."-\n";

set_time_limit(1000);

$sql="SELECT tcs.ID,tcs.StoreUrl,tcs.CompetitorId,tcs.Domain,tcs.DefaultUrl,c.Country from cp_temp_competitor_store tcs LEFT JOIN cp_competitor c ON(tcs.CompetitorId=c.ID) WHERE tcs.CompetitorStoreId=-1 AND tcs.Domain IS NOT NULL AND tcs.Domain !='' AND tcs.Domain!='Domain Error' LIMIT 1000";
$db = new Mysql(DB_NAME, DB_HOST, DB_USER, DB_PWD);
$list=$db->getRows($sql);
foreach ($list as $vo){
	$storeID=get_store_domain($vo['Domain'],$vo['DefaultUrl'],$vo['Country']);
	if(!empty($storeID)){
		$sql="insert ignore into cp_competitor_store(CompetitorId,StoreId,Url,UpdateFrequency,AddTime) values ({$vo['CompetitorId']},{$storeID},'{$vo['StoreUrl']}','259200','".date("Y-m-d H:i:s")."')";
		$ind=$db->query($sql);
		$ind=$db->getLastInsertId();
		$sqlUp="update cp_temp_competitor_store set  StoreId={$storeID},CompetitorStoreId={$ind} where ID={$vo['ID']}";
		$GLOBALS['db']->query($sqlUp);
	}
}
$db->close();
echo "sync end time:".date("Y-m-d H:i:s")."-\n";

function get_store_domain($domain,$defaultUrl,$country){
	$store_table="cp_store";
	$domain=str_ireplace("www.","",strtolower($domain));
	$sql="select ID from {$store_table} where Country='{$country}' and Domain = '{$domain}'";
    $storeVo=$GLOBALS['db']->getFirstRow($sql);

	if(!empty($storeVo)){
		return $storeVo['ID'];
	}else{
		$sql="insert into {$store_table}(Url,Domain,ValiDomain,Name,Status,Country,AddTime) values ('{$defaultUrl}','{$domain}','{$domain}','{$domain}','Perm Inactive','{$country}','".date("Y-m-d H:i:s")."')";
		$ind=$GLOBALS['db']->query($sql);
		$ind=$GLOBALS['db']->getLastInsertId();
		return $ind;
	}
}
