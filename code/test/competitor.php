<?php
/**
 * Created by PhpStorm.
 * User: daqin
 * Date: 2017/8/15
 * Time: 23:06
 */
include_once dirname(dirname(dirname(__FILE__))).DIRECTORY_SEPARATOR . 'etc/initiate.php';
$competitorId = isset($_SERVER["argv"][1])?$_SERVER["argv"][1]:0;
if(empty($competitorId)){
    exit("competitor id empty\n");
}

$db = new Mysql(DB_NAME, DB_HOST, DB_USER, DB_PWD);

$sqlcom="SELECT cf.*,cs.Url FROM cp_competitor_catch_file cf LEFT JOIN cp_competitor_store cs on cf.CompetitorStoreId = cs.ID where cs.CompetitorId = {$competitorId} and cf.IsAvailable = 1 ORDER BY cf.ID desc limit 2";
$comp_list = $db->getRows($sqlcom);

//引入竞争对手类
if(file_exists(dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . "lib/competitor/Class.Competitor{$competitorId}.php")){
    require_once dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . "lib/competitor/Class.Competitor{$competitorId}.php";
    //初始化类
    $class_name = "Competitor{$competitorId}";

    foreach ($comp_list as $item){

        $htmlContent = @file_get_contents($item['FilePath']);

        $competitor = new $class_name($htmlContent,$item['Url']);

        echo "{$competitor->currentUrl} is {$competitor->country} ,is update code :{$competitor->isUpdateCodeUrl} ,wrong strs are : ".implode(" | ",$competitor->wrongCouponStrs)." , similar store preg is {$competitor->pregSimStore}\n";

        echo "temp store info :\n";
        $arr = $competitor->getTempStoreInfo();
        d($arr);

        echo "store info :\n";
        $storeInfo = $competitor->getStoreInfo();
        d($storeInfo);

        $coupon_list = $competitor->getCoupons();
        $count_coupon_list = count($coupon_list);
        echo "coupon list : count {$count_coupon_list}\n";

        echo "first coupon is :\n";
        $first_item = array_shift($coupon_list);
        d($first_item);
    }


}else{
    die("competitor is not exist!");
}