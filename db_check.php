<?php 
	// DB_CHECK
	// Checks the database for corrupted tables
	$starttime = microtime(true);
	
	require 'blablabra_config.php';
	require 'blablabra_functions.php';
	if (!isset($_GET['debug']))
		if (!$CRON_DB_CHECK){
			brecho('DB CHECK deactivated in blablabra_config');
			die;
		}
	
	$thread_id = date('ymdHis').rand(0,9).' DBCHECK';
	
	$db = mysql_connect($DB_SERVER, $DB_USER, $DB_PASSWORD);
	if (!$db) {
		die('AAARGH, can''t connect to DB - ' . mysql_error());
	}
	mysql_select_db($DB_DATABASE) or die ("AAARGH, can''t select database.");
	
	LogThis($thread_id, 'Starting.');
	
	// Try to query each one of those tables.
	$tables = array('syslog','trending_urls','url_occurrences');
	foreach($tables as $tablename){
		if(!mysql_query('select * from '.$tablename.' LIMIT 0,1;')){
			// Table has an error. Does a REPAIR TABLE.
			brecho('Repairing '.$tablename.' - '.mysql_error());
			LogThis($thread_id, 'Repairing '.$tablename.' - '.mysql_error());
			if (!mysql_query('REPAIR TABLE '.$tablename.';')){
				brecho('Table '.$tablename.' has problems. Repairing...');
				LogThis($thread_id, 'Can''t repair '.$tablename.' - '.mysql_error());	
			} else { 
				brecho('Table '.$tablename.' repaired!');
				LogThis($thread_id, 'Table '.$tablename.' repaired!');	
			}
		}
	}
	
	LogThis($thread_id, 'DB_CHECK finished.');
?>