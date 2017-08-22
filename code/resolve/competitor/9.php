<?php
/**
 * Created by PhpStorm.
 * User: daqin
 * Date: 2017/8/9
 * Time: 22:18
 */
//http://couponfollow.com
//获取竞争对手其他store链接 start
preg_match_all("/ href=\"(\/site\/[^\"]+)\"/i", $htmlContent, $matchUrl,PREG_SET_ORDER);
$sqlInsUrl=$sqlInsUrlPre="insert ignore into cp_temp_competitor_store(StoreUrl,CompetitorId,AddTime) values ";
//site_pre
$site_pre='https://couponfollow.com';
foreach ($matchUrl as $url){
    if(strstr($url[1],"/site/out/") || $url[1]=="/site/" || strstr($url[1],"/site/browse")){
        continue;
    }
    $sqlInsUrl.="('".$site_pre.$url[1]."',{$row['CompetitorId']},'".date("Y-m-d H:i:s")."'),";
}
if($sqlInsUrl!=$sqlInsUrlPre){
    $sqlInsUrl=substr($sqlInsUrl,0,-1);
    $GLOBALS['db']->query($sqlInsUrl);
}
//获取竞争对手其他store链接 end

$cs_data_arr = array();

preg_match_all("/<title>([^<]+)<\\/title>/", $htmlContent, $matchTitle,PREG_SET_ORDER);
$cs_data_arr['MetaTitle']=empty($matchTitle)?"":$matchTitle[0][1];

//keywords No Meta keywords
preg_match_all("/<meta name=\"keywords\" content=\"([^\"]+)\"/", $htmlContent, $matchKeywords,PREG_SET_ORDER);
$cs_data_arr['MetaKeywords']=empty($matchKeywords)?"":$matchKeywords[0][1];

//meta description
preg_match_all("/<meta name=\"description\"\s*content=\"([^\"]+)\"/", $htmlContent, $matchMetaDesc,PREG_SET_ORDER);
$cs_data_arr['MetaDescription']=empty($matchMetaDesc)?"":$matchMetaDesc[0][1];

//描述
preg_match_all("/<li class=\"about\">[\\w\\W]*<h3>[^<]*<\/h3>[\\w\\W]*<p>([\\w\\W]*)<\/p>/U", $htmlContent, $matchDesc,PREG_SET_ORDER);
$description=empty($matchDesc)?"":$matchDesc[0][1];
$cs_data_arr['Description']=preg_replace("/<[^>]*>/", "", $description);
//H1
preg_match_all("/<h1[^>]*>([^<]+)<\\/h1>/", $htmlContent, $matchH1,PREG_SET_ORDER);
$cs_data_arr['H1']=empty($matchH1)?"":$matchH1[0][1];
//Screen Img
preg_match_all("/<img class=\"brandlogo\" src=\"([^\"]*)\"/", $htmlContent, $matchScreenImg,PREG_SET_ORDER);
$cs_data_arr['ScreenImg']=empty($matchScreenImg)?"":$matchScreenImg[0][1];
//Merchant Go Url
preg_match_all("/<a href=\"([^\"]*)\" rel=\"nofollow\" title=\"[^\"]*\">[\\w\\W]{0,100}<img class=\"brandlogo\"/", $htmlContent, $matchGo,PREG_SET_ORDER);
$cs_data_arr['MerchantGoUrl']=empty($matchGo)?"":($site_pre.$matchGo[0][1]);
$sql = $pre_sql ="update cp_competitor_store set ";
foreach ($cs_data_arr as $key=>$val){
    if(empty($val)){
        $empty_log_str .=" {$key} --empty --";
    }
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

//    coupons 数据
$rank=0;
$sqlIns=$sqlInsPre="INSERT ignore into cp_competitor_store_coupon (CompetitorStoreId,CompetitorId,CouponID,CouponTitle,GoUrl,CouponDesc,CouponRestriction,CouponCode,Used,ExpirationDate,Country,MaybeValid,AddTime,type) values ";
preg_match_all("/<ul class=\"span8 couponslist\">([\w\W]*)<\/ul>/U", $htmlContent, $matchCouponList,PREG_SET_ORDER);
$arr=explode('<li id="codecontainer',$matchCouponList[0][1]);
if(count($arr)>1){
    for($i=1;$i<count($arr);$i++){
        $couponHtml=$arr[$i];
        $couponData['MaybeValid'] = 1;
        $couponData['Country'] = "US";

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
        $couponData['IsUpdateCodeUrl']='0';

        //similar coupon跳过

        //couponId
        preg_match_all('/data-id="([^"]*)"/', $couponHtml, $matchCouponId);
        if(!empty($matchCouponId[0])){
            $couponData['CouponID']=$matchCouponId[1][0];
            $couponData['CouponTitle']=deal_text(str_replace("￡", "&pound;", $couponData['CouponTitle']));
        }

        //type && code
        preg_match_all("/data-clipboard-text=\"([\w\W]*)\"/U", $couponHtml, $matchCouponCode);
        if(isset($matchCouponCode[1][0])){
            $couponData['CouponCode']=$matchCouponCode[1][0];
            $couponData['type']='code';
        }

        //title
        preg_match_all("/<h2>([\s\S]+?)<\/h2>/", $couponHtml, $matchCouponTitle);
        $couponData['CouponTitle']= empty($matchCouponTitle[0])?'':deal_text($matchCouponTitle[1][0]);

        //gourl
        preg_match_all("/href=\"(\/code\/out\/\d+)\"/", $couponHtml, $matchGoUrl);
        if(!empty($matchGoUrl)){
            $couponData['GoUrl']=$site_pre.$matchGoUrl[1][0];
        }

        //desc
        preg_match_all("/<p class=\"explain\">(.*?)<\/p>/", $couponHtml, $matchCouponCouponDesc);
        if(!empty($matchCouponCouponDesc[0])){
            $matchCouponCouponDesc[1][0]=str_replace("￡", "&pound;", $matchCouponCouponDesc[1][0]);
        }
        $couponData['CouponDesc']=empty($matchCouponCouponDesc[0])?'':deal_text($matchCouponCouponDesc[1][0]);

        //有效期
        //无

        if(empty($couponData['ExpirationDate'])){
            $couponData['ExpirationDate']='0000-00-00';
        }

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
//end9