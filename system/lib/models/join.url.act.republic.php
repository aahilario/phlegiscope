<?php

/*
 * Class RepublicActUrlJoin
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class RepublicActUrlJoin extends ModelJoin {
  
  // Join table model
  var $republic_act_RepublicActDocumentModel;
  var $url_UrlModel;

  function __construct() {
    parent::__construct();
    //$this->dump_accessor_defs_to_syslog();
    //$this->recursive_dump($this->get_attrdefs(),'(marker) "+++++++"');
  }

  function & set_republic_act($v) { $this->republic_act_RepublicActDocumentModel = $v; return $this; }
  function get_republic_act($v = NULL) { if (!is_null($v)) $this->set_republic_act($v); return $this->republic_act_RepublicActDocumentModel; }

  function & set_url($v) { $this->url_UrlModel = $v; return $this; }
  function get_url($v = NULL) { if (!is_null($v)) $this->set_url($v); return $this->url_UrlModel; }

}

