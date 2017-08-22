<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/8/8 0008
 * Time: 15:33
 */
/*启 html 解释进程*/
include_once dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . 'etc/initiate.php';

$num = checkScriptProcessCount(basename(__FILE__));
if($num > 1) exit("catch ing !\n");
echo "start time:".date("Y-m-d H:i:s")."\n";

set_time_limit(3600);

$pdo = new PdoMysql([
    'host' => DB_HOST,
    'port' => DB_PORT,
    'user' => DB_USER,
    'passwd' => DB_PWD,
    'dbname' => DB_NAME,
]);

$sql = "SELECT cs.CompetitorId,count(cs.CompetitorId) as num FROM cp_competitor_store cs  
left join cp_competitor_catch_file ccf ON ccf.CompetitorStoreId=cs.ID 
where ccf.MerchantName='".MERCHANT_NAME."' and ccf.ReaderTimes=0 and ccf.isAvailable=1 
GROUP BY cs.CompetitorId";
$list = $pdo->doSql($sql);

if(!count($list)){
    echo "no need resolve\n";
}
foreach ($list as $vo){
    //检测是否存在
    if(file_exists(dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . "lib/competitor/Class.Competitor{$vo['CompetitorId']}.php")){
        $check_file_name = INCLUDE_ROOT."code/resolve/resolve_html_obj.php";
    }else{
        $check_file_name = INCLUDE_ROOT."code/resolve/resolve_html.php";
    }

    $competitorId=strlen($vo['CompetitorId'])>1?$vo['CompetitorId']:'0'.$vo['CompetitorId'];

    $for_count = checkScriptProcessCount($check_file_name);

    $cmd = "php ".$check_file_name." {$competitorId}";
    $cmd = $cmd." >>".INCLUDE_LOG_ROOT."cp_log/resolve_html_{$competitorId}.txt";

    if($for_count>1){
        //在运行
        while($for_count>1){
            echo "sleep 5\n";
            sleep(5);
            $for_count = checkScriptProcessCount($check_file_name);

            if($for_count<=1){
                //未运行
                
                echo $cmd.PHP_EOL;
                exec($cmd);
                break;
            }
        }
    }else{
        //未运行
        echo $cmd.PHP_EOL;
        exec($cmd);
    }
}

echo "end time:".date("Y-m-d H:i:s")."\n";