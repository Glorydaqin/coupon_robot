<?php
/*检查IP是否有效*/
include_once dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . 'etc/initiate.php';
$competitorId = isset($_SERVER["argv"][1])?$_SERVER["argv"][1]:0;
if(empty($competitorId)){
	exit("competitor id empty\n");
}
$num = checkScriptProcessCount(basename(__FILE__)." ".$competitorId);
if($num > 1) exit("Competitor ID:".$competitorId." check ing !\n");
echo "start time:".date("Y-m-d H:i:s")."\n";

//避免单数字出错,去掉CompetitorId前缀0
$competitorId=ltrim($competitorId,'0');
$maxActiveIPs=PARAM_MAX_ACTIVE_IPS;
$maxActiveIPs=empty($maxActiveIPs)?30:$maxActiveIPs;

set_time_limit(300);
$db = new Mysql(DB_NAME, DB_HOST, DB_USER, DB_PWD);
$fail_num =5;
$sql="select count(*) as num from cp_ip_info where CompetitorId={$competitorId} and FailNum<".$fail_num." and (Status='active' or Status='ing')";
$count=$db->getFirstRow($sql);
echo "count:{$count['num']}\n";

if($count['num']<$maxActiveIPs ){
	$limit=$maxActiveIPs-$count['num'];
	$sql="select * from cp_ip_info where CompetitorId={$competitorId} and FailNum<".PARAM_CATCH_FAIL_MAX_COUNT_IP." and GoodCatch=0 and Status='normol' limit {$limit}";
	$valiIpList=$db->getRows($sql,"ID");
	if(count($valiIpList)<$limit){
		get_proxy_info_by_cid($competitorId);
		$sql="select * from cp_ip_info where CompetitorId={$competitorId} and FailNum<".PARAM_CATCH_FAIL_MAX_COUNT_IP." and GoodCatch=0 and Status='normol' limit {$limit}";
		$valiIpList=$db->getRows($sql,"ID");
	}
	$sql="select * from cp_competitor where ID={$competitorId}";
	$competitorVo=$db->getFirstRow($sql);
	if(!empty($valiIpList)){
		$ipids=implode(",", array_keys($valiIpList));
		$sql="update cp_ip_info set Status='ing' where ID in ({$ipids})";
		$db->query($sql);
	}
	$i=0;
	foreach ($valiIpList as $vo){
		$cmd = "php ".INCLUDE_ROOT."code/check_ip/check_ip_by_id_url.php {$vo['ID']} {$competitorId} {$vo['Info']} {$competitorVo['Url']}";
		$cmd = $cmd." >/dev/null 2>&1 &";
		exec($cmd);
		sleep(1);
		$i++;
	}
	echo "check IP count:".$i."\n";
}else{
	sleep(5);
	$sql="select count(*) as num from cp_ip_info where CompetitorId={$competitorId} and FailNum=0 and Status='ing'";
	$count=$db->getFirstRow($sql);
	if($count['num']>=$maxActiveIPs){
		echo "update ing count num\n";
		$sql="update cp_ip_info set Status='normol' where CompetitorId={$competitorId} and FailNum=0 and Status='ing'";
		$db->query($sql);
	}
}
$db->close();
echo "end time:".date("Y-m-d H:i:s")."\n";
