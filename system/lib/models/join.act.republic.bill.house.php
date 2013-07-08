<?php

/*
 * Class HouseBillRepublicActJoin
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class HouseBillRepublicActJoin extends ModelJoin {
  
  // Join table model
  var $house_bill_HouseBillDocumentModel;
  var $republic_act_RepublicActDocumentModel;

  function __construct() {
    parent::__construct();
    //$this->dump_accessor_defs_to_syslog();
    //$this->recursive_dump($this->get_attrdefs(),'(marker) "+++++++"');
  }

  function & set_house_bill($v) { $this->house_bill_HouseBillDocumentModel = $v; return $this; }
  function get_house_bill($v = NULL) { if (!is_null($v)) $this->set_house_bill($v); return $this->house_bill_HouseBillDocumentModel; }

  function & set_republic_act($v) { $this->republic_act_RepublicActDocumentModel = $v; return $this; }
  function get_republic_act($v = NULL) { if (!is_null($v)) $this->set_republic_act($v); return $this->republic_act_RepublicActDocumentModel; }

}

