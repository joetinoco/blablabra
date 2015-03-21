<div id="sidebar">
	<?php require '_sidebar.php'; ?>
</div>
<div id="content">
	<?php
	// Displays an image with the top 3 trending topics, then some current tweets
	echo '<h2 class="first">Top 3</h2>'."\n";
	echo '<img class="graph_top" src="'.GoogleGraph('TRENDING','','10min',12,'',510,150).'">'."\n";
?>
</div>	
<?php
	// Displays top links
	$showed_title = false;
	$i = 0;
	$iteration_data = array( 0 => array('query' => 'SELECT is_https, url, title FROM trending_urls WHERE timeframe = 1 ORDER BY hits DESC LIMIT 0,10;',
										'title' => "<h3>Na última hora:</h3>\n"),
							 1 => array('query' => 'SELECT is_https, url, title FROM trending_urls WHERE timeframe = 24 AND url NOT IN (SELECT url FROM trending_urls WHERE timeframe = 1) ORDER BY hits DESC LIMIT 0,10;',
										'title' => "<h3>Nas últimas 24 horas:</h3>\n"));
	$div = 'toplinkbox';
	do{
		$toplinks = mysql_query($iteration_data[$i]['query']);
		if (mysql_num_rows($toplinks) > 0){
			if (!$showed_title){
				echo '<div class="bigboxtop"></div><div class="bigbox"><h2 class="first">Links mais comentados</h2>'."\n";
				$showed_title = true;
				if ($i == 1) $div = 'toplinkbox-wide';
			}
			if (mysql_num_rows($toplinks) < 5 && $i == 0) $div = 'toplinkbox-wide';
			echo '<div class="'.$div.'">';
			echo $iteration_data[$i]['title'];
			echo '<ul>'."\n";
			while($link = mysql_fetch_assoc($toplinks)){
				if ($link['is_https'] == 1) $protocol = "https://"; else $protocol = "http://";
				$title = $link['title'];
				if ($title == '') $title = $link['url'];
				if (strlen($title) > 100) $title = mb_substr($title,0,100,'UTF-8')."...";
				echo '<li><a target="_blank" href="'.$protocol.$link['url'].'">'.$title.'</a></li>'."\n";
			}			
			echo "</ul>\n</div>\n";
		}
		$i++;
	}while($i<2);
	if ($showed_title) echo "</div><div class=\"bigboxbottom\"></div>\n";
?>
<div id="content">
<?php
	// Displays last tweets written in portuguese
	echo '<h2>Últimos tweets em português</h2>'."\n";
	if (isset($_GET['page'])) $page = (int)mysql_real_escape_string($_GET['page']);
	$page_size = 20;
	if (isset($page))
		$reg_start = ($page-1)*$page_size;
	else 
		$reg_start = 0;
	$query = 'SELECT user_profile_image_url, user_screen_name, tweet, created_at, source, id '.
			 'FROM status ORDER BY id DESC LIMIT '.$reg_start.','.$page_size.';';
	$result = mysql_query($query) or die ("Paginação estranha essa sua, hein?");

	if (mysql_num_rows($result) > 0) {
		while($row = mysql_fetch_assoc($result)) {
			PrintTweet($row);
		}
	}
	?>
	<div id="pagination">
		<p>
		<?php	
		if (isset($page)){
			if ($page == 2)
				echo '<a href="'.$SITE_URL.'">&lt;&lt; Mais recentes</a>';
			else 
				echo '<a href="'.$SITE_URL.'index.php?page='.($page-1).'">&lt;&lt; Mais recentes</a>';
			if (mysql_num_rows($result) == $page_size)	// ...whenever this returns false, the last page was reached.
				echo ' - <a href="'.$SITE_URL.'index.php?page='.($page+1).'">Mais antigos &gt;&gt;</a>';
		} else echo '<a href="'.$SITE_URL.'index.php?page=2">Mais antigos &gt;&gt;</a>';
		?>
		</p>
	</div>
</div>