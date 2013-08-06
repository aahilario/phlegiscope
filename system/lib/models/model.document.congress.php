<?php

/*
 * Class CongressDocumentModel
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class CongressDocumentModel extends DatabaseUtility {
  
  var $congress_tag_vc8uniq = NULL;
  var $create_date_utx = NULL;
  var $adjourn_date_dtm = NULL;
  var $convene_date_dtm = NULL;

  function __construct() {
    parent::__construct();
    $this->dump_accessor_defs_to_syslog();
    //$this->recursive_dump($this->get_attrdefs(),'(marker) "+++++++"');
  }

  function & set_congress_tag($v) { $this->congress_tag_vc8uniq = $v; return $this; }
  function get_congress_tag($v = NULL) { if (!is_null($v)) $this->set_congress_tag($v); return $this->congress_tag_vc8uniq; }

  function & set_create_date($v) { $this->create_date_utx = $v; return $this; }
  function get_create_date($v = NULL) { if (!is_null($v)) $this->set_create_date($v); return $this->create_date_utx; }

  function & set_adjourn_date($v) { $this->adjourn_date_dtm = $v; return $this; }
  function get_adjourn_date($v = NULL) { if (!is_null($v)) $this->set_adjourn_date($v); return $this->adjourn_date_dtm; }

  function & set_convene_date($v) { $this->convene_date_dtm = $v; return $this; }
  function get_convene_date($v = NULL) { if (!is_null($v)) $this->set_convene_date($v); return $this->convene_date_dtm; }

}

