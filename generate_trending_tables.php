<?php
	// Generate the data for the trending URLs tables.
	// This uses data from the last hour, updated every five minutes.
	require 'blablabra_config.php';
	require 'blablabra_functions.php';
	if (!$CRON_GENERATE_TRENDING_TABLES){
		brecho('Generate Trending Tables deactivated in blablabra_config');
		die;
	}
	
	$thread_id = date('ymdHis').rand(0,9).' TRENDING';
	$start_time = time();
	LogThis($thread_id, 'Starting.');
	
	$db = mysql_connect($DB_SERVER, $DB_USER, $DB_PASSWORD);
	if (!$db) {
		die('<b>AAARGH</b>, nao consegui conectar na base de dados - ' . mysql_error());
	}
	mysql_select_db($DB_DATABASE) or die ("<b>AAARGH</b>, nao consegui selecionar a database dos tweets.");
	mysql_query("SET NAMES 'utf8';"); // Comunicação entre o servidor e o cliente acontecerá usando UTF-8
	
	mysql_query('CREATE TEMPORARY TABLE tmp_occ_1hour ENGINE = MEMORY AS SELECT * FROM term_occurrences WHERE time_of_occurrence >= SUBDATE(NOW(), INTERVAL 1 HOUR);') or die('<b>AAARGH</b>, nao consegui criar tmp_occ_1hour  - ' . mysql_error());
	mysql_query('CREATE TEMPORARY TABLE tmp_occurrences SELECT toc.*, st.user_screen_name FROM tmp_occ_1hour AS toc, status AS st WHERE toc.id = st.id;') or die('<b>AAARGH</b>, nao consegui criar tmp_occurrences  - ' . mysql_error());
	mysql_query('TRUNCATE TABLE tmp_occ_1hour;');
	
	// Calcula usuários ativos na última hora, e maiores twittadores
	mysql_query('DELETE FROM trending_top_users;') or die('<b>AAARGH</b>, nao consegui limpar trending_top_users - ' . mysql_error());
	$totaltalkers = 'INSERT INTO trending_top_users SELECT "TOTAL ACTIVE USERS", COUNT(DISTINCT user_screen_name) FROM tmp_occurrences;';
	mysql_query($totaltalkers) or die('<b>AAARGH</b>, nao consegui calcular total de users ativos  - ' . mysql_error());
	$toptalkers = 'INSERT INTO trending_top_users SELECT user_screen_name, COUNT(DISTINCT id) FROM tmp_occurrences GROUP BY user_screen_name ORDER BY COUNT(DISTINCT id) DESC LIMIT 0,10;';
	mysql_query($toptalkers) or die('<b>AAARGH</b>, nao consegui calcular TOP 10 faladores  - ' . mysql_error());
	if (!mysql_query('INSERT INTO twitter_movements SELECT NOW(), "active users", total_tweets FROM trending_top_users WHERE user_screen_name = "TOTAL ACTIVE USERS";')) echo mysql_error();
	
	// ======================================================================================================================================================================================
	// Calculates popular URLs.
	// First, for the last hour.
	mysql_query('CREATE TEMPORARY TABLE tmp_trending_urls (
				  `is_https` tinyint(1) NOT NULL,
				  `url` varchar(2048) NOT NULL,
				  `title` varchar(140) NOT NULL,
				  `hits` int(10) unsigned NOT NULL,
				  `timeframe` tinyint(3) unsigned NOT NULL
				) ENGINE=MEMORY DEFAULT CHARSET=utf8;') or die('<b>AAARGH</b>, nao consegui criar tmp_trending_urls - ' . mysql_error());
	$query_URLs = 'INSERT INTO tmp_trending_urls
			SELECT is_https, url, "" as title, count(id) as hits, 1 as timeframe
			FROM url_occurrences 
			WHERE decoded = 1
			AND time_of_occurrence > subdate(now(), interval 1 hour) 
			GROUP BY url HAVING count(id) >= 10 ORDER BY count(id) DESC LIMIT 0,30;';
	mysql_query($query_URLs) or die('<b>AAARGH</b>, can''t calculate trending URLs - ' . mysql_error());
	// Then, for the last 24 hours.
	// This takes two steps. The frequency of occurrences is retrieved in a first step, then used for the calculation.
	$query_URLs = 'CREATE TEMPORARY TABLE tmp_hourly_urls ENGINE=MEMORY AS
					SELECT is_https, url, count(distinct left(time_of_occurrence, 13)) AS hours_appearing, count(*) AS hourly_hits
					FROM url_occurrences
					WHERE decoded = 1
					AND time_of_occurrence > subdate(now(), interval 24 hour) 
					GROUP BY is_https, url
					ORDER BY hours_appearing DESC, hourly_hits DESC
					LIMIT 0,20;';
	mysql_query($query_URLs) or die('<b>AAARGH</b>, can''t create tmp_hourly_urls - ' . mysql_error());
	$query_URLs = 'INSERT INTO tmp_trending_urls SELECT url, "" AS title, hours_appearing AS hits, 24 AS timeframe FROM tmp_hourly_urls;';
	mysql_query($query_URLs) or die('<b>AAARGH</b>, can''t calculate trending urls for the last 24 hours - ' . mysql_error());
	
	// Create arrays for a series of URL adjustments
	function UsersWhoTweetedThisURL($url, $timeframe){
		// Calcula quantos usuários tuitaram uma URL num timeframe específico
		$result = mysql_fetch_assoc(mysql_query('select count(distinct st.user_screen_name) as tweeting_users
												from status AS st, url_occurrences AS urlocc
												WHERE urlocc.id = st.id
												AND urlocc.url = "'.$url.'"
												AND urlocc.time_of_occurrence > subdate(now(), interval '.$timeframe.' hour);'));
		return $result['tweeting_users'];
	}
	
	$min_users = array(1 => 10, 24 => 12); // Mínimo de usuários twitadores de cada termo para que ele seja considerado popular (um valor para cada timeframe)
	$trending_urls = array();
	$i = 0;
	$urldata = mysql_query('SELECT * FROM tmp_trending_urls');
	while($row = mysql_fetch_assoc($urldata)){
		$trending_urls[$i] = $row;
		$aux_urls_only[$i] = $row['url'];
		$i++;
	}
	$twitcam_already_in_list = 0;
	foreach($trending_urls as $url){
		// If a site is duplicated with and without www (like www.pudim.com.br and pudim.com.br), deletes the one with less hits.
		if (in_array("www.".$url['url'], $aux_urls_only)){
			$other = mysql_fetch_assoc(mysql_query('SELECT hits FROM tmp_trending_urls WHERE url = "www.'.$url['url'].'";'));
			if ($url['hits'] > $other['hits'])
				mysql_query('DELETE FROM tmp_trending_urls WHERE url = "www.'.$url['url'].'";');
			else mysql_query('DELETE FROM tmp_trending_urls WHERE url = "'.$url['url'].'";');
		
		// Deletes spammy or unwanted URLs (like error pages from URL shorteners)
		} else if (IsThisSpam($url['url'],TRUE)){
			mysql_query('DELETE FROM tmp_trending_urls WHERE url = "'.$url['url'].'";');
		
		// Deletes links for .EXE files.
		} else if (in_array(substr($url['url'],-4),array('.scr','.exe','.com','.bat'))){
			mysql_query('DELETE FROM tmp_trending_urls WHERE url = "'.$url['url'].'";');
		
		// Deletes involuntary self-advertising :)	
		} else if (substr($url['url'],0,13) == 'blablabra.net'){
			mysql_query('DELETE FROM tmp_trending_urls WHERE url = "'.$url['url'].'";');
		
		// Makes sure there is only ONE url from each domain on the list - otherwise, ask.fm or Tweetcam would take all the spots.
		} else if (substr($url['url'],0,7) == 'twitcam'){
			if ($twitcam_already_in_list == 0) $twitcam_already_in_list = 1;
			else mysql_query('DELETE FROM tmp_trending_urls WHERE url = "'.$url['url'].'";');
		// Deletes terms that are too frequent and that were tweet by only one person (they are always spam)
		} else if (UsersWhoTweetedThisUrl($url['url'],$url['timeframe']) < $min_users[$url['timeframe']]){
			mysql_query('DELETE FROM tmp_trending_urls WHERE url = "'.$url['url'].'";');
		} else {
			// If we got this far, it's a good URL and it will be inserted into tmp_trending_URL
			if ($url['is_https'] == 1) $protocol = 'https://';
			else $protocol = 'http://';
			$title = page_title($protocol.$url['url']);
			brecho ('titulo de - '.$protocol.$url['url'].' - '.$title);
			if ($title != ""){
				mysql_query('UPDATE tmp_trending_urls SET title = "'.mb_substr(mysql_real_escape_string($title),0,139,'UTF-8').'" WHERE url = "'.$url['url'].'";');
			}
		}
	}
	// Updates the actual trending URLs table.
	$how = mysql_fetch_assoc(mysql_query('select count(*) as many from tmp_trending_urls;'));
	if ($how['many'] > 0){
		mysql_query('DELETE FROM trending_urls;') or die('<b>AAARGH</b>, nao consegui limpar trending_urls - ' . mysql_error());
		mysql_query('INSERT INTO trending_urls SELECT * FROM tmp_trending_urls;') or die('<b>AAARGH</b>, nao consegui carregar trending_urls - ' . mysql_error());
	}
	mysql_query('TRUNCATE TABLE tmp_trending_urls;');
	mysql_query('TRUNCATE TABLE tmp_hourly_urls;');
	// ======================================================================================================================================================================================
	
	// Calculates trending terms
	mysql_query("CREATE TEMPORARY TABLE tmp_insert_buffer (
	  term_ID int(11) NOT NULL,
	  term varchar(140) NOT NULL,
	  term_type tinyint(3) unsigned NOT NULL default '1',
	  hits bigint(21) NOT NULL default '0')
	  ENGINE=MEMORY DEFAULT CHARSET=utf8;") or die('<b>AAARGH</b>, can''t create temp table  - ' . mysql_error());
		
	$trending_SQL = 'INSERT INTO tmp_insert_buffer SELECT terms.term_ID AS term_ID, terms.term AS term, terms.term_type AS term_type, COUNT(occ.term_ID) AS hits '.
					'FROM terms, tmp_occurrences AS occ '.
					'WHERE terms.term_ID = occ.term_ID '.
					'AND (terms.term_type = 2 OR terms.term_type = 3) '. // This filters only proper nouns and terms with more than one word
					'GROUP BY terms.term_ID, terms.term, terms.term_type '.
					'ORDER BY COUNT(occ.term_ID) DESC '.
					'LIMIT 0,10;';
	mysql_query($trending_SQL) or die('<b>AAARGH</b>, can''t create temp table - ' . mysql_error());
	$trending_SQL = 'INSERT INTO tmp_insert_buffer SELECT terms.term_ID AS term_ID, terms.term AS term, terms.term_type AS term_type, COUNT(occ.term_ID) AS hits '.
					'FROM terms, tmp_occurrences AS occ '.
					'WHERE terms.term_ID = occ.term_ID '.
					'AND terms.term_type = 1 '. // hashtags.
					'GROUP BY terms.term_ID, terms.term, terms.term_type '.
					'ORDER BY COUNT(occ.term_ID) DESC '.
					'LIMIT 0,10;';
	mysql_query($trending_SQL) or die('<b>AAARGH</b>, can''t create temp table - ' . mysql_error());
	$trending_SQL = 'INSERT INTO tmp_insert_buffer SELECT terms.term_ID AS term_ID, terms.term AS term, terms.term_type AS term_type, COUNT(occ.term_ID) AS hits '.
					'FROM terms, tmp_occurrences AS occ '.
					'WHERE terms.term_ID = occ.term_ID '.
					'AND terms.term_type = 4 '. // ordinary terms.
					'GROUP BY terms.term_ID, terms.term, terms.term_type '.
					'ORDER BY COUNT(occ.term_ID) DESC '.
					'LIMIT 0,10;';
	mysql_query($trending_SQL) or die('<b>AAARGH</b>, can''t create temp table - ' . mysql_error());
	$trending_SQL = 'INSERT INTO tmp_insert_buffer SELECT terms.term_ID AS term_ID, terms.term AS term, terms.term_type AS term_type, COUNT(occ.term_ID) AS hits '.
					'FROM terms, tmp_occurrences AS occ '.
					'WHERE terms.term_ID = occ.term_ID '.
					'AND terms.term_type = 5 '. // @users in retweets
					'GROUP BY terms.term_ID, terms.term, terms.term_type '.
					'ORDER BY COUNT(occ.term_ID) DESC '.
					'LIMIT 0,10;';
	mysql_query($trending_SQL) or die('<b>AAARGH</b>, can''t create temp table - ' . mysql_error());
	$trending_SQL = 'INSERT INTO tmp_insert_buffer SELECT terms.term_ID AS term_ID, terms.term AS term, terms.term_type AS term_type, COUNT(occ.term_ID) AS hits '.
					'FROM terms, tmp_occurrences AS occ '.
					'WHERE terms.term_ID = occ.term_ID '.
					'AND terms.term_type = 7 '. // @users in replies
					'GROUP BY terms.term_ID, terms.term, terms.term_type '.
					'ORDER BY COUNT(occ.term_ID) DESC '.
					'LIMIT 0,10;';
	mysql_query($trending_SQL) or die('<b>AAARGH</b>, can''t create temp table - ' . mysql_error());
	
	// Terms too common (like Twitter or Sao Paulo), as well as numbers, have their count divided by an arbitrary number.
	// This prevents them to dominate the trending topics.
	$result = mysql_query('select * from tmp_insert_buffer');
	while($row = mysql_fetch_assoc($result)){
		$special = mysql_fetch_assoc(mysql_query('select * from special_terms where term = "'.$row['term'].'"'));
		if ($special['term'] || is_numeric($row['term'])){
			if (is_numeric($row['term'])){ 
				$newvalue = round($row['hits'] / 20);
			} else $newvalue = round($row['hits'] / $special['divide_by']);
			brecho('Rebaixando '.$row['term'].' de '.$row['hits'].' para '.$newvalue.' hits');
			if ($newvalue < 10)
				mysql_query('DELETE FROM tmp_insert_buffer WHERE term_ID = '.$row['term_ID'].';');
			else mysql_query('UPDATE tmp_insert_buffer 
							SET hits = '.$newvalue.'
							WHERE term_ID = '.$row['term_ID'].';');
		}
	}
	
	// A term can trend only if it has more than 10 hits.
	mysql_query('DELETE FROM tmp_insert_buffer WHERE hits < 10;');
	// Also, the number of people vs occurrences (PVO) must be also high, to ensure it's not only one person repeating a word.
	mysql_query('CREATE TEMPORARY TABLE tmp_PVO ENGINE = MEMORY AS SELECT tib.term_ID, count( DISTINCT st.user_ID ) AS usercount
				FROM tmp_insert_buffer AS tib, 
					tmp_occurrences AS toc,
					status AS st
				WHERE tib.term_ID = toc.term_ID
				AND toc.id = st.id
				GROUP BY tib.term_ID;');
	mysql_query('CREATE TEMPORARY TABLE tmp_low_PVO 
				SELECT tib.term_ID 
				FROM tmp_PVO AS pvo, 
					tmp_insert_buffer AS tib 
				WHERE tib.term_ID = pvo.term_ID
				AND pvo.usercount < 10');
	mysql_query('DELETE FROM tmp_insert_buffer WHERE term_ID in (SELECT term_ID	FROM tmp_low_PVO)');
	
	// Removes trending terms contained in composite words.
	// This prevents "Michael Jackson", "Michael" and "Jackson" to trend at the same time.
	$contained_check = mysql_query('SELECT * FROM tmp_insert_buffer WHERE term_type in (2,3)');
	$terms_1word = array();
	$terms_Xwords = array();
	while($row = mysql_fetch_assoc($contained_check)){
		if ($row['term_type'] == 3) $terms_Xwords[$row['term_ID']] = $row['term'];
		else $terms_1word[$row['term_ID']] = $row['term'];
	}
	
	foreach($terms_Xwords as $term_Xw_id => $term_Xw){
		foreach($terms_1word as $term_1w_id => $term_1w)
			if ((strpos($term_Xw, $term_1w.' ') === false) && (strpos($term_Xw, ' '.$term_1w) === false)){
				// Sem probs.
			} else {
				mysql_query('DELETE FROM tmp_insert_buffer WHERE term_ID = '.$term_1w_id);
				unset($terms_1word[$term_1w_id]);
			}
	}
	
	// UPDATE 26/10/2009 - Limits to 5 trending hashtags, otherwise they dominate the ranking.
	$hashcount_check = mysql_query('SELECT * FROM tmp_insert_buffer where term_type in (1,2,3);');
	$totalcount = mysql_num_rows($hashcount_check);
	$hashcount = 0;
	while($row = mysql_fetch_assoc($hashcount_check)){
		if ($row['term_type'] == 1)
			if ($hashcount >= 5 && $totalcount > $hashcount){
				mysql_query('DELETE FROM tmp_insert_buffer WHERE term_ID = '.$row['term_ID']);
			} else $hashcount++;
	}
	
	// If any data is left after all this, inserts it into trending_now
	$check = mysql_fetch_assoc(mysql_query('select count(*) AS howmany from tmp_insert_buffer;'));
	if ($check['howmany'] != 0){
		mysql_query('DELETE FROM trending_now;') or die('<b>AAARGH</b>, can''t access trending_now - ' . mysql_error());
		mysql_query('INSERT INTO trending_now SELECT * FROM tmp_insert_buffer;') or die('<b>AAARGH</b>, can''t access trending_now  - ' . mysql_error());
		// Updates sysinfo
		mysql_query("UPDATE sysinfo SET last_trending = NOW();") or die('<b>AAARGH</b>, can''t access sysinfo - ' . mysql_error());
	}
	
	LogThis($thread_id, 'Finishing OK after '.(time() - $start_time).' seconds');
	echo date('Y-m-d G:i:s').' - Done!';
?>