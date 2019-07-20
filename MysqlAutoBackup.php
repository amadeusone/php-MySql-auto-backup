<?php
//config
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "test";

//other config
//- if you run this script from cron jobs or using windows task schedule, better set with full folder path ex: "/var/www/xxx.com/backup" dont forget change permision folder to 777
$backup_path = "";
//- leave blank if you want use dinamyc file name (auto generate date & time) 
$backup_name = "";

function dbbackup($host, $user, $pass, $name, $backup_path="./", $tables=false, $backup_name=false)
{	
	set_time_limit(3000); 
	$mysqli = new mysqli($host,$user,$pass,$name); 
	if ($mysqli->connect_error) {
		return false;
	}
	$mysqli->select_db($name); 
	$mysqli->query("SET NAMES 'utf8'");
	$queryTables = $mysqli->query('SHOW TABLES'); 
	$result = false;
	while($row = $queryTables->fetch_row()) 
	{ 
		$target_tables[] = $row[0]; 
	}   
	if($tables !== false) 
	{
		$target_tables = array_intersect( $target_tables, $tables); 
	}
	$datetime = date('d-m-Y H:i:s');
	$backup_name = $backup_name ? $backup_name : $name.'___('.date('H-i-s').'_'.date('d-m-Y').').sql';
	$content = "----------------------------------------------------------\r\n";	
	$content .= "-- Database Host	: $host                              \r\n";
	$content .= "-- Database Name	: $name                              \r\n";
	$content .= "-- Backup Date  	: $datetime                          \r\n";
	$content .= "-- Backup File Name	: $backup_name                   \r\n";
	$content .= "----------------------------------------------------------\r\n\r\n";
	$content .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\r\nSET AUTOCOMMIT = 0;\r\nSTART TRANSACTION;\r\nSET time_zone = \"+00:00\";\r\n\r\n\r\n/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\r\n/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\r\n/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\r\n/*!40101 SET NAMES utf8 */;\r\n--\r\n-- Database: `".$name."`\r\n--\r\n\r\n\r\n";
	foreach($target_tables as $table)
	{
		if (empty($table))
		{
			continue;
		} 
		$result = $mysqli->query('SELECT * FROM `'.$table.'`');     $fields_amount=$result->field_count;  $rows_num=$mysqli->affected_rows;     $res = $mysqli->query('SHOW CREATE TABLE '.$table); $TableMLine=$res->fetch_row(); 
		$content .= "\n\n".$TableMLine[1].";\n\n";   $TableMLine[1]=str_ireplace('CREATE TABLE `','CREATE TABLE IF NOT EXISTS `',$TableMLine[1]);
		for ($i = 0, $st_counter = 0; $i < $fields_amount;   $i++, $st_counter=0) 
		{
			while($row = $result->fetch_row())  
			{
				if ($st_counter%100 == 0 || $st_counter == 0 )  
				{
					$content .= "\nINSERT INTO ".$table." VALUES";
				}
				$content .= "\n(";    
				for($j=0; $j<$fields_amount; $j++)
				{
					$row[$j] = str_replace("\n","\\n", addslashes($row[$j]) ); 
					if (isset($row[$j]))
					{
						$content .= '"'.$row[$j].'"' ;
					} 
					else
					{
						$content .= '""';
					}
					if ($j<($fields_amount-1))
					{
						$content.= ',';
					}  
				}
				$content .=")";
				if ( (($st_counter+1)%100==0 && $st_counter!=0) || $st_counter+1==$rows_num) 
				{
					$content .= ";";
				}
				else
				{
					$content .= ",";
				}
				$st_counter=$st_counter+1;
			}
		} $content .="\n\n\n";
	}
	
	$content .= "\r\n\r\n/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\r\n/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\r\n/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;";
	$myfile = fopen($backup_path.$backup_name, "a");
	fwrite($myfile, $content);
	fclose($myfile);
	if(file_exists($backup_path.$backup_name))
	{
		$result = true;
	}
	
	return $result;
}

if(strlen($backup_name) >= 1 )
{
    $backup_name = strip_tags($backup_name); 
    $backup_name = preg_replace('/[\r\n\t ]+/', ' ', $backup_name);
    $backup_name = preg_replace('/[\"\*\/\:\<\>\?\'\|]+/', ' ', $backup_name);
    $backup_name = strtolower($backup_name);
    $backup_name = html_entity_decode( $backup_name, ENT_QUOTES, "utf-8" );
    $backup_name = htmlentities($backup_name, ENT_QUOTES, "utf-8");
    $backup_name = preg_replace("/(&)([a-z])([a-z]+;)/i", '$2', $backup_name);
    $backup_name = str_replace(' ', '-', $backup_name);
    $backup_name = rawurlencode($backup_name);
    $backup_name = str_replace('%', '-', $backup_name);
} else {
	$backup_name = false;
}
if(dbbackup($db_host, $db_user, $db_pass, $db_name, $backup_path, false, $backup_name))
{
	$file_path = "";
	if(strlen($backup_path) >= 1)
	{
		$file_path = $backup_path."".$backup_name ? $backup_name : $db_name.'___('.date('H-i-s').'_'.date('d-m-Y').').sql';
	} else {
		$folder = str_replace('\\', '/', getcwd());
		$file_name = $backup_name ? $backup_name : $db_name.'___('.date('H-i-s').'_'.date('d-m-Y').').sql';
		$file_path = $folder."/".$file_name;
	}
	
	echo "Success...\r\n";
	echo "Backup db: ".$db_name."\r\n";
	echo "From Host: ".$db_host."\r\n";
	echo "File path: $file_path\r\n";
	echo "Done!\r\n";
} else {
	echo "Fail...\r\n";
	echo "Can't connect to database!\r\n";
}

exit;
?>
