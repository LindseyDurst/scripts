<?php
require_once 'libs/phpexcel/PHPExcel.php';
include("libs\\progress_bar.php");
$fields = array(
	"inetnum",
	"netname",
	"descr",
	"country",
	"admin-c",
	"tech-c",
	"mnt-by",
	"changed",
	"status",
	"source",
);
$shortopts = "f:"; 
$shortopts .= "o:"; 
$shortopts .= "h"; 

$options = getopt($shortopts);
if(isset($options['h'])){
	echo "-f\tfilename with queries\n-o\tfilename for the results (.xls)";
	die();
}
if(!isset($options['f'])){
	echo "file name required!";
	die();
}
if(!isset($options['o'])){
	echo "output file name required!";
	die();
}
$file=$options['o'];
function result_preparing($array) {
	global $fields;

	$new_array = array();

	foreach($fields as $field) {
		if(array_key_exists($field, $array)) {
			$new_array[$field] = $array[$field];
		} else {
			$new_array[$field] = "";
		}
	}

	return $new_array;
}

function replace_in_array($array) {
	$new_array = array();
	foreach($array as $key => $value) {
		$new_array[$key] = str_replace(";", ".,", $value);
	}
	return $new_array;
}

function resolve($domain) {
	$ip = gethostbyname($domain);
	if($ip == $domain) {
		$ip == gethostbyname("www.{$domain}");;
		//echo($ip); die();
	}
	if($ip == $domain || $ip == "www.{$domain}") {
		$ip = false;
	}

	return $ip;
}

function link_preparing($array) {
	//print_r($array); die();

	$r = array();
	
	foreach($array as $a) {
		$r[] = $a["text"];
	}

	return $r;
}

function apnic_search($query) {
	$results = array();

    $ch = curl_init();
    curl_setopt_array($ch, array(
	CURLOPT_URL => "http://wq.apnic.net/whois-search/query?searchtext=".urlencode(trim($query)),
	CURLOPT_HEADER => false,
    #CURLOPT_HTTPHEADER => array("Host: events.media.ferrari.com"),
    CURLOPT_PROXY => "localhost",
    CURLOPT_PROXYPORT => 8080,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10
    ));
    $response = curl_exec($ch);

	$response = json_decode($response, true);

	foreach($response as $data) {
		if(array_key_exists("type", $data) && $data["type"] == "object" && array_key_exists("objectType", $data) && $data["objectType"] == "inetnum") {
			$result = array();
			foreach($data["attributes"] as $line) {
				//print_r($line);
				if(array_key_exists("values", $line))
					$result[$line["name"]] = implode(",", $line["values"]);
				if(array_key_exists("links", $line))
					$result[$line["name"]] = implode(",", link_preparing($line["links"]));
			}
			$results[] = replace_in_array($result);
		}
	}

	return $results;
}

function excel($arr,$struct){
	global $file;
	$phpexcel = new PHPExcel();
	$page = $phpexcel->setActiveSheetIndex(0);
	$begcol=ord("A");
	for ($i=0;$i<count($struct);$i++){
	$page->setCellValue(chr($begcol+$i)."1", $struct[$i]);
	}
	for($i=0,$j=2;$i<count($arr);$i++){
			foreach($struct as $key=>$colname){
				@$page->setCellValue(chr($begcol+$key).$j, $arr[$i][$colname]); 
			}
			$j++;
	}
	$page->setTitle("nonet");
	$objWriter = PHPExcel_IOFactory::createWriter($phpexcel, 'Excel2007');
	$objWriter->save($file);
}

$querym=file($options['f'],FILE_IGNORE_NEW_LINES);
//$query = "BOCI-Net";
//$filename = "ip2.txt";
$i=1;
foreach ($querym as $key=>$query){
	$r = apnic_search($query);
	show_status($i,count($querym),15);
	foreach($r as $l) {
		$arr[$key]=$l;
		//echo(implode(";", $l)."\r\n");
	}
	$i++;
}
//print_r($arr);
excel($arr,$fields);
?>