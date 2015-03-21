<?php
	echo '<div class="box-top"></div><div class="box">
		<p><a target="_blank" href="http://twitter.com/blablabra"><img id="sidebar-ico" src="blablabra-ico.png">Siga o @blablabra no Twitter</a> e fique por dentro de tudo que acontece por aqui</p>
		</div><div class="box-bottom"></div>'."\n\t\t";
	
	// Proper names (if not found, skips them and goes straight to common terms)
	$trending_result = mysql_query('SELECT term_ID, term FROM `trending_now` where term_type <=3 order by hits desc limit 0,15;');
	if (mysql_num_rows($trending_result) > 0){
		echo '<div class="box-top"></div><div class="box">'."\n\t\t";
		echo '<h2 class="first">Assuntos populares neste momento<br/><small>clique para detalhes</small></h2>'."\n\t\t";
		echo '<ul>'."\n\t\t";
		while($trending_topic = mysql_fetch_assoc($trending_result)){
			echo '<li><a href="'.$SITE_URL.'index.php?s='.urlencode($trending_topic['term']).'">'.$trending_topic['term'].'</a></li>'."\n\t\t";
			$trending_list[] = $trending_topic['term'];
		}
		echo '</ul>'."\n\t\t";
		echo '<p class="tweetthis"><a target="_blank" href="http://twitter.com/home?status=Assuntos do momento no Twitter: '.FriendlyTopics(array_values($trending_list), 70, true).' - Veja mais em http://blablabra.net">Compartilhe isto no Twitter!</a></p>'."\n\t\t";
		echo '</div><div class="box-bottom"></div>'."\n\t\t";
		
		// TwitterBrasil links (partnership agreement)
		$ctx = stream_context_create(array('http' => array('timeout' => 1)));
		$feedcontent = file_get_contents("http://www.twitterbrasil.org/feed/", 0, $ctx); 
		if ($feedcontent){
			echo '<div class="box-top"></div><div class="box">'."\n\t\t";
			echo '<h2 class="first">Últimas notícias sobre o Twitter</h2>'."\n\t\t";
			echo '<ul>'."\n\t\t";
			$feed = new SimpleXmlElement($feedcontent);
			$i = 1;
			foreach($feed->channel->item as $entry){
				echo "<li><a target=\"_blank\" href='$entry->link' title='$entry->title'>" . $entry->title . "</a></li>\n\t\t";  
				if ($i == 5) break;
				else $i++;
			}
			echo '</ul>'."\n\t\t";
			echo '<p class="tweetthis"><a target="_blank" href="http://www.twitterbrasil.org/"><div style="float:right;"><img src="tbrasil.png"></div><div style="float:right; margin: 4px 4px 0 0;">Veja mais no</div></a></p>'."\n\t\t";
			echo '</div><div class="box-bottom"></div>'."\n\t\t";
		}
		
		// Users - retweeted
		$trending_result = mysql_query('SELECT term_ID, term FROM `trending_now` where term_type = 5 order by hits desc limit 0,10;');
		if (mysql_num_rows($trending_result) > 0){
			echo '<div class="box-top"></div><div class="box">'."\n\t\t";
			echo '<h2 class="first">Usuários mais retwitados<br/></h2>'."\n\t\t";
			echo '<ul>'."\n\t\t";
			while($trending_topic = mysql_fetch_assoc($trending_result)){
				echo '<li><a href="'.$SITE_URL.'index.php?s='.urlencode($trending_topic['term']).'">'.$trending_topic['term'].'</a></li>'."\n\t\t";
				$trending_rt_list[] = $trending_topic['term'];
			}
			echo '</ul>'."\n\t\t";
			echo '<p class="tweetthis"><a target="_blank" href="http://twitter.com/home?status=Usuários mais retuitados na última hora: '.FriendlyTopics(array_values($trending_rt_list), 61, true).' - Veja mais em http://blablabra.net">Compartilhe isto no Twitter!</a></p>'."\n\t\t";
			echo '</div><div class="box-bottom"></div>'."\n\t\t";
		}
		
		// Users - replied to
		$trending_result = mysql_query('SELECT term_ID, term FROM `trending_now` where term_type = 7 order by hits desc limit 0,10;');
		if (mysql_num_rows($trending_result) > 0){
			echo '<div class="box-top"></div><div class="box">'."\n\t\t";
			echo '<h2 class="first">Usuários mais respondidos</h2>'."\n\t\t";
			echo '<ul>'."\n\t\t";
			while($trending_topic = mysql_fetch_assoc($trending_result)){
				echo '<li><a href="'.$SITE_URL.'index.php?s='.urlencode($trending_topic['term']).'">'.$trending_topic['term'].'</a></li>'."\n\t\t";
				$trending_user_list[] = $trending_topic['term'];
			}
			echo '</ul>'."\n\t\t";
			echo '<p class="tweetthis"><a target="_blank" href="http://twitter.com/home?status=Usuários mais respondidos na última hora: '.FriendlyTopics(array_values($trending_user_list), 61, true).' - Veja mais em http://blablabra.net">Compartilhe isto no Twitter!</a></p>'."\n\t\t";
			echo '</div><div class="box-bottom"></div>'."\n\t\t";
		}
		
		// TOP tweeting users
		$trending_result = mysql_query('SELECT * FROM trending_top_users WHERE user_screen_name <> "TOTAL ACTIVE USERS" AND total_tweets >= 10 ORDER BY total_tweets DESC LIMIT 0,10;');
		if (mysql_num_rows($trending_result) > 0){
			echo '<div class="box-top"></div><div class="box">'."\n\t\t";
			echo '<h2 class="first">Usuários que mais twitaram na última hora</h2>'."\n\t\t";
			echo '<ul>'."\n\t\t";
			while($trending_topic = mysql_fetch_assoc($trending_result)){
				echo '<li><a target="_blank" href="http://twitter.com/'.$trending_topic['user_screen_name'].'">@'.$trending_topic['user_screen_name'].'</a></li>'."\n\t\t";
				$trending_toptalkers_list[] = '@'.$trending_topic['user_screen_name'];
			}
			echo '</ul>'."\n\t\t";
			echo '<p class="tweetthis"><a target="_blank" href="http://twitter.com/home?status=Usuários que mais twitaram na última hora: '.FriendlyTopics(array_values($trending_toptalkers_list), 60, true).' - Veja mais em http://blablabra.net">Compartilhe isto no Twitter!</a></p>'."\n\t\t";
			echo '</div><div class="box-bottom"></div>'."\n\t\t";
		}

		$caption = '<h2 class="first">Outras palavras bastante usadas</h2>'."\n\t\t";
	} else $caption = '<h2 class="first">Assuntos populares neste momento<br/><small>clique para detalhes</small></h2>'."\n\t\t";
	
	// Other terms
	echo '<div class="box-top"></div><div class="box">'."\n\t\t";
	echo $caption;
	$trending_result = mysql_query('SELECT term_ID, term FROM `trending_now` where term_type = 4 order by hits desc limit 0,10;');
	if (mysql_num_rows($trending_result) > 0){
		echo '<ul>'."\n\t\t";
		while($trending_topic = mysql_fetch_assoc($trending_result)){
			echo '<li><a href="'.$SITE_URL.'index.php?s='.urlencode($trending_topic['term']).'">'.$trending_topic['term'].'</a></li>'."\n\t\t";
		}
		echo '</ul>'."\n\t\t";
	} else echo 'Nada no momento.'."\n\t\t";
	echo '</div><div class="box-bottom"></div>'."\n\t\t";
	echo '<div class="box-top"></div><div class="box">'."\n\t\t";
	echo '<h2 class="first">Movimentação dos brasileiros no Twitter</h2>'."\n\t\t";
	$sysinfo = mysql_fetch_assoc(mysql_query('SELECT UNIX_TIMESTAMP(last_trending) AS last_trending, twitter_speed FROM sysinfo;'));
	echo '<p><b>'.number_format($sysinfo['twitter_speed'],1,',','.').'</b> tweets por segundo.</p>'."\n\t\t";
	$trending_users = mysql_fetch_assoc(mysql_query('SELECT total_tweets FROM trending_top_users WHERE user_screen_name = "TOTAL ACTIVE USERS";'));
	echo '<p><b>'.number_format($trending_users['total_tweets'],0,',','.').'</b> usuários twittaram na última hora.</p>'."\n\t\t";
	echo '</div><div class="box-bottom"></div>'."\n\t\t";
	echo '<p class="footnote"><small>Dados dos últimos 60 minutos, recalculados a cada 5 minutos.<br/>Última atualização: '.date('G:i', $sysinfo['last_trending']+($TIME_OFFSET*60*60)).'</small></p>'."\n\t\t";
	echo '<p class="footnote"><small><a href="'.$SITE_URL.'?faq">Dúvidas? Leia o FAQ.</a></small></p>';
?>