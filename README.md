**USAGE**
<br /><br />
**full text search on ripe**
<br /><br />
php search_ripe.php -h<br />
-f file with queries.<br />
-q string query to search.<br />
-o output excel file. <br />
<br />
search queries from file:<br />
php search_ripe.php -f=file.txt -o=table.xlsx<br />
<br />
search query from terminal:<br />
php search_ripe.php -q=company -o=table.xlsx<br />
<br /><br />

**search networks for ips**
<br /><br />
php ripe.php -h<br />
-f input file with ips.<br />
-o output excel file.<br />
<br />
php ripe.php -f=ips_file -o=table.xlsx
