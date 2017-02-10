<?php
# finally it works *_*

include("libs/phpexcel/PHPExcel.php");
include("libs/progress_bar.php");
$whois=[];

$shortopts  = "";
$shortopts .= "o:";  // Required value for output file
$shortopts .= "f::"; // Optional value filename
$shortopts .= "q::"; // Optional value query
$shortopts .= "h"; // value help
$longopts  = array(
    "help"     // Help value
);

$options = getopt($shortopts, $longopts);

#var_dump($options);die();
if(isset($options['h']) || isset($options['help'])){
	echo "-f\t\tfile with queries.\n-q\t\tstring query to search.\n-o\t\toutput excel file.\n";
	die();
}
if(isset($options['f']) && isset($options['o']) && !isset($options['q'])){
	$arr=get_cookies();
	$cookies=$arr[0];
	$viewstate=$arr[1];
	$search_queries=file_get_contents($options['f']);
	$search_queries=explode("\n",$search_queries);
	foreach($search_queries as $search_query){
		$whois_temp=search_ripe(urlencode(trim($search_query)),$viewstate,$cookies);
		$whois=array_merge($whois,$whois_temp);
	}
} else if(isset($options['q'])&& isset($options['o']) && !isset($options['f'])){
	$arr=get_cookies();
	$cookies=$arr[0];
	$viewstate=$arr[1];
	$search_query=urlencode($options['q']);
	$whois=search_ripe($search_query,$viewstate,$cookies);
} else {
	echo "Error";
	die();
}

build_excel($whois,$options['o']);


#~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~#
function get_cookies(){
	$page=request("https://apps.db.ripe.net/search/full-text.html","",0,"");
	preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $page, $matches);
	$cookies = "cookies-accepted=accepted; ".$matches[1][0];
	preg_match_all("~<input.*?name=\"javax.faces.ViewState\".*?value=\"(.*?)\".*?>~", $page, $matches);
	$viewstate=$matches[1][0];
	$arr=[$cookies,$viewstate];
	return $arr;
}
function search_ripe($search_query,$viewstate,$cookies){	
	#viewstate might need updating in some time as well as sessionid in cookies
	
	$pars='home_search=home_search&home_search%3Asearchform_q='.$search_query.'&home_search%3AadvancedSearch%3AtypeSelectBox=1&home_search%3AadvancedSearch%3AselectObjectType=inet6num&home_search%3AadvancedSearch%3AselectObjectType=inetnum&home_search%3AadvancedSearch%3AselectFieldType=descr&home_search%3AadvancedSearch%3AselectFieldType=org&home_search%3AdoSearch=Search&javax.faces.ViewState='.urlencode($viewstate);
	
	#first initial request. catches first page.
	$page=request("https://apps.db.ripe.net/search/full-text.html",$pars,1,$cookies);

	#if there is no results, die
	if(preg_match("~No results were found for your search. Your search details may be too selective~",$page)){
		Echo "\nNothing found for query: ".$search_query."\n";
		return [];
	}
	$i=1;
	$links=[];
	#for 6 pages start collecting links
	while($i<6){
		$temp=get_links($page);
		$links=array_merge($links,$temp);
		$pars="resultsView%3ApaginationViewTop%3ApaginationForm=resultsView%3ApaginationViewTop%3ApaginationForm&resultsView%3ApaginationViewTop%3ApaginationForm%3Amain%3Aafter%3Arepeat%3A0%3AbyIndex=".$i."&javax.faces.ViewState=".$viewstate;
		#$pars="resultsView%3ApaginationView%3AdpaginationForm=resultsView%3ApaginationView%3AdpaginationForm&resultsView%3ApaginationView%3AdpaginationForm%3Amain%3Aafter%3Arepeat%3A0%3AbyIndex=2&javax.faces.ViewState=-5843575552417549428%3A-2593155963285978645";
		$page=request("https://apps.db.ripe.net/search/full-text.html",$pars,1,$cookies);
		if(!preg_match("~id=\"results\"~",$page)) break;
		$i++;
	}
	#now when we have all ripe links, we need to collect whois data on every network.
	echo "\nCollected ".count($links)." networks for query: {$search_query}\n\n";
	$stat=1;
	$whois=[];
	foreach ($links as $link) {
		#echo $link;
		str_replace(" ","",$link);
		$link=trim($link);
		$page=request($link,0,0,"");
		$whois[]=get_data($page);
		#$whois=array_merge($whois,$temp);
		show_status($stat,count($links),15);
		$stat++;
	}
	return $whois;
}

function build_excel($whois,$filename){
	$phpexcel = new PHPExcel();
	$page = $phpexcel->setActiveSheetIndex(0);
	$page->setCellValue("A1", "inetnum");
	$page->setCellValue("B1", "netname"); 
	$page->setCellValue("C1", "descr");
	$page->setCellValue("D1", "country");
	$i=2;
	foreach($whois as $el){
		$page->setCellValue("A".$i, $el['inetnum']); 
		$page->setCellValue("B".$i, $el['netname']); 
		$page->setCellValue("C".$i, $el['descr']); 
		$page->setCellValue("D".$i, $el['country']); 
		$i++;
	}
	echo "DONE";
	$page->setTitle("networks");
	$objWriter = PHPExcel_IOFactory::createWriter($phpexcel, 'Excel2007');
	$objWriter->save($filename);
}
function get_data($page){
	$dom = new DOMDocument;
	@$dom->loadHTML($page);
	$xpath = new DOMXPath($dom);
	$lis = $xpath->query('//ul[@class="attrblock"]/li');
	
	$whois=array('inetnum'=>'','orgname'=>'','netname'=>'','country'=>'','descr'=>'');
	foreach($lis as $li){
		$t=explode(":         ",$li->nodeValue);
		if(!isset($t[1])) $t=explode(":   ",$li->nodeValue);
		if(!isset($t[1])) $t=explode(":  ",$li->nodeValue);
		$name=trim($t[0]);
		$val=trim($t[1]);
		$val=preg_replace("~<a.*?>(.*?)</a>~",'$1',$val);
		if((stristr($name,"inetnum") || stristr($name,"domain")) && strlen($whois['inetnum'])<2){
			$whois['inetnum']=$val;
		}
		else if(stristr($name,"netname")&& strlen($whois['netname'])<2)$whois['netname']=$val;
		else if(stristr($name,"orgname")&& strlen($whois['orgname'])<2)$whois['orgname']=$val;
		else if(stristr($name,"country")&& strlen($whois['country'])<2)$whois['country']=$val;
		else if(stristr($name,"descr")) $whois['descr'].=$val." ";
	}
	return $whois;
}

function get_links($page){
	$dom = new DOMDocument;
	@$dom->loadHTML($page);
	$xpath = new DOMXPath($dom);
	$results = $xpath->query('//div[@id="results"]');
	$hrefs= $xpath->query("descendant::a/@href",$results[0]);
	foreach($hrefs as $h){
		if(!stristr($h->value,"ripe.net") && strstr($h->value," - "))$links[]="https://apps.db.ripe.net/search/".urlencode($h->value);
	}
	if(!empty($links)){
		return $links;
	}else return [];
}

function request($link,$pars,$post,$cookies){ 
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $link);
	curl_setopt($ch, CURLOPT_HEADER, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
	curl_setopt($ch, CURLOPT_PROXY, "localhost"); 
	curl_setopt($ch, CURLOPT_PROXYPORT, 8080); 
	curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:47.0) Gecko/20100101 Firefox/47.0');
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_COOKIE, $cookies);
    if($post==1){
    	curl_setopt($ch, CURLOPT_POST, true);
    	curl_setopt($ch, CURLOPT_POSTFIELDS, "{$pars}");
    }
	$data = curl_exec($ch);
	curl_close($ch);
	#echo $data; die();
	return $data;
}
?>