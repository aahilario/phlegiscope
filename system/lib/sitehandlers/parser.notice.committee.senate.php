<?php

/*
 * Class SenateCommitteeNoticeParser
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenateCommitteeNoticeParser extends LegiscopeBase {
  
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
    if ( C('ENABLE_GENERATED_CONTENT_BUFFERING') ) {
      file_put_contents($this->seek_cache_filename, $response);
    }
    echo $response;
    exit(0);
  }


}

