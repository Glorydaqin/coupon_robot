<?php
/**
 * Created by PhpStorm.
 * User: 大秦
 * Date: 2017/8/19
 * Time: 10:21
 */
include_once dirname(dirname(dirname(__FILE__))).DIRECTORY_SEPARATOR . 'etc/initiate.php';
$num = checkScriptProcessCount(basename(__FILE__));  //检测是否多开
if($num > 1) exit( basename(__FILE__).":  ing !\n");
set_time_limit(0);
@ini_set('memory_limit', '128M');
echo "start time:".date("Y-m-d H:i:s")."\n";

$max_size = 5;
$max_frequency = 172800;
$limit = 1000;
$sleep = 500;
$db = new Mysql(DB_NAME, DB_HOST, DB_USER, DB_PWD);

//需要刷新的表
$refresh_db = array(
    'sync_store_promosgo_mapping'=>'https://www.promosgo.com',
    'sync_store_promosgo_uk_mapping'=>'https://uk.promosgo.com',
    'sync_store_promosgo_fr_mapping'=>'https://fr.promosgo.com',
    'sync_store_promosgo_de_mapping'=>'https://de.promosgo.com',
    'sync_store_promosgo_au_mapping'=>'https://au.promosgo.com',
);

$mh = curl_multi_init(); //返回一个新cURL批处理句柄
$active = null;

foreach ($refresh_db as $key=>$value){

    $need_refresh = "SELECT * FROM `{$key}` where UNIX_TIMESTAMP(LastCacheTime)<(UNIX_TIMESTAMP()-{$max_frequency}) ORDER BY LastCacheTime asc limit {$limit}";
    $needs = $db->getRows($need_refresh,'Url');
    if(empty($needs)){
        continue;
    }
    //更新为当前时间
    $time = Date("Y-m-d H:i:s");
    $up_sql = "update $key set LastCacheTime='{$time}' where Url in( '".implode("','",array_keys($needs))."' )";
    $db->query($up_sql);


    //初始化添加任务
    for($i = 0 ;$i<$max_size ;$i++){

        $first_item = array_shift($needs);

        $ch = curl_init();  //初始化单个cURL会话
        $options= getOptions(getUrl($first_item['Url'],$value));
        curl_setopt_array($ch,$options);
        $requestMap[$i] = $ch; //记录添加的cUrl会话
        curl_multi_add_handle($mh, $ch);  //向curl批处理会话中添加单独的curl句柄
//        echo date("Y-m-d H:i:s")." start add one :{$first_item['Url']}\n";
//        echo "data usage:".memory_usage().PHP_EOL;
        usleep($sleep);
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
            if($info['http_code']==200){
                //不处理
            }

            //保证同时有$max_size个请求在处理
            if (count($needs)>0)
            {
                $ch = curl_init();
                $tmp_cs = array_shift($needs);

                $options= getOptions(getUrl($tmp_cs['Url'],$value));
                curl_setopt_array($ch,$options);

                curl_multi_add_handle($mh, $ch);
//                echo date("Y-m-d H:i:s")." second add one :{$tmp_cs['Url']}\n";
//                echo "data usage:".memory_usage().PHP_EOL;
            }

            curl_multi_remove_handle($mh, $done['handle']);
            usleep($sleep);
        }

        if ($active)
            curl_multi_select($mh, 1);
    } while ($active);

}

curl_multi_close($mh);
echo "end time:".date("Y-m-d H:i:s")."\n";


function getUrl($url,$pre){
    return $pre.$url."?del_cache_key=1";
}

function getOptions($url,$ipinfo=''){
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
    if(!empty($ipinfo)){
        $options[CURLOPT_PROXY]=$ipinfo;
    }

    return $options;
}