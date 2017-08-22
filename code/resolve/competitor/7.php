<?php
/**
 * Created by PhpStorm.
 * User: daqin
 * Date: 2017/8/9
 * Time: 22:17
 */
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