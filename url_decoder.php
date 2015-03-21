<?php
	// URL DECODER
	// URL unshortener
	// Uses CURL - a simple and ingenious idea by a polish guy (@pies on Twitter), seen here - http://devthought.com/blog/server-side/2009/04/php-url-shortening-class-released/
	
	$starttime = microtime(true);
	
	require 'blablabra_config.php';
	require 'blablabra_functions.php';

	if (!isset($_GET['debug']))
		if (!$CRON_URL_DECODER){
			brecho('URL Decoder deactivated in blablabra_config');
			die;
		}
	
	$thread_id = date('ymdHis').rand(0,9).' URLDECODER';
	
	$db = mysql_connect($DB_SERVER, $DB_USER, $DB_PASSWORD);
	if (!$db) {
		die;
	}
	mysql_select_db($DB_DATABASE) or die;
	
		$timeout_limit = 300; // Script goes on for five minutes or until all URLs are unshortened
	if (isset($_GET['timeout'])) $timeout_limit = $_GET['timeout'];
	$iterations = 0;
	$successful_urls = 0;
	$failed_urls = 0;
	
	while((microtime(true) - $starttime) < $timeout_limit){
		$iterations++;
		$query = 'SELECT * FROM url_occurrences WHERE decoded < 1 limit '.$failed_urls.',1;';
		$result = mysql_query($query);
		if (mysql_num_rows($result) == 0)
			// No lines to process
			break;
		
		$occ = mysql_fetch_assoc($result);
		// Tags the line as "in progress" (code 2) so no other script that might be running tries to process it.
		mysql_query('UPDATE url_occurrences SET decoded = 2	WHERE url = "'.mysql_real_escape_string($occ['url']).'" AND ID = '.$occ['id'].';');
		
		$url = $occ['url'];
		$decoded = 0;
		$is_https = $occ['is_https'];
		if ($is_https == 1) $protocol = 'https://';
		else $protocol = 'http://';
		
		brecho("<hr><br>Processando URL - ".$protocol.$url);
		
		// Verifies if the URL comes from an known shortener
		$cut = strpos($url,'/');
		if ($cut === false || $cut == strlen($url)-1){
			brecho("No trailing slash");
			$decoded = 1; // If no trailing slash, or a slash at the end of the URL(like uol.com.br) is obviously not a shortener URL
		} else {
			$base_url = substr($url,0,strrpos($url,'/')+1);
			
			// Uses CURL to fetch the destination (and - voila - unshortened) URL
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $protocol.$url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // Returned content will be a string
			curl_setopt($login, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.1) Gecko/20090624 Firefox/3.5 (.NET CLR 3.5.30729)"); // Pretends it's firefox (otherwise sites like Facebook return an error)
			curl_setopt($ch, CURLOPT_HEADER, true); // Gets the header...
			curl_setopt($ch, CURLOPT_NOBODY, true); // ...and the header only.
			curl_setopt($ch, CURLOPT_HTTPGET, true); // Does a HTTP GET (instead of HEAD, which just returns the header)
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follows all redirects until the final URL.
			curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Timeout, in seconds
			$output = curl_exec($ch);
			$curl_info = curl_getinfo($ch);
			
			// CURL is successful if returns a code lesser than 400
			// or if it fails and the returned data contains a 301 redirect			
			if ($output === false){
				// CURL error - which is ignored if it actually returned a correct URL.
				if ($curl_info['http_code'] < 400 && strcmp($protocol.$url,$curl_info['url']) != 0)
					$decoded = 1;
				else {
					// This is an actual error. Uses a negative code in the "decoded" field to count retries.
					// This stops after nine tries (decoded = -9)
					brecho("CURL failed - ".$curl_info['http_code']);
					brecho($curl_info);
					brecho($output);
					$failed_urls++;
					if ($occ['decoded'] <= -1) $decoded = $occ['decoded'] - 1;
					else $decoded = -1;
				}
			} else $decoded = 1;
			
			if ($decoded == 1) {
				// Connection successful - retrieves the correct URL.
				$successful_urls++;
				if (strtolower(substr($curl_info['url'],4,1)) == 's'){
					$is_https = 1;
					$url = substr($curl_info['url'],8);
				} else $url = substr($curl_info['url'],7);
				brecho('CURL went smoothly (HTTP '.$curl_info['http_code'].'), unshortened URL is - '.$url);
			} else $decoded = $occ['decoded'] - 1;
			curl_close($ch);
		}
		
		// Updates url_occurrences
		if ($decoded < -9){
			// More than 9 retries indicate a URL that cannot be reached. So it is deleted.
			$query = 'DELETE FROM url_occurrences
						WHERE url = "'.mysql_real_escape_string($occ['url']).'" AND ID = '.$occ['id'].';';
		} else {
			// Before the update, adjusts the URL format: with or without a trailing slash, with or without www at the beginning, based on what's already stored in the table.
			$prefix = 'www.';
			$suffix = '';
			$middle = $url;
			if (substr($url,0,4) == 'www.')
				$middle = substr($middle,4);
				
			if(strrpos($url,'/') === strlen($url)-1){
				$middle = substr($middle,0,strlen($url)-1);
				$suffix = '/';
			}
			$formatquery = 'SELECT url FROM url_occurrences 
							WHERE url = "'.$prefix.$middle.$suffix.'"
							OR url = "'.$prefix.$middle.'"
							OR url = "'.$middle.$suffix.'"
							OR url = "'.$middle.'" LIMIT 0,1;';
			$alreadyrecorded = mysql_fetch_assoc(mysql_query($formatquery));
			if (isset($alreadyrecorded['url']))
				if($alreadyrecorded['url'] != $url){
					$url = $alreadyrecorded['url'];
					brecho('ATENÇÃO - Já gravada na base com grafia diferente ('.$alreadyrecorded['url'].'), usando esta grafia');
				}
			// Setup the update query
			$query = 'UPDATE url_occurrences 
						SET url = "'.mysql_real_escape_string($url).'", decoded = '.$decoded.', is_https = '.$is_https.'
						WHERE url = "'.mysql_real_escape_string($occ['url']).'" AND ID = '.$occ['id'].';';
			
			$query2 = '';
			if ($decoded == 1)
			$query2 = 'UPDATE url_occurrences 
						SET url = "'.mysql_real_escape_string($url).'", decoded = 1, is_https = '.$is_https.'
						WHERE url = "'.mysql_real_escape_string($occ['url']).'" AND decoded = 0;';
		}
		
		if ($decoded != 1) $failed_urls++;
		
		brecho($query);
		if (!mysql_query($query)){
			LogThis($thread_id, 'ERROR - Cannot update URL with ID '.$occ['id'].' - '.mysql_error());
			$failed_urls++;
		}
		
		if ($query2 != ''){
			brecho($query2);
			if (!mysql_query($query2)){
				LogThis($thread_id, 'ERROR - Cannot update URL with ID '.$occ['id'].' - '.mysql_error());
			}
		}
		
	}
	LogThis($thread_id, 'Finishing: '.number_format(microtime(true) - $starttime, 4).' seconds. Did '.$iterations.' URLs, '.$successful_urls.' decoded, '.$failed_urls.' errors.');
?>