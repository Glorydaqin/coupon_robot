<?php
//memcache by aaron 20150725	
class Cache
{

	var $c_obj   = null;
	
	function Cache($server=MEM_CACHE_SERVER_IP, $port=MEM_CACHE_PORT, $is_debug=MEM_CACHE_DEBUG) {
		if(!$is_debug){
            try
            {
                $this->c_obj = new Redis();
                $this->c_obj->connect($server,$port);
            }
            catch(Exception $e)
            {
                echo 'Message: ' .$e->getMessage();

                //异常重启 redis
				system('rm -f /var/run/redis.pid;/etc/init.d/redis restart;',$retval);
                if($retval==0){
                    echo "restart cache success";
                }else{
                    echo "restart cache wrong";
                }
            }

//			$this->c_obj = new Memcache;
//			$this->c_obj->addServer(MEM_CACHE_SERVER_IP,MEM_CACHE_PORT);
		}
	}
	
	//设置缓存
	function set_cache($key,&$content,$exp_time=MEM_CACHE_EXP_TIME){
		$key = MEM_CACHE_PRE.$key;
//		$need_compress = MEMCACHE_COMPRESSED; //如果长度大于256压缩
		$res = $this->c_obj->set($key,json_encode($content),$exp_time);
	}
	
	//获取缓存
	function get_cache($key){
		$key = MEM_CACHE_PRE.$key;
		$res =json_decode($this->c_obj->get($key),true);
		if ($res === false || empty($res)) {
			return '';
		}else{
			return $res;
		}
	}
	
	//根据竞争对手获取ip  返回ip后 CatchIngNum=CatchIngNum+1
	function get_ip_by_competitor_id($cid){
		
		$ip_list = $this->get_cache(MEM_CACHE_IP_KEY);
		$ip_info = "";
		$min_num_pre = 0;
		$min_obj = array();
		if(!empty($ip_list)){
			
			if(isset($ip_list[$cid])){
				foreach ($ip_list[$cid] as $k=>$v){//获取memcache 错误次数最小&代理ing 最小值
					$fail_num = intval($v['FailNum']);
					$catch_ing_num = isset($v['CatchIngNum'])?$v['CatchIngNum']:0;
					$on_num = $fail_num+$catch_ing_num;
					if($on_num == 0){
						$ip_list[$cid][$k]['CatchIngNum'] = 1;
						$ip_info = $k;
						break;
					}else{
						if($min_num_pre ==0){
							$min_num_pre = $on_num;
							$min_obj['cid'] = $cid;
							$min_obj['ip'] = $k;
						}elseif($on_num < $min_num_pre){
							$min_num_pre = $on_num;
							$min_obj['cid'] = $cid;
							$min_obj['ip'] = $k;
						}
							
					}
				}
			}
			
			
// 			foreach($ip_list as $key=>$o){ 
// 				if($key == $cid){
// 					foreach ($o as $k=>$v){//获取memcache 错误次数最小&代理ing 最小值
// 						$fail_num = intval($v['FailNum']);
// 						$catch_ing_num = isset($v['CatchIngNum'])?$v['CatchIngNum']:0;
// 						$on_num = $fail_num+$catch_ing_num;
// 						if($on_num == 0){
// 							$ip_list[$key][$k]['CatchIngNum'] = 1;
// 							$ip_info = $k;
// 							break;
// 						}else{
// 							if($min_num_pre ==0){
// 								$min_num_pre = $on_num;
// 								$min_obj['cid'] = $key;
// 								$min_obj['ip'] = $k;
// 							}elseif($on_num < $min_num_pre){
// 								$min_num_pre = $on_num;
// 								$min_obj['cid'] = $key;
// 								$min_obj['ip'] = $k;
// 							}
							
// 						}
// 					}
// 				}
// 			}
			if(!empty($min_obj)){
				$ip_list[$min_obj['cid']][$min_obj['ip']]['CatchIngNum'] = isset($ip_list[$min_obj['cid']][$min_obj['ip']]['CatchIngNum'])?($ip_list[$min_obj['cid']][$min_obj['ip']]['CatchIngNum']+1):1;
				$ip_info = $min_obj['ip'];
			}
		}
		
		$this->set_cache(MEM_CACHE_IP_KEY, $ip_list);//更新memcache的值
		return $ip_info;
	}
	
	
	/*
	 * 根据抓取结果更新数据 更新成功文件&失败文件数，catchingnum -1
	 * $cid 竞争对手id
	 * $ip 
	 * $is_good_catch true (+1,FailNum=0)   false -1
	 * */
	function update_data_by_get_html_res($cid,$ip,$is_good_catch){
		$ip_list = $this->get_cache(MEM_CACHE_IP_KEY);
		if(isset($ip_list[$cid][$ip])){
			if($is_good_catch){
				$ip_list[$cid][$ip]['GoodCatch'] = isset($ip_list[$cid][$ip]['GoodCatch'])?(intval($ip_list[$cid][$ip]['GoodCatch'])+1):1;
				$ip_list[$cid][$ip]['FailNum'] = 0;
			}else{
				$ip_list[$cid][$ip]['FailNum'] = isset($ip_list[$cid][$ip]['FailNum'])?(intval($ip_list[$cid][$ip]['FailNum'])+1):1;
			}
			
			$ip_list[$cid][$ip]['CatchIngNum'] = isset($ip_list[$cid][$ip]['CatchIngNum'])?(intval($ip_list[$cid][$ip]['CatchIngNum'])-1):0;
		}
		
		$this->set_cache(MEM_CACHE_IP_KEY, $ip_list);
	}
	
	/*把ip抓取信息更新到数据库，移除抓取次数大于一定值的ip,读取新的ip数据*/
	function update_memcache_ip_list_value(){
		$ip_list = $this->get_cache(MEM_CACHE_IP_KEY);
		$db = new Mysql(DB_NAME, DB_HOST, DB_USER, DB_PWD);
		//把memcache ip list 数据更新到数据库
		$del_memcahe_ip_by_fail_num_arr = array();

		$sql =$pre_sql= "replace into cp_ip_info(`ID`,`CompetitorID`,`Info`,`AddTime`,`FailNum`,`GoodCatch`,`LastChangeTime`,`Status`)Values";
		$on_date = date("Y-m-d H:i:s");
		$ii=0;
		foreach($ip_list as $key=>$o){
			foreach ($o as $k=>$v){
				if(empty($v['ID'])){ //处理有时重启等数据错乱时去除
					unset($ip_list[$key][$k]);
					continue;
				}

				$ii++;
				$sql.="('{$v['ID']}','{$v['CompetitorID']}','{$v['Info']}','{$v['AddTime']}','{$v['FailNum']}','{$v['GoodCatch']}','{$on_date}','{$v['Status']}'),";

				if($ii%1000==0){
					$db->query(substr($sql,0,-1));
					$sql =$pre_sql;
				}
			}
		}
		if($sql !=$pre_sql){
			$db->query(substr($sql,0,-1));
		}

		
		//获取所有Active并且FailNum=0的ip
		$sql = "SELECT * FROM cp_ip_info WHERE STATUS='active' AND FailNum<".PARAM_CATCH_FAIL_MAX_COUNT_IP;
		$list = $db->getRows($sql);
		if(empty($list)){ //如果读取不到数据剔除状态 ,有限读取GoodCatch > 0  order by LastChangeTime
			$sql = "SELECT * FROM cp_ip_info WHERE  GoodCatch > 0  order by  LastChangeTime desc  limit 200" ;
			$list = $db->getRows($sql);
			if(empty($list)){
				$sql = "SELECT * FROM cp_ip_info WHERE  FailNum < ".PARAM_CATCH_FAIL_MAX_COUNT_IP." limit 200" ;
				$list = $db->getRows($sql);
			}
		}
		
		//判断竞争对手是否有ip，如果没有 按 GoodCatch > 0  order by LastChangeTime 补20个
		$sql = "SELECT ID FROM cp_competitor WHERE IsCatch = 1";
		$competitor_list = $db->getRows($sql,"ID");
		foreach ($list as $obj){
			if(isset($competitor_list[$obj['CompetitorID']])){
					unset($competitor_list[$obj['CompetitorID']]);
			}
		}
		if(!empty($competitor_list)){
			$glob_cs_data = array();
			foreach ($competitor_list as $kk=>$vv){
				$sql = "SELECT * FROM cp_ip_info WHERE CompetitorID= '{$kk}' and   GoodCatch > 0  order by  LastChangeTime desc  limit 20" ;
				$list_row = $db->getRows($sql);
				$glob_cs_data = array_merge($list_row,$glob_cs_data);
			}
			$list = array_merge($list,$glob_cs_data);
		}
		
		if(!empty($list)){
                        $ip_list = array();
			foreach($list as $o){
				$ip_list[$o['CompetitorID']][$o['Info']] = $o;
			}
		}
		
		$this->set_cache(MEM_CACHE_IP_KEY, $ip_list);
		
	}

    public function __destruct()
    {
        // TODO: Implement __destruct() method.
		$this->c_obj->close();
    }
}
?>
