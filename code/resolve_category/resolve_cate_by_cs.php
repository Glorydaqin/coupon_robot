<?php
/*根据分析html*/

include_once dirname(dirname(dirname(__FILE__))).DIRECTORY_SEPARATOR . 'etc/initiate.php';
echo "start time:".date("Y-m-d H:i:s")."\n";
$num = checkScriptProcessCount(basename(__FILE__));
if($num > 1) exit("Competitor ID: catch ing !\n");


set_time_limit(0);

$db = new Mysql(DB_NAME, DB_HOST, DB_USER, DB_PWD);


$cs_sql="select c.ID,c.CompetitorId,c.StoreId,cc.FilePath from cp_competitor_store c 
left join cp_competitor_catch_file cc on cc.CompetitorStoreId=c.ID 
where c.Category is null ORDER BY cc.AddTime desc limit 4000";
$cs_list=$db->getRows($cs_sql);


//循环所有抓取的文件
foreach ($cs_list as $item){

    echo "get content ".$item['FilePath'].PHP_EOL;
    $html_content=@file_get_contents($item['FilePath']);
    if(empty($html_content)){
        echo 'empty content !'.PHP_EOL;
        continue;
    }
    //匹配分类
    $cate='';
    $match_cate='';
    switch ($item['CompetitorId']){
        case 4:

            $match_cate = Selector::select($html_content,'*//div[@class="side-breadcrumbs"]/a[last()]');
            break;
        case 13:

            $match_cate = Selector::select($html_content,'*//div[@class="t_breadcrumbs"]/a[last()]');
            break;
        case 18:

            $match_cate = Selector::select($html_content,'*//aside[@class="block categories"]/ul/li[last()]/a');
            break;
        case 19:

            $match_cate = Selector::select($html_content,'*//nav[@class="breadcrumbs small-text"]/span[@itemprop][last()]/a');
            break;
    }

    if(empty($match_cate)){
        echo "match empty !".PHP_EOL;
        continue;
    }else{
        $cate = addslashes(trim(strip_tags($match_cate)));
        if(strlen($cate)==0||strlen($cate)>100){
            echo "large category !\n";
            continue;
        }

        //写入数据库
        echo "up sql cate: ".$cate.PHP_EOL;
        $up_sql = "update cp_competitor_store set Category = '{$cate}' where ID = {$item['ID']}";
        $db->query($up_sql);
    }

}

echo "end time:".date("Y-m-d H:i:s");
exit();