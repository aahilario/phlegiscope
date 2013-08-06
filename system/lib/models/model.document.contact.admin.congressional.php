<?php

/*
 * Class CongressionalAdminContactDocumentModel
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class CongressionalAdminContactDocumentModel extends DatabaseUtility {
  
	var $office_designation_vc128 = NULL;
  var $office_address_vc4096 = NULL;
	var $congress_tag_vc8 = NULL;
  var $create_time_utx = NULL;

  function __construct() {
    parent::__construct();
  }

  function & set_office_address($v) {/*{{{*/
    $this->office_address_vc4096 = is_array($v) ? json_encode($v) : $v;
    return $this;
  }/*}}}*/
  function get_office_address($v = NULL) { /*{{{*/
    if (!is_null($v)) $this->set_office_address($v);
    return is_string($this->office_address_vc4096) ? json_decode($this->office_address_vc4096,TRUE) : $this->office_address_vc4096;
  }/*}}}*/

  function & set_congress_tag($v) { $this->congress_tag_vc8 = $v; return $this; }
  function get_congress_tag($v = NULL) { if (!is_null($v)) $this->set_congress_tag($v); return $this->congress_tag_vc8; }

  function & set_create_time($v) { $this->create_time_utx = $v; return $this; }
  function get_create_time($v = NULL) { if (!is_null($v)) $this->set_create_time($v); return $this->create_time_utx; }

}

