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
set_time_limit(1800);

$db = new Mysql(DB_NAME, DB_HOST, DB_USER, DB_PWD);
$sql="SELECT ccf.*,cs.CompetitorId,cs.Url from cp_competitor_catch_file  ccf LEFT JOIN cp_competitor_store cs ON (ccf.CompetitorStoreId=cs.ID) where cs.CompetitorId={$competitorId} and ccf.MerchantName='".MERCHANT_NAME."' and ccf.ReaderTimes=0 and ccf.isAvailable=1 order by AddTime asc limit 1000";
$list = $db->getRows($sql);
$deal_num=0;

foreach($list as $row){

//	if($row['FileSize']<1000){
//		$log_str=date("Y-m-d H:i:s")."Url:".$row['Url']." FileId:".$row['ID']." CouponFile Empty\n";
//		file_put_contents(LOG_RESOLVE_FILE, $log_str,FILE_APPEND);
//		$sql="update cp_competitor_catch_file  set isAvailable=0 where ID={$row['ID']}";
//		$db->query($sql);
//		continue;
//	}

	$htmlContent=file_get_contents($row['FilePath']);
	//$htmlContent=str_replace("'", "\\'", $htmlContent);
	$htmlContent=addcslashes($htmlContent, "\'");

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



	if($row['CompetitorId']==3){
		
		//https://www.promopro.com
//		$htmlContent=iconv("ISO-8859-1","UTF-8",$htmlContent);
		preg_match_all("/href=\"(\/promo-codes-[^\"]+)\"/i", $htmlContent, $matchUrl,PREG_SET_ORDER);
		$sqlInsUrl = $sqlInsUrlPre = "insert ignore into cp_temp_competitor_store(StoreUrl,CompetitorId,AddTime) values ";
		//site_pre
		$site_pre='https://www.promospro.com';
		if (!empty($matchUrl)) {
			foreach ($matchUrl as $url) {
				$sqlInsUrl .= "('" .$site_pre. $url[1] . "',{$row['CompetitorId']},'" . date("Y-m-d H:i:s") . "'),";
			}
			if ($sqlInsUrl != $sqlInsUrlPre) {
				$sqlInsUrl = substr($sqlInsUrl, 0, strlen($sqlInsUrl) - 1);
				$sqlInsUrl .= ";";
				$db->query($sqlInsUrl);
			}
		}

//MetaTitle
		preg_match_all("/<title>([^<]+)<\/title>/", $htmlContent, $matchTitle, PREG_SET_ORDER);
		$cs_data_arr['MetaTitle'] = empty($matchTitle) ? "" : $matchTitle[0][1];
		//keywords No Meta keywords
		$cs_data_arr['MetaKeywords'] = '';
		//meta description
		preg_match_all("/<meta content=\"([^\"]+)\" name=\"description\"/", $htmlContent, $matchMetaDesc, PREG_SET_ORDER);
		$cs_data_arr['MetaDescription'] = empty($matchMetaDesc) ? "" : addslashes( substr(trim(strip_tags($matchMetaDesc[0][1])),0,250));
		//描述
		preg_match_all("/class=\"merchant_description less\">(.*?)<\/p>/", $htmlContent, $matchDesc, PREG_SET_ORDER);
		$cs_data_arr['Description'] = empty($matchDesc)?'':deal_text($matchDesc[0][1]);
//H1
		preg_match_all("/<h1>(.*?)<\/h1>/", $htmlContent, $matchH1, PREG_SET_ORDER);
		$cs_data_arr['H1'] = empty($matchH1) ? "" : deal_text($matchH1[0][1]);
//Merchant Go Url
		preg_match_all("/href=\"([^\"]+)\" target=\"_blank\" rel=\"nofollow\" class=\"button mgos\"/", $htmlContent, $matchGo);
		$cs_data_arr['MerchantGoUrl'] = empty($matchGo[0]) ? "" :$site_pre.$matchGo[1][0];
		//Screen Img
		preg_match_all("/class=\"mgos\"><img alt=\"[^\"]+\"\s+src=\"([^\"]+)\"/",$htmlContent,$matchImg);
		$cs_data_arr['ScreenImg'] = empty($matchImg[0]) ? "" :$matchImg[1][0];

		$sql = $pre_sql = "update cp_competitor_store set ";
		foreach ($cs_data_arr as $key => $val) {
			if (strlen($val) > 100) {
				$val = del_br_space_by_str($val);
			}
			if (strrpos($key, "meta") !== false) {
				$val = substr($val, 0, 250);
			}
			$sql .= " {$key} = '" . addslashes($val) . "' , ";
		}

		if($sql != $pre_sql ){
			$sql.=" LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$row['CompetitorStoreId']}";
			$GLOBALS['db']->query($sql);
		}

		$rank=0;
		$sqlIns=$sqlInsPre="INSERT ignore into cp_competitor_store_coupon (CompetitorStoreId,CompetitorId,CouponID,CouponTitle,GoUrl,CouponCode,ExpirationDate,CouponDesc,Used,Country,MaybeValid,AddTime,type,CouponCodeUrl,IsUpdateCodeUrl) values ";
		//coupons 数据
        preg_match_all("/class=\"list_coupons clear\"([\s\S]*?)\"wrapper2\"/", $htmlContent, $matchValidCoupon);

		$matchCoupon=explode('<article data-cid',$matchValidCoupon[1][0]);
		if (!empty($matchCoupon)) {

			for($i=1;$i<count($matchCoupon);$i++){
				$couponHtml=$matchCoupon[$i];

				$couponData['MaybeValid'] = 1;
				$couponData['Country'] = "US";

				$couponData['CouponID'] =$rank;
				$couponData['CouponTitle']='';
				$couponData['CouponDesc']='';
				$couponData['GoUrl']='';
				$couponData['type']='deal';
				$couponData['Used']='';
				$couponData['CouponRestriction']='';
				$couponData['ExpirationDate']='';
				$couponData['CouponCodeUrl'] = "";
				$couponData['CouponCode']='';
				$couponData['IsUpdateCodeUrl']='0';

                //similar coupon跳过
                if(strripos($couponHtml,'class="sc_label"')){
                    continue;
                }

				//couponId
				preg_match_all("/\"(\d+)\" data-block=\"coupon\"/",$couponHtml,$matchCouponId);
				if(!empty($matchCouponId[0])){
					$couponData['CouponID']=$matchCouponId[1][0];
				}

				//type && code
				preg_match_all("/<span>([^\/]+)<\/span><\/div>/", $couponHtml,$matchType);
				if(isset($matchType[1][0]) && strripos($matchType[1][0] ,'Code')){
					$couponData['IsUpdateCodeUrl']='1';
					$couponData['type']='code';
					$couponData['CouponCodeUrl']=$row['Url'].'?promoid='.$couponData['CouponID'];
				}

				//title
				preg_match_all("/<h3>([\s\S]+?)<\/h3>/",$couponHtml,$matchcoupontitle);
				$couponData['CouponTitle']= empty($matchcoupontitle[0])?'':deal_text($matchcoupontitle[1][0]);
				//gourl
				$couponData['GoUrl']=empty($matchcoupontitle[0])?'':$site_pre.trim(strip_tags($matchcoupontitle[1][0]));

				//desc
				preg_match_all("/class=\"details less\">(.*?)<\/div>/",$couponHtml,$matchCoupondesc);
				$couponData['CouponDesc']=empty($matchCoupondesc[0])?'':deal_text($matchCoupondesc[1][0]);

				//有效期
				preg_match_all("/icon-time\"><\/i>\s*([\d\/]+)\s*<\/li>/",$couponHtml,$coupondate);
				if(!empty($coupondate[0])){
					$arr_time=explode('/',$coupondate[1][0] );
					$couponData['ExpirationDate']=date('Y-m-d',mktime(0,0,0,$arr_time[0],$arr_time[1],$arr_time[2]));
				}
				if(empty($couponData['ExpirationDate'])){
					$couponData['ExpirationDate']='0000-00-00';
				}

				$rank++;
				if(!empty($couponData['CouponID']))
					$couponRankMap[''.$couponData['CouponID']]=$rank;
				if(!isset($couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']])){
					$sqlIns.="('{$row['CompetitorStoreId']}','{$row['CompetitorId']}','{$couponData['CouponID']}','{$couponData['CouponTitle']}','{$couponData['GoUrl']}','{$couponData['CouponCode']}','{$couponData['ExpirationDate']}','{$couponData['CouponDesc']}','{$couponData['Used']}','{$couponData['Country']}','{$couponData['MaybeValid']}','".date("Y-m-d H:i:s")."','{$couponData['type']}','{$couponData['CouponCodeUrl']}','{$couponData['IsUpdateCodeUrl']}'),";
				}else{
					$expiresVo=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']];
					if($expiresVo['ExpirationDate']!=$couponData['ExpirationDate'] || $expiresVo['CouponTitle']!=$couponData['CouponTitle']  ||  $expiresVo['CouponDesc']!=$couponData['CouponDesc'] || $expiresVo['CouponCodeUrl']!=$couponData['CouponCodeUrl'] || $expiresVo['type']!=$couponData['type']){
						$sqlUp="update cp_competitor_store_coupon set ExpirationDate='{$couponData['ExpirationDate']}',CouponTitle='{$couponData['CouponTitle']}',CouponDesc='{$couponData['CouponDesc']}',CouponCodeUrl='{$couponData['CouponCodeUrl']}',type='{$couponData['type']}',IsUpdateCodeUrl='{$couponData['IsUpdateCodeUrl']}',LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$expiresVo['ID']}";
						$db->query($sqlUp);
					}
					$couponOnMap[]=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']]['ID'];
				}
			}
			if($sqlIns!=$sqlInsPre){
				$sqlIns=substr($sqlIns,0,strlen($sqlIns)-1);
				$sqlIns.=";";
				$db->query($sqlIns);
			}

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
		//end 3
	}else if($row['CompetitorId']==4){
		//https://www.voucherhoney.co.uk
        $site_pre="https://www.voucherhoney.co.uk";
		//获取竞争对手其他store链接
        preg_match_all("/href=[\"\'](\/merchant[^\"\']+)[\'\"]/", $htmlContent, $matchUrl,PREG_SET_ORDER);
		$sqlInsUrl=$sqlInsUrlPre="insert ignore into cp_temp_competitor_store(StoreUrl,CompetitorId,AddTime) values ";
		foreach ($matchUrl as $url){
            $sqlInsUrl.="('{$site_pre}".$url[1]."',{$row['CompetitorId']},'".date("Y-m-d H:i:s")."'),";
		}
		if($sqlInsUrl!=$sqlInsUrlPre){
			$sqlInsUrl=substr($sqlInsUrl,0,strlen($sqlInsUrl)-1);
			$sqlInsUrl.=";";
			$db->query($sqlInsUrl);
		}

//MetaTitle
        preg_match_all("/<title>([^<]+)<\\/title>/", $htmlContent, $matchTitle,PREG_SET_ORDER);
        $cs_data_arr['MetaTitle'] = empty($matchTitle) ? "" : $matchTitle[0][1];
//keywords No Meta keywords
        $cs_data_arr['MetaKeywords'] = '';
//meta description
        preg_match_all("/<meta content=\"([^\"]+)\" name=\"description\"/", $htmlContent, $matchMetaDesc, PREG_SET_ORDER);
        $cs_data_arr['MetaDescription'] = empty($matchMetaDesc) ? "" : addslashes( substr(trim(strip_tags($matchMetaDesc[0][1])),0,250));
//描述
        preg_match_all("/class=\"desc less\" track=\".*?\">([\s\S]+?)<\/p>/", $htmlContent, $matchDesc,PREG_SET_ORDER);
        $cs_data_arr['Description'] = empty($matchDesc)?'':deal_text($matchDesc[0][1]);
//H1
        preg_match_all("/<h1[^>]*>([^<]+)<\\/h1>/", $htmlContent, $matchH1,PREG_SET_ORDER);
        $cs_data_arr['H1'] = empty($matchH1) ? "" : deal_text($matchH1[0][1]);
//Merchant Go Url
        preg_match_all("/href=\"(\/redirect[^\"]*)\"/", $htmlContent, $matchGo,PREG_SET_ORDER);
        $cs_data_arr['MerchantGoUrl'] = empty($matchGo[0]) ? "" :$site_pre.$matchGo[0][1];
//Screen Img
        preg_match_all("/class=\"m-logo\">\s+<img src=\"(.*?)\"/", $htmlContent, $matchScreenImg,PREG_SET_ORDER);
        $cs_data_arr['ScreenImg'] = empty($matchScreenImg[0]) ? "" :$matchScreenImg[0][1];

        $sql = $pre_sql = "update cp_competitor_store set ";
        $empty_log_str = "";
        foreach ($cs_data_arr as $key => $val) {
            if (empty($val)) {
                $empty_log_str .= " {$key} --empty --";
            }
            if (strlen($val) > 100) {
                $val = del_br_space_by_str($val);
            }
            if (strrpos($key, "meta") !== false) {
                $val = substr($val, 0, 250);
            }
            $sql .= " {$key} = '" . addslashes($val) . "' , ";
        }

        if($sql != $pre_sql ){
            $sql.=" LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$row['CompetitorStoreId']}";
            $GLOBALS['db']->query($sql);
        }

		//coupon
		//有效的coupon
		//正常没有过期
		$rank=0;
		$sqlIns=$sqlInsPre="INSERT ignore into cp_competitor_store_coupon (CompetitorStoreId,CompetitorId,CouponID,CouponTitle,GoUrl,CouponDesc,CouponRestriction,CouponCode,Used,ExpirationDate,Country,MaybeValid,AddTime,type) values ";
		$htmlContent=explode("expired_title",$htmlContent);
		$tempHtml=$htmlContent[0];
		$arr=explode("<li data-cid=", $tempHtml);
		if(count($arr)>1){
			for($i=1;$i<count($arr);$i++){
				$couponHtml=$arr[$i];

                $couponData['MaybeValid'] = 1;
                $couponData['Country'] = "UK";

                $couponData['CouponID'] =$rank;
                $couponData['CouponTitle']='';
                $couponData['CouponDesc']='';
                $couponData['GoUrl']='';
                $couponData['type']='deal';
                $couponData['Used']='';
                $couponData['CouponRestriction']='';
                $couponData['ExpirationDate']='';
                $couponData['CouponCodeUrl'] = "";
                $couponData['CouponCode']='';
                $couponData['IsUpdateCodeUrl']='0';

                //similar coupon跳过

                //couponId
                preg_match_all('/voucher-(\d+).html/', $couponHtml, $matchCouponId);
                if(!empty($matchCouponId[0])){
                    $couponData['CouponID']=$matchCouponId[1][0];
                }

                //type && code
                preg_match_all("/data-clipboard-text=\"([^\"]*)\"/", $couponHtml, $matchCouponCode);
                if(isset($matchCouponCode[1][0])){
                    $couponData['CouponCode']=$matchCouponCode[1][0];
                    $couponData['type']='code';
                }

                //title
                preg_match_all("/class=\"brief-title\">(.*?)<\/span/", $couponHtml, $matchCouponTitle);
                $couponData['CouponTitle']= empty($matchCouponTitle[0])?'':deal_text($matchCouponTitle[1][0]);

                //gourl
                preg_match_all("/<a href=\"([^\"]*)\"/", $couponHtml, $matchGoUrl);
                if(!empty($matchGoUrl)){
                    $couponData['GoUrl']=$site_pre.$matchGoUrl[1][0];
                }

                //desc
                preg_match_all("/class=\"[codedal]{4}_clr\">(.*?)<\/p>/", $couponHtml, $matchCouponCouponDesc);
                $couponData['CouponDesc']=empty($matchCouponCouponDesc[0])?'':deal_text($matchCouponCouponDesc[1][0]);

                //有效期
                preg_match_all("/expires\s*([^\"]*)\"/", $couponHtml, $matchExpirationDate,PREG_SET_ORDER);
                if(!empty($matchExpirationDate)){
                    $expires=$matchExpirationDate[0][1];
                    if(substr_count($expires, "day")>0){
                        preg_match_all("/in ([0-9]*) day/", $expires, $matchDays,PREG_SET_ORDER);
                        if(!empty($matchDays)){
                            $couponData['ExpirationDate']=addDates($matchDays[0][1]+1);
                        }else{
                            $couponData['ExpirationDate']=null;
                        }
                    }else{
                        $temp_expires=explode("-",$expires);
                        if(count($temp_expires)==3){
                            $expires=$temp_expires[2]."-".$temp_expires[1]."-".$temp_expires[0];
                        }
                        $couponData['ExpirationDate']=dateConv($expires);
                    }
                }
                if(empty($couponData['ExpirationDate'])){
                    $couponData['ExpirationDate']='0000-00-00';
                }

				$rank++;
				if(!empty($couponData['CouponID']))
					$couponRankMap[''.$couponData['CouponID']]=$rank;
				if(!isset($couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']])){
					$sqlIns.="('{$row['CompetitorStoreId']}','{$row['CompetitorId']}','{$couponData['CouponID']}','{$couponData['CouponTitle']}','{$couponData['GoUrl']}','{$couponData['CouponDesc']}','{$couponData['CouponRestriction']}','{$couponData['CouponCode']}','{$couponData['Used']}','{$couponData['ExpirationDate']}','{$couponData['Country']}','{$couponData['MaybeValid']}','".date("Y-m-d H:i:s")."','{$couponData['type']}'),";
				}else{
					$expiresVo=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']];
					if($expiresVo['ExpirationDate']!=$couponData['ExpirationDate'] || $expiresVo['CouponTitle']!=$couponData['CouponTitle']  || $expiresVo['CouponCode']!=$couponData['CouponCode'] || $expiresVo['CouponDesc']!=$couponData['CouponDesc'] || $expiresVo['CouponCodeUrl']!=$couponData['CouponCodeUrl'] || $expiresVo['type']!=$couponData['type']){
						$sqlUp="update cp_competitor_store_coupon set ExpirationDate='{$couponData['ExpirationDate']}',CouponTitle='{$couponData['CouponTitle']}',CouponDesc='{$couponData['CouponDesc']}',CouponCodeUrl='{$couponData['CouponCodeUrl']}',type='{$couponData['type']}',IsUpdateCodeUrl='{$couponData['IsUpdateCodeUrl']}',LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$expiresVo['ID']}";
						$db->query($sqlUp);
					}
					$couponOnMap[]=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']]['ID'];
				}
			}
			if($sqlIns!=$sqlInsPre){
				$sqlIns=substr($sqlIns,0,strlen($sqlIns)-1);
				$sqlIns.=";";
				$db->query($sqlIns);
			}
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
		//end 4
	}else if($row['CompetitorId']==6){
		//获取竞争对手其他store链接  start
        preg_match_all("/href=\"(\/view\/[^\"]+)\"/i", $htmlContent, $matchUrl,PREG_SET_ORDER);
        $sqlInsUrl = $sqlInsUrlPre = "insert ignore into cp_temp_competitor_store(StoreUrl,CompetitorId,AddTime) values ";

//site_pre
        $site_pre='https://www.retailmenot.com';
        if (!empty($matchUrl)) {
            foreach ($matchUrl as $url) {
				$sqlInsUrl .= "('" .$site_pre. $url[1] . "',{$row['CompetitorId']},'" . date("Y-m-d H:i:s") . "'),";
			}
			if ($sqlInsUrl != $sqlInsUrlPre) {
				$sqlInsUrl = substr($sqlInsUrl, 0, strlen($sqlInsUrl) - 1);
				$sqlInsUrl .= ";";
				$db->query($sqlInsUrl);
			}
		}
		$cs_data_arr = array();

//MetaTitle
        preg_match_all("/<title>([^<]+)<\/title>/", $htmlContent, $matchTitle, PREG_SET_ORDER);
        $cs_data_arr['MetaTitle'] = empty($matchTitle) ? "" : $matchTitle[0][1];
//keywords No Meta keywords
        preg_match_all("/<meta name=\"keywords\" content=\"([^\"]+)\"\s*\/>/", $htmlContent, $matchMetaKey, PREG_SET_ORDER);
        $cs_data_arr['MetaKeywords'] = empty($matchMetaKey) ? "" : $matchMetaKey[0][1];
//meta description
        preg_match_all("/<meta name=\"description\" content=\"([^\"]+)\"\s*\/>/", $htmlContent, $matchMetaDesc, PREG_SET_ORDER);
        $cs_data_arr['MetaDescription'] = empty($matchMetaDesc) ? "" : $matchMetaDesc[0][1];
//描述
        preg_match_all("/<p class=\"js-to-truncate\">([^<]+)<\/p>/", $htmlContent, $matchDesc, PREG_SET_ORDER);
        $cs_data_arr['Description'] = empty($matchDesc)?'':trim(del_br_space_by_str(strip_tags($matchDesc[0][1])));
//H1
        preg_match_all("/<h1[^>]*>([^<]+)<\/h1>/", $htmlContent, $matchH1, PREG_SET_ORDER);
        $cs_data_arr['H1'] = empty($matchH1) ? "" : trim(strip_tags($matchH1[0][1]));
//Merchant Go Url
        preg_match_all("/<a href=\"(\/out[^\"]*)\"/", $htmlContent, $matchGo);
        $cs_data_arr['MerchantGoUrl'] = empty($matchGo[0]) ? "" :$site_pre.$matchGo[1][0];
//Screen Img
        preg_match_all("/class=\"merchant-logo js-merchant-logo\">\s*<img src=\"([^\"]+)\" alt=\"[^\"]*\"/",$htmlContent,$matchImg);
        $cs_data_arr['ScreenImg'] = empty($matchImg[0]) ? "" :$matchImg[1][0];


		$sql = $pre_sql = "update cp_competitor_store set ";
		foreach ($cs_data_arr as $key => $val) {
			if (strlen($val) > 100) {
				$val = del_br_space_by_str($val);
			}
			if (strrpos($key, "meta") !== false) {
				$val = substr($val, 0, 250);
			}
			$sql .= " {$key} = '" . addslashes($val) . "' , ";
		}

		if($sql != $pre_sql ){
			$sql.=" LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$row['CompetitorStoreId']}";
			$GLOBALS['db']->query($sql);
		}

		$rank=0;
		$sqlIns=$sqlInsPre="INSERT ignore into cp_competitor_store_coupon (CompetitorStoreId,CompetitorId,CouponID,CouponTitle,GoUrl,CouponCode,ExpirationDate,CouponDesc,Used,Country,MaybeValid,AddTime,type,CouponCodeUrl,IsUpdateCodeUrl) values ";
//    coupons 数据
        $matchList = Selector::select($htmlContent,"//*[@id=\"site-main\"]/div/div[1]/div[2]/ul");
        if (!empty($matchList)) {
            $matchCoupon = explode("class=\"js-offer-toggle",$matchList);

            for($i=1;$i<count($matchCoupon);$i++){
                $couponHtml=$matchCoupon[$i];

                $couponData['MaybeValid'] = 1;
                $couponData['Country'] = "US";

                $couponData['CouponID'] =$rank;
                $couponData['CouponTitle']='';
                $couponData['CouponDesc']='';
                $couponData['GoUrl']='';
                $couponData['type']='deal';
                $couponData['Used']='';
                $couponData['CouponRestriction']='';
                $couponData['ExpirationDate']='';
                $couponData['CouponCodeUrl'] = "";
                $couponData['CouponCode']='';
                $couponData['IsUpdateCodeUrl']=0;


                //跳过相关推荐
                if(stripos($couponHtml,"class=\"conquested-text") || stripos($couponHtml,'class="dfp-banner-container')){
                    continue;
                }

                //couponId
                preg_match_all("/data-offer-id=\"([^\"]+)\"/",$couponHtml,$matchCouponId);
                if(!empty($matchCouponId[0])){
                    $couponData['CouponID']=$matchCouponId[1][0];
                }

                //type && code
                preg_match_all("/data-type=\"code\"/", $couponHtml,$matchType);
                if(!empty($matchType[0])){
                    $couponData['IsUpdateCodeUrl']=1;
                    $couponData['type']='code';
                    $couponData['CouponCodeUrl']=$row['Url']."?c={$couponData['CouponID']}";
                }

                //title
                preg_match_all("/data-analytics-click-location=\"OfferTitle\"\s*?>(.*?)<\/a>/",$couponHtml,$matchcoupontitle);
                $couponData['CouponTitle']= empty($matchcoupontitle[0])?'':deal_text($matchcoupontitle[1][0]);

                //gourl
                preg_match_all("/class=\"logo-wrapper js-outclick-merchant-logo\" href=\"(\/out[^\"]*)\"/",$couponHtml,$matchcouponUrl);
                $couponData['GoUrl']=empty($matchcouponUrl[0])?'':$site_pre.$matchcouponUrl[1][0];

                //desc
                preg_match_all("/<strong>Details:<\/strong>(.*?)<\/p>/",$couponHtml,$matchCoupondesc);
                $couponData['CouponDesc']=empty($matchCoupondesc[0])?'':deal_text($matchCoupondesc[1][0]);

                //有效期
                preg_match_all("#Expires:</strong>(\d{2}/\d{2}/\d{2})</p>#Ui",$couponHtml,$coupondate);

                if(!empty($coupondate[0])){
                    $tmp_date=explode('/',$coupondate[1][0] );
                    $couponData['ExpirationDate']=date('Y-m-d',mktime(0,0,0,$tmp_date[0],$tmp_date[1],$tmp_date[2]));
                }
                if(empty($couponData['ExpirationDate'])){
                    $couponData['ExpirationDate']='0000-00-00';
                }


                $rank++;
				if(!empty($couponData['CouponID']))
					$couponRankMap[''.$couponData['CouponID']]=$rank;
				if(!isset($couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']])){
					$sqlIns.="('{$row['CompetitorStoreId']}','{$row['CompetitorId']}','{$couponData['CouponID']}','{$couponData['CouponTitle']}','{$couponData['GoUrl']}','{$couponData['CouponCode']}','{$couponData['ExpirationDate']}','{$couponData['CouponDesc']}','{$couponData['Used']}','{$couponData['Country']}','{$couponData['MaybeValid']}','".date("Y-m-d H:i:s")."','{$couponData['type']}','{$couponData['CouponCodeUrl']}','{$couponData['IsUpdateCodeUrl']}'),";
				}else{
					$expiresVo=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']];
					if($expiresVo['ExpirationDate']!=$couponData['ExpirationDate'] || $expiresVo['CouponTitle']!=$couponData['CouponTitle']  || $expiresVo['CouponCode']!=$couponData['CouponCode'] || $expiresVo['CouponDesc']!=$couponData['CouponDesc'] || $expiresVo['CouponCodeUrl']!=$couponData['CouponCodeUrl'] || $expiresVo['type']!=$couponData['type']){
						$sqlUp="update cp_competitor_store_coupon set ExpirationDate='{$couponData['ExpirationDate']}',CouponTitle='{$couponData['CouponTitle']}',CouponDesc='{$couponData['CouponDesc']}',CouponCodeUrl='{$couponData['CouponCodeUrl']}',type='{$couponData['type']}',IsUpdateCodeUrl='{$couponData['IsUpdateCodeUrl']}',LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$expiresVo['ID']}";
						$db->query($sqlUp);
					}
					$couponOnMap[]=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']]['ID'];
				}
			}
			if($sqlIns!=$sqlInsPre){
				$sqlIns=substr($sqlIns,0,strlen($sqlIns)-1);
				$sqlIns.=";";
				$db->query($sqlIns);
			}

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
		//end coupons 22
	}else if($row['CompetitorId']==7){
		$sqlInsUrl=$sqlInsUrlPre="insert ignore into cp_temp_competitor_store(StoreUrl,CompetitorId) values ";
		preg_match_all("/href=\"(\/coupon-codes\/[^\"\/]+\/)\"/", $htmlContent, $matchUrl,PREG_SET_ORDER);
		foreach ($matchUrl as $url){
			if(strstr($url[1],"/coupon-codes/categories/")){
				continue;
			}else if(strstr($url[1],"/coupon-codes/go/")){
				continue;
			}
			$sqlInsUrl.="('https://www.coupons.com".$url[1]."',{$row['CompetitorId']}),";
		}
		if($sqlInsUrl!=$sqlInsUrlPre){
			$sqlInsUrl=substr($sqlInsUrl,0,strlen($sqlInsUrl)-1);
			$sqlInsUrl.=";";
			$db->query($sqlInsUrl);
		}

        //MetaTitle
        preg_match_all("/<title>([^<]+)<\\/title>/", $htmlContent, $matchTitle,PREG_SET_ORDER);
        $cs_data_arr['MetaTitle'] = empty($matchTitle[0]) ? "" : $matchTitle[0][1];
        //keywords No Meta keywords
        preg_match_all("/<meta\s*name=\"keywords\"\s*content=\"([^\"]+)\"/", $htmlContent, $matchkeywords, PREG_SET_ORDER);
        $cs_data_arr['MetaKeywords'] = empty($matchkeywords[0]) ? "" : $matchkeywords[0][1];
        //meta description
        preg_match_all("/<meta name=\"description\" content=\"([^\"]+)\"\s*\\/>/", $htmlContent, $matchMetaDesc,PREG_SET_ORDER);
        $cs_data_arr['MetaDescription'] = empty($matchMetaDesc[0]) ? "" : $matchMetaDesc[0][1];
        //描述
        preg_match_all("/<div id=\"pageinfodesc\" itemprop=\"description\" class=\"page-info-description\">([\\w\\W]*)<\/div>/U", $htmlContent, $matchDesc,PREG_SET_ORDER);
        $cs_data_arr['Description'] = empty($matchDesc[0])?'':trim(del_br_space_by_str(strip_tags($matchDesc[0][1])));
//H1
        preg_match_all("/<h1[^>]*>([^<]+)<\\/h1>/", $htmlContent, $matchH1,PREG_SET_ORDER);
        $cs_data_arr['H1'] = empty($matchH1[0]) ? "" : strip_tags($matchH1[0][1]);
//Merchant Go Url
        preg_match_all("/<img class=\"js-redirect\" itemprop=\"logo\" data-url=\"([^\"]*)\"/", $htmlContent, $matchGo,PREG_SET_ORDER);
        $cs_data_arr['MerchantGoUrl'] = empty($matchGo[0]) ? "" :  'https://www.coupons.com/'.$matchGo[0][1];
        //Screen Img
        preg_match_all("/<div class=\"media\"><img class=\"js-redirect\" data-url=\"[^\"]*\" alt=\"[^\"]*\" title=\"[^\"]*\" width=\"[^\"]*\" height=\"[^\"]*\" src=\"([^\"]*)\">/", $htmlContent, $matchImg,PREG_SET_ORDER);
        $cs_data_arr['ScreenImg'] = empty($matchImg[0]) ? "" :'http:'.$matchImg[0][1];

        $sql = $pre_sql = "update cp_competitor_store set ";
        $empty_log_str = "";
        foreach ($cs_data_arr as $key => $val) {
            if (empty($val)) {
                $empty_log_str .= " {$key} --empty --";
            }
            if (strlen($val) > 100) {
                $val = del_br_space_by_str($val);
            }
            if (strrpos($key, "meta") !== false) {
                $val = substr($val, 0, 250);
            }
            $sql .= " {$key} = '" . addslashes($val) . "' , ";
        }

        if($sql != $pre_sql ){
            $sql.=" LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$row['CompetitorStoreId']}";
            $GLOBALS['db']->query($sql);
        }
		
		$htmlContent=explode("<div class=\"expired-coupon\">", $htmlContent);
		$htmlContent=$htmlContent[0];
		
		
		//设置此竞争对手的coupon为失效
		//echo $htmlContent;exit;
		//coupon
		//有效的coupon
		//正常没有过期
		$rank=0;
		$sqlIns=$sqlInsPre="INSERT ignore into cp_competitor_store_coupon (CompetitorStoreId,CompetitorId,CouponID,CouponTitle,GoUrl,CouponCode,ExpirationDate,CouponDesc,Used,Country,MaybeValid,AddTime,type,CouponCodeUrl,IsUpdateCodeUrl) values ";
		preg_match_all("/class=\"ccpod\s*[large|medium]{0,7}\s*[is\\-deal]{0,9}\s*[codes|printable|sales]{0,10}[^\"]*\"\s*podid=\"CCPOD([^_]+)_2\"\s*([\\w\\W]*)<div class=\"btn-block\s*\">(.*)data-ccti=\"(.*)\"/U", $htmlContent, $matchValidCoupon,PREG_SET_ORDER);
		if(!empty($matchValidCoupon)){
			for($i=0;$i<count($matchValidCoupon);$i++){
				$couponHtml=$matchValidCoupon[$i][2];
				$couponData['MaybeValid']=1;
				$couponData['Country']="US";
				$couponData['CouponCode']="";
				$couponData['IsUpdateCodeUrl']="0";
				$matchCouponId=$matchValidCoupon[$i][1];
				$ccti=$matchValidCoupon[$i][4];
				if(!empty($matchCouponId)){
					$couponData['CouponID']=$matchCouponId;
					if(strstr($matchValidCoupon[$i][0],"printable")){
						$couponData['type']="print";
						$couponData['GoUrl']="";
						$couponData['CouponCodeUrl']="";
					}else if(strstr($matchValidCoupon[$i][3],"btn-get-direct-code")){
						$couponData['type']="code";
						$couponData['GoUrl']="https://www.coupons.com/coupon-codes/go/rs?k=".$matchCouponId."_2&pos=1";
						$couponData['CouponCodeUrl']=$row['Url']."?PLID=Media_COTHP&CRID=JCPENNEY&pos=1&lbox=1&".str_replace("k=","cid=",$ccti) ;
						$couponData['IsUpdateCodeUrl']="1";
					}else if(strstr($matchValidCoupon[$i][3],"btn-get-indirect-code")){
						$couponData['type']="deal";
						$couponData['GoUrl']="https://www.coupons.com/coupon-codes/go/rs?k=".$matchCouponId."_2&pos=1";
						$couponData['CouponCodeUrl']="";
					}else{
						$couponData['type']="deal";
						$couponData['GoUrl']="https://www.coupons.com/coupon-codes/go/rs?k=".$matchCouponId."_2&pos=1";
						$couponData['CouponCodeUrl']="";
					}
				}else{
					$couponData['CouponID']="";
					$couponData['GoUrl']="";
					$couponData['CouponCodeUrl']="";
					continue;
				}
					
					
				preg_match_all("/<h3[^>]*>(.*?)<\/h3>/", $couponHtml, $matchCouponTitle,PREG_SET_ORDER);
				if(!empty($matchCouponTitle)){
					$couponData['CouponTitle']=preg_replace("/<[^>]*>/", "", $matchCouponTitle[0][1]);
				}else{
					$couponData['CouponTitle']="";
				}
				$couponData['CouponTitle'] = substr($couponData['CouponTitle'],0,250);
				preg_match_all("/<p class=\"desc\"[^>]*>(.*?)<\/p>/", $couponHtml, $matchCouponDesc,PREG_SET_ORDER);
				if(!empty($matchCouponDesc)){
					$couponData['CouponDesc']=preg_replace("/<[^>]*>/", "", $matchCouponDesc[0][1]);
				}else{
					$couponData['CouponDesc']="";
				}
				$couponData['CouponDesc'] = substr($couponData['CouponDesc'],0,250);
				preg_match_all("/([0-9]+\\/[0-9]+\\/[0-9]+)/", $couponHtml, $matchExpirationDate,PREG_SET_ORDER);
				if(!empty($matchExpirationDate)){
					$expires=$matchExpirationDate[0][1];
					$couponData['ExpirationDate']=dateConv($expires);
				}else{
					$couponData['ExpirationDate']=null;
				}
				if(empty($couponData['ExpirationDate'])) $couponData['ExpirationDate'] = "0000-00-00";
				preg_match_all("/<div class=\"clicks\"> Used ([0-9,]*) times <\/div>/", $couponHtml, $matchCouponUsed,PREG_SET_ORDER);
				if(!empty($matchCouponUsed)){
					$couponData['Used']=str_replace(",", "", $matchCouponUsed[0][1]);
				}else{
					$couponData['Used']=0;
				}
				$rank++;
				if(!empty($couponData['CouponID']))
					$couponRankMap[''.$couponData['CouponID']]=$rank;
				if(!isset($couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']])){
					$sqlIns.="('{$row['CompetitorStoreId']}','{$row['CompetitorId']}','{$couponData['CouponID']}','{$couponData['CouponTitle']}','{$couponData['GoUrl']}','{$couponData['CouponCode']}','{$couponData['ExpirationDate']}','{$couponData['CouponDesc']}','{$couponData['Used']}','{$couponData['Country']}','{$couponData['MaybeValid']}','".date("Y-m-d H:i:s")."','{$couponData['type']}','{$couponData['CouponCodeUrl']}','{$couponData['IsUpdateCodeUrl']}'),";
				}else{
					$expiresVo=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']];
					if($expiresVo['ExpirationDate']!=$couponData['ExpirationDate'] || $expiresVo['CouponTitle']!=$couponData['CouponTitle']  || $expiresVo['CouponCode']!=$couponData['CouponCode'] || $expiresVo['CouponDesc']!=$couponData['CouponDesc'] || $expiresVo['CouponCodeUrl']!=$couponData['CouponCodeUrl'] || $expiresVo['type']!=$couponData['type']){
						$sqlUp="update cp_competitor_store_coupon set ExpirationDate='{$couponData['ExpirationDate']}',CouponTitle='{$couponData['CouponTitle']}',CouponDesc='{$couponData['CouponDesc']}',CouponCodeUrl='{$couponData['CouponCodeUrl']}',type='{$couponData['type']}',IsUpdateCodeUrl='{$couponData['IsUpdateCodeUrl']}',LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$expiresVo['ID']}";
						$db->query($sqlUp);
					}
					$couponOnMap[]=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']]['ID'];
				}
			}
			if($sqlIns!=$sqlInsPre){
				$sqlIns=substr($sqlIns,0,strlen($sqlIns)-1);
				$sqlIns.=";";
				$db->query($sqlIns);
			}
			
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
		//end coupons 7
	}else if($row['CompetitorId']==8){

		preg_match_all("/href=\\\'(\/coupons\/[^\/\']+?)\\\'/i", $htmlContent, $matchUrl,PREG_SET_ORDER);
		$sqlInsUrl = $sqlInsUrlPre = "insert ignore into cp_temp_competitor_store(StoreUrl,CompetitorId,AddTime) values ";
		//site_pre
		$site_pre='https://www.goodsearch.com';
		if (!empty($matchUrl)) {
			foreach ($matchUrl as $url) {
				$sqlInsUrl .= "('" .$site_pre. $url[1] . "',{$row['CompetitorId']},'" . date("Y-m-d H:i:s") . "'),";
			}
			if ($sqlInsUrl != $sqlInsUrlPre) {
				$sqlInsUrl = substr($sqlInsUrl, 0, strlen($sqlInsUrl) - 1);
				$sqlInsUrl .= ";";
				$db->query($sqlInsUrl);
			}
		}


//MetaTitle
		preg_match_all("/<title>([^<]+)<\/title>/", $htmlContent, $matchTitle, PREG_SET_ORDER);
		$cs_data_arr['MetaTitle'] = empty($matchTitle) ? "" : $matchTitle[0][1];
		//keywords No Meta keywords
		preg_match_all("/<meta content=\"([^\"]+)\"\s*name=\"keywords\"/", $htmlContent, $matchMetaKey, PREG_SET_ORDER);
		$cs_data_arr['MetaKeywords'] = empty($matchMetaKey) ? "" : $matchMetaKey[0][1];
		//meta description
		preg_match_all("/<meta content=\"([^\"]+)\"\s*name=\"description\"/", $htmlContent, $matchMetaDesc, PREG_SET_ORDER);
		$cs_data_arr['MetaDescription'] = empty($matchMetaDesc) ? "" : $matchMetaDesc[0][1];
		//描述
		preg_match_all("/<div class=\"copy\" data-clamp=\"6\">([\w\W]*)<\/div>/U", $htmlContent, $matchDesc, PREG_SET_ORDER);
		$cs_data_arr['Description'] = empty($matchDesc)?'':trim(del_br_space_by_str(strip_tags($matchDesc[0][1])));
//H1
		preg_match_all("/<h1 class=\"name\">([^<]+)<\/h1>/", $htmlContent, $matchH1, PREG_SET_ORDER);
		$cs_data_arr['H1'] = empty($matchH1) ? "" : trim(strip_tags($matchH1[0][1]));
//Merchant Go Url
		preg_match_all("/<a href=\"([^\"]*)\" class=\"title\" data-js=\"merchant-link\"/", $htmlContent, $matchGo);
		$cs_data_arr['MerchantGoUrl'] = empty($matchGo[0]) ? "" :$matchGo[1][0];
		if(empty($cs_data_arr['MerchantGoUrl'])){
			preg_match_all("/<li\s*class=\"deal-item\s*filter-coupon\s*[filter\-promo\-code|filter\-deal]{0,18}\s*[filter\-online]{0,18}\"\s*data-deal-id=\"[^\"]+\"[\\w\\W]*data-deal-url=\"([^\"]+)\"/", $htmlContent, $matchGo,PREG_SET_ORDER);
			$cs_data_arr['MerchantGoUrl']=empty($matchGo)?"":$matchGo[0][1];
		}
		//Screen Img
		preg_match_all("/class=\"logo\" height=\"[^\"]*\" src=\"([^\"]*)\"/U",$htmlContent,$matchImg);
		$cs_data_arr['ScreenImg'] = empty($matchImg[0]) ? "" :$matchImg[1][0];
		if($cs_data_arr['ScreenImg']=="//assets.goodsearch.com/assets/good_shop/default-merchant-logo-for-merchant-landing.png"){
			$cs_data_arr['ScreenImg']=null;
		}

		$sql = $pre_sql = "update cp_competitor_store set ";
		foreach ($cs_data_arr as $key => $val) {
			if (strlen($val) > 100) {
				$val = del_br_space_by_str($val);
			}
			if (strrpos($key, "meta") !== false) {
				$val = substr($val, 0, 250);
			}
			$sql .= " {$key} = '" . addslashes($val) . "' , ";
		}

		if($sql != $pre_sql ){
			$sql.=" LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$row['CompetitorStoreId']}";
			$GLOBALS['db']->query($sql);
		}

		$rank=0;
		$sqlIns=$sqlInsPre="INSERT ignore into cp_competitor_store_coupon (CompetitorStoreId,CompetitorId,CouponID,CouponTitle,GoUrl,CouponDesc,CouponRestriction,CouponCode,Used,ExpirationDate,Country,MaybeValid,AddTime,type) values ";
		//coupons 数据
		preg_match_all("/class=\"list\"([\s\S]+?)class=\"breadcrumbs\"/", $htmlContent, $matchValidCoupon);

		if(substr_count($matchValidCoupon[1][0],'<div class="coupon-list expired-coupon-list">')>0){
			$tmp_valid=explode('<div class="coupon-list expired-coupon-list">',$matchValidCoupon[1][0]);
		}else{
			$tmp_valid[0]=$matchValidCoupon[1][0];
		}

		$matchCoupon=explode('<li class="deal-item',$tmp_valid[0]);

		if (!empty($matchCoupon)) {

			for($i=1;$i<count($matchCoupon);$i++){
				$couponHtml=$matchCoupon[$i];

				$couponData['MaybeValid'] = 1;
				$couponData['Country'] = "US";

				$couponData['CouponID'] =$rank;
				$couponData['CouponTitle']='';
				$couponData['CouponDesc']='';
				$couponData['GoUrl']='';
				$couponData['type']='deal';
				$couponData['Used']='';
				$couponData['CouponRestriction']='';
				$couponData['ExpirationDate']='';
				$couponData['CouponCodeUrl'] = "";
				$couponData['CouponCode']='';
				$couponData['IsUpdateCodeUrl']=0;


				//couponId
				preg_match_all("/data-deal-id=\"(\d+)\"/",$couponHtml,$matchCouponId);
				if(!empty($matchCouponId[0])){
					$couponData['CouponID']=$matchCouponId[1][0];
				}else{
					continue;
				}

				//type && code
				preg_match_all("/<span class=\"code\">([^<]+?)<\/span>/", $couponHtml,$matchType);
				if(!empty($matchType[1])){
					$couponData['type']='code';
					$couponData['CouponCode']=$matchType[1][0];
				}else{
					$couponData['type']='deal';
				}

				//title
                preg_match_all("/<span class=\"title\">(.+?)<\/span>/",$couponHtml,$matchcoupontitle);
				$couponData['CouponTitle']= empty($matchcoupontitle[0])?'':deal_text($matchcoupontitle[1][0]);

				//gourl
				preg_match_all("/data-deal-url=\"([^\"]+)\"/",$couponHtml,$matchcouponUrl);
				$couponData['GoUrl']=empty($matchcouponUrl[0])?'':str_replace("&amp;", "&", $matchcouponUrl[1][0]);

				//desc
//            preg_match_all("/<\/a> <p>([^>]+)/",$couponHtml,$matchCoupondesc);
				$couponData['CouponDesc']='';

				//有效期
                preg_match_all("/data-js=\"expires\">expires:\s+(\d+\/\d+\/\d+)</U",$couponHtml,$coupondate);

				if(!empty($coupondate[0])){
					$couponData['ExpirationDate']=date('Y-m-d',strtotime($coupondate[1][0]));
				}
				if(empty($couponData['ExpirationDate'])){
					$couponData['ExpirationDate']='0000-00-00';
				}

				$rank++;

				if(!empty($couponData['CouponID']))
					$couponRankMap[''.$couponData['CouponID']]=$rank;
				if(!isset($couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']])){
					$sqlIns.="('{$row['CompetitorStoreId']}','{$row['CompetitorId']}','{$couponData['CouponID']}','{$couponData['CouponTitle']}','{$couponData['GoUrl']}','{$couponData['CouponDesc']}','{$couponData['CouponRestriction']}','{$couponData['CouponCode']}','{$couponData['Used']}','{$couponData['ExpirationDate']}','{$couponData['Country']}','{$couponData['MaybeValid']}','".date("Y-m-d H:i:s")."','{$couponData['type']}'),";
				}else{
					$expiresVo=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']];
					if($expiresVo['ExpirationDate']!=$couponData['ExpirationDate'] || $expiresVo['CouponTitle']!=$couponData['CouponTitle']  || $expiresVo['CouponCode']!=$couponData['CouponCode'] || $expiresVo['CouponDesc']!=$couponData['CouponDesc'] || $expiresVo['CouponCodeUrl']!=$couponData['CouponCodeUrl'] || $expiresVo['type']!=$couponData['type']){
						$sqlUp="update cp_competitor_store_coupon set ExpirationDate='{$couponData['ExpirationDate']}',CouponTitle='{$couponData['CouponTitle']}',CouponDesc='{$couponData['CouponDesc']}',CouponCodeUrl='{$couponData['CouponCodeUrl']}',type='{$couponData['type']}',IsUpdateCodeUrl='{$couponData['IsUpdateCodeUrl']}',LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$expiresVo['ID']}";
						$db->query($sqlUp);

					}
					$couponOnMap[]=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']]['ID'];
				}
			}
			if($sqlIns!=$sqlInsPre){
				$sqlIns=substr($sqlIns,0,strlen($sqlIns)-1);
				$sqlIns.=";";
				$db->query($sqlIns);
			}
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


		//end 8


	}else if($row['CompetitorId']==9){
		
	//http://couponfollow.com
		//获取竞争对手其他store链接 start
		preg_match_all("/ href=\"(\/site\/[^\"]+)\"/i", $htmlContent, $matchUrl,PREG_SET_ORDER);
		$sqlInsUrl=$sqlInsUrlPre="insert ignore into cp_temp_competitor_store(StoreUrl,CompetitorId,AddTime) values ";
        //site_pre
        $site_pre='https://couponfollow.com';
		foreach ($matchUrl as $url){
			if(strstr($url[1],"/site/out/") || $url[1]=="/site/" || strstr($url[1],"/site/browse")){
				continue;
			}
			$sqlInsUrl.="('".$site_pre.$url[1]."',{$row['CompetitorId']},'".date("Y-m-d H:i:s")."'),";
		}
		if($sqlInsUrl!=$sqlInsUrlPre){
			$sqlInsUrl=substr($sqlInsUrl,0,-1);
			$GLOBALS['db']->query($sqlInsUrl);
		}
		//获取竞争对手其他store链接 end
		
		$cs_data_arr = array();

		preg_match_all("/<title>([^<]+)<\\/title>/", $htmlContent, $matchTitle,PREG_SET_ORDER);
		$cs_data_arr['MetaTitle']=empty($matchTitle)?"":$matchTitle[0][1];
		
		//keywords No Meta keywords
		preg_match_all("/<meta name=\"keywords\" content=\"([^\"]+)\"/", $htmlContent, $matchKeywords,PREG_SET_ORDER);
		$cs_data_arr['MetaKeywords']=empty($matchKeywords)?"":$matchKeywords[0][1];
		
		//meta description
		preg_match_all("/<meta name=\"description\"\s*content=\"([^\"]+)\"/", $htmlContent, $matchMetaDesc,PREG_SET_ORDER);
		$cs_data_arr['MetaDescription']=empty($matchMetaDesc)?"":$matchMetaDesc[0][1];
		
		//描述
		preg_match_all("/<li class=\"about\">[\\w\\W]*<h3>[^<]*<\/h3>[\\w\\W]*<p>([\\w\\W]*)<\/p>/U", $htmlContent, $matchDesc,PREG_SET_ORDER);
		$description=empty($matchDesc)?"":$matchDesc[0][1];
		$cs_data_arr['Description']=preg_replace("/<[^>]*>/", "", $description);
		//H1
		preg_match_all("/<h1[^>]*>([^<]+)<\\/h1>/", $htmlContent, $matchH1,PREG_SET_ORDER);
		$cs_data_arr['H1']=empty($matchH1)?"":$matchH1[0][1];
		//Screen Img
		preg_match_all("/<img class=\"brandlogo\" src=\"([^\"]*)\"/", $htmlContent, $matchScreenImg,PREG_SET_ORDER);
		$cs_data_arr['ScreenImg']=empty($matchScreenImg)?"":$matchScreenImg[0][1];
		//Merchant Go Url
		preg_match_all("/<a href=\"([^\"]*)\" rel=\"nofollow\" title=\"[^\"]*\">[\\w\\W]{0,100}<img class=\"brandlogo\"/", $htmlContent, $matchGo,PREG_SET_ORDER);
		$cs_data_arr['MerchantGoUrl']=empty($matchGo)?"":($site_pre.$matchGo[0][1]);
		$sql = $pre_sql ="update cp_competitor_store set ";
		foreach ($cs_data_arr as $key=>$val){
			if(empty($val)){
				$empty_log_str .=" {$key} --empty --";
			}
			if(strlen($val) > 100){
				$val = del_br_space_by_str($val);
			}
			if(strrpos($key,"meta") !== false){
				$val = substr($val,0,250);
			}
			$sql .= " {$key} = '".addslashes($val)."' , ";
		}

		if($sql != $pre_sql ){
			$sql.=" LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$row['CompetitorStoreId']}";
			$GLOBALS['db']->query($sql);
		}

//    coupons 数据
        $rank=0;
        $sqlIns=$sqlInsPre="INSERT ignore into cp_competitor_store_coupon (CompetitorStoreId,CompetitorId,CouponID,CouponTitle,GoUrl,CouponDesc,CouponRestriction,CouponCode,Used,ExpirationDate,Country,MaybeValid,AddTime,type) values ";
        preg_match_all("/<ul class=\"span8 couponslist\">([\w\W]*)<\/ul>/U", $htmlContent, $matchCouponList,PREG_SET_ORDER);
        $arr=explode('<li id="codecontainer',$matchCouponList[0][1]);
        if(count($arr)>1){
            for($i=1;$i<count($arr);$i++){
                $couponHtml=$arr[$i];
                $couponData['MaybeValid'] = 1;
                $couponData['Country'] = "US";

                $couponData['CouponID'] =$rank;
                $couponData['CouponTitle']='';
                $couponData['CouponDesc']='';
                $couponData['GoUrl']='';
                $couponData['type']='deal';
                $couponData['Used']='';
                $couponData['CouponRestriction']='';
                $couponData['ExpirationDate']='';
                $couponData['CouponCodeUrl'] = "";
                $couponData['CouponCode']='';
                $couponData['IsUpdateCodeUrl']='0';

                //similar coupon跳过

                //couponId
                preg_match_all('/data-id="([^"]*)"/', $couponHtml, $matchCouponId);
                if(!empty($matchCouponId[0])){
                    $couponData['CouponID']=$matchCouponId[1][0];
                    $couponData['CouponTitle']=deal_text(str_replace("￡", "&pound;", $couponData['CouponTitle']));
                }

                //type && code
                preg_match_all("/data-clipboard-text=\"([\w\W]*)\"/U", $couponHtml, $matchCouponCode);
                if(isset($matchCouponCode[1][0])){
                    $couponData['CouponCode']=$matchCouponCode[1][0];
                    $couponData['type']='code';
                }

                //title
                preg_match_all("/<h2>([\s\S]+?)<\/h2>/", $couponHtml, $matchCouponTitle);
                $couponData['CouponTitle']= empty($matchCouponTitle[0])?'':deal_text($matchCouponTitle[1][0]);

                //gourl
                preg_match_all("/href=\"(\/code\/out\/\d+)\"/", $couponHtml, $matchGoUrl);
                if(!empty($matchGoUrl)){
                    $couponData['GoUrl']=$site_pre.$matchGoUrl[1][0];
                }

                //desc
                preg_match_all("/<p class=\"explain\">(.*?)<\/p>/", $couponHtml, $matchCouponCouponDesc);
                if(!empty($matchCouponCouponDesc[0])){
                    $matchCouponCouponDesc[1][0]=str_replace("￡", "&pound;", $matchCouponCouponDesc[1][0]);
                }
                $couponData['CouponDesc']=empty($matchCouponCouponDesc[0])?'':deal_text($matchCouponCouponDesc[1][0]);

                //有效期
                //无

                if(empty($couponData['ExpirationDate'])){
                    $couponData['ExpirationDate']='0000-00-00';
                }

                $rank++;
                if(!empty($couponData['CouponID']))
                    $couponRankMap[''.$couponData['CouponID']]=$rank;
                if(!isset($couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']])){
                    $sqlIns.="('{$row['CompetitorStoreId']}','{$row['CompetitorId']}','{$couponData['CouponID']}','{$couponData['CouponTitle']}','{$couponData['GoUrl']}','{$couponData['CouponDesc']}','{$couponData['CouponRestriction']}','{$couponData['CouponCode']}','{$couponData['Used']}','{$couponData['ExpirationDate']}','{$couponData['Country']}','{$couponData['MaybeValid']}','".date("Y-m-d H:i:s")."','{$couponData['type']}'),";
                }else{
                    $expiresVo=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']];
                    if($expiresVo['ExpirationDate']!=$couponData['ExpirationDate'] || $expiresVo['CouponTitle']!=$couponData['CouponTitle']  || $expiresVo['CouponCode']!=$couponData['CouponCode'] || $expiresVo['CouponDesc']!=$couponData['CouponDesc'] || $expiresVo['CouponCodeUrl']!=$couponData['CouponCodeUrl'] || $expiresVo['type']!=$couponData['type']){
                        $sqlUp="update cp_competitor_store_coupon set ExpirationDate='{$couponData['ExpirationDate']}',CouponTitle='{$couponData['CouponTitle']}',CouponDesc='{$couponData['CouponDesc']}',CouponCodeUrl='{$couponData['CouponCodeUrl']}',type='{$couponData['type']}',IsUpdateCodeUrl='{$couponData['IsUpdateCodeUrl']}',LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$expiresVo['ID']}";
                        $db->query($sqlUp);
                    }
                    $couponOnMap[]=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']]['ID'];
                }
            }
            if($sqlIns!=$sqlInsPre){
                $sqlIns=substr($sqlIns,0,strlen($sqlIns)-1);
                $sqlIns.=";";
                $db->query($sqlIns);
            }
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
		//end9
	}elseif($row['CompetitorId']==11){

		//start 11
//https://www.cuponation.com.au/
//获取竞争对手其他store链接  start
		$rank=0;

		preg_match_all("/<a href=\"\/([^\/\"]+)\"/i", $htmlContent, $matchUrl);
		$sqlInsUrl=$sqlInsUrlPre="insert ignore into cp_temp_competitor_store(StoreUrl,CompetitorId,AddTime) values ";

		if(!empty($matchUrl)){
			foreach ($matchUrl[1] as $url){
				//排斥的域名
				if($url=='allshop'){
					continue;
				}
				$sqlInsUrl.="('https://www.cuponation.com.au/".$url."',{$row['CompetitorId']},'".date("Y-m-d H:i:s")."'),";
			}
			if($sqlInsUrl!=$sqlInsUrlPre){
				$sqlInsUrl=substr($sqlInsUrl,0,-1);
				$GLOBALS['db']->query($sqlInsUrl);
			}
		}
//获取竞争对手其他store链接  end

//cstore 信息
//http://couponfollow.com
        $cs_data_arr = array();

//MetaTitle
        preg_match_all("/<title>([^<]+)<\\/title>/", $htmlContent, $matchTitle,PREG_SET_ORDER);
        $cs_data_arr['MetaTitle']=empty($matchTitle)?"":$matchTitle[0][1];
//keywords No Meta keywords
        preg_match_all("/<meta name=\"keywords\" content=\"([^\"]+)\"/", $htmlContent, $matchKeywords,PREG_SET_ORDER);
        $cs_data_arr['MetaKeywords']=empty($matchKeywords)?"":$matchKeywords[0][1];
//meta description
        preg_match_all("/<meta name=\"description\"\s*content=\"([^\"]+)\"/", $htmlContent, $matchMetaDesc,PREG_SET_ORDER);
        $cs_data_arr['MetaDescription']=empty($matchMetaDesc)?"":$matchMetaDesc[0][1];
//描述
        preg_match_all("/\"cn-retailer-sidebar-text-content\">\s*([^<]+?)\s*</", $htmlContent, $matchDesc,PREG_SET_ORDER);
        $cs_data_arr['Description']=empty($matchDesc)?"":$matchDesc[0][1];
//H1
        preg_match_all("/<h1[^>]*>\s*([^<]+)<\\/h1>/", $htmlContent, $matchH1,PREG_SET_ORDER);
        $cs_data_arr['H1']=empty($matchH1)?"":$matchH1[0][1];
//Screen Img
        preg_match_all("/class=\"cn-retailer-logo-image\"><img src=\"([^\"]+)\"/", $htmlContent, $matchScreenImg,PREG_SET_ORDER);
        $cs_data_arr['ScreenImg']=empty($matchScreenImg)?"":$matchScreenImg[0][1];
//Merchant Go Url
        preg_match_all("/<span data-slug=\"([^\"]+)\"\s+class=\"hover cn-data-link/", $htmlContent, $matchGo,PREG_SET_ORDER);
        $cs_data_arr['MerchantGoUrl']=empty($matchGo)?"":("https://www.cuponation.com.au/redirect-to?url=".$matchGo[0][1]);

//cs数据更新
		$sql = $pre_sql ="update cp_competitor_store set ";
		foreach ($cs_data_arr as $key=>$val){
			if(empty($val)){
				$empty_log_str .=" {$key} --empty --";
			}
			if(strlen($val) > 100){
				$val = del_br_space_by_str($val);
			}
			if(strrpos($key,"meta") !== false){
				$val = substr($val,0,250);
			}
			$sql .= " {$key} = '".addslashes($val)."' , ";
		}

		if($sql != $pre_sql ){
			$sql.=" LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$row['CompetitorStoreId']}";
			$GLOBALS['db']->query($sql);
		}
//更新cs数据完成

//coupon外层
        preg_match_all("/class=\"voucher-list\"[^>]+>([\s\S]+?)class=\"cn-product-level-discount clear\"/",$htmlContent,$coupons,PREG_SET_ORDER);
//coupon列表
        if(!empty($coupons)){
            $matchCoupon=explode('<div data-cn-voucher',$coupons[0][1]);
        }

        $sqlIns=$sqlInsPre="INSERT ignore into cp_competitor_store_coupon (CompetitorStoreId,CompetitorId,CouponID,CouponTitle,GoUrl,CouponCode,ExpirationDate,CouponDesc,Used,Country,MaybeValid,AddTime,type,CouponCodeUrl,IsUpdateCodeUrl) values ";

        if(!empty($matchCoupon)) {
            for($i=1;$i<count($matchCoupon);$i++){
                $couponHtml=$matchCoupon[$i];

                $couponData['MaybeValid'] = 1;
                $couponData['Country'] = "AU";

                $couponData['CouponID'] =$rank;
                $couponData['CouponTitle']='';
                $couponData['CouponDesc']='';
                $couponData['GoUrl']='';
                $couponData['type']='deal';
                $couponData['Used']='';
                $couponData['CouponRestriction']='';
                $couponData['ExpirationDate']='';
                $couponData['CouponCodeUrl'] = "";
                $couponData['CouponCode']='';
                $couponData['IsUpdateCodeUrl']=1;

                //失效coupon跳过
                preg_match_all("/class=\"date-tooltip-base icon-close\"/",$couponHtml,$matchAvailable);
                if(!empty($matchAvailable[0])){
                    continue;
                }
                //couponId
                preg_match_all("/data-voucher-id=\"(\w+)\"/",$couponHtml,$matchCouponId);
                if(!empty($matchCouponId[0])){
                    $couponData['CouponID']=$matchCouponId[1][0];
                }
                //TYPE
                preg_match_all("/<span class=\"text\">View Discount<\/span>/",$couponHtml,$CouponType);
                if(!empty($CouponType[0])){
                    $couponData['type']="deal";
                    $couponData['GoUrl']="https://clickout.cuponation.com.au/clickout/out/id/".$couponData['CouponID'];
                }else{
                    $couponData['type']="code";
                    $couponData['IsUpdateCodeUrl']="1";
                    $couponData['CouponCodeUrl']='https://www.cuponation.com.au/ajax/voucherpopup?id='.$couponData['CouponID'];
                }
                //title
                preg_match_all("/>([^<]+)<\/span><\/h3>/",$couponHtml,$matchcoupontitle);
                $couponData['CouponTitle']= empty($matchcoupontitle[0])?'':deal_text($matchcoupontitle[1][0]);

                //gourl
//            preg_match_all("/data-clickout-target=\"([^\"]+)\"/",$couponHtml,$matchcouponUrl);
                $couponData['GoUrl']= $cs_data_arr['MerchantGoUrl'];

                //desc
                preg_match_all("/cn-description\">([^<]+)</", $couponHtml, $CouponDesc);
                $couponData['CouponDesc']= empty($CouponDesc[0])?'':deal_text($CouponDesc[1][0]);

                //有效期
                $couponData['ExpirationDate'] = "0000-00-00";

				$rank++;
				if (!empty($couponData['CouponID']))
					$couponRankMap['' . $couponData['CouponID']] = $rank;
				if (!isset($couponCsMap[$row['CompetitorStoreId'] . "\t" . $couponData['CouponID']])) {
					$sqlIns .= "('{$row['CompetitorStoreId']}','{$row['CompetitorId']}','{$couponData['CouponID']}','{$couponData['CouponTitle']}','{$couponData['GoUrl']}','{$couponData['CouponCode']}','{$couponData['ExpirationDate']}','{$couponData['CouponDesc']}','{$couponData['Used']}','{$couponData['Country']}','{$couponData['MaybeValid']}','" . date("Y-m-d H:i:s") . "','{$couponData['type']}','{$couponData['CouponCodeUrl']}','{$couponData['IsUpdateCodeUrl']}'),";
				} else {
					$expiresVo = $couponCsMap[$row['CompetitorStoreId'] . "\t" . $couponData['CouponID']];
					if ($expiresVo['ExpirationDate'] != $couponData['ExpirationDate'] || $expiresVo['CouponTitle'] != $couponData['CouponTitle'] || $expiresVo['CouponDesc'] != $couponData['CouponDesc'] || $expiresVo['CouponCodeUrl'] != $couponData['CouponCodeUrl'] || $expiresVo['type'] != $couponData['type']) {
						$sqlUp = "update cp_competitor_store_coupon set ExpirationDate='{$couponData['ExpirationDate']}',CouponTitle='{$couponData['CouponTitle']}',CouponDesc='{$couponData['CouponDesc']}',CouponCodeUrl='{$couponData['CouponCodeUrl']}',type='{$couponData['type']}',LastChangeTime='" . date("Y-m-d H:i:s") . "' where ID={$expiresVo['ID']}";
						$db->query($sqlUp);
					}

					$couponOnMap[] = $couponCsMap[$row['CompetitorStoreId'] . "\t" . $couponData['CouponID']]['ID'];
				}
			}

			if($sqlIns!=$sqlInsPre){
				$sqlIns=substr($sqlIns,0,strlen($sqlIns)-1);
				$sqlIns.=";";
				$db->query($sqlIns);
			}
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
//end 11

	}else if($row['CompetitorId']==13){
		//https://www.ozdiscount.net/
		//获取竞争对手其他store链接  href="/store/en.comebuy.com"
        $site_pre = "https://www.ozdiscount.net";
		preg_match_all("/href=\"\/store\/([^\"]+)\"/i", $htmlContent, $matchUrl);
		$sqlInsUrl = $sqlInsUrlPre = "insert ignore into cp_temp_competitor_store(StoreUrl,CompetitorId,AddTime) values ";

		if (!empty($matchUrl)) {
			foreach ($matchUrl[1] as $url) {

				$sqlInsUrl .= "('{$site_pre}/store/" . $url . "',{$row['CompetitorId']},'" . date("Y-m-d H:i:s") . "'),";
			}
			if($sqlInsUrl!=$sqlInsUrlPre){
				$sqlInsUrl=substr($sqlInsUrl,0,-1);
				$GLOBALS['db']->query($sqlInsUrl);
			}
		}

		$cs_data_arr = array();


//MetaTitle
		preg_match_all("/<title>([^<]+)<\/title>/", $htmlContent, $matchTitle, PREG_SET_ORDER);
		$cs_data_arr['MetaTitle'] = empty($matchTitle) ? "" : $matchTitle[0][1];
		//keywords No Meta keywords
		preg_match_all("/<meta content=\"([^\"]+)\" name=\"keywords\"/", $htmlContent, $matchKeywords, PREG_SET_ORDER);
		$cs_data_arr['MetaKeywords'] = empty($matchKeywords) ? "" : $matchKeywords[0][1];
		//meta description
		preg_match_all("/<meta\s*content=\"([^\"]+)\"\s*name=\"description\"/", $htmlContent, $matchMetaDesc, PREG_SET_ORDER);
		$cs_data_arr['MetaDescription'] = empty($matchMetaDesc) ? "" : $matchMetaDesc[0][1];
		//描述
		preg_match_all("/<p class=\"merchant_description less\">(.*?)\<\/p>/", $htmlContent, $matchDesc, PREG_SET_ORDER);
		$cs_data_arr['Description'] = empty($matchDesc) ? "" : $matchDesc[0][1];
//H1
		preg_match_all("/<h1[^>]*>\s*([^<]+)<\\/h1>/", $htmlContent, $matchH1, PREG_SET_ORDER);
		$cs_data_arr['H1'] = empty($matchH1) ? "" : $matchH1[0][1];
//Screen Img
		preg_match_all("/id=\"merchant_logo\" src=\"([^\"]+)\"/", $htmlContent, $matchScreenImg, PREG_SET_ORDER);
		$cs_data_arr['ScreenImg'] = empty($matchScreenImg) ? "" : $matchScreenImg[0][1];
//Merchant Go Url
		preg_match_all("/href=\"\/redirect-to-merchant-merchant([^\"]+)/", $htmlContent, $matchGo, PREG_SET_ORDER);
		$cs_data_arr['MerchantGoUrl'] = empty($matchGo) ? "" : ("{$site_pre}/redirect-to-merchant-merchant" . $matchGo[0][1]);

		//update cs  info
		$sql = $pre_sql ="update cp_competitor_store set ";
		foreach ($cs_data_arr as $key=>$val){
			if(empty($val)){
				$empty_log_str .=" {$key} --empty --";
			}
			if(strlen($val) > 100){
				$val = del_br_space_by_str($val);
			}
			if(strrpos($key,"Meta") !== false){
				$val = substr($val,0,250);
			}
			$sql .= " {$key} = '".addslashes($val)."' , ";
		}

		if($sql != $pre_sql ){
			$sql.=" LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$row['CompetitorStoreId']}";
			$GLOBALS['db']->query($sql);
		}


		//coupon
		//有效的coupon
		//正常没有过期
		$rank=0;
		$sqlIns=$sqlInsPre="INSERT ignore into cp_competitor_store_coupon (CompetitorStoreId,CompetitorId,CouponID,CouponTitle,GoUrl,CouponDesc,CouponRestriction,CouponCode,Used,ExpirationDate,Country,MaybeValid,AddTime,type) values ";

        $htmlContent=explode("expired_title",$htmlContent);
        $tempHtml=$htmlContent[0];
        $arr=explode("<li data-cid=", $tempHtml);
        if(count($arr)>1){
            for($i=1;$i<count($arr);$i++){
                $couponHtml=$arr[$i];
                $couponData['MaybeValid']=1;
                $couponData['Country']="AU";
                preg_match_all('/voucher-(\d+).html/', $couponHtml, $matchCouponId,PREG_SET_ORDER);
                if(!empty($matchCouponId)){
                    $couponData['CouponID']=$matchCouponId[0][1];
                }else{
                    $couponData['CouponID']="";
                }
                preg_match_all("/data-clipboard-text=\"([^\"]*)\"/", $couponHtml, $matchCouponCode,PREG_SET_ORDER);
                if(!empty($matchCouponCode)){
                    $couponData['CouponCode']=$matchCouponCode[0][1];
                }else{
                    $couponData['CouponCode']="";
                }
                preg_match_all("/<a href=\"([^\"]*)\"/", $couponHtml, $matchGoUrl,PREG_SET_ORDER);
                if(!empty($matchGoUrl)){
                    $couponData['GoUrl']=$site_pre.$matchGoUrl[0][1];
                }else{
                    $couponData['GoUrl']="";
                }
                preg_match_all("/<h3 class=\"[^\"]+?_clr\">(.*?)<\/h3>/", $couponHtml, $matchCouponTitle,PREG_SET_ORDER);
                if(!empty($matchCouponTitle)){
                    $couponData['CouponTitle']=strip_tags($matchCouponTitle[0][1]);
                }else{
                    $couponData['CouponTitle']="";
                }
                $couponData['CouponTitle'] = deal_text(substr($couponData['CouponTitle'],0,250));

                preg_match_all("/expires\s*([^\"]*)\"/", $couponHtml, $matchExpirationDate,PREG_SET_ORDER);
                if(!empty($matchExpirationDate)){
                    $expires=$matchExpirationDate[0][1];
                    if(substr_count($expires, "day")>0){
                        preg_match_all("/in ([0-9]*) day/", $expires, $matchDays,PREG_SET_ORDER);
                        if(!empty($matchDays)){
                            $couponData['ExpirationDate']=addDates($matchDays[0][1]+1);
                        }else{
                            $couponData['ExpirationDate']=null;
                        }
                    }else{
                        $temp_expires=explode("-",$expires);
                        if(count($temp_expires)==3){
                            $expires=$temp_expires[2]."-".$temp_expires[1]."-".$temp_expires[0];
                        }
                        $couponData['ExpirationDate']=dateConv($expires);
                    }
                }else{
                    $couponData['ExpirationDate']=null;
                }
                if(empty($couponData['ExpirationDate'])) $couponData['ExpirationDate'] = "0000-00-00";
                preg_match_all("/<span title=\"([0-9,]*) used\">/", $couponHtml, $matchCouponUsed,PREG_SET_ORDER);
                if(!empty($matchCouponUsed)){
                    $couponData['Used']=str_replace(",", "", $matchCouponUsed[0][1]);
                }else{
                    $couponData['Used']=0;
                }

                preg_match_all("/Restriction:\s*(.*?)\s*</", $couponHtml, $matchCouponRestriction,PREG_SET_ORDER);
                if(!empty($matchCouponRestriction)){
                    $couponData['CouponRestriction']=$matchCouponRestriction[0][1];
                }else{
                    $couponData['CouponRestriction']="";
                }
                $couponData['type']="deal";
                if(!empty($couponData['CouponCode'])){
                    $couponData['type']="code";
                }
                preg_match_all("/<div class=\"description\">([\\w\\W]*)<\\/div>/U", $couponHtml, $matchCouponCouponDesc,PREG_SET_ORDER);
                if(!empty($matchCouponCouponDesc)){
                    $couponData['CouponDesc']=deal_text(preg_replace("/<[^>]*>/", "", $matchCouponCouponDesc[0][1]));
                }else{
                    $couponData['CouponDesc']="";
                }
                $couponData['CouponDesc'] = substr($couponData['CouponDesc'],0,250);
                $couponData['CouponCodeUrl']="";

				$rank++;
				if(empty($couponData['ExpirationDate'])) $couponData['ExpirationDate'] = "0000-00-00";
				if(!empty($couponData['CouponID']))
					$couponRankMap[''.$couponData['CouponID']]=$rank;
				if(!isset($couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']])){
					$sqlIns.="('{$row['CompetitorStoreId']}','{$row['CompetitorId']}','{$couponData['CouponID']}','{$couponData['CouponTitle']}','{$couponData['GoUrl']}','{$couponData['CouponDesc']}','{$couponData['CouponRestriction']}','{$couponData['CouponCode']}','{$couponData['Used']}','{$couponData['ExpirationDate']}','{$couponData['Country']}','{$couponData['MaybeValid']}','".date("Y-m-d H:i:s")."','{$couponData['type']}'),";
				}else{
					$expiresVo=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']];
					if($expiresVo['ExpirationDate']!=$couponData['ExpirationDate'] || $expiresVo['CouponTitle']!=$couponData['CouponTitle']  || $expiresVo['CouponCode']!=$couponData['CouponCode'] || $expiresVo['CouponDesc']!=$couponData['CouponDesc'] || $expiresVo['CouponCodeUrl']!=$couponData['CouponCodeUrl'] || $expiresVo['type']!=$couponData['type']){
						$sqlUp="update cp_competitor_store_coupon set ExpirationDate='{$couponData['ExpirationDate']}',CouponTitle='{$couponData['CouponTitle']}',CouponDesc='{$couponData['CouponDesc']}',CouponCodeUrl='{$couponData['CouponCodeUrl']}',type='{$couponData['type']}',IsUpdateCodeUrl='{$couponData['IsUpdateCodeUrl']}',LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$expiresVo['ID']}";
						$db->query($sqlUp);

					}
					$couponOnMap[]=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']]['ID'];
				}
			}
			if($sqlIns!=$sqlInsPre){
				$sqlIns=substr($sqlIns,0,strlen($sqlIns)-1);
				$sqlIns.=";";
				$db->query($sqlIns);
			}
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
		//end 13
	}else if($row['CompetitorId']==14){

		//获取竞争对手其他store链接
		preg_match_all("/href\=\"\/interests\/([^\/]+)\/coupons\/([^\"\#]+)\"/i", $htmlContent, $matchUrl,PREG_SET_ORDER);
		$sqlInsUrl = $sqlInsUrlPre = "insert ignore into cp_temp_competitor_store(StoreUrl,CompetitorId,AddTime) values ";

		if (!empty($matchUrl)) {
			foreach ($matchUrl as $url) {

				$sqlInsUrl .= "('http://www.thebargainavenue.com.au/interests/" . $url[1].'/coupons/'.$url[2] . "',{$row['CompetitorId']},'" . date("Y-m-d H:i:s") . "'),";

			}
			if($sqlInsUrl!=$sqlInsUrlPre){
				$sqlInsUrl=substr($sqlInsUrl,0,-1);
				$GLOBALS['db']->query($sqlInsUrl);
			}
		}

		$cs_data_arr = array();


//MetaTitle
        preg_match_all("/<title>([^<]+)<\/title>/", $htmlContent, $matchTitle, PREG_SET_ORDER);
        $cs_data_arr['MetaTitle'] = empty($matchTitle[0]) ? "" : $matchTitle[0][1];
        //keywords No Meta keywords
        preg_match_all("/<meta name=\"keywords\" content=\"([^\"]+)\"/", $htmlContent, $matchKeywords, PREG_SET_ORDER);
        $cs_data_arr['MetaKeywords'] = empty($matchKeywords[0]) ? "" : $matchKeywords[0][1];
        //meta description
        preg_match_all("/<meta\s*name=\"description\"\s*content=\"([^\"]+)\"/", $htmlContent, $matchMetaDesc, PREG_SET_ORDER);
        $cs_data_arr['MetaDescription'] = empty($matchMetaDesc[0]) ? "" : $matchMetaDesc[0][1];
        //描述
        preg_match_all("/<td>(.*?)<br \/><br \/><!-- social links start -->/", $htmlContent, $matchDesc, PREG_SET_ORDER);
        $cs_data_arr['Description'] = empty($matchDesc[0]) ? "" : preg_replace("/<[^>]*>/", "",$matchDesc[0][1]);
//H1
        preg_match_all("/<h1 itemprop=\"name\">([^<]+)<\/h1>/", $htmlContent, $matchH1, PREG_SET_ORDER);
        $cs_data_arr['H1'] = empty($matchH1[0]) ? "" : trim($matchH1[0][1]);
//Screen Img
        preg_match_all("/Go to Store<\/div>\s+<br \/> <img src=\"([^\"]+)/", $htmlContent, $matchScreenImg, PREG_SET_ORDER);
        $cs_data_arr['ScreenImg'] = empty($matchScreenImg[0]) ? "" : $matchScreenImg[0][1];
//Merchant Go Url
        preg_match_all("/href=\"([^\"]+)\" target=\"_blank\" rel=\"nofollow\">\s+<div class=\"stealbuttondec/", $htmlContent, $matchGo, PREG_SET_ORDER);
        $cs_data_arr['MerchantGoUrl'] = empty($matchGo[0]) ? "" : ("http://www.thebargainavenue.com.au" . $matchGo[0][1]);

		//update cs  info
		$sql = $pre_sql ="update cp_competitor_store set ";
		foreach ($cs_data_arr as $key=>$val){
			if(empty($val)){
				$empty_log_str .=" {$key} --empty --";
			}
			if(strlen($val) > 100){
				$val = del_br_space_by_str($val);
			}
			if(strrpos($key,"Meta") !== false){
				$val = substr($val,0,250);
			}
			$sql .= " {$key} = '".addslashes($val)."' , ";
		}

		if($sql != $pre_sql ){
			$sql.=" LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$row['CompetitorStoreId']}";
			$GLOBALS['db']->query($sql);
		}

		//coupon
		//有效的coupon
		//正常没有过期
		$rank=0;
        $sqlIns=$sqlInsPre="INSERT ignore into cp_competitor_store_coupon (CompetitorStoreId,CompetitorId,CouponID,CouponTitle,GoUrl,CouponCode,ExpirationDate,CouponDesc,Used,Country,MaybeValid,AddTime,type,CouponCodeUrl,IsUpdateCodeUrl) values ";

//    coupons 数据
        preg_match_all("/rt-mainbody-wrapper rt-grid-6 rt-push-3([\s\S]+)rt-sidebar-wrapper rt-grid-3 rt-pull-6/", $htmlContent, $matchValidCoupon, PREG_SET_ORDER);

        $tempHtml = $matchValidCoupon[0][1];
        $arr = explode("<!-- social links ends -->", $tempHtml);
        $arr=substr($arr[1],70,-1);
        $matchCoupon=explode("<div class=\"clr\">",$arr);

        if (!empty($matchCoupon)) {

            for($i=0;$i<count($matchCoupon)-1;$i++){
                $couponHtml=$matchCoupon[$i];

                $couponData['MaybeValid'] = 1;
                $couponData['Country'] = "AU";

                $couponData['CouponID'] =$rank;
                $couponData['CouponTitle']='';
                $couponData['CouponDesc']='';
                $couponData['GoUrl']='';
                $couponData['type']='deal';
                $couponData['Used']='';
                $couponData['CouponRestriction']='';
                $couponData['ExpirationDate']='';
                $couponData['CouponCodeUrl'] = "";
                $couponData['CouponCode']='';
                $couponData['IsUpdateCodeUrl']=0;

                //couponId  页面id有重复值

                //type
                preg_match_all("/window.prompt\(\\\'Copy this coupon into cart-checkout for discount\\\',\\\'([^\']+)\\\'\)/",$couponHtml,$matchType);
                if(!empty($matchType[0])){
                    $couponData['type']='code';
                    $couponData['CouponCode']=$matchType[1][0];
                }

                //title
                preg_match_all("/td>(.*)<\/td>/",$couponHtml,$matchcoupontitle);
                $couponData['CouponTitle']= empty($matchcoupontitle[0])?'':deal_text($matchcoupontitle[1][0]);

                //gourl
                preg_match_all("/href=\"(\/redirect\/[^\"]+)\"/",$couponHtml,$matchGo);
                $couponData['GoUrl'] = empty($matchGo[0])?'':'http://www.thebargainavenue.com.au'.$matchGo[1][0];

                //desc
                $couponData['CouponDesc']=$couponData['CouponTitle'];

                //有效期
                preg_match_all("/<strong>Exp:\s*([^<]+)<br\s*\/>/",$couponHtml,$coupondate);
                if(!empty($coupondate[0])){
                    if( $coupondate[1][0]=='Ended'){
                        continue;
                    }
                    $couponData['ExpirationDate']=dateConv($coupondate[1][0]);
                }

                if(empty($couponData['ExpirationDate'])){
                    $couponData['ExpirationDate']='0000-00-00';
                }
                if(strtotime($couponData['ExpirationDate'])<time() && $couponData['ExpirationDate']!='0000-00-00' ){
                    continue;
                }

                $rank++;
                if(!empty($couponData['CouponID']))
                    $couponRankMap[''.$couponData['CouponID']]=$rank;
                if(!isset($couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']])){
                    $sqlIns.="('{$row['CompetitorStoreId']}','{$row['CompetitorId']}','{$couponData['CouponID']}','{$couponData['CouponTitle']}','{$couponData['GoUrl']}','{$couponData['CouponCode']}','{$couponData['ExpirationDate']}','{$couponData['CouponDesc']}','{$couponData['Used']}','{$couponData['Country']}','{$couponData['MaybeValid']}','".date("Y-m-d H:i:s")."','{$couponData['type']}','{$couponData['CouponCodeUrl']}','{$couponData['IsUpdateCodeUrl']}'),";
                }else{
                    $expiresVo=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']];
                    if($expiresVo['ExpirationDate']!=$couponData['ExpirationDate'] || $expiresVo['CouponTitle']!=$couponData['CouponTitle']  ||  $expiresVo['CouponDesc']!=$couponData['CouponDesc'] || $expiresVo['CouponCodeUrl']!=$couponData['CouponCodeUrl'] || $expiresVo['type']!=$couponData['type']){
                        $sqlUp="update cp_competitor_store_coupon set ExpirationDate='{$couponData['ExpirationDate']}',CouponTitle='{$couponData['CouponTitle']}',CouponDesc='{$couponData['CouponDesc']}',CouponCodeUrl='{$couponData['CouponCodeUrl']}',type='{$couponData['type']}',IsUpdateCodeUrl='{$couponData['IsUpdateCodeUrl']}',LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$expiresVo['ID']}";
                        $db->query($sqlUp);
                    }
                    $couponOnMap[]=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']]['ID'];
                }
            }
            if($sqlIns!=$sqlInsPre){
                $sqlIns=substr($sqlIns,0,strlen($sqlIns)-1);
                $sqlIns.=";";
                $db->query($sqlIns);
            }
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
		//end 14
	}else if($row['CompetitorId']==15){
		preg_match_all("/href\=\"\/store\/([^\?\/\"]+)\"/i", $htmlContent, $matchUrl,PREG_SET_ORDER);
		$sqlInsUrl = $sqlInsUrlPre = "insert ignore into cp_temp_competitor_store(StoreUrl,CompetitorId,AddTime) values ";
		foreach ($matchUrl as $url){
			$sqlInsUrl .= "('https://www.topbargains.com.au/store/" . $url[1] . "',{$row['CompetitorId']},'" . date("Y-m-d H:i:s") . "'),";
		}
		if($sqlInsUrl!=$sqlInsUrlPre){
			$sqlInsUrl=substr($sqlInsUrl,0,strlen($sqlInsUrl)-1);
			$sqlInsUrl.=";";
			$db->query($sqlInsUrl);
		}

        preg_match_all("/<title>([^<]+)<\/title>/", $htmlContent, $matchTitle, PREG_SET_ORDER);
        $cs_data_arr['MetaTitle'] = empty($matchTitle) ? "" : $matchTitle[0][1];
//keywords No Meta keywords
        preg_match_all("/<meta name=\"keywords\" content=\"([^\"]+)\"/", $htmlContent, $matchKeywords, PREG_SET_ORDER);
        $cs_data_arr['MetaKeywords'] = empty($matchKeywords) ? "" : $matchKeywords[0][1];
//meta description
        preg_match_all("/<meta\s*name=\"description\"\s*content=\"([^\"]+)\"/", $htmlContent, $matchMetaDesc, PREG_SET_ORDER);
        $cs_data_arr['MetaDescription'] = empty($matchMetaDesc) ? "" : $matchMetaDesc[0][1];
//描述
        $cs_data_arr['Description'] = '';
//H1
        preg_match_all("/<h2 class=\"h4\">([^<]+)<\/h2>/", $htmlContent, $matchH1, PREG_SET_ORDER);
        $cs_data_arr['H1'] = empty($matchH1) ? "" : $matchH1[0][1];
//Screen Img
//Merchant Go Url
        preg_match_all("/<a href='(.*?)'rel='nofollow' target='_blank'><img class=\"thumb85 img-responsive\" width=\"120\" height=\"120\" src=\"(.*?)\"/", $htmlContent, $matchGo);
        $cs_data_arr['MerchantGoUrl'] = empty($matchGo[0]) ? "" :  $matchGo[1][0];
        $cs_data_arr['ScreenImg'] = empty($matchGo[0]) ? "" : $matchGo[2][0];

		$sql = $pre_sql = "update cp_competitor_store set ";
		foreach ($cs_data_arr as $key => $val) {
			if (strlen($val) > 100) {
				$val = del_br_space_by_str($val);
			}
			if (strrpos($key, "meta") !== false) {
				$val = substr($val, 0, 250);
			}
			$sql .= " {$key} = '" . addslashes($val) . "' , ";
		}

		if($sql != $pre_sql ){
			$sql.=" LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$row['CompetitorStoreId']}";
			$GLOBALS['db']->query($sql);
		}

		$rank=0;
		$sqlIns=$sqlInsPre="INSERT ignore into cp_competitor_store_coupon (CompetitorStoreId,CompetitorId,CouponID,CouponTitle,GoUrl,CouponCode,ExpirationDate,CouponDesc,Used,Country,MaybeValid,AddTime,type,CouponCodeUrl,IsUpdateCodeUrl) values ";
//    coupons 数据
        preg_match_all("/class=\"views-row views-row-\d+[^\"]+\"><div([\s\S]+?)<\/div>\s*<\/li>/", $htmlContent, $matchValidCoupon);

        if (!empty($matchValidCoupon)) {

            foreach ($matchValidCoupon[0] as $couponHtml){

                $couponData['MaybeValid'] = 1;
                $couponData['Country'] = "AU";
                $couponData['CouponID'] =$rank;
                $couponData['CouponTitle']='';
                $couponData['CouponDesc']='';
                $couponData['GoUrl']='';
                $couponData['type']='deal';
                $couponData['Used']='';
                $couponData['CouponRestriction']='';
                $couponData['ExpirationDate']='';
                $couponData['CouponCodeUrl'] = "";
                $couponData['CouponCode']='';
                $couponData['IsUpdateCodeUrl']=0;

                //couponId
                preg_match_all("/data-coupon=\"(\d+)\"/",$couponHtml,$matchCouponId);
                if(!empty($matchCouponId[0])){
                    $couponData['CouponID']=$matchCouponId[1][0];
                }

                //title
                preg_match_all("/<h3[^>]+>([\s\S]*?)<\/h3>/",$couponHtml,$matchcoupontitle);
                $couponData['CouponTitle']= empty($matchcoupontitle[0])?'':trim(strip_tags($matchcoupontitle[1][0]));

                //gourl
                preg_match_all("/data-store=\"(.*?)\"/",$couponHtml,$matchGoUrl);
                $couponData['GoUrl']=empty($matchGoUrl[0])?'':''.$matchGoUrl[1][0];

                //desc
                preg_match_all("/<div class=\"coupon-body\">([\s\S]+?)<\/div>/",$couponHtml,$matchCoupondesc);
                $couponData['CouponDesc']=empty($matchCoupondesc[0])?'':trim(strip_tags($matchCoupondesc[1][0]));

                if(stripos($couponHtml,'<strong>&nbsp;View Code&nbsp;</strong>')){
                    //code
                    $couponData['IsUpdateCodeUrl']=1;
                    $couponData['type']='code';
                    $couponData['CouponCodeUrl'] = "{$row['Url']}?view_coupon_code={$couponData['CouponID']}";
                }

				if (empty($couponData['ExpirationDate'])) $couponData['ExpirationDate'] = "0000-00-00";

				$rank++;
				if(!empty($couponData['CouponID']))
					$couponRankMap[''.$couponData['CouponID']]=$rank;
				if(!isset($couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']])){
					$sqlIns.="('{$row['CompetitorStoreId']}','{$row['CompetitorId']}','{$couponData['CouponID']}','{$couponData['CouponTitle']}','{$couponData['GoUrl']}','{$couponData['CouponCode']}','{$couponData['ExpirationDate']}','{$couponData['CouponDesc']}','{$couponData['Used']}','{$couponData['Country']}','{$couponData['MaybeValid']}','".date("Y-m-d H:i:s")."','{$couponData['type']}','{$couponData['CouponCodeUrl']}','{$couponData['IsUpdateCodeUrl']}'),";
				}else{
					$expiresVo=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']];
					if($expiresVo['ExpirationDate']!=$couponData['ExpirationDate'] || $expiresVo['CouponTitle']!=$couponData['CouponTitle']  ||  $expiresVo['CouponDesc']!=$couponData['CouponDesc'] || $expiresVo['CouponCodeUrl']!=$couponData['CouponCodeUrl'] || $expiresVo['type']!=$couponData['type']){
						$sqlUp="update cp_competitor_store_coupon set ExpirationDate='{$couponData['ExpirationDate']}',CouponTitle='{$couponData['CouponTitle']}',CouponDesc='{$couponData['CouponDesc']}',CouponCodeUrl='{$couponData['CouponCodeUrl']}',type='{$couponData['type']}',IsUpdateCodeUrl='{$couponData['IsUpdateCodeUrl']}',LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$expiresVo['ID']}";
						$db->query($sqlUp);
					}
					$couponOnMap[]=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']]['ID'];
				}
			}
			if($sqlIns!=$sqlInsPre){
				$sqlIns=substr($sqlIns,0,strlen($sqlIns)-1);
				$sqlIns.=";";
				$db->query($sqlIns);
			}

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
		//end coupons 15
	}else if($row['CompetitorId']==16){

		//获取竞争对手其他store链接
		preg_match_all("/href\=\"(https\:\/\/coupns.com.au\/stores\/[^\/]+\/)\"/i", $htmlContent, $matchUrl,PREG_SET_ORDER);
		$sqlInsUrl = $sqlInsUrlPre = "insert ignore into cp_temp_competitor_store(StoreUrl,CompetitorId,AddTime) values ";

		if (!empty($matchUrl)) {
			foreach ($matchUrl as $url) {

				$sqlInsUrl .= "('" . $url[1] . "',{$row['CompetitorId']},'" . date("Y-m-d H:i:s") . "'),";

			}
			if($sqlInsUrl!=$sqlInsUrlPre){
				$sqlInsUrl=substr($sqlInsUrl,0,-1);
				$GLOBALS['db']->query($sqlInsUrl);
			}
		}

		$cs_data_arr = array();

//MetaTitle
        preg_match_all("/<title>([^<]+)<\/title>/", $htmlContent, $matchTitle, PREG_SET_ORDER);
        $cs_data_arr['MetaTitle'] = empty($matchTitle[0]) ? "" : $matchTitle[0][1];
        //keywords No Meta keywords
        $cs_data_arr['MetaKeywords'] = '';
        //meta description
        preg_match_all("/<meta\s*name=\"description\"\s*content=\"([^\"]+)\"/", $htmlContent, $matchMetaDesc, PREG_SET_ORDER);
        $cs_data_arr['MetaDescription'] = empty($matchMetaDesc[0]) ? "" : $matchMetaDesc[0][1];
        //描述
        preg_match_all("/<div class=\"desc\">([\s\S]+?)<p class\=\"store-url\">/", $htmlContent, $matchDesc, PREG_SET_ORDER);
        $cs_data_arr['Description'] = empty($matchDesc[0])?'':trim(strip_tags($matchDesc[0][1]));
//H1
        preg_match_all("/<h1>([^<]+)<\/h1>/", $htmlContent, $matchH1, PREG_SET_ORDER);
        $cs_data_arr['H1'] = empty($matchH1[0]) ? "" : $matchH1[0][1];
//Merchant Go Url
        preg_match_all("/class=\"store-url\"><a href=\"[^\"]+\" target=\"_blank\">([^<]+)<\/a><\/p>/", $htmlContent, $matchGo);
        $cs_data_arr['MerchantGoUrl'] = empty($matchGo[0]) ? "" :  $matchGo[1][0];
        //Screen Img
        preg_match_all("/\"store-thumb\" src=\"([^\"]+)\"/",$htmlContent,$matchImg);
        $cs_data_arr['ScreenImg'] = empty($matchImg[0]) ? "" : $matchImg[1][0];

		//update cs  info
		$sql = $pre_sql ="update cp_competitor_store set ";
		foreach ($cs_data_arr as $key=>$val){
			if(empty($val)){
				$empty_log_str .=" {$key} --empty --";
			}
			if(strlen($val) > 100){
				$val = del_br_space_by_str($val);
			}
			if(strrpos($key,"Meta") !== false){
				$val = substr($val,0,250);
			}
			$sql .= " {$key} = '".addslashes($val)."' , ";
		}

		if($sql != $pre_sql ){
			$sql.=" LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$row['CompetitorStoreId']}";
			$GLOBALS['db']->query($sql);
		}

		//coupon
		//有效的coupon
		//正常没有过期
		$rank=0;
		$sqlIns=$sqlInsPre="INSERT ignore into cp_competitor_store_coupon (CompetitorStoreId,CompetitorId,CouponID,CouponTitle,GoUrl,CouponDesc,CouponRestriction,CouponCode,Used,ExpirationDate,Country,MaybeValid,AddTime,type) values ";

//    coupons 数据
        preg_match_all("/<div class=\"item post([\s\S]+?)<div class=\"top\"><a href=\"\#top\">/", $htmlContent, $matchValidCoupon, PREG_SET_ORDER);

        $matchCoupon=explode("<div class=\"item post-",$matchValidCoupon[0][1]);

        if (!empty($matchCoupon)) {

            for($i=0;$i<count($matchCoupon);$i++){
                $couponHtml=$matchCoupon[$i];

                $couponData['MaybeValid'] = 1;
                $couponData['Country'] = "AU";

                $couponData['CouponID'] =$rank;
                $couponData['CouponTitle']='';
                $couponData['CouponDesc']='';
                $couponData['GoUrl']='';
                $couponData['type']='deal';
                $couponData['Used']='';
                $couponData['CouponRestriction']='';
                $couponData['ExpirationDate']='';
                $couponData['CouponCodeUrl'] = "";
                $couponData['CouponCode']='';
                $couponData['IsUpdateCodeUrl']=0;

                //couponId
                preg_match_all("/id=\"post-([^\"]+)\"/", $couponHtml, $matchCouponId, PREG_SET_ORDER);
                if (!empty($matchCouponId)) {
                    $couponData['CouponID'] = $matchCouponId[0][1];
                }

                //type
                preg_match_all("/data-clipboard-text=\"([^\"]+)\"/",$couponHtml,$matchType);
                if(!empty($matchType[0])){
                    $couponData['type']='code';
                    $couponData['CouponCode']=$matchType[1][0];
                }

                //title
                preg_match_all("/rel=\"bookmark\">([^<]+)<\/a><\/h3>/",$couponHtml,$matchcoupontitle);
                $couponData['CouponTitle']= empty($matchcoupontitle[0])?'':trim(strip_tags($matchcoupontitle[1][0]));

                //gourl
                preg_match_all("/<div class=\"link-holder\">\s+<a href=\"([^\"]+)\"/",$couponHtml,$matchGo);
                $couponData['GoUrl'] = empty($matchGo[0])?'':'http://www.thebargainavenue.com.au'.$matchGo[1][0];

                //desc
                preg_match_all("/<p class=\"desc entry-content\">([^<]+)<a/",$couponHtml,$matchcoupondesc);
                if(!empty($matchcoupondesc[0])){
                    $couponData['CouponDesc']=trim(del_br_space_by_str(strip_tags($matchcoupondesc[1][0])));
                }

                //有效期
                preg_match_all("/class=\"entry-date expired\" datetime=\"([^\"]+)\"/",$couponHtml,$coupondate);
                if(!empty($coupondate[0])){
                    $couponData['ExpirationDate']=substr($coupondate[1][0],0,10);
                }

                if(empty($couponData['ExpirationDate'])){
                    $couponData['ExpirationDate']='0000-00-00';
                }
                if(strtotime($couponData['ExpirationDate'])<time() && $couponData['ExpirationDate']!='0000-00-00' ){
                    continue;
                }

				$rank++;
				if(!empty($couponData['CouponID']))
					$couponRankMap[''.$couponData['CouponID']]=$rank;
				if(!isset($couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']])){
					$sqlIns.="('{$row['CompetitorStoreId']}','{$row['CompetitorId']}','{$couponData['CouponID']}','{$couponData['CouponTitle']}','{$couponData['GoUrl']}','{$couponData['CouponDesc']}','{$couponData['CouponRestriction']}','{$couponData['CouponCode']}','{$couponData['Used']}','{$couponData['ExpirationDate']}','{$couponData['Country']}','{$couponData['MaybeValid']}','".date("Y-m-d H:i:s")."','{$couponData['type']}'),";
				}else{
					$expiresVo=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']];
					if($expiresVo['ExpirationDate']!=$couponData['ExpirationDate'] || $expiresVo['CouponTitle']!=$couponData['CouponTitle']  || $expiresVo['CouponCode']!=$couponData['CouponCode'] || $expiresVo['CouponDesc']!=$couponData['CouponDesc'] || $expiresVo['CouponCodeUrl']!=$couponData['CouponCodeUrl'] || $expiresVo['type']!=$couponData['type']){
						$sqlUp="update cp_competitor_store_coupon set ExpirationDate='{$couponData['ExpirationDate']}',CouponTitle='{$couponData['CouponTitle']}',CouponDesc='{$couponData['CouponDesc']}',CouponCodeUrl='{$couponData['CouponCodeUrl']}',type='{$couponData['type']}',IsUpdateCodeUrl='{$couponData['IsUpdateCodeUrl']}',LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$expiresVo['ID']}";
						$db->query($sqlUp);

					}
					$couponOnMap[]=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']]['ID'];
				}
			}
			if($sqlIns!=$sqlInsPre){
				$sqlIns=substr($sqlIns,0,strlen($sqlIns)-1);
				$sqlIns.=";";
				$db->query($sqlIns);
			}
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
		//end 16
	}else if($row['CompetitorId']==17){

		//获取竞争对手其他store链接
		preg_match_all("/href=\"(\/discount-codes\/shops\/[^\/\"]+)\"/i", $htmlContent, $matchUrl,PREG_SET_ORDER);
		$sqlInsUrl = $sqlInsUrlPre = "insert ignore into cp_temp_competitor_store(StoreUrl,CompetitorId,AddTime) values ";

		if (!empty($matchUrl)) {
			foreach ($matchUrl as $url) {

				$sqlInsUrl .= "('" . 'https://www.groupon.co.uk'.$url[1] . "',{$row['CompetitorId']},'" . date("Y-m-d H:i:s") . "'),";

			}
			if($sqlInsUrl!=$sqlInsUrlPre){
				$sqlInsUrl=substr($sqlInsUrl,0,-1);
				$GLOBALS['db']->query($sqlInsUrl);
			}
		}

		$cs_data_arr = array();

//MetaTitle
		preg_match_all("/<title>([^<]+)<\/title>/", $htmlContent, $matchTitle, PREG_SET_ORDER);
		$cs_data_arr['MetaTitle'] = empty($matchTitle) ? "" : $matchTitle[0][1];
		//keywords No Meta keywords
		preg_match_all("/meta name=\"keywords\" content=\"([^\"]+)\"/", $htmlContent, $matchMetaKeywords, PREG_SET_ORDER);
		$cs_data_arr['MetaKeywords'] = empty($matchMetaKeywords) ? "" : $matchMetaKeywords[0][1];
		//meta description
		preg_match_all("/<meta\s*name=\"description\"\s*content=\"([^\"]+)\"/", $htmlContent, $matchMetaDesc, PREG_SET_ORDER);
		$cs_data_arr['MetaDescription'] = empty($matchMetaDesc) ? "" : $matchMetaDesc[0][1];
		//描述
		preg_match_all("/<p class=\"should-truncate\">(.*?)<\/p>/", $htmlContent, $matchDesc, PREG_SET_ORDER);
		$cs_data_arr['Description'] = empty($matchDesc)?'':trim(strip_tags($matchDesc[0][1]));
//H1
		preg_match_all("/<h1>([^<]+)<\/h1>/", $htmlContent, $matchH1, PREG_SET_ORDER);
		$cs_data_arr['H1'] = empty($matchH1) ? "" : $matchH1[0][1];
//Merchant Go Url
		preg_match_all("/href=\"([^\"]+)\" class=\"affiliate-link/", $htmlContent, $matchGo);
		$cs_data_arr['MerchantGoUrl'] = empty($matchGo) ? "" :  'https://www.groupon.co.uk'.$matchGo[1][0];
		//Screen Img
		preg_match_all("/StoreImageLink\"><img src=\"([^\"]+)\"/",$htmlContent,$matchImg);
		$cs_data_arr['ScreenImg'] = empty($matchImg[0]) ? "" :'http:'.$matchImg[1][0];

		//update cs  info
		$sql = $pre_sql ="update cp_competitor_store set ";
		foreach ($cs_data_arr as $key=>$val){
			if(empty($val)){
				$empty_log_str .=" {$key} --empty --";
			}
			if(strlen($val) > 100){
				$val = del_br_space_by_str($val);
			}
			if(strrpos($key,"Meta") !== false){
				$val = substr($val,0,250);
			}
			$sql .= " {$key} = '".addslashes($val)."' , ";
		}

		if($sql != $pre_sql ){
			$sql.=" LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$row['CompetitorStoreId']}";
			$GLOBALS['db']->query($sql);
		}

		//coupon
		//有效的coupon
		//正常没有过期
		$rank=0;
		$sqlIns=$sqlInsPre="INSERT ignore into cp_competitor_store_coupon (CompetitorStoreId,CompetitorId,CouponID,CouponTitle,GoUrl,CouponDesc,CouponRestriction,CouponCode,Used,ExpirationDate,Country,MaybeValid,AddTime,type) values ";

//    coupons 数据
		preg_match_all("/<div id=\"coupons-list\"(.*?)<div data-bhw=\"ExpiredCoupons/", $htmlContent, $matchValidCoupon);
		$matchCoupon=explode('<div class="coupon row"',$matchValidCoupon[1][0]);
		if (!empty($matchCoupon)) {

			for($i=1;$i<count($matchCoupon);$i++){
				$couponHtml=$matchCoupon[$i];

                $couponData['MaybeValid'] = 1;
                $couponData['Country'] = "UK";
                $couponData['CouponID'] =$rank;
                $couponData['CouponTitle']='';
                $couponData['CouponDesc']='';
                $couponData['GoUrl']='';
                $couponData['type']='deal';
                $couponData['Used']='';
                $couponData['CouponRestriction']='';
                $couponData['ExpirationDate']='';
                $couponData['CouponCodeUrl'] = "";
                $couponData['CouponCode']='';
                $couponData['IsUpdateCodeUrl']=0;

                //CouponId
                preg_match_all("/data-uuid=\"([^\"]+)\"/i",$couponHtml,$MatchCouponId);
                $couponData['CouponID']=empty($MatchCouponId[0])?$rank:$MatchCouponId[1][0];

				preg_match_all("/itemprop=\"validThrough\" content=\"([^\"]+)\">/",$couponHtml,$coupondate);
				if(!empty($coupondate[0])){
					$couponData['ExpirationDate']=substr($coupondate[1][0],0,10);
				}

				preg_match_all("/<a href=\"([^\"]+)\" class=\"should-truncate coupon-title affiliate-url\" itemprop=\"name\" data-bhw=\"TitleLink\" rel=\"nofollow\">([^<]+)<\/a>/",$couponHtml,$matchcoupontitle);
				$couponData['CouponTitle']= empty($matchcoupontitle[0])?'':$matchcoupontitle[2][0];
				$couponData['GoUrl']= empty($matchcoupontitle[0])?'':'https://www.groupon.co.uk'.$matchcoupontitle[1][0];

				preg_match_all("/data-clipboard-text=\"([^\"]+)\"/",$couponHtml,$matchCouponTitle);
				$couponData['CouponCode']= empty($matchCouponTitle[0])?'':$matchCouponTitle[1][0];
				if(!empty($couponData['CouponCode'])){
					$couponData['type']='code';
				}

				preg_match_all("/<p class=\"desc entry-content\">([^<]+)<a/",$couponHtml,$couponDesc);
				$couponData['CouponDesc'] =empty($couponDesc[0])?'':substr(del_br_space_by_str(strip_tags($couponDesc[1][0])), 0, 250);

				if (empty($couponData['ExpirationDate'])){$couponData['ExpirationDate'] = "0000-00-00";}

				$rank++;
				
				if(!empty($couponData['CouponID']))
					$couponRankMap[''.$couponData['CouponID']]=$rank;
				if(!isset($couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']])){
					$sqlIns.="('{$row['CompetitorStoreId']}','{$row['CompetitorId']}','{$couponData['CouponID']}','{$couponData['CouponTitle']}','{$couponData['GoUrl']}','{$couponData['CouponDesc']}','{$couponData['CouponRestriction']}','{$couponData['CouponCode']}','{$couponData['Used']}','{$couponData['ExpirationDate']}','{$couponData['Country']}','{$couponData['MaybeValid']}','".date("Y-m-d H:i:s")."','{$couponData['type']}'),";
				}else{
					$expiresVo=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']];
					if($expiresVo['ExpirationDate']!=$couponData['ExpirationDate'] || $expiresVo['CouponTitle']!=$couponData['CouponTitle']  || $expiresVo['CouponCode']!=$couponData['CouponCode'] || $expiresVo['CouponDesc']!=$couponData['CouponDesc'] || $expiresVo['CouponCodeUrl']!=$couponData['CouponCodeUrl'] || $expiresVo['type']!=$couponData['type']){
						$sqlUp="update cp_competitor_store_coupon set ExpirationDate='{$couponData['ExpirationDate']}',CouponTitle='{$couponData['CouponTitle']}',CouponDesc='{$couponData['CouponDesc']}',CouponCodeUrl='{$couponData['CouponCodeUrl']}',type='{$couponData['type']}',IsUpdateCodeUrl='{$couponData['IsUpdateCodeUrl']}',LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$expiresVo['ID']}";
						$db->query($sqlUp);

					}
					$couponOnMap[]=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']]['ID'];
				}
			}
			if($sqlIns!=$sqlInsPre){
				$sqlIns=substr($sqlIns,0,strlen($sqlIns)-1);
				$sqlIns.=";";
				$db->query($sqlIns);
			}
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
		//end 17
	}else if($row['CompetitorId']==18){
		preg_match_all("/href=\"\/([^\/\"]+)\"/i", $htmlContent, $matchUrl,PREG_SET_ORDER);
		$sqlInsUrl = $sqlInsUrlPre = "insert ignore into cp_temp_competitor_store(StoreUrl,CompetitorId,AddTime) values ";

		if (!empty($matchUrl)) {
			foreach ($matchUrl as $url) {
				$sqlInsUrl .= "('" . 'https://www.vouchercodes.co.uk/' . $url[1] . "',{$row['CompetitorId']},'" . date("Y-m-d H:i:s") . "'),";
			}
			if ($sqlInsUrl != $sqlInsUrlPre) {
				$sqlInsUrl = substr($sqlInsUrl, 0, strlen($sqlInsUrl) - 1);
				$sqlInsUrl .= ";";
				$db->query($sqlInsUrl);
			}
		}

		//MetaTitle
		preg_match_all("/<title>([^<]+)<\/title>/", $htmlContent, $matchTitle, PREG_SET_ORDER);
		$cs_data_arr['MetaTitle'] = empty($matchTitle) ? "" : $matchTitle[0][1];
		//keywords No Meta keywords
		$cs_data_arr['MetaKeywords'] = '';
		//meta description
		preg_match_all("/<meta\s*name=\"description\"\s*content=\"([^\"]+)\"/", $htmlContent, $matchMetaDesc, PREG_SET_ORDER);
		$cs_data_arr['MetaDescription'] = empty($matchMetaDesc) ? "" : $matchMetaDesc[0][1];
		//描述
		preg_match_all("/<p class=\"tp-regular\">(.*?)<br><br><\/p>/", $htmlContent, $matchDesc, PREG_SET_ORDER);
		$cs_data_arr['Description'] = empty($matchDesc)?'':trim(strip_tags($matchDesc[0][1]));
//H1
		preg_match_all("/<h1 itemprop=\"name\">([^<]+)<\/h1>/", $htmlContent, $matchH1, PREG_SET_ORDER);
		$cs_data_arr['H1'] = empty($matchH1) ? "" : $matchH1[0][1];
//Merchant Go Url
		preg_match_all("/href=\"([^\"]+)\" target=\"_blank\" class=\"btn btn-tiny btn-icon btn-merchant-visit-site\"/", $htmlContent, $matchGo);
		$cs_data_arr['MerchantGoUrl'] = empty($matchGo) ? "" :  'https://www.vouchercodes.co.uk'.$matchGo[1][0];
		if(!empty($cs_data_arr['MerchantGoUrl'])){
			$tmp_rs=explode('?',$cs_data_arr['MerchantGoUrl']);
			if(!empty($tmp_rs[0])){
				$cs_data_arr['MerchantGoUrl']=$tmp_rs[0];
			}
		}
		//Screen Img
        $matchImg = Selector::select($htmlContent,'//*/section[@class=\'merchant-logo\']/a/img/@src');
        $cs_data_arr['ScreenImg'] = empty($matchImg) ? "" : 'https://www.vouchercodes.co.uk'.$matchImg;

		$sql = $pre_sql = "update cp_competitor_store set ";
		foreach ($cs_data_arr as $key => $val) {
			if (strlen($val) > 100) {
				$val = del_br_space_by_str($val);
			}
			if (strrpos($key, "meta") !== false) {
				$val = substr($val, 0, 250);
			}
			$sql .= " {$key} = '" . addslashes($val) . "' , ";
		}

		if($sql != $pre_sql ){
			$sql.=" LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$row['CompetitorStoreId']}";
			$GLOBALS['db']->query($sql);
		}

		$rank=0;
        $sqlIns=$sqlInsPre="INSERT ignore into cp_competitor_store_coupon (CompetitorStoreId,CompetitorId,CouponID,CouponTitle,GoUrl,CouponCode,ExpirationDate,CouponDesc,Used,Country,MaybeValid,AddTime,type,CouponCodeUrl,IsUpdateCodeUrl) values ";
//    coupons 数据
        preg_match_all("/<div class=\"merch-offers clearfix\">([\s\S]+?)class=\"similar-offers clearfix\"/", $htmlContent, $matchValidCoupon);
        $matchCoupon=explode('<article',$matchValidCoupon[1][0]);
        if (!empty($matchCoupon)) {

            for($i=1;$i<count($matchCoupon);$i++){
                $couponHtml=$matchCoupon[$i];

                $couponData['MaybeValid'] = 1;
                $couponData['Country'] = "UK";
                $couponData['type']='deal';
                $couponData['Used']='';
                $couponData['CouponDesc']='';
                $couponData['CouponCodeUrl'] = "";
                $couponData['CouponCode']='';
                $couponData['IsUpdateCodeUrl']=0;

                preg_match_all("/id=\"voucher-([^\"]+)\"/",$couponHtml,$couponId);
                $couponData['CouponID'] = empty($couponId[0])?'':$couponId[1][0];
                //有效期
                $couponData['ExpirationDate']='';
                preg_match_all("/<span class=\"tp-small shortdate\">([^ ]+) ([^<]+)<\/span>/",$couponHtml,$coupondate);
                if(!empty($coupondate[0])){
                    if($coupondate[1][0]=='Expires'){
                        $tmp_date=$coupondate[2][0];
                        $couponData['ExpirationDate']=date('Y-m-d', strtotime($tmp_date));

                    }
                }

                preg_match_all("/class=\"tp-offertitle js-offer-title\">\s*<a href=\"(.*?)\"[^>]+>([\s\S]+?)<\/a>/",$couponHtml,$matchcoupontitle);
                $couponData['CouponTitle']= empty($matchcoupontitle[0])?'':trim(strip_tags($matchcoupontitle[2][0]));
                if(!empty($matchcoupontitle[0])){
                    $tmp_go=explode('?',$matchcoupontitle[1][0]);
                    $couponData['GoUrl']='https://www.vouchercodes.co.uk'.$tmp_go[0];
                }

                preg_match_all("/data-offer-type=\"([^\"]+)\"/",$couponHtml,$matchcoupontype);
                if(!empty($matchcoupontype[0])){
                    if($matchcoupontype[1][0]=='code'){
                        $couponData['type']='code';
                        $couponData['CouponCodeUrl'] =$row['Url'].'?rc='.$couponData['CouponID'];
                        $couponData['IsUpdateCodeUrl']=1;
                    }
                }
                if(empty($couponData['ExpirationDate'])){
                    $couponData['ExpirationDate']='0000-00-00';
                }

				$rank++;
				if(!empty($couponData['CouponID']))
					$couponRankMap[''.$couponData['CouponID']]=$rank;
				if(!isset($couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']])){
					$sqlIns.="('{$row['CompetitorStoreId']}','{$row['CompetitorId']}','{$couponData['CouponID']}','{$couponData['CouponTitle']}','{$couponData['GoUrl']}','{$couponData['CouponCode']}','{$couponData['ExpirationDate']}','{$couponData['CouponDesc']}','{$couponData['Used']}','{$couponData['Country']}','{$couponData['MaybeValid']}','".date("Y-m-d H:i:s")."','{$couponData['type']}','{$couponData['CouponCodeUrl']}','{$couponData['IsUpdateCodeUrl']}'),";
				}else{
					$expiresVo=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']];
					if($expiresVo['ExpirationDate']!=$couponData['ExpirationDate'] || $expiresVo['CouponTitle']!=$couponData['CouponTitle'] || $expiresVo['CouponDesc']!=$couponData['CouponDesc'] || $expiresVo['CouponCodeUrl']!=$couponData['CouponCodeUrl'] || $expiresVo['type']!=$couponData['type']){
						$sqlUp="update cp_competitor_store_coupon set ExpirationDate='{$couponData['ExpirationDate']}',CouponTitle='{$couponData['CouponTitle']}',CouponDesc='{$couponData['CouponDesc']}',CouponCodeUrl='{$couponData['CouponCodeUrl']}',type='{$couponData['type']}',IsUpdateCodeUrl='{$couponData['IsUpdateCodeUrl']}',LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$expiresVo['ID']}";
						$db->query($sqlUp);
					}
					$couponOnMap[]=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']]['ID'];
				}
			}
			if($sqlIns!=$sqlInsPre){
				$sqlIns=substr($sqlIns,0,strlen($sqlIns)-1);
				$sqlIns.=";";
				$db->query($sqlIns);
			}

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
		//end coupons 15
	}else if($row['CompetitorId']==19){

		//获取竞争对手其他store链接
        preg_match_all("/href=\"(\/[^\/\"]+)\"/i", $htmlContent, $matchUrl,PREG_SET_ORDER);
        $sqlInsUrl = $sqlInsUrlPre = "insert ignore into cp_temp_competitor_store(StoreUrl,CompetitorId,AddTime) values ";

        $site_pre = 'https://www.myvouchercodes.co.uk';
        if (!empty($matchUrl)) {
            foreach ($matchUrl as $url) {
                $sqlInsUrl .= "('" . $site_pre . $url[1] . "',{$row['CompetitorId']},'" . date("Y-m-d H:i:s") . "'),";
            }
            if ($sqlInsUrl != $sqlInsUrlPre) {
                $sqlInsUrl = substr($sqlInsUrl, 0, strlen($sqlInsUrl) - 1);
                $sqlInsUrl .= ";";
                $db->query($sqlInsUrl);
            }
        }

		$cs_data_arr = array();
//MetaTitle
        preg_match_all("/<title>([^<]+)<\/title>/", $htmlContent, $matchTitle, PREG_SET_ORDER);
        $cs_data_arr['MetaTitle'] = empty($matchTitle[0]) ? "" : $matchTitle[0][1];
//keywords No Meta keywords
        $cs_data_arr['MetaKeywords'] = '';
//meta description
        preg_match_all("/<meta\s*name=\"description\"\s*content=\"([^\"]+)\"/", $htmlContent, $matchMetaDesc, PREG_SET_ORDER);
        $cs_data_arr['MetaDescription'] = empty($matchMetaDesc[0]) ? "" : $matchMetaDesc[0][1];
//描述
        preg_match_all("/itemprop=\"description\">([\s\S]+?)<\/div>/", $htmlContent, $matchDesc, PREG_SET_ORDER);
        $cs_data_arr['Description'] = empty($matchDesc[0])?'':deal_text($matchDesc[0][1]);
//H1
        preg_match_all("/<h1>(.+?)<\/h1>/", $htmlContent, $matchH1, PREG_SET_ORDER);
        $cs_data_arr['H1'] = empty($matchH1[0]) ? "" : deal_text($matchH1[0][1]);
//Merchant Go Url
        preg_match_all("/class=\"InfoHeaderMerchant-cta Offer-cta\" href=\"([^\"]+)\"/", $htmlContent, $matchGo);
        $cs_data_arr['MerchantGoUrl'] = empty($matchGo[0]) ? "" :  $site_pre.$matchGo[1][0];
//Screen Img
        preg_match_all("/<img src=\"([^\"]+)\" alt=\"[^\"]+?\" itemprop=\"image\"/",$htmlContent,$matchImg);
        $cs_data_arr['ScreenImg'] = empty($matchImg[0]) ? "" :$matchImg[1][0];

		//update cs  info
		$sql = $pre_sql ="update cp_competitor_store set ";
		foreach ($cs_data_arr as $key=>$val){
			if(strlen($val) > 100){
				$val = del_br_space_by_str($val);
			}
			if(strrpos($key,"Meta") !== false){
				$val = substr($val,0,250);
			}
			$sql .= " {$key} = '".addslashes($val)."' , ";
		}

		if($sql != $pre_sql ){
			$sql.=" LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$row['CompetitorStoreId']}";
			$GLOBALS['db']->query($sql);
		}

		//coupon
		//有效的coupon
		//正常没有过期
		$rank=0;
		$sqlIns=$sqlInsPre="INSERT ignore into cp_competitor_store_coupon (CompetitorStoreId,CompetitorId,CouponID,CouponTitle,GoUrl,CouponDesc,CouponRestriction,CouponCode,Used,ExpirationDate,Country,MaybeValid,AddTime,type) values ";

//    coupons 数据
        preg_match_all("/class=\"OfferList offers offers-standard\"([\s\S]+?)class=\"offer_last_updated\"/", $htmlContent, $matchValidCoupon);

        if (!empty($matchValidCoupon[0])) {
            $matchCoupon=explode('<li id',$matchValidCoupon[1][0]);
            for($i=1;$i<count($matchCoupon);$i++){
                $couponHtml=$matchCoupon[$i];

                $couponData['MaybeValid'] = 1;
                $couponData['Country'] = "UK";

                $couponData['CouponID'] =$rank;
                $couponData['CouponTitle']='';
                $couponData['CouponDesc']='';
                $couponData['GoUrl']='';
                $couponData['type']='deal';
                $couponData['Used']='';
                $couponData['CouponRestriction']='';
                $couponData['ExpirationDate']='';
                $couponData['CouponCodeUrl'] = "";
                $couponData['CouponCode']='';
                $couponData['IsUpdateCodeUrl']=0;

                preg_match_all("/=\"([^\"]+?)\" class=\"Offer\s/",$couponHtml,$matchCouponId);
                if(!empty($matchCouponId[0])){
                    $couponData['CouponID']=$matchCouponId[1][0];
                }

                //title
                preg_match_all("/class=\"Offer-title\"><a href=\"([^\"]+)\"[^>]+?>([\s\S]+?)<\/a><\/h3>/",$couponHtml,$matchcoupontitle);
                $couponData['CouponTitle']= empty($matchcoupontitle[0])?'':deal_text($matchcoupontitle[2][0]);
                if(!empty($matchcoupontitle[0])){
                    $couponData['GoUrl']=$site_pre.$matchcoupontitle[1][0];
                }
                //desc
                preg_match_all("/<p class=\"Offer-text truncate\"[^>]+>(.*?)<\/p>/",$couponHtml,$matchcoupondesc);
                if(!empty($matchcoupondesc[0])){
                    $couponData['CouponDesc']=deal_text($matchcoupondesc[1][0]);
                }

                //有效期
                preg_match_all("/class=\"Offer-expiry-date\">Ends: (.*?) - <\/span>/",$couponHtml,$coupondate);
                if(!empty($coupondate[0])){
                    $couponData['ExpirationDate']=date('Y-m-d', strtotime(str_replace('/', '-',$coupondate[1][0])));
                }
                if(empty($couponData['ExpirationDate'])){
                    $couponData['ExpirationDate']='0000-00-00';
                }

                //type
                $matchType = Selector::select($couponHtml,"//div[@class=\"Offer-container\"]/a/span");
                if(strripos(strtolower($matchType),'code')){
                    $couponData['type']='code';
                    $couponData['CouponCodeUrl'] = "https://www.myvouchercodes.co.uk/system/ajax-offer?offer={$couponData['CouponID']}";
                    $couponData['IsUpdateCodeUrl']=1;
                }

                $rank++;

				if(!empty($couponData['CouponID']))
					$couponRankMap[''.$couponData['CouponID']]=$rank;
				if(!isset($couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']])){
					$sqlIns.="('{$row['CompetitorStoreId']}','{$row['CompetitorId']}','{$couponData['CouponID']}','{$couponData['CouponTitle']}','{$couponData['GoUrl']}','{$couponData['CouponDesc']}','{$couponData['CouponRestriction']}','{$couponData['CouponCode']}','{$couponData['Used']}','{$couponData['ExpirationDate']}','{$couponData['Country']}','{$couponData['MaybeValid']}','".date("Y-m-d H:i:s")."','{$couponData['type']}'),";
				}else{
					$expiresVo=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']];
					if($expiresVo['ExpirationDate']!=$couponData['ExpirationDate'] || $expiresVo['CouponTitle']!=$couponData['CouponTitle']  || $expiresVo['CouponCode']!=$couponData['CouponCode'] || $expiresVo['CouponDesc']!=$couponData['CouponDesc'] || $expiresVo['CouponCodeUrl']!=$couponData['CouponCodeUrl'] || $expiresVo['type']!=$couponData['type']){
						$sqlUp="update cp_competitor_store_coupon set ExpirationDate='{$couponData['ExpirationDate']}',CouponTitle='{$couponData['CouponTitle']}',CouponDesc='{$couponData['CouponDesc']}',CouponCodeUrl='{$couponData['CouponCodeUrl']}',type='{$couponData['type']}',IsUpdateCodeUrl='{$couponData['IsUpdateCodeUrl']}',LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$expiresVo['ID']}";
						$db->query($sqlUp);

					}
					$couponOnMap[]=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']]['ID'];
				}
			}
			if($sqlIns!=$sqlInsPre){
				$sqlIns=substr($sqlIns,0,strlen($sqlIns)-1);
				$sqlIns.=";";
				$db->query($sqlIns);
			}
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
		//end 19
	}else if($row['CompetitorId']==20){
        preg_match_all("/<a href=\"(\/[^\"\/]+?\/)\"/i", $htmlContent, $matchUrl,PREG_SET_ORDER);
        $sqlInsUrl = $sqlInsUrlPre = "insert ignore into cp_temp_competitor_store(StoreUrl,CompetitorId,AddTime) values ";

		if (!empty($matchUrl)) {
			foreach ($matchUrl as $url) {
				$sqlInsUrl .= "('" . 'http://www.gutschein.de'.$url[1] . "',{$row['CompetitorId']},'" . date("Y-m-d H:i:s") . "'),";
			}
			if ($sqlInsUrl != $sqlInsUrlPre) {
				$sqlInsUrl = substr($sqlInsUrl, 0, strlen($sqlInsUrl) - 1);
				$sqlInsUrl .= ";";
				$db->query($sqlInsUrl);
			}
		}

//MetaTitle
        preg_match_all("/<title>([^<]+)<\/title>/", $htmlContent, $matchTitle, PREG_SET_ORDER);
        $cs_data_arr['MetaTitle'] = empty($matchTitle[0]) ? "" : $matchTitle[0][1];
//keywords No Meta keywords
        preg_match_all("/<meta\s*name=\"keywords\"\s*content=\"([^\"]+)\"/", $htmlContent, $matchkeywords, PREG_SET_ORDER);
        $cs_data_arr['MetaKeywords'] = empty($matchkeywords[0]) ? "" : $matchkeywords[0][1];
//meta description
        preg_match_all("/<meta\s*name=\"description\"\s*content=\"([^\"]+)\"/", $htmlContent, $matchMetaDesc, PREG_SET_ORDER);
        $cs_data_arr['MetaDescription'] = empty($matchMetaDesc[0]) ? "" : $matchMetaDesc[0][1];
//描述
        preg_match_all("/class=\"coupon-text-short\">([\S\s]+?)<\/div>/", $htmlContent, $matchDesc, PREG_SET_ORDER);
        $cs_data_arr['Description'] = empty($matchDesc[0])?'':trim(del_br_space_by_str(strip_tags($matchDesc[0][1])));
//H1
        preg_match_all("/<h1[^>]+>(.*?)<\/h1>/", $htmlContent, $matchH1, PREG_SET_ORDER);
        $cs_data_arr['H1'] = empty($matchH1[0]) ? "" : strip_tags($matchH1[0][1]);
//Merchant Go Url
        preg_match_all("/data-nofollow-url=\"([^\"]+?)\"/", $htmlContent, $matchGo);
        $cs_data_arr['MerchantGoUrl'] = empty($matchGo[0]) ? "" :  'http://www.gutschein.de'.$matchGo[1][0];
//Screen Img
        preg_match_all("/<img class=\"provider-logo\" src=\"(.*?)\"/",$htmlContent,$matchImg);
        $cs_data_arr['ScreenImg'] = empty($matchImg[0]) ? "" :$matchImg[1][0];

		$sql = $pre_sql = "update cp_competitor_store set ";
		foreach ($cs_data_arr as $key => $val) {
			if (strlen($val) > 100) {
				$val = del_br_space_by_str($val);
			}
			if (strrpos($key, "meta") !== false) {
				$val = substr($val, 0, 250);
			}
			$sql .= " {$key} = '" . addslashes($val) . "' , ";
		}

		if($sql != $pre_sql ){
			$sql.=" LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$row['CompetitorStoreId']}";
			$GLOBALS['db']->query($sql);
		}

		$rank=0;
		$sqlIns=$sqlInsPre="INSERT ignore into cp_competitor_store_coupon (CompetitorStoreId,CompetitorId,CouponID,CouponTitle,GoUrl,CouponCode,ExpirationDate,CouponDesc,Used,Country,MaybeValid,AddTime,type,CouponCodeUrl,IsUpdateCodeUrl) values ";
//    coupons 数据
        preg_match_all("/<div id=\"coupon-div-anchor\"([\s\S]+?)<section id=\"newsletter-widget\"/", $htmlContent, $matchValidCoupon);

        $matchCoupon=explode('class="box-template coupon',$matchValidCoupon[1][0]);
        if (!empty($matchCoupon)) {

            for($i=1;$i<count($matchCoupon);$i++){
                $couponHtml=$matchCoupon[$i];

                $couponData['MaybeValid'] = 1;
                $couponData['Country'] = "DE";

                $couponData['CouponID'] =$rank;
                $couponData['CouponTitle']='';
                $couponData['CouponDesc']='';
                $couponData['GoUrl']='';
                $couponData['type']='deal';
                $couponData['Used']='';
                $couponData['CouponRestriction']='';
                $couponData['ExpirationDate']='';
                $couponData['CouponCodeUrl'] = "";
                $couponData['CouponCode']='';
                $couponData['IsUpdateCodeUrl']=0;

                //couponId
                preg_match_all("/data-clickout-id=\"(\d+)\"/",$couponHtml,$matchCouponId);
                if(!empty($matchCouponId[0])){
                    $couponData['CouponID']=$matchCouponId[1][0];
                }

                //type
                preg_match_all("/coupon-button-code/",$couponHtml,$matchType);
                if(!empty($matchType[0])){
                    $couponData['type']='code';
                    $couponData['CouponCodeUrl'] ="http://www.gutschein.de/ajax/conversionlayer/?id=".$couponData['CouponID'];
                    $couponData['IsUpdateCodeUrl']=1;
                }

                //title
                preg_match_all("/class=\"h3 text-left\"[^>]+?>([\s\S]*?)<\//",$couponHtml,$matchcoupontitle);
                $couponData['CouponTitle']= empty($matchcoupontitle[0])?'':trim(strip_tags($matchcoupontitle[1][0]));

                //gourl
                preg_match_all("/data-clickout-url=\"(.*?)\"/",$couponHtml,$matchGo);
                $couponData['GoUrl']=empty($matchGo[0])?'':'http://www.gutschein.de'.$matchGo[1][0];

                //desc
                preg_match_all("/<div class=\"coupon-text-short\">\s+?<p>([\S\s]+?)<\/p>/",$couponHtml,$matchcoupondesc);
                if(!empty($matchcoupondesc[0])){
                    $couponData['CouponDesc']=trim(del_br_space_by_str(strip_tags($matchcoupondesc[1][0])));
                }

                //有效期
                preg_match_all("/gültig bis <span>(.*?)<\/span>/",$couponHtml,$coupondate);
                if(!empty($coupondate[0])){
                    $couponData['ExpirationDate']=date('Y-m-d', strtotime($coupondate[1][0]));
                }

                if(empty($couponData['ExpirationDate']) || strtotime($couponData['ExpirationDate'])<time()){
                    $couponData['ExpirationDate']='0000-00-00';
                }

				$rank++;
				if(!empty($couponData['CouponID']))
					$couponRankMap[''.$couponData['CouponID']]=$rank;
				if(!isset($couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']])){
					$sqlIns.="('{$row['CompetitorStoreId']}','{$row['CompetitorId']}','{$couponData['CouponID']}','{$couponData['CouponTitle']}','{$couponData['GoUrl']}','{$couponData['CouponCode']}','{$couponData['ExpirationDate']}','{$couponData['CouponDesc']}','{$couponData['Used']}','{$couponData['Country']}','{$couponData['MaybeValid']}','".date("Y-m-d H:i:s")."','{$couponData['type']}','{$couponData['CouponCodeUrl']}','{$couponData['IsUpdateCodeUrl']}'),";
				}else{
					$expiresVo=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']];
					if($expiresVo['ExpirationDate']!=$couponData['ExpirationDate'] || $expiresVo['CouponTitle']!=$couponData['CouponTitle']  || $expiresVo['CouponDesc']!=$couponData['CouponDesc'] || $expiresVo['CouponCodeUrl']!=$couponData['CouponCodeUrl'] || $expiresVo['type']!=$couponData['type']){
						$sqlUp="update cp_competitor_store_coupon set ExpirationDate='{$couponData['ExpirationDate']}',CouponTitle='{$couponData['CouponTitle']}',CouponDesc='{$couponData['CouponDesc']}',CouponCodeUrl='{$couponData['CouponCodeUrl']}',type='{$couponData['type']}',IsUpdateCodeUrl='{$couponData['IsUpdateCodeUrl']}',LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$expiresVo['ID']}";
						$db->query($sqlUp);
					}
					$couponOnMap[]=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']]['ID'];
				}
			}
			if($sqlIns!=$sqlInsPre){
				$sqlIns=substr($sqlIns,0,strlen($sqlIns)-1);
				$sqlIns.=";";
				$db->query($sqlIns);
			}

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
		//end coupons 20
	}else if($row['CompetitorId']==21){

		//获取竞争对手其他store链接
		preg_match_all("/href=\"(https:\/\/www.gutscheinpony.de\/gutscheine\/[^\/\"\#]+)\"/i", $htmlContent, $matchUrl,PREG_SET_ORDER);
		$sqlInsUrl = $sqlInsUrlPre = "insert ignore into cp_temp_competitor_store(StoreUrl,CompetitorId,AddTime) values ";
		if (!empty($matchUrl)) {
			foreach ($matchUrl as $url) {
				$sqlInsUrl .= "('" .$url[1] . "',{$row['CompetitorId']},'" . date("Y-m-d H:i:s") . "'),";
			}
			if ($sqlInsUrl != $sqlInsUrlPre) {
				$sqlInsUrl = substr($sqlInsUrl, 0, strlen($sqlInsUrl) - 1);
				$sqlInsUrl .= ";";
				$db->query($sqlInsUrl);
			}
		}

		$cs_data_arr = array();
//MetaTitle
		preg_match_all("/<title>([^<]+)<\/title>/", $htmlContent, $matchTitle, PREG_SET_ORDER);
		$cs_data_arr['MetaTitle'] = empty($matchTitle) ? "" : $matchTitle[0][1];
		//keywords No Meta keywords
		preg_match_all("/<meta\s*name=\"keywords\"\s*content=\"([^\"]+)\"/", $htmlContent, $matchkeywords, PREG_SET_ORDER);
		$cs_data_arr['MetaKeywords'] = empty($matchkeywords) ? "" : $matchkeywords[0][1];
		//meta description
		preg_match_all("/<meta\s*name=\"description\"\s*content=\"([^\"]+)\"/", $htmlContent, $matchMetaDesc, PREG_SET_ORDER);
		$cs_data_arr['MetaDescription'] = empty($matchMetaDesc) ? "" : $matchMetaDesc[0][1];
		//描述
		preg_match_all("/class=\"full-text hidden\">\s*<p>(.*?)<\/p>/", $htmlContent, $matchDesc, PREG_SET_ORDER);
		$cs_data_arr['Description'] = empty($matchDesc)?'':trim(del_br_space_by_str(strip_tags($matchDesc[0][1])));
//H1
		preg_match_all("/<h1>(.+?)<\/h1>/", $htmlContent, $matchH1, PREG_SET_ORDER);
		$cs_data_arr['H1'] = empty($matchH1) ? "" : strip_tags($matchH1[0][1]);
//Merchant Go Url
		preg_match_all("/action=\"([^\"]+\/go\/[^\"]+)\"/", $htmlContent, $matchGo);
		$cs_data_arr['MerchantGoUrl'] = empty($matchGo) ? "" :$matchGo[1][0];
		//Screen Img
		preg_match_all("/src=\"([^\"]+)\" alt=\"[^\"]*\" itemprop=\"logo\"/",$htmlContent,$matchImg);
		$cs_data_arr['ScreenImg'] = empty($matchImg) ? "" :$matchImg[1][0];

		//update cs  info
		$sql = $pre_sql ="update cp_competitor_store set ";
		foreach ($cs_data_arr as $key=>$val){
			if(empty($val)){
				$empty_log_str .=" {$key} --empty --";
			}
			if(strlen($val) > 100){
				$val = del_br_space_by_str($val);
			}
			if(strrpos($key,"Meta") !== false){
				$val = substr($val,0,250);
			}
			$sql .= " {$key} = '".addslashes($val)."' , ";
		}

		if($sql != $pre_sql ){
			$sql.=" LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$row['CompetitorStoreId']}";
			$GLOBALS['db']->query($sql);
		}

		//coupon
		//有效的coupon
		//正常没有过期
		$rank=0;
		$sqlIns=$sqlInsPre="INSERT ignore into cp_competitor_store_coupon (CompetitorStoreId,CompetitorId,CouponID,CouponTitle,GoUrl,CouponDesc,CouponRestriction,CouponCode,Used,ExpirationDate,Country,MaybeValid,AddTime,type) values ";

//    coupons 数据
		preg_match_all("/\"list-unstyled js-offer-list\">([\s\S]+?)class=\"shop-tabs js-tabs shopinfo\"/", $htmlContent, $matchValidCoupon);
		$matchCoupon=explode('<label',$matchValidCoupon[1][0]);
		if (!empty($matchCoupon)) {

			for($i=1;$i<count($matchCoupon);$i++){
				$couponHtml=$matchCoupon[$i];

				$couponData['MaybeValid'] = 1;
				$couponData['Country'] = "DE";

				$couponData['CouponID'] =$rank;
				$couponData['CouponTitle']='';
				$couponData['CouponDesc']='';
				$couponData['GoUrl']='';
				$couponData['type']='deal';
				$couponData['Used']='';
				$couponData['CouponRestriction']='';
				$couponData['ExpirationDate']='';
				$couponData['CouponCodeUrl'] = "";
				$couponData['CouponCode']='';
				$couponData['IsUpdateCodeUrl']=0;
				preg_match_all("/data-code=\"([^\"]+)\"/",$couponHtml,$matchCode);
				$couponData['CouponCode']=empty($matchCode[0])?'':$matchCode[1][0];

				$couponData['type']=empty($couponData['CouponCode'])?'deal':'code';

				//couponId
				preg_match_all("/data-offer_type_id=\"(\w+)\"/",$couponHtml,$matchCouponId);
				if(!empty($matchCouponId[0])){
					$couponData['CouponID']=$matchCouponId[1][0];
				}

				//title
				preg_match_all("/class=\"discount-label\">(.*?)<\/div>/",$couponHtml,$matchcoupontitle);
				$couponData['CouponTitle']= empty($matchcoupontitle[0])?'':trim(strip_tags($matchcoupontitle[1][0]));
				//gourl
				$couponData['GoUrl']=$cs_data_arr['MerchantGoUrl'];

				//desc
				preg_match_all("/<h3>(.*?)<\/h3>/",$couponHtml,$matchcoupondesc);
				if(!empty($matchcoupondesc[0])){
					$couponData['CouponDesc']=del_br_space_by_str(strip_tags($matchcoupondesc[1][0]));
				}

				//有效期
				preg_match_all("/<strong>Gültig bis:<\/strong>\s*(.*?)\s*<\/span>/",$couponHtml,$coupondate);
				if(!empty($coupondate[0])){
					$couponData['ExpirationDate']=date('Y-m-d', strtotime($coupondate[1][0]));
				}
				if(empty($couponData['ExpirationDate'])){
					$couponData['ExpirationDate']='0000-00-00';
				}

				$rank++;

				if(!empty($couponData['CouponID']))
					$couponRankMap[''.$couponData['CouponID']]=$rank;
				if(!isset($couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']])){
					$sqlIns.="('{$row['CompetitorStoreId']}','{$row['CompetitorId']}','{$couponData['CouponID']}','{$couponData['CouponTitle']}','{$couponData['GoUrl']}','{$couponData['CouponDesc']}','{$couponData['CouponRestriction']}','{$couponData['CouponCode']}','{$couponData['Used']}','{$couponData['ExpirationDate']}','{$couponData['Country']}','{$couponData['MaybeValid']}','".date("Y-m-d H:i:s")."','{$couponData['type']}'),";
				}else{
					$expiresVo=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']];
					if($expiresVo['ExpirationDate']!=$couponData['ExpirationDate'] || $expiresVo['CouponTitle']!=$couponData['CouponTitle']  || $expiresVo['CouponCode']!=$couponData['CouponCode'] || $expiresVo['CouponDesc']!=$couponData['CouponDesc'] || $expiresVo['CouponCodeUrl']!=$couponData['CouponCodeUrl'] || $expiresVo['type']!=$couponData['type']){
						$sqlUp="update cp_competitor_store_coupon set ExpirationDate='{$couponData['ExpirationDate']}',CouponTitle='{$couponData['CouponTitle']}',CouponDesc='{$couponData['CouponDesc']}',CouponCodeUrl='{$couponData['CouponCodeUrl']}',type='{$couponData['type']}',IsUpdateCodeUrl='{$couponData['IsUpdateCodeUrl']}',LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$expiresVo['ID']}";
						$db->query($sqlUp);

					}
					$couponOnMap[]=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']]['ID'];
				}
			}
			if($sqlIns!=$sqlInsPre){
				$sqlIns=substr($sqlIns,0,strlen($sqlIns)-1);
				$sqlIns.=";";
				$db->query($sqlIns);
			}
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
		//end 21
	}else if($row['CompetitorId']==22){
		preg_match_all("/href=\"(\/gutscheine\/[^\/\"]+)\"/i", $htmlContent, $matchUrl,PREG_SET_ORDER);
		$sqlInsUrl = $sqlInsUrlPre = "insert ignore into cp_temp_competitor_store(StoreUrl,CompetitorId,AddTime) values ";

		if (!empty($matchUrl)) {
			foreach ($matchUrl as $url) {
				$sqlInsUrl .= "('" .'https://www.gutscheinsammler.de'. $url[1] . "',{$row['CompetitorId']},'" . date("Y-m-d H:i:s") . "'),";
			}
			if ($sqlInsUrl != $sqlInsUrlPre) {
				$sqlInsUrl = substr($sqlInsUrl, 0, strlen($sqlInsUrl) - 1);
				$sqlInsUrl .= ";";
				$db->query($sqlInsUrl);
			}
		}

//MetaTitle
		preg_match_all("/<title>([^<]+)<\/title>/", $htmlContent, $matchTitle, PREG_SET_ORDER);
		$cs_data_arr['MetaTitle'] = empty($matchTitle) ? "" : $matchTitle[0][1];
		//keywords No Meta keywords
		$cs_data_arr['MetaKeywords'] = '';
		//meta description
		preg_match_all("/<meta\s*name=\"description\"\s*content=\"([^\"]+)\"/", $htmlContent, $matchMetaDesc, PREG_SET_ORDER);
		$cs_data_arr['MetaDescription'] = empty($matchMetaDesc) ? "" : $matchMetaDesc[0][1];
		//描述
		preg_match_all("/itemprop=\"description\">([\s\S]+?)<\/div>/", $htmlContent, $matchDesc, PREG_SET_ORDER);
		$cs_data_arr['Description'] = empty($matchDesc)?'':trim(del_br_space_by_str(strip_tags($matchDesc[0][1])));
//H1
		preg_match_all("/<h1 class=\"wg-module-heading\"[^>]+>([^<]+)<\/h1>/", $htmlContent, $matchH1, PREG_SET_ORDER);
		$cs_data_arr['H1'] = empty($matchH1) ? "" : trim(strip_tags($matchH1[0][1]));
//Merchant Go Url
		preg_match_all("/<span class=\"wg-anchor\".*title=\"([^\"]+)\"/", $htmlContent, $matchGo);
		$cs_data_arr['MerchantGoUrl'] = empty($matchGo) ? "" :$matchGo[1][0];
		//Screen Img
		preg_match_all("/\"shop-info-media_shop-logo\" \s+alt=\".*?\"\s+ src=\"([^\"]+)\"/",$htmlContent,$matchImg);
		$cs_data_arr['ScreenImg'] = empty($matchImg) ? "" :'https:'.$matchImg[1][0];
//Address
		preg_match_all("/class=\"icomoon-shop-info-location\"><\/i><p>([\s\S]+?)<\/p>/", $htmlContent, $matchAddress, PREG_SET_ORDER);
		$cs_data_arr['Address'] = empty($matchAddress[0])?'':trim(del_br_space_by_str(preg_replace("/<[^<>]+>/", ' ',$matchAddress[0][1])));
//Tel
		preg_match_all("/class=\"fa fa-phone\"><\/i>(.*?)\s+<\/p>/", $htmlContent, $matchTel, PREG_SET_ORDER);
		$cs_data_arr['Tel'] = empty($matchTel[0]) ? "" : trim(strip_tags($matchTel[0][1]));
//Email
		preg_match_all("/title=\"([^\"]+)\"><i class=\"fa fa-envelope-o\">/", $htmlContent, $matchEmail);
		$cs_data_arr['Email'] = empty($matchEmail[0]) ? "" :$matchEmail[1][0];
		
		$sql = $pre_sql = "update cp_competitor_store set ";
		foreach ($cs_data_arr as $key => $val) {
			if (strlen($val) > 100) {
				$val = del_br_space_by_str($val);
			}
			if (strrpos($key, "meta") !== false) {
				$val = substr($val, 0, 250);
			}
			$sql .= " {$key} = '" . addslashes($val) . "' , ";
		}

		if($sql != $pre_sql ){
			$sql.=" LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$row['CompetitorStoreId']}";
			$GLOBALS['db']->query($sql);
		}

		$rank=0;
		$sqlIns=$sqlInsPre="INSERT ignore into cp_competitor_store_coupon (CompetitorStoreId,CompetitorId,CouponID,CouponTitle,GoUrl,CouponCode,ExpirationDate,CouponDesc,Used,Country,MaybeValid,AddTime,type,CouponCodeUrl,IsUpdateCodeUrl) values ";
//    coupons 数据
		preg_match_all("/<div id=\"shopcodes\"([\s\S]+?)<h2 class=\"wg-element-heading\">/", $htmlContent, $matchValidCoupon);

		$matchCoupon=explode('<div class="wg-discount single-voucher',$matchValidCoupon[1][0]);
		if (!empty($matchCoupon)) {

			for($i=0;$i<count($matchCoupon);$i++){
				$couponHtml=$matchCoupon[$i];

				$couponData['MaybeValid'] = 1;
				$couponData['Country'] = "DE";

				$couponData['CouponID'] =$rank;
				$couponData['CouponTitle']='';
				$couponData['CouponDesc']='';
				$couponData['GoUrl']='';
				$couponData['type']='deal';
				$couponData['Used']='';
				$couponData['CouponRestriction']='';
				$couponData['ExpirationDate']='';
				$couponData['CouponCodeUrl'] = "";
				$couponData['CouponCode']='';
				$couponData['IsUpdateCodeUrl']=0;

				//type && code
				preg_match_all("/co-target=\"\?v=([^\"]+)\"/",$couponHtml,$matchCode);
				$couponData['CouponCodeUrl'] = empty($matchCode[0])?'':'code';
				$couponData['type']=empty($matchCode[0])?'deal':'code';
				if($couponData['CouponCodeUrl']=='code'){
					$couponData['IsUpdateCodeUrl']=1;
					$couponData['CouponCodeUrl']='https://www.gutscheinsammler.de/get-vouchercode/'.$matchCode[1][0];
				}

				//couponId
				preg_match_all("/id=\"voucher-(\w+)\"/",$couponHtml,$matchCouponId);
				if(!empty($matchCouponId[0])){
					$couponData['CouponID']=$matchCouponId[1][0];
				}else{
					$couponData['CouponID']=$rank;
				}

				//title
				preg_match_all("/\"wg-discount_info_title\">(.*?)<\/h3>/",$couponHtml,$matchcoupontitle);
				$couponData['CouponTitle']= empty($matchcoupontitle[0])?'':trim(strip_tags($matchcoupontitle[1][0]));
				//gourl
				$couponData['GoUrl']=$cs_data_arr['MerchantGoUrl'];

				//desc,该站点没有coupon描述

				//有效期
				preg_match_all("/Gültig bis ([\d\.]+)\s/",$couponHtml,$coupondate);
				if(!empty($coupondate[0])){
					$couponData['ExpirationDate']=date('Y-m-d', strtotime($coupondate[1][0]));
				}
				if(empty($couponData['ExpirationDate'])){
					$couponData['ExpirationDate']='0000-00-00';
				}

				$rank++;
				if(!empty($couponData['CouponID']))
					$couponRankMap[''.$couponData['CouponID']]=$rank;
				if(!isset($couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']])){
					$sqlIns.="('{$row['CompetitorStoreId']}','{$row['CompetitorId']}','{$couponData['CouponID']}','{$couponData['CouponTitle']}','{$couponData['GoUrl']}','{$couponData['CouponCode']}','{$couponData['ExpirationDate']}','{$couponData['CouponDesc']}','{$couponData['Used']}','{$couponData['Country']}','{$couponData['MaybeValid']}','".date("Y-m-d H:i:s")."','{$couponData['type']}','{$couponData['CouponCodeUrl']}','{$couponData['IsUpdateCodeUrl']}'),";
				}else{
					$expiresVo=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']];
					if($expiresVo['ExpirationDate']!=$couponData['ExpirationDate'] || $expiresVo['CouponTitle']!=$couponData['CouponTitle'] || $expiresVo['CouponDesc']!=$couponData['CouponDesc'] || $expiresVo['CouponCodeUrl']!=$couponData['CouponCodeUrl'] || $expiresVo['type']!=$couponData['type']){
						$sqlUp="update cp_competitor_store_coupon set ExpirationDate='{$couponData['ExpirationDate']}',CouponTitle='{$couponData['CouponTitle']}',CouponDesc='{$couponData['CouponDesc']}',CouponCodeUrl='{$couponData['CouponCodeUrl']}',type='{$couponData['type']}',IsUpdateCodeUrl='{$couponData['IsUpdateCodeUrl']}',LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$expiresVo['ID']}";
						$db->query($sqlUp);
					}
					$couponOnMap[]=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']]['ID'];
				}
			}
			if($sqlIns!=$sqlInsPre){
				$sqlIns=substr($sqlIns,0,strlen($sqlIns)-1);
				$sqlIns.=";";
				$db->query($sqlIns);
			}
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
		//end coupons 22
	}else if($row['CompetitorId']==23){
		preg_match_all("/href=\"(\/.*?-gutschein\/)\"/i", $htmlContent, $matchUrl,PREG_SET_ORDER);
		$sqlInsUrl = $sqlInsUrlPre = "insert ignore into cp_temp_competitor_store(StoreUrl,CompetitorId,AddTime) values ";
        $site_pre = 'https://www.rabattcode.de';
		if (!empty($matchUrl)) {
			foreach ($matchUrl as $url) {
				$sqlInsUrl .= "('" .$site_pre. $url[1] . "',{$row['CompetitorId']},'" . date("Y-m-d H:i:s") . "'),";
			}
			if ($sqlInsUrl != $sqlInsUrlPre) {
				$sqlInsUrl = substr($sqlInsUrl, 0, strlen($sqlInsUrl) - 1);
				$sqlInsUrl .= ";";
				$db->query($sqlInsUrl);
			}
		}

//MetaTitle
        preg_match_all("/<title>([^<]+)<\/title>/", $htmlContent, $matchTitle, PREG_SET_ORDER);
        $cs_data_arr['MetaTitle'] = empty($matchTitle) ? "" : $matchTitle[0][1];
//keywords No Meta keywords
        $cs_data_arr['MetaKeywords'] = '';
//meta description
        preg_match_all("/<meta\s*name=\"description\"\s*content=\"([^\"]+)\"/", $htmlContent, $matchMetaDesc, PREG_SET_ORDER);
        $cs_data_arr['MetaDescription'] = empty($matchMetaDesc) ? "" : $matchMetaDesc[0][1];
//描述
        preg_match_all("/<\/h1>\s*<p>(.*?)<\/p>/", $htmlContent, $matchDesc, PREG_SET_ORDER);
        $cs_data_arr['Description'] = empty($matchDesc)?'':trim(del_br_space_by_str(strip_tags($matchDesc[0][1])));
//H1
        preg_match_all("/<h1[^>]+>(.*?)<\/h1>/", $htmlContent, $matchH1, PREG_SET_ORDER);
        $cs_data_arr['H1'] = empty($matchH1) ? "" : trim(strip_tags($matchH1[0][1]));
//Merchant Go Url
        $matchGo = Selector::select($htmlContent,"//a[@class=\"visit-shop hidden-xs\"]/@href");
        $cs_data_arr['MerchantGoUrl'] = empty($matchGo)?'':$site_pre.$matchGo;
//Screen Img
        preg_match_all("/\"header-store-thumb\">\s*<a href=\"[^\"]+\" target=\"_blank\"><img src=\"([^\"]+)\"/",$htmlContent,$matchImg);
        $cs_data_arr['ScreenImg'] = empty($matchImg[0]) ? "" :$site_pre.$matchImg[1][0];

		$sql = $pre_sql = "update cp_competitor_store set ";
		foreach ($cs_data_arr as $key => $val) {
			if (strlen($val) > 100) {
				$val = del_br_space_by_str($val);
			}
			if (strrpos($key, "meta") !== false) {
				$val = substr($val, 0, 250);
			}
			$sql .= " {$key} = '" . addslashes($val) . "' , ";
		}

		if($sql != $pre_sql ){
			$sql.=" LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$row['CompetitorStoreId']}";
			$GLOBALS['db']->query($sql);
		}

		$rank=0;
		$sqlIns=$sqlInsPre="INSERT ignore into cp_competitor_store_coupon (CompetitorStoreId,CompetitorId,CouponID,CouponTitle,GoUrl,CouponCode,ExpirationDate,CouponDesc,Used,Country,MaybeValid,AddTime,type,CouponCodeUrl,IsUpdateCodeUrl) values ";
//    coupons 数据
        preg_match_all("/class=\"store-listings\">([\s\S]+)<div class=\"widget-area col-md-4\">/", $htmlContent, $matchValidCoupon);

        $matchCoupon=explode('<div class="voucher-item"',$matchValidCoupon[1][0]);
        if (!empty($matchCoupon)) {

            for($i=1;$i<count($matchCoupon);$i++){
                $couponHtml=$matchCoupon[$i];


                $couponData['MaybeValid'] = 1;
                $couponData['Country'] = "DE";

                $couponData['CouponID'] =$rank;
                $couponData['CouponTitle']='';
                $couponData['CouponDesc']='';
                $couponData['GoUrl']='';
                $couponData['type']='deal';
                $couponData['Used']='';
                $couponData['CouponRestriction']='';
                $couponData['ExpirationDate']='';
                $couponData['CouponCodeUrl'] = "";
                $couponData['CouponCode']='';
                $couponData['IsUpdateCodeUrl']=0;

                //couponId
                preg_match_all("/data-id=\"(\d+)\"/",$couponHtml,$matchCouponId);
                if(!empty($matchCouponId[0])){
                    $couponData['CouponID']=$matchCouponId[1][0];
                }

                //type && code
                if(!empty($couponData['CouponID'])){
                    $couponData['IsUpdateCodeUrl']=1;
                    $couponData['CouponCodeUrl']=$site_pre.'/xhr/global/modal/?id='.$matchCouponId[1][0];
                }

                //title
                preg_match_all("/<h3 class=\"coupon-title\"><a href=\"([^\"]+)\" rel=\"nofollow\">(.*?)<\/a>/",$couponHtml,$matchcoupontitle);
                $couponData['CouponTitle']= empty($matchcoupontitle[0])?'':trim(strip_tags($matchcoupontitle[2][0]));
                //gourl
                $couponData['GoUrl']=empty($matchcoupontitle[0])?'':$site_pre.trim(strip_tags($matchcoupontitle[1][0]));

                //desc
                preg_match_all("/<div class=\"coupon-des\">(.*?)<\/div>/",$couponHtml,$matchCoupondesc);
                $couponData['CouponDesc']=empty($matchCoupondesc[0])?'':trim(strip_tags($matchCoupondesc[1][0]));

                //有效期
                preg_match_all("/Gültig bis ([\d\.]+)/",$couponHtml,$coupondate);
                if(!empty($coupondate[0])){
                    $couponData['ExpirationDate']=date('Y-m-d', strtotime($coupondate[1][0]));
                }
                if(empty($couponData['ExpirationDate'])){
                    $couponData['ExpirationDate']='0000-00-00';
                }


				$rank++;
				if(!empty($couponData['CouponID']))
					$couponRankMap[''.$couponData['CouponID']]=$rank;
				if(!isset($couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']])){
					$sqlIns.="('{$row['CompetitorStoreId']}','{$row['CompetitorId']}','{$couponData['CouponID']}','{$couponData['CouponTitle']}','{$couponData['GoUrl']}','{$couponData['CouponCode']}','{$couponData['ExpirationDate']}','{$couponData['CouponDesc']}','{$couponData['Used']}','{$couponData['Country']}','{$couponData['MaybeValid']}','".date("Y-m-d H:i:s")."','{$couponData['type']}','{$couponData['CouponCodeUrl']}','{$couponData['IsUpdateCodeUrl']}'),";
				}else{
					$expiresVo=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']];
					if($expiresVo['ExpirationDate']!=$couponData['ExpirationDate'] || $expiresVo['CouponTitle']!=$couponData['CouponTitle'] || $expiresVo['CouponDesc']!=$couponData['CouponDesc'] || $expiresVo['CouponCodeUrl']!=$couponData['CouponCodeUrl'] || $expiresVo['type']!=$couponData['type']){
						$sqlUp="update cp_competitor_store_coupon set ExpirationDate='{$couponData['ExpirationDate']}',CouponTitle='{$couponData['CouponTitle']}',CouponDesc='{$couponData['CouponDesc']}',CouponCodeUrl='{$couponData['CouponCodeUrl']}',type='{$couponData['type']}',IsUpdateCodeUrl='{$couponData['IsUpdateCodeUrl']}',LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$expiresVo['ID']}";
						$db->query($sqlUp);
					}
					$couponOnMap[]=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']]['ID'];
				}
			}
			if($sqlIns!=$sqlInsPre){
				$sqlIns=substr($sqlIns,0,strlen($sqlIns)-1);
				$sqlIns.=";";
				$db->query($sqlIns);
			}

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
		//end coupons 22
	}else if($row['CompetitorId']==25){
		//25
        preg_match_all("/href=\"(\/code-promo\/magasins\/[^\"\/\?\#]+)\"/i", $htmlContent, $matchUrl,PREG_SET_ORDER);
        $sqlInsUrl = $sqlInsUrlPre = "insert ignore into cp_temp_competitor_store(StoreUrl,CompetitorId,AddTime) values ";
//site_pre
        $site_pre='https://www.groupon.fr';
		if (!empty($matchUrl)) {
			foreach ($matchUrl as $url) {
				$sqlInsUrl .= "('" .$site_pre. $url[1] . "',{$row['CompetitorId']},'" . date("Y-m-d H:i:s") . "'),";
			}
			if ($sqlInsUrl != $sqlInsUrlPre) {
				$sqlInsUrl = substr($sqlInsUrl, 0, strlen($sqlInsUrl) - 1);
				$sqlInsUrl .= ";";
				$db->query($sqlInsUrl);
			}
		}
		$cs_data_arr = array();

//MetaTitle
        preg_match_all("/<title>([^<]+)<\/title>/", $htmlContent, $matchTitle, PREG_SET_ORDER);
        $cs_data_arr['MetaTitle'] = empty($matchTitle) ? "" : $matchTitle[0][1];
//keywords No Meta keywords
        preg_match_all("/<meta name=\"keywords\" content=\"([^\"]+)\"\s*\/>/", $htmlContent, $matchMetaKey, PREG_SET_ORDER);
        $cs_data_arr['MetaKeywords'] = empty($matchMetaKey) ? "" : $matchMetaKey[0][1];
//meta description
        preg_match_all("/<meta name=\"description\" content=\"([^\"]+)\"\s*\/>/", $htmlContent, $matchMetaDesc, PREG_SET_ORDER);
        $cs_data_arr['MetaDescription'] = empty($matchMetaDesc) ? "" : $matchMetaDesc[0][1];
//描述
        preg_match_all("/<\/h3><p class=\"should-truncate\">([^<]+)<\/p>/", $htmlContent, $matchDesc, PREG_SET_ORDER);
        $cs_data_arr['Description'] = empty($matchDesc)?'':trim(del_br_space_by_str(strip_tags($matchDesc[0][1])));
//H1
        preg_match_all("/<h1[^>]*>([^<]+)<\/h1>/", $htmlContent, $matchH1, PREG_SET_ORDER);
        $cs_data_arr['H1'] = empty($matchH1) ? "" : trim(strip_tags($matchH1[0][1]));
//Merchant Go Url
        preg_match_all("/data-affiliateurl=\"(\/code-promo\/magasins\/click\/[^\"]+)\"/", $htmlContent, $matchGo);
        $cs_data_arr['MerchantGoUrl'] = empty($matchGo[0]) ? "" :$site_pre.$matchGo[1][0];
//Screen Img
        preg_match_all("/data-bhw=\"StoreImageLink\"><img src=\"([^\"]+)\"/",$htmlContent,$matchImg);
        $cs_data_arr['ScreenImg'] = empty($matchImg[0]) ? "" :$matchImg[1][0];

		$sql = $pre_sql = "update cp_competitor_store set ";
		foreach ($cs_data_arr as $key => $val) {
			if (strlen($val) > 100) {
				$val = del_br_space_by_str($val);
			}
			if (strrpos($key, "meta") !== false) {
				$val = substr($val, 0, 250);
			}
			$sql .= " {$key} = '" . addslashes($val) . "' , ";
		}
		if($sql != $pre_sql ){
			$sql.=" LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$row['CompetitorStoreId']}";
			$GLOBALS['db']->query($sql);
		}

		$rank=0;
		$sqlIns=$sqlInsPre="INSERT ignore into cp_competitor_store_coupon (CompetitorStoreId,CompetitorId,CouponID,CouponTitle,GoUrl,CouponCode,ExpirationDate,CouponDesc,Used,Country,MaybeValid,AddTime,type,CouponCodeUrl,IsUpdateCodeUrl) values ";
//    coupons 数据
        preg_match_all("/<div id=\"coupons-list\"([\s\S]+?)<\/div><div class=\"row\"><\/div>/", $htmlContent, $matchList);

        if (!empty($matchList[0])) {
            $matchCoupon = explode("class=\"coupon row\"",$matchList[1][0]);
			for($i=1;$i<count($matchCoupon);$i++){
				$couponHtml=$matchCoupon[$i];


				$couponData['MaybeValid'] = 1;
				$couponData['Country'] = "FR";

				$couponData['CouponID'] =$rank;
				$couponData['CouponTitle']='';
				$couponData['CouponDesc']='';
				$couponData['GoUrl']='';
				$couponData['type']='deal';
				$couponData['Used']='';
				$couponData['CouponRestriction']='';
				$couponData['ExpirationDate']='';
				$couponData['CouponCodeUrl'] = "";
				$couponData['CouponCode']='';
				$couponData['IsUpdateCodeUrl']=0;

                //couponId
                preg_match_all("/data-uuid=\"([^\"]+)\"/",$couponHtml,$matchCouponId);
                if(!empty($matchCouponId[0])){
                    $couponData['CouponID']=$matchCouponId[1][0];
                }

				//type && code
                preg_match_all("/data-clipboard-text=\"([^\"]+)\"/", $couponHtml,$matchType);
                if(!empty($matchType[0])){
                    $couponData['type']='code';
                    $couponData['CouponCode']=$matchType[1][0];
                }

                //title
                preg_match_all("/<h4>([\s\S]+?)<\/h4>/",$couponHtml,$matchcoupontitle);
                $couponData['CouponTitle']= empty($matchcoupontitle[0])?'':trim(strip_tags($matchcoupontitle[1][0]));

				//gourl
                $couponData['GoUrl']=empty($couponData['CouponID'])?'':$site_pre."/code-promo/click/".$couponData['CouponID'];

                //desc
                preg_match_all("/itemprop=\"description\">(.*?)<\/span>/",$couponHtml,$matchCoupondesc);
                $couponData['CouponDesc']=empty($matchCoupondesc[0])?'':trim(strip_tags($matchCoupondesc[1][0]));

				//有效期
                preg_match_all("/(\d{2}\/\d{2}\/\d{4})\sUTC/",$couponHtml,$coupondate);
                if(!empty($coupondate[0])){
                    $couponData['ExpirationDate']=date('Y-m-d',strtotime(str_replace("/",'-',$coupondate[1][0])));
                }
				if(empty($couponData['ExpirationDate'])){
					$couponData['ExpirationDate']='0000-00-00';
				}

				$rank++;
				if(!empty($couponData['CouponID']))
					$couponRankMap[''.$couponData['CouponID']]=$rank;
				if(!isset($couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']])){
					$sqlIns.="('{$row['CompetitorStoreId']}','{$row['CompetitorId']}','{$couponData['CouponID']}','{$couponData['CouponTitle']}','{$couponData['GoUrl']}','{$couponData['CouponCode']}','{$couponData['ExpirationDate']}','{$couponData['CouponDesc']}','{$couponData['Used']}','{$couponData['Country']}','{$couponData['MaybeValid']}','".date("Y-m-d H:i:s")."','{$couponData['type']}','{$couponData['CouponCodeUrl']}','{$couponData['IsUpdateCodeUrl']}'),";
				}else{
					$expiresVo=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']];
					if($expiresVo['ExpirationDate']!=$couponData['ExpirationDate'] || $expiresVo['CouponTitle']!=$couponData['CouponTitle']  || $expiresVo['CouponDesc']!=$couponData['CouponDesc'] || $expiresVo['CouponCodeUrl']!=$couponData['CouponCodeUrl'] || $expiresVo['type']!=$couponData['type']){
						$sqlUp="update cp_competitor_store_coupon set ExpirationDate='{$couponData['ExpirationDate']}',CouponTitle='{$couponData['CouponTitle']}',CouponDesc='{$couponData['CouponDesc']}',CouponCodeUrl='{$couponData['CouponCodeUrl']}',type='{$couponData['type']}',IsUpdateCodeUrl='{$couponData['IsUpdateCodeUrl']}',LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$expiresVo['ID']}";
						$db->query($sqlUp);
					}
					$couponOnMap[]=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']]['ID'];
				}
			}
			if($sqlIns!=$sqlInsPre){
				$sqlIns=substr($sqlIns,0,strlen($sqlIns)-1);
				$sqlIns.=";";
				$db->query($sqlIns);
			}

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
		//end coupons 22
	}else if($row['CompetitorId']==26){
		//获取竞争对手其他store链接  start
        preg_match_all("/href=\"(https:\/\/www.ma-reduc.com\/reductions-pour-[^\"]+?.php)\"/i", $htmlContent, $matchUrl,PREG_SET_ORDER);
		$sqlInsUrl = $sqlInsUrlPre = "insert ignore into cp_temp_competitor_store(StoreUrl,CompetitorId,AddTime) values ";

		//site_pre
		$site_pre='';
		if (!empty($matchUrl)) {
			foreach ($matchUrl as $url) {
				$sqlInsUrl .= "('" .$site_pre. $url[1] . "',{$row['CompetitorId']},'" . date("Y-m-d H:i:s") . "'),";
			}
			if ($sqlInsUrl != $sqlInsUrlPre) {
				$sqlInsUrl = substr($sqlInsUrl, 0, strlen($sqlInsUrl) - 1);
				$sqlInsUrl .= ";";
				$db->query($sqlInsUrl);
			}
		}
		$cs_data_arr = array();

//MetaTitle
		preg_match_all("/<title>([^<]+)<\/title>/", $htmlContent, $matchTitle, PREG_SET_ORDER);
		$cs_data_arr['MetaTitle'] = empty($matchTitle) ? "" : $matchTitle[0][1];
		//keywords No Meta keywords
		$cs_data_arr['MetaKeywords'] = '';
		//meta description
		preg_match_all("/<meta content=\"([^\"]+)\" name=\"description\"/", $htmlContent, $matchMetaDesc, PREG_SET_ORDER);
		$cs_data_arr['MetaDescription'] = empty($matchMetaDesc) ? "" : $matchMetaDesc[0][1];
		//描述
		preg_match_all("/h2 class=\"text-overflow hidden-xs\">([^<]+)<\/h2>/", $htmlContent, $matchDesc, PREG_SET_ORDER);
		$cs_data_arr['Description'] = empty($matchDesc)?'':trim(del_br_space_by_str(strip_tags($matchDesc[0][1])));
//H1
		preg_match_all("/<h1[^>]*>(.*?)<\/h1>/", $htmlContent, $matchH1, PREG_SET_ORDER);
		$cs_data_arr['H1'] = empty($matchH1) ? "" : trim(strip_tags($matchH1[0][1]));
//Merchant Go Url
		preg_match_all("/data-out=\"{&quot;r&quot;:1,&quot;m&quot;:(\d+)/", $htmlContent, $matchGo);
		$cs_data_arr['MerchantGoUrl'] = empty($matchGo[0]) ? "" :'http://www.ma-reduc.com/out/merchant/'.$matchGo[1][0];
		//Screen Img
		preg_match_all("/data-src=\"([^\"]+)\" data-src-retina/",$htmlContent,$matchImg);
		$cs_data_arr['ScreenImg'] = empty($matchImg[0]) ? "" :$matchImg[1][1];


		$sql = $pre_sql = "update cp_competitor_store set ";
		foreach ($cs_data_arr as $key => $val) {
			if (strlen($val) > 100) {
				$val = del_br_space_by_str($val);
			}
			if (strrpos($key, "meta") !== false) {
				$val = substr($val, 0, 250);
			}
			$sql .= " {$key} = '" . addslashes($val) . "' , ";
		}
		if($sql != $pre_sql ){
			$sql.=" LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$row['CompetitorStoreId']}";
			$GLOBALS['db']->query($sql);
		}

		$rank=0;
		$sqlIns=$sqlInsPre="INSERT ignore into cp_competitor_store_coupon (CompetitorStoreId,CompetitorId,CouponID,CouponTitle,GoUrl,CouponCode,ExpirationDate,CouponDesc,Used,Country,MaybeValid,AddTime,type,CouponCodeUrl,IsUpdateCodeUrl) values ";
//    coupons 数据
        preg_match_all("/class=\"abtest-coupon-list\">([\s\S]+?)id=\"div-gpt-ad-1488272603559-0\"/", $htmlContent, $matchValidCoupon);

        $matchCoupon=explode('class="abtest-coupon-web',$matchValidCoupon[1][0]);
        if (!empty($matchCoupon)) {

            for($i=1;$i<count($matchCoupon);$i++){
                $couponHtml=$matchCoupon[$i];


                $couponData['MaybeValid'] = 1;
                $couponData['Country'] = "FR";

                $couponData['CouponID'] =$rank;
                $couponData['CouponTitle']='';
                $couponData['CouponDesc']='';
                $couponData['GoUrl']='';
                $couponData['type']='deal';
                $couponData['Used']='';
                $couponData['CouponRestriction']='';
                $couponData['ExpirationDate']='';
                $couponData['CouponCodeUrl'] = "";
                $couponData['CouponCode']='';
                $couponData['IsUpdateCodeUrl']=0;

                //couponId
                preg_match_all("/data-id=\"(\d+)\"/",$couponHtml,$matchCouponId);
                if(!empty($matchCouponId[0])){
                    $couponData['CouponID']=$matchCouponId[1][0];
                }

////type && code
//        无法分析具体如何获取code
//        preg_match_all("/abtest-coupon-code/", $couponHtml,$matchType);
//        if(!empty($matchType[0])){
//            $couponData['IsUpdateCodeUrl']=1;
//            $couponData['type']='code';
//            $couponData['CouponCodeUrl']=$row['Url'].'?c='.$couponData['CouponID'];
//        }

                //title
                preg_match_all("/<h3>([^<]+)/",$couponHtml,$matchcoupontitle);
                $couponData['CouponTitle']= empty($matchcoupontitle[0])?'':trim(strip_tags($matchcoupontitle[1][0]));


                //gourl
//            preg_match_all("/href=\"([^\"]+)\" class=\"popup go_crd/",$couponHtml,$matchcouponUrl);
                $couponData['GoUrl']='http://www.ma-reduc.com/out/coupon/'.$couponData['CouponID'];

                //desc
//            preg_match_all("/<\/a> <p>([^>]+)/",$couponHtml,$matchCoupondesc);
                $couponData['CouponDesc']='';

                //有效期
                preg_match_all("/class=\"coupon-add\">Fin le ([^<]+)<\/span>/",$couponHtml,$coupondate);
                if(!empty($coupondate[0])){
                    $couponData['ExpirationDate']=date('Y-m-d',strtotime(str_replace('/','-' , $coupondate[1][0])));
                }
                if(empty($couponData['ExpirationDate'])){
                    $couponData['ExpirationDate']='0000-00-00';
                }


				$rank++;
				if(!empty($couponData['CouponID']))
					$couponRankMap[''.$couponData['CouponID']]=$rank;
				if(!isset($couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']])){
					$sqlIns.="('{$row['CompetitorStoreId']}','{$row['CompetitorId']}','{$couponData['CouponID']}','{$couponData['CouponTitle']}','{$couponData['GoUrl']}','{$couponData['CouponCode']}','{$couponData['ExpirationDate']}','{$couponData['CouponDesc']}','{$couponData['Used']}','{$couponData['Country']}','{$couponData['MaybeValid']}','".date("Y-m-d H:i:s")."','{$couponData['type']}','{$couponData['CouponCodeUrl']}','{$couponData['IsUpdateCodeUrl']}'),";
				}else{
					$expiresVo=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']];
					if($expiresVo['ExpirationDate']!=$couponData['ExpirationDate'] || $expiresVo['CouponTitle']!=$couponData['CouponTitle'] || $expiresVo['CouponDesc']!=$couponData['CouponDesc'] || $expiresVo['CouponCodeUrl']!=$couponData['CouponCodeUrl'] || $expiresVo['type']!=$couponData['type']){
						$sqlUp="update cp_competitor_store_coupon set ExpirationDate='{$couponData['ExpirationDate']}',CouponTitle='{$couponData['CouponTitle']}',CouponDesc='{$couponData['CouponDesc']}',CouponCodeUrl='{$couponData['CouponCodeUrl']}',type='{$couponData['type']}',IsUpdateCodeUrl='{$couponData['IsUpdateCodeUrl']}',LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$expiresVo['ID']}";
						$db->query($sqlUp);
					}
					$couponOnMap[]=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']]['ID'];
				}
			}
			if($sqlIns!=$sqlInsPre){
				$sqlIns=substr($sqlIns,0,strlen($sqlIns)-1);
				$sqlIns.=";";
				$db->query($sqlIns);
			}

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
		//end coupons 22
	}else if($row['CompetitorId']==28){
		//获取竞争对手其他store链接  start
        preg_match_all("/href=\"(\/codes-promo\/[^\/]+\/)\"/i", $htmlContent, $matchUrl,PREG_SET_ORDER);
		$sqlInsUrl = $sqlInsUrlPre = "insert ignore into cp_temp_competitor_store(StoreUrl,CompetitorId,AddTime) values ";

		//site_pre
        $site_pre='https://www.frcodespromo.com';
		if (!empty($matchUrl)) {
			foreach ($matchUrl as $url) {
				$sqlInsUrl .= "('" .$site_pre. $url[1] . "',{$row['CompetitorId']},'" . date("Y-m-d H:i:s") . "'),";
			}
			if ($sqlInsUrl != $sqlInsUrlPre) {
				$sqlInsUrl = substr($sqlInsUrl, 0, strlen($sqlInsUrl) - 1);
				$sqlInsUrl .= ";";
				$db->query($sqlInsUrl);
			}
		}
		$cs_data_arr = array();

//MetaTitle
        preg_match_all("/<title>([^<]+)<\/title>/", $htmlContent, $matchTitle, PREG_SET_ORDER);
        $cs_data_arr['MetaTitle'] = empty($matchTitle) ? "" : $matchTitle[0][1];
        //keywords No Meta keywords
        $cs_data_arr['MetaKeywords'] = '';
        //meta description
        preg_match_all("/<meta content=\"([^\"]+)\" name=\"description\"/", $htmlContent, $matchMetaDesc, PREG_SET_ORDER);
        $cs_data_arr['MetaDescription'] = empty($matchMetaDesc) ? "" : $matchMetaDesc[0][1];
        //描述
        preg_match_all("/class=\"store_de\">([\s\S]+?)<\/div>/", $htmlContent, $matchDesc, PREG_SET_ORDER);
        $cs_data_arr['Description'] = empty($matchDesc)?'':trim(del_br_space_by_str(strip_tags($matchDesc[0][1])));
//H1
        preg_match_all("/<h1[^>]*>(.*?)<\/h1>/", $htmlContent, $matchH1, PREG_SET_ORDER);
        $cs_data_arr['H1'] = empty($matchH1) ? "" : trim(strip_tags($matchH1[0][1]));
//Merchant Go Url
        preg_match_all("/<div class=\"mer_pic\">\s+<a href=\"(.*?)\"[^>]+>\s+<img src=\"([^\"]+)\"/", $htmlContent, $matchGo);
        $cs_data_arr['MerchantGoUrl'] = empty($matchGo[0]) ? "" :$site_pre.$matchGo[1][0];
        //Screen Img
        //preg_match_all("/target=\"_blank\"> <img src=\"(.*?)\"\/> <\/a> <a href=\"[^\"]+\" rel=\"nofollow\" target=\"_blank\" class=\"golink\"/",$htmlContent,$matchImg);
        $cs_data_arr['ScreenImg'] = empty($matchGo[0]) ? "" :$matchGo[2][0];

		$sql = $pre_sql = "update cp_competitor_store set ";

		foreach ($cs_data_arr as $key => $val) {
			if (strlen($val) > 100) {
				$val = del_br_space_by_str($val);
			}
			if (strrpos($key, "meta") !== false) {
				$val = substr($val, 0, 250);
			}
			$sql .= " {$key} = '" . addslashes($val) . "' , ";
		}

		if($sql != $pre_sql ){
			$sql.=" LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$row['CompetitorStoreId']}";
			$GLOBALS['db']->query($sql);
		}

		$rank=0;
		$sqlIns=$sqlInsPre="INSERT ignore into cp_competitor_store_coupon (CompetitorStoreId,CompetitorId,CouponID,CouponTitle,GoUrl,CouponCode,ExpirationDate,CouponDesc,Used,Country,MaybeValid,AddTime,type,CouponCodeUrl,IsUpdateCodeUrl) values ";
//    coupons 数据
        preg_match_all("/coupon list start([\s\S]+?)coupon list end/", $htmlContent, $matchValidCoupon);

        $matchCoupon=explode('<div class="ds_list',$matchValidCoupon[1][0]);
        if (!empty($matchCoupon)) {

            for($i=1;$i<count($matchCoupon);$i++){
                $couponHtml=$matchCoupon[$i];


                $couponData['MaybeValid'] = 1;
                $couponData['Country'] = "FR";

                $couponData['CouponID'] =$rank;
                $couponData['CouponTitle']='';
                $couponData['CouponDesc']='';
                $couponData['GoUrl']='';
                $couponData['type']='deal';
                $couponData['Used']='';
                $couponData['CouponRestriction']='';
                $couponData['ExpirationDate']='';
                $couponData['CouponCodeUrl'] = "";
                $couponData['CouponCode']='';
                $couponData['IsUpdateCodeUrl']=0;

                //couponId
                preg_match_all("/id=\"coupontitle_(\d+)\"/",$couponHtml,$matchCouponId);
                if(!empty($matchCouponId[0])){
                    $couponData['CouponID']=$matchCouponId[1][0];
                }

                //type && code
                preg_match_all("/<span class=\"coupon_code icode_\d+\" id=\"couponcode_\d+\">(.*?)<\/span>/", $couponHtml,$matchType);
                if(!empty($matchType[0])){
                    $couponData['type']='code';
                    $couponData['CouponCode']=trim(strip_tags($matchType[1][0]));
                }

                //title
                preg_match_all("/class=\"coupon_title\" id=\"[^\"]+\"[^>]+>([\s\S]+?)<\/div>/",$couponHtml,$matchcoupontitle);
                $couponData['CouponTitle']= empty($matchcoupontitle[0])?'':trim(strip_tags($matchcoupontitle[1][0]));

                //gourl
                preg_match_all("/href='([^']+)' class=\"title\"/",$couponHtml,$matchcouponUrl);
                $couponData['GoUrl']=empty($matchcouponUrl[0])?'':$site_pre.$matchcouponUrl[1][0];

                //desc
                preg_match_all("/<span id=\"coupondesc_\d+\" class=\"cpdesc less\">(.*?)<\/span>/",$couponHtml,$matchCoupondesc);
                $couponData['CouponDesc']=empty($matchCoupondesc[0])?'':$matchCoupondesc[1][0];

                //有效期
                if(empty($couponData['ExpirationDate'])){
                    $couponData['ExpirationDate']='0000-00-00';
                }


				$rank++;
				if(!empty($couponData['CouponID']))
					$couponRankMap[''.$couponData['CouponID']]=$rank;
				if(!isset($couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']])){
					$sqlIns.="('{$row['CompetitorStoreId']}','{$row['CompetitorId']}','{$couponData['CouponID']}','{$couponData['CouponTitle']}','{$couponData['GoUrl']}','{$couponData['CouponCode']}','{$couponData['ExpirationDate']}','{$couponData['CouponDesc']}','{$couponData['Used']}','{$couponData['Country']}','{$couponData['MaybeValid']}','".date("Y-m-d H:i:s")."','{$couponData['type']}','{$couponData['CouponCodeUrl']}','{$couponData['IsUpdateCodeUrl']}'),";
				}else{
					$expiresVo=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']];
					if($expiresVo['ExpirationDate']!=$couponData['ExpirationDate'] || $expiresVo['CouponTitle']!=$couponData['CouponTitle']  || $expiresVo['CouponDesc']!=$couponData['CouponDesc'] || $expiresVo['CouponCodeUrl']!=$couponData['CouponCodeUrl'] || $expiresVo['type']!=$couponData['type']){
						$sqlUp="update cp_competitor_store_coupon set ExpirationDate='{$couponData['ExpirationDate']}',CouponTitle='{$couponData['CouponTitle']}',CouponDesc='{$couponData['CouponDesc']}',CouponCodeUrl='{$couponData['CouponCodeUrl']}',type='{$couponData['type']}',IsUpdateCodeUrl='{$couponData['IsUpdateCodeUrl']}',LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$expiresVo['ID']}";
						$db->query($sqlUp);
					}
					$couponOnMap[]=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']]['ID'];
				}
			}
			if($sqlIns!=$sqlInsPre){
				$sqlIns=substr($sqlIns,0,strlen($sqlIns)-1);
				$sqlIns.=";";
				$db->query($sqlIns);
			}

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
		//end coupons 22
	}else if($row['CompetitorId']==29){
        //获取竞争对手其他store链接  start
        preg_match_all("/href=\"(\/code-promo-[^\/\"]+?)\"/i", $htmlContent, $matchUrl,PREG_SET_ORDER);
        $sqlInsUrl = $sqlInsUrlPre = "insert ignore into cp_temp_competitor_store(StoreUrl,CompetitorId,AddTime) values ";

        //site_pre
        $site_pre='http://codepromo.lexpress.fr';
		if (!empty($matchUrl)) {
			foreach ($matchUrl as $url) {
				$sqlInsUrl .= "('" .$site_pre. $url[1] . "',{$row['CompetitorId']},'" . date("Y-m-d H:i:s") . "'),";
			}
			if ($sqlInsUrl != $sqlInsUrlPre) {
				$sqlInsUrl = substr($sqlInsUrl, 0, strlen($sqlInsUrl) - 1);
				$sqlInsUrl .= ";";
				$db->query($sqlInsUrl);
			}
		}
		$cs_data_arr = array();


//MetaTitle
        preg_match_all("/<title>([^<]+)<\/title>/", $htmlContent, $matchTitle, PREG_SET_ORDER);
        $cs_data_arr['MetaTitle'] = empty($matchTitle) ? "" : $matchTitle[0][1];
        //keywords No Meta keywords
        $cs_data_arr['MetaKeywords'] = '';
        //meta description
        preg_match_all("/<meta content=\"([^\"]+)\" name=\"description\"/", $htmlContent, $matchMetaDesc, PREG_SET_ORDER);
        $cs_data_arr['MetaDescription'] = empty($matchMetaDesc) ? "" : $matchMetaDesc[0][1];
        //描述
        preg_match_all("/<h2[^>]*>(.*?)<\/h2>/", $htmlContent, $matchDesc, PREG_SET_ORDER);
        $cs_data_arr['Description'] = empty($matchDesc)?'':trim(del_br_space_by_str(strip_tags($matchDesc[0][1])));
//H1
        preg_match_all("/<h1[^>]*>(.*?)<\/h1>/", $htmlContent, $matchH1, PREG_SET_ORDER);
        $cs_data_arr['H1'] = empty($matchH1) ? "" : trim(strip_tags($matchH1[0][1]));
//Merchant Go Url
        preg_match_all("/class=\"lexpress-page-header-img \" data-slug=\"([^\"]+?)\"/", $htmlContent, $matchGo);
        $cs_data_arr['MerchantGoUrl'] = empty($matchGo[0]) ? "" :'http://codepromo.lexpress.fr/redirect-to?url='.$matchGo[1][0];
        //Screen Img
        preg_match_all("/background-image:url\(\\\\'([^\'\"\\\]+?)\\\\'\)/",$htmlContent,$matchImg);
        $cs_data_arr['ScreenImg'] = empty($matchImg[0]) ? "" :$matchImg[1][0];


		$sql = $pre_sql = "update cp_competitor_store set ";
		foreach ($cs_data_arr as $key => $val) {
			if (strlen($val) > 100) {
				$val = del_br_space_by_str($val);
			}
			if (strrpos($key, "meta") !== false) {
				$val = substr($val, 0, 250);
			}
			$sql .= " {$key} = '" . addslashes($val) . "' , ";
		}

		if($sql != $pre_sql ){
			$sql.=" LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$row['CompetitorStoreId']}";
			$GLOBALS['db']->query($sql);
		}

		$rank=0;
		$sqlIns=$sqlInsPre="INSERT ignore into cp_competitor_store_coupon (CompetitorStoreId,CompetitorId,CouponID,CouponTitle,GoUrl,CouponCode,ExpirationDate,CouponDesc,Used,Country,MaybeValid,AddTime,type,CouponCodeUrl,IsUpdateCodeUrl) values ";
//    coupons 数据
        $matchValidCoupon=Selector::select($htmlContent,'//div[@data-cn-voucher-list]');
        $matchCoupon=explode('<div data-cn-voucher',$matchValidCoupon);
        if (!empty($matchCoupon)) {

            for($i=1;$i<count($matchCoupon);$i++){
                $couponHtml=$matchCoupon[$i];


                $couponData['MaybeValid'] = 1;
                $couponData['Country'] = "FR";

                $couponData['CouponID'] =$rank;
                $couponData['CouponTitle']='';
                $couponData['CouponDesc']='';
                $couponData['GoUrl']='';
                $couponData['type']='deal';
                $couponData['Used']='';
                $couponData['CouponRestriction']='';
                $couponData['ExpirationDate']='';
                $couponData['CouponCodeUrl'] = "";
                $couponData['CouponCode']='';
                $couponData['IsUpdateCodeUrl']=0;

                //couponId
                preg_match_all("/id=\"item-(\d+)\"/",$couponHtml,$matchCouponId);
                if(!empty($matchCouponId[0])){
                    $couponData['CouponID']=$matchCouponId[1][0];
                }

                //type && code
                preg_match_all("/data-code-field/", $couponHtml,$matchType);
                if(!empty($matchType[0])){
                    $couponData['IsUpdateCodeUrl']=1;
                    $couponData['type']='code';
                    $couponData['CouponCodeUrl']='http://codepromo.lexpress.fr/ajax/voucherpopup?id='.$couponData['CouponID'].'&isTablet=false';
                }

                //title
                $matchcoupontitle=Selector::select($couponHtml,'//*/h3/span[1]');
                $couponData['CouponTitle']= empty($matchcoupontitle)?'':deal_text($matchcoupontitle);


                //gourl
                preg_match_all("/data-slug=\"([^\"]+)\"/",$couponHtml,$matchcouponUrl);
                $couponData['GoUrl']=empty($matchcouponUrl[0])?'':'http://codepromo.lexpress.fr/redirect-to?url='.$matchcouponUrl[1][0];

                //desc
                $couponData['CouponDesc']='';

                //有效期
                $couponData['ExpirationDate']='0000-00-00';

				$rank++;
				if(!empty($couponData['CouponID']))
					$couponRankMap[''.$couponData['CouponID']]=$rank;
				if(!isset($couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']])){
					$sqlIns.="('{$row['CompetitorStoreId']}','{$row['CompetitorId']}','{$couponData['CouponID']}','{$couponData['CouponTitle']}','{$couponData['GoUrl']}','{$couponData['CouponCode']}','{$couponData['ExpirationDate']}','{$couponData['CouponDesc']}','{$couponData['Used']}','{$couponData['Country']}','{$couponData['MaybeValid']}','".date("Y-m-d H:i:s")."','{$couponData['type']}','{$couponData['CouponCodeUrl']}','{$couponData['IsUpdateCodeUrl']}'),";
				}else{
					$expiresVo=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']];
					if($expiresVo['ExpirationDate']!=$couponData['ExpirationDate'] || $expiresVo['CouponTitle']!=$couponData['CouponTitle']  || $expiresVo['CouponDesc']!=$couponData['CouponDesc'] || $expiresVo['CouponCodeUrl']!=$couponData['CouponCodeUrl'] || $expiresVo['type']!=$couponData['type']){
						$sqlUp="update cp_competitor_store_coupon set ExpirationDate='{$couponData['ExpirationDate']}',CouponTitle='{$couponData['CouponTitle']}',CouponDesc='{$couponData['CouponDesc']}',CouponCodeUrl='{$couponData['CouponCodeUrl']}',type='{$couponData['type']}',IsUpdateCodeUrl='{$couponData['IsUpdateCodeUrl']}',LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$expiresVo['ID']}";
						$db->query($sqlUp);
					}
					$couponOnMap[]=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']]['ID'];
				}
			}
			if($sqlIns!=$sqlInsPre){
				$sqlIns=substr($sqlIns,0,strlen($sqlIns)-1);
				$sqlIns.=";";
				$db->query($sqlIns);
			}

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
		//end coupons 29
	}else if($row['CompetitorId']==30){
        //获取竞争对手其他store链接  start
        preg_match_all("/href=\"(http:\/\/www.sparwelt.de\/gutscheine\/[^\"]+)/i", $htmlContent, $matchUrl,PREG_SET_ORDER);
        $sqlInsUrl = $sqlInsUrlPre = "insert ignore into cp_temp_competitor_store(StoreUrl,CompetitorId,AddTime) values ";

        //site_pre

        if (!empty($matchUrl)) {
            foreach ($matchUrl as $url) {
                $sqlInsUrl .= "('" . $url[1] . "',{$row['CompetitorId']},'" . date("Y-m-d H:i:s") . "'),";
            }
            if ($sqlInsUrl != $sqlInsUrlPre) {
                $sqlInsUrl = substr($sqlInsUrl, 0, strlen($sqlInsUrl) - 1);
                $sqlInsUrl .= ";";
                $db->query($sqlInsUrl);
            }
        }
        $cs_data_arr = array();

//MetaTitle
        preg_match_all("/<title>([^<]+)<\/title>/", $htmlContent, $matchTitle, PREG_SET_ORDER);
        $cs_data_arr['MetaTitle'] = empty($matchTitle) ? "" : $matchTitle[0][1];
        //keywords No Meta keywords
        $cs_data_arr['MetaKeywords'] = '';
        //meta description
        preg_match_all("/<meta content=\"([^\"]+)\" name=\"description\"/", $htmlContent, $matchMetaDesc, PREG_SET_ORDER);
        $cs_data_arr['MetaDescription'] = empty($matchMetaDesc) ? "" : $matchMetaDesc[0][1];
        //描述
        preg_match_all("/class=\"provider-description collapse-content collapse in\"><p>(.*?)<\/p>/", $htmlContent, $matchDesc, PREG_SET_ORDER);
        $cs_data_arr['Description'] = empty($matchDesc)?'':trim(del_br_space_by_str(strip_tags($matchDesc[0][1])));
//H1
        preg_match_all("/<h1[^>]*>(.*?)<\/h1>/", $htmlContent, $matchH1, PREG_SET_ORDER);
        $cs_data_arr['H1'] = empty($matchH1) ? "" : trim(strip_tags($matchH1[0][1]));
//Merchant Go Url
        preg_match_all("/id=\"jumplink-4\" href=\"([^\"]+)\"/", $htmlContent, $matchGo);
        $cs_data_arr['MerchantGoUrl'] = empty($matchGo[0]) ? "" :$matchGo[1][0];
        //Screen Img
        preg_match_all("/class=\"col-xs-5 col-smaller-4 col-md-offset-1 col-md-10 js-touchpoint-spot\"><img[\s\S]*?data-src=\"([^\"]+)\"/",$htmlContent,$matchImg);
        $cs_data_arr['ScreenImg'] = empty($matchImg[0]) ? "" :$matchImg[1][0];


        $sql = $pre_sql = "update cp_competitor_store set ";
        $empty_log_str = "";
        foreach ($cs_data_arr as $key => $val) {
            if (empty($val)) {
                $empty_log_str .= " {$key} --empty --";
            }
            if (strlen($val) > 100) {
                $val = del_br_space_by_str($val);
            }
            if (strrpos($key, "meta") !== false) {
                $val = substr($val, 0, 250);
            }
            $sql .= " {$key} = '" . addslashes($val) . "' , ";
        }

        if($sql != $pre_sql ){
            $sql.=" LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$row['CompetitorStoreId']}";
            $GLOBALS['db']->query($sql);
        }

        $rank=0;
        $sqlIns=$sqlInsPre="INSERT ignore into cp_competitor_store_coupon (CompetitorStoreId,CompetitorId,CouponID,CouponTitle,GoUrl,CouponCode,ExpirationDate,CouponDesc,Used,Country,MaybeValid,AddTime,type,CouponCodeUrl,IsUpdateCodeUrl) values ";
//    coupons 数据
        preg_match_all("/\"media-list vouchers-active\">([\s\S]*?)<\/section>/", $htmlContent, $matchValidCoupon, PREG_SET_ORDER);

        if (!empty($matchValidCoupon[0])) {
            $matchCoupon = explode("\"teaser-long teaser-voucher bg-blank space-bottom status-1  js-touchpoint relative js-teaser-bookmark\"", $matchValidCoupon[0][1]);

            for ($i = 1; $i < count($matchCoupon); $i++) {
                $couponHtml=$matchCoupon[$i];

                $couponData['MaybeValid'] = 1;
                $couponData['Country'] = "DE";

                $couponData['CouponID'] =$rank;
                $couponData['CouponTitle']='';
                $couponData['CouponDesc']='';
                $couponData['GoUrl']='';
                $couponData['type']='deal';
                $couponData['Used']='';
                $couponData['CouponRestriction']='';
                $couponData['ExpirationDate']='';
                $couponData['CouponCodeUrl'] = "";
                $couponData['CouponCode']='';
                $couponData['IsUpdateCodeUrl']=0;

                //couponId
                preg_match_all("/data-id=\"(\d+)\"/",$couponHtml,$matchCouponId);
                if(!empty($matchCouponId[0])){
                    $couponData['CouponID']=$matchCouponId[1][0];
                }

                //type && code
                preg_match_all("/btn-code-label text-default/", $couponHtml,$matchType);
                if(!empty($matchType[0])){
                    $couponData['IsUpdateCodeUrl']=1;
                    $couponData['type']='code';
                    $couponData['CouponCodeUrl']='http://www.sparwelt.de/ajax/gutschein/flat/'.$couponData['CouponID'];
                }

                //title
                preg_match_all("/data-clickspot=\"title\">([\s\S]*?)<\/div>/",$couponHtml,$matchcoupontitle);
                $couponData['CouponTitle']= empty($matchcoupontitle[0])?'':trim(strip_tags($matchcoupontitle[1][0]));

                //gourl
                preg_match_all("/data-clickout-target=\"([^\"]+)\"/",$couponHtml,$matchcouponUrl);
                $couponData['GoUrl']=empty($matchcouponUrl[0])?'':$matchcouponUrl[1][0];

                //desc
                $couponData['CouponDesc']='';

                //有效期
                $couponData['ExpirationDate']='0000-00-00';

                $rank++;
                if(!empty($couponData['CouponID']))
                    $couponRankMap[''.$couponData['CouponID']]=$rank;
                if(!isset($couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']])){
                    $sqlIns.="('{$row['CompetitorStoreId']}','{$row['CompetitorId']}','{$couponData['CouponID']}','{$couponData['CouponTitle']}','{$couponData['GoUrl']}','{$couponData['CouponCode']}','{$couponData['ExpirationDate']}','{$couponData['CouponDesc']}','{$couponData['Used']}','{$couponData['Country']}','{$couponData['MaybeValid']}','".date("Y-m-d H:i:s")."','{$couponData['type']}','{$couponData['CouponCodeUrl']}','{$couponData['IsUpdateCodeUrl']}'),";
                }else{
                    $expiresVo=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']];
                    if($expiresVo['ExpirationDate']!=$couponData['ExpirationDate'] || $expiresVo['CouponTitle']!=$couponData['CouponTitle']  || $expiresVo['CouponDesc']!=$couponData['CouponDesc'] || $expiresVo['CouponCodeUrl']!=$couponData['CouponCodeUrl'] || $expiresVo['type']!=$couponData['type']){
                        $sqlUp="update cp_competitor_store_coupon set ExpirationDate='{$couponData['ExpirationDate']}',CouponTitle='{$couponData['CouponTitle']}',CouponDesc='{$couponData['CouponDesc']}',CouponCodeUrl='{$couponData['CouponCodeUrl']}',type='{$couponData['type']}',IsUpdateCodeUrl='{$couponData['IsUpdateCodeUrl']}',LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$expiresVo['ID']}";
                        $db->query($sqlUp);
                    }
                    $couponOnMap[]=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']]['ID'];
                }
            }
            if($sqlIns!=$sqlInsPre){
                $sqlIns=substr($sqlIns,0,strlen($sqlIns)-1);
                $sqlIns.=";";
                $db->query($sqlIns);
            }

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
        //end coupons 22
    }else if($row['CompetitorId']==31){
        //获取竞争对手其他store链接  start
        preg_match_all("/href=\"(https:\/\/www.gutscheinemagazin.de\/[^\/\"]+\/)\"/i", $htmlContent, $matchUrl,PREG_SET_ORDER);
        $sqlInsUrl = $sqlInsUrlPre = "insert ignore into cp_temp_competitor_store(StoreUrl,CompetitorId,AddTime) values ";
        //site_pre

        if (!empty($matchUrl)) {
            foreach ($matchUrl as $url) {
                $sqlInsUrl .= "('" . $url[1] . "',{$row['CompetitorId']},'" . date("Y-m-d H:i:s") . "'),";
            }
            if ($sqlInsUrl != $sqlInsUrlPre) {
                $sqlInsUrl = substr($sqlInsUrl, 0, strlen($sqlInsUrl) - 1);
                $sqlInsUrl .= ";";
                $db->query($sqlInsUrl);
            }
        }
        $cs_data_arr = array();

//MetaTitle
        preg_match_all("/<title>([^<]+)<\/title>/", $htmlContent, $matchTitle, PREG_SET_ORDER);
        $cs_data_arr['MetaTitle'] = empty($matchTitle) ? "" : $matchTitle[0][1];
        //keywords No Meta keywords
        $cs_data_arr['MetaKeywords'] = '';
        //meta description
        preg_match_all("/<meta name=description content=\"([^\"]+)\"/", $htmlContent, $matchMetaDesc, PREG_SET_ORDER);
        $cs_data_arr['MetaDescription'] = empty($matchMetaDesc) ? "" : $matchMetaDesc[0][1];
        //描述
        preg_match_all("/<\/a>\s*<\/div>\s*<p>(.*?)<\/p>/", $htmlContent, $matchDesc, PREG_SET_ORDER);
        $cs_data_arr['Description'] = empty($matchDesc)?'':trim(del_br_space_by_str(strip_tags($matchDesc[0][1])));
//H1
        preg_match_all("/<h1[^>]*>(.*?)<\/h1>/", $htmlContent, $matchH1, PREG_SET_ORDER);
        $cs_data_arr['H1'] = empty($matchH1) ? "" : trim(strip_tags($matchH1[0][1]));
//Merchant Go Url
        preg_match_all("/href=\"([^\"]+)\" target=_blank>\s*<img src=\"[^\"]+\" class=\"logo[^\"]*\"/", $htmlContent, $matchGo);
        $cs_data_arr['MerchantGoUrl'] = empty($matchGo[0]) ? "" :'http://www.gutscheinemagazin.de'.$matchGo[1][0];
        //Screen Img
        preg_match_all("/<img src=\"([^\"]+)\" class=\"logo[^\"]*\"/",$htmlContent,$matchImg);
        $cs_data_arr['ScreenImg'] = empty($matchImg[0]) ? "" :'https://www.gutscheinemagazin.de'.$matchImg[1][0];


        $sql = $pre_sql = "update cp_competitor_store set ";
        $empty_log_str = "";
        foreach ($cs_data_arr as $key => $val) {
            if (empty($val)) {
                $empty_log_str .= " {$key} --empty --";
            }
            if (strlen($val) > 100) {
                $val = del_br_space_by_str($val);
            }
            if (strrpos($key, "meta") !== false) {
                $val = substr($val, 0, 250);
            }
            $sql .= " {$key} = '" . addslashes($val) . "' , ";
        }

        if($sql != $pre_sql ){
            $sql.=" LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$row['CompetitorStoreId']}";
            $GLOBALS['db']->query($sql);
        }

        $rank=0;
        $sqlIns=$sqlInsPre="INSERT ignore into cp_competitor_store_coupon (CompetitorStoreId,CompetitorId,CouponID,CouponTitle,GoUrl,CouponCode,ExpirationDate,CouponDesc,Used,Country,MaybeValid,AddTime,type,CouponCodeUrl,IsUpdateCodeUrl) values ";
//    coupons 数据
        preg_match_all("/<div class=box>([\s\S]+?)<\/div>\s*<div class=yarpp-related>/", $htmlContent, $matchValidCoupon, PREG_SET_ORDER);
//    var_dump($matchValidCoupon);die;
        if (!empty($matchValidCoupon[0])) {
            $matchCoupon = explode("class=\"coupon  type-", $matchValidCoupon[0][1]);

            for ($i = 1; $i < count($matchCoupon); $i++) {
                $couponHtml=$matchCoupon[$i];

                $couponData['MaybeValid'] = 1;
                $couponData['Country'] = "DE";

                $couponData['CouponID'] =$rank;
                $couponData['CouponTitle']='';
                $couponData['CouponDesc']='';
                $couponData['GoUrl']='';
                $couponData['type']='deal';
                $couponData['Used']='';
                $couponData['CouponRestriction']='';
                $couponData['ExpirationDate']='';
                $couponData['CouponCodeUrl'] = "";
                $couponData['CouponCode']='';
                $couponData['IsUpdateCodeUrl']=0;

                //couponId
                preg_match_all("/data-coupon=(\d+)>/",$couponHtml,$matchCouponId);
                if(!empty($matchCouponId[0])){
                    $couponData['CouponID']=$matchCouponId[1][0];
                }

                preg_match_all("/\/([^\/\.]+)\//",$row['Url'],$matchPath);
                //type && code
                preg_match_all("/class=\"couponconvert topcode\"/", $couponHtml,$matchType);
                if(!empty($matchType[0]) && !empty($matchPath[0])){

                    $couponData['IsUpdateCodeUrl']=1;
                    $couponData['type']='code';
                    $couponData['CouponCodeUrl']="https://www.gutscheinemagazin.de/gutschein-overlay/?path=/{$matchPath[1][0]}/&coupon={$couponData['CouponID']}";
                }

                //title
                preg_match_all("/class=text>([\s\S]*?)<\/div>/",$couponHtml,$matchcoupontitle);
                $couponData['CouponTitle']= empty($matchcoupontitle[0])?'':trim(strip_tags($matchcoupontitle[1][0]));

                //gourl
//            preg_match_all("/data-clickout-target=\"([^\"]+)\"/",$couponHtml,$matchcouponUrl);
                $couponData['GoUrl']="https://www.gutscheinemagazin.de/einloesen/{$couponData['CouponID']}";

                //desc
                $couponData['CouponDesc']='';

                //有效期
                preg_match_all("/<span class=value>(\d{2}.\d{2}.\d{4})<\/span>/",$couponHtml,$matchExpirationDate);
                $couponData['ExpirationDate']=empty($matchExpirationDate[0])?"0000-00-00 00:00:00":date("Y-m-d H:i:s",strtotime($matchExpirationDate[1][0]));

                $rank++;
                if(!empty($couponData['CouponID']))
                    $couponRankMap[''.$couponData['CouponID']]=$rank;
                if(!isset($couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']])){
                    $sqlIns.="('{$row['CompetitorStoreId']}','{$row['CompetitorId']}','{$couponData['CouponID']}','{$couponData['CouponTitle']}','{$couponData['GoUrl']}','{$couponData['CouponCode']}','{$couponData['ExpirationDate']}','{$couponData['CouponDesc']}','{$couponData['Used']}','{$couponData['Country']}','{$couponData['MaybeValid']}','".date("Y-m-d H:i:s")."','{$couponData['type']}','{$couponData['CouponCodeUrl']}','{$couponData['IsUpdateCodeUrl']}'),";
                }else{
                    $expiresVo=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']];
                    if($expiresVo['ExpirationDate']!=$couponData['ExpirationDate'] || $expiresVo['CouponTitle']!=$couponData['CouponTitle']  || $expiresVo['CouponDesc']!=$couponData['CouponDesc'] || $expiresVo['CouponCodeUrl']!=$couponData['CouponCodeUrl'] || $expiresVo['type']!=$couponData['type']){
                        $sqlUp="update cp_competitor_store_coupon set ExpirationDate='{$couponData['ExpirationDate']}',CouponTitle='{$couponData['CouponTitle']}',CouponDesc='{$couponData['CouponDesc']}',CouponCodeUrl='{$couponData['CouponCodeUrl']}',type='{$couponData['type']}',IsUpdateCodeUrl='{$couponData['IsUpdateCodeUrl']}',LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$expiresVo['ID']}";
                        $db->query($sqlUp);
                    }
                    $couponOnMap[]=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']]['ID'];
                }
            }
            if($sqlIns!=$sqlInsPre){
                $sqlIns=substr($sqlIns,0,strlen($sqlIns)-1);
                $sqlIns.=";";
                $db->query($sqlIns);
            }

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
        //end coupons 22
    }else if($row['CompetitorId']==32){
        //获取竞争对手其他store链接  start
        preg_match_all("/href=\"(http:\/\/gutscheine.focus.de\/gutscheine\/[^\"\/]+)\"/i", $htmlContent, $matchUrl,PREG_SET_ORDER);
        $sqlInsUrl = $sqlInsUrlPre = "insert ignore into cp_temp_competitor_store(StoreUrl,CompetitorId,AddTime) values ";
        //site_pre

        if (!empty($matchUrl)) {
            foreach ($matchUrl as $url) {
                $sqlInsUrl .= "('" . $url[1] . "',{$row['CompetitorId']},'" . date("Y-m-d H:i:s") . "'),";
            }
            if ($sqlInsUrl != $sqlInsUrlPre) {
                $sqlInsUrl = substr($sqlInsUrl, 0, strlen($sqlInsUrl) - 1);
                $sqlInsUrl .= ";";
                $db->query($sqlInsUrl);
            }
        }
        $cs_data_arr = array();

//MetaTitle
        preg_match_all("/<title>([^<]+)<\/title>/", $htmlContent, $matchTitle, PREG_SET_ORDER);
        $cs_data_arr['MetaTitle'] = empty($matchTitle) ? "" : $matchTitle[0][1];
        //keywords
        preg_match_all("/<meta name=\"keywords\" content=\"([^\"]*)\"/", $htmlContent, $matchMetaKey, PREG_SET_ORDER);
        $cs_data_arr['MetaKeywords'] =empty($matchMetaKey[0])?'':$matchMetaKey[0][1];
        //meta description
        preg_match_all("/<meta name=\"description\" itemprop=\"description\" content=\"([^\"]+)\"/", $htmlContent, $matchMetaDesc, PREG_SET_ORDER);
        $cs_data_arr['MetaDescription'] = empty($matchMetaDesc) ? "" : $matchMetaDesc[0][1];
        //描述
        preg_match_all("/teaser-description hidden-xs hidden-smaller\">([\s\S]*?)<\/div>/", $htmlContent, $matchDesc, PREG_SET_ORDER);
        $cs_data_arr['Description'] = empty($matchDesc)?'':trim(del_br_space_by_str(strip_tags($matchDesc[0][1])));
//H1
        preg_match_all("/<h1 class=\"title\">([\s\S]*?)<\/h1>/", $htmlContent, $matchH1, PREG_SET_ORDER);
        $cs_data_arr['H1'] = empty($matchH1) ? "" : trim(strip_tags($matchH1[0][1]));
//Merchant Go Url
        preg_match_all("/class=\"link-to text-center\"\s+?href=\"([^\"]+)\"/", $htmlContent, $matchGo);
        $cs_data_arr['MerchantGoUrl'] = empty($matchGo[0]) ? "" :$matchGo[1][0];
        //Screen Img
        preg_match_all("/class=\"media-img box-bordered-full-xs\" src=\"([^\"]+)\" /",$htmlContent,$matchImg);
        $cs_data_arr['ScreenImg'] = empty($matchImg[0]) ? "" :'http://gutscheine.focus.de'.$matchImg[1][0];


        $sql = $pre_sql = "update cp_competitor_store set ";
        $empty_log_str = "";
        foreach ($cs_data_arr as $key => $val) {
            if (empty($val)) {
                $empty_log_str .= " {$key} --empty --";
            }
            if (strlen($val) > 100) {
                $val = del_br_space_by_str($val);
            }
            if (strrpos($key, "meta") !== false) {
                $val = substr($val, 0, 250);
            }
            $sql .= " {$key} = '" . addslashes($val) . "' , ";
        }

        if($sql != $pre_sql ){
            $sql.=" LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$row['CompetitorStoreId']}";
            $GLOBALS['db']->query($sql);
        }

        $rank=0;
        $sqlIns=$sqlInsPre="INSERT ignore into cp_competitor_store_coupon (CompetitorStoreId,CompetitorId,CouponID,CouponTitle,GoUrl,CouponCode,ExpirationDate,CouponDesc,Used,Country,MaybeValid,AddTime,type,CouponCodeUrl,IsUpdateCodeUrl) values ";
//    coupons 数据
        preg_match_all("/class=\"teaser-voucher-wrapper js-teaser-voucher-wrapper\">([\s\S]+)<div class=\"newsletter-wrapper\"/", $htmlContent, $matchValidCoupon, PREG_SET_ORDER);
//    var_dump($matchValidCoupon);die;
        if (!empty($matchValidCoupon[0])) {
            $matchCoupon = explode("class=\"teaser teaser-voucher status-1 media js-touchpoint-wrap border-bottom\"", $matchValidCoupon[0][1]);

            for ($i = 1; $i < count($matchCoupon); $i++) {
                $couponHtml=$matchCoupon[$i];

                $couponData['MaybeValid'] = 1;
                $couponData['Country'] = "DE";

                $couponData['CouponID'] =$rank;
                $couponData['CouponTitle']='';
                $couponData['CouponDesc']='';
                $couponData['GoUrl']='';
                $couponData['type']='deal';
                $couponData['Used']='';
                $couponData['CouponRestriction']='';
                $couponData['ExpirationDate']='';
                $couponData['CouponCodeUrl'] = "";
                $couponData['CouponCode']='';
                $couponData['IsUpdateCodeUrl']=1;

                //couponId
                preg_match_all("/data-id=\"(\d+)\"/",$couponHtml,$matchCouponId);
                if(!empty($matchCouponId[0])){
                    $couponData['CouponID']=$matchCouponId[1][0];
                }

                $row['Url']="http://www.gutscheinemagazin.de/congstar/";
                preg_match_all("/\/([^\/\.]+)\//",$row['Url'],$matchPath);
                //type && code
                $couponData['CouponCodeUrl']="http://gutscheine.focus.de/ajax/voucher/overlay/{$couponData['CouponID']}?isLocalReferrer=true";

                //title
                preg_match_all("/class=\"title\">([\s\S]+?)<\/span>/",$couponHtml,$matchcoupontitle);
                $couponData['CouponTitle']= empty($matchcoupontitle[0])?'':trim(strip_tags($matchcoupontitle[1][0]));

                //gourl
//            preg_match_all("/data-clickout-target=\"([^\"]+)\"/",$couponHtml,$matchcouponUrl);
                $couponData['GoUrl']= $cs_data_arr['MerchantGoUrl'];

                //desc
                $couponData['CouponDesc']='';

                //有效期
                preg_match_all("/gültig bis:\s+(.*?)\s+<\/span>/",$couponHtml,$matchExpirationDate);
                $couponData['ExpirationDate']=empty($matchExpirationDate[0])?"":date("Y-m-d H:i:s",strtotime($matchExpirationDate[1][0]));

                $rank++;
                if(!empty($couponData['CouponID']))
                    $couponRankMap[''.$couponData['CouponID']]=$rank;
                if(!isset($couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']])){
                    $sqlIns.="('{$row['CompetitorStoreId']}','{$row['CompetitorId']}','{$couponData['CouponID']}','{$couponData['CouponTitle']}','{$couponData['GoUrl']}','{$couponData['CouponCode']}','{$couponData['ExpirationDate']}','{$couponData['CouponDesc']}','{$couponData['Used']}','{$couponData['Country']}','{$couponData['MaybeValid']}','".date("Y-m-d H:i:s")."','{$couponData['type']}','{$couponData['CouponCodeUrl']}','{$couponData['IsUpdateCodeUrl']}'),";
                }else{
                    $expiresVo=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']];
                    if($expiresVo['ExpirationDate']!=$couponData['ExpirationDate'] || $expiresVo['CouponTitle']!=$couponData['CouponTitle']  || $expiresVo['CouponDesc']!=$couponData['CouponDesc'] || $expiresVo['CouponCodeUrl']!=$couponData['CouponCodeUrl'] || $expiresVo['type']!=$couponData['type']){
                        $sqlUp="update cp_competitor_store_coupon set ExpirationDate='{$couponData['ExpirationDate']}',CouponTitle='{$couponData['CouponTitle']}',CouponDesc='{$couponData['CouponDesc']}',CouponCodeUrl='{$couponData['CouponCodeUrl']}',type='{$couponData['type']}',IsUpdateCodeUrl='{$couponData['IsUpdateCodeUrl']}',LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$expiresVo['ID']}";
                        $db->query($sqlUp);
                    }
                    $couponOnMap[]=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']]['ID'];
                }
            }
            if($sqlIns!=$sqlInsPre){
                $sqlIns=substr($sqlIns,0,strlen($sqlIns)-1);
                $sqlIns.=";";
                $db->query($sqlIns);
            }

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
        //end coupons 22
    }else if($row['CompetitorId']==33){
        //33 start     www.retailmenot.it
        //获取竞争对手其他store链接  start
        preg_match_all("/<a href=\"(\/[^\/\"]+)\">.*<\/a>/i", $htmlContent, $matchUrl,PREG_SET_ORDER);
        $sqlInsUrl = $sqlInsUrlPre = "insert ignore into cp_temp_competitor_store(StoreUrl,CompetitorId,AddTime) values ";
        //site_pre
        $site_pre='http://www.retailmenot.it';

        if (!empty($matchUrl)) {
            foreach ($matchUrl as $url) {
                $sqlInsUrl .= "('" .$site_pre.$url[1] . "',{$row['CompetitorId']},'" . date("Y-m-d H:i:s") . "'),";
            }
            if ($sqlInsUrl != $sqlInsUrlPre) {
                $sqlInsUrl = substr($sqlInsUrl, 0, strlen($sqlInsUrl) - 1);
                $sqlInsUrl .= ";";
                $db->query($sqlInsUrl);
            }
        }
        $cs_data_arr = array();

//MetaTitle
        preg_match_all("/<title>([^<]+)<\/title>/", $htmlContent, $matchTitle, PREG_SET_ORDER);
        $cs_data_arr['MetaTitle'] = empty($matchTitle) ? "" : $matchTitle[0][1];
        //keywords No Meta keywords
        $cs_data_arr['MetaKeywords'] = '';
        //meta description
        preg_match_all("/<meta name=\"description\" content=\"([^\"]+)\"/", $htmlContent, $matchMetaDesc, PREG_SET_ORDER);
        $cs_data_arr['MetaDescription'] = empty($matchMetaDesc) ? "" : $matchMetaDesc[0][1];
        //描述
//    preg_match_all("/h2 class=\"text-overflow hidden-xs\">([^<]+)<\/h2>/", $htmlContent, $matchDesc, PREG_SET_ORDER);
//    $cs_data_arr['Description'] = empty($matchDesc)?'':trim(del_br_space_by_str(strip_tags($matchDesc[0][1])));
        $cs_data_arr['Description'] = '';
//H1
        preg_match_all("/<h1[^>]*>(.*?)<\/h1>/", $htmlContent, $matchH1, PREG_SET_ORDER);
        $cs_data_arr['H1'] = empty($matchH1) ? "" : trim(strip_tags($matchH1[0][1]));
//Merchant Go Url
        preg_match_all("/class=\"col-merchant-logo merchant-logo hidden-xs\">\s+<a href=\"(.*?)\"/", $htmlContent, $matchGo);
        $cs_data_arr['MerchantGoUrl'] = empty($matchGo[0]) ? "" :$site_pre.$matchGo[1][0];
        //Screen Img
        preg_match_all("/<img class=\"img-responsive\" src=\"([^\"]+?)\"/",$htmlContent,$matchImg);
        $cs_data_arr['ScreenImg'] = empty($matchImg[0]) ? "" :$matchImg[1][0];

        if(!strripos('retailmenot',$cs_data_arr['ScreenImg'])){
            $tmp=explode('..', $cs_data_arr['ScreenImg']);
            $cs_data_arr['ScreenImg']=$tmp[0].'.retailmenot.'.$tmp[1];
        }

        $sql = $pre_sql = "update cp_competitor_store set ";
        $empty_log_str = "";
        foreach ($cs_data_arr as $key => $val) {
            if (empty($val)) {
                $empty_log_str .= " {$key} --empty --";
            }
            if (strlen($val) > 100) {
                $val = del_br_space_by_str($val);
            }
            if (strrpos($key, "meta") !== false) {
                $val = substr($val, 0, 250);
            }
            $sql .= " {$key} = '" . addslashes($val) . "' , ";
        }

        if($sql != $pre_sql ){
            $sql.=" LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$row['CompetitorStoreId']}";
            $GLOBALS['db']->query($sql);
        }

        $rank=0;
        $sqlIns=$sqlInsPre="INSERT ignore into cp_competitor_store_coupon (CompetitorStoreId,CompetitorId,CouponID,CouponTitle,GoUrl,CouponCode,ExpirationDate,CouponDesc,Used,Country,MaybeValid,AddTime,type,CouponCodeUrl,IsUpdateCodeUrl) values ";
//    coupons 数据
        preg_match_all("/class=\"merchant-coupons\">([\s\S]+?)<\/div><\/div>/", $htmlContent, $matchValidCoupon);

        if (!empty($matchValidCoupon[0])) {
            $matchCoupon=explode('<div class="element-coupon coupon outclick"',$matchValidCoupon[1][0]);

            for ($i = 1; $i < count($matchCoupon); $i++) {
                $couponHtml=$matchCoupon[$i];

                $couponData['MaybeValid'] = 1;
                $couponData['Country'] = "IT";

                $couponData['CouponID'] =$rank;
                $couponData['CouponTitle']='';
                $couponData['CouponDesc']='';
                $couponData['GoUrl']='';
                $couponData['type']='deal';
                $couponData['Used']='';
                $couponData['CouponRestriction']='';
                $couponData['ExpirationDate']='';
                $couponData['CouponCodeUrl'] = "";
                $couponData['CouponCode']='';
                $couponData['IsUpdateCodeUrl']=0;

                //couponId
                preg_match_all("/data-coupon=\"(\d+?)\"/",$couponHtml,$matchCouponId);
                if(!empty($matchCouponId[0])){
                    $couponData['CouponID']=$matchCouponId[1][0];
                }

                //type && code
                preg_match_all("/data-type=\"(.*?)\"/", $couponHtml,$matchType);
                if(!empty($matchType[0])){
                    //暂时获取不了code
//                    if($matchType[1][0]=='code'){
//                        $couponData['type']='code';
//                    }
                }

                //title
                preg_match_all("/<h3>([\s\S]+?)<\/h3>/",$couponHtml,$matchcoupontitle);
                $couponData['CouponTitle']= empty($matchcoupontitle[0])?'':trim(strip_tags($matchcoupontitle[1][0]));

                //gourl
//            preg_match_all("/href=\"([^\"]+)\" class=\"popup go_crd/",$couponHtml,$matchcouponUrl);
                $couponData['GoUrl']='http://www.retailmenot.it/out/c'.$couponData['CouponID'];

                //desc
//            preg_match_all("/<\/a> <p>([^>]+)/",$couponHtml,$matchCoupondesc);
                $couponData['CouponDesc']='';

                //有效期
                if(empty($couponData['ExpirationDate'])){
                    $couponData['ExpirationDate']='0000-00-00';
                }

                $rank++;
                if(!empty($couponData['CouponID']))
                    $couponRankMap[''.$couponData['CouponID']]=$rank;
                if(!isset($couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']])){
                    $sqlIns.="('{$row['CompetitorStoreId']}','{$row['CompetitorId']}','{$couponData['CouponID']}','{$couponData['CouponTitle']}','{$couponData['GoUrl']}','{$couponData['CouponCode']}','{$couponData['ExpirationDate']}','{$couponData['CouponDesc']}','{$couponData['Used']}','{$couponData['Country']}','{$couponData['MaybeValid']}','".date("Y-m-d H:i:s")."','{$couponData['type']}','{$couponData['CouponCodeUrl']}','{$couponData['IsUpdateCodeUrl']}'),";
                }else{
                    $expiresVo=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']];
                    if($expiresVo['ExpirationDate']!=$couponData['ExpirationDate'] || $expiresVo['CouponTitle']!=$couponData['CouponTitle']  || $expiresVo['CouponDesc']!=$couponData['CouponDesc'] || $expiresVo['CouponCodeUrl']!=$couponData['CouponCodeUrl'] || $expiresVo['type']!=$couponData['type']){
                        $sqlUp="update cp_competitor_store_coupon set ExpirationDate='{$couponData['ExpirationDate']}',CouponTitle='{$couponData['CouponTitle']}',CouponDesc='{$couponData['CouponDesc']}',CouponCodeUrl='{$couponData['CouponCodeUrl']}',type='{$couponData['type']}',IsUpdateCodeUrl='{$couponData['IsUpdateCodeUrl']}',LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$expiresVo['ID']}";
                        $db->query($sqlUp);
                    }
                    $couponOnMap[]=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']]['ID'];
                }
            }
            if($sqlIns!=$sqlInsPre){
                $sqlIns=substr($sqlIns,0,strlen($sqlIns)-1);
                $sqlIns.=";";
                $db->query($sqlIns);
            }

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
        // //33    end    www.retailmenot.it
    }else if($row['CompetitorId']==35){
        //35 start   http://www.piucodicisconto.com
        //获取竞争对手其他store链接  start
        preg_match_all("/href=\"(http:\/\/www.piucodicisconto.com\/offerte-codice-sconto-.*?.html)\"/i", $htmlContent, $matchUrl,PREG_SET_ORDER);
        $sqlInsUrl = $sqlInsUrlPre = "insert ignore into cp_temp_competitor_store(StoreUrl,CompetitorId,AddTime) values ";

        if (!empty($matchUrl)) {
            foreach ($matchUrl as $url) {
                $sqlInsUrl .= "('" .$url[1] . "',{$row['CompetitorId']},'" . date("Y-m-d H:i:s") . "'),";
            }
            if ($sqlInsUrl != $sqlInsUrlPre) {
                $sqlInsUrl = substr($sqlInsUrl, 0, strlen($sqlInsUrl) - 1);
                $sqlInsUrl .= ";";
                $db->query($sqlInsUrl);
            }
        }
        $cs_data_arr = array();

//MetaTitle
        preg_match_all("/<title>([^<]+)<\/title>/", $htmlContent, $matchTitle, PREG_SET_ORDER);
        $cs_data_arr['MetaTitle'] = empty($matchTitle) ? "" : $matchTitle[0][1];
        //keywords No Meta keywords
        $cs_data_arr['MetaKeywords'] = '';
        //meta description
        preg_match_all("/<meta name=\"description\" content=\"([^\"]+)\"/", $htmlContent, $matchMetaDesc, PREG_SET_ORDER);
        $cs_data_arr['MetaDescription'] = empty($matchMetaDesc) ? "" : $matchMetaDesc[0][1];
        //描述
        preg_match_all("/style=\"letter-spacing:0.4px;\">(.*?)</", $htmlContent, $matchDesc, PREG_SET_ORDER);
        $cs_data_arr['Description'] = empty($matchDesc)?'':trim(del_br_space_by_str(strip_tags($matchDesc[0][1])));
//H1
        preg_match_all("/\"codiceright\">\s*<h2>(.*?)<\/h2>/", $htmlContent, $matchH1, PREG_SET_ORDER);
        $cs_data_arr['H1'] = empty($matchH1) ? "" : trim(strip_tags($matchH1[0][1]));
//Merchant Go Url
        preg_match_all("/class=\"euit\" href=\"([^\"]+)\"/", $htmlContent, $matchGo);
        $cs_data_arr['MerchantGoUrl'] = empty($matchGo[0]) ? "" :'http://www.piucodicisconto.com/'.$matchGo[1][0];
        //Screen Img
        preg_match_all("/class=\"codiceleft\">\s+<a href=\"[^\"]+\" rel=\"nofollow\" target=\"_blank\"><img src=\"(.*?)\"/",$htmlContent,$matchImg);
        $cs_data_arr['ScreenImg'] = empty($matchImg[0]) ? "" :'http://www.piucodicisconto.com/'.$matchImg[1][0];


        $sql = $pre_sql = "update cp_competitor_store set ";
        $empty_log_str = "";
        foreach ($cs_data_arr as $key => $val) {
            if (empty($val)) {
                $empty_log_str .= " {$key} --empty --";
            }
            if (strlen($val) > 100) {
                $val = del_br_space_by_str($val);
            }
            if (strripos($key, "meta") !== false) {
                $val = substr($val, 0, 250);
            }
            $sql .= " {$key} = '" . addslashes($val) . "' , ";
        }

        if($sql != $pre_sql ){
            $sql.=" LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$row['CompetitorStoreId']}";
            $GLOBALS['db']->query($sql);
        }

        $rank=0;
        $sqlIns=$sqlInsPre="INSERT ignore into cp_competitor_store_coupon (CompetitorStoreId,CompetitorId,CouponID,CouponTitle,GoUrl,CouponCode,ExpirationDate,CouponDesc,Used,Country,MaybeValid,AddTime,type,CouponCodeUrl,IsUpdateCodeUrl) values ";
//    coupons 数据
        preg_match_all("/<div class=\"couponin\">\s+<ul>(\s*<li[\s\S]*?)<\/ul>/", $htmlContent, $matchValidCoupon);

        if (!empty($matchValidCoupon[0])) {
            $matchCoupon=explode('<li',$matchValidCoupon[1][0]);

            for ($i = 1; $i < count($matchCoupon); $i++) {
                $couponHtml=$matchCoupon[$i];

                if(strripos($couponHtml,'style="opacity:0.7;"')){
                    continue;
                }

                $couponData['MaybeValid'] = 1;
                $couponData['Country'] = "IT";

                $couponData['CouponID'] =$rank;
                $couponData['CouponTitle']='';
                $couponData['CouponDesc']='';
                $couponData['GoUrl']='';
                $couponData['type']='deal';
                $couponData['Used']='';
                $couponData['CouponRestriction']='';
                $couponData['ExpirationDate']='';
                $couponData['CouponCodeUrl'] = "";
                $couponData['CouponCode']='';
                $couponData['IsUpdateCodeUrl']=0;

                //title
                preg_match_all("/class=\"sconto\">\s+<h2>([\s\S]+?)<\/h2>/",$couponHtml,$matchcoupontitle);
                $couponData['CouponTitle']= empty($matchcoupontitle[0])?'':trim(strip_tags($matchcoupontitle[1][0]));

                //gourl
                preg_match_all("/\\\'(.+?)\\\',\\\'(.+?)\\\',event/i",$couponHtml,$matchcouponUrl);
                $couponData['GoUrl']=empty($matchcouponUrl[0])?'':'http://www.piucodicisconto.com/'.$matchcouponUrl[2][1];

                //type && code
                preg_match_all("/class=\"codici\"/", $couponHtml,$matchType);
                if(!empty($matchType[0]) && !empty($matchcouponUrl[0])){
                    $couponData['type']='code';
                    $couponData['IsUpdateCodeUrl']=1;
                    $couponData['CouponCodeUrl'] ='http://www.piucodicisconto.com/'.$matchcouponUrl[1][1];
                }

                //desc
                preg_match_all("/<p>([^<]+)<\/p>/",$couponHtml,$matchCoupondesc);
                $couponData['CouponDesc']=empty($matchCoupondesc[0])?'':$matchCoupondesc[1][0];;

                if(empty($couponData['ExpirationDate'])){
                    $couponData['ExpirationDate']='0000-00-00';
                }

                $rank++;
                if(!empty($couponData['CouponID']))
                    $couponRankMap[''.$couponData['CouponID']]=$rank;
                if(!isset($couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']])){
                    $sqlIns.="('{$row['CompetitorStoreId']}','{$row['CompetitorId']}','{$couponData['CouponID']}','{$couponData['CouponTitle']}','{$couponData['GoUrl']}','{$couponData['CouponCode']}','{$couponData['ExpirationDate']}','{$couponData['CouponDesc']}','{$couponData['Used']}','{$couponData['Country']}','{$couponData['MaybeValid']}','".date("Y-m-d H:i:s")."','{$couponData['type']}','{$couponData['CouponCodeUrl']}','{$couponData['IsUpdateCodeUrl']}'),";
                }else{
                    $expiresVo=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']];
                    if($expiresVo['ExpirationDate']!=$couponData['ExpirationDate'] || $expiresVo['CouponTitle']!=$couponData['CouponTitle']  || $expiresVo['CouponDesc']!=$couponData['CouponDesc'] || $expiresVo['CouponCodeUrl']!=$couponData['CouponCodeUrl'] || $expiresVo['type']!=$couponData['type']){
                        $sqlUp="update cp_competitor_store_coupon set ExpirationDate='{$couponData['ExpirationDate']}',CouponTitle='{$couponData['CouponTitle']}',CouponDesc='{$couponData['CouponDesc']}',CouponCodeUrl='{$couponData['CouponCodeUrl']}',type='{$couponData['type']}',IsUpdateCodeUrl='{$couponData['IsUpdateCodeUrl']}',LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$expiresVo['ID']}";
                        $db->query($sqlUp);
                    }
                    $couponOnMap[]=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']]['ID'];
                }
            }
            if($sqlIns!=$sqlInsPre){
                $sqlIns=substr($sqlIns,0,strlen($sqlIns)-1);
                $sqlIns.=";";
                $db->query($sqlIns);
            }

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
        // //35   end    http://www.piucodicisconto.com
    }else if($row['CompetitorId']==37){
        //37 start  http://www.codicepromozionalecoupon.it
        //获取竞争对手其他store链接  start
        preg_match_all("/href=\"(http:\/\/www.codicepromozionalecoupon.it\/[^\/]+\/)\"/i", $htmlContent, $matchUrl,PREG_SET_ORDER);
        $sqlInsUrl = $sqlInsUrlPre = "insert ignore into cp_temp_competitor_store(StoreUrl,CompetitorId,AddTime) values ";

        if (!empty($matchUrl)) {
            foreach ($matchUrl as $url) {
                $sqlInsUrl .= "('" .$url[1] . "',{$row['CompetitorId']},'" . date("Y-m-d H:i:s") . "'),";
            }
            if ($sqlInsUrl != $sqlInsUrlPre) {
                $sqlInsUrl = substr($sqlInsUrl, 0, strlen($sqlInsUrl) - 1);
                $sqlInsUrl .= ";";
                $db->query($sqlInsUrl);
            }
        }
        $cs_data_arr = array();

//MetaTitle
        preg_match_all("/<title>([^<]+)<\/title>/", $htmlContent, $matchTitle, PREG_SET_ORDER);
        $cs_data_arr['MetaTitle'] = empty($matchTitle) ? "" : $matchTitle[0][1];
        //keywords
        preg_match_all("/<meta name=\"keywords\" content=\"([^\"]+)\"/", $htmlContent, $matchKeyword, PREG_SET_ORDER);
        $cs_data_arr['MetaKeywords'] =empty($matchKeyword[0])?'':$matchKeyword[0][1];
        //meta description
        preg_match_all("/<meta name=\"description\" content=\"([^\"]+)\"/", $htmlContent, $matchMetaDesc, PREG_SET_ORDER);
        $cs_data_arr['MetaDescription'] = empty($matchMetaDesc) ? "" : $matchMetaDesc[0][1];
        //描述
        preg_match_all("/<\/h1>\s+<p>([\s\S]+?)<\/p>/", $htmlContent, $matchDesc, PREG_SET_ORDER);
        $cs_data_arr['Description'] = empty($matchDesc)?'':trim(del_br_space_by_str(strip_tags($matchDesc[0][1])));
//H1
        preg_match_all("/<h1>(.*?)<\/h1>/", $htmlContent, $matchH1, PREG_SET_ORDER);
        $cs_data_arr['H1'] = empty($matchH1) ? "" : trim(strip_tags($matchH1[0][1]));
//Merchant Go Url
        preg_match_all("/href=\"(.*?)\" target=\"_blank\" rel=\"nofollow\">\s*<img id=\"bigThumb\"/", $htmlContent, $matchGo);
        $cs_data_arr['MerchantGoUrl'] = empty($matchGo[0]) ? "" :$matchGo[1][0];
        //Screen Img
        preg_match_all("/id=\"bigThumb\" src=\"(.*?)\"/",$htmlContent,$matchImg);
        $cs_data_arr['ScreenImg'] = empty($matchImg[0]) ? "" :$matchImg[1][0];


        $sql = $pre_sql = "update cp_competitor_store set ";
        $empty_log_str = "";
        foreach ($cs_data_arr as $key => $val) {
            if (empty($val)) {
                $empty_log_str .= " {$key} --empty --";
            }
            if (strlen($val) > 100) {
                $val = del_br_space_by_str($val);
            }
            if (strripos($key, "meta") !== false) {
                $val = substr($val, 0, 250);
            }
            $sql .= " {$key} = '" . addslashes($val) . "' , ";
        }

        if($sql != $pre_sql ){
            $sql.=" LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$row['CompetitorStoreId']}";
            $GLOBALS['db']->query($sql);
        }

        $rank=0;
        $sqlIns=$sqlInsPre="INSERT ignore into cp_competitor_store_coupon (CompetitorStoreId,CompetitorId,CouponID,CouponTitle,GoUrl,CouponCode,ExpirationDate,CouponDesc,Used,Country,MaybeValid,AddTime,type,CouponCodeUrl,IsUpdateCodeUrl) values ";
//    coupons 数据
        preg_match_all("/<div class=\"coupon\" id=\"c\d+\">\s+([\s\S]+?)<div class=\"comments\"/", $htmlContent, $matchValidCoupon1);
        preg_match_all("/class=\"categorySponsor\">([\s\S]+?)<div class=\"break\"/", $htmlContent, $matchValidCoupon2);
        $matchCoupon=array();
        if(!empty($matchValidCoupon1[0])){
            $matchCoupon= array_merge_recursive($matchCoupon,$matchValidCoupon1[1]);
        }
        if(!empty($matchValidCoupon2[0])){
            $matchCoupon= array_merge_recursive($matchCoupon,$matchValidCoupon2[1]);
        }

        if (!empty($matchCoupon)) {

            for ($i = 0; $i < count($matchCoupon); $i++) {
                $couponHtml=$matchCoupon[$i];

                $couponData['MaybeValid'] = 1;
                $couponData['Country'] = "IT";

                $couponData['CouponID'] =$rank;
                $couponData['CouponTitle']='';
                $couponData['CouponDesc']='';
                $couponData['GoUrl']='';
                $couponData['type']='deal';
                $couponData['Used']='';
                $couponData['CouponRestriction']='';
                $couponData['ExpirationDate']='';
                $couponData['CouponCodeUrl'] = "";
                $couponData['CouponCode']='';
                $couponData['IsUpdateCodeUrl']=0;

                //title
                preg_match_all("/<\/div>\s+<p>(.*?)<\/p>/",$couponHtml,$matchcoupontitle);
                $couponData['CouponTitle']= empty($matchcoupontitle[0])?'':trim(strip_tags($matchcoupontitle[1][0]));
                if(empty($couponData['CouponTitle'])){
                    preg_match_all("/<strong>\s+?<a[^>]+>([\s\S]*?)<\/a>\s+?<\/strong>/",$couponHtml,$matchcoupontitle2);
                    $couponData['CouponTitle']= empty($matchcoupontitle2[0])?'':trim(strip_tags($matchcoupontitle2[1][0]));
                }

                //gourl
                preg_match_all("/href=\"([^\"]+)\"/",$couponHtml,$matchcouponUrl);
                $couponData['GoUrl']=empty($matchcouponUrl[0])?'':$matchcouponUrl[1][0];

                //type && code
                preg_match_all("/name=\"coupon_code_\d+?\" >([\S\s]+?)<\/strong>/", $couponHtml,$matchType);
                if(!empty($matchType[0])){
                    $couponData['type']='code';
                    $couponData['IsUpdateCodeUrl']=0;
                    $couponData['CouponCode']=trim($matchType[1][0]);
                }

                //desc
                preg_match_all("/<p>([^<]+)<\/p>/",$couponHtml,$matchCoupondesc);
                $couponData['CouponDesc']=empty($matchCoupondesc[0])?'':$matchCoupondesc[1][0];;

                //有效期
                preg_match_all("/Scadenza:<\/strong>\s+([^\s]+)/i",$couponHtml,$coupondate);
                if(!empty($coupondate[0])){
                    $couponData['ExpirationDate']=date('Y-m-d',strtotime($coupondate[1][0]));
                }
                if(empty($couponData['ExpirationDate'])){
                    $couponData['ExpirationDate']='0000-00-00';
                }

                $rank++;
                if(!empty($couponData['CouponID']))
                    $couponRankMap[''.$couponData['CouponID']]=$rank;
                if(!isset($couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']])){
                    $sqlIns.="('{$row['CompetitorStoreId']}','{$row['CompetitorId']}','{$couponData['CouponID']}','{$couponData['CouponTitle']}','{$couponData['GoUrl']}','{$couponData['CouponCode']}','{$couponData['ExpirationDate']}','{$couponData['CouponDesc']}','{$couponData['Used']}','{$couponData['Country']}','{$couponData['MaybeValid']}','".date("Y-m-d H:i:s")."','{$couponData['type']}','{$couponData['CouponCodeUrl']}','{$couponData['IsUpdateCodeUrl']}'),";
                }else{
                    $expiresVo=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']];
                    if($expiresVo['ExpirationDate']!=$couponData['ExpirationDate'] || $expiresVo['CouponTitle']!=$couponData['CouponTitle']  || $expiresVo['CouponDesc']!=$couponData['CouponDesc'] || $expiresVo['CouponCodeUrl']!=$couponData['CouponCodeUrl'] || $expiresVo['type']!=$couponData['type']){
                        $sqlUp="update cp_competitor_store_coupon set ExpirationDate='{$couponData['ExpirationDate']}',CouponTitle='{$couponData['CouponTitle']}',CouponDesc='{$couponData['CouponDesc']}',CouponCodeUrl='{$couponData['CouponCodeUrl']}',type='{$couponData['type']}',IsUpdateCodeUrl='{$couponData['IsUpdateCodeUrl']}',LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$expiresVo['ID']}";
                        $db->query($sqlUp);
                    }
                    $couponOnMap[]=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']]['ID'];
                }
            }
            if($sqlIns!=$sqlInsPre){
                $sqlIns=substr($sqlIns,0,strlen($sqlIns)-1);
                $sqlIns.=";";
                $db->query($sqlIns);
            }

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
        // //37   end    http://www.codicepromozionalecoupon.it
    }else if($row['CompetitorId']==39){
        //39 start  http://www.retailmenot.ca
        //获取竞争对手其他store链接  start
        preg_match_all("/href=\"(\/coupons\/.*?)\"/i", $htmlContent, $matchUrl,PREG_SET_ORDER);
        $sqlInsUrl = $sqlInsUrlPre = "insert ignore into cp_temp_competitor_store(StoreUrl,CompetitorId,AddTime) values ";

        if (!empty($matchUrl)) {
            foreach ($matchUrl as $url) {
                $sqlInsUrl .= "('" . 'http://www.retailmenot.ca'.$url[1] . "',{$row['CompetitorId']},'" . date("Y-m-d H:i:s") . "'),";
            }
            if ($sqlInsUrl != $sqlInsUrlPre) {
                $sqlInsUrl = substr($sqlInsUrl, 0, strlen($sqlInsUrl) - 1);
                $sqlInsUrl .= ";";
                $db->query($sqlInsUrl);
            }
        }
        $cs_data_arr = array();

//MetaTitle
        preg_match_all("/<title>([^<]+)<\/title>/", $htmlContent, $matchTitle, PREG_SET_ORDER);
        $cs_data_arr['MetaTitle'] = empty($matchTitle) ? "" : $matchTitle[0][1];
        //keywords
        preg_match_all("/<meta name=\"keywords\" content=\"([^\"]+)\"/", $htmlContent, $matchKeyword, PREG_SET_ORDER);
        $cs_data_arr['MetaKeywords'] =empty($matchKeyword[0])?'':$matchKeyword[0][1];
        //meta description
        preg_match_all("/<meta name=\"description\" content=\"([^\"]+)\"/", $htmlContent, $matchMetaDesc, PREG_SET_ORDER);
        $cs_data_arr['MetaDescription'] = empty($matchMetaDesc) ? "" : $matchMetaDesc[0][1];
        //描述
        preg_match_all("/class=\"store_description\">([\s\S]+?)<\/p>/", $htmlContent, $matchDesc, PREG_SET_ORDER);
        $cs_data_arr['Description'] = empty($matchDesc)?'':trim(del_br_space_by_str(strip_tags($matchDesc[0][1])));
//H1
        preg_match_all("/<h1>(.*?)<\/h1>/", $htmlContent, $matchH1, PREG_SET_ORDER);
        $cs_data_arr['H1'] = empty($matchH1) ? "" : trim(strip_tags($matchH1[0][1]));
//Merchant Go Url
        preg_match_all("/class=\"module logo-wrapper js-outclick-merchant-logo\" href=\"(.*?)\"/", $htmlContent, $matchGo);
        $cs_data_arr['MerchantGoUrl'] = empty($matchGo[0]) ? "" :"http://www.retailmenot.ca".$matchGo[1][0];
        //Screen Img
        preg_match_all("/class=\"logo-image\" src=\"(.*?)\"/",$htmlContent,$matchImg);
        $cs_data_arr['ScreenImg'] = empty($matchImg[0]) ? "" :$matchImg[1][0];


        $sql = $pre_sql = "update cp_competitor_store set ";
        $empty_log_str = "";
        foreach ($cs_data_arr as $key => $val) {
            if (empty($val)) {
                $empty_log_str .= " {$key} --empty --";
            }
            if (strlen($val) > 100) {
                $val = del_br_space_by_str($val);
            }
            if (strripos($key, "meta") !== false) {
                $val = substr($val, 0, 250);
            }
            $sql .= " {$key} = '" . addslashes($val) . "' , ";
        }

        if($sql != $pre_sql ){
            $sql.=" LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$row['CompetitorStoreId']}";
            $GLOBALS['db']->query($sql);
        }

        $rank=0;
        $sqlIns=$sqlInsPre="INSERT ignore into cp_competitor_store_coupon (CompetitorStoreId,CompetitorId,CouponID,CouponTitle,GoUrl,CouponCode,ExpirationDate,CouponDesc,Used,Country,MaybeValid,AddTime,type,CouponCodeUrl,IsUpdateCodeUrl) values ";
//    coupons 数据
        preg_match_all("/class=\"offer_list popular\">([\s\S]+?)<\/div><\/li>\s+?<\/ul>/", $htmlContent, $matchValidCoupon);

        if (!empty($matchValidCoupon[0])) {

            $matchCoupon=explode('<li id=',$matchValidCoupon[1][0]);

            for ($i = 1; $i < count($matchCoupon); $i++) {
                $couponHtml=$matchCoupon[$i];

                $couponData['MaybeValid'] = 1;
                $couponData['Country'] = "CA";
                $couponData['CouponID'] =$rank;
                $couponData['CouponTitle']='';
                $couponData['CouponDesc']='';
                $couponData['GoUrl']='';
                $couponData['type']='deal';
                $couponData['Used']='';
                $couponData['CouponRestriction']='';
                $couponData['ExpirationDate']='';
                $couponData['CouponCodeUrl'] = "";
                $couponData['CouponCode']='';
                $couponData['IsUpdateCodeUrl']=0;

                //couponId
                preg_match_all("/\"c(\d+)\"/",$couponHtml,$matchCouponId);
                if(!empty($matchCouponId[0])){
                    $couponData['CouponID']=$matchCouponId[1][0];
                }

                //title
                preg_match_all("/<h3 class=\"\">([\s\S]+?)<\/h3>/",$couponHtml,$matchcoupontitle);
                $couponData['CouponTitle']= empty($matchcoupontitle[0])?'':trim(strip_tags($matchcoupontitle[1][0]));

                //gourl
                $couponData['GoUrl']='http://www.retailmenot.ca/out/'.$couponData['CouponID'];

                //type && code
                preg_match_all("/class=\"code\">(.*?)<\/div>/", $couponHtml,$matchType);
                if(!empty($matchType[0])){
                    $couponData['type']='code';
                    $couponData['CouponCode']=trim(addslashes($matchType[1][0]));
                }

                //desc
                preg_match_all("/class=\"discount\">(.*?)<\/p>/",$couponHtml,$matchCoupondesc);
                $couponData['CouponDesc']=empty($matchCoupondesc[0])?'':trim(addslashes($matchCoupondesc[1][0]));

                if(empty($couponData['ExpirationDate'])){
                    $couponData['ExpirationDate']='0000-00-00';
                }

                $rank++;
                if(!empty($couponData['CouponID']))
                    $couponRankMap[''.$couponData['CouponID']]=$rank;
                if(!isset($couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']])){
                    $sqlIns.="('{$row['CompetitorStoreId']}','{$row['CompetitorId']}','{$couponData['CouponID']}','{$couponData['CouponTitle']}','{$couponData['GoUrl']}','{$couponData['CouponCode']}','{$couponData['ExpirationDate']}','{$couponData['CouponDesc']}','{$couponData['Used']}','{$couponData['Country']}','{$couponData['MaybeValid']}','".date("Y-m-d H:i:s")."','{$couponData['type']}','{$couponData['CouponCodeUrl']}','{$couponData['IsUpdateCodeUrl']}'),";
                }else{
                    $expiresVo=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']];
                    if($expiresVo['ExpirationDate']!=$couponData['ExpirationDate'] || $expiresVo['CouponTitle']!=$couponData['CouponTitle']  || $expiresVo['CouponDesc']!=$couponData['CouponDesc'] || $expiresVo['CouponCodeUrl']!=$couponData['CouponCodeUrl'] || $expiresVo['type']!=$couponData['type']){
                        $sqlUp="update cp_competitor_store_coupon set ExpirationDate='{$couponData['ExpirationDate']}',CouponTitle='{$couponData['CouponTitle']}',CouponDesc='{$couponData['CouponDesc']}',CouponCodeUrl='{$couponData['CouponCodeUrl']}',type='{$couponData['type']}',IsUpdateCodeUrl='{$couponData['IsUpdateCodeUrl']}',LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$expiresVo['ID']}";
                        $db->query($sqlUp);
                    }
                    $couponOnMap[]=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']]['ID'];
                }
            }
            if($sqlIns!=$sqlInsPre){
                $sqlIns=substr($sqlIns,0,strlen($sqlIns)-1);
                $sqlIns.=";";
                $db->query($sqlIns);
            }

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
        // //39  end    http://www.retailmenot.ca
    }else if($row['CompetitorId']==40){
        //40 start      https://www.bargainmoose.ca/
        //获取竞争对手其他store链接  start
        preg_match_all("/href=\"(https:\/\/www.bargainmoose.ca\/coupons\/.*?)\"/i", $htmlContent, $matchUrl,PREG_SET_ORDER);
        $sqlInsUrl = $sqlInsUrlPre = "insert ignore into cp_temp_competitor_store(StoreUrl,CompetitorId,AddTime) values ";

        if (!empty($matchUrl)) {
            foreach ($matchUrl as $url) {
                $sqlInsUrl .= "('" .$url[1] . "',{$row['CompetitorId']},'" . date("Y-m-d H:i:s") . "'),";
            }
            if ($sqlInsUrl != $sqlInsUrlPre) {
                $sqlInsUrl = substr($sqlInsUrl, 0, strlen($sqlInsUrl) - 1);
                $sqlInsUrl .= ";";
                $db->query($sqlInsUrl);
            }
        }
        $cs_data_arr = array();

//MetaTitle
        preg_match_all("/<title>([^<]+)<\/title>/", $htmlContent, $matchTitle, PREG_SET_ORDER);
        $cs_data_arr['MetaTitle'] = empty($matchTitle) ? "" : $matchTitle[0][1];
        //keywords
        preg_match_all("/<meta name=\"keywords\" content=\"([^\"]+)\"/", $htmlContent, $matchKeyword, PREG_SET_ORDER);
        $cs_data_arr['MetaKeywords'] =empty($matchKeyword[0])?'':$matchKeyword[0][1];
        //meta description
        preg_match_all("/<meta name=\"description\" content=\"([^\"]+)\"/", $htmlContent, $matchMetaDesc, PREG_SET_ORDER);
        $cs_data_arr['MetaDescription'] = empty($matchMetaDesc) ? "" : $matchMetaDesc[0][1];
        //描述
        preg_match_all("/class=\"text-small\">([\s\S]+?)<\/div>/", $htmlContent, $matchDesc, PREG_SET_ORDER);
        $cs_data_arr['Description'] = empty($matchDesc)?'':trim(del_br_space_by_str(strip_tags($matchDesc[0][1])));
//H1
        preg_match_all("/class=\"title mb4\">(.*?)<\/h1>/", $htmlContent, $matchH1, PREG_SET_ORDER);
        $cs_data_arr['H1'] = empty($matchH1) ? "" : trim(strip_tags($matchH1[0][1]));
//Merchant Go Url
        preg_match_all("/href=\"(.*?)\"\s+?class=\"logo-icon\"\s+?title=\"Visit .*?\"><img src=\"(.*?)\"/", $htmlContent, $matchGo);
        $cs_data_arr['MerchantGoUrl'] = empty($matchGo[0]) ? "" :$matchGo[1][0];
        //Screen Img
//    preg_match_all("/class=\"logo-image\" src=\"(.*?)\"/",$htmlContent,$matchImg);
        $cs_data_arr['ScreenImg'] = empty($matchGo[0]) ? "" :$matchGo[2][0];


        $sql = $pre_sql = "update cp_competitor_store set ";
        $empty_log_str = "";
        foreach ($cs_data_arr as $key => $val) {
            if (empty($val)) {
                $empty_log_str .= " {$key} --empty --";
            }
            if (strlen($val) > 100) {
                $val = del_br_space_by_str($val);
            }
            if (strripos($key, "meta") !== false) {
                $val = substr($val, 0, 250);
            }
            $sql .= " {$key} = '" . addslashes($val) . "' , ";
        }

        if($sql != $pre_sql ){
            $sql.=" LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$row['CompetitorStoreId']}";
            $GLOBALS['db']->query($sql);
        }

        $rank=0;
        $sqlIns=$sqlInsPre="INSERT ignore into cp_competitor_store_coupon (CompetitorStoreId,CompetitorId,CouponID,CouponTitle,GoUrl,CouponCode,ExpirationDate,CouponDesc,Used,Country,MaybeValid,AddTime,type,CouponCodeUrl,IsUpdateCodeUrl) values ";
//    coupons 数据
        preg_match_all("/<div class=\"card-voucher bg-white mb3 promotion \"([\s\S]+?)class=\"card--comments\"/", $htmlContent, $matchValidCoupon);

        if (!empty($matchValidCoupon[0])) {

            $matchCoupon=$matchValidCoupon[1];
            for ($i = 0; $i < count($matchCoupon); $i++) {
                $couponHtml=$matchCoupon[$i];

                $couponData['MaybeValid'] = 1;
                $couponData['Country'] = "CA";
                $couponData['CouponID'] =$rank;
                $couponData['CouponTitle']='';
                $couponData['CouponDesc']='';
                $couponData['GoUrl']='';
                $couponData['type']='deal';
                $couponData['Used']='';
                $couponData['CouponRestriction']='';
                $couponData['ExpirationDate']='';
                $couponData['CouponCodeUrl'] = "";
                $couponData['CouponCode']='';
                $couponData['IsUpdateCodeUrl']=0;

                //couponId
                preg_match_all("/id=\"(\d+)\"/",$couponHtml,$matchCouponId);
                if(!empty($matchCouponId[0])){
                    $couponData['CouponID']=$matchCouponId[1][0];
                }

                //title
                preg_match_all("/<h2 class=\"[^\"]+\">([\s\S]+?)<\/h2>/",$couponHtml,$matchcoupontitle);
                $couponData['CouponTitle']= empty($matchcoupontitle[0])?'':trim(strip_tags($matchcoupontitle[1][0]));

                //gourl
                $couponData['GoUrl']='http://www.bargainmoose.ca/coupons/coupons/visit/promotion/'.$couponData['CouponID'];

                //type && code
                preg_match_all("/data-clipboard-text=\"([^\"]+)\"/", $couponHtml,$matchType);
                if(!empty($matchType[0])){
                    $couponData['type']='code';
                    $couponData['CouponCode']=trim(addslashes($matchType[1][0]));
                }

                //desc
                $couponData['CouponDesc']='';

                if(empty($couponData['ExpirationDate'])){
                    $couponData['ExpirationDate']='0000-00-00';
                }

                $rank++;
                if(!empty($couponData['CouponID']))
                    $couponRankMap[''.$couponData['CouponID']]=$rank;
                if(!isset($couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']])){
                    $sqlIns.="('{$row['CompetitorStoreId']}','{$row['CompetitorId']}','{$couponData['CouponID']}','{$couponData['CouponTitle']}','{$couponData['GoUrl']}','{$couponData['CouponCode']}','{$couponData['ExpirationDate']}','{$couponData['CouponDesc']}','{$couponData['Used']}','{$couponData['Country']}','{$couponData['MaybeValid']}','".date("Y-m-d H:i:s")."','{$couponData['type']}','{$couponData['CouponCodeUrl']}','{$couponData['IsUpdateCodeUrl']}'),";
                }else{
                    $expiresVo=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']];
                    if($expiresVo['ExpirationDate']!=$couponData['ExpirationDate'] || $expiresVo['CouponTitle']!=$couponData['CouponTitle']  || $expiresVo['CouponDesc']!=$couponData['CouponDesc'] || $expiresVo['CouponCodeUrl']!=$couponData['CouponCodeUrl'] || $expiresVo['type']!=$couponData['type']){
                        $sqlUp="update cp_competitor_store_coupon set ExpirationDate='{$couponData['ExpirationDate']}',CouponTitle='{$couponData['CouponTitle']}',CouponDesc='{$couponData['CouponDesc']}',CouponCodeUrl='{$couponData['CouponCodeUrl']}',type='{$couponData['type']}',IsUpdateCodeUrl='{$couponData['IsUpdateCodeUrl']}',LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$expiresVo['ID']}";
                        $db->query($sqlUp);
                    }
                    $couponOnMap[]=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']]['ID'];
                }
            }
            if($sqlIns!=$sqlInsPre){
                $sqlIns=substr($sqlIns,0,strlen($sqlIns)-1);
                $sqlIns.=";";
                $db->query($sqlIns);
            }

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
        // //40  end    https://www.bargainmoose.ca/
    }else if($row['CompetitorId']==41){
        //41 start      https://www.savvybeaver.ca/
        //获取竞争对手其他store链接  start
        preg_match_all("/href=\"(\/[^\"]*?-coupons)\"/i", $htmlContent, $matchUrl,PREG_SET_ORDER);
        $sqlInsUrl = $sqlInsUrlPre = "insert ignore into cp_temp_competitor_store(StoreUrl,CompetitorId,AddTime) values ";

        if (!empty($matchUrl)) {
            foreach ($matchUrl as $url) {
                $sqlInsUrl .= "('" ."https://www.savvybeaver.ca". $url[1] . "',{$row['CompetitorId']},'" . date("Y-m-d H:i:s") . "'),";
            }
            if ($sqlInsUrl != $sqlInsUrlPre) {
                $sqlInsUrl = substr($sqlInsUrl, 0, strlen($sqlInsUrl) - 1);
                $sqlInsUrl .= ";";
                $db->query($sqlInsUrl);
            }
        }
        $cs_data_arr = array();

//MetaTitle
        preg_match_all("/<title>([^<]+)<\/title>/", $htmlContent, $matchTitle, PREG_SET_ORDER);
        $cs_data_arr['MetaTitle'] = empty($matchTitle) ? "" : $matchTitle[0][1];
        //keywords
        preg_match_all("/<meta name=\"keywords\" content=\"([^\"]+)\"/", $htmlContent, $matchKeyword, PREG_SET_ORDER);
        $cs_data_arr['MetaKeywords'] =empty($matchKeyword[0])?'':$matchKeyword[0][1];
        //meta description
        preg_match_all("/<meta name=\"description\" content=\"([^\"]+)\"/", $htmlContent, $matchMetaDesc, PREG_SET_ORDER);
        $cs_data_arr['MetaDescription'] = empty($matchMetaDesc) ? "" : $matchMetaDesc[0][1];
        //描述
        preg_match_all("/class=\"read-more-content one-liner\">([\s\S]+?)<\/div>/", $htmlContent, $matchDesc, PREG_SET_ORDER);
        $cs_data_arr['Description'] = empty($matchDesc)?'':trim(del_br_space_by_str(strip_tags($matchDesc[0][1])));
//H1
        preg_match_all("/class=\"title\">([\s\S]+?)<\/h1>/", $htmlContent, $matchH1, PREG_SET_ORDER);
        $cs_data_arr['H1'] = empty($matchH1) ? "" : trim(strip_tags($matchH1[0][1]));
//Merchant Go Url
        preg_match_all("/class=\"logo\" href=\"(.*?)\"/", $htmlContent, $matchGo);
        $cs_data_arr['MerchantGoUrl'] = empty($matchGo[0]) ? "" :"https://www.savvybeaver.ca".$matchGo[1][0];
        //Screen Img
        preg_match_all("/title=\"Visit Shop\"><img src=\"(.*?)\"/",$htmlContent,$matchImg);
        $cs_data_arr['ScreenImg'] = empty($matchImg[0]) ? "" :$matchImg[1][0];


        $sql = $pre_sql = "update cp_competitor_store set ";
        $empty_log_str = "";
        foreach ($cs_data_arr as $key => $val) {
            if (empty($val)) {
                $empty_log_str .= " {$key} --empty --";
            }
            if (strlen($val) > 100) {
                $val = del_br_space_by_str($val);
            }
            if (strripos($key, "meta") !== false) {
                $val = substr($val, 0, 250);
            }
            $sql .= " {$key} = '" . addslashes($val) . "' , ";
        }

        if($sql != $pre_sql ){
            $sql.=" LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$row['CompetitorStoreId']}";
            $GLOBALS['db']->query($sql);
        }

        $rank=0;
        $sqlIns=$sqlInsPre="INSERT ignore into cp_competitor_store_coupon (CompetitorStoreId,CompetitorId,CouponID,CouponTitle,GoUrl,CouponCode,ExpirationDate,CouponDesc,Used,Country,MaybeValid,AddTime,type,CouponCodeUrl,IsUpdateCodeUrl) values ";
//    coupons 数据
        preg_match_all("/class=\"item-holder\"([\s\S]+?)class=\"clearfix\"/", $htmlContent, $matchValidCoupon);

        if (!empty($matchValidCoupon[0])) {

            $matchCoupon=$matchValidCoupon[1];
            for ($i = 0; $i < count($matchCoupon); $i++) {
                $couponHtml=$matchCoupon[$i];

                $couponData['MaybeValid'] = 1;
                $couponData['Country'] = "CA";
                $couponData['CouponID'] =$rank;
                $couponData['CouponTitle']='';
                $couponData['CouponDesc']='';
                $couponData['GoUrl']='';
                $couponData['type']='deal';
                $couponData['Used']='';
                $couponData['CouponRestriction']='';
                $couponData['ExpirationDate']='';
                $couponData['CouponCodeUrl'] = "";
                $couponData['CouponCode']='';
                $couponData['IsUpdateCodeUrl']=0;

                //title
                preg_match_all("/<h3>([\s\S]+?)<\/h3>/",$couponHtml,$matchcoupontitle);
                $couponData['CouponTitle']= empty($matchcoupontitle[0])?'':trim(strip_tags($matchcoupontitle[1][0]));

                //gourl
                preg_match_all("/data-wloc=\"(.*?)\"/",$couponHtml,$matchGoUrl);
                $couponData['GoUrl']=empty($matchGoUrl[0])?'':'https://www.savvybeaver.ca'.$matchGoUrl[1][0];

                //type && code
                preg_match_all("/class=\"redeem-txt\">Get Coupon</", $couponHtml,$matchType);
                preg_match_all("/href=\"\/jcpenny-coupons#d-([^\"]+)\"/",$couponHtml,$matchCouponUrl);

                if(!empty($matchType[0]) && !empty($matchCouponUrl[0])){
                    $couponData['type']='code';
                    $couponData['IsUpdateCodeUrl']=1;
                    $couponData['CouponCodeUrl'] ='https://www.savvybeaver.ca/deal/ajax-view?id='.$matchCouponUrl[1][0];
                }

                //desc
                $couponData['CouponDesc']='';

                if(empty($couponData['ExpirationDate'])){
                    $couponData['ExpirationDate']='0000-00-00';
                }

                $rank++;
                if(!empty($couponData['CouponID']))
                    $couponRankMap[''.$couponData['CouponID']]=$rank;
                if(!isset($couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']])){
                    $sqlIns.="('{$row['CompetitorStoreId']}','{$row['CompetitorId']}','{$couponData['CouponID']}','{$couponData['CouponTitle']}','{$couponData['GoUrl']}','{$couponData['CouponCode']}','{$couponData['ExpirationDate']}','{$couponData['CouponDesc']}','{$couponData['Used']}','{$couponData['Country']}','{$couponData['MaybeValid']}','".date("Y-m-d H:i:s")."','{$couponData['type']}','{$couponData['CouponCodeUrl']}','{$couponData['IsUpdateCodeUrl']}'),";
                }else{
                    $expiresVo=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']];
                    if($expiresVo['ExpirationDate']!=$couponData['ExpirationDate'] || $expiresVo['CouponTitle']!=$couponData['CouponTitle']  || $expiresVo['CouponDesc']!=$couponData['CouponDesc'] || $expiresVo['CouponCodeUrl']!=$couponData['CouponCodeUrl'] || $expiresVo['type']!=$couponData['type']){
                        $sqlUp="update cp_competitor_store_coupon set ExpirationDate='{$couponData['ExpirationDate']}',CouponTitle='{$couponData['CouponTitle']}',CouponDesc='{$couponData['CouponDesc']}',CouponCodeUrl='{$couponData['CouponCodeUrl']}',type='{$couponData['type']}',IsUpdateCodeUrl='{$couponData['IsUpdateCodeUrl']}',LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$expiresVo['ID']}";
                        $db->query($sqlUp);
                    }
                    $couponOnMap[]=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']]['ID'];
                }
            }
            if($sqlIns!=$sqlInsPre){
                $sqlIns=substr($sqlIns,0,strlen($sqlIns)-1);
                $sqlIns.=";";
                $db->query($sqlIns);
            }

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
        // //41  end    https://www.bargainmoose.ca/
    }else if($row['CompetitorId']==42){
        //42 start      http://vouchercodes.ca/

        $cs_data_arr = array();

//MetaTitle
        preg_match_all("/<title>([^<]+)<\/title>/", $htmlContent, $matchTitle, PREG_SET_ORDER);
        $cs_data_arr['MetaTitle'] = empty($matchTitle) ? "" : $matchTitle[0][1];
        //keywords
        preg_match_all("/<meta name=\"keywords\" content=\"([^\"]+)\"/", $htmlContent, $matchKeyword, PREG_SET_ORDER);
        $cs_data_arr['MetaKeywords'] =empty($matchKeyword[0])?'':$matchKeyword[0][1];
        //meta description
        preg_match_all("/<meta name=\"description\" content=\"([^\"]+)\"/", $htmlContent, $matchMetaDesc, PREG_SET_ORDER);
        $cs_data_arr['MetaDescription'] = empty($matchMetaDesc) ? "" : $matchMetaDesc[0][1];
        //描述
        preg_match_all("/class=\"store-desc\">([\s\S]+?)<\/p>/", $htmlContent, $matchDesc, PREG_SET_ORDER);
        $cs_data_arr['Description'] = empty($matchDesc)?'':trim(del_br_space_by_str(strip_tags($matchDesc[0][1])));
//H1
        preg_match_all("/class=\"entry-title\">([\s\S]*?)<\/h1>/", $htmlContent, $matchH1, PREG_SET_ORDER);
        $cs_data_arr['H1'] = empty($matchH1) ? "" : trim(strip_tags($matchH1[0][1]));
//Merchant Go Url
        preg_match_all("/class=\"store-url\"><a href=\"(.*?)\" target=\"_blank\">.*?<\/a>/", $htmlContent, $matchGo);
        $cs_data_arr['MerchantGoUrl'] = empty($matchGo[0]) ? "" :$matchGo[1][0];
        //Screen Img
        preg_match_all("/target=\"_blank\"><img src=\"(.*?)\" class=\"store-thumbnail\"/",$htmlContent,$matchImg);
        $cs_data_arr['ScreenImg'] = empty($matchImg[0]) ? "" :$matchImg[1][0];


        $sql = $pre_sql = "update cp_competitor_store set ";
        $empty_log_str = "";
        foreach ($cs_data_arr as $key => $val) {
            if (empty($val)) {
                $empty_log_str .= " {$key} --empty --";
            }
            if (strlen($val) > 100) {
                $val = del_br_space_by_str($val);
            }
            if (strripos($key, "meta") !== false) {
                $val = substr($val, 0, 250);
            }
            $sql .= " {$key} = '" . addslashes($val) . "' , ";
        }

        if($sql != $pre_sql ){
            $sql.=" LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$row['CompetitorStoreId']}";
            $GLOBALS['db']->query($sql);
        }

        $rank=0;
        $sqlIns=$sqlInsPre="INSERT ignore into cp_competitor_store_coupon (CompetitorStoreId,CompetitorId,CouponID,CouponTitle,GoUrl,CouponCode,ExpirationDate,CouponDesc,Used,Country,MaybeValid,AddTime,type,CouponCodeUrl,IsUpdateCodeUrl) values ";
//    coupons 数据
        preg_match_all("/(<article id=\"post-\d+\" class=\"[^\"]+status-publish[^\"]+\"[\s\S]+?<\/article>)/i", $htmlContent, $matchValidCoupon);

        if (!empty($matchValidCoupon[0])) {

            $matchCoupon=$matchValidCoupon[1];
            for ($i = 0; $i < count($matchCoupon); $i++) {
                $couponHtml=$matchCoupon[$i];

                $couponData['MaybeValid'] = 1;
                $couponData['Country'] = "CA";
                $couponData['CouponID'] =$rank;
                $couponData['CouponTitle']='';
                $couponData['CouponDesc']='';
                $couponData['GoUrl']='';
                $couponData['type']='deal';
                $couponData['Used']='';
                $couponData['CouponRestriction']='';
                $couponData['ExpirationDate']='';
                $couponData['CouponCodeUrl'] = "";
                $couponData['CouponCode']='';
                $couponData['IsUpdateCodeUrl']=0;

                preg_match_all("/id=\"post-(\d+)\"/",$couponHtml,$matchCouponId);
                if(!empty($matchCouponId[0])){
                    $couponData['CouponID']=$matchCouponId[1][0];
                }

                //title
                preg_match_all("/class=\"entry-title\">([\s\S]+?)<\/h1>/",$couponHtml,$matchcoupontitle);
                $couponData['CouponTitle']= empty($matchcoupontitle[0])?'':trim(strip_tags($matchcoupontitle[1][0]));

                //gourl
                $couponData['GoUrl']=empty($matchGo[0]) ? "" :$matchGo[1][0];

                //type && code
                preg_match_all("/data-clipboard-text=\"(.*?)\"/",$couponHtml,$matchCouponUrl);
                if(!empty($matchCouponUrl[0]) && trim($matchCouponUrl[1][0])!=''){
                    $couponData['type']='code';
                    $couponData['CouponCode']=$matchCouponUrl[1][0];
                }

                //desc
                preg_match_all("/class=\"entry-excerpt\">([\s\S]+?)<\/div>/",$couponHtml,$matchCoupondesc);
                $couponData['CouponDesc']=empty($matchCoupondesc[0])?'':trim(addslashes($matchCoupondesc[1][0]));

                if(empty($couponData['ExpirationDate'])){
                    $couponData['ExpirationDate']='0000-00-00';
                }

                $rank++;
                if(!empty($couponData['CouponID']))
                    $couponRankMap[''.$couponData['CouponID']]=$rank;
                if(!isset($couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']])){
                    $sqlIns.="('{$row['CompetitorStoreId']}','{$row['CompetitorId']}','{$couponData['CouponID']}','{$couponData['CouponTitle']}','{$couponData['GoUrl']}','{$couponData['CouponCode']}','{$couponData['ExpirationDate']}','{$couponData['CouponDesc']}','{$couponData['Used']}','{$couponData['Country']}','{$couponData['MaybeValid']}','".date("Y-m-d H:i:s")."','{$couponData['type']}','{$couponData['CouponCodeUrl']}','{$couponData['IsUpdateCodeUrl']}'),";
                }else{
                    $expiresVo=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']];
                    if($expiresVo['ExpirationDate']!=$couponData['ExpirationDate'] || $expiresVo['CouponTitle']!=$couponData['CouponTitle']  || $expiresVo['CouponDesc']!=$couponData['CouponDesc'] || $expiresVo['CouponCodeUrl']!=$couponData['CouponCodeUrl'] || $expiresVo['type']!=$couponData['type']){
                        $sqlUp="update cp_competitor_store_coupon set ExpirationDate='{$couponData['ExpirationDate']}',CouponTitle='{$couponData['CouponTitle']}',CouponDesc='{$couponData['CouponDesc']}',CouponCodeUrl='{$couponData['CouponCodeUrl']}',type='{$couponData['type']}',IsUpdateCodeUrl='{$couponData['IsUpdateCodeUrl']}',LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$expiresVo['ID']}";
                        $db->query($sqlUp);
                    }
                    $couponOnMap[]=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']]['ID'];
                }
            }
            if($sqlIns!=$sqlInsPre){
                $sqlIns=substr($sqlIns,0,strlen($sqlIns)-1);
                $sqlIns.=";";
                $db->query($sqlIns);
            }

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
        // //42  end   http://vouchercodes.ca/
    }else if($row['CompetitorId']==43){

        preg_match_all("/<a href=\"(\/[^\"\/]+?)\" title/i", $htmlContent, $matchUrl,PREG_SET_ORDER);
        $sqlInsUrl = $sqlInsUrlPre = "insert ignore into cp_temp_competitor_store(StoreUrl,CompetitorId,AddTime) values ";
        //site_pre
        $site_pre='https://www.reduc.fr';
        if (!empty($matchUrl)) {
            foreach ($matchUrl as $url) {
                $sqlInsUrl .= "('" .$site_pre. $url[1] . "',{$row['CompetitorId']},'" . date("Y-m-d H:i:s") . "'),";
            }
            if ($sqlInsUrl != $sqlInsUrlPre) {
                $sqlInsUrl = substr($sqlInsUrl, 0, strlen($sqlInsUrl) - 1);
                $sqlInsUrl .= ";";

                $db->query($sqlInsUrl);
            }
        }


        $cs_data_arr = array();

//MetaTitle
        preg_match_all("/<title>([^<]+)<\/title>/", $htmlContent, $matchTitle, PREG_SET_ORDER);
        $cs_data_arr['MetaTitle'] = empty($matchTitle) ? "" : $matchTitle[0][1];
        //keywords
        preg_match_all("/<meta name=\"keywords\" content=\"([^\"]+)\"/", $htmlContent, $matchKeyword, PREG_SET_ORDER);
        $cs_data_arr['MetaKeywords'] =empty($matchKeyword[0])?'':$matchKeyword[0][1];
        //meta description
        preg_match_all("/<meta name=\"description\" content=\"([^\"]+)\"/", $htmlContent, $matchMetaDesc, PREG_SET_ORDER);
        $cs_data_arr['MetaDescription'] = empty($matchMetaDesc) ? "" : $matchMetaDesc[0][1];
        //描述
        preg_match_all("/class=\"description\">([\s\S]+?)<\/div>/", $htmlContent, $matchDesc, PREG_SET_ORDER);
        $cs_data_arr['Description'] = empty($matchDesc)?'':trim(del_br_space_by_str(strip_tags($matchDesc[0][1])));
//H1
        preg_match_all("/<h1 class=\"h2\">([\s\S]*?)<\/h1>/", $htmlContent, $matchH1, PREG_SET_ORDER);
        $cs_data_arr['H1'] = empty($matchH1) ? "" : trim(strip_tags($matchH1[0][1]));
//Merchant Go Url
        preg_match_all("/data-redirect=\"(.*?)\"/", $htmlContent, $matchGo);
        $cs_data_arr['MerchantGoUrl'] = empty($matchGo[0]) ? "" :$matchGo[1][0];
        //Screen Img
        preg_match_all("/class=\"zorro-url\"[^>]+?>\s+<img src=\"([^\"]+?)\"/",$htmlContent,$matchImg);
        $cs_data_arr['ScreenImg'] = empty($matchImg[0]) ? "" :$matchImg[1][0];


        $sql = $pre_sql = "update cp_competitor_store set ";
        $empty_log_str = "";
        foreach ($cs_data_arr as $key => $val) {
            if (empty($val)) {
                $empty_log_str .= " {$key} --empty --";
            }
            if (strlen($val) > 100) {
                $val = del_br_space_by_str($val);
            }
            if (strripos($key, "meta") !== false) {
                $val = substr($val, 0, 250);
            }
            $sql .= " {$key} = '" . addslashes($val) . "' , ";
        }

        if($sql != $pre_sql ){
            $sql.=" LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$row['CompetitorStoreId']}";
            $GLOBALS['db']->query($sql);
        }

        $rank=0;
        $sqlIns=$sqlInsPre="INSERT ignore into cp_competitor_store_coupon (CompetitorStoreId,CompetitorId,CouponID,CouponTitle,GoUrl,CouponCode,ExpirationDate,CouponDesc,Used,Country,MaybeValid,AddTime,type,CouponCodeUrl,IsUpdateCodeUrl) values ";
//    coupons 数据
        preg_match_all("/id=\"all-results\">([\s\S]+?)<\/article>\s+?<\/div>/i", $htmlContent, $matchValidCoupon);

        if (!empty($matchValidCoupon[0])) {

            $matchCoupon=explode('class="big-voucher',$matchValidCoupon[1][0]);
            for ($i = 1; $i < count($matchCoupon); $i++) {
                $couponHtml=$matchCoupon[$i];

                $couponData['MaybeValid'] = 1;
                $couponData['Country'] = "FR";
                $couponData['CouponID'] =$rank;
                $couponData['CouponTitle']='';
                $couponData['CouponDesc']='';
                $couponData['GoUrl']='';
                $couponData['type']='deal';
                $couponData['Used']='';
                $couponData['CouponRestriction']='';
                $couponData['ExpirationDate']='';
                $couponData['CouponCodeUrl'] = "";
                $couponData['CouponCode']='';
                $couponData['IsUpdateCodeUrl']=0;

                preg_match_all("/data-id=\"(.*?)\"/i",$couponHtml,$matchCouponId);
                $couponData['CouponID']= empty($matchCouponId[0])?'':trim($matchCouponId[1][0]);

                //title
                preg_match_all("/<span class=\"zorro-url\"[^>]+>([\s\S]+?)<\/span>/",$couponHtml,$matchcoupontitle);
                $couponData['CouponTitle']= empty($matchcoupontitle[0])?'':trim(strip_tags($matchcoupontitle[1][0]));

                //type && code
                preg_match_all("/class=\"coupon-inner\">(.*?)<\/div>/",$couponHtml,$matchCode);

                if(!empty($matchCode[0])){
                    $couponData['type']='code';
                    $couponData['CouponCodeUrl']="https://www.reduc.fr/gutscheine/undefined/{$couponData['CouponID']}";
                    $couponData['IsUpdateCodeUrl']=1;
                }

                if(empty($couponData['ExpirationDate'])){
                    $couponData['ExpirationDate']='0000-00-00';
                }

                $rank++;
                if(!empty($couponData['CouponID']))
                    $couponRankMap[''.$couponData['CouponID']]=$rank;
                if(!isset($couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']])){
                    $sqlIns.="('{$row['CompetitorStoreId']}','{$row['CompetitorId']}','{$couponData['CouponID']}','{$couponData['CouponTitle']}','{$couponData['GoUrl']}','{$couponData['CouponCode']}','{$couponData['ExpirationDate']}','{$couponData['CouponDesc']}','{$couponData['Used']}','{$couponData['Country']}','{$couponData['MaybeValid']}','".date("Y-m-d H:i:s")."','{$couponData['type']}','{$couponData['CouponCodeUrl']}','{$couponData['IsUpdateCodeUrl']}'),";
                }else{
                    $expiresVo=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']];
                    if($expiresVo['ExpirationDate']!=$couponData['ExpirationDate'] || $expiresVo['CouponTitle']!=$couponData['CouponTitle']  || $expiresVo['CouponDesc']!=$couponData['CouponDesc'] || $expiresVo['CouponCodeUrl']!=$couponData['CouponCodeUrl'] || $expiresVo['type']!=$couponData['type']){
                        $sqlUp="update cp_competitor_store_coupon set ExpirationDate='{$couponData['ExpirationDate']}',CouponTitle='{$couponData['CouponTitle']}',CouponDesc='{$couponData['CouponDesc']}',CouponCodeUrl='{$couponData['CouponCodeUrl']}',type='{$couponData['type']}',IsUpdateCodeUrl='{$couponData['IsUpdateCodeUrl']}',LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$expiresVo['ID']}";
                        $db->query($sqlUp);
                    }
                    $couponOnMap[]=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']]['ID'];
                }
            }
            if($sqlIns!=$sqlInsPre){
                $sqlIns=substr($sqlIns,0,strlen($sqlIns)-1);
                $sqlIns.=";";
                $db->query($sqlIns);
            }

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
        // //43  end   https://www.reduc.fr/laredoute#
    }else if($row['CompetitorId']==44){
        //44 start     http://coupons.ca/tanda

        $cs_data_arr = array();

//MetaTitle
        preg_match_all("/<title>([^<]+)<\/title>/", $htmlContent, $matchTitle, PREG_SET_ORDER);
        $cs_data_arr['MetaTitle'] = empty($matchTitle) ? "" : $matchTitle[0][1];
        //keywords
        preg_match_all("/<meta name=\"keywords\" content=\"([^\"]+)\"/", $htmlContent, $matchKeyword, PREG_SET_ORDER);
        $cs_data_arr['MetaKeywords'] =empty($matchKeyword[0])?'':$matchKeyword[0][1];
        //meta description
        preg_match_all("/<meta name=\"description\" content=\"([^\"]+)\"/", $htmlContent, $matchMetaDesc, PREG_SET_ORDER);
        $cs_data_arr['MetaDescription'] = empty($matchMetaDesc) ? "" : $matchMetaDesc[0][1];
        //描述
        preg_match_all("/class=\"description\">([\s\S]+?)<\/div>/", $htmlContent, $matchDesc, PREG_SET_ORDER);
        $cs_data_arr['Description'] = empty($matchDesc)?'':trim(del_br_space_by_str(strip_tags($matchDesc[0][1])));
//H1
        preg_match_all("/<h1>([\s\S]*?)<\/h1>/", $htmlContent, $matchH1, PREG_SET_ORDER);
        $cs_data_arr['H1'] = empty($matchH1) ? "" : trim(strip_tags($matchH1[0][1]));
//Merchant Go Url
        preg_match_all("/type=\"text\" value=\"(.*?)\"/", $htmlContent, $matchGo);
        $cs_data_arr['MerchantGoUrl'] = empty($matchGo[0]) ? "" :$matchGo[1][0];
        //Screen Img
        preg_match_all("/imgMerchantLogo\" src=\"(.*?)\"/",$htmlContent,$matchImg);
        $cs_data_arr['ScreenImg'] = empty($matchImg[0]) ? "" :$matchImg[1][0];


        $sql = $pre_sql = "update cp_competitor_store set ";
        $empty_log_str = "";
        foreach ($cs_data_arr as $key => $val) {
            if (empty($val)) {
                $empty_log_str .= " {$key} --empty --";
            }
            if (strlen($val) > 100) {
                $val = del_br_space_by_str($val);
            }
            if (strripos($key, "meta") !== false) {
                $val = substr($val, 0, 250);
            }
            $sql .= " {$key} = '" . addslashes($val) . "' , ";
        }

        if($sql != $pre_sql ){
            $sql.=" LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$row['CompetitorStoreId']}";
            $GLOBALS['db']->query($sql);
        }

        $rank=0;
        $sqlIns=$sqlInsPre="INSERT ignore into cp_competitor_store_coupon (CompetitorStoreId,CompetitorId,CouponID,CouponTitle,GoUrl,CouponCode,ExpirationDate,CouponDesc,Used,Country,MaybeValid,AddTime,type,CouponCodeUrl,IsUpdateCodeUrl) values ";
//    coupons 数据
        preg_match_all("/<!-- breadbar -->([\s\S]+?)<!-- divUserCouponWrap/i", $htmlContent, $matchValidCoupon);

        if (!empty($matchValidCoupon[0])) {

            $matchCoupon=explode('<div class="coupon">',$matchValidCoupon[1][0]);
            for ($i = 1; $i < count($matchCoupon); $i++) {
                $couponHtml=$matchCoupon[$i];

                $couponData['MaybeValid'] = 1;
                $couponData['Country'] = "CA";
                $couponData['CouponID'] =$rank;
                $couponData['CouponTitle']='';
                $couponData['CouponDesc']='';
                $couponData['GoUrl']='';
                $couponData['type']='deal';
                $couponData['Used']='';
                $couponData['CouponRestriction']='';
                $couponData['ExpirationDate']='';
                $couponData['CouponCodeUrl'] = "";
                $couponData['CouponCode']='';
                $couponData['IsUpdateCodeUrl']=0;

                //title
                preg_match_all("/<h3 class=\"heading\">([\s\S]*?)<\/h3>/",$couponHtml,$matchcoupontitle);
                $couponData['CouponTitle']= empty($matchcoupontitle[0])?'':trim(strip_tags($matchcoupontitle[1][0]));

                //gourl
                preg_match_all("/href=\"(http:\/\/coupons.ca\/r.aspx\?[^\"]+)\"/",$couponHtml,$matchGoUrl);
                $couponData['GoUrl']=empty($matchGo[0]) ? "" :$matchGo[1][0];

                //type && code
                preg_match_all("/class=\"code\">(.*?)<\/span>/",$couponHtml,$matchCode);

                if(!empty($matchCode[0]) && trim($matchCode[1][0]!='Click to Save')){
                    $couponData['type']='code';
                    $couponData['CouponCode']=$matchCode[1][0];
                }

//            //desc
                preg_match_all("/class=\"description\">([\s\S]*?)<\/p>/",$couponHtml,$matchCoupondesc);
                $couponData['CouponDesc']=empty($matchCoupondesc[0])?'':trim(addslashes($matchCoupondesc[1][0]));

                if(empty($couponData['CouponTitle']) && !empty($couponData['CouponDesc'])){
                    $couponData['CouponTitle']=$couponData['CouponDesc'];
                }

                //容错处理
                if(empty($couponData['CouponTitle']) && empty($couponData['CouponDesc'])){
                    continue;
                }
                
                if(empty($couponData['ExpirationDate'])){
                    $couponData['ExpirationDate']='0000-00-00';
                }

                $rank++;
                if(!empty($couponData['CouponID']))
                    $couponRankMap[''.$couponData['CouponID']]=$rank;
                if(!isset($couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']])){
                    $sqlIns.="('{$row['CompetitorStoreId']}','{$row['CompetitorId']}','{$couponData['CouponID']}','{$couponData['CouponTitle']}','{$couponData['GoUrl']}','{$couponData['CouponCode']}','{$couponData['ExpirationDate']}','{$couponData['CouponDesc']}','{$couponData['Used']}','{$couponData['Country']}','{$couponData['MaybeValid']}','".date("Y-m-d H:i:s")."','{$couponData['type']}','{$couponData['CouponCodeUrl']}','{$couponData['IsUpdateCodeUrl']}'),";
                }else{
                    $expiresVo=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']];
                    if($expiresVo['ExpirationDate']!=$couponData['ExpirationDate'] || $expiresVo['CouponTitle']!=$couponData['CouponTitle']  || $expiresVo['CouponDesc']!=$couponData['CouponDesc'] || $expiresVo['CouponCodeUrl']!=$couponData['CouponCodeUrl'] || $expiresVo['type']!=$couponData['type']){
                        $sqlUp="update cp_competitor_store_coupon set ExpirationDate='{$couponData['ExpirationDate']}',CouponTitle='{$couponData['CouponTitle']}',CouponDesc='{$couponData['CouponDesc']}',CouponCodeUrl='{$couponData['CouponCodeUrl']}',type='{$couponData['type']}',IsUpdateCodeUrl='{$couponData['IsUpdateCodeUrl']}',LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$expiresVo['ID']}";
                        $db->query($sqlUp);
                    }
                    $couponOnMap[]=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']]['ID'];
                }
            }
            if($sqlIns!=$sqlInsPre){
                $sqlIns=substr($sqlIns,0,strlen($sqlIns)-1);
                $sqlIns.=";";
                $db->query($sqlIns);
            }

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
        // //44  end  http://coupons.ca/tanda
    }else if($row['CompetitorId']==45){
        //45 start     http://coupons.ca/tanda

        $cs_data_arr = array();

//MetaTitle
        preg_match_all("/<h1 class=\"ilcorrieredellasera-title\">(.*?)<\/h1>/", $htmlContent, $matchTitle, PREG_SET_ORDER);
        $cs_data_arr['MetaTitle'] = empty($matchTitle) ? "" : $matchTitle[0][1];
        //keywords No Meta keywords
        $cs_data_arr['MetaKeywords'] = '';
        //meta description
        preg_match_all("/<meta name=\"description\" content=\"(.*?)\"/", $htmlContent, $matchMetaDesc, PREG_SET_ORDER);
        $cs_data_arr['MetaDescription'] = empty($matchMetaDesc) ? "" : addslashes( substr(trim(strip_tags($matchMetaDesc[0][1])),0,250));
        //描述
        preg_match_all("/<h2 class=\"ilcorrieredellasera-subtitle\">(.*?)<\/h2>/", $htmlContent, $matchDesc, PREG_SET_ORDER);
        $cs_data_arr['Description'] = empty($matchDesc)?'':trim(del_br_space_by_str(strip_tags($matchDesc[0][1])));
        //H1
        preg_match_all("/<h1 class=\"ilcorrieredellasera-title\">(.*?)<\/h1>/", $htmlContent, $matchH1, PREG_SET_ORDER);
        $cs_data_arr['H1'] = empty($matchH1) ? "" : trim(strip_tags($matchH1[0][1]));
        //Merchant Go Url
        preg_match_all("/href=\"([^\"]+)\" target=\"_blank\" rel=\"nofollow\" class=\"button mgos\"/", $htmlContent, $matchGo);
        $cs_data_arr['MerchantGoUrl'] = empty($matchGo[0]) ? "" :$matchGo[1][0];
        //Screen Img
        preg_match_all("/class=\"ilcorrieredellasera-retailer-logo\" data-slug=\".*?\"><img src=\"(.*?)\"/",$htmlContent,$matchImg);
        $cs_data_arr['ScreenImg'] = empty($matchImg[0]) ? "" :$matchImg[1][0];


        $sql = $pre_sql = "update cp_competitor_store set ";
        $empty_log_str = "";
        foreach ($cs_data_arr as $key => $val) {
            if (empty($val)) {
                $empty_log_str .= " {$key} --empty --";
            }
            if (strlen($val) > 100) {
                $val = del_br_space_by_str($val);
            }
            if (strripos($key, "meta") !== false) {
                $val = substr($val, 0, 250);
            }
            $sql .= " {$key} = '" . addslashes($val) . "' , ";
        }

        if($sql != $pre_sql ){
            $sql.=" LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$row['CompetitorStoreId']}";
            $GLOBALS['db']->query($sql);
        }

        $rank=0;
        $sqlIns=$sqlInsPre="INSERT ignore into cp_competitor_store_coupon (CompetitorStoreId,CompetitorId,CouponID,CouponTitle,GoUrl,CouponCode,ExpirationDate,CouponDesc,Used,Country,MaybeValid,AddTime,type,CouponCodeUrl,IsUpdateCodeUrl) values ";
//    coupons 数据
        preg_match_all("/id=\"item-\d+\"([\s\S]+?)<\/span><\/div><\/div>/", $htmlContent, $matchValidCoupon);

        if (!empty($matchValidCoupon[0])) {
            $matchCoupon=$matchValidCoupon[1];
            for($i=0;$i<count($matchCoupon);$i++){
                $couponHtml=$matchCoupon[$i];

                $couponData['MaybeValid'] = 1;
                $couponData['Country'] = "IT";
                $couponData['CouponID'] =$rank;
                $couponData['CouponTitle']='';
                $couponData['CouponDesc']='';
                $couponData['GoUrl']='';
                $couponData['type']='deal';
                $couponData['Used']='';
                $couponData['CouponRestriction']='';
                $couponData['ExpirationDate']='';
                $couponData['CouponCodeUrl'] = "";
                $couponData['CouponCode']='';
                $couponData['IsUpdateCodeUrl']=0;

                preg_match_all("/expired\"/",$couponHtml,$matchActive);
                if(!empty($matchActive[0])){
                    continue;
                }

                //couponId
                preg_match_all("/data-voucher-id=\"([^\"]+)\"/",$couponHtml,$matchCouponId);
                if(!empty($matchCouponId[0])){
                    $couponData['CouponID']=$matchCouponId[1][0];
                }

                //type && code
                preg_match_all("/ilcorrieredellasera-voucher code/", $couponHtml,$matchType);
                if(!empty($matchType[0])){
                    $couponData['IsUpdateCodeUrl']='1';
                    $couponData['CouponCodeUrl']="http://sconti.corriere.it/ajax/voucherpopup?id=".$couponData['CouponID'];
                }

                //title
                preg_match_all("/<h3 class=\"ilcorrieredellasera-title\">([\s\S]+?)<\/h3>/",$couponHtml,$matchcoupontitle);
                $couponData['CouponTitle']= empty($matchcoupontitle[0])?'':trim(strip_tags($matchcoupontitle[1][0]));

                //gourl
                $couponData['GoUrl']=$cs_data_arr['MerchantGoUrl'];

                //desc
                $couponData['CouponDesc']='';

                //有效期
                if(empty($couponData['ExpirationDate'])){
                    $couponData['ExpirationDate']='0000-00-00';
                }

                $rank++;
                if(!empty($couponData['CouponID']))
                    $couponRankMap[''.$couponData['CouponID']]=$rank;
                if(!isset($couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']])){
                    $sqlIns.="('{$row['CompetitorStoreId']}','{$row['CompetitorId']}','{$couponData['CouponID']}','{$couponData['CouponTitle']}','{$couponData['GoUrl']}','{$couponData['CouponCode']}','{$couponData['ExpirationDate']}','{$couponData['CouponDesc']}','{$couponData['Used']}','{$couponData['Country']}','{$couponData['MaybeValid']}','".date("Y-m-d H:i:s")."','{$couponData['type']}','{$couponData['CouponCodeUrl']}','{$couponData['IsUpdateCodeUrl']}'),";
                }else{
                    $expiresVo=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']];
                    if($expiresVo['ExpirationDate']!=$couponData['ExpirationDate'] || $expiresVo['CouponTitle']!=$couponData['CouponTitle']  || $expiresVo['CouponDesc']!=$couponData['CouponDesc'] || $expiresVo['CouponCodeUrl']!=$couponData['CouponCodeUrl'] || $expiresVo['type']!=$couponData['type']){
                        $sqlUp="update cp_competitor_store_coupon set ExpirationDate='{$couponData['ExpirationDate']}',CouponTitle='{$couponData['CouponTitle']}',CouponDesc='{$couponData['CouponDesc']}',CouponCodeUrl='{$couponData['CouponCodeUrl']}',type='{$couponData['type']}',IsUpdateCodeUrl='{$couponData['IsUpdateCodeUrl']}',LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$expiresVo['ID']}";
                        $db->query($sqlUp);
                    }
                    $couponOnMap[]=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']]['ID'];
                }
            }
            if($sqlIns!=$sqlInsPre){
                $sqlIns=substr($sqlIns,0,strlen($sqlIns)-1);
                $sqlIns.=";";
                $db->query($sqlIns);
            }

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
        // //45  end  http://coupons.ca/tanda
    }else if($row['CompetitorId']==46){
        //46 start     http://coupons.ca/tanda
        preg_match_all("/href=\"(http:\/\/www.acties.nl\/[^\/\"]+)\"/i", $htmlContent, $matchUrl,PREG_SET_ORDER);
        $sqlInsUrl = $sqlInsUrlPre = "insert ignore into cp_temp_competitor_store(StoreUrl,CompetitorId,AddTime) values ";

        if (!empty($matchUrl)) {
            foreach ($matchUrl as $url) {
                $sqlInsUrl .= "('" . $url[1] . "',{$row['CompetitorId']},'" . date("Y-m-d H:i:s") . "'),";
            }
            if ($sqlInsUrl != $sqlInsUrlPre) {
                $sqlInsUrl = substr($sqlInsUrl, 0, strlen($sqlInsUrl) - 1);
                $sqlInsUrl .= ";";

                $db->query($sqlInsUrl);
            }
        }

        $cs_data_arr = array();

//MetaTitle
        preg_match_all("/<title>(.*?)<\/title>/", $htmlContent, $matchTitle, PREG_SET_ORDER);
        $cs_data_arr['MetaTitle'] = empty($matchTitle) ? "" : $matchTitle[0][1];
        //keywords No Meta keywords
        $cs_data_arr['MetaKeywords'] = '';
        //meta description
        preg_match_all("/<meta name=\"description\" content=\"(.*?)\"/", $htmlContent, $matchMetaDesc, PREG_SET_ORDER);
        $cs_data_arr['MetaDescription'] = empty($matchMetaDesc) ? "" : addslashes( substr(trim(strip_tags($matchMetaDesc[0][1])),0,250));
        //描述
        preg_match_all("/<h2>(.*?)<\/h2>/", $htmlContent, $matchDesc, PREG_SET_ORDER);
        $cs_data_arr['Description'] = empty($matchDesc)?'':trim(del_br_space_by_str(strip_tags($matchDesc[0][1])));
        //H1
        preg_match_all("/<h1 class=\"check\">(.*?)<\/h1>/", $htmlContent, $matchH1, PREG_SET_ORDER);
        $cs_data_arr['H1'] = empty($matchH1) ? "" : trim(strip_tags($matchH1[0][1]));
        //Merchant Go Url
        preg_match_all("/href=\"([^\"]+)\" target=\"_blank\" rel=\"nofollow\" class=\"button mgos\"/", $htmlContent, $matchGo);
        $cs_data_arr['MerchantGoUrl'] = empty($matchGo[0]) ? "" :$matchGo[1][0];
        //Screen Img
        preg_match_all("/data-clickable>\s+<img src=\"(.*?)\"/",$htmlContent,$matchImg);
        $cs_data_arr['ScreenImg'] = empty($matchImg[0]) ? "" :$matchImg[1][0];


        $sql = $pre_sql = "update cp_competitor_store set ";
        $empty_log_str = "";
        foreach ($cs_data_arr as $key => $val) {
            if (empty($val)) {
                $empty_log_str .= " {$key} --empty --";
            }
            if (strlen($val) > 100) {
                $val = del_br_space_by_str($val);
            }
            if (strripos($key, "meta") !== false) {
                $val = substr($val, 0, 250);
            }
            $sql .= " {$key} = '" . addslashes($val) . "' , ";
        }

        if($sql != $pre_sql ){
            $sql.=" LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$row['CompetitorStoreId']}";
            $GLOBALS['db']->query($sql);
        }

        $rank=0;
        $sqlIns=$sqlInsPre="INSERT ignore into cp_competitor_store_coupon (CompetitorStoreId,CompetitorId,CouponID,CouponTitle,GoUrl,CouponCode,ExpirationDate,CouponDesc,Used,Country,MaybeValid,AddTime,type,CouponCodeUrl,IsUpdateCodeUrl) values ";
//    coupons 数据
        preg_match_all("/class=\"offers\">([\s\S]+?)<\/section>/", $htmlContent, $matchValidCoupon);

        if (!empty($matchValidCoupon[0])) {
            $matchCoupon=explode('<article',$matchValidCoupon[1][0]);
            for($i=1;$i<count($matchCoupon);$i++){
                $couponHtml=$matchCoupon[$i];

                $couponData['MaybeValid'] = 1;
                $couponData['Country'] = "NL";
                $couponData['CouponID'] =$rank;
                $couponData['CouponTitle']='';
                $couponData['CouponDesc']='';
                $couponData['GoUrl']='';
                $couponData['type']='deal';
                $couponData['Used']='';
                $couponData['CouponRestriction']='';
                $couponData['ExpirationDate']='';
                $couponData['CouponCodeUrl'] = "";
                $couponData['CouponCode']='';
                $couponData['IsUpdateCodeUrl']=0;

                preg_match_all("/id=\"offer-(\d+)\"/",$couponHtml,$matchCouponId);
                if(!empty($matchCouponId[0])){
                    $couponData['CouponID']=$matchCouponId[1][0];
                }

                //type && code
                preg_match_all("/class=\"code\">([\s\S]+?)<\/div>/", $couponHtml,$matchType);
                if(!empty($matchType[0])){
                    $couponData['type']='code';
                    $couponData['CouponCode']=trim(strip_tags($matchType[1][0]));
               }

                //title
                preg_match_all("/<h3>([\s\S]+?)<\/h3>/",$couponHtml,$matchcoupontitle);
                $couponData['CouponTitle']= empty($matchcoupontitle[0])?'':trim(strip_tags($matchcoupontitle[1][0]));

                //gourl
                $couponData['GoUrl']='http://www.acties.nl/jump/offer/'.$couponData['CouponID'];

                //desc
                preg_match_all("/class=\"terms\">(.*?)<\//",$couponHtml,$matchCoupondesc);
                $couponData['CouponDesc']=empty($matchCoupondesc[0])?'':trim(strip_tags($matchCoupondesc[1][0]));

                //有效期
                if(empty($couponData['ExpirationDate'])){
                    $couponData['ExpirationDate']='0000-00-00';
                }

                $rank++;
                if(!empty($couponData['CouponID']))
                    $couponRankMap[''.$couponData['CouponID']]=$rank;
                if(!isset($couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']])){
                    $sqlIns.="('{$row['CompetitorStoreId']}','{$row['CompetitorId']}','{$couponData['CouponID']}','{$couponData['CouponTitle']}','{$couponData['GoUrl']}','{$couponData['CouponCode']}','{$couponData['ExpirationDate']}','{$couponData['CouponDesc']}','{$couponData['Used']}','{$couponData['Country']}','{$couponData['MaybeValid']}','".date("Y-m-d H:i:s")."','{$couponData['type']}','{$couponData['CouponCodeUrl']}','{$couponData['IsUpdateCodeUrl']}'),";
                }else{
                    $expiresVo=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']];
                    if($expiresVo['ExpirationDate']!=$couponData['ExpirationDate'] || $expiresVo['CouponTitle']!=$couponData['CouponTitle']  || $expiresVo['CouponDesc']!=$couponData['CouponDesc'] || $expiresVo['CouponCodeUrl']!=$couponData['CouponCodeUrl'] || $expiresVo['type']!=$couponData['type']){
                        $sqlUp="update cp_competitor_store_coupon set ExpirationDate='{$couponData['ExpirationDate']}',CouponTitle='{$couponData['CouponTitle']}',CouponDesc='{$couponData['CouponDesc']}',CouponCodeUrl='{$couponData['CouponCodeUrl']}',type='{$couponData['type']}',IsUpdateCodeUrl='{$couponData['IsUpdateCodeUrl']}',LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$expiresVo['ID']}";
                        $db->query($sqlUp);
                    }
                    $couponOnMap[]=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']]['ID'];
                }
            }
            if($sqlIns!=$sqlInsPre){
                $sqlIns=substr($sqlIns,0,strlen($sqlIns)-1);
                $sqlIns.=";";
                $db->query($sqlIns);
            }

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
        // //46  end  http://coupons.ca/tanda
    }else if($row['CompetitorId']==47){
        //47 start     http://coupons.ca/tanda
        preg_match_all("/href=\"(http:\/\/www.kortingscode.nl\/[^\"\/]+)\"/i", $htmlContent, $matchUrl,PREG_SET_ORDER);
        $sqlInsUrl = $sqlInsUrlPre = "insert ignore into cp_temp_competitor_store(StoreUrl,CompetitorId,AddTime) values ";

        if (!empty($matchUrl)) {
            foreach ($matchUrl as $url) {
                $sqlInsUrl .= "('" . $url[1] . "',{$row['CompetitorId']},'" . date("Y-m-d H:i:s") . "'),";
            }
            if ($sqlInsUrl != $sqlInsUrlPre) {
                $sqlInsUrl = substr($sqlInsUrl, 0, strlen($sqlInsUrl) - 1);
                $sqlInsUrl .= ";";

                $db->query($sqlInsUrl);
            }
        }

        $cs_data_arr = array();

//MetaTitle
        preg_match_all("/<title>(.*?)<\/title>/", $htmlContent, $matchTitle, PREG_SET_ORDER);
        $cs_data_arr['MetaTitle'] = empty($matchTitle) ? "" : $matchTitle[0][1];
        //keywords No Meta keywords
        $cs_data_arr['MetaKeywords'] = '';
        //meta description
        preg_match_all("/<meta name=\"description\" content=\"(.*?)\"/", $htmlContent, $matchMetaDesc, PREG_SET_ORDER);
        $cs_data_arr['MetaDescription'] = empty($matchMetaDesc) ? "" : addslashes( substr(trim(strip_tags($matchMetaDesc[0][1])),0,250));
        //描述
        preg_match_all("/<h2>(.*?)<\/h2>/", $htmlContent, $matchDesc, PREG_SET_ORDER);
        $cs_data_arr['Description'] = empty($matchDesc)?'':trim(del_br_space_by_str(strip_tags($matchDesc[0][1])));
        //H1
        preg_match_all("/<h1>(.*?)<\/h1>/", $htmlContent, $matchH1, PREG_SET_ORDER);
        $cs_data_arr['H1'] = empty($matchH1) ? "" : trim(strip_tags($matchH1[0][1]));
        //Merchant Go Url
        preg_match_all("/href=\"(.*?)\"><img class=\"radiusImg\"/", $htmlContent, $matchGo);
        $cs_data_arr['MerchantGoUrl'] = empty($matchGo[0]) ? "" :$matchGo[1][0];
        //Screen Img
        preg_match_all("/class=\"radiusImg\"\s+?src=\"([^\"]+)\"/",$htmlContent,$matchImg);
        $cs_data_arr['ScreenImg'] = empty($matchImg[0]) ? "" :$matchImg[1][0];


        $sql = $pre_sql = "update cp_competitor_store set ";
        $empty_log_str = "";
        foreach ($cs_data_arr as $key => $val) {
            if (empty($val)) {
                $empty_log_str .= " {$key} --empty --";
            }
            if (strlen($val) > 100) {
                $val = del_br_space_by_str($val);
            }
            if (strripos($key, "meta") !== false) {
                $val = substr($val, 0, 250);
            }
            $sql .= " {$key} = '" . addslashes($val) . "' , ";
        }

        if($sql != $pre_sql ){
            $sql.=" LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$row['CompetitorStoreId']}";
            $GLOBALS['db']->query($sql);
        }

        $rank=0;
        $sqlIns=$sqlInsPre="INSERT ignore into cp_competitor_store_coupon (CompetitorStoreId,CompetitorId,CouponID,CouponTitle,GoUrl,CouponCode,ExpirationDate,CouponDesc,Used,Country,MaybeValid,AddTime,type,CouponCodeUrl,IsUpdateCodeUrl) values ";
//    coupons 数据
        preg_match_all("/<section class=\"section shoppage\">([\s\S]+?)<\/section>/", $htmlContent, $matchValidCoupon);

        if (!empty($matchValidCoupon[0])) {
            $matchCoupon=explode('<article',$matchValidCoupon[1][0]);
            for($i=1;$i<count($matchCoupon);$i++){
                $couponHtml=$matchCoupon[$i];

                $couponData['MaybeValid'] = 1;
                $couponData['Country'] = "NL";
                $couponData['CouponID'] =$rank;
                $couponData['CouponTitle']='';
                $couponData['CouponDesc']='';
                $couponData['GoUrl']='';
                $couponData['type']='deal';
                $couponData['Used']='';
                $couponData['CouponRestriction']='';
                $couponData['ExpirationDate']='';
                $couponData['CouponCodeUrl'] = "";
                $couponData['CouponCode']='';
                $couponData['IsUpdateCodeUrl']=0;

                preg_match_all("/expired-deal\"/",$couponHtml,$matchActive);
                if(!empty($matchActive[0])){
                    continue;
                }

                //couponId
                preg_match_all("/id=\"(\d+)\"/",$couponHtml,$matchCouponId);
                if(!empty($matchCouponId[0])){
                    $couponData['CouponID']=$matchCouponId[1][0];
                }

                //type && code
                preg_match_all("/offer-teaser-button kccode/", $couponHtml,$matchType);
                if(!empty($matchType[0])){
                    $couponData['IsUpdateCodeUrl']='1';
                    $couponData['type']='code';
                    $couponData['CouponCodeUrl']="http://www.kortingscode.nl/offer/offer-detail?id=".$couponData['CouponID'];
                }

                //title
                preg_match_all("/\"link clickout-title\">([\s\S]+?)<\/span>/",$couponHtml,$matchcoupontitle);
                $couponData['CouponTitle']= empty($matchcoupontitle[0])?'':trim(strip_tags($matchcoupontitle[1][0]));

                //gourl
                $couponData['GoUrl']='http://www.kortingscode.nl/out/offer/'.$couponData['CouponID'];

                //desc
                $couponData['CouponDesc']='';

                //有效期
                if(empty($couponData['ExpirationDate'])){
                    $couponData['ExpirationDate']='0000-00-00';
                }

                $rank++;
                if(!empty($couponData['CouponID']))
                    $couponRankMap[''.$couponData['CouponID']]=$rank;
                if(!isset($couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']])){
                    $sqlIns.="('{$row['CompetitorStoreId']}','{$row['CompetitorId']}','{$couponData['CouponID']}','{$couponData['CouponTitle']}','{$couponData['GoUrl']}','{$couponData['CouponCode']}','{$couponData['ExpirationDate']}','{$couponData['CouponDesc']}','{$couponData['Used']}','{$couponData['Country']}','{$couponData['MaybeValid']}','".date("Y-m-d H:i:s")."','{$couponData['type']}','{$couponData['CouponCodeUrl']}','{$couponData['IsUpdateCodeUrl']}'),";
                }else{
                    $expiresVo=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']];
                    if($expiresVo['ExpirationDate']!=$couponData['ExpirationDate'] || $expiresVo['CouponTitle']!=$couponData['CouponTitle']  || $expiresVo['CouponDesc']!=$couponData['CouponDesc'] || $expiresVo['CouponCodeUrl']!=$couponData['CouponCodeUrl'] || $expiresVo['type']!=$couponData['type']){
                        $sqlUp="update cp_competitor_store_coupon set ExpirationDate='{$couponData['ExpirationDate']}',CouponTitle='{$couponData['CouponTitle']}',CouponDesc='{$couponData['CouponDesc']}',CouponCodeUrl='{$couponData['CouponCodeUrl']}',type='{$couponData['type']}',IsUpdateCodeUrl='{$couponData['IsUpdateCodeUrl']}',LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$expiresVo['ID']}";
                        $db->query($sqlUp);
                    }
                    $couponOnMap[]=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']]['ID'];
                }
            }
            if($sqlIns!=$sqlInsPre){
                $sqlIns=substr($sqlIns,0,strlen($sqlIns)-1);
                $sqlIns.=";";
                $db->query($sqlIns);
            }

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
        // //47  end  http://coupons.ca/tanda
    }else if($row['CompetitorId']==48){
        //48 start     http://coupons.ca/tanda
        preg_match_all("/href=\"(\/[^\"\/]+)\"/i", $htmlContent, $matchUrl,PREG_SET_ORDER);
        $sqlInsUrl = $sqlInsUrlPre = "insert ignore into cp_temp_competitor_store(StoreUrl,CompetitorId,AddTime) values ";

        if (!empty($matchUrl)) {
            foreach ($matchUrl as $url) {
                $sqlInsUrl .= "('". 'http://www.actiepagina.nl'.$url[1] . "',{$row['CompetitorId']},'" . date("Y-m-d H:i:s") . "'),";
            }
            if ($sqlInsUrl != $sqlInsUrlPre) {
                $sqlInsUrl = substr($sqlInsUrl, 0, strlen($sqlInsUrl) - 1);
                $sqlInsUrl .= ";";

                $db->query($sqlInsUrl);
            }
        }

        $cs_data_arr = array();

//MetaTitle
        preg_match_all("/<title>(.*?)<\/title>/", $htmlContent, $matchTitle, PREG_SET_ORDER);
        $cs_data_arr['MetaTitle'] = empty($matchTitle) ? "" : $matchTitle[0][1];
        //keywords No Meta keywords
        $cs_data_arr['MetaKeywords'] = '';
        //meta description
        preg_match_all("/<meta name=\"description\" content=\"(.*?)\"/", $htmlContent, $matchMetaDesc, PREG_SET_ORDER);
        $cs_data_arr['MetaDescription'] = empty($matchMetaDesc) ? "" : addslashes( substr(trim(strip_tags($matchMetaDesc[0][1])),0,250));
        //描述
        preg_match_all("/class=\"heading\">([\s\S]+?)<\/p>/", $htmlContent, $matchDesc, PREG_SET_ORDER);
        $cs_data_arr['Description'] = empty($matchDesc)?'':trim(del_br_space_by_str(strip_tags($matchDesc[0][1])));
        //H1
        preg_match_all("/<h1 class=\"merchant-title\">(.*?)<\/h1>/", $htmlContent, $matchH1, PREG_SET_ORDER);
        $cs_data_arr['H1'] = empty($matchH1) ? "" : trim(strip_tags($matchH1[0][1]));
        //Merchant Go Url
        preg_match_all("/\"merchant-logo-title merchant-logo visible-xs\">\s+?<a href=\"(.*?)\"/", $htmlContent, $matchGo);
        $cs_data_arr['MerchantGoUrl'] = empty($matchGo[0]) ? "" :'http://www.actiepagina.nl'.$matchGo[1][0];
        //Screen Img
        preg_match_all("/class=\"img-responsive\" src=\"(.*?)\"/",$htmlContent,$matchImg);
        $cs_data_arr['ScreenImg'] = empty($matchImg[0]) ? "" :$matchImg[1][0];


        $sql = $pre_sql = "update cp_competitor_store set ";
        $empty_log_str = "";
        foreach ($cs_data_arr as $key => $val) {
            if (empty($val)) {
                $empty_log_str .= " {$key} --empty --";
            }
            if (strlen($val) > 100) {
                $val = del_br_space_by_str($val);
            }
            if (strripos($key, "meta") !== false) {
                $val = substr($val, 0, 250);
            }
            $sql .= " {$key} = '" . addslashes($val) . "' , ";
        }

        if($sql != $pre_sql ){
            $sql.=" LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$row['CompetitorStoreId']}";
            $GLOBALS['db']->query($sql);
        }

        $rank=0;
        $sqlIns=$sqlInsPre="INSERT ignore into cp_competitor_store_coupon (CompetitorStoreId,CompetitorId,CouponID,CouponTitle,GoUrl,CouponCode,ExpirationDate,CouponDesc,Used,Country,MaybeValid,AddTime,type,CouponCodeUrl,IsUpdateCodeUrl) values ";
//    coupons 数据
        preg_match_all("/class=\"merchant-coupons\">([\s\S]+?)<div class=\"merchant-description\"/", $htmlContent, $matchValidCoupon);

        if (!empty($matchValidCoupon[0])) {
            $matchCoupon=explode('class="element-coupon',$matchValidCoupon[1][0]);
            for($i=1;$i<count($matchCoupon);$i++){
                $couponHtml=$matchCoupon[$i];

                $couponData['MaybeValid'] = 1;
                $couponData['Country'] = "NL";
                $couponData['CouponID'] =$rank;
                $couponData['CouponTitle']='';
                $couponData['CouponDesc']='';
                $couponData['GoUrl']='';
                $couponData['type']='deal';
                $couponData['Used']='';
                $couponData['CouponRestriction']='';
                $couponData['ExpirationDate']='';
                $couponData['CouponCodeUrl'] = "";
                $couponData['CouponCode']='';
                $couponData['IsUpdateCodeUrl']=0;

                //couponId
                preg_match_all("/data-coupon=\"(\d+)\"/",$couponHtml,$matchCouponId);
                if(!empty($matchCouponId[0])){
                    $couponData['CouponID']=$matchCouponId[1][0];
                }

                //type && code
                preg_match_all("/class=\"coupon-code outclickable\"/", $couponHtml,$matchType);
                if(!empty($matchType[0])){
                    //类型code,暂时获取不了code
                }

                //title
                preg_match_all("/<h3>([\s\S]+?)<\/h3>/",$couponHtml,$matchcoupontitle);
                $couponData['CouponTitle']= empty($matchcoupontitle[0])?'':trim(strip_tags($matchcoupontitle[1][0]));

                //gourl
                $couponData['GoUrl']='http://www.actiepagina.nl/out/c'.$couponData['CouponID'];

                //desc
                preg_match_all("/class=\"more-description\">([\s\S]+?)<\//",$couponHtml,$matchCoupondesc);
                $couponData['CouponDesc']=empty($matchCoupondesc[0])?'':trim(strip_tags($matchCoupondesc[1][0]));

                //有效期
                if(empty($couponData['ExpirationDate'])){
                    $couponData['ExpirationDate']='0000-00-00';
                }

                $rank++;
                if(!empty($couponData['CouponID']))
                    $couponRankMap[''.$couponData['CouponID']]=$rank;
                if(!isset($couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']])){
                    $sqlIns.="('{$row['CompetitorStoreId']}','{$row['CompetitorId']}','{$couponData['CouponID']}','{$couponData['CouponTitle']}','{$couponData['GoUrl']}','{$couponData['CouponCode']}','{$couponData['ExpirationDate']}','{$couponData['CouponDesc']}','{$couponData['Used']}','{$couponData['Country']}','{$couponData['MaybeValid']}','".date("Y-m-d H:i:s")."','{$couponData['type']}','{$couponData['CouponCodeUrl']}','{$couponData['IsUpdateCodeUrl']}'),";
                }else{
                    $expiresVo=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']];
                    if($expiresVo['ExpirationDate']!=$couponData['ExpirationDate'] || $expiresVo['CouponTitle']!=$couponData['CouponTitle']  || $expiresVo['CouponDesc']!=$couponData['CouponDesc'] || $expiresVo['CouponCodeUrl']!=$couponData['CouponCodeUrl'] || $expiresVo['type']!=$couponData['type']){
                        $sqlUp="update cp_competitor_store_coupon set ExpirationDate='{$couponData['ExpirationDate']}',CouponTitle='{$couponData['CouponTitle']}',CouponDesc='{$couponData['CouponDesc']}',CouponCodeUrl='{$couponData['CouponCodeUrl']}',type='{$couponData['type']}',IsUpdateCodeUrl='{$couponData['IsUpdateCodeUrl']}',LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$expiresVo['ID']}";
                        $db->query($sqlUp);
                    }
                    $couponOnMap[]=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']]['ID'];
                }
            }
            if($sqlIns!=$sqlInsPre){
                $sqlIns=substr($sqlIns,0,strlen($sqlIns)-1);
                $sqlIns.=";";
                $db->query($sqlIns);
            }

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
        // //48  end  http://coupons.ca/tanda
    }else if($row['CompetitorId']==49){
        //49 start     http://coupons.ca/tanda
        preg_match_all("/href=\"(https:\/\/mrkortingscode.nl\/[^\/]+)\/\"/i", $htmlContent, $matchUrl,PREG_SET_ORDER);
        $sqlInsUrl = $sqlInsUrlPre = "insert ignore into cp_temp_competitor_store(StoreUrl,CompetitorId,AddTime) values ";

        if (!empty($matchUrl)) {
            foreach ($matchUrl as $url) {
                $sqlInsUrl .= "('".$url[1] . "',{$row['CompetitorId']},'" . date("Y-m-d H:i:s") . "'),";
            }
            if ($sqlInsUrl != $sqlInsUrlPre) {
                $sqlInsUrl = substr($sqlInsUrl, 0, strlen($sqlInsUrl) - 1);
                $sqlInsUrl .= ";";

                $db->query($sqlInsUrl);
            }
        }

        $cs_data_arr = array();

//MetaTitle
        preg_match_all("/<title>([^<]+)<\/title>/", $htmlContent, $matchTitle, PREG_SET_ORDER);
        $cs_data_arr['MetaTitle'] = empty($matchTitle) ? "" : $matchTitle[0][1];
        //keywords No Meta keywords
        $cs_data_arr['MetaKeywords'] = '';
        //meta description
        preg_match_all("/<meta content=\"([^\"]+)\" name=\"description\"/", $htmlContent, $matchMetaDesc, PREG_SET_ORDER);
        $cs_data_arr['MetaDescription'] = empty($matchMetaDesc) ? "" : addslashes( substr(trim(strip_tags($matchMetaDesc[0][1])),0,250));
        //描述
        preg_match_all("/class=\"gray-categorie-text\"(.*?)<\/h2>/", $htmlContent, $matchDesc, PREG_SET_ORDER);
        $cs_data_arr['Description'] = empty($matchDesc)?'':trim(del_br_space_by_str(strip_tags($matchDesc[0][1])));
//H1
        preg_match_all("/<h1 class=\"nmt\">(.*?)<\/h1>/", $htmlContent, $matchH1, PREG_SET_ORDER);
        $cs_data_arr['H1'] = empty($matchH1) ? "" : trim(strip_tags($matchH1[0][1]));
//Merchant Go Url
        preg_match_all("/data-track-affnet=\"[^\"]+\" href=\"\/visit\/([^\"]+)\"/", $htmlContent, $matchGo);
        $cs_data_arr['MerchantGoUrl'] = empty($matchGo[0]) ? "" :'https://mrkortingscode.nl/visit/'.$matchGo[1][0];
        //Screen Img
        preg_match_all("/src=\"(.*?)\" alt=\"\" class=\"img-responsive shop-logo\"/",$htmlContent,$matchImg);
        $cs_data_arr['ScreenImg'] = empty($matchImg[0]) ? "" :$matchImg[1][0];


        $sql = $pre_sql = "update cp_competitor_store set ";
        $empty_log_str = "";
        foreach ($cs_data_arr as $key => $val) {
            if (empty($val)) {
                $empty_log_str .= " {$key} --empty --";
            }
            if (strlen($val) > 100) {
                $val = del_br_space_by_str($val);
            }
            if (strripos($key, "meta") !== false) {
                $val = substr($val, 0, 250);
            }
            $sql .= " {$key} = '" . addslashes($val) . "' , ";
        }

        if($sql != $pre_sql ){
            $sql.=" LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$row['CompetitorStoreId']}";
            $GLOBALS['db']->query($sql);
        }

        $rank=0;
        $sqlIns=$sqlInsPre="INSERT ignore into cp_competitor_store_coupon (CompetitorStoreId,CompetitorId,CouponID,CouponTitle,GoUrl,CouponCode,ExpirationDate,CouponDesc,Used,Country,MaybeValid,AddTime,type,CouponCodeUrl,IsUpdateCodeUrl) values ";
//    coupons 数据
        preg_match_all("/id=\"categories\"([\s\S]+?)id=\"comments-header\"/", $htmlContent, $matchValidCoupon);

        if (!empty($matchValidCoupon[0])) {
            $matchCoupon=explode('<div id="offer',$matchValidCoupon[1][0]);
            for($i=1;$i<count($matchCoupon);$i++){
                $couponHtml=$matchCoupon[$i];

                $couponData['MaybeValid'] = 1;
                $couponData['Country'] = "NL";
                $couponData['CouponID'] =$rank;
                $couponData['CouponTitle']='';
                $couponData['CouponDesc']='';
                $couponData['GoUrl']='';
                $couponData['type']='deal';
                $couponData['Used']='';
                $couponData['CouponRestriction']='';
                $couponData['ExpirationDate']='';
                $couponData['CouponCodeUrl'] = "";
                $couponData['CouponCode']='';
                $couponData['IsUpdateCodeUrl']=0;

                //couponId
                preg_match_all("/id=\"offer-link-(\d+)\"/",$couponHtml,$matchCouponId);
                if(!empty($matchCouponId[0])){
                    $couponData['CouponID']=$matchCouponId[1][0];
                }

                //type && code
                preg_match_all("/class=\"code code-bordered\"/", $couponHtml,$matchType);
                preg_match_all("/data-current-href=\"(.*?)\"/",$couponHtml,$matchCodeUrl);
                if(!empty($matchType[0]) && !empty($matchCodeUrl)){

                    $couponData['IsUpdateCodeUrl']='1';
                    $couponData['type']='code';
                    $couponData['CouponCodeUrl']="https://mrkortingscode.nl".trim($matchCodeUrl[1][0]);
                }

                //title
                preg_match_all("/class=\"one-categorie-blue\">([\s\S]*?)<\/h3>/",$couponHtml,$matchcoupontitle);
                $couponData['CouponTitle']= empty($matchcoupontitle[0])?'':trim(strip_tags($matchcoupontitle[1][0]));

                //gourl
                preg_match_all("/data-track-affnet=\"[^\"]+\" href=\"(.*?)\"/",$couponHtml,$matchGo);
                $couponData['GoUrl']=empty($matchGo[0])?'':"https://mrkortingscode.nl".trim(strip_tags($matchGo[1][0]));

                //desc
                $couponData['CouponDesc']='';

                //有效期
                if(empty($couponData['ExpirationDate'])){
                    $couponData['ExpirationDate']='0000-00-00';
                }

                $rank++;
                if(!empty($couponData['CouponID']))
                    $couponRankMap[''.$couponData['CouponID']]=$rank;
                if(!isset($couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']])){
                    $sqlIns.="('{$row['CompetitorStoreId']}','{$row['CompetitorId']}','{$couponData['CouponID']}','{$couponData['CouponTitle']}','{$couponData['GoUrl']}','{$couponData['CouponCode']}','{$couponData['ExpirationDate']}','{$couponData['CouponDesc']}','{$couponData['Used']}','{$couponData['Country']}','{$couponData['MaybeValid']}','".date("Y-m-d H:i:s")."','{$couponData['type']}','{$couponData['CouponCodeUrl']}','{$couponData['IsUpdateCodeUrl']}'),";
                }else{
                    $expiresVo=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']];
                    if($expiresVo['ExpirationDate']!=$couponData['ExpirationDate'] || $expiresVo['CouponTitle']!=$couponData['CouponTitle']  || $expiresVo['CouponDesc']!=$couponData['CouponDesc'] || $expiresVo['CouponCodeUrl']!=$couponData['CouponCodeUrl'] || $expiresVo['type']!=$couponData['type']){
                        $sqlUp="update cp_competitor_store_coupon set ExpirationDate='{$couponData['ExpirationDate']}',CouponTitle='{$couponData['CouponTitle']}',CouponDesc='{$couponData['CouponDesc']}',CouponCodeUrl='{$couponData['CouponCodeUrl']}',type='{$couponData['type']}',IsUpdateCodeUrl='{$couponData['IsUpdateCodeUrl']}',LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$expiresVo['ID']}";
                        $db->query($sqlUp);
                    }
                    $couponOnMap[]=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']]['ID'];
                }
            }
            if($sqlIns!=$sqlInsPre){
                $sqlIns=substr($sqlIns,0,strlen($sqlIns)-1);
                $sqlIns.=";";
                $db->query($sqlIns);
            }

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
        // //49  end  http://coupons.ca/tanda
    }else if($row['CompetitorId']==50){
        //50 start     http://coupons.ca/tanda
        preg_match_all("/href=\"(\/descuentos-[^\"]*?.html)\"/i", $htmlContent, $matchUrl,PREG_SET_ORDER);
        $sqlInsUrl = $sqlInsUrlPre = "insert ignore into cp_temp_competitor_store(StoreUrl,CompetitorId,AddTime) values ";

        if (!empty($matchUrl)) {
            foreach ($matchUrl as $url) {
                $sqlInsUrl .= "('".'https://cupon.es'.$url[1] . "',{$row['CompetitorId']},'" . date("Y-m-d H:i:s") . "'),";
            }
            if ($sqlInsUrl != $sqlInsUrlPre) {
                $sqlInsUrl = substr($sqlInsUrl, 0, strlen($sqlInsUrl) - 1);
                $sqlInsUrl .= ";";

                $db->query($sqlInsUrl);
            }
        }

        $cs_data_arr = array();

//MetaTitle
        preg_match_all("/<title>([^<]+)<\/title>/", $htmlContent, $matchTitle, PREG_SET_ORDER);
        $cs_data_arr['MetaTitle'] = empty($matchTitle) ? "" : $matchTitle[0][1];
        //keywords No Meta keywords
        preg_match_all("/<meta name=\"keywords\" content=\"(.*?)\"/", $htmlContent, $matchkey, PREG_SET_ORDER);
        $cs_data_arr['MetaKeywords'] =empty($matchkey) ? "" : $matchkey[0][1];
        //meta description
        preg_match_all("/<meta name=\"description\" content=\"([^\"]+)\"/", $htmlContent, $matchMetaDesc, PREG_SET_ORDER);
        $cs_data_arr['MetaDescription'] = empty($matchMetaDesc) ? "" : addslashes( substr(trim(strip_tags($matchMetaDesc[0][1])),0,250));
        //描述
        preg_match_all("/itemprop=\"description\" class=\"\">\s+?<span class=\"text-truncated\">([\s\S]+?)<\/span>/", $htmlContent, $matchDesc, PREG_SET_ORDER);
        $cs_data_arr['Description'] = empty($matchDesc)?'':trim(del_br_space_by_str(strip_tags($matchDesc[0][1])));
//H1
        preg_match_all("/<h1 class=\"page-title\" itemprop=\"alternateName\">(.*?)<\/h1>/", $htmlContent, $matchH1, PREG_SET_ORDER);
        $cs_data_arr['H1'] = empty($matchH1) ? "" : trim(strip_tags($matchH1[0][1]));
//Merchant Go Url
        preg_match_all("/class=\"shop-header-logo\">\s+<a class=\"fallback_link\" data-shop=\"[^\"]+\" href=\"([^\"]+)\"/", $htmlContent, $matchGo);
        $cs_data_arr['MerchantGoUrl'] = empty($matchGo[0]) ? "" :'https://cupon.es/'.$matchGo[1][0];
        //Screen Img
        preg_match_all("/itemprop=\"logo\" src=\"(.*?)\"[^\>]+>\s+<\/span>/",$htmlContent,$matchImg);
        $cs_data_arr['ScreenImg'] = empty($matchImg[0]) ? "" :'http:'.$matchImg[1][0];


        $sql = $pre_sql = "update cp_competitor_store set ";
        $empty_log_str = "";
        foreach ($cs_data_arr as $key => $val) {
            if (empty($val)) {
                $empty_log_str .= " {$key} --empty --";
            }
            if (strlen($val) > 100) {
                $val = del_br_space_by_str($val);
            }
            if (strripos($key, "meta") !== false) {
                $val = substr($val, 0, 250);
            }
            $sql .= " {$key} = '" . addslashes($val) . "' , ";
        }

        if($sql != $pre_sql ){
            $sql.=" LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$row['CompetitorStoreId']}";
            $GLOBALS['db']->query($sql);
        }

        $rank=0;
        $sqlIns=$sqlInsPre="INSERT ignore into cp_competitor_store_coupon (CompetitorStoreId,CompetitorId,CouponID,CouponTitle,GoUrl,CouponCode,ExpirationDate,CouponDesc,Used,Country,MaybeValid,AddTime,type,CouponCodeUrl,IsUpdateCodeUrl) values ";
//    coupons 数据
        preg_match_all("/<ul class=\"list-unstyled\"[^<]+>([\s\S]+?)list-unstyled/i", $htmlContent, $matchValidCoupon);

        if (!empty($matchValidCoupon[0])) {
            $matchCoupon=explode('<li id="coupon',$matchValidCoupon[1][0]);
            for($i=1;$i<count($matchCoupon);$i++){
                $couponHtml=$matchCoupon[$i];

                $couponData['MaybeValid'] = 1;
                $couponData['Country'] = "ES";
                $couponData['CouponID'] =$rank;
                $couponData['CouponTitle']='';
                $couponData['CouponDesc']='';
                $couponData['GoUrl']='';
                $couponData['type']='deal';
                $couponData['Used']='';
                $couponData['CouponRestriction']='';
                $couponData['ExpirationDate']='';
                $couponData['CouponCodeUrl'] = "";
                $couponData['CouponCode']='';
                $couponData['IsUpdateCodeUrl']=0;

                //couponId
                preg_match_all("/data-coupon-id=\"(\d+)\"/",$couponHtml,$matchCouponId);
                if(!empty($matchCouponId[0])){
                    $couponData['CouponID']=$matchCouponId[1][0];
                }

                //type && code
                preg_match_all("/class=\"btn btn-cloud\"/", $couponHtml,$matchType);
//            preg_match_all("/data-current-href=\"(.*?)\"/",$couponHtml,$matchCodeUrl);
                if(!empty($matchType[0])){

                    $couponData['IsUpdateCodeUrl']='1';
                    $couponData['type']='code';
                    $couponData['CouponCodeUrl']='https://cupon.es/modals/coupon_clickout?id='.$couponData['CouponID'];
                }

                //title
                preg_match_all("/class=\"coupon-title\">([\s\S]*?)<\/h3>/",$couponHtml,$matchcoupontitle);
                $couponData['CouponTitle']= empty($matchcoupontitle[0])?'':trim(strip_tags($matchcoupontitle[1][0]));

                //gourl
                preg_match_all("/data-coupon-url=\"(\/.*?)\"/",$couponHtml,$matchGo);
                $couponData['GoUrl']=empty($matchGo[0])?'':"https://cupon.es".trim(strip_tags($matchGo[1][0]));

                //desc
                preg_match_all("/class=\"text-truncated\">(.*?)<\/span>/",$couponHtml,$matchCoupondesc);
                $couponData['CouponDesc']=empty($matchCoupondesc[0])?'':trim(strip_tags($matchCoupondesc[1][0]));
//            $couponData['CouponDesc']='';

                //有效期
                preg_match_all("/data-time=\"(.*?)\"/",$couponHtml,$coupondate);
                if(!empty($coupondate[0])){
                    $couponData['ExpirationDate']=date('Y-m-d',strtotime($coupondate[1][0]));
                }
                if(empty($couponData['ExpirationDate'])){
                    $couponData['ExpirationDate']='0000-00-00';
                }

                $rank++;
                if(!empty($couponData['CouponID']))
                    $couponRankMap[''.$couponData['CouponID']]=$rank;
                if(!isset($couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']])){
                    $sqlIns.="('{$row['CompetitorStoreId']}','{$row['CompetitorId']}','{$couponData['CouponID']}','{$couponData['CouponTitle']}','{$couponData['GoUrl']}','{$couponData['CouponCode']}','{$couponData['ExpirationDate']}','{$couponData['CouponDesc']}','{$couponData['Used']}','{$couponData['Country']}','{$couponData['MaybeValid']}','".date("Y-m-d H:i:s")."','{$couponData['type']}','{$couponData['CouponCodeUrl']}','{$couponData['IsUpdateCodeUrl']}'),";
                }else{
                    $expiresVo=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']];
                    if($expiresVo['ExpirationDate']!=$couponData['ExpirationDate'] || $expiresVo['CouponTitle']!=$couponData['CouponTitle']  || $expiresVo['CouponDesc']!=$couponData['CouponDesc'] || $expiresVo['CouponCodeUrl']!=$couponData['CouponCodeUrl'] || $expiresVo['type']!=$couponData['type']){
                        $sqlUp="update cp_competitor_store_coupon set ExpirationDate='{$couponData['ExpirationDate']}',CouponTitle='{$couponData['CouponTitle']}',CouponDesc='{$couponData['CouponDesc']}',CouponCodeUrl='{$couponData['CouponCodeUrl']}',type='{$couponData['type']}',IsUpdateCodeUrl='{$couponData['IsUpdateCodeUrl']}',LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$expiresVo['ID']}";
                        $db->query($sqlUp);
                    }
                    $couponOnMap[]=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']]['ID'];
                }
            }
            if($sqlIns!=$sqlInsPre){
                $sqlIns=substr($sqlIns,0,strlen($sqlIns)-1);
                $sqlIns.=";";
                $db->query($sqlIns);
            }

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
        // //50  end  http://coupons.ca/tanda
    }else if($row['CompetitorId']==51){
        //51 start     http://coupons.ca/tanda
        preg_match_all("/href=\"(\/[\w]+)\"/i", $htmlContent, $matchUrl,PREG_SET_ORDER);
        $sqlInsUrl = $sqlInsUrlPre = "insert ignore into cp_temp_competitor_store(StoreUrl,CompetitorId,AddTime) values ";

        if (!empty($matchUrl)) {
            foreach ($matchUrl as $url) {
                $sqlInsUrl .= "('".'https://www.cupones.es'.$url[1] . "',{$row['CompetitorId']},'" . date("Y-m-d H:i:s") . "'),";
            }
            if ($sqlInsUrl != $sqlInsUrlPre) {
                $sqlInsUrl = substr($sqlInsUrl, 0, strlen($sqlInsUrl) - 1);
                $sqlInsUrl .= ";";

                $db->query($sqlInsUrl);
            }
        }

        $cs_data_arr = array();

//MetaTitle
        preg_match_all("/<title>([^<]+)<\/title>/", $htmlContent, $matchTitle, PREG_SET_ORDER);
        $cs_data_arr['MetaTitle'] = empty($matchTitle) ? "" : $matchTitle[0][1];
        //keywords No Meta keywords
        preg_match_all("/<meta name=\"keywords\" content=\"(.*?)\"/", $htmlContent, $matchkey, PREG_SET_ORDER);
        $cs_data_arr['MetaKeywords'] =empty($matchkey) ? "" : $matchkey[0][1];
        //meta description
        preg_match_all("/<meta name=\"description\" content=\"([^\"]+)\"/", $htmlContent, $matchMetaDesc, PREG_SET_ORDER);
        $cs_data_arr['MetaDescription'] = empty($matchMetaDesc) ? "" : addslashes( substr(trim(strip_tags($matchMetaDesc[0][1])),0,250));
        //描述
        preg_match_all("/<div class=\"description\">([\s\S]+?)<\/div>/", $htmlContent, $matchDesc, PREG_SET_ORDER);
        $cs_data_arr['Description'] = empty($matchDesc)?'':trim(del_br_space_by_str(strip_tags($matchDesc[0][1])));
//H1
        preg_match_all("/<h1 class=\"h2\">(.*?)<\/h1>/", $htmlContent, $matchH1, PREG_SET_ORDER);
        $cs_data_arr['H1'] = empty($matchH1) ? "" : trim(strip_tags($matchH1[0][1]));
//Merchant Go Url
        preg_match_all("/data-redirect=\"(.*?)\"/", $htmlContent, $matchGo);
        $cs_data_arr['MerchantGoUrl'] = empty($matchGo[0]) ? "" :$matchGo[1][0];
        //Screen Img
        preg_match_all("/property=\"og:image\" content=\"(.*?)\"/",$htmlContent,$matchImg);
        $cs_data_arr['ScreenImg'] = empty($matchImg[0]) ? "" :$matchImg[1][0];


        $sql = $pre_sql = "update cp_competitor_store set ";
        $empty_log_str = "";
        foreach ($cs_data_arr as $key => $val) {
            if (empty($val)) {
                $empty_log_str .= " {$key} --empty --";
            }
            if (strlen($val) > 100) {
                $val = del_br_space_by_str($val);
            }
            if (strripos($key, "meta") !== false) {
                $val = substr($val, 0, 250);
            }
            $sql .= " {$key} = '" . addslashes($val) . "' , ";
        }

        if($sql != $pre_sql ){
            $sql.=" LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$row['CompetitorStoreId']}";
            $GLOBALS['db']->query($sql);
        }

        $rank=0;
        $sqlIns=$sqlInsPre="INSERT ignore into cp_competitor_store_coupon (CompetitorStoreId,CompetitorId,CouponID,CouponTitle,GoUrl,CouponCode,ExpirationDate,CouponDesc,Used,Country,MaybeValid,AddTime,type,CouponCodeUrl,IsUpdateCodeUrl) values ";
//    coupons 数据
        preg_match_all("/id=\"all-results\"([\s\S]+?)Coupons List -->/i", $htmlContent, $matchValidCoupon);

        if (!empty($matchValidCoupon[0])) {
            $matchCoupon=explode('<article',$matchValidCoupon[1][0]);
            for($i=1;$i<count($matchCoupon);$i++){
                $couponHtml=$matchCoupon[$i];

                $couponData['MaybeValid'] = 1;
                $couponData['Country'] = "ES";
                $couponData['CouponID'] =$rank;
                $couponData['CouponTitle']='';
                $couponData['CouponDesc']='';
                $couponData['GoUrl']='';
                $couponData['type']='deal';
                $couponData['Used']='';
                $couponData['CouponRestriction']='';
                $couponData['ExpirationDate']='';
                $couponData['CouponCodeUrl'] = "";
                $couponData['CouponCode']='';
                $couponData['IsUpdateCodeUrl']=0;

                //type && code
                preg_match_all("/class=\"coupon-inner\">(.*?)<\/div>/", $couponHtml,$matchType);
               if(!empty($matchType[0])){

                    $couponData['IsUpdateCodeUrl']='1';
                    $couponData['type']='code';
                    $couponData['CouponCode']=empty($matchType[0])?'':addslashes(trim($matchType[1][0]));
               }

                //title
                preg_match_all("/<a rel=\"nofollow\" class=\"inline\"  href=\"[^\"]+\" data-redirect=\"(.*?)\">([\s\S]+?)<\/a>/",$couponHtml,$matchcoupontitle);
                $couponData['CouponTitle']= empty($matchcoupontitle[0])?'':trim(strip_tags($matchcoupontitle[2][0]));

                //gourl
                $couponData['GoUrl']=empty($matchcoupontitle[0])?'':trim(strip_tags($matchcoupontitle[1][0]));

                //desc
                $couponData['CouponDesc']='';

                //有效期
                if(empty($couponData['ExpirationDate'])){
                    $couponData['ExpirationDate']='0000-00-00';
                }

                $rank++;
                if(!empty($couponData['CouponID']))
                    $couponRankMap[''.$couponData['CouponID']]=$rank;
                if(!isset($couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']])){
                    $sqlIns.="('{$row['CompetitorStoreId']}','{$row['CompetitorId']}','{$couponData['CouponID']}','{$couponData['CouponTitle']}','{$couponData['GoUrl']}','{$couponData['CouponCode']}','{$couponData['ExpirationDate']}','{$couponData['CouponDesc']}','{$couponData['Used']}','{$couponData['Country']}','{$couponData['MaybeValid']}','".date("Y-m-d H:i:s")."','{$couponData['type']}','{$couponData['CouponCodeUrl']}','{$couponData['IsUpdateCodeUrl']}'),";
                }else{
                    $expiresVo=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']];
                    if($expiresVo['ExpirationDate']!=$couponData['ExpirationDate'] || $expiresVo['CouponTitle']!=$couponData['CouponTitle']  || $expiresVo['CouponDesc']!=$couponData['CouponDesc'] || $expiresVo['CouponCodeUrl']!=$couponData['CouponCodeUrl'] || $expiresVo['type']!=$couponData['type']){
                        $sqlUp="update cp_competitor_store_coupon set ExpirationDate='{$couponData['ExpirationDate']}',CouponTitle='{$couponData['CouponTitle']}',CouponDesc='{$couponData['CouponDesc']}',CouponCodeUrl='{$couponData['CouponCodeUrl']}',type='{$couponData['type']}',IsUpdateCodeUrl='{$couponData['IsUpdateCodeUrl']}',LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$expiresVo['ID']}";
                        $db->query($sqlUp);
                    }
                    $couponOnMap[]=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']]['ID'];
                }
            }
            if($sqlIns!=$sqlInsPre){
                $sqlIns=substr($sqlIns,0,strlen($sqlIns)-1);
                $sqlIns.=";";
                $db->query($sqlIns);
            }

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
        // //51  end  http://coupons.ca/tanda
    }else if($row['CompetitorId']==52){
        //52 start     http://coupons.ca/tanda
        preg_match_all("/href=\"(\/[\w\-]+)\"/i", $htmlContent, $matchUrl,PREG_SET_ORDER);
        $sqlInsUrl = $sqlInsUrlPre = "insert ignore into cp_temp_competitor_store(StoreUrl,CompetitorId,AddTime) values ";

        if (!empty($matchUrl)) {
            foreach ($matchUrl as $url) {
                $sqlInsUrl .= "('".'https://kupon.pl'.$url[1] . "',{$row['CompetitorId']},'" . date("Y-m-d H:i:s") . "'),";
            }
            if ($sqlInsUrl != $sqlInsUrlPre) {
                $sqlInsUrl = substr($sqlInsUrl, 0, strlen($sqlInsUrl) - 1);
                $sqlInsUrl .= ";";

                $db->query($sqlInsUrl);
            }
        }

        $cs_data_arr = array();

//MetaTitle
        preg_match_all("/<title>([^<]+)<\/title>/", $htmlContent, $matchTitle, PREG_SET_ORDER);
        $cs_data_arr['MetaTitle'] = empty($matchTitle) ? "" : $matchTitle[0][1];
        //keywords No Meta keywords
        preg_match_all("/<meta name=\"keywords\" content=\"(.*?)\"/", $htmlContent, $matchkey, PREG_SET_ORDER);
        $cs_data_arr['MetaKeywords'] =empty($matchkey) ? "" : $matchkey[0][1];
        //meta description
        preg_match_all("/<meta name=\"description\" content=\"([^\"]+)\"/", $htmlContent, $matchMetaDesc, PREG_SET_ORDER);
        $cs_data_arr['MetaDescription'] = empty($matchMetaDesc) ? "" : addslashes( substr(trim(strip_tags($matchMetaDesc[0][1])),0,250));
        //描述
        preg_match_all("/itemprop=\"description\" class=\"\">\s+?<span class=\"text-truncated\">([\s\S]+?)<\/span>/", $htmlContent, $matchDesc, PREG_SET_ORDER);
        $cs_data_arr['Description'] = empty($matchDesc)?'':trim(del_br_space_by_str(strip_tags($matchDesc[0][1])));
//H1
        preg_match_all("/<h1 class=\"page-title\" itemprop=\"alternateName\">(.*?)<\/h1>/", $htmlContent, $matchH1, PREG_SET_ORDER);
        $cs_data_arr['H1'] = empty($matchH1) ? "" : trim(strip_tags($matchH1[0][1]));
//Merchant Go Url
        preg_match_all("/class=\"shop-header-logo\">\s+<a class=\"fallback_link\" data-shop=\"[^\"]+\" href=\"([^\"]+)\"/", $htmlContent, $matchGo);
        $cs_data_arr['MerchantGoUrl'] = empty($matchGo[0]) ? "" :'https://kupon.pl'.$matchGo[1][0];
        //Screen Img
        preg_match_all("/itemprop=\"logo\" src=\"(.*?)\"[^\>]+>\s+<\/span>/",$htmlContent,$matchImg);
        $cs_data_arr['ScreenImg'] = empty($matchImg[0]) ? "" :'http:'.$matchImg[1][0];


        $sql = $pre_sql = "update cp_competitor_store set ";
        $empty_log_str = "";
        foreach ($cs_data_arr as $key => $val) {
            if (empty($val)) {
                $empty_log_str .= " {$key} --empty --";
            }
            if (strlen($val) > 100) {
                $val = del_br_space_by_str($val);
            }
            if (strripos($key, "meta") !== false) {
                $val = substr($val, 0, 250);
            }
            $sql .= " {$key} = '" . addslashes($val) . "' , ";
        }

        if($sql != $pre_sql ){
            $sql.=" LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$row['CompetitorStoreId']}";
            $GLOBALS['db']->query($sql);
        }

        $rank=0;
        $sqlIns=$sqlInsPre="INSERT ignore into cp_competitor_store_coupon (CompetitorStoreId,CompetitorId,CouponID,CouponTitle,GoUrl,CouponCode,ExpirationDate,CouponDesc,Used,Country,MaybeValid,AddTime,type,CouponCodeUrl,IsUpdateCodeUrl) values ";
//    coupons 数据
        preg_match_all("/<ul class=\"list-unstyled\"[^<]+>([\s\S]+?)list-unstyled/i", $htmlContent, $matchValidCoupon);

        if (!empty($matchValidCoupon[0])) {
            $matchCoupon=explode('<li id="coupon',$matchValidCoupon[1][0]);
            for($i=1;$i<count($matchCoupon);$i++){
                $couponHtml=$matchCoupon[$i];

                $couponData['MaybeValid'] = 1;
                $couponData['Country'] = "PL";
                $couponData['CouponID'] =$rank;
                $couponData['CouponTitle']='';
                $couponData['CouponDesc']='';
                $couponData['GoUrl']='';
                $couponData['type']='deal';
                $couponData['Used']='';
                $couponData['CouponRestriction']='';
                $couponData['ExpirationDate']='';
                $couponData['CouponCodeUrl'] = "";
                $couponData['CouponCode']='';
                $couponData['IsUpdateCodeUrl']=0;

                //couponId
                preg_match_all("/data-coupon-id=\"(\d+)\"/",$couponHtml,$matchCouponId);
                if(!empty($matchCouponId[0])){
                    $couponData['CouponID']=$matchCouponId[1][0];
                }

                //type && code
                preg_match_all("/class=\"btn btn-cloud\"/", $couponHtml,$matchType);
                if(!empty($matchType[0])){

                    $couponData['IsUpdateCodeUrl']='1';
                    $couponData['type']='code';
                    $couponData['CouponCodeUrl']='https://kupon.pl/modals/coupon_clickout?id='.$couponData['CouponID'];
                }

                //title
                preg_match_all("/class=\"coupon-title\">([\s\S]*?)<\/h3>/",$couponHtml,$matchcoupontitle);
                $couponData['CouponTitle']= empty($matchcoupontitle[0])?'':trim(strip_tags($matchcoupontitle[1][0]));

                //gourl
                preg_match_all("/data-coupon-url=\"(\/.*?)\"/",$couponHtml,$matchGo);
                $couponData['GoUrl']=empty($matchGo[0])?'':"https://kupon.pl".trim(strip_tags($matchGo[1][0]));

                //desc
                preg_match_all("/class=\"text-truncated\">(.*?)<\/span>/",$couponHtml,$matchCoupondesc);
                $couponData['CouponDesc']=empty($matchCoupondesc[0])?'':trim(strip_tags($matchCoupondesc[1][0]));
//            $couponData['CouponDesc']='';

                //有效期
                preg_match_all("/data-time=\"(.*?)\"/",$couponHtml,$coupondate);
                if(!empty($coupondate[0])){
                    $couponData['ExpirationDate']=date('Y-m-d',strtotime($coupondate[1][0]));
                }
                //有效期
                if(empty($couponData['ExpirationDate'])){
                    $couponData['ExpirationDate']='0000-00-00';
                }

                $rank++;
                if(!empty($couponData['CouponID']))
                    $couponRankMap[''.$couponData['CouponID']]=$rank;
                if(!isset($couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']])){
                    $sqlIns.="('{$row['CompetitorStoreId']}','{$row['CompetitorId']}','{$couponData['CouponID']}','{$couponData['CouponTitle']}','{$couponData['GoUrl']}','{$couponData['CouponCode']}','{$couponData['ExpirationDate']}','{$couponData['CouponDesc']}','{$couponData['Used']}','{$couponData['Country']}','{$couponData['MaybeValid']}','".date("Y-m-d H:i:s")."','{$couponData['type']}','{$couponData['CouponCodeUrl']}','{$couponData['IsUpdateCodeUrl']}'),";
                }else{
                    $expiresVo=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']];
                    if($expiresVo['ExpirationDate']!=$couponData['ExpirationDate'] || $expiresVo['CouponTitle']!=$couponData['CouponTitle']  || $expiresVo['CouponDesc']!=$couponData['CouponDesc'] || $expiresVo['CouponCodeUrl']!=$couponData['CouponCodeUrl'] || $expiresVo['type']!=$couponData['type']){
                        $sqlUp="update cp_competitor_store_coupon set ExpirationDate='{$couponData['ExpirationDate']}',CouponTitle='{$couponData['CouponTitle']}',CouponDesc='{$couponData['CouponDesc']}',CouponCodeUrl='{$couponData['CouponCodeUrl']}',type='{$couponData['type']}',IsUpdateCodeUrl='{$couponData['IsUpdateCodeUrl']}',LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$expiresVo['ID']}";
                        $db->query($sqlUp);
                    }
                    $couponOnMap[]=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']]['ID'];
                }
            }
            if($sqlIns!=$sqlInsPre){
                $sqlIns=substr($sqlIns,0,strlen($sqlIns)-1);
                $sqlIns.=";";
                $db->query($sqlIns);
            }

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
        // //52  end  http://coupons.ca/tanda
    }else if($row['CompetitorId']==53){
        //53 start     http://coupons.ca/tanda
        $cs_data_arr = array();

//MetaTitle
        preg_match_all("/<title>([^<]+)<\/title>/", $htmlContent, $matchTitle, PREG_SET_ORDER);
        $cs_data_arr['MetaTitle'] = empty($matchTitle) ? "" : $matchTitle[0][1];
        //keywords No Meta keywords
        preg_match_all("/<meta name=\"keywords\" content=\"(.*?)\"/", $htmlContent, $matchkey, PREG_SET_ORDER);
        $cs_data_arr['MetaKeywords'] =empty($matchkey) ? "" : $matchkey[0][1];
        //meta description
        preg_match_all("/<meta name=\"description\" content=\"([^\"]+)\"/", $htmlContent, $matchMetaDesc, PREG_SET_ORDER);
        $cs_data_arr['MetaDescription'] = empty($matchMetaDesc) ? "" : addslashes( substr(trim(strip_tags($matchMetaDesc[0][1])),0,250));
        //描述
        preg_match_all("/class=\"brand-description__text\">([\s\S]+?)<\/div>/", $htmlContent, $matchDesc, PREG_SET_ORDER);
        $cs_data_arr['Description'] = empty($matchDesc)?'':trim(del_br_space_by_str(strip_tags($matchDesc[0][1])));
//H1
        preg_match_all("/<h1>([\S\s]*?)<\/h1>/", $htmlContent, $matchH1, PREG_SET_ORDER);
        $cs_data_arr['H1'] = empty($matchH1) ? "" : trim(strip_tags($matchH1[0][1]));
//Merchant Go Url
        preg_match_all("/data-id=\"(\d+)\"/", $htmlContent, $matchGo);
        $cs_data_arr['MerchantGoUrl'] = empty($matchGo[0]) ? "" :'https://www.qpony.pl/redirect/'.$matchGo[1][0];
        //Screen Img
        preg_match_all("/class=\"brand-description__logo__image\" src=\"(.*?)\"/",$htmlContent,$matchImg);
        $cs_data_arr['ScreenImg'] = empty($matchImg[0]) ? "" :$matchImg[1][0];

        $sql = $pre_sql = "update cp_competitor_store set ";
        $empty_log_str = "";
        foreach ($cs_data_arr as $key => $val) {
            if (empty($val)) {
                $empty_log_str .= " {$key} --empty --";
            }
            if (strlen($val) > 100) {
                $val = del_br_space_by_str($val);
            }
            if (strripos($key, "meta") !== false) {
                $val = substr($val, 0, 250);
            }
            $sql .= " {$key} = '" . addslashes($val) . "' , ";
        }

        if($sql != $pre_sql ){
            $sql.=" LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$row['CompetitorStoreId']}";
            $GLOBALS['db']->query($sql);
        }

        $rank=0;
        $sqlIns=$sqlInsPre="INSERT ignore into cp_competitor_store_coupon (CompetitorStoreId,CompetitorId,CouponID,CouponTitle,GoUrl,CouponCode,ExpirationDate,CouponDesc,Used,Country,MaybeValid,AddTime,type,CouponCodeUrl,IsUpdateCodeUrl) values ";
//    coupons 数据
        preg_match_all("/class=\"section__qpons\">([\s\S]+?)class=\"headline headline--brand\"/i", $htmlContent, $matchValidCoupon);

        if (!empty($matchValidCoupon[0])) {
            $matchCoupon=explode('class="qpon-horizontal"',$matchValidCoupon[1][0]);

            for($i=1;$i<count($matchCoupon);$i++){
                $couponHtml=$matchCoupon[$i];

                $couponData['MaybeValid'] = 1;
                $couponData['Country'] = "PL";
                $couponData['CouponID'] =$rank;
                $couponData['CouponTitle']='';
                $couponData['CouponDesc']='';
                $couponData['GoUrl']='';
                $couponData['type']='deal';
                $couponData['Used']='';
                $couponData['CouponRestriction']='';
                $couponData['ExpirationDate']='';
                $couponData['CouponCodeUrl'] = "";
                $couponData['CouponCode']='';
                $couponData['IsUpdateCodeUrl']=0;

                //couponId
                preg_match_all("/data-id=\"(\d+)\"/",$couponHtml,$matchCouponId);
                if(!empty($matchCouponId[0])){
                    $couponData['CouponID']=$matchCouponId[1][0];
                }

                //title
                preg_match_all("/class=\"qpon-horizontal__name\">(.*?)<\/div>/",$couponHtml,$matchcoupontitle);
                $couponData['CouponTitle']= empty($matchcoupontitle[0])?'':trim(strip_tags($matchcoupontitle[1][0]));

                //gourl
                $couponData['GoUrl']=empty($couponData['CouponID'])?'':'https://www.qpony.pl/redirect/'.$couponData['CouponID'];

                //desc
                $couponData['CouponDesc']='';

                //有效期
                preg_match_all("/Kupon ważny: do ([\d\.]+)</",$couponHtml,$coupondate);
                if(!empty($coupondate[0])){
                    $couponData['ExpirationDate']=date('Y-m-d',strtotime($coupondate[1][0]));
                }
                //有效期
                if(empty($couponData['ExpirationDate'])){
                    $couponData['ExpirationDate']='0000-00-00';
                }

                $rank++;
                if(!empty($couponData['CouponID']))
                    $couponRankMap[''.$couponData['CouponID']]=$rank;
                if(!isset($couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']])){
                    $sqlIns.="('{$row['CompetitorStoreId']}','{$row['CompetitorId']}','{$couponData['CouponID']}','{$couponData['CouponTitle']}','{$couponData['GoUrl']}','{$couponData['CouponCode']}','{$couponData['ExpirationDate']}','{$couponData['CouponDesc']}','{$couponData['Used']}','{$couponData['Country']}','{$couponData['MaybeValid']}','".date("Y-m-d H:i:s")."','{$couponData['type']}','{$couponData['CouponCodeUrl']}','{$couponData['IsUpdateCodeUrl']}'),";
                }else{
                    $expiresVo=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']];
                    if($expiresVo['ExpirationDate']!=$couponData['ExpirationDate'] || $expiresVo['CouponTitle']!=$couponData['CouponTitle']  || $expiresVo['CouponDesc']!=$couponData['CouponDesc'] || $expiresVo['CouponCodeUrl']!=$couponData['CouponCodeUrl'] || $expiresVo['type']!=$couponData['type']){
                        $sqlUp="update cp_competitor_store_coupon set ExpirationDate='{$couponData['ExpirationDate']}',CouponTitle='{$couponData['CouponTitle']}',CouponDesc='{$couponData['CouponDesc']}',CouponCodeUrl='{$couponData['CouponCodeUrl']}',type='{$couponData['type']}',IsUpdateCodeUrl='{$couponData['IsUpdateCodeUrl']}',LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$expiresVo['ID']}";
                        $db->query($sqlUp);
                    }
                    $couponOnMap[]=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']]['ID'];
                }
            }
            if($sqlIns!=$sqlInsPre){
                $sqlIns=substr($sqlIns,0,strlen($sqlIns)-1);
                $sqlIns.=";";
                $db->query($sqlIns);
            }

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
        // //53  end  http://coupons.ca/tanda
    }else if($row['CompetitorId']==54){
        //54 start     http://coupons.ca/tanda
        preg_match_all("/href=\"(http:\/\/www.promoszop.pl\/kupony-rabatowe\/[^\"]+)\"/i", $htmlContent, $matchUrl,PREG_SET_ORDER);
        $sqlInsUrl = $sqlInsUrlPre = "insert ignore into cp_temp_competitor_store(StoreUrl,CompetitorId,AddTime) values ";

        if (!empty($matchUrl)) {
            foreach ($matchUrl as $url) {
                $sqlInsUrl .= "('".$url[1] . "',{$row['CompetitorId']},'" . date("Y-m-d H:i:s") . "'),";
            }
            if ($sqlInsUrl != $sqlInsUrlPre) {
                $sqlInsUrl = substr($sqlInsUrl, 0, strlen($sqlInsUrl) - 1);
                $sqlInsUrl .= ";";

                $db->query($sqlInsUrl);
            }
        }

        $cs_data_arr = array();

//MetaTitle
        preg_match_all("/<title>([^<]+)<\/title>/", $htmlContent, $matchTitle, PREG_SET_ORDER);
        $cs_data_arr['MetaTitle'] = empty($matchTitle) ? "" : $matchTitle[0][1];
        //keywords No Meta keywords
        preg_match_all("/<meta name=\"keywords\" content=\"(.*?)\"/", $htmlContent, $matchkey, PREG_SET_ORDER);
        $cs_data_arr['MetaKeywords'] =empty($matchkey) ? "" : $matchkey[0][1];
        //meta description
        preg_match_all("/<meta name=\"description\" itemprop=\"description\" content=\"([^\"]+)\"/", $htmlContent, $matchMetaDesc, PREG_SET_ORDER);
        $cs_data_arr['MetaDescription'] = empty($matchMetaDesc) ? "" : addslashes( substr(trim(strip_tags($matchMetaDesc[0][1])),0,250));
        //描述
        preg_match_all("/style=\"text-align: justify;\">([\s\S]+?)<\/p>\s+?<ul/", $htmlContent, $matchDesc, PREG_SET_ORDER);
        $cs_data_arr['Description'] = empty($matchDesc)?'':trim(del_br_space_by_str(strip_tags($matchDesc[0][1])));
//H1
        preg_match_all("/<h1>([\S\s]*?)<\/h1>/", $htmlContent, $matchH1, PREG_SET_ORDER);
        $cs_data_arr['H1'] = empty($matchH1) ? "" : trim(strip_tags($matchH1[0][1]));
//Merchant Go Url
        preg_match_all("/class=\"btn-neutral btn\" rel=\"nofollow\" target=\"_blank\" href=\"(.*?)\"/", $htmlContent, $matchGo);
        $cs_data_arr['MerchantGoUrl'] = empty($matchGo[0]) ? "" :$matchGo[1][0];
        //Screen Img
        preg_match_all("/class=\"span2\">\s+?<img src=\"(.*?)\" alt=\"[^\"]+\" \/>/",$htmlContent,$matchImg);
        $cs_data_arr['ScreenImg'] = empty($matchImg[0]) ? "" :$matchImg[1][0];


        $sql = $pre_sql = "update cp_competitor_store set ";
        $empty_log_str = "";
        foreach ($cs_data_arr as $key => $val) {
            if (empty($val)) {
                $empty_log_str .= " {$key} --empty --";
            }
            if (strlen($val) > 100) {
                $val = del_br_space_by_str($val);
            }
            if (strripos($key, "meta") !== false) {
                $val = substr($val, 0, 250);
            }
            $sql .= " {$key} = '" . addslashes($val) . "' , ";
        }

        if($sql != $pre_sql ){
            $sql.=" LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$row['CompetitorStoreId']}";
            $GLOBALS['db']->query($sql);
        }

        $rank=0;
        $sqlIns=$sqlInsPre="INSERT ignore into cp_competitor_store_coupon (CompetitorStoreId,CompetitorId,CouponID,CouponTitle,GoUrl,CouponCode,ExpirationDate,CouponDesc,Used,Country,MaybeValid,AddTime,type,CouponCodeUrl,IsUpdateCodeUrl) values ";
//    coupons 数据
        preg_match_all("/<section class=\"section section-vouchers \">([\S\s]+?)<\/section>/i", $htmlContent, $matchValidCoupon);

        if (!empty($matchValidCoupon[0])) {

            $matchCoupon=explode('class="voucher-teaser js-touchpoint-wrap voucher-clickout',$matchValidCoupon[1][0]);

            for($i=1;$i<count($matchCoupon);$i++){
                $couponHtml=$matchCoupon[$i];

                $couponData['MaybeValid'] = 1;
                $couponData['Country'] = "PL";
                $couponData['CouponID'] =$rank;
                $couponData['CouponTitle']='';
                $couponData['CouponDesc']='';
                $couponData['GoUrl']='';
                $couponData['type']='deal';
                $couponData['Used']='';
                $couponData['CouponRestriction']='';
                $couponData['ExpirationDate']='';
                $couponData['CouponCodeUrl'] = "";
                $couponData['CouponCode']='';
                $couponData['IsUpdateCodeUrl']=0;

                //couponId
                //非数字

                //title
                preg_match_all("/<h3[^>]+>([\s\S]*?)<\/h3>/",$couponHtml,$matchcoupontitle);
                $couponData['CouponTitle']= empty($matchcoupontitle[0])?'':trim(strip_tags($matchcoupontitle[1][0]));

                //gourl
                preg_match_all("/href=\"(.*?)\" class=\"btn js-clickout/",$couponHtml,$matchGo);
                $couponData['GoUrl']=empty($matchGo[0])?'':trim(strip_tags($matchGo[1][0]));

                //desc
                $couponData['CouponDesc']='';

                //有效期
                if(empty($couponData['ExpirationDate'])){
                    $couponData['ExpirationDate']='0000-00-00';
                }

                $rank++;
                if(!empty($couponData['CouponID']))
                    $couponRankMap[''.$couponData['CouponID']]=$rank;
                if(!isset($couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']])){
                    $sqlIns.="('{$row['CompetitorStoreId']}','{$row['CompetitorId']}','{$couponData['CouponID']}','{$couponData['CouponTitle']}','{$couponData['GoUrl']}','{$couponData['CouponCode']}','{$couponData['ExpirationDate']}','{$couponData['CouponDesc']}','{$couponData['Used']}','{$couponData['Country']}','{$couponData['MaybeValid']}','".date("Y-m-d H:i:s")."','{$couponData['type']}','{$couponData['CouponCodeUrl']}','{$couponData['IsUpdateCodeUrl']}'),";
                }else{
                    $expiresVo=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']];
                    if($expiresVo['ExpirationDate']!=$couponData['ExpirationDate'] || $expiresVo['CouponTitle']!=$couponData['CouponTitle']  || $expiresVo['CouponDesc']!=$couponData['CouponDesc'] || $expiresVo['CouponCodeUrl']!=$couponData['CouponCodeUrl'] || $expiresVo['type']!=$couponData['type']){
                        $sqlUp="update cp_competitor_store_coupon set ExpirationDate='{$couponData['ExpirationDate']}',CouponTitle='{$couponData['CouponTitle']}',CouponDesc='{$couponData['CouponDesc']}',CouponCodeUrl='{$couponData['CouponCodeUrl']}',type='{$couponData['type']}',IsUpdateCodeUrl='{$couponData['IsUpdateCodeUrl']}',LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$expiresVo['ID']}";
                        $db->query($sqlUp);
                    }
                    $couponOnMap[]=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']]['ID'];
                }
            }
            if($sqlIns!=$sqlInsPre){
                $sqlIns=substr($sqlIns,0,strlen($sqlIns)-1);
                $sqlIns.=";";
                $db->query($sqlIns);
            }

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
        // //54  end  http://coupons.ca/tanda
    }else if($row['CompetitorId']==55){
        //55 start     http://coupons.ca/tanda
        preg_match_all("/href=\"(http:\/\/alerabat.com\/kod[y]*-promocyjn[ey]\/[^\"]+)\"/i", $htmlContent, $matchUrl,PREG_SET_ORDER);
        $sqlInsUrl = $sqlInsUrlPre = "insert ignore into cp_temp_competitor_store(StoreUrl,CompetitorId,AddTime) values ";

        if (!empty($matchUrl)) {
            foreach ($matchUrl as $url) {
                $sqlInsUrl .= "('".$url[1] . "',{$row['CompetitorId']},'" . date("Y-m-d H:i:s") . "'),";
            }
            if ($sqlInsUrl != $sqlInsUrlPre) {
                $sqlInsUrl = substr($sqlInsUrl, 0, strlen($sqlInsUrl) - 1);
                $sqlInsUrl .= ";";

                $db->query($sqlInsUrl);
            }
        }

        $cs_data_arr = array();

//MetaTitle
        preg_match_all("/<title>([^<]+)<\/title>/", $htmlContent, $matchTitle, PREG_SET_ORDER);
        $cs_data_arr['MetaTitle'] = empty($matchTitle) ? "" : $matchTitle[0][1];
        //keywords No Meta keywords
        preg_match_all("/<meta name=\"keywords\" content=\"(.*?)\"/", $htmlContent, $matchkey, PREG_SET_ORDER);
        $cs_data_arr['MetaKeywords'] =empty($matchkey) ? "" : $matchkey[0][1];
        //meta description
        preg_match_all("/<meta name=\"description\" content=\"([^\"]+)\"/", $htmlContent, $matchMetaDesc, PREG_SET_ORDER);
        $cs_data_arr['MetaDescription'] = empty($matchMetaDesc) ? "" : addslashes( substr(trim(strip_tags($matchMetaDesc[0][1])),0,250));
        //描述
        $cs_data_arr['Description'] = '';
//H1
        preg_match_all("/<h1>([\S\s]*?)<\/h1>/", $htmlContent, $matchH1, PREG_SET_ORDER);
        $cs_data_arr['H1'] = empty($matchH1) ? "" : trim(strip_tags($matchH1[0][1]));
//Merchant Go Url
        preg_match_all("/data-href=\"(\/r\/\d+)\"/", $htmlContent, $matchGo);
        $cs_data_arr['MerchantGoUrl'] = empty($matchGo[0]) ? "" :'http://alerabat.com'.$matchGo[1][0];
        //Screen Img
        preg_match_all("/<img itemprop=\"image\"\s+?alt=\".*?\"\s+?title=\".*?\"\s+?src=\"(.*?)\"/",$htmlContent,$matchImg);
        $cs_data_arr['ScreenImg'] = empty($matchImg[0]) ? "" :$matchImg[1][0];


        $sql = $pre_sql = "update cp_competitor_store set ";
        $empty_log_str = "";
        foreach ($cs_data_arr as $key => $val) {
            if (empty($val)) {
                $empty_log_str .= " {$key} --empty --";
            }
            if (strlen($val) > 100) {
                $val = del_br_space_by_str($val);
            }
            if (strripos($key, "meta") !== false) {
                $val = substr($val, 0, 250);
            }
            $sql .= " {$key} = '" . addslashes($val) . "' , ";
        }

        if($sql != $pre_sql ){
            $sql.=" LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$row['CompetitorStoreId']}";
            $GLOBALS['db']->query($sql);
        }

        $rank=0;
        $sqlIns=$sqlInsPre="INSERT ignore into cp_competitor_store_coupon (CompetitorStoreId,CompetitorId,CouponID,CouponTitle,GoUrl,CouponCode,ExpirationDate,CouponDesc,Used,Country,MaybeValid,AddTime,type,CouponCodeUrl,IsUpdateCodeUrl) values ";
//    coupons 数据
        preg_match_all("/<section id=\"sklep-listing\"([\s\S]+?)<\/section>/i", $htmlContent, $matchValidCoupon);

        if (!empty($matchValidCoupon[0])) {

            $matchCoupon=explode('class="rabat-list__item ',$matchValidCoupon[1][0]);
            for($i=1;$i<count($matchCoupon);$i++){
                $couponHtml=$matchCoupon[$i];

                $couponData['MaybeValid'] = 1;
                $couponData['Country'] = "PL";
                $couponData['CouponID'] =$rank;
                $couponData['CouponTitle']='';
                $couponData['CouponDesc']='';
                $couponData['GoUrl']='';
                $couponData['type']='deal';
                $couponData['Used']='';
                $couponData['CouponRestriction']='';
                $couponData['ExpirationDate']='';
                $couponData['CouponCodeUrl'] = "";
                $couponData['CouponCode']='';
                $couponData['IsUpdateCodeUrl']=0;

                //couponId
                preg_match_all("/data-href=\"\/r\/(\d+)\"/",$couponHtml,$matchCouponId);
                if(!empty($matchCouponId[0])){
                    $couponData['CouponID']=$matchCouponId[1][0];
                }

                //title
                preg_match_all("/<h2[^>]+>(.*?)<\/h2>/",$couponHtml,$matchcoupontitle);
                $couponData['CouponTitle']= empty($matchcoupontitle[0])?'':trim(strip_tags($matchcoupontitle[1][0]));

                //gourl
                $couponData['GoUrl']=empty($couponData['CouponID'])?'':'http://alerabat.com/r/'.$couponData['CouponID'];

                //desc
                preg_match_all("/<p[^>]+>(.*?)<\/p>/",$couponHtml,$matchCoupondesc);
                $couponData['CouponDesc']=empty($matchCoupondesc[0])?'':trim(strip_tags($matchCoupondesc[1][0]));

                //有效期
                if(empty($couponData['ExpirationDate'])){
                    $couponData['ExpirationDate']='0000-00-00';
                }

                $rank++;
                if(!empty($couponData['CouponID']))
                    $couponRankMap[''.$couponData['CouponID']]=$rank;
                if(!isset($couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']])){
                    $sqlIns.="('{$row['CompetitorStoreId']}','{$row['CompetitorId']}','{$couponData['CouponID']}','{$couponData['CouponTitle']}','{$couponData['GoUrl']}','{$couponData['CouponCode']}','{$couponData['ExpirationDate']}','{$couponData['CouponDesc']}','{$couponData['Used']}','{$couponData['Country']}','{$couponData['MaybeValid']}','".date("Y-m-d H:i:s")."','{$couponData['type']}','{$couponData['CouponCodeUrl']}','{$couponData['IsUpdateCodeUrl']}'),";
                }else{
                    $expiresVo=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']];
                    if($expiresVo['ExpirationDate']!=$couponData['ExpirationDate'] || $expiresVo['CouponTitle']!=$couponData['CouponTitle']  || $expiresVo['CouponDesc']!=$couponData['CouponDesc'] || $expiresVo['CouponCodeUrl']!=$couponData['CouponCodeUrl'] || $expiresVo['type']!=$couponData['type']){
                        $sqlUp="update cp_competitor_store_coupon set ExpirationDate='{$couponData['ExpirationDate']}',CouponTitle='{$couponData['CouponTitle']}',CouponDesc='{$couponData['CouponDesc']}',CouponCodeUrl='{$couponData['CouponCodeUrl']}',type='{$couponData['type']}',IsUpdateCodeUrl='{$couponData['IsUpdateCodeUrl']}',LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$expiresVo['ID']}";
                        $db->query($sqlUp);
                    }
                    $couponOnMap[]=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']]['ID'];
                }
            }
            if($sqlIns!=$sqlInsPre){
                $sqlIns=substr($sqlIns,0,strlen($sqlIns)-1);
                $sqlIns.=";";
                $db->query($sqlIns);
            }

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
        // //55  end  http://coupons.ca/tanda
    }else if($row['CompetitorId']==56){
        //56 start     http://coupons.ca/tanda
        preg_match_all("/href=\"(http:\/\/www.codigosdescuentospromocionales.es\/codigos-de-descuentos-[^\"]+)\"/i", $htmlContent, $matchUrl,PREG_SET_ORDER);
        $sqlInsUrl = $sqlInsUrlPre = "insert ignore into cp_temp_competitor_store(StoreUrl,CompetitorId,AddTime) values ";

        if (!empty($matchUrl)) {
            foreach ($matchUrl as $url) {
                $sqlInsUrl .= "('".$url[1] . "',{$row['CompetitorId']},'" . date("Y-m-d H:i:s") . "'),";
            }
            if ($sqlInsUrl != $sqlInsUrlPre) {
                $sqlInsUrl = substr($sqlInsUrl, 0, strlen($sqlInsUrl) - 1);
                $sqlInsUrl .= ";";

                $db->query($sqlInsUrl);
            }
        }

        $cs_data_arr = array();

//MetaTitle
        preg_match_all("/<title>([^<]+)<\/title>/", $htmlContent, $matchTitle, PREG_SET_ORDER);
        $cs_data_arr['MetaTitle'] = empty($matchTitle) ? "" : $matchTitle[0][1];
        //keywords No Meta keywords
        preg_match_all("/<meta name=\"keywords\" content=\"(.*?)\"/", $htmlContent, $matchkey, PREG_SET_ORDER);
        $cs_data_arr['MetaKeywords'] =empty($matchkey) ? "" : $matchkey[0][1];
        //meta description
        preg_match_all("/<meta name=\"description\" content=\"([^\"]+)\"/", $htmlContent, $matchMetaDesc, PREG_SET_ORDER);
        $cs_data_arr['MetaDescription'] = empty($matchMetaDesc) ? "" : addslashes( substr(trim(strip_tags($matchMetaDesc[0][1])),0,250));
        //描述
        preg_match_all("/class=\"termlogo\" \/><p>([\s\S]+?)<\/p>/", $htmlContent, $matchDesc, PREG_SET_ORDER);
        $cs_data_arr['Description'] = empty($matchDesc)?'':trim(del_br_space_by_str(strip_tags($matchDesc[0][1])));
//H1
        preg_match_all("/<h1>([\S\s]*?)<\/h1>/", $htmlContent, $matchH1, PREG_SET_ORDER);
        $cs_data_arr['H1'] = empty($matchH1) ? "" : trim(strip_tags($matchH1[0][1]));
//Merchant Go Url
        preg_match_all("/rel=\"nofollow,noindex\" href=\"(.*?)\"/", $htmlContent, $matchGo);
        $cs_data_arr['MerchantGoUrl'] = empty($matchGo[0]) ? "" :'http://www.codigosdescuentospromocionales.es'.$matchGo[1][0];
        //Screen Img
        preg_match_all("/src=\"(.*?)\" alt=\".*?\" class=\"termlogo\"/",$htmlContent,$matchImg);
        $cs_data_arr['ScreenImg'] = empty($matchImg[0]) ? "" :$matchImg[1][0];

        $sql = $pre_sql = "update cp_competitor_store set ";
        $empty_log_str = "";
        foreach ($cs_data_arr as $key => $val) {
            if (empty($val)) {
                $empty_log_str .= " {$key} --empty --";
            }
            if (strlen($val) > 100) {
                $val = del_br_space_by_str($val);
            }
            if (strripos($key, "meta") !== false) {
                $val = substr($val, 0, 250);
            }
            $sql .= " {$key} = '" . addslashes($val) . "' , ";
        }

        if($sql != $pre_sql ){
            $sql.=" LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$row['CompetitorStoreId']}";
            $GLOBALS['db']->query($sql);
        }

        $rank=0;
        $sqlIns=$sqlInsPre="INSERT ignore into cp_competitor_store_coupon (CompetitorStoreId,CompetitorId,CouponID,CouponTitle,GoUrl,CouponCode,ExpirationDate,CouponDesc,Used,Country,MaybeValid,AddTime,type,CouponCodeUrl,IsUpdateCodeUrl) values ";
//    coupons 数据
        preg_match_all("/id=\"itemsbox\">([\s\S]+?)<li id=\"relatedcoupons\"/i", $htmlContent, $matchValidCoupon);

        if (!empty($matchValidCoupon[0])) {

            $matchCoupon=explode('class="couponcontent coupons"',$matchValidCoupon[1][0]);
            for($i=1;$i<count($matchCoupon);$i++){
                $couponHtml=$matchCoupon[$i];

                $couponData['MaybeValid'] = 1;
                $couponData['Country'] = "ES";
                $couponData['CouponID'] =$rank;
                $couponData['CouponTitle']='';
                $couponData['CouponDesc']='';
                $couponData['GoUrl']='';
                $couponData['type']='deal';
                $couponData['Used']='';
                $couponData['CouponRestriction']='';
                $couponData['ExpirationDate']='';
                $couponData['CouponCodeUrl'] = "";
                $couponData['CouponCode']='';
                $couponData['IsUpdateCodeUrl']=0;

                //couponId
                preg_match_all("/data-id=\"(\d+)\"/",$couponHtml,$matchCouponId);
                if(!empty($matchCouponId[0])){
                    $couponData['CouponID']=$matchCouponId[1][0];
                }

                //type && code
                preg_match_all("/<b>Ver código<\/b><\/span>(.*?)<\/a>/",$couponHtml,$matchCodeUrl);
                if(!empty($matchCodeUrl[0])){

                    $couponData['type']='code';
                    $couponData['CouponCode']=trim(strip_tags($matchCodeUrl[1][0]));
                }

                //title
                preg_match_all("/class=\".*\" data-id=\".*?\">([\s\S]*?)<\/a>/",$couponHtml,$matchcoupontitle);
                $couponData['CouponTitle']= empty($matchcoupontitle[0])?'':trim(strip_tags($matchcoupontitle[1][0]));

                //gourl
                $couponData['GoUrl']=empty($couponData['CouponID'])?'':$row['Url'].'?oid='.$couponData['CouponID'];

                //desc
                preg_match_all("/class='wlt_shortcode_excerpt'>([\s\S]+?)<\/span>/",$couponHtml,$matchCoupondesc);
                $couponData['CouponDesc']=empty($matchCoupondesc[0])?'':trim(strip_tags($matchCoupondesc[1][0]));

                //有效期
                if(empty($couponData['ExpirationDate'])){
                    $couponData['ExpirationDate']='0000-00-00';
                }

                $rank++;
                if(!empty($couponData['CouponID']))
                    $couponRankMap[''.$couponData['CouponID']]=$rank;
                if(!isset($couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']])){
                    $sqlIns.="('{$row['CompetitorStoreId']}','{$row['CompetitorId']}','{$couponData['CouponID']}','{$couponData['CouponTitle']}','{$couponData['GoUrl']}','{$couponData['CouponCode']}','{$couponData['ExpirationDate']}','{$couponData['CouponDesc']}','{$couponData['Used']}','{$couponData['Country']}','{$couponData['MaybeValid']}','".date("Y-m-d H:i:s")."','{$couponData['type']}','{$couponData['CouponCodeUrl']}','{$couponData['IsUpdateCodeUrl']}'),";
                }else{
                    $expiresVo=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']];
                    if($expiresVo['ExpirationDate']!=$couponData['ExpirationDate'] || $expiresVo['CouponTitle']!=$couponData['CouponTitle']  || $expiresVo['CouponDesc']!=$couponData['CouponDesc'] || $expiresVo['CouponCodeUrl']!=$couponData['CouponCodeUrl'] || $expiresVo['type']!=$couponData['type']){
                        $sqlUp="update cp_competitor_store_coupon set ExpirationDate='{$couponData['ExpirationDate']}',CouponTitle='{$couponData['CouponTitle']}',CouponDesc='{$couponData['CouponDesc']}',CouponCodeUrl='{$couponData['CouponCodeUrl']}',type='{$couponData['type']}',IsUpdateCodeUrl='{$couponData['IsUpdateCodeUrl']}',LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$expiresVo['ID']}";
                        $db->query($sqlUp);
                    }
                    $couponOnMap[]=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']]['ID'];
                }
            }
            if($sqlIns!=$sqlInsPre){
                $sqlIns=substr($sqlIns,0,strlen($sqlIns)-1);
                $sqlIns.=";";
                $db->query($sqlIns);
            }

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
        // //56  end  http://coupons.ca/tanda
    }else if($row['CompetitorId']==57){
        //57 start     http://coupons.ca/tanda
        preg_match_all("/href=\"(\/[^\/]+?)\"/i", $htmlContent, $matchUrl,PREG_SET_ORDER);
        $sqlInsUrl = $sqlInsUrlPre = "insert ignore into cp_temp_competitor_store(StoreUrl,CompetitorId,AddTime) values ";

        if (!empty($matchUrl)) {
            foreach ($matchUrl as $url) {
                $sqlInsUrl .= "('".'http://www.cuponation.es'.$url[1] . "',{$row['CompetitorId']},'" . date("Y-m-d H:i:s") . "'),";
            }
            if ($sqlInsUrl != $sqlInsUrlPre) {
                $sqlInsUrl = substr($sqlInsUrl, 0, strlen($sqlInsUrl) - 1);
                $sqlInsUrl .= ";";

                $db->query($sqlInsUrl);
            }
        }

        $cs_data_arr = array();

//MetaTitle
        preg_match_all("/<title>([^<]+)<\/title>/", $htmlContent, $matchTitle, PREG_SET_ORDER);
        $cs_data_arr['MetaTitle'] = empty($matchTitle) ? "" : $matchTitle[0][1];
        //keywords No Meta keywords
        preg_match_all("/<meta name=\"keywords\" content=\"(.*?)\"/", $htmlContent, $matchkey, PREG_SET_ORDER);
        $cs_data_arr['MetaKeywords'] =empty($matchkey) ? "" : $matchkey[0][1];
        //meta description
        preg_match_all("/<meta name=\"description\" content=\"([^\"]+)\"/", $htmlContent, $matchMetaDesc, PREG_SET_ORDER);
        $cs_data_arr['MetaDescription'] = empty($matchMetaDesc) ? "" : addslashes( substr(trim(strip_tags($matchMetaDesc[0][1])),0,250));
        //描述
        $cs_data_arr['Description'] = '';
//H1
        preg_match_all("/<h1[^>]+>([\S\s]*?)<\/h1>/", $htmlContent, $matchH1, PREG_SET_ORDER);
        $cs_data_arr['H1'] = empty($matchH1) ? "" : trim(strip_tags($matchH1[0][1]));
//Merchant Go Url
        preg_match_all("/data-slug=\"(.*?)\" class=\"cn-retailer-logo-image\"/", $htmlContent, $matchGo);
        $cs_data_arr['MerchantGoUrl'] = empty($matchGo[0]) ? "" :'http://www.cuponation.es/redirect-to?url='.$matchGo[1][0];
        //Screen Img
        preg_match_all("/class=\"cn-retailer-logo-image\"><img src=\"(.*?)\"/",$htmlContent,$matchImg);
        $cs_data_arr['ScreenImg'] = empty($matchImg[0]) ? "" :$matchImg[1][0];

        $sql = $pre_sql = "update cp_competitor_store set ";
        $empty_log_str = "";
        foreach ($cs_data_arr as $key => $val) {
            if (empty($val)) {
                $empty_log_str .= " {$key} --empty --";
            }
            if (strlen($val) > 100) {
                $val = del_br_space_by_str($val);
            }
            if (strripos($key, "meta") !== false) {
                $val = substr($val, 0, 250);
            }
            $sql .= " {$key} = '" . addslashes($val) . "' , ";
        }

        if($sql != $pre_sql ){
            $sql.=" LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$row['CompetitorStoreId']}";
            $GLOBALS['db']->query($sql);
        }

        $rank=0;
        $sqlIns=$sqlInsPre="INSERT ignore into cp_competitor_store_coupon (CompetitorStoreId,CompetitorId,CouponID,CouponTitle,GoUrl,CouponCode,ExpirationDate,CouponDesc,Used,Country,MaybeValid,AddTime,type,CouponCodeUrl,IsUpdateCodeUrl) values ";
//    coupons 数据
        preg_match_all("/<div class=\"voucher-list\"([\s\S]+?)<\/footer><\/div><\/div>/i", $htmlContent, $matchValidCoupon);

        if (!empty($matchValidCoupon[0])) {

            $matchCoupon=explode('<div data-cn-voucher',$matchValidCoupon[1][0]);
            for($i=1;$i<count($matchCoupon);$i++){
                $couponHtml=$matchCoupon[$i];

                $couponData['MaybeValid'] = 1;
                $couponData['Country'] = "ES";
                $couponData['CouponID'] =$rank;
                $couponData['CouponTitle']='';
                $couponData['CouponDesc']='';
                $couponData['GoUrl']='';
                $couponData['type']='deal';
                $couponData['Used']='';
                $couponData['CouponRestriction']='';
                $couponData['ExpirationDate']='';
                $couponData['CouponCodeUrl'] = "";
                $couponData['CouponCode']='';
                $couponData['IsUpdateCodeUrl']=0;

                //couponId
                preg_match_all("/data-voucher-id=\"(\d+)\"/",$couponHtml,$matchCouponId);
                if(!empty($matchCouponId[0])){
                    $couponData['CouponID']=$matchCouponId[1][0];
                }

                //type && code
                preg_match_all("/class=\"code-field\"/",$couponHtml,$matchCodeUrl);
                if(!empty($matchCodeUrl[0])){

                    $couponData['type']='code';
                    $couponData['CouponCodeUrl'] = "http://www.cuponation.es/ajax/voucherpopup?id=".$couponData['CouponID'];
                    $couponData['IsUpdateCodeUrl']='1';
                }

                //title
                preg_match_all("/<h3[^>]+>([\s\S]*?)<\/h3>/",$couponHtml,$matchcoupontitle);
                $couponData['CouponTitle']= empty($matchcoupontitle[0])?'':trim(strip_tags($matchcoupontitle[1][0]));

                //gourl
                $couponData['GoUrl']=empty($couponData['CouponID'])?'':'http://clickout.cuponation.es/clickout/out/id/'.$couponData['CouponID'];

                //desc
                preg_match_all("/class=\"cn-description\"[^>]*>([\S\s]+?)<\/div>/",$couponHtml,$matchCoupondesc);
                $couponData['CouponDesc']=empty($matchCoupondesc[0])?'':trim(strip_tags($matchCoupondesc[1][0]));

                //有效期
                if(empty($couponData['ExpirationDate'])){
                    $couponData['ExpirationDate']='0000-00-00';
                }

                $rank++;
                if(!empty($couponData['CouponID']))
                    $couponRankMap[''.$couponData['CouponID']]=$rank;
                if(!isset($couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']])){
                    $sqlIns.="('{$row['CompetitorStoreId']}','{$row['CompetitorId']}','{$couponData['CouponID']}','{$couponData['CouponTitle']}','{$couponData['GoUrl']}','{$couponData['CouponCode']}','{$couponData['ExpirationDate']}','{$couponData['CouponDesc']}','{$couponData['Used']}','{$couponData['Country']}','{$couponData['MaybeValid']}','".date("Y-m-d H:i:s")."','{$couponData['type']}','{$couponData['CouponCodeUrl']}','{$couponData['IsUpdateCodeUrl']}'),";
                }else{
                    $expiresVo=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']];
                    if($expiresVo['ExpirationDate']!=$couponData['ExpirationDate'] || $expiresVo['CouponTitle']!=$couponData['CouponTitle']  || $expiresVo['CouponDesc']!=$couponData['CouponDesc'] || $expiresVo['CouponCodeUrl']!=$couponData['CouponCodeUrl'] || $expiresVo['type']!=$couponData['type']){
                        $sqlUp="update cp_competitor_store_coupon set ExpirationDate='{$couponData['ExpirationDate']}',CouponTitle='{$couponData['CouponTitle']}',CouponDesc='{$couponData['CouponDesc']}',CouponCodeUrl='{$couponData['CouponCodeUrl']}',type='{$couponData['type']}',IsUpdateCodeUrl='{$couponData['IsUpdateCodeUrl']}',LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$expiresVo['ID']}";
                        $db->query($sqlUp);
                    }
                    $couponOnMap[]=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']]['ID'];
                }
            }
            if($sqlIns!=$sqlInsPre){
                $sqlIns=substr($sqlIns,0,strlen($sqlIns)-1);
                $sqlIns.=";";
                $db->query($sqlIns);
            }

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
        // //57  end  http://coupons.ca/tanda
    }else if($row['CompetitorId']==58){
        //58 start     http://coupons.ca/tanda
        preg_match_all("/href=\"(\/gutscheine\/[^\/]+\/)/i", $htmlContent, $matchUrl,PREG_SET_ORDER);
        $sqlInsUrl = $sqlInsUrlPre = "insert ignore into cp_temp_competitor_store(StoreUrl,CompetitorId,AddTime) values ";

        if (!empty($matchUrl)) {
            foreach ($matchUrl as $url) {
                $sqlInsUrl .= "('".'http://de.fyvor.com'.$url[1] . "',{$row['CompetitorId']},'" . date("Y-m-d H:i:s") . "'),";
            }
            if ($sqlInsUrl != $sqlInsUrlPre) {
                $sqlInsUrl = substr($sqlInsUrl, 0, strlen($sqlInsUrl) - 1);
                $sqlInsUrl .= ";";

                $db->query($sqlInsUrl);
            }
        }

        $cs_data_arr = array();

//MetaTitle
        preg_match_all("/<title>([^<]+)<\/title>/", $htmlContent, $matchTitle, PREG_SET_ORDER);
        $cs_data_arr['MetaTitle'] = empty($matchTitle) ? "" : $matchTitle[0][1];
        //keywords No Meta keywords
        preg_match_all("/<meta name=\"keywords\" content=\"(.*?)\"/", $htmlContent, $matchkey, PREG_SET_ORDER);
        $cs_data_arr['MetaKeywords'] =empty($matchkey) ? "" : $matchkey[0][1];
        //meta description
        preg_match_all("/<meta name=\"description\" content=\"([^\"]+)\"/", $htmlContent, $matchMetaDesc, PREG_SET_ORDER);
        $cs_data_arr['MetaDescription'] = empty($matchMetaDesc) ? "" : addslashes( substr(trim(strip_tags($matchMetaDesc[0][1])),0,250));
        //描述
        preg_match_all("/class=\"store_de\">([\S\s]+)<\/div>/", $htmlContent, $matchDesc, PREG_SET_ORDER);
        $cs_data_arr['Description'] = empty($matchDesc)?'':trim(del_br_space_by_str(strip_tags($matchDesc[0][1])));
//H1
        preg_match_all("/<h1[^>]*>([\S\s]*?)<\/h1>/", $htmlContent, $matchH1, PREG_SET_ORDER);
        $cs_data_arr['H1'] = empty($matchH1) ? "" : trim(strip_tags($matchH1[0][1]));
//Merchant Go Url
        preg_match_all("/class=\"mer_pic\"> <a title=\".*?\" href='(.*?)'/", $htmlContent, $matchGo);
        $cs_data_arr['MerchantGoUrl'] = empty($matchGo[0]) ? "" :'http://de.fyvor.com'.$matchGo[1][0];
        //Screen Img
        preg_match_all("/<img src=\"([^\"]*?)\" width=\"[^\"]*?\" alt=\".*?\"><\/a>/",$htmlContent,$matchImg);
        $cs_data_arr['ScreenImg'] = empty($matchImg[0]) ? "" :$matchImg[1][0];

        $sql = $pre_sql = "update cp_competitor_store set ";
        $empty_log_str = "";
        foreach ($cs_data_arr as $key => $val) {
            if (empty($val)) {
                $empty_log_str .= " {$key} --empty --";
            }
            if (strlen($val) > 100) {
                $val = del_br_space_by_str($val);
            }
            if (strripos($key, "meta") !== false) {
                $val = substr($val, 0, 250);
            }
            $sql .= " {$key} = '" . addslashes($val) . "' , ";
        }

        if($sql != $pre_sql ){
            $sql.=" LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$row['CompetitorStoreId']}";
            $GLOBALS['db']->query($sql);
        }

        $rank=0;
        $sqlIns=$sqlInsPre="INSERT ignore into cp_competitor_store_coupon (CompetitorStoreId,CompetitorId,CouponID,CouponTitle,GoUrl,CouponCode,ExpirationDate,CouponDesc,Used,Country,MaybeValid,AddTime,type,CouponCodeUrl,IsUpdateCodeUrl) values ";
//    coupons 数据
        preg_match_all("/class=\"c_list\">([\s\S]+?)<!-- coupon list end/i", $htmlContent, $matchValidCoupon);

        if (!empty($matchValidCoupon[0])) {

            $matchCoupon=explode('-- coupon block start --',$matchValidCoupon[1][0]);
            for($i=1;$i<count($matchCoupon);$i++){
                $couponHtml=$matchCoupon[$i];

                $couponData['MaybeValid'] = 1;
                $couponData['Country'] = "DE";

                $couponData['CouponID'] =$rank;
                $couponData['CouponTitle']='';
                $couponData['CouponDesc']='';
                $couponData['GoUrl']='';
                $couponData['type']='deal';
                $couponData['Used']='';
                $couponData['CouponRestriction']='';
                $couponData['ExpirationDate']='';
                $couponData['CouponCodeUrl'] = "";
                $couponData['CouponCode']='';
                $couponData['IsUpdateCodeUrl']='0';

                //couponId
                preg_match_all("/id=\"divcover_(\d+)\"/",$couponHtml,$matchCouponId);
                if(!empty($matchCouponId[0])){
                    $couponData['CouponID']=$matchCouponId[1][0];
                }

                //type && code
                preg_match_all("/class=\"coupon_code icode_01\" id=\"[^\"]+\">(.*?)<\/span>/",$couponHtml,$matchCodeUrl);
                if(!empty($matchCodeUrl[0])){

                    $couponData['type']='code';
                    $couponData['CouponCode']=empty($matchCodeUrl[0])?'':$matchCodeUrl[1][0];

                }

                //title
                preg_match_all("/class=\"coupon_title\">([\s\S]*?)<\/div>/",$couponHtml,$matchcoupontitle);
                $couponData['CouponTitle']= empty($matchcoupontitle[0])?'':trim(strip_tags($matchcoupontitle[1][0]));

                //gourl
                preg_match_all("/href='(\/go\/.*?)'/",$couponHtml,$matchGo);
                $couponData['GoUrl']=empty($matchGo[0])?'':'http://de.fyvor.com'.trim(strip_tags($matchGo[1][0]));

                //desc
                preg_match_all("/class=\"cpdesc\">(.*?)<\/span>/",$couponHtml,$matchCoupondesc);
                $couponData['CouponDesc']=empty($matchCoupondesc[0])?'':trim(strip_tags($matchCoupondesc[1][0]));

                //有效期
                if(empty($couponData['ExpirationDate'])){
                    $couponData['ExpirationDate']='0000-00-00';
                }

                $rank++;
                if(!empty($couponData['CouponID']))
                    $couponRankMap[''.$couponData['CouponID']]=$rank;
                if(!isset($couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']])){
                    $sqlIns.="('{$row['CompetitorStoreId']}','{$row['CompetitorId']}','{$couponData['CouponID']}','{$couponData['CouponTitle']}','{$couponData['GoUrl']}','{$couponData['CouponCode']}','{$couponData['ExpirationDate']}','{$couponData['CouponDesc']}','{$couponData['Used']}','{$couponData['Country']}','{$couponData['MaybeValid']}','".date("Y-m-d H:i:s")."','{$couponData['type']}','{$couponData['CouponCodeUrl']}','{$couponData['IsUpdateCodeUrl']}'),";
                }else{
                    $expiresVo=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']];
                    if($expiresVo['ExpirationDate']!=$couponData['ExpirationDate'] || $expiresVo['CouponTitle']!=$couponData['CouponTitle']  || $expiresVo['CouponDesc']!=$couponData['CouponDesc'] || $expiresVo['CouponCodeUrl']!=$couponData['CouponCodeUrl'] || $expiresVo['type']!=$couponData['type']){
                        $sqlUp="update cp_competitor_store_coupon set ExpirationDate='{$couponData['ExpirationDate']}',CouponTitle='{$couponData['CouponTitle']}',CouponDesc='{$couponData['CouponDesc']}',CouponCodeUrl='{$couponData['CouponCodeUrl']}',type='{$couponData['type']}',IsUpdateCodeUrl='{$couponData['IsUpdateCodeUrl']}',LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$expiresVo['ID']}";
                        $db->query($sqlUp);
                    }
                    $couponOnMap[]=$couponCsMap[$row['CompetitorStoreId']."\t".$couponData['CouponID']]['ID'];
                }
            }
            if($sqlIns!=$sqlInsPre){
                $sqlIns=substr($sqlIns,0,strlen($sqlIns)-1);
                $sqlIns.=";";
                $db->query($sqlIns);
            }

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
        // //58  end  http://coupons.ca/tanda
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
    system("rm -rf {$row['FilePath']}", $retval);
}

if(!empty($couponOnMap)){

    //更新竞争对手最后解释时间
    $up_competitor_sql="update cp_competitor SET LastResolveTime=now() where ID={$row['CompetitorId']}";
    $db->query($up_competitor_sql);

}
$db->close();

echo "end time:".date("Y-m-d H:i:s")." total:{$deal_num}\n";
