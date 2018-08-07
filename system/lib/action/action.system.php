<?php

/*
 * Class SystemAction
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 * Generated on Tue, 07 Aug 2018 16:13:06 +0000
 */

class SystemAction extends LegiscopeBase {
  
  function __construct() {
    parent::__construct();
  }

  function systemrootlinks( $method )
  {
    if ( !($method == "GET") )
      $this->raw_json_reply([]);

    $hostmodel = new HostModel();

    $hostmodel
      ->query('SELECT hostname_hash, hostname FROM host_model WHERE hits IS NOT NULL ORDER BY hits DESC')
      ->recordfetch_setup();

    $record = [];
    $json = [ 'links' => [] ];

    while ( $hostmodel->recordfetch($record) ) {
        $hostmodel->syslog( __FUNCTION__,__LINE__,"(marker) -- {$record['hostname']}");
        $json['links'][] = [ 'host' => $record['hostname'], 'hash' => $record['hostname_hash'] ];
    }

    $this->raw_json_reply( $json );
  }

  function system()
  {

    $method   = $this->filter_server('REQUEST_METHOD');
    $fragment = $this->filter_request('fragment',NULL,255,'/[^a-z]/i');

    $this
      ->syslog( __FUNCTION__,__LINE__,"(marker) Handle Legiscope panel {$method} request for {$fragment}")
      ->recursive_dump($_REQUEST, "(marker) ---")
      ->empty_unauthed_json_reply( __FUNCTION__, __LINE__ );

    $callable_method = NULL;
    if ( method_exists( $this, $fragment ) && is_callable( [ $this, $fragment ], FALSE, $callable_method ) )
      call_user_func( [ $this, $fragment ], $method );

    $cache_force = $this->filter_post('cache');
    $json_reply  = array('std' => 'class');
    $response    = json_encode($json_reply);
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

