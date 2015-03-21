<?php 
	// DB_PURGE
	// Purges old data from some tables
	$starttime = microtime(true);
	
	require 'blablabra_config.php';
	require 'blablabra_functions.php';
	if (!isset($_GET['debug']))
		if (!$CRON_DB_PURGE){
			brecho('DB Purge deactivated in blablabra_config');
			die;
		}
	
	$thread_id = date('ymdHis').rand(0,9).' PURGE';
	
	$db = mysql_connect($DB_SERVER, $DB_USER, $DB_PASSWORD);
	if (!$db) {
		die('AAARGH, can''t connect to DB  - ' . mysql_error());
	}
	mysql_select_db($DB_DATABASE) or die ("AAARGH, can''t select DB.");
	
	// Purges URL_OCCURRENCES
	mysql_query('delete from url_occurrences where time_of_occurrence < subdate(now(), interval 24 hour);');
	
	// Purges TERM_OCCURRENCES and STATUS (migrating them to TERM_OCCURRENCES_OLD)
	$to_purge = mysql_query('SELECT id FROM status WHERE created_at < SUBDATE(NOW(), INTERVAL 25 HOUR) LIMIT 0,4000;');
	$deleted_lines = 0;
	
	while($item = mysql_fetch_assoc($to_purge)){
		mysql_query('BEGIN WORK;');
		$ok1 = mysql_query('INSERT INTO term_occurrences_OLD 
							SELECT t.term_ID AS term_ID,
							t.term AS term,
							toc.time_of_occurrence AS time_of_occurrence,
							s.id AS id,
							s.user_screen_name AS user_screen_name
							FROM terms AS t, term_occurrences AS toc, status AS s
							WHERE t.term_ID = toc.term_ID
							AND toc.id = s.id
							AND toc.id = '.$item['id'].';');
		$ok2 = mysql_query('DELETE FROM term_occurrences WHERE id = '.$item['id'].';');
		$error2 = mysql_errno().' - '.mysql_error();
		$ok3 = mysql_query('DELETE FROM status WHERE id = '.$item['id'].';');
		$error3 = mysql_errno().' - '.mysql_error();
		if ($ok1 && $ok2 && $ok3){
			mysql_query('COMMIT WORK');
			$deleted_lines++;
		} else {
			mysql_query('ROLLBACK WORK');
			if (!$ok1) LogThis($thread_id, 'ABORT - Error inserting in term_occurrences_OLD - '.$error1);
			if (!$ok2) LogThis($thread_id, 'ABORT - Error deleting term_occurrences - '.$error2);
			if (!$ok3) LogThis($thread_id, 'ABORT - Error deleting from status - '.$error3);
		}
		if ((microtime(true) - $starttime) > 118){
			// Another db_purge is about to be started by the cron. So aborts this to prevent simultaneous executions.
			LogThis($thread_id, $deleted_lines.' linhas movidas, mas ABORTANDO por proximidade de timeout ('.number_format(microtime(true) - $starttime, 4).' segundos ativo);');
			die;
		}
	}
	LogThis($thread_id, $deleted_lines.' tweets moved to term_occurrences_OLD ('.number_format(microtime(true) - $starttime, 4).' seconds);');
	brecho($deleted_lines.' tweets moved to term_occurrences_OLD ('.number_format(microtime(true) - $starttime, 4).' seconds);');
?>