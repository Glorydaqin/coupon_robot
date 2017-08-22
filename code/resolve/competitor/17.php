<?php
/**
 * Created by PhpStorm.
 * User: daqin
 * Date: 2017/8/9
 * Time: 22:21
 */
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