<?php
/*根据store爬取竞争对手html*/
include_once dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . 'etc/initiate.php';

$num = checkScriptProcessCount(basename(__FILE__));
if($num > 1) exit("robot catch ing !\n");

echo "start time:".date("Y-m-d H:i:s")."\n";
set_time_limit(3600);

$db = new Mysql(DB_NAME, DB_HOST, DB_USER, DB_PWD);

//取需要更新的竞争对手
$need_up_sql = "SELECT cs.CompetitorId,count(cs.CompetitorId) as count_num from cp_competitor_store cs LEFT JOIN cp_competitor c ON (cs.CompetitorId=c.ID)  WHERE cs.IsUpdate=0 AND cs.ErrorTime<10 AND cs.IsCatch=1 And cs.301Times<7 AND cs.404Times<7 AND cs.Is301=1 AND ((UNIX_TIMESTAMP(cs.LastChangeTime)+ cs.UpdateFrequency) < UNIX_TIMESTAMP() or cs.LastChangeTime = '0000-00-00 00:00:00') group by cs.CompetitorId";
$need_up_list = $db->getRows($need_up_sql);

//循环更新
foreach ($need_up_list as $item){
    $competitorId = $item['CompetitorId'];
    $check_file_name = INCLUDE_ROOT."code/robot_html/catch_html_save.php";

    echo "catch {$competitorId}-{$item['count_num']}\n";

    $sqlcom="SELECT cs.ID,cs.CompetitorId,cs.Url,c.IPAuto from cp_competitor_store cs LEFT JOIN cp_competitor c ON (cs.CompetitorId=c.ID)  WHERE cs.IsUpdate=0 AND cs.ErrorTime<10 AND cs.CompetitorId={$competitorId} AND cs.IsCatch=1 And cs.301Times<7 AND cs.404Times<7 AND cs.Is301=1 AND ((UNIX_TIMESTAMP(cs.LastChangeTime)+ cs.UpdateFrequency) < UNIX_TIMESTAMP() or cs.LastChangeTime = '0000-00-00 00:00:00')  ORDER BY cs.LastChangeTime ASC LIMIT 2000";
    $comp_list = $db->getRows($sqlcom,"ID");

    //更新数据库状态
    $up_cs_ids = array_keys($comp_list);
    if(empty($up_cs_ids)) {
        exit("No Data Update !\n");
    }else{
        $sql="update cp_competitor_store set IsUpdate=1 where ID in (".join(",",$up_cs_ids).")";
        $db->query($sql);

        foreach($comp_list as $o){
            $for_count = checkScriptProcessCount($check_file_name);
            if($for_count > CatchHtmlMaxThread) {
                sleep($for_count-CatchHtmlMaxThread);
            }

            $o['Url']=base64_encode($o['Url']);
            $cmd = "php ".INCLUDE_ROOT."code/robot_html/catch_html_save.php {$competitorId} {$o['ID']} {$o['IPAuto']} '{$o['Url']}'";
            $cmd = $cmd." >/dev/null 2>&1 &";
            exec($cmd);

            usleep(500);
        }
    }
}


echo "end time:".date("Y-m-d H:i:s")."\n";
