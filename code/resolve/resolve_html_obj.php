<?php
/*根据分析html*/
include_once dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . 'etc/initiate.php';
$competitorId = isset($_SERVER["argv"][1])?$_SERVER["argv"][1]:0;
if(empty($competitorId)){
	exit("competitor id empty\n");
}
$num = checkScriptProcessCount(basename(__FILE__)." ".$competitorId);
if($num > 1) exit("\tCompetitor ID:".$competitorId." catch ing !\n");
echo "start time:".date("Y-m-d H:i:s")."\n";

//避免单数字出错,去掉CompetitorId前缀0
$competitorId=ltrim($competitorId,'0');
require_once dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . "lib/competitor/Class.Competitor{$competitorId}.php";
set_time_limit(1800);

$db = new Mysql(DB_NAME, DB_HOST, DB_USER, DB_PWD);
$sql="SELECT ccf.*,cs.CompetitorId,cs.Url from cp_competitor_catch_file  ccf LEFT JOIN cp_competitor_store cs ON (ccf.CompetitorStoreId=cs.ID) where cs.CompetitorId={$competitorId} and ccf.MerchantName='".MERCHANT_NAME."' and ccf.ReaderTimes=0 and ccf.isAvailable=1 order by AddTime asc limit 3000";
$list = $db->getRows($sql);
$deal_num=0;

foreach($list as $row){

	if($row['FileSize']<1000){
		$log_str=date("Y-m-d H:i:s")."Url:".$row['Url']." FileId:".$row['ID']." CouponFile Empty\n";
		file_put_contents(LOG_RESOLVE_FILE, $log_str,FILE_APPEND);
		$sql="update cp_competitor_catch_file  set isAvailable=0 where ID={$row['ID']}";
		$db->query($sql);
		continue;
	}
	$htmlContent=file_get_contents($row['FilePath']);

    //竞争对手58页面没有</html>
    if($row['CompetitorId']!=58 && $row['CompetitorId']!=41){
        
        if(substr_count($htmlContent,"html>")==0 || substr_count($htmlContent,"<title>Filter actief</title>")>0 || substr_count($htmlContent,"<title>RouterOS router configuration page</title>")>0){

            $log_str=date("Y-m-d H:i:s")."Url:".$row['Url']." FileId:".$row['ID']." NotEndHtml Empty\n";
            file_put_contents(LOG_RESOLVE_FILE, $log_str,FILE_APPEND);
            $sql="update cp_competitor_catch_file  set isAvailable=0 where ID={$row['ID']}";
            $db->query($sql);
            $sql="update cp_competitor_store set LastChangeTime='2016-01-01 00:00:00' where ID={$row['CompetitorStoreId']}";
            $db->query($sql);
            continue;
        }
    }

	$deal_num++;
	$couponRankMap=array();
	$couponMap=array();
	$couponOnMap=array();
	$sql="select ID,CompetitorId,CouponID,ExpirationDate,CompetitorStoreId,CouponTitle,CouponCode,CouponDesc,CouponCodeUrl,type from cp_competitor_store_coupon where CompetitorStoreId={$row['CompetitorStoreId']}";
	$couponCsMap=$db->getRows($sql,"CompetitorStoreId,CouponID");
	foreach ($couponCsMap as $vo){
		$couponMap[$vo['ID']]=$vo['ID'];
	}

	//初始化类
	$class_name = "Competitor{$competitorId}";
	$competitor = new $class_name($htmlContent,$row['Url']);

    $sqlInsUrl = $sqlInsUrlPre = "insert ignore into cp_temp_competitor_store(StoreUrl,CompetitorId,AddTime) values ";

    $relateLinks = $competitor->getRelateLinks();
    if (!empty($relateLinks)) {
        foreach ($relateLinks as $url) {
            $sqlInsUrl .= "('" .$url . "',{$row['CompetitorId']},'" . date("Y-m-d H:i:s") . "'),";
        }
        if ($sqlInsUrl != $sqlInsUrlPre) {
            $sqlInsUrl = substr($sqlInsUrl, 0, strlen($sqlInsUrl) - 1) .";";
            $db->query($sqlInsUrl);
        }
    }

    //商家信息
    $cs_data_arr = $competitor->getStoreInfo();
    $sql = $pre_sql = "update cp_competitor_store set ";
    foreach ($cs_data_arr as $key => $val) {
        $sql .= " {$key} = '" . addslashes($val) . "' , ";
    }
    if($sql != $pre_sql ){
        $sql.=" LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$row['CompetitorStoreId']}";
        $GLOBALS['db']->query($sql);
    }


    $rank=0;
    $sqlIns=$sqlInsPre="INSERT ignore into cp_competitor_store_coupon (CompetitorStoreId,CompetitorId,CouponID,CouponTitle,GoUrl,CouponCode,ExpirationDate,CouponDesc,Country,MaybeValid,AddTime,type,CouponCodeUrl,IsUpdateCodeUrl) values ";

    $couponList = $competitor->getCoupons();

    foreach ($couponList as $couponData)
    {
        $rank++;
        if(!empty($couponData['CouponID']))
            $couponRankMap[''.$couponData['CouponID']]=$rank;
        if(!isset($couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']])){
            //insert
            $sqlIns.="('{$row['CompetitorStoreId']}','{$row['CompetitorId']}','{$couponData['CouponID']}','{$couponData['CouponTitle']}','{$couponData['GoUrl']}','{$couponData['CouponCode']}','{$couponData['ExpirationDate']}','{$couponData['CouponDesc']}','{$couponData['Country']}','{$couponData['MaybeValid']}','".date("Y-m-d H:i:s")."','{$couponData['type']}','{$couponData['CouponCodeUrl']}','{$couponData['IsUpdateCodeUrl']}'),";
        }else{
            //update
            $expiresVo=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']];
            if($expiresVo['ExpirationDate']!=$couponData['ExpirationDate'] || $expiresVo['CouponTitle']!=$couponData['CouponTitle']  ||  $expiresVo['CouponDesc']!=$couponData['CouponDesc'] || $expiresVo['CouponCodeUrl']!=$couponData['CouponCodeUrl'] || $expiresVo['type']!=$couponData['type']){
                $sqlUp="update cp_competitor_store_coupon set ExpirationDate='{$couponData['ExpirationDate']}',CouponTitle='{$couponData['CouponTitle']}',CouponDesc='{$couponData['CouponDesc']}',CouponCodeUrl='{$couponData['CouponCodeUrl']}',type='{$couponData['type']}',IsUpdateCodeUrl='{$couponData['IsUpdateCodeUrl']}',LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$expiresVo['ID']}";
                $db->query($sqlUp);
            }
            $couponOnMap[]=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']]['ID'];
        }
    }

    if($sqlIns!=$sqlInsPre){
        //真是 写入
        $sqlIns=substr($sqlIns,0,strlen($sqlIns)-1);
        $sqlIns.=";";
        $db->query($sqlIns);
    }

    if(!empty($couponOnMap)){
        $sqlUp="update cp_competitor_store_coupon set isAvailable=1,LastChangeTime='".date("Y-m-d H:i:s")."' where ID in (".implode(",",$couponOnMap).") and isAvailable!=1";
        $db->query($sqlUp);
        $diffArr=array_diff(array_keys($couponMap),$couponOnMap);
        if(!empty($diffArr)){
            $sqlUp="update cp_competitor_store_coupon set isAvailable=0,IsAdd=2,LastChangeTime='".date("Y-m-d H:i:s")."' where ID in (".implode(",",$diffArr).") and isAvailable!=0";
            $db->query($sqlUp);
        }
    }

	$c_couon_ids = array_keys($couponRankMap);
	$ids_str=trim(join(",",$c_couon_ids));
	if(!empty($ids_str)){
		$sql="select ID,CompetitorId,CouponID from cp_competitor_store_coupon where CompetitorStoreId={$row['CompetitorStoreId']} and CouponID in ('".join("','",$c_couon_ids)."')";
		$coupon_rank_list=$db->getRows($sql);
		$sqlc=$sqlc_pre="replace into cp_coupon_rank(CouponId,CompetitorId,Rank,LastChangeTime) values ";
		foreach ($coupon_rank_list as $item){
			$sqlc.="('{$item['ID']}','{$item['CompetitorId']}','".$couponRankMap[''.$item['CouponID']]."','".date("Y-m-d H:i:s")."'),";
		}
		if($sqlc!=$sqlc_pre){
			$sqlc=substr($sqlc,0,strlen($sqlc)-1);
			$sqlc.=";";
			$db->query($sqlc);
		}
	}

	$sql="update cp_competitor_catch_file  set ReaderTimes=(ReaderTimes+1) where ID={$row['ID']}";
	$db->query($sql);
//    system("rm -rf {$row['FilePath']}", $retval);
}

if(!empty($couponOnMap)){
    //更新竞争对手最后解释时间
    $up_competitor_sql="update cp_competitor SET LastResolveTime=now() where ID={$row['CompetitorId']}";
    $db->query($up_competitor_sql);
}
$db->close();

echo "end time:".date("Y-m-d H:i:s")." total:{$deal_num}\n";