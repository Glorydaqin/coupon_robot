<?php
/*爬取code进程*/
include_once dirname(dirname(dirname(__FILE__))).DIRECTORY_SEPARATOR . 'etc/initiate.php';

$num = checkScriptProcessCount(basename(__FILE__));
if($num > 1) exit("catch code ing !\n");

echo "start time:".date("Y-m-d H:i:s")."\n";

set_time_limit(3600);
$db = new Mysql(DB_NAME, DB_HOST, DB_USER, DB_PWD);
//是否需要优先抓取没有当前没有code的？需要思考下
$sqlQuery="SELECT ID,CouponCodeUrl,CompetitorId from cp_competitor_store_coupon WHERE IsUpdateCodeUrl='1' AND ErrorTime <8 ORDER BY LastChangeTime ASC LIMIT 1000";
//echo $sqlQuery;
$list = $db->getRows($sqlQuery,"ID");

$up_cs_ids = array_keys($list);
if(empty($up_cs_ids))exit("No Data Update !\n");
$sql="update cp_competitor_store_coupon set IsUpdateCodeUrl= '0' where ID in (".join(",",$up_cs_ids).")";
$db->query($sql);
$db->close();
foreach($list as $row){
    //检测是否存在
    if(file_exists(dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . "lib/competitor/Class.Competitor{$row['CompetitorId']}.php")){
        $check_file_name = INCLUDE_ROOT."code/robot_code/catch_cs_code_obj.php";
    }else{
        $check_file_name = INCLUDE_ROOT."code/robot_code/catch_cs_code_id.php";
    }

	$for_count = checkScriptProcessCount($check_file_name);
	if($for_count > CatchCodeMaxThread) {
        sleep($for_count-CatchCodeMaxThread);
	}
	if (trim($row['CouponCodeUrl']) != "")
	{
		$row['CouponCodeUrl']=base64_encode($row['CouponCodeUrl']);
		$cmd = "php ".$check_file_name." {$row['CompetitorId']} {$row['ID']} '{$row['CouponCodeUrl']}'";

		$cmd = $cmd." >/dev/null 2>&1 &";
		exec($cmd);
	}

	usleep(500);
}
echo "end time:".date("Y-m-d H:i:s")."\n";