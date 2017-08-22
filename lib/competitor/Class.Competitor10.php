<?php
/**
 * Created by PhpStorm.
 * User: daqin
 * Date: 2017/8/10
 * Time: 20:42
 */
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'Class.CompetitorBase.php';
class Competitor10 extends CompetitorBase {

    //https://www.vouchercloud.com

    public $country = 'UK';
    public $currentUrl = null;  //当前链接
    public $sitePre='https://www.vouchercloud.com';
    public $content = null;
    public $isUpdateCodeUrl = 0;  //是否需要通过新连接获取code
    public $wrongCouponStrs = array('class="tile-list-signup-wrap"');  //错误coupon跳过
    public $pregSimStore = '/vouchercloud.com\/(.*?)\-vouchers/i'; //链接相似度匹配正则

    //商家MetaTitle
    public function storeMetaTitle(){
        preg_match_all("/<title>([^<]+)<\/title>/", $this->content, $matchTitle, PREG_SET_ORDER);
        return empty($matchTitle[0]) ? "" : $matchTitle[0][1];
    }
    //商家MetaKeywords
    public function storeMetaKeywords(){
        $matchKeywords = Selector::select($this->content,'*//meta[@name="keywords"]/@content');
        return empty($matchKeywords)? '': $matchKeywords;
    }
    //商家MetaDescription
    public function storeMetaDescription(){
        $matchMetaDesc = Selector::select($this->content,"*//meta[@name=\"description\"]/@content");
        return empty($matchMetaDesc) ? "" : $matchMetaDesc;
    }
    //商家H1
    public function storeH1(){
        $matchH1 = Selector::select($this->content,"//h1");
        return empty($matchH1) ? "" : $matchH1;
    }
    //商家描述
    public function storeDesc(){
        $matchDesc = Selector::select($this->content,'//div[@class="widget-text widget-text-about text-area"]');
        return empty($matchDesc)?'':$matchDesc;
    }
    //商家GoUrl
    public function storeGoUrl(){
        $matchGo = Selector::select($this->content,'//a[@class="toolbar-button-website"]/@href');
        if(is_array($matchGo)){
            $matchGo = $matchGo[0];
        }
        return empty($matchGo) ? "" :$this->sitePre.$matchGo;
    }
    //商家页面Domain
    public function storePageDomain()
    {
//        preg_match_all("/Visit\s+([^<]+?)<\/a><\/div><div class=\"store_de\">/", $this->content, $matchPageDomain);
        return '';
    }
    //商家Logo
    public function storeScreenImg(){
        $matchImg = Selector::select($this->content,'//div[@class="banner-logo"]//img/@src');
        return empty($matchImg) ? "" :$matchImg;
    }
    //外围 coupon list
    public function couponList()
    {
        $tmp = array();
        $matchValidCoupon = Selector::select($this->content,'//div[@class="pure-g page-row tile-group"]');
        if(is_array($matchValidCoupon)){
            $matchValidCoupon = $matchValidCoupon[0];
        }
        $matchCoupon=explode('<div class="pure-u-1 page-column"',$matchValidCoupon);
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
        preg_match_all("/id=\"(\d+)\"/",$couponItem,$matchCouponId);
        return empty($matchCouponId[0])?'':$matchCouponId[1][0];
    }
    //couponTitle
    public function couponTitle($couponItem)
    {
        $matchCouponTitle = Selector::select($couponItem,'//h2[@class=\'tile-description\']/span[1]');
        return empty($matchCouponTitle)?'':$matchCouponTitle;
    }
    //couponDesc
    public function couponDesc($couponItem)
    {
        $matchCouponDesc = Selector::select($couponItem,'//h2[@class=\'tile-description\']/p');
        return empty($matchCouponDesc)?'':$matchCouponDesc;
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
        preg_match_all("/Ends\s+?(\d+?)\-(\d+?)\-(\d+?)\s/",$couponItem,$couponDate);
        if(!empty($couponDate[0])){
            $tmp =date("Y-m-d", mktime(null,null,null,$couponDate[2][0],$couponDate[1][0],$couponDate[3][0]) );
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
        if(strripos($couponItem,'class="button-text">GET CODE<')){
            $tmp = $this->currentUrl.'?rid='.$this->couponId($couponItem);
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
        preg_match_all("/href=\"(\/[^\"]+?\-vouchers)\"/i", $this->content, $matchUrl);
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
        $matchCode = Selector::select($this->content,'//div[@id="redemption-code"]');
        return empty($matchCode)?'':trim($matchCode);
    }

}