<?php

/*
 * system/base.php
 * Legiscope crawler
 */

// Enable logging
openlog( basename(__FILE__), /*LOG_PID |*/ LOG_NDELAY, LOG_USER ); 

// Setup __autoload paths

ini_set('include_path', join(':', array_filter(array_merge(explode(':', ini_get('include_path') . ':' . SYSTEM_BASE . ':' . SYSTEM_BASE . '/lib' ))))); 

function camelcase_to_array($classname) {
  $name_components = array(0 => NULL);
  $ucase_cname     = strtoupper($classname);
  $last_matchpos   = 0;
  // Fill array with camelcase name parts
  for ( $nameindex = 0 ; $nameindex < strlen($classname) ; $nameindex++ ) {
    if ( substr($classname,$nameindex,1) == substr($ucase_cname,$nameindex,1) ) {
      // syslog( LOG_INFO, "- {$last_matchpos} - {$name_components[$last_matchpos]}" );
      $last_matchpos++;
      $name_components[$last_matchpos] = '';
    }
    $name_components[$last_matchpos] .= strtolower(substr($classname,$nameindex,1));
  }
  return array_filter($name_components);
}

spl_autoload_register(function ($classname) {
  // Transform class names in the form AxxxByyyCzzz 
  // to class filename czzz.byyy.axxx.php
  // syslog( LOG_INFO, "Finding class {$classname}");
  $name_components = camelcase_to_array($classname);
  $target_filename = join('.', array_reverse(array_filter($name_components))) . '.php';
  $target_filename = preg_replace('/^' . getcwd . '/', '', $target_filename);
  if ( file_exists( "./system/lib/{$target_filename}" ) ) {
    $target_filename = "./system/lib/{$target_filename}";
  } else {
    if ( 1 == preg_match('/(.*)Database(.*)/', $classname) ) {
      $target_filename = "./system/lib/database/{$target_filename}";
      $base = 'implements DatabasePlugin';
      $classdef = <<<EOH
class {$classname} {$base} {
  
  function __construct() {
    parent::__construct();
  }

}

EOH;
    } else if ( 1 == preg_match('/(.*)Utility$/i', $classname) && !file_exists("./system/lib/sitehandlers/{$target_filename}") ) {
      $target_filename = "./system/lib/{$target_filename}";
      $base = 'extends RawparseUtility';
      $classdef = <<<EOH
class {$classname} {$base} {
  
  function __construct() {
    parent::__construct();
  }

}

EOH;
    } else if ( 1 == preg_match('/(.*)Model$/i', $classname) ) {
      $target_filename = "./system/lib/models/{$target_filename}";
      $base = 'extends DatabaseUtility';
      $classdef = <<<EOH
class {$classname} {$base} {
  
  function __construct() {
    parent::__construct();
  }

}

EOH;
    } else {
      $target_filename = "./system/lib/sitehandlers/{$target_filename}";
      $base = 'extends LegiscopeBase';
      $classdef = <<<EOH
class {$classname} {$base} {
  
  function __construct() {
    \$this->syslog( __FUNCTION__, 'FORCE', 'Using site-specific container class' );
    parent::__construct();
  }

  function seek() {
    \$json_reply = parent::seek();
    \$response = json_encode(\$json_reply);
    header('Content-Type: application/json');
    header('Content-Length: ' . strlen(\$response));
    \$this->flush_output_buffer();
    if ( C('ENABLE_GENERATED_CONTENT_BUFFERING') ) {
      file_put_contents(\$this->seek_cache_filename, \$response);
    }
    echo \$response;
    exit(0);
  }


}

EOH;
    }
    if (!('LegiscopeBase' == $classname))
    if ( !file_exists($target_filename) ) {
      $class_skeleton = <<<EOH
<?php

/*
 * Class {$classname}
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

{$classdef}

EOH;
      file_put_contents($target_filename, $class_skeleton);
    }
  }
  // syslog( LOG_INFO, "- Try to load {$target_filename} for class {$classname} " . ini_get('include_path') . " cwd " . getcwd() );
  require_once($target_filename);
  // if ( class_exists($classname) ) syslog( LOG_INFO, "- Class {$classname} exists at tail of spl_autoload()" );
},TRUE);

// Instantiate Legiscope controller
$n = LegiscopeBase::instantiate_by_host();

$n->handle_image_request();
$n->handle_javascript_request();
$n->handle_stylesheet_request();
$n->handle_model_action();
