<?php 
	// BATCH CHECKER
	// Audits the 'batches' tables every now and then
	
	require 'blablabra_config.php';
	require 'blablabra_functions.php';
	if (!$CRON_BATCH_CHECKER){
		brecho('Batch Checker deactivated in blablabra_config');
		die;
	}
	
	$thread_id = date('ymdHis').rand(0,9).' CHECKER';
	
	$db = mysql_connect($DB_SERVER, $DB_USER, $DB_PASSWORD);
	if (!$db) {
		die('AAARGH, can''t connect to the database - ' . mysql_error());
	}
	mysql_select_db($DB_DATABASE) or die ("AAARGH, can''t select tweets database.");
	
	$forgotten_batches = mysql_query('SELECT batch_id FROM batches WHERE taken = 1 AND created < SUBDATE(NOW(), INTERVAL 1 HOUR);');
	
	while ($forgotten = mysql_fetch_assoc($forgotten_batches)){
		$msg = 'WARNING - Batch '.$forgotten['batch_id'].' being processed for more than one hour. Status was reset to 0.';
		echo $msg;
		LogThis($thread_id, $msg);
		mysql_query('UPDATE batches SET taken = 0, created = NOW() WHERE batch_id = '.$forgotten['batch_id'].';');
	}

	$orphans = mysql_fetch_assoc(mysql_query('select count(*) as orphans from status where id < (select min(first_id) from batches) and META_processed = 0;'));
	if($orphans['orphans'] > 0){
		mysql_query('insert into batches (first_id, last_id, amount, taken)
select min(id), max(id), count(*), 0 from status where id < (select min(first_id) from batches) and META_processed = 0;');
		$msg = 'WARNING - '.$orphans['orphans'].' orphan records in status. A batch was created to handle them.';
		echo $msg;
		LogThis($thread_id, $msg);
	}
	
	
?>