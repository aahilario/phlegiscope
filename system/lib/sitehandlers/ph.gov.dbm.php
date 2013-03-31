<?php

/*
 * Class DbmGovPh
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class DbmGovPh extends LegiscopeBase {
  
  function __construct() {
    $this->syslog( __FUNCTION__, '-', 'Using site-specific container class' );
    parent::__construct();
  }

	function seek() {
		$json_reply = parent::seek();
		$response = json_encode($json_reply);
		header('Content-Type: application/json');
		header('Content-Length: ' . strlen($response));
		echo $response;
		exit(0);
	}

}
