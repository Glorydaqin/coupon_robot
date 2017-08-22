<?php
include_once dirname(dirname(dirname(__FILE__))).DIRECTORY_SEPARATOR . 'etc/initiate.php';

$vo['CompetitorId']=isset($_SERVER["argv"][1])?$_SERVER["argv"][1]:0;
$vo['ID']=isset($_SERVER["argv"][2])?$_SERVER["argv"][2]:0;
$vo['GoUrl']=isset($_SERVER["argv"][3])?$_SERVER["argv"][3]:0;
$vo['StoreUrl']=isset($_SERVER["argv"][4])?$_SERVER["argv"][4]:0;

set_time_limit(200);

if(!empty($vo['CompetitorId']) && !empty($vo['ID']) && !empty($vo['StoreUrl']) && !empty($vo['GoUrl'])){
	$vo['GoUrl']=base64_decode($vo['GoUrl']);
	$vo['StoreUrl']=base64_decode($vo['StoreUrl']);
	$db = new Mysql(DB_NAME, DB_HOST, DB_USER, DB_PWD);
	$cache_obj = new Cache();
	if(!MEM_CACHE_DEBUG){ //如果有memcache 走memcache
		$ip_info = $cache_obj->get_ip_by_competitor_id($vo['CompetitorId']);
	}else{
		$ip_info = file_get_contents(DIR_IP_CONFIG.$vo['CompetitorId'].".txt");
		if(empty($ip_info)){
			$ip_info = get_proxy_info($vo['CompetitorId']);
			file_put_contents(DIR_IP_CONFIG.$vo['CompetitorId'].".txt", $ip_info);
		}
	}
	
	$ip_info = trim($ip_info);

	//判断是否为联盟链接输出js跳转
	$url = $vo['GoUrl'];

	if(strripos($url,"shareasale.com") || strripos($url,"redirectingat.com")){
		$content_first = curl_post_data($vo['GoUrl'],$ip_info);
		preg_match("/window.location.replace\('([^\']+)'\)/i",$content_first,$url_res);
		$realUrl = $url_res[1];
	}elseif (strripos($url,"linksynergy.com") || strripos($url,"pntrs.com") || strripos($url,"affiliatetechnology.com")){
		$content_first = curl_post_data($vo['GoUrl'],$ip_info);
		preg_match('/<meta\s+http-equiv="Refresh"\s+content="1;url=([^\"]+)"/i',$content_first,$url_res);
		$realUrl = $url_res[1];
	}elseif (strripos($url,"belboon.de")){
		$content_first = curl_post_data($vo['GoUrl'],$ip_info);
		preg_match('/<meta\s+http-equiv="refresh"\s+content="5;\s*URL=([^\"]+)"/i',$content_first,$url_res);
		$realUrl = $url_res[1];
	}elseif (strripos($url,"webmasterplan.com")){
		$content_first = curl_post_data($vo['GoUrl'],$ip_info);
		preg_match('/<meta\s+http-equiv="refresh"\s+content="2;\s*URL=([^\"]+)"/i',$content_first,$url_res);
		$url_exp = explode("diurl=",$url_res[1]);
		$url_res[1] = urldecode($url_exp[1]);
		$realUrl = $url_res[1];
	}elseif (strripos($url,"clickout.cupones.es")){
        $content_first = curl_post_data($vo['GoUrl'],$ip_info);
        preg_match("/location.href=\"([^\"]+)\"/i",$content_first,$url_res);
        $realUrl =empty($url_res[0])?$vo['GoUrl']:$url_res[1][0];
    }else{

		//该页面单独获取跳转js
		if(strripos($url,"rabattcode.de")){
			//德国www.rabattcode.de 出站先获取js跳转地址
			$content_one = curl_post_data($url,$ip_info);
			preg_match("/<meta\s+http-equiv=\"refresh\"\s+content=\"\d+;\s*url=([^\"]+)\">/i",$content_one,$url_res);
			$weburl= $url_res[1];
//			$realUrl=curl_post_get_url($weburl,$ip_info);
			$realUrl=get_redirect_url($weburl);
		}elseif(strripos($url,'codespromofr.com' ) || strripos($url,'fyvor.com' ) ||strripos($url,'codepromo.lexpress.fr' )){
			//法国站
			$realUrl=get_redirect_url($url);
            if(strripos($realUrl,"r.brandreward.com") || strripos($realUrl,"r.meikaiinfotech.com")){
                $tmp=explode("&url=",$realUrl);
                if(!empty($tmp[1])){
                    $realUrl=urldecode($tmp[1]);
                }
            }
		}else{
			$realUrl=vspider_get_301_ip($vo['GoUrl'],$ip_info);
		}

		if(strripos($url,"shareasale.com") || strripos($url,"redirectingat.com")){
			$content_first = curl_post_data($realUrl);
			preg_match("/window.location.replace\('([^\']+)'\)/i",$content_first,$url_res);
			$realUrl = $url_res[1];
		}elseif (strripos($url,"linksynergy.com") || strripos($url,"pntrs.com") || strripos($url,"affiliatetechnology.com")){
			$content_first = curl_post_data($realUrl);
			preg_match('/<meta\s+http-equiv="Refresh"\s+content="1;url=([^\"]+)"/i',$content_first,$url_res);
			$realUrl = $url_res[1];
		}elseif (strripos($url,"belboon.de")){
			$content_first = curl_post_data($realUrl);
			preg_match('/<meta\s+http-equiv="refresh"\s+content="5;\s*URL=([^\"]+)"/i',$content_first,$url_res);
			$realUrl = $url_res[1];
		}elseif (strripos($url,"webmasterplan.com")){
			$content_first = curl_post_data($realUrl);
			preg_match('/<meta\s+http-equiv="refresh"\s+content="2;\s*URL=([^\"]+)"/i',$content_first,$url_res);
			$url_exp = explode("diurl=",$url_res[1]);
			$url_res[1] = urldecode($url_exp[1]);
			$realUrl = $url_res[1];
		}elseif(strripos($realUrl,"r.brandreward.com") || strripos($realUrl,"r.meikaiinfotech.com")){
            $tmp=explode("&url=",$realUrl);
            if(!empty($tmp[1])){
                $realUrl=urldecode($tmp[1]);
            }
        }else {
            //卡在联盟跳转页面,在请求
            if($vo['CompetitorId']==37){
                $realUrl=get_redirect_url($realUrl);
            }
        }
	}
	
	$realUrl=addslashes(urldecode($realUrl));
    $realUrl=trim($realUrl);
	
	if(empty($realUrl) || $realUrl=="false" || $realUrl=="error"){
		$sqlUp="update cp_temp_competitor_store set ErrorTime=ErrorTime+1,GoCouponUrl=null,Domain=null,DefaultUrl=null where ID={$vo['ID']}";
		$db->query($sqlUp);
		if(!empty($ip_info)){
			if($realUrl=="error"){
				if(!MEM_CACHE_DEBUG){ //如果有memcache 走memcache
					$ip_info = $cache_obj->update_data_by_get_html_res($vo['CompetitorId'], $ip_info, false);
				}else{
					$sql = "UPDATE ip_info SET FailNum=FailNum+1,LastChangeTime='".date("Y-m-d H:i:s")."'WHERE Info = '".trim($ip_info)."' and CompetitorID='".$vo['CompetitorId']."'";
					$res = $db->query($sql);
					$ip_info = get_proxy_info($vo['CompetitorId']);
					file_put_contents(DIR_IP_CONFIG.$vo['CompetitorId'].".txt", $ip_info);
				}
			}
		}
	}else{
		if(!empty($ip_info)){
			if(!MEM_CACHE_DEBUG){ //如果有memcache 走memcache
					$ip_info = $cache_obj->update_data_by_get_html_res($vo['CompetitorId'], $ip_info, true);
				}else{
					$sql = "UPDATE ip_info SET GoodCatch=GoodCatch+1,FailNum=0,LastChangeTime='".date("Y-m-d H:i:s")."'  WHERE Info = '".trim($ip_info)."' and CompetitorID='".$vo['CompetitorId']."'";
					$res = $db->query($sql);
				}
		}
	
		//domain比较函数  判断域名与url相似度 >70 可添加store
		$res = get_sim_by_two_url($vo['CompetitorId'],$vo['StoreUrl'],$realUrl);
		$realUrl = strtolower($realUrl);
		$realUrl=addslashes(trim($realUrl));
		print_r($realUrl."\n");
		if($res){
			$domain = $res;
			$defaultUrl="http://".$domain;
			$sqlUp="update cp_temp_competitor_store set  GoCouponUrl='".$realUrl."',Domain='".$domain."',DefaultUrl='".$defaultUrl."',ErrorTime=0 where ID={$vo['ID']}";
		}else{
			$sqlUp="update cp_temp_competitor_store set  GoCouponUrl='{$realUrl}',Domain='Domain Error',ErrorTime=0 where ID={$vo['ID']}";
		}
		$db->query($sqlUp);
	
	}
}


//匹配相似度 by two url & 竞争对手
function get_sim_by_two_url($competitorId,$storeUrl,$goCouponUrl){
	if(empty($competitorId) || empty($storeUrl) || empty($goCouponUrl)) return false;

    if(file_exists(dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . "lib/competitor/Class.Competitor{$competitorId}.php")){
        require_once dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . "lib/competitor/Class.Competitor{$competitorId}.php";
        //初始化类
        $class_name = "Competitor{$competitorId}";
        $competitor = new $class_name('-0-',$storeUrl);

        $preg_att = $competitor->pregSimStore;

    }else{

        if($competitorId == 6){
            //http://www.retailmenot.com/view/bcbg.com
            $preg_att = '/\/view\/(.*)/i';
        }elseif($competitorId == 7){
            //http://www.coupons.com/coupon-codes/the-peanut-shop/
            $preg_att = '/\/coupon-codes\/([^\/]+)/i';
        }elseif($competitorId == 8){
            //http://www.goodsearch.com/holiday-inn/coupons
            $preg_att = '/goodsearch\.com\/coupons\/([^\/]+)/i';
        }else if($competitorId == 9){
            //http://couponfollow.com/site/6pm.com
            $preg_att = '/\/site\/(.*)/i';
        }else if($competitorId == 11){
            //http://www.cuponation.com.au/asos-coupons
            $preg_att = '/\/([^\/]+?)-(promo|code|coupon|discount|deal|voucher)/i';
        }else if($competitorId==14){
            //http://www.thebargainavenue.com.au/interests/home-a-garden/coupons/25643-woolworths-coupons-latest-woolworths-voucher-codes-woolworths-promotional-discounts
            $preg_att = '/\/coupons\/\d+\-([^\-]+)/i';
        }else if($competitorId==15){
            $preg_att = '/\/store\/(.*)/i';
        }else if($competitorId==16){
            //http://www.thebargainavenue.com.au/interests/home-a-garden/coupons/25643-woolworths-coupons-latest-woolworths-voucher-codes-woolworths-promotional-discounts
            $preg_att = '/stores\/([^\/]+?)-(promo|code|coupon|discount)/i';
        }else if($competitorId==17){
            $preg_att = '/shops\/([^\/]+)/i';
        }else if($competitorId==18){
            $preg_att = '/vouchercodes.co.uk\/([^\/]+)/i';
        }else if($competitorId==20){
            $preg_att = '/gutschein.de\/([^\/]+)/i';
        }else if($competitorId==21){
            $preg_att = '/\/gutscheine\/([^\/]+)/i';
        }else if($competitorId==22){
            $preg_att = '/\/gutscheine\/([^\/]+)/i';
        }else if($competitorId==25){
            $preg_att = '/code-promo\/magasins\/([^\/]+)/i';
        }else if($competitorId==26){
            $preg_att = '/\/reductions-pour-(.+?).php/i';
        }else if($competitorId==28){
            $preg_att = '/\/codes-promo\/([^\/]+)/i';
        }else if($competitorId==29){
            $preg_att='/\/code-promo-([^\/]+)/i';
        }else if($competitorId==30){
            $preg_att='/\/gutscheine\/(.+)/i';
        }else if($competitorId==31){
            $preg_att='/\/gutscheinemagazin.de\/([^\/]+)/i';
        }else if($competitorId==32){
            $preg_att='/\/gutscheine\/(.+)/i';
        }else if($competitorId==33){
            $preg_att="/retailmenot.it\/([^\/\s]*)/i";
        }else if($competitorId==35){
            $preg_att="/offerte-codice-sconto-(.*?).html/i";
        }else if($competitorId==36){
            $preg_att="/offerte-codice-sconto-(.*?).html/i";
        }else if($competitorId==37){
            $preg_att="/codicepromozionalecoupon.it\/([^\/]+)/i";
        }else if($competitorId==39){
            $preg_att="/coupons\/(.*)/i";
        }else if($competitorId==40){
            $preg_att="/coupons\/(.*)/i";
        }else if($competitorId==41){
            $preg_att="/savvybeaver.ca\/(.*?)-coupons/i";
        }else if($competitorId==42){
            $preg_att="/stores\/(.*?)\//i";
        }else if($competitorId==43){
            $preg_att="/reduc.fr\/(.+)/i";
        }else if($competitorId==44){
            $preg_att="/coupons.ca\/([^\/]+)/i";
        }else if($competitorId==48){
            $preg_att="/actiepagina.nl\/([^\/]+)/i";
        }else if($competitorId==50){
            $preg_att="/\/descuentos-(.*?).html/i";
        }else if($competitorId==51){
            $preg_att="/cupones.es\/(.+)/i";
        }else if($competitorId==52){
            $preg_att="/kupon.pl\/([^\/]+)/i";
        }else if($competitorId==53){
            $preg_att="/qpony.pl\/([^\/]+)/i";
        }else if($competitorId==54){
            $preg_att="/kupony-rabatowe\/([^\/]+)/i";
        }else if($competitorId==55){
            $preg_att="/kod[y]*-promocyjn[ey]\/([^\/]+)/i";
        }else if($competitorId==56){
            $preg_att="/codigos-de-descuentos-(.*)/i";
        }else if($competitorId==57){
            $preg_att="/cuponation.es\/([^\-]+)/i";
        }else if($competitorId==58){
            $preg_att="/\/gutscheine\/([^\/]+)/i";
        }
    }


        //Store Url
	preg_match($preg_att,$storeUrl,$r);
	$store_domain = $r[1];
	//去掉后缀
    $store_domain =str_ireplace("www.","",$store_domain);
    //去除域名后缀，一般url生成根据name 不会有域名后缀
    $r_p = strripos($store_domain,".");
    if($r_p){
        $store_domain = substr($store_domain,0,$r_p);
    }
	$store_domain = preg_replace('/[^a-z|A-Z|0-9]+/',"",$store_domain); //去除所有特殊符号

        //Go Coupon Url
    $go_domain_arr = parse_url($goCouponUrl);
    if($go_domain_arr){
        $go_domain_str = $go_domain_arr['host'];
    }else{
        $go_domain_str = get_short_domain($goCouponUrl);
    }
	$go_domain =str_ireplace("www.","",$go_domain_str);
	//去除域名后缀，一般url生成根据name 不会有域名后缀
	$r_p = strripos($go_domain,".");
	if($r_p){
        $go_domain = substr($go_domain,0,$r_p);
    }
	$go_domain = preg_replace('/[^a-z|A-Z|0-9]+/',"",$go_domain); //去除所有特殊符号

	similar_text(strtolower($store_domain),strtolower($go_domain),$res);
	if($res > 70){
        return $go_domain_str;
    } else{
        return false;
    }

}


//post 数据提交
function curl_post_data($url,$ipinfo="",$data){

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.3; WOW64; rv:40.0) Gecko/20100101 Firefox/40.0');
	//post提交数据
	if(!empty($data)){

		$data = implode("&", $data);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch,CURLOPT_POSTFIELDS,$data);

	}
	$cookie_file=DIR_TMP_COOKIE.rand(1000,9999).".txt";

	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch, CURLOPT_COOKIESESSION, true );
	curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
	curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);

	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($ch, CURLOPT_TIMEOUT,60);
	if(!empty($ipinfo)){
		curl_setopt($ch,CURLOPT_PROXY,$ipinfo);
	}
	$contents = curl_exec($ch);

	curl_close($ch);
	return $contents;

}

//post 数据提交
function curl_post_get_url($url,$ipinfo="",$data){

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HEADER, TRUE);
	curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.3; WOW64; rv:40.0) Gecko/20100101 Firefox/40.0');
	//post提交数据
	if(!empty($data)){

		$data = implode("&", $data);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch,CURLOPT_POSTFIELDS,$data);

	}
	$cookie_file=DIR_TMP_COOKIE.rand(1000,9999).".txt";

	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch, CURLOPT_COOKIESESSION, true );
	curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
	curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);

	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($ch, CURLOPT_TIMEOUT,60);
	if(!empty($ipinfo)){
		curl_setopt($ch,CURLOPT_PROXY,$ipinfo);
	}
	curl_exec($ch);

	$real_url = curl_getinfo($ch,CURLINFO_EFFECTIVE_URL);
	$httpCode = curl_getinfo($ch,CURLINFO_HTTP_CODE);
	curl_close($ch);
	unlink($cookie_file);
	if($httpCode!=0){
		return get_redirect_url($real_url);
	}else{
		return "error";
	}
}
$db->close();
function get_redirect_url($url){
//	获取301、302跳转最终链接
	$header = get_headers($url, 1);
	if (strpos($header[0], '301') !== false || strpos($header[0], '302') !== false) {
		if(is_array($header['Location'])) {
			return $header['Location'][count($header['Location'])-1];
		}else{
			return $header['Location'];
		}
	}else {
		return 'error';
	}
}