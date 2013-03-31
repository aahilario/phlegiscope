<?php

class DenrGovPh extends LegiscopeBase {
  
  function __construct() {
    $this->syslog( __FUNCTION__, 'FORCE', 'Using site-specific container class' );
    parent::__construct();
  }

  function seek() {
    $json_reply = parent::seek();
    $response = json_encode($json_reply);
    header('Content-Type: application/json');
    header('Content-Length: ' . strlen($response));
		$this->flush_output_buffer();
    echo $response;
    exit(0);
  }

}
