<?php

//dir
define("DIR_IP_CONFIG",INCLUDE_ROOT."data/");
define("DIR_CATCH_HTML",INCLUDE_ROOT."catch_html/");
define("DIR_TMP_COOKIE",INCLUDE_ROOT."tmp/");

//file
define("LOG_RESOLVE_FILE",INCLUDE_ROOT."log/resolve_log.txt");
define("LOG_RESOLVE_CATE_FILE",INCLUDE_ROOT."log/resolve_cate_log.txt");
define("LOG_OUT_FILE",INCLUDE_ROOT."log/thread_out_log.txt");
define("LOG_NEW_STORE_BASE_INFO_FILE",INCLUDE_ROOT."log/get_new_store_base_info.txt"); //获取新商家时log
define("LOG_LANDING_FILE",INCLUDE_ROOT."log/landing_log.txt");	//landing匹配页面

//param
define("PARAM_SERVER","_188");
define("PARAM_MAX_ACTIVE_IPS",20);
define("PARAM_CATCH_FAIL_MAX_COUNT_IP",10); //IP抓取失败最大次数
define("COMPETITOR_INFO_KEY","COMPETITOR_INFO_KEY"); //竞争对手数据缓存KEY

//运行设备唯一名称
define("MERCHANT_NAME","QCLOUD_ROBOT");
define("CatchTempCSGOMaxThread",15);
define("CatchTempCSGOURLMaxThread",10);
define("CatchHtmlMaxThread",40);
define("CatchCodeMaxThread",20);

//任务配置
define("DAY_DEL_FILE", date("Y-m-d",strtotime("-2 day")) );
define("DAY_DEL_IP", date("Y-m-d",strtotime("-2 day")) );

//ip link
define("IP_LINK",'http://svip.kuaidaili.com/api/getproxy/?orderid=998646377208447&num=50&protocol=1&method=1&quality=0&sort=0&dedup=1&format=text&sep=1');