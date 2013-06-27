<?php

/*
 * Class SenateHousebillSenateHousebillJoin
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenateHousebillSenateHousebillJoin extends DatabaseUtility {
  
  // Join table model
  var $left_senate_housebill_SenateHousebillDocumentModel;
  var $right_senate_housebill_SenateHousebillDocumentModel;

  function __construct() {
    parent::__construct();
    // $this->dump_accessor_defs_to_syslog();
    // $this->recursive_dump($this->get_attrdefs(),'(marker) "+++++++"');
  }

  function & set_left_senate_housebill($v) { $this->left_senate_housebill_SenateHousebillDocumentModel = $v; return $this; }
  function get_left_senate_housebill($v = NULL) { if (!is_null($v)) $this->set_left_senate_housebill($v); return $this->left_senate_housebill_SenateHousebillDocumentModel; }

  function & set_right_senate_housebill($v) { $this->right_senate_housebill_SenateHousebillDocumentModel = $v; return $this; }
  function get_right_senate_housebill($v = NULL) { if (!is_null($v)) $this->set_right_senate_housebill($v); return $this->right_senate_housebill_SenateHousebillDocumentModel; }

}

