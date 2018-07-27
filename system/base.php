<?php

/*
 * system/base.php
 * Legiscope crawler
 */

// Enable logging
openlog( basename(__FILE__), /*LOG_PID |*/ LOG_NDELAY, LOG_LOCAL1 ); 
// Setup __autoload paths

ini_set('include_path', join(':', array_filter(array_merge(explode(':', ini_get('include_path') . ':' . SYSTEM_BASE . ':' . SYSTEM_BASE . '/lib' ))))); 

function array_element($a, $v, $defaultval = NULL) {
  return (is_array($a) && array_key_exists($v, $a)) ? $a[$v] : $defaultval;
}

function nonempty_array_element($a, $v, $defaultval = NULL) {
  return (is_array($a) && array_key_exists($v, $a) && !empty($a[$v])) ? $a[$v] : $defaultval;
}

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
  // syslog( LOG_INFO, "----- ---- --- -- - --------------- Finding class {$classname}");

  $debug_method = FALSE;

  openlog( basename(__FILE__), /*LOG_PID |*/ LOG_NDELAY, LOG_LOCAL1 );

  $skip_load       = FALSE;
  $name_components = camelcase_to_array($classname);
  $target_filename = join('.', array_reverse(array_filter($name_components))) . '.php';
  $target_filename = preg_replace('@/^' . getcwd() . '/@', '', $target_filename);
  if ( file_exists( SYSTEM_BASE . "/lib/{$target_filename}" ) ) {/*{{{*/
    $target_filename = SYSTEM_BASE . "/lib/{$target_filename}";
  }/*}}}*/
  else {/*{{{*/
    $matches = array();
    if ( 1 == preg_match('/((.*)Rootnode(.*))/', $classname, $matches) ) {/*{{{*/
      $target_filename = SYSTEM_BASE . "/lib/nodes/{$target_filename}";
      $base = 'extends GlobalRootnode';
      $prefix = strtolower(array_element($matches,2,'action'));
      $classdef = <<<EOH
class {$classname} {$base} {
  
  function __construct(\$request_uri) {
    \$this->syslog( __FUNCTION__, __LINE__, '(marker) Root node.' );
    parent::__construct(\$request_uri);
    \$this->register_derived_class();
  }

}

EOH;
    }/*}}}*/
    else if ( 1 == preg_match('/((.*)Action(.*))/', $classname, $matches) ) {/*{{{*/
      $target_filename = SYSTEM_BASE . "/lib/action/{$target_filename}";
      $base = 'extends LegiscopeBase';
      $prefix = strtolower(array_element($matches,2,'action'));
      $classdef = <<<EOH
class {$classname} {$base} {
  
  function __construct() {
    \$this->syslog( __FUNCTION__, __LINE__, '(marker) Generic action handler.' );
    parent::__construct();
  }

  function {$prefix}() {
    \$cache_force = \$this->filter_post('cache');
    \$json_reply = array('std' => 'class'); 
    \$response = json_encode(\$json_reply);
    header('Content-Type: application/json');
    header('Content-Length: ' . strlen(\$response));
    \$this->flush_output_buffer();
    if ( C('ENABLE_GENERATED_CONTENT_BUFFERING') || (\$cache_force == 'true') ) {
      file_put_contents(\$this->seek_cache_filename, \$response);
    }
    echo \$response;
    exit(0);
  }

}

EOH;
    }/*}}}*/
    else if ( 1 == preg_match('/(.*)Database(.*)/', $classname) ) {/*{{{*/
      $target_filename = SYSTEM_BASE . "/lib/database/{$target_filename}";
      $base = 'implements DatabasePlugin';
      $classdef = <<<EOH
class {$classname} {$base} {
  
  function __construct() {
    parent::__construct();
  }

}

EOH;
    }/*}}}*/
     else if ( 1 == preg_match('/(.*)Utility$/i', $classname) && !file_exists(SYSTEM_BASE . "/lib/sitehandlers/{$target_filename}") ) {/*{{{*/
      $target_filename = SYSTEM_BASE . "/lib/{$target_filename}";
      $base = 'extends RawparseUtility';
      $classdef = <<<EOH
class {$classname} {$base} {
  
  function __construct() {
    parent::__construct();
  }

}

EOH;
    }/*}}}*/
    else if ( 1 == preg_match('@(.*)Join$@i', $classname) ) {/*{{{*/
      $components = array();
      $builtins = '';
      if ( $debug_method ) syslog( LOG_INFO, "- Generating {$classname}");
      // Note: If the file containing this class declaration does not
      // exist yet, then neither has the backing store (linking table)
      // been created for the object defined by this class.
      if ( $debug_method ) syslog( LOG_INFO, "---- Classname {$classname}" );

      if ( 1 == preg_match('@\_Plus\_@i', $classname) ) {/*{{{*/
        $components_real = explode('_Plus_',  preg_replace("@Join$@i","",$classname));
        $varnames        = $components_real;
        $components      = array_map(create_function('$a', 'return preg_replace("@(Document)*Model$@i","",$a);'), $components_real);
        $classname       = join('', $components) . 'Join';
        $name_components = camelcase_to_array($classname);
        $target_filename = join('.', array_reverse(array_filter($name_components))) . '.php';
        // Allow for possibility of self-referencing join (same names in components[0], components[1])
        array_walk($components,create_function('& $a, $k', '$a = join("_", camelcase_to_array($a));'));
        if ( $components[0] == $components[1] ) {
          $components[0] = "left_{$components[0]}";
          $components[1] = "right_{$components[1]}";
        }
        $builtins = <<<EOH
  function & set_{$components[0]}(\$v) { \$this->{$components[0]}_{$varnames[0]} = \$v; return \$this; }
  function get_{$components[0]}(\$v = NULL) { if (!is_null(\$v)) \$this->set_{$components[0]}(\$v); return \$this->{$components[0]}_{$varnames[0]}; }

  function & set_{$components[1]}(\$v) { \$this->{$components[1]}_{$varnames[1]} = \$v; return \$this; }
  function get_{$components[1]}(\$v = NULL) { if (!is_null(\$v)) \$this->set_{$components[1]}(\$v); return \$this->{$components[1]}_{$varnames[1]}; }
EOH;
        array_walk($components,create_function(
          '& $a, $k, $s', '$a = "  var \${$a}_{$s[$k]};";'),
          $varnames
        );
      }/*}}}*/
      $components = join("\n", $components);
      $target_filename = SYSTEM_BASE . "/lib/models/{$target_filename}";

      if ( $debug_method ) {
        syslog( LOG_INFO, "---- Target filename: {$target_filename}" );
        syslog( LOG_INFO, "---- Final classname: {$classname}" );
      }

      $base = 'extends DatabaseUtility';
      $classdef = <<<EOH
class {$classname} {$base} {
  
  // Join table model
{$components}

  function __construct() {
    parent::__construct();
    if (C('DEBUG_'.get_class(\$this))) {
      \$this->dump_accessor_defs_to_syslog();
      \$this->recursive_dump(\$this->get_attrdefs(),'(marker) "+++++++"');
    }
  }

{$builtins}

}

EOH;
    }/*}}}*/
    else if ( 1 == preg_match('/(.*)Model$/i', $classname) ) {/*{{{*/
      $target_filename = SYSTEM_BASE . "/lib/models/{$target_filename}";
      $base = 'extends DatabaseUtility';
      $classdef = <<<EOH
class {$classname} {$base} {
  
  function __construct() {
    parent::__construct();
  }

}

EOH;
    }/*}}}*/
     else if (!(1 == preg_match('@^pie.simple@i',$target_filename))) {/*{{{*/
      $target_filename = SYSTEM_BASE . "/lib/sitehandlers/{$target_filename}";
      $base = 'extends LegiscopeBase';
      $classdef = <<<EOH
class {$classname} {$base} {
  
  function __construct() {
    \$this->syslog( __FUNCTION__, __LINE__, '(marker) Using site-specific container class' );
    parent::__construct();
  }

  function seek() {
    \$cache_force = \$this->filter_post('cache');
    \$json_reply = parent::seek();
    \$response = json_encode(\$json_reply);
    header('Content-Type: application/json');
    header('Content-Length: ' . strlen(\$response));
    \$this->flush_output_buffer();
    if ( C('ENABLE_GENERATED_CONTENT_BUFFERING') || (\$cache_force == 'true') ) {
      file_put_contents(\$this->seek_cache_filename, \$response);
    }
    echo \$response;
    exit(0);
  }


}

EOH;
    }/*}}}*/
    else {
      $skip_load = TRUE;
    }

    if (!$skip_load && !('LegiscopeBase' == $classname) && !C('DISABLE_CLASS_AUTOGENERATE') ) {
      if ( ( FALSE == stripos($target_filename,'_') ) && !file_exists($target_filename) ) {/*{{{*/
        $generate_date = date(DATE_RFC2822);
        $class_skeleton = <<<EOH
<?php

/*
 * Class {$classname}
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 * Generated on {$generate_date}
 */

{$classdef}

EOH;
        file_put_contents($target_filename, $class_skeleton);
      }/*}}}*/
    }
  }/*}}}*/

  if ( file_exists($target_filename) ) {
    if ( $debug_method ) syslog( LOG_INFO, "- Try to load {$target_filename} for class {$classname} " . ini_get('include_path') . " cwd " . getcwd() );
    require_once($target_filename);
  }
  else {
    if ( $debug_method ) syslog( LOG_INFO, "- No file {$target_filename} for class {$classname} in " . ini_get('include_path') . " cwd " . getcwd() );
  }
  // if ( class_exists($classname) ) syslog( LOG_INFO, "- Class {$classname} exists at tail of spl_autoload()" );
},TRUE);


