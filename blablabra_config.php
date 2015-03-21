<?php
	// Global variables and configurations
	// (I know global variables are a big no-no, but I wanted to ship soon and the production environment was pretty much secure/isolated)
	
	if (substr(__FILE__,0,10) == '<local file path>'){
		$DB_SERVER = 'localhost'; // Local test environment
		$SITE_URL = 'http://127.0.0.1/blablabra/';
	}
	else { // Production settings
		$DB_SERVER = '<insert database server here>';
		$SITE_URL = '<insert production URL here - used to be blablabra.net';
		//error_reporting(0);
		error_reporting(E_ALL);
	}
		
	$DB_USER = '<insert DB username here>';
	$DB_PASSWORD = '<insert DB password here>';
	$DB_DATABASE = '<insert DB name here>';
	
	// Time zone difference between the servers (zero if it is the local test server)
	//if ($SITE_URL == 'http://blablabra.net/') $TIME_OFFSET = 2;
	//else $TIME_OFFSET = 0;
	$TIME_OFFSET = 0;
	
	// Calculates the time zone difference between GMT and server time. Used to store tweets timestamped with the correct time.
	$t = date('O',time());
	$GMT_OFFSET = (int)substr($t,0,strlen($t)-2);
	
	$SERIOUSLY_BROKEN = false; // Puts the site in "fail whale" mode.
	$MAINTENANCE_MODE = false; // Displays a maintenance message.
	$FRONTPAGE_ONLY_MODE = false; // Only the front page works. Search is disabled.
	
	// Notice message, shown on the front page. Used for warning messages for scheduled maintenance, for example.
	$NOTICE_MSG = '';
	
	// CRON Jobs for all the server-side tweet processing (true = active)
	$CRON_SEARCH_SCANNER = true;
	$CRON_TRENDING_TOPICS_ANALYZER = true;
	$CRON_GENERATE_TRENDING_TABLES = true;
	$CRON_BATCH_CHECKER = true;
	$CRON_DB_PURGE = true;
	$CRON_DB_CHECK = true;
	$CRON_URL_DECODER = true;
?>