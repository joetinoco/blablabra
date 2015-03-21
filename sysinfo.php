<html>
<title>blablabra - SYSINFO</title>
<head>
	<meta http-equiv="content-type" content="text-html; charset=UTF-8">
	<link rel="stylesheet" href="style.css" type="text/css" media="screen" />
</head>
<body>
	<div class="textblock">
	<?php

		//
		// SYSINFO is a very basic dashboard that shows what is going on under the hood.
		//
		
		require 'blablabra_config.php';
		
		// (pseudo, very lousy) access control.
		if (isset($_GET['tel'])){
			if ($_GET['tel'] <> '5556423'){
				echo '<h1>'.$_GET['tel'].'? Não é esse o telefone, marujo.</h1>';
				die;
			}
		} else { 
			echo '<h1>Qual o telefone, marujo?</h1>';
			die;
		}
		
		$db = mysql_connect($DB_SERVER, $DB_USER, $DB_PASSWORD);
		if (!$db) {
			die;
		}
		mysql_select_db($DB_DATABASE) or die;
		mysql_query("SET NAMES 'utf8';");
		
		$urldecoder = mysql_fetch_assoc(mysql_query('select count(*) as urls from url_occurrences where processed < 1;'));
		echo 'URLs waiting for processing in url_occurrences = <b>'.$urldecoder['urls'].'</b>';		
		echo '<p></p>';
		
		//SYSLOG
		$syslog = mysql_query('SELECT * FROM syslog ORDER BY event_id DESC LIMIT 0,100;');
		echo '<table>';
		while ($log_row = mysql_fetch_assoc($syslog)){
			echo '<tr>';
			echo '<td>'.date('d/m G:i:s',($TIME_OFFSET*60*60)+strtotime($log_row['instant'])).'</td>';
			echo '<td>'.$log_row['script_id'].'</td>';
			echo '<td><p align="left">'.$log_row['event'].'</p></td>';
			echo '</tr>';
		}
		echo '</table>';
	?>
	</div>
</body>
</html>