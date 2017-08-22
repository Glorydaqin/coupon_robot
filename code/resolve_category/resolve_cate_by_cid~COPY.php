<?php
/*根据分析html*/

include_once dirname(dirname(dirname(__FILE__))).DIRECTORY_SEPARATOR . 'etc/initiate.php';
echo "start time:".date("Y-m-d H:i:s")."\n";
$num = checkScriptProcessCount(basename(__FILE__));
if($num > 1) exit("Competitor ID:".$cid." catch ing !\n");


set_time_limit(0);

$db = new Mysql(DB_NAME, DB_HOST, DB_USER, DB_PWD);

//获取10000个没有分类的store
$s_sql = 'select ID,Country from cp_store where Country="US" and ID not in(select StoreId from cp_store_cate_mapping) limit 200000';
$s_list = $db->getRows($s_sql);

//Competitor优先级配置
$competitor_level = array(8, 3, 6);

//获取store下的cs
//$i=0;
file_put_contents('category.txt', '');
foreach ($s_list as $v) {
//	$i++;
	$cs_sql='select c.ID,c.CompetitorId,c.StoreId,cc.FilePath from cp_competitor_store c left join competitor_catch_file cc on cc.CompetitorStoreId=c.ID where StoreId='.$v['ID'].' order by cc.AddTime desc';
	$cs_list=$db->getrows($cs_sql);

	if(empty($cs_list)){
		continue;
	}
//	默认路径
	$default=array();

	//按照Competitor优先程度查找cs下的catchfile地址
	foreach ($cs_list as $vo){
		if($vo['CompetitorId']==$competitor_level[2] &&!empty($vo['FilePath'])){
			$default['ID'] = $vo['ID'];
			$default['FilePath']=$vo['FilePath'];
			$default['CompetitorId']=$vo['CompetitorId'];
			break;
		}
	}
	foreach ($cs_list as $vo){
		if($vo['CompetitorId']==$competitor_level[1] && !empty($vo['FilePath'])){
			$default['ID'] = $vo['ID'];
			$default['FilePath']=$vo['FilePath'];
			$default['CompetitorId']=$vo['CompetitorId'];
			break;
		}
	}
	foreach ($cs_list as $vo){
		if($vo['CompetitorId']==$competitor_level[0] && !empty($vo['FilePath'])) {
			$default['ID'] = $vo['ID'];
			$default['FilePath'] = $vo['FilePath'];
			$default['CompetitorId'] = $vo['CompetitorId'];
			break;
		}
	}
//	print_r($default);
	if(empty($default['FilePath'])){
		continue;
	}
	//执行正则匹配分类
	$content=file_get_contents($default['FilePath']);
	if($default['CompetitorId']==$competitor_level[0]){
		//为8的竞争对手
		preg_match_all('/<a class="crumb" href="[^"]+">([^<]+)<\/a>/',$content,$rs);

		if(!empty($rs[1][2])){
			$cate_name=$rs[1][2];
		}
	}
	if($default['CompetitorId']==$competitor_level[1]){
//		http://www.promopro.com/
		preg_match_all('/<span itemprop\=\"title\">([^<]+)/',$content,$rs);
		//第二个为空时，说明没有分类
		if(!empty($rs[1][2])){
			$cate_name=$rs[1][1];
		}elseif(!empty($rs[1][1])){
			$cate_name=$rs[1][0];
		}
	}

	if ($default['CompetitorId']==$competitor_level[2]){
//		http://www.retailmenot.com/
		preg_match_all('/>([^<]+)<\/a><span class="s"/',$content,$rs);

		if(!empty($rs[1][3])){
			$t=3;
			$cate_name=$rs[1][3];
		}elseif(!empty($rs[1][2])){
			$t=2;
			$cate_name=$rs[1][2];
		}elseif(!empty($rs[1][1])){
			$t=1;
			$cate_name=$rs[1][1];
		}
	}

	$cate_name=trim($cate_name);
	if(strlen($cate_name)==0||strlen($cate_name)>100){
		echo "分类名：".$cate_name."，长度为0或》100，已跳过,请检查正则\n";
		continue;
	}
	if(!empty($cate_name)){
//			查询category是否有值
		$cate_ck_sql="select ID from cp_store_category where Name='".addslashes($cate_name)."'";
		$cate_map_id=$db->getFirstRow($cate_ck_sql);
		if(!empty($cate_map_id)){

			//添加到store_cate_mapping，建立关系
			$StoreCateId=$cate_map_id['ID'];
			$store_cate_mapping_sql="insert into cp_store_cate_mapping (StoreId,StoreCateId,CompetitorId,CompetitorStoreId,AddTime) VALUES (".$v['ID'].",$StoreCateId,".$default['CompetitorId'].','.$default['ID'].",'".date('Y-m-d H:i:s')."')";
//			echo $store_cate_mapping_sql;die;
			$db->query($store_cate_mapping_sql);
		}else{
			//添加到store_category
			$cate_sql="insert into cp_store_category (Name,AddTime,Country) VALUES ('".addslashes($cate_name)."','".date('Y-m-d H:i:s')."','".$v["Country"]."')";
			$result=$db->query($cate_sql);

			file_put_contents('category.txt',$cate_name."\n",FILE_APPEND);

			//添加到store_cate_mapping，建立本地对应关系
			$StoreCateId=$db->getLastInsertId();
			$store_cate_mapping_sql="insert into cp_store_cate_mapping (StoreId,StoreCateId,CompetitorId,CompetitorStoreId,AddTime) VALUES (".$v['ID'].",$StoreCateId,".$default['CompetitorId'].','.$default['ID'].",'".date('Y-m-d H:i:s')."')";
			$db->query($store_cate_mapping_sql);
		}
	}
}

echo "end time:".date("Y-m-d H:i:s");
