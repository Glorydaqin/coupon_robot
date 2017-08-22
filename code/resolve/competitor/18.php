<?php
/**
 * Created by PhpStorm.
 * User: daqin
 * Date: 2017/8/9
 * Time: 22:21
 */
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
$matchCoupon=explode('<article data-offer-type',$matchValidCoupon[1][0]);
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
//end coupons 18