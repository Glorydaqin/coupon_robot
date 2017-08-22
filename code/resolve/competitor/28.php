<?php
/**
 * Created by PhpStorm.
 * User: daqin
 * Date: 2017/8/9
 * Time: 22:24
 */
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