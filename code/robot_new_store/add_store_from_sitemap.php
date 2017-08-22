<?php
/*添加新store ,根据 temp cs表*/
include_once dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . 'etc/initiate.php';

$row['CompetitorId'] = isset($_SERVER["argv"][1])?$_SERVER["argv"][1]:0;

$num = checkScriptProcessCount(basename(__FILE__));  //检测是否多开
if($num > 1) exit(basename(__FILE__).": catch ing !\n");

if(empty($row['CompetitorId'])){
	exit('cid is empty');
}
echo " start time:".date("Y-m-d H:i:s")."-\n";

set_time_limit(1000);

$db = new Mysql(DB_NAME, DB_HOST, DB_USER, DB_PWD);

$htmlContent=file_get_contents('sitemap'.DIRECTORY_SEPARATOR.$row['CompetitorId'].'.xml');

if($row['CompetitorId']==4){
    preg_match_all("/(http:\/\/www.promospro.co.uk\/merchant\-.*?\.html\s)/i", $htmlContent, $matchUrl);
}elseif($row['CompetitorId']==6){
	preg_match_all("/<loc>(https:\/\/www.retailmenot.com\/view\/[^\/]+)<\/loc>/i", $htmlContent, $matchUrl);
}else if($row['CompetitorId']==8){
	preg_match_all("/<loc>(https:\/\/www.goodsearch.com\/coupons\/[^<]+?)<\/loc>/i", $htmlContent, $matchUrl);

}else if($row['CompetitorId']==9){
	preg_match_all("/<loc>(https:\/\/couponfollow.com\/site\/[^<]+?)<\/loc>/i", $htmlContent, $matchUrl);

}else if($row['CompetitorId']==10){
	preg_match_all("/<loc>(https:\/\/www.vouchercloud.com\/.+?\-vouchers)<\/loc>/i", $htmlContent, $matchUrl);

}else if($row['CompetitorId']==11){
	preg_match_all("/<loc>([^<]+)<\/loc>/i", $htmlContent, $matchUrl);
}else if($row['CompetitorId']==14){
	//cid 14
	preg_match_all("/(http\:\/\/www\.thebargainavenue\.com\.au\/interests\/[^\/]+\/coupons\/[^\s]+)/i", $htmlContent, $matchUrl);

}else if($row['CompetitorId']==15){
	//cid 15
	preg_match_all("/href\=\"\/store\/([^\?\/\"]+)\"/i", $htmlContent, $matchUrl);

}else if($row['CompetitorId']==16){
	//cid 15
	preg_match_all("/href\=\"(https:\/\/coupns.com.au\/stores\/[^\/]+\/)\"/i", $htmlContent, $matchUrl);

}else if($row['CompetitorId']==18){
	//cid 15
	preg_match_all("/<loc>(https:\/\/www.vouchercodes.co.uk\/[^<]+?)<\/loc>/i", $htmlContent, $matchUrl);

}else if($row['CompetitorId']==19){
	//cid 15
	preg_match_all("/(https:\/\/www\.myvouchercodes\.co\.uk\/[^\/]+?)\s/i", $htmlContent, $matchUrl);

}else if($row['CompetitorId']==20){
	//cid 15
	preg_match_all("/>(http\:\/\/www.gutschein.de\/[^\/]+\/)</i", $htmlContent, $matchUrl);

}else if($row['CompetitorId']==21){
	//cid 15
	preg_match_all("/>\s*(https:\/\/www.gutscheinpony.de\/gutscheine\/[^<]+)\s*</i", $htmlContent, $matchUrl);
}else if($row['CompetitorId']==23){
	//cid 15
	preg_match_all("/(http:\/\/www.rabattcode.de\/[^\/]+?-gutschein\/)/i", $htmlContent, $matchUrl);
}else if($row['CompetitorId']==29){
	//cid 15
	preg_match_all("/>(http:\/\/codepromo.lexpress.fr\/code-promo-[^<]+)</i", $htmlContent, $matchUrl);
}else if($row['CompetitorId']==26){
	//cid 15
	preg_match_all("/>(http:\/\/www.ma-reduc.com\/reductions-[^<]+)</i", $htmlContent, $matchUrl);
}else if($row['CompetitorId']==25){
	//cid 15
	preg_match_all("/(http:\/\/www.codespromofr.com\/code-promo-[^\/]+)/i", $htmlContent, $matchUrl);
}else if($row['CompetitorId']==31){
    //cid 15
    preg_match_all("/href=\"(https:\/\/www.gutscheinemagazin.de\/[^\/]+\/)\"/i", $htmlContent, $matchUrl);
}else if($row['CompetitorId']==32){
    //cid 15
    preg_match_all("/href=\"(http:\/\/gutscheine.focus.de\/gutscheine\/[^\/\"]+)\"/i", $htmlContent, $matchUrl);
}else if($row['CompetitorId']==35){
    //cid 15
    preg_match_all("/href=\"(offerte-codice-sconto-.*?.html)\"/i", $htmlContent, $matchUrl);
}else if($row['CompetitorId']==37){
    //cid 15
    preg_match_all("/\s(http:\/\/www.codicepromozionalecoupon.it\/[^\/]+\/)\s/i", $htmlContent, $matchUrl);
}else if($row['CompetitorId']==33){
    //cid 15
    preg_match_all("/<loc>(http:\/\/www.retailmenot.it\/[^\/]+)<\/loc>/i", $htmlContent, $matchUrl);
}else if($row['CompetitorId']==41){
    //cid 15
    preg_match_all("/<loc>(https:\/\/www.savvybeaver.ca\/.*?-coupons)<\/loc>/i", $htmlContent, $matchUrl);
}else if($row['CompetitorId']==42){
    //cid 15
    preg_match_all("/href=\"(http:\/\/vouchercodes.ca\/stores\/.+?)\"/i", $htmlContent, $matchUrl);
}else if($row['CompetitorId']==44){
    //cid 15
    preg_match_all("/href=\"(http:\/\/coupons.ca\/[^\/]+?)\"/i", $htmlContent, $matchUrl);
}else if($row['CompetitorId']==45){
    //cid 15
    preg_match_all("/loc>(http:\/\/sconti.corriere.it\/[\w\-]+?)<\/loc/i", $htmlContent, $matchUrl);
}else if($row['CompetitorId']==46){
    //cid 15
    preg_match_all("/>(http:\/\/www.acties.nl\/[^\/]+)</i", $htmlContent, $matchUrl);
}else if($row['CompetitorId']==47){
    //cid 15
    preg_match_all("/href=\"(http:\/\/www.kortingscode.nl\/[^\"\/]+)\"/i", $htmlContent, $matchUrl);
}else if($row['CompetitorId']==48){
    //cid 15
    preg_match_all("/href=\"(\/[^\/\"]+)\"/i", $htmlContent, $matchUrl);
}else if($row['CompetitorId']==49){
    //cid 15
    preg_match_all("/href=\"(https:\/\/mrkortingscode.nl\/[^\/]+)\/\"/i", $htmlContent, $matchUrl);
}else if($row['CompetitorId']==50){
    //cid 15
    preg_match_all("/href=\"(\/descuentos-[^\.]+.html)\"/i", $htmlContent, $matchUrl);
}else if($row['CompetitorId']==51){
    //cid 15
    preg_match_all("/<loc>(https:\/\/www.cupones.es\/[^\/]+?)<\/loc>/i", $htmlContent, $matchUrl);
}else if($row['CompetitorId']==52){
    //cid 15
    preg_match_all("/<loc>(https:\/\/kupon.pl\/[\w\-]+?)<\/loc>/i", $htmlContent, $matchUrl);
}else if($row['CompetitorId']==53){
    //cid 15
    preg_match_all("/>(https:\/\/www.qpony.pl\/[^\/\,]+?)</i", $htmlContent, $matchUrl);
}else if($row['CompetitorId']==54){
    //cid 15
    preg_match_all("/href=\"(http:\/\/www.promoszop.pl\/kupony-rabatowe\/[^\"]+)\"/i", $htmlContent, $matchUrl);
}else if($row['CompetitorId']==55){
    //cid 15
    preg_match_all("/\"(http:\/\/alerabat.com\/kod[y]*-promocyjn[ey]\/[^\"]+)/i", $htmlContent, $matchUrl);
}else if($row['CompetitorId']==56){
    //cid 15
    preg_match_all("/>(http:\/\/www.codigosdescuentospromocionales.es\/codigos-de-descuentos-[^<]+)</i", $htmlContent, $matchUrl);
}else if($row['CompetitorId']==57){
    //cid 15
    preg_match_all("/>(http:\/\/www.cuponation.es\/[^\/<]+)</i", $htmlContent, $matchUrl);
}else if($row['CompetitorId']==58){
    //cid 15
    preg_match_all("/href=\"(\/gutscheine\/.*?\/)\"/i", $htmlContent, $matchUrl);
}

$sqlInsUrl=$sqlInsUrlPre="insert ignore into cp_temp_competitor_store(StoreUrl,CompetitorId,AddTime) values ";
if(!empty($matchUrl)){
    $i = 0;
	foreach ($matchUrl[1] as $url){
			//链接拼接
		if($row['CompetitorId']==15){
			$url='https://www.topbargains.com.au/store/'.$url;
		}else if($row['CompetitorId']==35){
            $url='http://www.piucodicisconto.com/'.$url;
        }else if($row['CompetitorId']==48){
            $url='http://www.actiepagina.nl'.$url;
        }else if($row['CompetitorId']==50){
            $url='https://cupon.es'.$url;
        }else if($row['CompetitorId']==58){
            $url='http://de.fyvor.com'.$url;
        }

		$sqlInsUrl.="('".addslashes($url)."',{$row['CompetitorId']},'".date("Y-m-d H:i:s")."'),";
        if($i == 300){
            $sqlInsUrl=substr($sqlInsUrl,0,-1);
            $db->query($sqlInsUrl);
            $sqlInsUrl=$sqlInsUrlPre;
            $i = 0;
        }
        $i++;
	}
	if($sqlInsUrl!=$sqlInsUrlPre){
		$sqlInsUrl=substr($sqlInsUrl,0,-1);
		$db->query($sqlInsUrl);
	}
}
$db->close();
echo " end time:".date("Y-m-d H:i:s")."-\n";