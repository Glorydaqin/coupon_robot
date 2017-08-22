<?php
/**
 * Created by PhpStorm.
 * User: daqin
 * Date: 2017/8/10
 * Time: 20:42
 */
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'Class.CompetitorBase.php';
class Competitor5 extends CompetitorBase {

    // https://www.fyvor.com

    public $country = 'US';
    public $currentUrl = null;  //当前链接
    public $sitePre='https://www.fyvor.com';
    public $content = null;
    public $isUpdateCodeUrl = 0;  //是否需要通过新连接获取code
    public $wrongCouponStrs = array();  //错误coupon跳过
    public $pregSimStore = '/\/coupons\/([^\/]+)/i'; //链接相似度匹配正则

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
        preg_match_all("/<h1[^>]*?>([^<]+?)<\/h1>/", $this->content, $matchH1, PREG_SET_ORDER);
        return empty($matchH1[0]) ? "" : $matchH1[0][1];
    }
    //商家描述
    public function storeDesc(){
        preg_match_all("/class=\"store_de\">(.*?)<a/", $this->content, $matchDesc, PREG_SET_ORDER);
        return empty($matchDesc[0])?'':$matchDesc[0][1];
    }
    //商家GoUrl
    public function storeGoUrl(){
        preg_match_all("/href=\'([^\']+?)\' target=\"_blank\" rel=\"nofollow\" class=\"golink\"/", $this->content, $matchGo);
        return empty($matchGo[0]) ? "" :$this->sitePre.$matchGo[1][0];
    }
    //商家页面Domain
    public function storePageDomain()
    {
        preg_match_all("/Visit\s+([^<]+?)<\/a><\/div><div class=\"store_de\">/", $this->content, $matchPageDomain);
        return empty($matchPageDomain[0])?"":$matchPageDomain[1][0];
    }
    //商家Logo
    public function storeScreenImg(){
        $matchImg = Selector::select($this->content,'*//div[@class=\'mer_pic\']/*/img/@src');
        return empty($matchImg) ? "" :$matchImg;
    }
    //外围 coupon list
    public function couponList()
    {
        $tmp = array();
        $matchValidCoupon = Selector::select($this->content,'*//div[@id="coupon_list"]//div[@class="c_list"]');
        if(is_array($matchValidCoupon)){
            $matchValidCoupon = $matchValidCoupon[0];
        }
        $matchCoupon=explode('class="ds_list',$matchValidCoupon);
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
        preg_match_all("/id=\"divcover_(\d+)\"/",$couponItem,$matchCouponId);
        return empty($matchCouponId[0])?'':$matchCouponId[1][0];
    }
    //couponTitle
    public function couponTitle($couponItem)
    {
        preg_match_all("/class=\"coupon_title\" id=\"coupontitle_\d+\" infos=\"\d+_n\">([\s\S]+?)<\/div>/",$couponItem,$matchCouponTitle);
        return empty($matchCouponTitle[0])?'':$matchCouponTitle[1][0];
    }
    //couponDesc
    public function couponDesc($couponItem)
    {
        preg_match_all("/class=\"cpdesc less\">([^<]+?)<\/span>/",$couponItem,$matchCouponDesc);
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
        preg_match_all("/Expires\s+?([\d\/]+?)\s/",$couponItem,$couponDate);
        if(!empty($couponDate[0])){
            $tmp = date("Y-m-d H:i:s",strtotime($couponDate[1][0]));
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
        preg_match_all("#id=\"couponcode_\d+\">([^<]+?)</span>#",$couponItem,$matchCouponCode);
        return empty($matchCouponCode[0])?'':self::escapeString($matchCouponCode[1][0]);
    }

    /**
     * 获取相关链接
     */
    public function getRelateLinks()
    {
        preg_match_all("/href=\"(\/coupons\/[^\/\"]+\/)\"/i", $this->content, $matchUrl);
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