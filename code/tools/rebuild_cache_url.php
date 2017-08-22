<?php
/**
 * Created by PhpStorm.
 * User: 大秦
 * Date: 2017/8/19
 * Time: 10:21
 */
include_once 'cp_construct.php';
//$num = checkScriptProcessCount(basename(__FILE__));  //检测是否多开
//if($num > 1) exit( "\t".basename(__FILE__).":  ing !\n");
set_time_limit(0);
@ini_set('memory_limit', '128M');
echo "start time:".date("Y-m-d H:i:s")."\n";

$db = new Mysql($content_db['dbname'], $content_db['host'], $content_db['username'], $content_db['password']);

//需要刷新的表
$refresh_db = array(
    'promosgo',
    'promosgo_au',
    'promosgo_uk',
    'promosgo_fr',
    'promosgo_de',
);


$i = 0;
foreach ($refresh_db as $item){
    $web_db = null;
    switch ($item){
        case "promosgo":
            $web_db = new Mysql($promosgo['dbname'], $promosgo['host'], $promosgo['username'], $promosgo['password']);
            break;
        case "promosgo_uk":
            $web_db = new Mysql($promosgo_uk['dbname'], $promosgo_uk['host'], $promosgo_uk['username'], $promosgo_uk['password']);
            break;
        case "promosgo_au":
            $web_db = new Mysql($promosgo_au['dbname'], $promosgo_au['host'], $promosgo_au['username'], $promosgo_au['password']);
            break;
        case "promosgo_de":
            $web_db = new Mysql($promosgo_de['dbname'], $promosgo_de['host'], $promosgo_de['username'], $promosgo_de['password']);
            break;
        case "promosgo_fr":
            $web_db = new Mysql($promosgo_fr['dbname'], $promosgo_fr['host'], $promosgo_fr['username'], $promosgo_fr['password']);
            break;
    }

    $all_web_store = "SELECT object_id,url from c_url where model_type = 'store'";
    $all_web_store_list = $web_db->getRows($all_web_store);

    $sync_table_name = "sync_store_{$item}_mapping";
    $three_days = date("Y-m-d",strtotime("-3 day"));
    foreach ($all_web_store_list as $value) {
        $i++;
        $up_sql = "update {$sync_table_name} set Url = '{$value['url']}',LastCacheTime = '{$three_days}' where MerchantId = {$value['object_id']}";
        $res = $db->query($up_sql);

        if($res){
            echo "deal {$i},up {$value['url']} success\n";
        }else{
            echo "deal {$i},up {$value['url']} false\n";
        }
    }
}