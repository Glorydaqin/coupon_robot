<?php
/*保持抓取的html*/
include_once dirname(dirname(dirname(__FILE__))).DIRECTORY_SEPARATOR . 'etc/initiate.php';

//命令行传递参数
$row['ID']= isset($_SERVER["argv"][2])?$_SERVER["argv"][2]:0;
$row['IPAuto']= isset($_SERVER["argv"][3])?$_SERVER["argv"][3]:0;
$row['Url']= isset($_SERVER["argv"][4])?$_SERVER["argv"][4]:0;
$row['CompetitorId'] = isset($_SERVER["argv"][1])?$_SERVER["argv"][1]:0;

if(empty($row['ID']) || empty($row['CompetitorId']) || empty($row['IPAuto']) || empty($row['Url'])){
	exit("param empty \n");
}

$row['Url']=base64_decode($row['Url']);
$db = new Mysql(DB_NAME, DB_HOST, DB_USER, DB_PWD);
$cache_obj = new Cache();

set_time_limit(100);

$filePath=createCatchFile($row['CompetitorId'],$row['ID']);
		
$url=$row['Url'];
$ip_info = "";

//获取ip
if($row['IPAuto']==2){
	if(!MEM_CACHE_DEBUG){ //如果有memcache 走memcache
		$ip_info = $cache_obj->get_ip_by_competitor_id($row['CompetitorId']);

	}else{
		$ip_info = file_get_contents(DIR_IP_CONFIG.$row['CompetitorId'].".txt");
		if(empty($ip_info)){
			$ip_info = get_proxy_info($row['CompetitorId']);
			file_put_contents(DIR_IP_CONFIG.$row['CompetitorId'].".txt", $ip_info);
		}
	}
	
	$ip_info = trim($ip_info);
}
//$ip_info = "127.0.1.1:2358";

$r=vspider_get_code_file($url, $filePath,$ip_info,true);

$on_url_arr=parse_url($url);
$re_url_arr=parse_url($r['url']);

$content = file_get_contents($filePath);
$res = check_ip_by_get_content($row['CompetitorId'],"store",$content,true);

if($res == "no"){
	$cache_obj->update_data_by_get_html_res($row['CompetitorId'], $ip_info, false);
	$sqlUp="update cp_competitor_store set ErrorTime=ErrorTime+1,IsUpdate=0 where ID=".$row['ID'];
	$db->query($sqlUp);
}else{
	if($res == "yes"){
		$up_end_url = "";
		if($on_url_arr['path'] != $re_url_arr['path']){
			$up_end_url = " , 301Url='".addslashes($r['url'])."'  ";
		}
		$sqlUp="update cp_competitor_store set LastChangeTime='".date("Y-m-d H:i:s")."',ErrorTime=0,301Times=0,IsCatch=1,404Times=0 {$up_end_url},IsUpdate=0  where ID={$row['ID']}";
		$db->query($sqlUp);
		$sqlIns="INSERT into cp_competitor_catch_file  (CompetitorStoreId,MerchantName,FilePath,FileSize,AddTime) VALUES(".$row['ID'].",'".MERCHANT_NAME."','".$filePath."',".filesize($filePath).",'".date("Y-m-d H:i:s")."')";
		$db->query($sqlIns);
	}elseif($res =="notfound"){
		$sqlUp="update cp_competitor_store set LastChangeTime='".date("Y-m-d H:i:s")."',IsUpdate=0,IsCatch=0,Is301='404',ErrorTime=0  where ID={$row['ID']}";
		$db->query($sqlUp);
	}
	$cache_obj->update_data_by_get_html_res($row['CompetitorId'], $ip_info, true);
}
$db->close();