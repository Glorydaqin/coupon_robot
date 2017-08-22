<?php
/*根据分析html 获取类别*/

include_once dirname(dirname(dirname(__FILE__))).DIRECTORY_SEPARATOR . 'etc/initiate.php';
echo "start time:".date("Y-m-d H:i:s")."\n";

$cate_grade_arr = array(3,4,6,7,9,8,5);  //获取分类优先级
$de_grade_arr = array(14,13);

$num = checkScriptProcessCount(basename(__FILE__));
if($num > 1) exit(" catch ing !\n");

/*
 * 1.根据term 找到store 
 * 2.根据store找到cs，然后按优先级解析相应的面包屑，过滤未分类的面包屑：如store,product,字母分配等
 * 3.插入到数据库，并把关系插入到数据库
 */
$num = 100000;
$db = new Mysql(DB_NAME, DB_HOST, DB_USER, DB_PWD);
$sql = "SELECT tsm.*,s.Country FROM term_store_mapping AS tsm LEFT JOIN store AS s ON(tsm.StoreId=s.ID) LEFT JOIN store_cate_mapping AS scm ON(s.ID=scm.StoreId) WHERE   scm.StoreCateId IS NULL and s.Country='DE' order by tsm.TermId limit {$num}";
$store_list = $db->getRows($sql,"StoreId");

foreach($store_list as $k=>$o){
	if($o['Country'] == "DE"){
		$competitor_str = join(",",$de_grade_arr);
	}else{
		$competitor_str = join(",",$cate_grade_arr);
	}
	
	$sql = " SELECT  ID,CompetitorId,StoreId  FROM  competitor_store  WHERE StoreId = {$k} AND CompetitorId IN ({$competitor_str}) ORDER BY FIELD(CompetitorId,{$competitor_str})";
	$cs_list = $db->getRows($sql);
	foreach ($cs_list as $c){
		$sql = "select * from cp_competitor_catch_file where CompetitorStoreId = {$c['ID']} order by ID desc limit 1 ";
		$obj = $db->getFirstRow($sql);
		if(empty($obj)) continue;
		$res = get_path_by_csid($c['CompetitorId'],$obj['FilePath']);
		print_r($res);
		if(!empty($res)){
			//插入类别数据
			$clen = count($res);
			if($clen >3){
				$res = array_slice($res,$clen-3,3);
			}
			$cate_name = "";
			$cate_name_two = "";
			$cate_name_three = "";
			if(count($res) == 1){
				$cate_name = $res[0];
			}elseif (count($res) ==2){
				$cate_name_two = $res[0];
				$cate_name = $res[1];
			}else{
				$cate_name_three = $res[0];
				$cate_name_two = $res[1];
				$cate_name = $res[2];
			}
			
			if(empty($cate_name)) continue;
			//检查类别是否添加
			$sql = "select * from cp_store_category where Name = '".addslashes($cate_name)."' and Country = '{$o['Country']}'";
			$c_obj = $db->getFirstRow($sql);
			$cate_id = 0;
			if(empty($c_obj)){
				$sql = "Insert into cp_store_category(`Name`,`NameOne`,`NameTwo`,`AddTime`,`Country`)Values('".addslashes($cate_name)."','".addslashes($cate_name_two)."','".addslashes($cate_name_three)."',Now(),'{$o['Country']}')";
				$db->query($sql);
				$cate_id = $db->getLastInsertId();
			}else{
				$cate_id = $c_obj['ID'];
			}
			//插入store cate mapping
			if(!empty($cate_id)){
				$sql = "Insert into cp_store_cate_mapping(`StoreId`,`StoreCateId`,`CompetitorId`,`CompetitorStoreId`,`AddTime`)Values('{$c['StoreId']}','{$cate_id}','{$c['CompetitorId']}','{$c['ID']}',Now())";
				$db->query($sql);
			}
			break;
		}
		
	}
}

echo "end time:".date("Y-m-d H:i:s")."\n";
function get_path_by_csid($cid,$file_path){
	if(empty($file_path) || !file_exists($file_path)) return "";
	$content = file_get_contents($file_path);
	if(empty($content)) return "";
	$comp_path_patt = array(
		3=>"/<span\s+itemprop=\"title\">([^<]*)<\/span>\s*<\/a>/",
		4=>"/<span\s+itemprop=\"title\">([^<]+)<\/span>\s*<\/a>/",
		5=>"/<span\s+itemprop=\"[^\"]*\">([^<]*)<\/span><\/a>/",
		6=>"/<a\s+href=\"[^\"]+\">([^<]*)<\/a><span\s+class=\"s\">/",
		7=>"/<span\s+itemprop=\"title\">([^<]*)<\/span>/i",
		8=>"/<a\s+class=\"crumb\"\s+href=\"[^\"]*\">([^<]*)<\/a>/i",
		9=>"/<a\s+class=\"crumb\"\s+href=\"[^<]*\">([^<]*)<\/a>/i",
		13=>"/<span\s+itemprop=\"title\">([^<]+)<\/span>\s*<\/a>/i",
		14=>"/<span\s+itemprop=\"title\">([^<]+)<\/span>\s*<\/a>/i"
	);
	
	$patt = $comp_path_patt[$cid];
	preg_match_all($patt,$content,$res);
	$data = array();
	if(!empty($res)){
		$len = count($res);
		if($len > 1){
			$data = $res[1];
		}else{
			$data = $res;
		}
		$res_arr = array();
		foreach ($data as $o){
			$o = str_ireplace("Australia","",$o);
			$o = str_ireplace("Canada","",$o);
			$o = str_ireplace("India","",$o);
			$o = str_ireplace("UK ","",$o);
			$o = str_ireplace("Japan","",$o);
			$o = str_ireplace("France","",$o);
			$o = str_ireplace("United Kingdom","",$o);
			$o = str_ireplace("New Stores","",$o);
			$o = str_ireplace("New Zealand","",$o);
			$o = str_ireplace("Brands","",$o);
			
			
			
			
			
			$o = trim($o);
			if(strtolower(trim($o)) == "home" || strtolower(trim($o)) == "all categories" || strtolower(trim($o)) == "stores"|| strtolower(trim($o)) == "coupon codes" || strtolower(trim($o)) == "back to school" || strtolower(trim($o)) == "all stores") continue;
			if(strlen($o) < 3) continue;
			if(strtolower(substr($o,strlen($o)-5,5)) == "deals") continue;
			$o = str_ireplace("&amp;", "&", $o);
			$res_arr[] = $o;
		}
		if($cid == 3){
			array_pop($res_arr);
		}
		return $res_arr;
	}
	return "";
	
}




