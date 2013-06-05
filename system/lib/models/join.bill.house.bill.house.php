<?php

/*
 * Class HouseBillHouseBillJoin
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class HouseBillHouseBillJoin extends ModelJoin {
  
  // Join table model
  var $left_house_bill_HouseBillDocumentModel;
  var $right_house_bill_HouseBillDocumentModel;

  function __construct() {
    parent::__construct();
    $this->dump_accessor_defs_to_syslog();
    $this->recursive_dump($this->get_attrdefs(),'(marker) "+++++++"');
  }

  function & set_left_house_bill($v) { $this->left_house_bill_HouseBillDocumentModel = $v; return $this; }
  function get_left_house_bill($v = NULL) { if (!is_null($v)) $this->set_left_house_bill($v); return $this->left_house_bill_HouseBillDocumentModel; }

  function & set_right_house_bill($v) { $this->right_house_bill_HouseBillDocumentModel = $v; return $this; }
  function get_right_house_bill($v = NULL) { if (!is_null($v)) $this->set_right_house_bill($v); return $this->right_house_bill_HouseBillDocumentModel; }

}

