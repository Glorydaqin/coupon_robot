<?php
/*根据store爬取竞争对手html*/
include_once dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . 'etc/initiate.php';
$competitorId = isset($_SERVER["argv"][1])?$_SERVER["argv"][1]:0;
if(empty($competitorId)){
    exit("competitor id empty\n");
}
$num = checkScriptProcessCount(basename(__FILE__)." ".$competitorId);
if($num > 1) exit("Competitor ID:".$competitorId." catch ing !\n");

echo "start time:".date("Y-m-d H:i:s")."\n";
//避免单数字出错,去掉CompetitorId前缀0
$competitorId=ltrim($competitorId,'0');

//最大线程数量
$max = $GLOBALS['CompetitorParam'][$competitorId]['CatchHtmlMaxThread'];
if(empty($max)){
    exit("Competitor ID:".$competitorId."empty max thread");
}
set_time_limit(1800);

$check_file_name = INCLUDE_ROOT."code/robot_html/catch_html_save.php {$competitorId}";

$db = new Mysql(DB_NAME, DB_HOST, DB_USER, DB_PWD);

$sqlcom="SELECT cs.ID,cs.CompetitorId,cs.Url,c.IPAuto from cp_competitor_store cs LEFT JOIN cp_competitor c ON (cs.CompetitorId=c.ID)  WHERE cs.IsUpdate=0 AND cs.ErrorTime<10 AND cs.CompetitorId={$competitorId} AND cs.IsCatch=1 And cs.301Times<7 AND cs.404Times<7 AND cs.Is301=1 AND ((UNIX_TIMESTAMP(cs.LastChangeTime)+ cs.UpdateFrequency) < UNIX_TIMESTAMP() or cs.LastChangeTime = '0000-00-00 00:00:00')  ORDER BY cs.LastChangeTime ASC LIMIT 1000";
$comp_list = $db->getRows($sqlcom,"ID");

//更新数据库状态
$up_cs_ids = array_keys($comp_list);
if(empty($up_cs_ids)) {
    exit("No Data Update !\n");
}
$sql="update cp_competitor_store set IsUpdate=1 where ID in (".join(",",$up_cs_ids).")";
$db->query($sql);
$db->close();
foreach($comp_list as $o){
    $for_count = checkScriptProcessCount($check_file_name);
    if($for_count > $max) {
        sleep(($for_count-$max)*0.5);
    }
    //usleep(1000);

    $o['Url']=base64_encode($o['Url']);
    $cmd = "php ".INCLUDE_ROOT."code/robot_html/catch_html_save.php {$competitorId} {$o['ID']} {$o['IPAuto']} '{$o['Url']}'";
    $cmd = $cmd." >/dev/null 2>&1 &";
    exec($cmd);
}
echo "end time:".date("Y-m-d H:i:s")."\n";
