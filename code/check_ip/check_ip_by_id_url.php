<?php
/*检查IP是否可用*/
include_once dirname(dirname(dirname(__FILE__))).DIRECTORY_SEPARATOR . 'etc/initiate.php';
//test   php /data1/robot/code/check_ip/check_ip_by_id_url.php 81094 9 1.197.14.102:8000 http://couponfollow.com
$vo['ID']=isset($_SERVER["argv"][1])?$_SERVER["argv"][1]:0;
$vo['CID']=isset($_SERVER["argv"][2])?$_SERVER["argv"][2]:0;
$vo['Info']=isset($_SERVER["argv"][3])?$_SERVER["argv"][3]:0;
$vo['Url']=isset($_SERVER["argv"][4])?$_SERVER["argv"][4]:0;

if(!empty($vo['ID']) && !empty($vo['Url']) && !empty($vo['Info'])){
	set_time_limit(100);
	$filePath=createCheckFile($vo['CID'],$vo['ID']);
	$httpStatus=vspider_get_code($vo['Url'], $filePath,$vo['Info']);
	
	$db = new Mysql(DB_NAME, DB_HOST, DB_USER, DB_PWD);
	
	$content = file_get_contents($filePath);
	//del_file($filePath);
	$res = check_ip_by_get_content($vo['CID'],"index",$content);

	if($res == "no"){
		$sql = "UPDATE cp_ip_info SET Status='deleted',LastChangeTime='".date("Y-m-d H:i:s")."' WHERE ID={$vo['ID']}";
		$db->query($sql);
	}else{
		$sql = "UPDATE cp_ip_info SET Status='active',LastChangeTime='".date("Y-m-d H:i:s")."' WHERE ID={$vo['ID']}";
		$db->query($sql);
	}
}
$db->close();

//删除操作
function del_file($file_path){
	system("rm -rf {$file_path}", $retval);
		
}