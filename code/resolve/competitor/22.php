<?php
/**
 * Created by PhpStorm.
 * User: daqin
 * Date: 2017/8/9
 * Time: 22:23
 */

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