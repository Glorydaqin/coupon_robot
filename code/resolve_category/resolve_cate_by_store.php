<?php
/*根据分析html*/

include_once dirname(dirname(dirname(__FILE__))).DIRECTORY_SEPARATOR . 'etc/initiate.php';
echo "start time:".date("Y-m-d H:i:s")."\n";
$num = checkScriptProcessCount(basename(__FILE__));
if($num > 1) exit("Competitor ID: catch ing !\n");


set_time_limit(0);

$db = new Mysql(DB_NAME, DB_HOST, DB_USER, DB_PWD);

//获取10000个没有分类的store
$s_sql = 'select ID,Country from cp_store where ID not in(select StoreId from cp_store_cate_mapping) limit 200000';
$s_list = $db->getRows($s_sql);

//获取store下的cs
//$i=0;
//file_put_contents('category.txt', '');
//循环获取不同竞争对手抓取的文件
foreach ($s_list as $v) {
//	$i++;
	$cs_sql="select c.ID,c.CompetitorId,c.StoreId,cc.FilePath from cp_competitor_store c left join competitor_catch_file cc on cc.CompetitorStoreId=c.ID where StoreId={$v['ID']} ORDER BY cc.AddTime desc limit 10";
	$cs_list=$db->getrows($cs_sql);

	if(empty($cs_list)){
		continue;
	}

	//循环所有抓取的文件
    foreach ($cs_list as $item){

        $html_content=@file_get_contents($item['FilePath']);
        if(empty($html_content)){
            continue;
        }

        //匹配分类
        $match_cate='';
        $cate='';
        switch ($item['CompetitorId']){
            case '3':
                $patten="/<a href=\"\/[^\"]+\" itemprop=\"url\"><span itemprop\=\"title\">([^<]+)<\/span><\/a>/";
                break;
            case '4':
                $patten="/<span itemprop=\"title\">([^<]*?)<\/span>\s*?<\/a>/";
                break;
            case '5':
                continue;
                break;
            case '6':
                $patten="/<a href=\"\/[^\"]+\">(.*?)<\/a><span class=\"s\">/";
                break;
            case '7':
                $patten="/href=\"\/[^\"]+\" itemprop=\"url\"><span itemprop=\"title\">(.*?)<\/span>/i";
                break;
            case '8':
                $patten="/<a class=\"crumb\" href=\"\/[^\"]+\">(.*?)<\/a>/";
                break;
            case '9':
                continue;
                break;
            case '10':
                continue;
                break;
            case '11':
                continue;
                break;
            case '12':
                continue;
                break;
            case '13':
                $patten="/<div class=\"breadcrumbs_item prev\" itemscope itemtype=\"[^\"]+\">\s*?<div class=\"b_item\">\s*?<a href=\"[^\"]+\" itemprop=\"url\">\s*?<span itemprop=\"title\">(.*?)<\/span>/";
                break;
            case '14':
                continue;
                break;
            case '15':
                continue;
                break;
            case '16':
                continue;
                break;
            case '17':
                $patten="/<span itemprop=\"title\">(.*?)<\/span>/";
                break;
            case '18':
                $patten="/Click to see hundreds more\s*?<a href=\"[^\"]+\">(.*?)<\/a>/";
                break;
            case '19':
                $patten="/<span itemprop=\"title\">(.*?)<\/span>/";
                break;
            case '20':
                continue;
                break;
            case '21':
                $patten="/<span itemprop=\"title\">(.*?)<\/span><\/a>\s*?››/";
                break;
            case '22':
                $patten="/class=\"wg-breadcrumb_anchor\" >(.*?)<\/a>/";
                break;
            case '23':
                continue;
                break;
            case '24':
                continue;
                break;
            case '25':
                $patten="/<a href=\"\/[^\"]+\">(.*?)<\/a><span>&gt;&gt;<\/span>/";
                break;
            case '26':
                continue;
                break;
            case '27':
                $patten="";
                break;
            case '28':
                $patten="/class=\"breadcrumbs\">\s*?<div>\s*?<a href=\"\/\"><span>Accueil<\/span><\/a>\s*?<\/div>\s*?<div>\s*?<a href=\"[^\"]+\"><span>(.*?)<\/span>/";
                break;
            case '29':
                continue;
                break;
            case '30':
                $patten="/<span itemprop=\"title\">(.*?)<\/span><\/a>/";
                break;
            case '31':
                $patten="/<span itemprop=title>(.*?)<\/span><\/a>/";
                break;
            case '32':
                $patten="/<span class=\"text\" itemprop=\"name\">(.*?)<\/span><\/a>/";
                break;
            case '33':
                $patten="/<div class=\"title\">Categoria<\/div>\s+?<ul class=\"list-unstyled dropdowned\">\s+?<li><a href=\"\/categoria[^\"]+\">(.*?)<\/a>/";
                break;
            case '34':
                continue;
                break;
            case '35':
                continue;
                break;
            case '36':
                continue;
                break;
            case '37':
                continue;
                break;
            case '38':
                continue;
                break;
            case '39':
                $patten="/<a href=\"\/[^\"]+\">(.*?)<\/a><span class=\"s\">/";
                break;
            case '40':
                $patten="/class=\"text-color--meta\" href=\"[^\"]+\">(.*?)<\/a>/";
                break;
            case '41':
                continue;
                break;
            case '42':
                continue;
                break;
            case '43':
                $patten="/class=\"breadcrumb-node\"><a title=\"[^\"]+\" href=\"\/[^\"]+\"><span>(.*?)<\/span>/";
                break;
            case '44':
                continue;
                break;
            case '45':
                continue;
                break;
            case '46':
                $patten="/<li><a href=\"http:\/\/www.acties.nl\/categorie\/[^\"]+\">(.*?)<\/a><\/li>/";
                break;
            case '47':
                continue;
                break;
            case '48':
                continue;
                break;
            case '49':
                continue;
                break;
            case '50':
                $patten="/<span itemprop=\"name\">([\s\S]+?)<\/span>\s+<\/a>\s+<meta itemprop=\"position\" content=\"3\">/";
                break;
            case '51':
                $patten="/class=\"breadcrumb-node\"><a title=\"[^\"]+\" href=\"[^\"]+\"><span>(.*?)<\/span><\/a><\/li>/";
                break;
            case '52':
                $patten="/<span itemprop=\"name\">([\s\S]+?)<\/span>\s+<\/a>\s+<meta itemprop=\"position\" content=\"3\">/";
                break;
            case '53':
                continue;
                break;
            case '54':
                $patten="/<span itemprop=\"name\">(.*?)<\/span>\s+<meta itemprop=\"position\" content=\"2\" \/>/";
                break;
            case '55':
                continue;
                break;
            case '56':
                $patten="/itemprop=\"url\"><span itemprop=\"title\">(.*?)<\/span><\/a>/";
                break;
            case '57':
                continue;
                break;
            case '58':
                $patten="/itemprop=\"url\"><span itemprop=\"title\">(.*?)<\/span><\/a>/";
                break;
        }

        if(empty($patten)){
            continue;
        }
        preg_match_all($patten,$html_content,$match_cate,PREG_SET_ORDER);
        if(!empty($match_cate[0])){
            $cate=trim(strip_tags($match_cate[count($match_cate)-1][1]));
            if(strlen($cate)==0||strlen($cate)>100){
                continue;
            }

            if(!empty($cate)){
//			查询category是否有值
                $cate_ck_sql="select ID from cp_store_category where Name='".addslashes($cate)."'";
                $cate_map_id=$db->getFirstRow($cate_ck_sql);
                if(!empty($cate_map_id)){

                    //添加到store_cate_mapping，建立关系
                    $StoreCateId=$cate_map_id['ID'];
                    $store_cate_mapping_sql="insert into cp_store_cate_mapping (StoreId,StoreCateId,CompetitorId,CompetitorStoreId,AddTime) VALUES (".$v['ID'].",$StoreCateId,".$item['CompetitorId'].','.$item['ID'].",'".date('Y-m-d H:i:s')."')";
//			echo $store_cate_mapping_sql;die;
                    $db->query($store_cate_mapping_sql);
                }else{
                    //添加到store_category
                    $cate_sql="insert into cp_store_category (Name,AddTime,Country) VALUES ('".addslashes($cate)."','".date('Y-m-d H:i:s')."','".$v["Country"]."')";
                    $result=$db->query($cate_sql);

                    //添加到store_cate_mapping，建立本地对应关系
                    $StoreCateId=$db->getLastInsertId();
                    $store_cate_mapping_sql="insert into cp_store_cate_mapping (StoreId,StoreCateId,CompetitorId,CompetitorStoreId,AddTime) VALUES (".$v['ID'].",$StoreCateId,".$item['CompetitorId'].','.$item['ID'].",'".date('Y-m-d H:i:s')."')";
                    $db->query($store_cate_mapping_sql);
                }
            }
            break;
        }

    }

}

echo "end time:".date("Y-m-d H:i:s");
