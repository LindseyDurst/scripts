<?php
#RIPE PERFECTION *_*

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
	$p3=request_get("https://apps.db.ripe.net/search/query.html?searchtext=".urlencode($nets[$j]));
	preg_match_all("~<ul class=\"attrblock\">(.*?)</ul>~",$p3,$ul);	
	$whois[]=array("inetnum"=>'',"netname"=>'',"descr"=>'',"country"=>'');
	foreach($ul[1] as $uls){
		preg_match_all("~<li.*?>(.*?)</li>~",$uls,$li);
		for($i=0;$i<count($li[1]);$i++){		
			$t=explode(":         ",$li[1][$i]);
			if(!isset($t[1])) $t=explode(":   ",$li[1][$i]);
			if(!isset($t[1])) $t=explode(":  ",$li[1][$i]);
			$name=trim($t[0]);
			$val=trim($t[1]);
			$val=preg_replace("~<a.*?>(.*?)</a>~",'$1',$val);
			if(stristr($name,"inetnum") && strlen($whois[$j]['inetnum'])<2){
				$whois[$j]['inetnum']=$val;
			}
			else if(stristr($name,"netname")&& strlen($whois[$j]['netname'])<2)$whois[$j]['netname']=$val;
			else if(stristr($name,"country")&& strlen($whois[$j]['country'])<2)$whois[$j]['country']=$val;
			else if(stristr($name,"descr"))$whois[$j]['descr'].=$val." ";
		}
	}
	show_status($j+1,count($nets),15);
}
$i=2;
$phpexcel = new PHPExcel();
$page = $phpexcel->setActiveSheetIndex(0);
$page->setCellValue("A1", "inetnum");
$page->setCellValue("B1", "netname"); 
$page->setCellValue("C1", "descr");
$page->setCellValue("D1", "country");
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
$objWriter->save($options['o']);


function request_get($link){ // функция отправки запросов
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
    curl_setopt($ch, CURLOPT_COOKIE, "JSESSIONID=blaarkop1awpei5rx8k5t1ekabliln0a4i.blaarkop");
	$data = curl_exec($ch);
	curl_close($ch);
	//echo $data; die();
	return $data;

}
?>