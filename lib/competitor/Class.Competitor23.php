<?php
/**
 * Created by PhpStorm.
 * User: daqin
 * Date: 2017/8/10
 * Time: 20:42
 */
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'Class.CompetitorBase.php';
class Competitor23 extends CompetitorBase {

    // https://www.rabattcode.de

    public $country = 'DE';
    public $currentUrl = null;  //当前链接
    public $sitePre='https://www.rabattcode.de';
    public $content = null;
    public $isUpdateCodeUrl = 0;  //是否需要通过新连接获取code
    public $wrongCouponStrs = array();  //错误coupon跳过
    public $pregSimStore = '/\/rabattcode.de\/([^\/]+)/i'; //链接相似度匹配正则

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
        $matchH1 = Selector::select($this->content,"*//h1");
        return empty($matchH1) ? "" : $matchH1;
    }
    //商家描述
    public function storeDesc(){
        preg_match_all("/<\/h1>\s*<p>(.*?)<\/p>/", $this->content, $matchDesc, PREG_SET_ORDER);
        return empty($matchDesc[0])?'':$matchDesc[0][1];
    }
    //商家GoUrl
    public function storeGoUrl(){
        $matchGo = Selector::select($this->content,'//a[@class="visit-shop hidden-xs"]/@href');
        return empty($matchGo) ? "" :$this->sitePre.$matchGo;
    }
    //商家页面Domain
    public function storePageDomain()
    {
        return '';
    }
    //商家Logo
    public function storeScreenImg(){
        $matchImg = Selector::select($this->content,'*//div[@class="header-store-thumb"]//img/@src');
        if(is_array($matchImg)){
            $matchImg = $matchImg[0];
        }
        return empty($matchImg) ? "" :$this->sitePre.$matchImg;
    }
    //外围 coupon list
    public function couponList()
    {
        $tmp = array();
        $matchValidCoupon = Selector::select($this->content,'*//div[@class="store-listings"]');
        $matchCoupon=explode('<div class="voucher-item"',$matchValidCoupon);
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
        preg_match_all("/data-id=\"(\d+)\"/",$couponItem,$matchCouponId);
        return empty($matchCouponId[0])?'':$matchCouponId[1][0];
    }
    //couponTitle
    public function couponTitle($couponItem)
    {
        preg_match_all("/<h3 class=\"coupon-title\"><a href=\"[^\"]+\" rel=\"nofollow\">(.*?)<\/a>/",$couponItem,$matchCouponTitle);
        return empty($matchCouponTitle[0])?'':$matchCouponTitle[1][0];
    }
    //couponDesc
    public function couponDesc($couponItem)
    {
        preg_match_all("/<div class=\"coupon-des\">(.*?)<\/div>/",$couponItem,$matchCouponDesc);
        return empty($matchCouponDesc[0])?'':$matchCouponDesc[1][0];
    }
    //couponGoUrl
    public function couponGoUrl($couponItem)
    {
        preg_match_all("/<h3 class=\"coupon-title\"><a href=\"([^\"]+)\" rel=\"nofollow\">.*?<\/a>/",$couponItem,$matchGoUrl);
        return empty($matchGoUrl[0])?'':$this->sitePre.$matchGoUrl[1][0];
    }
    //couponExpirationDate
    public function couponExpirationDate($couponItem)
    {
        $tmp = '';
        preg_match_all("/Gültig bis ([\d\.]+)/",$couponItem,$coupondate);
        if(!empty($coupondate[0])){
            $tmp=date('Y-m-d', strtotime($coupondate[1][0]));
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
//        preg_match_all("/<span>([^\/]+)<\/span><\/div>/", $couponItem,$matchType);
//        if(isset($matchType[1][0]) && strripos($matchType[1][0] ,'Code')){
//            $tmp=$this->currentUrl.'?promoid='.$this->couponId($couponItem);
//        }
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
        preg_match_all("/href=\"(\/.*?-gutschein\/)\"/i", $this->content, $matchUrl);
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
        preg_match_all("/<div class=\"code-text\">(.*?)<\/div>/", $this->content, $matchCode);
        $code=empty($matchCode[0])?'':trim($matchCode[1][0]);

        return $code;
    }

}