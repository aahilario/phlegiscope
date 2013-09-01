<?php

/*
 * Class SenateBillSenateBillJoin
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenateBillSenateBillJoin extends ModelJoin {

  // Join table model
  var $left_senate_bill_SenateBillDocumentModel;
  var $right_senate_bill_SenateBillDocumentModel;

  var $jointype_vc16 = NULL; // Edge payload type.  This may, for example, be 'reading-state' (floor reading)
  var $join_subtype_vc64 = NULL; // Qualifier for jointype, when that content needs to be searchable.  Use the sole MEDIUMBLOB attribute (_blob) to store content that need not be indexed.
  var $jointype_date_dtm = NULL; // Payload date (when applicable, taken from parsed data or recorded from user frontend data entry)
  var $create_time_utx = NULL; // Internal (system) time

  function __construct() {
    parent::__construct();
    // $this->dump_accessor_defs_to_syslog();
    // $this->recursive_dump($this->get_attrdefs(),'(marker) "+++++++"');
  }

  function & set_left_senate_bill($v) { $this->left_senate_bill_SenateBillDocumentModel = $v; return $this; }
  function get_left_senate_bill($v = NULL) { if (!is_null($v)) $this->set_left_senate_bill($v); return $this->left_senate_bill_SenateBillDocumentModel; }

  function & set_right_senate_bill($v) { $this->right_senate_bill_SenateBillDocumentModel = $v; return $this; }
  function get_right_senate_bill($v = NULL) { if (!is_null($v)) $this->set_right_senate_bill($v); return $this->right_senate_bill_SenateBillDocumentModel; }

  function & set_jointype($v) { $this->jointype_vc16 = $v; return $this; }
  function get_jointype($v = NULL) { if (!is_null($v)) $this->set_jointype($v); return $this->jointype_vc16; }

  function & set_join_subtype($v) { $this->join_subtype_vc64 = $v; return $this; }
  function get_join_subtype($v = NULL) { if (!is_null($v)) $this->set_join_subtype($v); return $this->join_subtype_vc64; }

  function & set_jointype_date($v) { $this->jointype_date_dtm = $v; return $this; }
  function get_jointype_date($v = NULL) { if (!is_null($v)) $this->set_jointype_date($v); return $this->jointype_date_dtm; }

  function & set_create_time($v) { $this->create_time_utx = $v; return $this; }
  function get_create_time($v = NULL) { if (!is_null($v)) $this->set_create_time($v); return $this->create_time_utx; }

}

