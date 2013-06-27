<?php

/*
 * Class SenateCommitteeSenateResolutionJoin
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenateCommitteeSenateResolutionJoin extends DatabaseUtility {
  
  // Join table model
  var $senate_committee_SenateCommitteeModel;
  var $senate_resolution_SenateResolutionDocumentModel;

	var $referral_mode_vc16 = NULL;
	var $referral_date_dtm = NULL;

  function __construct() {
    parent::__construct();
    // $this->dump_accessor_defs_to_syslog();
    // $this->recursive_dump($this->get_attrdefs(),'(marker) "+++++++"');
  }

  function & set_senate_committee($v) { $this->senate_committee_SenateCommitteeModel = $v; return $this; }
  function get_senate_committee($v = NULL) { if (!is_null($v)) $this->set_senate_committee($v); return $this->senate_committee_SenateCommitteeModel; }

  function & set_senate_resolution($v) { $this->senate_resolution_SenateResolutionDocumentModel = $v; return $this; }
  function get_senate_resolution($v = NULL) { if (!is_null($v)) $this->set_senate_resolution($v); return $this->senate_resolution_SenateResolutionDocumentModel; }

	function & set_referral_mode($v) { $this->referral_mode_vc16 = $v; return $this; }
	function get_referral_mode($v = NULL) { if (!is_null($v)) $this->set_referral_mode($v); return $this->referral_mode_vc16; }


}

