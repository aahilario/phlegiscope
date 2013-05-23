<?php

/*
 * system/configuration.php
 * Legiscope crawler
 */

define('SITE_URL'   , 'http://ra1017x.avahilario.net');
define('SYSTEM_BASE', './system');
define('CACHE_PATH', './cache');

// Settings for CurlUtility
define('LEGISCOPE_USER_AGENT', $_SERVER['HTTP_USER_AGENT']);
define('LEGISCOPE_CURLOPT_CONNECTTIMEOUT', 60);
define('LEGISCOPE_CURLOPT_TIMEOUT', 120);
define('LEGISCOPE_CURLOPT_PROXY', '127.0.0.1');
define('LEGISCOPE_CURLOPT_PROXYPORT', 9050);
define('LEGISCOPE_CURLOPT_PROXYTYPE',  CURLPROXY_SOCKS5);

// Debug logging
define('DEBUG_DatabaseUtility', FALSE);
define('DEBUG_LegiscopeBase', FALSE);
define('DEBUG_CongressGovPh', FALSE);
define('DEBUG_RawparseUtility', FALSE);
define('DEBUG_MysqlDatabasePlugin', FALSE);
define('DEBUG_UrlModel', FALSE);
define('DEBUG_ALL', FALSE);
define('SLOW_DOWN_RECURSIVE_DUMP',20000);
define('DISABLE_AUTOMATIC_URL_EDGES',TRUE);

define('SELENIUM_WEBDRIVER', 'http://127.0.0.8:4444/wd/hub');

// Debug flags
define('ENABLE_STRUCTURE_DUMP', FALSE);
define('DEBUG_STACKTRACE_ON_SQL_ERROR', TRUE);

// Session handling
define('LEGISCOPE_SESSION_NAME', 'LEGISCOPE');

// Flow control
define('DISPLAY_ORIGINAL', TRUE);
define('LEGISCOPE_SENATE_DOC_SN_UBOUND', 10700);

// Caching
define('CONTENT_SIZE_THRESHOLD', 65535);
define('ENABLE_GENERATED_CONTENT_BUFFERING', FALSE);
define('ENABLE_NEW_FETCHED_CONTENT_CACHING', TRUE);
define('DISPLAY_EXISTING_REPUBLIC_ACTS', TRUE);

// Database configuration
define('DBTYPE', 'Mysql');
define('DBHOST', '127.0.0.1');
define('DBUSER', 'root');
define('DBPASS', 'suvorov');
define('DBNAME', 'legiscope');

// Model handling
define('LS_SYNCHRONIZE_MODEL_STRUCTURE', TRUE);
