<?php
// ====================================================================================================================================
// ====================================================================================================================================
//					BLABLABRA FUNCTIONS
//						This is Blablabra's function "toolbox".
// ====================================================================================================================================
// ====================================================================================================================================

// ====================================================================================================================================
// CHECK TABLE CACHE
// Deprecated. Retrieves which table should be searched for one or more terms, according to the cache state of each of them.
function CheckTableCache($terms){
	$occurrences_table = 'term_occurrences';
	/*
	if(mysql_num_rows(mysql_query("SHOW TABLES LIKE 'cache_frontpage_index'"))){
		$tblcache = mysql_fetch_assoc(mysql_query('SELECT count(*) AS cached_items FROM cache_frontpage_index WHERE term_ID IN ('.implode(',',$terms).');'));
		if ($tblcache['cached_items'] == count(array_unique($terms))) $occurrences_table = 'cache_frontpage';
	}
	*/
	return $occurrences_table;
}

// ====================================================================================================================================
// FORMAT LABEL
// Returns a neatly formatted label for the chart, taking into account the data timeframe ($accuracy) - weekly, daily, hourly
// This is used by GoogleGraph()
function FormatLabel($timelabel, $accuracy){
	switch($accuracy){
		case '1min':
			return substr($timelabel,11,5);
		case '10min':
			return substr($timelabel,11,4).'0';
		case '1hour':
			return substr($timelabel,11,2);
		case '1day':
			return substr($timelabel,8,2).'/'.substr($timelabel,5,2);
		case 'week':
			$weekday = date('D',mktime(0,0,0,substr($timelabel,5,2),substr($timelabel,8,2),substr($timelabel,0,4)));
			switch($weekday){
				case 'Sun': return 'Dom'; // Translating weekdays to portuguese
				case 'Mon': return 'Seg';
				case 'Tue': return 'Ter';
				case 'Wed': return 'Qua';
				case 'Thu': return 'Qui';
				case 'Fri': return 'Sex';
				case 'Sat': return 'Sáb';
			}			
	}
}

// ====================================================================================================================================
// GOOGLE GRAPHS
// Generates graphs (or charts? - my english sucks) using Google Graph's API.
// v2.0 - Plots several data sequences in the same graph.
function GoogleGraph($plot_terms, $term_groupings, $acc_set = '10min', $xres = 20, $graph_title = '', $width = 500, $height = 150){
	require 'blablabra_config.php'; // Requires a connection to the tweets database
	$is_trending = false;
	$accuracy = array( 	'1min' => 16,  // This is the scaling for the X axis.
						'10min' => 15, // The number is the splitting point for the timestamp string
						'1hour' => 13,
						'1day' => 10,
						'week' => 10);	
	
	$db = mysql_connect($DB_SERVER, $DB_USER, $DB_PASSWORD);
	if (!$db) {
		return('<b>AAARGH</b>, can''t connect to the database.');
	}
	mysql_select_db($DB_DATABASE);
	
	if ($plot_terms == 'TRENDING'){ // Special mode - plots the 3 first trending topics
		$is_trending = true;
		$plot_terms = array();
		$trending = mysql_query('SELECT term_ID, term FROM `trending_now` where term_type <=3 order by hits desc limit 0,3;');
		if (mysql_num_rows($trending) == 0)
			$trending = mysql_query('SELECT term_ID, term FROM `trending_now` where term_type <= 4 order by hits desc limit 0,3;');
		while ($trow = mysql_fetch_assoc($trending)){
			$plot_terms[$trow['term_ID']] = $trow['term'];
		}
	}
	
	if (!is_array($plot_terms)) $plot_terms = array($plot_terms => 0);
	
	// Determines which table will be used to read term occurrences. Terms shown on the front page are cached, thus are much faster to read.
	$occurrences_table = CheckTableCache(array_keys($plot_terms));
	
	// Reads term occurrences in a temp table.
	if (!$is_trending){
		$max_time = mysql_fetch_assoc(mysql_query('SELECT max(time_of_occurrence) AS max_time FROM '.$occurrences_table.' WHERE term_ID IN ('.implode(',',array_keys($plot_terms)).');'));
	} else {
		$max_time = mysql_fetch_assoc(mysql_query('SELECT NOW() AS max_time'));
	}	
	// Appends the thread ID and a random number to the table name, to prevent simultaneous calls to the code of accessing the same table.
	$tmptable = 'tmp_stats'.mysql_thread_id($db).rand(0,999); 
	$OKtoPlot = false;
	do{
		switch($acc_set){
		case '1min':
			$step = 1*60;
			$time_suffix = ':00';
			break;
		case '10min':
			$step = 10*60;
			$time_suffix = '0:00';
			break;
		case '1hour':
			$step = 60*60;
			$time_suffix = ':00:00';
			break;
		case '1day':
		case 'week':			
			$step = 24*60*60;
			$time_suffix = ' 00:00:00';
			break;
		}
		$endtime = substr(date('Y-m-d H:i:s', strtotime($max_time['max_time'])-($step * ($xres + 1)) ), 0, $accuracy[$acc_set]).$time_suffix;
		// But how much data is there to put on the graph?
		$datapoints = mysql_fetch_assoc(mysql_query('SELECT count(*) AS points 
						FROM '.$occurrences_table.'
						WHERE time_of_occurrence >= "'.$endtime.'" 
						AND time_of_occurrence <= "'.substr(date('Y-m-d H:i:s',(strtotime($max_time['max_time']) - $step)),0,$accuracy[$acc_set]).$time_suffix.'"
						AND term_ID IN ('.implode(',',array_keys($plot_terms)).');'));
		// The graph scaling can be overriden if there is not enough data to render it properly at the current resolution.
		if ($datapoints['points'] < $xres){
			if ($acc_set != 'week'){
				// Attempts to rebuild the temp table with weekly data
				$acc_set = 'week';
				$xres = 7;
			} else {
				// If there is no data to plot, returns a blank chart.
				if ($datapoints['points'] == 0){
					return "blank.png";
				} else $OKtoPlot = true;
			}
		} else $OKtoPlot = true;
		
	}while(!$OKtoPlot);
		
	// With the scaling correctly determined, let's get the actual data.
	$query = 'CREATE TEMPORARY TABLE '.$tmptable.' ENGINE = MEMORY AS
			SELECT term_ID, TIMESTAMPADD(HOUR,'.$TIME_OFFSET.',time_of_occurrence) AS time 
			FROM '.$occurrences_table.' 
			WHERE time_of_occurrence >= \''.$endtime.'\'
			AND term_ID IN ('.implode(',',array_keys($plot_terms)).');';
	$result = mysql_query($query);
	if (!$result) return('<b>AAARGH</b>, nao consegui criar a tabela temporária.'.mysql_error());
	
	// Term groupings are included in the temp table, under the same term_ID of the "master" term.
	if (is_array($term_groupings) && count($term_groupings) > 0){
		foreach($term_groupings as $master_ID => $slave_IDs)
		$query = 'INSERT INTO '.$tmptable.' 
				SELECT '.$master_ID.' AS term_ID, TIMESTAMPADD(HOUR,'.$TIME_OFFSET.',time_of_occurrence) AS time 
				FROM term_occurrences 
				WHERE time_of_occurrence >= \''.$endtime.'\' 
				AND term_ID IN ('.implode(',',array_values($slave_IDs)).');';
		$result = mysql_query($query);
		if (!$result) return('<b>AAARGH</b>, can''t add term groupings to the temp table.');
	}
					
	if ($acc_set == 'week') $xres = 7;
	
	// Data points are stored in $gdata, a n-dimensional array of key-value pairs.
	// $gdata[0] stores X axis labels (keys) and values for the first data sequence
	// $gdata[1] does the same, but for the second data sequence, and so on.
	$gdata = array();
	$timescale_ref = array();
	$gdata_key = 0;
	$scale = 0;
	
	foreach($plot_terms as $term_ID => $term_name){
		// X axis labels are generated in $datatable, then copied to $gdata
		$datatable = array();
		$graph_query = 'SELECT LEFT(time,'.$accuracy[$acc_set].') AS time, count(*) AS hits 
						FROM '.$tmptable.'
						WHERE term_ID = '.$term_ID.'
						GROUP BY LEFT(time,'.$accuracy[$acc_set].')
						ORDER BY time DESC LIMIT 1,'.$xres.';';
		$graph_data = mysql_query($graph_query) or die('<b>AAARGH</b>, can''t get data from the occurrences table.');
		
		// Use the first record from the result set to infer the X-axis labels, storing them into a pre-filled array
		// This is required because sometimes there are "holes" in the data set (i.e., days without data in a daily graph). 
		// Those don't come from the query results but need to be shown in the chart.
		$row = mysql_fetch_assoc($graph_data);
		if (isset($row)){
			$datatable[(string)FormatLabel($row['time'],$acc_set)] = $row['hits'];
			$startpoint = strtotime($row['time'].$time_suffix);
			$timescale_ref[$gdata_key] = $startpoint;
			for ($i = 1; $i < $xres; $i++){
				$datatable[(string)FormatLabel(date('Y-m-d H:i:s',$startpoint-($i*$step)),$acc_set)] = 0;
			}
			// Invents a title for the graph, if not provided
			if ($graph_title == '' && !$is_trending){
				if ($startpoint >= strtotime(substr(date('Y-m-d H:i:s',(time() - $step)),0,$accuracy[$acc_set]).$time_suffix)){
					// there is data until the current instant in time, so the title reads 'last [x] [period]' (e.g., 'last 3 days')
					switch($acc_set){
						case '1min':
							$graph_title = 'Últimos '.(($step*$xres)/60).' minutos';
							break;
						case '10min':
							if ($xres > 6)
								if (floor(($step*$xres)/(60*60)) == 1) $graph_title = 'Última hora';
								else $graph_title = 'Últimas '.floor(($step*$xres)/(60*60)).' horas';
							else $graph_title = 'Últimos '.(($step*$xres)/60).' minutos';
							break;
						case '1hour':
							$graph_title = 'Últimas '.(($step*$xres)/(60*60)).' horas';
							break;
						case '1day':
						case 'week':			
							$graph_title = 'Últimos '.(($step*$xres)/(60*60*24)).' dias';
							break;
					}
				} else {
					// there is a gap between now and the time of the most current data point. So the title shows the latter's date/day/hour.
					$graph_title = date('d/m',$startpoint);
					switch ($graph_title){
						case date('d/m',time()): 
							$graph_title = 'Hoje, '.$graph_title;
							break;
						case date('d/m',time()-(24*60*60)): 
							$graph_title = 'Ontem, '.$graph_title;
							break;
					}
				}
			}		
		} 
		// Data outside the graph's timeframe is discarded. 
		// They can be returned in the query and cause bugs like an overlap of two weekdays from different weeks in a weekly chart.
		$min_time = $startpoint-($xres*$step);

		// Fills $datatable with the rest of the result set
		while ($row = mysql_fetch_assoc($graph_data)){
			if (strtotime($row['time'].$time_suffix) >= $min_time){
				$datatable[FormatLabel($row['time'],$acc_set)] = $row['hits'];
			}
		}
		
		// Reverses and chops the array at position $xres 
		$tmp_arr = array_chunk($datatable, $xres, true);
		$datatable = array_reverse($tmp_arr[0], true);
		
		// Stores the greater data point. This is used later to infer the y-axis scale.
		$r = array_values($datatable);
		rsort($r);
		if ($r[0] > $scale) $scale = $r[0];
		
		// Finally, copies $datatable into $gdata
		$gdata[$gdata_key] = $datatable;
		$gdata_key++;
	}
	
	// In graphs with several data sequences, axis scales must be synchronized because they don't always contain data for the same timeframe.
	// Ex.: one might contain data from 6pm to 9pm, the other might contain data from 5h30pm to 8h30pm, and so on.
	if(count($gdata) > 1){
		arsort($timescale_ref);
		$ref = key($timescale_ref);
		$refseries = array_keys($gdata[$ref]);
		
		for ($i = 0; $i < count($gdata); $i++)
			if ($i != $ref){
				reset($refseries);
				$newseq = array();
				do{
					if (isset($gdata[$i][current($refseries)]))
						$newseq[current($refseries)] = $gdata[$i][current($refseries)];
					else 
						$newseq[current($refseries)] = 0;
				}while(!(next($refseries) === FALSE));
				$gdata[$i] = $newseq;
			}
	}
	
	// Now the fun part: generating the chart.
	// (which is basically creating a long and complex URL into $graph_url which will be used to trigger Google Graph's API).
	if ($height < 100 || (count($gdata) > 1 && !$is_trending)) $axis_font_size = 9;
	else $axis_font_size = 11;
	
	$graph_url = 'http://chart.apis.google.com/chart?'; // Graph type (it's actually a chart)
	if ($acc_set != 'week') $graph_url .= 'cht=lc'; // Line chart
	else $graph_url .= 'cht=bvg&chbh=a,1,10'; // Bar chart with grouped columns, width is automatic, bars and groups are spaced out 1 and 10 pixels respectively.
	$graph_url .= '&chd=t:'; // The data points
	foreach($gdata as $series_data){
		$graph_url .= trim(implode(',',array_values($series_data))).'|';
	}
	$graph_url = substr($graph_url,0,-1); // Chops out the last '|' from the data string
	// Parameters, labels, etc.
	$graph_url .= '&chds=0,'.$scale. // Scaling
	'&chs='.$width.'x'.$height;
	if ($graph_title != '') $graph_url .= '&chtt='.preg_replace('@\s@','+',$graph_title);
	$graph_url .= '&chxt=x,y&chxl=0:|'.implode('|',array_keys($gdata[0])).'|1:|'.ceil($scale/2).'|'.$scale. // X and Y axis labels
	'&chco=FFA51F,7F0000,004A7F,267F00'. // Data series' colors
	'&chxp=1,50,100'.
	'&chxs=1,666666,'.$axis_font_size.',-1,t,CCCCCC|0,666666,'.$axis_font_size.'&chxtc=1,-'.$width; // Axis labels and gridlines parameters
	if (count($plot_terms) > 1 || $is_trending){
		// Generates the graph legend
		$aux_legend = $plot_terms;
		$termtypes_result = mysql_query('SELECT term_ID, term_type FROM terms WHERE term_ID IN ('.implode(',',array_keys($plot_terms)).');');
		while ($termtype = mysql_fetch_assoc($termtypes_result)){
			// If it is a @username, appends "retweets" or "replies" to the label to explain which is the data displayed.
			if ($termtype['term_type'] == 5 || $termtype['term_type'] == 7){
				if ($termtype['term_type']	== 5) $suffix_text = ' (retweets)';
				if ($termtype['term_type']	== 7) $suffix_text = ' (replies)';
				$aux_legend[$termtype['term_ID']] .= $suffix_text;
			}
		}
		$graph_url .= '&chdl='.urlencode(trim(implode('|',array_values($aux_legend)))); // Legend
	}
	return $graph_url;
}

// ====================================================================================================================================
// PRINT STATS
// Prints stats from a term into $terms
function PrintStats($terms, $term_groupings){

	$day_step = 60*60*24; // Amount of milisseconds in a day. Used to make date operations with UNIX timestamps easier.
	
	// Creates a "term_types" array with term_types of corresponding term_IDs. This is used to show the table and the calendar.
	$term_result = mysql_query('SELECT term_ID, term_type FROM terms WHERE term_ID IN ('.implode(',',array_keys($terms)).');');
	while ($item = mysql_fetch_assoc($term_result)){
		$term_types[$item['term_ID']] = $item['term_type'];
		if (mysql_num_rows($term_result) == 1) switch ($item['term_type']){
			case 5:
				echo '<p><b>Obs.:</b> Os números mostrados referem-se apenas a retweets, pois não foi contado nenhum reply para o usuário.</p>'."\n\t\t\t";
				break;
			case 7:
				echo '<p><b>Obs.:</b> Os números mostrados referem-se apenas a replies direcionados ao usuário. Não foi contado nenhum retweet do usuário.</p>'."\n\t\t\t";
				break;
		}
	}
	
	// Determines which table will be used to read term occurrences. Terms shown on the front page are cached, thus are much faster to read.
	$occurrences_table = CheckTableCache(array_keys($terms));
	
	// $tmpdailies is a temp table to calculate a term's daily average of mentions
	$tmpdailies = 'tmp_'.rand(0,99999);
	mysql_query('CREATE TEMPORARY TABLE '.$tmpdailies.'
		SELECT term_ID, LEFT(time_of_occurrence, 10) AS day, COUNT(*) AS day_hits
		FROM '.$occurrences_table.'
		WHERE term_ID IN ('.implode(',',array_keys($terms)).')
		GROUP BY term_ID, LEFT(time_of_occurrence, 10);');
	
	if (is_array($term_groupings) && count($term_groupings) > 0)
		foreach ($term_groupings as $master_ID => $slave_IDs)
			mysql_query('INSERT INTO '.$tmpdailies.'
				SELECT '.$master_ID.' AS term_ID, LEFT(time_of_occurrence, 10) AS day, COUNT(*) AS day_hits
				FROM term_occurrences
				WHERE term_ID IN ('.implode(',',array_values($slave_IDs)).')
				GROUP BY term_ID, LEFT(time_of_occurrence, 10);');
		
	// Totals (per day) are inserted into a calendar array ($cal), like this: term_ID => (data => hits, data => hits, data => hits)
	foreach ($terms as $term_ID => $term_name){
		$aux_cal = array();
		$day_result = mysql_query('SELECT day, SUM(day_hits) AS day_hits FROM '.$tmpdailies.' WHERE term_ID = '.$term_ID.' GROUP BY day ORDER BY day DESC;');
		if ($row = mysql_fetch_assoc($day_result)){
			$max_day = strtotime($row['day']);
			$aux_cal[$max_day] = $row['day_hits'];
			$row = mysql_fetch_assoc($day_result);
			for ($i = 1; $i <= 30; $i++){
				if ($row){ 
					if (strtotime($row['day']) == $max_day-($day_step * $i)){
						$aux_cal[$max_day-($day_step * $i)] = $row['day_hits'];
						$row = mysql_fetch_assoc($day_result);
					} else $aux_cal[$max_day-($day_step * $i)] = 0;
				} else $aux_cal[$max_day-($day_step * $i)] = 0; // No more lines? Uses zeros for padding.
			}
		}
		$cal[$term_ID] = array_reverse($aux_cal, true);
	}
	
	// Prints a neat HTML table with the results
	echo '<table>'."\n\t\t\t".'<tr class="first_line">';
	if (count($terms) > 1) 
		echo '<td>Palavra</td>';
	echo '<td>Últimos 30 dias</td><td>Média</td><td>Máximo</td></tr>'."\n\t\t\t"; // Column names
	
	// Retrieves monthly totals for the term. This is also used to switch between terms when the table is being printed.
	$query = 'SELECT tocc.term_ID, SUM(tocc.day_hits) AS total_hits
		FROM '.$tmpdailies.' AS tocc
		WHERE tocc.term_ID IN ('.implode(',',array_keys($terms)).') 
		GROUP BY tocc.term_ID;';
	$totals_result = mysql_query($query);
	
	// The most recent and most ancient days are chopped off the table, to ensure the average only takes into account full 24-hour days
	foreach(array_keys($terms) as $aux_id){
		mysql_query('DELETE FROM '.$tmpdailies.' WHERE term_ID = '.$aux_id.' AND day = (SELECT MIN(day) FROM '.$tmpdailies.' WHERE term_ID = '.$aux_id.');');
		mysql_query('DELETE FROM '.$tmpdailies.' WHERE term_ID = '.$aux_id.' AND day = (SELECT MAX(day) FROM '.$tmpdailies.' WHERE term_ID = '.$aux_id.');');
	}
	
	while($aux = mysql_fetch_assoc($totals_result)){
		// Determines the term type, if necessary
		if (count($terms) > 1){ 
			switch ($term_types[$aux['term_ID']]){
				case 5:
					echo '<td><b>'.$terms[$aux['term_ID']].'</b><br/><small>em retweets</small></td>'."\n\t\t\t";
					break;
				case 7:
					echo '<td><b>'.$terms[$aux['term_ID']].'</b><br/><small>em respostas (@replies)</small></td>'."\n\t\t\t";
					break;
				default:
					echo '<td><b>'.$terms[$aux['term_ID']].'</b></td>'."\n\t\t\t";
			}
		}
		// Total occurrences
		echo '<td>'.$aux['total_hits'].'<br/>ocorrências</td>'."\n\t\t\t";
		// Daily average
		$query = 'SELECT term_ID, SUM(day_hits)/DATEDIFF(MAX(day),MIN(day)) AS daily_avg
			FROM '.$tmpdailies.' WHERE term_ID = '.$aux['term_ID'].' GROUP BY term_ID';
		$average = mysql_fetch_assoc(mysql_query($query));
		$str_avg = (int)$average['daily_avg'];
		if ($str_avg == 0) $str_avg = '-';
		else $str_avg .= '<br/>ocorr/dia';
		echo '<td>'.$str_avg.'</td>'."\n\t\t\t";
		// Daily maximum for the last 30 days.
		$aux_max = $cal[$aux['term_ID']];
		arsort($aux_max);
		echo '<td>'.current($aux_max).'<br/>(em '.date('j/n/y',key($aux_max)).')</td>'."\n\t\t\t";
		$max_hits[$aux['term_ID']] = current($aux_max);
		echo '</tr>'."\n\t\t\t";
	}
	echo '</table>'."\n\t\t\t";
	echo '<p><small>Médias diárias podem não ser calculadas para períodos de menos de 24 horas.</small></p>'."\n\t\t\t";
	
	// Prints a "heatmap calendar" showing the hottest days (days with more mentions).
	if (isset($max_hits)){
		echo '<h2> Distribuição por dia da semana <small>(nos últimos 30 dias)</small></h2>'."\n\t\t\t";
		foreach ($cal as $term_ID => $cal_data){
			echo '<table>'."\n\t\t\t";
			if (count($terms) > 1){
				switch ($term_types[$term_ID]){
					case 5:
						echo '<tr><td colspan="7"><b>'.$terms[$term_ID].'</b><br/><small>em retweets</small></td></tr>'."\n\t\t\t";
						break;
					case 7:
						echo '<tr><td colspan="7"><b>'.$terms[$term_ID].'</b><br/><small>em respostas (@replies)</small></td></tr>'."\n\t\t\t";
						break;
					default:
						echo '<tr><td colspan="7"><b>'.$terms[$term_ID].'</b></td></tr>'."\n\t\t\t";
				}
			}
			echo '<tr class="first_line"><td>Dom</td><td>Seg</td><td>Ter</td><td>Qua</td><td>Qui</td><td>Sex</td><td>Sáb</td></tr>'."\n\t\t\t";
			echo '<tr>'."\n\t\t\t";
			$aux = array_keys($cal_data);
			sort($aux);
			$min_date = $aux[0];
			$week_offset = (int)date('w',$min_date) * (-1);
			while($week_offset < 0){
				echo '<td class="graycell">';
				echo date('j',$min_date+($week_offset*$day_step));
				echo '</td>'."\n\t\t\t";
				$week_offset++;
			}
			foreach($cal_data as $cal_date => $cal_hits){
				$cell_color = floor(($cal_hits/$max_hits[$term_ID])*10);
				if ($cell_color == 0 && $cal_hits > 0) $cell_color = 1;
				echo '<td class="cellcolor'.$cell_color.'">';
				echo date('j',$cal_date);
				echo '</td>'."\n\t\t\t";
				if ((int)date('w',$cal_date) == 6){
					echo '</tr>'."\n\t\t\t".'<tr>';
				}
				$last_date = $cal_date;
			}
			$week_offset = 1;
			$max_offset = 6 - (int)date('w',$last_date);
			while($week_offset <= $max_offset){
				echo '<td class="graycell">';
				echo date('j',$last_date+($week_offset*$day_step));
				echo '</td>'."\n\t\t\t";
				$week_offset++;
			}
			echo '</tr>'."\n\t\t\t";
			echo '</table>'."\n\t\t\t";
			// Calendar legend
			echo '<div class="legend"><p><small>Ocorrências:</small>&nbsp;<table class="legend"><tr>';
			echo '<td class="cellcolor0">Nenhuma</td>';
			echo '<td class="cellcolor1">1 - '.floor(0.2*$max_hits[$term_ID]).'</td>';
			echo '<td class="cellcolor3">'.(floor(0.2*$max_hits[$term_ID])+1).' - '.floor(0.4*$max_hits[$term_ID]).'</td>';
			echo '<td class="cellcolor5">'.(floor(0.4*$max_hits[$term_ID])+1).' - '.floor(0.6*$max_hits[$term_ID]).'</td>';
			echo '<td class="cellcolor7">'.(floor(0.6*$max_hits[$term_ID])+1).' - '.floor(0.8*$max_hits[$term_ID]).'</td>';
			echo '<td class="cellcolor9">'.(floor(0.8*$max_hits[$term_ID])+1).' - '.$max_hits[$term_ID].'</td></tr></table></p></div>'."\n\t\t\t";
		}
	}
}

// ====================================================================================================================================
// FRIENDLY TOPICS
// Returns a user-friendly, $max_len-long string with the topics contained in $arr_topics.
// This is used in the "share this on Twitter" links.
function FriendlyTopics($arr_topics, $max_len=140, $html_link_safe=false){
	$str_topics = '';
	if (is_array($arr_topics)){
		foreach($arr_topics as $topic){
			if (strlen($str_topics.', '.$topic) > $max_len) break;
			if ($str_topics != '') $str_topics .= ', ';
			$str_topics .= $topic;
		}
		if (count($arr_topics) > 1) $str_topics = substr_replace($str_topics, ' e', strrpos($str_topics,','),1); // Replaces the last comma for an 'e' (and, in portuguese)
	} else $str_topics = $arr_topics;
	if ($html_link_safe) $str_topics = str_replace('#','%23',$str_topics);
	return $str_topics;
}
// ====================================================================================================================================
// PRINT TWEET
// Formats and prints a tweet. 
// (2015 NOTE): This is deprecated because it was written waaaay before Twitter mandated that all tweet representations should be embedded from their end.
function PrintTweet($row, $highlights = 'None'){
	require 'blablabra_config.php';
	
	// Adjusts some parameters from the Twitter API, if needed
	if (!isset($row['user_profile_image_url'])){
		$row['user_profile_image_url'] = $row['profile_image_url'];
		$row['user_screen_name'] = $row['from_user'];
		$row['tweet'] = $row['text'];
		$row['created_at'] = $row['created_at'];
		$row['source'] = $row['source'];
	}
	
	echo '<div class="tweet"><p>';
	// If there is no user picture, goes with the default.
	if ($row['user_profile_image_url'] == ''){ 
		$avatar_url = "http://static.twitter.com/images/default_profile_normal.png";
	} else $avatar_url = $row['user_profile_image_url'];
	
	// Avatar
	echo '<img src="'.$avatar_url.'" class="avatar_img" height=48 width=48> '; 
	
	// Username (with a link to their profile)
	echo '<b><a target="_blank" href="http://twitter.com/'.$row['user_screen_name'].'">'.$row['user_screen_name'].'</a></b> '; 
	
	// The actual tweet
	$tweet_text = $row['tweet'];
	// REGEX to include "<a href" into the links
	$tweet_text = preg_replace('{http(s?)://(\S)+}','<a target="_blank" href="$0">$0</a>',$tweet_text);
	// REGEX to include profile links to the user instead of @usernames
	$tweet_text = preg_replace('/@([a-zA-Z0-9_-]+)/','<a target="_blank" href="http://twitter.com/$1">@$1</a>',$tweet_text); 
	
	// REGEX that bolds some tweet terms (used when retrieving search results)
	if ($highlights != 'None'){
		foreach ($highlights as $hl_term)
			$tweet_text = str_replace($hl_term,'<b>'.$hl_term.'</b>',$tweet_text); 
	}
	echo $tweet_text .'<br/>'."\n"; 
	
	// Date/time of the tweet adjusted for the Brazilian format.
	$tweet_date = strtotime($row['created_at']);
	$tweet_date += $TIME_OFFSET*60*60; // Adds 4 hours - Twitter is in California
	echo '<small>enviado em '.date("d/m\, G:i",$tweet_date);
	
	// Tweet source
	echo ' via '.html_entity_decode($row['source']); 
	echo "</small></p></div>"."\n";
}
// ====================================================================================================================================
// FIX ENCODING
// Fixes utf8 issues
function FixEncoding($in_str)
{
  $cur_encoding = mb_detect_encoding($in_str) ;
  if($cur_encoding == "UTF-8" && mb_check_encoding($in_str,"UTF-8"))
    return $in_str;
  else
    return utf8_encode($in_str);
}
// ====================================================================================================================================
// REMOVE ACENTOS
// Removes accents (é, á, ã, etc) from a term. Used for search.
function RemoveAcentos($in_str)
{
	return strtr(utf8_decode($in_str), utf8_decode("áàâãéêíñóõôúüçÁÂÀÉÊÍÑÓÕÔÚÜÇ"), "aaaaeeinooouucAAAEEINOOOUUC");
}
// ====================================================================================================================================
// SEARCH ALTERNATIVES
// Displays alternatives whenever the search returns no results.
// Exibe alternativas de pesquisa para quando o site não encontra algo que o usuário digitou, 
function SearchAlternatives($terms, $case_sensitive, $acento_sensitive)
{
	require 'blablabra_config.php';
	$occurrences = 100;
	$max_suggestions = 10;
	
	function Search($query){
		// Internal function, used to query the terms table.
		$arr = array();
		$results = mysql_query($query);
		if (mysql_num_rows($results) > 0) 
			while ($result = mysql_fetch_assoc($results))
				$arr[$result['term_ID']] = $result['term'];
		return $arr;
	}
	
	$prefix = 'SELECT term, term_type, min(term_ID) AS term_ID FROM terms WHERE term ';
	$suffix = 'COLLATE utf8_general_ci GROUP BY term, term_type ORDER BY term_type, term_ID;';
	$alt = array();
	$starttime = time();
	foreach($terms as $term_ID => $t){
		$howmany = mysql_fetch_assoc(mysql_query('SELECT COUNT(*) AS terms FROM term_occurrences WHERE term_ID = '.$term_ID.';'));
		if ($howmany['terms'] > $occurrences) $occurrences = $howmany['terms'];
		
		if (mb_substr($t,0,1,'UTF-8') == '@' || mb_substr($t,0,1,'UTF-8') == '#'){
			// Adds a @ or #, no accents
			$alt = $alt + Search($prefix.'LIKE "'.RemoveAcentos($t).'" COLLATE utf8_general_ci OR term LIKE "'.mb_substr($t,1,mb_strlen($t),'UTF-8').'" '.$suffix);
			// Removes @ and # to keep searching
			$t = mb_substr($t,1,mb_strlen($t),'UTF-8');
		}
		
		// Searches without accents.
		$alt = $alt + Search($prefix.'LIKE "'.RemoveAcentos($t).'" COLLATE utf8_general_ci OR term LIKE "'.$t.'%" COLLATE utf8_general_ci OR term LIKE "'.RemoveAcentos($t).'%" '.$suffix);
	}
	brecho('time - '.(time()-$starttime));
	
	// Prepares the return variable
	$message = '';
		
	$howmany = mysql_query('SELECT term_ID, COUNT(*) AS amount FROM term_occurrences WHERE time_of_occurrence >= SUBDATE(NOW(), INTERVAL 7 DAY) AND term_ID in ('.implode(',',array_keys($alt)).') GROUP BY term_ID ORDER BY amount DESC LIMIT 0,'.$max_suggestions.';');
	while($item = mysql_fetch_assoc($howmany)){
		if ($item['amount'] > $occurrences){
			if ($message != '') $message .= ', ';
			$message .= '<a href="'.$SITE_URL.'?s='.urlencode($alt[$item['term_ID']]).'">'.$alt[$item['term_ID']].'</a>';
		}
	}
	
	brecho('time - '.(time()-$starttime));
	return $message;
}
// ====================================================================================================================================
// SPECIAL TRENDING VALUE
// Returns the hourly average of mentions of a term, for the last two weeks. This is used for very common terms (like Twitter, or Brazil).
// This is used as an historical average so that GenerateTrendingTables can determine if the word is, in fact, 'trending' (by 'breaking' the average plateau)
function SpecialTrendingValue($term_ID)
{
	require 'blablabra_config.php';
	$week = 1;
	$historic_average = 0;
	$count = 1;
	while($week < 3){
		
		$now = time()-(60*60*24*7*$week);
		$a = date('Y-m-d H:i:s',$now-(60*60));
		$b = date('Y-m-d H:i:s',$now);
		
		$query = 'SELECT count(*) AS hits
					FROM term_occurrences
					WHERE term_ID = '.$term_ID.'
					AND time_of_occurrence BETWEEN \''.$a.'\' AND \''.$b.'\';';
		$history_result = mysql_fetch_assoc(mysql_query($query));
		if ($history_result['hits'] != 0){
			$historic_average += $history_result['hits'];
			$count++;
		}
		$week++;
	}
	$historic_average = (int)$historic_average / $count;
	return (int)$historic_average;
}
// ====================================================================================================================================
// PAGE TITLE
// Retrieves the title of a URL. Used to display "trending links".
function page_title($url) {
	$fp = file_get_contents($url);
	if (!$fp){
		return "";
	}

	$res = preg_match("{<title>(.*)<\/title>}ims", $fp, $title_matches);
	if (!$res) $res = preg_match("{<title>(.*)<\/title>}im", $fp, $title_matches); // This is a weird bug where Youtube URLs are not retrieved when the "s" modifier (DOTALL) is used.
	if (!$res){ 
		// Another weird bug where the regex fails if the title contains a vertical bar. ( "|" ). In that case, goes straight into the HTML and extracts the goddamn title.
		$start = stripos($fp,"<title>");
		$finish = stripos($fp,"</title>");
		if ($start === false || $finish === false){
			$res = false;
		} else {
			$title_matches[1] = substr($fp, $start+7,$finish-$start);
			$res = true;
		}
	}
	if (!$res){
		brecho('ERROR - This page has no <title> tag.');
		return ""; 
	}

	$title = $title_matches[1];
	$title = trim($title);
	$title = strip_tags($title);
	$title = preg_replace("/[\n\r]/","",$title);
	
	// Checks the title against a 'blacklist'.
	// Twitpic is included because EVERY twitpic has it, so I decided to show the URL instead.
	$bad_titles = array('Untitled Document', 
						'403 Forbidden', 
						'404 Not Found', 
						'502 Proxy Error', 
						'Twitpic - Share photos on Twitter', 
						'Wikimedia Error', 
						'orkut -', 
						'cPanel');
	foreach($bad_titles as $bad_title){
		if (strpos($title, $bad_title) !== false){
			return "";
		}
	}
	if (mb_detect_encoding($title) != 'UTF-8') $title = utf8_encode($title);
	if (!mb_check_encoding($title,'UTF-8'))	$title = utf8_encode($title);
	
	return $title;
}
// ====================================================================================================================================
// REMOVE REPEATING LETTERS
// Converts strings like "GOOOOOOOOOOOOOOOOAL" into "GOAL".
function RemoveRepeatingLetters($str, $repetitions = 4)
{
	$i = 0;
	while ($i < mb_strlen($str,'UTF-8')){
		$j = $i;
		// Traverses the string comparing the first character with the others.
		while(mb_substr($str,$j,1,'UTF-8') == mb_substr($str,$j+1,1,'UTF-8'))
			$j++;
		// Removes the repetition if needed
		if ($j >= $i+$repetitions)
			$str = mb_substr($str,0,$i,'UTF-8') . mb_substr($str,$j,mb_strlen($str)-$j,'UTF-8');		
		$i++;
	}
	return $str;
}
// ====================================================================================================================================
// ISTHISPAM
// Disregards spammy tweets.
function IsThisSpam($inputtext, $isURL = FALSE){

	if ($isURL) {
		// Uses the url_blacklist table for URLs.
		$badURLarray = array();
		$results = mysql_query("SELECT url FROM url_blacklist;");
		if (mysql_num_rows($results) > 0) 
			while ($result = mysql_fetch_assoc($results))
				$badURLarray[] = $result['url'];
		// Does the comparison against the blacklist.
		foreach($badURLarray as $spamtext){
			if (mb_stristr($inputtext,$spamtext,false,'UTF-8') !== FALSE)
				return true;
		}
	} else {
		// This array stores the spammy users and terms.
		$spamtextarray = array(
		"@QueroFollowers",
		"#aumentar seus #seguidores",
		"@pri_ilhabela",
		"@soll_ilhabela",
		"@talita_ilhabela",
		"@MuitosFollowers",
		"@BrasilFollowers",
		"@MC_TMX",
		"@flavia_ilhabela",
		"@centralfollower",
		"@HOTfollowers",
		"@HYPEfollowers",
		"@GOfollowers",
		"@FollowU2",
		"@aumentefollow",
		"@sigodevolta",
		"@meusfollowers",
		"@lcsloys",
		"faz tudo sozinho, 150 #followers por hora",
		"mais usar, mais #followers",
		"#txatatxa",
		"o TwitterFollowers",
		"Twitter Followers",
		"no #flwtwtet",
		"#maisfollowers"
		);
		
		// Compares the tweet against the spamtexts
		foreach($spamtextarray as $spamtext){
			if (mb_stristr($inputtext,$spamtext,false,'UTF-8') !== FALSE)
				return true;
		}
	}
	
	// If it got this far, it's not spam.
	return false;
}
// ====================================================================================================================================
// LOG THIS
// Writes into 'syslog' - the circular system log. Data is preserved up to a week.
function LogThis($id, $text, $critical = false)
{
	require 'blablabra_config.php';
	$db = mysql_connect($DB_SERVER, $DB_USER, $DB_PASSWORD);
	if (!$db) {
		die('AAARGH, can''t connect to the database - ' . mysql_error());
	}
	mysql_select_db($DB_DATABASE) or die ("AAARGH, nao consegui selecionar a database dos tweets.");

	mysql_query('BEGIN WORK;');
	mysql_query('LOCK TABLES syslog WRITE;');
	
	$ok = mysql_query('INSERT INTO syslog (script_id, event) VALUES ("'.$id.'","'.mb_substr($text,0,399,'UTF-8').'");');
	if (!$ok) echo "CAN'T LOG THIS - ".mb_substr($text,0,399,'UTF-8')." - ".mysql_error()."</br>";
	
	$ok = mysql_query('DELETE FROM syslog WHERE instant < SUBDATE(NOW(), INTERVAL 7 DAY);');
	if (!$ok) echo "CAN'T LOG THIS - ".mb_substr($text,0,399,'UTF-8')." - ".mysql_error()."</br>";
	
	mysql_query('UNLOCK TABLES;');
	mysql_query('COMMIT WORK;');
	
	if ($critical) die;
}
// ====================================================================================================================================
// BR ECHO
// A debug helper that appends a <br> to echo().
function brecho($in)
{
	if (is_array($in)) print_r($in);
	else echo $in;
	echo "<br/>\n";
}
?>