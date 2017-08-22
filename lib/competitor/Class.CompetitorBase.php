<?php
/**
 * Created by PhpStorm.
 * User: daqin
 * Date: 2017/8/10
 * Time: 20:42
 */
class CompetitorBase{

    // https://www.promospro.com

    public $country = 'US';
    public $currentUrl = null;  //当前链接
    public $sitePre='https://www.promospro.com';
    public $content = null;
    public $isUpdateCodeUrl = 0;  //是否需要通过新连接获取code
    public $wrongCouponStrs = array('class="sc_label"');  //错误coupon跳过
    //链接相似度匹配正则
    public $pregSimStore = '/\/store\/(.*)/i';

    public function __construct($content,$currentUrl)
    {
        if(empty($content) || empty($currentUrl)){
            die("empty args");
        }
        $this->content=$content;
        $this->currentUrl = $currentUrl;
    }


    //商家MetaTitle
    public function storeMetaTitle(){
        preg_match_all("/<title>([^<]+)<\/title>/", $this->content, $matchTitle, PREG_SET_ORDER);
        return empty($matchTitle[0]) ? "" : $matchTitle[0][1];
    }
    //商家MetaKeywords
    public function storeMetaKeywords(){
        return '';
    }
    //商家MetaDescription
    public function storeMetaDescription(){
        preg_match_all("/<meta content=\"([^\"]+)\" name=\"description\"/", $this->content, $matchMetaDesc, PREG_SET_ORDER);
        return empty($matchMetaDesc[0]) ? "" : $matchMetaDesc[0][1];
    }
    //商家H1
    public function storeH1(){
        preg_match_all("/<h1>(.*?)<\/h1>/", $this->content, $matchH1, PREG_SET_ORDER);
        return empty($matchH1[0]) ? "" : $matchH1[0][1];
    }
    //商家描述
    public function storeDesc(){
        preg_match_all("/class=\"merchant_description less\">(.*?)<\/p>/", $this->content, $matchDesc, PREG_SET_ORDER);
        return empty($matchDesc[0])?'':$matchDesc[0][1];
    }
    //商家GoUrl
    public function storeGoUrl(){
        preg_match_all("/href=\"([^\"]+)\" target=\"_blank\" rel=\"nofollow\" class=\"button mgos\"/", $this->content, $matchGo);
        return empty($matchGo[0]) ? "" :$this->sitePre.$matchGo[1][0];
    }
    //商家页面Domain
    public function storePageDomain()
    {
        preg_match_all("/class=\"button mgos\">go to (.*?)<i/", $this->content, $matchPageDomain,PREG_SET_ORDER);
        return empty($matchPageDomain[0])?"":$matchPageDomain[0][1];
    }
    //商家Logo
    public function storeScreenImg(){
        preg_match_all("/class=\"mgos\"><img alt=\"[^\"]+\"\s+src=\"([^\"]+)\"/",$this->content,$matchImg);
        return empty($matchImg[0]) ? "" :$matchImg[1][0];
    }
    //外围 coupon list
    public function couponList()
    {
//        $tmp = array();
//        $matchValidCoupon = Selector::select($this->content,'*//div[@id="sub_term_coupon_US"]/div[@class="c_list"]');
//        $matchCoupon=explode('class="ds_list',$matchValidCoupon);
//        if (!empty($matchCoupon)) {
//            for ($i = 1 ;$i<count($matchCoupon);$i++){
//                $tmp[]=$matchCoupon[$i];
//            }
//        }
//        return $tmp;
    }
    //couponId
    public function couponId($couponItem)
    {
        preg_match_all("/\"(\d+)\" data-block=\"coupon\"/",$couponItem,$matchCouponId);
        return empty($matchCouponId[0])?'':$matchCouponId[0][1];
    }
    //couponTitle
    public function couponTitle($couponItem)
    {
        preg_match_all("/<h3>([\s\S]+?)<\/h3>/",$couponItem,$matchCouponTitle);
        return empty($matchCouponTitle[0])?'':$matchCouponTitle[1][0];
    }
    //couponDesc
    public function couponDesc($couponItem)
    {
        preg_match_all("/class=\"details less\">(.*?)<\/div>/",$couponItem,$matchCouponDesc);
        return empty($matchCouponDesc[0])?'':$matchCouponDesc[1][0];
    }
    //couponGoUrl
    public function couponGoUrl($couponItem)
    {
        return '';
    }
    //couponExpirationDate
    public function couponExpirationDate($couponItem)
    {
        $tmp = '';
        preg_match_all("/icon-time\"><\/i>\s*([\d\/]+)\s*<\/li>/",$couponItem,$couponDate);
        if(!empty($couponDate[0])){
            $arr_time=explode('/',$couponDate[1][0] );
            $tmp=date('Y-m-d',mktime(0,0,0,$arr_time[0],$arr_time[1],$arr_time[2]));
        }
        if(empty($tmp)){
            $tmp='0000-00-00';
        }
        return $tmp;
    }
    //couponCodeUrl
    public function couponCodeUrl($couponItem)
    {
        $tmp= '';
        preg_match_all("/<span>([^\/]+)<\/span><\/div>/", $couponItem,$matchType);
        if(isset($matchType[1][0]) && strripos($matchType[1][0] ,'Code')){
            $tmp=$this->currentUrl.'?promoid='.$this->couponId($couponItem);
        }
        return $tmp;
    }
    //couponCode
    public function couponCode($couponItem)
    {
        return '';
    }


    /**
     * 获取相关链接
     */
    public function getRelateLinks()
    {
        return '';
    }

    /**
     * 获取独立抓取code
     */
    public function getNewtabCode()
    {
        return '';
    }

    /**
     * 获取临时商家信息
     */
    public function getTempStoreInfo()
    {
        $tmp_cs = array();

        $tmp_cs['H1'] = $this->escapeString($this->storeH1());
        $tmp_cs['GoUrl'] = $this->escapeString($this->storeGoUrl());
        $tmp_cs['PageDomainUrl'] = $this->escapeString($this->storePageDomain());

        return $tmp_cs;
    }

    /**
     * 获取商家信息
     */
    public function getStoreInfo()
    {
        $cs_data_arr = array();

        $cs_data_arr['MetaTitle'] = $this->escapeString($this->storeMetaTitle());
        $cs_data_arr['MetaKeywords'] = $this->escapeString($this->storeMetaKeywords());
        $cs_data_arr['MetaDescription'] = $this->escapeString($this->storeMetaDescription());
        $cs_data_arr['Description'] = $this->escapeString($this->storeDesc(),500);
        $cs_data_arr['H1'] = $this->escapeString($this->storeH1());
        $cs_data_arr['MerchantGoUrl'] = $this->escapeString($this->storeGoUrl());
        $cs_data_arr['ScreenImg'] = $this->escapeString($this->storeScreenImg());

        return $cs_data_arr;
    }

    /**
     * 获取coupons 列表
     */
    public function getCoupons()
    {
        $tmp = array();
        $couponList = $this->couponList();
        $rank = 0;
        foreach ($couponList as $item){
            $couponData['MaybeValid'] = 1;
            $couponData['Country'] = $this->country;
            $couponData['CouponID'] =$rank;
            $couponData['CouponTitle']='';
            $couponData['CouponDesc']='';
            $couponData['GoUrl']='';
            $couponData['type']='deal';
            $couponData['ExpirationDate']='0000-00-00';
            $couponData['CouponCodeUrl'] = "";
            $couponData['CouponCode']='';
            $couponData['IsUpdateCodeUrl']=$this->isUpdateCodeUrl;

            //错误数据跳过
            $wrong_count =0;
            foreach ($this->wrongCouponStrs as $wrongCouponStr){
                if(strripos($item,$wrongCouponStr)>0){
                    $wrong_count++;
                    break;
                }
            }
            if($wrong_count>0){
                continue;
            }

            $couponId = $this->couponId($item);
            if(!empty($couponId)){
                $couponData['CouponID']=$this->escapeString($couponId,50);
            }
            $couponTitle = $this->couponTitle($item);
            if(!empty($couponTitle)){
                $couponData['CouponTitle']=$this->escapeString($couponTitle);
            }
            $couponDesc = $this->couponDesc($item);
            if(!empty($couponDesc)){
                $couponData['CouponDesc']=$this->escapeString($couponDesc,500);
                if(empty($couponData['CouponTitle']))
                    $couponData['CouponTitle'] = $couponData['CouponDesc'];
            }
            $couponGoUrl = $this->couponGoUrl($item);
            if(!empty($couponGoUrl)){
                $couponData['GoUrl']=$this->escapeString($couponGoUrl);
            }
            $couponCode = $this->escapeString($this->couponCode($item));
            $couponCodeUrl = $this->escapeString($this->couponCodeUrl($item));
            if(empty($couponCode)){
                if(empty($couponCodeUrl)){
                    $couponData['type']='deal';
                    $couponData['IsUpdateCodeUrl']=0;
                }else{
                    $couponData['type']='code';
                    $couponData['CouponCodeUrl'] = $couponCodeUrl;
                    $couponData['IsUpdateCodeUrl']=1;
                }
            }else{
                $couponData['type']='code';
                $couponData['CouponCode']= $couponCode;
                $couponData['IsUpdateCodeUrl']=0;
            }
            $couponExpirationDate = $this->escapeString($this->couponExpirationDate($item));
            if(!empty($couponExpirationDate)){
                $couponData['ExpirationDate']= $couponExpirationDate;
            }

            $tmp[] = $couponData;
            $rank++;
        }
        return $tmp;
    }


    /**
     *  转义处理
     * @param $str 待处理数据
     * @param $max_len 最大长度
     * @return 处理后字符串
     */
    public function escapeString($str,$max_len=255){
        if(is_array($str)){
            $str = $str[0];
        }
        $str = strip_tags(trim($str));
        $str = preg_replace("/[\s]+/is"," ",$str);
        $str = substr($str,0,$max_len);
        $str = addslashes($str);
        return $str;
    }
}