<?php

/*
 * Class SenateBillSenateBillJoin
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenateBillSenateBillJoin extends DatabaseUtility {
  
  // Join table model
  var $left_senate_bill_SenateBillDocumentModel;
  var $right_senate_bill_SenateBillDocumentModel;

  function __construct() {
    parent::__construct();
    // $this->dump_accessor_defs_to_syslog();
    // $this->recursive_dump($this->get_attrdefs(),'(marker) "+++++++"');
  }

  function & set_left_senate_bill($v) { $this->left_senate_bill_SenateBillDocumentModel = $v; return $this; }
  function get_left_senate_bill($v = NULL) { if (!is_null($v)) $this->set_left_senate_bill($v); return $this->left_senate_bill_SenateBillDocumentModel; }

  function & set_right_senate_bill($v) { $this->right_senate_bill_SenateBillDocumentModel = $v; return $this; }
  function get_right_senate_bill($v = NULL) { if (!is_null($v)) $this->set_right_senate_bill($v); return $this->right_senate_bill_SenateBillDocumentModel; }

}

