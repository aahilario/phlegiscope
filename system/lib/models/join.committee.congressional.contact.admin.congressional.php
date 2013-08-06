<?php

/*
 * Class CongressionalAdminContactCongressionalCommitteeJoin
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class CongressionalAdminContactCongressionalCommitteeJoin extends DatabaseUtility {
  
  // Join table model
  var $congressional_admin_contact_CongressionalAdminContactDocumentModel;
  var $congressional_committee_CongressionalCommitteeDocumentModel;
	var $congress_tag_vc8 = NULL; 
  var $create_time_utx = NULL;

  function __construct() {
    parent::__construct();
    // $this->dump_accessor_defs_to_syslog();
    // $this->recursive_dump($this->get_attrdefs(),'(marker) "+++++++"');
  }

  function & set_congressional_admin_contact($v) { $this->congressional_admin_contact_CongressionalAdminContactDocumentModel = $v; return $this; }
  function get_congressional_admin_contact($v = NULL) { if (!is_null($v)) $this->set_congressional_admin_contact($v); return $this->congressional_admin_contact_CongressionalAdminContactDocumentModel; }

  function & set_congressional_committee($v) { $this->congressional_committee_CongressionalCommitteeDocumentModel = $v; return $this; }
  function get_congressional_committee($v = NULL) { if (!is_null($v)) $this->set_congressional_committee($v); return $this->congressional_committee_CongressionalCommitteeDocumentModel; }

  function & set_congress_tag($v) { $this->congress_tag_vc8 = $v; return $this; }
  function get_congress_tag($v = NULL) { if (!is_null($v)) $this->set_congress_tag($v); return $this->congress_tag_vc8; }

  function & set_create_time($v) { $this->create_time_utx = $v; return $this; }
  function get_create_time($v = NULL) { if (!is_null($v)) $this->set_create_time($v); return $this->create_time_utx; }

}

