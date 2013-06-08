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

  function & set_hits($v) { $this->hits_int11 = $v; return $this; }
  function get_hits($v = NULL) { if (!is_null($v)) $this->set_hits($v); return $this->hits_int11; }

  function & set_hostname($v) { $this->hostname_vc512 = $v; return $this; }
  function get_hostname($v = NULL) { if (!is_null($v)) $this->set_hostname($v); return $this->hostname_vc512; }

  function & set_hostname_hash($v) { $this->hostname_hash_vc128uniq = $v; return $this; }
  function get_hostname_hash($v = NULL) { if (!is_null($v)) $this->set_hostname_hash($v); return $this->hostname_hash_vc128uniq; }
}
