<?php
include_once dirname(dirname(dirname(__FILE__))).DIRECTORY_SEPARATOR . 'etc/initiate.php';


$db = new Mysql(DB_NAME, DB_HOST, DB_USER, DB_PWD);

test_competitor_flag_by_file();
//repair_store_error_data();die;
//up_competitor_301_url();die();
//import_data();die;


function test_competitor_flag_by_file(){
	$filePath = "https://www.groupon.co.uk/";
	$cid =17;
	$content = file_get_contents($filePath);
	$res = check_ip_by_get_content_new($cid,"index",$content,false);
	print_r($res);
}

function check_ip_by_get_content_new($cid,$type,$content,$is_cache=true){
	$flag = "no";
	if(strlen($content) <  5000){

		return $flag;
	}
	$content = del_br_space_by_str($content);
	$mem_obj = new Cache();
	$c_list = $mem_obj->get_cache(COMPETITOR_INFO_KEY);
	if(!$is_cache){
		$c_list = "";
	}


	if(empty($c_list)){
		$db_obj_tmp = new Mysql(DB_NAME, DB_HOST, DB_USER, DB_PWD);
		$sql = "select * from cp_competitor";
		$c_list = $db_obj_tmp->getRows($sql,"ID");
		$mem_obj->set_cache(COMPETITOR_INFO_KEY, $c_list);
	}

	print_r($c_list);
	if(isset($c_list[$cid])){
		$find_str = "";
		if($type == "index"){
			$find_str = $c_list[$cid]['IndexFlag'];
		}elseif($type == "store"){
			$find_str = $c_list[$cid]['StoreFlag'];
		}
		if(!empty($find_str)){
			if(stripos($content, $find_str)){
				$flag = "yes";
			}else{
				if(!empty($c_list[$cid]['DeleteFlag'])){
					if(stripos($content, $c_list[$cid]['DeleteFlag'])){
						$flag = "notfound";
					}
				}
			}
		}
	}
	return $flag;
}

//修复数据 带 http 号
function repair_store_error_data(){
	global $db;
	$sql = "select * from cp_store where Domain like '%:%'";
	$list = $db->getRows($sql);
	foreach ($list as $o){
		
		$tmp_arr = explode(":",$o['Domain']);
		if(count($tmp_arr) != 2 )continue;
		$domain =  $tmp_arr[0];
		
		
// 		if(substr($o['Domain'],-5,5) == "http:"){
// 			$domain =  substr($o['Domain'],0,-5);	
// 		}elseif (substr($o['Domain'],-4,4) == "http"){
// 			$domain =  substr($o['Domain'],0,-4);		
// 		}else{
// 			print_r($o);
// 			continue;
// 		}
		$sql = "select * from cp_store where Domain = '{$domain}' ";
		
		$res = $db->getFirstRow($sql);
		
		if(empty($res)){
			$sql = "update store set Domain = '{$domain}' ,Name = '{$domain}' where ID = '{$o['ID']}' ";
			$db->query($sql);
		}else{
			$sql = "UPDATE   competitor_store SET StoreId = '{$res['ID']}' WHERE StoreId = '{$o['ID']}'  ";
			$db->query($sql);
			$sql = "UPDATE   temp_competitor_store SET StoreId = '{$res['ID']}' WHERE StoreId = '{$o['ID']}'  ";
			$db->query($sql);
			$sql = "delete from cp_store where ID = '{$o['ID']}'  ";
			$db->query($sql);
		}
	}
}

//更新竞争对手3换域名后的url
function up_competitor_301_url(){
	global $db;
	$competitor_id = 3;
	$sql = "select ID,Url,301Url  from cp_competitor_store WHERE CompetitorId = {$competitor_id}";
	$cs_list = $db->getRows($sql);
	$not_found_num = 0;
	$not_match_num = 0;
	$up_num = 0;
	$not_match_arr = array();
	foreach ($cs_list as $o){
		//http://www.promopro.com/store/moo.com
		if(empty($o['301Url']))  {
			$not_found_num++;
			$sql = "delete from cp_competitor_store where ID = '{$o['ID']}' ";
			$db->query($sql);
			$sql = "delete from cp_temp_competitor_store where CompetitorStoreId = '{$o['ID']}' ";
			$db->query($sql);
			continue;
		}
		$patt_store = "/http:\/\/www.promopro.com\/store\/(.*)/i";
		preg_match($patt_store,$o['301Url'],$r);
		if(empty($r)){
			$not_match_num ++;
			$not_match_arr[] = $o['Url']."--".$o['301Url'];
			$sql = "delete from cp_competitor_store where ID = '{$o['ID']}' ";
			$db->query($sql);
			$sql = "delete from cp_temp_competitor_store where CompetitorStoreId = '{$o['ID']}' ";
			$db->query($sql);
		}else{
			if($o['Url'] == $o['301Url']) continue;
			//查询url是否存在
			$sql = "select ID from cp_competitor_store where Url = '{$o['301Url']}' ";
			$check_obj = $db->getFirstRow($sql);
			if(!empty($check_obj)){
				$sql = "DELETE from cp_competitor_store WHERE  ID = '{$o['ID']}' ";
				$db->query($sql);
				$sql = "DELETE from cp_temp_competitor_store WHERE  CompetitorStoreId = '{$o['ID']}' ";
				$db->query($sql);
				$sql = "DELETE from cp_competitor_store_coupon WHERE CompetitorStoreId = '{$o['ID']}' ";
				$db->query($sql);
			}else{
				$sql = "update cp_competitor_store set Url = '{$o['301Url']}' where ID =  '{$o['ID']}' ";
				$db->query($sql);
				$sql = "update cp_temp_competitor_store set StoreUrl = '{$o['301Url']}' where CompetitorStoreId =  '{$o['ID']}' ";
				$db->query($sql);
				$up_num++;
			}
		}
	}
}




//根据sitemap 导入竞争数据，xml
function import_data(){
	global $db;
	@ini_set('memory_limit', '512M');
	$path  = INCLUDE_ROOT."data/categories-1.xml";
	$competitor_id = 10;
	$htmlContent = file_get_contents($path);
	preg_match_all("/<loc>([^<]+)<\/loc>/", $htmlContent, $matchUrl,PREG_SET_ORDER);
	$num = 0;
	foreach ($matchUrl as $o){
		
		
		if(!stripos($o[1], "/deals/")) continue;
		
		if(!empty($o[1])){
			$on_time = date("Y-m-d H:i:s");
			$storeUrl = trim($o[1]);
			$sql = "insert ignore into cp_competitor_deals(`ID`,`Url`,`CompetitorId`,`AddTime`) Values('','{$storeUrl}','{$competitor_id}','{$on_time}')";
			$db->query($sql);
		//	echo $sql."\n";
			$num++;
		}
	}
	print_r("---import CompetitorID:{$competitor_id}--Total Num: {$num}\n");
}

