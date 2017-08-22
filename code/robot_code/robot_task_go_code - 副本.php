<?php
/*爬取code进程*/
include_once dirname(dirname(dirname(__FILE__))).DIRECTORY_SEPARATOR . 'etc/initiate.php';

$num = checkScriptProcessCount(basename(__FILE__));
if($num > 1) exit("code catch ing !\n");

echo "start time:".date("Y-m-d H:i:s")."\n";

set_time_limit(1800);
$db = new Mysql(DB_NAME, DB_HOST, DB_USER, DB_PWD);
$sqlQuery="SELECT ID,CompetitorId,CouponCodeUrl from cp_competitor_store_coupon WHERE IsUpdateCodeUrl='1' AND ErrorTime <8 ORDER BY ID DESC LIMIT 1000";
//echo $sqlQuery;
$list = $db->getRows($sqlQuery,"ID");

$up_cs_ids = array_keys($list);
if(empty($up_cs_ids))exit("No Data Update !\n");
$sql="update cp_competitor_store_coupon set IsUpdateCodeUrl= '0' where ID in (".join(",",$up_cs_ids).")";
$db->query($sql);

foreach($list as $vo){

    $check_file_name = INCLUDE_ROOT."code/robot_code/catch_cs_code_id.php";

    $for_count = checkScriptProcessCount($check_file_name);

    $vo['CouponCodeUrl']=base64_encode($vo['CouponCodeUrl']);
    $cmd = "php ".INCLUDE_ROOT."code/robot_code/catch_cs_code_id.php {$vo['CompetitorId']} {$vo['ID']} '{$vo['CouponCodeUrl']}'";
    $cmd = $cmd." >/dev/null 2>&1 &";

    if($for_count>1){
        //在运行
        while($for_count>1){
            echo "sleep 5\n";
            sleep(5);
            $for_count = checkScriptProcessCount($check_file_name);

            if($for_count<=1){
                //未运行

                echo $cmd.PHP_EOL;
                exec($cmd);
                break;
            }
        }
    }else{
        //未运行
        echo $cmd.PHP_EOL;
        exec($cmd);
    }

}
echo "end time:".date("Y-m-d H:i:s")."\n";