<?php
/*
 * 各种问题定期修复
 * */
include_once dirname(dirname(dirname(__FILE__))).DIRECTORY_SEPARATOR . 'etc/initiate.php';
$num = checkScriptProcessCount(basename(__FILE__));  //检测是否多开
if($num > 1) exit( "\t".basename(__FILE__).":  ing !\n");
set_time_limit(0);
@ini_set('memory_limit', '256M');
echo "start time:".date("Y-m-d H:i:s")."\n";

$db = new Mysql(DB_NAME, DB_HOST, DB_USER, DB_PWD);

//const
$catch_path = DIR_CATCH_HTML;
$day_del_file =  DAY_DEL_FILE; //删除多少天了以前的数据
$day_del_ip =  DAY_DEL_IP; //删除2天前ip

if(date('d')=='1'){
    //月任务

}
if(date("w")=='1'){
    //周任务
    //重置临时抓取错误
    resetTempStore($db);
    resetCompetitorStore($db);
    //不删除404页面数据，删除了网站也会404
    //delNotFoundUrl($db);
    delCatchFileTable($db);
}
//天任务
//删除文件
del_files_by_root_path($catch_root);
//重置IsUpdate=1
resetIsUpdate($db);
//删除ip信息
del_ip_info($db);


echo "end time:".date("Y-m-d H:i:s")."\n";


//删除ip
function del_ip_info(&$db){
    global $day_del_ip;
    $sql="delete from cp_ip_info where AddTime < '{$day_del_ip}' " ;
    $db->query($sql);
    echo date('Y-m-d H:i:s')."----删除两天前ip信息完成\n";
}

//根据根目录删除相应文件
function del_files_by_root_path($catch_path = "/home/ubuntu/cp_robot/catch_html/"){

	$foder_arr = scandir($catch_path);
	if(!empty($foder_arr)){
		foreach ($foder_arr as $o){
			if($o == "." || $o == "..") continue;
			$tmp_path = $catch_path.$o;
	
			$tmp_foder_arr = scandir($tmp_path);
			if(!empty($tmp_foder_arr)){
				foreach ($tmp_foder_arr as $t){
					if($t == "." || $t == "..") continue;
					if($t == "check_ip" || $t == "temp_go" || $t == "catch_code"){
						$tmp_path_sub = $tmp_path."/".$t;
						$sub_tmp_foder_arr = scandir($tmp_path_sub);
	
						del_file($tmp_path_sub,$sub_tmp_foder_arr);
					}else{
						del_file($tmp_path,$tmp_foder_arr);
					}
	
				}
			}
	
		}
	}

    echo date('Y-m-d H:i:s')."----删除抓取文件完成\n";
}

//删除操作
function del_file($path_root ,$files){
	global $day_del_file;
	if(!empty($files)){
		foreach ($files as $f){
			if($f == "." || $f == "..") continue;
			if($f < $day_del_file){
				$del_path = $path_root."/".$f;
				$last_line = system("rm -rf {$del_path}", $retval);
				
			}
		}
	}
}

//重置临时抓取
function resetTempStore(&$db){
    $sql="update cp_temp_competitor_store set ErrorTime=0 where ErrorTime>=6";
    $db->query($sql);
    echo date('Y-m-d H:i:s')."----重置临时抓取错误为0完成\n";
}

//重置可能导致的isupdate=1,超过一天的重置
function resetIsUpdate(&$db){
    $reset_sql="UPDATE cp_competitor_store SET IsUpdate=0,ErrorTime=0 where IsUpdate=1 and (UNIX_TIMESTAMP()-UNIX_TIMESTAMP(LastChangeTime))>(UpdateFrequency+86400)";
    $db->query($reset_sql);
    echo date('Y-m-d H:i:s')."----重置可能导致的isupdate=1,超过一天的重置完成\n";
}

//清除抓取表数据
function delCatchFileTable(&$db){
    $lastMonth=date("Y-m-d",strtotime('-15 day'));
    $reset_sql="DELETE FROM `cp_competitor_catch_file` WHERE (ReaderTimes=1 or IsAvailable=0) and AddTime<'{$lastMonth}'";
    $db->query($reset_sql);
    echo date('Y-m-d H:i:s')."----清除抓取表数据完成\n";
}

//定期清理301/404页面
function delNotFoundUrl(&$db){
    //主要删除competitorStore 和对应的 CompetitorStoreCoupon ,TempCompetitorStore
    $cs_sql="SELECT ID FROM cp_competitor_store where 301Url is not null or Is301 !='1' limit 3000";
    $res=$db->getRows($cs_sql);

    $delCs=$preDelCs="Delete from cp_competitor_store where ID in (";
    $delTemp=$preDelTemp="Delete from cp_temp_competitor_store where CompetitorStoreId in (";
    $delCoupon=$preDelCoupon="Update cp_competitor_store_coupon set IsAvailable=0 and LastChangeTime=now() where CompetitorStoreId in (";
    $i=0;
    foreach ($res as $re){
        $delCs.=$re['ID'].',';
        $delTemp.=$re['ID'].',';
        $delCoupon.=$re['ID'].',';

        if($i % 300 == 0 ){
            $delCs=substr($delCs,0,-1).");";
            $delTemp=substr($delTemp,0,-1).");";
            $delCoupon=substr($delCoupon,0,-1).");";

            $db->query($delTemp);
            $db->query($delCs);
            $db->query($delCoupon);

            $delCs=$preDelCs;
            $delTemp=$preDelTemp;
            $delCoupon=$preDelCoupon;

            sleep(1);
        }

        $i++;
    }
    if($delCs != $preDelCs){
        $delCs=substr($delCs,0,-1).");";
        $delTemp=substr($delTemp,0,-1).");";
        $delCoupon=substr($delCoupon,0,-1).");";

        $db->query($delTemp);
        $db->query($delCs);
        $db->query($delCoupon);
    }
    echo date('Y-m-d H:i:s')."----del 301/404 页面完成\n";
}

//重置抓取错误为10的数据
function resetCompetitorStore(&$db){
    $resetSql = "UPDATE cp_competitor_store SET ErrorTime=0 WHERE ErrorTime=10";
    $db->query($resetSql);

    echo date('Y-m-d H:i:s')."----重置竞争对手抓取错误为10次的数据完成\n";
}
