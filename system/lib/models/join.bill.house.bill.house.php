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

  var $content_blob = NULL;
  var $content_type_vc64 = NULL;
  var $update_time_utx = NULL;
  var $create_time_utx = NULL;
  var $revision_int11 = NULL;
  var $jointype_vc16 = NULL;

  function __construct() {
    parent::__construct();
    //$this->dump_accessor_defs_to_syslog();
    //$this->recursive_dump($this->get_attrdefs(),'(marker) "+++++++"');
  }

  function & set_left_house_bill($v) { $this->left_house_bill_HouseBillDocumentModel = $v; return $this; }
  function get_left_house_bill($v = NULL) { if (!is_null($v)) $this->set_left_house_bill($v); return $this->left_house_bill_HouseBillDocumentModel; }

  function & set_right_house_bill($v) { $this->right_house_bill_HouseBillDocumentModel = $v; return $this; }
  function get_right_house_bill($v = NULL) { if (!is_null($v)) $this->set_right_house_bill($v); return $this->right_house_bill_HouseBillDocumentModel; }

  function & set_jointype($v) { $this->jointype_vc16 = $v; return $this; }
  function get_jointype($v = NULL) { if (!is_null($v)) $this->set_jointype($v); return $this->jointype_vc16; }

  function & set_revision($v) { $this->revision_int11 = $v; return $this; }
  function get_revision($v = NULL) { if (!is_null($v)) $this->set_revision($v); return $this->revision_int11; }

  function & set_update_time($v) { $this->update_time_utx = $v; return $this; }
  function get_update_time($v = NULL) { if (!is_null($v)) $this->set_update_time($v); return $this->update_time_utx; }

  function & set_create_time($v) { $this->create_time_utx = $v; return $this; }
  function get_create_time($v = NULL) { if (!is_null($v)) $this->set_create_time($v); return $this->create_time_utx; }

  function & set_content_type($v) { $this->content_type_vc64 = $v; return $this; }
  function get_content_type($v = NULL) { if (!is_null($v)) $this->set_content_type($v); return $this->content_type_vc64; }

  function & set_content($v) { $this->content_blob = $v; return $this; }
  function get_content($v = NULL) { if (!is_null($v)) $this->set_content($v); return $this->content_blob; }
}

