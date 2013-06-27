<?php

/*
 * Class UnimplementedRootnode
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class UnimplementedRootnode extends GlobalRootnode {
  
  function __construct($request_uri) {
    $this->syslog( __FUNCTION__, __LINE__, '(marker) Root node.' );
    parent::__construct($request_uri);
		$this->register_derived_class();
  }

}

