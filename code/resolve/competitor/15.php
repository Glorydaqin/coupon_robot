<?php
/**
 * Created by PhpStorm.
 * User: daqin
 * Date: 2017/8/9
 * Time: 22:20
 */
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
preg_match_all("/<h1>([^<]+)<\/h1>/", $htmlContent, $matchH1, PREG_SET_ORDER);
$cs_data_arr['H1'] = empty($matchH1) ? "" : $matchH1[0][1];
//Merchant Go Url
$matchGo = Selector::select($htmlContent,"*//a[@class=\"btn btn-success store-url btn-xs\"]/@href");
$cs_data_arr['MerchantGoUrl'] = empty($matchGo) ? "" :  $matchGo;
//Screen Img
$matchScreenImg = Selector::select($htmlContent,"*//div[@class=\"image store-image block-center\"]/img/@src");
$cs_data_arr['ScreenImg'] = empty($matchScreenImg) ? "" : $matchScreenImg;

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
preg_match_all("/class=\"list-group\"([\s\S]+?)class=\"store newsletter clearfix\"/", $htmlContent, $matchValidCoupon);
$matchCoupon=explode('<li class="views-row',$matchValidCoupon[1][0]);
if (!empty($matchCoupon)) {

    for($i = 1 ;$i<count($matchCoupon);$i++){
        $couponHtml = $matchCoupon[$i];

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