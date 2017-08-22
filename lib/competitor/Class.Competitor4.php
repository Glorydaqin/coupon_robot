<?php
/**
 * Created by PhpStorm.
 * User: daqin
 * Date: 2017/8/10
 * Time: 20:42
 */
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'Class.CompetitorBase.php';
class Competitor4 extends CompetitorBase {

    // https://www.voucherhoney.co.uk

    public $country = 'UK';
    public $currentUrl = null;  //当前链接
    public $sitePre='https://www.voucherhoney.co.uk';
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
        $matchDesc = Selector::select($this->content,'*//p[@class="desc less"]');
        return empty($matchDesc)?'':$matchDesc;
    }
    //商家GoUrl
    public function storeGoUrl(){
        preg_match_all("/href=\"(\/redirect[^\"]*)\"/", $this->content, $matchGo);
        return empty($matchGo[0]) ? "" :$this->sitePre.$matchGo[1][0];
    }
    //商家页面Domain
    public function storePageDomain()
    {
        preg_match_all("/class=\"icon-external-link\"><\/i>go to\s+(.*?)\s+?<\/a>/", $this->content, $matchPageDomain);
        return empty($matchPageDomain[0])?"":$matchPageDomain[1][0];
    }
    //商家Logo
    public function storeScreenImg(){
        $matchImg = Selector::select($this->content,'*//div[@class="logo-wrapper"]/a/img/@src');
        return empty($matchImg) ? "" :$matchImg;
    }
    //外围 coupon list
    public function couponList()
    {
        $tmp = array();
        $matchValidCoupon = Selector::select($this->content,'*//ul[@id="merchant_coupons"]');
        $matchCoupon=explode('<li data-cid=',$matchValidCoupon);
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
        preg_match_all("/voucher-(\d+).html/",$couponItem,$matchCouponId);
        return empty($matchCouponId[0])?'':$matchCouponId[1][0];
    }
    //couponTitle
    public function couponTitle($couponItem)
    {
        $matchCouponTitle = Selector::select($couponItem,'*//div[@class="promo_title"]/p');
        return empty($matchCouponTitle)?'':$matchCouponTitle;
    }
    //couponDesc
    public function couponDesc($couponItem)
    {
        preg_match_all("/class=\"brief-title\">([\s\S]+?)<\/span/",$couponItem,$matchCouponDesc);
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
        preg_match_all("/expires\s*([^\"]*)\"/", $couponItem, $matchExpirationDate,PREG_SET_ORDER);
        if(!empty($matchExpirationDate)){
            $expires=$matchExpirationDate[0][1];
            if(substr_count($expires, "day")>0){
                preg_match_all("/in ([0-9]*) day/", $expires, $matchDays,PREG_SET_ORDER);
                if(!empty($matchDays)){
                    $tmp=addDates($matchDays[0][1]+1);
                }else{
                    $tmp=null;
                }
            }else{
                $temp_expires=explode("-",$expires);
                if(count($temp_expires)==3){
                    $expires=$temp_expires[2]."-".$temp_expires[1]."-".$temp_expires[0];
                }
                $tmp=dateConv($expires);
            }
        }
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
        preg_match_all("/data-clipboard-text=\"([^\"]*)\"/",$couponItem,$matchCouponCode);
        return empty($matchCouponCode[0])?'':self::escapeString($matchCouponCode[1][0]);
    }

    /**
     * 获取相关链接
     */
    public function getRelateLinks()
    {
        preg_match_all("/href=[\"\'](\/merchant[^\"\']+)[\'\"]/i", $this->content, $matchUrl);
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