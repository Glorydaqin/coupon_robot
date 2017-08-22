<?php
/*执行爬取Code进程*/
include_once dirname(dirname(dirname(__FILE__))).DIRECTORY_SEPARATOR . 'etc/initiate.php';

$vo['CompetitorId']=isset($_SERVER["argv"][1])?$_SERVER["argv"][1]:0;
$vo['ID']=isset($_SERVER["argv"][2])?$_SERVER["argv"][2]:0;
$vo['CouponCodeUrl']=isset($_SERVER["argv"][3])?$_SERVER["argv"][3]:0;

set_time_limit(100);

require_once dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . "lib/competitor/Class.Competitor{$vo['CompetitorId']}.php";

if(!empty($vo['CompetitorId']) && !empty($vo['ID']) && !empty($vo['CouponCodeUrl'])){
	$vo['CouponCodeUrl']=base64_decode($vo['CouponCodeUrl']);
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
	//echo $ip_info."\n";

	$filePath=createCatchCodeFile($vo['CompetitorId'],$vo['ID']);

    if($vo['CompetitorId']==3){
		//相同参数两次请求
		$httpStatus=vspider_dble_get_code_file($vo['CouponCodeUrl'], $filePath,$ip_info);
	}elseif($vo['CompetitorId']==23){
        //通过post抓取
        $urls=explode("?",$vo['CouponCodeUrl']);
        $queryParts = explode('&', $urls[1]);
        $params = array();
        foreach ($queryParts as $param)
        {
            $item = explode('=', $param);
            $params[$item[0]] = $item[1];
        }
        $httpStatus=post_get_code($urls[0], $filePath,$ip_info,$params);
    }else{
		//默认情况
		$httpStatus=vspider_get_code($vo['CouponCodeUrl'], $filePath,$ip_info);
	}
	$flag=false;

	if($httpStatus==200){
		$htmlContent=file_get_contents($filePath);

		$normal_num=5000;
		if($vo['CompetitorId']==20){
			//json请求的数据,此时content大小会低于5000
			$normal_num=2500;
		}elseif($vo['CompetitorId']==22){
			$normal_num=20;
		}elseif($vo['CompetitorId']==23 || $vo['CompetitorId']==41){
			$normal_num=250;
		}elseif($vo['CompetitorId']==29){
			$normal_num=1000;
		}elseif($vo['CompetitorId']==45 or $vo['CompetitorId']==47 or $vo['CompetitorId']==57){
            $normal_num=1000;
        }

		if(filesize($filePath) > $normal_num){

			if(!MEM_CACHE_DEBUG){ //如果有memcache 走memcache
				$ip_info = $cache_obj->update_data_by_get_html_res($vo['CompetitorId'], $ip_info, true);
			}else{
				$sql = "UPDATE ip_info SET GoodCatch=GoodCatch+1,FailNum=0,LastChangeTime='".date("Y-m-d H:i:s")."'  WHERE Info = '".trim($ip_info)."' and CompetitorID='".$vo['CompetitorId']."'";
				$res = $db->query($sql);
			}
			$htmlContent=file_get_contents($filePath);


            //初始化类
            $class_name = "Competitor{$vo['CompetitorId']}";
            $competitor = new $class_name($htmlContent,$vo['CouponCodeUrl']);
            $code = $competitor->getNewtabCode();

			//更新code或状态
			if(!empty($code)){
				$sql="update cp_competitor_store_coupon set CouponCode='".addslashes($code)."',ErrorTime=0,Type='code',UpdateCodeTime='".date("Y-m-d H:i:s")."',LastChangeTime='".date("Y-m-d H:i:s")."' where ID={$vo['ID']}";
				$db->query($sql);
			}else{
				if(!$flag) {
					$sql = "update cp_competitor_store_coupon set ErrorTime=ErrorTime+1,IsUpdateCodeUrl='1',LastChangeTime='" . date("Y-m-d H:i:s") . "' where ID={$vo['ID']}";
					$db->query($sql);
				}
			}

		}else{
			$flag = true;
			if(!MEM_CACHE_DEBUG){ //如果有memcache 走memcache
				$ip_info = $cache_obj->update_data_by_get_html_res($vo['CompetitorId'], $ip_info, false);
			}else{
				$sql = "UPDATE ip_info SET FailNum=FailNum+1,LastChangeTime='".date("Y-m-d H:i:s")."'WHERE Info = '".trim($ip_info)."' and CompetitorID='".$vo['CompetitorId']."'";
				$res = $db->query($sql);
				$ip_info = get_proxy_info($vo['CompetitorId']);
				file_put_contents(DIR_IP_CONFIG.$vo['CompetitorId'].".txt", $ip_info);
			}
		}
	}else{
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
        return false;
    }
}