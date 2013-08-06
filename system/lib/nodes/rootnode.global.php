<?php

/*
 * Class GlobalRootnode 
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class GlobalRootnode extends SeekAction {
  
  var $debug_memory_usage_delta = FALSE;
  var $update_existing = FALSE;
	var $node_uri = NULL;

  function __construct($request_uri) {
    parent::__construct();
    $this->register_derived_class();
		$this->node_uri = is_array($request_uri) ? $request_uri : explode('/', $request_uri);
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Node URI '".join('/',$this->node_uri)."'" );
  }

	function evaluate() {
		// Evaluate the path URI assigned in constructor to $this->node_uri
		// 1. Find a method name matching the leading component
    $method = nonempty_array_element($this->node_uri,0,'index');
    $this->syslog(__FUNCTION__,__LINE__,"(marker) --- {$method}");
		if ( method_exists($this, $method) ) {
      // Remove zeroth element
			array_shift($this->node_uri);
			return $this->$method($this->node_uri);
		}
	}

}

