<?php
/*根据store爬取竞争对手html*/
include_once dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . 'etc/initiate.php';

$num = checkScriptProcessCount(basename(__FILE__));
if($num > 1) exit("catch ing !\n");

echo "start time:".date("Y-m-d H:i:s")."\n";

set_time_limit(3600);

$db = new Mysql(DB_NAME, DB_HOST, DB_USER, DB_PWD);

$sqlcom="SELECT cs.ID,cs.CompetitorId,cs.Url,c.IPAuto from cp_competitor_store cs LEFT JOIN cp_competitor c ON (cs.CompetitorId=c.ID)  WHERE cs.IsUpdate=0 AND cs.ErrorTime<10 AND cs.IsCatch=1 And cs.301Times<7 AND cs.404Times<7 AND cs.Is301=1 AND ((UNIX_TIMESTAMP(cs.LastChangeTime)+ cs.UpdateFrequency) < UNIX_TIMESTAMP() or cs.LastChangeTime = '0000-00-00 00:00:00')  ORDER BY cs.LastChangeTime ASC LIMIT 100";
$cs_list = $db->getRows($sqlcom,"ID");

//更新数据库状态
$up_cs_ids = array_keys($cs_list);
if(empty($up_cs_ids)) {
    exit("No Data Update !\n");
}
$sql="update cp_competitor_store set IsUpdate=1 where ID in (".join(",",$up_cs_ids).")";
//$db->query($sql);

echo "data usage:".memory_get_usage().PHP_EOL;
$cache_obj = new Cache();


$mh = curl_multi_init(); //返回一个新cURL批处理句柄
$active = null;
$len =  count($cs_list);
$max_size = $len>30 ? 30 : $len;
$ip_info = "";

//初始化添加任务
for($i = 0 ;$i<$max_size ;$i++){

    $first_item = array_shift($cs_list);

    $ip_info = $cache_obj->get_ip_by_competitor_id($first_item['CompetitorId']);
    $ip_info = trim($ip_info);


    $ch = curl_init();  //初始化单个cURL会话
    $options= getOptions($first_item['Url'],$ip_info);
    curl_setopt_array($ch,$options);
    $requestMap[$i] = $ch; //记录添加的cUrl会话
    curl_multi_add_handle($mh, $ch);  //向curl批处理会话中添加单独的curl句柄
    echo date("Y-m-d H:i:s")." start add one :{$first_item['Url']}\n";

    echo "data usage:".memory_get_usage().PHP_EOL;
}



do {
    while (($cme = curl_multi_exec($mh, $active)) == CURLM_CALL_MULTI_PERFORM);

    if ($cme != CURLM_OK) {break;}

    while ($done = curl_multi_info_read($mh))
    {
        $info = curl_getinfo($done['handle']);
        $tmp_result = curl_multi_getcontent($done['handle']);
        $error = curl_error($done['handle']);

        //数据处理
        echo "deal one :the curl is \n";
        if($info['http_code']==200){

            var_dump($info);
        }

        //保证同时有$max_size个请求在处理
        if (count($cs_list)>0)
        {
            $ch = curl_init();
            $tmp_cs = array_shift($cs_list);
            //get ip
            $ip_info = $cache_obj->get_ip_by_competitor_id($tmp_cs['CompetitorId']);
            $ip_info = trim($ip_info);

            $options= getOptions($tmp_cs['Url'],$ip_info);
            curl_setopt_array($ch,$options);

            curl_multi_add_handle($mh, $ch);
            echo date("Y-m-d H:i:s")." second add one :{$tmp_cs['Url']}\n";

            echo "data usage:".memory_get_usage().PHP_EOL;
        }

        curl_multi_remove_handle($mh, $done['handle']);
    }

    if ($active)
        curl_multi_select($mh, 1);
} while ($active);



curl_multi_close($mh);

echo "data usage:".memory_get_usage().PHP_EOL;

echo "end time:".date("Y-m-d H:i:s")."\n";


function getOptions($url,$ipinfo){
    $url_arr=parse_url($url);
    $source_url="http://".$url_arr['host'];

    $options = array();
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
    $options[CURLOPT_URL]=$url;
    $options[CURLOPT_USERAGENT]=$agentArray[$ind];
    $options[CURLOPT_REFERER]=$source_url;
    $options[CURLOPT_TIMEOUT]=60;
    $options[CURLOPT_FOLLOWLOCATION]=1;
    $options[CURLOPT_RETURNTRANSFER]=1;
    $options[CURLOPT_SSL_VERIFYPEER]=0;
    $options[CURLOPT_SSL_VERIFYHOST]=0;
    $options[CURLOPT_PROXY]=$ipinfo;

    return $options;
}