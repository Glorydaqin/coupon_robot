<?php
/**
 * Created by PhpStorm.
 * User: daqin
 * Date: 2017/8/10
 * Time: 20:42
 */
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'Class.CompetitorBase.php';
class Competitor19 extends CompetitorBase {

    // https://www.fyvor.com

    public $country = 'UK';
    public $currentUrl = null;  //当前链接
    public $sitePre='https://www.myvouchercodes.co.uk';
    public $content = null;
    public $isUpdateCodeUrl = 1;  //是否需要通过新连接获取code
    public $wrongCouponStrs = array();  //错误coupon跳过
    public $pregSimStore = '/myvouchercodes.co.uk\/([^\/]+)/i'; //链接相似度匹配正则

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
        preg_match_all("/itemprop=\"description\">([\s\S]+?)<\/div>/", $this->content, $matchDesc, PREG_SET_ORDER);
        return empty($matchDesc[0])?'':$matchDesc[0][1];
    }
    //商家GoUrl
    public function storeGoUrl(){
        preg_match_all("/class=\"InfoHeaderMerchant-cta Offer-cta\" href=\"([^\"]+)\"/", $this->content, $matchGo);
        return empty($matchGo[0]) ? "" :$this->sitePre.$matchGo[1][0];
    }
    //商家页面Domain
    public function storePageDomain()
    {
        return '';
    }
    //商家Logo
    public function storeScreenImg(){
        $matchImg = Selector::select($this->content,'*//div[@class="InfoHeaderMerchant-col1-logo"]/img/@src');
        return empty($matchImg) ? "" :$matchImg;
    }
    //外围 coupon list
    public function couponList()
    {
        $tmp = array();
        $matchValidCoupon = Selector::select($this->content,'*//div[@class="OfferList offers offers-standard"]');
        $matchCoupon=explode('<li id',$matchValidCoupon);
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
        preg_match_all("/=\"([^\"]+?)\" class=\"Offer\s/",$couponItem,$matchCouponId);
        return empty($matchCouponId[0])?'':$matchCouponId[1][0];
    }
    //couponTitle
    public function couponTitle($couponItem)
    {
        preg_match_all("/class=\"Offer-title\"><a href=\"[^\"]+\"[^>]+?>([\s\S]+?)<\/a><\/h3>/",$couponItem,$matchCouponTitle);
        return empty($matchCouponTitle[0])?'':$matchCouponTitle[1][0];
    }
    //couponDesc
    public function couponDesc($couponItem)
    {
        preg_match_all("/<p class=\"Offer-text truncate\"[^>]+>(.*?)<\/p>/",$couponItem,$matchCouponDesc);
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
        preg_match_all("/class=\"Offer-expiry-date\">Ends: (.*?) - <\/span>/",$couponItem,$couponDate);
        if(!empty($couponDate[0])){
            $tmp = date("Y-m-d H:i:s",strtotime(str_replace('/', '-',$couponDate[1][0])));
        }
        if(empty($tmp)){
            $tmp='0000-00-00';
        }
        return $tmp;
    }
    //couponCodeUrl
    public function couponCodeUrl($couponItem)
    {
        $matchType = Selector::select($couponItem,"//div[@class=\"Offer-container\"]/a/span");
        if(strripos(strtolower($matchType),'code')){
            $url = "https://www.myvouchercodes.co.uk/system/ajax-offer?offer=".$this->couponId($couponItem);
        }else{
            $url = '';
        }
        return $url;
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
        preg_match_all("/href=\"(\/[^\/\"]+)\"/i", $this->content, $matchUrl);
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
//        preg_match_all("/data-clipboard-text=\"([^\"]+)\"/", $this->content, $matchCode);
//        return empty($matchCode[0])?'':trim($matchCode[1][0]);
    }

}