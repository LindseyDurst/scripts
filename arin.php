<?php
include("libs/phpexcel/PHPExcel.php");
include("libs/progress_bar.php");

$shortopts  = "";
$shortopts .= "o:";  // Required value for output file
$shortopts .= "f:"; // Optional value filename
$shortopts .= "h"; // value help
$longopts  = array(
    "help",     // Help value
);

$options = getopt($shortopts, $longopts);
if(isset($options['h']) || isset($options['help'])){
	echo "-f\tinput file with ips.\n-o\toutput excel file.";
	die();
}
if (!isset($options['o']) || !isset($options['f'])){
	"need more options.\n use -h for help\n";
	die();
}
$nets=file_get_contents($options['f']);
$nets=explode("\n",$nets);
print_r($nets);
$whois=[];
for($j=0;$j<count($nets);$j++){
	$p3=request("whois.arin.net/ui/query.do",urlencode(trim($nets[$j])));
	preg_match_all("~<startAddress>(.*?)</startAddress>~",$p3,$startaddress);
	if(isset($startaddress[1][0])) $startaddress=$startaddress[1][0];
	else $startaddress="";
	preg_match_all("~<endAddress>(.*?)</endAddress>~",$p3,$endaddress);
	if(isset($endaddress[1][0])) $endaddress=$endaddress[1][0];
	else $endaddress="";
	$inetnum=$startaddress."-".$endaddress;
	preg_match_all("~<name>(.*?)</name>~",$p3,$netname);
	if(isset($netname[1][0])) $netname=$netname[1][0];
	else $netname="";
	preg_match_all("~<companyName>(.*?)</companyName>~",$p3,$orgname);
	if(isset($orgname[1][0])) $orgname=$orgname[1][0];
	else $orgname="";
	preg_match_all("~<code2>(.*?)</code2>~",$p3,$country);
	if(isset($country[1][0])) $country=$country[1][0];
	else $country="";	
	$whois[]=array("inetnum"=>$inetnum,"netname"=>$netname,"org-name"=>$orgname,"descr"=>"","country"=>$country);
	#print_r($whois);die();
	show_status($j+1,count($nets),15);
}
$i=2;
echo count($whois);
$phpexcel = new PHPExcel();
$page = $phpexcel->setActiveSheetIndex(0);
$page->setCellValue("A1", "inetnum");
$page->setCellValue("B1", "netname"); 
$page->setCellValue("C1", "org-name");
$page->setCellValue("D1", "country");
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
$objWriter->save($options['o']);


function request($link,$pars){ // функция отправки запросов
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $link);
	curl_setopt($ch, CURLOPT_HEADER, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
	curl_setopt($ch, CURLOPT_PROXY, "localhost"); //PROXY| IF VPN COMMENT IT
	curl_setopt($ch, CURLOPT_PROXYPORT, 8080); 
	curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:47.0) Gecko/20100101 Firefox/47.0');
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "xslt=http%3A%2F%2Flocalhost%3A8080%2Fwhoisrws-servlet%2Farin.xsl&flushCache=false&queryinput={$pars}&whoisSubmitButton=+");
   # curl_setopt($ch, CURLOPT_COOKIE, "JSESSIONID=blaarkop1awpei5rx8k5t1ekabliln0a4i.blaarkop");
	$data = curl_exec($ch);
	curl_close($ch);
	//echo $data; die();
	return $data;

}
?>