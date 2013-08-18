<?php

/*
 * Class CongressionalCommitteeRepresentativeDossierJoin
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class CongressionalCommitteeRepresentativeDossierJoin extends ModelJoin {
  
  // Join table model
  var $congressional_committee_CongressionalCommitteeDocumentModel;
  var $representative_dossier_RepresentativeDossierModel;
	var $create_time_utx = NULL;
  var $congress_tag_vc8 = NULL;
  var $role_vc32 = NULL;

  function __construct() {
    parent::__construct();
    // $this->dump_accessor_defs_to_syslog();
    // $this->recursive_dump($this->get_attrdefs(),'(marker) "+++++++"');
  }

  function & set_congressional_committee($v) { $this->congressional_committee_CongressionalCommitteeDocumentModel = $v; return $this; }
  function get_congressional_committee($v = NULL) { if (!is_null($v)) $this->set_congressional_committee($v); return $this->congressional_committee_CongressionalCommitteeDocumentModel; }

  function & set_representative_dossier($v) { $this->representative_dossier_RepresentativeDossierModel = $v; return $this; }
  function get_representative_dossier($v = NULL) { if (!is_null($v)) $this->set_representative_dossier($v); return $this->representative_dossier_RepresentativeDossierModel; }

  function & set_role($v) { $this->role_vc32 = $v; return $this; }
  function get_role($v = NULL) { if (!is_null($v)) $this->set_role($v); return $this->role_vc32; }

  function & set_congress_tag($v) { $this->congress_tag_vc8 = $v; return $this; }
  function get_congress_tag($v = NULL) { if (!is_null($v)) $this->set_congress_tag($v); return $this->congress_tag_vc8; }

  function & set_create_time($v) { $this->create_time_utx = $v; return $this; }
  function get_create_time($v = NULL) { if (!is_null($v)) $this->set_create_time($v); return $this->create_time_utx; }
}

