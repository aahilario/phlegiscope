<?php

/*
 * Class SimplePie
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SimplePie extends LegiscopeBase {
  
  function __construct() {
    $this->syslog( __FUNCTION__, __LINE__, '(marker) Using site-specific container class' );
    parent::__construct();
  }

  function seek() {
    $cache_force = $this->filter_post('cache');
    $json_reply = parent::seek();
    $response = json_encode($json_reply);
    header('Content-Type: application/json');
    header('Content-Length: ' . strlen($response));
    $this->flush_output_buffer();
    if ( C('ENABLE_GENERATED_CONTENT_BUFFERING') || ($cache_force == 'true') ) {
      file_put_contents($this->seek_cache_filename, $response);
    }
    echo $response;
    exit(0);
  }


}

