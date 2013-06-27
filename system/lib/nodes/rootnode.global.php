<?php

/*
 * Class GlobalRootnode 
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class GlobalRootnode extends LegiscopeBase {
  
  var $debug_memory_usage_delta = FALSE;
  var $update_existing = FALSE;

  function __construct($request_uri) {
    parent::__construct();
    $this->register_derived_class();
  }

}

