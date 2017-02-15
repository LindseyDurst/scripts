<?php
/* NETWORKS FROM ARIN WHOIS API */
include("libs/phpexcel/PHPExcel.php");
include("libs/progress_bar.php");

$shortopts  = "o:";  // Required value for output file
$shortopts .= "f:"; // Optional value filename
$shortopts .= "h"; // value help
$longopts  = array(
    "help",     // Help value
);

$options = getopt($shortopts, $longopts);
if(isset($options['h']) || isset($options['help'])){
	echo "-f\tinput file with ips.\n-o\toutput excel file.\n";
	die();
}
if (!isset($options['o']) || !isset($options['f'])){
	"need more options.\n use -h for help\n";
	die();
}
$str=file_get_contents("ips");
$arr=explode("\n",$str);
$i=0;
$newarr=[];
foreach ($arr as $key) {
	$console=shell_exec("whois -h whois.arin.net 'n ".$key."'"); 
	#$whois[]=array("domain"=>$key,"whois"=>$whois);
	preg_match_all("~NetRange\:[ ]+([^ ].*)~i",$console,$inetnum);
	if(isset($inetnum[1][0])) $inetnum=$inetnum[1][0];
	else $inetnum="";
	preg_match_all("~(?:Organization\:[ ]+([^ ].*)|OrgName:[ ]+([^ ].*))~i",$console,$orgname);
	if(isset($orgname[1][0])) $orgname=$orgname[1][0];
	else $orgname="";
	preg_match_all("~NetName\:[ ]+([^ ].*)~i",$console,$netname);
	if(isset($netname[1][0])) $netname=$netname[1][0];
	else $netname="";
	preg_match_all("~Country\:[ ]+([^ ].*)~i",$console,$country);
	if(isset($country[1][0])) $country=$country[1][0];
	else $country="";
	$whois[]=array("inetnum"=>$inetnum,"netname"=>$netname,"org-name"=>$orgname,"country"=>$country);
	show_status($j+1,count($nets),15);
}
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

?>
