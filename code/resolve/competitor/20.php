<?php
/**
 * Created by PhpStorm.
 * User: daqin
 * Date: 2017/8/9
 * Time: 22:22
 */
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