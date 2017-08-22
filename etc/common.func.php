<?php

function convert_coin($coin_str){
    //iso-8859-1特殊符号转为html实体
    $map = array(
        chr(0x8A) => chr(0xA9),
        chr(0x8C) => chr(0xA6),
        chr(0x8D) => chr(0xAB),
        chr(0x8E) => chr(0xAE),
        chr(0x8F) => chr(0xAC),
        chr(0x9C) => chr(0xB6),
        chr(0x9D) => chr(0xBB),
        chr(0xA1) => chr(0xB7),
        chr(0xA5) => chr(0xA1),
        chr(0xBC) => chr(0xA5),
        chr(0x9F) => chr(0xBC),
        chr(0xB9) => chr(0xB1),
        chr(0x9A) => chr(0xB9),
        chr(0xBE) => chr(0xB5),
        chr(0x9E) => chr(0xBE),
        chr(0x80) => '&euro;',
        chr(0x82) => '&sbquo;',
        chr(0x84) => '&bdquo;',
        chr(0x85) => '&hellip;',
        chr(0x86) => '&dagger;',
        chr(0x87) => '&Dagger;',
        chr(0x89) => '&permil;',
        chr(0x8B) => '&lsaquo;',
        chr(0x91) => '&lsquo;',
        chr(0x92) => '&rsquo;',
        chr(0x93) => '&ldquo;',
        chr(0x94) => '&rdquo;',
        chr(0x95) => '&bull;',
        chr(0x96) => '&ndash;',
        chr(0x97) => '&mdash;',
        chr(0x99) => '&trade;',
        chr(0x9B) => '&rsquo;',
        chr(0xA6) => '&brvbar;',
        chr(0xA9) => '&copy;',
        chr(0xAB) => '&laquo;',
        chr(0xAE) => '&reg;',
        chr(0xB1) => '&plusmn;',
        chr(0xB5) => '&micro;',
        chr(0xB6) => '&para;',
        chr(0xB7) => '&middot;',
        chr(0xBB) => '&raquo;',
    );

    return strtr($coin_str, $map);
}


//获取代理信息
function get_proxy_info($cid){
	$db_obj_tmp = new Mysql(DB_NAME, DB_HOST, DB_USER, DB_PWD);
	$num=20;
	$sql = "SELECT * FROM ip_info WHERE CompetitorID=".$cid." and FailNum < ".$num." and Status='active' order by ID asc LIMIT 1";
	$query_res = $db_obj_tmp->getRows($sql);
	$rt_res = "";

	if(empty($query_res)){
		$sql = "SELECT * FROM ip_info WHERE CompetitorID=".$cid." and FailNum < ".$num." and Status='deleted' order by ID asc LIMIT 1";
		$query_res = $db_obj_tmp->getRows($sql);
		if(empty($query_res)){
			$rt_res = "";
		}else{
			$rt_res =$query_res[0]['Info'];
		}
	}else{
		$rt_res =$query_res[0]['Info'];
	}
	$db_obj_tmp->close();
	return $rt_res;
}

function get_proxy_info_by_cid($cid,$ip_link = IP_LINK){
	$db_obj_tmp = new Mysql(DB_NAME, DB_HOST, DB_USER, DB_PWD);
	$sqlCompetitor="select ID from cp_competitor where IsCatch=1 and Status='active' and IPAuto=2";
	$query_competitor=$db_obj_tmp->getRows($sqlCompetitor);
	$res = file_get_contents($ip_link);
	
	if(!empty($res)){
		$sqli=$sqlipre="insert ignore into cp_ip_info(`CompetitorID`,`Info`,`AddTime`,`FailNum`,`Status`) values ";
		$i=0;
		$arr = explode("\n", $res);
		foreach($arr as $ip){
			$pattern = "/[^0-9.:]/";
			$ip = preg_replace($pattern, "", $ip);
			
			if(empty($ip)) continue;
			foreach ($query_competitor as $vo){
				$i++;
				$sqli .= "('".$vo['ID']."','".$ip."','".date("Y-m-d H:i:s")."',0,'normol'),";
				if($i%1000==0){
					$sqli=substr($sqli,0,strlen($sqli)-1);
					$sqli.=";";
					$db_obj_tmp->query($sqli);
					$sqli=$sqlipre;
				}
			}
		}
		if($sqli!=$sqlipre){
			$sqli=substr($sqli,0,strlen($sqli)-1);
			$sqli.=";";
			$res = $db_obj_tmp->query($sqli);
		}
	}
	$db_obj_tmp->close();
}

function my_trim($str)
{
	$str = trim($str);
	$str = str_replace("\r", "", $str);
	$str = str_replace("\n", "", $str);
	$str = str_replace("\t", "", $str);
	$str = preg_replace("/(\s+)/", " ", $str);
	return $str;
}

//获取文件
function vspider_get_file($url,$ipinfo=""){
	$url_arr=parse_url($url);
	$source_url="http://".$url_arr['host'];
	$ch2 = curl_init();

	$agentArray=array(
		"Mozilla/5.0 (Windows NT 6.1; WOW64; rv:37.0) Gecko/20100101 Firefox/37.0",
		"Mozilla/5.0 (Windows NT 6.1; rv:12.0) Gecko/20100101 Firefox/12.0",
		"Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/536.11 (KHTML, like Gecko) Chrome/20.0.1132.11 TaoBrowser/2.0 Safari/536.11",
		"Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.1 (KHTML, like Gecko) Chrome/21.0.1180.71 Safari/537.1 LBBROWSER",
		"Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; WOW64; Trident/5.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; .NET4.0C; .NET4.0E; LBBROWSER)",
		"Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; QQDownload 732; .NET4.0C; .NET4.0E; LBBROWSER)",
		"Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/535.11 (KHTML, like Gecko) Chrome/17.0.963.84 Safari/535.11 LBBROWSER",
		"Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.1; WOW64; Trident/5.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; .NET4.0C; .NET4.0E)",
		"Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; WOW64; Trident/5.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; .NET4.0C; .NET4.0E; QQBrowser/7.0.3698.400)",
		"Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; QQDownload 732; .NET4.0C; .NET4.0E)",
		"Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; Trident/4.0; SV1; QQDownload 732; .NET4.0C; .NET4.0E; 360SE)",
		"Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; QQDownload 732; .NET4.0C; .NET4.0E)",
		"Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.1; WOW64; Trident/5.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; .NET4.0C; .NET4.0E)",
		"Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.1 (KHTML, like Gecko) Chrome/21.0.1180.89 Safari/537.1",
		"Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.1 (KHTML, like Gecko) Chrome/21.0.1180.89 Safari/537.1",
		"Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; QQDownload 732; .NET4.0C; .NET4.0E)",
		"Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.1; WOW64; Trident/5.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; .NET4.0C; .NET4.0E)",
		"Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; WOW64; Trident/5.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; .NET4.0C; .NET4.0E)",
		"Mozilla/5.0 (Windows NT 5.1) AppleWebKit/535.11 (KHTML, like Gecko) Chrome/17.0.963.84 Safari/535.11 SE 2.X MetaSr 1.0",
		"Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; Trident/4.0; SV1; QQDownload 732; .NET4.0C; .NET4.0E; SE 2.X MetaSr 1.0)",
		"Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:16.0) Gecko/20121026 Firefox/16.0"
	);
	$ind=rand(0, count($agentArray)-1);
	$user_agent = $agentArray[$ind];
	curl_setopt($ch2, CURLOPT_URL, $url);
	curl_setopt($ch2, CURLOPT_TIMEOUT,200);   //只需要设置一个秒的数量就可以
	curl_setopt($ch2, CURLOPT_HEADER, 0);
	curl_setopt($ch2, CURLOPT_REFERER, $source_url);//这里写一个来源地址，可以写要抓的页面的首页
	curl_setopt($ch2, CURLOPT_USERAGENT, $user_agent);
	curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch2, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($ch2, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch2, CURLOPT_FOLLOWLOCATION, 1);
	if(!empty($ipinfo)){
		curl_setopt($ch2,CURLOPT_PROXY,$ipinfo);
	}

	$content=curl_exec($ch2);
	//$res = curl_getinfo($ch2);
	$httpCode = curl_getinfo($ch2,CURLINFO_HTTP_CODE);

	curl_close ($ch2);
	//fclose ($fp);
	$r=array();
	$r['httpcode']=$httpCode;
	$r['content']=$content;
	return $r;
}
function post_get_code($url,$filePath,$ipinfo="",$post_data=null){
	$url_arr=parse_url($url);
	$source_url="http://".$url_arr['host'];
	$ch2 = curl_init();
	$agentArray=array(
		"Mozilla/5.0 (Windows NT 6.1; WOW64; rv:37.0) Gecko/20100101 Firefox/37.0",
		"Mozilla/5.0 (Windows NT 6.1; rv:12.0) Gecko/20100101 Firefox/12.0",
		"Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/536.11 (KHTML, like Gecko) Chrome/20.0.1132.11 TaoBrowser/2.0 Safari/536.11",
		"Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.1 (KHTML, like Gecko) Chrome/21.0.1180.71 Safari/537.1 LBBROWSER",
		"Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; WOW64; Trident/5.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; .NET4.0C; .NET4.0E; LBBROWSER)",
		"Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; QQDownload 732; .NET4.0C; .NET4.0E; LBBROWSER)",
		"Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/535.11 (KHTML, like Gecko) Chrome/17.0.963.84 Safari/535.11 LBBROWSER",
		"Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.1; WOW64; Trident/5.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; .NET4.0C; .NET4.0E)",
		"Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; WOW64; Trident/5.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; .NET4.0C; .NET4.0E; QQBrowser/7.0.3698.400)",
		"Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; QQDownload 732; .NET4.0C; .NET4.0E)",
		"Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; Trident/4.0; SV1; QQDownload 732; .NET4.0C; .NET4.0E; 360SE)",
		"Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; QQDownload 732; .NET4.0C; .NET4.0E)",
		"Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.1; WOW64; Trident/5.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; .NET4.0C; .NET4.0E)",
		"Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.1 (KHTML, like Gecko) Chrome/21.0.1180.89 Safari/537.1",
		"Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.1 (KHTML, like Gecko) Chrome/21.0.1180.89 Safari/537.1",
		"Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; QQDownload 732; .NET4.0C; .NET4.0E)",
		"Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.1; WOW64; Trident/5.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; .NET4.0C; .NET4.0E)",
		"Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; WOW64; Trident/5.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; .NET4.0C; .NET4.0E)",
		"Mozilla/5.0 (Windows NT 5.1) AppleWebKit/535.11 (KHTML, like Gecko) Chrome/17.0.963.84 Safari/535.11 SE 2.X MetaSr 1.0",
		"Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; Trident/4.0; SV1; QQDownload 732; .NET4.0C; .NET4.0E; SE 2.X MetaSr 1.0)",
		"Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:16.0) Gecko/20121026 Firefox/16.0"
	);
	$ind=rand(0, count($agentArray)-1);
	$user_agent = $agentArray[$ind];
	if(!empty($post_data)){
	    // post数据
		curl_setopt($ch2, CURLOPT_POST, 1);
		// post的变量
		curl_setopt($ch2, CURLOPT_POSTFIELDS, $post_data);
	}
	curl_setopt($ch2, CURLOPT_URL, $url);
	curl_setopt($ch2, CURLOPT_TIMEOUT,200);   //只需要设置一个秒的数量就可以
	curl_setopt($ch2, CURLOPT_HEADER, 0);
	curl_setopt($ch2, CURLOPT_REFERER, $source_url);//这里写一个来源地址，可以写要抓的页面的首页
	curl_setopt($ch2, CURLOPT_USERAGENT, $user_agent);
	curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch2, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($ch2, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch2, CURLOPT_FOLLOWLOCATION, 1);
	$cookie_file=DIR_TMP_COOKIE.rand(1000,5000).".txt";
	if(!file_exists($cookie_file)){
		file_put_contents($cookie_file, "");
	}
	curl_setopt($ch2, CURLOPT_COOKIEJAR, $cookie_file);
	curl_setopt($ch2, CURLOPT_COOKIEFILE, $cookie_file);
	if(!empty($ipinfo)){
		curl_setopt($ch2,CURLOPT_PROXY,$ipinfo);
	}
	$content=curl_exec($ch2);
	file_put_contents($filePath, $content);
	//$res = curl_getinfo($ch2);
	$httpCode = curl_getinfo($ch2,CURLINFO_HTTP_CODE);
	
	curl_close ($ch2);
	unlink($cookie_file);
	return $httpCode;
}
function vspider_get_code($url,$filePath,$ipinfo="",$checkUrl=false,$source_url='http://www.bing.com'){
	$url_arr=parse_url($url);
	$source_url="http://".$url_arr['host'];
	//$fp = fopen ($filePath, "w");
	$ch2 = curl_init();
	//curl_setopt ($ch2, CURLOPT_FILE, $fp);
	//$user_agent = "Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)";//这里模拟的是蜘蛛
	$agentArray=array(
			"Mozilla/5.0 (Windows NT 6.1; WOW64; rv:37.0) Gecko/20100101 Firefox/37.0",
			"Mozilla/5.0 (Windows NT 6.1; rv:12.0) Gecko/20100101 Firefox/12.0",
			"Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/536.11 (KHTML, like Gecko) Chrome/20.0.1132.11 TaoBrowser/2.0 Safari/536.11",
			"Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.1 (KHTML, like Gecko) Chrome/21.0.1180.71 Safari/537.1 LBBROWSER",
			"Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; WOW64; Trident/5.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; .NET4.0C; .NET4.0E; LBBROWSER)",
			"Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; QQDownload 732; .NET4.0C; .NET4.0E; LBBROWSER)",
			"Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/535.11 (KHTML, like Gecko) Chrome/17.0.963.84 Safari/535.11 LBBROWSER",
			"Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.1; WOW64; Trident/5.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; .NET4.0C; .NET4.0E)",
			"Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; WOW64; Trident/5.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; .NET4.0C; .NET4.0E; QQBrowser/7.0.3698.400)",
			"Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; QQDownload 732; .NET4.0C; .NET4.0E)",
			"Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; Trident/4.0; SV1; QQDownload 732; .NET4.0C; .NET4.0E; 360SE)",
			"Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; QQDownload 732; .NET4.0C; .NET4.0E)",
			"Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.1; WOW64; Trident/5.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; .NET4.0C; .NET4.0E)",
			"Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.1 (KHTML, like Gecko) Chrome/21.0.1180.89 Safari/537.1",
			"Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.1 (KHTML, like Gecko) Chrome/21.0.1180.89 Safari/537.1",
			"Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; QQDownload 732; .NET4.0C; .NET4.0E)",
			"Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.1; WOW64; Trident/5.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; .NET4.0C; .NET4.0E)",
			"Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; WOW64; Trident/5.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; .NET4.0C; .NET4.0E)",
			"Mozilla/5.0 (Windows NT 5.1) AppleWebKit/535.11 (KHTML, like Gecko) Chrome/17.0.963.84 Safari/535.11 SE 2.X MetaSr 1.0",
			"Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; Trident/4.0; SV1; QQDownload 732; .NET4.0C; .NET4.0E; SE 2.X MetaSr 1.0)",
			"Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:16.0) Gecko/20121026 Firefox/16.0"
	);
	$ind=rand(0, count($agentArray)-1);
	$user_agent = "Mozilla/5.0 (Windows NT 6.1; WOW64; rv:45.0) Gecko/20100101 Firefox/45.0";
	$agentArray = $agentArray[$ind];
	$agentArray = $user_agent;
	curl_setopt($ch2, CURLOPT_URL, $url);
	curl_setopt($ch2, CURLOPT_TIMEOUT,200);   //只需要设置一个秒的数量就可以
	curl_setopt($ch2, CURLOPT_HEADER, 0);
	curl_setopt($ch2, CURLOPT_REFERER, $source_url);//这里写一个来源地址，可以写要抓的页面的首页
	curl_setopt($ch2, CURLOPT_USERAGENT, $agentArray);
	curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch2, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($ch2, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch2, CURLOPT_FOLLOWLOCATION, 1);
	$cookie_file=DIR_TMP_COOKIE.rand(1000,5000).".txt";
	if(!file_exists($cookie_file)){
		file_put_contents($cookie_file, "");
	}
	curl_setopt($ch2, CURLOPT_COOKIEJAR, $cookie_file);
	curl_setopt($ch2, CURLOPT_COOKIEFILE, $cookie_file);
	if(!empty($ipinfo)){
		curl_setopt($ch2,CURLOPT_PROXY,$ipinfo);
	}
	$content=curl_exec($ch2);
	file_put_contents($filePath, $content);
	//$res = curl_getinfo($ch2);
	$httpCode = curl_getinfo($ch2,CURLINFO_HTTP_CODE);
	if($checkUrl){
		$res = curl_getinfo($ch2);
		if(strstr($res['url'],"www.coupons.com")){
			if(strstr($res['url'],"/coupon-codes/categories/") || strstr($res['url'],"/coupon-codes/go/") || !preg_match ("/\/coupon-codes\/[\\w\\W]+/i",$res['url'])){
				$httpCode=111;
			}
		}
	}
	curl_close ($ch2);
	unlink($cookie_file);
	return $httpCode;
}

function vspider_get_code_file($url,$filePath,$ipinfo="",$checkUrl=false,$source_url='http://www.bing.com'){
	//$fp = fopen ($filePath, "w");
	$url_arr=parse_url($url);
	$source_url="http://".$url_arr['host'];
	$ch2 = curl_init();
	//curl_setopt ($ch2, CURLOPT_FILE, $fp);
	//$user_agent = "Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)";//这里模拟的是蜘蛛
	$agentArray=array(
			"Mozilla/5.0 (Windows NT 6.1; WOW64; rv:37.0) Gecko/20100101 Firefox/37.0",
			"Mozilla/5.0 (Windows NT 6.1; rv:12.0) Gecko/20100101 Firefox/12.0",
			"Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/536.11 (KHTML, like Gecko) Chrome/20.0.1132.11 TaoBrowser/2.0 Safari/536.11",
			"Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.1 (KHTML, like Gecko) Chrome/21.0.1180.71 Safari/537.1 LBBROWSER",
			"Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; WOW64; Trident/5.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; .NET4.0C; .NET4.0E; LBBROWSER)",
			"Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; QQDownload 732; .NET4.0C; .NET4.0E; LBBROWSER)",
			"Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/535.11 (KHTML, like Gecko) Chrome/17.0.963.84 Safari/535.11 LBBROWSER",
			"Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.1; WOW64; Trident/5.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; .NET4.0C; .NET4.0E)",
			"Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; WOW64; Trident/5.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; .NET4.0C; .NET4.0E; QQBrowser/7.0.3698.400)",
			"Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; QQDownload 732; .NET4.0C; .NET4.0E)",
			"Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; Trident/4.0; SV1; QQDownload 732; .NET4.0C; .NET4.0E; 360SE)",
			"Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; QQDownload 732; .NET4.0C; .NET4.0E)",
			"Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.1; WOW64; Trident/5.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; .NET4.0C; .NET4.0E)",
			"Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.1 (KHTML, like Gecko) Chrome/21.0.1180.89 Safari/537.1",
			"Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.1 (KHTML, like Gecko) Chrome/21.0.1180.89 Safari/537.1",
			"Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; QQDownload 732; .NET4.0C; .NET4.0E)",
			"Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.1; WOW64; Trident/5.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; .NET4.0C; .NET4.0E)",
			"Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; WOW64; Trident/5.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; .NET4.0C; .NET4.0E)",
			"Mozilla/5.0 (Windows NT 5.1) AppleWebKit/535.11 (KHTML, like Gecko) Chrome/17.0.963.84 Safari/535.11 SE 2.X MetaSr 1.0",
			"Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; Trident/4.0; SV1; QQDownload 732; .NET4.0C; .NET4.0E; SE 2.X MetaSr 1.0)",
			"Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:16.0) Gecko/20121026 Firefox/16.0"
    );
    $ind=rand(0, count($agentArray)-1);
    $user_agent = "Mozilla/5.0 (Windows NT 6.2; WOW64; rv:30.0) Gecko/20100101 Firefox/30.0";
    $user_agent = $agentArray[$ind];
    curl_setopt($ch2, CURLOPT_URL, $url);
    curl_setopt($ch2, CURLOPT_TIMEOUT,200);   //只需要设置一个秒的数量就可以
    curl_setopt($ch2, CURLOPT_HEADER, 0);
    curl_setopt($ch2, CURLOPT_REFERER, $source_url);//这里写一个来源地址，可以写要抓的页面的首页
    curl_setopt($ch2, CURLOPT_USERAGENT, $user_agent);
    curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch2, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch2, CURLOPT_FOLLOWLOCATION, 1);
    if(!empty($ipinfo)){
        curl_setopt($ch2,CURLOPT_PROXY,$ipinfo);

    }
    $content=curl_exec($ch2);
    file_put_contents($filePath, $content);
    //$res = curl_getinfo($ch2);
    $httpCode = curl_getinfo($ch2,CURLINFO_HTTP_CODE);
    if($checkUrl){
        $res = curl_getinfo($ch2);
        if(strstr($res['url'],"www.coupons.com")){
            if(strstr($res['url'],"/coupon-codes/categories/") || strstr($res['url'],"/coupon-codes/go/") || !preg_match ("/\/coupon-codes\/[\\w\\W]+/i",$res['url'])){
                $httpCode=111;
            }
        }
    }
    curl_close ($ch2);
    //fclose ($fp);
    $r=array();
    $r['httpcode']=$httpCode;
    $r['url']=$res['url'];
    return $r;
}

function vspider_dble_get_code_file($url,$filePath,$ipinfo=""){
	$url_arr=parse_url($url);
	$source_url="http://".$url_arr['host'];
	$ch2 = curl_init();
	//curl_setopt ($ch2, CURLOPT_FILE, $fp);
	//$user_agent = "Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)";//这里模拟的是蜘蛛
	$agentArray=array(
		"Mozilla/5.0 (Windows NT 6.1; WOW64; rv:37.0) Gecko/20100101 Firefox/37.0",
		"Mozilla/5.0 (Windows NT 6.1; rv:12.0) Gecko/20100101 Firefox/12.0",
		"Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/536.11 (KHTML, like Gecko) Chrome/20.0.1132.11 TaoBrowser/2.0 Safari/536.11",
		"Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.1 (KHTML, like Gecko) Chrome/21.0.1180.71 Safari/537.1 LBBROWSER",
		"Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; WOW64; Trident/5.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; .NET4.0C; .NET4.0E; LBBROWSER)",
		"Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; QQDownload 732; .NET4.0C; .NET4.0E; LBBROWSER)",
		"Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/535.11 (KHTML, like Gecko) Chrome/17.0.963.84 Safari/535.11 LBBROWSER",
		"Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.1; WOW64; Trident/5.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; .NET4.0C; .NET4.0E)",
		"Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; WOW64; Trident/5.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; .NET4.0C; .NET4.0E; QQBrowser/7.0.3698.400)",
		"Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; QQDownload 732; .NET4.0C; .NET4.0E)",
		"Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; Trident/4.0; SV1; QQDownload 732; .NET4.0C; .NET4.0E; 360SE)",
		"Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; QQDownload 732; .NET4.0C; .NET4.0E)",
		"Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.1; WOW64; Trident/5.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; .NET4.0C; .NET4.0E)",
		"Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.1 (KHTML, like Gecko) Chrome/21.0.1180.89 Safari/537.1",
		"Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.1 (KHTML, like Gecko) Chrome/21.0.1180.89 Safari/537.1",
		"Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; QQDownload 732; .NET4.0C; .NET4.0E)",
		"Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.1; WOW64; Trident/5.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; .NET4.0C; .NET4.0E)",
		"Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; WOW64; Trident/5.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; .NET4.0C; .NET4.0E)",
		"Mozilla/5.0 (Windows NT 5.1) AppleWebKit/535.11 (KHTML, like Gecko) Chrome/17.0.963.84 Safari/535.11 SE 2.X MetaSr 1.0",
		"Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; Trident/4.0; SV1; QQDownload 732; .NET4.0C; .NET4.0E; SE 2.X MetaSr 1.0)",
		"Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:16.0) Gecko/20121026 Firefox/16.0"
	);
	$ind=rand(0, count($agentArray)-1);
	$user_agent = $agentArray[$ind];
	curl_setopt($ch2, CURLOPT_URL, $url_arr['host'].$url_arr['path']);
	curl_setopt($ch2, CURLOPT_TIMEOUT,200);   //只需要设置一个秒的数量就可以
	curl_setopt($ch2, CURLOPT_HEADER, 0);
	curl_setopt($ch2, CURLOPT_REFERER, $source_url);//这里写一个来源地址，可以写要抓的页面的首页
	curl_setopt($ch2, CURLOPT_USERAGENT, $user_agent);
	curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch2, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($ch2, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch2, CURLOPT_FOLLOWLOCATION, 1);
	//cookie
	$cookie_file=DIR_TMP_COOKIE.rand(1000,5000).".txt";
	if(!file_exists($cookie_file)){
		file_put_contents($cookie_file, "");
	}
	curl_setopt($ch2, CURLOPT_COOKIEJAR, $cookie_file);
	if(!empty($ipinfo)){
		curl_setopt($ch2,CURLOPT_PROXY,$ipinfo);
	}
	curl_exec($ch2);

	sleep(3);
	curl_setopt($ch2, CURLOPT_URL,$url);
	curl_setopt($ch2, CURLOPT_COOKIEFILE, $cookie_file);
	$content=curl_exec($ch2);
	$httpCode = curl_getinfo($ch2,CURLINFO_HTTP_CODE);

	file_put_contents($filePath, $content);
	curl_close ($ch2);
	//fclose ($fp);
	return $httpCode;
}

function vspider_get_code_file_new_store($url,$filePath,$ipinfo="",$checkUrl=false,$source_url='http://www.bing.com'){
	$url_arr=parse_url($url);
	$source_url="http://".$url_arr['host'];
	$ch2 = curl_init();
	$agentArray=array(
			"Mozilla/5.0 (Windows NT 6.1; WOW64; rv:37.0) Gecko/20100101 Firefox/37.0",
			"Mozilla/5.0 (Windows NT 6.1; rv:12.0) Gecko/20100101 Firefox/12.0",
			"Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/536.11 (KHTML, like Gecko) Chrome/20.0.1132.11 TaoBrowser/2.0 Safari/536.11",
			"Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.1 (KHTML, like Gecko) Chrome/21.0.1180.71 Safari/537.1 LBBROWSER",
			"Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; WOW64; Trident/5.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; .NET4.0C; .NET4.0E; LBBROWSER)",
			"Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; QQDownload 732; .NET4.0C; .NET4.0E; LBBROWSER)",
			"Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/535.11 (KHTML, like Gecko) Chrome/17.0.963.84 Safari/535.11 LBBROWSER",
			"Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.1; WOW64; Trident/5.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; .NET4.0C; .NET4.0E)",
			"Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; WOW64; Trident/5.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; .NET4.0C; .NET4.0E; QQBrowser/7.0.3698.400)",
			"Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; QQDownload 732; .NET4.0C; .NET4.0E)",
			"Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; Trident/4.0; SV1; QQDownload 732; .NET4.0C; .NET4.0E; 360SE)",
			"Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; QQDownload 732; .NET4.0C; .NET4.0E)",
			"Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.1; WOW64; Trident/5.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; .NET4.0C; .NET4.0E)",
			"Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.1 (KHTML, like Gecko) Chrome/21.0.1180.89 Safari/537.1",
			"Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.1 (KHTML, like Gecko) Chrome/21.0.1180.89 Safari/537.1",
			"Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; QQDownload 732; .NET4.0C; .NET4.0E)",
			"Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.1; WOW64; Trident/5.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; .NET4.0C; .NET4.0E)",
			"Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; WOW64; Trident/5.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; .NET4.0C; .NET4.0E)",
			"Mozilla/5.0 (Windows NT 5.1) AppleWebKit/535.11 (KHTML, like Gecko) Chrome/17.0.963.84 Safari/535.11 SE 2.X MetaSr 1.0",
			"Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; Trident/4.0; SV1; QQDownload 732; .NET4.0C; .NET4.0E; SE 2.X MetaSr 1.0)",
			"Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:16.0) Gecko/20121026 Firefox/16.0"
    );
    $ind=rand(0, count($agentArray)-1);
    $user_agent = $agentArray[$ind];
    //$user_agent = "Mozilla/5.0 (Windows NT 6.1; WOW64; rv:31.0) Gecko/20100101 Firefox/31.0 FirePHP/0.7.4";
    curl_setopt($ch2, CURLOPT_URL, $url);
    curl_setopt($ch2, CURLOPT_TIMEOUT,200);   //只需要设置一个秒的数量就可以
    curl_setopt($ch2, CURLOPT_HEADER, 0);
    curl_setopt($ch2, CURLOPT_REFERER, $source_url);//这里写一个来源地址，可以写要抓的页面的首页
    curl_setopt($ch2, CURLOPT_USERAGENT, $user_agent);
    curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch2, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch2, CURLOPT_FOLLOWLOCATION, 1);
    if(!empty($ipinfo)){
        curl_setopt($ch2,CURLOPT_PROXY,$ipinfo);
    }
    $content=curl_exec($ch2);
    file_put_contents($filePath, $content);
    //$res = curl_getinfo($ch2);
    $httpCode = curl_getinfo($ch2,CURLINFO_HTTP_CODE);
    if($checkUrl){
        $res = curl_getinfo($ch2);
        if(strstr($res['url'],"www.coupons.com")){
            if(strstr($res['url'],"/coupon-codes/categories/") || strstr($res['url'],"/coupon-codes/go/") || !preg_match ("/\/coupon-codes\/[\\w\\W]+/i",$res['url'])){
                $httpCode=111;
            }
        }
    }
    curl_close ($ch2);
    //fclose ($fp);
    $r=array();
    $r['httpcode']=$httpCode;
    $r['url']=$res['url'];
    return $r;
}

function vspider_get_301_ip($url,$ipinfo=""){
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	if(!empty($ipinfo)){
		curl_setopt($ch,CURLOPT_PROXY,$ipinfo);
	}
	curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch, CURLOPT_TIMEOUT,300);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	$cookie_file=DIR_TMP_COOKIE.rand(1000,5000).".txt";
	if(!file_exists($cookie_file)){
		file_put_contents($cookie_file, "");
	}
	curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
	curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
	$contents = curl_exec($ch);
	$res = curl_getinfo($ch);
	$httpCode = curl_getinfo($ch,CURLINFO_HTTP_CODE);
	curl_close($ch);
	unlink($cookie_file);
	if($httpCode!=0){
		if(strstr($res['url'],"humanCheck")){
			return "error";
		}else{
			return $res['url'];
		}
	}else{
		return "error";
	}
}

//英文日期转中文日期
function dateConv($str){
	if(empty($str)) return "";
	$str=str_replace("Januar","January",$str);
	$str=str_replace("Februar","February",$str);
	$str=str_replace("März","March",$str);
	$str=str_replace("April","April",$str);
	$str=str_replace("Mai","May",$str);
	$str=str_replace("Juni","June",$str);
	$str=str_replace("Juli","July",$str);
	$str=str_replace("August","August",$str);
	$str=str_replace("September","September",$str);
	$str=str_replace("Oktober","October",$str);
	$str=str_replace("November","November",$str);
	$str=str_replace("Dezember","December",$str);
	$t = strtotime($str);
	$dateStr=date("Y-m-d",$t);
	if($dateStr=="1970-01-01"){
		$dateStr="";
	}
	return $dateStr;
}
//算出几天后的日期
function addDates($days){
	return date("Y-m-d",strtotime("+".$days." day"));
}
//创建文件夹
function mk_folder($folder){
	if(!is_dir($folder)) {
		mkdir($folder,0777);
	}
}
//创建爬取文件名和绝对路径
function createCatchFile($cid,$id){
	mk_folder(DIR_CATCH_HTML);
	mk_folder(DIR_CATCH_HTML.$cid."/");
	mk_folder(DIR_CATCH_HTML.$cid."/".date("Y-m-d")."/");
	mk_folder(DIR_CATCH_HTML.$cid."/".date("Y-m-d")."/".date("H")."/");
	//添加小时目录避免目录下文件过多，读写速度慢耗内存
	return DIR_CATCH_HTML.$cid."/".date("Y-m-d")."/".date("H")."/".$id."_".time().".txt";
}
//创建checkIP爬取文件
function createCheckFile($cid,$id){
	mk_folder(DIR_CATCH_HTML);
	mk_folder(DIR_CATCH_HTML.$cid."/");
	mk_folder(DIR_CATCH_HTML.$cid."/check_ip/");
	mk_folder(DIR_CATCH_HTML.$cid."/check_ip/".date("Y-m-d")."/");
	//添加小时目录避免目录下文件过多，读写速度慢耗内存
	return DIR_CATCH_HTML.$cid."/check_ip/".date("Y-m-d")."/".$id."_".time().".txt";
}
//创建CatchCode爬取文件
function createCatchCodeFile($cid,$id){
	mk_folder(DIR_CATCH_HTML);
	mk_folder(DIR_CATCH_HTML.$cid."/");
	mk_folder(DIR_CATCH_HTML.$cid."/catch_code/");
	mk_folder(DIR_CATCH_HTML.$cid."/catch_code/".date("Y-m-d")."/");
	//添加小时目录避免目录下文件过多，读写速度慢耗内存
	return DIR_CATCH_HTML.$cid."/catch_code/".date("Y-m-d")."/".$id."_".time().".txt";
}
//创建CatchTermGoUrl爬取文件
function createCatchTempGoFile($cid,$id){
	mk_folder(DIR_CATCH_HTML);
	mk_folder(DIR_CATCH_HTML.$cid."/");
	mk_folder(DIR_CATCH_HTML.$cid."/temp_go/");
	mk_folder(DIR_CATCH_HTML.$cid."/temp_go/".date("Y-m-d")."/");
	//添加小时目录避免目录下文件过多，读写速度慢耗内存
	return DIR_CATCH_HTML.$cid."/temp_go/".date("Y-m-d")."/".$id."_".time().".txt";
}
//检查PHP文件进程数量
function checkScriptProcessCount($file, $cmd_arg=''){
    $script_name = $file;
    $cmd = "ps aux|grep '" . $script_name ;
    if ($cmd_arg) {
        $cmd .= ' ' .$cmd_arg;
    }
    $cmd .= "'|grep -v 'grep " . $script_name . "'|grep -v 'sh -c'|wc -l";

    $count = intval(trim(exec($cmd)));
    return $count;

}
/*同步数据到线上，拼装sql语句提交*/
function sync_data_by_post_sql($sql){
	$data = array(
			'up_sql'=>$sql
	);
	$ch = curl_init();
	$url = "http://www.discountscat.com/site/api/code/cron_up_data_by_db.php";
	curl_setopt($ch, CURLOPT_URL, $url);
//	curl_setopt($ch, CURLOPT_USERPWD, 'aaronpeng:abcd1234');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	$content = curl_exec($ch);
	curl_close($ch);
	return $content;
}

/*判断字符串是否有中文*/
function check_is_chinese($s){
	return preg_match('/[\x80-\xff]./', $s);
}

/*根据竞争对手判断ip是否能用
 * $type : index 首页用来检查ip,store:用来检查获取商家内容
 * 	
 * */
function check_ip_by_get_content($cid,$type,$content,$is_cache=true){
	$flag = "no";
	if(strlen($content) <  5000){
		return $flag;
	}
	$content = del_br_space_by_str($content);
	$mem_obj = new Cache();
	$c_list = $mem_obj->get_cache(COMPETITOR_INFO_KEY);
	if(!$is_cache){
		$c_list = "";
	}
	if(empty($c_list)){
		$db_obj_tmp = new Mysql(DB_NAME, DB_HOST, DB_USER, DB_PWD);
		$sql = "select * from cp_competitor";
		$c_list = $db_obj_tmp->getRows($sql,"ID");
		$mem_obj->set_cache(COMPETITOR_INFO_KEY, $c_list);
	}
	
	if(isset($c_list[$cid])){
		$find_str = "";
		if($type == "index"){
			$find_str = $c_list[$cid]['IndexFlag'];
		}elseif($type == "store"){
			$find_str = $c_list[$cid]['StoreFlag'];
		}
		if(!empty($find_str)){
			if(stripos($content, $find_str)){
				$flag = "yes";
			}else{
				if(!empty($c_list[$cid]['DeleteFlag'])){
					if(stripos($content, $c_list[$cid]['DeleteFlag'])){
						$flag = "notfound";
					}
				}
			}
		}
	}
	return $flag;
}

//取出内容所有换行，并把多个空格替换为一个空格
function del_br_space_by_str($content){
	$order   = array("\r\n", "\n", "\r");
	$content=str_replace($order, "", $content);
	$content = preg_replace("/[\s]+/is"," ",$content);
	return trim($content);
}


function deal_text($content){

    return addslashes(del_br_space_by_str(strip_tags($content)));
}

//匹配域名
function get_short_domain($url){
    preg_match('/https?:\/\/([^\/|^\?|^\#|^\:]+)/i', $url,$match);
    if(empty($match[1])){
        preg_match('/([^\/|^\?|^\#|^\:]+)/i', $url,$match);
    }
    $match[1] = str_ireplace("http:","",$match[1]);
    if(substr($match[1],-4,4) == "http"){
        $match[1] = substr($match[1],0,-4);
    }
    return $match[1];
}

// 得到字包含数字和英文的商家名称
function get_top_domain($url){
    preg_match('/[\w][\w-]*\.(?:com\.cn|com\.br|com\.co|com\.ng|com\.au|com\.ua|com\.sg|com\.hk|com\.mx|com\.pa|com\.ec|com\.ar|com\.ve|com\.my|com\.ph|com\.hr|com\.pr|bt\.com|com\.uy|com\.pe|tur\.br|com\.bh|com\.kw|com\.gh|com\.eg|com\.tr|com\.pk|ltd\.uk|uk\.com|go\.com|edu\.au|org\.br|org\.uk|org\.au|co\.jp|co\.in|co\.kr|co\.uk|co\.nz|co\.za|co\.id|co\.th|org\.uk|net\.nz|net\.au|mobi|name|clothing|solutions|reviews|travel|camera|company|aero|watch|kiwi|me\.uk|net\.br|edu|cn|ca|co|net|org|gov|jobs|cc|biz|info|asia|club|pro|nyc|ie|pl|ph|es|de|cz|fr|be|bz|it|es|tv|ro|in|si|ru|nl|se|fm|us|no|dk|eu|nu|ch|hu|at|ag|sm|jp|lv|pt|fi|ws|tw|hk|sa|me|md|mx|rs|tk|sk|vn|gr|cl|su|ms|je|mu|my|uk|ae|cr|lt|am|ua|by|br|kz|sg|ma|tn|io|ci|qa|pk|is|au|lu|il|st|ac|as|lk|ke|la|ly|to|gift|guru|coop|com|london|shoes|training|life|training|rest|equipment|realtor|photography|im|gifts|bg|za|net\.ee|ne|center|cday|ar|re|coffee|cool|discount|fishing|toys|tips|cards|bio|ee|so|gl)(\/|\?|\#|\%|\s|$|\:)/isU', $url, $domain);
    if(isset($domain[0])){
        $domain[0] = trim($domain[0]);
        $res = trim($domain[0],"/");
        $res = trim($res,"?");
        $res = trim($res,"#");
        $res = trim($res,"%");
        $res = strtolower($res);
        return $res;
    }else{
        return "";
    }
}

function memory_usage() {
    $memory     = ( ! function_exists('memory_get_usage')) ? '0' : round(memory_get_usage()/1024/1024, 2).'MB';
    return $memory;
}