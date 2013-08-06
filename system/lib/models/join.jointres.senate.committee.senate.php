<?php

/*
 * Class SenateCommitteeSenateJointresJoin
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenateCommitteeSenateJointresJoin extends ModelJoin {
  
  // Join table model
  var $senate_committee_SenateCommitteeModel;
  var $senate_jointres_SenateJointresDocumentModel;

	var $referral_mode_vc16 = NULL;
	var $referral_date_dtm = NULL;

  function __construct() {
    parent::__construct();
    // $this->dump_accessor_defs_to_syslog();
    // $this->recursive_dump($this->get_attrdefs(),'(marker) "+++++++"');
  }

  function & set_senate_committee($v) { $this->senate_committee_SenateCommitteeModel = $v; return $this; }
  function get_senate_committee($v = NULL) { if (!is_null($v)) $this->set_senate_committee($v); return $this->senate_committee_SenateCommitteeModel; }

  function & set_senate_jointres($v) { $this->senate_jointres_SenateJointresDocumentModel = $v; return $this; }
  function get_senate_jointres($v = NULL) { if (!is_null($v)) $this->set_senate_jointres($v); return $this->senate_jointres_SenateJointresDocumentModel; }

	function & set_referral_mode($v) { $this->referral_mode_vc16 = $v; return $this; }
	function get_referral_mode($v = NULL) { if (!is_null($v)) $this->set_referral_mode($v); return $this->referral_mode_vc16; }

}

