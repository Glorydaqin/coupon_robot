<?php
/**
 * Created by PhpStorm.
 * User: daqin
 * Date: 2017/8/9
 * Time: 22:20
 */

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
$cs_data_arr['ScreenImg'] = ($matchScreenImg[0]) ? "" : $matchScreenImg[0][1];
//Merchant Go Url
preg_match_all("/href=\"([^\"]+)\" target=\"_blank\" rel=\"nofollow\">\s+<div class=\"stealbuttondec/", $htmlContent, $matchGo, PREG_SET_ORDER);
$cs_data_arr['MerchantGoUrl'] = empty($matchGo[0]) ? "" : ("http://www.thebargainavenue.com.au" . $matchGo[0][1]);

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