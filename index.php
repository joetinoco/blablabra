<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd" >
<html>
<?php 
	require 'blablabra_config.php';
	require 'blablabra_functions.php';
	
	$starttime = microtime(true);
	
	require_once '_preparations.php'; // Sets query variables, determines which page to show, etc.
	
	// Checks the front page cache
	if ($showpage == 0){
		$cachefile = "cache/index.html";
		$cachetime = 1 * 60;
		// Uses the cache if it is more recent than $cachetime (and the page is not being served locally)
		if($SITE_URL != 'http://127.0.0.1/blablabra/')
			if (file_exists($cachefile) && (time() - $cachetime < filemtime($cachefile))) 
			{
				include($cachefile); // Displays from the cache
				echo "<!-- cached file, generated in ".date('Y-m-d G:i:s', filemtime($cachefile))." -->\n";
				exit;
			}
		// Else, starts buffering the output to use as cache.
		ob_start();
	}
?>
<title>blablabra - <?php echo $titlecaption; ?></title>
<head>
	<meta http-equiv="content-type" content="text-html; charset=utf-8">
	<link rel="stylesheet" href="style.css" type="text/css" media="screen" />
	<link rel="shortcut icon" href="<?php echo $SITE_URL; ?>favicon.ico" type="image/x-icon">
	<meta name="robots" content="index,follow">
	<meta name="description" content="Um timeline do Twitter em portugues" />
	<meta name="keywords" content="twitter,timeline,portugues,brasil,tweet,trending,topics,trending topics,brazil" />
	<meta name="author" content="Jose Carlos Tinoco" />
</head>
<body>
	<div id="container"> <? // This div is closed inside _footer.php ?>		
		<div id="header">
			<div id="header_img">
				<a href="<?php echo $SITE_URL; ?>"><img src="blablabra.png" alt="blablabra"></a>
			</div>
		</div> <!-- Header -->
		
		<div id="search">
			<div id="search_form">
			<?php if (!$SERIOUSLY_BROKEN && !$MAINTENANCE_MODE && !$FRONTPAGE_ONLY_MODE) { ?>
				<h1>O que você está procurando?</h1>
				<form name="procura" method="get" action="<?php echo $SITE_URL; ?>index.php">
					<div id="txt_holder"><input type="text" name="s" class="txt_search" value="Palavra, @usuário ou #hashtag" size=30 maxlength=100 onclick="this.value = ''"></div>
					<!--<input type="image" src="btn-pesquisar.png" value="Procurar">-->
					<input class="btn_submit" type="submit" value="">
					<br/><small><b>Dica: Para <em>comparar palavras</em>, digite-as separadas por vírgula.</b></small>
				</form>
			<?php } ?>
			</div>
		</div>
		
		<div id="middle">
			<div id="main">
				<?php 
				if ($SERIOUSLY_BROKEN) { 
					echo '<div id="content"><h2>O Blablabra quebrou uma perna :(</h2>';
					echo '<p>Já estão correndo com ele pro pronto-socorro.</p>';
					echo '<p><small>P.s: Tem backup de tudo, não esquenta.</small></p></div>';
				} else if ($MAINTENANCE_MODE) { 
					echo '<div id="content"><h2>O Blablabra está trocando a embreagem</h2>';
					echo '<p>Como é uma manutenção mais complicada, tem que parar o site. Volte daqui a uma ou duas horas e ele já deve estar rodando de novo.</p></div>';
				} else {				
					if($NOTICE_MSG != '')
						echo '<div id="notice_msg"><p>'.$NOTICE_MSG.'</p></div>';
					switch ($showpage){
						case 0:
							require '_main.php'; break;// Default front page
						case 1:
						case 2:
							require '_stats.php'; break;// Stats page
						case 3:
							require '_faq.php'; break;// FAQ
					}
				}
				
				require '_footer.php';
				?>
			</div>
		</div>
		
		<div id="footer-image">
			<a href="<?php echo $SITE_URL; ?>"><img src="blablabra-mini.png" alt="blablabra"></a>
		</div>
	</div> <!-- Container -->

<?php if ($SITE_URL != 'http://127.0.0.1/blablabra/'){ ?>
	<script type="text/javascript">
	var gaJsHost = (("https:" == document.location.protocol) ? "https://ssl." : "http://www.");
	document.write(unescape("%3Cscript src='" + gaJsHost + "google-analytics.com/ga.js' type='text/javascript'%3E%3C/script%3E"));
	</script>
	<script type="text/javascript">
	try {
	var pageTracker = _gat._getTracker("UA-2008275-3");
	pageTracker._trackPageview();
	} catch(err) {}</script>
<?php } 
echo '<!-- Page generated in '.number_format(microtime(true) - $starttime, 4).' seconds. -->'."\n";  ?>
</body>
</html>
<?php
	// If we got this far, the page was generated from scratch and should be cached.
	if ($showpage == 0){
		// Creates the cache file
		$fp = fopen($cachefile, 'w'); 
		fwrite($fp, ob_get_contents());
		fclose($fp); 
		// Flushes the content to the browser
		ob_end_flush();
	}
?>