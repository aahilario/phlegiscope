<?php

/*
 * system/wordpress-adapter.php
 * WordPress adapter methods and classes, and initialization 
 */

function legiscope_extend_include_path() {
  $system_path = explode('/',__FILE__);
  array_pop($system_path);
  define('SYSTEM_BASE', join('/',$system_path));
  define('LEGISCOPE_PLUGIN_NAME', array_pop($system_path));
  ini_set('include_path', join(':', array_filter(array_merge(explode(':', ini_get('include_path') . ':' . SYSTEM_BASE . ':' . SYSTEM_BASE . '/lib' ))))); 
  syslog( LOG_INFO, "- - - - -    Base: " . SYSTEM_BASE);
  syslog( LOG_INFO, "- - - - - Include: " . ini_get('include_path'));

  define('DISABLE_CLASS_AUTOGENERATE', TRUE);
  define('MODE_WORDPRESS_PLUGIN'     , TRUE);

  array_push($system_path, 'js');
  define('LEGISCOPE_JS_PATH'         , join('/', $system_path));

}

legiscope_extend_include_path();

require_once('configuration.php');

