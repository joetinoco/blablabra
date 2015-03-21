<?php 
	
	// SEARCH SCANNER
	// Uses twitter API to retrieve brazilian twitters and extract their URLs
	require 'blablabra_config.php';
	require 'blablabra_functions.php';
	if (!$CRON_SEARCH_SCANNER){
		brecho('Search scanner deactivated in blablabra_config');
		die;
	}
	
	$thread_id = date('ymdHis').rand(0,9).' SCANNER';
	$start_time = time();
	
	// DB connection
	$db = mysql_connect($DB_SERVER, $DB_USER, $DB_PASSWORD);
	if (!$db) {
		die('AAARGH, can''t connect to the DB - ' . mysql_error());
	}
	mysql_select_db($DB_DATABASE) or die ("AAARGH, can''t select DB.");
	
	LogThis($thread_id, 'Starting.');
	
	$num_tweets = 0;
	if ($SITE_URL == 'http://127.0.0.1/blablabra/') $max_loops = 2;
	else $max_loops = 14;

	$last_search_result = mysql_fetch_assoc(mysql_query('SELECT max(id) AS last_search_ID FROM status;'));
	$last_search = $last_search_result['last_search_ID'];
	$first_processed_search = 0;
	$last_processed_search = 0;
	
	echo 'GMT OFFSET = '.$GMT_OFFSET.'. Procurando a partir de '.$last_search.'<br>';
	
	// Prepares the query and sends it to Twitter's API
	$base_url = 'http://search.twitter.com/search.json';
	$url = $base_url . '?lang=pt&geocode=-10.183056%2C-48.333611%2C2500km&rpp=100';
	$loop = 0;
	$ids = array();
	
	// Configures HTTP request context
	$context = stream_context_create(array('http'=>array('method'=>"GET",'header'=>"user_agent: blablabra.net\r\n")));
	do {
		$search_api_result = file_get_contents($url, false, $context);
		$tweets = json_decode($search_api_result, true);
		if (count($tweets['results']) == 0){
			// FAIL WHALE MODE - If the API is delivering outdated information, searches without since_ID
			echo 'FAIL WHALE MODE<br>';
			$errormsg = $http_response_header[0];
			$errormsg .= " - ".$tweets['warning'];
			LogThis($thread_id, 'Search with since_id failed: '.$errormsg);
			$url = $base_url . '?lang=pt&geocode=-10.183056%2C-48.333611%2C2500km&rpp=100';
			$search_api_result = file_get_contents($url);
			$tweets = json_decode($search_api_result, true);
		}
		$sweep_count = 0;
		// Inserts each tweet found
		foreach($tweets['results'] as $tweet){
			// ... checking first if they are not on the database.
			if (!mysql_fetch_assoc(mysql_query('SELECT id FROM status WHERE id = '.$tweet['id'].';'))){
				$query = "INSERT INTO status VALUES (".
				mysql_real_escape_string($tweet['id']).",'" .
				// Adjusts timezone offset
				mysql_real_escape_string(strftime('%Y-%m-%d %H:%M:%S',strtotime(substr($tweet['created_at'],5,20))+($GMT_OFFSET*60*60)))."','" .
				utf8_decode(mysql_real_escape_string(html_entity_decode($tweet['text'])))."','" .
				mysql_real_escape_string($tweet['source'])."','" .
				mysql_real_escape_string($tweet['from_user_id'])."','" .
				"','" . // Username (blank, not being used)
				mysql_real_escape_string($tweet['from_user'])."','" .
				"','" . // Description (blank, same reason)
				"','" . // Location (blank, same reason)
				mysql_real_escape_string($tweet['profile_image_url'])."','" .
				"'," . // User URL (blank, same reason)
				"'srch', 0);"; // Tweet origin and processing status
				// The actual insertion
				$insert_ok = mysql_query($query);
				if (!$insert_ok){
					LogThis($thread_id, 'ABORTANDO, erro gravando tweet '.$tweet['id'].' (de "'.$tweet['from_user'].'") em status - '.mysql_error(), true);
				}
				$ids[] = $tweet['id'];
				$num_tweets += 1;
				$sweep_count++;
			}
		}
		if (!isset($tweets['next_page']) || $sweep_count == 0) break;
		else $url = $base_url . $tweets['next_page'];
		$loop++;
	} while ($loop < $max_loops);
	
	// Calculates tweets per minute
	// ============================================================================================================
	$url = $base_url . '?lang=pt&geocode=-10.183056%2C-48.333611%2C2500km&rpp=100';
	$search_api_result = file_get_contents($url);
	$tweets = json_decode($search_api_result, true);
	$arr_results = $tweets['results'];
	$search_count = count($arr_results);
	$element = current($arr_results);
	$first_time = strtotime(substr($element['created_at'],5,20));
	$element = end($arr_results);
	$last_time = strtotime(substr($element['created_at'],5,20));
	
	$twitter_speed = $search_count/($first_time - $last_time + 1);
	if ($twitter_speed > 0)
		if (!mysql_query('INSERT INTO twitter_movements VALUES (NOW(), "speed", '.$twitter_speed.');')){
			brecho(mysql_error());
			LogThis($thread_id, 'Não consegui gravar a velocidade ('.$twitter_speed.') em twitter_movements - '.mysql_error());
		}
	// ============================================================================================================	
	
	// Creates a new processing batch
	if ($num_tweets > 0){
		sort($ids);
		$first_batch = current($ids);
		$last_batch = end($ids);
		
		mysql_query('BEGIN WORK;') or LogThis($thread_id, 'ERROR starting the batch creation transaction - '.mysql_error(), true);
		mysql_query('LOCK TABLES batches WRITE;') or LogThis($thread_id, 'ERROR locking "batches" - '.mysql_error(), true);
		$sql_ok = mysql_query('INSERT INTO batches (first_id, last_id, amount, taken) values ('.$first_batch.','.$last_batch.','.$num_tweets.',0);');
		if (!$sql_ok){
			echo mysql_error();
			LogThis($thread_id, 'ERROR batching '.$num_tweets.' tweets ('.$first_batch.' a '.$last_batch.')'.mysql_error());
		} else {
			brecho('Batch created for '.$num_tweets.' tweets ('.$first_batch.' to '.$last_batch.')');
			LogThis($thread_id, 'Batch created for '.$num_tweets.' tweets ('.$first_batch.' to '.$last_batch.')');
		}
		mysql_query('UNLOCK TABLES;');
		mysql_query('COMMIT WORK;');
		
		// Updates SYSINFO
		if ($twitter_speed > 0)	$sql_ok = mysql_query('UPDATE sysinfo SET last_search_ID = '.$last_batch.', search_scan_hits = '.$num_tweets.', last_scanner = NOW(), twitter_speed = '.$twitter_speed.';');
		else $sql_ok = mysql_query('UPDATE sysinfo SET last_search_ID = '.$last_batch.', search_scan_hits = '.$num_tweets.', last_scanner = NOW();');
		if (!$sql_ok){
			brecho(mysql_error());
			LogThis($thread_id, 'ERROR accessing SYSINFO - '.mysql_error());
		}
	} else LogThis($thread_id, 'search.twitter.com returned zero results.');
	
	brecho(date('Y-m-d G:i:s')." - Scanned search results successfully. ".$num_tweets." tweet(s) were found and inserted in the database;");
	LogThis($thread_id, 'Finishing OK. '.(time() - $start_time).' seconds');
	?>