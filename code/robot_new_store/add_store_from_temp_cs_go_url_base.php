<?php
/*Get Go Url*/
include_once dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . 'etc/initiate.php';

$vo['CompetitorId']=isset($_SERVER["argv"][1])?$_SERVER["argv"][1]:0;
$vo['ID']=isset($_SERVER["argv"][2])?$_SERVER["argv"][2]:0;
$vo['StoreUrl']=isset($_SERVER["argv"][3])?$_SERVER["argv"][3]:0;

set_time_limit(100);

if(!empty($vo['CompetitorId']) && !empty($vo['ID']) && !empty($vo['StoreUrl'])){
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
	$filePath=createCatchTempGoFile($vo['CompetitorId'],$vo['ID']);
	$r=vspider_get_code_file_new_store($vo['StoreUrl'], $filePath,$ip_info,true);
	$htmlContent=file_get_contents($filePath);
    //del_file($filePath);

	//检查是否爬取正确的内容
	 $ck_res = check_ip_by_get_content($vo['CompetitorId'],"store",$htmlContent);
	 if($ck_res == "no"){
	 	$ip_info = $cache_obj->update_data_by_get_html_res($vo['CompetitorId'], $ip_info, false);
	 	$sql="update cp_temp_competitor_store set ErrorTime=ErrorTime+1 where ID={$vo['ID']}";
	 	$db->query($sql);
	 	exit("--file check error!\n");
	 }else{
	 	$ip_info = $cache_obj->update_data_by_get_html_res($vo['CompetitorId'], $ip_info, true);
	 }
	
	$htmlContent=addcslashes($htmlContent, "\'");
	$url_arr=parse_url($vo['StoreUrl']);
	$url_re=parse_url($r['url']);


	//初始化数据
    $h1=$GoUrl=$PageDomainUrl='';

    if(file_exists(dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . "lib/competitor/Class.Competitor{$vo['CompetitorId']}.php")){
        require_once dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . "lib/competitor/Class.Competitor{$vo['CompetitorId']}.php";
        //初始化类
        $class_name = "Competitor{$vo['CompetitorId']}";
        $competitor = new $class_name($htmlContent,$vo['StoreUrl']);

        $arr = $competitor->getTempStoreInfo();
        extract($arr);

    }else{


        //原代码
        if($vo['CompetitorId']==6){
            preg_match_all("/<h1[^>]*>([^<]+)<\\/h1>/", $htmlContent, $matchH1,PREG_SET_ORDER);
            $h1=empty($matchH1[0])?"":deal_text($matchH1[0][1]);
            preg_match_all("/class=\"logo-wrapper js-outclick-merchant-logo\" href=\"(\/out[^\"]*)\"/", $htmlContent, $matchGo,PREG_SET_ORDER);
            $GoUrl=empty($matchGo[0])?"":$matchGo[0][1];
            if(empty($GoUrl)){
                preg_match_all("/<a href=\"(\/out[^\"]*)\"/", $htmlContent, $matchGo,PREG_SET_ORDER);
                $GoUrl=empty($matchGo[0])?"":"https://www.retailmenot.com".addslashes(trim($matchGo[0][1]));
            }
            preg_match_all("/retailmenot\.com\/view\/([^\/\"]+)/i", $vo['StoreUrl'], $matchPageDomain,PREG_SET_ORDER);
            if(!empty($matchPageDomain)){
                $PageDomainUrl=$matchPageDomain[0][1];
                $PageDomainUrl=trim($PageDomainUrl);
            }

        }else if($vo['CompetitorId']==7){
            preg_match_all("/<h1[^>]*>([^<]+)<\\/h1>/", $htmlContent, $matchH1,PREG_SET_ORDER);
            $h1=empty($matchH1[0])?"":$matchH1[0][1];
            preg_match_all("/<img class=\"js-redirect\" data-url=\"([^\"]*)\"/", $htmlContent, $matchGo,PREG_SET_ORDER);
            $GoUrl=empty($matchGo[0])?"":$matchGo[0][1];
            if(empty($GoUrl)){
                preg_match_all("/class=\"ccpod\s*[large|medium]{0,7}\s*[codes|sales]{0,10}\s*[free]{0,5}?\s*\"\s*podid=\"CCPOD([^_]+)_2/", $htmlContent, $matchGo,PREG_SET_ORDER);
                $GoUrl=empty($matchGo[0])?"":$matchGo[0][1];
                if(!empty($GoUrl)){
                    $GoUrl="http://www.coupons.com/coupon-codes/go/rs?k=".trim($GoUrl)."_2&pos=1";
                }
            }else{
                $GoUrl="http://www.coupons.com".trim($GoUrl);
            }

        }else if($vo['CompetitorId']==8){
            preg_match_all("/<h1[^>]*>([^<]+)<\\/h1>/", $htmlContent, $matchH1,PREG_SET_ORDER);
            $h1=empty($matchH1[0])?"":$matchH1[0][1];
            preg_match_all("/<a href=\"([^\"]*)\" class=\"title\" data-js=\"merchant-link\"/", $htmlContent, $matchGo,PREG_SET_ORDER);
            $GoUrl=empty($matchGo[0])?"":"https://www.goodsearch.com".$matchGo[0][1];
            if(empty($GoUrl)){
                preg_match_all("/<li\s*class=\"deal-item\s*filter-coupon\s*[filter\-promo\-code|filter\-deal]{0,18}\s*[filter\-online]{0,18}\"\s*data-deal-id=\"[^\"]+\"[\\w\\W]*data-deal-url=\"([^\"]+)\"/", $htmlContent, $matchGo,PREG_SET_ORDER);
                $GoUrl=empty($matchGo[0])?"":$matchGo[0][1];
            }

        }else if($vo['CompetitorId']==9){
            preg_match_all("/<h1[^>]*>([^<]+)<\\/h1>/", $htmlContent, $matchH1,PREG_SET_ORDER);
            $h1=empty($matchH1[0])?"":deal_text($matchH1[0][1]);
            preg_match_all("/<a href=\"([^\"]*)\" rel=\"nofollow\" title=\"[^\"]*\">[\\w\\W]{0,100}<img class=\"brandlogo\"/", $htmlContent, $matchGo,PREG_SET_ORDER);
            $GoUrl=empty($matchGo[0])?"":$matchGo[0][1];
            preg_match_all('/<a class="icon link" rel="nofollow" href="[^\"]+">Visit\s+([\w\.\-]+[\.]+[\w\.\-]+)<\/a>/', $htmlContent, $PageDomainUrl,PREG_SET_ORDER);
            $PageDomainUrl=empty($PageDomainUrl)?"":$PageDomainUrl[0][1];
            if(empty($GoUrl)){

                preg_match_all("/<a href=\"([^\"]*)\" class=\"couponclick\"", $htmlContent, $matchGo,PREG_SET_ORDER);
                $GoUrl=empty($matchGo[0])?"":$matchGo[0][1];
                $GoUrl=str_replace("&amp;", "&", $GoUrl);
                $GoUrl="https://couponfollow.com".$GoUrl;
            }


        }else if($vo['CompetitorId']==11){
            preg_match_all("/>([^<]+)<\/h1>/", $htmlContent, $matchH1,PREG_SET_ORDER);
            $h1=empty($matchH1[0])?"":deal_text($matchH1[0][1]);
            preg_match_all("/<span\s+data-slug=\"([^\"]+)\"\s+class=\"hover cn-data-link\"/", $htmlContent, $matchGo,PREG_SET_ORDER);
            $GoUrl=empty($matchGo[0])?"":'https://www.cuponation.com.au/redirect-to?url='.$matchGo[0][1];
            if(empty($GoUrl)){
                preg_match_all("/data-slug=\"([^\"]+)\"/", $htmlContent, $matchGo,PREG_SET_ORDER);
                $GoUrl=empty($matchGo[0])?"":$matchGo[0][1];
                $GoUrl=str_replace("&amp;", "&", $GoUrl);
                $GoUrl='https://www.cuponation.com.au/redirect-to?url='.$GoUrl;
            }

        }else if($vo['CompetitorId']==14){
            preg_match_all("/<h1 itemprop=\"name\">([^<]+)<\/h1>/", $htmlContent, $matchH1);
            $h1=empty($matchH1[0])?"":deal_text($matchH1[1][0]);
//go_url
            preg_match_all("/<a href=\"([^\"]+)\" target=\"_blank\" rel=\"nofollow\">\s*<div class=\"stealbuttondec\"/", $htmlContent, $matchGo,PREG_SET_ORDER);
            $GoUrl=empty($matchGo[0])?"":'http://www.thebargainavenue.com.au'.$matchGo[0][1];
            //    pageDomainUrl
            preg_match_all("/buttondec\">Steal the Deal<\/div>\s+<strong>([^<]+)/", $htmlContent, $matchPageDomain,PREG_SET_ORDER);
            if(!empty($matchPageDomain[0])){
                $PageDomainUrl=$matchPageDomain[0][1];
                $PageDomainUrl=trim($PageDomainUrl);
            }else{
                preg_match_all("/buttondec\">Get Coupon &amp; Buy<\/div>\s+<strong>([^<]+)/", $htmlContent, $matchPageDomain,PREG_SET_ORDER);
                if(!empty($matchPageDomain[0])) {
                    $PageDomainUrl = $matchPageDomain[0][1];
                    $PageDomainUrl = trim($PageDomainUrl);
                }
            }

        }else if($vo['CompetitorId']==15){
//h1
            preg_match_all("/<h1>([^<]+)<\/h1>/", $htmlContent, $matchH1);
            $h1=empty($matchH1[0])?"":deal_text($matchH1[1][0]);
//go_url
            $matchGo = Selector::select($htmlContent,"*//a[@class=\"btn btn-success store-url btn-xs\"]/@href");
            $GoUrl=empty($matchGo)?"":$matchGo;
//    pageDomainUrl
            $matchPageDomain = Selector::select($htmlContent,"*//a[@class=\"btn btn-success store-url btn-xs\"]");
            if(!empty($matchPageDomain)){
                $PageDomainUrl=trim(strip_tags($matchPageDomain));
            }

        }else if($vo['CompetitorId']==16){

            //h1
            preg_match_all("/<h1>([^<]+)<\/h1>/", $htmlContent, $matchH1);
            $h1=empty($matchH1[0])?"":deal_text($matchH1[1][0]);
//go_url
            preg_match_all("/class=\"store-url\"><a href=\"[^\"]+\" target=\"_blank\">([^<]+)<\/a><\/p>/", $htmlContent, $matchGo,PREG_SET_ORDER);
            $GoUrl=empty($matchGo[0])?"":$matchGo[0][1];
//    pageDomainUrl
            if(!empty($GoUrl)){
                $parse_PageDomainUrl=parse_url($GoUrl);
                $PageDomainUrl=$parse_PageDomainUrl['host'];
            }

        }else if($vo['CompetitorId']==17){
            //h1
            preg_match_all("/<h1>([^<]+)<\/h1>/", $htmlContent, $matchH1);
            $h1=empty($matchH1[0])?"":deal_text($matchH1[1][0]);
//go_url
            $matchGo = Selector::select($htmlContent,'//*[@id="sidebar"]/div/a/@href');
            $GoUrl=empty($matchGo)?"":'https://www.groupon.co.uk'.$matchGo;
//    pageDomainUrl
            if(!empty($GoUrl)){
                $PageDomainUrl=$GoUrl;
                $pagedomain_list=explode('/',$GoUrl);
                $PageDomainUrl=strtolower($pagedomain_list[count($pagedomain_list)-1]);
            }

        }else if($vo['CompetitorId']==18){
            //h1
            preg_match_all("/<h1 itemprop=\"name\">([^<]+)<\/h1>/", $htmlContent, $matchH1);
            $h1=empty($matchH1[0])?"":deal_text($matchH1[1][0]);
//go_url
            preg_match_all("/href=\"([^\"]+)\" target=\"_blank\" class=\"btn btn-tiny btn-icon btn-merchant-visit-site\"/", $htmlContent, $matchGo,PREG_SET_ORDER);
            $GoUrl=empty($matchGo[0])?"":'https://www.vouchercodes.co.uk'.$matchGo[0][1];

        }else if($vo['CompetitorId']==19){
            //h1
            preg_match_all("/<h1>(.*?)<\/h1>/", $htmlContent, $matchH1);
            $h1=empty($matchH1[0])?"":deal_text($matchH1[1][0]);
//go_url
            preg_match_all("/class=\"InfoHeaderMerchant-cta Offer-cta\" href=\"([^\"]+)\"/", $htmlContent, $matchGo,PREG_SET_ORDER);
            $GoUrl=empty($matchGo[0])?"":'https://www.myvouchercodes.co.uk'.$matchGo[0][1];

//    pageDomainUrl
            preg_match_all("/<span class=\"dontshow\">https:\/\/<\/span>(.*?)<\/span>/",$htmlContent,$matchDomain);
            if(!empty($matchDomain[0])){
                $PageDomainUrl=$matchDomain[1][0];
            }

        }else if($vo['CompetitorId']==20){
//h1
            preg_match_all("/<h1[^>]+>(.+?)<\/h1>/", $htmlContent, $matchH1);
            $h1=empty($matchH1[0])?"":deal_text($matchH1[1][0]);
//go_url
            preg_match_all("/data-nofollow-url=\"([^\"]+?)\"/", $htmlContent, $matchGo,PREG_SET_ORDER);
            $GoUrl=empty($matchGo[0])?"":'http://www.gutschein.de'.$matchGo[0][1];

        }else if($vo['CompetitorId']==21){
            //h1
            preg_match_all("/<h1>(.+?)<\/h1>/", $htmlContent, $matchH1);
            $h1=empty($matchH1[0])?"":deal_text($matchH1[1][0]);
//go_url
            preg_match_all("/class=\"shoplogo\"><a href=\"([^\"\']+)\"/", $htmlContent, $matchGo,PREG_SET_ORDER);
            $GoUrl=empty($matchGo[0])?"":$matchGo[0][1];

        }else if($vo['CompetitorId']==22){
//h1
            preg_match_all("/<h1 class=\"wg-module-heading\">([^<]+)<\/h1>/", $htmlContent, $matchH1);
            $h1=empty($matchH1[0])?"":deal_text($matchH1[1][0]);
//go_url
            preg_match_all("/<span class=\"wg-anchor\".*title=\"([^\"]+)\"/", $htmlContent, $matchGo,PREG_SET_ORDER);
            $GoUrl=empty($matchGo[0])?"":'http://'.$matchGo[0][1];
//    pageDomainUrl
            $url=parse_url($GoUrl);
            $PageDomainUrl=$url['host'];


        }else if($vo['CompetitorId']==23){
//h1
            preg_match_all("/<h1[^>]+>(.*?)<\/h1>/", $htmlContent, $matchH1);
            $h1=empty($matchH1[0])?"":deal_text($matchH1[1][0]);
//go_url
            $matchGo = Selector::select($htmlContent,"//a[@class=\"visit-shop hidden-xs\"]/@href");
            $GoUrl = empty($matchGo[0])?'':'https://www.rabattcode.de'.$matchGo;


        }else if($vo['CompetitorId']==24){
//h1
            preg_match_all("/<h1[^>]+>(.*?)<\/h1>/", $htmlContent, $matchH1);
            $h1=empty($matchH1[0])?"":deal_text($matchH1[1][0]);
//go_url
            preg_match_all("/class=\"side-nav-image\"><a href=\"([^\"]+)\"/", $htmlContent, $matchGo,PREG_SET_ORDER);
            $GoUrl=empty($matchGo[0])?"":'https://www.giftcardgranny.com'.$matchGo[0][1];
//    pageDomainUrl
            preg_match_all("/>Shop at\s([^<]+)<\/a><\/li>/", $htmlContent, $matchPageDomain,PREG_SET_ORDER);
            $PageDomainUrl=empty($matchPageDomain[0])?"":addslashes(strtolower(trim($matchPageDomain[0][1])));

        }else if($vo['CompetitorId']==25){
            preg_match_all("/<h1[^>]*>([^<]+)<\/h1>/", $htmlContent, $matchH1,PREG_SET_ORDER);
            $h1=empty($matchH1[0])?"":deal_text($matchH1[0][1]);
            preg_match_all("/data-affiliateurl=\"(\/code-promo\/magasins\/click\/[^\"]+?)\"/", $htmlContent, $matchGo,PREG_SET_ORDER);
            $GoUrl=empty($matchGo[0])?"":'https://www.groupon.fr'.$matchGo[0][1];
            //pageDomainUrl
            preg_match_all("/data-bhw=\"StartShoppingLink\" rel=\"nofollow\">Achetez\s+([^\s]+?)\s+<span/", $htmlContent, $matchPageDomain,PREG_SET_ORDER);
            $PageDomainUrl=empty($matchPageDomain[0])?"":addslashes(strtolower(trim($matchPageDomain[0][1])));

        }else if($vo['CompetitorId']==26){
//h1
            preg_match_all("/<h1[^<]+>(.*?)<\/h1>/", $htmlContent, $matchH1);
            $h1=empty($matchH1[0])?"":deal_text($matchH1[1][0]);
//go_url
            preg_match_all("/data-out=\"{&quot;r&quot;:1,&quot;m&quot;:(\d+)/", $htmlContent, $matchGo,PREG_SET_ORDER);
            $GoUrl=empty($matchGo[0])?"":'http://www.ma-reduc.com/out/merchant/'.$matchGo[0][1];

        }else if($vo['CompetitorId']==28){
//h1
            preg_match_all("/<h1[^<]*>(.*?)<\/h1>/", $htmlContent, $matchH1);
            $h1=empty($matchH1[0])?"":deal_text($matchH1[1][0]);
//go_url
            preg_match_all("/<div class=\"mer_pic\">\s+<a href=\"(.*?)\"[^>]+>\s+<img src=\"([^\"]+)\"/", $htmlContent, $matchGo,PREG_SET_ORDER);
            $GoUrl=empty($matchGo[0])?"":'https://www.frcodespromo.com'.$matchGo[0][1];

        }else if($vo['CompetitorId']==29){
//h1
            preg_match_all("/<h1[^>]*>(.*?)<\/h1>/", $htmlContent, $matchH1);
            $h1=empty($matchH1[0])?"":deal_text($matchH1[1][0]);
//go_url
            preg_match_all("/class=\"lexpress-page-header-img img-centered\" data-slug=\"([^\"\']+)\"/", $htmlContent, $matchGo,PREG_SET_ORDER);
            $GoUrl=empty($matchGo[0])?"":'http://codepromo.lexpress.fr/redirect-to?url='.$matchGo[0][1];

        }else if($vo['CompetitorId']==30){
//h1
            preg_match_all("/<h1[^>]*>(.*?)<\/h1>/", $htmlContent, $matchH1);
            $h1=empty($matchH1[0])?"":deal_text($matchH1[1][0]);
//go_url
            preg_match_all("/id=\"jumplink-4\" href=\"([^\"]+)\"/", $htmlContent, $matchGo,PREG_SET_ORDER);
            $GoUrl=empty($matchGo[0])?"":$matchGo[0][1];

        }else if($vo['CompetitorId']==31){
//h1
            preg_match_all("/<h1[^>]*>(.*?)<\/h1>/", $htmlContent, $matchH1);
            $h1=empty($matchH1[0])?"":deal_text($matchH1[1][0]);
//go_url
            preg_match_all("/href=\"([^\"]+)\" target=_blank>\s*<img src=\"[^\"]+\" class=\"logo medium\"/", $htmlContent, $matchGo,PREG_SET_ORDER);
            $GoUrl=empty($matchGo[0])?"":'https://www.gutscheinemagazin.de'.$matchGo[0][1];

            //    pageDomainUrl
            preg_match_all("/window.shopdomain=\"([^\"]+)\"/", $htmlContent, $matchPageDomain,PREG_SET_ORDER);
            $PageDomainUrl=empty($matchPageDomain[0])?'':trim(strtolower($matchPageDomain[0][1]));

        }else if($vo['CompetitorId']==32){
//h1
            preg_match_all("/<h1 class=\"title\">([\s\S]*?)<\/h1>/", $htmlContent, $matchH1);
            $h1=empty($matchH1[0])?"":deal_text($matchH1[1][0]);
//go_url
            preg_match_all("/class=\"link-to text-center\"\s+?href=\"([^\"]+)\"/", $htmlContent, $matchGo,PREG_SET_ORDER);
            $GoUrl=empty($matchGo[0])?"":$matchGo[0][1];

//    pageDomainUrl
            preg_match_all("/<a target=\"_blank\" rel=\"nofollow\" href=\"(.*?)\">\s+<span>/", $htmlContent, $matchPageDomain,PREG_SET_ORDER);
            if(!empty($matchPageDomain[0])){
                $url_arr=parse_url($matchPageDomain[0][1]);
                if(!empty($url_arr['host'])){
                    $PageDomainUrl=strtolower($url_arr['host']);
                }
            }

        }else if($vo['CompetitorId']==33){
//h1
            preg_match_all("/class=\"merchant-title\">(.*?)<\/h1>/", $htmlContent, $matchH1);
            $h1=empty($matchH1[0])?"":deal_text($matchH1[1][0]);
//go_url
            preg_match_all("/class=\"col-merchant-logo merchant-logo hidden-xs\">\s+<a href=\"(.*?)\"/", $htmlContent, $matchGo,PREG_SET_ORDER);
            $GoUrl=empty($matchGo[0])?"":'http://www.retailmenot.it'.$matchGo[0][1];

        }else if($vo['CompetitorId']==35){
//h1
            preg_match_all("/\"codiceright\">\s*<h2>(.*?)<\/h2>/", $htmlContent, $matchH1);
            $h1=empty($matchH1[0])?"":deal_text($matchH1[1][0]);
//go_url
            preg_match_all("/class=\"euit\" href=\"([^\"]+)\"/", $htmlContent, $matchGo,PREG_SET_ORDER);
            $GoUrl=empty($matchGo[0])?"":'http://www.piucodicisconto.com/'.$matchGo[0][1];

            preg_match_all("/class=\"euit\" href=\"[^\"]+\" target=\"_blank\" rel=\"nofollow\">([^<]*?)<\/a>/",$htmlContent,$matchPageDomain);
            if(!empty($matchPageDomain[0])){
                $tmp=parse_url(trim($matchPageDomain[1][0]));
                $PageDomainUrl=$tmp['host'];
            }

        }else if($vo['CompetitorId']==36){
//h1
            preg_match_all("/<h1[^>]+>(.*?)<\/h1>/", $htmlContent, $matchH1);
            $h1=empty($matchH1[0])?"":deal_text($matchH1[1][0]);
//go_url
            preg_match_all("/class=\"ilcorrieredellasera-retailer-logo\" data-slug=\"([^\"]+)\">/", $htmlContent, $matchGo,PREG_SET_ORDER);
            $GoUrl=empty($matchGo[0])?"":'http://sconti.corriere.it/redirect-to?url='.$matchGo[0][1];

        }else if($vo['CompetitorId']==37){
//h1
            preg_match_all("/<h1>(.*?)<\/h1>/", $htmlContent, $matchH1);
            $h1=empty($matchH1[0])?"":deal_text($matchH1[1][0]);
//go_url
            preg_match_all("/href=\"(.*?)\" target=\"_blank\" rel=\"nofollow\">\s*<img id=\"bigThumb\"/", $htmlContent, $matchGo,PREG_SET_ORDER);
            $GoUrl=empty($matchGo[0])?"":$matchGo[0][1];

        }else if($vo['CompetitorId']==39){
//h1
            preg_match_all("/<h1>(.*?)<\/h1>/", $htmlContent, $matchH1);
            $h1=empty($matchH1[0])?"":deal_text($matchH1[1][0]);
//go_url
            preg_match_all("/class=\"module logo-wrapper js-outclick-merchant-logo\" href=\"([^\"]+)\" title=\"Shop (.*?)\"/", $htmlContent, $matchGo,PREG_SET_ORDER);
            $GoUrl=empty($matchGo[0])?"":"http://www.retailmenot.ca".$matchGo[0][1];

            if(!empty($matchGo[0])){
                $PageDomainUrl=trim($matchGo[0][2]);
            }

        }else if($vo['CompetitorId']==40){
//h1
            preg_match_all("/class=\"title mb4\">(.*?)<\/h1>/", $htmlContent, $matchH1);
            $h1=empty($matchH1[0])?"":deal_text($matchH1[1][0]);
//go_url
            preg_match_all("/href=\"(.*?)\"\s+?class=\"logo-icon\"\s+?title=\"Visit (.*?)\"/", $htmlContent, $matchGo,PREG_SET_ORDER);
            $GoUrl=empty($matchGo[0])?"":$matchGo[0][1];

            if(!empty($matchGo[0])){
                $PageDomainUrl=trim($matchGo[0][2]);
            }

        }else if($vo['CompetitorId']==41){
//h1
            preg_match_all("/class=\"title\">([\s\S]*?)<\/h1>/", $htmlContent, $matchH1);
            $h1=empty($matchH1[0])?"":deal_text($matchH1[1][0]);
//go_url
            preg_match_all("/class=\"logo\" href=\"(.*?)\"/", $htmlContent, $matchGo,PREG_SET_ORDER);
            $GoUrl=empty($matchGo[0])?"":'https://www.savvybeaver.ca'.$matchGo[0][1];

        }else if($vo['CompetitorId']==42){
//h1
            preg_match_all("/class=\"entry-title\">([\s\S]*?)<\/h1>/", $htmlContent, $matchH1);
            $h1=empty($matchH1[0])?"":deal_text($matchH1[1][0]);
//go_url
            preg_match_all("/class=\"store-url\"><a href=\"(.*?)\" target=\"_blank\">(.*?)<\/a>/", $htmlContent, $matchGo,PREG_SET_ORDER);
            $GoUrl=empty($matchGo[0])?"":$matchGo[0][1];

            if(!empty($matchGo[0])){
                $PageDomainUrl=trim($matchGo[0][2]);
            }

        }else if($vo['CompetitorId']==43){
//h1
            preg_match_all("/<h1 class=\"h2\">(.*?)<\/h1>/", $htmlContent, $matchH1);
            $h1=empty($matchH1[0])?"":deal_text($matchH1[1][0]);
//go_url
            preg_match_all("/class=\"h2\">\s+<a href=\"([^\"]+)\" target=\"_blank\"/", $htmlContent, $matchGo,PREG_SET_ORDER);
            $GoUrl=empty($matchGo[0])?"":$matchGo[0][1];

            if(!empty($matchGo[0])){
                $PageDomainUrl=str_replace("https://",'',trim($matchGo[0][1]));
                $PageDomainUrl=str_replace("http://",'',$PageDomainUrl);
            }

        }else if($vo['CompetitorId']==44){
//h1
            preg_match_all("/<h1>(.*?)<\/h1>/", $htmlContent, $matchH1);
            $h1=empty($matchH1[0])?"":deal_text($matchH1[1][0]);
//go_url
            preg_match_all("/type=\"text\" value=\"(.*?)\"/", $htmlContent, $matchGo,PREG_SET_ORDER);
            $GoUrl=empty($matchGo[0])?"":$matchGo[0][1];

            if(!empty($matchGo[0])){
                $tmp_arr=parse_url($matchGo[0][1]);
                $PageDomainUrl=$tmp_arr['host'];
            }

        }else if($vo['CompetitorId']==45){
//h1
            preg_match_all("/<h1 class=\"ilcorrieredellasera-title\">(.*?)<\/h1>/", $htmlContent, $matchH1);
            $h1=empty($matchH1[0])?"":deal_text($matchH1[1][0]);
//go_url
            preg_match_all("/class=\"ilcorrieredellasera-retailer-logo\" data-slug=\"(.*?)\"/", $htmlContent, $matchGo,PREG_SET_ORDER);
            $GoUrl=empty($matchGo[0])?"":"http://sconti.corriere.it/redirect-to?url=".$matchGo[0][1];

        }else if($vo['CompetitorId']==46){
//h1
            preg_match_all("/<h1 class=\"check\">(.*?)<\/h1>/", $htmlContent, $matchH1);
            $h1=empty($matchH1[0])?"":deal_text($matchH1[1][0]);
//go_url
            preg_match_all("/class=\"visit-shop\">\s+?<a href=\"(.*?)\"/", $htmlContent, $matchGo,PREG_SET_ORDER);
            $GoUrl=empty($matchGo[0])?"":$matchGo[0][1];

            preg_match_all("/Ga naar (.*?)\s*?<\/a>/i",$htmlContent,$matchPageDomain,PREG_SET_ORDER);
            if(!empty($matchPageDomain[0])){
                $PageDomainUrl=trim($matchPageDomain[0][1]);
            }

        }else if($vo['CompetitorId']==47){
//h1
            preg_match_all("/<h1>(.*?)<\/h1>/", $htmlContent, $matchH1);
            $h1=empty($matchH1[0])?"":deal_text($matchH1[1][0]);
//go_url
            preg_match_all("/href=\"(.*?)\"><img class=\"radiusImg\"/", $htmlContent, $matchGo,PREG_SET_ORDER);
            $GoUrl=empty($matchGo[0])?"":$matchGo[0][1];

            preg_match_all("/<h4>Officiële website<\/h4>\s+?<a.*?>(.*?)<\/a>/i",$htmlContent,$matchPageDomain,PREG_SET_ORDER);
            if(!empty($matchPageDomain[0])){
                $PageDomainUrl=trim($matchPageDomain[0][1]);
            }

        }else if($vo['CompetitorId']==48){
//h1
            preg_match_all("/<h1 class=\"merchant-title\">(.*?)<\/h1>/", $htmlContent, $matchH1);
            $h1=empty($matchH1[0])?"":deal_text($matchH1[1][0]);
//go_url
            preg_match_all("/\"merchant-logo-title merchant-logo visible-xs\">\s+?<a href=\"(.*?)\"/", $htmlContent, $matchGo,PREG_SET_ORDER);
            $GoUrl=empty($matchGo[0])?"":'http://www.actiepagina.nl'.$matchGo[0][1];

        }else if($vo['CompetitorId']==49){
//h1
            preg_match_all("/<h1 class=\"nmt\">(.*?)<\/h1>/", $htmlContent, $matchH1);
            $h1=empty($matchH1[0])?"":deal_text($matchH1[1][0]);
//go_url
            preg_match_all("/data-track-affnet=\"[^\"]+\" href=\"\/visit\/([^\"]+)\"/", $htmlContent, $matchGo,PREG_SET_ORDER);
            $GoUrl=empty($matchGo[0])?"":'https://mrkortingscode.nl/visit/'.$matchGo[0][1];

            preg_match_all("/rel=\"nofollow\" target=\"_blank\">\s+?(.*?)\s+?<\/a>/i",$htmlContent,$matchPageDomain,PREG_SET_ORDER);
            if(!empty($matchPageDomain[0])){
                $PageDomainUrl=trim($matchPageDomain[0][1]);
            }

        }else if($vo['CompetitorId']==50){
//h1
            preg_match_all("/<h1 class=\"page-title\" itemprop=\"alternateName\">(.*?)<\/h1>/", $htmlContent, $matchH1);
            $h1=empty($matchH1[0])?"":deal_text($matchH1[1][0]);
//go_url
            preg_match_all("/class=\"shop-header-logo\">\s+<a class=\"fallback_link\" data-shop=\"[^\"]+\" href=\"([^\"]+)\"/", $htmlContent, $matchGo,PREG_SET_ORDER);
            $GoUrl=empty($matchGo[0])?"":'https://cupon.es'.$matchGo[0][1];

        }else if($vo['CompetitorId']==51){
//h1
            preg_match_all("/<h1 class=\"h2\">(.*?)<\/h1>/", $htmlContent, $matchH1);
            $h1=empty($matchH1[0])?"":deal_text($matchH1[1][0]);
//go_url
            preg_match_all("/data-redirect=\"(.*?)\"/", $htmlContent, $matchGo,PREG_SET_ORDER);
            $GoUrl=empty($matchGo[0])?"":$matchGo[0][1];

        }else if($vo['CompetitorId']==52){
//h1
            preg_match_all("/<h1 class=\"page-title\" itemprop=\"alternateName\">(.*?)<\/h1>/", $htmlContent, $matchH1);
            $h1=empty($matchH1[0])?"":deal_text($matchH1[1][0]);
//go_url
            preg_match_all("/class=\"shop-header-logo\">\s+<a class=\"fallback_link\" data-shop=\"[^\"]+\" href=\"([^\"]+)\"/", $htmlContent, $matchGo,PREG_SET_ORDER);
            $GoUrl=empty($matchGo[0])?"":'https://kupon.pl'.$matchGo[0][1];

        }else if($vo['CompetitorId']==53){
//h1
            preg_match_all("/<h2>([\s\S]+?)<\/h2>/", $htmlContent, $matchH1);
            $h1=empty($matchH1[0])?"":deal_text($matchH1[1][0]);
//go_url
            preg_match_all("/data-id=\"(\d+)\"/", $htmlContent, $matchGo,PREG_SET_ORDER);
            $GoUrl=empty($matchGo[0])?"":'https://www.qpony.pl/redirect/'.$matchGo[0][1];

        }else if($vo['CompetitorId']==54){
            //h1
            preg_match_all("/<h1[^>]*>([\s\S]*?)<\/h1>/", $htmlContent, $matchH1);
            $h1=empty($matchH1[0])?"":deal_text($matchH1[1][0]);
//go_url
            preg_match_all("/class=\"btn-neutral btn\" rel=\"nofollow\" target=\"_blank\" href=\"(.*?)\"/", $htmlContent, $matchGo,PREG_SET_ORDER);
            $GoUrl=empty($matchGo[0])?"":$matchGo[0][1];

        }else if($vo['CompetitorId']==55){
//h1
            preg_match_all("/<h1[^>]*>([\s\S]*?)<\/h1>/", $htmlContent, $matchH1);
            $h1=empty($matchH1[0])?"":deal_text($matchH1[1][0]);
//go_url
            preg_match_all("/data-href=\"(\/r\/\d+)\"/", $htmlContent, $matchGo,PREG_SET_ORDER);
            $GoUrl=empty($matchGo[0])?"":'http://alerabat.com'.$matchGo[0][1];

        }else if($vo['CompetitorId']==56){
//h1
            preg_match_all("/<h1[^>]*>([\s\S]*?)<\/h1>/", $htmlContent, $matchH1);
            $h1=empty($matchH1[0])?"":deal_text($matchH1[1][0]);
//go_url
            preg_match_all("/rel=\"nofollow,noindex\" href=\"(.*?)\"><img/", $htmlContent, $matchGo,PREG_SET_ORDER);
            $GoUrl=empty($matchGo[0])?"":'http://www.codigosdescuentospromocionales.es'.$matchGo[0][1];

        }else if($vo['CompetitorId']==57){
//h1
            preg_match_all("/<h1[^>]*>([\s\S]*?)<\/h1>/", $htmlContent, $matchH1);
            $h1=empty($matchH1[0])?"":deal_text($matchH1[1][0]);
//go_url
            preg_match_all("/data-slug=\"(.*?)\" class=\"cn-retailer-logo-image\"/", $htmlContent, $matchGo,PREG_SET_ORDER);
            $GoUrl=empty($matchGo[0])?"":'http://www.cuponation.es/redirect-to?url='.$matchGo[0][1];

        }else if($vo['CompetitorId']==58){
//h1
            preg_match_all("/<h1[^>]*>([\s\S]*?)<\/h1>/", $htmlContent, $matchH1);
            $h1=empty($matchH1[0])?"":deal_text($matchH1[1][0]);
//go_url
            preg_match_all("/class=\"mer_pic\">\s+<a title=\".*?\" href='(.*?)'/", $htmlContent, $matchGo,PREG_SET_ORDER);
            $GoUrl=empty($matchGo[0])?"":'http://de.fyvor.com'.$matchGo[0][1];

            preg_match_all("/Zu\s+([^\s]+?)\s+gehen/i",$htmlContent,$matchPageDomain,PREG_SET_ORDER);
            if(!empty($matchPageDomain[0])){
                $PageDomainUrl=trim($matchPageDomain[0][1]);
            }
        }


    }
	


    if(empty($PageDomainUrl)){
        $sql="update cp_temp_competitor_store set  H1='".$h1."',GoUrl='".$GoUrl."',ErrorTime=0 where ID={$vo['ID']}";
    }else{
        $sql="update cp_temp_competitor_store set  H1='".$h1."',GoUrl='".$GoUrl."',DefaultUrl='http://".$PageDomainUrl."',Domain='".$PageDomainUrl."',PageDomainUrl='".$PageDomainUrl."',ErrorTime=0 where ID={$vo['ID']}";
    }
    $db->query($sql);

}
$db->close();
