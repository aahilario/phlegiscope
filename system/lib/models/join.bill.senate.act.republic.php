<?php

/*
 * Class RepublicActSenateBillJoin
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class RepublicActSenateBillJoin extends DatabaseUtility {
  
  // Join table model
  var $republic_act_RepublicActDocumentModel;
  var $senate_bill_SenateBillDocumentModel;

  var $update_time_utx = NULL;
  var $create_time_utx = NULL;

  function __construct() {
    parent::__construct();
    //$this->dump_accessor_defs_to_syslog();
    //$this->recursive_dump($this->get_attrdefs(),'(marker) "+++++++"');
  }

  function & set_republic_act($v) { $this->republic_act_RepublicActDocumentModel = $v; return $this; }
  function get_republic_act($v = NULL) { if (!is_null($v)) $this->set_republic_act($v); return $this->republic_act_RepublicActDocumentModel; }

  function & set_senate_bill($v) { $this->senate_bill_SenateBillDocumentModel = $v; return $this; }
  function get_senate_bill($v = NULL) { if (!is_null($v)) $this->set_senate_bill($v); return $this->senate_bill_SenateBillDocumentModel; }

  function & set_update_time($v) { $this->update_time_utx = $v; return $this; }
  function get_update_time($v = NULL) { if (!is_null($v)) $this->set_update_time($v); return $this->update_time_utx; }

  function & set_create_time($v) { $this->create_time_utx = $v; return $this; }
  function get_create_time($v = NULL) { if (!is_null($v)) $this->set_create_time($v); return $this->create_time_utx; }


}

