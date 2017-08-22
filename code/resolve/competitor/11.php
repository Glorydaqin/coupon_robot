<?php
/**
 * Created by PhpStorm.
 * User: daqin
 * Date: 2017/8/9
 * Time: 22:19
 */
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