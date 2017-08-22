<?php
/**
 * Created by PhpStorm.
 * User: daqin
 * Date: 2017/8/9
 * Time: 22:25
 */
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