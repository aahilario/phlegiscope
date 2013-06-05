<?php

/*
 * Class CongressionalCommitteeUrlJoin
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class CongressionalCommitteeUrlJoin extends DatabaseUtility {
  
  // Join table model
  var $congressional_committee_CongressionalCommitteeDocumentModel = NULL;
  var $url_UrlModel = NULL;

	var $url_type_vc32 = NULL;

  function __construct() {
    parent::__construct();
    // $this->dump_accessor_defs_to_syslog();
    // $this->recursive_dump($this->get_attrdefs(),'(marker) "+++++++"');
  }

  function & set_congressional_committee($v) { $this->congressional_committee_CongressionalCommitteeDocumentModel = $v; return $this; }
  function get_congressional_committee($v = NULL) { if (!is_null($v)) $this->set_congressional_committee($v); return $this->congressional_committee_CongressionalCommitteeDocumentModel; }

  function & set_url($v) { $this->url_UrlModel = $v; return $this; }
  function get_url($v = NULL) { if (!is_null($v)) $this->set_url($v); return $this->url_UrlModel; }

}

