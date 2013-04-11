<?php

/*
 * Class HostModel
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class HostModel extends UrlModel {
  
  // Model fields are used to generate typed fields. See DatabaseUtility::fetch_typemap()
  // BEGIN ModelFields
  var $hostname_vc512 = NULL;
  var $hostname_hash_vc128uniq = NULL;
  var $hits_int11 = NULL;
  // END ModelFields

  function __construct($url_or_hostname = NULL) {
    parent::__construct();
    $this->hostname_vc512 = NULL;
    $this->hostname_hash_vc128uniq = NULL;
    if ( !is_null($url_or_hostname) ) {
      $this->hostname_vc512 = UrlModel::parse_url($url_or_hostname, PHP_URL_HOST); 
      $this->hostname_hash_vc128uniq = UrlModel::get_url_hash($this->hostname_vc512);
      $this->fetch($this->hostname_hash_vc128uniq, 'hostname_hash');
      $state = $this->in_database() ? "Fetched {$this->id}" : "Recording";
      $this->syslog( __FUNCTION__, 'FORCE', "(marker) {$state} URL {$this->hostname_vc512} [{$this->hostname_hash_vc128uniq}]" ); 
    }
  }

  function increment_hits() {
    $this->hits_int11 = intval($this->hits_int11) + 1;
    return $this;
  }

}
