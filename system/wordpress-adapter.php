<?php

/*
 * system/wordpress-adapter.php
 * WordPress adapter methods and classes, and initialization 
 */

function legiscope_extend_include_path() {/*{{{*/

  $debug_method = TRUE;

  if ( defined('SYSTEM_BASE') ) return;

  $system_path = explode('/',__FILE__);
  array_pop($system_path);
  define('SYSTEM_BASE', join('/',$system_path));
  array_pop($system_path);
  define('LEGISCOPE_PLUGIN_NAME', array_pop($system_path));
  array_push($system_path, LEGISCOPE_PLUGIN_NAME);
  ini_set('include_path', join(':', array_filter(array_merge(explode(':', ini_get('include_path') . ':' . SYSTEM_BASE . ':' . SYSTEM_BASE . '/lib' ))))); 

  if ( $debug_method ) {
    syslog( LOG_INFO, "- - - - -    Base: " . SYSTEM_BASE);
    syslog( LOG_INFO, "- - - - - Include: " . ini_get('include_path'));
  }

  // Uncomment the next line to prevent clobbering
  // WordPress framework class autoloader.
  // define('DISABLE_CLASS_AUTOGENERATE', TRUE);
  define('MODE_WORDPRESS_PLUGIN' , TRUE);

  $sub_path = $system_path;
  array_push($sub_path, 'js');

  define('LEGISCOPE_JS_PATH'         , join('/', $sub_path));

  $sub_path = $system_path;
  array_push($sub_path, 'css');

  define('LEGISCOPE_CSS_PATH'        , join('/', $sub_path));

}/*}}}*/

legiscope_extend_include_path();

require_once('configuration.php');

