<html>
<head>
<meta http-equiv="content-type" content="text-html; charset=UTF-8">
</head>
<body style="font-family: 'Lucida Grande', Verdana, Arial, Sans-Serif; font-size: 12px; line-height: 1.2em;">
<?php
	// TRENDING TOPICS ANALYZER
	// Analyses the retrieved tweets, separating each term, counting and storing them.
	require 'blablabra_config.php';
	require 'blablabra_functions.php';
	if (!$CRON_TRENDING_TOPICS_ANALYZER){
		brecho('Analyzer deactivated in blablabra_config');
		die;
	}
	
	$thread_id = date('ymdHis').rand(0,9).' ANALYZER';
	$start_time = time();
	
	function AbortProcessing($errortext, $batch_id, $last_processed_status, $thread_id){
		// Error handling
		echo '</br>'.$errortext;
		mysql_query('BEGIN WORK;') or die('<br><br>Error initializing batch error handling<br><br>'.mysql_error());
		mysql_query('LOCK TABLES batches WRITE;') or die('<br><br>ERROR treating the batch error<br><br>'.mysql_error());
		mysql_query('UPDATE batches SET taken = 0, first_id = '.$last_processed_status.' WHERE batch_id = '.$batch_id.';') or die('<br><br>ERROR treating the batch error<br><br>'.mysql_error());	
		mysql_query('UNLOCK TABLES;');
		mysql_query('COMMIT WORK;');
		LogThis($thread_id, $errortext.' - batch '.$batch_id, true);
	}
	
	$iterations = 1;
	$max_iterations = 3;
	$max_batch_retries = 10;
	$verbose = false; // Less messages (helps when calling this script by hand for debugging). Use TRUE for a complete, verbose execution.
	$dont_check_batches = false; // Stops checking the batch size against the number of records in the status table.
	
	// Some parameters can be manually altered via the script URL.
	if (isset($_GET['batch_check'])) $dont_check_batches = true;
	if (isset($_GET['do'])) $max_iterations = $_GET['do'];
	if (isset($_GET['verbose'])) if ($_GET['verbose'] == 1) $verbose = true;
	if (isset($_GET['batch'])){
		$max_iterations = 1;
		$specific_batch = $_GET['batch'];
	}
	
	echo 'Beginning now. I''ll do at most '.$max_iterations.' batches...<br>';
	
	$db = mysql_connect($DB_SERVER, $DB_USER, $DB_PASSWORD);
	if (!$db) {
		die('<b>AAARGH</b>, can''t connect - ' . mysql_error());
	}
	mysql_select_db($DB_DATABASE) or die ("<b>AAARGH</b>, can''t select database.");
	
	mysql_query("SET NAMES 'utf8';");
	
	LogThis($thread_id, 'Initializing');
	
	do{
		// Set aside a pack of unprocessed tweets.
		mysql_query('BEGIN WORK;') or die('<br><br>Error initializing the batch acquisition.<br><br>'.mysql_error());
		mysql_query('LOCK TABLES batches WRITE;') or die('<br><br>Error acquiring batch.<br><br>'.mysql_error());
		if (isset($specific_batch))
			$batch_result = mysql_query('SELECT * FROM batches WHERE batch_id = '.$specific_batch.' LIMIT 0,1;') or die('<br><br>Error acquiring batch.<br><br>'.mysql_error());
		else 
			$batch_result = mysql_query('SELECT * FROM batches WHERE taken = 0 ORDER BY batch_id DESC LIMIT 0,1;') or die('<br><br>Error acquiring batch.<br><br>'.mysql_error());
		if (mysql_num_rows($batch_result) != 0){
			$batch = mysql_fetch_assoc($batch_result);
			mysql_query('UPDATE batches SET taken = 1 WHERE batch_id = '.$batch['batch_id'].';') or die('<br><br>Error acquiring batch.<br><br>'.mysql_error());
			echo '<br><hr><b>Processando batch '.$batch['batch_id'].' ('.$batch['amount'].' registros)</b><hr><br>';
		} else $batch = 'EMPTY'; // If there is no batches.
		mysql_query('UNLOCK TABLES;');
		mysql_query('COMMIT WORK;');
		
		if ($batch != 'EMPTY'){
			$batch_retries = 0;
			do{
				$query = 'SELECT tweet, created_at, id FROM status WHERE id >= '.$batch['first_id'].' AND id <= '.$batch['last_id'].' AND META_processed = 0 ORDER BY id;';
				$batch_rows = mysql_query($query);
				if (!$batch_rows) AbortProcessing('Error getting tweets from the batch ',$batch['batch_id'],$batch['first_id'],$thread_id);
				
				echo 'Batch has this records to process: '.mysql_num_rows($batch_rows).'<br>';
				if ($batch_retries == 0) LogThis($thread_id, 'Processing batch '.$batch['batch_id'].' ('.$batch['amount'].' records, '.mysql_num_rows($batch_rows).' elligible for processing)');
				else LogThis($thread_id, 'REPROCESSING batch '.$batch['batch_id'].' ('.$batch['amount'].' records, '.mysql_num_rows($batch_rows).' elligible for processing)');
				
				if (mysql_num_rows($batch_rows) < $batch['amount'] && mysql_num_rows($batch_rows) > 0) 
					$incomplete_batch = true; // Not all batch rows were written in "status" (search_scanner's INSERT might still be running in the database)
				else 
					$incomplete_batch = false;
				
				if ($dont_check_batches) $incomplete_batch = false;
				
				if (mysql_num_rows($batch_rows) > 0) {
					$batch_starttime = microtime(true);
					while($row = mysql_fetch_assoc($batch_rows)) {
						$created_at = $row['created_at'];
						$last_processed_status = $row['id'];
						// Stores all terms, including @users and #hashtags, in arrays
						$tweet = $row['tweet'];
						echo '<br/><br/>TWEET being analysed: ['.$row['id'].']: '.$tweet.'<br>';
						
						// UPDATE 09/26/09 - Anti-spam check.
						if (!IsThisSpam($tweet)){
							// This takes some serious REGEXP-fu.
							// First, let's separate @usernames from @replies...
							$regex_replies = '/^@[\w]+,?\s?(@[\w]+,?\s?)*/ui';
							$aux = array();
							preg_match_all($regex_replies, $tweet, $aux);
							if(count($aux[0]) > 0) {
								preg_match_all('/@[\w]+/ui', $aux[0][0], $topics_replynames);
								$tweet = preg_replace($regex_replies,'',$tweet);
							}
							if ($verbose) brecho('Usernames separated - '.$tweet);
							
							// ...separate URLs...
							$URLs = array();
							$regex_URLs = '{http(s?)://(\S)+}u';
							preg_match_all($regex_URLs, $tweet, $URLs);
							$tweet = preg_replace($regex_URLs,'',$tweet);
							if ($verbose) brecho('URLs removidos - '.$tweet);
							
							// ... remove laughter (kkkk, hahaha, rsrsrs etc.)...
							$tweet = preg_replace('/((?<![\w@#])(a?u?h[aeiou]u?a?){2,}h?)|(?<![\w@#])(k{2,})|(?<![\w@#])(z{2,})|(?<![\w@#])((rs){2,}r?)/ui','',$tweet);
							if ($verbose) brecho('Risadinhas removidas - '.$tweet);
						
							// ... and change capitalization of words with ALL CAPS.
							$regex_shouting = '/(?<![@#\wáàâãéêíñóõôúüç])[A-Z0-9ÁÂÀÃÉÊÍÑÓÕÔÚÜÇ]+([\s-][A-Z0-9ÁÂÀÃÉÊÍÑÓÕÔÚÜÇ]+)+(?![@#\wáàâãéêíñóõôúüç])/ue';
							$tweet = preg_replace($regex_shouting, "mb_strtolower('$0','UTF-8')", $tweet);
							if ($verbose) brecho('The POWER OF CAPS LOCK contido - '.$tweet);
							
							// Capital letters after punctuation are decapitalized so they won't be mistaken for proper nouns.
							$tweet = preg_replace('{(?<=[!:"\'?\.])\s?[A-ZÁÂÀÃÉÊÍÑÓÕÔÚÜÇ](?=[a-záàâãéêíñóõôúüç])}ue', "mb_strtolower('$0','UTF-8')", $tweet);
							if ($verbose) brecho('Maiúsculas após pontuação recapitalizadas - '.$tweet);
							
							// Same for the first letter in the tweet.
							$tweet = trim($tweet);
							$tweet = preg_replace('{^[A-ZÁÂÀÃÉÊÍÑÓÕÔÚÜÇ](?=[a-záàâãéêíñóõôúüç])}ue', "mb_strtolower('$0','UTF-8')", $tweet);
							if ($verbose) brecho('Primeira letra recapitalizada - '.$tweet);
						
							// Now, for the capture. First, the terms with more than one words (like "San Francisco").
							// The criteria is capitalized words written in sequence and connected by spaces, hyphens, "de, do, das" ("of" in portuguese).
							$regex_nomes_proprios ='{(?<![@#\wáàâãéêíñóõôúüç])[A-ZÁÂÀÃÉÊÍÑÓÕÔÚÜÇ][-\w\'áàâãéêíñóõôúüçÁÂÀÃÉÊÍÑÓÕÔÚÜÇ]+([-\s\&]((d[eoa]s?|e|\&)[-\s])?[A-ZÁÂÀÃÉÊÍÑÓÕÔÚÜÇ][-\w\'áàâãéêíñóõôúüçÁÂÀÃÉÊÍÑÓÕÔÚÜÇ]+)+}u';
							preg_match_all($regex_nomes_proprios, $tweet, $topics_expressions);
							// Those terms are, then, removed from the tweet. Otherwise, "San Francisco", "San" and "Francisco" would be counted.
							$tweet = preg_replace($regex_nomes_proprios,'',$tweet);
							if ($verbose) brecho('Proper nouns captured - '.$tweet);
							
							// Then every word with more than 2 letters is captured, including @users and #hashtags.
							preg_match_all('/[@#]?[-\w\'áàâãéêíñóõôúüçÁÂÀÃÉÊÍÑÓÕÔÚÜÇs]{2,}/u', $tweet, $topics_words);
							
							// All terms captured are then stored in the database.
							// (arrays are merged to use a single foreach loop. array_unique() is used to remove repeated terms).
							if (isset($topics_replynames)) $topics_words[0] = array_merge($topics_words[0], $topics_replynames[0]);
							foreach (array_merge(array_unique($topics_expressions[0]), array_unique($topics_words[0])) as $term){
								if ($verbose) echo '<br>&gt; '.$term;
								$term = trim($term);
								$term = RemoveRepeatingLetters($term,4); // Changes "GOOOOOOOOAL" into "GOAL"
								
								// Checks the word agains a blacklist (for spam)
								$blacklist = mysql_query('SELECT blacklist_term FROM blacklist WHERE blacklist_term = "'.mysql_real_escape_string(mb_strtolower($term,'UTF-8')).'";');
								if (mysql_num_rows($blacklist) > 0){
									if ($verbose) echo ' - <font style="color: red;">blacklisted.</font>';
									continue; // Skips to the next term.
								}
								
								// UPDATE 06/17/09 - words are recapitalized before analysis.
								// This prevents a term_ID for USP, UsP, USp, which hinders performance.
								// Only three sets of capitalization are allowed: all caps, first letter with caps, no caps.
								if (strpos($term, ' ') === FALSE)
									if ((substr($term,0,1) != '#') && (substr($term,0,1) != '@'))
										if (mb_substr($term,0,1,'UTF-8') == mb_strtoupper(mb_substr($term,0,1,'UTF-8'),'UTF-8')){ // First letter w/ caps
											if (strcmp($term,mb_strtoupper($term,'UTF-8')) != 0) // ...but not all letters.
												$term = mb_convert_case($term, MB_CASE_TITLE, 'UTF-8');
										} else $term = mb_strtolower($term, 'UTF-8');
								echo('['.$term.']');		
								
								// Checks if the term already exists in the DB.
								// (04/27/09) case insensitive check for @users and #hashtags
								$type_check = '';
								if ((substr($term,0,1) == '#') || (substr($term,0,1) == '@')){
									$like_type = 'LIKE';
									if (substr($term,0,1) == '@'){
										$type_check = 'AND term_type = 5';
										if (isset($topics_replynames)){
											if (in_array($term,$topics_replynames[0])){
												$type_check = 'AND term_type = 7'; // This means the @username is in a reply, not a quote or retweet.
												if ($verbose) echo ' (tipo 7, reply) ';
											}
										}
									}
									$term = mb_strtolower($term,'UTF-8');
									if ($verbose) echo ' ('.$term.') ';
								} else $like_type = 'LIKE BINARY';
								
								$terms_query = 'SELECT term_ID FROM terms WHERE term '.$like_type.' "'.str_replace('_','\\_',mysql_real_escape_string($term)).'" COLLATE utf8_general_ci '.$type_check.' ORDER BY term_ID;';
								$terms = mysql_query($terms_query);
								if (!$terms) AbortProcessing('ABORT - Error retrieving term_ID from: '.mysql_error(), $batch['batch_id'], $last_processed_status, $thread_id);
								
								if (mysql_num_rows($terms) == 0){ // New term, generates a term_ID for it.
									// Determines term type
									$term_type = 4; // Simple, single word (default)
									switch (substr($term,0,1)){
										case '@':
											if ($type_check == 'AND term_type = 5'){
												$term_type = 5; // @Username in a retweet
											} else $term_type = 7; // @Username in a reply
											break;
										case '#':
											$term_type = 1; // #hashtag
											break;
										default:
											if (mb_substr($term,0,1,'UTF-8') == mb_strtoupper(mb_substr($term,0,1,'UTF-8'),'UTF-8')){ // Nome próprio (primeira letra maiúscula)
												if (strpos($term, ' ') !== FALSE){
													$term_type = 3; // Proper noun with more than one words (because it contains one or more spaces)
												} else {
													$term_type = 2; // Proper noun with one word.
												}
											}
											if (substr($term,0,4) == 'http') $term_type = 6; // URL
									}
									$insert_query = 'INSERT INTO terms (term, term_type) VALUES ("'.mysql_real_escape_string($term).'", '.$term_type.');';
									$query_ok = mysql_query($insert_query);
									if (!$query_ok) AbortProcessing('ERROR writing in terms: '.mysql_error(), $batch['batch_id'], $last_processed_status, $thread_id);
									// Retrieves the generated term_ID
									$retrieve_query = 'SELECT term_ID FROM terms WHERE term '.$like_type.' "'.str_replace('_','\\_',mysql_real_escape_string($term)).'" COLLATE utf8_general_ci AND term_type = '.$term_type.' ORDER BY term_ID;';
									$terms = mysql_query($retrieve_query);
									if (!$terms) AbortProcessing('ERROR reading from terms: '.mysql_error(), $batch['batch_id'], $last_processed_status, $thread_id);
									if ($verbose) echo " - (new)";
								}
								$terms_row = mysql_fetch_assoc($terms);
								$term_ID = $terms_row['term_ID'];
								if ($verbose) echo ' (ID '.$term_ID.')';
								
								// Finally, stores the term occurrence
								$insertion = mysql_query('INSERT INTO term_occurrences (id, term_ID, time_of_occurrence) VALUES ('.$last_processed_status.','.$term_ID.', \''.$created_at.'\');');
								if ($insertion)
									if ($verbose) echo ' - <font style="color: green;"><em>Stored OK!</em></font>';
								else {
									// Might continue if the error is related to a duplicated primary key (meaning we already have the term, so no biggie)
									if ($verbose) echo ' - <em>Already stored</em></font>';
									if (mysql_errno() != 1062 && mysql_errno() != 0) AbortProcessing('ERROR recording in term_occurrences: '.mysql_errno().' - '.mysql_error(), $batch['batch_id'], $last_processed_status, $thread_id); 
								}
							}
							if (isset($topics_replynames)) unset($topics_replynames);
							
							// Stores found URLs in a separate table.
							foreach (array_unique($URLs[0]) as $URL){
								// Removes the http to save disk space. Uses the field 'is_http' instead.
								$is_https = 0;
								if (strtolower(substr($URL,4,1)) == 's'){
									$is_https = 1;
									$URL = substr($URL,8);
								} else $URL = substr($URL,7);
								// Strips unwanted characters from the end of the URL.
								$bad_chars = array('.',',','(',')','[',']','{','}','\\','"',"'",'<','>','!','?','&');
								while (in_array(substr($URL,-1),$bad_chars)){
									$URL = substr($URL,0,strlen($URL)-1);
								}
								
								echo("<br>> Storing URL ".$URL);
								$insertion = mysql_query('INSERT INTO url_occurrences (id, url, is_https, time_of_occurrence) VALUES ('.$last_processed_status.',"'.mysql_real_escape_string($URL).'", '.$is_https.', "'.$created_at.'");');
								if (!$insertion) LogThis($thread_id,'ERROR inserting into URL_occurrences. URL = '.$URL.' - '.mysql_error()); 
								brecho(" ...OK");
							}
						} else { // (end of If IsThisSpam)
							echo '<br/><br/><b>TWEET IGNORED (Spam)</b><br>';
						}
						// Flags the tweet as processed
						$query_ok = mysql_query('UPDATE status SET META_processed = 1 WHERE id = '.$last_processed_status.';');
						if (!$query_ok) AbortProcessing('ABORTANDO - erro marcando status como processado: '.mysql_error(), $batch['batch_id'], $last_processed_status, $thread_id);
					
					} //end while
					if(mysql_num_rows($batch_rows) != 0) $batch_speed = mysql_num_rows($batch_rows)/(microtime(true) - $batch_starttime);
					else $batch_speed = 0;
				} // end if (mysql_num_rows($batch_rows) > 0)
	
				// Updates the batches table.
				if (!$incomplete_batch){
					mysql_query('BEGIN WORK;') or die('<br><br>ERROR in the batch completion transaction<br><br>'.mysql_error());
					mysql_query('LOCK TABLES batches WRITE;') or die('<br><br>ERROR in the batch completion transaction<br><br>'.mysql_error());
					mysql_query('DELETE FROM batches WHERE batch_id = '.$batch['batch_id'].';') or die('<br><br>ERROR in the batch completion transaction<br><br>'.mysql_error());	
					echo '<br><hr><b>Batch '.$batch['batch_id'].' finished (did '.number_format($batch_speed, 4).' tweets/second)</b><hr><br>';
					LogThis($thread_id, 'Batch '.$batch['batch_id'].' finished (did '.number_format($batch_speed, 4).' tweets/second)');
					mysql_query('UNLOCK TABLES;');
					mysql_query('COMMIT WORK;');
				} else {
					if ($batch_retries >= $max_batch_retries){ // If batch is still bad after retries, flags it as broken
						mysql_query('UPDATE batches SET taken = 99 WHERE batch_id = '.$batch['batch_id'].';') or die('<br><br>ERROR flagging batch<br><br>'.mysql_error());	
						echo '<br><hr><b>Batch '.$batch['batch_id'].' has a PROBLEM, tried '.$max_batch_retries.' times, could not finish it.</b><hr><br>';
						LogThis($thread_id, 'Batch '.$batch['batch_id'].' has a PROBLEM');
						break; // Go get a new batch.
					} else $batch_retries++; // Try some more.
				}
			} while($incomplete_batch);
			$iterations += 1;
			if ($iterations > $max_iterations) break;
		} // end if $batch != empty
	} while ($batch != 'EMPTY');
	
	mysql_query("UPDATE sysinfo SET last_analyzer = NOW();");
	echo '<br><br><br>Done!';
	LogThis($thread_id, 'Finishing OK after '.(time() - $start_time).' seconds');
?>
</body>
</html>