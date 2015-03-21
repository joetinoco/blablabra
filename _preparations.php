<?php 
	// _PREPARATIONS.PHP
	// Search variables are set up here. It also specifies which page will be generated: front page, search results page, etc.

	$titlecaption = 'O que o Brasil anda twittando?';
			
	if (!$SERIOUSLY_BROKEN && !$MAINTENANCE_MODE){
		$db = mysql_connect($DB_SERVER, $DB_USER, $DB_PASSWORD);
		if (!$db) {
			echo '<h1>O blablabra furou um olho :(</h1>';
			echo '<p>... e não está enxergando seu banco de dados. Mas já estão correndo com ele pro pronto-socorro.</p>';
			die;
		}
		mysql_select_db($DB_DATABASE) or die ('<h1>O blablabra furou um olho :(</h1><p>... e não está conseguindo selecionar seu banco de dados. Mas já estão correndo com ele pro pronto-socorro.</p>');
		mysql_query("SET NAMES 'utf8';"); // Server-client data will be exchanged using UTF-8
		
		// =============================================================================================================================
		// First preparations: determine which page to show (default front page or stats pages), initialize variables, performs searches
		// =============================================================================================================================
		if (!$FRONTPAGE_ONLY_MODE){
			$found = true; // Signals if the term being searched was found
			$query_terms = 0; // Amount of search terms the user inputted on the search field
			
			// SHOWPAGE determines which page to display
			$showpage = 0; 
			
			$case_sensitive = true; 
			$acento_sensitive = true; // 'acento' means 'accent' in portuguese. They can be ignored if needed.
			if (isset($_GET['semcase'])) // 'semcase' means 'case insensitive'
				if ($_GET['semcase'] == 1) $case_sensitive = false;
			if (isset($_GET['semacentos'])) // 'semacentos' means 'accent insensitive'
				if ($_GET['semacentos'] == 1) $acento_sensitive = false;

			$terms = array(); // Terms from the stats page are stored here in the format (term_ID => term)
			$term_groupings = array(); // Used in insensitive cases. Terms equivalent to a term_ID are listed here, in the format term_ID => (equivalent term_ID, equivalent term_ID, ...)
			$stat_notes = array(); // Stores messages to be displayed in the footer of the stats page
			
			
			// =====================================================================
			// Use the terms to retrieve the matching term_IDs
			if (isset($_GET['s']) || isset($_GET['str_term'])){ // str_term is used to maintain 'backwards compatibility' with the first site versions
				if (isset($_GET['str_term'])) $search_str = $_GET['str_term'];
				else $search_str = $_GET['s'];
				
				if ($search_str != ''){ 			
					$arr_aux = explode(',', $search_str);
					$query_terms = count(array_unique(array_values($arr_aux)));
					
					// Retrieves term_IDs from 'terms' table
					foreach($arr_aux as $aux){
						// Preps $term to the query according to the search parameters
						$term = mysql_real_escape_string(trim($aux));
						$term = FixEncoding($term);
						if ((substr($term,0,1) == '#') || (substr($term,0,1) == '@') || (!$case_sensitive)){ // Case-insensitive search
							$like_type = 'LIKE';
							$term = mb_strtolower($term,'UTF-8');
						} else $like_type = 'LIKE BINARY';
							
						// The actual query
						$term_found = mysql_query('SELECT term, term_type, min(term_ID) AS term_ID FROM terms WHERE term '.$like_type.' "'.str_replace('_','\\_',$term).'" COLLATE utf8_general_ci GROUP BY term, term_type ORDER BY term_type, term_ID;');
						if (mysql_num_rows($term_found) == 0){ // If not found, gives it another case-insensitive try.
							$case_sensitive = false;
							$like_type = 'LIKE';
							$term_found = mysql_query('SELECT term, term_type, min(term_ID) AS term_ID FROM terms WHERE term '.$like_type.' "'.$term.'" COLLATE utf8_general_ci GROUP BY term, term_type ORDER BY term_type, term_ID;');
						}
						
						// Stores results in $terms and $term_groupings arrays
						if(mysql_num_rows($term_found) != 0){
							$row_number = 1;
							$aux_stat_text = '';
							while ($row = mysql_fetch_assoc($term_found)){
								$term_ID = $row['term_ID'];
								$is_username = false;
								if (substr($term,0,1) == '@'){
									$is_username = true;
									$term = strtolower($row['term']);
								} else $term = $row['term'];
								
								// First term goes into $terms...
								if ($row_number == 1 || ($is_username && $row_number == 2)){ 
									$terms[$term_ID] = $term;
									$master_ID = $term_ID;
								} else if(!$case_sensitive || !$acento_sensitive){ 
									// ...the remaining terms are term groupings.
									if (!$is_username){
										$term_groupings[$master_ID][] = $term_ID;
										switch ($row_number){
											case 2:
												$aux_stat_text = 'Inclui resultados para grafias diferentes, como "'.$term.'"';
												break;
											case 3:
												$aux_stat_text .= ', "'.$term.'"';
												break;
											case 4:
												$aux_stat_text .= ', "'.$term.'", etc.';
												break;
										}
									}
								}
								if (mysql_num_rows($term_found) == $row_number && !$acento_sensitive) // Searches without accents and, if found, continues looping.
									$term_found = mysql_query('SELECT term, term_type, min(term_ID) AS term_id FROM terms WHERE term '.$like_type.' "'.RemoveAcentos(str_replace('_','\\_',$term)).'" COLLATE utf8_general_ci GROUP BY term, term_type ORDER BY term_type, term_ID;');
								$row_number++;
							}
							if ($aux_stat_text != '') $stat_notes[] = $aux_stat_text;
						} else {
							$found = false;
							$showpage = 1; // Error message for stats page.
						}
					}
					echo '<!-- Term search took '.number_format(microtime(true) - $starttime, 4).' seconds. -->'."\n";
				}
			}

			// =====================================================================
			// Uses the TERM_IDs to retrieve corresponding TERMs
			// This is sorta deprecated, but preserved for backwards compatibility
			if (isset($_GET['term'])){
				$arr_aux = explode(',', $_GET['term']);
				$query_terms = count(array_unique(array_values($arr_aux)));
				foreach($arr_aux as $aux){
					$term_ID = (int)mysql_real_escape_string($aux);
					$result = mysql_fetch_assoc(mysql_query('SELECT term, term_ID, term_type FROM terms WHERE term_ID = '.$term_ID.' LIMIT 0,1;'));
					if(isset($result['term_ID'])){
						if (substr($result['term'],0,1) == '@') $term = mb_strtolower($result['term'],'UTF-8');
						else $term = $result['term'];
						$terms[$term_ID] = $term;
						if (substr($result['term'],0,1) == '@'){
							// for @usernames, searches for equal terms with a different term_type
							if ($result['term_type'] == 7) $other_type = 5;
							else $other_type = 7;
							$other_terms = mysql_fetch_assoc(mysql_query('SELECT min(term_ID) FROM terms WHERE term LIKE "'.$term.'" COLLATE utf8_general_ci AND term_type = '.$other_type.';'));
							if (isset($other_terms['term_ID'])) $terms[$other_terms['term_ID']] = $term;
						}
					} else die('Invalid TERM ID.');
				}
			}
			
			// ===================================================================================
			// Defines which page to display. Codes are:
			// 0 - default start page, 
			// 1 - stats page for one term, 
			// 2 - stats comparison for two or more terms, 
			// 3 - FAQ
			switch ($query_terms){
				case 0:
					$showpage = 0;
					if (!$found) $showpage = 1;
					break;
				case 1:
					$titlecaption = 'Estatísticas para a palavra "'.$term.'"';
					$showpage = 1;
					break;
				default: 
					$titlecaption = 'Comparando: '.implode(', ', array_unique(array_values($terms)));
					$showpage = 2;
					break;
			}
			if (isset($_GET['faq'])){
				$showpage = 3;
				$titlecaption = 'Perguntas frequentes (FAQ)';
			}
		} else $showpage = 0;
	}
	// ====================================================================================
?>