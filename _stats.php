<div id="sidebar">
	<?php require '_sidebar.php';	?>
</div>
<div id="content">
	<?php 
	// Error message for invalid queries or an invalid term_ID in the URL
	if (!$found){ 
		echo '<p class="errormessage"><b>Oops</b>, não encontrei alguma das coisas que você digitou.</p>';
		if (count($terms) > 0) $term = implode(', ',array_values($terms));
		else {
			if (isset($_GET['str_term'])) $term = mysql_real_escape_string($_GET['str_term']);
			else $term = mysql_real_escape_string($_GET['s']);
			$terms[0] = $term;
		}
	} else if ($showpage == 1) { 
	// ============================== Simple stats (for one term) ==============================
	?>
	<h1>Estatísticas da palavra "<em><?php $aux = array_values($terms); reset($aux); echo $aux[0]; ?></em>"</h1>
	<p class="tweetthis"><a target="_blank" href="http://twitter.com/home?status=Estatísticas sobre '<?php echo FriendlyTopics(array_unique(array_values($terms)),140,true); ?>' no Twitter - <?php echo $SITE_URL.'index.php?s='.str_replace('#','%23',urlencode(implode(',',array_unique(array_values($terms))))); ?>">Compartilhe isto no Twitter!</a></p>
	<?php
		echo '<div class="graph_top">'."\n\t\t\t\t";
		echo '<img src="'.GoogleGraph($terms,$term_groupings,'10min',12).'">'."\n\t\t\t";
		echo '</div>'."\n\t\t\t";
		echo '<!-- Chart 1 generated in '.number_format(microtime(true) - $starttime, 4).' seconds. -->'."\n\t\t\t";
		
		echo '<!-- Stats shown in '.number_format(microtime(true) - $starttime, 4).' seconds. -->'."\n\t\t\t";
		
		echo '<div class="graph">'."\n\t\t\t\t";
		echo '<img src="'.GoogleGraph($terms,$term_groupings,'1hour',24).'">'."\n\t\t\t"; 
		echo '</div>'."\n\t\t\t";
		echo '<!-- Chart 2 generated in '.number_format(microtime(true) - $starttime, 4).' seconds. -->'."\n\t\t\t";
		
		if (count($stat_notes) > 0){
			echo '<p><small><b>Obs.:</b> ';
			foreach($stat_notes as $note) echo $note.'; ';
			echo '</small></p>'."\n\t\t\t";
		} 
	 } else { 
	// ============================== Term comparisons ==============================
	?>
		<h1>Comparando: <em><?php echo FriendlyTopics(array_unique(array_values($terms))); ?></em></h1>
		<p class="tweetthis"><a target="_blank" href="http://twitter.com/home?status=Veja um comparativo entre <?php echo FriendlyTopics(array_unique(array_values($terms)),140,true); ?> no Twitter - <?php echo $SITE_URL.'index.php?s='.str_replace('#','%23',urlencode(implode(',',array_unique(array_values($terms))))); ?>">Compartilhe isto no Twitter!</a></p>
		<div class="graph"><?php echo '<img src="'.GoogleGraph($terms,$term_groupings,'1hour',12).'">'; ?></div>

		<?php
		PrintStats($terms, $term_groupings);
		if (count($stat_notes) > 0){
			echo '<p><small><b>Obs.:</b> ';
			foreach($stat_notes as $note) echo $note.'; ';
			echo '</small></p>';
		} 
		// stores terms in $term for the remaining script parts (search.twitter.com queries, etc)
		$term = implode(' OR ',array_unique(array_values($terms)));
	} 
	$baseurl = 'http://search.twitter.com/search';
	$searchparameters = '?lang=pt&geocode=-10.183056%2C-48.333611%2C2500km&rpp=10&q='.urlencode($term);
	
	// Displays a couple tweets with the search term
	if ($found){ 
		$display_pagination = false;
		$query = 'SELECT st.user_profile_image_url AS user_profile_image_url,
						 st.user_screen_name AS user_screen_name,
						 st.tweet AS tweet,
						 st.created_at AS created_at,
						 st.source AS source,
						 st.id AS id
				 FROM status AS st, '.CheckTableCache(array_keys($terms)).' AS occ
				 WHERE st.id = occ.id
				 AND occ.time_of_occurrence > SUBDATE(NOW(), INTERVAL 24 HOUR)
				 AND occ.term_ID IN ('.implode(',',array_keys($terms)).')
				 ORDER BY st.id DESC LIMIT 0,10;';
		$result = mysql_query($query);
		if (mysql_num_rows($result) > 0) {
			$display_pagination = true;
			$aux_caption = FriendlyTopics(array_unique(array_values($terms)));
			if (count(array_unique(array_values($terms))) > 1)
				$aux_caption = substr_replace($aux_caption, ' ou ', strrpos($aux_caption,' e '), 3);
			echo '<h2>Tweets recentes contendo <em>'.$aux_caption.'</em></h2>';
			while($row = mysql_fetch_assoc($result)) {
				PrintTweet($row,array_unique(array_values($terms)));
			}
		} else $found = false;
	} 
	
	if (!$found) {
		// Uses search.twitter.com whenever terms are not found (can't be seen in the 'status' table or haven't occurred in the last hour)
		$display_pagination = false;
		$searchformat = '.json';
		$url = $baseurl . $searchformat . $searchparameters;
		$search_api_result = file_get_contents($url);
		$tweets = json_decode($search_api_result, true);
		if (count($tweets['results']) > 0){
			$display_pagination = true;
			$aux_caption = FriendlyTopics(array_unique(array_values($terms)));
			if (count(array_unique(array_values($terms))) > 1)
				$aux_caption = substr_replace($aux_caption, ' ou ', strrpos($aux_caption,' e '), 3);
			echo '<h2>Tweets recentes contendo <em>'.$aux_caption.'</em></h2>';
			foreach($tweets['results'] as $search_result){
				PrintTweet($search_result, array_unique(array_values($terms)));
			}
		}
	}
	?>
	<div id="pagination">
		<p><?php 
		if ($display_pagination) 
			echo '<a href="'.$baseurl.$searchparameters.'&page=2">Continue vendo mais resultados em search.twitter.com &gt;&gt;&gt;</a>'; 
		?></p>
	</div>
</div>