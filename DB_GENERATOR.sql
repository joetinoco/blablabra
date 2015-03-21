-- BLABLABRA
-- DB generator script
-- MySQL version: 5.0.51
-- PHP version: 5.2.6-1+lenny9

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

--
-- DB should be called: `blablab_production`
--

-- --------------------------------------------------------

--
-- Table `batches`
--

CREATE TABLE IF NOT EXISTS `batches` (
  `batch_id` int(10) unsigned NOT NULL auto_increment,
  `created` timestamp NOT NULL default CURRENT_TIMESTAMP,
  `first_id` bigint(20) unsigned NOT NULL,
  `last_id` bigint(20) unsigned NOT NULL,
  `amount` int(10) unsigned NOT NULL,
  `taken` int(1) unsigned NOT NULL,
  PRIMARY KEY  (`batch_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=202677 ;

-- --------------------------------------------------------

--
-- Table `blacklist`
--

CREATE TABLE IF NOT EXISTS `blacklist` (
  `blacklist_term` varchar(50) NOT NULL,
  `hits` int(10) unsigned NOT NULL default '0',
  KEY `blacklist_term` (`blacklist_term`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table `cache_frontpage`
--

CREATE TABLE IF NOT EXISTS `cache_frontpage` (
  `id` bigint(20) unsigned default NULL,
  `term_ID` int(10) unsigned NOT NULL,
  `time_of_occurrence` timestamp NOT NULL default CURRENT_TIMESTAMP,
  UNIQUE KEY `id` (`id`,`term_ID`),
  KEY `term_ID` (`term_ID`,`time_of_occurrence`),
  KEY `id_2` (`id`),
  KEY `time_of_occurrence` (`time_of_occurrence`)
) ENGINE=MEMORY DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table `cache_frontpage_index`
--

CREATE TABLE IF NOT EXISTS `cache_frontpage_index` (
  `term_ID` int(10) unsigned NOT NULL,
  `updated_at` timestamp NOT NULL default CURRENT_TIMESTAMP
) ENGINE=MEMORY DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table `last_week`
--

CREATE TABLE IF NOT EXISTS `last_week` (
  `instant` timestamp NOT NULL default '0000-00-00 00:00:00',
  `metric` varchar(20) character set utf8 NOT NULL,
  `value` float unsigned NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table `special_terms`
--

CREATE TABLE IF NOT EXISTS `special_terms` (
  `term` varchar(140) NOT NULL,
  `divide_by` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table `status`
--

CREATE TABLE IF NOT EXISTS `status` (
  `id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NOT NULL default CURRENT_TIMESTAMP,
  `tweet` varchar(200) NOT NULL,
  `source` varchar(200) NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `user_name` varchar(100) NOT NULL,
  `user_screen_name` varchar(100) NOT NULL,
  `user_description` varchar(200) NOT NULL,
  `user_location` varchar(200) NOT NULL,
  `user_profile_image_url` varchar(200) NOT NULL,
  `user_url` varchar(200) NOT NULL,
  `lang` varchar(5) default NULL,
  `META_processed` tinyint(1) NOT NULL default '1',
  PRIMARY KEY  (`id`),
  KEY `META_processed` (`META_processed`),
  KEY `user_screen_name` (`user_screen_name`),
  KEY `created_at` (`created_at`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table `sysinfo`
--

CREATE TABLE IF NOT EXISTS `sysinfo` (
  `last_scanner` timestamp NULL default NULL,
  `last_analyzer` timestamp NULL default NULL,
  `last_trending` timestamp NOT NULL default CURRENT_TIMESTAMP,
  `last_search_ID` bigint(20) unsigned NOT NULL,
  `timeline_hits` int(10) unsigned NOT NULL,
  `search_scan_hits` int(11) NOT NULL,
  `twitter_speed` float NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table `syslog`
--

CREATE TABLE IF NOT EXISTS `syslog` (
  `event_id` bigint(20) unsigned NOT NULL auto_increment,
  `instant` timestamp NOT NULL default CURRENT_TIMESTAMP,
  `script_id` varchar(40) NOT NULL,
  `event` varchar(400) NOT NULL,
  PRIMARY KEY  (`event_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=8635565 ;

-- --------------------------------------------------------

--
-- Table `terms`
--

CREATE TABLE IF NOT EXISTS `terms` (
  `term_ID` int(10) unsigned NOT NULL auto_increment,
  `term` varchar(140) NOT NULL,
  `term_type` tinyint(3) unsigned NOT NULL default '1',
  PRIMARY KEY  (`term_ID`),
  KEY `term` (`term`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=18258105 ;

-- --------------------------------------------------------

--
-- Table `term_occurrences`
--

CREATE TABLE IF NOT EXISTS `term_occurrences` (
  `id` bigint(20) unsigned default NULL,
  `term_ID` int(10) unsigned NOT NULL,
  `time_of_occurrence` timestamp NOT NULL default CURRENT_TIMESTAMP,
  UNIQUE KEY `id` (`id`,`term_ID`),
  KEY `term_ID` (`term_ID`,`time_of_occurrence`),
  KEY `time_of_occurrence` (`time_of_occurrence`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table `term_types`
--

CREATE TABLE IF NOT EXISTS `term_types` (
  `term_type` tinyint(3) unsigned NOT NULL,
  `type` varchar(20) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table `trending_now`
--

CREATE TABLE IF NOT EXISTS `trending_now` (
  `term_ID` int(11) NOT NULL,
  `term` varchar(140) NOT NULL,
  `term_type` tinyint(3) unsigned NOT NULL default '1',
  `hits` bigint(21) NOT NULL default '0',
  KEY `term_type` (`term_type`,`hits`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table `trending_top_users`
--

CREATE TABLE IF NOT EXISTS `trending_top_users` (
  `user_screen_name` varchar(100) NOT NULL,
  `total_tweets` int(10) unsigned NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table `trending_urls`
--

CREATE TABLE IF NOT EXISTS `trending_urls` (
  `is_https` tinyint(1) NOT NULL,
  `url` varchar(2048) NOT NULL,
  `title` varchar(140) NOT NULL,
  `hits` int(10) unsigned NOT NULL,
  `timeframe` tinyint(3) unsigned NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table `twitter_movements`
--

CREATE TABLE IF NOT EXISTS `twitter_movements` (
  `instant` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  `metric` varchar(20) NOT NULL,
  `value` float unsigned NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table `url_blacklist`
--

CREATE TABLE IF NOT EXISTS `url_blacklist` (
  `url` varchar(2048) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table `url_occurrences`
--

CREATE TABLE IF NOT EXISTS `url_occurrences` (
  `id` bigint(20) NOT NULL,
  `url` varchar(2048) NOT NULL,
  `is_https` tinyint(1) NOT NULL,
  `time_of_occurrence` timestamp NOT NULL default CURRENT_TIMESTAMP,
  `decoded` tinyint(1) NOT NULL default '0',
  KEY `url` (`url`(255)),
  KEY `time_of_occurrence` (`time_of_occurrence`),
  KEY `decoded` (`decoded`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
