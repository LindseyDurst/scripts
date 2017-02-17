<?php
# finally it works *_*

include("libs/phpexcel/PHPExcel.php");
include("libs/progress_bar.php");
$whois=[];

$shortopts = "o:";  // Required value for output file
$shortopts .= "f:"; // Optional value filename
$shortopts .= "q:"; // Optional value query
$shortopts .= "h"; // value help
$longopts  = array(
    "help",
    "h"
);

$options = getopt($shortopts, $longopts);

#var_dump($options);die();
if(isset($options['h']) || isset($options['help'])){
	echo "-f\t\tfile with queries.\n-q\t\tstring query to search.\n-o\t\toutput excel file.\n";
	die();
}
if(isset($options['f']) && isset($options['o']) && !isset($options['q'])){
	$search_queries=file_get_contents($options['f']);
	$search_queries=explode("\n",$search_queries);
	foreach($search_queries as $search_query){
		$whois_temp=search_arin(urlencode(trim($search_query)));
		$whois=array_merge($whois,$whois_temp);		
	}
} else if(isset($options['q'])&& isset($options['o']) && !isset($options['f'])){
	$search_query=urlencode($options['q']);
	$whois=search_arin($search_query);
} else {
	echo "Error";
	die();
}

build_excel($whois,$options['o']);


#~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~#
function search_arin($search_query){	
	$pars_org="advanced=true&q={$search_query}&POC=handle&POC=name&POC=domain&NETWORK=handle&NETWORK=name&ASN=handle&ASN=name&ASN=number&r=ORGANIZATION&ORGANIZATION=handle&ORGANIZATION=name&CUSTOMER=name&DELEGATION=name";
	$pars_net="advanced=true&q={$search_query}&POC=handle&POC=name&POC=domain&r=NETWORK&NETWORK=handle&NETWORK=name&ASN=handle&ASN=name&ASN=number&ORGANIZATION=handle&ORGANIZATION=name&CUSTOMER=name&DELEGATION=name";
	$pars_cust="advanced=true&q={$search_query}&POC=handle&POC=name&POC=domain&NETWORK=handle&NETWORK=name&ASN=handle&ASN=name&ASN=number&ORGANIZATION=handle&ORGANIZATION=name&r=CUSTOMER&CUSTOMER=name&DELEGATION=name";

	$page=request("https://whois.arin.net/ui/query.do",$pars_org,1);
	$links=get_links($page,"//td//@href");
	$page=request("https://whois.arin.net/ui/query.do",$pars_net,1);
	$links=array_merge($links,get_links($page,"//td//@href"));
	$page=request("https://whois.arin.net/ui/query.do",$pars_cust,1);
	$links=array_merge($links,get_links($page,"//td//@href"));	
	$net_links=[];
	$stat=1;
	echo "\nCollecting Networks for {$search_query}\n";
	foreach($links as $link){
		$page=request(trim($link)."/nets",0,0);
		if(!preg_match("~No related resources were found for the handle provided~i", $page)){
			preg_match_all("~<netRef.*?>(.*?)</netRef>~i",$page,$temp);
			foreach($temp[1] as $t){
				$net_links[]=trim($t);
			}
		}
		show_status($stat,count($links),15);		
		$stat++;
	}
	$stat=1;
	echo "\nCollecting excel data for {$search_query}\n";
	$net_links=array_unique($net_links);
	foreach($net_links as $nl){
		$page=request(trim($nl),0,0);
		$whois[]=get_nets_data($page);	
		show_status($stat,count($net_links),15);		
		$stat++;	
	}
	return $whois;
}
function get_nets_data($page){
	preg_match_all("~<startAddress>(.*?)</startAddress>~",$page,$startaddress);
	if(isset($startaddress[1][0])) $startaddress=$startaddress[1][0];
	else $startaddress="";
	preg_match_all("~<endAddress>(.*?)</endAddress>~",$page,$endaddress);
	if(isset($endaddress[1][0])) $endaddress=$endaddress[1][0];
	else $endaddress="";
	$inetnum=$startaddress."-".$endaddress;
	preg_match_all("~<name>(.*?)</name>~",$page,$netname);
	if(isset($netname[1][0])) $netname=$netname[1][0];
	else $netname="";
	preg_match_all("~<orgRef.*?name=\"(.*?)\">~",$page,$orgname);
	if(isset($orgname[1][0])) $orgname=$orgname[1][0];
	else $orgname="";
	preg_match_all("~<code2>(.*?)</code2>~",$page,$country);
	if(isset($country[1][0])) $country=$country[1][0];
	else $country="";	
	$whois=array("inetnum"=>$inetnum,"netname"=>$netname,"org-name"=>$orgname,"descr"=>"","country"=>$country);
	return $whois;
}
function get_links($page,$query){
	$dom = new DOMDocument;
	@$dom->loadHTML($page);
	$xpath = new DOMXPath($dom);
	$hrefs = $xpath->query($query);
	$links=[];
	foreach ($hrefs as $href){
		$links[]=$href->value."\n";
	} 
	return $links;
}

function request($link,$pars,$post){ 
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
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    if($post==1){
    	curl_setopt($ch, CURLOPT_POST, true);
    	curl_setopt($ch, CURLOPT_POSTFIELDS, "{$pars}");
    }
	$data = curl_exec($ch);
	curl_close($ch);
	return $data;
}
function build_excel($whois,$filename){
	$phpexcel = new PHPExcel();
	$page = $phpexcel->setActiveSheetIndex(0);
	$page->setCellValue("A1", "inetnum");
	$page->setCellValue("B1", "netname"); 
	$page->setCellValue("C1", "org-name");
	$page->setCellValue("D1", "country");
	$i=2;
	foreach($whois as $el){
		$page->setCellValue("A".$i, $el['inetnum']); 
		$page->setCellValue("B".$i, $el['netname']); 
		$page->setCellValue("C".$i, $el['org-name']); 
		$page->setCellValue("D".$i, $el['country']); 
		$i++;
	}
	echo "DONE";
	$page->setTitle("networks");
	$objWriter = PHPExcel_IOFactory::createWriter($phpexcel, 'Excel2007');
	$objWriter->save($filename);
}
?>