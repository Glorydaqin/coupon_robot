<?php
/**
 * Created by PhpStorm.
 * User: daqin
 * Date: 2017/8/10
 * Time: 20:42
 */
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'Class.CompetitorBase.php';
class Competitor3 extends CompetitorBase {

    // https://www.promospro.com

    public $country = 'US';
    public $currentUrl = null;  //当前链接
    public $sitePre='https://www.promospro.com';
    public $content = null;
    public $isUpdateCodeUrl = 0;  //是否需要通过新连接获取code
    public $wrongCouponStrs = array('class="sc_label"');  //错误coupon跳过
    //链接相似度匹配正则
    public $pregSimStore = '/\/store\/(.*)/i';

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
        $matchDesc = Selector::select($this->content,'//p[@itemprop="description"]');
        return empty($matchDesc)?'':$matchDesc;
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
        $matchImg = Selector::select($this->content,'//span[@class="mgos"]/img/@src');
        if(is_array($matchImg)){
            $matchImg = $matchImg[0];
        }
        return empty($matchImg) ? "" :$matchImg;
    }
    //外围 coupon list
    public function couponList()
    {
        $tmp = array();
        preg_match_all("/class=\"list_coupons clear\"([\s\S]*?)\"wrapper2\"/", $this->content, $matchValidCoupon);
        $matchCoupon=explode('<article data-cid',$matchValidCoupon[1][0]);
        if (!empty($matchCoupon)) {
            for ($i = 1 ;$i<count($matchCoupon);$i++){
                $tmp[]=$matchCoupon[$i];
            }
        }
        return $tmp;
    }
    //couponId
    public function couponId($couponItem)
    {
        preg_match_all("/\"(\d+)\" data-block=\"coupon\"/",$couponItem,$matchCouponId);
        return empty($matchCouponId[0])?'':$matchCouponId[1][0];
    }
    //couponTitle
    public function couponTitle($couponItem)
    {
        preg_match_all("/<h3>([\s\S]+?)<\/h3>/",$couponItem,$matchCouponTitle);
        return empty($matchCouponTitle[0])?'':deal_text($matchCouponTitle[1][0]);
    }
    //couponDesc
    public function couponDesc($couponItem)
    {
        preg_match_all("/class=\"details less\">(.*?)<\/div>/",$couponItem,$matchCouponDesc);
        return empty($matchCouponDesc[0])?'':deal_text($matchCouponDesc[1][0]);
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
        preg_match_all("/href=\"(\/promo-codes-[^\"]+)\"/i", $this->content, $matchUrl);
        if(!empty($matchUrl[0])){
            foreach ($matchUrl[1] as &$value){
                //添加前缀
                $value = $this->sitePre.$value;
            }
            return $matchUrl[1];
        }else{
            return '';
        }
    }

    /**
     * 获取独立抓取code
     */
    public function getNewtabCode()
    {
        preg_match_all("/data-clipboard-text=\"([^\"]+)\"/", $this->content, $matchCode);
        return empty($matchCode[0])?'':trim($matchCode[1][0]);
    }

}