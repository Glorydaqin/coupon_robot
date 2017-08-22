<?php
/*同步store类别关系到term类别关系，store_cate_term_cate_mapping => store-term-mapp => term_cate_mapp */

include_once dirname(dirname(dirname(__FILE__))).DIRECTORY_SEPARATOR . 'etc/initiate.php';
echo "start time:".date("Y-m-d H:i:s")."\n";
$num = checkScriptProcessCount(basename(__FILE__));
if($num > 1) exit(" catch ing !\n");
/*
 * 1.根据store_cate_mapping store_cate_term_cate_mapping & term_store_mapping 生成 term_category_mapping 
 */
$db = new Mysql(DB_NAME, DB_HOST, DB_USER, DB_PWD);
$db_deals = new Mysql(DEALS_DB_NAME, DEALS_DB_HOST, DEALS_DB_USER, DEALS_DB_PWD);

//根据term store 找出对应关系
$sql = "SELECT tsm.* FROM term_store_mapping AS tsm LEFT JOIN store AS s ON(tsm.StoreId=s.ID) LEFT JOIN store_cate_mapping AS scm ON(s.ID=scm.StoreId) WHERE   scm.StoreCateId IS NOT NULL";
$term_store_list = $db->getRows($sql,"StoreId");

$sql = "SELECT scm.*,sctc.TermCateId from cp_store_cate_mapping AS scm  LEFT JOIN store_category AS sc ON(scm.StoreCateId=sc.ID) LEFT JOIN store_cate_term_cate_mapping AS sctc ON(scm.StoreCateId = sctc.StoreCateId) WHERE  TermCateId IS NOT NULL ";
$store_cate_mapp_list = $db->getRows($sql);
$sql=$pre_sql = "insert ignore into term_category_mapping(`TermId`,`CategoryId`,`IsPrimary`,`AddTime`)Values ";
if(!empty($store_cate_mapp_list)){
	$n = 0;
	foreach ($store_cate_mapp_list as $o){
		if(isset($term_store_list[$o['StoreId']])){
			$term_id = $term_store_list[$o['StoreId']]['TermId'];
			if(empty($term_id) || empty($o['TermCateId'])) continue;
			$sql.="('{$term_id}','{$o['TermCateId']}','yes',Now()),";
			$n++;
			if($n % 1000 == 0){
				$db_deals->query(substr($sql,0,-1));
				$sql=$pre_sql;
			}
		}
	}
	if($sql!=$pre_sql){
		$db_deals->query(substr($sql,0,-1));
	}
}
die("----sync num:{$n}------------");




